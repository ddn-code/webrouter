<?php
use ddn\api\router\Router;
use ddn\api\router\Renderer;
use ddn\api\router\Op;

require_once('vendor/autoload.php');

// First create the router for our application; it is a simple router thet we must manage
$router = new ddn\api\router\Router("_OPERATION");

if (defined('__CUSTOM_ROUTES') && (is_callable(__CUSTOM_ROUTES)))
    call_user_func_array(__CUSTOM_ROUTES, [ $router ]);

class SayHello extends ddn\api\router\Op {

    function _default_op() {
        echo "executed the default op";
    }
}

class OpUser extends ddn\api\router\Op {
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
        echo "would set:<br>\n- user to {$_POST['name']}<br>\n- email to {$_POST['email']}<br>";
        echo "executed the update data op<br>";
    }
    function _update_password() {
        echo "executed the update password op";
    }
    function _default_op() {
        echo "executed the default op";
    }
}

ddn\api\Renderer::set_default("template/renderer.pug");
$router->add("/", 'SayHello', 'template/example.pug');
$router->add("/user", 'OpUser', 'template/user.pug');
$ops = $router->exec();
if ($ops === false) {
    header("HTTP/1.0 404 Not Found");
    echo "Not found";
    exit;
}

$ops->render();
