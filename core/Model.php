<?php
/*
    Este fichero contiene el código relacionado con la clase Model.
*/

    class Model
    {
        private $inBDFlag = false; //Flag que indica si este modelo proviene de la base de datos
        private $columns; //Nombre de las columnas del modelo
        private $table; //Nombre de la tabla del modelo

        //Constructor
            public function __construct($columns)
            {
                //Añadimos la columna id
                    array_unshift($columns, "id");

                //Para cada par clave/tipo del array
                    foreach($columns as $k)
                    {
                       //Creamos un nuevo miembro llamado como la clave
                           $this->$k = '';
                    }

                //Guardamos la lista de columnas
                    $this->columns = $columns;

                //Guardamos el nombre de la tabla
                    $this->table = strtolower(get_called_class())."s";
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
                            if($this->$c != "" && $c != "id")
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
                            if($this->$c != "" && $c != "id" )
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
                foreach($values as $k => $v)
                {
                    $this->$k = $v;
                }
            }

        //SECCIÓN ESTÁTICA

        private static $query = ""; //Query a ejecutar

        //Función interna para limpiar la query
            private static function clearQuery()
            {
                self::$query = "";
            }

        //Función auxiliar que genera una select * a la tabla de la clase
            private static function _generateSelect()
            {
                self::$query = "SELECT * FROM ".strtolower(get_called_class())."s";
            }

        //Función para añadir una cláusula where a la select (si esta no existe, la crea)
            public static function where($field, $operator, $value)
            {
                if(self::$query == "") //Si no hay query
                {
                    self::_generateSelect(); //Generamos la select
                }

                //Añadimos la cláusula where
                    $val = BD::sanitize($value);
                    self::$query = self::$query." WHERE $field $operator $val";

            }

        //Función para añadir una cláusula orderby a la select (si esta no existe, la crea)
            public static function orderby($field, $order = "ASC")
            {
                if(self::$query == "") //Si no hay query
                {
                    self::_generateSelect(); //Generamos la select
                }

                //Añadimos la cláusula where
                    self::$query = self::$query." ORDER BY $field $order";
                    echo self::$query;

            }

        //Función para recuperar la lista de modelos que resulten de la ejecución de la query
        //Si no hay query genera una select *
            public static function get()
            {
                if(self::$query == "") //Si no hay query
                {
                    self::_generateSelect(); //Generamos select
                }

                //Ejecutamos la query
                    $query_result = BD::getInstance()->select(self::$query);

                $retorno = array(); //Inicializamos el array donde vamos a almacenar los modelos a devolver
                $class = get_called_class(); //Obtenemos la clase a instanciar

                //Para cada fila obtenida
                    foreach($query_result as $qr)
                    {
                        $m = new $class(); //Instanciamos el modelo
                        $m->setValues($qr); //Volcamos sus datos
                        $m->inBDFlag = true; //Activamos el flag de la BD
                        array_push($retorno, $m); //Añadimos el modelo a la lista de retorno
                    }

                self::$query = "";
                return $retorno; //Devolvemos la lista de retorno
            }

        //Función que devuelve el primer modelo que coincida con la select
            public static function first()
            {
                return self::get()[0]; //Devolvemos el modelo
            }
    }
