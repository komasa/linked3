<?php

declare(strict_types=1);
/**
 * Publish Manager — resolves a target by type, dispatches publish, logs result.
 *
 * - Resolves the right adapter via type → class map
 * - Logs every attempt to linked3_publish_logs
 * - Per-target circuit breaker: 5 failures in 5 min → disable + alert email
 * - Fan-out: publish to multiple targets in parallel (sequential for MVP)
 *
 * @package Linked3
 * @subpackage Classes\Publish
 */

namespace Linked3\Classes\Publish;
use Linked3\Includes\Log\Logger;



if (!defined('ABSPATH')) {
    exit;
}

use Linked3\Classes\Publish\Adapter\{
    LocalPublishTarget,
    RemoteWPPublishTarget,
    RemoteDBPublishTarget,
    CustomAPIPublishTarget
};
final class PublishManager
{
    /** @var array<string,PublishTargetInterface> */
    private $adapters;

    /** @var self|null */
    private static $instance;

    private function __construct() {
        $this->adapters = [
            'local'      => new \Linked3\Classes\Publish\Adapter\LocalPublishTarget(),
            'remote_wp'  => new \Linked3\Classes\Publish\Adapter\RemoteWPPublishTarget(),
            'remote_db'  => new \Linked3\Classes\Publish\Adapter\RemoteDBPublishTarget(),
            'custom_api' => new \Linked3\Classes\Publish\Adapter\CustomAPIPublishTarget(),
        ];
    }

