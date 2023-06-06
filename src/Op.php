<?php
namespace ddn\api\router;

if (!defined('__WILDCARD_SUBOP'))
    define('__WILDCARD_SUBOP', "##__ANY_VALUE_FOUND__##");

class OpMessage {
    const ERROR = "error";
    const WARNING = "warning";
    const SUCCESS = "success";
    const INFO = "info";
    const DEBUG = "debug";

    public $message = null;
    public $type = null;
    public $data = null;

    public function __construct($message, $type, $data = null) {
        $this->message = $message;
        switch ($type) {
            case self::ERROR:
            case self::WARNING:
            case self::SUCCESS:
            case self::INFO:
            case self::DEBUG:
                $this->type = $type;
            default:
                $this->type = "info";
                break;
        }
        $this->type = $type;
        $this->data = $data;
    }

    public function __toString() {
        return "<div class=\"{$this->type}\">{$this->message}</div>";
    }
}

class Op {
    /**
     * The name of the operation (not really required; just for information)
     */
    const _NAME = null;
    protected $messages = [];

    /**
     * The result of the operation (null should mean that it has not been executed)
     */
    protected $result = null;
    /**
     * This is a function to set the result, and it is in the form of setter and getter for future enhancements
     * @param $r the result to set
     */
    protected function set_result($r) {
        $this->result = $r;
    }
    public function get_result() {
        return $this->result;
    }

    /**
     * Parameters for the operation, as an associative array
     */
    public $_PARAMETERS = [];
    
    /**
     * Basic definition of functions for this operation. The syntax is an array of
     *  "variable" => "function name" or "variable" => [ "function value" => "function name", ... ]
     * An example:  
     *  const _FUNCTIONS = [
     *         "login" => "_login",
     *         "logout" => "_logout",
     *         "update" => [
     *             "" => "_update_data",
     *             "password" => "_update_password",
     *         ]
     *  ];
    */
    const _FUNCTIONS = [];

    /** 
     * Permisions required to execute this operation. This relies on the usage the ACLManager class
     *   where the permissions have to be defined, using groups and special features of the user that
     *   is using the operation.
     * The basic syntax is
     *   _PERMS = [
     *      "acl_entry_in_ACLManager_1",    // Common entries begin with non @ and the ACL entry must exist in the ACLManager
     *      "acl_entry_in_ACLManager_2",
     *      ...
     *      "@group_name",      // Raw entried begin with @
     *      "@logged"
     *      ...
     *   ]
     * If a _PERMS array is defined for an operation, it will be checked and if it is not fulfilled,
     *   the operation will not be executed, and the forbidden function will be called.
     * (*) _PERMS relies on the special methods of this object
     *      - get_app_user
     *      - forbidden
     *      - is_owner
     */ 
    const _PERMS=[];

    /**
     *  (* reminder) operations can be registered; the operations means that if a variable coming from 
     *      the client is set to a value (or any), a function will be executed instead of the default 
     *      operation. 
     * 
     * When executing an operation, where to get the values to check the operations. The possibilities
     *   are:
     *      "GET"
     *      "POST"
     *      "GET+POST" (if a variable is set both in GET and POST, the value in GET has priority)
     *      "POST+GET" (if a variable is set both in GET and POST, the value in POST has priority)
     */
    const _DEFAULT_VALUES_FOR_OPS="POST";

    /**
     * The route for which the op was instantiated
     */
    protected $__route = null;

    /**
     * Static function to get the user that is being using the application (e.g. the logged in user)
     *   (*) If there is a global function get_app_user, it will be called
     */
    static function _get_app_user() {
        if (function_exists("get_app_user")) {
            return get_app_user();
        } else {
            return null;
        }
    }

    /**
     * Static function that notifies the operation that the user has forbidden access to it
     *   (*) the default implementation calls the global function redirect_to_forbidden if it exists
     */
    static function _forbidden($die = true) {
        if (function_exists("redirect_to_forbidden")) {
            redirect_to_forbidden();
        } else {
            self::_html_error(403, $die);
        }
    }

    /**
     * Generates a header for an error code and sends it to the client; if [die] is set to true, the app will exit
     * @param [code] the error code
     * @param [die] whether to die or not after sending the header
     */
    static function _html_error($code, $die = true) {
        $text = self::_html_eror_to_text($code);
        $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
        header($protocol . ' ' . $code . ' ' . $text);
        if ($die) {
            echo $text;
            exit;
        }
    }

