<?php

declare(strict_types=1);
/**
 * 云模板管理 — 迁移 v2.9.6 yunmub.php (AI_Template_Manager)。
 *
 * 功能:预设文章模板(标题/内容/SEO 配置),用户可一键应用。
 * 原版支持云端模板同步,本版先用本地模板,Premium 可扩展云端。
 *
 * @package Linked3
 * @subpackage Classes\Templates
 */

namespace Linked3\Classes\Templates;
    use \Linked3\Classes\Templates\TemplateSeedTrait;



if (!defined('ABSPATH')) {
    exit;
}

final class TemplateManager
{
    use TemplateSeedTrait;

    /**
     * 预置模板(本地)。v4.8.0: delegates to the shared seed trait.
     */
    public function default_templates() : mixed {
        return $this->seed_templates_simple();
    }

    /**
     * 获取用户模板 + 预置模板。
     */
    public function get_all() : mixed     {
        $custom = get_option(LINKED3_OPTION_PREFIX . 'templates', []);
        $defaults = $this->default_templates();
        return array_merge($defaults, is_array($custom) ? $custom : []);
    }

    /**
     * v5.1.1: 获取指定类别的模板 (content / pipeline / visual)。
     *
     * @param string $category
     * @return array
     */
    public function get_by_category(string $category = 'content') : mixed {
        // A 类 (content): 从 option 存储的模板
        if ($category === 'content') {
            return $this->get_all();
        }
        // B 类 (pipeline): 从 seed_pipeline_templates() 获取内置 + option 存储
        if ($category === 'pipeline') {
            $custom = get_option(LINKED3_OPTION_PREFIX . 'pipeline_templates', []);
            $defaults = $this->seed_pipeline_templates();
            return array_merge($defaults, is_array($custom) ? $custom : []);
        }
        // C 类 (visual): v5.2 预留
        if ($category === 'visual') {
            return [];
        }
        return [];
    }

    /**
     * v5.1.1: 获取指定管线阶段的模板。
     *
     * @param string $stage  rewrite/outline/section/keyword/title/meta/excerpt/tags/video_script/visual
     * @return array
     */
    public function get_pipeline_templates(string $stage = '') : mixed     {
        $all = $this->get_by_category('pipeline');
        if (empty($stage)) {
            return $all;
        }
        return array_values(array_filter($all, fn($tpl) => ($tpl['pipeline_stage'] ?? '') === $stage));
    }

    /**
     * 获取单个自定义模板 (按索引)。
     */
    public function get_custom($index)
    {
        $custom = get_option(LINKED3_OPTION_PREFIX . 'templates', []);
        if (!is_array($custom)) return null;
        $index = (int) $index;
        return $custom[$index] ?? null;
    }

    /**
     * 添加用户模板。
     */
    public function add($name, $type, array $config)
    {
        $custom = get_option(LINKED3_OPTION_PREFIX . 'templates', []);
        if (!is_array($custom)) $custom = [];
        $custom[] = [
            'name' => sanitize_text_field($name),
            'type' => sanitize_text_field($type),
            'config' => $this->sanitize_config($config),
        ];
        update_option(LINKED3_OPTION_PREFIX . 'templates', $custom);
        return count($custom);
    }

    /**
     * 更新用户模板 (按索引)。
     */
    public function update($index, $name, $type, array $config): bool {
        $custom = get_option(LINKED3_OPTION_PREFIX . 'templates', []);
        if (!is_array($custom)) return false;
        $index = (int) $index;
        if (!isset($custom[$index])) return false;
        $custom[$index] = [
            'name' => sanitize_text_field($name),
            'type' => sanitize_text_field($type),
            'config' => $this->sanitize_config($config),
        ];
        update_option(LINKED3_OPTION_PREFIX . 'templates', $custom);
        return true;
    }

    /**
     * 删除用户模板(按索引)。
     */
    public function delete($index): bool {
        $custom = get_option(LINKED3_OPTION_PREFIX . 'templates', []);
        if (!is_array($custom)) return false;
        $index = (int) $index;
        if (!isset($custom[$index])) return false;
        array_splice($custom, $index, 1);
        update_option(LINKED3_OPTION_PREFIX . 'templates', $custom);
        return true;
    }

    /**
     * 保存用户模板 (兼容旧方法)。
     */
    public function save($name, $type, array $config)
    {
        return $this->add($name, $type, $config);
    }

    private function sanitize_config(array $cfg): array {
        return [
            'tone' => sanitize_text_field($cfg['tone'] ?? 'professional'),
            'complexity' => sanitize_text_field($cfg['complexity'] ?? 'intermediate'),
            'word_count' => (int) ($cfg['word_count'] ?? 1200),
            'content_length' => sanitize_text_field($cfg['content_length'] ?? 'medium'),
            'seo_focus' => (bool) ($cfg['seo_focus'] ?? true),
            'sections' => isset($cfg['sections']) && is_array($cfg['sections'])
                ? array_map('sanitize_text_field', $cfg['sections']) : [],
            'prompt' => sanitize_textarea_field($cfg['prompt'] ?? ''),
            'provider' => sanitize_text_field($cfg['provider'] ?? ''),
            'model' => sanitize_text_field($cfg['model'] ?? ''),
            'temperature' => (float) ($cfg['temperature'] ?? 0.7),
            'max_tokens' => (int) ($cfg['max_tokens'] ?? 2000),
            // v2.1.0: 6 个独立 prompt 字段
            'prompt_mode' => sanitize_text_field($cfg['prompt_mode'] ?? 'default'),
            'custom_title_prompt' => sanitize_textarea_field($cfg['custom_title_prompt'] ?? ''),
            'custom_content_prompt' => sanitize_textarea_field($cfg['custom_content_prompt'] ?? ''),
            'custom_meta_prompt' => sanitize_textarea_field($cfg['custom_meta_prompt'] ?? ''),
            'custom_keyword_prompt' => sanitize_textarea_field($cfg['custom_keyword_prompt'] ?? ''),
            'custom_excerpt_prompt' => sanitize_textarea_field($cfg['custom_excerpt_prompt'] ?? ''),
            'custom_tags_prompt' => sanitize_textarea_field($cfg['custom_tags_prompt'] ?? ''),
        ];
    }
}
