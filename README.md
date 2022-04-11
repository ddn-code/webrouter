# DDN Web API
A library to ease the development of PHP web applications.

## Installation

TBD

```
composer require ddn/router dev-main
```

## Doc

### Base web application

The underlying idea is to provide tools to deal with URLs like the next one:

```
https://my.server/app/path/to/op?token=12345
```

The server points URL `https://my.server/app` to the `app` directory. In that folder, we'll need a file called `index.php`, which is the entry point for the application. In the folder we need a file `.htaccess`, used to configure the access to the application.

Example content for **.htaccess**:

```
RewriteEngine On
RewriteBase /
RewriteRule ^/index\.php$ - [L]
RewriteRule ^/favicon\.ico$ - [L]

# If it is a php file, but it is not index.php, pass it as _OPERATION parameter
RewriteCond %{REQUEST_URI} ^(.*)\.php$
RewriteCond %{REQUEST_FILENAME} !index.php$
RewriteRule ^(.*)$ index.php?_OPERATION=$1 [L,QSA]

# If it is other file that exists, serve it
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^(.*)$ - [L,QSA]

# Finally, if the file does not exist nor is a folder, pass it as _OPERATION parameter
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?_OPERATION=$1 [L,QSA]
```

This fragment of `.htaccess` configures the router to use the `index.php` file as entry point. Any file that is not `index.php` will be passed as `_OPERATION` get parameter to the `index.php` file.

And now we need a file called **`index.php`**:

```php
use ddn\api\Router;
use ddn\api\Renderer;
use ddn\api\Router\Op;

require_once('vendor/autoload.php');

// First create the router for our application; it is a simple router thet we must manage
$router = new ddn\api\Router("_OPERATION");

if (defined('__CUSTOM_ROUTES') && (is_callable(__CUSTOM_ROUTES)))
    call_user_func_array(__CUSTOM_ROUTES, [ $router ]);

class SayHello extends ddn\api\Router\Op {
    function _default_op() {
        echo "executed the default op";
    }
}

ddn\api\Renderer::set_default("template/renderer.pug");
$router->add("/", 'SayHello', 'template/example.pug');
$ops = $router->exec();
if ($ops === false) {
    header("HTTP/1.0 404 Not Found");
    echo "Not found";
    exit;
}

$ops->render();
```

### Web application model

A web application consists of:

- A set of _endpoints_ that define paths in the web application that will be served.
- A set of operations (i.e. classes derived from `Op` class) that implement the actions at an _endpoin_ (e.g. creating or deleting an object, log in a user, etc.).
- A set of _views_, that show the result of the execution of an opration.
- A set of _renderers_, that show the views.

Detaching the _renderers_ from the _views_ makes it easy to create an homogeneous web application. The _renderer_ can be understood as a _layout_ for the web application, and the _view_ is estrictly the output of a web operation.

If it is not needed this kind of distinction, it is possible to render each _view_ as a whole output and ignore the _renderer_ (i.e. define the renderer as `($view) => { echo $view; }`).

At the end:
- A _view_ is an html fragment that shows the output of an operation.
- A _renderer_ is a html layout in which a view is placed.

#### Workflow

When a request arrives to the web server
1. The router evaluates whether there is a route defined or not
2. If there is no route that matches the URI, finalize.
3. Retrieve the parameters from the URI and instantiate the `Op` class that implements the operation.
4. Call funcion `_do` from `Op`
5. `_do` will chech whether there is a sub-operation defined; if not, it will call `_do_default_operation`. If there is a sub-operation, it will be executed.
6. Generate the view for the operation
    - `pug files`: var `_OP_HANDLER` will be available.
    - other file or function: global var `$_OP_HANDLER` will be available
7. Render the final layout:
    - `pug files`: vars `_OP_HANDLER` and `_VIEW` will be available.
    - other file or function: global vars `$_OP_HANDLER` and `$_VIEW` will be available

(*) a view will not be generated unless the result of the `exec()` call is rendered (i.e. method `render()` is called).

### Router

The router is the main class for web applications. 

To create the web application, it is needed to create a _router_ and set the default renderer:

