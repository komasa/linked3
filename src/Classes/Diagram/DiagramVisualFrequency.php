<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Visual_Frequency — extracted from DiagramEndpointRegistry.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Linked3_Diagram_Visual_Frequency {
    private array $frequencies = [
        'HF' => ['name_zh' => '高频', 'name_en' => 'High Frequency', 'desc' => '快速切换/动画密集', 'fps' => '24fps', 'suitable_for' => '动感/紧张/科技'],
        'MF' => ['name_zh' => '中频', 'name_en' => 'Medium Frequency', 'desc' => '正常节奏/适中切换', 'fps' => '12fps', 'suitable_for' => '教学/讲解/展示'],
        'LF' => ['name_zh' => '低频', 'name_en' => 'Low Frequency', 'desc' => '静态/慢速/定格', 'fps' => '6fps', 'suitable_for' => '冥想/总结/品牌'],
    ];

    public function getFrequency(string $code): ?array {
        return $this->frequencies[$code] ?? null;
    }

    public function selectByMood(string $mood): string {
        if (preg_match('/动感|紧张|科技|快/', $mood)) return 'HF';
        if (preg_match('/冥想|总结|品牌|慢/', $mood)) return 'LF';
        return 'MF';
    }

    public function getFrequencies(): array { return $this->frequencies; }
}
