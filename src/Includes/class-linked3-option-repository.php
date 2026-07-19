<?php
namespace Linked3\Includes;
if (!defined('ABSPATH')) exit;
/**
 * Option repository.
 *
 * @package    Linked3
 * @subpackage Linked3.Includes
 * @since      27.1.0
 */
final class Linked3_Option_Repository
{
    private static $instance = null;
    private $cache = [];
    public static function instance() { if (self::$instance === null) self::$instance = new self(); return self::$instance; }
    private function __construct() {}
    private function key($key) { return LINKED3_OPTION_PREFIX . $key; }
    public function get($key, $default = false) { $full = $this->key($key); if (isset($this->cache[$full])) return $this->cache[$full]; $val = get_option($full, $default); $this->cache[$full] = $val; return $val; }
    public function get_string($key, $default = '') { $v = $this->get($key, $default); return is_string($v) ? $v : (string)$v; }
    public function get_int($key, $default = 0) { $v = $this->get($key, $default); return (int)$v; }
    public function get_bool($key, $default = false) { $v = $this->get($key, $default); return (bool)$v; }
    public function get_array($key, $default = []) { $v = $this->get($key, $default); return is_array($v) ? $v : (array)$v; }
    public function set($key, $value) { $full = $this->key($key); $r = update_option($full, $value); $this->cache[$full] = $value; return $r; }
    public function delete($key) { $full = $this->key($key); unset($this->cache[$full]); return delete_option($full); }
    public function exists($key) { return $this->get($key, null) !== null; }
    public function clear_cache() : void { $this->cache = []; }
}
