<?php
/*
 *    Este fichero contiene el código relacionado con la clase Model.
 */

#[AllowDynamicProperties]
class Model
{
    private $inBDFlag = false; //Flag que indica si este modelo proviene de la base de datos
    private $columns; //Nombre de las columnas del modelo
    protected $table = ""; //Nombre de la tabla del modelo

    private $autoincrementid;

    //Constructor
    public function __construct($columns, $autoincrementid = true)
    {
        //Añadimos la columna id
        array_unshift($columns, "id");
        $this->autoincrementid = $autoincrementid;

        //Para cada par clave/tipo del array
        foreach($columns as $k)
        {
            //Creamos un nuevo miembro llamado como la clave
            $this->$k = '';
        }

        //Guardamos la lista de columnas
        $this->columns = $columns;

        //Guardamos el nombre de la tabla (solo autogenerar si la subclase no lo ha definido)
        $this->table = strtolower(get_called_class())."s";
    }

    public function getClassName()
    {
        return strtolower(get_called_class());
    }

    //Función que inserta o actualiza el modelo en la base de datos
    public function save()
    {
        $values = array();

        if($this->inBDFlag) //Si ya está en la base de datos
        {
            //Construímos la primera parte del update
            self::$query = "UPDATE ".$this->table." SET ";

            //Para cada columna generamos su actualización
            $primero = true;
            foreach($this->columns as $c)
            {
                if($c != "id")
                {
                    if($primero){$primero = false;}
                    else {self::$query = self::$query.", ";}

                    $s = BD::sanitize($this->$c);
                    self::$query = self::$query." ".$c." = ?";
                    array_push($values, $s);
                }
            }

            //Terminamos generando la parte del where
            self::$query = self::$query." WHERE id =".$this->id;
        }
        else //Si no está en la base de datos
        {
            //Construímos el inicio del insert
            self::$query = "INSERT INTO ".$this->table;


            //Generamos las partes de qué columnas y qué valores
            $primero = true;
            $value_part = "";
            foreach($this->columns as $c)
            {
                if($this->$c !== '' && ($c != "id" || !$this->autoincrementid) )
                {
                    if($primero){$primero = false; self::$query = self::$query."(";}
                    else {self::$query = self::$query.", "; $value_part = $value_part.", ";}

                    self::$query = self::$query." ".$c;
                    $s = BD::sanitize($this->$c);
                    $value_part = $value_part."?";
                    array_push($values, $s);
                }
            }

            //Unimos la parte de los valores al resto de la query
            self::$query = self::$query.") VALUES ($value_part)";
        }

        //Ejecutamos la query
        //$query_result = BD::getInstance()->execQuery(self::$query);
        BD::getInstance()->prepare(self::$query);
        $query_result = BD::getInstance()->execPreparedQuery($values);

        //Si hay error lo mostramos y abortamos ejecución
        if($query_result){echo $query_result; die();}

        //Limpiamos la query
        self::clearQuery();


        if(!$this->inBDFlag) //Si no estaba en la base de datos
        {
            //Obtenemos el id
            self::$query = "SELECT id FROM ".$this->table." ORDER BY id DESC LIMIT 1";
            $query_result = self::first();
            $this->id = $query_result->id;
            $this->inBDFlag = true;
        }
    }

    //Función para eliminar el modelo de la base de datos
    public function delete()
    {
        $id = BD::sanitize($this->id); //Por si alguien ha tocado algo que no debía

        //Creamos la query
        self::$query = "DELETE FROM ".$this->table." WHERE id = ".$id;

        //Ejecutamos la query
        BD::getInstance()->prepare(self::$query);
        $query_result = BD::getInstance()->execPreparedQuery([]);

        //Si hay error lo mostramos y abortamos ejecución
        if($query_result){echo $query_result; die();}

        //Limpiamos la query
        self::clearQuery();
    }

    //Función auxiliar para volcar los datos de la tabla en el modelo
    public function setValues($values)
    {
        // Nota: PDO::FETCH_BOTH devuelve claves tanto numéricas como asociativas.
        // Usamos comparación estricta en in_array para evitar que claves numéricas (0, 1, …)
        // coincidan por coerción de tipos con nombres de columna (0 == "id" en PHP).
        foreach($values as $k => $v)
        {
            if(is_string($k) && in_array($k, $this->columns, true)) $this->$k = $v;
        }

        //Fix if id on joins
        $this->id = $values['id'] ?? $values[0] ?? null;
    }

    //SECCIÓN ESTÁTICA

    private static $query = ""; //Query a ejecutar
    private static $selectColumns = "*"; //Columnas a seleccionar (por defecto *)
    private static $customFrom = null; //Tabla FROM personalizada (null = autogenerada)
    public static function exposeQuery()
    {
        return self::$query;
    }

    //Devuelve el nombre de la tabla de este modelo (en minúsculas, pluralizado)
    public static function tableName()
    {
        return strtolower(get_called_class())."s";
    }