    /**
     * Obtains the text that corresponds to a HTML error code
     */
    static function _html_eror_to_text($code) {
        switch ($code) {
            case 100: $text = 'Continue'; break;
            case 101: $text = 'Switching Protocols'; break;
            case 200: $text = 'OK'; break;
            case 201: $text = 'Created'; break;
            case 202: $text = 'Accepted'; break;
            case 203: $text = 'Non-Authoritative Information'; break;
            case 204: $text = 'No Content'; break;
            case 205: $text = 'Reset Content'; break;
            case 206: $text = 'Partial Content'; break;
            case 300: $text = 'Multiple Choices'; break;
            case 301: $text = 'Moved Permanently'; break;
            case 302: $text = 'Moved Temporarily'; break;
            case 303: $text = 'See Other'; break;
            case 304: $text = 'Not Modified'; break;
            case 305: $text = 'Use Proxy'; break;
            case 400: $text = 'Bad Request'; break;
            case 401: $text = 'Unauthorized'; break;
            case 402: $text = 'Payment Required'; break;
            case 403: $text = 'Forbidden'; break;
            case 404: $text = 'Not Found'; break;
            case 405: $text = 'Method Not Allowed'; break;
            case 406: $text = 'Not Acceptable'; break;
            case 407: $text = 'Proxy Authentication Required'; break;
            case 408: $text = 'Request Time-out'; break;
            case 409: $text = 'Conflict'; break;
            case 410: $text = 'Gone'; break;
            case 411: $text = 'Length Required'; break;
            case 412: $text = 'Precondition Failed'; break;
            case 413: $text = 'Request Entity Too Large'; break;
            case 414: $text = 'Request-URI Too Large'; break;
            case 415: $text = 'Unsupported Media Type'; break;
            case 500: $text = 'Internal Server Error'; break;
            case 501: $text = 'Not Implemented'; break;
            case 502: $text = 'Bad Gateway'; break;
            case 503: $text = 'Service Unavailable'; break;
            case 504: $text = 'Gateway Time-out'; break;
            case 505: $text = 'HTTP Version not supported'; break;
            default:
                exit('Unknown http status code "' . htmlentities($code) . '"');
            break;
        }
        return $text;
    }

    /**
     * Static function that notifies the operation that the function execution suceeded
     *   (*) the default implementation calls the global function redirect_to_succees if it exists
     */
    static function _success($die = true) {
        if (function_exists("redirect_to_succes")) {
            redirect_to_succes();
        } else {
            self::_html_error(200, $die);
        }
    }

    /**
     * Static function that notifies the operation that a resource is not found
     *   (*) the default implementation calls the global function redirect_to_notfound if it exists
     */
    static function _notfound($die = true) {
        if (function_exists("redirect_to_notfound")) {
            redirect_to_notfound();
        } else {
            self::_html_error(404, $die);
        }
    }

    /**
     * Static function that notifies the operation that a user is not authorized to access to a resource
     *   (*) the default implementation calls the global function redirect_to_unauthorized if it exists
     */
    static function _unauthorized($die = true) {
        if (function_exists("redirect_to_unauthorized")) {
            redirect_to_notfound();
        } else {
            self::_html_error(401, $die);
        }
    }

    /**
     * Static function that notifies the operation that a user is not authorized to access to a resource
     *   (*) the default implementation calls the global function redirect_to_unauthorized if it exists
     */
    static function _internalerror($die = true) {
        if (function_exists("redirect_to_internalerror")) {
            redirect_to_notfound();
        } else {
            self::_html_error(500, $die);
        }
    }

    /**
     * Static function that notifies the operation that the request made by the client is incomplete or bad received
     *   (*) the default implementation calls the global function redirect_to_badrequest if it exists
     */
    static function _badrequest($die = true) {
        if (function_exists("redirect_to_badrequest")) {
            redirect_to_notfound();
        } else {
            self::_html_error(400, $die);
        }
    }

    /**
     * Static function that sets the content type to json and dumps an object using json_encode function
     */
    static function _dump_json($obj, $die = true) {
        header("Content-type: application/json");
        print(json_encode($obj));
        if ($die) {
            exit;
        }
    }

    /**
     * Static function that retrieves the input of the query as an object, decoded using json_decode function
     *   (if the object is not json, it will return "null")
     */
    static function _read_json($die = true) {
        if (stripos( $_SERVER["CONTENT_TYPE"] , "application/json" ) !== 0) {
            self::_html_error(415, $die);
            return null;
        }
        $body = file_get_contents("php://input");
        return json_decode($body, true);
    }

    /**
     * Obtains the route for which the Op was instantiated
     */
    public function get_route() {
        return $this->__route;
    }
    
