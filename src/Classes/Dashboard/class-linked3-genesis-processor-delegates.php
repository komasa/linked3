<?php
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;
class Linked3_Genesis_Processor_Delegates
{
    public static function ajax_genesis_generate() : mixed { return Linked3_Genesis_Ajax_Core::ajax_genesis_generate(); }

    public static function ajax_genesis_styles() : mixed { return Linked3_Genesis_Ajax_Core::ajax_genesis_styles(); }

    public static function ajax_genesis_generate_multi() : mixed { return Linked3_Genesis_Ajax_Core::ajax_genesis_generate_multi(); }

    public static function genesisGenerateMultiInternal(string $script, string $styleId, string $platform, string $panelCountRaw, ?callable $progressCb = null, array $extraOptions = []): array
    {
        // v8.0.0: 加载 seed DNA (如果指定)
        $seedId = $extraOptions['seed_id'] ?? '';
        $seedDNA = null;
        if (!empty($seedId) && class_exists('\Linked3_Genesis_SeedDNA')) {
            $seedDNA = \Linked3_Genesis_SeedDNA::get($seedId);
            if ($seedDNA && $progressCb) {
                $progressCb(3, 'seed_loaded', '已加载 Seed DNA: ' . ($seedDNA['name'] ?? $seedId));
            }
        }
        // v7.2.0: 支持章节模式
        $splitMode = $extraOptions['split_mode'] ?? 'auto';
        $chapterMarker = $extraOptions['chapter_marker'] ?? 'auto';
        // v7.1.9: auto 模式根据剧本长度动态计算分镜数 (不再硬编码 10)
        $isAuto = ($panelCountRaw === 'auto');
        if ($isAuto) {
            $scriptLen = mb_strlen($script);
            // 动态计算: 每 80-120 字一个分镜, 范围 3-15
            $estimated = intval($scriptLen / 100);
            $targetPanels = max(3, min(15, $estimated));
        } else {
            $targetPanels = max(5, min(200, (int)$panelCountRaw));
        }
        // v7.2.0: 章节模式 — 按章节拆分, 每章一个节点
        $chapterNodes = [];
        if ($splitMode === 'chapter') {
            if ($progressCb) $progressCb(8, 'chapter_split', '章节模式: 按章节标记拆分剧本...');
            $chapterNodes = self::splitByChapters($script, $chapterMarker);
            if (count($chapterNodes) >= 2) {
                $targetPanels = count($chapterNodes);
            }
        }
        // v7.2.1: 精炼模式 — 长文短镜, AI 先提炼故事骨架再按目标分镜数分配
        // 精炼模式下, targetPanels 使用用户指定的固定数量 (不走 auto 动态计算)
        if ($splitMode === 'refine') {
            // 精炼模式: 用户必须指定分镜数, 如果是 auto 则默认 5
            if ($isAuto) {
                $targetPanels = 5;
            }
        }
        if ($progressCb) $progressCb(5, 'init', '初始化风格配置...');
        $styleIndex = \Linked3_Genesis_AtomIndex::instance();
        $styleConfig = $styleIndex->getStyleConfig($styleId);
        $styleName = $styleConfig['name_cn'] ?? $styleId;
        $scriptTrimmed = mb_substr($script, 0, 4000);
        // ============================================================
        // v7.1.0: 两阶段流程 (融入 deai_5d FP 部剥骨思想)
        // 阶段1: FP 剥骨提纯语义核 → N 个节点 (AI 调用一次, 输出短, 小模型能稳定返回)
        // 阶段2: 每节点并发调用 AI 生成画面 Prompt (curl_multi 真并发)
        // ============================================================
        if ($progressCb) $progressCb(10, 'fp_extract', '阶段1: FP 剥骨提纯语义核 (AI 调用中, 约 10-30s)...');
        // ---------- 阶段1: 节点提取 ----------
        // v7.2.0: 章节模式优先用章节节点, 跳过 AI 剥骨
        if ($splitMode === 'chapter' && count($chapterNodes) >= 2) {
            $nodes = $chapterNodes;
            if ($progressCb) $progressCb(20, 'chapter_done', '章节拆分完成, 共 ' . count($nodes) . ' 个章节节点');
        } elseif ($splitMode === 'refine') {
            // v7.2.1: 精炼模式 — AI 先提炼故事骨架, 再按目标分镜数分配
            if ($progressCb) $progressCb(10, 'refine', '精炼模式: AI 提炼故事核心情节链, 精炼为 ' . $targetPanels . ' 个分镜...');
            $nodes = self::genesisRefineAndSplit($scriptTrimmed, $targetPanels, $styleName, $styleId);
            if ($progressCb) $progressCb(20, 'refine_done', '精炼完成, 共 ' . count($nodes) . ' 个分镜节点');
        } else {
            $nodes = self::genesisFPExtractCores($scriptTrimmed, $targetPanels, $styleName, $isAuto, $styleId);
        }
        // v7.1.7: 剥骨失败 → 多级降级 (保证一定有分镜)
        if (count($nodes) < 2) {
            if ($progressCb) $progressCb(15, 'fallback_split', 'FP 剥骨失败, 启用句号降级...');
            // 降级1: 按中文标点切分
            $sentences = array_filter(array_map('trim', preg_split('/[。！？\n]+/u', $script)));
            $sentences = array_filter($sentences, function($s) { return mb_strlen($s) >= 5; });
            // 降级2: 中文标点切分失败 → 按英文标点切分
            if (count($sentences) < 2) {
                $sentences = array_filter(array_map('trim', preg_split('/[.!?\n]+/u', $script)));
                $sentences = array_filter($sentences, function($s) { return mb_strlen($s) >= 5; });
            }
            // 降级3: 英文标点也失败 → 按逗号切分
            if (count($sentences) < 2) {
                $sentences = array_filter(array_map('trim', preg_split('/[，,;；]+/u', $script)));
                $sentences = array_filter($sentences, function($s) { return mb_strlen($s) >= 5; });
            }
            // 降级4: 全部失败 → 按字数强制切分 (每 50 字一段)
            if (count($sentences) < 2) {
                $sentences = [];
                $len = mb_strlen($script);
                for ($i = 0; $i < $len; $i += 50) {
                    $chunk = trim(mb_substr($script, $i, 50));
                    if (mb_strlen($chunk) >= 5) $sentences[] = $chunk;
                }
            }
            // 降级5: 还是空 → 用整个剧本作为一个节点 (至少返回 1 个分镜)
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
            if ($progressCb) $progressCb(20, 'fallback_split', '句号降级完成, 生成 ' . count($nodes) . ' 个节点');
        }
        // v8.0.0 M1: 分镜数强制守卫 — 用户指定N=返回N (截断+补齐)
        $nodes = self::enforcePanelCount($nodes, $targetPanels, $script, $styleName);
        if ($progressCb) $progressCb(25, 'panel_count_enforced', '分镜数强制: ' . count($nodes) . ' 个 (目标 ' . $targetPanels . ')');
            // ---------- 阶段2: 并发调用 AI 生成每节点画面 Prompt ----------
            if ($progressCb) $progressCb(40, 'parallel_generate', '阶段2: curl_multi 并发调用 AI 生成画面 Prompt (' . count($nodes) . ' 节点, 约 15-40s)...');
            $parallelStart = microtime(true);
            $promptResults = self::genesisParallelGeneratePrompts($nodes, $styleId, $platform, $styleName);
            $parallelElapsedMs = (int) ((microtime(true) - $parallelStart) * 1000);
            if ($progressCb) $progressCb(80, 'assemble', '组装结果 + PQS 质检...');
            // ---------- 组装最终结果 (AI 成功的用 AI, 失败的用本地 PromptAssembler) ----------
            $assembler   = new \Linked3_Genesis_PromptAssembler();
            $pqsChecker  = new \Linked3_Genesis_PQSChecker();
            $results = [];
            $aiGeneratedCount = 0;
            $aiDegradedCount = 0;
            $aiRetryCount = 0;
            $localFallbackCount = 0;
            // v7.1.2: 主 provider 配置 (用于劣化重试)
            $primaryProvider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
            $retryProvider = $primaryProvider === 'deepseek' ? 'zhipu' : 'deepseek';
            $savedModelsRetry = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
            $retryModel = $savedModelsRetry[$retryProvider] ?? 'deepseek-chat';
            foreach ($nodes as $i => $node) {
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
                // 优先用 AI 并发生成的 Prompt
                if (isset($promptResults[$i]) && ($promptResults[$i]['ok'] ?? false)) {
                    $aiContent = trim($promptResults[$i]['content'] ?? '');
                    if (!empty($aiContent)) {
                        $cleaned = self::cleanAIPrompt($aiContent, $platform);
                        // v7.1.2: 验收门 — 检测循环/重复劣化
                        if (!self::isAIPromptDegraded($cleaned)) {
                            $promptEn = $cleaned;
                            $promptSource = 'ai';
                            $aiGeneratedCount++;
                        } else {
                            $aiDegraded = true;
                            $aiDegradedCount++;
                        }
                    }
                }
                // v7.1.2: AI 劣化 → 换大模型 deepseek-chat 串行重试一次
                if (empty($promptEn) && $aiDegraded) {
                    $retryPrompt = self::genesisBuildNodePrompt($node, $styleName, $platform, $styleId, $seedDNA);
                    try {
                        $retryResult = Linked3_AI_Dispatcher::instance()->chat(
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
                        $retryCleaned = self::cleanAIPrompt($retryResult['content'] ?? '', $platform);
                        if (!empty($retryCleaned) && !self::isAIPromptDegraded($retryCleaned)) {
                            $promptEn = $retryCleaned;
                            $promptSource = 'ai_retry';
                            $aiRetryCount++;
                            $aiDegraded = false;  // 重试成功, 清除劣化标记
                        }
                    } catch (\Throwable $e) {
                        // 重试也失败 → 走本地兜底
                    }
                }
                // AI 失败 + 重试也失败 → 降级本地 PromptAssembler 组装
                if (empty($promptEn)) {
                    try {
                        $selector  = new \Linked3_Genesis_AtomSelector();
                        $atoms     = $selector->selectForScene($scene);
                        $assembled = $assembler->assembleFull($atoms, $node, $styleId, $platform);
                        $promptEn = $assembled['prompt_with_params'] ?? '';
                        $promptSource = 'local';
                        $localFallbackCount++;
                    } catch (\Throwable $e) {
                        // v7.1.7: PromptAssembler 也失败 → 用节点信息直接构造 prompt
                        $promptSource = 'local_emergency';
                    }
                }
                // v7.1.7: 终极兜底 — 如果 promptEn 还是空, 用节点信息直接构造
                if (empty($promptEn)) {
                    $location = $node['location'] ?? 'scene';
                    $action = $node['action'] ?? 'a character in action';
                    $characters = implode(', ', $node['characters'] ?? []) ?: 'a lone figure';
                    $shot = $node['shot'] ?? '中景';
                    $angle = $node['angle'] ?? '平视';
                    $mood = $node['mood'] ?? 'tense';
                    // 中文镜头/角度翻译
                    $shotMap = ['远景'=>'wide shot','中景'=>'medium shot','近景'=>'close-up shot','特写'=>'extreme close-up'];
                    $angleMap = ['平视'=>'eye level','仰视'=>'low angle','俯视'=>'high angle'];
                    $shotEn = $shotMap[$shot] ?? 'medium shot';
                    $angleEn = $angleMap[$angle] ?? 'eye level';
                    // v19.55-fix: match() is PHP 8.0+, plugin requires PHP 7.4 — convert to switch.
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
                    $promptEn = sprintf(
                        '%s in %s, %s, %s from %s, %s atmosphere, cinematic lighting, detailed%s',
                        $characters, $location, $action, $shotEn, $angleEn, $mood, $platformParams
                    );
                    $promptSource = 'local_emergency';
                }
                $pqs = $pqsChecker->check(['prompt_en' => $promptEn, 'characters' => $node['characters'] ?? []]);
                $results[] = [
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
                    'style'      => $styleId,
                    'style_name' => $styleName,
                    'platform'   => $platform,
                    'platform_params' => '',
                    'character_details' => [],
                    'scene_detail' => '',
                    'pqs'        => $pqs,
                    // v7.1.0: 暴露 FP 剥骨结果 + Prompt 来源给前端展示
                    'core_info'     => $node['core_info'] ?? '',
                    'plot_point'    => $node['plot_point'] ?? '',
                    'prompt_source' => $promptSource,  // 'ai' / 'ai_retry' / 'local'
                    'ai_degraded'   => $aiDegraded,    // v7.1.1: AI 输出劣化标记
                ];
            }
            if ($progressCb) $progressCb(100, 'done', '生成完成! 共 ' . count($results) . ' 个分镜');
            // v7.1.7: 如果 results 为空, 记录诊断信息
            if (empty($results)) {
                return [
                    'panels'      => [],
                    'total_panels' => 0,
                    'total_scenes' => 0,
                    'style'       => $styleId,
                    'platform'    => $platform,
                    'mode'        => 'v7_1_fp_parallel',
                    'fp_cores'    => count($nodes),
                    'is_auto'     => $isAuto,
                    'error'       => '生成 0 个分镜 — nodes=' . count($nodes) . ', promptResults=' . json_encode(array_keys($promptResults)),
                    'diagnostic'  => [
                        'nodes_count'         => count($nodes),
                        'prompt_results_keys' => array_keys($promptResults),
                        'prompt_mode'         => $promptResults['__mode'] ?? 'unknown',
                        'script_length'       => mb_strlen($script),
                    ],
                ];
            }
            // v7.7.0: 风格污染审计
            $styleAudit = ['contaminated_count' => 0, 'clean_count' => count($results), 'issues' => []];
            if (class_exists('\Linked3_Genesis_StyleEngine') && !empty($results)) {
                $styleAudit = \Linked3_Genesis_StyleEngine::auditNodes($styleId, $results);
            }
            return [
                'panels'      => $results,
                'total_panels' => count($results),
                'total_scenes' => count(array_unique(array_column($results, 'scene_id'))),
                'style'       => $styleId,
                'platform'    => $platform,
                'mode'        => 'v7_1_fp_parallel',
                'fp_cores'    => count($nodes),
                'is_auto'     => $isAuto,
                'target_panels' => $targetPanels,
                'pipeline'    => 'fp_extract → curl_multi_parallel → assemble → style_audit',
                // v7.1.0: 并发质量指标
                'ai_generated_count'   => $aiGeneratedCount,
                'ai_retry_count'       => $aiRetryCount,
                'ai_degraded_count'    => $aiDegradedCount,
                'local_fallback_count' => $localFallbackCount,
                'parallel_elapsed_ms'  => $parallelElapsedMs,
                'parallel_mode'        => $promptResults['__mode'] ?? 'unknown',
                'retry_provider'       => $retryProvider,
                'retry_model'          => $retryModel,
                // v7.7.0: 风格污染审计报告
                'style_audit'          => $styleAudit,
            ];
    }

    public static function genesisPreflightCheck(): array
    {
        // 1. AI Dispatcher
        // v7.1.4: 修复 class_exists 误报 — 短名 'Linked3_AI_Dispatcher' 在有 use 别名的命名空间文件里
        // 可能返回 false (即使类已加载), 必须用全限定名
        if (!class_exists('\Linked3\Classes\Core\Linked3_AI_Dispatcher')) {
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
            $decrypted = \Linked3\Includes\Linked3_Crypto::decrypt((string) $k);
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
        if (class_exists('\Linked3\Classes\Core\Providers\Linked3_Provider_Factory')) {
            $factory = \Linked3\Classes\Core\Providers\Linked3_Provider_Factory::instance();
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
            'Linked3_Genesis_AtomIndex',
            'Linked3_Genesis_PromptAssembler',
            'Linked3_Genesis_PQSChecker',
            'Linked3_Genesis_AtomSelector',
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

    public static function ajax_genesis_test_connection() : mixed { return Linked3_Genesis_Ajax_Core::ajax_genesis_test_connection(); }

}
