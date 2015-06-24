<?php

/**
 * Module management classes
 * @package framework
 * @subpackage modules
 */

/**
 * Trait used as the basic logic for module management
 *
 * Two classes use this trait, Hm_Handler_Modules and Hm_Output_Modules.
 * These are the interfaces module sets use (indirectly) to interact with a request
 * and produce output to the browser.
 */
trait Hm_Modules {

    /* holds the module to page assignment list */
    private static $module_list = array();

    /* current module set name, used for error tracking and limiting php file inclusion */
    private static $source = false;

    /* a retry queue for modules that fail to insert immediately */
    private static $module_queue = array();

    /* queue for delayed module insertion for all pages */
    private static $all_page_queue = array();

    /**
     * Queue a module to be added to all defined pages
     * @param string $module the module to add
     * @param bool $logged_in true if the module requires the user to be logged in
     * @param string $marker the module to insert before or after
     * @param string $placement "before" or "after" the $marker module
     * @param string $source the module set containing this module
     * return void
     */
    public static function queue_module_for_all_pages($module, $logged_in, $marker, $placement, $source) {
        self::$all_page_queue[] = array($module, $logged_in, $marker, $placement, $source);
    }

    /**
     * Process queued modules and add them to all pages
     * @return void
     */
    public static function process_all_page_queue() {
        foreach (self::$all_page_queue as $mod) {
            self::add_to_all_pages($mod[0], $mod[1], $mod[2], $mod[3], $mod[4]);
        }
    }

    /**
     * Load a complete formatted module list
     * @param array $mod_list list of module assignments
     * @return void
     */
    public static function load($mod_list) {
        self::$module_list = $mod_list;
    }

    /**
     * Assign the module set name
     * @param string $source the name of the module set (imap, pop3, core, etc)
     * @return void
     */
    public static function set_source($source) {
        self::$source = $source;
    }

    /**
     * Add a module to every defined page
     * @param string $module the module to add
     * @param bool $logged_in true if the module requires the user to be logged in
     * @param string $marker the module to insert before or after
     * @param string $placement "before" or "after" the $marker module
     * @param string $source the module set containing this module
     * @return void
     */
    public static function add_to_all_pages($module, $logged_in, $marker, $placement, $source) {
        foreach (self::$module_list as $page => $modules) {
            if (!preg_match("/^ajax_/", $page)) {
                self::add($page, $module, $logged_in, $marker, $placement, true, $source);
            }
        }
    }

    /**
     * Add a module to a single page
     * @param string $page the page to assign the module to
     * @param string $module the module to add
     * @param bool $logged_in true if the module requires the user to be logged in
     * @param string $marker the module to insert before or after
     * @param string $placement "before" or "after" the $marker module
     * @param bool $queue true to attempt to re-insert the module later on failure
     * @param string $source the module set containing this module
     * @return void
     */
    public static function add($page, $module, $logged_in, $marker=false, $placement='after', $queue=true, $source=false) {
        $inserted = false;
        if (!array_key_exists($page, self::$module_list)) {
            self::$module_list[$page] = array();
        }
        if (array_key_exists($page, self::$module_list) && array_key_exists($module, self::$module_list[$page])) {
            Hm_Debug::add(sprintf("Already registered module re-attempted: %s", $module));
            return;
        }
        if (!$source) {
            $source = self::$source;
        }
        if ($marker) {
            $inserted = self::insert_at_marker($marker, $page, $module, $logged_in, $placement, $source);
        }
        else {
            self::$module_list[$page][$module] = array($source, $logged_in);
            $inserted = true;
        }
        if (!$inserted) {
            if ($queue) {
                self::$module_queue[] = array($page, $module, $logged_in, $marker, $placement, $source);
            }
            else {
                Hm_Debug::add(sprintf('failed to insert module %s on %s', $module, $page));
            }
        }
    }

    /**
     * Insert a module before or after another one
     * @param string $marker the module to insert before or after
     * @param string $page the page to assign the module to
     * @param string $module the module to add
     * @param bool $logged_in true if the module requires the user to be logged in
     * @param string $placement "before" or "after" the $marker module
     * @param string $source the module set containing this module
     * @return void
     */
    private static function insert_at_marker($marker, $page, $module, $logged_in, $placement, $source) {
        $inserted = false;
        $mods = array_keys(self::$module_list[$page]);
        $index = array_search($marker, $mods);
        if ($index !== false) {
            if ($placement == 'after') {
                $index++;
            }
            $list = self::$module_list[$page];
            self::$module_list[$page] = array_merge(array_slice($list, 0, $index), 
                array($module => array($source, $logged_in)),
                array_slice($list, $index));
            $inserted = true;
        }
        return $inserted;
    }

    /**
     * Replace an already assigned module with a different one
     * @param string $target module name to replace
     * @param string $replacement module name to swap in
     * @param string $page page to replace assignment on, try all pages if false
     * @return void
     */
    public static function replace($target, $replacement, $page=false) {
        if ($page) {
            if (array_key_exists($page, self::$module_list) && array_key_exists($target, self::$module_list[$page])) {
                self::$module_list[$page] = self::swap_key($target, $replacement, self::$module_list[$page]);
            }
        }
        else {
            foreach (self::$module_list as $page => $modules) {
                if (array_key_exists($target, $modules)) {
                    self::$module_list[$page] = self::swap_key($target, $replacement, self::$module_list[$page]);
                }
            }
        }
    }

