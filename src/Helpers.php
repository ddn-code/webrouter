<?php
namespace ddn\api\router\Helpers;
// use Pug\Pug;
use Phug;

function p_debug($msg, ...$args) {
    if (defined('DEBUG') && DEBUG) {
        echo "<pre class=\"debug\">";
        echo sprintf($msg, ...$args);
        echo "</pre>";
    }
}

function pre_var_dump(...$vars) {
    echo "<pre class=\"debug\">";
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo "</pre>";
}

function pugrender($fname, $variables = []) {
    /*
    $pug = new Pug([
        // here you can set options
    ]);
    $pug->displayFile($fname, $variables);            
    */
    Phug::displayFile($fname,
        $variables,
    );
}

/** 
 * Class used to manage a list and call the same function to each of the elements in it
 * (*) it is similar to array_walk
 */
class ProxyList {
    public function __construct($list, $fail_on_nomethod = false) {
        $this->list = $list;
        $this->fon = $fail_on_nomethod;
    }

    public function __call($method_name, $args) {
        foreach ($this->list as $proxy) {
            if (method_exists($proxy, $method_name)) {
                return call_user_func_array([$proxy, $method_name], $args);
            } else {
                p_debug("method $method_name not found in proxy $proxy");
            }
        }
    }
}
?>
