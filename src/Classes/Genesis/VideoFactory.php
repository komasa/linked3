<?php

declare(strict_types=1);
/**
 * Linked3 Video Factory — 视频脚本生产工厂 (Trait版)
 *
 * v10.4.2 (方案A) 新增: 视频工厂, 使用Trait代替继承
 *
 * 设计原理 (公理J: Trait代替继承):
 *   - 本类是独立类, 无extends, 加载顺序安全
 *   - use ScriptFactoryTrait 获得共享能力
 *   - 委托 Motion_Prompt_Engine 生成首尾帧+Motion (公理E: DRY)
 *
 * 生产管线 (5阶段):
 *   Stage 0: load_seed_dna()  — 加载SEED (角色/场景/道具/色板/品牌/风格)
 *   Stage 1: compile()         — 编译IR: Beat序列 × SEED × 风格 × 情绪弧线
 *   Stage 2: project()         — 投影为视频脚本 (首尾帧+Motion+转场, 委托Motion引擎)
 *   Stage 3: quality_check()   — PQS质检: 首尾帧连贯/Motion存在/时长合理/情绪弧线完整
 *   Stage 4: platform_adapt()  — 适配视频平台 (抖音/B站/YouTube)
 *
 * 视频脚本结构:
 *   每个Beat → 一个VideoGroup:
 *     - group_id: VG001, VG002, ...
 *     - beat_text: 节拍文本 (≤100字)
 *     - arc_position: opening/rising/peak/falling/resolution
 *     - emotion: neutral/happy/sad/tense/epic/...
 *     - first_frame: 首帧Prompt (含风格关键词+SEED引用)
 *     - last_frame: 尾帧Prompt
 *     - motion_prompt: 运动描述 (委托Motion_Prompt_Engine)
 *     - transition: 转场 (cut/fade/dissolve/zoom)
 *
 * @package Linked3\Genesis
 * @since 10.4.2
 * @version 10.4.2
 */

namespace Linked3\Classes\Genesis;
    use ScriptFactoryTrait;



if (!defined('ABSPATH')) exit;

// v10.4.6 P0修复: 确保Trait已加载 (Dependency_Loader按字母序加载, 本文件在Trait之前)
if (!trait_exists('ScriptFactoryTrait')) {
    require_once __DIR__ . '/trait-linked3-script-factory.php';
}

class VideoFactory {
    /** @var array 情绪弧线模板 */
    private $arc_templates = [
        'classic' => ['opening', 'rising', 'peak', 'falling', 'resolution'],
        'tension' => ['opening', 'rising', 'rising', 'peak', 'falling'],
        'calm'    => ['opening', 'opening', 'middle', 'middle', 'resolution'],
    ];

    /** @var int 默认Beat数 */
    private $default_beat_count = 5;

    public function __construct() {
        $this->script_type = 'video';
        $this->platform = 'video_generic';
    }

    /**
     * Stage 1: 编译中间表示 — Beat序列 × SEED × 风格 × 情绪弧线
     */
    protected function compile(array $context): array {
        $topic = $context['topic'] ?? '';
        $style = $context['style'] ?? 'cinematic_still';
        $platform = $context['platform'] ?? 'video_generic';
        $beat_count = $context['beat_count'] ?? $this->default_beat_count;
        $arc_type = $context['arc_type'] ?? 'classic';

        $arc = $this->arc_templates[$arc_type] ?? $this->arc_templates['classic'];
        $beats = $this->generate_beats($topic, $beat_count, $arc);

        return [
            'topic' => $topic,
            'style' => $style,
            'platform' => $platform,
            'beats' => $beats,
            'arc_template' => $arc,
            'style_keywords' => $this->style_config['keywords'] ?? [],
            'seed_refs' => $context['seed_refs'] ?? [],
        ];
    }

    /**
     * Stage 2: 投影为视频脚本 — 首尾帧+Motion+转场
     * 委托 Motion_Prompt_Engine::generate_video_group() (公理E: DRY)
     */
    protected function project(array $ir): array {
        $groups = [];
        $beats = $ir['beats'];
        $style_keywords = implode(' ', array_slice($ir['style_keywords'], 0, 5));

        foreach ($beats as $i => $beat) {
            $group = $this->generate_video_group($beat, $style_keywords, $i);
            $groups[] = $group;
        }

        return [
            'topic' => $ir['topic'],
            'style' => $ir['style'],
            'groups' => $groups,
            'total_groups' => count($groups),
            'estimated_duration' => $this->estimate_duration($groups),
        ];
    }

