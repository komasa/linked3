<?php

declare(strict_types=1);
/**
 * Content Enhancement Processor — picks low-SEO-score posts, rewrites them
 * via the Article_Rewriter to improve keyword focus + readability.
 *
 * @package Linked3
 * @subpackage Classes\AutoGPT\Processors
 */

namespace Linked3\Classes\AutoGPT\Processors;

use Linked3\Classes\SEO\Scoring\SEOScorecard;
use Linked3\Classes\Collect\Rewriter\ArticleRewriter;



if (!defined('ABSPATH')) {
    exit;
}
final class ContentEnhancementProcessor implements AutoGPTProcessorInterface
{
    public function process(array $task): array {
        $cfg = $task['config'];
        $min_score = (int) ($cfg['min_score'] ?? 60);
        $max = (int) ($cfg['max_per_run'] ?? 5);
        $processed = 0;
        $failed = 0;

        // Find posts whose last modification was >30 days ago (so we don't
        // re-enhance freshly published or freshly edited content).
        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => $max * 3,
            'date_query' => [['column' => 'post_modified', 'before' => '30 days ago']],
            'orderby' => 'modified',
            'order' => 'ASC',
        ]);

        $scorecard = class_exists(SEOScorecard::class) ? new \Linked3\Classes\SEO\Scoring\SEOScorecard() : null;

        foreach ($posts as $p) {
            if ($processed >= $max) break;

            // v0.8.0 fix: original code used
            //   $score = class_exists(SEO_Scorer::class) ? 70 : 70;
            // — both branches returned 70 (so with default min_score=60 the
            // `score >= min_score` check ALWAYS skipped every post → the
            // processor was a complete no-op). The class name was also wrong
            // (SEO_Scorer never existed; the real class is
            // SEOScorecard). Now we compute the real composite
            // 0-100 SEO score; if the scorecard class is somehow absent, we
            // fall back to a word-count heuristic (under 600 words = needs
            // enhancement) so the processor still does useful work.
            if ($scorecard) {
                $eval = $scorecard->evaluate($p);
                $score = (int) ($eval['score'] ?? 70);
            } else {
                $words = str_word_count(wp_strip_all_tags((string) $p->post_content));
                $score = $words >= 600 ? 80 : 40;
            }
            if ($score >= $min_score) continue;

            try {
                $rewriter = new \Linked3\Classes\Collect\Rewriter\ArticleRewriter();
                // v3.1.0: 改读 default_provider + saved_models,不再硬编码 openai
                $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
                $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
                $model = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';
                $rw = $rewriter->rewrite($p->post_content, [
                    'seo_focus' => true,
                    'provider'  => $provider,
                    'model'     => $model,
                    'user_id'   => $task['user_id'], // v0.8.0: bill the task owner
                ]);
                if (!empty($rw['ok']) && !empty($rw['content'])) {
                    wp_update_post([
                        'ID'           => $p->ID,
                        'post_content' => $rw['content'],
                    ]);
                    $processed++;
                } else {
                    $failed++;
                    // v3.1.0: 失败入队重试
                    $repo = new \Linked3\Classes\AutoGPT\AutoGPTTaskRepository();
                    $repo->enqueue($task['id'], [
                        'type' => 'enhance_retry',
                        'post_id' => $p->ID,
                        'reason' => $rw['message'] ?? 'rewrite failed',
                    ], gmdate('Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS));
                }
            } catch (\Exception $e) {
                $failed++;
                // v3.1.0: 异常入队重试
                $repo = new \Linked3\Classes\AutoGPT\AutoGPTTaskRepository();
                $repo->enqueue($task['id'], [
                    'type' => 'enhance_retry',
                    'post_id' => $p->ID,
                    'reason' => $e->getMessage(),
                ], gmdate('Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS));
                continue;
            }
        }

        // v0.8.0: distinguish "no work to do" (ok:true, 0 processed, 0 failed)
        // from "had candidates but every rewrite failed" (ok:false). The
        // latter advances the circuit breaker; the former does not.
        $ok = ($failed === 0) || ($processed > 0);
        $message = $processed > 0
            ? sprintf(__('已增强 %d 篇文章。', 'linked3'), $processed)
            : ($failed > 0
                ? sprintf(__('%d 篇文章增强失败。', 'linked3'), $failed)
                : __('无需增强的文章。', 'linked3'));

        return ['ok' => $ok, 'message' => $message, 'items_processed' => $processed];
    }
}
