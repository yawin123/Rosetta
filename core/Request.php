<?php
/*
    Este fichero contiene el código relacionado con la clase Request, que
    es la que almacena todo lo relacionado con los requests.
*/

    class Request
    {
        //Request es singleton
            private static $instance = null;
            public static function getInstance()
            {
                if (self::$instance == null)
                {
                    self::$instance = new Request();
                }

                return self::$instance;
            }

        //Función auxiliar para añadir miembros
            private function _addMember($args)
            {
                //Para cada par clave/dato del array
                    foreach($args as $k => $v)
                    {
                        //Creamos un nuevo miembro llamado como la clave
                        //y que contiene el dato
                            $this->$k = $v;
                    }
            }

        //Función estática para añadir miembros
            public static function addMember($args)
            {
                self::getInstance()->_addMember($args);
            }

        //Función estática que devuelve todas las variables miembro como array
            public static function expose()
            {
                return get_object_vars(self::getInstance());
            }

        //Función auxiliar para la funcionalidad "required"
            private function _required($args)
            {
                //Para cada elemento requerido
                foreach($args as $a)
                {
                    //Se comprueba que existe y contiene algo
                        if(!isset($this->$a))// || empty($this->$a))
                        {
                            //Si la comprobación falla se retorna falso
                                return false;
                        }
                }

                //Si todas las comprobaciones resultan ciertas, se retorna cierto
                return true;
            }

        //Función estática que ofrece la funcionalidad required
            public static function required($args)
            {
                //Si la función auxiliar nos indica que uno de los requisitos falla
                    if(!self::getInstance()->_required($args))
                    {
                        back(); //Se llama a la función back (que vuelve a la página anterior)
                        die();  //Y se aborta el resto de la ejecución
                    }
            }

            public static function hasRequiredFields($args)
            {
                //Si la función auxiliar nos indica que uno de los requisitos falla
                    if(!self::getInstance()->_required($args))
                    {
                        return false;
                    }

                return true;
            }
    }
