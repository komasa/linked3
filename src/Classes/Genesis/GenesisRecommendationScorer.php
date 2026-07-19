<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
/**
 * GenesisRecommendationScorer — G8 extraction.
 * @since 27.13.0
 */
class GenesisRecommendationScorer
{
    public function scoreByFeatures(array $styles, array $features, string $mode = 'auto'): array
    {
        foreach ($styles as &$style) {
            $score = 0;
            $cat = $style['category'] ?? '';
            $nameCn = $style['name_cn'] ?? '';
            $g7 = $style['g7_track'] ?? '';
            $genres = $style['suitable_genres'] ?? [];
            $wondershare = !empty($style['wondershare_ready']);
            $prodReady = !empty($style['production_ready']);
            $commercial = !empty($style['commercial_grade']);

            // ===== 基础评分(所有模式共享) =====
            // 行业匹配
            if ($features['industry'] !== 'general') {
                if (stripos($cat, $features['industry']) !== false) $score += 30;
                if (stripos($nameCn, $features['industry']) !== false) $score += 20;
            }
            // 万兴场景
            if ($features['scene'] === 'wondershare-diagram') {
                if (stripos($cat, '万兴') !== false) $score += 25;
                if ($wondershare) $score += 15;
            }
            // 复杂度匹配
            if ($features['complexity'] === 'high') {
                if (stripos($cat, '融合') !== false) $score += 20;
            }
            // 无障碍匹配
            if ($features['accessibility']) {
                if (stripos($nameCn, '高对比') !== false || stripos($nameCn, '大字号') !== false) $score += 30;
            }
            // 商业级
            if ($commercial) $score += 10;

            // ===== v1.1: 模式专属差异化评分 =====
            switch ($mode) {
                case 'beginner':
                    // 新手友好: 生产就绪+商业级 权重放大(降低试错成本)
                    if ($prodReady) $score += 25;
                    if ($commercial) $score += 15;
                    // 信息图类: 新手易上手
                    if (stripos($cat, '信息图') !== false || stripos($cat, '企业扁平') !== false) $score += 10;
                    break;

                case 'designer':
                    // 设计师精选: 信息图类+融合技法 权重放大
                    if (stripos($cat, '信息图') !== false) $score += 20;
                    if (stripos($cat, '融合') !== false) $score += 25;
                    if (stripos($cat, '艺术插画') !== false) $score += 15;
                    break;

                case 'market':
                    // 万兴市场: wondershare_ready权重放大(修复后字段正确)
                    if ($wondershare) $score += 35;
                    if ($prodReady) $score += 15;
                    if (stripos($cat, '万兴') !== false) $score += 20;
                    break;

                case 'industry':
                    // 行业专家: 行业匹配权重翻倍
                    if ($features['industry'] !== 'general') {
                        if (stripos($cat, $features['industry']) !== false) $score += 30; // 翻倍
                        if (stripos($nameCn, $features['industry']) !== false) $score += 20; // 翻倍
                    }
                    break;

                case 'accessible':
                    // 无障碍优先: 高对比+信息图类 权重翻倍
                    if (stripos($nameCn, '高对比') !== false || stripos($nameCn, '大字号') !== false) $score += 30; // 翻倍
                    if (stripos($cat, '信息图') !== false || stripos($cat, '企业扁平') !== false) $score += 25;
                    if (stripos($cat, '技术蓝图') !== false) $score += 20;
                    break;

                case 'conversion':
                    // 高转化: 真人摄影+商业级 权重翻倍(CTA导向)
                    if (stripos($cat, '真人') !== false || stripos($cat, '摄影') !== false) $score += 35;
                    if ($commercial) $score += 20;
                    // suitable_genres含营销/转化
                    foreach ($genres as $g) {
                        if (stripos($g, '营销') !== false || stripos($g, '转化') !== false) $score += 15;
                    }
                    break;

                case 'complex':
                    // 复杂内容: 融合技法+多模块 权重放大
                    if (stripos($cat, '融合') !== false) $score += 35;
                    if (stripos($cat, '信息图') !== false) $score += 15;
                    // suitable_genres多 = 适配复杂场景
                    if (count($genres) >= 2) $score += 15;
                    break;

                case 'cross-platform':
                    // 跨平台: G5/G6生产级+多场景 权重放大
                    if (stripos($g7, 'G5') === 0 || stripos($g7, 'G6') === 0) $score += 30;
                    if ($wondershare) $score += 15;
                    if (count($genres) >= 2) $score += 20;
                    break;

                case 'auto':
                default:
                    // auto: 均衡评分, 不额外加权(基础分已足够)
                    break;
            }

            // ===== v1.5: 区分度评分 — 避免大量同分, 确保Top排序有区分 =====
            // 问题: 通用内容时所有G5规范融合类都得10分(commercial), Top3全是30分无区分
            // 方案: 增加基于usage_code序号/g7_track/风格名哈希的微小区分分(0-9分)
            $usageCode = $style['usage_code'] ?? '';
            if ($usageCode) {
                // usage_code序号: F01>F02>...>F57, 序号越小优先级越高(生产级默认)
                $seqNum = intval(substr($usageCode, 1));
                if ($seqNum > 0 && $seqNum <= 99) {
                    $score += max(0, 10 - intval($seqNum / 6)); // F01-F06=+9, F07-F12=+8, ...
                }
            }
            // g7_track: G3-MVP > G4 > G5 > G6 > G7 (生产成熟度排序)
            $g7 = $style['g7_track'] ?? '';
            if ($g7) {
                if (strpos($g7, 'G3') === 0) $score += 5;
                elseif (strpos($g7, 'G4') === 0) $score += 4;
                elseif (strpos($g7, 'G5') === 0) $score += 3;
                elseif (strpos($g7, 'G6') === 0) $score += 2;
                elseif (strpos($g7, 'G7') === 0) $score += 1;
            }
            // 风格名哈希: 确保同分类下也有微小差异(0-4分), 避免完全同分
            $nameHash = crc32($nameCn) % 5;
            $score += $nameHash;

            $style['match_score'] = $score;
        }
        return $styles;
    }

