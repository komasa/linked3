<?php

declare(strict_types=1);
/**
 * Three-Layer Prompt Assembler v8.1.0 — M2 三层 Prompt 架构
 *
 * 公理1·信息熵减: 漫画分镜 Prompt = META视觉定义层 + Script剧本控制层 + Validation验证层
 *   - L1 META: 约束"画什么风格" (Brand/Signature/Color/Mood/Culture/Platform/CharacterSeed/Lock)
 *   - L2 Script: 约束"讲什么故事" (Arc/Dialogue/EmotionMap/Transition/Pacing)
 *   - L3 Validation: 约束"是否达标" (视觉一致性/叙事完整性/角色一致性/品牌一致性)
 *
 * 替换废弃的 "a lone figure in [中文], [中文], [shot]..." 单行模板
 *
 * @package Linked3\AI\Pipeline
 * @since 8.1.0
 */

namespace Linked3\Classes\AI\Pipeline;

if (!defined('ABSPATH')) exit;

class PromptAssembler
{
    /**
     * v9.1.0-task3 — Seed 冲突优先级规则
     *
     * 规则1 (分类基础优先级, 数字越大优先级越高):
     *   CharacterSeed (char) > BrandSeed (brand) > SceneSeed (scene)
     *   > PropSeed (prop) > StyleSeed (style) > SoulSeed (soul)
     *
     * 规则2 (锁定级别, 同链位置时的 tiebreaker):
     *   Lock 项 (Critical) > Priority 项 (Important) > Flexible 项
     *
     * 规则3 (字段专属链, 覆盖规则1 的默认分类顺序):
     *   - color:     CharacterSeed costume > SceneSeed atmosphere > StyleSeed palette
     *   - signature: SoulSeed > StyleSeed > 其他 (灵魂风格优先)
     *   - mood:      SceneSeed 场景氛围 > CharacterSeed 角色情绪 (再缺失回落 EmotionMap)
     *   - culture:   BrandSeed > SceneSeed
     */
    const CATEGORY_DEFAULT_PRIORITY = [
        'char'  => 100,
        'brand' => 90,
        'scene' => 80,
        'prop'  => 70,
        'style' => 60,
        'soul'  => 50,
    ];

    /** 字段专属优先级链 (从高到低; 未列入链中的分类按链外基准分 10) */
    const FIELD_PRIORITY_CHAINS = [
        'color'     => ['char', 'scene', 'style'],                 // 规则3.1: costume > atmosphere > palette
        'signature' => ['soul', 'style', 'char', 'brand', 'scene', 'prop'], // 规则3.2: 灵魂风格优先
        'mood'      => ['scene', 'char'],                          // 规则3.3: 场景氛围 > 角色情绪
        'culture'   => ['brand', 'scene'],                         // 规则3.4: BrandSeed > SceneSeed
    ];

    /** 锁定级别加分 (tiebreaker; 远小于 chain_position 步长 10) */
    const LOCK_LEVEL_SCORE = [
        'critical'  => 9,   // Lock 项 / priority.critical
        'important' => 3,   // priority.important
        'flexible'  => 0,   // priority.flexible 或未声明
    ];

    /** chain_position 基准分 (链头); 每下降一位减 10 */
    const CHAIN_HEAD_SCORE = 100;
    /** 链外分类的 chain_position 分 */
    const CHAIN_OUT_OF_CHAIN_SCORE = 10;

