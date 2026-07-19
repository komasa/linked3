<?php

declare(strict_types=1);
/**
 * Cognitive Operating System — Skill 库 (v20.0)
 *
 * Skill 是 COS 固化的认知能力 — 每次成功演化后结晶为 Skill,
 * 后续可直接调用已固化的规则, 跳过重新学习的过程。
 *
 * Skill 有适应度 (fitness) 和使用次数 (usage_count), 越用越强。
 *
 * @package Linked3\CognitiveOS\Storage
 * @since   20.0
 */

namespace Linked3\Classes\CognitiveOS\Storage;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class COSSkillLibrary
 *
 * Skill 库 — 存储固化的认知能力。
 */
class COSSkillLibrary
{
    const OPTION_KEY = 'linked3_cos_skills';

    /**
     * 保存或更新一个 Skill。
     *
     * @param string $name    Skill 名称
     * @param array  $payload Skill 数据 { rules, domain, fitness, ... }
     * @return bool
     */
    public static function save(string $name, array $payload): bool
    {
        $skills = self::all();
        $payload['name']        = $name;
        $payload['updated_at']  = current_time('mysql');
        $payload['usage_count'] = ($skills[$name]['usage_count'] ?? 0);
        $payload['fitness']    = $payload['fitness'] ?? ($skills[$name]['fitness'] ?? 5.0);
        $skills[$name] = $payload;
        return update_option(self::OPTION_KEY, $skills, false);
    }

    /**
     * 获取单个 Skill。
     *
     * @param string $name
     * @return array|null
     */
    public static function get(string $name): ?array
    {
        $skills = self::all();
        return $skills[$name] ?? null;
    }

    /**
     * 获取所有 Skill。
     *
     * @return array<string, array>
     */
    public static function all(): array
    {
        $skills = get_option(self::OPTION_KEY, []);
        return is_array($skills) ? $skills : [];
    }

    /**
     * 删除一个 Skill。
     *
     * @param string $name
     * @return bool
     */
    public static function delete(string $name): bool
    {
        $skills = self::all();
        unset($skills[$name]);
        return update_option(self::OPTION_KEY, $skills, false);
    }

    /**
     * 增加使用次数 + 更新适应度。
     *
     * @param string $name
     * @param float   $delta  适应度增量 (正数=增强, 负数=减弱)
     * @return bool
     */
    public static function touch(string $name, float $delta = 0.0): bool
    {
        $skills = self::all();
        if (!isset($skills[$name])) {
            return false;
        }
        $skills[$name]['usage_count'] = (int) ($skills[$name]['usage_count'] ?? 0) + 1;
        $skills[$name]['fitness']     = max(0.0, min(10.0, (float) ($skills[$name]['fitness'] ?? 5.0) + $delta));
        $skills[$name]['last_used']   = current_time('mysql');
        return update_option(self::OPTION_KEY, $skills, false);
    }

    /**
     * 增加使用次数 (应用 Skill 时调用)。
     *
     * @param string $name
     * @return bool
     */
    public static function increment_usage(string $name): bool
    {
        $skills = self::all();
        if (!isset($skills[$name])) {
            return false;
        }
        $skills[$name]['usage_count'] = (int) ($skills[$name]['usage_count'] ?? 0) + 1;
        $skills[$name]['last_used']   = current_time('mysql');
        return update_option(self::OPTION_KEY, $skills, false);
    }

    /**
     * 按适应度排序获取 Top-K Skill。
     *
     * @param int $top_k
     * @return array
     */
    public static function top_k(int $top_k = 10): array
    {
        $skills = self::all();
        uasort($skills, function ($a, $b) {
            return (float) ($b['fitness'] ?? 0) <=> (float) ($a['fitness'] ?? 0);
        });
        return array_slice($skills, 0, $top_k, true);
    }

    /**
     * 按领域匹配 Skill。
     *
     * @param string $domain
     * @param int    $top_k
     * @return array
     */
    public static function match_domain(string $domain, int $top_k = 5): array
    {
        $skills = self::all();
        $matched = [];
        foreach ($skills as $name => $s) {
            if (stripos($name, $domain) !== false || stripos($s['domain'] ?? '', $domain) !== false) {
                $matched[$name] = $s;
            }
        }
        uasort($matched, function ($a, $b) {
            return (float) ($b['fitness'] ?? 0) <=> (float) ($a['fitness'] ?? 0);
        });
        return array_slice($matched, 0, $top_k, true);
    }

    /**
     * 统计信息。
     *
     * @return array { count, avg_fitness, total_usage }
     */
    public static function stats(): array
    {
        $skills = self::all();
        $count  = count($skills);
        $total_fitness = 0.0;
        $total_usage   = 0;
        foreach ($skills as $s) {
            $total_fitness += (float) ($s['fitness'] ?? 0);
            $total_usage   += (int) ($s['usage_count'] ?? 0);
        }
        return [
            'count'       => $count,
            'avg_fitness'  => $count > 0 ? round($total_fitness / $count, 2) : 0,
            'total_usage'  => $total_usage,
        ];
    }
}
