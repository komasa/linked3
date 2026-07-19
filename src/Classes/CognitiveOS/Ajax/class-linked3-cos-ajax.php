<?php
/**
 * Cognitive Operating System — AJAX 端点 (v20.0)
 *
 * 提供前端调用 COS 引擎的 AJAX 接口:
 *   wp_ajax_linked3_cos_evolve       — 启动一次三代演化
 *   wp_ajax_linked3_cos_dashboard    — 获取仪表盘数据
 *   wp_ajax_linked3_cos_skills      — 获取 Skill 列表
 *   wp_ajax_linked3_cos_archive     — 获取演化归档
 *   wp_ajax_linked3_cos_run_lever   — 调用单个杠杆
 *
 * @package Linked3\Classes\CognitiveOS\Ajax
 * @since   20.0
 */

namespace Linked3\Classes\CognitiveOS\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Linked3_COS_Ajax
 *
 * COS AJAX 端点注册器。
 */
class Linked3_COS_Ajax
{
    /**
     * 注册所有 AJAX 端点。
     */
    public static function register(): void
    {
        add_action('wp_ajax_linked3_cos_evolve',      [__CLASS__, 'ajax_evolve']);
        add_action('wp_ajax_linked3_cos_dashboard',   [__CLASS__, 'ajax_dashboard']);
        add_action('wp_ajax_linked3_cos_skills',      [__CLASS__, 'ajax_skills']);
        add_action('wp_ajax_linked3_cos_archive',     [__CLASS__, 'ajax_archive']);
        add_action('wp_ajax_linked3_cos_run_lever',   [__CLASS__, 'ajax_run_lever']);
        add_action('wp_ajax_linked3_cos_chain_levers', [__CLASS__, 'ajax_chain_levers']);
        add_action('wp_ajax_linked3_cos_delete_skill', [__CLASS__, 'ajax_delete_skill']);
        add_action('wp_ajax_linked3_cos_apply_skill', [__CLASS__, 'ajax_apply_skill']);
        add_action('wp_ajax_linked3_cos_get_sop',     [__CLASS__, 'ajax_get_sop']);
        add_action('wp_ajax_linked3_cos_version',     [__CLASS__, 'ajax_version']);
        add_action('wp_ajax_linked3_cos_diagnose',    [__CLASS__, 'ajax_diagnose']);
        add_action('wp_ajax_linked3_cos_recommend_levers', [__CLASS__, 'ajax_recommend_levers']);
        add_action('wp_ajax_linked3_cos_evolve_gen',  [__CLASS__, 'ajax_evolve_gen']);
        add_action('wp_ajax_linked3_cos_evolve_finalize', [__CLASS__, 'ajax_evolve_finalize']);
        // v20.4-fix12: 重置 AI 熔断器 — 清除所有 provider 的失败计数
        add_action('wp_ajax_linked3_cos_reset_circuit', [__CLASS__, 'ajax_reset_circuit']);
    }

    /**
     * AJAX: 异步演化 — 运行单代 (v20.4-fix8)。
     * 前端串行调用 G1 → G2 → G3, 每次一个 AJAX 请求, 避免超时。
     */
        public static function ajax_evolve_gen() : mixed { return Linked3_COS_Ajax_Evolve::ajax_evolve_gen(); }

    /**
     * AJAX: 异步演化 — 最终结晶 (v20.4-fix8)。
     * G3 完成后调用, 保存 Skill。
     */
        public static function ajax_evolve_finalize() : mixed { return Linked3_COS_Ajax_Evolve::ajax_evolve_finalize(); }

    /**
     * AJAX: AI 诊断 — 测试 AI 调用是否正常, 返回详细错误信息。
     * 用于排查 "Failed to fetch" 的根因。
     */
        public static function ajax_diagnose() { return Linked3_COS_Ajax_Evolve::ajax_diagnose(); }

    /**
     * AJAX: 重置 AI 熔断器 — v20.4-fix12 新增。
     * 清除所有 provider 的失败计数 (transient: linked3_pcb_{slug}),
     * 让被熔断的 provider 立即恢复可用。
     * 适用场景: AI 曾因超时失败触发熔断, 但 API 已恢复, 用户想立即重试。
     */
        public static function ajax_reset_circuit() { return Linked3_COS_Ajax_Evolve::ajax_reset_circuit(); }

