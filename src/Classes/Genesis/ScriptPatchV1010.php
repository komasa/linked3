<?php

declare(strict_types=1);
/**
 * Linked3 图文/视频脚本 AJAX Handler 补丁 v10.1.0
 *
 * 注册新的AJAX endpoint:
 *   - linked3_video_generate_v10: 视频脚本生成 (首尾帧+Motion Prompt)
 *   - linked3_charts_generate_v10: 图文脚本生成 (4Band结构)
 *
 * @package Linked3\Genesis
 * @version 10.1.0
 * @date 2026-06-23
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class ScriptPatchV1010 {

    public static function register() : void {
        add_action('wp_ajax_linked3_video_generate_v10', [__CLASS__, 'ajax_video_generate']);
        add_action('wp_ajax_linked3_charts_generate_v10', [__CLASS__, 'ajax_charts_generate']);

        if (function_exists('error_log')) {
            error_log('[linked3 v10.1.0] Script patch registered (video+charts)');
        }
    }

    // ================================================================
    // 视频脚本生成 — 首尾帧 + Motion Prompt
    // ================================================================

        public static function ajax_video_generate() : mixed { return ScriptPatchHandlers::ajax_video_generate(); }

    /**
     * 构建帧Prompt (首帧/尾帧) — v11.2.0: 基于feicai4.0 motion-prompt-methodology
     * 首帧=动作起始状态, 尾帧=动作完成状态, 明确差异
     */
    private static function build_frame_prompt(array $fpCore, string $styleKeywords, string $styleNegative, array $seedDna, string $frameType): string {
        $who = $fpCore['who'] ?? 'a figure';
        $where = $fpCore['where'] ?? '';
        $action = $fpCore['action_en'] ?? 'standing still';
        $emotion = $fpCore['emotion'] ?? 'neutral';
        $beatText = $fpCore['beat_text'] ?? '';

        // v11.2.0 #2: 首尾帧明确动作差异 (基于feicai4.0方法论)
        $actionDesc = self::resolveFrameAction($action, $frameType);

        // v11.2.0: 叙事式Prompt (参考feicai4.0 gemini-image-prompt-guide.md)
        $phaseLabel = ($frameType === 'first') ? '起始帧' : '结束帧';

        $prompt = '';
        // 景别+环境
        $prompt .= '中景，';
        $prompt .= $where ? ($where . '。') : '一个室内场景。';

        // 主体描述+动作 (首尾帧差异明确)
        $prompt .= ucfirst($who) . '，' . $actionDesc . '。';

        // v11.2.0 #2: 基于beat_text补充场景叙事
        if (!empty($beatText)) {
            $prompt .= ' 场景叙事：' . mb_substr($beatText, 0, 60) . '。';
        }

        // 情绪氛围 (叙事式, 首尾帧情绪可有变化)
        $prompt .= self::resolveMoodNarrative($emotion, $frameType);

        // SEED visual_dna注入 (叙事式融入)
        if (!empty($seedDna['character'])) {
            $prompt .= ' 角色特征：' . $seedDna['character'] . '。';
        }
        if (!empty($seedDna['scene'])) {
            $prompt .= ' 场景细节：' . $seedDna['scene'] . '。';
        }

        // v11.2.0 #2: 明确帧类型标记 (帮助生图工具理解)
        $prompt .= ' 【' . $phaseLabel . '，与另一帧构成首尾帧对，用于视频生成】';

        // 明确无文字 (视频帧不应有文字)
        $prompt .= ' 画面中不包含任何文字、字母、数字或水印。';

        // 画风关键词
        if ($styleKeywords) {
            $prompt .= ' ' . $styleKeywords;
        }

        // 负面关键词 — 强制包含text相关
        $negativeBase = 'text, watermark, letters, words, signature, logo';
        $prompt .= ' --no ' . $negativeBase . ($styleNegative ? ', ' . $styleNegative : '');

        // 平台参数
        $prompt .= ' --ar 16:9 --s 250 --style raw';

        return $prompt;
    }

    /**
     * 解析帧动作描述 (首尾帧差异化).
     */
    private static function resolveFrameAction(string $action, string $frameType): string
    {
        if ($frameType === 'first') {
            return 'about to ' . $action . ', mid-action pose, tension in muscles';
        }
        $actionMap = [
            'standing' => 'has shifted weight, relaxed posture',
            'sitting' => 'has settled into seat, composed',
            'walking' => 'has arrived at destination, stopped',
            'running' => 'has stopped, catching breath',
            'turning' => 'has completed turn, facing new direction',
            'looking' => 'gaze locked on target, focused',
            'reaching' => 'has grasped the object, holding firmly',
            'jumping' => 'has landed, balanced stance',
            'fighting' => 'has struck, impact moment frozen',
            'speaking' => 'has finished speaking, awaiting response',
        ];
        return $actionMap[$action] ?? ('has completed ' . $action . ', result visible');
    }

    /**
     * 解析情绪氛围叙述 (首尾帧情绪变化).
     */
    private static function resolveMoodNarrative(string $emotion, string $frameType): string
    {
        $moodMap = [
            '振奋' => $frameType === 'first' ? '光线渐亮，力量感蓄势待发。' : '光线明亮，充满力量感，胜利姿态。',
            '紧张' => $frameType === 'first' ? '阴影加深，空气中弥漫着紧张的气息。' : '紧张达到顶点，动态模糊暗示冲突。',
            '悲伤' => $frameType === 'first' ? '冷色调，沉静忧郁，低头姿态。' : '冷色调加深，泪水可见，情绪释放。',
            '温情' => $frameType === 'first' ? '暖光初现，柔和而期待。' : '暖光包裹，微笑绽放，温馨时刻。',
            '希望' => $frameType === 'first' ? '黑暗中微光初现，抬头仰望。' : '一束光照亮主体，黑暗退散，充满希望。',
            '释然' => $frameType === 'first' ? '紧张感渐消，眉头舒展。' : '光线柔和，紧张感完全消散，平静微笑。',
            'neutral' => $frameType === 'first' ? '自然光线，平静的氛围，准备状态。' : '自然光线，平静的氛围，完成状态。',
        ];
        return $moodMap[$emotion] ?? $moodMap['neutral'];
    }

    private static function suggest_transition(string $arcPosition): string {
        $map = [
            '开场' => 'fade in from black',
            '发展' => 'cut to next scene',
            '高潮' => 'quick cut, match on action',
            '收尾' => 'fade out to black',
        ];
        return $map[$arcPosition] ?? 'cut';
    }

    // ================================================================
    // 图文脚本生成 — 4Band结构
    // ================================================================

        public static function ajax_charts_generate() : mixed { return ScriptPatchHandlers::ajax_charts_generate(); }

    private static function suggest_text_overlay(string $band, string $topic): string {
        $map = [
            'Hook' => mb_substr($topic, 0, 12) . '!',
            'Body' => '3个核心要点',
            'Proof' => '数据证明',
            'CTA' => '立即行动',
        ];
        return $map[$band] ?? '';
    }

    // ================================================================
    // 共享工具方法
    // ================================================================

    private static function split_script_to_beats(string $script, int $count, string $mode): array {
        $beats = [];

        if ($mode === 'sentence') {
            $sentences = preg_split('/[。！？\n]/u', $script);
            $sentences = array_filter($sentences, fn($s) => mb_strlen(trim($s)) >= 10);
            $beats = array_map(fn($s) => ['text' => trim($s), 'emotion' => 'neutral', 'arc_position' => '发展'], array_values($sentences));
        } else {
            // auto / fixed: 按段落拆分
            $paragraphs = preg_split('/\n\s*\n/', $script);
            $paragraphs = array_filter($paragraphs, fn($p) => mb_strlen(trim($p)) >= 15);

            if (count($paragraphs) < $count) {
                // 段落不够, 按句拆
                $sentences = preg_split('/[。！？\n]/u', $script);
                $sentences = array_filter($sentences, fn($s) => mb_strlen(trim($s)) >= 10);
                $paragraphs = array_values($sentences);
            }

            $beats = [];
            $total = count($paragraphs);
            if ($mode === 'fixed' && $total > $count) {
                // 均匀采样
                $step = $total / $count;
                for ($i = 0; $i < $count; $i++) {
                    $idx = (int)($i * $step);
                    $text = is_array($paragraphs) ? ($paragraphs[$idx] ?? '') : '';
                    $beats[] = ['text' => trim($text), 'emotion' => 'neutral', 'arc_position' => self::arc_position($i, $count)];
                }
            } else {
                foreach (array_slice($paragraphs, 0, $count) as $i => $p) {
                    $beats[] = ['text' => trim($p), 'emotion' => 'neutral', 'arc_position' => self::arc_position($i, min($count, $total))];
                }
            }
        }

        return $beats;
    }

    private static function arc_position(int $i, int $total): string {
        if ($total <= 1) return '开场';
        if ($i === 0) return '开场';
        if ($i === $total - 1) return '收尾';
        if ($i >= $total * 0.6) return '高潮';
        return '发展';
    }

    private static function load_seed_dna(array $seedRefs): array {
        $dna = ['character' => '', 'scene' => '', 'style' => ''];
        if (empty($seedRefs) || !class_exists('\Linked3\Classes\Genesis\GenesisSeedCPT')) return $dna;

        foreach ($seedRefs as $ref) {
            try {
                $seed = \GenesisSeedCPT::get_by_seed_id($ref);
                if (!$seed) continue;
                $category = $seed['seed_category'] ?? '';
                $desc = self::extractSeedDescription($seed['visual_dna'] ?? []);

                if ($category === 'char' && empty($dna['character'])) {
                    $dna['character'] = trim($desc);
                } elseif ($category === 'scene' && empty($dna['scene'])) {
                    $dna['scene'] = trim($desc);
                } elseif ($category === 'style' && empty($dna['style'])) {
                    $dna['style'] = trim($desc);
                }
            } catch (\Throwable $e) { if (function_exists("linked3_log")) linked3_log("app", "warning", $e->getMessage()); else error_log("Linked3: " . $e->getMessage()); }
        }

        return $dna;
    }

    /**
     * 从 seed visual_dna 提取描述串 (拼接 appearance/clothing/description/atmosphere).
     */
    private static function extractSeedDescription($visualDna): string
    {
        if (is_string($visualDna)) {
            $visualDna = json_decode($visualDna, true) ?: [];
        }
        if (!is_array($visualDna)) {
            return '';
        }
        $desc = '';
        foreach (['appearance', 'clothing', 'description', 'atmosphere'] as $field) {
            if (!empty($visualDna[$field])) {
                $desc .= $visualDna[$field] . ' ';
            }
        }
        return $desc;
    }

    /**
     * v16.0.23 [公理α: H↓ 消除"选什么布局"不确定性]
     * 自动选择布局 — 根据内容关键词和模块数量推断最佳布局
     */
    private static function auto_select_layout(string $topic, int $moduleCount): string {
        $topic_lower = mb_strtolower($topic);

        // 关键词→布局映射规则
        $rules = [
            // 对比类
            '对比|比较|vs|VS|区别|差异|优缺点' => 'binary-comparison',
            '评测|评分|排行榜|榜单|top' => 'comparison-matrix',
            // 流程类
            '步骤|流程|教程|方法|怎么做|如何' => 'linear-progression',
            '路线图|规划|里程碑|计划|阶段' => 'winding-roadmap',
            '循环|闭环|周期|迭代|飞轮' => 'circular-flow',
            '漏斗|转化|转化率|营销' => 'funnel',
            // 结构类
            '分类|类别|体系|框架|结构' => 'tree-branching',
            '层级|层次|金字塔|等级|级别' => 'hierarchical-layers',
            '拆解|分解|剖析|组成|构成' => 'structural-breakdown',
            '核心|中心|围绕|辐射' => 'hub-spoke',
            // 关系类
            '交集|重叠|共同|关系|关联' => 'venn-diagram',
            '拼图|组合|整合|拼装' => 'jigsaw',
            '桥梁|连接|问题.*方案|因果' => 'bridge',
            // 模型类
            '冰山|表象|本质|隐藏|深层' => 'iceberg',
            '故事|叙事|剧情|弧线' => 'story-mountain',
            '数据|指标|KPI|看板|仪表' => 'dashboard',
            '元素|周期|分类网格' => 'periodic-table',
            '连环|序列|分镜|漫画' => 'comic-strip',
        ];

        foreach ($rules as $pattern => $layout) {
            if (preg_match('/' . $pattern . '/u', $topic_lower)) {
                return $layout;
            }
        }

        // 兜底: 模块数多→便当格, 少→线性递进
        if ($moduleCount >= 6) return 'bento-grid';
        if ($moduleCount <= 3) return 'linear-progression';
        return 'bento-grid';
    }

    /**
     * v1.4: 构建平台特定的Prompt后缀
     * 不同生图平台使用不同的参数语法, 确保生成的Prompt可直接粘贴使用
     *
     * @param string $platform 平台 (midjourney/stable_diffusion/dalle3/flux)
     * @param string $aspectRatio 画幅比例 (1:1/3:4/4:3/16:9/9:16)
     * @return string 平台特定的Prompt后缀
     */
    private static function build_platform_suffix(string $platform, string $aspectRatio): string
    {
        switch ($platform) {
            case 'midjourney':
                // Midjourney: --ar --s --style raw
                return '--ar ' . $aspectRatio . ' --s 250 --style raw';
            case 'stable_diffusion':
                // Stable Diffusion: 无--ar语法, 用自然语言描述比例
                $ratioDesc = [
                    '1:1' => 'square composition',
                    '3:4' => 'portrait vertical composition',
                    '4:3' => 'landscape horizontal composition',
                    '16:9' => 'widescreen cinematic composition',
                    '9:16' => 'vertical mobile composition',
                ];
                return '(' . ($ratioDesc[$aspectRatio] ?? 'portrait composition') . '), high quality, detailed, best quality';
            case 'dalle3':
                // DALL·E 3: 自然语言描述比例
                $ratioDesc = [
                    '1:1' => 'square format',
                    '3:4' => 'vertical portrait format',
                    '4:3' => 'horizontal landscape format',
                    '16:9' => 'widescreen format',
                    '9:16' => 'vertical tall format',
                ];
                return 'Aspect ratio: ' . ($ratioDesc[$aspectRatio] ?? 'vertical portrait format') . ', high quality, detailed';
            case 'flux':
                // Flux: 自然语言描述比例
                $ratioDesc = [
                    '1:1' => '1:1 square',
                    '3:4' => '3:4 portrait',
                    '4:3' => '4:3 landscape',
                    '16:9' => '16:9 widescreen',
                    '9:16' => '9:16 vertical',
                ];
                return 'aspect ratio ' . ($ratioDesc[$aspectRatio] ?? '3:4 portrait') . ', high quality, detailed';
            default:
                return '--ar ' . $aspectRatio . ' --s 250 --style raw';
        }
    }

    /**
     * v1.3: 自动选择模块数量 — 基于内容长度和复杂度
     * 修复旧版BUG: intval('auto')=0被钳为1, 实际只生成1个模块
     *
     * 规则:
     *   - 内容<50字(短主题): 4个 (精简: Hook+Body+Proof+CTA)
     *   - 内容50-200字(中等): 6个 (扩展: Hook+Body×2+Proof×2+CTA)
     *   - 内容>200字(长文): 8个 (学会写作2.0同款: Hook+Body×3+Proof×3+CTA)
     *   - 含"体系/全景/多模块"关键词: 强制8个
     *   - 含"精简/简明/核心"关键词: 强制4个
     */
    private static function auto_select_module_count(string $topic): int
    {
        $len = mb_strlen($topic);
        $topic_lower = mb_strtolower($topic);

        // v16.3.0: 阈值调整 — 每镜含完整4Band(信息密度高), 镜数应比旧拆分式少
        // 旧拆分式: 8模块=8张独立图(每张仅1Band); 新整体式: 8镜=8张完整4Band图(信息量×4)
        // 关键词覆盖
        if (preg_match('/体系|全景|多模块|完整|全面|大全/i', $topic_lower)) return 4; // 原8, v16.3.0降为4
        if (preg_match('/精简|简明|核心|要点|速览/i', $topic_lower)) return 1; // 原4, v16.3.0降为1

        // 按长度分级 (v16.3.0: 每镜信息密度×4, 阈值相应下调)
        if ($len < 50) return 1;   // 原4, 短主题1镜足够
        if ($len <= 200) return 2; // 原6, 中等长度2镜
        return 3;                  // 原8, 长内容3镜
    }

    /**
     * v1.3: 自动选择信息图技法 — 基于内容特征
     * 当用户选"自动适配"时, AI根据内容推断最佳宝玉17技法
     *
     * 规则:
     *   - 技术/代码/架构类 → technical-schematic (技术蓝图)
     *   - 商务/企业/管理类 → corporate-memphis (企业扁平)
     *   - 数据/指标/KPI类 → dashboard (仪表盘风)
     *   - 产品/展示/陈列类 → knolling (整齐排列)
     *   - 兜底 → xuehui-infographic (学会写作2.0信息图, 商业生产级默认)
     */
    private static function auto_select_visual_style(string $topic): string
    {
        $topic_lower = mb_strtolower($topic);

        if (preg_match('/技术|代码|架构|系统|工程|开发|程序|算法/i', $topic_lower)) return 'technical-schematic';
        if (preg_match('/商务|企业|管理|公司|职场|组织|团队/i', $topic_lower)) return 'corporate-memphis';
        if (preg_match('/数据|指标|kpi|看板|仪表|统计|分析|报表/i', $topic_lower)) return 'dashboard';
        if (preg_match('/产品|展示|陈列|商品|物品|装备|工具/i', $topic_lower)) return 'knolling';

        return 'xuehui-infographic';
    }
}

// G5.1: Script Patch auto-registration disabled
add_action('init', ['\Linked3\Classes\Genesis\ScriptPatchV1010', 'register'], 5);
