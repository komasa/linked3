<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;
class GenesisProcessorDelegates
{
    public static function ajax_genesis_generate() : mixed { return GenesisAjaxCore::ajax_genesis_generate(); }

    public static function ajax_genesis_styles() : mixed { return GenesisAjaxCore::ajax_genesis_styles(); }

    public static function ajax_genesis_generate_multi() : mixed { return GenesisAjaxCore::ajax_genesis_generate_multi(); }

    public static function genesisGenerateMultiInternal(string $script, string $styleId, string $platform, string $panelCountRaw, ?callable $progressCb = null, array $extraOptions = []): array
    {
        // Phase 1: Prepare generation context (seed DNA, split mode, target panels)
        $ctx = self::prepare_genesis_context($script, $styleId, $platform, $panelCountRaw, $extraOptions, $progressCb);

        // Phase 2: Extract nodes (FP extract / chapter split / refine / fallback)
        $nodes = self::extract_genesis_nodes($ctx, $progressCb);

        // Phase 3: Enforce panel count
        $nodes = \GenesisHelpers::enforcePanelCount($nodes, $ctx['target_panels'], $script, $ctx['style_name']);
        if ($progressCb) $progressCb(25, 'panel_count_enforced', '分镜数强制: ' . count($nodes) . ' 个 (目标 ' . $ctx['target_panels'] . ')');

        // Phase 4: Parallel generate prompts via curl_multi
        if ($progressCb) $progressCb(40, 'parallel_generate', '阶段2: curl_multi 并发调用 AI 生成画面 Prompt (' . count($nodes) . ' 节点)...');
        $parallelStart = microtime(true);
        $promptResults = \GenesisHelpers::genesisParallelGeneratePrompts($nodes, $styleId, $platform, $ctx['style_name']);
        $parallelElapsedMs = (int) ((microtime(true) - $parallelStart) * 1000);

        if ($progressCb) $progressCb(80, 'assemble', '组装结果 + PQS 质检...');

        // Phase 5: Assemble results with AI/retry/local fallback
        $assembled = self::assemble_genesis_results($nodes, $promptResults, $ctx, $parallelElapsedMs);

        if ($progressCb) $progressCb(100, 'done', '生成完成! 共 ' . count($assembled['results']) . ' 个分镜');

        // Phase 6: Build final response (with style audit)
        return self::build_genesis_response($assembled, $ctx, $parallelElapsedMs, $promptResults);
    }

    /**
     * Phase 1: Prepare generation context (seed DNA, split mode, target panels, style config).
     */
    private static function prepare_genesis_context(string $script, string $styleId, string $platform, string $panelCountRaw, array $extraOptions, ?callable $progressCb): array {
        $seedId = $extraOptions['seed_id'] ?? '';
        $seedDNA = null;
        if (!empty($seedId) && class_exists('\\GenesisSeedDNA')) {
            $seedDNA = \GenesisSeedDNA::get($seedId);
            if ($seedDNA && $progressCb) {
                $progressCb(3, 'seed_loaded', '已加载 Seed DNA: ' . ($seedDNA['name'] ?? $seedId));
            }
        }
        $splitMode = $extraOptions['split_mode'] ?? 'auto';
        $chapterMarker = $extraOptions['chapter_marker'] ?? 'auto';
        $isAuto = ($panelCountRaw === 'auto');
        if ($isAuto) {
            $estimated = intval(mb_strlen($script) / 100);
            $targetPanels = max(3, min(15, $estimated));
        } else {
            $targetPanels = max(5, min(200, (int)$panelCountRaw));
        }
        $chapterNodes = [];
        if ($splitMode === 'chapter') {
            if ($progressCb) $progressCb(8, 'chapter_split', '章节模式: 按章节标记拆分剧本...');
            $chapterNodes = \GenesisHelpers::splitByChapters($script, $chapterMarker);
            if (count($chapterNodes) >= 2) $targetPanels = count($chapterNodes);
        }
        if ($splitMode === 'refine' && $isAuto) {
            $targetPanels = 5;
        }
        if ($progressCb) $progressCb(5, 'init', '初始化风格配置...');
        $styleIndex = \GenesisAtomIndex::instance();
        $styleConfig = $styleIndex->getStyleConfig($styleId);
        $styleName = $styleConfig['name_cn'] ?? $styleId;
        $scriptTrimmed = mb_substr($script, 0, 4000);

        return [
            'script'         => $script,
            'script_trimmed' => $scriptTrimmed,
            'style_id'       => $styleId,
            'style_name'     => $styleName,
            'platform'       => $platform,
            'split_mode'     => $splitMode,
            'is_auto'        => $isAuto,
            'target_panels'  => $targetPanels,
            'chapter_nodes'  => $chapterNodes,
            'seed_dna'       => $seedDNA,
            'primary_provider' => get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow'),
        ];
    }

