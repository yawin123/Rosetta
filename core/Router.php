<?php
/*
 *    Este fichero contiene el código relacionado con el enrutado de la aplicación.
 *    Permite enrutar el contenido, haciendo que no sea necesario llenar los sripts
 *    de includes.
 */

#[AllowDynamicProperties]
class Router
{
    private $route_path;   //Path al que se quiere acceder. Es un array.
    //Ej.: /user/foo => ["user", "foo"]

    private $routes;    //Array donde se almacenan las rutas
    private $route_names; //Array donde se relacionan las rutas con nombres
    private $active_route_name = ""; //Nombre de la ruta activa

    //Router es singleton
    private static $instance = null;
    public static function getInstance()
    {
        if (self::$instance == null)
        {
            self::$instance = new Router();

            //Añadimos la ruta de redirección
            Router::get("redirect/{name}", [Router::class, "redirect"]);
        }

        return self::$instance;
    }

    private function __construct()
    {
        //Separamos ruta de variables
        $tmp = explode('?', $_SERVER['REQUEST_URI'], 2);
        $tmp[0] = str_replace($GLOBALS['ROOT_PATH'], "", $tmp[0]);

        //Cocinamos la ruta
        $this->route_path = $tmp[0];

        /*
         *                    Esto iba a usarlo para poder hacer paths tipo:
         *                    Router::get("/user/$user", [UserController::class, 'panel']);
         *                    Lo cual haría que si haces get a /user/usuario se llamara a
         *                    UserController::panel, pasándole como argumento $user = "usuario".
         *
         *                    Pero me da pereza y puedo hacer el proyecto sin ello, así que
         *                    se queda así.
         */

        // Informamos a Request de las variables GET
        Request::addMember($_GET);


        $this->method = $_SERVER['REQUEST_METHOD']; //Obtenemos el método
        if($this->method == "POST")
        {
            // Informamos a Request de las variables POST
            Request::addMember($_POST);

            //Si el método es POST, existe la variable "crlf" tanto en la llamada como en la sesión y sus valores coinciden
            if(isset($_POST['crlf']) && isset($_SESSION['crlf']) && $_POST['crlf'] == $_SESSION['crlf'])
            {
                //Cambiamos el método a SecPOST
                $this->method = "SecPOST";
            }
        }

        //Si hay ficheros en la llamada
        if(isset($_FILES) && !empty($_FILES))
        {
            Request::addMember($_FILES);
        }

        //Inicializamos los arrays de rutas y nombres
        $this->routes = ["GET" => array(), "POST" => array(), "SecPOST" => array()];
        $this->route_names = array();

        //Inicializamos el token para el método PUT
        //$_SESSION['crlf'] = rand();
    }

    //Función para añadir una redirección
    public static function redirection($name, $route)
    {
        self::getInstance()->route_names[$name] = $route;
    }

    public static function redirect($name)
    {
        redirect(self::getInstance()->route_names[$name]);
    }

    //Función para dar de alta una ruta GET
    public static function get($route, $callback, $name = "")
    {
        //Asociamos la ruta con su callback
        self::getInstance()->routes["GET"][$route] = $callback;

        if($name != "") //Si se ha indicado nombre
        {
            //Asociamos el nombre a la ruta
            self::getInstance()->route_names[$name] = $route;
        }
    }

    //Función para dar de alta una ruta POST
    public static function post($route, $callback, $name = "")
    {
        //Asociamos la ruta con su callback
        self::getInstance()->routes["POST"][$route] = $callback;

        if($name != "") //Si se ha indicado nombre
        {
            //Asociamos el nombre a la ruta
            self::getInstance()->route_names[$name] = $route;
        }
    }

    //Función para dar de alta una ruta PUT
    public static function secpost($route, $callback, $name = "")
    {
        //Asociamos la ruta con su callback
        self::getInstance()->routes["SecPOST"][$route] = $callback;

        if($name != "") //Si se ha indicado nombre
        {
            //Asociamos el nombre a la ruta
            self::getInstance()->route_names[$name] = $route;
        }
    }

