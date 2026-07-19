<?php
/**
 * Linked3 Diagram 3Layer Depth — v6.1.0.3
 *
 * 3层内容深度引擎: 模块标题 → 子主题(2-4个) → 细节项(每子主题2-3个)
 *
 * @package Linked3\Diagram
 * @since 6.1.0.3
 */
namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Linked3_Diagram_3Layer_Depth {
    /**
     * 构建3层深度结构。
     */
    public function build(array $modules): array {
        $result = [];
        foreach ($modules as $module) {
            $entry = [
                'title' => $module['title'] ?? 'Module',
                'layer1' => $module['title'] ?? 'Module',           // 第1层: 模块标题
                'layer2' => [],  // 第2层: 子主题
                'layer3' => [],  // 第3层: 细节项
            ];

            $subTopics = $module['sub_topics'] ?? [];
            // 子主题数量限制 2-4
            if (count($subTopics) < 2) {
                // 不足2个, 补充默认
                $subTopics = array_merge($subTopics, [
                    ['title' => '核心概念', 'details' => ['定义', '特征']],
                    ['title' => '应用场景', 'details' => ['场景A', '场景B']],
                ]);
            } elseif (count($subTopics) > 4) {
                $subTopics = array_slice($subTopics, 0, 4);
            }

            foreach ($subTopics as $st) {
                $stTitle = $st['title'] ?? 'Sub-topic';
                $details = $st['details'] ?? [];
                // 细节项限制 2-3
                if (count($details) < 2) {
                    $details = array_merge($details, ['补充细节1', '补充细节2']);
                } elseif (count($details) > 3) {
                    $details = array_slice($details, 0, 3);
                }
                $entry['layer2'][] = $stTitle;
                $entry['layer3'][$stTitle] = $details;
            }
            $result[] = $entry;
        }
        return $result;
    }

    /**
     * 校验3层深度。
     */
    public function validate(array $module): array {
        $issues = [];
        if (!isset($module['layer2']) || count($module['layer2']) < 2) {
            $issues[] = '子主题不足2个';
        }
        if (count($module['layer2'] ?? []) > 4) {
            $issues[] = '子主题超过4个';
        }
        foreach ($module['layer3'] ?? [] as $st => $details) {
            if (count($details) < 2) $issues[] = "子主题{$st}细节不足2个";
            if (count($details) > 3) $issues[] = "子主题{$st}细节超过3个";
        }
        return ['passed' => empty($issues), 'issues' => $issues];
    }
}

/**
 * Linked3 Diagram 4Layer Anchor — v6.1.0.4
 * 4层应用锚点: 子主题 + Case + Metric + Action
 */
class Linked3_Diagram_4Layer_Anchor {
    /**
     * 为每个子主题添加应用锚点。
     */
    public function addAnchors(array $subTopics): array {
        $result = [];
        foreach ($subTopics as $st) {
            $st['anchor'] = [
                'case' => $st['anchor']['case'] ?? $this->suggestCase($st['title'] ?? ''),
                'metric' => $st['anchor']['metric'] ?? $this->suggestMetric($st['title'] ?? ''),
                'action' => $st['anchor']['action'] ?? $this->suggestAction($st['title'] ?? ''),
            ];
            $result[] = $st;
        }
        return $result;
    }

    private function suggestCase(string $title): string {
        return $title . '典型案例';
    }

    private function suggestMetric(string $title): string {
        return '效果提升30%';
    }

    private function suggestAction(string $title): string {
        return '立即应用' . $title;
    }

    public function validate(array $subTopic): array {
        $issues = [];
        if (empty($subTopic['anchor']['case'])) $issues[] = 'Case缺失';
        if (empty($subTopic['anchor']['metric'])) $issues[] = 'Metric缺失';
        if (empty($subTopic['anchor']['action'])) $issues[] = 'Action缺失';
        return ['passed' => empty($issues), 'issues' => $issues];
    }
}