    /**
     * 组装三层 Prompt
     *
     * @param array $shot_data {
     *   scene_type:    string (documentary_photo|watercolor_healing|...)
     *   seed_refs:     array  [CHAR_001, SCENE_003, STYLE_治愈, ...]
     *   arc_position:  string (开场|发展|高潮|收尾)
     *   dialogue:      string (画面文字/气泡)
     *   emotion:       string (从 EmotionMap 注入)
     *   transition:    string (与前后镜衔接)
     *   pacing:        string (快|中|慢|定格)
     *   fp_core:       array  FP真剥骨结构化语义核 {who/what/where/when/emotion/theme/action_en}
     *   shot:          string (远景|中景|近景|特写)
     *   angle:         string (平视|仰视|俯视)
     *   comp:          string (三分法|对角线|中心构图)
     *   platform:      string (midjourney|sdxl|flux|dalle)
     * }
     * @return array {prompt: string, meta: array, script: array, validation: array, skeleton_id: string}
     */
    public function assemble(array $shot_data): array
    {
        // 获取场景专属骨架
        $skeleton = SkeletonLibrary::get($shot_data['scene_type'] ?? 'documentary_photo');

        // L1: META 层 — 从 Seed 提取 VisualDNA + Lock + 场景签名
        $meta = $this->build_meta($shot_data, $skeleton);

        // L2: Script 层 — 叙事控制
        $script = $this->build_script($shot_data);

        // L3: Validation 层 — 质量标准
        $validation = $this->build_validation($shot_data, $skeleton);

        // 合并三层 → 最终 Prompt
        $prompt = $this->merge_layers($meta, $script, $skeleton, $shot_data);

        return [
            'prompt'     => $prompt,
            'meta'       => $meta,
            'script'     => $script,
            'validation' => $validation,
            'skeleton_id'=> $skeleton['skeleton_id'] ?? 'unknown',
        ];
    }

    /**
     * L1: META 层 — 视觉定义
     *
     * v9.1.0-task3: 增 Seed 冲突优先级解析, 把 resolved {color,signature,mood,culture}
     * 注入 META, 并在 `seed_conflicts` 字段记录被覆盖的候选 Seed (供前端显示冲突警告)。
     */
    private function build_meta(array $shot, array $skeleton): array
    {
        $meta = [
            'scene_id'       => $shot['scene_id'] ?? '',
            'brand'          => $shot['brand'] ?? '',
            'signature'      => $shot['signature'] ?? $skeleton['signature_default'] ?? '',
            'color'          => $shot['color'] ?? $skeleton['color_default'] ?? '',
            'mood'           => $shot['emotion'] ?? $shot['mood'] ?? 'neutral',
            'culture'        => $shot['culture'] ?? '',
            'platform'       => $shot['platform'] ?? 'midjourney',
            'character_seeds'=> [],
            'character_lock' => [],
            'seed_conflicts' => [],  // v9.1.0-task3 新增: 记录被覆盖的字段冲突
        ];

        // 一次性加载全部 seed_refs (供 resolve_seed_conflicts 与 character_seeds 复用)
        $seeds = $this->load_seed_refs($shot['seed_refs'] ?? []);

        // v9.1.0-task3: Seed 冲突优先级解析 (color/signature/mood/culture)
        $resolution = $this->resolve_seed_conflicts($seeds);

        // 注入 resolved 值 (覆盖 shot 默认值; 空值不覆盖)
        foreach (['color', 'signature', 'mood', 'culture'] as $field) {
            $resolved = $resolution['resolved'][$field] ?? null;
            if ($resolved !== null && $resolved !== '') {
                $meta[$field] = $resolved;
            }
        }
        $meta['seed_conflicts'] = $resolution['conflicts'];

        // 保留 v9.0.0 行为: CharacterSeed/BrandSeed VisualDNA + Lock 注入
        foreach ($seeds as $entry) {
            $category = $entry['category'];
            $ref      = $entry['seed_id'];
            $seed     = $entry['seed'];
            if ($category === 'char' || $category === 'brand') {
                $meta['character_seeds'][] = [
                    'seed_id'    => $ref,
                    'visual_dna' => $seed['visual_dna'] ?? [],
                ];
                // CharacterLock: Critical 项必须跨镜一致
                $priority = $seed['priority'] ?? [];
                if (!empty($priority['critical'])) {
                    $meta['character_lock'][$ref] = $priority['critical'];
                }
            }
        }

        return $meta;
    }

