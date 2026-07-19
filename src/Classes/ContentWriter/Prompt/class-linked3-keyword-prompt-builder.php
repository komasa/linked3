<?php
namespace Linked3\Classes\ContentWriter\Prompt;
if (!defined('ABSPATH')) exit;
/**
 * Keyword prompt builder.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.ContentWriter.Prompt
 * @since      27.1.0
 */
final class Linked3_Keyword_Prompt_Builder {
    public function build(array $params) : mixed {
        $seed = $params['seed'] ?? '';
        $count = (int) ($params['count'] ?? 20);
        $lang = $params['language'] ?? 'zh-CN';
        return sprintf(
            __('为种子关键词「%2$s」生成 %1$d 个长尾关键词变体(用 %3$s),每行一个,不要编号。', 'linked3'),
            $count, $seed, $lang
        );
    }
}
