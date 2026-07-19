<?php

declare(strict_types=1);
/**
 * TextRank keyword extractor.
 *
 * Pure-PHP port of the classic Mihalcea-Tarau TextRank algorithm with a
 * co-occurrence window of 5 tokens. Tokens are produced via the same
 * Chinese regex as the TF-IDF extractor (so both algorithms operate on
 * the same vocabulary).
 *
 * Convergence: 30 iterations or change < 1e-4 — whichever comes first.
 * Damping factor d = 0.85 (standard).
 *
 * Complexity: O(N + E) per iteration where E is the number of unique
 * co-occurrence edges. Practical inputs (5k-token articles) converge
 * in ~10ms on a modern PHP host.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Keyword
 */

namespace Linked3\Classes\SEO\Keyword;

if (!defined('ABSPATH')) {
    exit;
}

final class TextRankExtractor
{
    const WINDOW = 5;
    const DAMPING = 0.85;
    const MAX_ITER = 30;
    const EPSILON = 1.0e-4;

    /**
     * @param string $text
     * @param array  $opts
     * @return array<string,float>
     */
    public function extract($text, array $opts = []) : mixed {
        $stop_zh = $opts['stopwords_zh'] ?? [];
        $stop_en = $opts['stopwords_en'] ?? [];
        $min_len = (int) ($opts['min_word_len'] ?? 2);
        $max_kw  = (int) ($opts['max_keywords'] ?? 10);

        $tokens = (new TFIDFExtractor())->tokenize($text);
        if (empty($tokens)) {
            return [];
        }

        // Build vocabulary (filtered).
        $vocab = [];
        $seq = [];
        foreach ($tokens as $tok) {
            $len = function_exists('mb_strlen') ? mb_strlen($tok, 'UTF-8') : strlen($tok);
            if ($len < $min_len) {
                continue;
            }
            if (in_array($tok, $stop_zh, true) || in_array(strtolower($tok), $stop_en, true)) {
                continue;
            }
            if (!isset($vocab[$tok])) {
                $vocab[$tok] = count($vocab);
            }
            $seq[] = $vocab[$tok];
        }
        $n = count($vocab);
        if ($n === 0) {
            return [];
        }
        if ($n === 1) {
            $only = array_keys($vocab);
            return [$only[0] => 1.0];
        }

        // Co-occurrence edges within window.
        $edges = []; // node → [neighbour => weight]
        $seq_len = count($seq);
        for ($i = 0; $i < $seq_len; $i++) {
            $from = $seq[$i];
            $lo = max(0, $i - self::WINDOW);
            $hi = min($seq_len - 1, $i + self::WINDOW);
            for ($j = $lo; $j <= $hi; $j++) {
                if ($j === $i) {
                    continue;
                }
                $to = $seq[$j];
                if (!isset($edges[$from][$to])) {
                    $edges[$from][$to] = 0;
                }
                $edges[$from][$to] += 1.0;
            }
        }

        // Weighted degree (out) for normalisation.
        $out_sum = array_fill(0, $n, 0.0);
        foreach ($edges as $from => $nbrs) {
            foreach ($nbrs as $to => $w) {
                $out_sum[$from] += $w;
            }
        }
        foreach ($out_sum as $i => $s) {
            if ($s <= 0) {
                $out_sum[$i] = 1.0; // avoid divide-by-zero (isolated nodes)
            }
        }

        // Iterative PageRank-style score propagation.
        $score = array_fill(0, $n, 1.0 / $n);
        for ($iter = 0; $iter < self::MAX_ITER; $iter++) {
            $next = array_fill(0, $n, (1.0 - self::DAMPING) / $n);
            foreach ($edges as $from => $nbrs) {
                $share = self::DAMPING * $score[$from] / $out_sum[$from];
                foreach ($nbrs as $to => $w) {
                    $next[$to] += $share * $w;
                }
            }
            $delta = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $delta += abs($next[$i] - $score[$i]);
            }
            $score = $next;
            if ($delta < self::EPSILON) {
                break;
            }
        }

        // Map back to keyword strings, sort, slice.
        $out = [];
        foreach ($vocab as $word => $idx) {
            $out[$word] = $score[$idx];
        }
        arsort($out);
        return array_slice($out, 0, $max_kw, true);
    }
}
