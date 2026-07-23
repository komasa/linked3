<?php

declare(strict_types=1);
/**
 * Meta Lever Registry — v19.40 元提示词杠杆注册表.
 *
 * 统一管理所有元能力杠杆，提供：
 *   - 注册/注销杠杆
 *   - 按任务类型推荐杠杆
 *   - 按标签匹配杠杆
 *   - 组合多个杠杆的 system_prompt
 *   - 运行时启用/禁用
 *
 * @package Linked3
 * @subpackage Classes\MetaLever
 */

namespace Linked3\Classes\MetaLever;

if (!defined('ABSPATH')) {
    exit;
}

class MetaLeverRegistry
{
    /** @var array<string, MetaLeverInterface> */
    private static $levers = [];

    /** @var array<string, bool> 启用状态 */
    private static $enabled = [];

    /** @var bool 是否已初始化 */
    private static $initialized = false;

    /**
     * 初始化：加载所有内置杠杆 + 读取启用配置.
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        // G3.5: Load levers from JSON via data-driven class (replaces 45 individual files)
        // PSR-4: autoload handles class loading, just call directly
        foreach (MetaLeverDataDriven::load_all() as $lever) {
            if ($lever instanceof MetaLeverInterface) {
                self::register($lever);
            }
        }

        // 读取启用配置（默认全部启用）
        $saved = get_option('linked3_meta_levers_enabled', []);
        if (is_array($saved) && !empty($saved)) {
            self::$enabled = $saved;
        } else {
            // 默认全部启用
            foreach (self::$levers as $id => $lever) {
                self::$enabled[$id] = true;
            }
        }

        // 允许第三方扩展
        do_action('linked3_meta_levers_registered', self::$levers);
    }

    /**
     * 注册一个杠杆.
     */
    public static function register(MetaLeverInterface $lever): void
    {
        self::$levers[$lever->id()] = $lever;
        if (!isset(self::$enabled[$lever->id()])) {
            self::$enabled[$lever->id()] = true;
        }
    }

    /**
     * 获取指定杠杆.
     *
     * v20.4-fix: 自动触发 init(), 防止 get() 在 init() 未调用时返回 null。
     */
    public static function get(string $id): ?MetaLeverInterface
    {
        if (!self::$initialized) {
            self::init();
        }
        return self::$levers[$id] ?? null;
    }

    /**
     * 获取所有已注册杠杆.
     *
     * v20.4-fix: 自动触发 init()。
     *
     * @return array<string, MetaLeverInterface>
     */
    public static function all(): array
    {
        if (!self::$initialized) {
            self::init();
        }
        return self::$levers;
    }

    /**
     * 获取所有已启用的杠杆.
     *
     * v20.4-fix: 自动触发 init()。
     *
     * @return array<string, MetaLeverInterface>
     */
    public static function enabled(): array
    {
        if (!self::$initialized) {
            self::init();
        }
        $result = [];
        foreach (self::$levers as $id => $lever) {
            if (self::$enabled[$id] ?? true) {
                $result[$id] = $lever;
            }
        }
        return $result;
    }

    /**
     * 启用/禁用杠杆.
     */
    public static function set_enabled(string $id, bool $enabled): void
    {
        self::$enabled[$id] = $enabled;
        update_option('linked3_meta_levers_enabled', self::$enabled);
    }

    /**
     * 按任务类型推荐杠杆.
     *
     * @param string $task_type 任务类型 (xhs_generate / seo_article / ...)
     * @return array<string, MetaLeverInterface>
     */
    public static function recommend_for_task(string $task_type): array
    {
        $result = [];
        foreach (self::enabled() as $id => $lever) {
            if (in_array($task_type, $lever->applicable_tasks(), true)) {
                $result[$id] = $lever;
            }
        }
        return $result;
    }

