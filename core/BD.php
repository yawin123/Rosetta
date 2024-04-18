<?php
/*
Este fichero contiene el código relacionado con la base de datos.
*/

  class BD
  {
    private $bd = array(), $bd_conf = array();
    private $selectedBD = "";

    //La clase es singleton
    private static $instance = null;
    public static function start($_engine, $_bd, $_user, $_pass, $bd_name)
    {
      if (self::$instance == null)
      {
        self::$instance = new BD($_engine, $_bd, $_user, $_pass, $bd_name);
      }
    }

    public static function getInstance()
    {
      return self::$instance;
    }

    private function __construct($_engine, $_bd, $_user, $_pass, $bd_name)
    {
      $this->addBD($_engine, $_bd, $_user, $_pass, $bd_name);
      $this->selectedBD = $bd_name;
    }

    public function addBD($_engine, $_bd, $_user, $_pass, $bd_name)
    {
      if(!$this->isLoaded($bd_name))
      {
        switch($_engine)
        {
          case "mysql":
            //Se abre la conexión
            $this->bd[$bd_name] = new PDO("mysql:dbname=".$bd_name.";host=".$_bd, $_user, $_pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));

            //Se guarda la configuración
            $this->bd_conf[$bd_name] = "mysql:dbname=".$bd_name.";host=".$_bd.":".$_user.":".$_pass;
            break;

          case "sqlite":
            //Se abre la conexión
            $this->bd[$bd_name] = new PDO("sqlite:".$bd_name);

            //Se guarda la configuración
            $this->bd_conf[$bd_name] = "sqlite:".$bd_name;

            break;
        }
      }
    }

    public function isLoaded($bdID)
    {
      return array_key_exists($bdID, $this->bd);
    }

    public function getSelected()
    {
      return $this->selectedBD;
    }
    public function setSelected($bdID)
    {
      if($this->isLoaded($bdID)) $this->selectedBD = $bdID;
    }

    public function getBD()
    {
      return $this->bd[$this->selectedBD];
    }

    public function select($query)
    {
      return $this->bd[$this->selectedBD]->query($query);
    }

    public function prepare($query)
    {
      try
      {
        //Se prepara la query
        $this->stmnt = $this->bd[$this->selectedBD]->prepare($query);
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
      return $this->bd_conf[$this->selectedBD];
    }
  }

  if($GLOBALS['BD_ENABLED'])
  {
    try
    {
      if(!isset($GLOBALS['BD_SERVER'])) $GLOBALS['BD_SERVER'] = '';
      if(!isset($GLOBALS['BD_USER'])) $GLOBALS['BD_USER'] = '';
      if(!isset($GLOBALS['BD_PASS'])) $GLOBALS['BD_PASS'] = '';

      BD::start($GLOBALS['BD_ENGINE'], $GLOBALS['BD_SERVER'], $GLOBALS['BD_USER'], $GLOBALS['BD_PASS'], $GLOBALS['BD_NAME']);
    }
    catch(Exception $ex)
    {
      echo "[ERROR] No se ha podido conectar con la base de datos";
      die();
    }
  }