    //Función interna para limpiar la query
    private static function clearQuery()
    {
        self::$query = "";
        self::$selectColumns = "*";
        self::$customFrom = null;
    }

    //Función auxiliar que genera una select a la tabla de la clase respetando columnas y tabla personalizada
    private static function _generateQuery()
    {
        $tabla = self::$customFrom ?? strtolower(get_called_class())."s";
        self::$query = "SELECT ".self::$selectColumns." FROM ".$tabla;
    }

    //Función para añadir una cláusula where a la select (si esta no existe, la crea)
    public static function where($field, $operator, $value)
    {
        if(self::$query == "") //Si no hay query
        {
            self::_generateQuery(); //Generamos la select
        }

        //Añadimos la cláusula where
        $val = BD::sanitize($value);
        self::$query = self::$query." WHERE $field $operator $val";

    }

    //Función para añadir una cláusula and where a la select (si esta no existe, la crea sin el and)
    public static function andwhere($field, $operator, $value)
    {
        $and = "AND";
        if(self::$query == "") //Si no hay query
        {
            self::_generateQuery(); //Generamos la select
            $and = "WHERE";
        }

        //Añadimos la cláusula where
        $val = BD::sanitize($value);
        self::$query = self::$query." $and $field $operator $val";

    }

    //Función para añadir una cláusula orderby a la select (si esta no existe, la crea)
    //Soporta múltiples orderby: la primera llamada usa ORDER BY, las siguientes añaden con ,
    public static function orderby($field, $order = "ASC")
    {
        if(self::$query == "") //Si no hay query
        {
            self::_generateQuery(); //Generamos la select
        }

        //Si ya existe una cláusula ORDER BY, añadimos con coma; si no, creamos la cláusula
        if(strpos(self::$query, "ORDER BY") !== false)
        {
            self::$query = self::$query.", $field $order";
        }
        else
        {
            self::$query = self::$query." ORDER BY $field $order";
        }

    }

    //Función para hacer inner join (la cláusula ON se pasa como SQL literal)
    public static function inner_join($tabla, $on_clause)
    {
        if(self::$query == "") //Si no hay query
        {
            self::_generateQuery(); //Generamos la select
        }

        self::$query = self::$query." INNER JOIN $tabla ON $on_clause";

    }

    //Inner join a partir de una clase Model, su alias y la cláusula ON literal
    public static function innerJoinModel($class, $alias, $onClause)
    {
        self::inner_join($class::tableName()." ".$alias, $onClause);
    }

    //Función para hacer left outer join (la cláusula ON se pasa como SQL literal)
    public static function outer_join($tabla, $on_clause)
    {
        if(self::$query == "") //Si no hay query
        {
            self::_generateQuery(); //Generamos la select
        }

        self::$query = self::$query." LEFT OUTER JOIN $tabla ON $on_clause";

    }

    //Left outer join a partir de una clase Model, su alias y la cláusula ON literal
    public static function outerJoinModel($class, $alias, $onClause)
    {
        self::outer_join($class::tableName()." ".$alias, $onClause);
    }

    //Función para paginar la petición
    public static function paginate($pagesize, $pageindex = -1)
    {
        if(self::$query == "") //Si no hay query
        {
            self::_generateQuery(); //Generamos la select
        }

        //Añadimos la cláusula
        self::$query = self::$query." LIMIT $pagesize";

        if($pageindex >= 0)
        {
            //Añadimos la cláusula
            self::$query = self::$query." OFFSET ".$pageindex*$pagesize;
        }
    }

    //Función para recuperar la lista de modelos que resulten de la ejecución de la query
    //Si no hay query genera una select *
    public static function get()
    {
        if(self::$query == "") //Si no hay query
        {
            self::_generateQuery(); //Generamos select
        }

        //Ejecutamos la query
        $query_result = BD::getInstance()->select(self::$query);

        $retorno = array(); //Inicializamos el array donde vamos a almacenar los modelos a devolver
        $class = get_called_class(); //Obtenemos la clase a instanciar

        //Para cada fila obtenida
        if(!empty($query_result))
        {
            foreach($query_result as $qr)
            {
                $m = new $class(); //Instanciamos el modelo
                $m->setValues($qr); //Volcamos sus datos
                $m->inBDFlag = true; //Activamos el flag de la BD
                array_push($retorno, $m); //Añadimos el modelo a la lista de retorno
            }
        }

        self::$query = "";
        return $retorno; //Devolvemos la lista de retorno
    }

    //Devuelve el resultado de la query como array asociativo crudo (sin instanciar modelos).
    //Útil para consultas con JOIN que devuelven columnas de varias tablas.
    public static function getRaw()
    {
        if(self::$query == "") //Si no hay query
        {
            self::_generateQuery(); //Generamos select
        }

        $query_result = BD::getInstance()->select(self::$query)->fetchAll(\PDO::FETCH_ASSOC);

        self::$query = "";
        return (!empty($query_result)) ? $query_result : [];
    }

