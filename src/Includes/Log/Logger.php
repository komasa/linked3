<?php

declare(strict_types=1);
/**
 * Linked3 Logger.
 *
 * Lightweight Monolog-style logger with no external dependencies.
 * Channels: ai / cron / license / billing / security / publish / collect / chat / vector / general.
 * Files rotate by day; retention enforced by a daily cron.
 *
 * @package Linked3
 * @subpackage Log
 */

namespace Linked3\Includes\Log;

if (!defined('ABSPATH')) {
    exit;
}

final class Logger
{
    const LEVELS = ['debug' => 100, 'info' => 200, 'notice' => 250, 'warning' => 300, 'error' => 400, 'critical' => 500, 'alert' => 550, 'emergency' => 600];
    const CHANNELS = ['ai', 'cron', 'license', 'billing', 'security', 'publish', 'collect', 'chat', 'vector', 'general'];

    /** @var self|null */
    private static $instance = null;

    /** @var string */
    private $dir;

    /** @var int */
    private $min_level = 200; // info+

    /** @var bool Whether payload sanitisation is enabled. */
    private $sanitize = true;

    private function __construct() {
        $uploads = wp_upload_dir();
        $this->dir = trailingslashit($uploads['basedir']) . 'linked3-logs/';
        if (!is_dir($this->dir)) {
            wp_mkdir_p($this->dir);
        }
        // Protect logs from public web access.
        $htaccess = $this->dir . '.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Order deny,allow\nDeny from all\n"); // phpcs:ignore
        }
        $index = $this->dir . 'index.php';
        if (!file_exists($index)) {
            @file_put_contents($index, "<?php // Silence is golden."); // phpcs:ignore
        }

        $this->min_level = (int) apply_filters('linked3/log_min_level', 200);
        $this->sanitize = (bool) apply_filters('linked3/log_sanitize', true);
    }

    /**
     * @return self
     */
    public static function instance(): self
    {
        if (null === self::$instance) {
            // v4.4.6: delegate to the DI container when available.
            if (class_exists('\\Linked3\\Includes\\Container')) {
                $container = \Linked3\Includes\Container::instance();
                if ($container->has(self::class)) {
                    self::$instance = $container->get(self::class);
                    return self::$instance;
                }
            }
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construct the singleton WITHOUT going through the container.
     *
     * v4.4.6: used by the container's factory to avoid infinite recursion.
     *
     * @return self
     * @internal
     */
    public static function instance_without_container(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param string $channel
     * @param string $level
     * @param string $message
     * @param array  $context
     * @return void
     */
    public function log(string $channel, string $level, string $message, array $context = []): void {
        $level = strtolower($level);
        $level_value = self::LEVELS[$level] ?? 200;
        if ($level_value < $this->min_level) {
            return;
        }
        if (!in_array($channel, self::CHANNELS, true)) {
            $channel = 'general';
        }

        if ($this->sanitize && !empty($context)) {
            $context = PayloadSanitizer::sanitize_for_logging($context);
        }

        $line = sprintf(
            "[%s] %s.%s: %s %s\n",
            gmdate('Y-m-d\\TH:i:s\\Z'),
            strtoupper($channel),
            strtoupper($level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );

        $file = $this->dir . $channel . '-' . gmdate('Y-m-d') . '.log';
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX); // phpcs:ignore
    }

    /** @return void */ public function info($channel, $message, array $c = [])    : void { $this->log($channel, 'info', $message, $c); }
    /** @return void */ public function warning($channel, $message, array $c = [])  : void { $this->log($channel, 'warning', $message, $c); }
    /** @return void */ public function error($channel, $message, array $c = [])    : void { $this->log($channel, 'error', $message, $c); }
    /** @return void */ public function critical($channel, $message, array $c = []) : void { $this->log($channel, 'critical', $message, $c); }

    /**
     * Daily retention cron — delete log files older than N days.
     *
     * @param int $retention_days
     * @return int Files removed.
     */
    public function prune(int $retention_days = 30): int
    {
        $retention_days = max(1, (int) $retention_days);
        $cutoff = time() - $retention_days * DAY_IN_SECONDS;
        $removed = 0;
        foreach ((array) glob($this->dir . '*.log') as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file); // phpcs:ignore
                $removed++;
            }
        }
        return $removed;
    }
}