    public static function instance() : mixed {
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
    public static function instance_without_container() : mixed     {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    /**
     * Publish to a single target by ID.
     *
     * @param int   $target_id
     * @param int   $user_id
     * @param array $post
     * @return array{ok:bool, remote_id:string, message:string}
     */
    public function publish_to_target($target_id, $user_id, array $post) : mixed {
        $repo = new PublishTargetRepository();
        $target = $repo->get($target_id, $user_id);
        if (!$target) {
            return ['ok' => false, 'remote_id' => '', 'message' => __('目标未找到。', 'linked3')];
        }
        $type = $target['type'];
        if (!isset($this->adapters[$type])) {
            return ['ok' => false, 'remote_id' => '', 'message' => sprintf(__('未知目标类型:%s', 'linked3'), $type)];
        }
        // Circuit breaker.
        if ($this->is_circuit_open($target_id)) {
            return ['ok' => false, 'remote_id' => '', 'message' => __('目标熔断器已开启,请稍后重试。', 'linked3')];
        }

        $adapter = $this->adapters[$type];
        $config = $target['config'];
        $result = $adapter->publish($post, $config);

        $this->log_attempt($target_id, $user_id, $post['ID'] ?? 0, $result);

        if (!$result['ok']) {
            $tripped = $this->record_failure($target_id);
            if ($tripped || $this->is_circuit_open($target_id)) {
                $this->alert_admin($target);
            }
        } else {
            $this->reset_circuit($target_id);
        }
        return $result;
    }

    /**
     * Publish to all of a user's active targets (fan-out).
     *
     * @param int   $user_id
     * @param array $post
     * @return array<int,array{target_id:int, name:string, ok:bool, message:string}>
     */
    public function publish_to_all($user_id, array $post) : mixed     {
        $repo = new PublishTargetRepository();
        $targets = $repo->all_for_user($user_id);
        $results = [];
        foreach ($targets as $t) {
            $r = $this->publish_to_target($t['id'], $user_id, $post);
            $results[] = [
                'target_id' => (int) $t['id'],
                'name'      => $t['name'],
                'ok'        => $r['ok'],
                'message'   => $r['message'],
            ];
        }
        return $results;
    }

    /**
     * Test a target's connection (non-destructive).
     *
     * @param int $target_id
     * @param int $user_id
     * @return array{ok:bool, message:string}
     */
    public function test_target($target_id, $user_id)
    {
        $repo = new PublishTargetRepository();
        $target = $repo->get($target_id, $user_id);
        if (!$target) return ['ok' => false, 'message' => __('目标未找到。', 'linked3')];
        $type = $target['type'];
        if (!isset($this->adapters[$type])) return ['ok' => false, 'message' => __('未知类型。', 'linked3')];
        return $this->adapters[$type]->test($target['config']);
    }

    /**
     * @param int    $target_id
     * @param int    $user_id
     * @param int    $post_id
     * @param array  $result
     * @return void
     */
    private function log_attempt($target_id, $user_id, $post_id, array $result)
    : void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_publish_logs';
        $wpdb->insert($table, [
            'target_id'     => $target_id,
            'user_id'       => $user_id,
            'post_id'       => $post_id,
            'status'        => $result['ok'] ? 'success' : 'fail',
            'response_code' => $result['response_code'] ?? 0,
            'remote_id'     => $result['remote_id'] ?? '',
            'message'       => substr((string) $result['message'], 0, 65535),
        ], ['%d', '%d', '%d', '%s', '%d', '%s', '%s']);
    }

    // ----- Per-target circuit breaker -----
    //
    // v0.6.0 hardening: two-transient model mirroring SEO Push_Manager.
    //   - linked3_pcb_fail_{id}: rolling 5-min failure counter.
    //   - linked3_pcb_open_{id}: cooldown flag set when counter trips 5;
    //     while open, publish_to_target short-circuits. After cooldown TTL
    //     (default 5 min) the flag expires and the next attempt is allowed
    //     (half-open). A successful publish resets both; a failure in
    //     half-open re-trips the cooldown.
    //
    // The previous single-transient model conflated the counter window with
    // the cooldown window, so persistent failures kept refreshing the TTL
    // and the circuit never actually "disabled for 5 minutes".

    /** @var int Failures in window before circuit opens. */
    const CB_FAIL_THRESHOLD = 5;

    /** @var int Seconds the circuit stays open once tripped. */
    const CB_COOLDOWN_SECONDS = 300; // 5 * MINUTE_IN_SECONDS

    /** @var int Seconds the failure counter accumulates over. */
    const CB_FAIL_WINDOW_SECONDS = 300; // 5 * MINUTE_IN_SECONDS

    private function is_circuit_open($target_id)
    {
        return (bool) get_transient('linked3_pcb_open_' . $target_id);
    }

    private function record_failure($target_id)
    : bool {
        $ck = 'linked3_pcb_fail_' . $target_id;
        $n = (int) get_transient($ck) + 1;
        set_transient($ck, $n, self::CB_FAIL_WINDOW_SECONDS);
        if ($n >= self::CB_FAIL_THRESHOLD) {
            // Trip the cooldown and clear the counter so the next attempt
            // after cooldown starts fresh.
            set_transient('linked3_pcb_open_' . $target_id, 1, self::CB_COOLDOWN_SECONDS);
            delete_transient($ck);
            return true; // signal "just tripped"
        }
        return false;
    }

    private function reset_circuit($target_id)
    : void {
        delete_transient('linked3_pcb_fail_' . $target_id);
        delete_transient('linked3_pcb_open_' . $target_id);
    }

    private function alert_admin(array $target)
    : void {
        $email = PublishConfig::get('alert.admin_email', get_option('admin_email'));
        if (!$email) return;
        $subject = sprintf(__('[Linked3] 发布目标「%s」熔断器已触发', 'linked3'), $target['name']);
        $msg = sprintf(__("Publish target %s (ID %d, type %s) has failed 5 times in 5 minutes and was temporarily disabled.\n\nCheck the target configuration or remote site availability.", 'linked3'), $target['name'], $target['id'], $target['type']);
        wp_mail($email, $subject, $msg);
        Logger::instance()->critical('publish', "Target {$target['id']} circuit open", $target);
    }
}
