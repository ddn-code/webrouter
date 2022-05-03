<?php
namespace ddn\api\router;
use function ddn\api\router\Helpers\pugrender;
use ddn\api\Helpers;

require_once("Helpers.php");

define('APP2REST_VAR_PATTERN', "/^<(?<optional>\?|)(?<name>[a-zA-Z_][a-zA-Z_0-9]*)(?:\s*=\s*(?<default>(?:'[^']*'|\"(?:[^\"\\\]|\\\.)*\"|[^'\"][^>]*))|)>$/");
define('APP2REST_VAR_QUERY_SUBST', "/<(?<name>(?:#|)[a-zA-Z][a-zA-Z0-9_]*)(?:\s*=\s*(?<default>(?:'[^']*'|\"(?:[^\"\\\]|\\\.)*\"|[^'\"][^>]*))|)\s*>/");

/**
 * Notation for the URL
 * 
 * /path/to/<param1>/<?param2>/<?param3=default>/or/other/
 * 
 * > usage in function (for almost any field): 
 *      <param1>
 *      <param1=default value>
 */

/**
 * Trims the URL, removing the beginning and ending '/' and the splits the URL into parts
 *   using '/' as a divider. It also trims the spaces in the parts.
 * @param url the URL to clean and split
 * @return the divided URL
 */
function clean_and_split_url($url) {
    return array_map(function($x) {return trim($x); }, explode('/', trim($url, "/")));
}

class Renderer {
    protected static $default_renderer = null;

    public static function set_default($renderer) {
        self::$default_renderer = $renderer;
    }

    public static function get_renderer($renderer) {
        if (is_null(self::$default_renderer)) {
            Rendered::$default_renderer = function ($view) { echo($view); };
        }
        if (is_null($renderer)) {
            return self::$default_renderer;
        }
        else {
            return $renderer;
        }
    }

    public function __construct($route, $handler) {
        $this->route = $route;
        $this->handler = $handler;
    }

    public function view() {
        if ($this->route->view !== null) {
            if (is_callable($this->route->view)) {
                call_user_func_array($this->route->view, [ $this->handler ] );
            } else {
                if (substr($this->route->view, -4) === '.pug') {
                    // Render the view using the pug runtime and make handler available to the view, as a variable
                    pugrender($this->route->view, [
                        "_OP_HANDLER" => $this->handler
                    ]);
                } else {
                    // Make handler avaliable in the view
                    global $_OP_HANDLER;
                    $_OP_HANDLER = $this->handler;
                    require_once($this->route->view);
                }
            }
            return true;
        } else {
            return null;
        }
    }

    /**
     * Gets the view into a string
     *  (see method view)
     */
    public function view_to_string() {
        ob_start();
        $retval = $this->view();
        $result = ob_get_clean();
        if ($retval === null)
            return null;
            
        return $result;
    }

    public function render() {
        // Get the view
        $view = $this->view_to_string();
        $renderer = Renderer::get_renderer($this->route->renderer);
        if ($view !== null) {
            if (is_callable($renderer)) {
                $result = call_user_func_array($renderer, [ $view, $this->handler ] );
                if ($result === false) 
                    return false;
            } else {
                // Make the view available for the renderer
                /*
                if (substr($renderer, -4) === '.pug') {
                    // Render the view using the pug runtime and make handler available to the view, as a variable
                    pugrender($renderer, [
                        "_VIEW" => $view,
                        "_OP_HANDLER" => $this->handler
                    ]);
                } else {
                    // Make handler avaliable in the view
                    global $_OP_HANDLER;
                    global $_VIEW;
                    $_VIEW = $view;
                    $_OP_HANDLER = $this->handler;
                    require_once($renderer);
                }
                */
            }
        }
        return true;
    }
}

class RouteDefinition {
    /**
     * The URL to which this function will serve
     */
    protected $url = null;
    /**
     * The regular expression used to match the incoming urls
     */
    protected $re_expression = null;
    /**
     * General configuration for the function
     */
    protected $config = [];

    public $view = null;
    public $renderer = null;

