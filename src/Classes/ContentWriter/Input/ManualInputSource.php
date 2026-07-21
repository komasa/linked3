<?php

declare(strict_types=1);
namespace Linked3\Classes\ContentWriter\Input;
if (!defined('ABSPATH')) exit;

/**
 * Manual input source.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.ContentWriter.Input
 * @since      27.1.0
 */

final class ManualInputSource implements InputSourceInterface
{
    public function slug() : string { return 'manual'; }
    public function label() : string { return __('手动输入', 'linked3'); }

    public function fetch(array $config, int $limit = 10): array {
        $items = $config['items'] ?? [];
        if (!is_array($items)) return [];
        return array_slice($items, 0, $limit);
    }
}
