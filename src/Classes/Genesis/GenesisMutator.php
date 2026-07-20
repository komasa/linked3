<?php

declare(strict_types=1);
/**
 * GenesisMutator — extracted from GenesisSeedLibrary.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Genesis
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisMutator {
    private array $operators;

    const DEFAULT_VARIANT_COUNT = 3;

    private static $strategies = [
        ['shot' => 'wide', 'angle' => 'high_angle', 'light' => 'back_light', 'mood' => 'epic', 'composition' => 'diagonal'],
        ['shot' => 'medium', 'angle' => 'eye_level', 'light' => 'side_light', 'mood' => 'tense', 'composition' => 'rule_of_thirds'],
        ['shot' => 'extreme_close_up', 'angle' => 'eye_level', 'light' => 'rim_light_warm', 'mood' => 'rage', 'composition' => 'center'],
        ['shot' => 'medium_wide', 'angle' => 'dutch_angle', 'light' => 'hard_shadow', 'mood' => 'horror', 'composition' => 'frame_within_frame'],
        ['shot' => 'close_up', 'angle' => 'low_angle', 'light' => 'volumetric', 'mood' => 'hopeful', 'composition' => 'golden_ratio'],
        ['shot' => 'medium', 'angle' => 'eye_level', 'light' => 'soft_diffused', 'mood' => 'melancholy', 'composition' => 'symmetric', 'particle' => 'fog_wisps'],
        ['shot' => 'wide', 'angle' => 'bird_eye', 'light' => 'natural', 'mood' => 'serene', 'composition' => 'leading_lines'],
        ['shot' => 'close_up', 'angle' => 'eye_level', 'light' => 'neon_glow', 'mood' => 'mysterious', 'composition' => 'rule_of_thirds'],
    ];

    public function __construct() {
        $this->operators = GenesisSeedLibrary::instance()->getOperators();
    }

    public function generateVariants(array $baseAtom, ?int $count = null): array {
        if ($count === null) {
            $count = intval(get_option(LINKED3_OPTION_PREFIX . 'v7_variant_count', self::DEFAULT_VARIANT_COUNT));
        }
        $count = max(1, min(8, $count)); // 限制1-8, 避免过度调用

        $variants = [];
        $stratCount = count(self::$strategies);

        for ($i = 0; $i < $count; $i++) {
            $variant = $baseAtom;
            $variant['id'] = ($baseAtom['id'] ?? 'atom') . '_v' . ($i + 1);

            $strategy = self::$strategies[$i % $stratCount];
            $variant['variant_ops'] = array_merge($baseAtom['variant_ops'] ?? [], $strategy);
            $variant['diff'] = $strategy;

            $variants[] = $variant;
        }

        if (class_exists('\Linked3\Classes\Genesis\GenesisLogger')) {
            GenesisLogger::stage('v7_mutate', '生成 ' . $count . ' 个变异体', [
                'atom_id' => $baseAtom['id'] ?? '',
                'strategies_used' => array_slice(array_keys(self::$strategies), 0, $count),
            ]);
        }

        return $variants;
    }

    public function applyTextDirective(array $atom, string $text): array {
        $newAtom = $atom;
        $newAtom['id'] = ($atom['id'] ?? 'atom') . '_text_' . time();

        $emotionMap = [
            '绝望' => 'desperate', '崩溃' => 'desperate',
            '愤怒' => 'rage', '怒' => 'rage', '暴怒' => 'rage',
            '悲伤' => 'melancholy', '哭' => 'melancholy', '哀' => 'melancholy',
            '希望' => 'hopeful', '期待' => 'hopeful',
            '平静' => 'calm', '宁静' => 'serene',
            '神秘' => 'mysterious', '诡异' => 'mysterious',
            '恐怖' => 'horror', '惊悚' => 'horror',
            '史诗' => 'epic', '壮阔' => 'epic',
        ];
        foreach ($emotionMap as $kw => $mood) {
            if (mb_strpos($text, $kw) !== false) {
                $newAtom['variant_ops']['mood'] = $mood;
                $charRef = $newAtom['seeds']['character'] ?? 'C1:calm';
                $charId = explode(':', $charRef)[0];
                $stateMap = ['desperate' => 'exhausted', 'rage' => 'angry', 'melancholy' => 'sad'];
                $state = $stateMap[$mood] ?? 'calm';
                $newAtom['seeds']['character'] = $charId . ':' . $state;
                break; // 只取第一个匹配的情绪
            }
        }

        if (preg_match('/暗|黑暗|阴暗/', $text)) {
            $newAtom['variant_ops']['light'] = 'hard_shadow';
        } elseif (preg_match('/霓虹|赛博/', $text)) {
            $newAtom['variant_ops']['light'] = 'neon_glow';
        } elseif (preg_match('/柔光|柔和/', $text)) {
            $newAtom['variant_ops']['light'] = 'soft_diffused';
        } elseif (preg_match('/体积光|耶稣光/', $text)) {
            $newAtom['variant_ops']['light'] = 'volumetric';
        }

        if (preg_match('/远景|全景/', $text)) {
            $newAtom['variant_ops']['shot'] = 'wide';
        } elseif (preg_match('/特写|近景/', $text)) {
            $newAtom['variant_ops']['shot'] = 'close_up';
        } elseif (preg_match('/中景/', $text)) {
            $newAtom['variant_ops']['shot'] = 'medium';
        } elseif (preg_match('/鸟瞰|俯瞰/', $text)) {
            $newAtom['variant_ops']['shot'] = 'bird_eye';
        }

        if (preg_match('/仰视|仰角/', $text)) {
            $newAtom['variant_ops']['angle'] = 'low_angle';
        } elseif (preg_match('/俯视|俯角/', $text)) {
            $newAtom['variant_ops']['angle'] = 'high_angle';
        } elseif (preg_match('/荷兰角|倾斜/', $text)) {
            $newAtom['variant_ops']['angle'] = 'dutch_angle';
        }

        $particleMap = [
            '雨' => 'rain', '雪' => 'snow', '雾' => 'fog_wisps',
            '火' => 'embers', '火焰' => 'embers', '火星' => 'embers',
        ];
        foreach ($particleMap as $kw => $particle) {
            if (mb_strpos($text, $kw) !== false) {
                $newAtom['variant_ops']['particle'] = $particle;
                break;
            }
        }

        if (preg_match('/三分法/', $text)) {
            $newAtom['variant_ops']['composition'] = 'rule_of_thirds';
        } elseif (preg_match('/对角线/', $text)) {
            $newAtom['variant_ops']['composition'] = 'diagonal';
        } elseif (preg_match('/对称/', $text)) {
            $newAtom['variant_ops']['composition'] = 'symmetric';
        } elseif (preg_match('/引导线/', $text)) {
            $newAtom['variant_ops']['composition'] = 'leading_lines';
        }

        return $newAtom;
    }
}
