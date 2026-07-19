<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_VisualScript_Transform — extracted from Diagram30Spectrum.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Linked3_Diagram_VisualScript_Transform {
    /**
     * 3层管线: 图示 → 剧本 → 动画
     */
    public function transform(array $diagram): array {
        return [
            'layer1_diagram' => $diagram,
            'layer2_script' => $this->diagramToScript($diagram),
            'layer3_animation' => $this->scriptToAnimation($this->diagramToScript($diagram)),
        ];
    }

    private function diagramToScript(array $diagram): array {
        $scenes = [];
        foreach ($diagram['bands'] ?? [] as $i => $band) {
            $scenes[] = [
                'scene' => $i + 1,
                'band' => $band['title'] ?? "Band {$i}",
                'visual' => $band['modules'] ?? [],
                'narration' => '画面展示' . ($band['title'] ?? ''),
                'duration' => 3,
            ];
        }
        return ['scenes' => $scenes, 'total_duration' => count($scenes) * 3];
    }

    private function scriptToAnimation(array $script): array {
        return [
            'keyframes' => array_map(fn($s) => [
                'time' => ($s['scene'] - 1) * $s['duration'],
                'action' => 'show_band',
                'target' => $s['band'],
            ], $script['scenes']),
            'transitions' => 'fade',
            'total_duration' => $script['total_duration'],
        ];
    }
}

// =================================================================
// v6.5.0.5: 品牌视觉资产5维度
// =================================================================
