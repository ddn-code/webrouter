<?php

namespace ddn\api\Router;
use function ddn\api\pre_var_dump;

interface ACLUser {
    /**
     * Function that returns "true" if the user belongs to the group "group"
     * @param group the group to check
     * @return boolean true if the user belongs to the group "group"
     */
    public function is_a($group);
    /**
     * Function that returns "true" if the user is logged in the application
     * @return boolean true if the user is logged in the application
     */
    public function is_logged_in();
}

/**
 * The ACLManager is a class that manages the access control list (ACL) of the application.
 * It is used to check if a user has the permissions to execute an operation.
 * The ACL entries are created using function add_perm_acl
 * 
 *  ACLManager::add_perm_acl("ACL_ENTRY", [ "group1", "group2" ]);
 * 
 * A call to that function means that the ACL_ENTRY is fulfilled if every entry in the array is fulfilled. And the entries in the
 *   array refer to the groups of the user and two special conditions: "logged_in" and "owner", which are stated using the special
 *   entries "l" and "o", respectively.
 * 
 * A ! at the beginning of one entry negates the meaning of the entry: e.g.: !l means "not logged in", or "!admin" means "not admin".
 * 
 * To make this possible, it is needed a user class that implements the ACLUser interface, which is externally provided when calling
 *   to the ACLManager validation.
 * 
 * To configure the list of possible groups in the application, it is needed a global variable $__CUSTOM_GROUPS, which is an associative 
 *   array with the groups as keys and the description of the group as values.
 * 
 * e.g.
 *  $__CUSTOM_GROUPS = [
 *     'A' => 'advanced user',
 *  ];
 * 
 * If not configured the custom groups, the ACLManager will only accept the keywords "logged" or "owner" as valid entries for ACL entries.
 * 
 * The validation can be made using the function ACLManager::check or ACLManager::raw_check. In the first case, the user is validated using
 *   the ACL entries created using the function add_perm_acl. In the second case, the user is validated using the list of groups that are
 *   created in the system.
 * 
 * e.g.
 *  $res = ACLManager::check("ACL_ENTRY", $object, get_app_user());
 * 
 *    is the same than
 * 
 *  $res = ACLManager::raw_check(["group1", "group2"], $object, get_app_user());
 * 
 */
class ACLManager {
    static $__SPECIAL_GROUPS = [];
    static $__acl_manager = null;
    static $__PERMISSIONS = [];
    static $__GROUPS_TO_ASSIGN = [];
    static $__initialized = false;
    protected static $_perms = null;

    private static function initialize() {
        if (self::$__initialized) return;

        if (!defined('__')) {
            function __($x) {
                return $x;
            }
        }

        ACLManager::$__SPECIAL_GROUPS = [
            'logged' => __('authorized'),    // Special permissions
            'owner' => __('owner')          // Special permissions
        ];    

        global $__CUSTOM_GROUPS;
        if (! isset($__CUSTOM_GROUPS))
            $__CUSTOM_GROUPS = [];

        if (count(array_intersect_key(ACLManager::$__SPECIAL_GROUPS, $__CUSTOM_GROUPS)) > 0) {
            throw new \Exception("Custom groups intersect special groups");
        }
    
        ACLManager::$__PERMISSIONS = $__CUSTOM_GROUPS + ACLManager::$__SPECIAL_GROUPS;
        ACLManager::$_perms = [];

        ACLManager::$__initialized = true;
    }

    /**
     * This function should check whether $req is a valid group or not
     */
    public static function valid_req($req) {
        ACLManager::initialize();
        return isset(ACLManager::$__PERMISSIONS[$req]);
    }

    public static function groups() {
        ACLManager::initialize();
        return ACLManager::$__PERMISSIONS;
    }

