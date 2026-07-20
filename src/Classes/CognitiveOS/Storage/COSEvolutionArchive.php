<?php

declare(strict_types=1);
/**
 * Cognitive Operating System — 演化归档系统 (v20.0)
 *
 * 存储每代演化的完整快照。当需要回溯到某个代际时,
 * 可以从归档中读取该代的完整状态, 作为新变异的基线。
 *
 * 每代归档包含: 方案种群、评分矩阵、结晶结果、公理/SLA 验证记录。
 *
 * @package Linked3\CognitiveOS\Storage
 * @since   20.0
 */

namespace Linked3\Classes\CognitiveOS\Storage;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class COSEvolutionArchive
 *
 * 演化归档系统 — 每代快照与回溯。
 */
class COSEvolutionArchive
{
    const OPTION_KEY = 'linked3_cos_evolution_archive';

    /**
     * 保存单代快照。
     *
     * @param array $snapshot {
     *     generation: string (G1/G2/G3),
     *     timestamp: string,
     *     problem: string,
     *     variants: array,
     *     survivors: array,
     *     mvp: array|null,
     *     axiom_results: array,
     *     sla_results: array
     * }
     * @return string 快照 ID
     */
    public static function save_generation(array $snapshot): string
    {
        $archive = self::all();
        $id = 'gen_' . substr(md5(uniqid('', true)), 0, 12);
        $snapshot['id']        = $id;
        $snapshot['saved_at']  = current_time('mysql');
        $archive[$id] = $snapshot;
        // 保留最近 50 代
        if (count($archive) > 50) {
            $archive = array_slice($archive, -50, 50, true);
        }
        update_option(self::OPTION_KEY, $archive, false);
        return $id;
    }

    /**
     * 获取单个快照。
     *
     * @param string $id
     * @return array|null
     */
    public static function get(string $id): ?array
    {
        $archive = self::all();
        return $archive[$id] ?? null;
    }

    /**
     * 获取所有快照。
     *
     * @return array<string, array>
     */
    public static function all(): array
    {
        $archive = get_option(self::OPTION_KEY, []);
        return is_array($archive) ? $archive : [];
    }

    /**
     * 获取最近 N 代快照。
     *
     * @param int $n
     * @return array
     */
    public static function recent(int $n = 10): array
    {
        $archive = self::all();
        // 按 saved_at 降序
        uasort($archive, function ($a, $b) {
            return strcmp($b['saved_at'] ?? '', $a['saved_at'] ?? '');
        });
        return array_slice($archive, 0, $n, true);
    }

    /**
     * 删除单个快照。
     *
     * @param string $id
     * @return bool
     */
    public static function delete(string $id): bool
    {
        $archive = self::all();
        unset($archive[$id]);
        return update_option(self::OPTION_KEY, $archive, false);
    }

    /**
     * 统计信息。
     *
     * @return array { count, by_generation, success_rate }
     */
    public static function stats(): array
    {
        $archive = self::all();
        $by_gen = ['G1' => 0, 'G2' => 0, 'G3' => 0];
        $success = 0;
        foreach ($archive as $snap) {
            $gen = $snap['generation'] ?? '';
            if (isset($by_gen[$gen])) {
                $by_gen[$gen]++;
            }
            if (!empty($snap['mvp'])) {
                $success++;
            }
        }
        $total = count($archive);
        return [
            'count'        => $total,
            'by_generation' => $by_gen,
            'success_rate'  => $total > 0 ? round($success / $total, 4) : 0,
        ];
    }
}
