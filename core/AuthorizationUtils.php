<?php
/*
    Este fichero contiene el código relacionado con las utilidades de login
*/
  require_once("core/BD.php");
  require_once("core/Model.php");

  class AuthorizationData extends Model
  {
    public function __construct()
    {
      parent::__construct(["username", "password", "semilla"]);
    }

    public static function autenticate($username, $pass)
    {
      self::where("username", "=", "'".md5($username)."'");
      $u = self::first();

      if(!empty($u) && $u->password == AuthorizationUtils::cript($pass, $u->semilla))
      {
        return $u->id;
      }

      return -1;
    }
  }

  class AuthorizationSession extends Model
  {
      public function __construct()
      {
          parent::__construct(["userid", "token", "ip", "expdata"]);
      }

      public static function RefreshExpData($date)
      {
        return $date->modify('+1 hour')->format("Y-m-d H:i:s");
      }

  		public static function GetRealIP()
  		{
  			if (!empty($_SERVER['HTTP_CLIENT_IP']))
  			{
  				return $_SERVER['HTTP_CLIENT_IP'];
  			}

  			if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
  			{
  				return $_SERVER['HTTP_X_FORWARDED_FOR'];
  			}

  			return $_SERVER['REMOTE_ADDR'];
  		}

      public static function Start($userid)
      {
        self::where("userid", "=", $userid);
        $session = self::first();

        if(empty($session))
        {
          $session = new AuthorizationSession;
          $session->userid = $userid;
        }

        $session->token = rand();
        $session->ip = self::GetRealIP();
        $session->expdata = self::RefreshExpData(new DateTimeImmutable(date("Y-m-d H:i:s")));
        $session->save();

        $_SESSION['session'] = serialize($session);
      }

      public static function autenticated()
      {
        $session = self::getSession();
        $ret = !empty($session);

        if($ret)
        {
          self::where("userid", "=", $session->userid);
          self::andwhere("ip", "=", "'".$session->ip."'");
          $aut = self::first();

          $ret = !empty($aut);
        }
        return $ret;
      }

      public static function getSession()
      {
        if(isset($_SESSION['session']) && !empty($_SESSION['session']))
        {
          return unserialize($_SESSION['session']);
        }
        else
        {
          return null;
        }
      }
  }

  class AuthorizationUtils
  {
    private $bd, $bd_bckp;
    private $engine, $bd_host, $user, $pass, $bd_name;

    private $opened = false;

    //La clase es singleton
    private static $instance = null;
    public static function start($_engine, $_bd_host, $_user, $_pass, $bd_name)
    {
      if (self::$instance == null)
      {
        self::$instance = new AuthorizationUtils($_engine, $_bd_host, $_user, $_pass, $bd_name);
      }
    }
    public static function getInstance()
    {
      return self::$instance;
    }

    private function __construct($_engine, $_bd_host, $_user, $_pass, $_bd_name)
    {
      $this->engine = $_engine;
      $this->bd_host = $_bd_host;
      $this->user = $_user;
      $this->pass = $_pass;
      $this->bd_name = $_bd_name;
      $this->opened = false;
    }

    public function prepare()
    {
      if(!BD::getInstance()->isLoaded($this->bd_name))
      {
        BD::getInstance()->addBD($this->engine, $this->bd_host, $this->user, $this->pass, $this->bd_name, $this->opened);
      }

      $this->oldBD = BD::getInstance()->getSelected();
      BD::getInstance()->setSelected($this->bd_name);
    }

    public function leave()
    {
      BD::getInstance()->setSelected($this->oldBD);
    }

    public function autenticate($username, $pass)
    {
      $this->prepare();

      $u_id = AuthorizationData::autenticate($username, $pass);
      if($u_id > 0) AuthorizationSession::Start($u_id);

      $this->leave();

      return $u_id;
    }

    public function autenticated()
    {
      $this->prepare();
      $ret = AuthorizationSession::autenticated();
      $this->leave();

      return $ret;
    }

    public function signup($username, $password)
    {
      $this->prepare();

      $usernameHashed = md5($username);

      AuthorizationData::where("username", "=", "'".md5($username)."'");
      $allOk = (AuthorizationData::first() == null);

      if($allOK)
      {
        $semilla = "";
        for($i = rand(9,20); $i > 0; $i--)
        {
          $semilla = $semilla.rand(0,9);
        }

        $passwordCripted = self::cript($password, $semilla);

        $ad = new AuthorizationData();
        $ad->username = $usernameHashed;
        $ad->password = $passwordCripted;
        $ad->semilla = $semilla;
        $ad->save();
      }

      $this->leave();
      return $allOk;
    }

    public static function cript ($contra,$semilla)
    {
      $algo=$contra;
      for($i=0;$i<strlen($semilla);$i++)
      {
        switch($semilla[$i])
        {
          case 0:
            $algo=hash('md2',$algo);
            break;
          case 1:
            $algo=hash('md5',$algo);
            break;
          case 2:
            $algo=hash('sha256',$algo);
            break;
          case 3:
            $algo=hash('sha1',$algo);
            break;
          case 4:
            $algo=hash('crc32',$algo);
            break;
          case 5:
            $algo=hash('adler32',$algo);
            break;
          case 6:
            $algo=hash('sha512',$algo);
            break;
          case 7:
            $algo=hash('snefru',$algo);
            break;
          case 8:
            $algo=hash('sha384',$algo);
            break;
          case 9:
            $algo=hash('md4',$algo);
            break;
          default:
            $algo=hash('whirlpool',$algo);
            break;
        }
      }
      return $algo;
    }
  }

  if($GLOBALS['LOGIN_BD_ENABLED'])
  {
    try
    {
      if(!isset($GLOBALS['LOGIN_BD_ENGINE'])) $GLOBALS['LOGIN_BD_ENGINE'] = '';
      if(!isset($GLOBALS['LOGIN_BD_SERVER'])) $GLOBALS['LOGIN_BD_SERVER'] = '';
      if(!isset($GLOBALS['LOGIN_BD_USER']))   $GLOBALS['LOGIN_BD_USER']   = '';
      if(!isset($GLOBALS['LOGIN_BD_PASS']))   $GLOBALS['LOGIN_BD_PASS']   = '';

      AuthorizationUtils::start($GLOBALS['LOGIN_BD_ENGINE'], $GLOBALS['LOGIN_BD_SERVER'], $GLOBALS['LOGIN_BD_USER'], $GLOBALS['LOGIN_BD_PASS'], $GLOBALS['LOGIN_BD_NAME']);
    }
    catch(Exception $ex)
    {
      echo "[ERROR] No se ha podido conectar con la base de datos de login";
      die();
    }
  }
