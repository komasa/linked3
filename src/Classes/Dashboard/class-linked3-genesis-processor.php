<?php

namespace Linked3\Classes\Dashboard;

use Linked3\Classes\Templates\TemplateManager;
use Linked3\Classes\SEO\Keyword\Linked3_Keyword_Manager;
use Linked3\Classes\Core\Linked3_AI_Dispatcher;
use Linked3\Includes\Http\Linked3_Safe_Remote;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-linked3-genesis-helpers.php';

/**
 * Genesis Processor — owns ALL Genesis comic/manga script generation logic.
 *
 * This class is self-contained: all `self::` references resolve within the class.
 * Both Linked3_Dashboard_Ajax_Registrar (legacy) and
 * Linked3_Dashboard_Genesis_Actions (new) delegate to this class.
 *
 * @package Linked3
 * @subpackage Classes\Dashboard
 */
final class Linked3_Genesis_Processor
{
    // v6.6.0: Genesis 漫画脚本引擎 AJAX
    // =================================================================
    /**
     * v6.6.0: Genesis 漫画脚本生成。
     * 5层管线: 剧本解析→原子选择→Prompt组装→PQS质检→平台适配
     */
        public static function ajax_genesis_generate() : mixed { return Linked3_Genesis_Processor_Delegates::ajax_genesis_generate(); }
    /**
     * v6.6.0: 获取 Genesis 风格列表。
     */
        public static function ajax_genesis_styles() : mixed { return Linked3_Genesis_Processor_Delegates::ajax_genesis_styles(); }
    /**
     * v7.1.0: AI 自动拆分剧本/故事 → N 个分镜, 每个分镜独立 Prompt。
     *
     * 两阶段流程 (融入 deai_5d FP 部"剥骨提纯语义核"思想):
     *   阶段1: FP 剥骨 — AI 一次调用, 只输出 N 个语义核节点元数据 (短, 小模型能稳定返回)
     *   阶段2: 并发组装 — 对每个节点并发调用 AI 生成画面 Prompt (curl_multi 真并发)
     *           - AI 失败的节点 → 降级本地 PromptAssembler 组装
     *           - curl_multi 不可用 → 串行调 Dispatcher::chat
     *
     * 修复历史 bug:
     *   - v7.0.5 旧流程让 AI 一次返回 N 个完整 prompt_en, 小模型输出长就偷懒只给 1 个
     *   - v7.1.0 把"拆分"和"组装 Prompt"解耦, 拆分阶段输出极短, 组装阶段每节点独立 AI 调用
     */
        public static function ajax_genesis_generate_multi() : mixed { return Linked3_Genesis_Processor_Delegates::ajax_genesis_generate_multi(); }
    /**
     * v7.1.5: 核心生成逻辑 (从 ajax_genesis_generate_multi 提取)
     *
     * 这个方法返回数据而非发送 JSON, 可被:
     *   1. ajax_genesis_generate_multi (同步模式, 兼容旧前端)
     *   2. Linked3_Genesis_JobRunner::runJob (异步模式, 推荐)
     *
     * @param string $script 剧本
     * @param string $styleId 风格 ID
     * @param string $platform 平台
     * @param string $panelCountRaw 分镜数量 ('auto' 或数字)
     * @param callable|null $progressCb 进度回调 fn(int $progress, string $stage, string $message)
     * @return array 生成结果
     */
        public static function genesisGenerateMultiInternal(string $script, string $styleId, string $platform, string $panelCountRaw, $progressCb = null, array $extraOptions = []) : mixed { return Linked3_Genesis_Processor_Delegates::genesisGenerateMultiInternal($script, $styleId, $platform, $panelCountRaw, $progressCb, $extraOptions); }
    /**
     * v7.1.3: 预检 — 在进入长流程前验证配置, 快速失败。
     *
     * 检查项:
     *   1. AI Dispatcher 类是否加载
     *   2. Provider 是否配置
     *   3. API Key 是否配置且可解密
     *   4. Provider Factory 能否实例化
     *   5. Genesis 核心类是否加载
     *
     * @return array {ok: bool, message: string, code?: string, troubleshoot?: string}
     */
        public static function genesisPreflightCheck() : mixed { return Linked3_Genesis_Processor_Delegates::genesisPreflightCheck(); }
    /**
     * v7.1.3: 新增 — 轻量级连通性测试 AJAX。
     *
     * 用户点击「测试连接」按钮, 发起一次最小 AI 调用 (1 token),
     * 3s 内返回结果。用于排查 "Failed to fetch" 是否为网络/API 问题。
     */
        public static function ajax_genesis_test_connection() : mixed { return Linked3_Genesis_Processor_Delegates::ajax_genesis_test_connection(); }
    // ============================================================
    // v7.1.5: 异步任务模式 (彻底解决 Failed to fetch)
    // ============================================================
    /**
     * v7.1.5: 启动异步生成任务 — 立即返回 job_id, 后台执行
     *
     * 浏览器调用此接口, 50ms 内拿到 job_id, 然后轮询 ajax_genesis_poll_job
     * 这样彻底绕过 nginx/Apache/PHP-FPM 的 60s 超时限制
     */
        public static function ajax_genesis_start_job() : mixed { return Linked3_Genesis_Ajax_Core::ajax_genesis_start_job(); }
    /**
     * v7.1.5: 轮询任务状态
     */
    public static function ajax_genesis_poll_job()
    : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? $_GET['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        $jobId = sanitize_text_field($_POST['job_id'] ?? $_GET['job_id'] ?? '');
        if (empty($jobId)) wp_send_json_error(['message' => __('缺少 job_id', 'linked3-ai')]);
        $status = \Linked3_Genesis_JobRunner::pollJob($jobId);
        if ($status['status'] === 'not_found') {
            wp_send_json_error(['message' => $status['message'], 'error_type' => 'job_not_found']);
        }
        wp_send_json_success($status);
    }
    /**
     * v7.1.5: 取消任务
     */
    public static function ajax_genesis_cancel_job()
    : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        $jobId = sanitize_text_field($_POST['job_id'] ?? '');
        $ok = \Linked3_Genesis_JobRunner::cancelJob($jobId);
        wp_send_json_success(['cancelled' => $ok]);
    }
    /**
     * v7.1.5: WP-Cron 回调 — 执行任务
     */
    public static function cron_genesis_run_job(int $jobId)
    : void {
        \Linked3_Genesis_JobRunner::runJob($jobId);
    }
    // ============================================================
    // v8.0.0: Seed DNA AJAX 端点
    // ============================================================
    /**
     * v8.0.0: 生成 Seed DNA
     */
    public static function ajax_genesis_seed_generate()
    : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        $script = wp_strip_all_tags(wp_unslash($_POST['script'] ?? ''));
        $styleId = sanitize_text_field($_POST['style'] ?? 'exorcism_dark_ink');
        $seedName = sanitize_text_field($_POST['seed_name'] ?? '未命名 Seed');
        if (empty($script)) wp_send_json_error(['message' => __('请输入剧本', 'linked3-ai')]);
        $styleConfig = \Linked3_Genesis_StyleEngine::load($styleId);
        $styleName = $styleConfig['name_cn'] ?? $styleId;
        try {
            $dna = \Linked3_Genesis_SeedDNA::generate($script, $styleId, $styleName);
            $dna['name'] = $seedName;
            $seedId = \Linked3_Genesis_SeedDNA::save($dna);
            wp_send_json_success([
                'seed_id'   => $seedId,
                'seed_name' => $seedName,
                'dna'       => $dna,
                'message'   => __('Seed DNA 生成成功', 'linked3-ai'),
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => __('Seed DNA 生成失败: ', 'linked3-ai') . $e->getMessage()]);
        }
    }
    /**
     * v8.0.0: 获取 Seed 列表 (v9.1.2 修复: 改用 CPT listAll, 旧 listAll 不存在导致 Fatal)
     */
    public static function ajax_genesis_seed_list()
    : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        // v9.1.2: 优先用 CPT (Linked3_Genesis_Seed_CPT::listAll), 内部已合并旧 option 存储
        if (class_exists('\Linked3\Classes\Dashboard\Linked3_Genesis_Seed_CPT') && method_exists('\Linked3\Classes\Dashboard\Linked3_Genesis_Seed_CPT', 'listAll')) {
            try {
                $seeds = \Linked3_Genesis_Seed_CPT::listAll();
                wp_send_json_success(['seeds' => $seeds]);
                return;
            } catch (\Throwable $e) {
                if (function_exists('error_log')) {
                    error_log('[linked3] ajax_genesis_seed_list CPT query failed: ' . $e->getMessage());
                }
                // 落入下面的兜底
            }
        }
        // 兜底: 旧 option 存储 (Linked3_Genesis_SeedDNA::getAll)
        $seeds = [];
        if (class_exists('\Linked3\Classes\Dashboard\Linked3_Genesis_SeedDNA')) {
            try {
                $legacy = (array) \Linked3_Genesis_SeedDNA::getAll();
                foreach ($legacy as $dna) {
                    $sid = $dna['seed_id'] ?? '';
                    if (empty($sid)) continue;
                    $seeds[] = [
                        'seed_id'  => $sid,
                        'name'     => $dna['name'] ?? $dna['title'] ?? $sid,
                        'category' => $dna['seed_category'] ?? $dna['category'] ?? '',
                    ];
                }
            } catch (\Throwable $e) {
                // 全部失败 → 返回空列表 (前端会提示 "Seed 库为空")
            }
        }
        wp_send_json_success(['seeds' => $seeds]);
    }
    /**
     * v8.0.0: 删除 Seed (v9.1.2: 优先 CPT, 兜底旧 option)
     */
    public static function ajax_genesis_seed_delete()
    : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        $seedId = sanitize_text_field($_POST['seed_id'] ?? '');
        if (empty($seedId)) wp_send_json_error(['message' => __('seed_id 不能为空', 'linked3-ai')], 400);
        $ok = false;
        // 1) CPT 删除
        if (class_exists('\Linked3\Classes\Dashboard\Linked3_Genesis_Seed_CPT')) {
            try {
                $seed = \Linked3_Genesis_Seed_CPT::get($seedId);
                if (!empty($seed['post_id'])) {
                    $ok = \Linked3_Genesis_Seed_CPT::trash($seed['post_id']);
                }
            } catch (\Throwable $e) {
                // 落入兜底
            }
        }
        // 2) 旧 option 删除
        if (!$ok && class_exists('\Linked3\Classes\Dashboard\Linked3_Genesis_SeedDNA') && method_exists('\Linked3\Classes\Dashboard\Linked3_Genesis_SeedDNA', 'delete')) {
            try { $ok = (bool) \Linked3_Genesis_SeedDNA::delete($seedId); } catch (\Throwable $e) {}
        }
        wp_send_json_success(['deleted' => $ok]);
    }
    /**
     * v8.0.0: 导出 Seed JSON (v9.1.2: 优先 CPT, 兜底旧 option)
     */
    public static function ajax_genesis_seed_export()
    : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        $seedId = sanitize_text_field($_POST['seed_id'] ?? '');
        if (empty($seedId)) wp_send_json_error(['message' => __('seed_id 不能为空', 'linked3-ai')], 400);
        $json = null;
        // 1) CPT 导出
        if (class_exists('\Linked3\Classes\Dashboard\Linked3_Genesis_Seed_CPT')) {
            try {
                $seed = \Linked3_Genesis_Seed_CPT::get($seedId);
                if (!empty($seed)) {
                    $json = wp_json_encode($seed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
            } catch (\Throwable $e) {}
        }
        // 2) 旧 option 导出
        if (empty($json) && class_exists('\Linked3\Classes\Dashboard\Linked3_Genesis_SeedDNA') && method_exists('\Linked3\Classes\Dashboard\Linked3_Genesis_SeedDNA', 'exportJSON')) {
            try { $json = \Linked3_Genesis_SeedDNA::exportJSON($seedId); } catch (\Throwable $e) {}
        }
        wp_send_json_success(['json' => $json, 'seed_id' => $seedId]);
    }
    /**
     * v7.1.5: 服务器诊断 — 返回 PHP/curl/timeout 配置信息
     * 用于排查 "Failed to fetch" 的服务器侧原因
     */
    public static function ajax_genesis_server_diagnostic()
    : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        $info = [
            'php' => [
                'version'             => PHP_VERSION,
                'sapi'                => PHP_SAPI,
                'max_execution_time'  => ini_get('max_execution_time'),
                'memory_limit'        => ini_get('memory_limit'),
                'max_input_time'      => ini_get('max_input_time'),
                'max_input_vars'      => ini_get('max_input_vars'),
                'post_max_size'       => ini_get('post_max_size'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
            ],
            'curl' => [
                'enabled'        => function_exists('curl_init'),
                'version'        => function_exists('curl_version') ? curl_version()['version'] : 'N/A',
                'multi_enabled'  => function_exists('curl_multi_init'),
                'ssl_version'    => function_exists('curl_version') ? (curl_version()['ssl_version'] ?? 'N/A') : 'N/A',
            ],
            'wordpress' => [
                'version'        => get_bloginfo('version'),
                'wp_debug'       => WP_DEBUG,
                'wp_debug_log'   => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
                'cron'           => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'disabled' : 'enabled',
                'alternate_cron' => defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON ? 'enabled' : 'disabled',
            ],
            'server' => [
                'software'       => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                'fastcgi_finish' => function_exists('fastcgi_finish_request'),
                'proc_open'      => function_exists('proc_open'),
            ],
            'genesis' => [
                'classes_loaded' => [
                    'Linked3_AI_Dispatcher'           => class_exists('\Linked3\Classes\Dashboard\Linked3_AI_Dispatcher'),
                    'Linked3_Genesis_AtomIndex'       => class_exists('\Linked3\Classes\Dashboard\Linked3_Genesis_AtomIndex'),
                    'Linked3_Genesis_PromptAssembler' => class_exists('\Linked3\Classes\Dashboard\Linked3_Genesis_PromptAssembler'),
                    'Linked3_Genesis_PQSChecker'      => class_exists('\Linked3\Classes\Dashboard\Linked3_Genesis_PQSChecker'),
                    'Linked3_Genesis_JobRunner'       => class_exists('\Linked3\Classes\Dashboard\Linked3_Genesis_JobRunner'),
                ],
                'preflight'      => self::genesisPreflightCheck(),
            ],
            'recommendations' => [],
        ];
        // 生成建议
        $rec = &$info['recommendations'];
        if ((int)ini_get('max_execution_time') > 0 && (int)ini_get('max_execution_time') < 120) {
            $rec[] = '⚠️ max_execution_time=' . ini_get('max_execution_time') . 's 过小, 建议 ≥120s (或 0=无限)';
        }
        if (ini_get('memory_limit') && ini_get('memory_limit') !== '-1') {
            $mem = (int)ini_get('memory_limit');
            if (preg_match('/(\d+)M/', ini_get('memory_limit'), $m)) $mem = (int)$m[1];
            if ($mem < 256) $rec[] = '⚠️ memory_limit=' . ini_get('memory_limit') . ' 过小, 建议 ≥512M';
        }
        if (!function_exists('curl_multi_init')) {
            $rec[] = '⚠️ curl_multi 不可用, 将降级串行模式 (慢)';
        }
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $rec[] = '⚠️ WP-Cron 被禁用, 异步任务模式将退化为 fastcgi_finish_request 或同步模式';
        }
        if (!$info['server']['fastcgi_finish']) {
            $rec[] = '⚠️ fastcgi_finish_request 不可用, 无法在响应后后台执行';
        }
        if (empty($rec)) {
            $rec[] = '✅ 服务器配置良好, 异步任务模式可正常工作';
        }
        wp_send_json_success($info);
    }
    /**
     * v7.1.0: 并发为每个语义核节点调用 AI 生成画面 Prompt。
     *
     * 策略:
     *   1. 优先用 curl_multi 真并发 (N 节点总耗时 ≈ 单次调用, 不是 N 倍)
     *   2. curl_multi 不可用 → 串行调 Dispatcher::chat (保留 failover/审计)
     *   3. 单节点失败 → 返回 ok=false, 让调用方降级本地组装
     *
     * 计费保留:
     *   - curl_multi 模式: 手动写 linked3_usage_logs 表 (绕过 Dispatcher 但保留计费)
     *   - 串行模式: 走 Dispatcher::chat, 计费由 Dispatcher 自动处理
     *
     * @param array  $nodes     语义核节点数组
     * @param string $styleId   风格 ID
     * @param string $platform  目标平台 (midjourney/sdxl/dalle)
     * @param string $styleName 风格名
     * @return array 每节点结果 {index: {ok, content, usage}} + __mode 字段
     */
        public static function genesisParallelGeneratePrompts(array $nodes, string $styleId, string $platform, string $styleName): array
     { return Linked3_Genesis_Helpers::genesisParallelGeneratePrompts($nodes, $styleId, $platform, $styleName); }
    /**
     * v7.1.0: curl_multi 真并发生成每节点 Prompt。
     *
     * 用 Provider Strategy 构建 N 个请求, curl_multi 并发发送。
     * 手动写 linked3_usage_logs 保留计费 (绕过 Dispatcher 但不丢审计)。
     *
     * @return array|null 成功返回结果数组, 失败返回 null (让调用方降级)
     */
    public static function genesisCurlMultiPrompts(array $nodes, string $providerSlug, string $model, string $styleName, string $platform, string $styleId = "", ?array $seedDNA = null): ?array
    {
        // 获取 Provider Strategy
        $factory = \Linked3\Classes\Core\Providers\Linked3_Provider_Factory::instance();
        $provider = $factory->make($providerSlug);
        if (!$provider) return null;
        // 读 API key (从 option, 用 Crypto 解密)
        $savedKeys = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        $rawKeys = !empty($savedKeys[$providerSlug])
            ? array_filter(array_map('trim', explode("\n", (string) $savedKeys[$providerSlug])))
            : [];
        $apiKey = '';
        foreach ($rawKeys as $k) {
            $decrypted = \Linked3\Includes\Linked3_Crypto::decrypt((string) $k);
            if ($decrypted !== '') {
                $apiKey = $decrypted;
                break;
            }
        }
        if ($apiKey === '') return null;  // 无 API key → 降级串行 (让 Dispatcher 报明确错误)
        $savedBases = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_api_bases', []);
        $apiBase = $savedBases[$providerSlug] ?? '';
        $config = [
            'api_key'  => $apiKey,
            'api_base' => $apiBase,
            'model'    => $model,
        ];
        // 为每个节点构建 curl handle
        $handles = [];
        // ── FIX v16.0.1: Guard curl_multi for restricted environments ─────
        if (!function_exists('curl_multi_init')) return null;
        $mh = curl_multi_init();
        if ($mh === false) return null;
        foreach ($nodes as $i => $node) {
            $prompt = self::genesisBuildNodePrompt($node, $styleName, $platform, $styleId, $seedDNA);
            $messages = [['role' => 'user', 'content' => $prompt]];
            $options = [
                'model'             => $model,
                'temperature'       => 0.6,    // v7.1.1: 0.7→0.6 减少随机循环
                'max_tokens'        => 800,    // v7.1.1: 500→800 防截断循环
                'frequency_penalty' => 0.3,    // v7.1.1: 防重复 token
                'presence_penalty'  => 0.2,    // v7.1.1: 鼓励新内容
            ];
            try {
                $url     = $provider->build_api_url('chat', $config);
                // v27.1.1: Validate host against Safe_Remote allow-list before cURL
                $url_host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
                $allowed  = method_exists('\Linked3\Includes\Http\Linked3_Safe_Remote', 'get_allowed_hosts')
                    ? \Linked3\Includes\Http\Linked3_Safe_Remote::get_allowed_hosts()
                    : [];
                if ($url_host !== '' && !in_array($url_host, $allowed, true)) {
                    $handles[$i] = null;
                    continue;
                }
                $headers = $provider->get_api_headers($config);
                $headers['Accept'] = 'application/json';
                $payload = $provider->format_chat_payload($messages, $options, $config);
                $headerLines = [];
                foreach ($headers as $k => $v) {
                    $headerLines[] = $k . ': ' . $v;
                }
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => $headerLines,
                    CURLOPT_POSTFIELDS     => wp_json_encode($payload),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 25,    // v7.1.3: 60→25 防止服务器整体超时
                    CURLOPT_CONNECTTIMEOUT => 8,    // v7.1.3: 10→8
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_DNS_CACHE_TIMEOUT => 60, // v7.1.3: DNS 缓存
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[$i] = $ch;
            } catch (\Throwable $e) {
                $handles[$i] = null;
            }
        }
        // 并发执行 (轮询直到全部完成)
        $active = null;
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh, 1.0);  // 等待 1 秒, 减少CPU空转
            }
        } while ($active && $status === CURLM_OK);
        // 收集响应
        $results = [];
        $userId = get_current_user_id();
        $logTable = $GLOBALS['wpdb']->prefix . 'linked3_usage_logs';
        foreach ($handles as $i => $ch) {
            if ($ch === null) {
                $results[$i] = ['ok' => false, 'error' => 'build_request_failed'];
                continue;
            }
            $response = curl_multi_getcontent($ch);
            $err      = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            if ($err || $httpCode >= 400) {
                $results[$i] = ['ok' => false, 'error' => $err ?: 'HTTP ' . $httpCode];
                continue;
            }
            try {
                $body   = json_decode($response, true);
                $parsed = $provider->parse_chat_response($body, $config);
                $usage  = $parsed['usage'] ?? [];
                // 手动记录 usage (保留计费, 绕过 Dispatcher 但不丢审计)
                if (!empty($usage['total_tokens'])) {
                    $GLOBALS['wpdb']->insert($logTable, [
                        'user_id'           => $userId,
                        'module'            => 'genesis',
                        'provider'          => $providerSlug,
                        'model'             => $model,
                        'prompt_tokens'     => $usage['prompt_tokens'] ?? 0,
                        'completion_tokens' => $usage['completion_tokens'] ?? 0,
                        'total_tokens'      => $usage['total_tokens'] ?? 0,
                        'cost_usd'          => 0,
                        'status'            => 'ok',
                        'error_code'        => '',
                    ], ['%d', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%s', '%s']);
                }
                $results[$i] = [
                    'ok'      => true,
                    'content' => $parsed['content'],
                    'usage'   => $usage,
                ];
            } catch (\Throwable $e) {
                $results[$i] = ['ok' => false, 'error' => $e->getMessage()];
            }
        }
        curl_multi_close($mh);
        return $results;
    }
    /**
     * v7.1.0: 串行降级 — 走 Dispatcher::chat (保留 failover/审计/rate limit)。
     *
     * 当 curl_multi 不可用或 API key 未配置时使用。
     * 慢但稳: N 节点 × 单次调用时间 (10 节点 ≈ 20-30s)。
     */
        public static function genesisSerialPrompts(array $nodes, string $providerSlug, string $model, string $styleName, string $platform, string $styleId = "", ?array $seedDNA = null): array
     { return Linked3_Genesis_Helpers::genesisSerialPrompts($nodes, $providerSlug, $model, $styleName, $platform, $styleId, $seedDNA); }
    /**
     * v7.1.2: 为单个语义核节点构建 AI 画面 Prompt 生成提示词。
     *
     * v7.1.2 改进 (修复 v7.1.1):
     *   - 去掉方括号模板标记 [subject] (AI 会照抄到输出)
     *   - 去掉 "Panel info:" / "Clause" 等结构标记 (会被 isAIPromptDegraded 抓)
     *   - 改用自然语言描述 + Few-shot 示例
     *
     * @param array  $node       语义核节点
     * @param string $styleName  风格名
     * @param string $platform   目标平台
     * @return string AI 提示词
     */
        public static function genesisBuildNodePrompt(array $node, string $styleName, string $platform, string $styleId = '', ?array $seedDNA = null): string
     { return Linked3_Genesis_Helpers::genesisBuildNodePrompt($node, $styleName, $platform, $styleId, $seedDNA); }
    /**
     * v7.1.0: 清洗 AI 返回的 Prompt (去 markdown 包裹, 去多余换行, 补平台参数)。
     *
     * @param string $raw     AI 原始输出
     * @param string $platform 目标平台
     * @return string 清洗后的 Prompt
     */
        public static function cleanAIPrompt(string $raw, string $platform): string
     { return Linked3_Genesis_Helpers::cleanAIPrompt($raw, $platform); }
    /**
     * v7.1.2: 检测 AI Prompt 是否劣化 (循环/重复/过短/过长/结构标记泄漏)。
     *
     * v7.1.2 改进 (修复 v7.1.1 漏检 "team team the team's team"):
     *   - 旧版只检测严格连续重复, 漏检 "X X the X's X" 这种间隔重复
     *   - 新版加滑动窗口重复率 + 2-gram 重复 + 3-gram 重复
     *
     * 检测规则:
     *   1. 长度: <50 或 >2000 字符 → 劣化
     *   2. 词数: <10 词 → 劣化
     *   3. 严格连续重复: 同词连续 3+ 次 → 劣化
     *   4. 滑动窗口重复率: 任意 20 词窗口内有 5+ 重复 → 劣化
     *   5. 2-gram 重复: 同一 2-gram 出现 3+ 次 → 劣化
     *   6. 单词频率: 某词占比 >25% → 劣化
     *   7. 中文占比: >20% → 劣化
     *   8. 结构标记泄漏: 含 "[subject]" / "Panel info:" / "Clause" → 劣化 (AI 照搬提示词)
     *   9. 平台参数损坏: "--" 出现 3+ 次 → 劣化
     *
     * @param string $prompt 待检测的 Prompt
     * @return bool true=劣化应降级, false=可用
     */
        public static function isAIPromptDegraded(string $prompt): bool
     { return Linked3_Genesis_Helpers::isAIPromptDegraded($prompt); }
    /**
     * v8.0.0 M1: 分镜数强制守卫 — 用户指定N=返回N
     *
     * 策略:
     *   - 节点数 > 目标: 按故事时间线均匀截断 (保留首尾+中间均匀采样)
     *   - 节点数 < 目标: 从原文补充节点 (按句号切分填补)
     *   - 节点数 = 目标: 直接通过
     *
     * @param array $nodes AI返回的节点
     * @param int $targetPanels 目标分镜数
     * @param string $script 原始剧本 (补齐用)
     * @param string $styleName 风格名
     * @return array 强制为 targetPanels 个节点
     */
        public static function enforcePanelCount(array $nodes, int $targetPanels, string $script, string $styleName): array
     { return Linked3_Genesis_Helpers::enforcePanelCount($nodes, $targetPanels, $script, $styleName); }
    /**
     * v7.2.0: 按章节标记拆分剧本
     *
     * 支持多种章节标记:
     *   - auto: 自动检测 (优先级: 【场景】> 第X章 > Chapter X > --- > 空行)
     *   - bracket: 【场景1】【场景2】
     *   - chapter_cn: 第一章/第1章/第1节
     *   - chapter_en: Chapter 1 / Chapter I
     *   - separator: --- 分隔符
     *   - blank_line: 空行分段
     *
     * @param string $script 剧本
     * @param string $marker 章节标记类型
     * @return array 语义核节点数组
     */
        public static function splitByChapters(string $script, string $marker = 'auto'): array
     { return Linked3_Genesis_Helpers::splitByChapters($script, $marker); }
    /**
     * v7.2.1: 精炼模式 — AI 先重构提炼故事核心情节链, 再按目标分镜数均匀分配
     *
     * 适用场景: 长文 + 少分镜 (如 5000 字故事只要 5 个分镜)
     *
     * 流程:
     *   1. AI 读取全文, 提炼故事核心情节链 (开端→发展→高潮→结局)
     *   2. 按目标分镜数 N, 从情节链中均匀选取 N 个关键节点
     *   3. 每个节点生成语义核元数据 (location/characters/action/mood/shot/angle/comp)
     *
     * 与 auto 模式的区别:
     *   - auto: 按字数算分镜数 (长文→多分镜)
     *   - refine: 用户指定分镜数, AI 精炼内容 (长文→少分镜)
     *
     * @param string $script 剧本
     * @param int $targetPanels 目标分镜数
     * @param string $styleName 风格名
     * @param string $styleId 风格 ID
     * @return array 语义核节点数组
     */
        public static function genesisRefineAndSplit(string $script, int $targetPanels, string $styleName, string $styleId = ''): array
     { return Linked3_Genesis_Helpers::genesisRefineAndSplit($script, $targetPanels, $styleName, $styleId); }
    /**
     * v7.2.0: 获取风格自适应示例 (不再硬编码驱魔录)
     *
     * 根据风格 ID 返回匹配的示例节点, 让 AI 模仿输出
     * 如果风格未匹配, 返回通用示例
     *
     * @param string $styleId 风格 ID
     * @param string $styleName 风格名
     * @return array 示例节点数组
     */
        public static function getStyleAdaptiveExamples(string $styleId, string $styleName): array
     { return Linked3_Genesis_Helpers::getStyleAdaptiveExamples($styleId, $styleName); }
    /**
     * v7.4.0: 获取风格提示词 (从 StyleEngine 加载)
     */
        public static function getStyleHint(string $styleId, string $styleName): string
     { return Linked3_Genesis_Helpers::getStyleHint($styleId, $styleName); }
    /**
     * v7.1.0: FP 部剥骨提纯语义核 — 融入 deai_5d 思想。
     *
     * 借鉴 deai_5d FP 部"语义溯源院"的剥骨原则:
     *   - 假设原文皆为机器伪装
     *   - 剥离一切修饰词/形容词/副词/连接词
     *   - 提取纯语义核(人物+事件+地点)
     *   - 语义零遗漏
     *
     * v7.1.1 改进:
     *   - auto 模式改为动态 N (3-15), 不再硬拆 10 个
     *   - 强约束改为"至少 3 个, 至多 N 个", 让 AI 按故事节奏决定
     *
     * 改进点 (修复"永远只1个分镜"bug):
     *   1. AI 只输出"语义核节点元数据"(短), 不输出 prompt_en → 小模型能稳定返回 N 个
     *   2. 强约束: 必须返回正好 N 个节点, 每个 node_id 1..N 连续
     *   3. 自检重试: 如果返回少于 N/2, 重试一次 (temperature 提高到 0.9)
     *   4. 动态示例: 示例分镜数 = min(N, 4), 让 AI 模仿"输出多个"
     *
     * @param string $script       故事文本 (已截断)
     * @param int    $targetPanels 目标分镜数 (auto 模式下为上限)
     * @param string $styleName    风格名
     * @param bool   $isAuto       是否 auto 模式 (动态 N)
     * @param string $styleId      风格 ID (v7.2.0 用于风格适配)
     * @return array 语义核节点数组
     */
        public static function genesisFPExtractCores(string $script, int $targetPanels, string $styleName, bool $isAuto = false, string $styleId = ''): array
     { return Linked3_Genesis_Helpers::genesisFPExtractCores($script, $targetPanels, $styleName, $isAuto, $styleId); }
    /**
     * v7.1.0: 解析 FP 剥骨返回的语义核节点 JSON。
     *
     * 支持多种格式:
     *   - {"nodes":[...]}
     *   - [...]
     *   - markdown 代码块包裹
     *   - 前后有解释文字
     *
     * @param string $raw AI 返回的原始文本
     * @return array 语义核节点数组
     */
        public static function parseFPNodesJson(string $raw): array
     { return Linked3_Genesis_Helpers::parseFPNodesJson($raw); }
    /**
     * v7.1.0: 标准化 FP 节点 (补全缺失字段, 保证镜头/构图多样性)。
     *
     * @param array $nodes 原始节点数组
     * @return array 标准化后的节点数组
     */
        public static function normalizeFPNodes(array $nodes): array
     { return Linked3_Genesis_Helpers::normalizeFPNodes($nodes); }
    /**
     * v7.0.5: 多层解析 AI 返回的分镜 JSON。
     */
        public static function v7ParsePanels(string $raw): array
     { return Linked3_Genesis_Helpers::v7ParsePanels($raw); }
    /**
     * v7.0.5: 标准化分镜数组。
     */
        public static function normalizePanels(array $panels): array
     { return Linked3_Genesis_Helpers::normalizePanels($panels); }
    /**
     * v6.6.2: AI 直接生成 N 个分镜 (不固定场景×格子, 按故事节奏自然分配)。
     *
     * AI 返回每个分镜的完整信息: scene_id, location, characters, action, mood, shot, angle, comp
     * 有的场景可能有 1 格, 有的可能有 5-10 格 — 完全由故事节奏决定
     */
        public static function genesisAIGeneratePanels(string $script, int $targetPanels, string $styleId, bool $isAuto): array
     { return Linked3_Genesis_Helpers::genesisAIGeneratePanels($script, $targetPanels, $styleId, $isAuto); }
    /**
     * v7.0.4: 兜底解析 — 如果JSON解析失败, 尝试从文本中提取分镜。
     */
        public static function fallbackParsePanels(string $raw, string $originalScript): array
     { return Linked3_Genesis_Helpers::fallbackParsePanels($raw, $originalScript); }
    /**
     * v6.6.2: 解析 AI 返回的分镜 JSON。
     */
        public static function parseGenesisPanelsJson(string $raw): array
     { return Linked3_Genesis_Helpers::parseGenesisPanelsJson($raw); }
    /**
     * v6.6.2: 格式化分镜 (降级模式用)。
     */
        public static function formatGenesisPanel(array $panel, array $assembled, array $pqs): array
     { return Linked3_Genesis_Helpers::formatGenesisPanel($panel, $assembled, $pqs); }
    /**
     * v9.0.0: 全新集成模式 — 整合 M1-M6 所有模块
     *
     * 流程: Story Parser → FP真剥骨 → 场景三轴路由 → 三层Prompt组装 → PQS 13维校验 → 劣化诊断
     *
     * AJAX: linked3_genesis_generate_v9
     */
}
