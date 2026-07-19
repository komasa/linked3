<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Validation_Layer — extracted from DiagramMETALayer.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Linked3_Diagram_Validation_Layer {
    /**
     * 构建4维一致性校验。
     */
    public function build(array $config): array {
        return [
            'visual_consistency' => [
                'ratio'        => '9:16竖版',
                'border'       => '细线圆角边框0.75pt',
                'padding'      => '内边距15%',
                'bg_tint'      => '极淡底色',
                'badge_system' => '9徽章色互不相同',
            ],
            'text_embed' => [
                'keyword_length'     => '2-6字',
                'golden_length'      => '4字占比≥60%',
                'font_ratio'         => '4:3:2:1.5',
                'min_font_size'      => '18pt',
                'image_text_ratio'   => '6:4',
            ],
            'system_quality' => [
                'color_restraint'    => '主色灰+强调色克制',
                'dashboard_quality'  => '咨询级看板质感',
                'endpoint_visible'   => '右下角终点清晰',
            ],
            'depth_anchor' => [
                '3layer'  => '模块标题→子主题(2-4)→细节(2-3)',
                '4layer'  => 'Case+Metric+Action 3锚点',
            ],
        ];
    }

    public function render(array $validation): string {
        $out = "# Validation\n";
        foreach ($validation as $dim => $rules) {
            $out .= "{$dim}: " . implode(', ', $rules) . "\n";
        }
        return $out;
    }
}

// =================================================================
// v6.2.0.4: 三层编译器
// =================================================================