    /**
     * L2: Script 层 — 叙事控制
     */
    private function build_script(array $shot): array
    {
        return [
            'arc_position' => $shot['arc_position'] ?? 'development',  // 开场|发展|高潮|收尾
            'dialogue'     => $shot['dialogue'] ?? '',
            'emotion'      => $shot['emotion'] ?? 'neutral',
            'transition'   => $shot['transition'] ?? 'cut',
            'pacing'       => $shot['pacing'] ?? 'medium',  // fast|medium|slow|freeze
        ];
    }

    /**
     * L3: Validation 层 — 质量标准
     */
    private function build_validation(array $shot, array $skeleton): array
    {
        return [
            'visual_consistency'   => !empty($shot['seed_refs']),  // 有 Seed 引用 = 视觉一致性保障
            'narrative_completeness' => !empty($shot['fp_core']['what']),
            'character_consistency' => !empty($shot['seed_refs']),
            'brand_consistency'     => !empty($shot['brand']),
            'skeleton_matched'      => ($skeleton['skeleton_id'] ?? '') === ($shot['scene_type'] ?? ''),
        ];
    }

    /**
     * 合并三层 + 骨架 → 最终英文 Prompt
     *
     * 关键: FP 真剥骨的 action_en (中文→英文翻译) 注入骨架, 禁止中文直塞
     */
    private function merge_layers(array $meta, array $script, array $skeleton, array $shot): string
    {
        // v9.4.0: 融合 feicai4.0 叙事式 Prompt 写法 (替代关键词堆叠)
        // feicai 核心原则: 用完整叙事段落描述场景, 而非逗号分隔关键词
        // 模板: [景别], [环境]. [主体描述], [动作/状态]. [光线描述], [光影效果]. [角度/构图]. [氛围/情绪].

        $fp = $shot['fp_core'] ?? [];
        $subject = $fp['who'] ?? 'a lone figure';
        $action = $fp['action_en'] ?? $fp['what'] ?? 'standing still';
        $location = $fp['where'] ?? $shot['location'] ?? 'an unspecified location';
        $when = $fp['when'] ?? '';
        $mood = $this->map_emotion_en($script['emotion'] ?? 'neutral');
        $theme = $fp['theme'] ?? '';

        // 镜头/构图 — 融合 feicai 镜头语言
        $shotMap = [
            '远景' => 'wide shot', '全景' => 'full shot', '中景' => 'medium shot',
            '近景' => 'close-up', '特写' => 'extreme close-up',
        ];
        $angleMap = [
            '平视' => 'eye level', '仰视' => 'low angle', '俯视' => 'high angle',
        ];
        $compMap = [
            '三分法' => 'rule of thirds', '对角线' => 'diagonal composition',
            '中心构图' => 'center composition', '对称式' => 'symmetric', '引导线' => 'leading lines',
        ];
        $shotEn = $shotMap[$shot['shot'] ?? ''] ?? 'medium shot';
        $angleEn = $angleMap[$shot['angle'] ?? ''] ?? 'eye level';
        $compEn = $compMap[$shot['comp'] ?? ''] ?? 'rule of thirds';

        // CharacterSeed VisualDNA 注入 — 融合 feicai 角色描述法
        $charDesc = '';
        if (!empty($meta['character_seeds'])) {
            $parts = [];
            foreach ($meta['character_seeds'] as $cs) {
                $vd = $cs['visual_dna'] ?? [];
                $desc = implode(', ', array_filter([
                    $vd['appearance'] ?? $vd['face'] ?? '',
                    $vd['clothing'] ?? $vd['costume'] ?? '',
                    $vd['distinctive_features'] ?? $vd['accessory'] ?? '',
                ]));
                if ($desc) $parts[] = $desc;
            }
            $charDesc = implode('; ', $parts);
        }

        // Signature (灵魂风格)
        $signature = $meta['signature'] ?? '';
        $color = $meta['color'] ?? '';

        // v9.4.0: 叙事式 Prompt 组装 — 融合 feicai gemini-image-prompt-guide
        $narrative = '';

        // 第一句: 景别 + 环境
        $narrative .= ucfirst($shotEn) . ', ' . $location;
        if ($when) $narrative .= ', ' . $when;
        $narrative .= '. ';

        // 第二句: 主体 + 动作
        $narrative .= $subject;
        if ($charDesc) $narrative .= ' (' . $charDesc . ')';
        $narrative .= ', ' . $action . '. ';

        // 第三句: 光线 + 色调 (融合 feicai 光影叙事功能)
        if ($color) {
            $narrative .= $this->describe_lighting($color, $mood) . '. ';
        }

        // 第四句: 角度 + 构图
        $narrative .= ucfirst($angleEn) . ', ' . $compEn . '. ';

        // 第五句: 氛围 + 情绪
        $narrative .= ucfirst($mood) . ' atmosphere';
        if ($theme) $narrative .= ', conveying ' . $theme;
        $narrative .= '.';

        // 签名风格
        if ($signature) {
            $narrative .= ' ' . $signature . ' style.';
        }

        // 追加平台参数
        $narrative .= $this->platform_params($meta['platform'], $skeleton);

        return $narrative;
    }

