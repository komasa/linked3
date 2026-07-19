<?php
/**
 * Linked3 Motion Vocabulary — 结构化镜头运动词汇库 (独立类)
 *
 * v10.4.4 (方案A) 新增: Motion词汇库, 独立类无继承
 *
 * 设计原理 (公理K: 独立类零继承):
 *   - 本类是独立类, 无extends, 加载顺序安全
 *   - 所有方法为static, 无需实例化
 *
 * 词汇库结构 (公理I: 结构化词汇库):
 *   • 镜头运动 (Camera Movements) — 8类
 *   • 主体动作 (Subject Actions) — 6类
 *   • 速度修饰 (Speed Modifiers) — 3级
 *   • 情绪映射 (Emotion Mapping) — 情绪→运动推荐
 *   • 禁忌规则 (Taboo Rules) — 不可组合的运动
 *
 * @package Linked3\Genesis
 * @since 10.4.4
 * @version 10.4.4
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class Linked3_Motion_Vocabulary {

    /**
     * 镜头运动词汇库 (8类)
     */
    public static function get_camera_movements(): array {
        return [
            'dolly_in' => [
                'en' => 'dolly in / push in',
                'cn' => '推近',
                'emotion' => ['focus', 'tension', 'intimacy', 'curiosity'],
                'desc' => '镜头向主体推进, 聚焦、紧张、亲密',
            ],
            'dolly_out' => [
                'en' => 'dolly out / pull back',
                'cn' => '拉远',
                'emotion' => ['reveal', 'isolation', 'loneliness', 'ending'],
                'desc' => '镜头从主体拉远, 揭示、孤立、孤独',
            ],
            'pan_left' => [
                'en' => 'pan left',
                'cn' => '左摇',
                'emotion' => ['discovery', 'search', 'transition'],
                'desc' => '镜头水平向左转动, 发现、搜索',
            ],
            'pan_right' => [
                'en' => 'pan right',
                'cn' => '右摇',
                'emotion' => ['discovery', 'search', 'transition'],
                'desc' => '镜头水平向右转动, 发现、搜索',
            ],
            'tilt_up' => [
                'en' => 'tilt up',
                'cn' => '上仰',
                'emotion' => ['awe', 'grandeur', 'aspiration'],
                'desc' => '镜头垂直向上转动, 敬畏、宏伟',
            ],
            'tilt_down' => [
                'en' => 'tilt down',
                'cn' => '下俯',
                'emotion' => ['contemplation', 'sadness', 'reflection'],
                'desc' => '镜头垂直向下转动, 沉思、悲伤',
            ],
            'orbit_cw' => [
                'en' => 'orbit clockwise',
                'cn' => '顺时针环绕',
                'emotion' => ['showcase', 'hero', 'epic'],
                'desc' => '镜头顺时针环绕主体, 展示、英雄感',
            ],
            'static_wide' => [
                'en' => 'static wide shot',
                'cn' => '固定广角',
                'emotion' => ['establish', 'context', 'calm'],
                'desc' => '固定广角镜头, 建立、平静',
            ],
        ];
    }

    /**
     * 主体动作词汇库 (6类)
     */
    public static function get_subject_actions(): array {
        return [
            'standing_still' => [
                'en' => 'stands still',
                'cn' => '站立不动',
                'emotion' => ['calm', 'contemplation', 'resolve'],
            ],
            'walk_forward' => [
                'en' => 'walks forward',
                'cn' => '向前走',
                'emotion' => ['progress', 'determination', 'journey'],
            ],
            'turn_around' => [
                'en' => 'turns around',
                'cn' => '转身',
                'emotion' => ['surprise', 'realization', 'change'],
            ],
            'look_back' => [
                'en' => 'looks back',
                'cn' => '回头看',
                'emotion' => ['nostalgia', 'reluctance', 'memory'],
            ],
            'reach_out' => [
                'en' => 'reaches out hand',
                'cn' => '伸出手',
                'emotion' => ['connection', 'plea', 'offering'],
            ],
            'head_turn' => [
                'en' => 'turns head',
                'cn' => '转头',
                'emotion' => ['attention', 'curiosity', 'alert'],
            ],
        ];
    }

    /**
     * 速度修饰词汇库 (3级)
     */
    public static function get_speed_modifiers(): array {
        return [
            'slow' => [
                'en' => 'slowly, gently',
                'cn' => '缓慢',
                'emotion' => ['calm', 'tender', 'melancholic'],
            ],
            'normal' => [
                'en' => 'smoothly, naturally',
                'cn' => '正常',
                'emotion' => ['neutral', 'steady'],
            ],
            'fast' => [
                'en' => 'quickly, rapidly',
                'cn' => '快速',
                'emotion' => ['tension', 'urgency', 'excitement'],
            ],
        ];
    }

    /**
     * 按情绪推荐运动组合
     */
    public static function recommend_by_emotion(string $emotion): array {
        $mapping = [
            'tension' => ['camera' => 'dolly_in', 'subject' => 'head_turn', 'speed' => 'fast'],
            'calm' => ['camera' => 'static_wide', 'subject' => 'standing_still', 'speed' => 'slow'],
            'curiosity' => ['camera' => 'dolly_in', 'subject' => 'head_turn', 'speed' => 'normal'],
            'sadness' => ['camera' => 'dolly_out', 'subject' => 'look_back', 'speed' => 'slow'],
            'excitement' => ['camera' => 'orbit_cw', 'subject' => 'walk_forward', 'speed' => 'fast'],
            'awe' => ['camera' => 'tilt_up', 'subject' => 'standing_still', 'speed' => 'slow'],
            'neutral' => ['camera' => 'static_wide', 'subject' => 'standing_still', 'speed' => 'normal'],
        ];
        return $mapping[$emotion] ?? $mapping['neutral'];
    }

    /**
     * 按弧线位置推荐
     */
    public static function recommend_by_arc_position(string $arc_position): array {
        $mapping = [
            'opening' => ['camera' => 'static_wide', 'subject' => 'standing_still', 'speed' => 'normal', 'reason' => '开场建立场景'],
            'rising' => ['camera' => 'pan_right', 'subject' => 'walk_forward', 'speed' => 'normal', 'reason' => '上升期跟随主体'],
            'peak' => ['camera' => 'orbit_cw', 'subject' => 'turn_around', 'speed' => 'slow', 'reason' => '高潮环绕展示'],
            'falling' => ['camera' => 'dolly_out', 'subject' => 'look_back', 'speed' => 'normal', 'reason' => '下降期拉远'],
            'resolution' => ['camera' => 'static_wide', 'subject' => 'standing_still', 'speed' => 'normal', 'reason' => '结局固定全景'],
        ];
        return $mapping[$arc_position] ?? $mapping['opening'];
    }

    /**
     * 构建Motion Prompt
     */
    public static function build_motion_prompt(string $camera_key, string $subject_key, string $speed_key, string $lang = 'en'): array {
        $cameras = self::get_camera_movements();
        $subjects = self::get_subject_actions();
        $speeds = self::get_speed_modifiers();

        $camera = $cameras[$camera_key] ?? $cameras['static_wide'];
        $subject = $subjects[$subject_key] ?? $subjects['standing_still'];
        $speed = $speeds[$speed_key] ?? $speeds['normal'];

        $prompt = '';
        if ($lang === 'en') {
            $prompt = sprintf('%s, subject %s, %s', $camera['en'], $subject['en'], $speed['en']);
        } elseif ($lang === 'cn') {
            $prompt = sprintf('镜头%s, 主体%s, %s', $camera['cn'], $subject['cn'], $speed['cn']);
        } else {
            $prompt = sprintf('%s (%s), subject %s (%s), %s (%s)',
                $camera['en'], $camera['cn'],
                $subject['en'], $subject['cn'],
                $speed['en'], $speed['cn']
            );
        }

        return [
            'prompt' => $prompt,
            'vocabulary_used' => [
                'camera' => $camera_key,
                'subject' => $subject_key,
                'speed' => $speed_key,
            ],
            'emotion_tags' => array_merge($camera['emotion'], $speed['emotion']),
        ];
    }
}
