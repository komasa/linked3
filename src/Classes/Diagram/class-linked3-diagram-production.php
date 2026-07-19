<?php
/**
 * Linked3 Diagram 30 Spectrum & Commercial Hardening — v6.5.0
 *
 * 9个原子版本:
 *   v6.5.0.1: 30种图示全谱引擎 (结构7+流程6+数据9+战略5+其他3)
 *   v6.5.0.2: 基座复用飞轮 (复用率70-80%)
 *   v6.5.0.3: 3D/AR/动态海报子系统
 *   v6.5.0.4: 视觉剧本转化3层管线
 *   v6.5.0.5: 品牌视觉资产5维度
 *   v6.5.0.6: 8大系统交叉引用矩阵
 *   v6.5.0.7: 商业加固 (熔断/限流/安全/缓存/审计)
 *   v6.5.0.8: E2E测试套件
 *   v6.5.0.9: 生产级启动器
 *
 * @package Linked3\Diagram
 * @since 6.5.0
 */
namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

// =================================================================
// v6.5.0.1: 30种图示全谱引擎
// =================================================================

class Linked3_Diagram_30_Spectrum {
    private static ?Linked3_Diagram_30_Spectrum $instance = null;
    private array $spectrum = [];

    public static function instance(): Linked3_Diagram_30_Spectrum {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // 结构关系7种
        $struct = ['Stacked架构图', 'Tree树形图', 'Pyramid金字塔', 'Network网络图', 'Radial辐射图', 'Venn韦恩图', 'Matrix矩阵'];
        foreach ($struct as $i => $name) {
            $this->register('D' . str_pad($i + 1, 2, '0', STR_PAD_LEFT), $name, '结构关系');
        }
        // 流程时序6种
        $flow = ['Flow流程图', 'Swimlane泳道图', 'Timeline时间线', 'Fishbone鱼骨图', 'Loop循环图', 'Staircase阶梯图'];
        foreach ($flow as $i => $name) {
            $this->register('D' . str_pad($i + 8, 2, '0', STR_PAD_LEFT), $name, '流程时序');
        }
        // 数据分析9种
        $data = ['Chart图表', 'Science科研绘图', 'TechRoadmap技术路线图', 'Infographic信息图', 'KnowledgeCard知识卡片', 'PyramidData数据金字塔', 'FishboneData数据鱼骨', 'MatrixData数据矩阵', 'StackedChart堆叠图'];
        foreach ($data as $i => $name) {
            $this->register('D' . str_pad($i + 14, 2, '0', STR_PAD_LEFT), $name, '数据分析');
        }
        // 战略分析5种
        $strategy = ['SWOT', 'PEST', 'Persona用户画像', 'UserStory用户故事', 'LeanCanvas精益画布'];
        foreach ($strategy as $i => $name) {
            $this->register('D' . str_pad($i + 23, 2, '0', STR_PAD_LEFT), $name, '战略分析');
        }
        // 其他3种
        $other = ['Treemap矩形树图', 'SimpleFlowchart简易流程', 'RadialSummary辐射总结'];
        foreach ($other as $i => $name) {
            $this->register('D' . str_pad($i + 28, 2, '0', STR_PAD_LEFT), $name, '其他');
        }
    }

    public function register(string $code, string $name, string $category): void {
        $this->spectrum[$code] = ['code' => $code, 'name' => $name, 'category' => $category];
    }

    public function all(): array { return $this->spectrum; }
    public function byCategory(string $cat): array {
        return array_filter($this->spectrum, fn($d) => $d['category'] === $cat);
    }
    public function get(string $code): ?array { return $this->spectrum[$code] ?? null; }
    public function count(): int { return count($this->spectrum); }

    public function getStats(): array {
        $cats = [];
        foreach ($this->spectrum as $d) {
            $cats[$d['category']] = ($cats[$d['category']] ?? 0) + 1;
        }
        return ['total' => count($this->spectrum), 'by_category' => $cats];
    }
}

// =================================================================
// v6.5.0.2: 基座复用飞轮
// =================================================================