    /**
     * v9.4.0: 融合 feicai 光影叙事 — 将色调用叙事语言描述
     * feicai 原则: 不只说"冷蓝色调", 而是描述光影效果
     */
    private function describe_lighting(string $color, string $mood): string
    {
        $lightingMap = [
            '#808080' => 'Natural light, neutral tones, balanced shadows',
            '#4682B4' => 'Cool blue light, casting deep shadows that convey focus and intensity',
            '#FF6347' => 'Warm orange light, golden hour glow, creating a nostalgic and intimate mood',
            '#228B22' => 'Soft green-tinted light, natural and serene',
            '#FFD700' => 'Golden light, warm and hopeful, illuminating the scene with optimism',
            '#DC143C' => 'Dramatic red light, intense and passionate, heightening the tension',
            '#4B0082' => 'Deep purple light, mysterious and melancholic',
            '#2F4F4F' => 'Dark slate light, somber and heavy with shadow',
        ];

        $base = $lightingMap[$color] ?? 'Natural lighting, ' . $mood . ' atmosphere';

        // 融合 feicai 光质选择
        if (strpos($mood, 'tense') !== false || strpos($mood, 'furious') !== false) {
            $base .= ', hard light creating sharp contrast';
        } elseif (strpos($mood, 'warm') !== false || strpos($mood, 'hopeful') !== false) {
            $base .= ', soft diffused light, gentle shadows';
        } elseif (strpos($mood, 'lonely') !== false || strpos($mood, 'melancholic') !== false) {
            $base .= ', overcast diffused light, flat and muted';
        }

        return $base;
    }

    /**
     * 情绪 → 英文氛围词映射 (EmotionMap 联动)
     */
    private function map_emotion_en(string $emotion): string
    {
        $map = [
            '振奋' => 'uplifting', '期待' => 'anticipatory', '专注' => 'focused',
            '温情' => 'warm', '希望' => 'hopeful', '决心' => 'determined',
            '宁静' => 'serene', '愉悦' => 'joyful', '紧张' => 'tense',
            '焦虑' => 'anxious', '悲伤' => 'melancholic', '愤怒' => 'furious',
            '恐惧' => 'fearful', '困惑' => 'puzzled', '释然' => 'relieved',
            '自豪' => 'proud', '孤独' => 'lonely', '怀念' => 'nostalgic',
            '惊讶' => 'surprised', '尴尬' => 'awkward', '不习惯' => 'unsettled',
            '接纳' => 'accepting', '勇敢' => 'courageous', '平和' => 'peaceful',
            'neutral' => 'neutral',
        ];
        return $map[$emotion] ?? 'dramatic';
    }

