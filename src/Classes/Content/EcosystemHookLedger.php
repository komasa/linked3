<?php

declare(strict_types=1);
/**
 * EcosystemHookLedger — Single source of truth for all WP hook registrations.
 *
 * This trait is `use`-ed by EcosystemAjax (Facade). Because PHP resolves
 * `__CLASS__` to the class that uses the trait (not the trait itself),
 * all `[__CLASS__, 'ajax_xxx']` callbacks resolve to EcosystemAjax::ajax_xxx()
 * — which are themselves provided by the other traits. This keeps the
 * hook→handler chain intact without any binding changes.
 *
 * @package Linked3\Content
 */

namespace Linked3\Classes\Content;

use Linked3\Includes\Log\Logger;

if (!defined('ABSPATH')) exit;

trait EcosystemHookLedger
{
    /**
     * Register all 17 AJAX endpoints. Called by `init` hook (priority 5).
     */
    public static function register() : void {
        add_action('wp_ajax_linked3_eco_synergy', [__CLASS__, 'ajax_synergy']);
        add_action('wp_ajax_linked3_eco_keywords', [__CLASS__, 'ajax_keywords']);
        add_action('wp_ajax_linked3_eco_content', [__CLASS__, 'ajax_content']);
        add_action('wp_ajax_linked3_eco_template_save', [__CLASS__, 'ajax_template_save']);
        add_action('wp_ajax_linked3_eco_image_save', [__CLASS__, 'ajax_image_save']);
        // v10.7.1: 全功能链新增端点
        add_action('wp_ajax_linked3_eco_hot_collect', [__CLASS__, 'ajax_hot_collect']);
        add_action('wp_ajax_linked3_eco_keywords_save', [__CLASS__, 'ajax_keywords_save']);
        // v16.0.14 [公理α/β]: 长尾词使用状态持久化 AJAX
        add_action('wp_ajax_linked3_eco_tail_used_save', [__CLASS__, 'ajax_tail_used_save']);
        add_action('wp_ajax_linked3_eco_longform_outline', [__CLASS__, 'ajax_longform_outline']);
        add_action('wp_ajax_linked3_eco_longform_section', [__CLASS__, 'ajax_longform_section']);
        add_action('wp_ajax_linked3_eco_csv_batch', [__CLASS__, 'ajax_csv_batch']);
        add_action('wp_ajax_linked3_eco_cron_enable', [__CLASS__, 'ajax_cron_enable']);
        add_action('wp_ajax_linked3_eco_cron_disable', [__CLASS__, 'ajax_cron_disable']);
        // v10.7.3: SOP闭环 — 保存草稿
        add_action('wp_ajax_linked3_eco_save_draft', [__CLASS__, 'ajax_save_draft']);
        // v10.7.4: 图片API保存 + 图片生成
        add_action('wp_ajax_linked3_save_image_api', [__CLASS__, 'ajax_save_image_api']);
        add_action('wp_ajax_linked3_eco_generate_images', [__CLASS__, 'ajax_generate_images']);

        Logger::instance()->info('ai', '[linked3 v10.7.1] Ecosystem AJAX registered (12 endpoints)');

    }
}

// ── Bootstrap: register on init (priority 5) ──────────────────────────
// This file-level call executes when EcosystemAjax.php does require_once
// on this file. The class name is a string literal so it resolves correctly
// regardless of which trait/method context loads this file.
add_action('init', ['\Linked3\Classes\Content\EcosystemAjax', 'register'], 5);
