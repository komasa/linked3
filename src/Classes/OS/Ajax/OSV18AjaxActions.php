<?php

declare(strict_types=1);
/**
 * OS V18 AJAX Actions v27.8.13
 *
 * 补齐 tab-v18 的 6 个 AJAX 端点:
 *   - linked3_ruliu_plan:      100天计划生成
 *   - linked3_ruliu_status:    计划状态查询
 *   - linked3_ruliu_update:    更新进度
 *   - linked3_nengzhi_detect:  认知层级检测
 *   - linked3_nengzhi_stages:  三阶说明加载
 *   - linked3_frequency_assign: 视觉频率分配
 *
 * @package Linked3\Classes\OS\Ajax
 * @since   27.8.13
 */

namespace Linked3\Classes\OS\Ajax;

if (!defined('ABSPATH')) exit;

class OSV18AjaxActions
{
    /**
     * 注册所有 AJAX 端点。
     */
    public static function register(): void
    {
        // 入流 (100天计划)
        add_action('wp_ajax_linked3_ruliu_plan',       [__CLASS__, 'ruliu_plan']);
        add_action('wp_ajax_linked3_ruliu_status',     [__CLASS__, 'ruliu_status']);
        add_action('wp_ajax_linked3_ruliu_update',     [__CLASS__, 'ruliu_update']);
        // 认知层级检测
        add_action('wp_ajax_linked3_nengzhi_detect',   [__CLASS__, 'nengzhi_detect']);
        add_action('wp_ajax_linked3_nengzhi_stages',   [__CLASS__, 'nengzhi_stages']);
        // 视觉频率分配
        add_action('wp_ajax_linked3_frequency_assign', [__CLASS__, 'frequency_assign']);
    }

    /**
     * 统一权限验证 — nonce + capability。
     * v27.8.13: 前端 tab-v18.php 使用 'linked3_content_writer' nonce action。
     */
    private static function verify(): void
    {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
    }

    // ── 入流 (100天计划) ──────────────────────────────────────────

    /**
     * 生成 100 天入流计划。
     * POST: profession (职业), track (赛道, 可选), goal (目标, 可选), platform (平台, 可选)
     */
    public static function ruliu_plan(): void
    {
        self::verify();

        $profession = sanitize_text_field($_POST['profession'] ?? '');
        $track      = sanitize_text_field($_POST['track'] ?? 'general');
        $goal       = sanitize_text_field($_POST['goal'] ?? '');
        $platform   = sanitize_text_field($_POST['platform'] ?? '');
        $current_day = absint($_POST['current_day'] ?? 1);

        if (empty($profession)) {
            wp_send_json_error(['message' => __('请输入职业', 'linked3')], 400);
        }

        // 生成 100 天计划
        $plan = self::generate_ruliu_plan($profession, $track, $goal, $platform);

        // 保存到 user_meta
        $user_id = get_current_user_id();
        update_user_meta($user_id, 'linked3_ruliu_plan', $plan);
        update_user_meta($user_id, 'linked3_ruliu_profession', $profession);
        update_user_meta($user_id, 'linked3_ruliu_track', $track);

        // 计算当前状态
        $progress = get_user_meta($user_id, 'linked3_ruliu_progress', true) ?: [];
        $state = self::calculate_ruliu_state($current_day, $progress);

        wp_send_json_success([
            'plan'           => $plan,
            'profession'     => $profession,
            'track'          => $track,
            'current_state'  => $state,
        ]);
    }

    /**
     * 查询某天的计划状态。
     * POST: day
     */
    public static function ruliu_status(): void
    {
        self::verify();

        $day = absint($_POST['day'] ?? 1);
        $user_id = get_current_user_id();
        $plan = get_user_meta($user_id, 'linked3_ruliu_plan', true) ?: [];
        $progress = get_user_meta($user_id, 'linked3_ruliu_progress', true) ?: [];

        $current_day = max(1, min($day, 100));
        $today_task = $plan[$current_day] ?? null;
        $completed = $progress[$current_day] ?? false;

        $state = self::calculate_ruliu_state($current_day, $progress);

        wp_send_json_success([
            'day'              => $current_day,
            'task'             => $today_task,
            'completed'        => $completed,
            'total_completed'  => count(array_filter($progress)),
            'current_state'    => $state,
        ]);
    }