    /**
     * Phase 2: Extract nodes via FP extract / chapter split / refine / fallback.
     */
    private static function extract_genesis_nodes(array $ctx, ?callable $progressCb): array {
        $splitMode = $ctx['split_mode'];
        $chapterNodes = $ctx['chapter_nodes'];

        if ($splitMode === 'chapter' && count($chapterNodes) >= 2) {
            if ($progressCb) $progressCb(20, 'chapter_done', '章节拆分完成, 共 ' . count($chapterNodes) . ' 个章节节点');
            return $chapterNodes;
        }
        if ($splitMode === 'refine') {
            if ($progressCb) $progressCb(10, 'refine', '精炼模式: AI 提炼故事核心情节链...');
            $nodes = \GenesisHelpers::genesisRefineAndSplit($ctx['script_trimmed'], $ctx['target_panels'], $ctx['style_name'], $ctx['style_id']);
            if ($progressCb) $progressCb(20, 'refine_done', '精炼完成, 共 ' . count($nodes) . ' 个分镜节点');
            return $nodes;
        }

        $nodes = \GenesisHelpers::genesisFPExtractCores($ctx['script_trimmed'], $ctx['target_panels'], $ctx['style_name'], $ctx['is_auto'], $ctx['style_id']);
        if (count($nodes) >= 2) return $nodes;

        // Fallback: multi-level sentence splitting
        if ($progressCb) $progressCb(15, 'fallback_split', 'FP 剥骨失败, 启用句号降级...');
        $sentences = self::fallback_split_sentences($ctx['script']);
        $nodes = [];
        $fallbackCount = max($ctx['target_panels'], 10);
        foreach (array_slice($sentences, 0, $fallbackCount) as $i => $s) {
            $nodes[] = [
                'node_id'    => $i + 1,
                'core_info'  => mb_substr($s, 0, 30),
                'location'   => mb_substr($s, 0, 10),
                'characters' => [],
                'action'     => $s,
                'mood'       => '紧张',
                'shot'       => ['远景','中景','近景','特写'][$i % 4],
                'angle'      => ['平视','仰视','俯视'][$i % 3],
                'comp'       => ['三分法','对角线','中心构图'][$i % 3],
                'plot_point' => '',
            ];
        }
        if ($progressCb) $progressCb(20, 'fallback_split', '句号降级完成, 生成 ' . count($nodes) . ' 个节点');
        return $nodes;
    }

    /**
     * Fallback sentence splitting (5 levels of degradation).
     */
    private static function fallback_split_sentences(string $script): array {
        $sentences = array_filter(array_map('trim', preg_split('/[。！？\\n]+/u', $script)));
        $sentences = array_filter($sentences, fn($s) => mb_strlen($s) >= 5);
        if (count($sentences) >= 2) return array_values($sentences);

        $sentences = array_filter(array_map('trim', preg_split('/[.!?\\n]+/u', $script)));
        $sentences = array_filter($sentences, fn($s) => mb_strlen($s) >= 5);
        if (count($sentences) >= 2) return array_values($sentences);

        $sentences = array_filter(array_map('trim', preg_split('/[，,;；]+/u', $script)));
        $sentences = array_filter($sentences, fn($s) => mb_strlen($s) >= 5);
        if (count($sentences) >= 2) return array_values($sentences);

        $sentences = [];
        $len = mb_strlen($script);
        for ($i = 0; $i < $len; $i += 50) {
            $chunk = trim(mb_substr($script, $i, 50));
            if (mb_strlen($chunk) >= 5) $sentences[] = $chunk;
        }
        if (count($sentences) >= 2) return $sentences;

        return [mb_substr($script, 0, 200)];
    }

