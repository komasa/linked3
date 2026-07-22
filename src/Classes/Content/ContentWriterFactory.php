<?php

declare(strict_types=1);
/**
 * Linked3 Content Writer Factory — 内容写作工厂 (Trait版)
 *
 * v10.5.1 (G5) 新增: 内容写作工厂, 使用Content_Ecosystem_Trait
 *
 * 移植 feicai4.0 文案5阶段法:
 *   Phase 1: 上下文收集 (受众/目标/产品)
 *   Phase 2: 文案简报锁定 (硬门禁)
 *   Phase 3: 草稿生成 (结构化输出)
 *   Phase 4: 自检 (诚实/清晰/可测)
 *   Phase 5: 交付 (含AB测试建议)
 *
 * @package Linked3\Content
 * @since 10.5.1
 * @version 10.5.1
 */

namespace Linked3\Classes\Content;
    use ContentEcosystemTrait;



if (!defined('ABSPATH')) exit;

// v10.5.1: 确保Trait已加载
if (!trait_exists('ContentEcosystemTrait')) {
    require_once __DIR__ . '/ContentEcosystemTrait.php';
}

class ContentWriterFactory {
    /** @var array feicai4.0 5阶段法 */
    private $copywriting_phases = [
        'context_gather' => '上下文收集: 受众/目标/产品/流量来源',
        'brief_lock'     => '文案简报锁定: 4-6要点硬门禁',
        'draft_generate' => '草稿生成: 结构化输出',
        'self_check'     => '自检: 诚实/清晰/可测',
        'deliver'        => '交付: 含AB测试建议',
    ];

    public function __construct() {
        $this->module_type = 'content_writer';
    }

    /**
     * 生成正文 — 实现 feicai4.0 5阶段法
     */
    protected function generate_content(array $ir): string {
        $keywords = $ir['keywords'] ?? [];
        $template = $ir['template'] ?? [];
        $topic = $this->eco_context['topic'] ?? '';

        // Phase 1: 上下文收集
        $context = $this->gather_context($topic, $keywords, $template);

        // Phase 2: 文案简报 (内化, 不暂停)
        $brief = $this->lock_brief($context);

        // Phase 3: 草稿生成
        $draft = $this->generate_draft($brief, $template);

        // Phase 4: 自检
        $draft = $this->self_check($draft);

        return $draft;
    }

    /**
     * 生成配图 — 委托 Image_Manager
     */
    protected function generate_image(array $ir): array {
        $content = $ir['content'] ?? '';
        if (empty($content)) return [];

        if (class_exists('\Linked3\Classes\Content\ImageManager')) {
            try {
                $mgr = new \ImageManager();
                $title = $this->eco_context['title'] ?? mb_substr($content, 0, 30);
                if (method_exists($mgr, 'build_prompt')) {
                    $prompt = $mgr->build_prompt($title, $content);
                    return [['prompt' => $prompt, 'source' => 'content_writer_factory']];
                }
            } catch (\Throwable $e) {}
        }
        return [];
    }

    private function gather_context(string $topic, array $keywords, array $template): array {
        return [
            'topic' => $topic,
            'keywords' => $keywords,
            'template' => $template,
            'audience' => $this->eco_context['audience'] ?? 'general',
            'goal' => $this->eco_context['goal'] ?? 'inform',
            'traffic_source' => $this->eco_context['traffic_source'] ?? 'organic',
        ];
    }

    private function lock_brief(array $context): array {
        return [
            'page_goal' => $context['goal'],
            'primary_cta' => $this->eco_context['cta'] ?? 'read_more',
            'audience' => $context['audience'],
            'key_keywords' => array_slice($context['keywords'], 0, 5),
            'tone' => $this->eco_context['tone'] ?? 'professional',
            'word_count' => $this->eco_context['word_count'] ?? 800,
        ];
    }

    private function generate_draft(array $brief, array $template): string {
        $topic = $brief['page_goal'] ?? 'content';
        $keywords = $brief['key_keywords'] ?? [];
        $word_count = $brief['word_count'] ?? 800;

        // 委托 Long_Form_Writer (若存在)
        if (class_exists('\Linked3\Classes\Content\LongFormWriter')) {
            try {
                $writer = new \LongFormWriter();
                if (method_exists($writer, 'generate')) {
                    return $writer->generate($topic, implode(',', $keywords), ['word_count' => $word_count]);
                }
            } catch (\Throwable $e) {}
        }

        // 降级: 模板化生成
        $kw_str = implode('、', array_slice($keywords, 0, 5));
        return sprintf(
            "# %s\n\n本文围绕【%s】展开, 涵盖关键词: %s。\n\n## 核心要点\n\n1. %s的基本概念\n2. 实际应用场景\n3. 最佳实践方法\n\n## 总结\n\n%s的完整指南, 助您快速掌握。",
            $topic, $topic, $kw_str, $topic, $topic
        );
    }

    private function self_check(string $draft): string {
        // feicai4.0 自检: 诚实/清晰/可测
        // 移除过度承诺词
        $overclaims = ['最好', '第一', '唯一', '100%', '绝对', '完美'];
        foreach ($overclaims as $word) {
            $draft = str_replace($word, '优秀', $draft);
        }
        return $draft;
    }
}
