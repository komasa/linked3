<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class GenesisEngineCore
{
    public function searchKeyword(string $keyword): array {
        return $this->data['keyword_index'][mb_strtolower($keyword)] ?? [];
    }

    public function getByType(string $type): array {
        return $this->data['by_type'][$type] ?? [];
    }

    public function getCharacters(): array {
        $result = [];
        foreach ($this->getByType('character') as $id) {
            $atom = $this->getAtom($id);
            if ($atom) $result[$id] = $atom['fields'];
        }
        return $result;
    }

    public function getScenes(): array {
        $result = [];
        foreach ($this->getByType('scene') as $id) {
            $atom = $this->getAtom($id);
            if ($atom) $result[$id] = $atom['fields'];
        }
        return $result;
    }

}