    //Función para saber si existe alguna ruta con el nombre dado
    public static function has($route_name)
    {
        return array_key_exists($route_name, self::getInstance()->route_names);
    }

    //Función para obtener la ruta asociada al nombre dado
    public static function isRealPath($route_path)
    {
        foreach(self::getInstance()->routes as $met)
        {
            if(array_key_exists($route_path, $met))
            {
                return true;
            }
        }

        return false;
    }

    public static function path($route_name, $vars = array())
    {
        $path = self::getInstance()->route_names[$route_name];

        if(self::isRealPath($path))
        {
            $i = 0;
            foreach($vars as $k => $v)
            {
                $path = ($i > 0) ? $path."&" : $path."?"; $i++;
                $path = $path.$k."=".$v;
            }

            return $GLOBALS['ROOT_PATH'].$path;
        }
        else
        {
            return $GLOBALS['ROOT_PATH']."/redirect/".$route_name;
        }
    }

    //Función para resolver la ruta solicitada (llamada a callback)
    public static function resolve()
    {
        $me = self::getInstance(); //Se obtiene la instancia al router

        //Si existe la ruta requerida
        if(array_key_exists($me->route_path, $me->routes[$me->method]))
        {
            //Guardamos el nombre de la ruta
            $me->active_route_name = array_search($me->route_path, $me->route_names);

            //Se llama a su callback
            echo call_user_func($me->routes[$me->method][$me->route_path]);
        }
        //Si no, se buscan coincidencias en rutas con variables
        else
        {
            //Preparamos la ruta
            $route_path = $me->route_path;
            if($route_path[0] == '/'){$route_path = substr($route_path, 1);}
            if($route_path[strlen($route_path)-1] == "/"){$route_path = substr($route_path, 0, strlen($route_path)-1);}

            //Si es raíz, no habrá coincidencias, así que se devuelve fallo
            if($route_path == ""){fail();}

            //Se divide la ruta en pedazos
            $args = explode("/", $route_path);
            $nargs = count($args);

            //Para cada ruta del método
            foreach($me->routes[$me->method] as $route => $callback)
            {
                //Limpiamos la ruta
                $r = $route;
                if($r[0] == '/'){$r = substr($r, 1);}

                //Si no es raíz
                if($r != "")
                {
                    //Dividimos la ruta en pedazos
                    $req_args = explode("/", $r);
                    $n_req_args = count($req_args);

                    //Si coinciden en número de argumentos
                    if($n_req_args == $nargs)
                    {
                        $argumentos = array();
                        $match = true;

                        //Para cada argumento
                        for($i = 0; $i < $n_req_args; $i++)
                        {
                            //Si el argumento es una variable
                            if($req_args[$i][0] == "{")
                            {
                                $arg = substr($req_args[$i], 1);
                                $arg = substr($arg, 0, strlen($arg)-1);
                                $argumentos[$arg] = $args[$i];

                            }
                            //Si el argumento es una constante, miramos si coinciden
                            else if($req_args[$i] != $args[$i])
                            {
                                $match = false;
                                break;
                            }
                        }

                        //Si hay coincidencia
                        if($match)
                        {
                            //Guardamos el nombre de la ruta
                            $me->active_route_name = array_search($route, $me->route_names);

                            echo call_user_func_array($callback, $argumentos);
                            return;
                        }
                    }
                }
            }

            //Si llegamos aquí es que no ha habido coincidencias
            $me->active_route_name = "";
            fail();
        }
    }

    //Para saber dónde estamos
    public static function getRoutePath()
    {
        return self::getInstance()->route_path;
    }

    //Para saber si la ruta actual es la indicada
    public static function isActualPath($route_name)
    {
        return (self::getInstance()->active_route_name == $route_name);
    }
}
