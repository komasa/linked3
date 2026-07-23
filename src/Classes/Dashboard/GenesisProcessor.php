<?php


declare(strict_types=1);
namespace Linked3\Classes\Dashboard;

use Linked3\Classes\Templates\TemplateManager;
use Linked3\Classes\SEO\Keyword\KeywordManager;
use Linked3\Classes\Core\AIDispatcher;
use Linked3\Includes\Http\SafeRemote;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/GenesisHelpers.php';

/**
 * Genesis Processor — owns ALL Genesis comic/manga script generation logic.
 *
 * This class is self-contained: all `self::` references resolve within the class.
 * Both DashboardAjaxRegistrar (legacy) and
 * DashboardGenesisActions (new) delegate to this class.
 *
 * @package Linked3
 * @subpackage Classes\Dashboard
 */
final class GenesisProcessor
{
    // v6.6.0: Genesis 漫画脚本引擎 AJAX
    // =================================================================
    /**
     * v6.6.0: Genesis 漫画脚本生成。
     * 5层管线: 剧本解析→原子选择→Prompt组装→PQS质检→平台适配
     */
        public static function ajax_genesis_generate() : mixed { return GenesisProcessorDelegates::ajax_genesis_generate(); }
    /**
     * v6.6.0: 获取 Genesis 风格列表。
     */
        public static function ajax_genesis_styles() : mixed { return GenesisProcessorDelegates::ajax_genesis_styles(); }
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
        public static function ajax_genesis_generate_multi() : mixed { return GenesisProcessorDelegates::ajax_genesis_generate_multi(); }
    /**
     * v7.1.5: 核心生成逻辑 (从 ajax_genesis_generate_multi 提取)
     *
     * 这个方法返回数据而非发送 JSON, 可被:
     *   1. ajax_genesis_generate_multi (同步模式, 兼容旧前端)
     *   2. GenesisJobRunner::runJob (异步模式, 推荐)
     *
     * @param string $script 剧本
     * @param string $styleId 风格 ID
     * @param string $platform 平台
     * @param string $panelCountRaw 分镜数量 ('auto' 或数字)
     * @param callable|null $progressCb 进度回调 fn(int $progress, string $stage, string $message)
     * @return array 生成结果
     */
        public static function genesisGenerateMultiInternal(string $script, string $styleId, string $platform, string $panelCountRaw, ?callable $progressCb = null, array $extraOptions = []) : mixed { return GenesisProcessorDelegates::genesisGenerateMultiInternal($script, $styleId, $platform, $panelCountRaw, $progressCb, $extraOptions); }
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
        public static function genesisPreflightCheck() : mixed { return GenesisProcessorDelegates::genesisPreflightCheck(); }
    /**
     * v7.1.3: 新增 — 轻量级连通性测试 AJAX。
     *
     * 用户点击「测试连接」按钮, 发起一次最小 AI 调用 (1 token),
     * 3s 内返回结果。用于排查 "Failed to fetch" 是否为网络/API 问题。
     */
        public static function ajax_genesis_test_connection() : mixed { return GenesisProcessorDelegates::ajax_genesis_test_connection(); }
    // ============================================================
    // v7.1.5: 异步任务模式 (彻底解决 Failed to fetch)
    // ============================================================
    /**
     * v7.1.5: 启动异步生成任务 — 立即返回 job_id, 后台执行
     *
     * 浏览器调用此接口, 50ms 内拿到 job_id, 然后轮询 ajax_genesis_poll_job
     * 这样彻底绕过 nginx/Apache/PHP-FPM 的 60s 超时限制
     */
        public static function ajax_genesis_start_job() : mixed { return GenesisAjaxCore::ajax_genesis_start_job(); }
    /**
     * v7.1.5: 轮询任务状态
     */
    static function ajax_genesis_poll_job(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? $_GET['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        $jobId = sanitize_text_field($_POST['job_id'] ?? $_GET['job_id'] ?? '');
        if (empty($jobId)) wp_send_json_error(['message' => __('缺少 job_id', 'linked3-ai')]);
        $status = \GenesisJobRunner::pollJob($jobId);
        if ($status['status'] === 'not_found') {
            wp_send_json_error(['message' => $status['message'], 'error_type' => 'job_not_found']);
        }
        wp_send_json_success($status);
    }
    /**
     * v7.1.5: 取消任务
     */
    static function ajax_genesis_cancel_job(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        $jobId = sanitize_text_field($_POST['job_id'] ?? '');
        $ok = \GenesisJobRunner::cancelJob($jobId);
        wp_send_json_success(['cancelled' => $ok]);
    }
    /**
     * v7.1.5: WP-Cron 回调 — 执行任务
     */
    static function cron_genesis_run_job(int $jobId): void {
        \GenesisJobRunner::runJob($jobId);
    }
    // ============================================================
    // v8.0.0: Seed DNA AJAX 端点
    // ============================================================
    /**
     * v8.0.0: 生成 Seed DNA
     */
    static function ajax_genesis_seed_generate(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        $script = wp_strip_all_tags(wp_unslash($_POST['script'] ?? ''));
        $styleId = sanitize_text_field($_POST['style'] ?? 'exorcism_dark_ink');
        $seedName = sanitize_text_field($_POST['seed_name'] ?? '未命名 Seed');
        if (empty($script)) wp_send_json_error(['message' => __('请输入剧本', 'linked3-ai')]);
        $styleConfig = \GenesisStyleEngine::load($styleId);
        $styleName = $styleConfig['name_cn'] ?? $styleId;
        try {
            $dna = \GenesisSeedDNA::generate($script, $styleId, $styleName);
            $dna['name'] = $seedName;
            $seedId = \GenesisSeedDNA::save($dna);
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
    static function ajax_genesis_seed_list(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        // v9.1.2: 优先用 CPT (GenesisSeedCPT::listAll), 内部已合并旧 option 存储
        if (class_exists('\Linked3\Classes\Dashboard\GenesisSeedCPT') && method_exists('\Linked3\Classes\Dashboard\GenesisSeedCPT', 'listAll')) {
            try {
                $seeds = \GenesisSeedCPT::listAll();
                wp_send_json_success(['seeds' => $seeds]);
                return;
            } catch (\Throwable $e) {
                if (function_exists('error_log')) {
                    error_log('[linked3] ajax_genesis_seed_list CPT query failed: ' . $e->getMessage());
                }
                // 落入下面的兜底
            }
        }
        // 兜底: 旧 option 存储 (GenesisSeedDNA::getAll)
        $seeds = [];
        if (class_exists('\Linked3\Classes\Dashboard\GenesisSeedDNA')) {
            try {
                $legacy = (array) \GenesisSeedDNA::getAll();
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
    static function ajax_genesis_seed_delete(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        $seedId = sanitize_text_field($_POST['seed_id'] ?? '');
        if (empty($seedId)) wp_send_json_error(['message' => __('seed_id 不能为空', 'linked3-ai')], 400);
        $ok = false;
        // 1) CPT 删除
        if (class_exists('\Linked3\Classes\Dashboard\GenesisSeedCPT')) {
            try {
                $seed = \GenesisSeedCPT::get($seedId);
                if (!empty($seed['post_id'])) {
                    $ok = \GenesisSeedCPT::trash($seed['post_id']);
                }
            } catch (\Throwable $e) {
                // 落入兜底
            }
        }
        // 2) 旧 option 删除
        if (!$ok && class_exists('\Linked3\Classes\Dashboard\GenesisSeedDNA') && method_exists('\Linked3\Classes\Dashboard\GenesisSeedDNA', 'delete')) {
            try { $ok = (bool) \GenesisSeedDNA::delete($seedId); } catch (\Throwable $e) {}
        }
        wp_send_json_success(['deleted' => $ok]);
    }
    /**
     * v8.0.0: 导出 Seed JSON (v9.1.2: 优先 CPT, 兜底旧 option)
     */
    static function ajax_genesis_seed_export(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        $seedId = sanitize_text_field($_POST['seed_id'] ?? '');
        if (empty($seedId)) wp_send_json_error(['message' => __('seed_id 不能为空', 'linked3-ai')], 400);
        $json = null;
        // 1) CPT 导出
        if (class_exists('\Linked3\Classes\Dashboard\GenesisSeedCPT')) {
            try {
                $seed = \GenesisSeedCPT::get($seedId);
                if (!empty($seed)) {
                    $json = wp_json_encode($seed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
            } catch (\Throwable $e) {}
        }
        // 2) 旧 option 导出
        if (empty($json) && class_exists('\Linked3\Classes\Dashboard\GenesisSeedDNA') && method_exists('\Linked3\Classes\Dashboard\GenesisSeedDNA', 'exportJSON')) {
            try { $json = \GenesisSeedDNA::exportJSON($seedId); } catch (\Throwable $e) {}
        }
        wp_send_json_success(['json' => $json, 'seed_id' => $seedId]);
    }
    /**
     * v7.1.5: 服务器诊断 — 返回 PHP/curl/timeout 配置信息
     * 用于排查 "Failed to fetch" 的服务器侧原因
     */
    static function ajax_genesis_server_diagnostic(): void {
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
                    'AIDispatcher'           => class_exists('\Linked3\Classes\Dashboard\AIDispatcher'),
                    'GenesisAtomIndex'       => class_exists('\Linked3\Classes\Dashboard\GenesisAtomIndex'),
                    'GenesisPromptAssembler' => class_exists('\Linked3\Classes\Genesis\GenesisPromptAssembler'),
                    'GenesisPQSChecker'      => class_exists('\Linked3\Classes\Genesis\GenesisPQSChecker'),
                    'GenesisJobRunner'       => class_exists('\Linked3\Classes\Dashboard\GenesisJobRunner'),
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
     { return GenesisHelpers::genesisFPExtractCores($script, $targetPanels, $styleName, $isAuto, $styleId); }
    /**
     * v9.0.0: 全新集成模式 — 整合 M1-M6 所有模块
     *
     * 流程: Story Parser → FP真剥骨 → 场景三轴路由 → 三层Prompt组装 → PQS 13维校验 → 劣化诊断
     *
     * AJAX: linked3_genesis_generate_v9
     */
}