    /**
     * Helper function to swap the key of an array and maintain it's value
     * @param string $target array key to replace
     * @param string $replacement array key to swap in
     * @param array $modules list of modules
     * @return array new list with the key swapped out
     */
    private static function swap_key($target, $replacement, $modules) {
        $keys = array_keys($modules);
        $values = array_values($modules);
        $size = count($modules);
        for ($i = 0; $i < $size; $i++) {
            if ($keys[$i] == $target) {
                $keys[$i] = $replacement;
                $values[$i][0] = self::$source;
                break;
            }
        }
        return array_combine($keys, $values);
    }

    /**
     * Attempt to insert modules that initially failed
     * @return void
     */
    public static function try_queued_modules() {
        foreach (self::$module_queue as $vals) {
            self::add($vals[0], $vals[1], $vals[2], $vals[3], $vals[4], false, $vals[5]);
        }
    }

    /**
     * Delete a module from the internal list
     * @param string $page page to delete from
     * @param string $module module name to delete
     * @return void
     */
    public static function del($page, $module) {
        if (array_key_exists($page, self::$module_list) && array_key_exists($module, self::$module_list[$page])) {
            unset(self::$module_list[$page][$module]);
        }
    }

    /**
     * Return all the modules assigned to a given page
     * @param string $page the request name
     * @return array list of assigned modules
     */
    public static function get_for_page($page) {
        $res = array();
        if (array_key_exists($page, self::$module_list)) {
            $res = array_merge($res, self::$module_list[$page]);
        }
        return $res;
    }

    /**
     * Return all modules for all pages
     * @return array list of all modules
     */
    public static function dump() {
        return self::$module_list;
    }
}

/**
 * Class to manage all the input processing modules
 */
class Hm_Handler_Modules { use Hm_Modules; }

/**
 * Class to manage all the output modules
 */
class Hm_Output_Modules { use Hm_Modules; }

/**
 * MODULE SET FUNCTIONS
 *
 * This is the functional interface used by module sets to
 * setup data handlers and output modules in their setup.php files.
 * They are easier to use than dealing directly with the class instances
 */ 

/**
 * Add a module set name to the input processing manager
 * @param string $source module set name
 * @return void
 */
function handler_source($source) {
    Hm_Handler_Modules::set_source($source);
}

/**
 * Add a module set name to the output module manager
 * @param string $source module set name
 * @return void
 */
function output_source($source) {
    Hm_Output_Modules::set_source($source);
}

/**
 * Replace an already assigned module with a different one
 * @param string $type either output or handler
 * @param string $target module name to replace
 * @param string $replacement module to swap in
 * @param string $page request id, otherwise try all page names
 * $return void
 */
function replace_module($type, $target, $replacement, $page=false) {
    if ($type == 'handler') {
        Hm_Handler_Modules::replace($target, $replacement, $page);
    }
    elseif ($type == 'output') {
        Hm_Output_Modules::replace($target, $replacement, $page);
    }
}

/**
 * Add an input handler module to a specific page
 * @param string $mod name of the module to add
 * @param bool $logged_in true if the module should only fire when logged in
 * @param string $source the module set containing the module code
 * @param string $marker the module name used to determine where to insert
 * @param string $placement "before" or "after" the $marker module name
 * @param bool $queue true if the module should be queued and retryed on failure
 * @return void
 */
function add_handler($page, $mod, $logged_in, $source=false, $marker=false, $placement='after', $queue=true) {
    Hm_Handler_Modules::add($page, $mod, $logged_in, $marker, $placement, $queue, $source);
}

/**
 * Add an output module to a specific page
 * @param string $mod name of the module to add
 * @param bool $logged_in true if the module should only fire when logged in
 * @param string $source the module set containing the module code
 * @param string $marker the module name used to determine where to insert
 * @param string $placement "before" or "after" the $marker module name
 * @param bool $queue true if the module should be queued and retryed on failure
 * @return void
 */
function add_output($page, $mod, $logged_in, $source=false, $marker=false, $placement='after', $queue=true) {
    Hm_Output_Modules::add($page, $mod, $logged_in, $marker, $placement, $queue, $source);
}

/**
 * Add an input or output module to all possible pages
 * @param string $type either output or handler
 * @param string $mod name of the module to add
 * @param bool $logged_in true if the module should only fire when logged in
 * @param string $source the module set containing the module code
 * @param string $marker the module name used to determine where to insert
 * @param string $placement "before" or "after" the $marker module name
 * @return void
 */
function add_module_to_all_pages($type, $mod, $logged_in, $source, $marker, $placement) {
    if ($type == 'output') {
        Hm_Output_Modules::queue_module_for_all_pages($mod, $logged_in, $marker, $placement, $source);
    }
    elseif ( $type == 'handler') {
        Hm_Handler_Modules::queue_module_for_all_pages($mod, $logged_in, $marker, $placement, $source);
    }
}
