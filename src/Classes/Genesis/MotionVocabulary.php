<?php

declare(strict_types=1);
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

class MotionVocabulary {

    /**
     * 镜头运动词汇库 (8类)
     */
    public static function get_camera_movements(): array {
        return [
            'dolly_in' => [
                'en' => 'dolly in / push in',
                'cn' => __('推近', 'linked3'),
                'emotion' => ['focus', 'tension', 'intimacy', 'curiosity'],
                'desc' => __('镜头向主体推进, 聚焦、紧张、亲密', 'linked3'),
            ],
            'dolly_out' => [
                'en' => 'dolly out / pull back',
                'cn' => __('拉远', 'linked3'),
                'emotion' => ['reveal', 'isolation', 'loneliness', 'ending'],
                'desc' => __('镜头从主体拉远, 揭示、孤立、孤独', 'linked3'),
            ],
            'pan_left' => [
                'en' => 'pan left',
                'cn' => __('左摇', 'linked3'),
                'emotion' => ['discovery', 'search', 'transition'],
                'desc' => __('镜头水平向左转动, 发现、搜索', 'linked3'),
            ],
            'pan_right' => [
                'en' => 'pan right',
                'cn' => __('右摇', 'linked3'),
                'emotion' => ['discovery', 'search', 'transition'],
                'desc' => __('镜头水平向右转动, 发现、搜索', 'linked3'),
            ],
            'tilt_up' => [
                'en' => 'tilt up',
                'cn' => __('上仰', 'linked3'),
                'emotion' => ['awe', 'grandeur', 'aspiration'],
                'desc' => __('镜头垂直向上转动, 敬畏、宏伟', 'linked3'),
            ],
            'tilt_down' => [
                'en' => 'tilt down',
                'cn' => __('下俯', 'linked3'),
                'emotion' => ['contemplation', 'sadness', 'reflection'],
                'desc' => __('镜头垂直向下转动, 沉思、悲伤', 'linked3'),
            ],
            'orbit_cw' => [
                'en' => 'orbit clockwise',
                'cn' => __('顺时针环绕', 'linked3'),
                'emotion' => ['showcase', 'hero', 'epic'],
                'desc' => __('镜头顺时针环绕主体, 展示、英雄感', 'linked3'),
            ],
            'static_wide' => [
                'en' => 'static wide shot',
                'cn' => __('固定广角', 'linked3'),
                'emotion' => ['establish', 'context', 'calm'],
                'desc' => __('固定广角镜头, 建立、平静', 'linked3'),
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
                'cn' => __('站立不动', 'linked3'),
                'emotion' => ['calm', 'contemplation', 'resolve'],
            ],
            'walk_forward' => [
                'en' => 'walks forward',
                'cn' => __('向前走', 'linked3'),
                'emotion' => ['progress', 'determination', 'journey'],
            ],
            'turn_around' => [
                'en' => 'turns around',
                'cn' => __('转身', 'linked3'),
                'emotion' => ['surprise', 'realization', 'change'],
            ],
            'look_back' => [
                'en' => 'looks back',
                'cn' => __('回头看', 'linked3'),
                'emotion' => ['nostalgia', 'reluctance', 'memory'],
            ],
            'reach_out' => [
                'en' => 'reaches out hand',
                'cn' => __('伸出手', 'linked3'),
                'emotion' => ['connection', 'plea', 'offering'],
            ],
            'head_turn' => [
                'en' => 'turns head',
                'cn' => __('转头', 'linked3'),
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
                'cn' => __('缓慢', 'linked3'),
                'emotion' => ['calm', 'tender', 'melancholic'],
            ],
            'normal' => [
                'en' => 'smoothly, naturally',
                'cn' => __('正常', 'linked3'),
                'emotion' => ['neutral', 'steady'],
            ],
            'fast' => [
                'en' => 'quickly, rapidly',
                'cn' => __('快速', 'linked3'),
                'emotion' => ['tension', 'urgency', 'excitement'],
            ],
        ];
    }

}