    //Establece las columnas del SELECT (por defecto "*")
    public static function select($columns)
    {
        self::$selectColumns = $columns;
    }

    //Establece una tabla FROM personalizada (con alias si se desea: "invitados i")
    public static function fromTable($tableName)
    {
        self::$customFrom = $tableName;
    }

    //Establece el FROM a partir de una clase Model y su alias
    public static function fromModel($class, $alias)
    {
        self::fromTable($class::tableName()." ".$alias);
    }

    //Añade una cláusula GROUP BY
    public static function groupBy($field)
    {
        if(self::$query == "") //Si no hay query
        {
            self::_generateQuery();
        }
        self::$query = self::$query." GROUP BY $field";
    }

    //Añade una cláusula LIMIT simple (sin offset)
    public static function limit($n)
    {
        if(self::$query == "") //Si no hay query
        {
            self::_generateQuery();
        }
        self::$query = self::$query." LIMIT ".intval($n);
    }

    //Añade una cláusula WHERE/AND con SQL literal sin sanitizar.
    //$operator: 'AND' (por defecto), 'WHERE', 'OR'
    public static function rawWhere($clause, $operator = 'AND')
    {
        if(self::$query == "")
        {
            self::_generateQuery();
            $operator = "WHERE";
        }
        elseif(stripos(self::$query, 'WHERE') === false)
        {
            $operator = "WHERE";
        }
        self::$query = self::$query." $operator $clause";
    }

    //Sugar syntax: where con LIKE
    public static function like($field, $pattern)
    {
        self::where($field, "LIKE", $pattern);
    }

    //Ejecuta COUNT(*) sobre la query construida y devuelve un entero.
    //Ignora ORDER BY y LIMIT de la query original.
    public static function count()
    {
        if(self::$query == "")
        {
            self::_generateQuery();
        }

        //Transformamos SELECT columnas FROM en SELECT COUNT(*) AS total FROM,
        //eliminando ORDER BY y LIMIT
        $countQuery = self::$query;
        $countQuery = preg_replace('/^SELECT\s+.+?\s+FROM\s+/i', 'SELECT COUNT(*) AS total FROM ', $countQuery);
        //Eliminar ORDER BY y lo que sigue
        $orderPos = stripos($countQuery, 'ORDER BY');
        if($orderPos !== false) {
            $countQuery = substr($countQuery, 0, $orderPos);
        }
        //Eliminar LIMIT y lo que sigue
        $limitPos = stripos($countQuery, 'LIMIT');
        if($limitPos !== false) {
            $countQuery = substr($countQuery, 0, $limitPos);
        }

        $rows = BD::getInstance()->select($countQuery)->fetchAll(\PDO::FETCH_ASSOC);
        self::clearQuery();

        if(!empty($rows) && isset($rows[0]['total'])) {
            return (int)$rows[0]['total'];
        }
        return 0;
    }

    //Elimina todos los registros que coincidan con los WHERE actuales.
    //Requiere al menos una cláusula WHERE por seguridad.
    public static function deleteAll()
    {
        if(self::$query == "")
        {
            //Sin WHERE no se borra nada, por seguridad
            return false;
        }

        //Transformamos SELECT ... FROM en DELETE FROM,
        //eliminando SELECT columns, ORDER BY, LIMIT, GROUP BY, JOINs
        $deleteQuery = self::$query;

        //Extraer la parte FROM tabla [alias]
        if(preg_match('/FROM\s+(\S+)/i', $deleteQuery, $matches)) {
            $fromTable = $matches[1];
        } else {
            $fromTable = strtolower(get_called_class())."s";
        }

        //Extraer solo las cláusulas WHERE
        $wherePart = "";
        $wherePos = stripos($deleteQuery, 'WHERE');
        if($wherePos !== false) {
            $wherePart = substr($deleteQuery, $wherePos);
            //Eliminar ORDER BY, LIMIT, GROUP BY posteriores
            foreach(['ORDER BY', 'LIMIT', 'GROUP BY'] as $kw) {
                $kwPos = stripos($wherePart, $kw);
                if($kwPos !== false) {
                    $wherePart = substr($wherePart, 0, $kwPos);
                }
            }
        }

        if(empty($wherePart)) {
            //Sin WHERE no se borra nada
            return false;
        }

        $deleteQuery = "DELETE FROM ".$fromTable." ".$wherePart;

        BD::getInstance()->prepare($deleteQuery);
        BD::getInstance()->execPreparedQuery([]);
        self::clearQuery();
        return true;
    }

    //Función que devuelve el primer modelo que coincida con la select
    public static function first()
    {
        $model_list = self::get();

        //Devolvemos el modelo
        return (!empty($model_list)) ? $model_list[0] : NULL;
    }
}
