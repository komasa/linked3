<?php
namespace Linked3\Classes\ContentWriter\Input;
if (!defined('ABSPATH')) exit;

/**
 * Manual input source.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.ContentWriter.Input
 * @since      27.1.0
 */

final class Linked3_Manual_Input_Source implements Linked3_Input_Source_Interface
{
    public function slug() : string { return 'manual'; }
    public function label() : mixed { return __('手动输入', 'linked3'); }

    public function fetch(array $config, $limit = 10) : mixed {
        $items = $config['items'] ?? [];
        if (!is_array($items)) return [];
        return array_slice($items, 0, $limit);
    }
}
