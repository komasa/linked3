<?php
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class Linked3_Genesis_Engine_Helpers
{
    public function searchKeyword(string $keyword) : mixed { return Linked3_Genesis_Engine_Core::searchKeyword($keyword); }

    public function getByType(string $type) : mixed { return Linked3_Genesis_Engine_Core::getByType($type); }

    public function getCharacters() : mixed { return Linked3_Genesis_Engine_Core::getCharacters(); }

    public function getScenes() : mixed { return Linked3_Genesis_Engine_Core::getScenes(); }

    public function getTemplates(): array {
        $result = [];
        foreach ($this->getByType('prompt_template') as $id) {
            $atom = $this->getAtom($id);
            if ($atom) $result[$id] = $atom['fields'];
        }
        return $result;
    }

}
