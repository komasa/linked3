<?php

declare(strict_types=1);
/**
 * Linked3_Genesis_StoryboardGenerator — extracted from GenesisAtomIndex.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Genesis

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisStoryboardGenerator {
    private array $shots = ['远景', '中景', '近景', '特写'];
    private array $angles = ['平视', '仰视', '俯视'];
    private array $comps = ['三分法', '对角线', '中心构图', '对称式'];

    public function generate(array $scenes, int $panelsPerScene = 0): array {
        $panels = [];
        $selector = new Linked3_Genesis_AtomSelector();

        foreach ($scenes as $sc) {
            $charCount = max(1, count($sc['characters'] ?? []) ?: 1);
            $panelCount = $panelsPerScene > 0 ? $panelsPerScene : min(max($charCount + 1, 3), 5);

            for ($i = 0; $i < $panelCount; $i++) {
                $focusChar = !empty($sc['characters'])
                    ? $sc['characters'][$i % count($sc['characters'])]
                    : '';

                $panel = [
                    'panel_id' => 'P' . str_pad((string)(count($panels) + 1), 4, '0', STR_PAD_LEFT),
                    'scene_id' => $sc['id'],
                    'location' => $sc['location'],
                    'action' => $sc['action'],
                    'mood' => $sc['mood'],
                    'focus' => $focusChar,
                    'shot' => $this->shots[$i % 4],
                    'angle' => $this->angles[$i % 3],
                    'comp' => $this->comps[$i % 4],
                    'characters' => $sc['characters'] ?? [],
                    'dialogue' => $sc['dialogues'][$i] ?? '',
                ];

                $panel['atoms'] = $selector->selectForScene($sc);
                $panels[] = $panel;
            }
        }

        return $panels;
    }
}
