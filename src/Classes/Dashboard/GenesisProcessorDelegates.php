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
        // Phase 1: Initialize configuration
        $config = self::initMultiConfig($script, $styleId, $platform, $panelCountRaw, $extraOptions);
        if ($progressCb) $progressCb(5, 'init', '初始化风格配置...');

        // Phase 2: Extract nodes (FP extract / chapter / refine / fallback)
        $nodes = self::extractNodes($script, $config, $progressCb);
        $nodes = GenesisHelpers::enforcePanelCount($nodes, $config['targetPanels'], $script, $config['styleName']);
        if ($progressCb) $progressCb(25, 'panel_count_enforced', '分镜数强制: ' . count($nodes) . ' 个 (目标 ' . $config['targetPanels'] . ')');

        // Phase 3: Parallel AI generation + assembly
        $results = self::generateAndAssemble($nodes, $config, $progressCb);

        // Phase 4: Style audit + return
        return self::finalizeResults($results, $nodes, $config, $progressCb);
    }

    /**
     * Initialize configuration for multi-generate.
     */
    private static function initMultiConfig(string $script, string $styleId, string $platform, string $panelCountRaw, array $extraOptions): array
    {
        $seedId = $extraOptions['seed_id'] ?? '';
        $seedDNA = null;
        if (!empty($seedId) && class_exists('\GenesisSeedDNA')) {
            $seedDNA = \GenesisSeedDNA::get($seedId);
        }
        $splitMode = $extraOptions['split_mode'] ?? 'auto';
        $chapterMarker = $extraOptions['chapter_marker'] ?? 'auto';
        $isAuto = ($panelCountRaw === 'auto');
        if ($isAuto) {
            $scriptLen = mb_strlen($script);
            $estimated = intval($scriptLen / 100);
            $targetPanels = max(3, min(15, $estimated));
        } else {
            $targetPanels = max(5, min(200, (int)$panelCountRaw));
        }
        $chapterNodes = [];
        if ($splitMode === 'chapter') {
            $chapterNodes = GenesisHelpers::splitByChapters($script, $chapterMarker);
            if (count($chapterNodes) >= 2) {
                $targetPanels = count($chapterNodes);
            }
        }
        if ($splitMode === 'refine' && $isAuto) {
            $targetPanels = 5;
        }
        $styleIndex = \GenesisAtomIndex::instance();
        $styleConfig = $styleIndex->getStyleConfig($styleId);
        $styleName = $styleConfig['name_cn'] ?? $styleId;
        $primaryProvider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $retryProvider = $primaryProvider === 'deepseek' ? 'zhipu' : 'deepseek';
        $savedModelsRetry = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $retryModel = $savedModelsRetry[$retryProvider] ?? 'deepseek-chat';
        return [
            'styleId' => $styleId,
            'styleName' => $styleName,
            'platform' => $platform,
            'isAuto' => $isAuto,
            'splitMode' => $splitMode,
            'targetPanels' => $targetPanels,
            'chapterNodes' => $chapterNodes,
            'seedDNA' => $seedDNA,
            'scriptTrimmed' => mb_substr($script, 0, 4000),
            'primaryProvider' => $primaryProvider,
            'retryProvider' => $retryProvider,
            'retryModel' => $retryModel,
        ];
    }

    /**
     * Extract nodes via FP/chapter/refine or fallback splitting.
     */
    private static function extractNodes(string $script, array $config, ?callable $progressCb): array
    {
        if ($progressCb) $progressCb(10, 'fp_extract', '阶段1: FP 剥骨提纯语义核 (AI 调用中, 约 10-30s)...');

        if ($config['splitMode'] === 'chapter' && count($config['chapterNodes']) >= 2) {
            $nodes = $config['chapterNodes'];
            if ($progressCb) $progressCb(20, 'chapter_done', '章节拆分完成, 共 ' . count($nodes) . ' 个章节节点');
            return $nodes;
        }

        if ($config['splitMode'] === 'refine') {
            if ($progressCb) $progressCb(10, 'refine', '精炼模式: AI 提炼故事核心情节链, 精炼为 ' . $config['targetPanels'] . ' 个分镜...');
            $nodes = GenesisHelpers::genesisRefineAndSplit($config['scriptTrimmed'], $config['targetPanels'], $config['styleName'], $config['styleId']);
            if ($progressCb) $progressCb(20, 'refine_done', '精炼完成, 共 ' . count($nodes) . ' 个分镜节点');
            return $nodes;
        }

        $nodes = GenesisHelpers::genesisFPExtractCores($config['scriptTrimmed'], $config['targetPanels'], $config['styleName'], $config['isAuto'], $config['styleId']);

        // Fallback if FP extraction failed
        if (count($nodes) < 2) {
            if ($progressCb) $progressCb(15, 'fallback_split', 'FP 剥骨失败, 启用句号降级...');
            $nodes = self::fallbackSplitScript($script, $config['targetPanels']);
            if ($progressCb) $progressCb(20, 'fallback_split', '句号降级完成, 生成 ' . count($nodes) . ' 个节点');
        }

        return $nodes;
    }

    /**
     * Multi-level fallback splitting when FP extraction fails.
     */
    private static function fallbackSplitScript(string $script, int $targetPanels): array
    {
        // Level 1: Chinese punctuation
        $sentences = array_filter(array_map('trim', preg_split('/[。！？\n]+/u', $script)));
        $sentences = array_filter($sentences, fn($s) => mb_strlen($s) >= 5);

        // Level 2: English punctuation
        if (count($sentences) < 2) {
            $sentences = array_filter(array_map('trim', preg_split('/[.!?\n]+/u', $script)));
            $sentences = array_filter($sentences, fn($s) => mb_strlen($s) >= 5);
        }

        // Level 3: Commas
        if (count($sentences) < 2) {
            $sentences = array_filter(array_map('trim', preg_split('/[，,;；]+/u', $script)));
            $sentences = array_filter($sentences, fn($s) => mb_strlen($s) >= 5);
        }

        // Level 4: Force split by character count
        if (count($sentences) < 2) {
            $sentences = [];
            $len = mb_strlen($script);
            for ($i = 0; $i < $len; $i += 50) {
                $chunk = trim(mb_substr($script, $i, 50));
                if (mb_strlen($chunk) >= 5) $sentences[] = $chunk;
            }
        }

        // Level 5: Use entire script as single node
        if (empty($sentences)) {
            $sentences = [mb_substr($script, 0, 200)];
        }

        $nodes = [];
        $fallbackCount = max($targetPanels, 10);
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
        return $nodes;
    }

    /**
     * Phase 3: Parallel AI generation + prompt assembly.
     */
    private static function generateAndAssemble(array $nodes, array $config, ?callable $progressCb): array
    {
        if ($progressCb) $progressCb(40, 'parallel_generate', '阶段2: curl_multi 并发调用 AI 生成画面 Prompt (' . count($nodes) . ' 节点, 约 15-40s)...');
        $parallelStart = microtime(true);
        $promptResults = GenesisHelpers::genesisParallelGeneratePrompts($nodes, $config['styleId'], $config['platform'], $config['styleName']);
        $parallelElapsedMs = (int) ((microtime(true) - $parallelStart) * 1000);
        if ($progressCb) $progressCb(80, 'assemble', '组装结果 + PQS 质检...');

        $assembler = new \GenesisPromptAssembler();
        $pqsChecker = new \GenesisPQSChecker();
        $results = [];
        $aiGeneratedCount = 0;
        $aiDegradedCount = 0;
        $aiRetryCount = 0;
        $localFallbackCount = 0;

        foreach ($nodes as $i => $node) {
            $result = self::assembleSingleNode($i, $node, $promptResults, $assembler, $pqsChecker, $config);
            $results[] = $result['panel'];
            $aiGeneratedCount += $result['ai_count'];
            $aiRetryCount += $result['retry_count'];
            $aiDegradedCount += $result['degraded_count'];
            $localFallbackCount += $result['local_count'];
        }

        // Store metrics for finalizeResults
        self::$lastMetrics = [
            'parallel_elapsed_ms' => $parallelElapsedMs,
            'ai_generated_count' => $aiGeneratedCount,
            'ai_retry_count' => $aiRetryCount,
            'ai_degraded_count' => $aiDegradedCount,
            'local_fallback_count' => $localFallbackCount,
            'parallel_mode' => $promptResults['__mode'] ?? 'unknown',
        ];

        return $results;
    }

    private static array $lastMetrics = [];

    /**
     * Assemble a single panel from node + AI result.
     */
    private static function assembleSingleNode(int $i, array $node, array $promptResults, \GenesisPromptAssembler $assembler, \GenesisPQSChecker $pqsChecker, array $config): array
    {
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
        $aiCount = 0;
        $retryCount = 0;
        $degradedCount = 0;
        $localCount = 0;

        // Try AI result first
        if (isset($promptResults[$i]) && ($promptResults[$i]['ok'] ?? false)) {
            $aiContent = trim($promptResults[$i]['content'] ?? '');
            if (!empty($aiContent)) {
                $cleaned = GenesisHelpers::cleanAIPrompt($aiContent, $config['platform']);
                if (!GenesisHelpers::isAIPromptDegraded($cleaned)) {
                    $promptEn = $cleaned;
                    $promptSource = 'ai';
                    $aiCount++;
                } else {
                    $aiDegraded = true;
                    $degradedCount++;
                }
            }
        }

        // AI degraded → retry with different provider
        if (empty($promptEn) && $aiDegraded) {
            $retryResult = self::retryWithFallbackProvider($node, $config);
            if ($retryResult !== null) {
                $promptEn = $retryResult;
                $promptSource = 'ai_retry';
                $retryCount++;
                $aiDegraded = false;
            }
        }

        // AI failed → local PromptAssembler
        if (empty($promptEn)) {
            $promptEn = self::assembleLocalPrompt($scene, $node, $assembler, $config);
            $promptSource = $promptEn ? 'local' : 'local_emergency';
            $localCount++;
        }

        // Ultimate fallback
        if (empty($promptEn)) {
            $promptEn = self::emergencyPrompt($node, $config['platform']);
            $promptSource = 'local_emergency';
        }

        $pqs = $pqsChecker->check(['prompt_en' => $promptEn, 'characters' => $node['characters'] ?? []]);
        $panel = [
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
            'style'      => $config['styleId'],
            'style_name' => $config['styleName'],
            'platform'   => $config['platform'],
            'platform_params' => '',
            'character_details' => [],
            'scene_detail' => '',
            'pqs'        => $pqs,
            'core_info'     => $node['core_info'] ?? '',
            'plot_point'    => $node['plot_point'] ?? '',
            'prompt_source' => $promptSource,
            'ai_degraded'   => $aiDegraded,
        ];

        return ['panel' => $panel, 'ai_count' => $aiCount, 'retry_count' => $retryCount, 'degraded_count' => $degradedCount, 'local_count' => $localCount];
    }

    /**
     * Retry with fallback provider when AI output is degraded.
     */
    private static function retryWithFallbackProvider(array $node, array $config): ?string
    {
        $retryPrompt = GenesisHelpers::genesisBuildNodePrompt($node, $config['styleName'], $config['platform'], $config['styleId'], $config['seedDNA']);
        try {
            $retryResult = AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => $retryPrompt]],
                [
                    'provider'          => $config['retryProvider'],
                    'model'             => $config['retryModel'],
                    'temperature'       => 0.5,
                    'max_tokens'        => 800,
                    'frequency_penalty' => 0.4,
                    'presence_penalty'  => 0.3,
                    'module'            => 'genesis',
                ],
                ['fallback_providers' => ['zhipu', 'siliconflow'], 'force_bypass_circuit' => true]
            );
            $retryCleaned = GenesisHelpers::cleanAIPrompt($retryResult['content'] ?? '', $config['platform']);
            if (!empty($retryCleaned) && !GenesisHelpers::isAIPromptDegraded($retryCleaned)) {
                return $retryCleaned;
            }
        } catch (\Throwable $e) {
            // Retry failed → fall through to local
        }
        return null;
    }

    /**
     * Assemble prompt using local PromptAssembler.
     */
    private static function assembleLocalPrompt(array $scene, array $node, \GenesisPromptAssembler $assembler, array $config): string
    {
        try {
            $selector = new \GenesisAtomSelector();
            $atoms = $selector->selectForScene($scene);
            $assembled = $assembler->assembleFull($atoms, $node, $config['styleId'], $config['platform']);
            return $assembled['prompt_with_params'] ?? '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Emergency fallback: construct prompt from node info directly.
     */
    private static function emergencyPrompt(array $node, string $platform): string
    {
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
            case 'sdxl':
                $platformParams = ' high quality, masterpiece, best quality';
                break;
            case 'dalle':
                $platformParams = ' photorealistic, cinematic, high-quality';
                break;
            default:
                $platformParams = ' --ar 2:3 --s 750 --style raw --no text';
                break;
        }
        return sprintf(
            '%s in %s, %s, %s from %s, %s atmosphere, cinematic lighting, detailed%s',
            $characters, $location, $action, $shotEn, $angleEn, $mood, $platformParams
        );
    }

    /**
     * Phase 4: Finalize results with style audit and metrics.
     */
    private static function finalizeResults(array $results, array $nodes, array $config, ?callable $progressCb): array
    {
        if ($progressCb) $progressCb(100, 'done', '生成完成! 共 ' . count($results) . ' 个分镜');

        if (empty($results)) {
            return [
                'panels'      => [],
                'total_panels' => 0,
                'total_scenes' => 0,
                'style'       => $config['styleId'],
                'platform'    => $config['platform'],
                'mode'        => 'v7_1_fp_parallel',
                'fp_cores'    => count($nodes),
                'is_auto'     => $config['isAuto'],
                'error'       => '生成 0 个分镜 — nodes=' . count($nodes),
            ];
        }

        $styleAudit = ['contaminated_count' => 0, 'clean_count' => count($results), 'issues' => []];
        if (class_exists('\GenesisStyleEngine') && !empty($results)) {
            $styleAudit = \GenesisStyleEngine::auditNodes($config['styleId'], $results);
        }

        $m = self::$lastMetrics;
        return [
            'panels'      => $results,
            'total_panels' => count($results),
            'total_scenes' => count(array_unique(array_column($results, 'scene_id'))),
            'style'       => $config['styleId'],
            'platform'    => $config['platform'],
            'mode'        => 'v7_1_fp_parallel',
            'fp_cores'    => count($nodes),
            'is_auto'     => $config['isAuto'],
            'target_panels' => $config['targetPanels'],
            'pipeline'    => 'fp_extract → curl_multi_parallel → assemble → style_audit',
            'ai_generated_count'   => $m['ai_generated_count'] ?? 0,
            'ai_retry_count'       => $m['ai_retry_count'] ?? 0,
            'ai_degraded_count'    => $m['ai_degraded_count'] ?? 0,
            'local_fallback_count' => $m['local_fallback_count'] ?? 0,
            'parallel_elapsed_ms'  => $m['parallel_elapsed_ms'] ?? 0,
            'parallel_mode'        => $m['parallel_mode'] ?? 'unknown',
            'retry_provider'       => $config['retryProvider'],
            'retry_model'          => $config['retryModel'],
            'style_audit'          => $styleAudit,
        ];
    }

    public static function genesisPreflightCheck(): array
    {
        // 1. AI Dispatcher
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
            if (function_exists('error_log')) {
                error_log('[linked3 genesis] curl_multi 不可用, 将降级串行模式 (慢)');
            }
        }
        return ['ok' => true, 'provider' => $providerSlug];
    }

    public static function ajax_genesis_test_connection() : mixed { return GenesisAjaxCore::ajax_genesis_test_connection(); }

}
