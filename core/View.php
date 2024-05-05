<?php
/*
    Este fichero contiene el código relacionado con el sistema de vistas.
    Permite desarrollar la aplicación web como un conjunto de vistas y plantillas.

    @extend(plantilla)     -> indica que la vista o plantilla hereda de la plantilla indicada
    @yield(contenido)      -> indica que en ese lugar se colocará el contenido de una sección con ese nombre
    @section(contenido)    -> indica el inicio de una sección con ese nombre
    @endsection(contenido) -> indica el final de una sección con ese nombre
*/

    class Content
    {
        public $sections = array(); //Aquí se guardan las secciones de la vista
                                    //o plantilla
        public $template = null; //Si el contenido extiende una plantilla, aquí
                                 //se guarda el puntero al Content de la misma.
        public $content = ""; //Aquí se guarda el resultado de evaluar el contenido

        private $regexp = "[A-Za-z0-9]+(\/[A-Za-z0-9]+)?";

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

            if($evaluate)
            {
              include_once "./views/".$view_name.".view"; //Incluimos la vista o plantilla
              $this->content = ob_get_contents(); //Obtenemos el contenido evaluado
              ob_clean(); //Limpiamos el buffer para dejarlo listo
            }
            else
            {
              $this->content = file_get_contents("./views/".$view_name.".view");
            }

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

            //Si incluye el comando @component, los renderizamos
              $components = [];
              $components_rendered = [];

              if(preg_match_all("/@component\(".$this->regexp."\)/", $this->content, $components))
              {
                foreach($components[0] as $component) //Para cada una
                {
                    $cname = $this->getParentesisCont($component); //Obtenemos el nombre
                    $content = new Content($cname, $args, null);

                    //Sustituimos el string "@component(nombre-del-componente)" por el contenido del componente
                        $this->content = str_replace("@component($cname)", $content->content, $this->content);
                }
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

    //Función para cargar una vista
        function view($view_name, $args = array())
        {
            ob_start(); //Iniciamos el sistema de buffer

            //Instanciamos la vista como contenido sin hijos y le pasamos los argumentos
                $contenido = new Content($view_name, $args, null);

            return $contenido->render(); //Devolvemos el resultado de la renderización
        }

    //Función para redirigir a otra url
        function redirect($path)
        {
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