    /**
     * Builds the object
     * @param the route for which the Op was instantiated
     * @param parameters associative array of "parameter" => "value"
     */
    public function __construct($route, $parameters = []) {
        $this->__route = $route;
        $this->_PARAMETERS = $parameters;

        // Create the functions from the static definition
        foreach (static::_FUNCTIONS as $function => $op) {
            if (is_array($op)) {
                foreach ($op as $subop => $subop_function) {
                    $this->add_op($function, $subop_function, $subop);
                }
            } else {
                $this->add_op($function, $op);
            }
        }
    }

    /**
     * Executes the operation
     * @return object false if no operation has been executed; an arbitrary value otherwise
     */
    public function do($values = null) {
        // Do nothing
        $this->result = null;
        if (! $this->_auth()) {
            $this->_unauthorized();
            return false;
        }
        $this->_do($values);
        return $this->result;
    }

    /**
     * Function that obtains the current user that is currently logged in the application
     */
    public function get_app_user() {
        return static::_get_app_user();
    }

    /** 
     * Function to notify the user that he has forbidden access to this function
     */
    public function forbidden() {
        static::_forbidden();
    }

    protected function _auth() {
        foreach (static::_PERMS as $perm) {
            $raw = false;
            if (substr($perm, 0, 1) === '@') {
                $perm = substr($perm, 1);
                $raw = true;
            }

            $negate = false;
            if (substr($perm, 0, 1) === '!') {
                $perm = substr($perm, 1);
                $negate = true;
            }

            if ($raw)
                $res = ACLManager::raw_check($perm, $this, $this->get_app_user());
            else
                $res = ACLManager::check($perm, $this, $this->get_app_user());

            if ($res === $negate) return false;
        }
        return true;
    }

    /**
     * Function that is called in case that one of the profiles required is "owner". The operation MUST decide whether 
     *   the current user is the owner of the object with which it is dealing.
     * @return is_owner true in case that the user should be recognised as "owner"; false in other case.
     */
    protected function is_owner() {
        return false;
    }

    /** Function that carries out with the operation
     * @return result false in case that the operation failed; any other object if the operation suceeded
     */
    protected function _do($values = null) {
        if ($this->_pre_do($values) !== true) return false;

        return $this->_do_ops($values);
    }

    protected function _pre_do($values = null) {
        return true;
    }
    /**
     * Obtains the number of error messages in the operation
     * @return the number of messages of type "error"
     */
    public function error_count() {
        return count(array_filter($this->messages, function($m) { return $m->type === OpMessage::ERROR; }));
    }
    /**
     * Retrieves the error messages in the operation
     * @return an array of OpMessage objects of type "error"
     */
    public function get_error_messages() {
        return array_filter($this->messages, function($m) { return $m->type === OpMessage::ERROR; });
    }
    /**
     * These are shortcuts to add a message and return the operation
     * 
     * The default value for the return is "true", because if the operation added a message, it is assumed to have been executed.
     *   When allowing multiple operations for one operation, if one of them returns "true", it means that more operations may
     *   be executed, but once one of them returns "false", the operation chain is considered to have failed.
     * 
     * The idea is to avoid multiple statements in the operation, like the next on
     *      if (error) {
     *          $this->add_message("this is an error");
     *          $this->set_result(false);
     *          return false;
     *      }
     *      if (othererror) {
     *          $this->add_message("this is other error");
     *          $this->set_result(false);
     *          return false;
     *      }
     *      $this->add_message("this is correct");
     *      $this->set_result(true);
     *      return false;
     * 
     * And convert it into
     *      if (error) return $this->add_message("this is an error");
     *      if (othererror) return $this->add_message("this is other error");
     *      return $this->add_message("this is correct");
     * 
     * @param message the message to add
     * @param $result the result of the operation
     * @param $retval the return value of the operation (defaults to false, to stop the operation chain)
     * @return $retval
     */
    protected function _add_message($message, $type, $result = true, $retval = false) {
        $this->messages[] = new OpMessage($message, $type, $result);
        $this->set_result($result);
        return $retval;
    }
    protected function add_error($message, $result = false, $retval = false) {
        return $this->_add_message($message, OpMessage::ERROR, $result, $retval);
    }
    protected function add_warning($message, $result = true, $retval = false) {
        return $this->_add_message($message, OpMessage::WARNING, $result, $retval);
    }
    protected function add_info($message, $result = true, $retval = false) {
        return $this->_add_message($message, OpMessage::INFO, $result, $retval);
    }
    protected function add_success($message, $result = true, $retval = false) {
        return $this->_add_message($message, OpMessage::SUCCESS, $result, $retval);
    }
    
