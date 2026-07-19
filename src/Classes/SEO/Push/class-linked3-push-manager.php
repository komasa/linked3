<?php
/**
 * Push manager — orchestrates engine selection + logging + circuit breaker.
 *
 * Mirrors v2.9.6 batch_push pattern (one entry point that fans out to
 * all configured engines). Hardening over v2.9.6:
 *   - All HTTP via Linked3_Safe_Remote (SSL verify ON, no raw cURL)
 *   - Per-engine circuit breaker: 5 failures in 5 min → cooldown 1h
 *   - Every push logged to linked3_push_logs (success / fail / message)
 *   - Plan gating delegated to the AJAX action layer (Push_Manager does
 *     not call License_Service directly — keeps it usable from non-AJAX
 *     contexts like cron)
 *
 * @package Linked3
 * @subpackage Classes\SEO\Push
 */

namespace Linked3\Classes\SEO\Push;

use Linked3\Classes\SEO\Linked3_SEO_Config;
use Linked3\Includes\Log\Linked3_Logger;



if (!defined('ABSPATH')) {
    exit;
}
final class Linked3_Push_Manager
{
    /** @var self|null */
    private static $instance;

    /** @var Linked3_Logger */
    private $log;

    private function __construct() {
        $this->log = Linked3_Logger::instance();
    }

    /**
     * @return self
     */
    public static function instance() : mixed {
        if (null === self::$instance) {
            // v4.4.6: delegate to the DI container when available.
            if (class_exists('\\Linked3\\Includes\\Linked3_Container')) {
                $container = \Linked3\Includes\Linked3_Container::instance();
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
    public static function instance_without_container() : mixed     {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Push a single URL to all configured engines (or one if $engine set).
     *
     * @param string      $url
     * @param string|null $engine  Specific engine slug, or null = all configured.
     * @return array<string,array{ok:bool,code:int,pushed:int,message:string}>
     */
    public function push_url($url, $engine = null) : mixed {
        $url = esc_url_raw($url);
        if ($url === '') {
            return [];
        }
        $engines = $engine
            ? [Linked3_Push_Engine_Factory::get($engine)]
            : array_values(Linked3_Push_Engine_Factory::all());
        return $this->push_to_engines([$url], $engines);
    }

    /**
     * Push a batch of URLs.
     *
     * @param string[]    $urls
     * @param string|null $engine
     * @return array<string,array{ok:bool,code:int,pushed:int,message:string}>
     */
    public function push_batch(array $urls, $engine = null) : mixed     {
        $urls = array_values(array_filter(array_map('esc_url_raw', $urls)));
        if (empty($urls)) {
            return [];
        }
        $engines = $engine
            ? [Linked3_Push_Engine_Factory::get($engine)]
            : array_values(Linked3_Push_Engine_Factory::all());
        return $this->push_to_engines($urls, $engines);
    }

    /**
     * @param string[]               $urls
     * @param Linked3_Push_Engine[]  $engines
     * @return array<string,array{ok:bool,code:int,pushed:int,message:string}>
     */
    private function push_to_engines(array $urls, array $engines) : mixed {
        $out = [];
        foreach ($engines as $engine) {
            if (!$engine) {
                continue;
            }
            $slug = $engine->slug();
            if (!$engine->is_configured()) {
                $out[$slug] = ['ok' => false, 'code' => 0, 'pushed' => 0, 'message' => __('未配置。', 'linked3')];
                continue;
            }
            if ($this->is_circuit_open($slug)) {
                $out[$slug] = ['ok' => false, 'code' => 0, 'pushed' => 0, 'message' => __('引擎因连续失败正在冷却中。', 'linked3')];
                continue;
            }
            try {
                $result = $engine->push($urls);
            } catch (\Exception $e) {
                $result = [
                    'ok'      => false,
                    'code'    => 0,
                    'body'    => '',
                    'message' => $e->getMessage(),
                    'pushed'  => 0,
                    'raw'     => null,
                ];
            }
            $status = $result['ok'] ? 'success' : 'fail';
            foreach ($urls as $u) {
                Linked3_Push_Log_Repository::insert([
                    'engine'        => $slug,
                    'url'           => $u,
                    'status'        => $status,
                    'response_code' => (int) $result['code'],
                    'response_body' => (string) $result['body'],
                    'message'       => (string) $result['message'],
                ]);
            }
            // Circuit breaker accounting.
            if ($result['ok']) {
                $this->reset_circuit($slug);
            } else {
                $this->record_failure($slug);
            }
            $this->log->info('seo', sprintf('Push to %s: %s', $slug, $status), ['urls' => $urls, 'result' => $result]);
            $out[$slug] = [
                'ok'      => (bool) $result['ok'],
                'code'    => (int) $result['code'],
                'pushed'  => (int) $result['pushed'],
                'message' => (string) $result['message'],
            ];
        }
        return $out;
    }

    /**
     * Per-engine failure counter. Once threshold reached, the engine
     * enters cooldown for `engine_cooldown` seconds.
     *
     * @param string $engine
     * @return void
     */
    private function record_failure($engine)
    : void {
        $threshold = (int) Linked3_SEO_Config::get('push.circuit_threshold', 5);
        $cooldown  = (int) Linked3_SEO_Config::get('push.engine_cooldown', HOUR_IN_SECONDS);
        $key = LINKED3_OPTION_PREFIX . 'push_fail_' . sanitize_key($engine);
        $count = (int) get_transient($key) + 1;
        set_transient($key, $count, 5 * MINUTE_IN_SECONDS);
        if ($count >= $threshold) {
            set_transient(LINKED3_OPTION_PREFIX . 'push_cooldown_' . sanitize_key($engine), 1, $cooldown);
            $this->log->warning('seo', sprintf('Engine %s entered cooldown after %d failures', $engine, $count));
        }
    }

    /**
     * @param string $engine
     * @return void
     */
    private function reset_circuit($engine)
    : void {
        delete_transient(LINKED3_OPTION_PREFIX . 'push_fail_' . sanitize_key($engine));
        delete_transient(LINKED3_OPTION_PREFIX . 'push_cooldown_' . sanitize_key($engine));
    }

    /**
     * @param string $engine
     * @return bool
     */
    private function is_circuit_open($engine) : mixed     {
        return (bool) get_transient(LINKED3_OPTION_PREFIX . 'push_cooldown_' . sanitize_key($engine));
    }
}
