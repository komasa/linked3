<?php

declare(strict_types=1);
/**
 * Linked3 Diagram 3Layer Depth — v6.1.0.3
 *
 * 3层内容深度引擎: 模块标题 → 子主题(2-4个) → 细节项(每子主题2-3个)
 *
 * @package Linked3\Diagram
 * @since 6.1.0.3
 */
namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Diagram3LayerDepth {
    /**
     * 构建3层深度结构。
     */
    public function build(array $modules): array {
        $result = [];
        foreach ($modules as $module) {
            $entry = [
                'title' => $module['title'] ?? 'Module',
                'layer1' => $module['title'] ?? 'Module',           // 第1层: 模块标题
                'layer2' => [],  // 第2层: 子主题
                'layer3' => [],  // 第3层: 细节项
            ];

            $subTopics = $module['sub_topics'] ?? [];
            // 子主题数量限制 2-4
            if (count($subTopics) < 2) {
                // 不足2个, 补充默认
                $subTopics = array_merge($subTopics, [
                    ['title' => '核心概念', 'details' => ['定义', '特征']],
                    ['title' => '应用场景', 'details' => ['场景A', '场景B']],
                ]);
            } elseif (count($subTopics) > 4) {
                $subTopics = array_slice($subTopics, 0, 4);
            }

            foreach ($subTopics as $st) {
                $stTitle = $st['title'] ?? 'Sub-topic';
                $details = $st['details'] ?? [];
                // 细节项限制 2-3
                if (count($details) < 2) {
                    $details = array_merge($details, ['补充细节1', '补充细节2']);
                } elseif (count($details) > 3) {
                    $details = array_slice($details, 0, 3);
                }
                $entry['layer2'][] = $stTitle;
                $entry['layer3'][$stTitle] = $details;
            }
            $result[] = $entry;
        }
        return $result;
    }

    /**
     * 校验3层深度。
     */
    public function validate(array $module): array {
        $issues = [];
        if (!isset($module['layer2']) || count($module['layer2']) < 2) {
            $issues[] = '子主题不足2个';
        }
        if (count($module['layer2'] ?? []) > 4) {
            $issues[] = '子主题超过4个';
        }
        foreach ($module['layer3'] ?? [] as $st => $details) {
            if (count($details) < 2) $issues[] = "子主题{$st}细节不足2个";
            if (count($details) > 3) $issues[] = "子主题{$st}细节超过3个";
        }
        return ['passed' => empty($issues), 'issues' => $issues];
    }
}