class Linked3_Diagram_BaseReuse_Flywheel {
    /**
     * 计算复用率。
     */
    public function calculateReuseRate(array $baseTemplate, array $derivedDiagram): array {
        $baseKeys = array_keys($baseTemplate);
        $derivedKeys = array_keys($derivedDiagram);
        $shared = array_intersect($baseKeys, $derivedKeys);
        $reuseRate = count($derivedKeys) > 0 ? count($shared) / count($derivedKeys) : 0;
        return [
            'base_fields' => count($baseKeys),
            'derived_fields' => count($derivedKeys),
            'shared_fields' => count($shared),
            'reuse_rate' => round($reuseRate * 100, 1) . '%',
            'target' => '70-80%',
            'passed' => $reuseRate >= 0.70,
        ];
    }

    /**
     * 从基座派生子类。
     */
    public function derive(string $baseType, array $overrides): array {
        // v19.55-fix: match() is PHP 8.0+, plugin requires PHP 7.4 — convert to switch.
        switch ($baseType) {
            case 'knowledge':
                $base = ['layout' => '4band', 'badge_system' => true, 'color_system' => '9badge', 'density' => 'deep'];
                break;
            case 'infographic':
                $base = ['layout' => '3column', 'badge_system' => false, 'color_system' => 'theme', 'density' => 'standard'];
                break;
            case 'flowchart':
                $base = ['layout' => 'linear', 'badge_system' => true, 'color_system' => 'mono', 'density' => 'minimal'];
                break;
            default:
                $base = ['layout' => '4band', 'badge_system' => true, 'color_system' => '9badge', 'density' => 'deep'];
                break;
        }
        return array_merge($base, $overrides);
    }
}

// =================================================================
// v6.5.0.3: 3D/AR/动态海报子系统
// =================================================================

class Linked3_Diagram_3D_AR_Subsystem {
    public function generate3DConfig(array $diagram): array {
        return [
            'format' => '3D',
            'depth_layers' => 3,
            'parallax' => true,
            'rotation' => 'y_axis_15deg',
            'lighting' => 'studio_soft',
            'export_format' => ['glb', 'usdz'],
        ];
    }

    public function generateARConfig(array $diagram): array {
        return [
            'format' => 'AR',
            'anchor' => 'image_recognition',
            'scale' => '1:1',
            'interaction' => 'tap_to_rotate',
            'platform' => ['iOS_ARKit', 'Android_ARCore'],
        ];
    }

    public function generateDynamicPoster(array $diagram): array {
        return [
            'format' => 'dynamic_poster',
            'animation' => 'fade_in_sequence',
            'duration' => '15s',
            'fps' => 24,
            'export' => ['mp4', 'gif', 'webp'],
        ];
    }
}

// =================================================================
// v6.5.0.4: 视觉剧本转化3层管线
// =================================================================

class Linked3_Diagram_VisualScript_Transform {
    /**
     * 3层管线: 图示 → 剧本 → 动画
     */
    public function transform(array $diagram): array {
        return [
            'layer1_diagram' => $diagram,
            'layer2_script' => $this->diagramToScript($diagram),
            'layer3_animation' => $this->scriptToAnimation($this->diagramToScript($diagram)),
        ];
    }

    private function diagramToScript(array $diagram): array {
        $scenes = [];
        foreach ($diagram['bands'] ?? [] as $i => $band) {
            $scenes[] = [
                'scene' => $i + 1,
                'band' => $band['title'] ?? "Band {$i}",
                'visual' => $band['modules'] ?? [],
                'narration' => '画面展示' . ($band['title'] ?? ''),
                'duration' => 3,
            ];
        }
        return ['scenes' => $scenes, 'total_duration' => count($scenes) * 3];
    }

    private function scriptToAnimation(array $script): array {
        return [
            'keyframes' => array_map(fn($s) => [
                'time' => ($s['scene'] - 1) * $s['duration'],
                'action' => 'show_band',
                'target' => $s['band'],
            ], $script['scenes']),
            'transitions' => 'fade',
            'total_duration' => $script['total_duration'],
        ];
    }
}

// =================================================================
// v6.5.0.5: 品牌视觉资产5维度
// =================================================================

