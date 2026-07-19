<?php
/**
 * Linked3 Prompt Engine — v5.6.0.5
 *
 * 功能:
 *   - 模板化 Prompt 管理 (变量替换/条件/循环)
 *   - 支持占位符: {topic} {keyword} {word_count} {brand} {mood} ...
 *   - A/B 测试: 同一意图多版本 Prompt 随机/轮转
 *   - Prompt 版本追踪: 记录哪个 Prompt 效果最好
 *
 * @package Linked3\AI\Pipeline
 * @since 5.6.0.5
 */
namespace Linked3\Classes\AI\Pipeline;

if (!defined('ABSPATH')) exit;

class Linked3_Prompt_Engine {
    private static ?Linked3_Prompt_Engine $instance = null;
    private array $templates = [];
    private array $abVariants = [];
    private array $performance = []; // ['template_id' => ['calls'=>0, 'avg_score'=>0]]

    public static function instance(): Linked3_Prompt_Engine {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /**
     * 注册 Prompt 模板。
     */
    public function register(string $id, string $template, array $defaults = []): void {
        $this->templates[$id] = [
            'template' => $template,
            'defaults' => $defaults,
        ];
    }

    /**
     * 渲染 Prompt (替换占位符)。
     */
    public function render(string $id, array $vars = []): string {
        if (!isset($this->templates[$id])) {
            throw new RuntimeException("Prompt template not found: {$id}");
        }
        $tpl = $this->templates[$id];
        $vars = array_merge($tpl['defaults'], $vars);
        $prompt = $tpl['template'];

        // 替换占位符 {key}
        foreach ($vars as $key => $value) {
            $prompt = str_replace('{' . $key . '}', (string) $value, $prompt);
        }
        // 清理未匹配的占位符
        $prompt = preg_replace('/\{[a-z_]+\}/i', '', $prompt);

        return $prompt;
    }

    /**
     * A/B 测试: 注册同一模板的多个变体。
     */
    public function registerAB(string $experimentId, array $variants): void {
        $this->abVariants[$experimentId] = $variants;
    }

    /**
     * A/B 测试: 获取一个变体 (随机)。
     */
    public function getABVariant(string $experimentId): string {
        if (!isset($this->abVariants[$experimentId])) {
            throw new RuntimeException("A/B experiment not found: {$experimentId}");
        }
        $variants = $this->abVariants[$experimentId];
        return $variants[array_rand($variants)];
    }

    /**
     * 记录 Prompt 性能 (用于优化)。
     */
    public function recordPerformance(string $templateId, float $score): void {
        if (!isset($this->performance[$templateId])) {
            $this->performance[$templateId] = ['calls' => 0, 'total_score' => 0, 'avg_score' => 0];
        }
        $this->performance[$templateId]['calls']++;
        $this->performance[$templateId]['total_score'] += $score;
        $this->performance[$templateId]['avg_score'] =
            $this->performance[$templateId]['total_score'] / $this->performance[$templateId]['calls'];
    }

    /**
     * 获取最佳模板 (按平均分排序)。
     */
    public function getBestTemplate(array $candidateIds): ?string {
        $best = null;
        $bestScore = 0;
        foreach ($candidateIds as $id) {
            $score = $this->performance[$id]['avg_score'] ?? 0;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $id;
            }
        }
        return $best;
    }

    /**
     * 获取性能报告。
     */
    public function getPerformanceReport(): array {
        return $this->performance;
    }
}

/**
 * Linked3 Content Quality Scorer — v5.6.0.5
 * 内容质量评分器: 规则 + AI 混合评分
 */
class Linked3_Content_Quality_Scorer {
    private array $rules = [];

    /**
     * 评分 (0-100)。
     */
    public function score(array $content): array {
        $scores = [];
        $scores['word_count'] = $this->scoreWordCount($content['content'] ?? '');
        $scores['readability'] = $this->scoreReadability($content['content'] ?? '');
        $scores['seo'] = $this->scoreSEO($content);
        $scores['structure'] = $this->scoreStructure($content['content'] ?? '');
        $scores['originality'] = $this->scoreOriginality($content['content'] ?? '');
        $scores['engagement'] = $this->scoreEngagement($content['content'] ?? '');

        $weights = [
            'word_count' => 0.15, 'readability' => 0.20, 'seo' => 0.25,
            'structure' => 0.15, 'originality' => 0.15, 'engagement' => 0.10,
        ];
        $overall = 0;
        foreach ($scores as $k => $v) {
            $overall += $v * ($weights[$k] ?? 0);
        }

        return [
            'overall' => round($overall, 1),
            'scores' => $scores,
            'passed' => $overall >= 70,
            'grade' => $this->toGrade($overall),
        ];
    }

    private function scoreWordCount(string $content): int {
        $count = mb_strlen(strip_tags($content));
        if ($count >= 2000) return 100;
        if ($count >= 1200) return 80;
        if ($count >= 600) return 60;
        return 30;
    }

    private function scoreReadability(string $content): int {
        $text = strip_tags($content);
        $sentences = preg_split('/[。！？.!?\n]/', $text);
        $sentences = array_filter($sentences, fn($s) => trim($s));
        if (empty($sentences)) return 50;
        $avgLen = mb_strlen(implode('', $sentences)) / count($sentences);
        if ($avgLen <= 25) return 90;
        if ($avgLen <= 40) return 75;
        if ($avgLen <= 60) return 55;
        return 30;
    }

    private function scoreSEO(array $content): int {
        $score = 0;
        if (!empty($content['title']) && mb_strlen($content['title']) <= 60) $score += 15;
        if (!empty($content['meta_description']) && mb_strlen($content['meta_description']) <= 160) $score += 15;
        if (!empty($content['keywords'])) $score += 15;
        if (!empty($content['title']) && strpos($content['content'] ?? '', $content['title']) !== false) $score += 15;
        $h2count = preg_match_all('/<h2|##\s/i', $content['content'] ?? '');
        if ($h2count >= 3) $score += 20;
        if (preg_match('/<img|!\[/', $content['content'] ?? '')) $score += 10;
        if (preg_match('/<a |\[.*\]\(http/', $content['content'] ?? '')) $score += 10;
        return min(100, $score);
    }

    private function scoreStructure(string $content): int {
        $h2 = preg_match_all('/<h2|##\s/i', $content);
        $h3 = preg_match_all('/<h3|###\s/i', $content);
        $p = preg_match_all('/<p>|<br|\n\n/i', $content);
        $list = preg_match_all('/<li|^- |^\d+\./im', $content);
        $score = min(100, $h2 * 12 + $h3 * 8 + min($p, 8) * 4 + min($list, 5) * 6);
        return max(30, $score);
    }

    private function scoreOriginality(string $content): int {
        // 简化: 检查重复短语
        $text = strip_tags($content);
        $phrases = array_filter(explode('。', $text));
        $unique = array_unique($phrases);
        $ratio = count($phrases) > 0 ? count($unique) / count($phrases) : 1;
        return (int) ($ratio * 100);
    }

    private function scoreEngagement(string $content): int {
        $score = 50;
        // 问句增加互动
        if (preg_match('/[？?]/', $content)) $score += 10;
        // 数字增加可信
        if (preg_match('/\d+/', $content)) $score += 10;
        // CTA
        if (preg_match('/关注|点赞|评论|分享|收藏/', $content)) $score += 15;
        // 情感词
        if (preg_match('/惊|震惊|必看|关键|核心|重要/', $content)) $score += 15;
        return min(100, $score);
    }

    private function toGrade(float $score): string {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }
}