    /**
     * 更新某天的完成状态。
     * POST: day
     */
    public static function ruliu_update(): void
    {
        self::verify();

        $day = absint($_POST['day'] ?? 0);
        if ($day < 1 || $day > 100) {
            wp_send_json_error(['message' => __('天数无效', 'linked3')], 400);
        }

        $user_id = get_current_user_id();
        $progress = get_user_meta($user_id, 'linked3_ruliu_progress', true) ?: [];
        $progress[$day] = true;
        update_user_meta($user_id, 'linked3_ruliu_progress', $progress);

        $state = self::calculate_ruliu_state($day, $progress);

        wp_send_json_success([
            'day'              => $day,
            'total_completed'  => count(array_filter($progress)),
            'current_state'    => $state,
        ]);
    }

    // ── 认知层级检测 ──────────────────────────────────────────────

    /**
     * 检测文本的认知层级 (纯文本分析, 不依赖 AI)。
     * POST: content
     */
    public static function nengzhi_detect(): void
    {
        self::verify();

        $content = sanitize_textarea_field(wp_unslash($_POST['content'] ?? ''));
        if (empty($content)) {
            wp_send_json_error(['message' => __('内容为空', 'linked3')], 400);
        }

        $level = self::detect_cognitive_level($content);

        wp_send_json_success([
            'detected_stage'   => $level['level'],
            'cognitive_level'  => $level['label'],
            'indicators'       => $level['indicators'],
            'scores'           => $level['scores'],
            'confidence'       => $level['confidence'],
        ]);
    }

    /**
     * 获取认知三阶说明。
     * POST: level (可选, 默认返回全部)
     */
    public static function nengzhi_stages(): void
    {
        self::verify();

        $level = sanitize_text_field($_POST['level'] ?? '');
        $stages = self::get_cognitive_stages($level);

        wp_send_json_success([
            'stages'       => $stages,
            'three_stages' => $stages,
        ]);
    }

    // ── 视觉频率分配 ──────────────────────────────────────────────

    /**
     * 基于内容长度和模块类型分配视觉频率。
     * POST: module_type, content
     */
    public static function frequency_assign(): void
    {
        self::verify();

        $module_type = sanitize_text_field($_POST['module_type'] ?? '');
        $content = sanitize_textarea_field(wp_unslash($_POST['content'] ?? ''));

        if (empty($content)) {
            wp_send_json_error(['message' => __('内容为空', 'linked3')], 400);
        }

        $frequency = self::calculate_visual_frequency($content, $module_type);

        wp_send_json_success([
            'frequency'    => $frequency['frequency'],
            'badge_label'  => $frequency['badge_label'],
            'count'        => $frequency['count'],
            'spacing'      => $frequency['spacing'],
            'module_type'  => $module_type,
        ]);
    }

    // ── 内部实现 ──────────────────────────────────────────────────

    /**
     * 生成 100 天入流计划。
     */
    private static function generate_ruliu_plan(string $profession, string $track, string $goal, string $platform): array
    {
        $plan = [];
        $phases = [
            1 => ['label' => __('基础期 (Day 1-30)', 'linked3'), 'focus' => 'core_skills', 'desc' => __('建立核心技能基础', 'linked3')],
            2 => ['label' => __('进阶期 (Day 31-70)', 'linked3'), 'focus' => 'advanced_application', 'desc' => __('进阶应用与实战', 'linked3')],
            3 => ['label' => __('突破期 (Day 71-100)', 'linked3'), 'focus' => 'mastery_innovation', 'desc' => __('精通与创新突破', 'linked3')],
        ];

        $platform_context = $platform ? "在{$platform}平台" : '';

        for ($day = 1; $day <= 100; $day++) {
            $phase = $day <= 30 ? 1 : ($day <= 70 ? 2 : 3);
            $phase_info = $phases[$phase];

            $task_templates = [
                1 => [
                    "学习{$profession}的核心概念",
                    "练习{$track}基础技能",
                    "研究{$platform_context}行业案例",
                    "建立{$profession}知识体系",
                    "完成{$track}入门练习",
                ],
                2 => [
                    "应用{$profession}解决实际问题",
                    "优化{$track}执行流程",
                    "分析{$platform_context}数据反馈",
                    "构建{$profession}方法论",
                    "完成{$track}进阶项目",
                ],
                3 => [
                    "创新{$profession}应用场景",
                    "突破{$track}技术瓶颈",
                    "建立{$platform_context}个人品牌",
                    "输出{$profession}原创内容",
                    "完成{$track} mastery 项目",
                ],
            ];

            $templates = $task_templates[$phase];
            $task = $templates[($day - 1) % count($templates)];

            $plan[$day] = [
                'day'          => $day,
                'phase'        => $phase,
                'phase_label'  => $phase_info['label'],
                'focus'        => $phase_info['focus'],
                'desc'         => $phase_info['desc'],
                'task'         => sprintf('Day %d: %s', $day, $task),
                'est_minutes'  => $phase === 1 ? 30 : ($phase === 2 ? 45 : 60),
            ];
        }

        return $plan;
    }

