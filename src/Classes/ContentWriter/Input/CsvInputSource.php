<?php

declare(strict_types=1);
namespace Linked3\Classes\ContentWriter\Input;
if (!defined('ABSPATH')) exit;

/**
 * Csv input source.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.ContentWriter.Input
 * @since      27.1.0
 */

final class CsvInputSource implements InputSourceInterface
{
    public function slug() : string { return 'csv'; }
    public function label() : string { return __('CSV 文件', 'linked3'); }

    public function fetch(array $config, int $limit = 10): array {
        $file = $config['file_path'] ?? '';
        if (empty($file) || !file_exists($file)) {
            return [];
        }
        $handle = fopen($file, 'r');
        if (!$handle) return [];
        $items = [];
        $count = 0;
        $headers = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            if ($count >= $limit) break;
            $assoc = array_combine($headers, $row);
            $items[] = [
                'title'   => $assoc['title'] ?? '',
                'content' => $assoc['content'] ?? '',
                'url'     => $assoc['url'] ?? '',
                'guid'    => md5(($assoc['title'] ?? '') . ($assoc['url'] ?? '') . $count),
            ];
            $count++;
        }
        fclose($handle);
        return $items;
    }
}
