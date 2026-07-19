<?php
namespace Linked3\Classes\ContentWriter\Prompt;
if (!defined('ABSPATH')) exit;
/**
 * Meta prompt builder.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.ContentWriter.Prompt
 * @since      27.1.0
 */
final class Linked3_Meta_Prompt_Builder {
    public function build(array $params) : mixed {
        $title = $params['title'] ?? '';
        $keyword = $params['keyword'] ?? '';
        return sprintf(
            __('为标题为「%1$s」、关于「%2$s」的文章生成 SEO Meta 描述(150-160 字符),必须自然包含关键词,仅输出 Meta 描述。', 'linked3'),
            $title, $keyword
        );
    }
}
