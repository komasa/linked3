<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class GenesisEngineHelpers
{
    public function searchKeyword(string $keyword) : mixed { return GenesisEngineCore::searchKeyword($keyword); }

    public function getByType(string $type) : mixed { return GenesisEngineCore::getByType($type); }

    public function getCharacters() : mixed { return GenesisEngineCore::getCharacters(); }

    public function getScenes() : mixed { return GenesisEngineCore::getScenes(); }

    public function getTemplates(): array {
        $result = [];
        foreach ($this->getByType('prompt_template') as $id) {
            $atom = $this->getAtom($id);
            if ($atom) $result[$id] = $atom['fields'];
        }
        return $result;
    }

}