    /**
     * 为指定任务构建增强 system_prompt.
     *
     * @param string $task_type  任务类型
     * @param string $base_prompt 原始 system_prompt
     * @return string 增强后的 system_prompt
     */
    public static function enhance_prompt(string $task_type, string $base_prompt): string
    {
        $levers = self::recommend_for_task($task_type);
        if (empty($levers)) {
            return $base_prompt;
        }

        $lever_prompts = [];
        foreach ($levers as $lever) {
            $lever_prompts[] = $lever->system_prompt();
        }

        $combined = implode("\n\n---\n\n", $lever_prompts);

        return $base_prompt . "\n\n---\n\n" . $combined;
    }

    /**
     * 获取所有杠杆的元信息（用于 UI 展示）.
     *
     * v20.4-fix: 自动触发 init()。
     *
     * @return array
     */
    public static function info(): array
    {
        if (!self::$initialized) {
            self::init();
        }
        // v20.4-fix20: 6大能力域分类映射
        $domain_map = [
            // 🔍 认知与元认知域
            'meta_cognition' => 'cognitive', 'meta_learning' => 'cognitive',
            'meta_essence' => 'cognitive', 'meta_attention' => 'cognitive',
            'meta_folding' => 'cognitive', 'meta_metacognition' => 'cognitive',
            'meta_self_calibration' => 'cognitive', 'meta_intuition' => 'cognitive',
            'meta_recursion' => 'cognitive', 'meta_concept' => 'cognitive',
            // 🧠 逻辑与推理域
            'meta_logic' => 'logic', 'meta_socratic' => 'logic',
            'meta_questioning' => 'logic', 'meta_reverse' => 'logic',
            'meta_problem_finding' => 'logic', 'meta_causal' => 'logic',
            'meta_probabilistic' => 'logic',
            // 🎨 创造与突破域
            'meta_creativity' => 'creative', 'meta_crossover' => 'creative',
            'meta_inspiration' => 'creative', 'meta_metaphor' => 'creative',
            'meta_analogy' => 'creative', 'meta_paradigm' => 'creative',
            'meta_design' => 'creative',
            // 📊 分析与评估域
            'meta_abstraction' => 'analytical', 'meta_pattern' => 'analytical',
            'meta_evaluation' => 'analytical', 'meta_stress_test' => 'analytical',
            'meta_knowledge_graph' => 'analytical', 'meta_system' => 'analytical',
            'meta_information' => 'analytical', 'meta_aesthetics' => 'analytical',
            // 🎯 战略与行动域
            'meta_strategy' => 'strategic', 'meta_decision' => 'strategic',
            'meta_execution' => 'strategic', 'meta_dynamics' => 'strategic',
            'meta_game' => 'strategic', 'meta_temporal' => 'strategic',
            'meta_ethics' => 'strategic',
            // 💬 沟通与协作域
            'meta_communication' => 'communication', 'meta_narrative' => 'communication',
            'meta_emotion' => 'communication', 'meta_collaboration' => 'communication',
            'meta_persuasion' => 'communication', 'meta_context' => 'communication',
        ];

        $domain_labels = [
            'cognitive' => '🔍 认知与元认知',
            'logic' => '🧠 逻辑与推理',
            'creative' => '🎨 创造与突破',
            'analytical' => '📊 分析与评估',
            'strategic' => '🎯 战略与行动',
            'communication' => '💬 沟通与协作',
        ];

        $info = [];
        foreach (self::$levers as $id => $lever) {
            $domain = $domain_map[$id] ?? 'cognitive';
            $info[] = [
                'id'          => $lever->id(),
                'label'       => $lever->label(),
                'description' => $lever->description(),
                'tags'        => $lever->tags(),
                'tasks'       => $lever->applicable_tasks(),
                'trace_field' => $lever->trace_field(),
                'enabled'     => self::$enabled[$id] ?? true,
                'domain'      => $domain,
                'domain_label'=> $domain_labels[$domain] ?? $domain,
            ];
        }
        return $info;
    }
}
