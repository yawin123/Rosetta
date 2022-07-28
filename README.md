# Rosetta
Rosetta es un framework de código abierto para desarrollar aplicaciones y servicios web. Fue creado por Miguel Albors Iruretagoyena durante sus clases en la universidad para poder explorar el funcionamiento de este tipo de herramientas.

## Componentes
### Router
El router es el sistema de navegación de la web. El .htaccess garantiza que, sea cual sea la petición, siempre se llamará a "/index.php". El router es quien toma la ruta solicitada y adquiere sus contenidos. Para ello, es necesario dar de alta las rutas en el fichero "routes.php".

Para hacerlo hay tres métodos:

 * Router::get(**ruta**, **callback**, **\<nombre\>**)
 * Router::post(**ruta**, **callback**, **\<nombre\>**)
 * Router::put(**ruta**, **callback**, **\<nombre\>**)

En todos los casos, los parámetros a enviar son los siguientes:
 - **ruta (string):** La ruta a la que se corresponde *(ej: "/about")*.
 - **callback (function):** Función a la que llamar cuando se llame a la ruta. Se pueden emplear tanto funciones como miembros de clases.
 - **nombre (string) \<opcional\>:** El nombre que se le da a esta ruta. No es necesario para que la ruta responda; pero es útil para rellenar los href llamando a *Router::path()*.

> ##### *La diferencia entre POST y SecPOST*
> *SecPOST es una forma securizada de POST. Sus rutas sólo responden si y sólo si el método de llamada es POST y en el request se incluye una variable llamada "crlf" cuyo contenido sea igual al contenido de la variable de sesión "crlf".*
> ```php
> <? if( isset($_POST['crlf']) &&
>       isset($_SESSION['crlf']) &&
>       $_POST['crlf'] == $_SESSION['crlf']
>    ) ?>
>```

#### Variables en las rutas
Este sistema permite definir rutas con bloques de contenido variable. Este contenido variable se enviará al callback en forma de argumentos.

```php
 <?
   Router::get("/", [HomeController::class, 'index'], "index");

   Router::get("/id/{var1}", [HomeController::class, 'vars']);
   Router::get("/{var1}/{var2}", [HomeController::class, 'vars2']);
   Router::post("/{var1}", [HomeController::class, 'vars']);
```

El sistema primero tratará de encontrar coincidencias literales. Si no las encuentra, buscará si la ruta solicitada se ajusta a alguna de las rutas con variables.

Se debe tener en cuenta que es importante el orden en el que se declaren las rutas; puesto que si hay dos coincidencias posibles, saltará la primera de las dos.

```php
 <?
   //En este ejemplo, siempre saltará la primera ruta, porque /id/{var1}
   //también da coincidencia con esta

   Router::get("/{var1}/{var2}", [HomeController::class, 'vars2']);
   Router::get("/id/{var1}", [HomeController::class, 'vars']);
```

#### Otros métodos de *Router*
**getInstance():** Aunque todas las funciones públicas son estáticas (por lo que no se necesita tener acceso directo a la instancia del router), esta función devuelve la instancia al router.

**has(string):** Consulta si está registrada alguna ruta con el nombre indicado.

**path(string, <[dict]>):** Devuelve la ruta asociada al nombre indicado. Aunque es opcional, se puede añadir un diccionario con variables que añadir a la ruta.

*Ejemplo:*

```php
      <? $user = "usuario"; $pass = "qwertyuiop"; ?>
      <li><a href="<? echo Router::path("users", compact("user", "pass"));?>">2 variables</a></li>
```

La ruta generada por este ejemplo sería:

      http://midominio.com/variables?user=usuario&pass=qwertyuiop

> *Si la ruta no existe, da error. Se debe comprobar primero si la ruta existe.*

**resolve():** Llama al callback asociado a la ruta a la que se ha accedido.

### Request
Request es la clase asociada a la información que envía el usuario. Por el momento, sólo contiene las variables enviadas por GET y por POST.

Para acceder a una variable enviada, sólo hay que acceder a ella como si de un miembro se tratara:
```php
      Request::getInstance()->variable;
```
Se puede exigir que una variable exista y contenga algo para continuar la ejecución. La función **Required([])** permite indicar qué variables se requiere que existan. En caso de, al menos, una de ellas no exista o no contenga datos, se vuelve a la página anterior.
```php
      Request::required(["var1", "var2", "var3", ...]);
```
### View
View es el sistema de vistas y plantillas que se ha implementado para facilitar la tarea de implementar la interfaz de la aplicación web.

