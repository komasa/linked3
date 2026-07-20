<?php

declare(strict_types=1);
/**
 * GenesisEvaluator — extracted from GenesisSeedLibrary.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Genesis

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisEvaluator {
    private int $threshold;

    public function __construct(int $threshold = 28) {
        $this->threshold = $threshold;
    }

    public function scoreVariant(array $ir): array {
        $score = 0;
        $feedback = [];

        $subWords = explode(',', $ir['subject'] ?? '');
        $subCount = count($subWords);
        if ($subCount >= 3 && $subCount <= 12) {
            $score += 8;
        } else {
            $score += 3;
            $feedback[] = "主体描述词数异常 ({$subCount}词)";
        }

        $env = strtolower($ir['environment'] ?? '');
        $light = strtolower($ir['camera'] ?? '');
        $hasConflict = false;
        if (strpos($env, 'rain') !== false && strpos($light, 'bright') !== false) {
            $score += 1;
            $feedback[] = "雨天与明亮光线冲突";
            $hasConflict = true;
        }
        if (strpos($env, 'dark') !== false && strpos($light, 'natural') !== false) {
            $score += 2;
            $feedback[] = "暗场景与自然光冲突";
            $hasConflict = true;
        }
        if (!$hasConflict) $score += 9;

        if (!empty($ir['negative']) || !empty($ir['style_negative'])) {
            $score += 5;
        } else {
            $feedback[] = "缺少负向提示词";
        }

        $totalLen = strlen($ir['subject'] ?? '') + strlen($ir['environment'] ?? '');
        if ($totalLen < 400) {
            $score += 5;
        } elseif ($totalLen < 600) {
            $score += 3;
            $feedback[] = "提示词较长";
        } else {
            $score += 1;
            $feedback[] = "提示词过长可能被截断";
        }

        $styleMod = strtolower($ir['style_mod'] ?? '');
        if (preg_match('/ink-wash|manga|cinematic|cyberpunk|photography|painting|gothic|ukiyo|steampunk|zen/i', $styleMod)) {
            $score += 5;
        } else {
            $score += 1;
            $feedback[] = "缺少风格锚点关键词";
        }

        if (!empty($ir['colors'])) {
            $score += 5;
        } else {
            $score += 1;
            $feedback[] = "缺少色彩方案";
        }

        return ['score' => $score, 'feedback' => $feedback, 'max_score' => 40];
    }

    public function cull(array $variantsData): array {
        $survivors = [];
        $culled = [];
        foreach ($variantsData as $v) {
            if (($v['score'] ?? 0) >= $this->threshold) {
                $survivors[] = $v;
            } else {
                $culled[] = $v;
            }
        }
        return ['survivors' => $survivors, 'culled' => $culled];
    }
}
