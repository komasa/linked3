<?php

declare(strict_types=1);
/**
 * Markdown → HTML 强制转换器 (兜底保障)
 *
 * 当高级设置启用 require_html 但 AI 仍返回 Markdown 时,
 * 自动将 Markdown 语法转换为安全的 HTML 标签。
 *
 * 转换规则:
 *   - H1/H2/H3/H4 (#/##/###/####) → <h1>/<h2>/<h3>/<h4>
 *   - 加粗 **text** / __text__ → <strong>text</strong>
 *   - 斜体 *text* / _text_ → <em>text</em>
 *   - 删除线 ~~text~~ → <del>text</del>
 *   - 行内代码 `code` → <code>code</code>
 *   - 代码块 ```lang\ncode\n``` → <pre><code>code</code></pre>
 *   - 引用 > text → <blockquote>text</blockquote>
 *   - 无序列表 - / * / + 开头 → <ul><li>
 *   - 有序列表 1. 开头 → <ol><li>
 *   - 分隔线 --- / *** → <hr>
 *   - 段落 → <p>
 *   - 链接 [text](url) → <a href="url">text</a>
 *
 * 安全保障:
 *   - 已是 HTML 的内容跳过 (检测到 <p>/<h2> 等标签)
 *   - 不允许 <script>/<iframe>/<style>/<object> 等危险标签
 *   - 不输出 <!DOCTYPE>/<html>/<head>/<body>
 *
 * @package Linked3
 * @subpackage Classes\ContentWriter\Prompt
 */

namespace Linked3\Classes\ContentWriter\Prompt;

if (!defined('ABSPATH')) {
    exit;
}

final class MarkdownHtmlConverter
{
    /** @var array 临时存储代码块占位符 (避免闭包 use) */
    private static $code_blocks = [];

    /** @var array 临时存储行内代码占位符 (避免闭包 use) */
    private static $inline_codes = [];

    /**
     * 主入口:根据需要将内容转换为 HTML。
     *
     * @param string $content   原始 AI 输出
     * @param bool   $require_html 是否要求 HTML 格式
     * @return string
     */
    public static function convert(string $content, bool $require_html = true) : mixed {
        if (empty($content) || !$require_html) {
            return $content;
        }

        // 已是 HTML(检测关键标签)则只清理危险标签
        if (self::is_already_html($content)) {
            return self::sanitize_html($content);
        }

        // 看起来是 Markdown,执行转换
        $html = self::markdown_to_html($content);
        return self::sanitize_html($html);
    }

    /**
     * 检测内容是否已经是 HTML 格式。
     */
    private static function is_already_html($content): bool {
        // 检测常见 HTML 块级标签
        $html_tags = ['<p>', '<p ', '<h1', '<h2', '<h3', '<h4', '<h5', '<h6',
                      '<ul', '<ol', '<li', '<blockquote', '<div', '<table',
                      '<pre', '<img', '<figure'];
        foreach ($html_tags as $tag) {
            if (stripos($content, $tag) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Markdown → HTML 转换。
     */
    private static function markdown_to_html($content) : mixed     {
        // 标准化换行
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // 1) 提取代码块(避免被其他规则破坏)
        self::$code_blocks = [];
        $content = preg_replace_callback('/```(\w*)\n(.*?)```/s', function ($m) {
            $lang = $m[1] ? ' class="language-' . esc_attr($m[1]) . '"' : '';
            $code = esc_html($m[2]);
            $placeholder = '<!--CODEBLOCK' . count(self::$code_blocks) . '-->';
            self::$code_blocks[] = '<pre><code' . $lang . '>' . $code . '</code></pre>';
            return $placeholder;
        }, $content);

        // 2) 行内代码(避免被其他规则破坏)
        self::$inline_codes = [];
        $content = preg_replace_callback('/`([^`]+)`/', function ($m) {
            $placeholder = '<!--INLINECODE' . count(self::$inline_codes) . '-->';
            self::$inline_codes[] = '<code>' . esc_html($m[1]) . '</code>';
            return $placeholder;
        }, $content);

        // 3) 链接 [text](url)
        $content = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/', function ($m) {
            return '<a href="' . esc_url($m[2]) . '">' . esc_html($m[1]) . '</a>';
        }, $content);

        // 4) 图片 ![alt](url)
        $content = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', function ($m) {
            return '<img src="' . esc_url($m[2]) . '" alt="' . esc_attr($m[1]) . '" />';
        }, $content);

        // 5) 水平分隔线 --- / *** / ___(独占一行)
        $content = preg_replace('/^(?:---|\*\*\*|___)\s*$/m', '<hr />', $content);

        // 6) 标题 #(1-6) → h1-h6(独占一行)
        for ($i = 6; $i >= 1; $i--) {
            $hashes = str_repeat('#', $i);
            $content = preg_replace(
                '/^' . preg_quote($hashes, '/') . '\s+(.+)$/m',
                '<h' . $i . '>$1</h' . $i . '>',
                $content
            );
        }

