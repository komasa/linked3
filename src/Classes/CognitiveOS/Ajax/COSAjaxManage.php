<?php

declare(strict_types=1);
namespace Linked3\Classes\CognitiveOS\Ajax;
if (!defined('ABSPATH')) exit;
class COSAjaxManage
{
    public static function recommend_levers_for_problem(string $problem, string $approach, string $domain): array
    {
        // 所有可用杠杆 (v20.4-fix16: 从12个扩展到24个基础杠杆)
        $all_levers = [
            // --- 原有12个基础杠杆 ---
            'meta_learning'        => __('通用元学习能力', 'linked3'),
            'meta_logic'           => __('通用逻辑学能力', 'linked3'),
            'meta_cognition'       => __('元认知能力', 'linked3'),
            'meta_creativity'      => __('元创造能力', 'linked3'),
            'meta_critique'        => __('元批判能力', 'linked3'),
            'meta_abstraction'     => __('元抽象能力', 'linked3'),
            'meta_analogy'         => __('元类比能力', 'linked3'),
            'meta_system'          => __('元系统思维', 'linked3'),
            'meta_decision'        => __('元决策能力', 'linked3'),
            'meta_communication'   => __('元沟通能力', 'linked3'),
            'meta_problem_finding' => __('元问题发现能力', 'linked3'),
            'meta_evaluation'      => __('元评估能力', 'linked3'),
            // --- v20.4-fix16 新增12个基础杠杆 ---
            'meta_essence'         => __('元本质追问', 'linked3'),
            'meta_reverse'         => __('元反向思考', 'linked3'),
            'meta_strategy'        => __('元深度谋划', 'linked3'),
            'meta_crossover'       => __('元跨界颠覆', 'linked3'),
            'meta_inspiration'     => __('元灵感管理', 'linked3'),
            'meta_stress_test'     => __('元压力测试', 'linked3'),
            'meta_socratic'        => __('元苏格拉底追问', 'linked3'),
            'meta_folding'         => __('元认知折叠', 'linked3'),
            'meta_dynamics'        => __('元动态演化', 'linked3'),
            'meta_execution'       => __('元行动落地', 'linked3'),
            'meta_questioning'     => __('元质疑解构', 'linked3'),
            'meta_metaphor'        => __('元隐喻工程', 'linked3'),
            // --- v20.4-fix18 新增12个深层基础杠杆 ---
            'meta_narrative'       => __('元叙事构建', 'linked3'),
            'meta_pattern'         => __('元模式识别', 'linked3'),
            'meta_emotion'         => __('元情绪智能', 'linked3'),
            'meta_concept'         => __('元概念工程', 'linked3'),
            'meta_aesthetics'      => __('元美学判断', 'linked3'),
            'meta_attention'       => __('元注意力管理', 'linked3'),
            'meta_knowledge_graph' => __('元知识图谱', 'linked3'),
            'meta_temporal'        => __('元时间维度', 'linked3'),
            'meta_game'            => __('元博弈对抗', 'linked3'),
            'meta_ethics'          => __('元伦理审查', 'linked3'),
            'meta_paradigm'        => __('元范式转换', 'linked3'),
            'meta_collaboration'   => __('元协作编排', 'linked3'),
            // --- v20.4-fix19 新增10个补齐缺失的基础杠杆 ---
            'meta_metacognition'   => __('元元认知', 'linked3'),
            'meta_self_calibration'=> __('元自我校准', 'linked3'),
            'meta_intuition'       => __('元直觉思维', 'linked3'),
            'meta_recursion'       => __('元递归思维', 'linked3'),
            'meta_causal'          => __('元因果推断', 'linked3'),
            'meta_probabilistic'   => __('元概率推理', 'linked3'),
            'meta_design'          => __('元设计思维', 'linked3'),
            'meta_information'     => __('元信息架构', 'linked3'),
            'meta_persuasion'      => __('元说服力工程', 'linked3'),
            'meta_context'         => __('元语境感知', 'linked3'),
        ];

        // v20.4-fix16: 一键精准匹配 — 基于场景关键词自动推荐杠杆组合
        $recommended = self::scene_match_levers($problem, $approach, $domain);

        // v20.4-fix24: 复合杠杆标签映射 (scene_match_levers可能返回复合杠杆ID)
        $composite_labels = [
            'deai_5d' => __('去AI味五部门', 'linked3'), 'genesis' => __('创世演化', 'linked3'), 'deep_strategy' => __('深度谋划', 'linked3'),
            'cross_innovation' => __('跨界创新', 'linked3'), 'socratic_review' => __('苏格拉底审查', 'linked3'),
            'super_prompt' => __('执行蓝图转换器', 'linked3'), 'cognitive_audit' => __('认知审计', 'linked3'),
            'knowledge_synthesis' => __('知识综合', 'linked3'), 'content_engine' => __('内容引擎', 'linked3'), 'risk_defense' => __('风险防御', 'linked3'),
        ];

        // 返回带标签的推荐
        $result = [];
        foreach ($recommended as $id) {
            if (isset($all_levers[$id])) {
                $result[] = ['id' => $id, 'label' => $all_levers[$id]];
            } elseif (isset($composite_labels[$id])) {
                // v20.4-fix24: 复合杠杆也需要返回标签
                $result[] = ['id' => $id, 'label' => $composite_labels[$id]];
            }
        }

        return $result;
    }

