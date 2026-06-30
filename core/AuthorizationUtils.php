<?php
/*
 *    Este fichero contiene el código relacionado con las utilidades de login
 */
require_once("core/BD.php");
require_once("core/Model.php");

#[AllowDynamicProperties]
class AuthorizationData extends Model
{
  public function __construct()
  {
    parent::__construct(["username", "password", "semilla"]);
  }

  public static function autenticate($username, $pass)
  {
    $result = AuthorizationUtils::apicall("/authorize", ['username'=> $username, 'password'=>$pass]);

    $userid = -1;
    if(isset($result->userid)) $userid = $result->userid;

    return $userid;
  }
}

#[AllowDynamicProperties]
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

    $_SESSION[$GLOBALS['BD_NAME'].'_session'] = serialize($session);
  }

  public static function Stop()
  {
    $ses = self::getSession();
    if($ses != null)
    {
      self::where("userid", "=", $ses->userid);
      $session = self::first();
      $session->delete();

      unset($_SESSION[$GLOBALS['BD_NAME'].'_session']);
    }
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
    if(isset($_SESSION[$GLOBALS['BD_NAME'].'_session']) && !empty($_SESSION[$GLOBALS['BD_NAME'].'_session']))
    {
      return unserialize($_SESSION[$GLOBALS['BD_NAME'].'_session']);
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

  public function changeToken($oldtoken, $newtoken)
  {
    $this->prepare();

    AuthorizationData::where("username", "=", "'".md5($oldtoken)."'");

    $ad = AuthorizationData::first();
    $ad->username = md5($newtoken);
    $ad->save();

    $this->leave();
  }

  public function changePasswd($token, $passwd)
  {
    $this->prepare();

    AuthorizationData::where("username", "=", "'".md5($token)."'");
    $ad = AuthorizationData::first();
    $ad->semilla = "";
    for($i = rand(9,20); $i > 0; $i--)
    {
      $ad->semilla = $ad->semilla.rand(0,9);
    }

    $resp = self::apicall("/hash", ["data" => $passwd, "seed" => $ad->semilla]);
    $ad->password = $resp->hash;

    $ad->save();

    $this->leave();
  }

  public function generatePassword()
  {
    $semilla = "";
    for($i = rand(9,20); $i > 0; $i--)
    {
      $semilla = $semilla.rand(0,9);
    }

    $resp = self::apicall("/hash", ["data" => $semilla, "seed" => $semilla]);
    $pass = $resp->hash;

    $resp = self::apicall("/hash", ["data" => $pass, "seed" => $semilla]);
    $hash = $resp->hash;

    return ["semilla" => $semilla, "pass" => $pass, "hash" => $hash];
  }

  public function createCredentials($username)
  {
    $this->prepare();

    $ad = new AuthorizationData();
    $ad->username = md5($username);

    $resp = $this->generatePassword();
    $ad->semilla = $resp["semilla"];
    $ad->password = $resp["hash"];
    $pass = $resp["pass"];

    $ad->save();

    AuthorizationData::orderby("id", "DESC");
    $ad_last = AuthorizationData::first();

    $this->leave();

    return ["pass" => $pass, "id" => $ad_last->id];
  }

  public function removeCredentials($username)
  {
    $this->prepare();

    AuthorizationData::where("username", "=", "'".md5($username)."'");
    $ad = AuthorizationData::first();
    $ad->delete();

    $this->leave();

  }

  /*****************************/
  /* MÉTODOS QUE NO USAN LA BD */
  /*****************************/

  public static function deautenticate()
  {
    if(self::autenticated())
    {
      AuthorizationSession::Stop();
    }
  }

  public static function autenticate($username, $pass)
  {
    $u_id = AuthorizationData::autenticate($username, $pass);
    if($u_id > 0) AuthorizationSession::Start($u_id);

    return $u_id;
  }

  public static function autenticated()
  {
    $ret = AuthorizationSession::autenticated();
    return $ret;
  }

  public static function apicall($dst, $body)
  {
    $url = $GLOBALS['API_URL'].":".$GLOBALS['API_PORT'].$dst;

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_HTTPHEADER => array(
        'X-API-KEY: '.$GLOBALS['API_KEY'],
        'Content-Type: application/json'
      ),
      CURLOPT_POSTFIELDS => json_encode($body)
    ));

    $response = curl_exec($ch);
    return json_decode($response);
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