    /**
     * Function that gets a valid entry array from a entry string
     */
    public static function valid_entry($entries) {
        ACLManager::initialize();

        if (!is_array($entries)) {
            $entries = [ $entries ];
        }

        foreach ($entries as $entry) {
            $negative = $entry[0] == '!';
            $entry = $negative ? substr($entry, 1) : $entry;

            if (!ACLManager::valid_req($entry)) {
                throw new \Exception("$entry is not a valid group or keyword for ACL (please adjust __CUSTOM_GROUPS)");
            }
        }

        return $entries;
    }

    public static function set_perm($perm, $entry) {
        ACLManager::initialize();
        ACLManager::$_perms[$perm] = static::valid_entry($entry);
    }

    public static function clear_perm($perm) {
        ACLManager::initialize();
        ACLManager::$_perms[$perm] = [];
    }

    public static function add_perm_acl($perm, $entry) {
        ACLManager::initialize();
        if (!isset(ACLManager::$_perms[$perm])) 
            ACLManager::$_perms[$perm] = [];

        ACLManager::$_perms[$perm] = array_merge(ACLManager::$_perms[$perm], static::valid_entry($entry));
    }

    /**
     * Evaluates an authorization entry. Every item in the entry MUST be met.
     * 
     *  - true: accept always
     *  - false: reject always
     *  - l: accept if is logged in
     *  - u: accept if is a user
     *  - a: accept if is an admin
     *  - o: accept if is the owner (as defined in the op by the call to is_owner)
     *  - !l: accept if is not logged in
     *  - !u: accept if is not an user
     *  - !a: accept if is not an admin
     *  - !o: accept if is not the owner
     * 
     * e.g.
     *   - _eval_auth_entry(['u', 'o']) means "if is a registered user and he is the owner"
     *   - _eval_auth_entry(['a']) means "if is an admin"
     * 
     * @return met true if all the requested profiles are met (an empty entry evaluates to 
     *              "true"); false if any of them fails).
     */
    protected static function _eval_auth_entry($entry, $object = null, $current_user = null) {
        ACLManager::initialize();
        if (!is_array($entry)) $entry = [ $entry ];
        foreach ($entry as $e) {
            if ($e === false) return false;
            if ($e === true) continue;
            switch ($e) {
                case '!logged': 
                    if ($current_user === null) throw new \Exception('login state permission required but no user is provided');
                    if (!method_exists($current_user, 'is_logged_in')) throw new \Exception('login state permission required but user does not have is_logged_in method');
                    if ($current_user->is_logged_in() === true) return false; break;
                case 'logged': 
                    if ($current_user === null) throw new \Exception('login state permission required but no user is provided');
                    if (!method_exists($current_user, 'is_logged_in')) throw new \Exception('login state permission required but user does not have is_logged_in method');
                    if ($current_user->is_logged_in() === false) return false; break;
                case '!owner': 
                    if ($object === null) throw new \Exception('owner permission required but no object is provided');
                    if ($object->is_owner() === true) return false; break;
                case 'owner': 
                    if ($object === null) throw new \Exception('owner permission required but no object is provided');
                    if ($object->is_owner() === false) return false; break;
                default:
                    if ($current_user === null) throw new \Exception('user group check required but no user is provided');
                    if (!method_exists($current_user, 'is_a')) throw new \Exception('user group check required but user does not have is_a method');

                    $negate = substr($e, 0, 1) === '!';
                    if ($negate)
                        $e = substr($e, 1);

                    $result = $current_user->is_a($e);
                    if ($result === $negate) return false;

                    break;
            }
        }
        return true;
    }

    public static function check($perm, $object = null, $user = null) {
        ACLManager::initialize();
        if (!isset(ACLManager::$_perms[$perm])) return false;
        foreach (ACLManager::$_perms[$perm] as $req) {
            if (static::_eval_auth_entry($req, $object, $user)) {
                return true;
            }
        }
        return false;
    }

    public static function raw_check($req, $object = null, $user = null) {
        ACLManager::initialize();
        if (static::_eval_auth_entry(ACLManager::valid_entry($req), $object, $user))
            return true;
        return false;
    }
}