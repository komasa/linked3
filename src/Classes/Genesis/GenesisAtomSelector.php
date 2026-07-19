<?php

declare(strict_types=1);
/**
 * Linked3_Genesis_AtomSelector — extracted from GenesisAtomIndex.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Genesis

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class Linked3_Genesis_AtomSelector {
    private GenesisAtomIndex $index;

    public function __construct() {
        $this->index = GenesisAtomIndex::instance();
    }

    public function selectForScene(array $scene): array {
        $text = $scene['action'] . ' ' . $scene['location'] . ' ' . $scene['mood'];
        $contentType = linked3_genesis_detect_content_type($text);
        $characters = !empty($scene['characters'])
            ? $this->matchCharacters($scene['characters'])
            : linked3_genesis_detect_characters($text);

        return [
            'content_type' => $contentType,
            'characters' => $characters,
            'template' => $this->selectTemplate($contentType, $characters),
            'scene' => $this->selectScene($scene['location']),
            'atmosphere' => $this->selectAtmosphere($scene['mood']),
            'camera' => $this->selectCamera($scene),
            'composition' => $this->selectComposition($contentType),
            'color_mapping' => $this->selectColorMapping($this->selectScene($scene['location'])),
        ];
    }

    private function matchCharacters(array $charNames): array {
        $found = [];
        foreach ($charNames as $name) {
            foreach (linked3_genesis_get_character_keywords() as $charId => $keywords) {
                foreach ($keywords as $kw) {
                    if (mb_strpos($name, $kw) !== false) {
                        $found[] = $charId;
                        break 2;
                    }
                }
            }
        }
        return array_unique($found) ?: ['C001'];
    }

    private function selectTemplate(string $contentType, array $characters): array {
        $templates = $this->index->getTemplates();
        foreach ($templates as $id => $t) {
            if ($t['content_type'] === $contentType) return ['id' => $id, 'fields' => $t];
        }
        $first = reset($templates);
        return ['id' => key($templates), 'fields' => $first];
    }

    private function selectScene(string $location): array {
        $scenes = $this->index->getScenes();
        foreach ($scenes as $id => $s) {
            if (mb_strpos($location, $s['scene_name']) !== false) return ['id' => $id, 'fields' => $s];
        }
        $keywords = ['古宅' => 'SC01', '荒野' => 'SC02', '山' => 'SC03', '地府' => 'SC04', '回忆' => 'SC05'];
        foreach ($keywords as $kw => $sid) {
            if (mb_strpos($location, $kw) !== false && isset($scenes[$sid])) {
                return ['id' => $sid, 'fields' => $scenes[$sid]];
            }
        }
        return ['id' => 'SC01', 'fields' => $scenes['SC01'] ?? []];
    }

    private function selectAtmosphere(string $mood): array {
        $atmospheres = $this->index->getByType('atmosphere');
        foreach ($atmospheres as $aid) {
            $atom = $this->index->getAtom($aid);
            if ($atom && mb_strpos($mood, $atom['fields']['mood_name'] ?? '') !== false) {
                return ['id' => $aid, 'fields' => $atom['fields']];
            }
        }
        $atom = $this->index->getAtom('AT01');
        return ['id' => 'AT01', 'fields' => $atom['fields'] ?? []];
    }

    private function selectCamera(array $scene): array {
        $cameras = $this->index->getByType('camera');
        $idx = count($scene['characters'] ?? []) % max(1, count($cameras));
        $camId = $cameras[$idx] ?? 'CM01';
        $atom = $this->index->getAtom($camId);
        return ['id' => $camId, 'fields' => $atom['fields'] ?? []];
    }

    private function selectComposition(string $contentType): array {
        $prefer = [
            'T1_动作战斗' => 'CP02', 'T2_对话叙事' => 'CP01',
            'T4_情感回忆' => 'CP03', 'T5_对峙紧张' => 'CP04',
        ];
        $preferId = $prefer[$contentType] ?? 'CP01';
        $atom = $this->index->getAtom($preferId);
        return ['id' => $preferId, 'fields' => $atom['fields'] ?? []];
    }

    private function selectColorMapping(array $sceneAtom): array {
        $sid = $sceneAtom['id'] ?? 'SC01';
        $mappings = $this->index->getByType('color_mapping');
        foreach ($mappings as $mid) {
            $atom = $this->index->getAtom($mid);
            if ($atom && ($atom['fields']['scene_ref'] ?? '') === $sid) {
                return ['id' => $mid, 'fields' => $atom['fields']];
            }
        }
        $atom = $this->index->getAtom('CM01');
        return ['id' => 'CM01', 'fields' => $atom['fields'] ?? []];
    }
}
