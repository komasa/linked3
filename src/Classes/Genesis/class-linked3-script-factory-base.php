<?php
/**
 * Linked3 Script Factory Base — 三脚本生态共享基类
 *
 * v10.1.5 (S6方案) 新增: 抽取三脚本(漫画/图文/视频)共享的工厂基类
 *
 * 设计模式: 模板方法 (Template Method)
 *   - 基类 final generate() 锁定生产管线: compile → project → quality_check
 *   - 子类只实现 project() (投影到目标平台), 不可重写 generate()
 *   - 公理D合规: 工厂必有引擎, 三脚本各有独立Factory子类
 *   - 公理E合规: DRY, 共享逻辑收归基类, 消除重复实现
 *
 * 生产管线 (5阶段):
 *   Stage 0: load_seed_dna()  — 加载SEED中心数据 (角色/场景/道具/色板/品牌/风格)
 *   Stage 1: compile()         — 编译中间表示 IR = SEED × STYLE × BEAT × PLATFORM
 *   Stage 2: project()         — [抽象] 子类实现, 将IR投影为目标脚本格式
 *   Stage 3: quality_check()   — PQS质检 (按script_type分发不同规则集)
 *   Stage 4: platform_adapt()  — 多平台适配 (Midjourney/SDXL/DALL-E/视频平台)
 *
 * @package Linked3\Genesis
 * @since 10.1.5
 * @version 10.1.5
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

abstract class Linked3_Script_Factory_Base {

    /** @var string 脚本类型: comic|charts|video */
    protected $script_type;

    /** @var string 目标平台: midjourney|sdxl|dalle|video_generic */
    protected $platform = 'midjourney';

    /** @var array SEED DNA缓存 */
    protected $seed_dna = [];

    /** @var array 风格配置 */
    protected $style_config = [];

    /** @var array 生产上下文 (用户输入) */
    protected $context = [];

    /**
     * 构造: 锁定脚本类型
     *
     * @param string $script_type comic|charts|video
     */
    final public function __construct(string $script_type) {
        $this->script_type = $script_type;
    }

    // ================================================================
    // 模板方法: 锁定生产管线, 子类不可重写
    // ================================================================

    /**
     * 生产入口 — 模板方法, 锁定5阶段管线
     *
     * @param array $context 用户输入上下文
     *   - topic: string 主题
     *   - style: string 风格key
     *   - seed_refs: array SEED引用 [char_id, scene_id, ...]
     *   - platform: string 目标平台
     *   - extra: array 额外参数
     * @return array { success: bool, output: array, ir: array, quality: array }
     */
    final public function generate(array $context): array {
        $this->context = $context;
        $this->platform = $context['platform'] ?? $this->platform;

        try {
            // Stage 0: 加载SEED DNA (统一入口, 公理F: 数据契约单一真源)
            $this->seed_dna = $this->load_seed_dna($context['seed_refs'] ?? []);

            // Stage 1: 加载风格配置
            $this->style_config = $this->load_style_config($context['style'] ?? 'default');

            // Stage 1.5: v10.7.0 跨生态共享 — 若指定云模版分类, 从云模版池拉取覆盖
            $cloud_result = $this->consume_cloud_template($context);
            $cloud_source = $cloud_result['cloud_source'];

            // Stage 2: 编译中间表示 IR
            $ir = $this->compile($context);

            // Stage 3: 投影到目标脚本格式 (子类实现)
            $output = $this->project($ir);

            // Stage 4: PQS质检 (按script_type分发)
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
                    'style' => $context['style'] ?? 'default',
                    'seed_count' => count($this->seed_dna),
                    'factory_version' => '10.7.0',
                    'cloud_template_source' => $cloud_source,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'trace' => WP_DEBUG ? $e->getTraceAsString() : '',
                'meta' => [
                    'script_type' => $this->script_type,
                    'factory_version' => '10.1.5',
                ],
            ];
        }
    }

    // ================================================================
    // 共享方法: SEED/风格加载 (公理F: 数据契约单一真源)
    // ================================================================

    /**
     * 加载SEED DNA — 统一入口, 三脚本共享
     * 优先使用 Seed_Unified (v10.1.5), 降级到 Seed_CPT
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
            $type = $ref['type'] ?? '';
            $id = $ref['id'] ?? 0;
            if (!$type || !$id) {
                continue;
            }

            // 公理F: 统一category枚举 (6类, palette非soul)
            $type = $this->normalize_seed_type($type);

            $entry = null;
            if (class_exists('\Linked3\Classes\Genesis\Linked3_Seed_Unified')) {
                $entry = \Linked3_Seed_Unified::load_seed_dna($type, $id);
            }
            if ($entry === null && class_exists('\Linked3\Classes\Genesis\Linked3_Seed_CPT')) {
                $entry = \Linked3_Seed_CPT::load_seed_dna($type, $id);
            }

            if ($entry !== null) {
                $dna[$type][] = $entry;
            }
        }
        return $dna;
    }

    /**
     * 加载风格配置 — 统一入口, 三脚本共享
     *
     * @param string $style_key 风格key (如 cinematic_still)
     * @return array 风格配置
     */
    protected function load_style_config(string $style_key): array {
        if (class_exists('\Linked3\Classes\Genesis\Linked3_Genesis_StyleEngine')) {
            return \Linked3_Genesis_StyleEngine::load($style_key);
        }
        return ['key' => $style_key, 'name' => $style_key];
    }

    /**
     * v10.7.0 跨生态共享: 从云模版池拉取模版 (公理R)
     *
     * 脚本生态(charts/genesis/video)消费写作生态的云模版:
     *   - 若 context 含 cloud_template_category, 则从云模版工厂拉取
     *   - 拉取的模版提供 style/structure/palette/tone, 覆盖默认 style_config
     *   - 来源溯源记录在 meta.cloud_template_source
     *
     * @param array $context 用户输入 (含可选 cloud_template_category)
     * @return array { style_config: array, cloud_source: array|null }
     */
    protected function consume_cloud_template(array $context): array {
        $category = $context['cloud_template_category'] ?? '';
        if (!$category || !class_exists('\Linked3\Classes\Genesis\Linked3_Cloud_Template_Factory')) {
            return ['style_config' => $this->style_config, 'cloud_source' => null];
        }

        try {
            $factory = new \Linked3_Cloud_Template_Factory();
            $shared = $factory->get_shared_template_for_script($category, $this->script_type);

            // 云模版覆盖风格配置 (style/palette/structure)
            $this->style_config = array_merge($this->style_config, [
                'key'       => 'cloud_' . $category,
                'name'      => $shared['source']['template'] ?? $category,
                'style'     => $shared['style'] ?? 'professional',
                'palette'   => $shared['palette'] ?? [],
                'structure' => $shared['structure'] ?? [],
                'tone'      => $shared['tone'] ?? 'professional',
            ]);

            return ['style_config' => $this->style_config, 'cloud_source' => $shared['source']];
        } catch (\Throwable $e) {
            return ['style_config' => $this->style_config, 'cloud_source' => null];
        }
    }

    /**
     * 归一化SEED类型 — 公理F: 统一6类枚举
     * soul → palette (双写兼容期, v10.1.4引入)
     *
     * @param string $type 原始类型
     * @return string 归一化类型
     */
    protected function normalize_seed_type(string $type): string {
        $map = [
            'soul' => 'palette',  // v10.1.4 双写: soul别名→palette
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
    // 抽象方法: 子类必须实现
    // ================================================================

    /**
     * 编译中间表示 IR — 子类实现
     * IR = SEED_DNA × STYLE_CONFIG × BEAT × PLATFORM
     *
     * @param array $context 用户输入
     * @return array 中间表示
     */
    abstract protected function compile(array $context): array;

    /**
     * 投影到目标脚本格式 — 子类实现
     * 漫画: 分镜列表; 图文: 4Band结构; 视频: 视频组列表
     *
     * @param array $ir 中间表示
     * @return array 目标脚本
     */
    abstract protected function project(array $ir): array;

    // ================================================================
    // 可重写方法: 质检与平台适配 (有默认实现)
    // ================================================================

    /**
     * PQS质检 — 按script_type分发不同规则集 (公理: 质检分治)
     *
     * @param array $output 投影结果
     * @param array $ir 中间表示
     * @return array { score: int, checks: array, passed: bool }
     */
    protected function quality_check(array $output, array $ir): array {
        // 默认: 基础结构检查
        $checks = [
            'has_output' => !empty($output),
            'has_seed' => !empty($this->seed_dna),
            'has_style' => !empty($this->style_config),
        ];
        $score = count(array_filter($checks)) * 33;  // 0-99
        return [
            'score' => min($score, 100),
            'checks' => $checks,
            'passed' => $score >= 60,
            'rule_set' => 'base',
        ];
    }

    /**
     * 平台适配 — 将脚本适配到目标平台参数
     *
     * @param array $output 投影结果
     * @param string $platform 目标平台
     * @return array 适配后的脚本
     */
    protected function platform_adapt(array $output, string $platform): array {
        // 默认: 透传 (子类可重写添加平台特定参数)
        $output['_platform'] = $platform;
        return $output;
    }

    // ================================================================
    // 工具方法: 子类可调用
    // ================================================================

    /**
     * 获取SEED DNA (按类型)
     *
     * @param string $type SEED类型
     * @return array 该类型的所有SEED DNA
     */
    final protected function get_seed(string $type): array {
        $type = $this->normalize_seed_type($type);
        return $this->seed_dna[$type] ?? [];
    }

    /**
     * 获取风格配置项
     *
     * @param string $key 配置key
     * @param mixed $default 默认值
     * @return mixed
     */
    final protected function get_style(string $key, $default = null) : mixed {
        return $this->style_config[$key] ?? $default;
    }

    /**
     * 获取上下文参数
     *
     * @param string $key 参数key
     * @param mixed $default 默认值
     * @return mixed
     */
    final protected function get_context(string $key, $default = null) {
        return $this->context[$key] ?? ($this->context['extra'][$key] ?? $default);
    }
}