    protected function clear_messages() {
        $this->messages = [];
    }

    public function get_messages() {
        return $this->messages;
    }

    // Array of internal ops, that will be executed depending on the values of POSTS
    protected $_ops = [];

    /**
     * Method that adds an operation to be executed if a var appears in the _POST array, with a specific value
     * @param postvar Variable that is expected to appear
     * @param function String that contains the function name of the "this" object to call (or an array that can be callable using call_user_func)
     * @param postval Value that is expected in order to trigger the function (if it is null, it will call the function independent from the value found in the _POST array)
     */
    protected function add_op($postvar, $function, $postval = null) {
        if (is_string($function))
            $function = [ $this, $function ];

        if (!isset($this->_ops[$postvar]))
            $this->_ops[$postvar] = [];

        if ($postval === null)
            $postval = __WILDCARD_SUBOP;
        $this->_ops[$postvar][$postval] = $function;
    }

    protected function remove_op($postvar, $postval = null) { 
        if (!isset($this->_ops[$postvar]))
            return;

        if ($postval === null)
            $postval = __WILDCARD_SUBOP;

        if (isset($this->_ops[$postvar][$postval]))
            unset($this->_ops[$postvar][$postval]);

        if (count($this->_ops[$postvar]) == 0)
            unset($this->_ops[$postvar]);
    }

    /** 
     * Function that it is executed if no other op is executed
    */
    function _default_op() {
    }

    /**
     * Method that executes the operations, if defined.
     * 
     * Each operation must return "true" to continue executing the "op" chain; otherwise this method will stop executing the chain and will return that value
     *  (e.g. return null when a single op is executed and will not want to continue)
     * 
     */
    protected function _do_ops($values = null) {
        if ($values === null) {
            switch (static::_DEFAULT_VALUES_FOR_OPS) {
                case "POST":
                    $values = $_POST;
                    break;
                case "GET":
                    $values = $_GET;
                    break;
                case "GET+POST":
                    $values = $_GET + $_POST;
                    break;
                case "POST+GET":
                    $values = $_POST + $_GET;
                    break;
                default:
                    throw new \Exception("invalid value for _DEFAULT_VALUES_FOR_OPS");
            }
            
        }

        $opsexecuted = 0;

        foreach ($this->_ops as $op => $subops) {
            if (isset($values[$op])) {
                $execute = false;
                $value = $values[$op];
                if (isset($subops[$value]))
                    $execute = $subops[$value];
                elseif (isset($subops[__WILDCARD_SUBOP]))
                    $execute = $subops[__WILDCARD_SUBOP];

                if ($execute !== false) {
                    $result = call_user_func($execute);
                    if ($result !== true)
                        return $result;
                    
                    $opsexecuted++;
                }
            }
        }

        if ($opsexecuted === 0)
            $this->_default_op();

        return true;
    }
}

/**
 * This is a class dedicated to create REST APIs. Defines the default operation to attend to the REST methods.
 * (*) the class keeps the ability to register other operations, as usual
 */
class RESTOp extends Op {
    /**
     * This would be a function similar to the add_op, but enabling to register handlers for operations, depending on the values of the query or the values of the form
     */
    protected function add_rest_op($postvar, $function, $postval = null, $methods = [ "GET", "POST" ]) {
        throw new \Exception("not implemented");
    }
    function POST() {
        self::_html_error(501, true);
    }
    function GET() {
        self::_html_error(501, true);
    }
    function PUT() {
        self::_html_error(501, true);
    }
    function HEAD() {
        self::_html_error(501, true);
    }
    function DELETE() {
        self::_html_error(501, true);
    }
    function CONNECT() {
        self::_html_error(501, true);
    }
    function OPTION() {
        self::_html_error(501, true);
    }
    function TRACE() {
        self::_html_error(501, true);
    }
    function _default_op() {
        switch ($_SERVER["REQUEST_METHOD"]) {
            case "POST":
                return $this->POST();
                break;
            case "GET":
                return $this->GET();
                break;
            case "PUT":
                return $this->PUT();
                break;
            case "HEAD":
                return $this->HEAD();
                break;
            case "DELETE":
                return $this->DELETE();
                break;
            case "CONNECT":
                return $this->CONNECT();
                break;
            case "OPTION":
                return $this->OPTION();
                break;
            case "TRACE":
                return $this->TRACE();
                break;
            default:
                self::_html_error(405, true);
        }
    }
}
?>