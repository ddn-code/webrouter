<?php
namespace ddn\api\router\Helpers;
// use Pug\Pug;
use ddn\api\Debug;

use Phug;

function get_globals() {
    $superglobals = [ "GLOBALS", "_SERVER", "_REQUEST", "_POST", "_GET", "_FILES", "_ENV", "_COOKIE", "_SESSION" ];
    return array_diff_key($GLOBALS, array_flip($superglobals));
}

function pugrender($fname, $variables = []) {
    /*
    $pug = new Pug([
        // here you can set options
    ]);
    $pug->displayFile($fname, $variables);            
    */
    Phug::displayFile($fname,
        get_globals() + $variables,
    );
}

function pugrender_s($fname, $variables = []) {
    /*
    $pug = new Pug([
        // here you can set options
    ]);
    $pug->displayFile($fname, $variables);            
    */
    return Phug::renderFile($fname,
        get_globals() + $variables,
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
                Debug::p_debug("method $method_name not found in proxy $proxy");
            }
        }
    }
}
?>