    /**
     * Stage 3: PQS质检 — 视频专项
     */
    protected function quality_check(array $output, array $ir): array {
        $checks = [];
        $score = 0;
        $groups = $output['groups'] ?? [];

        // 检查1: 视频组数≥3
        $group_count = count($groups);
        $checks['group_count'] = [
            'name' => '视频组数≥3',
            'passed' => $group_count >= 3,
            'value' => $group_count,
        ];
        if ($group_count >= 3) $score += 25;

        // 检查2: Motion Prompt存在
        $motion_present = 0;
        foreach ($groups as $g) {
            if (!empty($g['motion_prompt'])) $motion_present++;
        }
        $checks['motion_present'] = [
            'name' => 'Motion Prompt存在',
            'passed' => $motion_present === $group_count,
            'value' => $motion_present . '/' . $group_count,
        ];
        if ($motion_present === $group_count) $score += 25;

        // 检查3: 首尾帧连贯
        $coherent = 0;
        foreach ($groups as $g) {
            if ($this->frames_coherent($g['first_frame'] ?? '', $g['last_frame'] ?? '')) $coherent++;
        }
        $checks['frames_coherent'] = [
            'name' => '首尾帧连贯',
            'passed' => $coherent >= $group_count * 0.6,
            'value' => $coherent . '/' . $group_count,
        ];
        if ($coherent >= $group_count * 0.6) $score += 20;

        // 检查4: 时长合理 (15-300秒)
        $duration = $output['estimated_duration'] ?? 0;
        $checks['duration_reasonable'] = [
            'name' => '时长合理(15-300秒)',
            'passed' => $duration >= 15 && $duration <= 300,
            'value' => $duration . 's',
        ];
        if ($duration >= 15 && $duration <= 300) $score += 15;

        // 检查5: 情绪弧线完整
        $arc_positions = array_column($groups, 'arc_position');
        $arc_complete = in_array('opening', $arc_positions) && in_array('resolution', $arc_positions);
        $checks['arc_complete'] = [
            'name' => '情绪弧线完整',
            'passed' => $arc_complete,
            'value' => $arc_positions,
        ];
        if ($arc_complete) $score += 15;

        return [
            'score' => min($score, 100),
            'checks' => $checks,
            'passed' => $score >= 60,
            'rule_set' => 'video_beat',
        ];
    }

    /**
     * Stage 4: 平台适配
     */
    protected function platform_adapt(array $output, string $platform): array {
        $configs = [
            'douyin' => ['max_duration' => 60, 'aspect_ratio' => '9:16', 'cover_required' => true],
            'bilibili' => ['max_duration' => 600, 'aspect_ratio' => '16:9', 'cover_required' => true],
            'youtube' => ['max_duration' => 900, 'aspect_ratio' => '16:9', 'cover_required' => true],
        ];
        $output['_platform'] = $platform;
        $output['_platform_config'] = $configs[$platform] ?? $configs['douyin'];
        return $output;
    }

    // ================================================================
    // 私有工具方法
    // ================================================================

    private function generate_beats(string $topic, int $count, array $arc): array {
        $beats = [];
        for ($i = 0; $i < $count; $i++) {
            $arc_pos = $arc[$i] ?? 'middle';
            $beats[] = [
                'id' => 'VG' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
                'text' => $topic . ' - ' . $arc_pos . ' beat',
                'emotion' => $this->emotion_for_arc($arc_pos),
                'arc_position' => $arc_pos,
            ];
        }
        return $beats;
    }

    private function emotion_for_arc(string $arc_pos): string {
        $map = [
            'opening' => 'neutral',
            'rising' => 'tense',
            'peak' => 'epic',
            'falling' => 'melancholic',
            'resolution' => 'relieved',
            'middle' => 'neutral',
        ];
        return $map[$arc_pos] ?? 'neutral';
    }

    private function generate_video_group(array $beat, string $style_keywords, int $index): array {
        // 委托 Motion_Prompt_Engine (公理E: DRY)
        $motion = '';
        if (class_exists('\Linked3\Classes\Genesis\MotionPromptEngine')) {
            try {
                if (method_exists('\Linked3\Classes\Genesis\MotionPromptEngine', 'generate_video_group')) {
                    $engine_group = \MotionPromptEngine::generate_video_group($beat, [
                        'style_keywords' => $style_keywords,
                    ]);
                    return [
                        'group_id' => $engine_group['group_id'] ?: $beat['id'],
                        'beat_text' => $engine_group['beat_text'] ?: mb_substr($beat['text'], 0, 100),
                        'arc_position' => $engine_group['arc_position'] ?: $beat['arc_position'],
                        'emotion' => $engine_group['emotion'] ?: $beat['emotion'],
                        'first_frame' => $engine_group['first_frame'] ?: ($beat['text'] . ' ' . $style_keywords),
                        'last_frame' => $engine_group['last_frame'] ?: ($beat['text'] . ' ' . $style_keywords),
                        'motion_prompt' => $engine_group['motion_prompt'] ?: '',
                        'transition' => $engine_group['transition'] ?: 'cut',
                    ];
                }
                // 降级: 仅生成Motion Prompt
                if (method_exists('\Linked3\Classes\Genesis\MotionPromptEngine', 'derive_from_emotion')) {
                    $params = \MotionPromptEngine::derive_from_emotion($beat['emotion'], $beat['arc_position']);
                    $motion = \MotionPromptEngine::generate($params);
                }
            } catch (\Throwable $e) {
                $motion = '';
            }
        }

        return [
            'group_id' => $beat['id'],
            'beat_text' => mb_substr($beat['text'], 0, 100),
            'arc_position' => $beat['arc_position'],
            'emotion' => $beat['emotion'],
            'first_frame' => $beat['text'] . ' ' . $style_keywords,
            'last_frame' => $beat['text'] . ' ' . $style_keywords,
            'motion_prompt' => $motion,
            'transition' => 'cut',
        ];
    }

    private function frames_coherent(string $frame1, string $frame2): bool {
        if (empty($frame1) || empty($frame2)) return false;
        $words1 = array_unique(preg_split('/[\s,，、]+/u', $frame1));
        $words2 = array_unique(preg_split('/[\s,，、]+/u', $frame2));
        $common = array_intersect($words1, $words2);
        return count($common) >= 1;
    }

    private function estimate_duration(array $groups): int {
        return count($groups) * 5;
    }
}
