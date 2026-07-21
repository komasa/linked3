<?php

declare(strict_types=1);
/**
 * COS Reporter — 统计与仪表盘查询 (v20.0)
 *
 * 从 COSEngine 拆分而来, 专职负责只读统计查询:
 *   - skill_stats():        Skill 库统计
 *   - archive_stats():      演化归档统计
 *   - recent_evolutions():  最近的演化快照
 *   - top_skills():         Top-K Skill
 *   - dashboard_overview(): COS 系统总览仪表盘
 *
 * COSEngine 保留 evolve / lever 逻辑, 本类专注于数据查询。
 *
 * @package Linked3\Classes\CognitiveOS
 * @since   20.0
 */

namespace Linked3\Classes\CognitiveOS;

use Linked3\Classes\CognitiveOS\Storage\COSSkillLibrary;
use Linked3\Classes\CognitiveOS\Storage\COSEvolutionArchive;

if (!defined('ABSPATH')) {
    exit;
}

class COSReporter
{
    /**
     * 获取 Skill 库统计。
     *
     * @return array
     */
    public function skill_stats(): array
    {
        return COSSkillLibrary::stats();
    }

    /**
     * 获取演化归档统计。
     *
     * @return array
     */
    public function archive_stats(): array
    {
        return COSEvolutionArchive::stats();
    }

    /**
     * 获取最近的演化快照。
     *
     * @param int $n
     * @return array
     */
    public function recent_evolutions(int $n = 10): array
    {
        return COSEvolutionArchive::recent($n);
    }

    /**
     * 获取 Top-K Skill。
     *
     * @param int $top_k
     * @return array
     */
    public function top_skills(int $top_k = 10): array
    {
        return COSSkillLibrary::top_k($top_k);
    }

    /**
     * COS 系统总览 — 用于 UI 仪表盘。
     *
     * @return array
     */
    public function dashboard_overview(): array
    {
        $skill_stats   = $this->skill_stats();
        $archive_stats = $this->archive_stats();

        return [
            'version'        => '20.0',
            'axioms'         => ['信息熵减', '系统降维'],
            'departments'    => ['FP', 'EX', 'C', 'O', 'A'],
            'generations'    => ['G1', 'G2', 'G3'],
            'skill_count'    => $skill_stats['count'],
            'avg_fitness'    => $skill_stats['avg_fitness'],
            'total_skill_usage' => $skill_stats['total_usage'],
            'evolution_count'   => $archive_stats['count'],
            'evolution_success_rate' => $archive_stats['success_rate'],
            'by_generation'  => $archive_stats['by_generation'],
        ];
    }
}
