<?php

declare(strict_types=1);
namespace Linked3\Classes\CognitiveOS\Ajax;
if (!defined('ABSPATH')) exit;
class COSAjaxEvolve
{
    public static function ajax_evolve_gen(): void
    {
        check_ajax_referer('linked3_cos', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }

        // v20.4-fix15: 演化单代超时调整 (配合 32B 模型)
        @set_time_limit(50);
        @ini_set('max_execution_time', '50');

        $problem  = isset($_POST['problem']) ? sanitize_textarea_field(wp_unslash($_POST['problem'])) : '';
        $gen      = isset($_POST['generation']) ? sanitize_key(wp_unslash($_POST['generation'])) : 'G1';
        $domain   = isset($_POST['domain']) ? sanitize_text_field(wp_unslash($_POST['domain'])) : 'general';
        $baseline_json = isset($_POST['baseline']) ? wp_unslash($_POST['baseline']) : '';

        if (empty($problem)) {
            wp_send_json_error(['message' => __('问题描述不能为空', 'linked3')], 400);
        }

        $baseline = null;
        if (!empty($baseline_json)) {
            $decoded = json_decode($baseline_json, true);
            if (is_array($decoded)) {
                $baseline = $decoded;
            }
        }

        $context = ['domain' => $domain];

        try {
            $engine = \Linked3\Classes\CognitiveOS\COSEngine::instance();
            $result = $engine->evolve_single_gen($problem, $context, $gen, $baseline);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'file'    => basename($e->getFile()),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public static function ajax_evolve_finalize(): void
    {
        check_ajax_referer('linked3_cos', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }

        $problem  = isset($_POST['problem']) ? sanitize_textarea_field(wp_unslash($_POST['problem'])) : '';
        $domain   = isset($_POST['domain']) ? sanitize_text_field(wp_unslash($_POST['domain'])) : 'general';
        $mvp_json = isset($_POST['mvp']) ? wp_unslash($_POST['mvp']) : '';
        $gens_json = isset($_POST['generations']) ? wp_unslash($_POST['generations']) : '[]';

        if (empty($problem) || empty($mvp_json)) {
            wp_send_json_error(['message' => __('缺少 MVP 数据', 'linked3')], 400);
        }

        $mvp = json_decode($mvp_json, true);
        $generations = json_decode($gens_json, true) ?: [];

        if (!is_array($mvp)) {
            wp_send_json_error(['message' => __('MVP 数据格式错误', 'linked3')], 400);
        }

        // 构建 generations_summary
        $generations_summary = [];
        foreach ($generations as $g) {
            $generations_summary[] = [
                'generation'      => $g['generation'] ?? '',
                'status'          => $g['status'] ?? '',
                'variants_count'  => $g['variants_count'] ?? 0,
                'survivors_count' => $g['survivors_count'] ?? 0,
                'killed_count'    => $g['killed_count'] ?? 0,
                'mvp_id'          => $g['mvp']['id'] ?? '',
                'mvp_fitness'     => $g['mvp']['fitness'] ?? 0,
                'mvp_approach'    => mb_substr($g['mvp']['approach'] ?? '', 0, 200),
            ];
        }

        $context = ['domain' => $domain];

        try {
            $engine = \Linked3\Classes\CognitiveOS\COSEngine::instance();
            $result = $engine->finalize_evolution($problem, $context, $mvp, $generations_summary);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public static function ajax_diagnose(): void
    {
        check_ajax_referer('linked3_cos', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }

        // v20.4-fix7: 诊断端点也要提高超时 (会做真实 AI 调用测试)
        @set_time_limit(60);
        @ini_set('max_execution_time', '60');

        $diag = [
            'php_version'   => PHP_VERSION,
            'max_execution' => @ini_get('max_execution_time') ?: 'unknown',
            'set_time_limit' => function_exists('set_time_limit'),
            'ai_dispatcher' => class_exists('\\Linked3\\Classes\\Core\\AIDispatcher'),
            'default_provider' => get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow'),
            'provider_keys' => [],
            'test_result'   => null,
            'test_error'    => null,
        ];

        // 检查已配置的 provider keys
        $saved_keys = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        foreach (['siliconflow', 'openai', 'deepseek', 'zhipu'] as $slug) {
            $diag['provider_keys'][$slug] = !empty($saved_keys[$slug]) ? 'configured (' . strlen($saved_keys[$slug]) . ' chars)' : 'not configured';
        }

        // 尝试一次最小化 AI 调用
        if ($diag['ai_dispatcher']) {
            try {
                $dispatcher = \Linked3\Classes\Core\AIDispatcher::instance();
                // v20.4-fix11: 诊断测试也绕过陈旧熔断器, 否则熔断器打开时诊断永远失败
                $result = $dispatcher->chat(
                    [
                        ['role' => 'user', 'content' => '回复OK'],
                    ],
                    [
                        'max_tokens' => 10,
                        'module'     => 'cos_diag',
                        'user_id'    => get_current_user_id(),
                        'timeout'    => 30,
                    ],
                    ['fallback_providers' => ['siliconflow'], 'force_bypass_circuit' => true]
                );
                $diag['test_result'] = 'success: ' . substr($result['content'] ?? '', 0, 50);
            } catch (\Throwable $e) {
                $diag['test_error'] = $e->getMessage();
            }
        }

        wp_send_json_success($diag);
    }

    public static function ajax_reset_circuit(): void
    {
        check_ajax_referer('linked3_cos', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }

        $providers = ['siliconflow', 'deepseek', 'qwen', 'openai', 'kimi', 'zhipu', 'doubao', 'hunyuan'];
        $reset_count = 0;
        foreach ($providers as $slug) {
            $key = 'linked3_pcb_' . $slug;
            if (false !== get_transient($key)) {
                delete_transient($key);
                $reset_count++;
            }
        }

        wp_send_json_success([
            'reset_count' => $reset_count,
            'message' => sprintf('已重置 %d 个 provider 的熔断器', $reset_count),
        ]);
    }

    public static function ajax_recommend_levers(): void
    {
        check_ajax_referer('linked3_cos', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }

        $problem  = isset($_POST['problem']) ? sanitize_textarea_field(wp_unslash($_POST['problem'])) : '';
        $approach = isset($_POST['approach']) ? sanitize_textarea_field(wp_unslash($_POST['approach'])) : '';
        $domain   = isset($_POST['domain']) ? sanitize_text_field(wp_unslash($_POST['domain'])) : 'general';

        if (empty($approach)) {
            wp_send_json_error(['message' => __('缺少方案内容', 'linked3')], 400);
        }

        // 基于问题领域和方案特征推荐杠杆
        $recommendations = self::recommend_levers_for_problem($problem, $approach, $domain);

        wp_send_json_success([
            'recommended' => $recommendations,
            'reason' => '基于问题领域和方案特征自适配推荐',
        ]);
    }

    public static function ajax_version(): void
    {
        $patch = 'unknown';
        if (class_exists('\\Linked3\\Classes\\CognitiveOS\\COSEngine')) {
            $patch = \Linked3\Classes\CognitiveOS\COSEngine::patch_version();
        }

        // 同时检查关键修复点是否真的生效
        $checks = [
            'extract_rules_is_public' => false,
            'chat_has_3_args'         => false,
            'registry_auto_init'      => false,
            'chain_chunked_fix10'     => false, // v20.4-fix10: 杠杆链分块串行
        ];

        try {
            $rc = new \ReflectionClass('\\Linked3\\Classes\\CognitiveOS\\Core\\COSDepartments');
            $m = $rc->getMethod('extract_rules');
            $checks['extract_rules_is_public'] = $m->isPublic();
        } catch (\Throwable $e) {}

        try {
            $engine_file = __DIR__ . '/../CosEngine.php';
            $content = file_get_contents($engine_file);
            $checks['chat_has_3_args'] = (strpos($content, "fallback_providers") !== false)
                && (strpos($content, "cos_lever") !== false);
        } catch (\Throwable $e) {}

        try {
            $reg_file = __DIR__ . '/../../MetaLever/MetaLeverRegistry.php';
            $content = file_get_contents($reg_file);
            $checks['registry_auto_init'] = (strpos($content, 'if (!self::$initialized)') !== false);
        } catch (\Throwable $e) {}

        // v20.4-fix10: 验证杠杆链已改为分块串行 (前端 runOneLever 函数)
        // v20.4-fix11: 修正路径 — dirname(__DIR__, 3) 解析到 src/ 而非插件根目录
        // __DIR__ = src/Classes/CognitiveOS/Ajax, 需上溯 4 级才到插件根目录
        try {
            $plugin_root = dirname(__DIR__, 4);
            $tab_file = $plugin_root . '/admin/views/dashboard/partials/tab-cognitive-os.php';
            $content = @file_get_contents($tab_file);
            if ($content === false && defined('LINKED3_DIR')) {
                // 兜底: 用插件主文件定义的常量定位 (symlink / 异常路径场景)
                $tab_file = LINKED3_DIR . 'admin/views/dashboard/partials/tab-cognitive-os.php';
                $content = @file_get_contents($tab_file);
            }
            $checks['chain_chunked_fix10'] = ($content !== false)
                && (strpos($content, 'runOneLever') !== false)
                && (strpos($content, 'linked3_cos_run_lever') !== false)
                && (strpos($content, 'AbortController') !== false);
        } catch (\Throwable $e) {}

        wp_send_json_success([
            'patch_version' => $patch,
            'php_version'   => PHP_VERSION,
            'checks'        => $checks,
            'server_time'   => current_time('mysql'),
        ]);
    }

}