    public static function scene_match_levers(string $problem, string $approach, string $domain): array
    {
        $text = mb_strtolower($problem . ' ' . $approach . ' ' . $domain);

        // 场景关键词 → 杠杆组合映射表 (v20.4-fix22: 包含复合杠杆)
        $scenes = [
            // 电商营销类 — 推荐内容引擎+风险防御复合杠杆
            '电商'   => ['meta_essence', 'meta_creativity', 'meta_strategy', 'meta_evaluation', 'content_engine', 'risk_defense'],
            '选品'   => ['meta_essence', 'meta_strategy', 'meta_reverse', 'meta_stress_test', 'risk_defense', 'meta_execution'],
            '小红书' => ['meta_creativity', 'meta_communication', 'meta_metaphor', 'content_engine', 'meta_evaluation', 'meta_folding'],
            '营销'   => ['meta_creativity', 'meta_strategy', 'meta_communication', 'content_engine', 'meta_evaluation', 'meta_execution'],
            '转化'   => ['meta_essence', 'meta_reverse', 'meta_stress_test', 'meta_execution', 'meta_evaluation', 'meta_questioning'],

            // 内容创作类 — 推荐内容引擎复合杠杆
            '写作'   => ['meta_creativity', 'meta_metaphor', 'content_engine', 'meta_communication', 'meta_critique', 'meta_evaluation'],
            '文章'   => ['meta_creativity', 'meta_logic', 'meta_communication', 'meta_critique', 'content_engine', 'meta_folding'],
            '视频'   => ['meta_creativity', 'meta_metaphor', 'meta_communication', 'content_engine', 'meta_evaluation', 'meta_execution'],
            '封面'   => ['meta_creativity', 'meta_metaphor', 'meta_reverse', 'meta_evaluation', 'meta_communication', 'meta_folding'],
            '标题'   => ['meta_creativity', 'meta_reverse', 'meta_folding', 'meta_communication', 'meta_evaluation', 'meta_metaphor'],

            // 技术工程类 — 推荐认知审计复合杠杆
            '架构'   => ['meta_essence', 'meta_system', 'meta_logic', 'meta_stress_test', 'cognitive_audit', 'meta_evaluation'],
            '系统'   => ['meta_system', 'meta_dynamics', 'meta_logic', 'meta_stress_test', 'cognitive_audit', 'meta_evaluation'],
            '代码'   => ['meta_logic', 'meta_essence', 'meta_stress_test', 'meta_execution', 'cognitive_audit', 'meta_abstraction'],

            // 商业策略类 — 推荐深度谋划+风险防御复合杠杆
            '策略'   => ['meta_strategy', 'meta_reverse', 'meta_system', 'deep_strategy', 'risk_defense', 'meta_execution'],
            '增长'   => ['meta_strategy', 'meta_crossover', 'meta_reverse', 'meta_dynamics', 'deep_strategy', 'meta_execution'],
            '商业'   => ['meta_strategy', 'meta_essence', 'meta_reverse', 'deep_strategy', 'risk_defense', 'meta_execution'],

            // 通用审查类 — 推荐苏格拉底审查+认知审计复合杠杆
            '优化'   => ['meta_essence', 'meta_critique', 'meta_evaluation', 'socratic_review', 'cognitive_audit', 'meta_execution'],
            '分析'   => ['meta_essence', 'meta_logic', 'meta_system', 'socratic_review', 'meta_evaluation', 'cognitive_audit'],
        ];

        // 默认推荐 (通用审查组合)
        $recommended = ['meta_essence', 'meta_critique', 'meta_evaluation', 'meta_socratic', 'meta_questioning', 'meta_execution'];

        // 按匹配优先级排序 (先匹配到的优先)
        foreach ($scenes as $keyword => $levers) {
            if (mb_strpos($text, $keyword) !== false) {
                $recommended = $levers;
                break;
            }
        }

        // 领域微调
        $domain_lower = strtolower($domain);
        if (strpos($domain_lower, 'ecom') !== false && !in_array('meta_strategy', $recommended)) {
            $recommended[5] = 'meta_strategy';
        }

        return $recommended;
    }