Por el momento, el sistema incluye cuatro directivas que pueden añadirse a las vistas y plantillas:

 * **@extend(plantilla):** indica que la vista o plantilla hereda de la plantilla indicada.
 * **@yield(contenido):** indica que en ese lugar se colocará el contenido de una sección con ese nombre.
 * **@section(contenido):** indica el inicio de una sección con ese nombre.
 * **@endsection(contenido):** indica el final de una sección con ese nombre.
 * **@crlf:** genera el input oculto con el token de SecPOST. *Es obligatorio incluirlo en todo formulario cuyo action llame a una ruta SecPOST.*

#### Funciones de View

**view(string, \<[dict]\>):** devuelve el texto resultante de ejecutar la vista indicada. Opcionalmente se puede enviar un diccionario con variables. Más adelante, se aplicará un *extract* a dicho diccionario, convirtiendo su contenido en variables independientes; por lo que la vista porá acceder a ellas de manera independiente.

**redirect(string):** Esta función redirige a la url indicada.

**back():** La función back devuelve una cabecera que obliga a volver a la págna anterior.

**fail():** La función fail sirve para abortar la ejecución devolviendo un mensaje de error. Por el momento arroja siempre el error 404, cuya vista se encuentra en "views/404.view".

#### Ejemplo de uso

##### layout.view
```html
    <html>
        <head>
            <title>Rossetta</title>
        </head>
        <body>
            @yield(content)
            <hr/>
            <footer>
              Puedes ver el código en el siguiente repositorio: <a href="https://github.com/yawin123/sw" target="_blank">https://github.com/yawin123/sw</a>
            </footer>
        </body>
    </html>
```
##### variables.view
```html
    @extends(layout)

    @section(content)
        <h1>Variables</h1>
        <p>En esta vista puedes ver listadas las variables GET que has proporcionado<p>
        <p>Haz click <a href="<? echo Router::path("inicio");?>">aquí</a> para volver al index.</p>
        <? if(isset($vars))
        {?>
            <p>
                <table border="1px solid" style="text-align: center;">
                    <tr>
                       <th>Nombre</th>
                       <th>Valor</th>
                    </tr>
                    <? foreach($vars as $k => $v)
                    {?>
                        <tr><td><? echo $k;?></td><td><? echo $v;?></td></tr>
                    <?}?>
                </table>
            </p>
        <?}?>
    @endsection(content)
```

### Base de datos
Se ha creado una clase BD para gestionar la conexión con la base de datos. Posee dos funciones principales:

 * **select(string):** Esta función ejecuta la query proporcionada y, como asume que es una select, devuelve el resultado de la ejecución.
 * **execQuery(string):** Esta función es para ejecutar todas aquellas querys que no dan como resultado una serie de datos (update, insert, delete,...). Si la ejecución de la query da error, devuelve el error.

#### Modelos
Se está diseñando un sistema de Modelos para abstraer del uso de la base de datos.

Cada modelo heredará de *Model* y en su constructor deberá llamar al constructor padre indicando cuáles son sus columnas. Del siguiente modo:
```php
    class User extends Model
     {
         public function __construct()
         {
             parent::__construct(["username", "password", "email", "rango"]);
         }
     }
```
> La tabla del modelo deberá tener como nombre el nombre del modelo en minúsculas seguido de una *s*.
>
> Siguiendo con el modelo de ejemplo, la tabla del modelo *User* se llamaría *users*.

> Todos los modelos deben contener una columna llamada *id* que no se especificará en el constructor.

##### Funciones miembro
* **first():** Devuelve el primer modelo que coincida con la query.
* **get():** Devuelve un array con todas las instancias que coincidan con la query construída. Si no había query construída, devuelve todas las instancias.
* **where($field, $operator, $value):** añade una cláusula *WHERE* a la query construída. Si no hay query construída, genera primero una query *SELECT * from tabla*.
* **andwhere($field, $operator, $value):** añade una cláusula *AND WHERE* a la query construída. Si no hay query construída, genera primero una query *SELECT * from tabla* y añade la condición sin la cláusula *AND*.
* **ordeby($field, <$order>):** añade una cláusula *ORDER BY* a la query construída. Si no hay query construída, genera primero una query *SELECT * from tabla*.

* **delete():** Elimina el modelo de la base de datos. *Esta función no es estática.*
* **save():** Inserta o actualiza el modelo la base de datos. *Esta función no es estática.*
