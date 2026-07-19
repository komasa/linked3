<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;
class GenesisAjaxCore
{
    public static function ajax_genesis_generate()
    : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        $script = wp_strip_all_tags(wp_unslash($_POST['script'] ?? ''));
        $styleId = sanitize_text_field($_POST['style'] ?? 'exorcism_dark_ink');
        $platform = sanitize_text_field($_POST['platform'] ?? 'midjourney');
        if (empty($script)) {
            wp_send_json_error(['message' => __('请输入剧本', 'linked3-ai')]);
        }
        if (!class_exists('\Linked3\Classes\Dashboard\Linked3_Genesis_PlotParser')) {
            wp_send_json_error(['message' => __('Genesis 引擎未加载', 'linked3-ai')]);
        }
        // v7.0.3: 统一走 AI 拆分路径 (不再用旧 PlotParser 直接解析)
        $panelCountRaw = sanitize_text_field($_POST['panel_count'] ?? 'auto');
        $isAuto = ($panelCountRaw === 'auto');
        $targetPanels = $isAuto ? 0 : max(5, min(500, (int)$panelCountRaw));
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');
        try {
            // v7.0.3: 始终调用 AI 拆分 (不再降级到 PlotParser)
            $aiPanels = self::genesisAIGeneratePanels($script, $targetPanels, $styleId, $isAuto);
            if (empty($aiPanels)) {
                // AI 拆分失败 — 极端降级: 把整段文本作为1个分镜
                $aiPanels = [[
                    'scene_id' => 'S001',
                    'location' => mb_substr($script, 0, 20),
                    'characters' => [],
                    'action' => mb_substr($script, 0, 100),
                    'mood' => '紧张',
                    'shot' => '中景',
                    'angle' => '平视',
                    'comp' => '三分法',
                ]];
            }
            // 用 AI 拆分结果组装 Prompt
            $assembler = new \Linked3_Genesis_PromptAssembler();
            $pqsChecker = new \Linked3_Genesis_PQSChecker();
            $results = [];
            foreach ($aiPanels as $i => $aiPanel) {
                $scene = [
                    'id' => $aiPanel['scene_id'] ?? ('S' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT)),
                    'location' => $aiPanel['location'] ?? '',
                    'characters' => $aiPanel['characters'] ?? [],
                    'action' => $aiPanel['action'] ?? '',
                    'mood' => $aiPanel['mood'] ?? '',
                ];
                $selector = new \Linked3_Genesis_AtomSelector();
                $atoms = $selector->selectForScene($scene);
                $assembled = $assembler->assembleFull($atoms, $aiPanel, $styleId, $platform);
                $pqs = $pqsChecker->check($assembled);
                $results[] = [
                    'panel_id'   => 'P' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                    'scene_id'   => $scene['id'],
                    'location'   => $aiPanel['location'] ?? '',
                    'action'     => $aiPanel['action'] ?? '',
                    'mood'       => $aiPanel['mood'] ?? '',
                    'focus'      => ($aiPanel['characters'][0] ?? ''),
                    'shot'       => $aiPanel['shot'] ?? '中景',
                    'angle'      => $aiPanel['angle'] ?? '平视',
                    'comp'       => $aiPanel['comp'] ?? '三分法',
                    'characters' => $aiPanel['characters'] ?? [],
                    'prompt_en'  => $assembled['prompt_en'],
                    'prompt_with_params' => $assembled['prompt_with_params'],
                    'style'      => $assembled['style'],
                    'style_name' => $assembled['style_name'],
                    'platform'   => $assembled['platform'],
                    'platform_params' => $assembled['platform_params'],
                    'character_details' => $assembled['characters'],
                    'scene_detail' => $assembled['scene']['scene_name'] ?? '',
                    'pqs'        => $pqs,
                ];
            }
            wp_send_json_success([
                'panels'      => $results,
                'total_panels' => count($results),
                'total_scenes' => count(array_unique(array_column($results, 'scene_id'))),
                'style'       => $styleId,
                'platform'    => $platform,
                'mode'        => 'ai_split',
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'trace'   => WP_DEBUG ? $e->getTraceAsString() : '',
            ]);
        }
    }

    public static function ajax_genesis_styles()
    : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        if (!class_exists('\Linked3\Classes\Dashboard\Linked3_Genesis_AtomIndex')) {
            wp_send_json_error(['message' => __('Genesis 引擎未加载', 'linked3-ai')]);
        }
        $index = \Linked3_Genesis_AtomIndex::instance();
        $styles = $index->getStyles();
        $stats = $index->getStats();
        wp_send_json_success([
            'styles' => $styles,
            'stats'  => $stats,
            'characters' => $index->getCharacters(),
            'scenes' => $index->getScenes(),
            'templates' => $index->getTemplates(),
        ]);
    }

    public static function ajax_genesis_generate_multi()
    : void {
        // v7.1.3: 注册 fatal error 兜底 — PHP OOM/类未加载时也能返回 JSON 而非 HTML
        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                    http_response_code(500);
                }
                echo wp_json_encode([
                    'success' => false,
                    'data'    => [
                        'message'   => __('服务器内部错误: ', 'linked3-ai') . $err['message'],
                        'error_type'=> 'fatal',
                        'file'      => WP_DEBUG ? basename($err['file']) : '',
                        'line'      => WP_DEBUG ? $err['line'] : 0,
                    ],
                ]);
            }
        });
        // 输出缓冲 — 防止 PHP warning/notice 破坏 JSON 响应
        if (!headers_sent()) {
            while (ob_get_level() > 0) ob_end_clean();
            ob_start();
        }
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        $script = wp_strip_all_tags(wp_unslash($_POST['script'] ?? ''));
        $styleId = sanitize_text_field($_POST['style'] ?? 'exorcism_dark_ink');
        $platform = sanitize_text_field($_POST['platform'] ?? 'midjourney');
        $panelCountRaw = sanitize_text_field($_POST['panel_count'] ?? 'auto');
        if (empty($script)) {
            wp_send_json_error(['message' => __('请输入剧本或故事', 'linked3-ai')]);
        }
        // v7.1.0: 确定分镜数量
        $isAuto = ($panelCountRaw === 'auto');
        $targetPanels = $isAuto ? 10 : max(5, min(200, (int)$panelCountRaw));
        // v7.1.3: 更激进的时间/内存提升
        @set_time_limit(600);
        @ini_set('memory_limit', '768M');
        @ini_set('max_execution_time', '600');
        // ============================================================
        // v7.1.3 新增: 预检 (Pre-flight Check)
        // 在进入长流程前, 先验证 API Key / Provider 可用性
        // 避免用户等 60s 才发现配置错误
        // ============================================================
        $preflight = self::genesisPreflightCheck();
        if (!$preflight['ok']) {
            wp_send_json_error([
                'message'      => $preflight['message'],
                'error_type'   => 'preflight',
                'error_code'   => $preflight['code'] ?? '',
                'troubleshoot' => $preflight['troubleshoot'] ?? '',
            ]);
        }
        try {
            $result = self::genesisGenerateMultiInternal($script, $styleId, $platform, $panelCountRaw, null);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message'    => $e->getMessage(),
                'trace'      => WP_DEBUG ? $e->getTraceAsString() : '',
                'error_type' => 'exception',
                'error_class'=> get_class($e),
            ]);
        }
    }

    public static function ajax_genesis_test_connection()
    : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        $t0 = microtime(true);
        // 预检
        $preflight = self::genesisPreflightCheck();
        if (!$preflight['ok']) {
            wp_send_json_error([
                'message' => $preflight['message'],
                'code'    => $preflight['code'] ?? '',
                'stage'   => 'preflight',
                'elapsed_ms' => (int)((microtime(true) - $t0) * 1000),
            ]);
        }
        // 最小 AI 调用
        try {
            $providerSlug = $preflight['provider'];
            $savedModels = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
            $model = $savedModels[$providerSlug] ?? 'gpt-3.5-turbo';
            $result = AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => 'Reply with the single word: OK']],
                [
                    'provider'   => $providerSlug,
                    'model'      => $model,
                    'max_tokens' => 5,
                    'module'     => 'genesis_test',
                ],
                ['fallback_providers' => ['deepseek', 'zhipu'], 'force_bypass_circuit' => true]
            );
            $elapsed = (int)((microtime(true) - $t0) * 1000);
            $content = trim($result['content'] ?? '');
            wp_send_json_success([
                'message'    => __('连接成功', 'linked3-ai'),
                'provider'   => $providerSlug,
                'model'      => $model,
                'response'   => mb_substr($content, 0, 50),
                'elapsed_ms' => $elapsed,
                'usage'      => $result['usage'] ?? [],
            ]);
        } catch (\Throwable $e) {
            $elapsed = (int)((microtime(true) - $t0) * 1000);
            wp_send_json_error([
                'message'    => __('AI 调用失败: ', 'linked3-ai') . $e->getMessage(),
                'stage'      => 'ai_call',
                'elapsed_ms' => $elapsed,
                'error_class'=> get_class($e),
            ]);
        }
    }

    public static function ajax_genesis_start_job()
    : void {
        // Fatal error 兜底
        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                    http_response_code(500);
                }
                echo wp_json_encode([
                    'success' => false,
                    'data'    => ['message' => __('服务器内部错误: ', 'linked3-ai') . $err['message'], 'error_type' => 'fatal'],
                ]);
            }
        });
        while (ob_get_level() > 0) ob_end_clean();
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        $script = wp_strip_all_tags(wp_unslash($_POST['script'] ?? ''));
        $styleId = sanitize_text_field($_POST['style'] ?? 'exorcism_dark_ink');
        $platform = sanitize_text_field($_POST['platform'] ?? 'midjourney');
        $panelCountRaw = sanitize_text_field($_POST['panel_count'] ?? 'auto');
        // v7.2.0: 新增分镜模式 + 章节标记
        $splitMode = sanitize_text_field($_POST['split_mode'] ?? 'auto');
        $chapterMarker = sanitize_text_field($_POST['chapter_marker'] ?? 'auto');
        // v8.0.0: seed_id
        $seedId = sanitize_text_field($_POST['seed_id'] ?? '');
        if (empty($script)) wp_send_json_error(['message' => __('请输入剧本或故事', 'linked3-ai')]);
        // 预检 (50ms 内快速失败)
        $preflight = self::genesisPreflightCheck();
        if (!$preflight['ok']) {
            wp_send_json_error([
                'message'      => $preflight['message'],
                'error_type'   => 'preflight',
                'error_code'   => $preflight['code'] ?? '',
                'troubleshoot' => $preflight['troubleshoot'] ?? '',
            ]);
        }
        // 创建任务 (不触发执行, 只创建)
        $jobInfo = \Linked3_Genesis_JobRunner::startJob([
            'script'          => $script,
            'style'           => $styleId,
            'platform'        => $platform,
            'panel_count'     => $panelCountRaw,
            'split_mode'      => $splitMode,
            'chapter_marker'  => $chapterMarker,
            'seed_id'         => $seedId,
        ]);
        $jobId = $jobInfo['job_id'];
        $execMode = $jobInfo['exec_mode'];
        // v7.1.6: 根据执行模式触发任务
        if ($execMode === 'fastcgi') {
            // 最佳: 先发送响应, 然后 fastcgi_finish_request, 最后后台执行
            $response = [
                'success' => true,
                'data'    => [
                    'job_id'           => $jobId,
                    'status'           => 'pending',
                    'poll_interval_ms' => $jobInfo['poll_interval_ms'],
                    'exec_mode'        => $execMode,
                    'message'          => __('任务已创建, 后台执行中 (fastcgi_finish_request)', 'linked3-ai'),
                ],
            ];
            echo wp_json_encode($response);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Length: ' . ob_get_length());
            header('Connection: close');
            ob_end_flush();
            flush();
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            // 此时浏览器已收到响应, 后续代码在后台执行
            \Linked3_Genesis_JobRunner::runJob($jobId);
        } else {
            // cron / cli / lazy: 只触发, 不阻塞
            \Linked3_Genesis_JobRunner::triggerExecution($jobId);
            wp_send_json_success([
                'job_id'           => $jobId,
                'status'           => 'pending',
                'poll_interval_ms' => $jobInfo['poll_interval_ms'],
                'exec_mode'        => $execMode,
                'message'          => __('任务已创建, 执行模式: ', 'linked3-ai') . $execMode,
            ]);
        }
    }

}
