<?
/*
    Este fichero contiene el código de la aplicación web.
*/
  session_start();
  function load_path($rut)
  {
    $ruta = "./".$rut."/";
    if(is_dir($ruta))
    {
      if($dh = scandir($ruta))
      {
        foreach($dh as $file)
        {
          if(is_file($ruta.$file) && $file!="." && $file!="..")
          {
            require_once($ruta.$file);
          }
        }
      }
    }
  }

  //Cargamos configuración
    include_once "config.php";

  //Establecemos display de errores
  	if(isset($GLOBALS['ERROR_REPORTING_LEVEL']) && $GLOBALS['ERROR_REPORTING_LEVEL'] > 0)
    {
      error_reporting($GLOBALS['ERROR_REPORTING_LEVEL']);
      ini_set('display_errors', 1);
      ini_set('display_startup_errors', 1);
    }
  	else
  	{
  		ini_set('display_errors', 0);
  		ini_set('display_startup_errors', 0);
  		error_reporting(0);
  	}

  //Cargamos el framework
	  load_path("core");

  //Cargamos los modelos
	  load_path("models");

  //Cargamos los controladores
	  load_path("controllers");

  //Cargamos los recursos adicionales
    load_path("resources");

  //Cargamos las rutas
    include_once "routes.php";

  //Cargamos las rutas
    include_once "initializer.php";

  //Resolvemos la ruta
    Router::resolve();
?>
