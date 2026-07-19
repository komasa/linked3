<?php
namespace Linked3\Classes\ContentWriter\Prompt;
if (!defined('ABSPATH')) exit;
/**
 * Excerpt prompt builder.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.ContentWriter.Prompt
 * @since      27.1.0
 */
final class Linked3_Excerpt_Prompt_Builder {
    public function build(array $params) : mixed {
        $title = $params['title'] ?? '';
        $keyword = $params['keyword'] ?? '';
        return sprintf(
            __('Write a compelling 1-2 sentence excerpt (max 160 chars) for an article titled "%1$s" targeting keyword "%2$s". Output only the excerpt, no preamble.', 'linked3'),
            $title, $keyword
        );
    }
}
