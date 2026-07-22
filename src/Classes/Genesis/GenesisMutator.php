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
}
