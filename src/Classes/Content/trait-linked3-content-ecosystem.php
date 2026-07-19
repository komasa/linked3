<?php
/**
 * Linked3 Content Ecosystem Trait — 内容生态4模块共享Trait
 *
 * v10.5.0 (G5) 新增: 4模块(内容写作/关键词/云模版/图片设置)生态共享Trait
 *
 * 设计原理 (公理M: 内容生态4模块统一Trait):
 *   - 4模块共享统一生产管线
 *   - 通过Content_IR中间表示解耦 (公理N)
 *   - Trait代替继承, 零加载顺序风险 (公理J)
 *
 * 生态管线 (5阶段):
 *   Stage 0: load_keywords()  — 加载关键词 (驱动内容方向)
 *   Stage 1: load_template()  — 加载云模版 (驱动内容形态)
 *   Stage 2: generate_content() — [抽象] 生成正文 (消费关键词+模版)
 *   Stage 3: generate_image() — [抽象] 生成配图 (消费内容, 反馈关键词)
 *   Stage 4: quality_check()  — 生态质检
 *
 * @package Linked3\Content
 * @since 10.5.0
 * @version 10.5.0
 */

namespace Linked3\Classes\Content;

if (!defined('ABSPATH')) exit;

trait Linked3_Content_Ecosystem_Trait {

    /** @var string 模块类型 */
    protected $module_type = '';

    /** @var array Content_IR 中间表示 */
    protected $content_ir = [];

    /** @var array 生态上下文 */
    protected $eco_context = [];

    /**
     * 生态生产入口 — 模板方法, 锁定5阶段管线
     */
    public function ecosystem_generate(array $context): array {
        $this->eco_context = $context;

        try {
            $this->content_ir['keywords'] = $this->load_keywords($context);
            $this->content_ir['template'] = $this->load_template($context);
            $this->content_ir['content'] = $this->generate_content($this->content_ir);
            $this->content_ir['images'] = $this->generate_image($this->content_ir);
            $quality = $this->ecosystem_quality_check($this->content_ir);

            return [
                'success' => true,
                'ir' => $this->content_ir,
                'quality' => $quality,
                'meta' => ['module_type' => $this->module_type, 'ecosystem_version' => '10.5.0'],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'ir' => $this->content_ir,
            ];
        }
    }

    protected function load_keywords(array $context): array {
        if (!empty($context['keywords'])) {
            return is_array($context['keywords']) ? $context['keywords'] : [$context['keywords']];
        }
        if (class_exists('\Linked3\Classes\Content\Linked3_Keyword_Manager')) {
            try {
                $seed = $context['topic'] ?? $context['seed'] ?? '';
                if ($seed) {
                    $mgr = new \Linked3_Keyword_Manager();
                    if (method_exists($mgr, 'generate_tail_keywords')) {
                        return $mgr->generate_tail_keywords($seed, $context['keyword_count'] ?? 10);
                    }
                }
            } catch (\Throwable $e) {}
        }
        return [];
    }

    protected function load_template(array $context): array {
        if (!empty($context['template'])) {
            return is_array($context['template']) ? $context['template'] : ['name' => $context['template']];
        }
        if (class_exists('\Linked3\Classes\Content\Linked3_Template_Manager')) {
            try {
                $category = $context['template_category'] ?? 'content';
                $mgr = new \Linked3_Template_Manager();
                if (method_exists($mgr, 'get_by_category')) {
                    $templates = $mgr->get_by_category($category);
                    if (!empty($templates)) return $templates[0];
                }
            } catch (\Throwable $e) {}
        }
        return [];
    }

    protected function ecosystem_quality_check(array $ir): array {
        $checks = [];
        $score = 0;

        $checks['keywords_present'] = ['name' => '关键词已加载', 'passed' => !empty($ir['keywords']), 'value' => count($ir['keywords'] ?? [])];
        if (!empty($ir['keywords'])) $score += 25;

        $checks['template_present'] = ['name' => '模版已加载', 'passed' => !empty($ir['template'])];
        if (!empty($ir['template'])) $score += 25;

        $checks['content_present'] = ['name' => '内容已生成', 'passed' => !empty($ir['content']), 'value' => mb_strlen($ir['content'] ?? '')];
        if (!empty($ir['content'])) $score += 25;

        $checks['images_present'] = ['name' => '图片已生成', 'passed' => !empty($ir['images']), 'value' => count($ir['images'] ?? [])];
        if (!empty($ir['images'])) $score += 25;

        return ['score' => $score, 'checks' => $checks, 'passed' => $score >= 60];
    }

    abstract protected function generate_content(array $ir): string;
    abstract protected function generate_image(array $ir): array;

    protected function get_ir(string $key = '', $default = null) : mixed {
        if ($key === '') return $this->content_ir;
        return $this->content_ir[$key] ?? $default;
    }

    protected function get_eco_context(string $key, $default = null) {
        return $this->eco_context[$key] ?? $default;
    }
}
