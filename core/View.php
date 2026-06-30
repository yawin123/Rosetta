<?php
/*
    Este fichero contiene el código relacionado con el sistema de vistas.
    Permite desarrollar la aplicación web como un conjunto de vistas y plantillas.

    @extend(plantilla)     -> indica que la vista o plantilla hereda de la plantilla indicada
    @yield(contenido)      -> indica que en ese lugar se colocará el contenido de una sección con ese nombre
    @section(contenido)    -> indica el inicio de una sección con ese nombre
    @endsection(contenido) -> indica el final de una sección con ese nombre
*/

    #[AllowDynamicProperties]
    class Content
    {
        public $sections = array(); //Aquí se guardan las secciones de la vista
                                    //o plantilla
        public $template = null; //Si el contenido extiende una plantilla, aquí
                                 //se guarda el puntero al Content de la misma.
        public $content = ""; //Aquí se guarda el resultado de evaluar el contenido

        private $regexp = "[A-Za-z0-9_]+(\/[A-Za-z0-9_]+)*";

        //Función auxiliar para extraer el texto contenido en los paréntesis de las directivas
            private function getParentesisCont($text)
            {
                $cont = "";
                $par = false;
                foreach(str_split($text) as $l)
                {
                    if($l == "("){$par = true;}
                    else if($l == ")"){$par = false;}
                    else if($par){$cont = $cont.$l;}
                }

                return $cont;
            }

        public function __construct($view_name, $args, $child = null, $evaluate = true)
        {
            //Si hay argumentos los extraemos para que el código de este view pueda usarlos
                if(sizeof($args) > 0)
                {
                    extract($args); //Extract importa variables a la tabla de símbolos actual desde un array
                }

            if(ob_get_length() > 0) ob_clean(); //Limpiamos el buffer por si estuviera sucio

            $this->content = file_get_contents("./views/".$view_name.".view");

            //Si tenemos hijo, miramos si hay yields y si los hay, los obtenemos
              if($child != null)
              {
                  $yields = [];
                  //Buscamos directivas "@yield"
                      if(preg_match_all("/@yield\(".$this->regexp."\)/", $this->content, $yields))
                      {
                          //Si las encontramos
                          foreach($yields[0] as $y) //Para cada una
                          {
                              $yname = $this->getParentesisCont($y); //Obtenemos el nombre
                              //Sustituimos el string "@yield(nombre-del-yield)" por el contenido de la sección de su hijo
                                  $this->content = str_replace("@yield($yname)", $child->sections[$yname], $this->content);
                          }
                      }
              }

            //Si incluye el comando @crlf lo reemplazamos por un input hidden con su valor
              if(preg_match("/@crlf/", $this->content, $tmp))
              {
                $this->content = str_replace("@crlf", "<input hidden name='crlf' value='".crlf()."'/>", $this->content);
              }

            //Si incluye el comando @rawcomponent, los incrustamos
              $rawcomponents = [];
              $rawcomponents_rendered = [];

              if(preg_match_all("/@rawcomponent\(".$this->regexp."\)/", $this->content, $rawcomponents))
              {
                foreach($rawcomponents[0] as $rawcomponent) //Para cada una
                {
                  $cname = $this->getParentesisCont($rawcomponent); //Obtenemos el nombre

                  //Si no está renderizado, lo Renderizamos
                    if(!array_key_exists($cname, $rawcomponents_rendered))
                    {
                      $rawcomponents_rendered[$cname] = new Content($cname, $args, null, false);

                      //Sustituimos el string "@component(nombre-del-componente)" por el contenido del componente
                        $this->content = str_replace("@rawcomponent($cname)", $rawcomponents_rendered[$cname]->content, $this->content);
                    }
                }
              }

            //Si incluye el comando @component, los convertimos en llamadas PHP
            //que se ejecutarán durante eval(), cuando las variables de bucle ya existen.
            //Sintaxis: @component(ruta)  o  @component(ruta, ['var' => $valor])
            $offset = 0;
            while (($pos = strpos($this->content, '@component(', $offset)) !== false) {
                $depth = 0;
                $start = $pos + 11; // strlen('@component(')
                $end = $start;
                for ($i = $start; $i < strlen($this->content); $i++) {
                    if ($this->content[$i] === '(') $depth++;
                    elseif ($this->content[$i] === ')') {
                        if ($depth === 0) { $end = $i; break; }
                        $depth--;
                    }
                }

                $inner = substr($this->content, $start, $end - $start);
                $fullMatch = substr($this->content, $pos, $end - $pos + 1);

                // Buscar la primera coma fuera de corchetes/paréntesis
                $bdepth = 0;
                $commaPos = -1;
                for ($i = 0; $i < strlen($inner); $i++) {
                    if ($inner[$i] === '[' || $inner[$i] === '(') $bdepth++;
                    elseif ($inner[$i] === ']' || $inner[$i] === ')') $bdepth--;
                    elseif ($inner[$i] === ',' && $bdepth === 0) {
                        $commaPos = $i;
                        break;
                    }
                }

                if ($commaPos >= 0) {
                    $cname = var_export(trim(substr($inner, 0, $commaPos)), true);
                    $phpCall = '<? __component(' . $cname . ', ' . substr($inner, $commaPos + 1) . '); ?>';
                } else {
                    $cname = var_export(trim($inner), true);
                    $phpCall = '<? __component(' . $cname . ', get_defined_vars()); ?>';
                }

                $this->content = str_replace($fullMatch, $phpCall, $this->content);
                $offset = $pos;
            }

            //Trabajamos las secciones, si las hay
                $sections = [];

                //Buscamos las secciones
                    if(preg_match_all("/@section\(".$this->regexp."\)/", $this->content, $sections))
                    {
                        //Si las encontramos
                        foreach($sections[0] as $s) //Para cada una
                        {
                            $sname = $this->getParentesisCont($s); //Obtenemos su nombre

                            //Buscamos las posiciones de inicio y fin del contenido
                                $start = strpos($this->content, "@section($sname)") + strlen("@section($sname)");
                                $end = strpos($this->content, "@endsection($sname)") - $start;

                            //Introducimos el contenido en un diccionario
                                $this->sections[$sname] = substr($this->content, $start, $end);
                        }
                    }

                if($evaluate)
                {
                  if(ob_get_length() > 0) ob_clean(); //Limpiamos el buffer por si estuviera sucio
                  eval("?>".$this->content);
                  $this->content = ob_get_contents();
                  ob_clean();
                }

                $template = [];
                //Si extiende un template, lo obtenemos
                if(preg_match("/@extends\(".$this->regexp."\)/", $this->content, $template))
                {
                    //Y creamos un nuevo content con ese fichero, pasándole un puntero al contenido hijo y los argumentos.
                        $this->template = new Content($this->getParentesisCont($template[0]), $args, $this);
                }
        }

        //Función auxiliar que comprueba si el contenido a enviar es el propio
        //o el de una plantilla padre
            private function doRend()
            {
              return ($this->template) ? $this->template->doRend() : $this->content;
            }

        //Función que renderiza el contenido de la vista
            public function render()
            {
                if(ob_get_length() > 0) ob_clean(); //Limpiamos el buffer por si estuviera sucio
                echo $this->doRend(); //Renderizamos la vista
                return ob_get_clean(); //Devolvemos el contenido del buffer
            }
    }

    //Función auxiliar para @component — se invoca desde dentro de eval()
    //Permite que las variables de bucle (foreach) estén en scope.
        function __component($cname, $args = [])
        {
            extract($args);
            $content = file_get_contents("./views/{$cname}.view");
            // Procesar @crlf: los componentes no pasan por Content, hay que hacerlo aquí
            $content = str_replace("@crlf", "<input hidden name='crlf' value='".crlf()."'/>", $content);
            eval("?>".$content);
        }

    //Función para cargar una vista
        function view($view_name, $args = array())
        {
            ob_start(); //Iniciamos el sistema de buffer

            //Instanciamos la vista como contenido sin hijos y le pasamos los argumentos
                $contenido = new Content($view_name, $args, null);

            return $contenido->render(); //Devolvemos el resultado de la renderización
        }

    //Función para mostrar un json
        function viewData($data)
        {
            header('Content-Type: application/json; charset=utf-8');
            return json_encode($data);
        }


    //Función para redirigir a otra url
        function redirect($path)
        {
            // Si es una ruta relativa (empieza por /), añadir ROOT_PATH si no está ya
            if($path[0] == '/' && !str_starts_with($path, $GLOBALS['ROOT_PATH'] . '/') && $path !== $GLOBALS['ROOT_PATH'])
            {
                $path = $GLOBALS['ROOT_PATH'] . $path;
            }
            return header('Location: ' . $path);
        }

    //Función para volver a la página anterior
        function back()
        {
            return header('Location: ' . $_SERVER['HTTP_REFERER']);
        }

    //Función para devolver vista de error y abortar ejecución
        function fail()
        {
            //Por el momento, se devuelve siempre la vista del error 404
                echo view("404");
            die();
        }

    //Función para generar el token crlf
        function crlf()
        {
          $_SESSION['crlf'] = rand();
          return $_SESSION['crlf'];
        }