```php
// _OPERATION is the GET parameter in which the operations are included. If using the suggested setup, apache (or nginx) will set this parameter to the accessed path; so if requesting URI https://my.server/path/to/my/op?v=1, '_OPERATION' will be set to path/to/my/op, and 'v' will still be 1.
$router = new ddn\api\Router("_OPERATION");

// This is the default renderer, which contains the default layout for out application (it can be either a function, a php file, an html file or a pug file, which will be interpreted)
ddn\api\Renderer::set_default("template/renderer.pug");
```

Then we need to define our routes:

```php
// In path /hello we will have an operation, which will be served by class 'SayHello' and, after executing _do method of that class, we will show the view 'template/example.pug'. The renderer is the default (so we set it to null).
$router->add("/hello", 'SayHello', 'template/example.pug', null);
```

Once defined we need to exec the operation, according to the URI and render the output:

```php
$ops = $router->exec();
if ($ops === false) {
    header("HTTP/1.0 404 Not Found");
    echo "Not found";
    exit;
}

$ops->render();
```

#### Methods:
- `__construct($path_varname)`: Builds the object
    - $path_varname: is the name of the variable in the "_GET" array from which to get the path
- `add($url, $classname, $template)`: Adds a route the the router
    - $url: is the path definition that will match the route. It is possible to define the route using parameters:
        > /path/to/\<parameter\>/\<?optionalparam=default value\>/end
    - $classname: is the class that implements the operation (it is a `Op` subclass and/or it has to implement `_do` function). If null, there will not be any operation.
    - $template: is the template that shows the output of the operation.
- `exec()`: Checks if an URI endpoint has been hit and if it is, executes the operations and returns a `Renderer` object to be able to render the output.

### The `Op` class

The `Op` class is devoted to implement an operation in an end-point of the server. E.g if we define the next route:
`$router->add("/", 'SayHello', 'template/example.pug');`, if the accesed endpoint matches `/`, an instance of class `SayHello` will be created. Then its class `_do` will be called.

The execution of an operation may vary depending on the values set by the action of the user. This is why the `Op` class also includes helpers to execute different functions depending on the values of `$_GET` or `$_POST` supervars. 

In this way, the same class will be valid for multiple functions. E.g. it is possible to have a class `OpUsers` that implements `login`, `logout` or `update_data` depending on either a `$_POST` var is set and even depending on its value.

An example of class will be the next:

```php
class OpUser extends ddn\api\Router\Op {
    const _FUNCTIONS = [
        "login" => "_login",
        "logout" => "_logout",
        "update" => [
            "" => "_update_data",
            "password" => "_update_password",
        ]
    ];

    function _login() {
        echo "executed the login op";
    }
    function _logout() {
        echo "executed the logout op";
    }
    function _update_data() {
        $_POST["name"] = $_POST["name"]??null;
        $_POST["email"] = $_POST["email"]??null;
        echo "would set:<br>\n- user to ${_POST['name']}<br>\n- email to ${_POST['email']}<br>";
        echo "executed the update data op<br>";
    }
    function _update_password() {
        echo "executed the update password op";
    }
    function _default_op() {
        echo "executed the default op";
    }
}
```

The reading for `_FUNCTIONS` is:
- if executing this class with `_POST["login"]`, execute function `_login`
- if executing this class with `_POST["logout"]`, execute function `_login`
- if executing this class with `_POST["update"]` set to `password`, execute function `_update_password`
- if executing this class with `_POST["update"]` set to other thing than `password`, execute function `_update_password`
- else, execute `_default_op`

#### Permissions

It is possible to define the const array `_PERMS` that defines the permissions needed to grant the access to the execution of this operation.

If defined, the permissions are checked and if not met, the function `forbidden` of this operation will be executed.

Permissions rely on the usage of class `ACLManager` and the definition of the ACL entries depending on the membership of the user that is using the operation to different groups.

The permissions also rely on the implementation of several functions for the operation (either for the class or the object):
- `get_app_user`: returns the user that is using the operation. The user must implement `ACLUser` interface
- `is_owner`: returns wheter a user is the owner of the object with wich is dealing the operation.
- `forbidden`: notifies the user that the access to the operation is forbidden

It is possible to define permissions such as "grant access if the user is the owner", "grant access if the user is logged in", "grant access if the user belongs to admin group", etc.

(*) The definition of permissions is a richful automation feature, but complex to describe. So at this time, it is better to check the code than explaining.
