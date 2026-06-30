<?php
/*
    Gestor de configuración — Lee y escribe el archivo config.php
    preservando comentarios, formato y estructura original.
*/

class ConfigManager
{
    /*
        Lee config.php y devuelve un array indexado por clave con:
        - value:     valor PHP crudo (ej: "Jessica & Miguel", true, E_ALL)
        - rawLine:   línea completa original (para reemplazo quirúrgico)
        - lineNumber: número de línea (1-based)
        - type:      "string_double", "string_single", "boolean", "constant", "number"
        - comment:   comentario de bloque /* ... * / inmediatamente anterior, si existe
        - category:  nombre de categoría deducido del comentario de sección

        También devuelve una clave especial "_categories" con el orden de categorías.
    */
    public static function readConfig($filePath)
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $result = [];
        $categoryOrder = [];
        $currentCategory = null;
        $pendingComment = null;

        foreach ($lines as $i => $line) {
            $lineNumber = $i + 1;
            $trimmed = trim($line);

            // Detectar comentarios de sección: /* ... */
            if (preg_match('/^\/\*\s*(.+?)\s*\*\/$/', $trimmed, $m)) {
                $currentCategory = trim($m[1]);
                if (!in_array($currentCategory, $categoryOrder)) {
                    $categoryOrder[] = $currentCategory;
                }
                $pendingComment = null;
                continue;
            }

            // Detectar comentarios de línea (// o #) que preceden inmediatamente a un GLOBALS
            if (preg_match('/^\s*(\/\/|#)\s*(.+)/', $trimmed, $m)) {
                $pendingComment = trim($m[2]);
                continue;
            }

            // Detectar comentarios de bloque sueltos (que no son sección)
            if (preg_match('/^\/\*\s*(.+?)\s*\*\/$/', $trimmed, $m)) {
                $pendingComment = trim($m[1]);
                continue;
            }

            // Saltar líneas vacías u otras líneas no GLOBALS
            if ($trimmed === '' || $trimmed === '<?php') {
                continue;
            }

            // Detectar $GLOBALS['clave'] = valor;
            if (preg_match("/\\\$GLOBALS\s*\[\s*'([^']+)'\s*\]\s*=\s*(.+?)\s*;\s*$/", $trimmed, $m)) {
                $key = $m[1];
                $rawValue = $m[2];

                $parsed = self::parseValue($rawValue);

                $result[$key] = [
                    'value'       => $parsed['value'],
                    'displayValue'=> $parsed['displayValue'],
                    'rawLine'     => $line,
                    'lineNumber'  => $lineNumber,
                    'type'        => $parsed['type'],
                    'comment'     => $pendingComment,
                    'category'    => $currentCategory ?? 'General',
                    'rawValue'    => $rawValue,
                ];

                if (!in_array($currentCategory ?? 'General', $categoryOrder)) {
                    $categoryOrder[] = $currentCategory ?? 'General';
                }

                $pendingComment = null;
            }
        }