    /**
     * AJAX: 杠杆自适配推荐 — 根据演化结果自动推荐适合的杠杆。
     */
        public static function ajax_recommend_levers() { return Linked3_COS_Ajax_Evolve::ajax_recommend_levers(); }

    /**
     * 基于问题特征推荐杠杆组合。
     */
        public static function recommend_levers_for_problem(string $problem, string $approach, string $domain) { return Linked3_COS_Ajax_Manage::recommend_levers_for_problem($problem, $approach, $domain); }

    /**
     * v20.4-fix16: 一键精准匹配 — 基于场景关键词自动推荐杠杆组合
     * 支持18个场景的精准匹配，覆盖商用生产级常见需求
     */
        public static function scene_match_levers(string $problem, string $approach, string $domain) { return Linked3_COS_Ajax_Manage::scene_match_levers($problem, $approach, $domain); }

    /**
     * AJAX: 版本探针 — 验证部署的代码版本。
     * 前端调用此端点, 如果返回的 patch_version 不是最新值,
     * 说明旧代码仍在运行 (需要清 OPcache 或重新上传)。
     */
        public static function ajax_version() { return Linked3_COS_Ajax_Evolve::ajax_version(); }

    /**
     * AJAX: 启动一次三代演化。
     */
        public static function ajax_evolve() { return Linked3_COS_Ajax_Manage::ajax_evolve(); }

    /**
     * AJAX: 获取仪表盘数据。
     */
        public static function ajax_dashboard() { return Linked3_COS_Ajax_Manage::ajax_dashboard(); }

    /**
     * AJAX: 获取 Skill 列表。
     */
        public static function ajax_skills() { return Linked3_COS_Ajax_Manage::ajax_skills(); }

