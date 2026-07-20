<?php

declare(strict_types=1);
/**
 * DiagramFailureHandbook — extracted from DiagramMETALayer.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramFailureHandbook {
    private array $failures = [
        'F1' => [
            'name' => '图文脱咬',
            'symptom' => '文字漂浮在图示外部',
            'fix' => '将所有文字严格嵌入图示形状内部',
            'severity' => 'Critical',
        ],
        'F2' => [
            'name' => '层级混乱',
            'symptom' => '模块标题字号小于正文',
            'fix' => '执行字号比4:3:2:1.5',
            'severity' => 'Critical',
        ],
        'F3' => [
            'name' => '色彩溢出',
            'symptom' => '使用了9徽章色以外的颜色',
            'fix' => '严格使用9徽章色+全局主色',
            'severity' => 'Important',
        ],
        'F4' => [
            'name' => '密度不足',
            'symptom' => '图示看起来像PPT而非教科书页',
            'fix' => '增加子主题和细节项, 提升信息密度',
            'severity' => 'Important',
        ],
        'F5' => [
            'name' => '锚点缺失',
            'symptom' => '子主题没有Case+Metric+Action',
            'fix' => '为每个子主题添加3锚点',
            'severity' => 'Important',
        ],
        'F6' => [
            'name' => 'Endpoint缺失',
            'symptom' => '没有右下角终点图示',
            'fix' => '按6种Endpoint决策树选择并添加',
            'severity' => 'Critical',
        ],
        'F7' => [
            'name' => '关系线过多',
            'symptom' => '关系线超过9条',
            'fix' => '精简到最多9条, 每模块≤2条',
            'severity' => 'Flexible',
        ],
        'F8' => [
            'name' => '四字黄金不足',
            'symptom' => '4字关键词占比<60%',
            'fix' => '用关键词提炼5法压缩到4字',
            'severity' => 'Flexible',
        ],
    ];

    public function diagnose(array $diagram): array {
        $found = [];
        $validator = new DiagramValidation13Dim();
        $validation = $validator->validate($diagram);

        foreach ($validation['checks'] as $dim => $check) {
            if (!$check['passed']) {
                $failureId = $this->mapDimToFailure($dim);
                if ($failureId && isset($this->failures[$failureId])) {
                    $found[] = array_merge(['id' => $failureId], $this->failures[$failureId], ['dim' => $dim]);
                }
            }
        }
        return $found;
    }

    private function mapDimToFailure(string $dim): ?string {
        $map = [
            'text_embed' => 'F1', 'visual' => 'F2', 'system' => 'F3',
            'density_4level' => 'F4', 'anchor_4layer' => 'F5',
            'endpoint_6type' => 'F6', 'relationship_6code' => 'F7',
        ];
        return $map[$dim] ?? null;
    }

    public function getFailure(string $id): ?array {
        return $this->failures[$id] ?? null;
    }

    public function allFailures(): array {
        return $this->failures;
    }
}
