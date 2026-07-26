<?php

declare(strict_types=1);
namespace Linked3\Classes\CognitiveOS\Ajax;

use Linked3\Includes\Log\Logger;
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
        // v27.8.8-fix: 超时从50s提升到120s — GLM等模型响应可能较慢, 50s不够
        // v27.8.9-fix: 提升到180s + 内存提升到512M — 防止演化中途超时/内存不足
        // v28 PR-10: 180→55s — playground WASM max_execution_time=60s, 留5s余量
        // 非 playground 环境 set_time_limit 仍然有效 (55s 足够: 3代×15s+5s overhead)
        @set_time_limit(55);
        @ini_set('max_execution_time', '55');
        @ini_set('memory_limit', '512M');

        $problem  = isset($_POST['problem']) ? sanitize_textarea_field(wp_unslash($_POST['problem'])) : '';
        $gen      = isset($_POST['generation']) ? sanitize_key(wp_unslash($_POST['generation'])) : 'G1';
        $domain   = isset($_POST['domain']) ? sanitize_text_field(wp_unslash($_POST['domain'])) : 'general';
        $baseline_json = isset($_POST['baseline']) ? wp_unslash($_POST['baseline']) : '';

        if (empty($problem)) {
            wp_send_json_error(['message' => __('问题描述不能为空', 'linked3')], 400);
        }

        // v27.8.13 (审计Phase2): 轻量 AI 预检 — 只检查 key 是否存在 (不做真实 AI 调用)
        // 如果没有任何 provider 配置了 key, 提前返回引导信息 (而非等演化失败)
        $saved_keys = function_exists('get_option')
            ? (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', [])
            : [];
        $has_any_key = false;
        foreach (['siliconflow', 'zhipu', 'zai', 'deepseek', 'qwen', 'openai', 'kimi'] as $slug) {
            if (!empty($saved_keys[$slug])) {
                $has_any_key = true;
                break;
            }
        }
        if (!$has_any_key) {
            wp_send_json_error([
                'message' => __('未配置任何 AI Provider 的 API Key, 演化需要 AI 生成方案。请到「系统设置→API设置」配置至少一个 Provider。', 'linked3'),
                'code' => 'no_api_key',
                'diagnosis' => self::build_ai_config_diagnosis(),
            ], 400);
        }

        $baseline = null;
        if (!empty($baseline_json)) {
            $decoded = json_decode($baseline_json, true);
            if (is_array($decoded)) {
                $baseline = $decoded;
            }
        }

        $context = ['domain' => $domain];

        // v27.8.9-fix: 记录开始时间, 用于诊断超时
        $start_time = microtime(true);

        try {
            $engine = \Linked3\Classes\CognitiveOS\COSEngine::instance();
            $result = $engine->evolve_single_gen($problem, $context, $gen, $baseline);

            // v27.8.9-fix: 记录耗时, 帮助诊断
            $elapsed = round(microtime(true) - $start_time, 2);
            Logger::instance()->error('ai', sprintf('[linked3 COS] %s 演化完成, 耗时 %ss, status=%s', $gen, $elapsed, $result['status'] ?? 'unknown'));


            // v27.8.10 (审计Phase1): 如果演化状态非 pass, 返回详细诊断信息
            if (isset($result['status']) && $result['status'] !== 'pass') {
                $diag = self::build_evolve_diagnosis($result);
                $result['diagnosis'] = $diag;
                Logger::instance()->error('ai', '[linked3 COS] ' . $gen . ' 演化失败诊断: ' . wp_json_encode($diag));

            }

            // v27.8.14: 确保输出缓冲干净 — 清理所有层级的缓冲, 防止 warning/notice 破坏 JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            wp_send_json_success($result);
        } catch (\Throwable $e) {
            $elapsed = round(microtime(true) - $start_time, 2);
            Logger::instance()->error('ai', sprintf('[linked3 COS] %s 演化异常 (%ss): %s in %s:%d', $gen, $elapsed, $e->getMessage(), basename($e->getFile()), $e->getLine()));

            // v27.8.14: 异常时也清理输出缓冲
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            // v27.8.10 (审计Phase1): 异常时也返回 AI 配置诊断
            $diag = self::build_ai_config_diagnosis();
            wp_send_json_error([
                'message'   => $e->getMessage(),
                'file'      => basename($e->getFile()),
                'line'      => $e->getLine(),
                'elapsed'   => $elapsed,
                'diagnosis' => $diag,
            ], 500);
        }
    }

    /**
     * v27.8.10 (审计Phase1): 构建演化失败诊断信息
     * 返回哪个部门失败、SLA 验证失败原因、AI 是否可用等
     */
    private static function build_evolve_diagnosis(array $result): array
    {
        $diag = [
            'failed_at'      => $result['failed_at'] ?? 'unknown',
            'fail_message'   => $result['message'] ?? '',
            'sla_rollback'   => $result['sla_rollback'] ?? null,
            'ai_config'      => self::build_ai_config_diagnosis(),
        ];
        return $diag;
    }

    /**
     * v27.8.10 (审计Phase1): 构建 AI 配置诊断信息
     * 检查 provider 是否配置、key 是否存在
     */
    private static function build_ai_config_diagnosis(): array
    {
        $default_provider = function_exists('get_option')
            ? get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow')
            : 'siliconflow';
        $saved_keys = function_exists('get_option')
            ? (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', [])
            : [];
        $saved_models = function_exists('get_option')
            ? (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', [])
            : [];

        $providers_status = [];
        $has_any_key = false;
        foreach (['siliconflow', 'zhipu', 'zai', 'deepseek', 'qwen', 'openai', 'kimi'] as $slug) {
            $has_key = !empty($saved_keys[$slug]);
            if ($has_key) $has_any_key = true;
            $providers_status[$slug] = [
                'configured' => $has_key,
                'model'      => $saved_models[$slug] ?? '',
            ];
        }

        return [
            'default_provider'    => $default_provider,
            'default_has_key'     => !empty($saved_keys[$default_provider]),
            'any_provider_has_key' => $has_any_key,
            'providers'           => $providers_status,
            'dispatcher_loaded'   => class_exists('\\Linked3\\Classes\\Core\\AIDispatcher'),
        ];
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

    /**
     * v27.8.11 (审计Phase2): 动态杠杆评分
     *
     * 根据用户问题中的关键词, 匹配 lever-keywords.json 中的关键词映射,
     * 计算每个杠杆的动态适应度分数 (替代硬编码的固定分数)。
     *
     * POST: problem (问题文本)
     * Return: { scores: { lever_id: { fitness, matched_keywords, label } } }
     */
    public static function ajax_score_levers(): void
    {
        check_ajax_referer('linked3_cos', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }

        $problem = isset($_POST['problem']) ? sanitize_textarea_field(wp_unslash($_POST['problem'])) : '';
        if (empty($problem)) {
            wp_send_json_error(['message' => __('缺少问题描述', 'linked3')], 400);
        }

        // 加载关键词映射表
        $json_path = __DIR__ . '/../Data/lever-keywords.json';
        if (!file_exists($json_path)) {
            wp_send_json_error(['message' => __('杠杆关键词映射表不存在', 'linked3')], 500);
        }
        $mapping = json_decode(file_get_contents($json_path), true);
        if (!is_array($mapping)) {
            wp_send_json_error(['message' => __('杠杆关键词映射表解析失败', 'linked3')], 500);
        }

        // 计算每个杠杆的动态分数
        $problem_lower = mb_strtolower($problem);
        $scores = [];
        foreach ($mapping as $lever_id => $info) {
            if ($lever_id === '_meta') continue;
            $base = $info['base_fitness'] ?? 15;
            $boost = $info['keyword_boost'] ?? 3;
            $keywords = $info['keywords'] ?? [];
            $matched = [];

            foreach ($keywords as $kw) {
                if (mb_strpos($problem_lower, mb_strtolower($kw)) !== false) {
                    $matched[] = $kw;
                }
            }

            $fitness = $base + count($matched) * $boost;
            // 上限 25
            $fitness = min(25, $fitness);

            $scores[$lever_id] = [
                'fitness'           => $fitness,
                'label'             => $info['label'] ?? $lever_id,
                'matched_keywords'  => $matched,
                'match_count'       => count($matched),
            ];
        }

        // 按分数降序排序
        uasort($scores, function($a, $b) {
            return $b['fitness'] <=> $a['fitness'];
        });

        wp_send_json_success([
            'scores'    => $scores,
            'problem'   => $problem,
            'top_6'     => array_slice(array_keys($scores), 0, 6),
        ]);
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
                        ['role' => 'user', 'content' => __('回复OK', 'linked3')],
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

        // v27.8.7-fix: 自动适配时 approach 可能为空, 此时用 problem 推荐而非报错
        // 原代码 empty($approach) 直接返回 400, 导致自动适配按钮永远失败
        if (empty($approach) && empty($problem)) {
            wp_send_json_error(['message' => __('缺少问题和方案内容', 'linked3')], 400);
        }

        // approach 为空时用 problem 作为推荐依据
        $recommendation_text = !empty($approach) ? $approach : $problem;

        // 基于问题领域和方案特征推荐杠杆
        $recommendations = COSAjaxManage::recommend_levers_for_problem($problem, $recommendation_text, $domain);

        wp_send_json_success([
            'recommended' => $recommendations,
            'reason' => __('基于问题领域和方案特征自适配推荐', 'linked3'),
        ]);
    }

    public static function ajax_version(): void
    {
        check_ajax_referer('linked3_cos', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
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
        } catch (\Throwable $e) { Logger::instance()->warning('ai', $e->getMessage()); }

        try {
            // v27.6.19-fix: 修正文件名大小写 — CosEngine.php → COSEngine.php
            $engine_file = __DIR__ . '/../COSEngine.php';
            $content = file_get_contents($engine_file);
            $checks['chat_has_3_args'] = ($content !== false)
                && (strpos($content, 'fallback_providers') !== false)
                && (strpos($content, 'cos_lever') !== false);
        } catch (\Throwable $e) { Logger::instance()->warning('ai', $e->getMessage()); }

        try {
            $reg_file = __DIR__ . '/../../MetaLever/MetaLeverRegistry.php';
            $content = file_get_contents($reg_file);
            $checks['registry_auto_init'] = (strpos($content, 'if (!self::$initialized)') !== false);
        } catch (\Throwable $e) { Logger::instance()->warning('ai', $e->getMessage()); }

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
        } catch (\Throwable $e) { Logger::instance()->warning('ai', $e->getMessage()); }

        wp_send_json_success([
            'patch_version' => $patch,
            'php_version'   => PHP_VERSION,
            'checks'        => $checks,
            'server_time'   => current_time('mysql'),
        ]);
    }

}