    /**
     * AJAX: 获取演化归档。
     */
    public static function ajax_archive(): void
    {
        check_ajax_referer('linked3_cos', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }

        if (!class_exists('\\Linked3\\Classes\\CognitiveOS\\Storage\\Linked3_COS_Evolution_Archive')) {
            wp_send_json_error(['message' => __('归档系统未加载', 'linked3')], 500);
        }

        try {
            $recent = \Linked3\Classes\CognitiveOS\Storage\Linked3_COS_Evolution_Archive::recent(20);
            $stats  = \Linked3\Classes\CognitiveOS\Storage\Linked3_COS_Evolution_Archive::stats();
            wp_send_json_success(['recent' => $recent, 'stats' => $stats]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: 调用单个杠杆 — v20.4: 传入 problem/approach/steps 做真实审查。
     */
    public static function ajax_run_lever(): void
    {
        check_ajax_referer('linked3_cos', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }

        // v20.4-fix25: 超时调整 — 配合动态timeout(后期杠杆45s)
        @set_time_limit(60);
        @ini_set('max_execution_time', '60');

        $lever_id = isset($_POST['lever_id']) ? sanitize_key(wp_unslash($_POST['lever_id'])) : '';

        // v20.4: 从 POST 解析 input (problem, approach, steps, skill_name)
        $input = [];
        if (isset($_POST['problem'])) {
            $input['problem'] = sanitize_textarea_field(wp_unslash($_POST['problem']));
        }
        if (isset($_POST['approach'])) {
            $input['approach'] = sanitize_textarea_field(wp_unslash($_POST['approach']));
        }
        if (isset($_POST['steps'])) {
            $input['steps'] = sanitize_textarea_field(wp_unslash($_POST['steps']));
        }
        if (isset($_POST['accumulated_analysis'])) {
            $input['accumulated_analysis'] = sanitize_textarea_field(wp_unslash($_POST['accumulated_analysis']));
        }

        // 如果传了 skill_name, 从 Skill 库加载 approach/steps
        if (isset($_POST['skill_name']) && empty($input['approach'])) {
            $skill_name = sanitize_key($_POST['skill_name']);
            if (class_exists('\\Linked3\\Classes\\CognitiveOS\\Storage\\Linked3_COS_Skill_Library')) {
                $skill = \Linked3\Classes\CognitiveOS\Storage\Linked3_COS_Skill_Library::get($skill_name);
                if ($skill) {
                    $input['approach'] = $input['approach'] ?? ($skill['mvp_approach'] ?? '');
                    $input['steps']    = $input['steps']    ?? ($skill['mvp_steps'] ?? '');
                    $input['problem']  = $input['problem']  ?? ($skill['problem'] ?? '');
                }
            }
        }

        if (empty($lever_id)) {
            wp_send_json_error(['message' => __('杠杆 ID 不能为空', 'linked3')], 400);
        }

        if (!class_exists('\\Linked3\\Classes\\CognitiveOS\\Linked3_COS_Engine')) {
            wp_send_json_error(['message' => __('COS 引擎未加载', 'linked3')], 500);
        }

        try {
            $engine = \Linked3\Classes\CognitiveOS\Linked3_COS_Engine::instance();
            $result = $engine->run_lever($lever_id, $input);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: 杠杆链 — v20.4 新增, 串联多个杠杆做深度认知审查。
     *
     * 前一个杠杆的分析输出作为后一个杠杆的输入 (accumulated_analysis),
     * 形成真正的"链式增强", 而非各自独立输出 trace。
     *
     * v20.4-fix10: 此端点已废弃 (DEPRECATED)。前端改为分块串行调用 ajax_run_lever,
     * 每个杠杆一个 AJAX 请求, 避免单个请求超过 web server / PHP-FPM 超时。
     * 此端点保留仅为向后兼容 (如有外部代码直接调用)。
     */
    public static function ajax_chain_levers(): void
    {
        check_ajax_referer('linked3_cos', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }

        // v20.4-fix10: 降低超时从 300s → 120s, 并在响应中提示已废弃
        // 注意: 如果杠杆数 > 2, 此端点仍可能超时, 请使用前端分块串行模式
        @set_time_limit(120);
        @ini_set('max_execution_time', '120');

        // 解析杠杆列表
        $lever_ids = [];
        if (isset($_POST['levers']) && is_string($_POST['levers'])) {
            $decoded = json_decode(wp_unslash($_POST['levers']), true);
            if (is_array($decoded)) {
                $lever_ids = array_map('sanitize_key', $decoded);
            }
        } elseif (isset($_POST['levers']) && is_array($_POST['levers'])) {
            $lever_ids = array_map('sanitize_key', $_POST['levers']);
        }

        if (empty($lever_ids)) {
            wp_send_json_error(['message' => __('请至少选择一个杠杆', 'linked3')], 400);
        }

        // 解析输入上下文
        $input = [];
        if (isset($_POST['problem'])) {
            $input['problem'] = sanitize_textarea_field(wp_unslash($_POST['problem']));
        }
        if (isset($_POST['approach'])) {
            $input['approach'] = sanitize_textarea_field(wp_unslash($_POST['approach']));
        }
        if (isset($_POST['steps'])) {
            $input['steps'] = sanitize_textarea_field(wp_unslash($_POST['steps']));
        }

        // 如果传了 skill_name, 从 Skill 库加载
        if (isset($_POST['skill_name']) && empty($input['approach'])) {
            $skill_name = sanitize_key($_POST['skill_name']);
            if (class_exists('\\Linked3\\Classes\\CognitiveOS\\Storage\\Linked3_COS_Skill_Library')) {
                $skill = \Linked3\Classes\CognitiveOS\Storage\Linked3_COS_Skill_Library::get($skill_name);
                if ($skill) {
                    $input['approach'] = $skill['mvp_approach'] ?? '';
                    $input['steps']    = $skill['mvp_steps'] ?? '';
                    $input['problem']  = $input['problem'] ?? ($skill['problem'] ?? '');
                }
            }
        }

        if (empty($input['approach']) && empty($input['problem'])) {
            wp_send_json_error(['message' => __('缺少审查对象: 请提供 problem 或 skill_name', 'linked3')], 400);
        }

        if (!class_exists('\\Linked3\\Classes\\CognitiveOS\\Linked3_COS_Engine')) {
            wp_send_json_error(['message' => __('COS 引擎未加载', 'linked3')], 500);
        }

        try {
            $engine = \Linked3\Classes\CognitiveOS\Linked3_COS_Engine::instance();
            $result = $engine->chain_levers($lever_ids, $input);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: 删除一个 Skill。
     */
    public static function ajax_delete_skill(): void
    {
        check_ajax_referer('linked3_cos', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }

        $name = isset($_POST['name']) ? sanitize_key(wp_unslash($_POST['name'])) : '';
        if (empty($name)) {
            wp_send_json_error(['message' => __('Skill 名称不能为空', 'linked3')], 400);
        }

        if (!class_exists('\\Linked3\\Classes\\CognitiveOS\\Storage\\Linked3_COS_Skill_Library')) {
            wp_send_json_error(['message' => __('Skill 库未加载', 'linked3')], 500);
        }

        try {
            $ok = \Linked3\Classes\CognitiveOS\Storage\Linked3_COS_Skill_Library::delete($name);
            wp_send_json_success(['deleted' => $ok]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: 应用一个 Skill — 生成可用的 system_prompt 注入到内容生成模块。
     *
     * 这是 COS 的"出口" — 演化结晶的 Skill 转化为实际可用的提示词,
     * 注入到小红书/SEO/长文/视频等生成器中。
     */
    public static function ajax_apply_skill(): void
    {
        check_ajax_referer('linked3_cos', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }

        $name      = isset($_POST['name']) ? sanitize_key(wp_unslash($_POST['name'])) : '';
        $task_type = isset($_POST['task_type']) ? sanitize_key(wp_unslash($_POST['task_type'])) : 'xhs_generate';

        if (empty($name)) {
            wp_send_json_error(['message' => __('Skill 名称不能为空', 'linked3')], 400);
        }

        if (!class_exists('\\Linked3\\Classes\\CognitiveOS\\Storage\\Linked3_COS_Skill_Library')) {
            wp_send_json_error(['message' => __('Skill 库未加载', 'linked3')], 500);
        }

        try {
            $skill = \Linked3\Classes\CognitiveOS\Storage\Linked3_COS_Skill_Library::get($name);
            if (!$skill) {
                wp_send_json_error(['message' => __('Skill 不存在', 'linked3')], 404);
            }

            // 增加使用次数
            \Linked3\Classes\CognitiveOS\Storage\Linked3_COS_Skill_Library::increment_usage($name);

            // 构造 system_prompt — v20.4: 注入完整方案 + 步骤 + 规则
            $rules    = $skill['rules'] ?? [];
            $approach = $skill['mvp_approach'] ?? '';
            $steps    = $skill['mvp_steps'] ?? '';
            $problem  = $skill['problem'] ?? '';
            $fitness  = $skill['fitness'] ?? 0;
            // v20.4-fix3: 处理空字符串 domain (旧 Skill 可能存了空串)
            $domain   = !empty($skill['domain']) ? $skill['domain'] : 'general';

            // v20.4-fix3: 旧 Skill 数据兼容 — 如果 approach 为空但 rules 有旧占位文本,
            // 尝试从 rules[0] 提取 approach (旧格式: rules = [approach])
            if (empty($approach) && !empty($rules) && is_string($rules[0])) {
                $approach = $rules[0];
            }
            // 如果 rules 是旧格式 (只有1条且是占位文本), 重新提取
            if (count($rules) <= 1 && !empty($approach)) {
                $rules = \Linked3\Classes\CognitiveOS\Core\Linked3_COS_Departments::extract_rules([
                    'approach' => $approach,
                    'steps'    => $steps,
                ]);
            }

            // v20.4-fix23: Skill应用prompt也增加超级Prompt双层壳+纳什均衡
            $prompt  = "你是一个经过认知操作系统 (COS) 三代演化验证的「{$domain}」领域专家。\n\n";
            $prompt .= "<rules>\n";
            $prompt .= "输出≤3×原始 | 装饰≤20% | 核心目标不偏离 | 规则不可违\n";
            $prompt .= "公理刚性：需求必由[信息熵减]+[系统降维]推导 | 证伪至死：风险>8或可行<4直接抹杀\n";
            $prompt .= "纳什均衡：信息密度与系统降维的平衡点 | 用户目的性优先于技术优雅\n";
            $prompt .= "落地性：每条建议必须含具体操作步骤或工具示例, 禁止抽象方向\n";
            $prompt .= "</rules>\n\n";
            $prompt .= "你的方案经过 FP→EX→C→O→A 五部门流水线筛选, 从 10 个候选方案中经三代演化锁定为最优解 (MVP, 适应度 {$fitness})。\n\n";
            $prompt .= "## 原始问题\n{$problem}\n\n";
            $prompt .= "## 最优方案 (MVP)\n{$approach}\n\n";
            if (!empty($steps)) {
                $prompt .= "## 执行步骤\n{$steps}\n\n";
            }
            $prompt .= "## 固化规则 (经演化验证, 必须遵守)\n";
            if (!empty($rules)) {
                foreach ($rules as $i => $rule) {
                    $prompt .= ($i + 1) . ". " . $rule . "\n";
                }
            } else {
                $prompt .= "1. 严格遵循上述最优方案的执行步骤\n";
            }
            $prompt .= "\n## 工作要求\n";
            $prompt .= "<answer_operator>\n";
            $prompt .= "Analyze → Synthesize(纳什均衡) → Recommend(可落地) → Verify(用户价值) → Execute\n";
            $prompt .= "</answer_operator>\n";
            $prompt .= "1. 基于以上经过验证的方案和规则, 完成用户的内容生成任务\n";
            $prompt .= "2. 不得偏离固化规则, 如遇冲突以规则为准\n";
            $prompt .= "3. 输出需符合原始问题的领域特征和目标约束\n";
            $prompt .= "4. 始终以用户目的为锚点, 输出必须可落地执行\n";
            $prompt .= "5. 在信息密度与系统降维之间找到纳什均衡点\n";

            wp_send_json_success([
                'skill_name'    => $name,
                'task_type'      => $task_type,
                'system_prompt' => $prompt,
                'fitness'       => $fitness,
                'usage_count'   => ($skill['usage_count'] ?? 0) + 1,
                'approach_preview' => mb_substr($approach, 0, 200),
                'rules_count'   => count($rules),
                'message'       => __('Skill 已应用, system_prompt 已生成 (含完整方案+步骤+规则), 可复制到对应生成器使用', 'linked3-ai'),
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: 获取 COS 使用 SOP (引导式工作流)。
     */
    public static function ajax_get_sop(): void
    {
        check_ajax_referer('linked3_cos', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }

        $sop = [
            [
                'step'    => 1,
                'title'   => '提出问题',
                'icon'    => '🎯',
                'action'  => '在"演化控制台"输入你要解决的认知问题',
                'example' => '如何用AI做小红书电商选品 / 如何写一篇高转化率的SEO文章 / 如何设计一个视频脚本',
                'tip'     => '问题越具体, 演化越精准。建议包含: 领域 + 目标 + 约束',
            ],
            [
                'step'    => 2,
                'title'   => '启动三代演化',
                'icon'    => '🔄',
                'action'  => '点击"启动演化", COS 自动运行 FP→EX→C→O→A 五部门流水线',
                'example' => 'G1 生成10个方案→绞杀弱者→G2 交叉变异→G3 终极坍缩→锁定MVP',
                'tip'     => '每代约 2-5 秒, 三代共 6-15 秒。演化过程全自动, 无需干预',
            ],
            [
                'step'    => 3,
                'title'   => '查看结晶 Skill',
                'icon'    => '💎',
                'action'  => '演化成功后, 最优方案自动结晶为 Skill, 保存在 Skill 库',
                'example' => 'Skill 包含: 原始问题 + MVP方案 + 固化规则 + 适应度',
                'tip'     => '适应度越高, 方案越优。多次演化同一问题可提升适应度',
            ],
            [
                'step'    => 4,
                'title'   => '应用 Skill 到生成器',
                'icon'    => '🚀',
                'action'  => '点击 Skill 的"应用"按钮, 生成 system_prompt',
                'example' => 'system_prompt 可注入到: 小红书生成器 / SEO文章 / 长文写作 / 视频脚本',
                'tip'     => '应用后 Skill 使用次数 +1, 适应度会随使用次数提升',
            ],
            [
                'step'    => 5,
                'title'   => '杠杆链增强 (可选)',
                'icon'    => '🔗',
                'action'  => '串联多个认知杠杆, 对方案做深度审查',
                'example' => '元学习→逻辑学→元批判→问题发现→元抽象→元评估',
                'tip'     => '杠杆链用于"二次审查", 不是必选步骤。适合高风险决策',
            ],
        ];

        wp_send_json_success(['sop' => $sop]);
    }
}

// 延迟注册 — 在 init 钩子中注册 AJAX 端点
add_action('init', [Linked3_COS_Ajax::class, 'register'], 30);
