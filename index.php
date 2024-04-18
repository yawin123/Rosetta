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

  include_once "config.php";

	load_path("core");

	load_path("models");
	load_path("controllers");

  include_once "routes.php";

  Router::resolve();
?>