        // 7) 加粗 **text** 或 __text__
        $content = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $content);

        // 8) 斜体 *text* 或 _text_(注意避免与加粗冲突)
        $content = preg_replace('/(?<!\*)\*([^\*\n]+)\*(?!\*)/', '<em>$1</em>', $content);
        $content = preg_replace('/(?<!_)_([^_\n]+)_(?!_)/', '<em>$1</em>', $content);

        // 9) 删除线 ~~text~~
        $content = preg_replace('/~~([^~]+)~~/', '<del>$1</del>', $content);

        // 10) 引用 > text(连续行合并)
        $content = preg_replace_callback('/^(?:>\s?.*(?:\n>\s?.*)*)/m', function ($m) {
            $lines = explode("\n", $m[0]);
            $text = '';
            foreach ($lines as $line) {
                $text .= trim(preg_replace('/^>\s?/', '', $line)) . "\n";
            }
            return '<blockquote>' . trim($text) . '</blockquote>';
        }, $content);

        // 11) 列表(无序 -/*/+,有序 1. 2.)
        $content = self::convert_lists($content);

        // 12) 段落处理 — 将连续非空行包裹 <p>
        $content = self::wrap_paragraphs($content);

        // 13) 还原行内代码与代码块
        foreach (self::$inline_codes as $i => $code) {
            $content = str_replace('<!--INLINECODE' . $i . '-->', $code, $content);
        }
        foreach (self::$code_blocks as $i => $code) {
            $content = str_replace('<!--CODEBLOCK' . $i . '-->', $code, $content);
        }
        // 清理静态属性,避免内存残留
        self::$code_blocks = [];
        self::$inline_codes = [];

        // 14) 清理多余空行
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        return trim($content);
    }

    /**
     * 转换列表(无序+有序)。
     */
    private static function convert_lists($content) : mixed {
        $lines = explode("\n", $content);
        $out = [];
        $in_ul = false;
        $in_ol = false;

        foreach ($lines as $line) {
            // 无序列表项
            if (preg_match('/^\s*[-*+]\s+(.+)$/', $line, $m)) {
                if ($in_ol) { $out[] = '</ol>'; $in_ol = false; }
                if (!$in_ul) { $out[] = '<ul>'; $in_ul = true; }
                $out[] = '<li>' . trim($m[1]) . '</li>';
                continue;
            }
            // 有序列表项
            if (preg_match('/^\s*\d+\.\s+(.+)$/', $line, $m)) {
                if ($in_ul) { $out[] = '</ul>'; $in_ul = false; }
                if (!$in_ol) { $out[] = '<ol>'; $in_ol = true; }
                $out[] = '<li>' . trim($m[1]) . '</li>';
                continue;
            }
            // 非列表行
            if ($in_ul) { $out[] = '</ul>'; $in_ul = false; }
            if ($in_ol) { $out[] = '</ol>'; $in_ol = false; }
            $out[] = $line;
        }
        if ($in_ul) $out[] = '</ul>';
        if ($in_ol) $out[] = '</ol>';

        return implode("\n", $out);
    }

    /**
     * 将连续非空非块级行包裹为 <p>。
     */
    private static function wrap_paragraphs($content) : mixed     {
        $blocks = preg_split('/\n{2,}/', $content);
        $result = [];
        $block_tags = ['<h1', '<h2', '<h3', '<h4', '<h5', '<h6',
                       '<ul', '<ol', '<blockquote', '<pre', '<hr',
                       '<table', '<div', '<figure', '<p'];

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') continue;

            // 已是块级元素,跳过
            $is_block = false;
            foreach ($block_tags as $tag) {
                if (stripos($block, $tag) === 0) {
                    $is_block = true;
                    break;
                }
            }
            if ($is_block) {
                $result[] = $block;
                continue;
            }

            // 单行 hr 跳过
            if ($block === '<hr />') {
                $result[] = $block;
                continue;
            }

            // 段内换行保留为 <br>
            $block = nl2br($block);
            $result[] = '<p>' . $block . '</p>';
        }
        return implode("\n\n", $result);
    }

    /**
     * HTML 安全清理:移除危险标签与属性,剥离文档结构标签。
     */
    private static function sanitize_html($content)
    {
        // 移除文档结构标签
        $content = preg_replace('/<!DOCTYPE[^>]*>/i', '', $content);
        $content = preg_replace('/<\/?(?:html|head|body|meta|link|title)[^>]*>/i', '', $content);

        // 移除危险标签(script/iframe/object/embed/style 等)
        $dangerous = ['script', 'iframe', 'object', 'embed', 'style', 'form',
                      'input', 'button', 'textarea', 'select', 'option',
                      'base', 'svg', 'math'];
        foreach ($dangerous as $tag) {
            $content = preg_replace('/<' . $tag . '[^>]*>.*?<\/' . $tag . '>/is', '', $content);
            $content = preg_replace('/<\/?' . $tag . '[^>]*>/i', '', $content);
        }

        // 移除事件属性 (on*)
        $content = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $content);
        $content = preg_replace('/\s+on\w+\s*=\s*\'[^\']*\'/i', '', $content);
        $content = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $content);

        // 移除 javascript: 协议
        $content = preg_replace('/href\s*=\s*"\s*javascript:[^"]*"/i', 'href="#"', $content);
        $content = preg_replace('/href\s*=\s*\'\s*javascript:[^\']*\'/i', 'href="#"', $content);

        return trim($content);
    }
}
