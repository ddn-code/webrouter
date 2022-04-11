<?php

namespace ddn\api\Router;
use function ddn\api\pre_var_dump;

if (!defined('__WILDCARD_SUBOP'))
    define('__WILDCARD_SUBOP', "##__ANY_VALUE_FOUND__##");

class Op {
    /**
     * The name of the operation (not really required; just for information)
     */
    const _NAME = null;
    protected $messages = [];

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
    static function _forbidden() {
        if (function_exists("redirect_to_forbidden")) {
            redirect_to_forbidden();
        } else {
            header("HTTP/1.0 403 Forbidden");
            echo "Forbidden";
            exit;
        }
    }

    /**
     * Builds the object
     * @param parameters associative array of "parameter" => "value"
     */
    public function __construct($parameters = []) {
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
    public function do() {
        // Do nothing
        if (! $this->_auth()) {
            $this->forbidden();
            return false;
        }
        return $this->_do();
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
    protected function _do() {
        if ($this->_pre_do() !== true) return false;

        return $this->_do_ops();
    }

    protected function _pre_do() {
        return true;
    }

    protected function add_message($message, $retval = false) {
        $this->messages[] = $message;
        return $retval;
    }

    protected function clear_messages() {
        $this->messages = [];
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
        // $r = new stdClass();
        // $r->val = $postval;
        if (is_string($function))
            $function = [ $this, $function ];
        // $r->fnc = $function;
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
        if ($values === null)
            $values = $_POST;

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
?>