<?php
/**
 * Linked3 Diagram Endpoint & Followup System — v6.3.0
 *
 * 9个原子版本:
 *   v6.3.0.1: 6种Endpoint注册表 (Mountain/Flywheel/Spiral/Compound/Ecosystem/Transformation)
 *   v6.3.0.2: Endpoint选择决策树
 *   v6.3.0.3: 6种追问类型 (实战/决策/诊断/预测/追问/觉察)
 *   v6.3.0.4: 4种Footer类型 (价值观/方法论/原则/公式)
 *   v6.3.0.5: Footer×追问兼容性矩阵
 *   v6.3.0.6: 6种关系编码 (→/~>/<->/━/┄)
 *   v6.3.0.7: 6级认知标注 ([R][U][A][An][E][C])
 *   v6.3.0.8: 4档信息密度 (极简/标准/深度/极致)
 *   v6.3.0.9: 第9维度视觉频率 ([HF][MF][LF])
 *
 * @package Linked3\Diagram
 * @since 6.3.0
 */
namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

// =================================================================
// v6.3.0.1: 6种Endpoint注册表
// =================================================================

class Linked3_Diagram_Endpoint_Registry {
    private static ?Linked3_Diagram_Endpoint_Registry $instance = null;
    private array $endpoints = [];

    public static function instance(): Linked3_Diagram_Endpoint_Registry {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->register('Mountain path', [
            'name_zh' => '山峰路径', 'name_en' => 'Mountain path',
            'meaning' => '修行成长 (挑战→攀登→登顶)',
            'visual' => 'Mountain path with 4 milestones',
            'milestones' => 4,
            'emotion' => '成就感',
            'suitable_for' => '个人成长/技能进阶/团队发展',
        ]);
        $this->register('Flywheel', [
            'name_zh' => '飞轮', 'name_en' => 'Flywheel',
            'meaning' => '商业飞轮 (4要素互相加速)',
            'visual' => '4 gears circular acceleration',
            'milestones' => 4,
            'emotion' => '闭环感',
            'suitable_for' => '商业模式/增长引擎/正循环',
        ]);
        $this->register('Growth spiral', [
            'name_zh' => '增长螺旋', 'name_en' => 'Growth spiral',
            'meaning' => '迭代进化 (螺旋上升)',
            'visual' => 'Spiral with 4 milestones rising',
            'milestones' => 4,
            'emotion' => '进化感',
            'suitable_for' => '产品迭代/技术演进/认知升级',
        ]);
        $this->register('Compound curve', [
            'name_zh' => '复利曲线', 'name_en' => 'Compound curve',
            'meaning' => '复利积累 (S曲线拐点)',
            'visual' => 'S-curve with inflection point and 4 milestones',
            'milestones' => 4,
            'emotion' => '积累感',
            'suitable_for' => '投资/知识积累/技能复利',
        ]);
        $this->register('Ecosystem loop', [
            'name_zh' => '生态循环', 'name_en' => 'Ecosystem loop',
            'meaning' => '生态共生 (多节点闭环)',
            'visual' => 'Multi-node ecosystem with 4 milestones',
            'milestones' => 4,
            'emotion' => '共生感',
            'suitable_for' => '生态体系/产业链/平台经济',
        ]);
        $this->register('Transformation path', [
            'name_zh' => '转型路径', 'name_en' => 'Transformation path',
            'meaning' => '转型蜕变 (茧→蝶)',
            'visual' => 'Cocoon->butterfly with 3 stage markers',
            'milestones' => 3,
            'emotion' => '蜕变感',
            'suitable_for' => '企业转型/个人蜕变/品牌升级',
        ]);
    }

    public function register(string $id, array $config): void {
        $this->endpoints[$id] = array_merge(['id' => $id], $config);
    }

    public function get(string $id): ?array { return $this->endpoints[$id] ?? null; }
    public function all(): array { return $this->endpoints; }

    public function getVisual(string $id): string {
        return $this->endpoints[$id]['visual'] ?? '4 gears circular acceleration';
    }
}

// =================================================================
// v6.3.0.2: Endpoint选择决策树
// =================================================================

class Linked3_Diagram_Endpoint_DecisionTree {
    private array $rules = [
        '成长' => 'Mountain path',
        '修行' => 'Mountain path',
        '飞轮' => 'Flywheel',
        '正循环' => 'Flywheel',
        '迭代' => 'Growth spiral',
        '进化' => 'Growth spiral',
        '复利' => 'Compound curve',
        '积累' => 'Compound curve',
        '生态' => 'Ecosystem loop',
        '共生' => 'Ecosystem loop',
        '转型' => 'Transformation path',
        '蜕变' => 'Transformation path',
    ];

