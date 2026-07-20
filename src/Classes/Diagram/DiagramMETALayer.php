<?php

declare(strict_types=1);
/**
 * Linked3 Diagram 三层提示词架构 — v6.2.0
 *
 * 9个原子版本:
 *   v6.2.0.1: META视觉定义层 (6要素锚点)
 *   v6.2.0.2: 剧本控制层5维度 (Arc/Dialogue/EmotionMap/Transition/Pacing)
 *   v6.2.0.3: 验证校验层 (4维一致性)
 *   v6.2.0.4: 三层编译器 (META+Script+Validation→Prompt)
 *   v6.2.0.5: Prompt≤4500字符压缩器
 *   v6.2.0.6: 关键词提炼5法+四字黄金
 *   v6.2.0.7: 图文咬合量化校验
 *   v6.2.0.8: Loop迭代法7步闭环
 *   v6.2.0.9: 8种断裂模式手册
 *
 * @package Linked3\Diagram
 * @since 6.2.0
 */
namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

// =================================================================
// v6.2.0.1: META视觉定义层
// =================================================================

class DiagramMETALayer {
    /**
     * 构建META层 (6要素锚点)。
     */
    public function build(array $config): array {
        return [
            'brand'      => $config['brand'] ?? '知识图谱',
            'signature'  => $config['signature'] ?? '带状切片全景图(独立线框模块+微观饱和卡片+精准图文咬合)',
            'color'      => $this->buildColorSystem($config),
            'mood'       => $config['mood'] ?? '宏大严密·克制高级·信息密集·竖屏16字',
            'culture'    => $config['culture'] ?? '结构化知识图谱',
            'platform'   => $config['platform'] ?? '9:16竖版无UI长图',
        ];
    }

    private function buildColorSystem(array $config): array {
        $cs = new Linked3_Diagram_Color_System();
        return array_merge($cs->getColorPalette(), $config['color'] ?? []);
    }

    /**
     * 渲染META为文本。
     */
    public function render(array $meta): string {
        $color = $meta['color'];
        $badgeStr = implode('+', array_slice($color['badges'] ?? [], 0, 3)) . '...';
        return sprintf(
            "[META:diagram]\nBrand:%s | Signature:%s | Color:%s(底)+%s(主色)+%s | Mood:%s | Culture:%s | Platform:%s\n",
            $meta['brand'], $meta['signature'],
            $color['background'] ?? '#F8F8FF',
            $color['global_primary'] ?? '#2F4F4F',
            $badgeStr,
            $meta['mood'], $meta['culture'], $meta['platform']
        );
    }
}

// =================================================================
// v6.2.0.2: 剧本控制层5维度


// =================================================================
// v6.2.0.3: 验证校验层


// =================================================================
// v6.2.0.4: 三层编译器


// =================================================================
// v6.2.0.5: Prompt压缩器


// =================================================================
// v6.2.0.6: 关键词提炼5法


// =================================================================
// v6.2.0.7: 图文咬合量化校验


// =================================================================
// v6.2.0.8: Loop迭代法7步闭环


// =================================================================
// v6.2.0.9: 8种断裂模式手册