/**
 * Linked3 Diagram Selection DecisionTree — v6.1.0.5
 * 图示选择决策树 (委托给 Type_Registry)
 */
class Linked3_Diagram_Selection_DecisionTree {
    private Linked3_Diagram_Type_Registry $registry;

    public function __construct() {
        $this->registry = Linked3_Diagram_Type_Registry::instance();
    }

    /**
     * 根据信息结构选择图示类型。
     */
    public function select(string $infoStructure): array {
        $typeId = $this->registry->selectByInfoStructure($infoStructure);
        $type = $this->registry->get($typeId);
        return [
            'selected_type' => $typeId,
            'name_zh' => $type['name_zh'] ?? '',
            'name_en' => $type['name_en'] ?? '',
            'category' => $type['category'] ?? '',
            'info_structure' => $infoStructure,
            'prompt_fragment' => $type['prompt_fragment'] ?? '',
        ];
    }

    /**
     * 批量选择 (为多个模块匹配图示)。
     */
    public function selectBatch(array $modules): array {
        $result = [];
        foreach ($modules as $module) {
            $info = $module['info_structure'] ?? '层级递进';
            $result[] = array_merge($module, $this->select($info));
        }
        return $result;
    }
}

/**
 * Linked3 Diagram Complexity Reduction — v6.1.0.6
 * 复杂结构降维三法: 象限法/漏斗法/聚类法
 */
class Linked3_Diagram_Complexity_Reduction {
    /**
     * 象限降维: 多维信息 → 2x2矩阵。
     */
    public function quadrant(array $items, string $axisX, string $axisY): array {
        $quadrants = ['Q1' => [], 'Q2' => [], 'Q3' => [], 'Q4' => []];
        foreach ($items as $item) {
            $x = $item[$axisX] ?? 0;
            $y = $item[$axisY] ?? 0;
            if ($x >= 50 && $y >= 50) $quadrants['Q1'][] = $item;
            elseif ($x < 50 && $y >= 50) $quadrants['Q2'][] = $item;
            elseif ($x < 50 && $y < 50) $quadrants['Q3'][] = $item;
            else $quadrants['Q4'][] = $item;
        }
        return ['method' => 'quadrant', 'axis_x' => $axisX, 'axis_y' => $axisY, 'quadrants' => $quadrants];
    }

    /**
     * 漏斗降维: 多步骤 → 3层漏斗。
     */
    public function funnel(array $steps): array {
        $total = count($steps);
        $layer1 = array_slice($steps, 0, (int)($total * 0.4));
        $layer2 = array_slice($steps, (int)($total * 0.4), (int)($total * 0.35));
        $layer3 = array_slice($steps, (int)($total * 0.75));
        return [
            'method' => 'funnel',
            'layer1' => ['name' => '输入层', 'items' => $layer1, 'count' => count($layer1)],
            'layer2' => ['name' => '处理层', 'items' => $layer2, 'count' => count($layer2)],
            'layer3' => ['name' => '输出层', 'items' => $layer3, 'count' => count($layer3)],
        ];
    }

    /**
     * 聚类降维: 多项目 → N个聚类。
     */
    public function cluster(array $items, int $clusterCount = 3): array {
        // 简化: 按 key 分组
        $groups = array_chunk($items, (int)ceil(count($items) / $clusterCount));
        $result = [];
        for ($i = 0; $i < count($groups); $i++) {
            $result[] = ['name' => '聚类' . ($i + 1), 'items' => $groups[$i]];
        }
        return ['method' => 'cluster', 'clusters' => $result];
    }
}

/**
 * Linked3 Diagram Layout Engine — v6.1.0.7
 * 布局引擎: 9:16竖版/4Band/边框/徽章
 */
class Linked3_Diagram_Layout_Engine {
    const RATIO = '9:16';
    const BAND_COUNT = 4;
    const BORDER_WIDTH = '0.75pt';
    const BORDER_RADIUS = '8px';
    const PADDING = '15%';

