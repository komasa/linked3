<?php

declare(strict_types=1);
/**
 * DiagramValidationLayer — extracted from DiagramMETALayer.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramValidationLayer {
    /**
     * 构建4维一致性校验。
     */
    public function build(array $config): array {
        return [
            'visual_consistency' => [
                'ratio'        => __('9:16竖版', 'linked3'),
                'border'       => __('细线圆角边框0.75pt', 'linked3'),
                'padding'      => __('内边距15%', 'linked3'),
                'bg_tint'      => __('极淡底色', 'linked3'),
                'badge_system' => __('9徽章色互不相同', 'linked3'),
            ],
            'text_embed' => [
                'keyword_length'     => __('2-6字', 'linked3'),
                'golden_length'      => __('4字占比≥60%', 'linked3'),
                'font_ratio'         => '4:3:2:1.5',
                'min_font_size'      => '18pt',
                'image_text_ratio'   => '6:4',
            ],
            'system_quality' => [
                'color_restraint'    => __('主色灰+强调色克制', 'linked3'),
                'dashboard_quality'  => __('咨询级看板质感', 'linked3'),
                'endpoint_visible'   => __('右下角终点清晰', 'linked3'),
            ],
            'depth_anchor' => [
                '3layer'  => __('模块标题→子主题(2-4)→细节(2-3)', 'linked3'),
                '4layer'  => __('Case+Metric+Action 3锚点', 'linked3'),
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