    public static function ajax_evolve(): void
    {
        check_ajax_referer('linked3_cos', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }

        // v20.4-fix5: 演化需要 3 次 AI 调用 (G1/G2/G3), 每次可能 10-30 秒
        // 必须提高 PHP 超时, 否则 "Failed to fetch"
        @set_time_limit(180);
        @ini_set('max_execution_time', '180');

        $problem = isset($_POST['problem']) ? sanitize_textarea_field(wp_unslash($_POST['problem'])) : '';
        // v20.2-fix: context 从前端以 JSON 字符串发送, 需要解码
        $context = [];
        if (isset($_POST['context']) && is_string($_POST['context'])) {
            $decoded = json_decode(wp_unslash($_POST['context']), true);
            if (is_array($decoded)) {
                $context = $decoded;
            }
        } elseif (isset($_POST['context']) && is_array($_POST['context'])) {
            $context = $_POST['context'];
        }
        // 安全过滤: 只保留字符串值
        $context = array_map('sanitize_text_field', array_filter($context, function($v) {
            return is_string($v) || is_numeric($v);
        }));

        if (empty($problem)) {
            wp_send_json_error(['message' => __('问题描述不能为空', 'linked3')], 400);
        }

        if (!class_exists('\\Linked3\\Classes\\CognitiveOS\\COSEngine')) {
            wp_send_json_error(['message' => __('COS 引擎未加载', 'linked3')], 500);
        }

        try {
            $engine = \Linked3\Classes\CognitiveOS\COSEngine::instance();
            $result = $engine->evolve($problem, $context);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public static function ajax_dashboard(): void
    {
        check_ajax_referer('linked3_cos', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }

        if (!class_exists('\\Linked3\\Classes\\CognitiveOS\\COSEngine')) {
            wp_send_json_error(['message' => __('COS 引擎未加载', 'linked3')], 500);
        }

        try {
            $reporter = new \Linked3\Classes\CognitiveOS\COSReporter();
            $overview = $reporter->dashboard_overview();
            $skills   = $reporter->top_skills(5);
            $recent   = $reporter->recent_evolutions(5);
            wp_send_json_success([
                'overview'      => $overview,
                'top_skills'    => $skills,
                'recent_evolutions' => $recent,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public static function ajax_skills(): void
    {
        check_ajax_referer('linked3_cos', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }

        if (!class_exists('\\Linked3\\Classes\\CognitiveOS\\Storage\\COSSkillLibrary')) {
            wp_send_json_error(['message' => __('Skill 库未加载', 'linked3')], 500);
        }

        try {
            $skills = \Linked3\Classes\CognitiveOS\Storage\COSSkillLibrary::all();
            $stats  = \Linked3\Classes\CognitiveOS\Storage\COSSkillLibrary::stats();
            wp_send_json_success(['skills' => $skills, 'stats' => $stats]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

}
