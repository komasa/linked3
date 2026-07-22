<?php

declare(strict_types=1);
namespace Linked3\Includes;
if (!defined('ABSPATH')) exit;
/**
 * Request.
 *
 * @package    Linked3
 * @subpackage Linked3.Includes
 * @since      27.1.0
 */
final class Request
{
    public static function string($key, $default = '', $source = 'any') { $v = self::raw($key, $source); return $v === null ? $default : sanitize_text_field(wp_unslash($v)); }
    public static function textarea($key, $default = '') { $v = self::raw($key, 'post'); return $v === null ? $default : sanitize_textarea_field(wp_unslash($v)); }
    public static function int($key, $default = 0, $source = 'any') { $v = self::raw($key, $source); return $v === null ? $default : (int)$v; }
    public static function bool($key, $default = false, $source = 'any') { $v = self::raw($key, $source); return $v === null ? $default : filter_var($v, FILTER_VALIDATE_BOOLEAN); }
    public static function array($key, $default = [], $source = 'any') { $v = self::raw($key, $source); if (!is_array($v)) return $default; return array_map('sanitize_text_field', wp_unslash($v)); }
    public static function email($key, $default = '') { $v = self::string($key); return $v ? sanitize_email($v) : $default; }
    public static function url($key, $default = '') { $v = self::string($key); return $v ? esc_url_raw($v) : $default; }
    private static function raw($key, $source = 'any') { if ($source === 'post' && isset($_POST[$key])) return $_POST[$key]; if ($source === 'get' && isset($_GET[$key])) return $_GET[$key]; if ($source === 'any') { if (isset($_POST[$key])) return $_POST[$key]; if (isset($_GET[$key])) return $_GET[$key]; if (isset($_REQUEST[$key])) return $_REQUEST[$key]; } return null; }
    public static function has($key, $source = 'any') { return self::raw($key, $source) !== null; }
    public static function method() { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); }
    public static function is_cli() { return defined('WP_CLI') && WP_CLI; }
}
