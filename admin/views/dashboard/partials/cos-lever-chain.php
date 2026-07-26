<?php
/**
 * Partial: cos-lever-chain
 * Extracted from: tab-cognitive-os.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
    <!-- ═══════════════════════════════════════════════════════════════
         STEP 5: 杠杆链调用 — 深度认知审查 (可选)
    ═══════════════════════════════════════════════════════════════ -->
    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
            <span style="background: #ec4899; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;"><?php echo esc_html__('STEP 5 · 可选', 'linked3'); ?></span>
            <h2 style="margin: 0; font-size: 16px; font-weight: 600; color: #1f2937; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 20px;">🔗</span> 杠杆链调用 — 深度认知审查
            </h2>
        </div>
        <p style="margin: 0 0 12px; font-size: 12px; color: #6b7280;">
            <strong><?php echo esc_html__('这是什么:', 'linked3'); ?></strong><?php echo esc_html__('串联多个认知杠杆 (元学习/逻辑学/元批判等), 对方案做深度审查。每个杠杆注入一段 system_prompt, 教 AI "怎么思考"。', 'linked3'); ?><br>
            <strong><?php echo esc_html__('什么时候用:', 'linked3'); ?></strong><?php echo esc_html__('高风险决策 (如: 选品投入、内容方向、商业策略) 时, 用杠杆链做二次审查, 避免认知偏差。', 'linked3'); ?><br>
            <strong><?php echo esc_html__('怎么用:', 'linked3'); ?></strong> 勾选要调用的杠杆, 点击"运行杠杆链", 查看每个杠杆的 trace 字段。
        </p>
        <!-- v20.4-fix25: 手动场景选择器 -->
        <div style="margin-bottom: 12px; padding: 10px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px;">
            <div style="font-size: 12px; font-weight: 600; color: #0369a1; margin-bottom: 6px;"><?php echo esc_html__('🎯 场景适配选择器', 'linked3'); ?></div>
            <div style="font-size: 10px; color: #6b7280; margin-bottom: 8px;"><?php echo esc_html__('选择场景后自动勾选最匹配的6个杠杆组合。也可手动勾选下方杠杆自定义组合。', 'linked3'); ?></div>
            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                <button type="button" class="cos-scene-btn" data-scene="auto" style="padding: 5px 12px; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-size: 11px; cursor: pointer;"><?php echo esc_html__('🤖 自动适配', 'linked3'); ?></button>
                <button type="button" class="cos-scene-btn" data-scene="ecommerce" style="padding: 5px 12px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 11px; cursor: pointer;"><?php echo esc_html__('🛒 电商选品', 'linked3'); ?></button>
                <button type="button" class="cos-scene-btn" data-scene="content" style="padding: 5px 12px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 11px; cursor: pointer;"><?php echo esc_html__('✍️ 内容创作', 'linked3'); ?></button>
                <button type="button" class="cos-scene-btn" data-scene="tech" style="padding: 5px 12px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 11px; cursor: pointer;"><?php echo esc_html__('⚙️ 技术架构', 'linked3'); ?></button>
                <button type="button" class="cos-scene-btn" data-scene="strategy" style="padding: 5px 12px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 11px; cursor: pointer;"><?php echo esc_html__('🎯 商业策略', 'linked3'); ?></button>
                <button type="button" class="cos-scene-btn" data-scene="audit" style="padding: 5px 12px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 11px; cursor: pointer;"><?php echo esc_html__('🔍 深度审查', 'linked3'); ?></button>
                <button type="button" class="cos-scene-btn" data-scene="innovation" style="padding: 5px 12px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 11px; cursor: pointer;"><?php echo esc_html__('💡 创新突破', 'linked3'); ?></button>
                <button type="button" class="cos-scene-btn" data-scene="risk" style="padding: 5px 12px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 11px; cursor: pointer;"><?php echo esc_html__('🛡️ 风险防御', 'linked3'); ?></button>
            </div>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px;" id="cos-lever-chain">
            <?php
            // v20.4-fix20: 从 MetaLever Registry 动态获取杠杆列表, 按6大能力域分组显示
            $levers_for_chain = [];
            $levers_by_domain = [];
            if (class_exists('\\Linked3\\Classes\\MetaLever\\MetaLeverRegistry')) {
                $all_levers = \Linked3\Classes\MetaLever\MetaLeverRegistry::info();
                foreach ($all_levers as $l) {
                    $levers_for_chain[] = [
                        'id'    => $l['id'],
                        'label' => $l['label'],
                        'description' => $l['description'] ?? '',
                        'domain' => $l['domain'] ?? 'cognitive',
                        'domain_label' => $l['domain_label'] ?? '🔍 认知与元认知',
                    ];
                    $domain_key = $l['domain'] ?? 'cognitive';
                    if (!isset($levers_by_domain[$domain_key])) {
                        $levers_by_domain[$domain_key] = [
                            'label' => $l['domain_label'] ?? '🔍 认知与元认知',
                            'levers' => [],
                        ];
                    }
                    $levers_by_domain[$domain_key]['levers'][] = $l;
                }
            }
            if (empty($levers_for_chain)) {
                $levers_for_chain = [
                    ['id' => 'meta_learning', 'label' => __('元学习', 'linked3'), 'description' => __('从示例提取可迁移模式', 'linked3'), 'domain' => 'cognitive', 'domain_label' => __('🔍 认知与元认知', 'linked3')],
                    ['id' => 'meta_logic', 'label' => __('逻辑学', 'linked3'), 'description' => __('演绎/归纳/溯因推理', 'linked3'), 'domain' => 'logic', 'domain_label' => __('🧠 逻辑与推理', 'linked3')],
                    ['id' => 'meta_critique', 'label' => __('元批判', 'linked3'), 'description' => __('红队攻击+证伪测试', 'linked3'), 'domain' => 'logic', 'domain_label' => __('🧠 逻辑与推理', 'linked3')],
                    ['id' => 'meta_problem_finding', 'label' => __('问题发现', 'linked3'), 'description' => __('问题质疑+根因追问', 'linked3'), 'domain' => 'logic', 'domain_label' => __('🧠 逻辑与推理', 'linked3')],
                    ['id' => 'meta_abstraction', 'label' => __('元抽象', 'linked3'), 'description' => __('从案例提取通用模型', 'linked3'), 'domain' => 'analytical', 'domain_label' => __('📊 分析与评估', 'linked3')],
                    ['id' => 'meta_evaluation', 'label' => __('元评估', 'linked3'), 'description' => __('多维评分+基线对比', 'linked3'), 'domain' => 'analytical', 'domain_label' => __('📊 分析与评估', 'linked3')],
                ];
                $levers_by_domain = [
                    'cognitive' => ['label' => __('🔍 认知与元认知', 'linked3'), 'levers' => [$levers_for_chain[0]]],
                    'logic' => ['label' => __('🧠 逻辑与推理', 'linked3'), 'levers' => [$levers_for_chain[1], $levers_for_chain[2], $levers_for_chain[3]]],
                    'analytical' => ['label' => __('📊 分析与评估', 'linked3'), 'levers' => [$levers_for_chain[4], $levers_for_chain[5]]],
                ];
            }
            $default_checked = ['meta_essence', 'meta_critique', 'meta_evaluation', 'meta_socratic', 'meta_questioning', 'meta_execution'];
            if (class_exists('\\Linked3\\Classes\\MetaLever\\MetaLeverRegistry')) {
                $all_info = \Linked3\Classes\MetaLever\MetaLeverRegistry::info();
                if (!empty($all_info) && count($all_info) >= 6) {
                    $default_checked = array_slice(array_column($all_info, 'id'), 0, 6);
                }
            }

            // v20.4-fix20: 按能力域分组渲染, 每组有标题+注释
            $domain_colors = [
                'cognitive' => '#e0f2fe',
                'logic' => '#fef3c7',
                'creative' => '#fce7f3',
                'analytical' => '#e0e7ff',
                'strategic' => '#dcfce7',
                'communication' => '#f3e8ff',
            ];
            foreach ($levers_by_domain as $domain_key => $domain_data):
                $bg_color = $domain_colors[$domain_key] ?? '#f3f4f6';
            ?>
            <div style="width: 100%; margin-bottom: 8px;">
                <div style="font-size: 11px; font-weight: 700; color: #374151; margin-bottom: 4px; padding: 2px 8px; background: <?php echo esc_attr($bg_color); ?>; border-radius: 4px; display: inline-block;">
                    <?php echo esc_html($domain_data['label']); ?>
                </div>
                <div style="display: flex; gap: 6px; flex-wrap: wrap; padding-left: 8px;">
                <?php foreach ($domain_data['levers'] as $l):
                    $lid = $l['id'];
                    $llabel = $l['label'];
                    $ldesc = $l['description'] ?? '';
                ?>
                    <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #f3f4f6; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr($llabel . ' — ' . $ldesc); ?>">
                        <input type="checkbox" value="<?php echo esc_attr($lid); ?>" class="cos-lever-checkbox" style="margin: 0;" <?php echo in_array($lid, $default_checked, true) ? 'checked' : ''; ?>>
                        <?php echo esc_html($llabel); ?>
                    </label>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- v20.4-fix22: 复合杠杆区域 — 可勾选+全局可视+编排详情 -->
        <div style="margin-top: 12px; padding: 10px; background: #fafafa; border: 1px solid #e5e7eb; border-radius: 8px;">
            <div style="font-size: 12px; font-weight: 700; color: #1f2937; margin-bottom: 4px;"><?php echo esc_html__('⚡ 复合杠杆 (高级编排能力 — 17个)', 'linked3'); ?><span style="font-size: 10px; font-weight: 400; color: #6b7280;"><?php echo esc_html__('— 勾选后参与杠杆链，编排多个基础杠杆形成完整部门工作流', 'linked3'); ?></span></div>
            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fef3c7; border: 1px solid #fde68a; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('去AI味五部门 (适应度:20) | 编排: 本质追问→反向→批判→质疑→落地 | 场景: 去AI味/人类化/反检测', 'linked3'); ?>">
                    <input type="checkbox" value="deai_5d" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🛡️ 去AI味五部门 <span class="lever-fitness" data-default="20" style="font-size: 9px; color: #92400e;"><?php echo esc_html__('适应度20', 'linked3'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #dbeafe; border: 1px solid #93c5fd; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('创世演化 (适应度:21) | 编排: 本质→创造→批判→质疑→评估 | 场景: 方案生成/MVP锁定', 'linked3'); ?>">
                    <input type="checkbox" value="genesis" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🌟 创世演化 <span class="lever-fitness" data-default="21" style="font-size: 9px; color: #1e40af;"><?php echo esc_html__('适应度21', 'linked3'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #dcfce7; border: 1px solid #86efac; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('深度谋划 (适应度:19) | 编排: 谋划→系统→反向→动态→压力测试 | 场景: 商业策略/博弈推演', 'linked3'); ?>">
                    <input type="checkbox" value="deep_strategy" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🎯 深度谋划 <span class="lever-fitness" data-default="19" style="font-size: 9px; color: #166534;"><?php echo esc_html__('适应度19', 'linked3'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fce7f3; border: 1px solid #f9a8d4; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('跨界创新 (适应度:18) | 编排: 跨界→隐喻→压力测试→折叠→反向 | 场景: 产品创新/跨界颠覆', 'linked3'); ?>">
                    <input type="checkbox" value="cross_innovation" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🎨 跨界创新 <span class="lever-fitness" data-default="18" style="font-size: 9px; color: #9f1239;"><?php echo esc_html__('适应度18', 'linked3'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #e0e7ff; border: 1px solid #a5b4fc; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('苏格拉底审查 (适应度:19) | 编排: 苏格拉底→质疑→本质→反向→评估 | 场景: 深度审查/批判分析', 'linked3'); ?>">
                    <input type="checkbox" value="socratic_review" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🔍 苏格拉底审查 <span class="lever-fitness" data-default="19" style="font-size: 9px; color: #3730a3;"><?php echo esc_html__('适应度19', 'linked3'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fef9c3; border: 1px solid #fde047; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('超级Prompt转换器 (适应度:20) | 编排: 本质→信息→设计→折叠→落地 | 场景: Prompt升级/结构化转换', 'linked3'); ?>">
                    <input type="checkbox" value="super_prompt" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    ⚡ 超级Prompt转换器 <span class="lever-fitness" data-default="20" style="font-size: 9px; color: #854d0e;"><?php echo esc_html__('适应度20', 'linked3'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #f3e8ff; border: 1px solid #c084fc; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('认知审计 (适应度:19) | 编排: 自我校准→逻辑→评估→认知→质疑 | 场景: 偏差检测/谬误审查', 'linked3'); ?>">
                    <input type="checkbox" value="cognitive_audit" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    📋 认知审计 <span class="lever-fitness" data-default="19" style="font-size: 9px; color: #6b21a8;"><?php echo esc_html__('适应度19', 'linked3'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #ecfdf5; border: 1px solid #6ee7b7; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('知识综合 (适应度:18) | 编排: 知识图谱→模式→类比→折叠→抽象 | 场景: 知识管理/图谱构建', 'linked3'); ?>">
                    <input type="checkbox" value="knowledge_synthesis" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    📚 知识综合 <span class="lever-fitness" data-default="18" style="font-size: 9px; color: #166534;"><?php echo esc_html__('适应度18', 'linked3'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fff7ed; border: 1px solid #fdba74; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('内容引擎 (适应度:20) | 编排: 叙事→情绪→说服力→语境→折叠 | 场景: 内容创作/小红书/视频', 'linked3'); ?>">
                    <input type="checkbox" value="content_engine" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    ✍️ 内容引擎 <span class="lever-fitness" data-default="20" style="font-size: 9px; color: #9a3412;"><?php echo esc_html__('适应度20', 'linked3'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('风险防御 (适应度:19) | 编排: 压力测试→因果→博弈→伦理→自我校准 | 场景: 风险防御/压力测试', 'linked3'); ?>">
                    <input type="checkbox" value="risk_defense" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🛡️ 风险防御 <span class="lever-fitness" data-default="19" style="font-size: 9px; color: #991b1b;"><?php echo esc_html__('适应度19', 'linked3'); ?></span>
                </label>
                <!-- v27.17.9-fix1: 补全7个缺失的复合杠杆 (10→17) -->
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('代码优化器 | 编排: 分析→重构→测试→验证→部署 | 场景: 代码审查/技术债务', 'linked3'); ?>">
                    <input type="checkbox" value="code_optimizer" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🔧 代码优化器 <span class="lever-fitness" data-default="18" style="font-size: 9px; color: #166534;"><?php echo esc_html__('适应度18', 'linked3'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fdf4ff; border: 1px solid #e9d5ff; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('创意引擎 | 编排: 联想→变异→组合→评估→迭代 | 场景: 创意生成/brainstorm', 'linked3'); ?>">
                    <input type="checkbox" value="creative_engine" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    💡 创意引擎 <span class="lever-fitness" data-default="19" style="font-size: 9px; color: #86198f;"><?php echo esc_html__('适应度19', 'linked3'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #ecfeff; border: 1px solid #a5f3fc; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('意图解码器 | 编排: 语义→上下文→情感→意图→响应 | 场景: 用户意图分析/NLU', 'linked3'); ?>">
                    <input type="checkbox" value="intent_decoder" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🎯 意图解码器 <span class="lever-fitness" data-default="18" style="font-size: 9px; color: #155e75;"><?php echo esc_html__('适应度18', 'linked3'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('质量关卡 | 编排: 规范→安全→性能→可维护→交付 | 场景: 质量保证/发布前审查', 'linked3'); ?>">
                    <input type="checkbox" value="quality_gauntlet" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    ✅ 质量关卡 <span class="lever-fitness" data-default="20" style="font-size: 9px; color: #92400e;"><?php echo esc_html__('适应度20', 'linked3'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('种子重组器 | 编排: 拆解→变异→交叉→筛选→固化 | 场景: 方案重组/进化计算', 'linked3'); ?>">
                    <input type="checkbox" value="seed_recombinator" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🧬 种子重组器 <span class="lever-fitness" data-default="19" style="font-size: 9px; color: #0369a1;"><?php echo esc_html__('适应度19', 'linked3'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fefce8; border: 1px solid #fde047; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('通用三件套 | 编排: 分析→生成→验证 | 场景: 通用任务处理', 'linked3'); ?>">
                    <input type="checkbox" value="universal_trio" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    📐 通用三件套 <span class="lever-fitness" data-default="17" style="font-size: 9px; color: #854d0e;"><?php echo esc_html__('适应度17', 'linked3'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fdf2f8; border: 1px solid #fbcfe8; border-radius: 6px; font-size: 11px; cursor: pointer;" title="<?php echo esc_attr__('写作深度 | 编排: 结构→逻辑→情感→风格→深度 | 场景: 深度写作/长文创作', 'linked3'); ?>">
                    <input type="checkbox" value="writing_depth" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    ✍️ 写作深度 <span class="lever-fitness" data-default="19" style="font-size: 9px; color: #9d174d;"><?php echo esc_html__('适应度19', 'linked3'); ?></span>
                </label>
            </div>
        </div>
        <button id="cos-run-chain-btn" type="button" style="background: #1f2937; color: #fff; border: none; padding: 8px 20px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
            ▶ 运行杠杆链
        </button>
        <button id="cos-reset-circuit-perm-btn" type="button" style="background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; padding: 8px 16px; border-radius: 6px; font-size: 12px; cursor: pointer; margin-left: 8px;" title="<?php echo esc_attr__('清除所有 AI provider 的失败计数, 让被熔断的 provider 立即恢复可用', 'linked3'); ?>">
            🔄 重置 AI 熔断器
        </button>
        <div id="cos-chain-result" style="margin-top: 12px; display: none;"></div>
    </div>