    public function select(string $context): array {
        foreach ($this->rules as $keyword => $typeId) {
            if (strpos($context, $keyword) !== false) {
                $ep = Linked3_Diagram_Endpoint_Registry::instance()->get($typeId);
                return ['selected' => $typeId, 'endpoint' => $ep];
            }
        }
        $default = Linked3_Diagram_Endpoint_Registry::instance()->get('Flywheel');
        return ['selected' => 'Flywheel', 'endpoint' => $default];
    }
}

// =================================================================
// v6.3.0.3: 6种追问类型
// =================================================================

class Linked3_Diagram_Followup_Registry {
    private static ?Linked3_Diagram_Followup_Registry $instance = null;
    private array $followups = [];

    public static function instance(): Linked3_Diagram_Followup_Registry {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->register('E1', ['name_zh' => '实战型', 'name_en' => 'Practical', 'question_template' => '你属于哪种情形?立即行动', 'suitable_for' => '行动指南']);
        $this->register('E2', ['name_zh' => '决策型', 'name_en' => 'Decision', 'question_template' => 'A还是B?你的选择是', 'suitable_for' => '二选一场景']);
        $this->register('E3', ['name_zh' => '诊断型', 'name_en' => 'Diagnostic', 'question_template' => '你的症状是哪种?对号入座', 'suitable_for' => '问题诊断']);
        $this->register('E4', ['name_zh' => '预测型', 'name_en' => 'Predictive', 'question_template' => '未来3年会怎样?提前准备', 'suitable_for' => '趋势预测']);
        $this->register('E5', ['name_zh' => '追问型', 'name_en' => 'Probing', 'question_template' => '为什么?深挖底层逻辑', 'suitable_for' => '深度思考']);
        $this->register('E6', ['name_zh' => '觉察型', 'name_en' => 'Reflective', 'question_template' => '你有什么感受?共鸣', 'suitable_for' => '情感共鸣']);
    }

    public function register(string $id, array $config): void {
        $this->followups[$id] = array_merge(['id' => $id], $config);
    }
    public function get(string $id): ?array { return $this->followups[$id] ?? null; }
    public function all(): array { return $this->followups; }
}

// =================================================================
// v6.3.0.4: 4种Footer类型
// =================================================================

class Linked3_Diagram_Footer_Registry {
    private static ?Linked3_Diagram_Footer_Registry $instance = null;
    private array $footers = [];

    public static function instance(): Linked3_Diagram_Footer_Registry {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->register('values', ['name_zh' => '价值观型', 'template' => '{品牌}·{价值观}', 'suitable_for' => '品牌传达']);
        $this->register('method', ['name_zh' => '方法论型', 'template' => '{品牌}·{方法论}', 'suitable_for' => '方法论输出']);
        $this->register('principle', ['name_zh' => '原则型', 'template' => '{品牌}·{原则}', 'suitable_for' => '原则宣导']);
        $this->register('formula', ['name_zh' => '公式型', 'template' => '{品牌}·{公式}', 'suitable_for' => '公式总结']);
    }

    public function register(string $id, array $config): void {
        $this->footers[$id] = array_merge(['id' => $id], $config);
    }
    public function get(string $id): ?array { return $this->footers[$id] ?? null; }
    public function all(): array { return $this->footers; }

    public function render(string $id, array $vars): string {
        $footer = $this->get($id);
        if (!$footer) return '';
        $text = $footer['template'];
        foreach ($vars as $k => $v) {
            $text = str_replace('{' . $k . '}', $v, $text);
        }
        return $text;
    }
}

// =================================================================
// v6.3.0.5: Footer×追问兼容性矩阵
// =================================================================

class Linked3_Diagram_FooterFollowup_Matrix {
    private array $matrix = [
        'values'    => ['E1' => true, 'E2' => true, 'E3' => true, 'E4' => false, 'E5' => false, 'E6' => true],
        'method'    => ['E1' => true, 'E2' => true, 'E3' => true, 'E4' => true, 'E5' => true, 'E6' => false],
        'principle' => ['E1' => false, 'E2' => true, 'E3' => true, 'E4' => false, 'E5' => true, 'E6' => true],
        'formula'   => ['E1' => true, 'E2' => false, 'E3' => false, 'E4' => true, 'E5' => true, 'E6' => false],
    ];

    public function isCompatible(string $footerType, string $followupType): bool {
        return $this->matrix[$footerType][$followupType] ?? false;
    }

    public function getCompatibleFollowups(string $footerType): array {
        return array_keys(array_filter($this->matrix[$footerType] ?? [], fn($v) => $v));
    }

    public function getMatrix(): array { return $this->matrix; }
}

// =================================================================
// v6.3.0.6: 6种关系编码
// =================================================================