    /**
     * @param url url pattern in which the function may be triggered (URL pattern:    /path/to/<parameter>/or/<other_parameter/<?optional_parameter=default>
     * @param classname the class to instantiate, to handle the operation (will be instantiated and called the method 'do')
     * @param view the view to use as a result in the renderer
     */
    public function __construct($url, $classname, $view = null, $renderer = null) {

        if ($classname === null) {
            $classname = "ddn\\api\\Router\\Op";
        }

        $this->classname = $classname;
        $this->view = $view;
        $this->renderer = $renderer;

        if (empty($url)) {
            $this->url = "/";
            $this->re_expression = "/.*/";
            $this->param_default_value = [];
            return;
        }

        // URL in which the function may respond
        $this->url = trim($url, "/");
        $url_p = clean_and_split_url($url);

        // The regular expression for the URL
        $this->re_expression = "";

        // Make sure that there is a placeholder for the parameters
        $this->param_default_value = [];

        // Now identify the parameters and build the regular expression for the URL
        foreach ($url_p as $part) {
            if ((strlen($part) > 0) && ($part[0] === '<')) {
                if (preg_match(APP2REST_VAR_PATTERN, $part, $matches) !== 1) {
                    throw new \Exception(_p("invalid variable name %s", $part[0]));
                }

                $optional = ($matches["optional"] === '?');
                $default = $matches["default"]??null;

                if ($default !== null) {
                    if ((($default[0] === '"') && ($default[-1]==='"')) || (($default[0] === "'") && ($default[-1]==="'"))) {
                        $default = substr($default, 1, -1);
                    }
                }

                $param = $matches["name"];

                // Ensure that the definition of the parameters is complete
                if (!array_key_exists($param, $this->param_default_value)) {
                    $this->param_default_value[$param] = null;
                } else {
                    throw new \Exception(__("param names must be unique in an url"));
                }

                // Store the default value if provided
                if (($optional) && ($default !== null)) {
                    $this->param_default_value[$param] = $default;
                }

                // Prepare the regular expression for the parameter
                $re_expression = "\/(?<${param}>[^\/]+)";
                if ($optional === true) {
                    $re_expression = "(${re_expression}|)";
                }
            } else {
                $re_expression = "\/$part";
            }
            $this->re_expression .= $re_expression;
        }

        // Remove the \/ at the beginning, because we'll get the URLs trimmed
        $this->re_expression = substr($this->re_expression, 2);
        $this->re_expression = "/^" . $this->re_expression . "$/";
    }

    /**
     * Checks whether the incoming URL matches the URL in which the function responds
     * @param incomingurl the URL to check
     * @return true if the URL matches the URL in which the function listens
     */
    public function check_url($incomingurl) {
        return preg_match($this->re_expression, $incomingurl);
    }

    /**
     * Function that prepares the parameter values for the incoming URL (if it matches the expression)
     *   and returns a new handler that has the parameters set.
     * @param incomingurl the URL to check
     * @return a new handler that has the parameters set or false if the incoming URL does not match the route
     */
    public function get_handler($incomingurl) {
        Helpers::p_debug("executing function for url: $incomingurl");

        // Now check wether the url matches the function's url and capture the parameters
        $match = preg_match($this->re_expression, $incomingurl, $matches);

        // If not matches, return false
        if ($match !== 1) {
            return false;
        }

        // Make substitutions for the parameters that are not in the URL but are provided in the configuration
        $possible_parameters = array_unique(array_keys($this->param_default_value));

        // We'll build the matches
        $param_values = [];
        foreach ($possible_parameters as $param) {
            $value = $matches[$param]??null;
            if (empty($value)) {
                $value = $this->param_default_value[$param];
            }
            // Even if empty, if set in config, will get a value
            if ($value !== null) {
                $param_values[$param] = $value;
            }
        }

        return new $this->classname($param_values);
    }    

    /** This is inherited from other project; has no sense here (at this time) 
     * 
     * This function is used to substitute the values in a text with the values of the parameters
     * 
     *  The format for the text is freeform but if a construction like <param> is used, that value is substituted for the parameter.
     *    There are special constructions:
     *    - <param> will be substituted with the value of the parameter (or blank if not set)
     *    - <param=value1> will be substituted with the value of the parameter (or value1 if not set in the parameteres)
     *    - <#q1> will be substituted with the value of the query (or blank if not set)
     *    - <#q1=value1> will be substituted with the value of the query (or value1 if not set in the query)
     * 
     * @param txt_original the text to substitute in
     * @param parameters the parameters to substitute
     * @param default_values the default values for the parameters
     * @param query the values in a query string
     * @return the text with the values substituted
    */
    private static function replace_values($txt_original, $parameters, $default_values, $query) {
        return preg_replace_callback(APP2REST_VAR_QUERY_SUBST, function ($match) use ($parameters, $default_values, $query) {
            $varname = $match["name"];
            if ($varname[0] === '#') {
                $values = $query;
                $defaults = [];
                $varname = substr($varname, 1);
            } else {
                $defaults = $default_values;
                $values = $parameters;
            }
            $value = null;
            if (array_key_exists($varname, $values)) {
                $value = $values[$varname];
            } else {
                $value = $match["default"]??null;
                if ($value === null) {
                    $value = $defaults[$varname]??null;
                } else {
                    if (($value[0] === '"') && ($value[-1] === '"')) {
                        $value = substr($value, 1, -1);
                    } else {
                        if (($value[0] === "'") && ($value[-1] === "'")) {
                            $value = substr($value, 1, -1);
                        }
                    }
                }
            }
            //Helpers::p_debug($varname, $value, $values, $defaults, $match["default"]??null);
            return "" . $value;
        }, $txt_original);                  
    }
    