    /**
     * 计算入流状态进度。
     */
    private static function calculate_ruliu_state(int $day, array $progress): array
    {
        $total_completed = count(array_filter($progress));
        $phase = $day <= 30 ? 1 : ($day <= 70 ? 2 : 3);
        $state_labels = [
            1 => '基础期',
            2 => '进阶期',
            3 => '突破期',
        ];

        // 阶段内进度
        $phase_ranges = [1 => [1, 30], 2 => [31, 70], 3 => [71, 100]];
        [$phase_start, $phase_end] = $phase_ranges[$phase];
        $phase_total_days = $phase_end - $phase_start + 1;
        $day_in_state = $day - $phase_start + 1;

        // 统计本阶段已完成天数
        $phase_completed = 0;
        for ($d = $phase_start; $d <= $phase_end; $d++) {
            if (!empty($progress[$d])) $phase_completed++;
        }

        return [
            'state'               => 'phase_' . $phase,
            'state_label'         => $state_labels[$phase],
            'current_state'       => $state_labels[$phase],
            'day'                 => $day,
            'day_in_state'        => $day_in_state,
            'state_total_days'    => $phase_total_days,
            'overall_progress_pct' => intval($total_completed / 100 * 100),
            'state_progress_pct'  => intval($phase_completed / $phase_total_days * 100),
        ];
    }

    /**
     * 检测文本的认知层级 (纯文本分析)。
     */
    private static function detect_cognitive_level(string $content): array
    {
        $len = mb_strlen($content);
        $has_question   = (bool) preg_match('/[?？]/', $content);
        $has_reasoning  = (bool) preg_match('/因为|所以|因此|由于|导致|从而|于是/', $content);
        $has_synthesis  = (bool) preg_match('/综上|总之|本质上|归根结底|底层逻辑|核心在于|关键是/', $content);
        $has_meta       = (bool) preg_match('/反思|元认知|自我觉察|批判.*自己|我意识到|我认识到/', $content);

        $scores = [
            'stage_1' => 0,
            'stage_2' => 0,
            'stage_3' => 0,
        ];

        // L1 感知层: 基础信息接收
        $scores['stage_1'] = $len > 50 ? 3 : 1;
        // L2 提问层: 主动提问
        $scores['stage_2'] = $has_question ? 5 : 0;
        // L3 推理层: 因果推理
        if ($has_reasoning) $scores['stage_2'] += 2;
        // L4 综合层: 跨域综合
        $scores['stage_3'] = $has_synthesis ? 5 : 0;
        // L5 元认知层: 自我反思
        if ($has_meta) $scores['stage_3'] += 3;

        // 确定层级
        if ($has_meta) {
            $level = 'stage_3';
            $label = __('三阶·元认知', 'linked3');
            $indicators = ['自我反思', '元认知', '认知监控'];
            $confidence = __('高', 'linked3');
        } elseif ($has_synthesis) {
            $level = 'stage_3';
            $label = __('三阶·综合', 'linked3');
            $indicators = ['跨域综合', '本质抽象', '范式创新'];
            $confidence = __('高', 'linked3');
        } elseif ($has_reasoning) {
            $level = 'stage_2';
            $label = __('二阶·推理', 'linked3');
            $indicators = ['因果推理', '逻辑链', '系统思考'];
            $confidence = __('中', 'linked3');
        } elseif ($has_question) {
            $level = 'stage_2';
            $label = __('二阶·提问', 'linked3');
            $indicators = ['主动提问', '假设构建'];
            $confidence = __('中', 'linked3');
        } else {
            $level = 'stage_1';
            $label = __('一阶·感知', 'linked3');
            $indicators = ['信息接收', '模式识别'];
            $confidence = __('低', 'linked3');
        }

        return [
            'level'      => $level,
            'label'      => $label,
            'indicators' => $indicators,
            'scores'     => $scores,
            'confidence' => $confidence,
        ];
    }