    /**
     * Phase 5: Assemble results with AI/retry/local fallback per node.
     */
    private static function assemble_genesis_results(array $nodes, array $promptResults, array $ctx, int $parallelElapsedMs): array {
        $assembler   = new \GenesisPromptAssembler();
        $pqsChecker  = new \GenesisPQSChecker();
        $results = [];
        $aiGeneratedCount = $aiDegradedCount = $aiRetryCount = $localFallbackCount = 0;
        $retryProvider = $ctx['primary_provider'] === 'deepseek' ? 'zhipu' : 'deepseek';
        $savedModelsRetry = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $retryModel = $savedModelsRetry[$retryProvider] ?? 'deepseek-chat';

        foreach ($nodes as $i => $node) {
            $result = self::assemble_single_node(
                $i, $node, $promptResults, $ctx, $assembler, $pqsChecker,
                $retryProvider, $retryModel,
                $aiGeneratedCount, $aiDegradedCount, $aiRetryCount, $localFallbackCount
            );
            $results[] = $result;
        }

        return [
            'results'              => $results,
            'ai_generated_count'   => $aiGeneratedCount,
            'ai_retry_count'       => $aiRetryCount,
            'ai_degraded_count'    => $aiDegradedCount,
            'local_fallback_count' => $localFallbackCount,
        ];
    }

    /**
     * Assemble a single node result with AI/retry/local emergency fallback.
     */
    private static function assemble_single_node(int $i, array $node, array $promptResults, array $ctx, $assembler, $pqsChecker, string $retryProvider, string $retryModel, int &$aiGeneratedCount, int &$aiDegradedCount, int &$aiRetryCount, int &$localFallbackCount): array {
        $scene = [
            'id'         => 'S' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
            'location'   => $node['location'] ?? '',
            'characters' => $node['characters'] ?? [],
            'action'     => $node['action'] ?? '',
            'mood'       => $node['mood'] ?? '',
        ];
        $promptEn = '';
        $promptSource = 'local';
        $aiDegraded = false;

        // Try AI result first
        if (isset($promptResults[$i]) && ($promptResults[$i]['ok'] ?? false)) {
            $aiContent = trim($promptResults[$i]['content'] ?? '');
            if (!empty($aiContent)) {
                $cleaned = \GenesisHelpers::cleanAIPrompt($aiContent, $ctx['platform']);
                if (!\GenesisHelpers::isAIPromptDegraded($cleaned)) {
                    $promptEn = $cleaned;
                    $promptSource = 'ai';
                    $aiGeneratedCount++;
                } else {
                    $aiDegraded = true;
                    $aiDegradedCount++;
                }
            }
        }

        // Retry with larger model if degraded
        if (empty($promptEn) && $aiDegraded) {
            $promptEn = self::retry_ai_generation($node, $ctx, $retryProvider, $retryModel, $aiRetryCount, $aiDegraded);
            if (!empty($promptEn)) $promptSource = 'ai_retry';
        }

        // Local PromptAssembler fallback
        if (empty($promptEn)) {
            try {
                $selector  = new \GenesisAtomSelector();
                $atoms     = $selector->selectForScene($scene);
                $assembled = $assembler->assembleFull($atoms, $node, $ctx['style_id'], $ctx['platform']);
                $promptEn = $assembled['prompt_with_params'] ?? '';
                $promptSource = 'local';
                $localFallbackCount++;
            } catch (\Throwable $e) {
                $promptSource = 'local_emergency';
            }
        }

        // Emergency: construct prompt from node info
        if (empty($promptEn)) {
            $promptEn = self::build_emergency_prompt($node, $ctx['platform']);
            $promptSource = 'local_emergency';
        }

        $pqs = $pqsChecker->check(['prompt_en' => $promptEn, 'characters' => $node['characters'] ?? []]);

        return [
            'panel_id'   => 'P' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
            'scene_id'   => $scene['id'],
            'location'   => $node['location'] ?? '',
            'action'     => $node['action'] ?? '',
            'mood'       => $node['mood'] ?? '',
            'focus'      => ($node['characters'][0] ?? ''),
            'shot'       => $node['shot'] ?? '中景',
            'angle'      => $node['angle'] ?? '平视',
            'comp'       => $node['comp'] ?? '三分法',
            'characters' => $node['characters'] ?? [],
            'prompt_en'  => $promptEn,
            'prompt_with_params' => $promptEn,
            'style'      => $ctx['style_id'],
            'style_name' => $ctx['style_name'],
            'platform'   => $ctx['platform'],
            'platform_params' => '',
            'character_details' => [],
            'scene_detail' => '',
            'pqs'        => $pqs,
            'core_info'     => $node['core_info'] ?? '',
            'plot_point'    => $node['plot_point'] ?? '',
            'prompt_source' => $promptSource,
            'ai_degraded'   => $aiDegraded,
        ];
    }

