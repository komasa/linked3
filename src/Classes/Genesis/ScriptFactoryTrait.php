<?php

declare(strict_types=1);
/**
 * Linked3 Script Factory Trait — 三脚本生态共享Trait
 *
 * v10.4.0 (方案A) 新增: 用Trait代替继承, 零加载顺序风险
 *
 * 设计原理 (公理J: Trait代替继承):
 *   - Trait是PHP语言级horizontal reuse, 不触发autoload链
 *   - Trait在类定义时被"复制"到类中, 无加载顺序依赖
 *   - Dependency_Loader的字母序加载对Trait无影响
 *   - use Trait的类是独立类, 无extends, 加载安全
 *
 * 生产管线 (5阶段, 模板方法模式):
 *   Stage 0: load_seed_dna()  — 加载SEED中心数据
 *   Stage 1: load_style_config() — 加载风格配置
 *   Stage 2: compile()         — [抽象] 编译中间表示 IR
 *   Stage 3: project()         — [抽象] 投影到目标脚本格式
 *   Stage 4: quality_check()   — PQS质检
 *   Stage 5: platform_adapt()  — 多平台适配
 *
 * 使用方式:
 *   class ChartsFactory {
 *       use ScriptFactoryTrait;
 *       public function __construct() { $this->script_type = 'charts'; }
 *       protected function compile(array $context): array { ... }
 *       protected function project(array $ir): array { ... }
 *   }
 *
 * @package Linked3\Genesis
 * @since 10.4.0
 * @version 10.4.0
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

trait ScriptFactoryTrait {

    /** @var string 脚本类型: comic|charts|video */
    protected $script_type = '';

    /** @var string 目标平台: midjourney|sdxl|dalle|video_generic */
    protected $platform = 'midjourney';

    /** @var array SEED DNA缓存 */
    protected $seed_dna = [];

    /** @var array 风格配置 */
    protected $style_config = [];

    /** @var array 生产上下文 (用户输入) */
    protected $context = [];

    // ================================================================
    // 模板方法: 生产管线入口
    // ================================================================

    /**
     * 生产入口 — 模板方法, 锁定5阶段管线
     *
     * @param array $context 用户输入上下文
     * @return array { success: bool, output: array, ir: array, quality: array }
     */
    public function generate(array $context): array {
        $this->context = $context;
        $this->platform = $context['platform'] ?? $this->platform;

        try {
            // Stage 0: 加载SEED DNA
            $this->seed_dna = $this->load_seed_dna($context['seed_refs'] ?? []);

            // Stage 1: 加载风格配置
            $this->style_config = $this->load_style_config($context['style'] ?? 'default');

            // Stage 2: 编译中间表示 IR
            $ir = $this->compile($context);

            // Stage 3: 投影到目标脚本格式
            $output = $this->project($ir);

            // Stage 4: PQS质检
            $quality = $this->quality_check($output, $ir);

            // Stage 5: 平台适配
            $output = $this->platform_adapt($output, $this->platform);

            return [
                'success' => true,
                'output' => $output,
                'ir' => $ir,
                'quality' => $quality,
                'meta' => [
                    'script_type' => $this->script_type,
                    'platform' => $this->platform,
                    'seed_count' => count($this->seed_dna),
                    'factory_version' => '10.4.0',
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'trace' => defined('WP_DEBUG') && WP_DEBUG ? $e->getTraceAsString() : '',
                'meta' => [
                    'script_type' => $this->script_type,
                    'factory_version' => '10.4.0',
                ],
            ];
        }
    }

    // ================================================================
    // 共享方法: SEED/风格加载
    // ================================================================

    /**
     * 加载SEED DNA — 统一入口, 三脚本共享
     *
     * @param array $seed_refs SEED引用 [{type: char, id: 123}, ...]
     * @return array 按type分组的SEED DNA
     */
    protected function load_seed_dna(array $seed_refs): array {
        if (empty($seed_refs)) {
            return [];
        }

        $dna = [];
        foreach ($seed_refs as $ref) {
            // 兼容两种格式: {type:char, id:123} 或 "char:123"
            if (is_string($ref)) {
                $parts = explode(':', $ref, 2);
                if (count($parts) !== 2) continue;
                $type = $parts[0];
                $id = $parts[1];
            } else {
                $type = $ref['type'] ?? '';
                $id = $ref['id'] ?? 0;
            }
            if (!$type || !$id) continue;

            $type = $this->normalize_seed_type($type);
            $entry = $this->fetch_seed_entry($type, $id);
            if ($entry !== null) {
                $dna[$type][] = $entry;
            }
        }
        return $dna;
    }

    /**
     * 获取单个SEED条目 (v10.4.0修复: 使用正确的方法名)
     */
    private function fetch_seed_entry(string $type, $id): ?array {
        // 优先 Seed_Unified::get()
        if (class_exists('\Linked3\Classes\Genesis\SeedUnified')) {
            try {
                $entry = \SeedUnified::get($id);
                if (is_array($entry) && !empty($entry)) {
                    return $entry;
                }
            } catch (\Throwable $e) {}
        }
        // 降级 Seed_CPT::get_by_seed_id()
        if (class_exists('\Linked3\Classes\Genesis\GenesisSeedCPT')) {
            try {
                $entry = \GenesisSeedCPT::get_by_seed_id($id);
                if (is_array($entry) && !empty($entry)) {
                    return $entry;
                }
            } catch (\Throwable $e) {}
        }
        return null;
    }

    /**
     * 加载风格配置 — 统一入口
     */
    protected function load_style_config(string $style_key): array {
        if (class_exists('\Linked3\Classes\Genesis\GenesisStyleEngine')) {
            try {
                return \GenesisStyleEngine::load($style_key);
            } catch (\Throwable $e) {}
        }
        return ['key' => $style_key, 'name' => $style_key];
    }

    /**
     * 归一化SEED类型 — 公理F: 统一6类枚举
     */
    protected function normalize_seed_type(string $type): string {
        $map = [
            'soul' => 'palette',
            'character' => 'char',
            'scene' => 'scene',
            'prop' => 'prop',
            'style' => 'style',
            'brand' => 'brand',
            'palette' => 'palette',
            'char' => 'char',
        ];
        return $map[$type] ?? $type;
    }

    // ================================================================
    // 抽象方法: use Trait的类必须实现
    // ================================================================

    /**
     * 编译中间表示 IR — use类实现
     */
    abstract protected function compile(array $context): array;

    /**
     * 投影到目标脚本格式 — use类实现
     */
    abstract protected function project(array $ir): array;

    // ================================================================
    // 可重写方法: 质检与平台适配 (有默认实现)
    // ================================================================

    /**
     * PQS质检 — 默认基础质检, 子类可重写
     */
    protected function quality_check(array $output, array $ir): array {
        $checks = [];
        $score = 0;

        // 基础检查1: 输出非空
        $checks['output_not_empty'] = [
            'name' => '输出非空',
            'passed' => !empty($output),
            'value' => is_array($output) ? count($output) : 0,
        ];
        if (!empty($output)) $score += 50;

        // 基础检查2: SEED已加载
        $checks['seed_loaded'] = [
            'name' => 'SEED已加载',
            'passed' => !empty($this->seed_dna),
            'value' => count($this->seed_dna),
        ];
        if (!empty($this->seed_dna)) $score += 25;

        // 基础检查3: 风格已加载
        $checks['style_loaded'] = [
            'name' => '风格已加载',
            'passed' => !empty($this->style_config),
            'value' => !empty($this->style_config['name']),
        ];
        if (!empty($this->style_config)) $score += 25;

        return [
            'score' => min($score, 100),
            'checks' => $checks,
            'passed' => $score >= 60,
            'rule_set' => 'base',
        ];
    }

    /**
     * 平台适配 — 默认透传, 子类可重写
     */
    protected function platform_adapt(array $output, string $platform): array {
        $output['_platform'] = $platform;
        return $output;
    }

    // ================================================================
    // 工具方法: use类可调用
    // ================================================================

    /**
     * 获取SEED DNA (按类型)
     */
    protected function get_seed(string $type): array {
        $type = $this->normalize_seed_type($type);
        return $this->seed_dna[$type] ?? [];
    }

}
