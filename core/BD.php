<?php
/*
    Este fichero contiene el código relacionado con la base de datos.
*/

    class BD
    {
        private $bd, $bd_conf;

        //La clase es singleton
            private static $instance = null;
		    public static function start($_bd, $_user, $_pass, $bd_name)
		    {
                if (self::$instance == null)
                {
                    self::$instance = new BD($_bd, $_user, $_pass, $bd_name);
                }
		    }
            public static function getInstance()
            {
                return self::$instance;
            }

		private function __construct($_bd, $_user, $_pass, $bd_name)
		{
		    //Se abre la conexión
    			$this->bd = new PDO("mysql:dbname=".$bd_name.";host=".$_bd, $_user, $_pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));

    	    //Se guarda la configuración
    			$this->bd_conf = "mysql:dbname=".$bd_name.";host=".$_bd.$_user.$_pass;
		}

		public function getBD()
		{
			return $this->bd;
		}

		public function select($query)
		{
			return $this->bd->query($query);
		}

    public function prepare($query)
    {
			try
			{
			    //Se prepara la query
    				$this->stmnt = $this->bd->prepare($query);
      }
      catch(PDOException $e) //Si salta alguna excepción
      {
          //Se devuelve la excepción
            return $e->getMessage();
      }
    }

    public function execPreparedQuery($values)
    {
      if(isset($this->stmnt))
      {
        try
        {
          //Si al ejecutarla da error
          if($this->stmnt->execute($values) == false)
          {
            //Se devuelve el error
            return $this->stmnt->errorInfo()[2];
          }
        }
        catch(PDOException $e) //Si salta alguna excepción
        {
          //Se devuelve la excepción
          return $e->getMessage();
        }
      }
    }

    public function callProcedure($procedure, $arg)
    {
      self::prepare("CALL $procedure(?)");
      return self::execPreparedQuery($arg);
    }

		public static function sanitize($val)
		{
		    return htmlentities($val);
		}

		public function __toString()
        {
            return $this->bd_conf;
        }
	}

  if($GLOBALS['BD_ENABLED'])
  {
  	try
  	{
  		BD::start($GLOBALS['BD_SERVER'], $GLOBALS['BD_USER'], $GLOBALS['BD_PASS'], $GLOBALS['BD_NAME']);
  	}
  	catch(Exception $ex)
  	{
  		echo "[ERROR] No se ha podido conectar con la base de datos";
  		die();
  	}
  }
