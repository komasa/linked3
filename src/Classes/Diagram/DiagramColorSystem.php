<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Color_System — extracted from Diagram3LayerDepth.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramColorSystem {
    const BADGE_COLORS = [
        '01' => '#4A90E2', '02' => '#F5A623', '03' => '#7ED321',
        '04' => '#D0506E', '05' => '#9013FE', '06' => '#50C8D6',
        '07' => '#B8860B', '08' => '#8B4513', '09' => '#2E8B57',
    ];
    const GLOBAL_PRIMARY = '#2F4F4F';
    const BACKGROUND = '#F8F8FF';

    private array $moodColorMap = [
        '稳重' => '#2F4F4F', '活力' => '#F5A623', '深度' => '#9013FE',
        '成就' => '#7ED321', '闭环' => '#50C8D6', '紧迫' => '#D0506E',
        '冷静' => '#4A90E2', '温暖' => '#B8860B',
    ];

    public function getBadgeColor(string $badgeNum): string {
        return self::BADGE_COLORS[$badgeNum] ?? '#4A90E2';
    }

    public function getMoodColor(string $mood): string {
        foreach ($this->moodColorMap as $keyword => $color) {
            if (strpos($mood, $keyword) !== false) return $color;
        }
        return self::GLOBAL_PRIMARY;
    }

    public function getColorPalette(): array {
        return [
            'background' => self::BACKGROUND,
            'global_primary' => self::GLOBAL_PRIMARY,
            'badges' => self::BADGE_COLORS,
            'mood_map' => $this->moodColorMap,
        ];
    }
}

/**
 * Linked3 Diagram Validation 13Dim — v6.1.0.9
 * 13维校验系统
 */