    /**
     * Retry AI generation with a larger model (deepseek-chat).
     */
    private static function retry_ai_generation(array $node, array $ctx, string $retryProvider, string $retryModel, int &$aiRetryCount, bool &$aiDegraded): string {
        $retryPrompt = \GenesisHelpers::genesisBuildNodePrompt($node, $ctx['style_name'], $ctx['platform'], $ctx['style_id'], $ctx['seed_dna']);
        try {
            $retryResult = AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => $retryPrompt]],
                [
                    'provider'          => $retryProvider,
                    'model'             => $retryModel,
                    'temperature'       => 0.5,
                    'max_tokens'        => 800,
                    'frequency_penalty' => 0.4,
                    'presence_penalty'  => 0.3,
                    'module'            => 'genesis',
                ],
                ['fallback_providers' => ['zhipu', 'siliconflow'], 'force_bypass_circuit' => true]
            );
            $retryCleaned = \GenesisHelpers::cleanAIPrompt($retryResult['content'] ?? '', $ctx['platform']);
            if (!empty($retryCleaned) && !\GenesisHelpers::isAIPromptDegraded($retryCleaned)) {
                $aiRetryCount++;
                $aiDegraded = false;
                return $retryCleaned;
            }
        } catch (\Throwable $e) {}
        return '';
    }

    /**
     * Emergency prompt builder from raw node info (no AI, no assembler).
     */
    private static function build_emergency_prompt(array $node, string $platform): string {
        $location = $node['location'] ?? 'scene';
        $action = $node['action'] ?? 'a character in action';
        $characters = implode(', ', $node['characters'] ?? []) ?: 'a lone figure';
        $shot = $node['shot'] ?? '中景';
        $angle = $node['angle'] ?? '平视';
        $mood = $node['mood'] ?? 'tense';
        $shotMap = ['远景'=>'wide shot','中景'=>'medium shot','近景'=>'close-up shot','特写'=>'extreme close-up'];
        $angleMap = ['平视'=>'eye level','仰视'=>'low angle','俯视'=>'high angle'];
        $shotEn = $shotMap[$shot] ?? 'medium shot';
        $angleEn = $angleMap[$angle] ?? 'eye level';
        switch ($platform) {
            case 'sdxl':  $platformParams = ' high quality, masterpiece, best quality'; break;
            case 'dalle': $platformParams = ' photorealistic, cinematic, high-quality'; break;
            default:      $platformParams = ' --ar 2:3 --s 750 --style raw --no text'; break;
        }
        return sprintf(
            '%s in %s, %s, %s from %s, %s atmosphere, cinematic lighting, detailed%s',
            $characters, $location, $action, $shotEn, $angleEn, $mood, $platformParams
        );
    }

    /**
     * Phase 6: Build final response with style audit.
     */
    private static function build_genesis_response(array $assembled, array $ctx, int $parallelElapsedMs, array $promptResults): array {
        $results = $assembled['results'];
        if (empty($results)) {
            return [
                'panels'        => [],
                'total_panels'  => 0,
                'total_scenes'  => 0,
                'style'         => $ctx['style_id'],
                'platform'      => $ctx['platform'],
                'mode'          => 'v7_1_fp_parallel',
                'fp_cores'      => 0,
                'is_auto'       => $ctx['is_auto'],
                'error'         => '生成 0 个分镜',
                'diagnostic'    => ['script_length' => mb_strlen($ctx['script'])],
            ];
        }
        $styleAudit = ['contaminated_count' => 0, 'clean_count' => count($results), 'issues' => []];
        if (class_exists('\\GenesisStyleEngine')) {
            $styleAudit = \GenesisStyleEngine::auditNodes($ctx['style_id'], $results);
        }
        $retryProvider = $ctx['primary_provider'] === 'deepseek' ? 'zhipu' : 'deepseek';
        $savedModelsRetry = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $retryModel = $savedModelsRetry[$retryProvider] ?? 'deepseek-chat';

        return [
            'panels'               => $results,
            'total_panels'         => count($results),
            'total_scenes'         => count(array_unique(array_column($results, 'scene_id'))),
            'style'                => $ctx['style_id'],
            'platform'             => $ctx['platform'],
            'mode'                 => 'v7_1_fp_parallel',
            'fp_cores'             => count($results),
            'is_auto'              => $ctx['is_auto'],
            'target_panels'        => $ctx['target_panels'],
            'pipeline'             => 'fp_extract → curl_multi_parallel → assemble → style_audit',
            'ai_generated_count'   => $assembled['ai_generated_count'],
            'ai_retry_count'       => $assembled['ai_retry_count'],
            'ai_degraded_count'    => $assembled['ai_degraded_count'],
            'local_fallback_count' => $assembled['local_fallback_count'],
            'parallel_elapsed_ms'  => $parallelElapsedMs,
            'parallel_mode'        => $promptResults['__mode'] ?? 'unknown',
            'retry_provider'       => $retryProvider,
            'retry_model'          => $retryModel,
            'style_audit'          => $styleAudit,
        ];
    }

    public static function genesisPreflightCheck(): array
    {
        // 1. AI Dispatcher
        // v7.1.4: 修复 class_exists 误报 — 短名 'AIDispatcher' 在有 use 别名的命名空间文件里
        // 可能返回 false (即使类已加载), 必须用全限定名
        if (!class_exists('\Linked3\Classes\Core\AIDispatcher')) {
            return [
                'ok'            => false,
                'message'       => __('AI Dispatcher 未加载, 插件可能未正确激活', 'linked3-ai'),
                'code'          => 'dispatcher_missing',
                'troubleshoot'  => '请停用并重新激活 Linked3 插件, 或检查 PHP 自动加载器',
            ];
        }
        // 2. Provider 配置
        $providerSlug = get_option(LINKED3_OPTION_PREFIX . 'default_provider', '');
        if (empty($providerSlug)) {
            return [
                'ok'            => false,
                'message'       => __('未配置 AI 服务商 (Provider)', 'linked3-ai'),
                'code'          => 'no_provider',
                'troubleshoot'  => '请到「AI 设置」选择服务商 (SiliconFlow / DeepSeek / 智谱 / 腾讯混元 等)',
            ];
        }
        // 3. API Key
        $savedKeys = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        $rawKeys = !empty($savedKeys[$providerSlug])
            ? array_filter(array_map('trim', explode("\n", (string) $savedKeys[$providerSlug])))
            : [];
        $hasValidKey = false;
        foreach ($rawKeys as $k) {
            $decrypted = \Linked3\Includes\Crypto::decrypt((string) $k);
            if ($decrypted !== '') {
                $hasValidKey = true;
                break;
            }
        }
        if (!$hasValidKey) {
            return [
                'ok'            => false,
                'message'       => __('服务商 [', 'linked3-ai') . $providerSlug . '] 的 API Key 未配置或解密失败',
                'code'          => 'no_api_key',
                'troubleshoot'  => '请到「AI 设置」→「' . $providerSlug . '」填入有效 API Key 后保存',
            ];
        }
        // 4. Provider Factory
        if (class_exists('\Linked3\Classes\Core\Providers\ProviderFactory')) {
            $factory = \Linked3\Classes\Core\Providers\ProviderFactory::instance();
            $provider = $factory->make($providerSlug);
            if (!$provider) {
                return [
                    'ok'            => false,
                    'message'       => __('服务商 [', 'linked3-ai') . $providerSlug . '] 策略类未注册',
                    'code'          => 'provider_not_registered',
                    'troubleshoot'  => '插件版本不匹配, 请更新到最新版',
                ];
            }
        }
        // 5. Genesis 核心类
        $requiredClasses = [
            'GenesisAtomIndex',
            'GenesisPromptAssembler',
            'GenesisPQSChecker',
            'GenesisAtomSelector',
        ];
        foreach ($requiredClasses as $cls) {
            if (!class_exists($cls)) {
                return [
                    'ok'            => false,
                    'message'       => __('Genesis 核心类 ', 'linked3-ai') . $cls . ' 未加载',
                    'code'          => 'genesis_class_missing',
                    'troubleshoot'  => '请检查 src/Classes/Genesis/ 目录文件完整性',
                ];
            }
        }
        // 6. curl 扩展 (并发模式依赖)
        if (!function_exists('curl_multi_init')) {
            // 不致命, 但会降级串行
            if (function_exists('error_log')) {
                error_log('[linked3 genesis] curl_multi 不可用, 将降级串行模式 (慢)');
            }
        }
        return ['ok' => true, 'provider' => $providerSlug];
    }

    public static function ajax_genesis_test_connection() : mixed { return GenesisAjaxCore::ajax_genesis_test_connection(); }

}

