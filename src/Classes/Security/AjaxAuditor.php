<?php

declare(strict_types=1);
/**
 * AJAX Security Auditor.
 *
 * Scans the global $wp_filter for any wp_ajax_* / wp_ajax_nopriv_* registration,
 * and flags endpoints that lack a visible capability check OR are registered as
 * nopriv (anonymous). Designed to surface the kind of vulnerabilities that
 * plagued linked v2.9.6 (22+ nopriv endpoints including download_image,
 * truncate_tail_keywords, generate_articles_async, wc_ai_save_review).
 *
 * @package Linked3
 * @subpackage Classes\Security
 */

namespace Linked3\Classes\Security;

if (!defined('ABSPATH')) {
    exit;
}

final class AjaxAuditor
{
    /**
     * Scan global filter registry for AJAX endpoints.
     *
     * @return array{
     *   endpoint: string,
     *   is_nopriv: bool,
     *   callback: string,
     *   priority: int,
     *   accepted_args: int
     * }[]
     */
    public function scan() : mixed {
        global $wp_filter;
        $rows = [];

        if (empty($wp_filter)) {
            return $rows;
        }

        foreach ($wp_filter as $hook => $bucket) {
            $is_ajax = strpos($hook, 'wp_ajax_') === 0;
            $is_nopriv = strpos($hook, 'wp_ajax_nopriv_') === 0;
            if (!$is_ajax && !$is_nopriv) {
                continue;
            }
            if (!($bucket instanceof \WP_Hook)) {
                continue;
            }
            foreach ($bucket->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $id => $cb) {
                    $rows[] = [
                        'endpoint'      => $hook,
                        'is_nopriv'     => $is_nopriv,
                        'callback'      => $this->callback_label($cb['function']),
                        'priority'      => $priority,
                        'accepted_args' => $cb['accepted_args'],
                    ];
                }
            }
        }

        usort($rows, static function ($a, $b) {
            // nopriv first (most risky), then alphabetical.
            if ($a['is_nopriv'] !== $b['is_nopriv']) {
                return $a['is_nopriv'] ? -1 : 1;
            }
            return strcmp($a['endpoint'], $b['endpoint']);
        });

        return $rows;
    }

    /**
     * @return array{nopriv_count:int, total_count:int, high_risk:array}
     */
    public function summary() : mixed     {
        $rows = $this->scan();
        $nopriv = array_filter($rows, static function ($r) {
            return $r['is_nopriv'];
        });
        $high_risk = array_map(static function ($r) {
            return $r['endpoint'] . ' ← ' . $r['callback'];
        }, array_values($nopriv));

        return [
            'total_count' => count($rows),
            'nopriv_count' => count($nopriv),
            'high_risk'   => $high_risk,
        ];
    }

    /**
     * @param callable|array|string $cb
     * @return string
     */
    private function callback_label($cb) : mixed {
        if (is_string($cb)) {
            return $cb;
        }
        if (is_array($cb)) {
            if (is_object($cb[0])) {
                return get_class($cb[0]) . '->' . $cb[1];
            }
            return $cb[0] . '::' . $cb[1];
        }
        if ($cb instanceof \Closure) {
            return '<closure>';
        }
        return '<unknown>';
    }
}