    /**
     * 获取认知三阶说明。
     */
    private static function get_cognitive_stages(string $level): array
    {
        $stages = [
            'stage_1' => [
                'name' => __('一阶·感知层', 'linked3'),
                'desc' => __('信息接收与模式识别', 'linked3'),
                'practices' => [
                    ['name' => __('信息获取', 'linked3'), 'desc' => __('主动收集多源信息', 'linked3')],
                    ['name' => __('模式识别', 'linked3'), 'desc' => __('识别重复出现的结构', 'linked3')],
                    ['name' => __('分类归纳', 'linked3'), 'desc' => __('将信息按维度分类', 'linked3')],
                ],
            ],
            'stage_2' => [
                'name' => __('二阶·推理层', 'linked3'),
                'desc' => __('提问驱动与因果建模', 'linked3'),
                'practices' => [
                    ['name' => __('提问驱动', 'linked3'), 'desc' => __('用问题引导思考方向', 'linked3')],
                    ['name' => __('假设构建', 'linked3'), 'desc' => __('提出可验证的假设', 'linked3')],
                    ['name' => __('因果建模', 'linked3'), 'desc' => __('构建因果关系图', 'linked3')],
                    ['name' => __('验证修正', 'linked3'), 'desc' => __('通过实践验证假设', 'linked3')],
                ],
            ],
            'stage_3' => [
                'name' => __('三阶·综合层', 'linked3'),
                'desc' => __('跨域迁移与元认知', 'linked3'),
                'practices' => [
                    ['name' => __('跨域迁移', 'linked3'), 'desc' => __('将模型应用到新领域', 'linked3')],
                    ['name' => __('本质抽象', 'linked3'), 'desc' => __('提取底层不变的结构', 'linked3')],
                    ['name' => __('范式创新', 'linked3'), 'desc' => __('创造新的认知框架', 'linked3')],
                    ['name' => __('认知监控', 'linked3'), 'desc' => __('觉察自己的认知过程', 'linked3')],
                    ['name' => __('偏见矫正', 'linked3'), 'desc' => __('识别并修正认知偏差', 'linked3')],
                ],
            ],
        ];

        if (!empty($level) && isset($stages[$level])) {
            return [$level => $stages[$level]];
        }
        return $stages;
    }

    /**
     * 计算视觉频率。
     */
    private static function calculate_visual_frequency(string $content, string $module_type): array
    {
        $len = mb_strlen($content);
        $density = $len / 100; // 每 100 字

        // 基于模块类型和内容密度计算
        $count = 0;
        $frequency = 'MF'; // 默认中频
        $badge_label = __('中频·逻辑/方法', 'linked3');

        if ($module_type === 'diagram' || $module_type === 'chart') {
            $count = max(1, intval($density * 0.25));
            $frequency = 'MF';
            $badge_label = __('中频·逻辑/方法', 'linked3');
        } elseif ($module_type === 'icon') {
            $count = max(2, intval($density * 0.5));
            $frequency = 'HF';
            $badge_label = __('高频·洞察/灵感', 'linked3');
        } elseif ($module_type === 'image') {
            $count = max(1, intval($density * 0.15));
            $frequency = 'LF';
            $badge_label = __('低频·信息/背景', 'linked3');
        } else {
            $count = max(1, intval($density * 0.25));
            // 根据内容特征判断频率
            if (preg_match('/洞察|灵感|启发|顿悟/', $content)) {
                $frequency = 'HF';
                $badge_label = __('高频·洞察/灵感', 'linked3');
            } elseif (preg_match('/背景|信息|数据|事实/', $content)) {
                $frequency = 'LF';
                $badge_label = __('低频·信息/背景', 'linked3');
            }
        }

        return [
            'frequency'    => $frequency,
            'badge_label'  => $badge_label,
            'count'        => $count,
            'spacing'      => $count > 0 ? intval(100 / $count) : 100,
        ];
    }
}