        $result['_categories'] = $categoryOrder;
        return $result;
    }

    /*
        Parsea el valor crudo de un $GLOBALS y devuelve:
        - value:        valor nativo PHP
        - displayValue: representación para el formulario HTML
        - type:         tipo detectado
    */
    private static function parseValue($rawValue)
    {
        $rawValue = trim($rawValue);

        // Booleano: true / false
        if (strtolower($rawValue) === 'true') {
            return ['value' => true, 'displayValue' => 'true', 'type' => 'boolean'];
        }
        if (strtolower($rawValue) === 'false') {
            return ['value' => false, 'displayValue' => 'false', 'type' => 'boolean'];
        }

        // Entero (solo dígitos)
        if (preg_match('/^\d+$/', $rawValue)) {
            return ['value' => (int)$rawValue, 'displayValue' => $rawValue, 'type' => 'number'];
        }

        // String con comillas dobles
        if (preg_match('/^"(.*)"$/', $rawValue, $m)) {
            return ['value' => $m[1], 'displayValue' => $m[1], 'type' => 'string_double'];
        }

        // String con comillas simples
        if (preg_match("/^'(.*)'$/", $rawValue, $m)) {
            return ['value' => $m[1], 'displayValue' => $m[1], 'type' => 'string_single'];
        }

        // Asumimos que es una constante PHP (E_ALL, etc.)
        return ['value' => $rawValue, 'displayValue' => $rawValue, 'type' => 'constant'];
    }

    /*
        Convierte un valor de formulario al formato de escritura para config.php.
        $type: el tipo detectado al leer.
        $newDisplayValue: el valor tal cual viene del formulario.
    */
    private static function formatValue($newDisplayValue, $type)
    {
        switch ($type) {
            case 'boolean':
                $v = strtolower(trim($newDisplayValue));
                return ($v === 'true' || $v === '1') ? 'true' : 'false';

            case 'number':
                return (string)intval($newDisplayValue);

            case 'string_double':
                // Mantenemos comillas dobles, escapamos comillas dobles internas
                return '"' . str_replace('"', '\"', $newDisplayValue) . '"';

            case 'string_single':
                // Mantenemos comillas simples, escapamos comillas simples internas y backslashes
                return "'" . str_replace("'", "\\'", str_replace("\\", "\\\\", $newDisplayValue)) . "'";

            case 'constant':
                // Para constantes, usamos el valor tal cual (ya viene del select)
                return $newDisplayValue;

            default:
                return '"' . str_replace('"', '\"', $newDisplayValue) . '"';
        }
    }

    /*
        Escribe los nuevos valores en config.php.
        $newValues: array asociativo [clave => valor_del_formulario]
        $configData: array devuelto por readConfig() para conocer los tipos.
    */
    public static function writeConfig($filePath, $newValues, $configData)
    {
        if (!file_exists($filePath)) {
            return false;
        }

        if (!is_writable($filePath)) {
            return false;
        }

        $lines = file($filePath);
        if ($lines === false) {
            return false;
        }

        foreach ($configData as $key => $info) {
            if ($key === '_categories') continue;
            if (!array_key_exists($key, $newValues)) continue;

            $lineIndex = $info['lineNumber'] - 1;
            $oldLine = $info['rawLine'];
            $newDisplayValue = $newValues[$key];
            $newRawValue = self::formatValue($newDisplayValue, $info['type']);

            // Reemplazar el valor en la línea
            // Buscamos el patrón: $GLOBALS['clave'] = VIEJO_VALOR;
            $pattern = "/(\\\$GLOBALS\s*\[\s*'" . preg_quote($key, '/') . "'\s*\]\s*=\s*)"
                      . preg_quote($info['rawValue'], '/')
                      . "(\s*;\s*)$/";

            $newLine = preg_replace($pattern, '$1' . $newRawValue . '$2', $oldLine);

            if ($newLine !== null && $newLine !== $oldLine) {
                $lines[$lineIndex] = $newLine;
            }
        }

        $content = implode('', $lines);
        $result = file_put_contents($filePath, $content);

        return $result !== false;
    }

    /*
        Crea una copia de seguridad de config.php.
        Jerarquía: config.php -> config.php.backup -> config.php.backup.old
    */
    public static function backup($filePath)
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $backupPath = $filePath . '.backup';
        $oldBackupPath = $filePath . '.backup.old';

        // Si ya existe un backup, lo movemos a .old
        if (file_exists($backupPath)) {
            copy($backupPath, $oldBackupPath);
        }

        return copy($filePath, $backupPath);
    }

    /*
        Obtiene los niveles de error de PHP disponibles para el selector.
    */
    public static function getErrorLevels()
    {
        return [
            'E_ALL'               => 'E_ALL — Todos los errores',
            'E_ALL & ~E_NOTICE'   => 'E_ALL & ~E_NOTICE — Todos menos notices',
            'E_ALL & ~E_DEPRECATED' => 'E_ALL & ~E_DEPRECATED — Todos menos deprecated',
            'E_ERROR | E_WARNING | E_PARSE' => 'E_ERROR | E_WARNING | E_PARSE — Solo errores graves',
            '0'                   => '0 — No mostrar errores',
        ];
    }
}