    /**
     * This function is used to substitute the values in a text with the values of the parameters, recursively until no more substitutions are possible
     */
    private static function replace_values_multistage($txt_original, $parameters, $default_values, $query) {
        $new_txt = static::replace_values($txt_original, $parameters, $default_values, $query); 
    
        // Multi-stage subsitution enables to set a parameter as a value for a default value and then get it subsituted
        //  e.g. <p1=<p2>>
        //      in the 1st stage, p1 will be subsituted by <p2>
        //      if multistage, in the 2nd stage, p2 will be subsituted by its value
    
        if (MULTI_STAGE_SUBSTITUTION) {
            while ($new_txt !== $txt_original) {
                $txt_original = $new_txt;
                $new_txt = RouteDefinition::replace_values($txt_original, $parameters, $default_values, $query); 
            }
        }
        return $new_txt;    
    }
}

class Router {
    /**
     * Order of callbacks:
     * - pre-callback
     * - callback (i.e. $handler->do())
     * - onsuccess, onerror
     * - post-callback
     */
    public function __construct($path_varname, $folder = "") {
        /** The name of the variable in the "_GET" array from which to get the path */
        $this->_path_varname = $path_varname;
        $this->_routes = [];
        $this->_callbacks = [
            "onsuccess" => false,
            "onerror" => false,
            "precallback" => false,
            "postcallback" => false,    // If the result of this callback is true, the router will try to execute the next handler that matches the route
            "preview" => false,
            "postview" => false,
        ];
        $this->_folder = $folder;
    }

    public function set_folder($folder) {
        $this->_folder = $folder;
    }

    public function add($url, $classname, $template, $renderer = null) {
        if (!is_callable($template)) {
            if (! file_exists($template)) {
                $template = $this->_folder . "/" . $template;
            }
            if (! file_exists($template)) {
                throw new \Exception("Template file '$template' not found");
            }
            $template = function($handler) use ($template) {
                if (substr($template, -4) === '.pug') {
                    // Render the view using the pug runtime and make handler available to the view, as a variable
                    pugrender($template, [
                        "_OP_HANDLER" => $handler
                    ]);
                } else {
                    // Make handler avaliable in the view
                    global $_OP_HANDLER;
                    $_OP_HANDLER = $handler;
                    require_once($template);
                }
                return true;
            };
        }

        $renderer = Renderer::get_renderer($renderer);
        if (!is_callable($renderer)) {
            if (! file_exists($renderer)) {
                $renderer = $this->_folder . "/" . $renderer;
            }
            if (! file_exists($renderer)) {
                throw new \Exception("Renderer file '$renderer' not found");
            }
            $renderer = function($view, $handler) use ($renderer) {
                if (substr($renderer, -4) === '.pug') {
                    // Render the view using the pug runtime and make handler available to the view, as a variable
                    pugrender($renderer, [
                        "_VIEW" => $view,
                        "_OP_HANDLER" => $handler
                    ]);
                } else {
                    // Make handler avaliable in the view
                    global $_OP_HANDLER;
                    global $_VIEW;
                    $_VIEW = $view;
                    $_OP_HANDLER = $handler;
                    require_once($renderer);
                }    
                return true;
            };
        }
        $this->_routes[] = new RouteDefinition($url, $classname, $template, $renderer);
    }

    public function exec($values = null) {
        $route = $route ?? ($_GET[$this->_path_varname] ?? null);
        foreach ($this->_routes as $function) {
            if ($function->check_url($route)) {
                if (is_callable($this->_callbacks["precallback"])) {
                    $result = $this->_callbacks["precallback"]($route, $function);
                    if ($result === false) {
                        continue;
                    }
                }

                $handler = $function->get_handler($route);
                if ($handler !== false) {
                    $result = $handler->do($values);

                    if (($result !== false) && (is_callable($this->_callbacks["onsuccess"]))) {
                        $this->_callbacks["onsuccess"]($route, $function, $handler, $result);
                    } 
                    if (($result === false) && (is_callable($this->_callbacks["onerror"]))) {
                        $this->_callbacks["onerror"]($route, $function, $handler, $result);
                    }

                    // Call the "postcallback" function
                    if (is_callable($this->_callbacks["postcallback"])) {
                        $this->_callbacks["postcallback"]($route, $function, $handler, $result);
                    }

                    return new Renderer($function, $handler);
                }
            }
        }
        return false;
    }
}