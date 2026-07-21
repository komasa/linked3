<?php

declare(strict_types=1);
/**
 * Keyword extractor facade — chooses algorithm based on config.
 *
 * Algorithms (SEOConfig::get('keyword.algorithm')):
 *   - 'tfidf'     — TF-IDF only (fast, deterministic)
 *   - 'textrank'  — TextRank only (graph-based, slower but context-aware)
 *   - 'combined'  — TextRank rank × TF-IDF score (default-rank × tf score)
 *
 * Output: keyword → score, descending.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Keyword
 */

namespace Linked3\Classes\SEO\Keyword;

use Linked3\Classes\SEO\SEOConfig;



if (!defined('ABSPATH')) {
    exit;
}
final class KeywordExtractor
{
    /**
     * @param string $text
     * @param array  $override_opts Override config defaults.
     * @return array<string,float>
     */
    public function extract(string $text, array $override_opts = []) : mixed {
        $cfg = SEOConfig::get('keyword', []);
        $opts = array_merge([
            'algorithm'     => $cfg['algorithm'] ?? 'textrank',
            'max_keywords'  => $cfg['max_keywords'] ?? 10,
            'min_word_len'  => $cfg['min_word_len'] ?? 2,
            'stopwords_zh'  => $cfg['stopwords_zh'] ?? [],
            'stopwords_en'  => $cfg['stopwords_en'] ?? [],
        ], $override_opts);

        $algo = $opts['algorithm'];
        if ($algo === 'tfidf') {
            $scores = (new TFIDFExtractor())->extract($text, $opts);
        } elseif ($algo === 'combined') {
            $tfidf_scores = (new TFIDFExtractor())->extract($text, $opts);
            $tr_scores = (new TextRankExtractor())->extract($text, $opts);
            $scores = [];
            foreach ($tr_scores as $word => $rank) {
                $tf = $tfidf_scores[$word] ?? 0.0;
                $scores[$word] = $rank * (0.5 + $tf);
            }
            arsort($scores);
            $scores = array_slice($scores, 0, $opts['max_keywords'], true);
        } else {
            // Default: TextRank.
            $scores = (new TextRankExtractor())->extract($text, $opts);
        }
        return $scores;
    }

    /**
     * Convenience: just the keywords as a flat array (no scores).
     *
     * @param string $text
     * @param int    $limit
     * @return string[]
     */
    public function extract_keywords(string $text, int $limit = 10) : mixed     {
        $scores = $this->extract($text, ['max_keywords' => $limit]);
        return array_keys($scores);
    }
}
