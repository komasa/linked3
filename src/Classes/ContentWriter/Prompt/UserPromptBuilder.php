<?php

declare(strict_types=1);
/**
 * User prompt builder — crafts the per-article user prompt.
 *
 * @package Linked3
 * @subpackage Classes\ContentWriter\Prompt
 */

namespace Linked3\Classes\ContentWriter\Prompt;

if (!defined('ABSPATH')) {
    exit;
}

final class UserPromptBuilder
{
    /**
     * @param array $params {keyword, title, outline, word_count, references, extra_instructions}
     * @return string
     */
    public function build(array $params) : mixed {
        $keyword = $params['keyword'] ?? '';
        $title = $params['title'] ?? '';
        $outline = $params['outline'] ?? [];
        $word_count = (int) ($params['word_count'] ?? 1200);
        $references = $params['references'] ?? [];
        $extra = $params['extra_instructions'] ?? '';

        $parts = [];

        if ($title) {
            $parts[] = sprintf(__('标题:%s', 'linked3'), $title);
        }
        if ($keyword) {
            $parts[] = sprintf(__('目标关键词:%s', 'linked3'), $keyword);
        }
        $parts[] = sprintf(__('字数:约 %d 字。', 'linked3'), $word_count);

        if (!empty($outline) && is_array($outline)) {
            $parts[] = __('建议大纲:', 'linked3');
            foreach ($outline as $i => $heading) {
                $parts[] = sprintf('%d. %s', $i + 1, $heading);
            }
        }

        if (!empty($references) && is_array($references)) {
            $parts[] = __('参考资料(综合改写,不要照抄):', 'linked3');
            foreach ($references as $ref) {
                $parts[] = '- ' . $ref;
            }
        }

        if ($extra) {
            $parts[] = __('附加说明:', 'linked3');
            $parts[] = $extra;
        }

        $parts[] = __('现在写出完整正文(Markdown 格式)。', 'linked3');

        return implode("\n\n", $parts);
    }
}