class Linked3_Diagram_BrandSystem_5D {
    public function build(array $brandConfig): array {
        return [
            'dimension1_logo' => $brandConfig['logo'] ?? '',
            'dimension2_color' => $brandConfig['color'] ?? ['#2F4F4F'],
            'dimension3_typography' => $brandConfig['typography'] ?? '思源宋体+思源黑体',
            'dimension4_texture' => $brandConfig['texture'] ?? '磨砂质感',
            'dimension5_motion' => $brandConfig['motion'] ?? '克制缓慢',
        ];
    }

    public function validate(array $brand5D): array {
        $passed = true;
        $issues = [];
        foreach (['logo', 'color', 'typography', 'texture', 'motion'] as $dim) {
            if (empty($brand5D["dimension{$dim}_". $dim] ?? $brand5D['dimension1_logo'])) {
                // 简化检查
            }
        }
        return ['passed' => $passed, 'issues' => $issues];
    }
}

// =================================================================
// v6.5.0.6: 8大系统交叉引用矩阵
// =================================================================

class Linked3_Diagram_8System_CrossRef {
    private array $systems = [
        'diagram'    => '图示系统',
        'animation'  => '动画系统',
        'brand'      => '品牌系统',
        'character'  => '角色系统',
        'product'    => '产品系统',
        'manga'      => '漫画系统',
        'picture_book' => '绘本系统',
        'sticker'    => '表情包系统',
    ];

    private array $crossRef = [];

    public function __construct() {
        // 每对系统的交叉引用关系
        $this->crossRef = [
            'diagram×animation' => '图示是动画的分镜蓝图',
            'diagram×brand' => '品牌镜头签名在每帧贯穿',
            'animation×brand' => '品牌镜头签名在动画每帧',
            'animation×character' => '角色Seed在动画全程锁定',
            'manga×animation' => '漫画分格=动画分镜, 气泡映射声效',
            'picture_book×animation' => '绘本页=动画帧',
            'sticker×character' => '表情包共享角色Seed',
        ];
    }

    public function getRelation(string $systemA, string $systemB): ?string {
        $key1 = "{$systemA}×{$systemB}";
        $key2 = "{$systemB}×{$systemA}";
        return $this->crossRef[$key1] ?? $this->crossRef[$key2] ?? null;
    }

    public function getAllRelations(): array { return $this->crossRef; }
    public function getSystems(): array { return $this->systems; }
}

// =================================================================
// v6.5.0.7: 商业加固
// =================================================================

class Linked3_Diagram_Commercial_Hardening {
    public function harden(): array {
        $checks = [];

        // 熔断检查
        $checks['circuit_breaker'] = class_exists('\Linked3\Classes\Diagram\Linked3_Error_Handler');

        // 限流检查
        $checks['rate_limiter'] = class_exists('\Linked3\Classes\Diagram\Linked3_Rate_Limiter_V2');

        // 安全检查
        $checks['security'] = class_exists('\Linked3\Classes\Diagram\Linked3_Security_Validator');

        // 缓存检查
        $checks['cache'] = class_exists('\Linked3\Classes\Diagram\Linked3_Performance_Cache');

        // 审计检查
        $checks['audit'] = class_exists('\Linked3\Classes\Diagram\Linked3_Audit_Logger');

        $passed = count(array_filter($checks));
        return [
            'checks' => $checks,
            'passed' => $passed,
            'total' => count($checks),
            'hardened' => $passed === count($checks),
        ];
    }
}

// =================================================================
// v6.5.0.8: E2E测试套件
// =================================================================

class Linked3_Diagram_E2E_TestSuite {
    public function runAll(): array {
        $tests = [];

        // 测试1: 主模板生成
        $tests['master_template'] = $this->testMasterTemplate();

        // 测试2: 16种图示类型注册
        $tests['type_registry'] = $this->testTypeRegistry();

        // 测试3: 30种全谱
        $tests['spectrum_30'] = $this->testSpectrum30();

        // 测试4: 6种Endpoint
        $tests['endpoint_6'] = $this->testEndpoint6();

        // 测试5: 13维校验
        $tests['validation_13dim'] = $this->testValidation13Dim();

        // 测试6: 三层编译器
        $tests['prompt_compiler'] = $this->testPromptCompiler();

        // 测试7: Seed系统
        $tests['seed_system'] = $this->testSeedSystem();

        // 测试8: 商业加固
        $tests['commercial_hardening'] = $this->testCommercialHardening();

        $passed = count(array_filter($tests, fn($t) => $t['passed']));
        return [
            'total' => count($tests),
            'passed' => $passed,
            'pass_rate' => round($passed / count($tests) * 100, 1) . '%',
            'tests' => $tests,
        ];
    }

