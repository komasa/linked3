<?php

declare(strict_types=1);
/**
 * Linked3 Genesis Recommendation Engine v1.1.0
 *
 * G7 Track F 演化结晶 - AI辅助融合决策引擎
 * 将71种风格的选择负担降至 Top-3-5
 *
 * 8条决策规则:
 *   R1 功能驱动 (Hook/Body/Proof/CTA)
 *   R2 结构驱动 (linear/tree/network/matrix/radial)
 *   R3 场景驱动 (wondershare-diagram/general)
 *   R4 行业驱动 (medical/legal/finance/education)
 *   R5 复杂度驱动 (low/medium/high)
 *   R6 受众驱动 (beginner/designer/general)
 *   R7 平台驱动 (android/ios/web)
 *   R8 无障碍驱动 (wcag_aaa/standard)
 *
 * 9种推荐模式:
 *   F-01 auto          一键智能推荐
 *   F-02 beginner       新手友好推荐
 *   F-03 designer       设计师精选
 *   F-04 market         万兴市场优选
 *   F-05 industry       行业专家推荐
 *   F-06 accessible     无障碍优先
 *   F-07 conversion     高转化推荐
 *   F-08 complex        复杂内容推荐
 *   F-09 cross-platform 跨平台适配
 *
 * v1.1.0 修复:
 *   - BUG: require_wondershare_ready 检查 production_ready (字段名错误)
 *   - BUG: require_accessible filter 未实现 (accessible模式=auto模式)
 *   - BUG: match_function filter 未实现 (conversion模式=auto模式)
 *   - BUG: multi_platform filter 未实现 (cross-platform模式=auto模式)
 *   - BUG: include_categories 用 in_array 精确匹配, category含后缀时不命中
 *   - BUG: match_industry 仅在 industry≠general 时生效, 默认调用时失效
 *   - 修复: 6个BUG全部修复, 9模式实现差异化过滤+差异化评分
 *
 * @package Linked3\Genesis
 * @since 16.0.26
 * @version 1.1.0
 * @date 2026-06-27
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisRecommendationEngine
{
    /**
     * 风格库实例
     */
    private $styleEngine;

    /**
     * 9种推荐模式定义
     */
    private $modes = [
        'auto' => [
            'name_cn' => '一键智能推荐',
            'description' => 'AI分析内容，自动推荐Top-3',
            'top_n' => 3,
            'filters' => [],
        ],
        'beginner' => [
            'name_cn' => '新手友好推荐',
            'description' => '优先推荐低门槛风格，附带操作指引',
            'top_n' => 3,
            'filters' => ['exclude_categories' => ['科幻未来', '西方灵异', '东方灵异']],
        ],
        'designer' => [
            'name_cn' => '设计师精选',
            'description' => '推荐专业风格，支持精细调整',
            'top_n' => 5,
            'filters' => ['include_categories' => ['商业生产级信息图', '商业生产级信息图-融合技法', '商业生产级信息图-万兴垂直']],
        ],
        'market' => [
            'name_cn' => '万兴市场优选',
            'description' => '强制过滤万兴适配风格',
            'top_n' => 3,
            'filters' => ['require_wondershare_ready' => true],
        ],
        'industry' => [
            'name_cn' => '行业专家推荐',
            'description' => '按行业匹配垂直风格',
            'top_n' => 3,
            'filters' => ['match_industry' => true],
        ],
        'accessible' => [
            'name_cn' => '无障碍优先',
            'description' => '推荐高对比、大字号风格',
            'top_n' => 3,
            'filters' => ['require_accessible' => true],
        ],
        'conversion' => [
            'name_cn' => '高转化推荐',
            'description' => '推荐CTA导向风格',
            'top_n' => 3,
            'filters' => ['match_function' => 'CTA'],
        ],
        'complex' => [
            'name_cn' => '复杂内容推荐',
            'description' => '推荐融合风格',
            'top_n' => 3,
            'filters' => ['include_categories' => ['商业生产级信息图-融合技法']],
        ],
        'cross-platform' => [
            'name_cn' => '跨平台适配',
            'description' => '多平台风格组合',
            'top_n' => 5,
            'filters' => ['multi_platform' => true],
        ],
    ];

    public function __construct() {
        $this->styleEngine = new GenesisStyleEngine();
    }

    /**
     * 获取所有推荐模式定义
     *
     * @return array 模式列表
     */
    public function getModes(): array
    {
        return $this->modes;
    }

    /**
     * 主推荐入口
     *
     * @param string $content 内容描述
     * @param string $mode 推荐模式 (auto/beginner/designer/market/industry/accessible/conversion/complex/cross-platform)
     * @param string $industry 行业 (medical/legal/finance/education/general)
     * @return array 推荐结果
     */
    public function recommend(string $content, string $mode = 'auto', string $industry = 'general'): array
    {
        // 1. 提取内容特征
        $features = $this->extractFeatures($content);

        // 2. 获取模式配置
        $modeConfig = $this->modes[$mode] ?? $this->modes['auto'];

        // 3. 加载全部风格
        $allStyles = $this->loadAllStyles();

        // 4. 按模式过滤
        $filtered = $this->applyModeFilters($allStyles, $modeConfig, $features, $industry);

        // 5. 按特征匹配评分 (v1.1: 传入mode实现差异化评分)
        $scored = $this->scoreByFeatures($filtered, $features, $mode);

        // 6. 排序取Top-N
        usort($scored, function ($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });
        $top = array_slice($scored, 0, $modeConfig['top_n']);

        return [
            'mode' => $mode,
            'mode_name' => $modeConfig['name_cn'],
            'features' => $features,
            'recommendations' => $top,
            'reason' => $this->generateReason($features, $mode),
            'total_candidates' => count($filtered),
        ];
    }

    /**
     * 提取内容特征 (8条规则)
     */
    private function extractFeatures(string $content): array
    {
        return [
            'function' => $this->detectFunction($content),
            'structure' => $this->detectStructure($content),
            'scene' => $this->detectScene($content),
            'industry' => $this->detectIndustry($content),
            'complexity' => $this->detectComplexity($content),
            'audience' => $this->detectAudience($content),
            'platform' => $this->detectPlatform($content),
            'accessibility' => $this->detectAccessibility($content),
        ];
    }

    /**
     * R1: 功能检测
     */
    private function detectFunction(string $content): string
    {
        if (preg_match('/标题|吸引|hook|冲击/i', $content)) return 'Hook';
        if (preg_match('/数据|证明|背书|统计/i', $content)) return 'Proof';
        if (preg_match('/行动|转化|报名|购买|点击/i', $content)) return 'CTA';
        return 'Body';
    }

    /**
     * R2: 结构检测
     */
    private function detectStructure(string $content): string
    {
        if (preg_match('/流程|步骤|顺序/i', $content)) return 'linear';
        if (preg_match('/层级|树状|分类|组织/i', $content)) return 'tree';
        if (preg_match('/关系|网络|关联/i', $content)) return 'network';
        if (preg_match('/对比|矩阵|象限/i', $content)) return 'matrix';
        if (preg_match('/循环|迭代|闭环/i', $content)) return 'radial';
        if (preg_match('/融合|复合|多布局/i', $content)) return 'mixed';
        return 'mixed';
    }

    /**
     * R3: 场景检测
     */
    private function detectScene(string $content): string
    {
        if (preg_match('/万兴|图示|导图|亿图/i', $content)) return 'wondershare-diagram';
        return 'general';
    }

    /**
     * R4: 行业检测
     */
    private function detectIndustry(string $content): string
    {
        if (preg_match('/医疗|医院|疾病|健康|临床/i', $content)) return 'medical';
        if (preg_match('/法律|案件|诉讼|合规|法条/i', $content)) return 'legal';
        if (preg_match('/金融|财务|投资|资金|证券/i', $content)) return 'finance';
        if (preg_match('/教育|课程|教学|学习|学生/i', $content)) return 'education';
        return 'general';
    }

    /**
     * R5: 复杂度检测
     */
    private function detectComplexity(string $content): string
    {
        $len = mb_strlen($content);
        if ($len > 200 || preg_match('/复杂|体系|全景|多模块/i', $content)) return 'high';
        if ($len > 50) return 'medium';
        return 'low';
    }

    /**
     * R6: 受众检测
     */
    private function detectAudience(string $content): string
    {
        if (preg_match('/新手|入门|小白|初学/i', $content)) return 'beginner';
        if (preg_match('/设计师|专业|高端/i', $content)) return 'designer';
        return 'general';
    }

    /**
     * R7: 平台检测
     */
    private function detectPlatform(string $content): string
    {
        if (preg_match('/android|安卓|谷歌/i', $content)) return 'android';
        if (preg_match('/ios|苹果|iphone/i', $content)) return 'ios';
        if (preg_match('/网页|web|网站/i', $content)) return 'web';
        return 'general';
    }

    /**
     * R8: 无障碍检测
     */
    private function detectAccessibility(string $content): bool
    {
        return (bool) preg_match('/无障碍|适老|高对比|大字号|wcag/i', $content);
    }

    /**
     * 加载全部风格 (复用AtomIndex，避免重复扫描)
     */
    private function loadAllStyles(): array
    {
        $styles = [];

        // 优先复用 AtomIndex::getStyles() — 与既有系统保持一致
        if (class_exists('\Linked3\Classes\Genesis\GenesisAtomIndex')) {
            $idx = GenesisAtomIndex::instance();
            $raw = $idx->getStyles();
            if (isset($raw['styles']) && is_array($raw['styles'])) {
                foreach ($raw['styles'] as $sid => $sinfo) {
                    $config = GenesisStyleEngine::load($sid);
                    if (!empty($config)) {
                        $config['id'] = $sid;
                        $styles[] = $config;
                    }
                }
                return $styles;
            }
        }
    }

    /**
     * 应用模式过滤 — 委托至 Scorer
     */
    public function applyModeFilters(array $styles, array $modeConfig, array $features, string $industry): array {
        return GenesisRecommendationScorer::applyModeFilters($styles, $modeConfig, $features, $industry);
    }

    /**
     * 按特征评分 — 委托至 Scorer
     */
    private function scoreByFeatures(array $styles, array $features, string $mode = 'auto'): array {
        return GenesisRecommendationScorer::scoreByFeatures($styles, $features, $mode);
    }

    /**
     * 生成推荐理由
     */
    private function generateReason(array $features, string $mode): string {
        $reasons = ['auto' => 'auto', 'beginner' => 'beginner'];
        return $reasons[$mode] ?? 'auto';
    }
}
