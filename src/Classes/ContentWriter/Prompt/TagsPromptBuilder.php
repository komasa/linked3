<?php

declare(strict_types=1);
namespace Linked3\Classes\ContentWriter\Prompt;
if (!defined('ABSPATH')) exit;
/**
 * Tags prompt builder.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.ContentWriter.Prompt
 * @since      27.1.0
 */
final class TagsPromptBuilder {
    public function build(array $params) : mixed {
        $title = $params['title'] ?? '';
        $keyword = $params['keyword'] ?? '';
        $max = (int) ($params['max_tags'] ?? 8);
        return sprintf(
            __('Suggest up to %1$d relevant tags for an article titled "%2$s" about "%3$s". Output as a comma-separated list, no numbering, no preamble.', 'linked3'),
            $max, $title, $keyword
        );
    }
}