    public function applyModeFilters(array $styles, array $modeConfig, array $features, string $industry): array
    {
        $filtered = $styles;
        $filters = $modeConfig['filters'];

        // 排除分类 (v1.1: 用stripos模糊匹配)
        if (!empty($filters['exclude_categories'])) {
            $result = [];
            foreach ($filtered as $s) {
                $cat = $s['category'] ?? '';
                $excluded = false;
                foreach ($filters['exclude_categories'] as $ex) {
                    if (stripos($cat, $ex) !== false) {
                        $excluded = true;
                        break;
                    }
                }
                if (!$excluded) {
                    $result[] = $s;
                }
            }
            $filtered = $result;
        }

        // 包含分类 (v1.1: BUG修复 — in_array→stripos, 解决category含后缀不命中)
        if (!empty($filters['include_categories'])) {
            $result = [];
            foreach ($filtered as $s) {
                $cat = $s['category'] ?? '';
                $included = false;
                foreach ($filters['include_categories'] as $inc) {
                    if (stripos($cat, $inc) !== false) {
                        $included = true;
                        break;
                    }
                }
                if ($included) {
                    $result[] = $s;
                }
            }
            $filtered = $result;
        }

        // 万兴就绪过滤 (v1.1: BUG修复 — production_ready→wondershare_ready)
        if (!empty($filters['require_wondershare_ready'])) {
            $filtered = array_filter($filtered, function ($s) {
                return !empty($s['wondershare_ready']);
            });
        }

        // 无障碍优先过滤 (v1.1: 新增实现 — 之前完全缺失)
        // 策略: 优先信息图/企业扁平类(高对比+标准字号), 排除艺术插画类(低对比风险)
        if (!empty($filters['require_accessible'])) {
            $filtered = array_filter($filtered, function ($s) {
                $cat = $s['category'] ?? '';
                $nameCn = $s['name_cn'] ?? '';
                // 信息图/企业扁平/技术蓝图类: 高对比、标准字号, 无障碍友好
                if (stripos($cat, '信息图') !== false
                    || stripos($cat, '企业扁平') !== false
                    || stripos($cat, '技术蓝图') !== false) {
                    return true;
                }
                // 显式标注高对比/大字号的
                if (stripos($nameCn, '高对比') !== false || stripos($nameCn, '大字号') !== false) {
                    return true;
                }
                return false;
            });
        }

        // 功能匹配过滤 (v1.1: 新增实现 — 之前完全缺失)
        // 策略: CTA导向 → 优先真人摄影/海报卡片类(强视觉冲击+行动召唤)
        if (!empty($filters['match_function'])) {
            $targetFunc = $filters['match_function'];
            $result = [];
            foreach ($filtered as $s) {
                $cat = $s['category'] ?? '';
                $genres = $s['suitable_genres'] ?? [];
                $scenes = $s['scene_whitelist'] ?? [];
                if ($targetFunc === 'CTA') {
                    $matched = false;
                    // 真人摄影/海报类: 强CTA导向
                    if (stripos($cat, '真人') !== false || stripos($cat, '摄影') !== false) {
                        $matched = true;
                    }
                    // suitable_genres含营销/转化/行动
                    if (!$matched) {
                        foreach ($genres as $g) {
                            if (stripos($g, '营销') !== false || stripos($g, '转化') !== false
                                || stripos($g, '行动') !== false || stripos($g, 'CTA') !== false) {
                                $matched = true;
                                break;
                            }
                        }
                    }
                    if ($matched) {
                        $result[] = $s;
                    }
                } else {
                    $result[] = $s;
                }
            }
            $filtered = $result;
        }

        // 多平台过滤 (v1.1: 新增实现 — 之前完全缺失)
        // 策略: g7_track以G5/G6开头(生产级, 多平台适配) 或 suitable_genres数量>=2
        if (!empty($filters['multi_platform'])) {
            $filtered = array_filter($filtered, function ($s) {
                $g7 = $s['g7_track'] ?? '';
                $genres = $s['suitable_genres'] ?? [];
                $scenes = $s['scene_whitelist'] ?? [];
                // G5/G6系列: 生产级, 多平台适配
                if (stripos($g7, 'G5') === 0 || stripos($g7, 'G6') === 0) {
                    return true;
                }
                // suitable_genres >= 2: 多场景适配
                if (count($genres) >= 2) {
                    return true;
                }
                return false;
            });
        }

        // 行业匹配 (v1.1: BUG修复 — 即使industry=general也用features['industry']匹配)
        if (!empty($filters['match_industry'])) {
            // v1.1: 优先用features提取的行业, 兜底用参数industry
            $effectiveIndustry = $features['industry'] ?? $industry;
            if ($effectiveIndustry !== 'general') {
                $result = [];
                foreach ($filtered as $s) {
                    $cat = $s['category'] ?? '';
                    $ind = $s['industry'] ?? '';
                    $nameCn = $s['name_cn'] ?? '';
                    // 显式industry字段匹配
                    if (stripos($ind, $effectiveIndustry) !== false
                        || stripos($cat, $effectiveIndustry) !== false
                        || stripos($nameCn, $effectiveIndustry) !== false) {
                        $result[] = $s;
                    }
                }
                $filtered = $result;
            }
        }

        return $filtered;
    }

}
