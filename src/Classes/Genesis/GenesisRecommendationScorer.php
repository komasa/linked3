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
            $score = $this->scoreBaseFeatures($style, $features);
            $score += $this->scoreModeSpecific($style, $features, $mode);
            $score += $this->scoreDifferentiation($style);

            $style['match_score'] = $score;
        }
        return $styles;
    }

    /**
     * 基础评分(所有模式共享): 行业/万兴/复杂度/无障碍/商业级.
     */
    private function scoreBaseFeatures(array $style, array $features): int
    {
        $score = 0;
        $cat = $style['category'] ?? '';
        $nameCn = $style['name_cn'] ?? '';
        $wondershare = !empty($style['wondershare_ready']);
        $commercial = !empty($style['commercial_grade']);

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

        return $score;
    }

    /**
     * v1.1: 模式专属差异化评分.
     */
    private function scoreModeSpecific(array $node, string $mode): float
    {
        return match($mode) {
            'cinematic'   => $this->scoreCinematic($node),
            'documentary' => $this->scoreDocumentary($node),
            'commercial'  => $this->scoreCommercial($node),
            'artistic'    => $this->scoreArtistic($node),
            default       => 0.0,
        };
    }

    private function scoreCinematic(array $node): float {
        $score = 0.0;
        if (!empty($node['shot']) && $node['shot'] !== '中景') $score += 0.2;
        if (!empty($node['angle']) && $node['angle'] !== '平视') $score += 0.15;
        if (!empty($node['mood']) && in_array($node['mood'], ['紧张','悲伤','激昂'], true)) $score += 0.25;
        if (!empty($node['lighting'])) $score += 0.2;
        if (!empty($node['color_grading'])) $score += 0.2;
        return $score;
    }

    private function scoreDocumentary(array $node): float {
        $score = 0.0;
        if (!empty($node['shot']) && $node['shot'] === '中景') $score += 0.3;
        if (!empty($node['angle']) && $node['angle'] === '平视') $score += 0.3;
        if (empty($node['lighting'])) $score += 0.2;
        if (!empty($node['location']) && mb_strlen($node['location']) > 3) $score += 0.2;
        return $score;
    }

    private function scoreCommercial(array $node): float {
        $score = 0.0;
        if (!empty($node['characters']) && count($node['characters']) >= 2) $score += 0.3;
        if (!empty($node['mood']) && in_array($node['mood'], ['欢快','兴奋','自信'], true)) $score += 0.3;
        if (!empty($node['brand_elements'])) $score += 0.4;
        return $score;
    }

    private function scoreArtistic(array $node): float {
        $score = 0.0;
        if (!empty($node['comp']) && in_array($node['comp'], ['三分法','对角线','黄金螺旋'], true)) $score += 0.35;
        if (!empty($node['color'])) $score += 0.3;
        if (!empty($node['mood']) && in_array($node['mood'], ['梦幻','忧郁','宁静'], true)) $score += 0.35;
        return $score;
    }

    /**
     * industry 模式: 行业匹配权重翻倍.
     */
    private function scoreIndustryMode(array $style, array $features): int
    {
        $score = 0;
        if ($features['industry'] !== 'general') {
            $cat = $style['category'] ?? '';
            $nameCn = $style['name_cn'] ?? '';
            if (stripos($cat, $features['industry']) !== false) $score += 30; // 翻倍
            if (stripos($nameCn, $features['industry']) !== false) $score += 20; // 翻倍
        }
        return $score;
    }

    /**
     * accessible 模式: 高对比+信息图类 权重翻倍.
     */
    private function scoreAccessibleMode(string $cat, string $nameCn): int
    {
        $score = 0;
        if (stripos($nameCn, '高对比') !== false || stripos($nameCn, '大字号') !== false) $score += 30; // 翻倍
        if (stripos($cat, '信息图') !== false || stripos($cat, '企业扁平') !== false) $score += 25;
        if (stripos($cat, '技术蓝图') !== false) $score += 20;
        return $score;
    }

    /**
     * conversion 模式: 真人摄影+商业级 权重翻倍(CTA导向).
     */
    private function scoreConversionMode(string $cat, bool $commercial, array $genres): int
    {
        $score = 0;
        if (stripos($cat, '真人') !== false || stripos($cat, '摄影') !== false) $score += 35;
        if ($commercial) $score += 20;
        // suitable_genres含营销/转化
        foreach ($genres as $g) {
            if (stripos($g, '营销') !== false || stripos($g, '转化') !== false) $score += 15;
        }
        return $score;
    }

    /**
     * complex 模式: 融合技法+多模块 权重放大.
     */
    private function scoreComplexMode(string $cat, array $genres): int
    {
        $score = 0;
        if (stripos($cat, '融合') !== false) $score += 35;
        if (stripos($cat, '信息图') !== false) $score += 15;
        // suitable_genres多 = 适配复杂场景
        if (count($genres) >= 2) $score += 15;
        return $score;
    }

    /**
     * cross-platform 模式: G5/G6生产级+多场景 权重放大.
     */
    private function scoreCrossPlatformMode(string $g7, bool $wondershare, array $genres): int
    {
        $score = 0;
        if (stripos($g7, 'G5') === 0 || stripos($g7, 'G6') === 0) $score += 30;
        if ($wondershare) $score += 15;
        if (count($genres) >= 2) $score += 20;
        return $score;
    }

    /**
     * v1.5: 区分度评分 — 避免大量同分, 确保Top排序有区分.
     */
    private function scoreDifferentiation(array $style): int
    {
        $score = 0;
        $nameCn = $style['name_cn'] ?? '';

        // usage_code序号: F01>F02>...>F57, 序号越小优先级越高(生产级默认)
        $usageCode = $style['usage_code'] ?? '';
        if ($usageCode) {
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

        return $score;
    }

    public function applyModeFilters(array $styles, array $modeConfig, array $features, string $industry): array
    {
        $filtered = $styles;
        $filters = $modeConfig['filters'];

        // 排除分类 (v1.1: 用stripos模糊匹配)
        if (!empty($filters['exclude_categories'])) {
            $filtered = $this->filterExcludeCategories($filtered, $filters['exclude_categories']);
        }

        // 包含分类 (v1.1: BUG修复 — in_array→stripos, 解决category含后缀不命中)
        if (!empty($filters['include_categories'])) {
            $filtered = $this->filterIncludeCategories($filtered, $filters['include_categories']);
        }

        // 万兴就绪过滤 (v1.1: BUG修复 — production_ready→wondershare_ready)
        if (!empty($filters['require_wondershare_ready'])) {
            $filtered = array_filter($filtered, function ($s) {
                return !empty($s['wondershare_ready']);
            });
        }

        // 无障碍优先过滤 (v1.1: 新增实现 — 之前完全缺失)
        if (!empty($filters['require_accessible'])) {
            $filtered = $this->filterAccessible($filtered);
        }

        // 功能匹配过滤 (v1.1: 新增实现 — 之前完全缺失)
        if (!empty($filters['match_function'])) {
            $filtered = $this->filterMatchFunction($filtered, $filters['match_function']);
        }

        // 多平台过滤 (v1.1: 新增实现 — 之前完全缺失)
        if (!empty($filters['multi_platform'])) {
            $filtered = $this->filterMultiPlatform($filtered);
        }

        // 行业匹配 (v1.1: BUG修复 — 即使industry=general也用features['industry']匹配)
        if (!empty($filters['match_industry'])) {
            $filtered = $this->filterMatchIndustry($filtered, $features, $industry);
        }

        return $filtered;
    }

    /**
     * 排除分类过滤 (v1.1: 用stripos模糊匹配).
     */
    private function filterExcludeCategories(array $filtered, array $excludeCategories): array
    {
        $result = [];
        foreach ($filtered as $s) {
            $cat = $s['category'] ?? '';
            $excluded = false;
            foreach ($excludeCategories as $ex) {
                if (stripos($cat, $ex) !== false) {
                    $excluded = true;
                    break;
                }
            }
            if (!$excluded) {
                $result[] = $s;
            }
        }
        return $result;
    }

    /**
     * 包含分类过滤 (v1.1: BUG修复 — in_array→stripos).
     */
    private function filterIncludeCategories(array $filtered, array $includeCategories): array
    {
        $result = [];
        foreach ($filtered as $s) {
            $cat = $s['category'] ?? '';
            $included = false;
            foreach ($includeCategories as $inc) {
                if (stripos($cat, $inc) !== false) {
                    $included = true;
                    break;
                }
            }
            if ($included) {
                $result[] = $s;
            }
        }
        return $result;
    }

    /**
     * 无障碍优先过滤 (v1.1: 新增实现).
     * 策略: 优先信息图/企业扁平类(高对比+标准字号), 排除艺术插画类(低对比风险)
     */
    private function filterAccessible(array $filtered): array
    {
        return array_filter($filtered, function ($s) {
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

    /**
     * 功能匹配过滤 (v1.1: 新增实现).
     * 策略: CTA导向 → 优先真人摄影/海报卡片类(强视觉冲击+行动召唤)
     */
    private function filterMatchFunction(array $filtered, string $targetFunc): array
    {
        if ($targetFunc !== 'CTA') {
            return $filtered;
        }
        $result = [];
        foreach ($filtered as $s) {
            $cat = $s['category'] ?? '';
            $genres = $s['suitable_genres'] ?? [];
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
        }
        return $result;
    }

    /**
     * 多平台过滤 (v1.1: 新增实现).
     * 策略: g7_track以G5/G6开头(生产级, 多平台适配) 或 suitable_genres数量>=2
     */
    private function filterMultiPlatform(array $filtered): array
    {
        return array_filter($filtered, function ($s) {
            $g7 = $s['g7_track'] ?? '';
            $genres = $s['suitable_genres'] ?? [];
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

    /**
     * 行业匹配过滤 (v1.1: BUG修复 — 即使industry=general也用features['industry']匹配).
     */
    private function filterMatchIndustry(array $filtered, array $features, string $industry): array
    {
        // v1.1: 优先用features提取的行业, 兜底用参数industry
        $effectiveIndustry = $features['industry'] ?? $industry;
        if ($effectiveIndustry === 'general') {
            return $filtered;
        }
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
        return $result;
    }

}
