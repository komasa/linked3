<?php

declare(strict_types=1);
/**
 * TF-IDF keyword extractor.
 *
 * Lightweight corpus-free TF-IDF: term frequencies are computed against
 * the input document; IDF values are approximated using a static
 * "common Chinese / English stopwords" prior — a document-specific term
 * appearing once is treated as more informative than one appearing 50
 * times in a long generic corpus. This is intentionally a heuristic
 * rather than a rigorous IDF over a true corpus (which would require a
 * background-corpus table that we deliberately don't ship).
 *
 * The output is keyword → score (descending). Tokens are split using
 * the Chinese regex inherited from v2.9.6 (continuum of CJK + ASCII
 * letters/digits).
 *
 * @package Linked3
 * @subpackage Classes\SEO\Keyword
 */

namespace Linked3\Classes\SEO\Keyword;

if (!defined('ABSPATH')) {
    exit;
}

final class TFIDFExtractor
{
    /**
     * @param string $text
     * @param array  $opts {
     *     @type string[] $stopwords_zh
     *     @type string[] $stopwords_en
     *     @type int      $min_word_len  Default 2.
     *     @type int      $max_keywords  Default 10.
     * }
     * @return array<string,float> keyword → score
     */
    public function extract(string $text, array $opts = []) : mixed {
        $stop_zh = $opts['stopwords_zh'] ?? [];
        $stop_en = $opts['stopwords_en'] ?? [];
        $min_len = (int) ($opts['min_word_len'] ?? 2);
        $max_kw  = (int) ($opts['max_keywords'] ?? 10);

        $tokens = $this->tokenize($text);
        if (empty($tokens)) {
            return [];
        }

        $freq = [];
        $total = 0;
        foreach ($tokens as $tok) {
            $len = function_exists('mb_strlen') ? mb_strlen($tok, 'UTF-8') : strlen($tok);
            if ($len < $min_len) {
                continue;
            }
            if (in_array($tok, $stop_zh, true) || in_array(strtolower($tok), $stop_en, true)) {
                continue;
            }
            $freq[$tok] = ($freq[$tok] ?? 0) + 1;
            $total++;
        }
        if ($total === 0) {
            return [];
        }

        // TF normalised by total tokens.
        $tf = [];
        foreach ($freq as $tok => $count) {
            $tf[$tok] = $count / $total;
        }

        // Pseudo-IDF: penalise tokens appearing in many positions of the
        // document (rare terms get higher weight). 1 + log(N / df) where
        // df is approximated by count (more occurrences = more "common").
        $scores = [];
        foreach ($freq as $tok => $count) {
            $idf = 1.0 + log(max(1, $total / $count));
            $scores[$tok] = $tf[$tok] * $idf;
        }
        arsort($scores);
        return array_slice($scores, 0, $max_kw, true);
    }

    /**
     * Tokenise Chinese + English text. Mirrors v2.9.6's extract_keywords
     * regex: contiguous CJK chars form one token; contiguous ASCII
     * letters/digits form another.
     *
     * @param string $text
     * @return string[]
     */
    public function tokenize(string $text) : mixed     {
        $text = wp_strip_all_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        // CJK Unified Ideographs + ASCII letters/digits.
        preg_match_all('/[\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}]+|[A-Za-z][A-Za-z0-9]{1,}/u', $text, $m);
        return $m[0] ?? [];
    }
}