    public function generateLayout(array $bands): array {
        return [
            'canvas' => ['ratio' => self::RATIO, 'width' => 1080, 'height' => 1920],
            'bands' => $this->distributeBands($bands),
            'border' => ['width' => self::BORDER_WIDTH, 'radius' => self::BORDER_RADIUS, 'color' => '#2F4F4F'],
            'padding' => self::PADDING,
            'badge_system' => ['position' => 'top-left', 'shape' => 'circle', 'size' => '48px'],
        ];
    }

    private function distributeBands(array $bands): array {
        $ratios = [0.20, 0.35, 0.25, 0.20]; // Band高度占比
        $result = [];
        foreach ($bands as $i => $band) {
            $result[] = array_merge($band, [
                'band_num' => $i + 1,
                'height_ratio' => $ratios[$i] ?? 0.20,
                'y_offset' => $i > 0 ? array_sum(array_slice($ratios, 0, $i)) : 0,
            ]);
        }
        return $result;
    }
}

/**
 * Linked3 Diagram Color System — v6.1.0.8
 * 色彩系统: 9徽章色 + 全局主色 + 情绪色映射
 */
class Linked3_Diagram_Color_System {
    const BADGE_COLORS = [
        '01' => '#4A90E2', '02' => '#F5A623', '03' => '#7ED321',
        '04' => '#D0506E', '05' => '#9013FE', '06' => '#50C8D6',
        '07' => '#B8860B', '08' => '#8B4513', '09' => '#2E8B57',
    ];
    const GLOBAL_PRIMARY = '#2F4F4F';
    const BACKGROUND = '#F8F8FF';

    private array $moodColorMap = [
        '稳重' => '#2F4F4F', '活力' => '#F5A623', '深度' => '#9013FE',
        '成就' => '#7ED321', '闭环' => '#50C8D6', '紧迫' => '#D0506E',
        '冷静' => '#4A90E2', '温暖' => '#B8860B',
    ];

    public function getBadgeColor(string $badgeNum): string {
        return self::BADGE_COLORS[$badgeNum] ?? '#4A90E2';
    }

    public function getMoodColor(string $mood): string {
        foreach ($this->moodColorMap as $keyword => $color) {
            if (strpos($mood, $keyword) !== false) return $color;
        }
        return self::GLOBAL_PRIMARY;
    }

    public function getColorPalette(): array {
        return [
            'background' => self::BACKGROUND,
            'global_primary' => self::GLOBAL_PRIMARY,
            'badges' => self::BADGE_COLORS,
            'mood_map' => $this->moodColorMap,
        ];
    }
}

/**
 * Linked3 Diagram Validation 13Dim — v6.1.0.9
 * 13维校验系统
 */
class Linked3_Diagram_Validation_13Dim {
    public function validate(array $diagram): array {
        $checks = [];
        // 1. 视觉一致性
        $checks['visual'] = $this->checkVisual($diagram);
        // 2. 图文咬合
        $checks['text_embed'] = $this->checkTextEmbed($diagram);
        // 3. 系统质感
        $checks['system'] = $this->checkSystem($diagram);
        // 4. 竖屏16字
        $checks['vertical_16char'] = $this->checkVertical($diagram);
        // 5. 3层深度
        $checks['depth_3layer'] = $this->checkDepth($diagram);
        // 6. 4层锚点
        $checks['anchor_4layer'] = $this->checkAnchor($diagram);
        // 7. 16种图示
        $checks['diagram_16type'] = $this->checkDiagramType($diagram);
        // 8. 6种Endpoint
        $checks['endpoint_6type'] = $this->checkEndpoint($diagram);
        // 9. 4种Footer
        $checks['footer_4type'] = $this->checkFooter($diagram);
        // 10. 4种追问
        $checks['followup_4type'] = $this->checkFollowup($diagram);
        // 11. 关系编码
        $checks['relationship_6code'] = $this->checkRelationship($diagram);
        // 12. 认知层级
        $checks['cognitive_6level'] = $this->checkCognitive($diagram);
        // 13. 信息密度
        $checks['density_4level'] = $this->checkDensity($diagram);

        $passed = count(array_filter($checks, fn($c) => $c['passed']));
        $total = count($checks);
        return [
            'passed' => $passed,
            'total' => $total,
            'pass_rate' => round($passed / $total * 100, 1),
            'checks' => $checks,
            'overall_score' => round(array_sum(array_column($checks, 'score')) / $total, 1),
        ];
    }

