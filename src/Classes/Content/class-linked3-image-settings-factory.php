<?php
/**
 * Linked3 Image Settings Factory — 图片设置工厂 (Trait版)
 *
 * v10.5.4 (G5) 新增: 图片设置工厂, 使用Content_Ecosystem_Trait
 *
 * 移植 feicai4.0 万相/宝玉信息图方法论:
 *   • 万相图片生成 (text2image, 多分辨率)
 *   • 宝玉信息图 (20布局×17风格)
 *   • 图片注入策略 (位置/alt/尺寸)
 *
 * @package Linked3\Content
 * @since 10.5.4
 * @version 10.5.4
 */

namespace Linked3\Classes\Content;
    use Linked3_Content_Ecosystem_Trait;



if (!defined('ABSPATH')) exit;

if (!trait_exists('Linked3_Content_Ecosystem_Trait')) {
    require_once __DIR__ . '/trait-linked3-content-ecosystem.php';
}

class Linked3_Image_Settings_Factory {
    /** @var array 万相支持分辨率 (移植feicai4.0) */
    private $wanxiang_resolutions = [
        'square' => '1280*1280',
        'portrait' => '960*1696',
        'landscape' => '1696*960',
        'portrait_tall' => '1104*1472',
        'landscape_wide' => '1472*1104',
    ];

    /** @var array 宝玉信息图20布局 */
    private $infographic_layouts = [
        'comparison', 'timeline', 'process', 'list', 'quote',
        'stat_highlight', 'before_after', 'pyramid', 'matrix', 'flowchart',
        'radar', 'funnel', 'cycle', 'map', 'tree',
        'table', 'card', 'banner', 'step_bar', 'kpi',
    ];

    /** @var array 图片注入位置策略 */
    private $insertion_strategies = [
        'after_h1' => 'H1标题后',
        'after_first_paragraph' => '第一段后',
        'middle' => '文章中间',
        'before_conclusion' => '总结前',
        'featured' => '特色图片',
    ];

    public function __construct() {
        $this->module_type = 'image_settings';
    }

    /**
     * 生成正文 — 图片模块不直接生成正文
     */
    protected function generate_content(array $ir): string {
        return '';
    }

    /**
     * 生成配图 — 实现 feicai4.0 万相+信息图方法论
     */
    protected function generate_image(array $ir): array {
        $content = $ir['content'] ?? '';
        $keywords = $ir['keywords'] ?? [];
        $topic = $this->eco_context['topic'] ?? '';

        $images = [];

        // 1. 特色图片 (万相生成)
        $featured_prompt = $this->build_featured_prompt($topic, $keywords);
        $images[] = [
            'type' => 'featured',
            'prompt' => $featured_prompt,
            'resolution' => $this->wanxiang_resolutions['landscape'],
            'source' => 'wanxiang',
        ];

        // 2. 内容配图 (信息图布局)
        $content_images = $this->plan_content_images($content, $keywords);
        $images = array_merge($images, $content_images);

        // 3. 注入策略
        foreach ($images as &$img) {
            $img['insertion'] = $this->select_insertion_strategy($img['type']);
        }

        return $images;
    }

    /**
     * 获取图片设置
     */
    public function get_image_settings(): array {
        // 委托 Image_Manager (若存在)
        if (class_exists('\Linked3\Classes\Content\Linked3_Image_Manager')) {
            try {
                $mgr = new \Linked3_Image_Manager();
                if (method_exists($mgr, 'get_settings')) {
                    return $mgr->get_settings();
                }
            } catch (\Throwable $e) {}
        }

        // 降级: 默认设置
        return [
            'provider' => 'wanxiang',
            'default_resolution' => $this->wanxiang_resolutions['landscape'],
            'quality' => 'high',
            'auto_insert' => true,
            'insertion_strategy' => 'after_first_paragraph',
        ];
    }

    /**
     * 保存图片设置
     */
    public function save_image_settings(array $settings): bool {
        if (class_exists('\Linked3\Classes\Content\Linked3_Image_Manager')) {
            try {
                $mgr = new \Linked3_Image_Manager();
                if (method_exists($mgr, 'save_settings')) {
                    return $mgr->save_settings($settings);
                }
            } catch (\Throwable $e) {}
        }
        return false;
    }

    /**
     * 获取所有信息图布局
     */
    public function get_infographic_layouts(): array {
        return $this->infographic_layouts;
    }

    /**
     * 获取万相分辨率选项
     */
    public function get_wanxiang_resolutions(): array {
        return $this->wanxiang_resolutions;
    }

    private function build_featured_prompt(string $topic, array $keywords): string {
        $kw_str = implode(', ', array_slice($keywords, 0, 5));
        return sprintf(
            '专业配图, 主题: %s, 关键词: %s, 高质量, 细节丰富, 电影感光影',
            $topic, $kw_str
        );
    }

    private function plan_content_images(string $content, array $keywords): array {
        $images = [];
        $paragraphs = preg_split('/\n\s*\n/', $content);
        $paragraph_count = count($paragraphs);

        // 每3段配1张图
        $image_count = min(3, max(1, intval($paragraph_count / 3)));

        for ($i = 0; $i < $image_count; $i++) {
            $layout = $this->select_layout_by_content($content, $i);
            $images[] = [
                'type' => 'content_' . ($i + 1),
                'prompt' => $this->build_content_prompt($content, $keywords, $layout, $i),
                'resolution' => $this->wanxiang_resolutions['landscape'],
                'layout' => $layout,
                'source' => 'infographic',
            ];
        }

        return $images;
    }

    private function select_layout_by_content(string $content, int $index): string {
        // 智能匹配布局
        if (preg_match('/对比|vs|区别/i', $content)) return 'comparison';
        if (preg_match('/步骤|流程|如何/i', $content)) return 'process';
        if (preg_match('/数据|百分比|增长/i', $content)) return 'stat_highlight';
        if (preg_match('/时间|历史|发展/i', $content)) return 'timeline';

        // 按索引轮换
        return $this->infographic_layouts[$index % count($this->infographic_layouts)];
    }

    private function build_content_prompt(string $content, array $keywords, string $layout, int $index): string {
        $kw_str = implode(', ', array_slice($keywords, 0, 3));
        $layout_desc = $layout . ' layout';
        return sprintf(
            '信息图, %s, 关键词: %s, 清晰排版, 专业配色, 中文标注',
            $layout_desc, $kw_str
        );
    }

    private function select_insertion_strategy(string $image_type): string {
        if ($image_type === 'featured') return 'featured';
        if ($image_type === 'content_1') return 'after_first_paragraph';
        if ($image_type === 'content_2') return 'middle';
        return 'before_conclusion';
    }
}
