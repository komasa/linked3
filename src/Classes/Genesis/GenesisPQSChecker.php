<?php

declare(strict_types=1);
/**
 * Linked3_Genesis_PQSChecker — extracted from GenesisAtomIndex.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Genesis

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisPQSChecker {
    public function check(array $panel): array {
        $prompt = $panel['prompt_en'] ?? '';
        $chars = $panel['characters'] ?? [];
        $styleConfig = $panel['style_config'] ?? [];

        $checks = [];

        $ok = preg_match('/ink-wash|dark manga|chinese|cinematic|photography|portrait|fashion|documentary|hanfu|cyberpunk|steampunk|gothic|ukiyoe|zen|minimal/i', $prompt);
        $checks['PQS01'] = ['item' => '风格锚点', 'passed' => (bool)$ok, 'score' => $ok ? 100 : 40, 'msg' => $ok ? '风格关键词存在' : '缺少风格锚点'];

        $ok = count($chars) > 0;
        $checks['PQS02'] = ['item' => '角色完整性', 'passed' => $ok, 'score' => $ok ? 100 : 40, 'msg' => count($chars) . '个角色'];

        $ok = strpos($prompt, 'color palette') !== false;
        $checks['PQS03'] = ['item' => '色彩配比', 'passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? '色彩配置完整' : '色彩缺失'];

        $ok = preg_match('/rule of thirds|diagonal|center|symmetric|leading lines/i', $prompt);
        $checks['PQS04'] = ['item' => '构图有效性', 'passed' => (bool)$ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? '构图有效' : '构图缺失'];

        $ok = !empty($panel['scene']);
        $checks['PQS05'] = ['item' => '场景匹配', 'passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? '场景匹配' : '场景缺失'];

        $ok = strpos($prompt, 'symbols:') !== false;
        $checks['PQS06'] = ['item' => '符号准确性', 'passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? '符号存在' : '符号缺失'];

        $metaCount = substr_count($prompt, ',');
        $ok = $metaCount >= 10;
        $checks['PQS07'] = ['item' => 'META标签数', 'passed' => $ok, 'score' => $ok ? 100 : 60, 'msg' => $metaCount . '个标签'];

        $ok = preg_match('/atmosphere/i', $prompt);
        $checks['PQS08'] = ['item' => '氛围一致性', 'passed' => (bool)$ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? '氛围存在' : '氛围缺失'];

        $len = strlen($prompt);
        $ok = $len >= 200 && $len <= 2000;
        $checks['PQS09'] = ['item' => 'Prompt长度', 'passed' => $ok, 'score' => $ok ? 100 : 70, 'msg' => $len . '字符'];

        $ok = preg_match('/exorcist|dragon|demon|mansion|rainy|night/i', $prompt);
        $checks['PQS10'] = ['item' => '关键词覆盖', 'passed' => (bool)$ok, 'score' => $ok ? 100 : 60, 'msg' => $ok ? '核心关键词覆盖' : '关键词不足'];

        $ok = !preg_match('/[\x{4e00}-\x{9fff}]{5,}/u', $prompt); // 无5个以上连续中文
        $checks['PQS11'] = ['item' => '平台兼容性', 'passed' => $ok, 'score' => $ok ? 100 : 60, 'msg' => $ok ? '兼容' : '中文过多'];

        $ok = count($chars) >= 1 && count($chars) <= 4;
        $checks['PQS12'] = ['item' => '角色数合理', 'passed' => $ok, 'score' => $ok ? 100 : 70, 'msg' => count($chars) . '个角色'];

        $colorCount = substr_count($prompt, '#');
        $ok = $colorCount >= 3 && $colorCount <= 10;
        $checks['PQS13'] = ['item' => '色相数合理', 'passed' => $ok, 'score' => $ok ? 100 : 60, 'msg' => $colorCount . '个色相'];

        $words = explode(' ', strtolower($prompt));
        $dups = count($words) - count(array_unique($words));
        $ok = $dups < 10;
        $checks['PQS14'] = ['item' => '无冗余重复', 'passed' => $ok, 'score' => $ok ? 100 : 70, 'msg' => $dups . '个重复'];

        $passedCount = count(array_filter($checks, fn($c) => $c['passed']));
        $totalScore = array_sum(array_column($checks, 'score')) / count($checks);

        return [
            'checks' => $checks,
            'passed' => $passedCount,
            'total' => count($checks),
            'pass_rate' => count($checks) > 0 ? round($passedCount / count($checks) * 100, 1) : 0,
            'pqs_score' => round($totalScore, 1),
        ];
    }
}