class Linked3_Diagram_Relationship_Encoder {
    private array $codes = [
        'support'    => ['symbol' => '→', 'name_zh' => '支撑', 'meaning' => 'A支撑B'],
        'influence'  => ['symbol' => '~>', 'name_zh' => '影响', 'meaning' => 'A影响B'],
        'causal'     => ['symbol' => '<->', 'name_zh' => '因果', 'meaning' => 'A与B互为因果'],
        'strong'     => ['symbol' => '━', 'name_zh' => '强连接', 'meaning' => 'A强连接B'],
        'weak'       => ['symbol' => '┄', 'name_zh' => '弱连接', 'meaning' => 'A弱连接B'],
        'feedback'   => ['symbol' => '~>', 'name_zh' => '反馈', 'meaning' => 'A反馈B'],
    ];

    public function encode(string $type, string $from, string $to, string $desc = ''): array {
        $code = $this->codes[$type] ?? $this->codes['support'];
        return [
            'from' => $from,
            'to' => $to,
            'code' => $code['symbol'],
            'type' => $type,
            'name' => $code['name_zh'],
            'desc' => $desc,
            'rendered' => "{$from} {$code['symbol']} {$to}" . ($desc ? ": {$desc}" : ''),
        ];
    }

    public function getCodes(): array { return $this->codes; }
}

// =================================================================
// v6.3.0.7: 6级认知标注
// =================================================================

class Linked3_Diagram_Cognitive_6Level {
    private array $levels = [
        'R'  => ['name_zh' => '记忆', 'name_en' => 'Remember', 'desc' => '识别/回忆'],
        'U'  => ['name_zh' => '理解', 'name_en' => 'Understand', 'desc' => '解释/概括'],
        'A'  => ['name_zh' => '应用', 'name_en' => 'Apply', 'desc' => '执行/实施'],
        'An' => ['name_zh' => '分析', 'name_en' => 'Analyze', 'desc' => '分解/比较'],
        'E'  => ['name_zh' => '评价', 'name_en' => 'Evaluate', 'desc' => '判断/批判'],
        'C'  => ['name_zh' => '创造', 'name_en' => 'Create', 'desc' => '设计/生成'],
    ];

    private array $bandDefaults = [
        1 => 'R',  // Band1: 基础底座 → 记忆
        2 => 'A',  // Band2: 执行层 → 应用
        3 => 'An', // Band3: 框架层 → 分析
        4 => 'E',  // Band4: 结果层 → 评价
    ];

    public function getLevel(string $code): ?array {
        return $this->levels[$code] ?? null;
    }

    public function getDefaultForBand(int $bandNum): string {
        return $this->bandDefaults[$bandNum] ?? 'R';
    }

    public function getLevels(): array { return $this->levels; }
    public function getBandDefaults(): array { return $this->bandDefaults; }
}

// =================================================================
// v6.3.0.8: 4档信息密度
// =================================================================

class Linked3_Diagram_Density_4Level {
    private array $levels = [
        'minimal' => ['name_zh' => '极简版', 'modules_per_band' => 1, 'sub_topics_per_module' => 2, 'details_per_sub' => 2, 'char_target' => 2000],
        'standard' => ['name_zh' => '标准版', 'modules_per_band' => 1, 'sub_topics_per_module' => 3, 'details_per_sub' => 2, 'char_target' => 3000],
        'deep' => ['name_zh' => '深度版', 'modules_per_band' => 2, 'sub_topics_per_module' => 3, 'details_per_sub' => 3, 'char_target' => 4000],
        'extreme' => ['name_zh' => '极致版', 'modules_per_band' => 3, 'sub_topics_per_module' => 4, 'details_per_sub' => 3, 'char_target' => 4500],
    ];

    public function getLevel(string $id): ?array {
        return $this->levels[$id] ?? null;
    }
    public function getLevels(): array { return $this->levels; }
    public function getDefault(): array { return $this->levels['deep']; }
}

// =================================================================
// v6.3.0.9: 第9维度视觉频率
// =================================================================

class Linked3_Diagram_Visual_Frequency {
    private array $frequencies = [
        'HF' => ['name_zh' => '高频', 'name_en' => 'High Frequency', 'desc' => '快速切换/动画密集', 'fps' => '24fps', 'suitable_for' => '动感/紧张/科技'],
        'MF' => ['name_zh' => '中频', 'name_en' => 'Medium Frequency', 'desc' => '正常节奏/适中切换', 'fps' => '12fps', 'suitable_for' => '教学/讲解/展示'],
        'LF' => ['name_zh' => '低频', 'name_en' => 'Low Frequency', 'desc' => '静态/慢速/定格', 'fps' => '6fps', 'suitable_for' => '冥想/总结/品牌'],
    ];

    public function getFrequency(string $code): ?array {
        return $this->frequencies[$code] ?? null;
    }

    public function selectByMood(string $mood): string {
        if (preg_match('/动感|紧张|科技|快/', $mood)) return 'HF';
        if (preg_match('/冥想|总结|品牌|慢/', $mood)) return 'LF';
        return 'MF';
    }

    public function getFrequencies(): array { return $this->frequencies; }
}
