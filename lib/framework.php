<?php

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Initial setup
 * @package framework
 * @subpackage setup
 */

require APP_PATH.'lib/modules.php';
require APP_PATH.'lib/config.php';
require APP_PATH.'lib/auth.php';
require APP_PATH.'lib/oauth2.php';
require APP_PATH.'lib/session.php';
require APP_PATH.'lib/format.php';
require APP_PATH.'lib/router.php';
require APP_PATH.'lib/request.php';
require APP_PATH.'lib/cache.php';
require APP_PATH.'lib/output.php';
require APP_PATH.'lib/crypt.php';
require APP_PATH.'lib/db.php';
require APP_PATH.'lib/servers.php';

if (!class_exists('Hm_Functions')) {
    /**
     * Used to override built in functions that break unit tests
     * @package framework
     * @subpackage setup
     */
    class Hm_Functions {
        public static function setcookie($name, $value, $lifetime=0, $path='', $domain='', $secure=false, $html_only='') {
            return setcookie($name, $value, $lifetime, $path, $domain, $secure, $html_only);
        }
        public static function header($header) {
            return header($header);
        }
        public static function cease($msg=false) {
            die($msg);
        }
        public static function session_start() {
            return session_start();
        }
        public static function error_log($str) {
            error_log($str);
        }
    }
}
?>
