<?php
namespace Linked3\Includes;
if (!defined('ABSPATH')) exit;
/**
 * Performance monitor.
 *
 * @package    Linked3
 * @subpackage Linked3.Includes
 * @since      27.1.0
 */
final class Linked3_Performance_Monitor
{
    private static $timers = []; private static $durations = []; private static $enabled = null;
    public static function enabled() { if (self::$enabled === null) self::$enabled = (defined('LINKED3_PERF_MONITOR') && LINKED3_PERF_MONITOR) || (defined('WP_DEBUG') && WP_DEBUG); return self::$enabled; }
    public static function start($key) : void { if (!self::enabled()) return; self::$timers[$key] = ['start' => microtime(true), 'memory' => memory_get_usage(true)]; }
    public static function end($key) { if (!self::enabled() || !isset(self::$timers[$key])) return 0.0; $t = self::$timers[$key]; unset(self::$timers[$key]); $d = microtime(true) - $t['start']; self::$durations[$key] = ['time' => $d, 'memory' => memory_get_usage(true) - $t['memory']]; return $d; }
    public static function get_stats() { return self::$durations; }
    public static function log_stats() : void { if (!self::enabled() || empty(self::$durations)) return; $s = []; foreach (self::$durations as $k => $d) $s[] = sprintf('%s: %.3fs', $k, $d['time']); $m = 'Perf: ' . implode(' | ', $s); if (class_exists('\Linked3\Includes\Linked3_Logger')) \Linked3_Logger::log($m, 'info'); elseif (function_exists('error_log')) error_log('[linked3] ' . $m); }
    public static function reset() : void { self::$timers = []; self::$durations = []; }
}