    /**
     * 平台参数适配 (v8.3.0 M6 会增强)
     */
    private function platform_params(string $platform, array $skeleton): string
    {
        $ar = $skeleton['ar_default'] ?? '2:3';
        $s = $skeleton['s_default'] ?? 750;
        $neg = $skeleton['negative_default'] ?? 'text';

        // v19.55-fix: match() is PHP 8.0+, plugin requires PHP 7.4 — convert to switch.
        switch ($platform) {
            case 'sdxl':
                return " (high quality, masterpiece, best quality) Negative: {$neg}";
            case 'flux':
                return '';  // Flux 用自然语言, 无参数
            case 'dalle':
                return '';  // DALL-E 用自然语言
            default:
                return " --ar {$ar} --s {$s} --style raw --no {$neg}";  // Midjourney
        }
    }

    // ================================================================
    // v9.1.0-task3 — Seed 冲突优先级规则
    // ================================================================

    /**
     * 加载 seed_refs → 统一结构 [{seed_id, category, seed}]
     *
     * 向后兼容: CPT 查不到时回落到空骨架 (与 v9.0.0 行为一致)
     *
     * @param array $seed_refs [CHAR_001, SCENE_003, ...]
     * @return array [{seed_id, category, seed}, ...]
     */
    private function load_seed_refs(array $seed_refs): array
    {
        $out = [];
        foreach ($seed_refs as $ref) {
            $seed = GenesisSeedCPT::get_by_seed_id($ref);
            if (!$seed) {
                // 向后兼容旧 option 存储缺失场景: 给空骨架保持下游不崩
                $seed = ['visual_dna' => [], 'lock' => [], 'priority' => [], 'seed_category' => ''];
            }
            $out[] = [
                'seed_id'  => $ref,
                'category' => $seed['seed_category'] ?? '',
                'seed'     => $seed,
            ];
        }
        return $out;
    }

