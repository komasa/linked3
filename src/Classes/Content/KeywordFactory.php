<?php

declare(strict_types=1);
/**
 * Linked3 Keyword Factory — 关键词工厂 (Trait版)
 *
 * v10.5.2 (G5) 新增: 关键词工厂, 使用Content_Ecosystem_Trait
 *
 * 移植 feicai4.0 提示词工程方法论:
 *   • 关键词三维度分类 (功能/结构/应用)
 *   • 长尾词生成策略
 *   • 热词+长尾词生态组合
 *
 * @package Linked3\Content
 * @since 10.5.2
 * @version 10.5.2
 */

namespace Linked3\Classes\Content;
    use ContentEcosystemTrait;



if (!defined('ABSPATH')) exit;

if (!trait_exists(__NAMESPACE__ . '\\ContentEcosystemTrait')) {
    require_once __DIR__ . '/ContentEcosystemTrait.php';
}

class KeywordFactory {
    /** @var array 关键词分类 (移植feicai4.0三维度) */
    private $keyword_dimensions = [
        'functional' => ['primary', 'secondary', 'long_tail', 'branded'],
        'structural' => ['question', 'comparison', 'transactional', 'informational'],
        'applicational' => ['head', 'body', 'tail'],
    ];

    public function __construct() {
        $this->module_type = 'keyword';
    }

    /**
     * 生成正文 — 关键词模块产出关键词列表 (作为content的输入)
     */
    protected function generate_content(array $ir): string {
        $keywords = $ir['keywords'] ?? [];
        $topic = $this->eco_context['topic'] ?? '';

        // 委托 Keyword_Manager (若存在)
        if (class_exists('\Linked3\Classes\SEO\Keyword\KeywordManager')) {
            try {
                $mgr = new \Linked3\Classes\SEO\Keyword\KeywordManager();
                if (method_exists($mgr, 'generate_tail_keywords')) {
                    $tail = $mgr->generate_tail_keywords($topic, 10);
                    if (is_array($tail)) {
                        $keywords = array_merge($keywords, $tail);
                    }
                }
            } catch (\Throwable $e) { if (function_exists("linked3_log")) linked3_log("app", "warning", $e->getMessage()); else error_log("Linked3: " . $e->getMessage()); }
        }

        // 降级: 本地生成
        if (empty($keywords)) {
            $keywords = $this->generate_local_keywords($topic);
        }

        // 更新IR
        $this->content_ir['keywords'] = array_unique($keywords);

        return implode(', ', array_slice($keywords, 0, 10));
    }

    /**
     * 生成配图 — 关键词模块不直接生成图片
     */
    protected function generate_image(array $ir): array {
        return [];
    }

    private function generate_local_keywords(string $topic): array {
        if (empty($topic)) return [];

        $keywords = [$topic];
        // 长尾词模板
        $templates = [
            '%s是什么', '%s怎么做', '%s教程', '%s攻略',
            '%s工具', '%s软件', '%s推荐', '%s对比',
            '最好的%s', '免费的%s', '%s2026', '%s最新',
        ];
        foreach ($templates as $tpl) {
            $keywords[] = sprintf($tpl, $topic);
        }
        return array_unique($keywords);
    }

    private function classify_keywords(array $keywords): array {
        $classified = ['primary' => [], 'long_tail' => [], 'question' => []];
        foreach ($keywords as $kw) {
            if (mb_strpos($kw, '什么') !== false || mb_strpos($kw, '怎么') !== false) {
                $classified['question'][] = $kw;
            } elseif (mb_strlen($kw) > 8) {
                $classified['long_tail'][] = $kw;
            } else {
                $classified['primary'][] = $kw;
            }
        }
        return $classified;
    }
}
