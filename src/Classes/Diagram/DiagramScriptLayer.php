<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Script_Layer — extracted from DiagramMETALayer.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Linked3_Diagram_Script_Layer {
    /**
     * 构建剧本层。
     */
    public function build(array $config): array {
        $bands = $config['bands'] ?? [];
        $arcParts = [];
        foreach ($bands as $i => $band) {
            $act = $band['act_name'] ?? "Act" . ($i + 1);
            $arcParts[] = "{$act}({$band['title']})";
        }
        $arc = implode(' -> ', $arcParts) . ' -> Endpoint(' . ($config['endpoint']['type'] ?? 'Flywheel') . ')';

        return [
            'arc'          => $arc,
            'dialogue'     => [
                'main_title' => $config['main_title'] ?? "《{$config['brand']}全景图谱》",
                'top_left'   => "ID: " . ($config['id'] ?? 'DIAGRAM_001'),
                'top_right'  => "01/01 | " . ucfirst($config['density'] ?? 'deep') . "版",
            ],
            'emotion_map'  => $config['emotion_map'] ?? $this->defaultEmotionMap(),
            'transition'   => 'THICK GRAY SPINE lines connect bands. THIN GRAY ARROWS connect modules. LONG DASHED LINE from endpoint back to ID.',
            'pacing'       => 'extremely dense, like textbook page. NOT a slide.',
            'bands'        => $bands,
            'endpoint'     => $config['endpoint'] ?? [],
            'footer'       => $config['footer'] ?? '',
        ];
    }

    private function defaultEmotionMap(): array {
        return [
            'band1' => '稳重·基础', 'band2' => '活力·执行',
            'band3' => '深度·框架', 'band4' => '成就·结果', 'endpoint' => '闭环·飞轮',
        ];
    }

    public function render(array $script): string {
        $out = "# Script\n";
        $out .= "Arc: {$script['arc']}\n";
        $out .= "Main Title: \"{$script['dialogue']['main_title']}\"\n";
        $out .= "EmotionMap: " . json_encode($script['emotion_map'], JSON_UNESCAPED_UNICODE) . "\n";
        $out .= "Transition: {$script['transition']}\n";
        $out .= "Pacing: {$script['pacing']}\n";
        return $out;
    }
}

// =================================================================
// v6.2.0.3: 验证校验层
// =================================================================
