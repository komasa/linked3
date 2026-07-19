<?php
/**
 * System instruction builder v17.0 — 写作风格DNA + 反AI人类化
 *
 * v17.0 重构:
 *   - 集成写作风格DNA库 (16种行业大拿/顶级作品风格)
 *   - 集成反AI人类化规则 (参照/deai框架)
 *   - 修复输出不完整: 增加输出完整性指令
 *   - 修复AI味重: 注入人类混沌感
 *
 * @package Linked3
 * @subpackage Classes\ContentWriter\Prompt
 * @version 17.0.0
 */

namespace Linked3\Classes\ContentWriter\Prompt;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_System_Instruction_Builder
{
    /**
     * @var array 写作风格DNA缓存
     */
    private static ?array $style_cache = null;

    /**
     * 加载写作风格DNA库
     */
    public static function load_styles(): array
    {
        if (self::$style_cache !== null) {
            return self::$style_cache;
        }
        $path = LINKED3_DIR . 'src/Classes/ContentWriter/styles/_index.json';
        if (!file_exists($path)) {
            return self::$style_cache = ['styles' => []];
        }
        $json = file_get_contents($path);
        // v19.54: 检查 file_get_contents 失败
        if ($json === false) {
            return self::$style_cache = ['styles' => []];
        }
        $data = json_decode($json, true);
        return self::$style_cache = is_array($data) ? $data : ['styles' => []];
    }

    /**
     * 获取风格DNA
     */
    public static function get_style_dna(string $style_id): array
    {
        $styles = self::load_styles();
        return $styles['styles'][$style_id] ?? [];
    }

    /**
     * 获取所有风格列表 (用于UI下拉)
     */
    public static function get_style_options(): array
    {
        $styles = self::load_styles();
        $options = [];
        foreach ($styles['styles'] as $id => $info) {
            $options[$id] = $info['name_cn'] . ' [' . $info['category'] . ']';
        }
        return $options;
    }

    /**
     * 构建系统指令 (v17.0: 风格DNA + 反AI + 输出完整性)
     *
     * @param array $config {role, tone, language, complexity, seo_focus, require_html, style_dna, anti_ai}
     * @return string
     */
    public function build(array $config) : mixed {
        $role = $config['role'] ?? __('专业内容写作', 'linked3');
        $tone = $config['tone'] ?? 'professional';
        $language = $config['language'] ?? 'zh-CN';
        $complexity = $config['complexity'] ?? 'intermediate';
        $seo_focus = $config['seo_focus'] ?? true;
        $require_html = $config['require_html'] ?? false;
        $style_dna_id = $config['style_dna'] ?? '';
        $anti_ai = $config['anti_ai'] ?? true;

        $lang_name = [
            'zh-CN' => __('简体中文', 'linked3'),
            'en' => 'English',
            'ja' => '日本語',
        ][$language] ?? $language;

        $complexity_desc = [
            'beginner' => __('用简单的方式解释概念,避免术语,使用类比。', 'linked3'),
            'intermediate' => __('平衡技术深度与可读性。', 'linked3'),
            'expert' => __('假设读者是领域专家,使用精确术语。', 'linked3'),
        ][$complexity] ?? '';

        // v17.0: 基础指令
        $instruction = sprintf(
            __('您是一位 %1$s,用 %2$s 语气写作,完全使用 %3$s。', 'linked3'),
            $role, $tone, $lang_name
        );

        if ($complexity_desc) {
            $instruction .= ' ' . $complexity_desc;
        }

        // v17.0: 注入风格DNA
        if ($style_dna_id) {
            $style = self::get_style_dna($style_dna_id);
            if (!empty($style['prompt_dna'])) {
                $instruction .= "\n\n## 写作风格DNA\n" . $style['prompt_dna'];
            }
            // v17.1: 标志性表达 (人物化风格的核心特征)
            if (!empty($style['signature_phrases'])) {
                $instruction .= "\n\n## 标志性表达 (可自然融入, 不要生硬堆砌)\n";
                $instruction .= implode(' / ', $style['signature_phrases']);
            }
            if (!empty($style['anti_ai_rules']) && $anti_ai) {
                $instruction .= "\n\n## 反AI规则 (必须严格遵守)\n";
                foreach ($style['anti_ai_rules'] as $i => $rule) {
                    $instruction .= ($i + 1) . '. ' . $rule . "\n";
                }
            }
        }

        // v17.0: 通用反AI规则 (即使没有风格DNA也生效)
        if ($anti_ai && !$style_dna_id) {
            $instruction .= "\n\n## 反AI人类化规则 (必须严格遵守)\n";
            $instruction .= "1. 禁止使用'总之/综上所述/首先其次最后'等AI高频套话\n";
            $instruction .= "2. 禁止使用'值得注意的是/需要指出的是'开头\n";
            $instruction .= "3. 段落长度必须不均匀——有3字短段也有200字长段\n";
            $instruction .= "4. 至少有一处用'我'的第一人称经历或观点\n";
            $instruction .= "5. 禁止每段都是总分总结构,要有倒叙/插叙\n";
            $instruction .= "6. 用具体数字代替'大幅/显著/明显'等模糊词\n";
            $instruction .= "7. 结尾不要给标准答案,留下思考空间\n";
        }

        if ($seo_focus) {
            $instruction .= "\n\n## SEO要求\n";
            $instruction .= __('在标题、首段、2-3个小标题中自然使用目标关键词,避免堆砌。', 'linked3');
        }

        // v17.0: 输出格式 + 完整性指令
        $instruction .= "\n\n## 输出格式\n";
        $instruction .= $require_html
            ? '输出HTML标签格式(用H2/H3/p/ul/li等标签),不要加CSS代码,不需要<!DOCTYPE html>、<html>、<head>、<body>标签。正文不要包含H1标题。'
            : '输出Markdown格式,使用H2/H3小标题,适当用列表,含结尾段。正文不要包含H1标题。';

        // v17.0: 输出完整性指令 (修复文章输出不完整)
        $instruction .= "\n\n## 输出完整性要求 (极其重要)\n";
        $instruction .= "1. 必须写完整篇文章,包括引言、正文各段、结尾\n";
        $instruction .= "2. 不要在中途停止,不要输出'...'或'待续'\n";
        $instruction .= "3. 如果接近输出长度限制,优先保证文章结构完整(可以缩短段落但不能缺少段落)\n";
        $instruction .= "4. 每段必须写完最后一个句子,不能出现断句\n";
        $instruction .= "5. 结尾段必须有完整的总结或收束,不能戛然而止\n";

        return $instruction;
    }
}