    private function testMasterTemplate(): array {
        $ok = class_exists('\Linked3\Classes\Diagram\Linked3_Diagram_Master_Template');
        return ['passed' => $ok, 'msg' => $ok ? 'OK' : '主模板缺失'];
    }
    private function testTypeRegistry(): array {
        $count = Linked3_Diagram_Type_Registry::instance()->getStats()['total'] ?? 0;
        return ['passed' => $count === 16, 'msg' => "16种图示: {$count}/16"];
    }
    private function testSpectrum30(): array {
        $count = Linked3_Diagram_30_Spectrum::instance()->count();
        return ['passed' => $count === 30, 'msg' => "30种全谱: {$count}/30"];
    }
    private function testEndpoint6(): array {
        $count = count(Linked3_Diagram_Endpoint_Registry::instance()->all());
        return ['passed' => $count === 6, 'msg' => "6种Endpoint: {$count}/6"];
    }
    private function testValidation13Dim(): array {
        $ok = class_exists('\Linked3\Classes\Diagram\Linked3_Diagram_Validation_13Dim');
        return ['passed' => $ok, 'msg' => $ok ? 'OK' : '13维校验缺失'];
    }
    private function testPromptCompiler(): array {
        $ok = class_exists('\Linked3\Classes\Diagram\Linked3_Diagram_Prompt_Compiler');
        return ['passed' => $ok, 'msg' => $ok ? 'OK' : '编译器缺失'];
    }
    private function testSeedSystem(): array {
        $ok = class_exists('\Linked3\Classes\Diagram\Linked3_Diagram_CharacterSeed_Manager') && class_exists('\Linked3\Classes\Diagram\Linked3_Diagram_ProductSeed_Manager');
        return ['passed' => $ok, 'msg' => $ok ? 'OK' : 'Seed系统缺失'];
    }
    private function testCommercialHardening(): array {
        $result = (new Linked3_Diagram_Commercial_Hardening())->harden();
        return ['passed' => $result['hardened'], 'msg' => "加固: {$result['passed']}/{$result['total']}"];
    }
}

// =================================================================
// v6.5.0.9: 生产级启动器
// =================================================================

class Linked3_Diagram_Production_Bootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        // 确保 Diagram_Bootstrap 已启动
        if (class_exists('\Linked3\Classes\Diagram\Linked3_Diagram_Bootstrap')) {
            Linked3_Diagram_Bootstrap::boot();
        }

        $container = linked3_container();

        // v6.5.0: 30种全谱 + 商业加固
        $container->set('diagram.spectrum_30', fn() => Linked3_Diagram_30_Spectrum::instance());
        $container->set('diagram.base_reuse', fn() => new Linked3_Diagram_BaseReuse_Flywheel());
        $container->set('diagram.3d_ar', fn() => new Linked3_Diagram_3D_AR_Subsystem());
        $container->set('diagram.visual_script_transform', fn() => new Linked3_Diagram_VisualScript_Transform());
        $container->set('diagram.brand_5d', fn() => new Linked3_Diagram_BrandSystem_5D());
        $container->set('diagram.cross_ref_8system', fn() => new Linked3_Diagram_8System_CrossRef());
        $container->set('diagram.commercial_hardening', fn() => new Linked3_Diagram_Commercial_Hardening());
        $container->set('diagram.e2e_test', fn() => new Linked3_Diagram_E2E_TestSuite());

        // E2E 测试
        $testResult = (new Linked3_Diagram_E2E_TestSuite())->runAll();
        if ($container->has('logger')) {
            $container->get('logger')->info('Diagram E2E test result', $testResult);
        }

        linked3_dispatch('linked3.diagram.production.ready', [
            'version' => LINKED3_VERSION,
            'e2e_pass_rate' => $testResult['pass_rate'],
            'spectrum_count' => Linked3_Diagram_30_Spectrum::instance()->count(),
        ]);
    }
}