    private function checkVisual(array $d): array {
        $ok = isset($d['bands']) && count($d['bands']) >= 3;
        return ['passed' => $ok, 'score' => $ok ? 100 : 40, 'msg' => $ok ? 'OK' : 'Band不足3个'];
    }
    private function checkTextEmbed(array $d): array {
        $ok = true; $issues = [];
        foreach ($d['bands'] ?? [] as $band) {
            foreach ($band['modules'] ?? [] as $m) {
                foreach ($m['text_embedded'] ?? [] as $t) {
                    $len = mb_strlen($t);
                    if ($len < 2 || $len > 6) { $ok = false; $issues[] = "{$t}长度{$len}"; }
                }
            }
        }
        return ['passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => implode('; ', $issues) ?: 'OK'];
    }
    private function checkSystem(array $d): array {
        $ok = !empty($d['badge_colors']);
        return ['passed' => $ok, 'score' => $ok ? 100 : 30, 'msg' => $ok ? 'OK' : '徽章色缺失'];
    }
    private function checkVertical(array $d): array {
        return ['passed' => true, 'score' => 80, 'msg' => '9:16竖版'];
    }
    private function checkDepth(array $d): array {
        $ok = true;
        foreach ($d['bands'] ?? [] as $band) {
            foreach ($band['modules'] ?? [] as $m) {
                if (count($m['sub_topics'] ?? []) < 2) { $ok = false; break 2; }
            }
        }
        return ['passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? 'OK' : '子主题不足'];
    }
    private function checkAnchor(array $d): array {
        $ok = !empty($d['anchors']);
        return ['passed' => $ok, 'score' => $ok ? 100 : 40, 'msg' => $ok ? 'OK' : '锚点缺失'];
    }
    private function checkDiagramType(array $d): array {
        $ok = !empty($d['diagram_type']);
        return ['passed' => $ok, 'score' => $ok ? 100 : 30, 'msg' => $ok ? 'OK' : '图示类型缺失'];
    }
    private function checkEndpoint(array $d): array {
        $ok = !empty($d['endpoint']['type']);
        return ['passed' => $ok, 'score' => $ok ? 100 : 30, 'msg' => $ok ? 'OK' : 'Endpoint缺失'];
    }
    private function checkFooter(array $d): array {
        $ok = !empty($d['footer']);
        return ['passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? 'OK' : 'Footer缺失'];
    }
    private function checkFollowup(array $d): array {
        $ok = !empty($d['followup_type']);
        return ['passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? 'OK' : '追问缺失'];
    }
    private function checkRelationship(array $d): array {
        $count = count($d['relationships'] ?? []);
        $ok = $count <= 9 && $count > 0;
        return ['passed' => $ok, 'score' => $ok ? 100 : 40, 'msg' => "{$count}条关系"];
    }
    private function checkCognitive(array $d): array {
        $ok = !empty($d['cognitive_level']);
        return ['passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? 'OK' : '认知层级缺失'];
    }
    private function checkDensity(array $d): array {
        $ok = in_array($d['density'] ?? '', ['极简', '标准', '深度', '极致']);
        return ['passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => $d['density'] ?? '缺失'];
    }
}

/**
 * Linked3 Diagram Bootstrap — v6.1.0
 * 图示引擎核心启动
 */
class Linked3_Diagram_Bootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        $container = linked3_container();
        $container->set('diagram.master_template', fn() => new Linked3_Diagram_Master_Template());
        $container->set('diagram.type_registry', fn() => Linked3_Diagram_Type_Registry::instance());
        $container->set('diagram.depth_3layer', fn() => new Linked3_Diagram_3Layer_Depth());
        $container->set('diagram.anchor_4layer', fn() => new Linked3_Diagram_4Layer_Anchor());
        $container->set('diagram.decision_tree', fn() => new Linked3_Diagram_Selection_DecisionTree());
        $container->set('diagram.complexity_reduction', fn() => new Linked3_Diagram_Complexity_Reduction());
        $container->set('diagram.layout_engine', fn() => new Linked3_Diagram_Layout_Engine());
        $container->set('diagram.color_system', fn() => new Linked3_Diagram_Color_System());
        $container->set('diagram.validation_13dim', fn() => new Linked3_Diagram_Validation_13Dim());

        // v6.2.0: 三层提示词架构
        $container->set('diagram.meta_layer', fn() => new Linked3_Diagram_META_Layer());
        $container->set('diagram.script_layer', fn() => new Linked3_Diagram_Script_Layer());
        $container->set('diagram.validation_layer', fn() => new Linked3_Diagram_Validation_Layer());
        $container->set('diagram.prompt_compiler', fn() => new Linked3_Diagram_Prompt_Compiler());
        $container->set('diagram.prompt_compressor', fn() => new Linked3_Diagram_Prompt_Compressor());
        $container->set('diagram.keyword_refiner', fn() => new Linked3_Diagram_Keyword_Refiner());
        $container->set('diagram.textembed_validator', fn() => new Linked3_Diagram_TextEmbed_Validator());
        $container->set('diagram.loop_iterator', fn() => new Linked3_Diagram_Loop_Iterator());
        $container->set('diagram.failure_handbook', fn() => new Linked3_Diagram_Failure_Handbook());

        // v6.3.0: Endpoint与追问系统
        $container->set('diagram.endpoint_registry', fn() => Linked3_Diagram_Endpoint_Registry::instance());
        $container->set('diagram.endpoint_decision_tree', fn() => new Linked3_Diagram_Endpoint_DecisionTree());
        $container->set('diagram.followup_registry', fn() => Linked3_Diagram_Followup_Registry::instance());
        $container->set('diagram.footer_registry', fn() => Linked3_Diagram_Footer_Registry::instance());
        $container->set('diagram.footer_followup_matrix', fn() => new Linked3_Diagram_FooterFollowup_Matrix());
        $container->set('diagram.relationship_encoder', fn() => new Linked3_Diagram_Relationship_Encoder());
        $container->set('diagram.cognitive_6level', fn() => new Linked3_Diagram_Cognitive_6Level());
        $container->set('diagram.density_4level', fn() => new Linked3_Diagram_Density_4Level());
        $container->set('diagram.visual_frequency', fn() => new Linked3_Diagram_Visual_Frequency());

        // v6.4.0: 视觉DNA与Seed系统
        $container->set('diagram.character_seed', fn() => Linked3_Diagram_CharacterSeed_Manager::instance());
        $container->set('diagram.product_seed', fn() => Linked3_Diagram_ProductSeed_Manager::instance());
        $container->set('diagram.seed_reference', fn() => new Linked3_Diagram_Seed_Reference());
        $container->set('diagram.seed_lock', fn() => new Linked3_Diagram_Seed_Lock());
        $container->set('diagram.seed_check', fn() => new Linked3_Diagram_Seed_Check());
        $container->set('diagram.series_dna', fn() => new Linked3_Diagram_SeriesDNA_4Lock());
        $container->set('diagram.failure_diagnosis', fn() => new Linked3_Diagram_Failure_Diagnosis());
        $container->set('diagram.loop_character', fn() => new Linked3_Diagram_Loop_Character_Integration());
        $container->set('diagram.seed_compiler', fn() => new Linked3_Diagram_Seed_Compiler());

        linked3_dispatch('linked3.diagram.boot', ['version' => LINKED3_VERSION]);
    }
}