    /**
     * v9.1.0-task3: Seed 冲突优先级解析
     *
     * 评分模型: effective_score = chain_position_score + lock_level_score
     *   - chain_position_score: 字段链中位置 (链头=100, 每降一位 -10, 链外=10)
     *   - lock_level_score: critical=9 / important=3 / flexible=0
     *   - 步长 10 (chain) > 最大加分 9 (lock) => 跨分类时字段链主导,
     *     同链位置 (同分类) 时 Lock 突破 (规则2)
     *
     * 单元测试 (注释形式, 不实际执行):
     *
     * Test 1: CharacterSeed.color=红(flexible) + SceneSeed.color=蓝(flexible)
     *   - char chain_score=100, scene chain_score=90, lock_score 同为 0
     *   - effective: 100 vs 90 → 预期 resolved.color='红'
     *   - 预期 conflicts[0].winner_seed = CharacterSeed ID, conflict_count=2
     *
     * Test 2: SoulSeed.signature=宫崎骏(flexible) + StyleSeed.signature=水彩(flexible)
     *   - soul chain_score=100, style chain_score=90
     *   - 预期 resolved.signature='宫崎骏' (SoulSeed 优先)
     *
     * Test 3: 同分类两 Seed, A.color=红(important) + B.color=蓝(critical/Lock)
     *   - 同 chain_score (同 char) → 比较 lock_score: 9 vs 3
     *   - 预期 resolved.color='蓝' (Lock 项 > Priority 项, 规则2)
     *
     * Test 4: BrandSeed.culture=东方(flexible) + SceneSeed.culture=赛博(flexible)
     *   - brand chain_score=100, scene chain_score=90 → resolved.culture='东方'
     *
     * Test 5: 仅 SceneSeed.mood=孤寂, CharacterSeed 无 mood → resolved.mood='孤寂';
     *         若 SceneSeed 也无 mood → resolve_seed_conflicts 返回 null, build_meta 回落 EmotionMap
     *
     * @param array $seeds load_seed_refs() 返回的结构
     * @return array {
     *   resolved:  array {color, signature, mood, culture}  各字段胜出值 (无候选则 null)
     *   conflicts: array [{field, winner_seed, winner_value, winner_lock_level,
     *                      overridden_by: [{seed_id, value, lock_level}],
     *                      conflict_count}]
     * }
     */
    private function resolve_seed_conflicts(array $seeds): array
    {
        $resolved  = ['color' => null, 'signature' => null, 'mood' => null, 'culture' => null];
        $conflicts = [];

        foreach (self::FIELD_PRIORITY_CHAINS as $field => $chain) {
            // 1) 收集所有为该字段提供值的 Seed 候选
            $candidates = [];
            foreach ($seeds as $entry) {
                $value = $this->extract_field_from_seed($entry['seed'], $field);
                if ($value === null || $value === '') continue;
                $lock_level = $this->detect_lock_level($entry['seed'], $field);
                $candidates[] = [
                    'seed_id'      => $entry['seed_id'],
                    'category'     => $entry['category'],
                    'value'        => $value,
                    'lock_level'   => $lock_level,
                    'chain_score'  => $this->chain_position_score($entry['category'], $chain),
                    'lock_score'   => $this->lock_level_score($lock_level),
                ];
            }
            if (empty($candidates)) continue;

            // 2) 按 effective_score = chain_score + lock_score 降序, 选胜者
            usort($candidates, function ($a, $b) {
                $sa = $a['chain_score'] + $a['lock_score'];
                $sb = $b['chain_score'] + $b['lock_score'];
                if ($sa === $sb) return strcmp($b['seed_id'], $a['seed_id']); // 稳定兜底
                return $sb <=> $sa;
            });

            $winner = $candidates[0];
            $resolved[$field] = $winner['value'];

            // 3) 冲突检测: 候选 ≥ 2 即记录 (供前端冲突警告 + META 层 seed_conflicts 字段)
            if (count($candidates) >= 2) {
                $losers = [];
                for ($i = 1, $n = count($candidates); $i < $n; $i++) {
                    $losers[] = [
                        'seed_id'    => $candidates[$i]['seed_id'],
                        'value'      => $candidates[$i]['value'],
                        'lock_level' => $candidates[$i]['lock_level'],
                    ];
                }
                $conflicts[] = [
                    'field'            => $field,
                    'winner_seed'      => $winner['seed_id'],
                    'winner_value'     => $winner['value'],
                    'winner_lock_level'=> $winner['lock_level'],
                    // overridden_by: 历史命名保留 — 实际语义是"被 winner 覆盖的候选"
                    'overridden_by'    => $losers,
                    'conflict_count'   => count($candidates),
                ];
            }
        }

        return [
            'resolved'  => $resolved,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * 公开 API: 检测 seed_refs 间的字段冲突 (供前端冲突警告面板调用)
     *
     * @param array $seed_refs [CHAR_001, SCENE_003, STYLE_治愈, ...]
     * @return array [{field, conflict_count, winner_seed, winner_value, losers:[{seed_id,value,lock_level}]}, ...]
     *                无冲突时返回空数组
     */
    public function detect_conflicts(array $seed_refs): array
    {
        $seeds      = $this->load_seed_refs($seed_refs);
        $resolution = $this->resolve_seed_conflicts($seeds);

        $out = [];
        foreach ($resolution['conflicts'] as $c) {
            $out[] = [
                'field'         => $c['field'],
                'conflict_count'=> $c['conflict_count'],
                'winner_seed'   => $c['winner_seed'],
                'winner_value'  => $c['winner_value'],
                'losers'        => array_map(function ($l) {
                    return [
                        'seed_id'    => $l['seed_id'],
                        'value'      => $l['value'],
                        'lock_level' => $l['lock_level'],
                    ];
                }, $c['overridden_by']),
            ];
        }
        return $out;
    }

    /**
     * 从单个 Seed 提取目标字段的值
     *
     * 兼容 visual_dna 子结构多种命名 (color/costume_color/palette/...);
     * 也允许字段挂在 Seed 顶层 (与 v9.0.0 旧数据兼容)。
     *
     * @param array  $seed  Seed_CPT::get_by_seed_id() 返回的完整 Seed
     * @param string $field color|signature|mood|culture
     * @return string|null  非空字符串值; 无则 null
     */
    private function extract_field_from_seed(array $seed, string $field): ?string
    {
        $vd = $seed['visual_dna'] ?? [];
        if (!is_array($vd)) $vd = [];

        // v19.55-fix: match() is PHP 8.0+, plugin requires PHP 7.4 — convert to switch.
        switch ($field) {
            case 'color':
                $keys = ['color', 'costume_color', 'palette', 'atmosphere_color', 'dominant_color', 'tone'];
                break;
            case 'signature':
                $keys = ['signature', 'style', 'artist_signature', 'soul_style'];
                break;
            case 'mood':
                $keys = ['mood', 'atmosphere', 'emotion', 'vibe', 'emotional_tone'];
                break;
            case 'culture':
                $keys = ['culture', 'cultural_element', 'cultural_style', 'heritage'];
                break;
            default:
                $keys = [$field];
                break;
        }

        // 先查 visual_dna, 再查 Seed 顶层
        foreach ($keys as $k) {
            if (isset($vd[$k]) && is_string($vd[$k]) && $vd[$k] !== '') return $vd[$k];
        }
        foreach ($keys as $k) {
            if (isset($seed[$k]) && is_string($seed[$k]) && $seed[$k] !== '') return $seed[$k];
        }
        return null;
    }

    /**
     * 检测字段在 Seed 中的锁定级别 (规则2)
     *
     * 判定顺序 (取最高级):
     *   1. 字段名出现在 priority.critical → 'critical'
     *   2. 字段名出现在 lock 数组       → 'critical' (lock 与 critical 等价)
     *   3. 字段名出现在 priority.important → 'important'
     *   4. 字段名出现在 priority.flexible  → 'flexible'
     *   5. 未声明                       → 'flexible' (默认)
     *
     * @return string critical|important|flexible
     */
    private function detect_lock_level(array $seed, string $field): string
    {
        $priority = $seed['priority'] ?? [];
        if (!is_array($priority)) $priority = [];
        $lock = $seed['lock'] ?? [];
        if (!is_array($lock)) $lock = [];

        $critical = $priority['critical'] ?? [];
        $important = $priority['important'] ?? [];
        $flexible = $priority['flexible'] ?? [];
        if (!is_array($critical))  $critical = [];
        if (!is_array($important)) $important = [];
        if (!is_array($flexible))  $flexible = [];

        if (in_array($field, $critical, true) || in_array($field, $lock, true)) {
            return 'critical';
        }
        if (in_array($field, $important, true)) {
            return 'important';
        }
        if (in_array($field, $flexible, true)) {
            return 'flexible';
        }
        return 'flexible';
    }

    /**
     * 计算分类在字段专属链中的位置分
     *
     * @param string $category char|brand|scene|prop|style|soul
     * @param array  $chain    FIELD_PRIORITY_CHAINS[field]
     * @return int 链头 100, 每降一位 -10, 链外 10
     */
    private function chain_position_score(string $category, array $chain): int
    {
        $idx = array_search($category, $chain, true);
        if ($idx === false) {
            return self::CHAIN_OUT_OF_CHAIN_SCORE;
        }
        return self::CHAIN_HEAD_SCORE - ($idx * 10);
    }

    /**
     * 锁定级别 → 加分 (规则2 tiebreaker)
     */
    private function lock_level_score(string $level): int
    {
        return self::LOCK_LEVEL_SCORE[$level] ?? 0;
    }
}
