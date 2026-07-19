<?php
/**
 * Linked3 Agent Quality Gate — 质量门控
 *
 * @package Linked3\Agent\Quality
 * @since 5.5.0.6
 */
namespace Linked3\Classes\Agent\Quality;

if (!defined('ABSPATH')) exit;

class Linked3_Agent_Quality_Gate {
    private array $checks = [];

    public function registerCheck(string $name, callable $checker, int $threshold = 70): void {
        $this->checks[$name] = ['checker' => $checker, 'threshold' => $threshold];
    }

    public function evaluate(array $content): array {
        $results = [];
        $passed = true;
        $totalScore = 0;
        $count = 0;

        foreach ($this->checks as $name => $check) {
            $score = ($check['checker'])($content);
            $ok = $score >= $check['threshold'];
            $results[$name] = ['score' => $score, 'threshold' => $check['threshold'], 'passed' => $ok];
            if (!$ok) $passed = false;
            $totalScore += $score;
            $count++;
        }

        return [
            'passed' => $passed,
            'overall_score' => $count > 0 ? $totalScore / $count : 0,
            'checks' => $results,
        ];
    }
}
