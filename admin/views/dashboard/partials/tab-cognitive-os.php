<?php
/**
 * Dashboard partial: 🧠 认知操作系统 (v20.3)
 *
 * v20.3 重大重构 — 从"技术展示"改为"引导式工作流"
 *
 * 完整 SOP:
 *   ① 提出问题 → ② 启动演化 → ③ 查看结晶 Skill → ④ 应用 Skill → ⑤ 杠杆链审查 (可选)
 *
 * 每个区块都有:
 *   - "这是什么"说明
 *   - "怎么用"操作指引
 *   - "下一步"引导
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 */

if (!defined('ABSPATH')) {
    exit;
}

// 获取 COS 数据
$cos_overview = [];
$top_skills   = [];
$recent_evolutions = [];
if (class_exists('\\Linked3\\Classes\\CognitiveOS\\COSReporter')) {
    $reporter = new \Linked3\Classes\CognitiveOS\COSReporter();
    $cos_overview      = $reporter->dashboard_overview();
    $top_skills       = $reporter->top_skills(10);
    $recent_evolutions = $reporter->recent_evolutions(10);
}

$cos_nonce = wp_create_nonce('linked3_cos');
$ajax_url  = esc_url(admin_url('admin-ajax.php'));
?>

<div class="linked3-cos-dashboard" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 1200px;">

    <!-- ═══════════════════════════════════════════════════════════════
         顶部 Hero — COS 系统总览 + 统计
    ═══════════════════════════════════════════════════════════════ -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 28px; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
            <div>
                <h1 style="margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">
                    🧠 认知操作系统 <span style="opacity: 0.7; font-size: 14px; font-weight: 400;">Cognitive OS v27.6</span>
                    <span id="cos-patch-badge" style="font-size: 10px; background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px; margin-left: 8px; cursor: help;" title="点击查看版本诊断">检测中...</span>
                </h1>
                <p style="margin: 6px 0 0; opacity: 0.85; font-size: 13px;">
                    能够自行纠错的认知架构 — 演化验证过的方案, 直接应用到内容生成
                </p>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div style="background: rgba(255,255,255,0.15); padding: 10px 16px; border-radius: 8px; text-align: center;">
                    <div id="cos-stat-skills" style="font-size: 22px; font-weight: 700;"><?php echo esc_html((string) ($cos_overview['skill_count'] ?? 0)); ?></div>
                    <div style="font-size: 10px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px;">Skills</div>
                </div>
                <div style="background: rgba(255,255,255,0.15); padding: 10px 16px; border-radius: 8px; text-align: center;">
                    <div id="cos-stat-evolutions" style="font-size: 22px; font-weight: 700;"><?php echo esc_html((string) ($cos_overview['evolution_count'] ?? 0)); ?></div>
                    <div style="font-size: 10px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px;">演化次数</div>
                </div>
                <div style="background: rgba(255,255,255,0.15); padding: 10px 16px; border-radius: 8px; text-align: center;">
                    <div id="cos-stat-success-rate" style="font-size: 22px; font-weight: 700;"><?php echo esc_html(sprintf('%.0f%%', ($cos_overview['evolution_success_rate'] ?? 0) * 100)); ?></div>
                    <div style="font-size: 10px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px;">成功率</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         📋 使用 SOP — 5 步引导式工作流
    ═══════════════════════════════════════════════════════════════ -->
    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h2 style="margin: 0 0 12px; font-size: 16px; font-weight: 600; color: #1f2937; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 20px;">📋</span> 使用指南 — 5 步完整工作流
        </h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
            <div style="background: #f0f4ff; border-left: 3px solid #667eea; padding: 12px; border-radius: 6px;">
                <div style="font-size: 11px; color: #667eea; font-weight: 600; margin-bottom: 4px;">STEP 1 · 🎯</div>
                <div style="font-size: 13px; font-weight: 600; color: #1f2937; margin-bottom: 4px;">提出问题</div>
                <div style="font-size: 11px; color: #6b7280;">在下方输入你要解决的认知问题</div>
            </div>
            <div style="background: #f0fdf4; border-left: 3px solid #10b981; padding: 12px; border-radius: 6px;">
                <div style="font-size: 11px; color: #10b981; font-weight: 600; margin-bottom: 4px;">STEP 2 · 🔄</div>
                <div style="font-size: 13px; font-weight: 600; color: #1f2937; margin-bottom: 4px;">启动演化</div>
                <div style="font-size: 11px; color: #6b7280;">COS 自动运行三代演化, 锁定 MVP</div>
            </div>
            <div style="background: #fefce8; border-left: 3px solid #eab308; padding: 12px; border-radius: 6px;">
                <div style="font-size: 11px; color: #eab308; font-weight: 600; margin-bottom: 4px;">STEP 3 · 💎</div>
                <div style="font-size: 13px; font-weight: 600; color: #1f2937; margin-bottom: 4px;">查看 Skill</div>
                <div style="font-size: 11px; color: #6b7280;">最优方案结晶为 Skill, 保存在下方</div>
            </div>
            <div style="background: #fef3c7; border-left: 3px solid #f59e0b; padding: 12px; border-radius: 6px;">
                <div style="font-size: 11px; color: #f59e0b; font-weight: 600; margin-bottom: 4px;">STEP 4 · 🚀</div>
                <div style="font-size: 13px; font-weight: 600; color: #1f2937; margin-bottom: 4px;">应用 Skill</div>
                <div style="font-size: 11px; color: #6b7280;">点击"应用"生成 system_prompt</div>
            </div>
            <div style="background: #fdf2f8; border-left: 3px solid #ec4899; padding: 12px; border-radius: 6px;">
                <div style="font-size: 11px; color: #ec4899; font-weight: 600; margin-bottom: 4px;">STEP 5 · 🔗</div>
                <div style="font-size: 13px; font-weight: 600; color: #1f2937; margin-bottom: 4px;">杠杆链审查</div>
                <div style="font-size: 11px; color: #6b7280;">可选: 对方案做深度认知审查</div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         STEP 1+2: 演化控制台
    ═══════════════════════════════════════════════════════════════ -->
    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
            <span style="background: #667eea; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;">STEP 1+2</span>
            <h2 style="margin: 0; font-size: 16px; font-weight: 600; color: #1f2937; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 20px;">🚀</span> 演化控制台
            </h2>
        </div>
        <p style="margin: 0 0 12px; font-size: 12px; color: #6b7280;">
            <strong>这是什么:</strong> 输入一个认知问题, COS 自动运行 FP→EX→C→O→A 五部门流水线, 经历 G1→G2→G3 三代演化, 最终锁定最优方案 (MVP)。<br>
            <strong>怎么用:</strong> 填写问题描述 (越具体越好), 可选填领域, 点击"启动演化"。
        </p>
        <div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 10px; align-items: end; margin-bottom: 12px;">
            <div>
                <label style="display: block; font-size: 11px; color: #6b7280; margin-bottom: 4px; font-weight: 600;">问题描述 <span style="color: #ef4444;">*</span></label>
                <input id="cos-problem-input" type="text" value="如何用AI做小红书电商选品" placeholder="如: 如何写一篇高转化率的SEO文章" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;">
            </div>
            <div>
                <label style="display: block; font-size: 11px; color: #6b7280; margin-bottom: 4px; font-weight: 600;">领域 (可选)</label>
                <select id="cos-domain-input" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; background: #fff;">
                    <option value="ecommerce" selected>ecommerce · 电商/营销</option>
                    <option value="seo">seo · SEO/搜索优化</option>
                    <option value="content">content · 内容创作</option>
                    <option value="video">video · 视频脚本</option>
                    <option value="business">business · 商业策略</option>
                    <option value="tech">tech · 技术工程</option>
                    <option value="general">general · 通用</option>
                    <option value="__custom__">✏️ 自定义...</option>
                </select>
                <input id="cos-domain-custom" type="text" placeholder="输入自定义领域 (如: education)" style="display:none; width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; margin-top: 6px;">
            </div>
            <button id="cos-evolve-btn" type="button" style="background: #667eea; color: #fff; border: none; padding: 9px 20px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap;">
                ▶ 启动演化
            </button>
        </div>
        <div style="font-size: 11px; color: #9ca3af; margin-bottom: 8px;">
            💡 <strong>提示:</strong> 好的问题格式 = "如何[动作][对象]以达到[目标]"。如: "如何设计小红书封面以提高点击率"
        </div>
        <div id="cos-evolve-result" style="display: none;"></div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         STEP 3: Skill 库 — 演化结晶的认知能力
    ═══════════════════════════════════════════════════════════════ -->
    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
            <span style="background: #eab308; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;">STEP 3</span>
            <h2 style="margin: 0; font-size: 16px; font-weight: 600; color: #1f2937; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 20px;">💎</span> Skill 库 — 演化结晶的认知能力
            </h2>
        </div>
        <p style="margin: 0 0 12px; font-size: 12px; color: #6b7280;">
            <strong>这是什么:</strong> 每次演化成功后, 最优方案 (MVP) 自动结晶为 Skill, 包含原始问题、方案、固化规则和适应度。<br>
            <strong>怎么用:</strong> 点击 Skill 的"应用"按钮, 生成 system_prompt, 可复制到小红书/SEO/长文/视频生成器使用。
        </p>
        <div id="cos-skills-list" style="max-height: 400px; overflow-y: auto;">
            <?php if (empty($top_skills)): ?>
            <div style="text-align: center; padding: 32px; color: #9ca3af; font-size: 13px;">
                <div style="font-size: 32px; margin-bottom: 8px; opacity: 0.4;">💎</div>
                暂无 Skill — 在上方"演化控制台"启动一次演化即可结晶
            </div>
            <?php else: ?>
            <table style="width: 100%; font-size: 12px; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f9fafb;">
                        <th style="text-align: left; padding: 8px; border-bottom: 2px solid #e5e7eb;">Skill 名称</th>
                        <th style="padding: 8px; border-bottom: 2px solid #e5e7eb; text-align: center;">适应度</th>
                        <th style="padding: 8px; border-bottom: 2px solid #e5e7eb; text-align: center;">使用</th>
                        <th style="padding: 8px; border-bottom: 2px solid #e5e7eb; text-align: center;">领域</th>
                        <th style="text-align: left; padding: 8px; border-bottom: 2px solid #e5e7eb;">原始问题</th>
                        <th style="text-align: left; padding: 8px; border-bottom: 2px solid #e5e7eb;">方案预览</th>
                        <th style="padding: 8px; border-bottom: 2px solid #e5e7eb; text-align: center;">操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($top_skills as $name => $skill): ?>
                    <tr data-skill-name="<?php echo esc_attr($name); ?>">
                        <td style="padding: 8px; border-bottom: 1px solid #f3f4f6; font-weight: 600; font-family: monospace; font-size: 11px;"><?php echo esc_html($name); ?></td>
                        <td style="padding: 8px; border-bottom: 1px solid #f3f4f6; text-align: center;">
                            <span style="background: #d1fae5; color: #065f46; padding: 2px 6px; border-radius: 4px; font-weight: 600;"><?php echo esc_html(number_format((float) ($skill['fitness'] ?? 0), 1)); ?></span>
                        </td>
                        <td style="padding: 8px; border-bottom: 1px solid #f3f4f6; text-align: center; color: #6b7280;"><?php echo esc_html((string) ($skill['usage_count'] ?? 0)); ?></td>
                        <td style="padding: 8px; border-bottom: 1px solid #f3f4f6; text-align: center; font-size: 11px; color: #6b7280;"><?php echo esc_html($skill['domain'] ?? '-'); ?></td>
                        <td style="padding: 8px; border-bottom: 1px solid #f3f4f6; font-size: 11px; color: #6b7280; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc_attr($skill['problem'] ?? ''); ?>"><?php echo esc_html(mb_substr($skill['problem'] ?? '', 0, 30)); ?></td>
                        <td style="padding: 8px; border-bottom: 1px solid #f3f4f6; font-size: 11px; color: #374151; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc_attr($skill['mvp_approach'] ?? ''); ?>"><?php echo esc_html(mb_substr($skill['mvp_approach'] ?? '(空)', 0, 40)); ?></td>
                        <td style="padding: 8px; border-bottom: 1px solid #f3f4f6; text-align: center; white-space: nowrap;">
                            <button class="cos-apply-skill-btn" data-name="<?php echo esc_attr($name); ?>" style="background: #10b981; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; margin-right: 4px;">🚀 应用</button>
                            <button class="cos-delete-skill-btn" data-name="<?php echo esc_attr($name); ?>" style="background: #ef4444; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; font-size: 11px; cursor: pointer;">🗑 删除</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <div id="cos-skill-applied-result" style="margin-top: 12px; display: none;"></div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         演化归档 — 历史快照
    ═══════════════════════════════════════════════════════════════ -->
    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h2 style="margin: 0 0 8px; font-size: 16px; font-weight: 600; color: #1f2937; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 20px;">📚</span> 演化归档 — 历史快照
        </h2>
        <p style="margin: 0 0 12px; font-size: 12px; color: #6b7280;">
            <strong>这是什么:</strong> 每代演化 (G1/G2/G3) 的完整快照, 包含方案种群、评分、MVP。<br>
            <strong>怎么用:</strong> 用于回溯历史演化过程, 对比不同问题的演化结果。
        </p>
        <div id="cos-archive-list" style="max-height: 300px; overflow-y: auto;">
            <?php if (empty($recent_evolutions)): ?>
            <div style="text-align: center; padding: 24px; color: #9ca3af; font-size: 13px;">
                <div style="font-size: 28px; margin-bottom: 8px; opacity: 0.4;">📚</div>
                暂无演化记录 — 启动一次演化即可生成归档
            </div>
            <?php else: ?>
            <?php foreach ($recent_evolutions as $id => $snap): ?>
            <div style="padding: 10px; border-bottom: 1px solid #f3f4f6;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                    <span style="background: <?php echo esc_attr($snap['generation'] === 'G1' ? '#3b82f6' : ($snap['generation'] === 'G2' ? '#8b5cf6' : '#ec4899')); ?>; color: #fff; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px;"><?php echo esc_html($snap['generation'] ?? '?'); ?></span>
                    <span style="font-size: 12px; color: #6b7280; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo esc_html(mb_substr($snap['problem'] ?? '', 0, 50)); ?></span>
                    <span style="font-size: 10px; color: #9ca3af;"><?php echo esc_html($snap['saved_at'] ?? ''); ?></span>
                </div>
                <div style="font-size: 11px; color: #9ca3af; padding-left: 32px;">
                    方案 <?php echo esc_html((string) ($snap['variants_count'] ?? 0)); ?> · 存活 <?php echo esc_html((string) ($snap['survivors_count'] ?? 0)); ?> · 绞杀 <?php echo esc_html((string) ($snap['killed_count'] ?? 0)); ?>
                    <?php if (!empty($snap['mvp'])): ?> · MVP: <?php echo esc_html($snap['mvp']['id'] ?? ''); ?> (适应度 <?php echo esc_html((string) ($snap['mvp']['fitness'] ?? 0)); ?>)<?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         STEP 5: 杠杆链调用 — 深度认知审查 (可选)
    ═══════════════════════════════════════════════════════════════ -->
    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
            <span style="background: #ec4899; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;">STEP 5 · 可选</span>
            <h2 style="margin: 0; font-size: 16px; font-weight: 600; color: #1f2937; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 20px;">🔗</span> 杠杆链调用 — 深度认知审查
            </h2>
        </div>
        <p style="margin: 0 0 12px; font-size: 12px; color: #6b7280;">
            <strong>这是什么:</strong> 串联多个认知杠杆 (元学习/逻辑学/元批判等), 对方案做深度审查。每个杠杆注入一段 system_prompt, 教 AI "怎么思考"。<br>
            <strong>什么时候用:</strong> 高风险决策 (如: 选品投入、内容方向、商业策略) 时, 用杠杆链做二次审查, 避免认知偏差。<br>
            <strong>怎么用:</strong> 勾选要调用的杠杆, 点击"运行杠杆链", 查看每个杠杆的 trace 字段。
        </p>
        <!-- v20.4-fix25: 手动场景选择器 -->
        <div style="margin-bottom: 12px; padding: 10px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px;">
            <div style="font-size: 12px; font-weight: 600; color: #0369a1; margin-bottom: 6px;">🎯 场景适配选择器</div>
            <div style="font-size: 10px; color: #6b7280; margin-bottom: 8px;">选择场景后自动勾选最匹配的6个杠杆组合。也可手动勾选下方杠杆自定义组合。</div>
            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                <button type="button" class="cos-scene-btn" data-scene="auto" style="padding: 5px 12px; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-size: 11px; cursor: pointer;">🤖 自动适配</button>
                <button type="button" class="cos-scene-btn" data-scene="ecommerce" style="padding: 5px 12px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 11px; cursor: pointer;">🛒 电商选品</button>
                <button type="button" class="cos-scene-btn" data-scene="content" style="padding: 5px 12px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 11px; cursor: pointer;">✍️ 内容创作</button>
                <button type="button" class="cos-scene-btn" data-scene="tech" style="padding: 5px 12px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 11px; cursor: pointer;">⚙️ 技术架构</button>
                <button type="button" class="cos-scene-btn" data-scene="strategy" style="padding: 5px 12px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 11px; cursor: pointer;">🎯 商业策略</button>
                <button type="button" class="cos-scene-btn" data-scene="audit" style="padding: 5px 12px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 11px; cursor: pointer;">🔍 深度审查</button>
                <button type="button" class="cos-scene-btn" data-scene="innovation" style="padding: 5px 12px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 11px; cursor: pointer;">💡 创新突破</button>
                <button type="button" class="cos-scene-btn" data-scene="risk" style="padding: 5px 12px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 11px; cursor: pointer;">🛡️ 风险防御</button>
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
                    ['id' => 'meta_learning', 'label' => '元学习', 'description' => '从示例提取可迁移模式', 'domain' => 'cognitive', 'domain_label' => '🔍 认知与元认知'],
                    ['id' => 'meta_logic', 'label' => '逻辑学', 'description' => '演绎/归纳/溯因推理', 'domain' => 'logic', 'domain_label' => '🧠 逻辑与推理'],
                    ['id' => 'meta_critique', 'label' => '元批判', 'description' => '红队攻击+证伪测试', 'domain' => 'logic', 'domain_label' => '🧠 逻辑与推理'],
                    ['id' => 'meta_problem_finding', 'label' => '问题发现', 'description' => '问题质疑+根因追问', 'domain' => 'logic', 'domain_label' => '🧠 逻辑与推理'],
                    ['id' => 'meta_abstraction', 'label' => '元抽象', 'description' => '从案例提取通用模型', 'domain' => 'analytical', 'domain_label' => '📊 分析与评估'],
                    ['id' => 'meta_evaluation', 'label' => '元评估', 'description' => '多维评分+基线对比', 'domain' => 'analytical', 'domain_label' => '📊 分析与评估'],
                ];
                $levers_by_domain = [
                    'cognitive' => ['label' => '🔍 认知与元认知', 'levers' => [$levers_for_chain[0]]],
                    'logic' => ['label' => '🧠 逻辑与推理', 'levers' => [$levers_for_chain[1], $levers_for_chain[2], $levers_for_chain[3]]],
                    'analytical' => ['label' => '📊 分析与评估', 'levers' => [$levers_for_chain[4], $levers_for_chain[5]]],
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
            <div style="font-size: 12px; font-weight: 700; color: #1f2937; margin-bottom: 4px;">⚡ 复合杠杆 (高级编排能力 — 17个) <span style="font-size: 10px; font-weight: 400; color: #6b7280;">— 勾选后参与杠杆链，编排多个基础杠杆形成完整部门工作流</span></div>
            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fef3c7; border: 1px solid #fde68a; border-radius: 6px; font-size: 11px; cursor: pointer;" title="去AI味五部门 (适应度:20) | 编排: 本质追问→反向→批判→质疑→落地 | 场景: 去AI味/人类化/反检测">
                    <input type="checkbox" value="deai_5d" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🛡️ 去AI味五部门 <span style="font-size: 9px; color: #92400e;">适应度20</span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #dbeafe; border: 1px solid #93c5fd; border-radius: 6px; font-size: 11px; cursor: pointer;" title="创世演化 (适应度:21) | 编排: 本质→创造→批判→质疑→评估 | 场景: 方案生成/MVP锁定">
                    <input type="checkbox" value="genesis" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🌟 创世演化 <span style="font-size: 9px; color: #1e40af;">适应度21</span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #dcfce7; border: 1px solid #86efac; border-radius: 6px; font-size: 11px; cursor: pointer;" title="深度谋划 (适应度:19) | 编排: 谋划→系统→反向→动态→压力测试 | 场景: 商业策略/博弈推演">
                    <input type="checkbox" value="deep_strategy" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🎯 深度谋划 <span style="font-size: 9px; color: #166534;">适应度19</span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fce7f3; border: 1px solid #f9a8d4; border-radius: 6px; font-size: 11px; cursor: pointer;" title="跨界创新 (适应度:18) | 编排: 跨界→隐喻→压力测试→折叠→反向 | 场景: 产品创新/跨界颠覆">
                    <input type="checkbox" value="cross_innovation" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🎨 跨界创新 <span style="font-size: 9px; color: #9f1239;">适应度18</span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #e0e7ff; border: 1px solid #a5b4fc; border-radius: 6px; font-size: 11px; cursor: pointer;" title="苏格拉底审查 (适应度:19) | 编排: 苏格拉底→质疑→本质→反向→评估 | 场景: 深度审查/批判分析">
                    <input type="checkbox" value="socratic_review" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🔍 苏格拉底审查 <span style="font-size: 9px; color: #3730a3;">适应度19</span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fef9c3; border: 1px solid #fde047; border-radius: 6px; font-size: 11px; cursor: pointer;" title="超级Prompt转换器 (适应度:20) | 编排: 本质→信息→设计→折叠→落地 | 场景: Prompt升级/结构化转换">
                    <input type="checkbox" value="super_prompt" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    ⚡ 超级Prompt转换器 <span style="font-size: 9px; color: #854d0e;">适应度20</span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #f3e8ff; border: 1px solid #c084fc; border-radius: 6px; font-size: 11px; cursor: pointer;" title="认知审计 (适应度:19) | 编排: 自我校准→逻辑→评估→认知→质疑 | 场景: 偏差检测/谬误审查">
                    <input type="checkbox" value="cognitive_audit" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    📋 认知审计 <span style="font-size: 9px; color: #6b21a8;">适应度19</span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #ecfdf5; border: 1px solid #6ee7b7; border-radius: 6px; font-size: 11px; cursor: pointer;" title="知识综合 (适应度:18) | 编排: 知识图谱→模式→类比→折叠→抽象 | 场景: 知识管理/图谱构建">
                    <input type="checkbox" value="knowledge_synthesis" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    📚 知识综合 <span style="font-size: 9px; color: #166534;">适应度18</span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fff7ed; border: 1px solid #fdba74; border-radius: 6px; font-size: 11px; cursor: pointer;" title="内容引擎 (适应度:20) | 编排: 叙事→情绪→说服力→语境→折叠 | 场景: 内容创作/小红书/视频">
                    <input type="checkbox" value="content_engine" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    ✍️ 内容引擎 <span style="font-size: 9px; color: #9a3412;">适应度20</span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 6px; font-size: 11px; cursor: pointer;" title="风险防御 (适应度:19) | 编排: 压力测试→因果→博弈→伦理→自我校准 | 场景: 风险防御/压力测试">
                    <input type="checkbox" value="risk_defense" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🛡️ 风险防御 <span style="font-size: 9px; color: #991b1b;">适应度19</span>
                </label>
                <!-- v27.17.9-fix1: 补全7个缺失的复合杠杆 (10→17) -->
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; font-size: 11px; cursor: pointer;" title="代码优化器 | 编排: 分析→重构→测试→验证→部署 | 场景: 代码审查/技术债务">
                    <input type="checkbox" value="code_optimizer" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🔧 代码优化器 <span style="font-size: 9px; color: #166534;">适应度18</span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fdf4ff; border: 1px solid #e9d5ff; border-radius: 6px; font-size: 11px; cursor: pointer;" title="创意引擎 | 编排: 联想→变异→组合→评估→迭代 | 场景: 创意生成/brainstorm">
                    <input type="checkbox" value="creative_engine" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    💡 创意引擎 <span style="font-size: 9px; color: #86198f;">适应度19</span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #ecfeff; border: 1px solid #a5f3fc; border-radius: 6px; font-size: 11px; cursor: pointer;" title="意图解码器 | 编排: 语义→上下文→情感→意图→响应 | 场景: 用户意图分析/NLU">
                    <input type="checkbox" value="intent_decoder" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🎯 意图解码器 <span style="font-size: 9px; color: #155e75;">适应度18</span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px; font-size: 11px; cursor: pointer;" title="质量关卡 | 编排: 规范→安全→性能→可维护→交付 | 场景: 质量保证/发布前审查">
                    <input type="checkbox" value="quality_gauntlet" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    ✅ 质量关卡 <span style="font-size: 9px; color: #92400e;">适应度20</span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; font-size: 11px; cursor: pointer;" title="种子重组器 | 编排: 拆解→变异→交叉→筛选→固化 | 场景: 方案重组/进化计算">
                    <input type="checkbox" value="seed_recombinator" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    🧬 种子重组器 <span style="font-size: 9px; color: #0369a1;">适应度19</span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fefce8; border: 1px solid #fde047; border-radius: 6px; font-size: 11px; cursor: pointer;" title="通用三件套 | 编排: 分析→生成→验证 | 场景: 通用任务处理">
                    <input type="checkbox" value="universal_trio" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    📐 通用三件套 <span style="font-size: 9px; color: #854d0e;">适应度17</span>
                </label>
                <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #fdf2f8; border: 1px solid #fbcfe8; border-radius: 6px; font-size: 11px; cursor: pointer;" title="写作深度 | 编排: 结构→逻辑→情感→风格→深度 | 场景: 深度写作/长文创作">
                    <input type="checkbox" value="writing_depth" class="cos-lever-checkbox cos-composite-checkbox" style="margin: 0;">
                    ✍️ 写作深度 <span style="font-size: 9px; color: #9d174d;">适应度19</span>
                </label>
            </div>
        </div>
        <button id="cos-run-chain-btn" type="button" style="background: #1f2937; color: #fff; border: none; padding: 8px 20px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
            ▶ 运行杠杆链
        </button>
        <button id="cos-reset-circuit-perm-btn" type="button" style="background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; padding: 8px 16px; border-radius: 6px; font-size: 12px; cursor: pointer; margin-left: 8px;" title="清除所有 AI provider 的失败计数, 让被熔断的 provider 立即恢复可用">
            🔄 重置 AI 熔断器
        </button>
        <div id="cos-chain-result" style="margin-top: 12px; display: none;"></div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         双公理 + 五部门 + 三代演化 — 架构说明 (折叠)
    ═══════════════════════════════════════════════════════════════ -->
    <details style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; margin-bottom: 20px;">
        <summary style="font-size: 14px; font-weight: 600; color: #1f2937; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 18px;">📐</span> COS 架构说明 (双公理 · 五部门 · 三代演化) — 点击展开
        </summary>

        <!-- v20.4-fix26: 参考GordenPPTSkill/ppt-master的图示底层逻辑, 用SVG可视化架构 -->
        <!-- 设计原则: 1)信息层级清晰 2)色彩语义化 3)流程方向明确 4)关键数据高亮 -->

        <!-- 架构总览SVG: 双公理→五部门→三代演化→MVP -->
        <div style="margin-top: 16px; background: #fafafa; border-radius: 8px; padding: 16px; overflow-x: auto;">
            <div style="font-size: 12px; font-weight: 600; color: #1f2937; margin-bottom: 12px; text-align: center;">📊 COS 认知操作系统架构图</div>
            <svg width="100%" height="280" viewBox="0 0 800 280" xmlns="http://www.w3.org/2000/svg" style="max-width: 800px; margin: 0 auto; display: block;">
                <!-- 定义渐变和箭头 -->
                <defs>
                    <linearGradient id="axiomGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" style="stop-color:#dbeafe;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#bfdbfe;stop-opacity:1" />
                    </linearGradient>
                    <linearGradient id="deptGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" style="stop-color:#dcfce7;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#bbf7d0;stop-opacity:1" />
                    </linearGradient>
                    <linearGradient id="evoGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" style="stop-color:#fce7f3;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#fbcfe8;stop-opacity:1" />
                    </linearGradient>
                    <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                        <polygon points="0 0, 10 3.5, 0 7" fill="#6b7280" />
                    </marker>
                </defs>

                <!-- 第一层: 双公理 -->
                <rect x="50" y="20" width="700" height="50" rx="8" fill="url(#axiomGrad)" stroke="#3b82f6" stroke-width="1.5"/>
                <text x="400" y="42" text-anchor="middle" font-size="13" font-weight="600" fill="#1e40af">⚖️ 双公理系统</text>
                <text x="200" y="60" text-anchor="middle" font-size="10" fill="#374151">公理一: 信息熵减</text>
                <text x="600" y="60" text-anchor="middle" font-size="10" fill="#374151">公理二: 系统降维</text>

                <!-- 箭头: 公理→部门 -->
                <line x1="400" y1="70" x2="400" y2="95" stroke="#6b7280" stroke-width="2" marker-end="url(#arrowhead)"/>

                <!-- 第二层: 五部门流水线 -->
                <rect x="50" y="100" width="700" height="60" rx="8" fill="url(#deptGrad)" stroke="#10b981" stroke-width="1.5"/>
                <text x="400" y="120" text-anchor="middle" font-size="13" font-weight="600" fill="#065f46">🏛️ 五部门协同流水线</text>
                <!-- 五个部门节点 -->
                <g>
                    <rect x="70" y="130" width="120" height="24" rx="4" fill="#fff" stroke="#10b981" stroke-width="1"/>
                    <text x="130" y="146" text-anchor="middle" font-size="10" font-weight="600" fill="#065f46">FP 溯源</text>
                </g>
                <g>
                    <rect x="210" y="130" width="120" height="24" rx="4" fill="#fff" stroke="#10b981" stroke-width="1"/>
                    <text x="270" y="146" text-anchor="middle" font-size="10" font-weight="600" fill="#065f46">EX 变异</text>
                </g>
                <g>
                    <rect x="350" y="130" width="120" height="24" rx="4" fill="#fff" stroke="#10b981" stroke-width="1"/>
                    <text x="410" y="146" text-anchor="middle" font-size="10" font-weight="600" fill="#065f46">C 绞杀</text>
                </g>
                <g>
                    <rect x="490" y="130" width="120" height="24" rx="4" fill="#fff" stroke="#10b981" stroke-width="1"/>
                    <text x="550" y="146" text-anchor="middle" font-size="10" font-weight="600" fill="#065f46">O 降维</text>
                </g>
                <g>
                    <rect x="630" y="130" width="120" height="24" rx="4" fill="#fff" stroke="#10b981" stroke-width="1"/>
                    <text x="690" y="146" text-anchor="middle" font-size="10" font-weight="600" fill="#065f46">A 结晶</text>
                </g>
                <!-- 部门间箭头 -->
                <line x1="190" y1="142" x2="210" y2="142" stroke="#10b981" stroke-width="1.5" marker-end="url(#arrowhead)"/>
                <line x1="330" y1="142" x2="350" y2="142" stroke="#10b981" stroke-width="1.5" marker-end="url(#arrowhead)"/>
                <line x1="470" y1="142" x2="490" y2="142" stroke="#10b981" stroke-width="1.5" marker-end="url(#arrowhead)"/>
                <line x1="610" y1="142" x2="630" y2="142" stroke="#10b981" stroke-width="1.5" marker-end="url(#arrowhead)"/>

                <!-- 箭头: 部门→演化 -->
                <line x1="400" y1="160" x2="400" y2="185" stroke="#6b7280" stroke-width="2" marker-end="url(#arrowhead)"/>

                <!-- 第三层: 三代演化 -->
                <rect x="50" y="190" width="700" height="70" rx="8" fill="url(#evoGrad)" stroke="#ec4899" stroke-width="1.5"/>
                <text x="400" y="210" text-anchor="middle" font-size="13" font-weight="600" fill="#9f1239">🔄 三代演化循环</text>
                <!-- G1 G2 G3 节点 -->
                <g>
                    <circle cx="180" cy="235" r="18" fill="#3b82f6" stroke="#fff" stroke-width="2"/>
                    <text x="180" y="240" text-anchor="middle" font-size="11" font-weight="700" fill="#fff">G1</text>
                    <text x="180" y="262" text-anchor="middle" font-size="9" fill="#374151">初代涌现</text>
                </g>
                <g>
                    <circle cx="400" cy="235" r="18" fill="#8b5cf6" stroke="#fff" stroke-width="2"/>
                    <text x="400" y="240" text-anchor="middle" font-size="11" font-weight="700" fill="#fff">G2</text>
                    <text x="400" y="262" text-anchor="middle" font-size="9" fill="#374151">重组变异</text>
                </g>
                <g>
                    <circle cx="620" cy="235" r="18" fill="#ec4899" stroke="#fff" stroke-width="2"/>
                    <text x="620" y="240" text-anchor="middle" font-size="11" font-weight="700" fill="#fff">G3</text>
                    <text x="620" y="262" text-anchor="middle" font-size="9" fill="#374151">终极坍缩</text>
                </g>
                <!-- 演化箭头 -->
                <line x1="198" y1="235" x2="382" y2="235" stroke="#8b5cf6" stroke-width="2" marker-end="url(#arrowhead)"/>
                <line x1="418" y1="235" x2="602" y2="235" stroke="#ec4899" stroke-width="2" marker-end="url(#arrowhead)"/>
                <!-- MVP标记 -->
                <text x="680" y="240" font-size="10" font-weight="600" fill="#9f1239">→ MVP</text>
            </svg>
        </div>

        <!-- 杠杆体系可视化: 6大能力域 -->
        <div style="margin-top: 16px; background: #fafafa; border-radius: 8px; padding: 16px;">
            <div style="font-size: 12px; font-weight: 600; color: #1f2937; margin-bottom: 12px; text-align: center;">🧠 元杠杆体系 (24基础+17复合=41个)</div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px;">
                <div style="background: #e0f2fe; padding: 10px; border-radius: 6px; border-left: 3px solid #0284c7;">
                    <div style="font-size: 11px; font-weight: 600; color: #0c4a6e;">🔍 认知与元认知 (10)</div>
                    <div style="font-size: 9px; color: #6b7280; margin-top: 2px;">元认知/本质/注意力/折叠/直觉/递归...</div>
                </div>
                <div style="background: #fef3c7; padding: 10px; border-radius: 6px; border-left: 3px solid #d97706;">
                    <div style="font-size: 11px; font-weight: 600; color: #78350f;">🧠 逻辑与推理 (7)</div>
                    <div style="font-size: 9px; color: #6b7280; margin-top: 2px;">逻辑/苏格拉底/质疑/反向/因果...</div>
                </div>
                <div style="background: #fce7f3; padding: 10px; border-radius: 6px; border-left: 3px solid #db2777;">
                    <div style="font-size: 11px; font-weight: 600; color: #831843;">🎨 创造与突破 (7)</div>
                    <div style="font-size: 9px; color: #6b7280; margin-top: 2px;">创造/跨界/灵感/隐喻/范式/设计...</div>
                </div>
                <div style="background: #e0e7ff; padding: 10px; border-radius: 6px; border-left: 3px solid #4f46e5;">
                    <div style="font-size: 11px; font-weight: 600; color: #312e81;">📊 分析与评估 (8)</div>
                    <div style="font-size: 9px; color: #6b7280; margin-top: 2px;">抽象/模式/评估/压力测试/系统...</div>
                </div>
                <div style="background: #dcfce7; padding: 10px; border-radius: 6px; border-left: 3px solid #16a34a;">
                    <div style="font-size: 11px; font-weight: 600; color: #14532d;">🎯 战略与行动 (7)</div>
                    <div style="font-size: 9px; color: #6b7280; margin-top: 2px;">谋划/决策/落地/动态/博弈/伦理...</div>
                </div>
                <div style="background: #f3e8ff; padding: 10px; border-radius: 6px; border-left: 3px solid #9333ea;">
                    <div style="font-size: 11px; font-weight: 600; color: #581c87;">💬 沟通与协作 (7)</div>
                    <div style="font-size: 9px; color: #6b7280; margin-top: 2px;">沟通/叙事/情绪/协作/说服力/语境</div>
                </div>
            </div>
            <!-- 复合杠杆条 -->
            <div style="margin-top: 10px; padding: 8px; background: linear-gradient(90deg, #fef3c7, #dbeafe, #dcfce7, #fce7f3, #e0e7ff, #f3e8ff); border-radius: 6px; text-align: center;">
                <div style="font-size: 11px; font-weight: 600; color: #1f2937;">⚡ 复合杠杆 (17个高级编排能力)</div>
                <div style="font-size: 9px; color: #6b7280; margin-top: 2px;">去AI味五部门 · 创世演化 · 深度谋划 · 跨界创新 · 苏格拉底审查 · 超级Prompt · 认知审计 · 知识综合 · 内容引擎 · 风险防御 · 代码优化器 · 创意引擎 · 意图解码器 · 质量关卡 · 种子重组器 · 通用三件套 · 写作深度</div>
            </div>
        </div>

        <!-- 原始三栏说明保留 -->
        <div style="margin-top: 16px; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
            <!-- 双公理 -->
            <div style="background: #f0f4ff; padding: 14px; border-radius: 8px;">
                <h3 style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #1e40af;">⚖️ 双公理系统</h3>
                <div style="font-size: 11px; color: #374151; margin-bottom: 6px;"><strong>公理一 · 信息熵减:</strong> 操作后任务空间不确定性必须降低</div>
                <div style="font-size: 11px; color: #374151; margin-bottom: 8px;"><strong>公理二 · 系统降维:</strong> 高维概念降维为可操作循环</div>
                <div style="font-size: 10px; color: #ef4444; background: #fef2f2; padding: 4px 6px; border-radius: 4px;">⚠️ 公理刚性 · 证伪至死 · 任一违反即抹杀</div>
            </div>
            <!-- 五部门 -->
            <div style="background: #f0fdf4; padding: 14px; border-radius: 8px;">
                <h3 style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #065f46;">🏛️ 五部门协同</h3>
                <div style="font-size: 11px; color: #374151; line-height: 1.6;">
                    <strong>FP</strong> 定义公理和信息核 → <strong>EX</strong> 生成方案种群(10个) → <strong>C</strong> 绞杀弱者(风险>8或可行<4) → <strong>O</strong> 检测盲区与幻觉 → <strong>A</strong> 结晶锁定MVP
                </div>
            </div>
            <!-- 三代演化 -->
            <div style="background: #fdf2f8; padding: 14px; border-radius: 8px;">
                <h3 style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #9f1239;">🔄 三代演化循环</h3>
                <div style="font-size: 11px; color: #374151; line-height: 1.6;">
                    <strong style="color: #3b82f6;">G1</strong> 初代涌现(<span id="cos-gen-g1-count"><?php echo esc_html((string) (($cos_overview['by_generation']['G1'] ?? 0))); ?></span>) → <strong style="color: #8b5cf6;">G2</strong> 重组变异(<span id="cos-gen-g2-count"><?php echo esc_html((string) (($cos_overview['by_generation']['G2'] ?? 0))); ?></span>) → <strong style="color: #ec4899;">G3</strong> 终极坍缩(<span id="cos-gen-g3-count"><?php echo esc_html((string) (($cos_overview['by_generation']['G3'] ?? 0))); ?></span>)
                </div>
                <div style="font-size: 10px; color: #6b7280; margin-top: 4px;">每代结晶后物理归档, 作为下一代变异基线</div>
            </div>
        </div>
    </details>

</div>

<script>
(function(){
    'use strict';
    var ajaxUrl = '<?php echo $ajax_url; ?>';
    var nonce   = '<?php echo $cos_nonce; ?>';

    function post(action, data) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        for (var k in data) { if (data.hasOwnProperty(k)) fd.append(k, data[k]); }
        // v20.4-fix14: 客户端超时从 75s → 65s
        // v20.4-fix25: 客户端超时60s→65s, 配合动态timeout(后期杠杆45s)
        var controller = new AbortController();
        var timeoutId = setTimeout(function(){ controller.abort(); }, 65000);
        return fetch(ajaxUrl, {method: 'POST', body: fd, credentials: 'same-origin', signal: controller.signal})
            .then(function(r){
                clearTimeout(timeoutId);
                // v20.4-fix7: 先检查 HTTP 状态码, 非 200 直接报错
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status + ' ' + r.statusText);
                }
                return r.text();
            })
            .then(function(text){
                // v20.4-fix7: 容错解析 JSON, 非 JSON 时返回错误信息
                try {
                    return JSON.parse(text);
                } catch(e) {
                    // 截取前 200 字符帮助诊断
                    var preview = text.substring(0, 200);
                    throw new Error('AJAX 返回非 JSON: ' + preview);
                }
            })
            .catch(function(err){
                clearTimeout(timeoutId);
                // v20.4-fix14: 超时 abort 抛 AbortError, 转成更友好的中文提示
                if (err.name === 'AbortError') {
                    throw new Error('请求超时 (65秒), 服务器未响应。建议: 1)点击"重置 AI 熔断器" 2)减少勾选的杠杆数量 3)重试。');
                }
                throw err;
            });
    }

    // ── v20.4-fix2: 版本探针 — 页面加载时自动检测部署状态 ──
    function checkVersion() {
        var badge = document.getElementById('cos-patch-badge');
        if (!badge) return;
        post('linked3_cos_version', {}).then(function(res){
            if (!res.success || !res.data) {
                badge.textContent = '⚠ 版本未知';
                badge.style.background = 'rgba(254,243,199,0.9)';
                badge.style.color = '#92400e';
                return;
            }
            var d = res.data;
            var allOk = d.checks && d.checks.extract_rules_is_public && d.checks.chat_has_3_args && d.checks.registry_auto_init && d.checks.chain_chunked_fix10;
            // v27.6.19-fix: 不再硬编码版本号，检查 patch_version 非空且 allOk
            if (d.patch_version && d.patch_version !== 'unknown' && allOk) {
                badge.textContent = '✓ ' + d.patch_version + ' 已生效';
                badge.style.background = 'rgba(209,250,229,0.9)';
                badge.style.color = '#065f46';
            } else {
                badge.textContent = '✗ 旧代码仍在运行 (' + d.patch_version + ')';
                badge.style.background = 'rgba(254,226,226,0.9)';
                badge.style.color = '#991b1b';
                badge.title = '修复未生效! 检查项: ' + JSON.stringify(d.checks) + '\n需要: 1)重新上传zip 2)清OPcache 3)重启PHP-FPM';
            }
        }).catch(function(){
            badge.textContent = '⚠ 探针失败';
            badge.style.background = 'rgba(254,243,199,0.9)';
            badge.style.color = '#92400e';
        });
    }
    checkVersion();

    // ── v20.4-fix12: 领域下拉 + 自定义切换 ──
    var domainSelect = document.getElementById('cos-domain-input');
    var domainCustom = document.getElementById('cos-domain-custom');
    if (domainSelect && domainCustom) {
        domainSelect.addEventListener('change', function(){
            domainCustom.style.display = (this.value === '__custom__') ? 'block' : 'none';
        });
    }
    function getDomain() {
        if (!domainSelect) return 'ecommerce';
        if (domainSelect.value === '__custom__') {
            return (domainCustom.value || '').trim() || 'general';
        }
        return domainSelect.value;
    }

    // ── STEP 1+2: 启动演化 (v20.4-fix8: 异步逐代调用) ──
    var evolveBtn = document.getElementById('cos-evolve-btn');
    var resultDiv = document.getElementById('cos-evolve-result');
    evolveBtn.addEventListener('click', function(){
        var problem = document.getElementById('cos-problem-input').value.trim();
        var domain  = getDomain();
        if (!problem) { alert('请输入问题描述'); return; }
        evolveBtn.disabled = true;
        evolveBtn.textContent = '演化中...';
        resultDiv.style.display = 'block';

        // v20.4-fix8: 异步逐代演化 — G1 → G2 → G3 → finalize
        var generations = [];
        var finalMvp = null;

        function runGen(gen, baseline) {
            resultDiv.innerHTML = '<div style="padding: 16px; text-align: center; color: #6b7280;"><div style="display: inline-block; width: 24px; height: 24px; border: 3px solid #e5e7eb; border-top-color: #667eea; border-radius: 50%; animation: cos-spin 0.8s linear infinite;"></div><div style="margin-top: 8px; font-size: 13px;">运行 ' + gen + ' 演化中 (AI 生成方案)...</div></div>';

            var postData = {
                problem: problem,
                generation: gen,
                domain: domain,
            };
            if (baseline) postData.baseline = JSON.stringify(baseline);

            return post('linked3_cos_evolve_gen', postData).then(function(res){
                if (!res.success) {
                    throw new Error(res.data?.message || gen + ' 演化失败');
                }
                var genResult = res.data;
                generations.push(genResult);
                if (genResult.mvp) {
                    finalMvp = genResult.mvp;
                }
                return genResult;
            });
        }

        // 串行执行 G1 → G2 → G3 → finalize
        runGen('G1', null)
            .then(function(g1){ return runGen('G2', g1.mvp); })
            .then(function(g2){ return runGen('G3', g2.mvp); })
            .then(function(){
                // G3 完成, 调用 finalize 结晶 Skill
                if (!finalMvp) throw new Error('未获得最终 MVP');
                return post('linked3_cos_evolve_finalize', {
                    problem: problem,
                    domain: domain,
                    mvp: JSON.stringify(finalMvp),
                    generations: JSON.stringify(generations),
                });
            })
            .then(function(res){
                evolveBtn.disabled = false;
                evolveBtn.textContent = '▶ 启动演化';
                if (res.success) {
                    // 构造与旧格式兼容的结果
                    var compatResult = {
                        final_status: 'success',
                        final_mvp: finalMvp,
                        generations: generations,
                    };
                    renderEvolveResult(compatResult);
                    refreshDashboard();
                    // 自动推荐杠杆
                    if (finalMvp && finalMvp.approach) {
                        autoRecommendLevers(problem, domain, finalMvp.approach);
                    }
                } else {
                    resultDiv.innerHTML = '<div style="padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; font-size: 13px;">❌ 结晶失败: ' + escapeHtml(res.data?.message || '未知错误') + '</div>';
                }
            })
            .catch(function(err){
                evolveBtn.disabled = false;
                evolveBtn.textContent = '▶ 启动演化';
                resultDiv.innerHTML = '<div style="padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; font-size: 13px;">❌ 演化失败: ' + escapeHtml(String(err.message || err)) + '<br><br><button id="cos-diag-btn" style="background: #1f2937; color: #fff; border: none; padding: 6px 16px; border-radius: 6px; font-size: 12px; cursor: pointer;">🔍 运行 AI 诊断</button><div id="cos-diag-result" style="margin-top: 8px;"></div></div>';
                var diagBtn = document.getElementById('cos-diag-btn');
                if (diagBtn) diagBtn.addEventListener('click', runDiagnose);
            });
    });

    // v20.4-fix6: AI 诊断功能
    function runDiagnose() {
        var diagResult = document.getElementById('cos-diag-result');
        if (!diagResult) return;
        diagResult.innerHTML = '<div style="padding: 8px; color: #6b7280; font-size: 12px;">诊断中...</div>';
        post('linked3_cos_diagnose', {}).then(function(res){
            if (!res || !res.success || !res.data) {
                var errMsg = (res && res.data && res.data.message) ? res.data.message : '诊断失败 - AJAX 返回非 JSON';
                diagResult.innerHTML = '<div style="padding: 8px; color: #991b1b; font-size: 12px;">' + escapeHtml(errMsg) + '</div>';
                return;
            }
            var d = res.data;
            var html = '<div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; font-size: 11px; color: #374151; line-height: 1.8;">';
            html += '<strong>PHP 版本:</strong> ' + escapeHtml(d.php_version) + '<br>';
            html += '<strong>max_execution_time:</strong> ' + escapeHtml(d.max_execution) + ' 秒<br>';
            html += '<strong>set_time_limit 可用:</strong> ' + (d.set_time_limit ? '✓' : '✗') + '<br>';
            html += '<strong>AI Dispatcher:</strong> ' + (d.ai_dispatcher ? '✓ 已加载' : '✗ 未加载') + '<br>';
            html += '<strong>默认 Provider:</strong> ' + escapeHtml(d.default_provider) + '<br>';
            html += '<strong>Provider Keys:</strong><br>';
            for (var slug in d.provider_keys) {
                html += '&nbsp;&nbsp;' + slug + ': ' + d.provider_keys[slug] + '<br>';
            }
            if (d.test_result) {
                html += '<strong style="color: #065f46;">AI 测试:</strong> ' + escapeHtml(d.test_result) + '<br>';
            }
            if (d.test_error) {
                html += '<strong style="color: #991b1b;">AI 错误:</strong> ' + escapeHtml(d.test_error) + '<br>';
                // v20.4-fix12: AI 错误时显示"重置熔断器"按钮
                html += '<button id="cos-reset-circuit-btn" style="background: #dc2626; color: #fff; border: none; padding: 4px 12px; border-radius: 4px; font-size: 11px; cursor: pointer; margin-top: 6px;">🔄 重置 AI 熔断器</button>';
            }
            html += '</div>';
            diagResult.innerHTML = html;
            // v20.4-fix12: 绑定重置熔断器按钮
            var resetBtn = document.getElementById('cos-reset-circuit-btn');
            if (resetBtn) resetBtn.addEventListener('click', resetCircuit);
        }).catch(function(err){
            diagResult.innerHTML = '<div style="padding: 8px; color: #991b1b; font-size: 12px;">诊断请求失败: ' + escapeHtml(String(err)) + '</div>';
        });
    }

    // v20.4-fix12: 重置 AI 熔断器
    function resetCircuit() {
        if (!confirm('确认重置所有 AI provider 的熔断器?\n\n这会清除所有 provider 的失败计数, 让被熔断的 provider 立即恢复可用。\n\n适用场景: AI 曾因超时失败触发熔断, 但 API 已恢复, 想立即重试。')) return;
        post('linked3_cos_reset_circuit', {}).then(function(res){
            if (res.success && res.data) {
                alert(res.data.message + '\n\n现在可以重新运行杠杆链了。');
            } else {
                alert('重置失败: ' + (res.data?.message || '未知错误'));
            }
        }).catch(function(err){
            alert('重置请求失败: ' + String(err.message || err));
        });
    }

    // v20.4-fix6: 演化成功后自动推荐杠杆并勾选
    function autoRecommendLevers(problem, domain, approach) {
        post('linked3_cos_recommend_levers', {
            problem: problem,
            approach: approach,
            domain: domain
        }).then(function(res){
            if (!res.success || !res.data || !res.data.recommended) return;
            // 先取消所有勾选
            document.querySelectorAll('.cos-lever-checkbox').forEach(function(cb){ cb.checked = false; });
            // 勾选推荐的杠杆
            res.data.recommended.forEach(function(l){
                var cb = document.querySelector('.cos-lever-checkbox[value="' + l.id + '"]');
                if (cb) cb.checked = true;
            });
            // 显示推荐提示
            var chainSection = document.getElementById('cos-lever-chain');
            if (chainSection) {
                var tip = document.createElement('div');
                tip.style.cssText = 'width:100%;margin-top:8px;padding:8px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;font-size:11px;color:#1e40af;';
                tip.innerHTML = '✨ 已根据你的问题自适配推荐 ' + res.data.recommended.length + ' 个杠杆 (已勾选)。可直接点击"运行杠杆链"。';
                // 移除旧提示
                var oldTip = chainSection.querySelector('.cos-recommend-tip');
                if (oldTip) oldTip.remove();
                tip.className = 'cos-recommend-tip';
                chainSection.appendChild(tip);
            }
        });
    }

    function renderEvolveResult(data) {
        var html = '<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px;">';
        html += '<div style="font-size: 14px; font-weight: 600; color: #166534; margin-bottom: 12px;">✅ 演化完成 — ' + (data.final_status || 'unknown') + '</div>';
        if (data.final_mvp) {
            html += '<div style="background: #fff; padding: 12px; border-radius: 6px; margin-bottom: 10px; border-left: 3px solid #10b981;">';
            html += '<div style="font-size: 13px; font-weight: 600; color: #1f2937;">🏆 MVP: ' + escapeHtml(data.final_mvp.id || '') + ' (适应度 ' + (data.final_mvp.fitness || 0) + ')</div>';
            // v20.4: 显示真实方案内容
            if (data.final_mvp.approach) {
                html += '<div style="font-size: 12px; color: #374151; margin-top: 8px; line-height: 1.6; white-space: pre-wrap;">' + escapeHtml(data.final_mvp.approach) + '</div>';
            }
            // v20.4: 显示执行步骤
            if (data.final_mvp.steps) {
                html += '<div style="font-size: 11px; color: #6b7280; margin-top: 8px; padding: 6px; background: #f9fafb; border-radius: 4px;"><strong>执行步骤:</strong><br>' + escapeHtml(data.final_mvp.steps) + '</div>';
            }
            // v20.4: 显示评分明细
            if (data.final_mvp.score) {
                var s = data.final_mvp.score;
                html += '<div style="font-size: 10px; color: #9ca3af; margin-top: 6px;">评分: 风险=' + (s.risk||0) + ' · 可行=' + (s.feasibility||0) + ' · 新颖=' + (s.novelty||0) + '</div>';
            }
            html += '</div>';
        }
        if (data.generations && data.generations.length) {
            html += '<div style="font-size: 12px; color: #6b7280; margin-bottom: 8px;">三代演化详情:</div>';
            data.generations.forEach(function(g){
                var color = g.status === 'pass' ? '#10b981' : '#ef4444';
                html += '<div style="display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f3f4f6;">';
                html += '<span style="background: ' + (g.generation === 'G1' ? '#3b82f6' : (g.generation === 'G2' ? '#8b5cf6' : '#ec4899')) + '; color: #fff; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px;">' + g.generation + '</span>';
                html += '<span style="color: ' + color + '; font-size: 14px;">' + (g.status === 'pass' ? '✓' : '✗') + '</span>';
                html += '<span style="font-size: 12px; color: #4b5563; flex: 1;">' + escapeHtml(g.message || '') + '</span>';
                html += '</div>';
            });
        }
        html += '<div style="margin-top: 10px; padding: 8px; background: #fef3c7; border-radius: 6px; font-size: 12px; color: #92400e;">';
        html += '💡 <strong>下一步:</strong> 滚动到下方"Skill 库", 找到刚结晶的 Skill, 点击"🚀 应用"按钮生成 system_prompt';
        html += '</div>';
        html += '</div>';
        resultDiv.innerHTML = html;
    }

    // ── STEP 3+4: Skill 应用与删除 ──
    document.addEventListener('click', function(e){
        if (e.target.classList.contains('cos-apply-skill-btn')) {
            var name = e.target.getAttribute('data-name');
            applySkill(name);
        }
        if (e.target.classList.contains('cos-delete-skill-btn')) {
            var name = e.target.getAttribute('data-name');
            if (confirm('确认删除 Skill: ' + name + '?')) {
                deleteSkill(name);
            }
        }
    });

    function applySkill(name) {
        var resultEl = document.getElementById('cos-skill-applied-result');
        resultEl.style.display = 'block';
        resultEl.innerHTML = '<div style="padding: 12px; text-align: center; color: #6b7280; font-size: 13px;">生成 system_prompt 中...</div>';
        post('linked3_cos_apply_skill', {name: name, task_type: 'xhs_generate'}).then(function(res){
            if (res.success && res.data) {
                var d = res.data;
                var html = '<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 14px;">';
                html += '<div style="font-size: 13px; font-weight: 600; color: #166534; margin-bottom: 8px;">✅ Skill 已应用 — ' + escapeHtml(d.message) + '</div>';
                html += '<div style="font-size: 11px; color: #6b7280; margin-bottom: 8px;">适应度: ' + d.fitness + ' · 使用次数: ' + d.usage_count + ' · 固化规则: ' + (d.rules_count || 0) + ' 条</div>';
                // v20.4: 显示方案预览
                if (d.approach_preview) {
                    html += '<div style="font-size: 11px; color: #374151; margin-bottom: 8px; padding: 8px; background: #fff; border-radius: 6px; border-left: 3px solid #10b981;"><strong>方案预览:</strong> ' + escapeHtml(d.approach_preview) + (d.approach_preview.length >= 200 ? '...' : '') + '</div>';
                }
                html += '<div style="font-size: 11px; color: #374151; margin-bottom: 6px; font-weight: 600;">📋 生成的 system_prompt (可复制到生成器):</div>';
                html += '<textarea style="width: 100%; height: 160px; font-family: monospace; font-size: 11px; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; resize: vertical;" readonly onclick="this.select()">' + escapeHtml(d.system_prompt) + '</textarea>';
                html += '<div style="font-size: 11px; color: #6b7280; margin-top: 6px;">💡 复制上方文本, 粘贴到小红书/SEO/长文/视频生成器的 system_prompt 字段即可使用</div>';
                html += '</div>';
                resultEl.innerHTML = html;
                refreshDashboard();
            } else {
                resultEl.innerHTML = '<div style="padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; font-size: 13px;">❌ ' + (res.data?.message || '应用失败') + '</div>';
            }
        });
    }

    function deleteSkill(name) {
        post('linked3_cos_delete_skill', {name: name}).then(function(res){
            if (res.success) {
                refreshDashboard();
            } else {
                alert('删除失败: ' + (res.data?.message || '未知错误'));
            }
        });
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── STEP 5: 杠杆链 (v20.4-fix10: 分块串行调用, 每个杠杆一个 AJAX 请求) ──
    // 旧实现 (fix9) 在单个 PHP 请求里串行跑 6 个 AI 调用 (最长 360s),
    // 超过 web server / PHP-FPM 超时 (通常 60-120s) → 连接被掐断 → "TypeError: Failed to fetch"。
    // fix10: 改为前端逐个调用 ajax_run_lever, 每个请求 ≤60s, 并实时渲染进度。
    var chainBtn = document.getElementById('cos-run-chain-btn');
    var chainResult = document.getElementById('cos-chain-result');
    // v20.4-fix12: 绑定永久"重置熔断器"按钮
    var resetCircuitPermBtn = document.getElementById('cos-reset-circuit-perm-btn');
    if (resetCircuitPermBtn) resetCircuitPermBtn.addEventListener('click', resetCircuit);

    // v20.4-fix25: 场景选择器绑定
    var scenePresets = {
        'auto': null, // 自动适配, 调用后端
        'ecommerce': ['meta_essence', 'meta_creativity', 'meta_strategy', 'meta_evaluation', 'content_engine', 'risk_defense'],
        'content': ['meta_creativity', 'meta_metaphor', 'content_engine', 'meta_communication', 'meta_critique', 'meta_evaluation'],
        'tech': ['meta_essence', 'meta_system', 'meta_logic', 'meta_stress_test', 'cognitive_audit', 'meta_evaluation'],
        'strategy': ['meta_strategy', 'meta_reverse', 'meta_system', 'deep_strategy', 'risk_defense', 'meta_execution'],
        'audit': ['meta_essence', 'meta_critique', 'meta_evaluation', 'socratic_review', 'cognitive_audit', 'meta_execution'],
        'innovation': ['meta_creativity', 'meta_crossover', 'meta_metaphor', 'cross_innovation', 'meta_reverse', 'meta_folding'],
        'risk': ['meta_stress_test', 'meta_causal', 'meta_game', 'meta_ethics', 'risk_defense', 'meta_self_calibration'],
    };

    document.querySelectorAll('.cos-scene-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var scene = this.getAttribute('data-scene');
            // 更新按钮样式
            document.querySelectorAll('.cos-scene-btn').forEach(function(b){
                b.style.background = '#f3f4f6';
                b.style.color = '#374151';
                b.style.border = '1px solid #d1d5db';
            });
            this.style.background = '#2563eb';
            this.style.color = '#fff';
            this.style.border = 'none';

            if (scene === 'auto') {
                // 自动适配: 调用后端推荐
                var problem = document.querySelector('textarea[name="cos_problem"], #cos-problem-input, #cos_problem')?.value || '';
                var domain = document.querySelector('select[name="cos_domain"], #cos-domain-select')?.value || '';
                post('linked3_cos_recommend_levers', {problem: problem, approach: '', domain: domain}).then(function(res){
                    if (res.success && res.data && res.data.recommended) {
                        // 先取消所有勾选
                        document.querySelectorAll('.cos-lever-checkbox').forEach(function(cb){ cb.checked = false; });
                        // 勾选推荐的杠杆
                        res.data.recommended.forEach(function(r){
                            var cb = document.querySelector('.cos-lever-checkbox[value="' + r.id + '"]');
                            if (cb) cb.checked = true;
                        });
                    }
                }).catch(function(){});
            } else {
                // 手动场景: 使用预设
                var preset = scenePresets[scene] || [];
                // 先取消所有勾选
                document.querySelectorAll('.cos-lever-checkbox').forEach(function(cb){ cb.checked = false; });
                // 勾选预设的杠杆
                preset.forEach(function(lid){
                    var cb = document.querySelector('.cos-lever-checkbox[value="' + lid + '"]');
                    if (cb) cb.checked = true;
                });
            }
        });
    });

    chainBtn.addEventListener('click', function(){
        var levers = [];
        document.querySelectorAll('.cos-lever-checkbox:checked').forEach(function(cb){ levers.push(cb.value); });
        if (levers.length === 0) { alert('请至少选择一个杠杆'); return; }

        // v20.4: 收集审查上下文 (问题 + 最近 MVP 方案)
        var problem = document.querySelector('textarea[name="cos_problem"], #cos-problem-input, #cos_problem')?.value || '';
        var approach = '';
        var steps = '';
        var skillName = '';

        // 尝试从最近应用的 Skill 结果中获取 approach
        var appliedTextarea = document.querySelector('#cos-skill-applied-result textarea');
        if (appliedTextarea) {
            var promptText = appliedTextarea.value;
            var m = promptText.match(/## 最优方案 \(MVP\)\n([\s\S]*?)(\n\n## |\n\n## )/);
            if (m) approach = m[1].trim();
            var m2 = promptText.match(/## 执行步骤\n([\s\S]*?)(\n\n## )/);
            if (m2) steps = m2[1].trim();
        }

        // 尝试从 Skill 表格中获取最近一个 Skill 的 name
        var firstSkillBtn = document.querySelector('.cos-apply-skill-btn[data-name]');
        if (firstSkillBtn) {
            skillName = firstSkillBtn.getAttribute('data-name');
        }

        if (!problem && !approach && !skillName) {
            alert('请先启动演化生成方案, 或在问题描述中输入内容, 再运行杠杆链');
            return;
        }

        chainBtn.disabled = true;
        chainBtn.textContent = '运行中...';
        chainResult.style.display = 'block';

        // v20.4-fix10: 分块串行 — 每个杠杆一个 AJAX 请求, 累积 analysis 传给下一个
        var chainResults = [];
        var accumulated = '';
        var leverLabels = {}; // 缓存杠杆中文名, 用于进度显示

        function renderChainProgress(currentIdx, total, currentLabel) {
            var html = '<div style="padding: 12px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;">';
            html += '<div style="font-size: 13px; font-weight: 600; color: #1f2937; margin-bottom: 8px;">🔗 杠杆链运行中 (' + (currentIdx + 1) + '/' + total + ')</div>';
            html += '<div style="height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; margin-bottom: 10px;">';
            html += '<div style="height: 100%; width: ' + ((currentIdx / total) * 100) + '%; background: linear-gradient(90deg, #667eea, #764ba2); transition: width 0.3s;"></div>';
            html += '</div>';
            html += '<div style="font-size: 12px; color: #6b7280; margin-bottom: 8px;">当前: ' + escapeHtml(currentLabel) + ' (调用 AI 审查中, 约 5-15 秒)...</div>';
            // 已完成的杠杆
            chainResults.forEach(function(r) {
                var ok = r.status === 'success';
                var aiOk = r.ai_status === 'success';
                html += '<div style="padding: 6px 0; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; gap: 8px;">';
                html += '<span style="color: ' + (ok ? '#10b981' : '#ef4444') + '; font-size: 13px;">' + (ok ? '✓' : '✗') + '</span>';
                html += '<span style="font-size: 11px; font-weight: 600; color: #1f2937; min-width: 120px;">' + escapeHtml(r.lever_name || r.lever) + '</span>';
                html += '<span style="font-size: 10px; color: ' + (aiOk ? '#10b981' : '#f59e0b') + '; background: ' + (aiOk ? '#ecfdf5' : '#fffbeb') + '; padding: 2px 6px; border-radius: 4px;">' + (aiOk ? 'AI 已调用' : '降级模式') + '</span>';
                html += '</div>';
            });
            html += '</div>';
            chainResult.innerHTML = html;
        }

        function runOneLever(idx, retryCount) {
            if (idx >= levers.length) {
                // 全部完成, 渲染最终结果
                var finalData = assembleChainResult(chainResults, accumulated, problem, approach, steps);
                renderChainResult(finalData);
                chainBtn.disabled = false;
                chainBtn.textContent = '▶ 运行杠杆链';
                return;
            }

            var leverId = levers[idx];
            var label = leverLabels[leverId] || leverId;
            renderChainProgress(idx, levers.length, label);

            var postData = {
                lever_id: leverId,
                problem: problem,
                approach: approach,
                steps: steps,
                accumulated_analysis: accumulated,
            };
            if (skillName) postData.skill_name = skillName;

            post('linked3_cos_run_lever', postData).then(function(res){
                if (res.success && res.data) {
                    var r = res.data;
                    // 缓存杠杆中文名
                    if (r.lever_name) leverLabels[leverId] = r.lever_name;
                    chainResults.push(r);
                    // 累积 analysis 传给下一个杠杆 (链式增强)
                    if (r.status === 'success' && r.accumulated_analysis) {
                        accumulated = r.accumulated_analysis;
                    } else if (r.analysis) {
                        accumulated += '\n\n--- ' + (r.lever_name || leverId) + ' ---\n' + r.analysis;
                    }
                } else {
                    // v20.4-fix25: 自动重试3次 (retryCount=0→1→2→3), 每次间隔递增
                    if (retryCount < 3) {
                        var delay = (retryCount + 1) * 2000; // 2s, 4s, 6s
                        setTimeout(function(){ runOneLever(idx, retryCount + 1); }, delay);
                        return;
                    }
                    // 重试3次仍失败, 记录错误继续下一个
                    chainResults.push({
                        lever: leverId,
                        lever_name: label,
                        status: 'error',
                        ai_status: 'error: ' + (res.data?.message || 'AJAX 失败'),
                        analysis: '杠杆调用失败 (重试3次后): ' + escapeHtml(res.data?.message || '未知错误'),
                    });
                }
                // v20.4-fix25: 杠杆间延迟 2.5 秒, 避免连续请求触发熔断器
                setTimeout(function(){ runOneLever(idx + 1, 0); }, 2500);
            }).catch(function(err){
                // v20.4-fix25: 网络错误也自动重试3次
                if (retryCount < 3) {
                    var delay = (retryCount + 1) * 2000;
                    setTimeout(function(){ runOneLever(idx, retryCount + 1); }, delay);
                    return;
                }
                // 重试3次仍失败, 不中断整条链
                chainResults.push({
                    lever: leverId,
                    lever_name: label,
                    status: 'error',
                    ai_status: 'error: network',
                    analysis: '网络错误 (重试3次后): ' + escapeHtml(String(err.message || err)),
                });
                setTimeout(function(){ runOneLever(idx + 1, 0); }, 2500);
            });
        }

        // v20.4-fix10: 前端组装最终增强 prompt (与后端 chain_levers 逻辑一致)
        // v20.4-fix13: 清理累积分析中的乱码, 确保最终 prompt 干净可读
        function assembleChainResult(results, acc, prob, appr, stp) {
            var enhanced = '你是一个经过认知操作系统 (COS) 三代演化 + 杠杆链深度审查的专家。\n\n';
            enhanced += '<rules>\n';
            enhanced += '输出≤3×原始 | 装饰≤20% | 核心目标不偏离 | 杠杆使命不可违\n';
            enhanced += '公理刚性：需求必由[信息熵减]+[系统降维]推导 | 证伪至死：风险>8或可行<4直接抹杀\n';
            enhanced += '纳什均衡：信息密度与系统降维的平衡点 | 用户目的性优先于技术优雅\n';
            enhanced += '落地性：每条建议必须含具体操作步骤或工具示例, 禁止抽象方向\n';
            enhanced += '差异化：各杠杆审查结论已去重, 请综合而非重复\n';
            enhanced += '</rules>\n\n';
            enhanced += '## 原始问题\n' + prob + '\n\n';
            enhanced += '## 最优方案 (MVP)\n' + appr + '\n\n';
            if (stp) {
                enhanced += '## 执行步骤\n' + stp + '\n\n';
            }
            enhanced += '## 杠杆链审查结论 (经 ' + results.length + ' 个元认知杠杆深度审查)\n';
            enhanced += '以下是各杠杆对方案的审查分析, 请在执行时严格遵守其中的修正建议:\n\n';
            enhanced += cleanAiOutput(acc);
            enhanced += '\n\n## 工作要求\n';
            enhanced += '<answer_operator>\n';
            enhanced += 'Analyze(综合审查) → Synthesize(纳什均衡) → Recommend(可落地步骤) → Verify(用户价值) → Execute\n';
            enhanced += '</answer_operator>\n';
            enhanced += '1. 基于上述方案和审查结论完成用户的内容生成任务\n';
            enhanced += '2. 优先采纳杠杆链审查中指出的修正方向\n';
            enhanced += '3. 规避审查中识别的盲区和风险\n';
            enhanced += '4. 始终以用户目的为锚点, 输出必须可落地执行\n';
            enhanced += '5. 在信息密度与系统降维之间找到纳什均衡点\n';
            return {
                results: results,
                final_enhanced_prompt: enhanced,
                accumulated_analysis: acc,
            };
        }

        // 启动第一个杠杆
        runOneLever(0, 0);
    });

    // v20.4-fix13: 清理 AI 输出 — 去掉 JSON 代码块、多余空格、重复字符
    function cleanAiOutput(text) {
        if (!text) return '';
        var cleaned = String(text);
        // 去掉 ```json ... ``` 和 ``` ... ``` 代码块标记
        cleaned = cleaned.replace(/```json\s*/gi, '').replace(/```\s*/g, '');
        // 去掉行首尾的 { } [ ] (JSON 残留)
        cleaned = cleaned.replace(/^[\s{}[\]]+/, '').replace(/[\s{}[\]]+$/, '');
        // 去掉连续 3 个以上的空格 (乱码特征)
        cleaned = cleaned.replace(/ {3,}/g, ' ');
        // 去掉连续 3 个以上的换行
        cleaned = cleaned.replace(/\n{3,}/g, '\n\n');
        // 去掉连续 5 个以上的相同字符 (重复乱码, 如 """"")
        cleaned = cleaned.replace(/(.)\1{4,}/g, '$1$1');
        // 去掉行首的引号残留
        cleaned = cleaned.replace(/^\s*["""]+/gm, '');
        // trim
        cleaned = cleaned.trim();
        return cleaned;
    }

    function renderChainResult(data) {
        var results = data.results || [];
        var html = '<div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px;">';
        html += '<div style="font-size: 13px; font-weight: 600; color: #1f2937; margin-bottom: 4px;">🔗 杠杆链结果 (' + results.length + ' 个杠杆 · 链式增强)</div>';
        html += '<div style="font-size: 11px; color: #6b7280; margin-bottom: 10px;">每个杠杆真实调用 AI 审查方案, 前一杠杆的输出作为后一杠杆的输入, 形成认知增强链。</div>';
        results.forEach(function(r, idx){
            var ok = r.status === 'success';
            var aiOk = r.ai_status === 'success';
            html += '<div style="padding: 10px 0; border-bottom: 1px solid #e5e7eb;">';
            html += '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">';
            html += '<span style="color: ' + (ok ? '#10b981' : '#ef4444') + '; font-size: 14px;">' + (ok ? '✓' : '✗') + '</span>';
            html += '<span style="font-size: 12px; font-weight: 600; color: #1f2937; min-width: 140px;">' + escapeHtml(r.lever_name || r.lever) + '</span>';
            html += '<span style="font-size: 10px; color: ' + (aiOk ? '#10b981' : '#f59e0b') + '; background: ' + (aiOk ? '#ecfdf5' : '#fffbeb') + '; padding: 2px 6px; border-radius: 4px;">' + (aiOk ? 'AI 已调用' : '降级模式') + '</span>';
            html += '</div>';
            if (r.analysis) {
                // v20.4-fix13: 清理 AI 输出后再显示
                var cleanAnalysis = cleanAiOutput(r.analysis);
                html += '<div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; font-size: 12px; color: #374151; max-height: 250px; overflow-y: auto; white-space: pre-wrap; line-height: 1.6;">' + escapeHtml(cleanAnalysis) + '</div>';
            }
            html += '</div>';
        });
        // 汇总: 最终增强后的 system_prompt
        if (data.final_enhanced_prompt) {
            // v20.4-fix13: 清理最终 prompt 中的乱码
            var cleanPrompt = cleanAiOutput(data.final_enhanced_prompt);
            // v20.4-fix24: 生成唯一ID用于复制功能
            var promptId = 'cos-final-prompt-' + Date.now();
            html += '<div style="margin-top: 10px; padding: 10px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px;">';
            html += '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">';
            html += '<div style="font-size: 12px; font-weight: 600; color: #1e40af;">💎 杠杆链增强后的最终 system_prompt (可复制)</div>';
            html += '<button type="button" onclick="var t=document.getElementById(\'' + promptId + '\');t.select();document.execCommand(\'copy\');this.textContent=\'✓ 已复制\';setTimeout(function(){this.textContent=\'📋 一键复制\'}.bind(this),2000);" style="background: #2563eb; color: #fff; border: none; padding: 4px 12px; border-radius: 4px; font-size: 11px; cursor: pointer; white-space: nowrap;">📋 一键复制</button>';
            html += '</div>';
            html += '<textarea id="' + promptId + '" style="width: 100%; height: 150px; font-family: monospace; font-size: 11px; padding: 8px; border: 1px solid #93c5fd; border-radius: 6px; resize: vertical; line-height: 1.5;" readonly onclick="this.select()">' + escapeHtml(cleanPrompt) + '</textarea>';
            html += '</div>';
            html += '</div>';
        }
        html += '</div>';
        chainResult.innerHTML = html;
    }

    // ── 刷新仪表盘 ──
    function refreshDashboard() {
        post('linked3_cos_dashboard', {}).then(function(res){
            if (!res.success || !res.data) return;
            var d = res.data;
            // v20.4-fix3: dashboard AJAX 返回 {overview, top_skills, recent_evolutions}
            // 统计数据嵌套在 overview 里, 不是顶层
            var ov = d.overview || d;
            var skillEl = document.getElementById('cos-stat-skills');
            var evoEl   = document.getElementById('cos-stat-evolutions');
            var rateEl  = document.getElementById('cos-stat-success-rate');
            if (skillEl) skillEl.textContent = ov.skill_count || 0;
            if (evoEl) evoEl.textContent = ov.evolution_count || 0;
            if (rateEl) rateEl.textContent = Math.round((ov.evolution_success_rate || 0) * 100) + '%';
            var byGen = ov.by_generation || {G1: 0, G2: 0, G3: 0};
            var g1El = document.getElementById('cos-gen-g1-count');
            var g2El = document.getElementById('cos-gen-g2-count');
            var g3El = document.getElementById('cos-gen-g3-count');
            if (g1El) g1El.textContent = byGen.G1 || 0;
            if (g2El) g2El.textContent = byGen.G2 || 0;
            if (g3El) g3El.textContent = byGen.G3 || 0;
        });
        post('linked3_cos_skills', {}).then(function(res){
            if (!res.success || !res.data) return;
            renderSkillsList(res.data.skills || [], res.data.stats || {});
        });
        post('linked3_cos_archive', {n: 10}).then(function(res){
            if (!res.success || !res.data) return;
            renderArchiveList(res.data.recent || []);
        });
    }

    function renderSkillsList(skills, stats) {
        var container = document.getElementById('cos-skills-list');
        if (!container) return;
        var keys = Object.keys(skills);
        if (keys.length === 0) {
            container.innerHTML = '<div style="text-align: center; padding: 32px; color: #9ca3af; font-size: 13px;"><div style="font-size: 32px; margin-bottom: 8px; opacity: 0.4;">💎</div>暂无 Skill — 在上方"演化控制台"启动一次演化即可结晶</div>';
            return;
        }
        var html = '<div style="font-size: 11px; color: #6b7280; margin-bottom: 8px;">平均适应度: ' + (stats.avg_fitness || 0).toFixed(1) + ' · 共 ' + keys.length + ' 个 Skill</div>';
        html += '<table style="width: 100%; font-size: 12px; border-collapse: collapse;">';
        // v20.4-fix3: 添加方案预览列, 与 PHP 渲染的表头一致
        html += '<thead><tr style="background: #f9fafb;"><th style="text-align: left; padding: 8px; border-bottom: 2px solid #e5e7eb;">Skill</th><th style="padding: 8px; border-bottom: 2px solid #e5e7eb; text-align: center;">适应度</th><th style="padding: 8px; border-bottom: 2px solid #e5e7eb; text-align: center;">使用</th><th style="padding: 8px; border-bottom: 2px solid #e5e7eb; text-align: center;">领域</th><th style="text-align: left; padding: 8px; border-bottom: 2px solid #e5e7eb;">问题</th><th style="text-align: left; padding: 8px; border-bottom: 2px solid #e5e7eb;">方案预览</th><th style="padding: 8px; border-bottom: 2px solid #e5e7eb; text-align: center;">操作</th></tr></thead><tbody>';
        keys.forEach(function(name){
            var s = skills[name];
            // v20.4-fix3: 方案预览
            var approachPreview = (s.mvp_approach || '').substring(0, 40);
            if (!approachPreview) approachPreview = '(空)';
            html += '<tr><td style="padding: 8px; border-bottom: 1px solid #f3f4f6; font-weight: 600; font-family: monospace; font-size: 11px;">' + escapeHtml(name) + '</td>';
            html += '<td style="padding: 8px; border-bottom: 1px solid #f3f4f6; text-align: center;"><span style="background: #d1fae5; color: #065f46; padding: 2px 6px; border-radius: 4px; font-weight: 600;">' + (s.fitness || 0).toFixed(1) + '</span></td>';
            html += '<td style="padding: 8px; border-bottom: 1px solid #f3f4f6; text-align: center; color: #6b7280;">' + (s.usage_count || 0) + '</td>';
            html += '<td style="padding: 8px; border-bottom: 1px solid #f3f4f6; text-align: center; font-size: 11px; color: #6b7280;">' + escapeHtml(s.domain || '-') + '</td>';
            html += '<td style="padding: 8px; border-bottom: 1px solid #f3f4f6; font-size: 11px; color: #6b7280; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' + escapeHtml(s.problem || '') + '">' + escapeHtml((s.problem || '').substring(0, 30)) + '</td>';
            // v20.4-fix3: 方案预览列
            html += '<td style="padding: 8px; border-bottom: 1px solid #f3f4f6; font-size: 11px; color: #374151; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' + escapeHtml(s.mvp_approach || '') + '">' + escapeHtml(approachPreview) + '</td>';
            html += '<td style="padding: 8px; border-bottom: 1px solid #f3f4f6; text-align: center; white-space: nowrap;"><button class="cos-apply-skill-btn" data-name="' + escapeHtml(name) + '" style="background: #10b981; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; margin-right: 4px;">🚀 应用</button><button class="cos-delete-skill-btn" data-name="' + escapeHtml(name) + '" style="background: #ef4444; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; font-size: 11px; cursor: pointer;">🗑 删除</button></td></tr>';
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    }

    function renderArchiveList(recent) {
        var container = document.getElementById('cos-archive-list');
        if (!container) return;
        var arr = Object.keys(recent).map(function(k){ return recent[k]; });
        if (arr.length === 0) {
            container.innerHTML = '<div style="text-align: center; padding: 24px; color: #9ca3af; font-size: 13px;"><div style="font-size: 28px; margin-bottom: 8px; opacity: 0.4;">📚</div>暂无演化记录 — 启动一次演化即可生成归档</div>';
            return;
        }
        var html = '<div style="font-size: 11px; color: #6b7280; margin-bottom: 8px;">最近 ' + arr.length + ' 条记录</div>';
        arr.forEach(function(snap){
            var genColor = snap.generation === 'G1' ? '#3b82f6' : (snap.generation === 'G2' ? '#8b5cf6' : '#ec4899');
            html += '<div style="padding: 10px; border-bottom: 1px solid #f3f4f6;">';
            html += '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">';
            html += '<span style="background: ' + genColor + '; color: #fff; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px;">' + snap.generation + '</span>';
            html += '<span style="font-size: 12px; color: #6b7280; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' + (snap.problem || '').substring(0, 50) + '</span>';
            html += '<span style="font-size: 10px; color: #9ca3af;">' + (snap.saved_at || '') + '</span>';
            html += '</div>';
            html += '<div style="font-size: 11px; color: #9ca3af; padding-left: 32px;">方案 ' + (snap.variants_count || 0) + ' · 存活 ' + (snap.survivors_count || 0) + ' · 绞杀 ' + (snap.killed_count || 0);
            if (snap.mvp) { html += ' · MVP: ' + (snap.mvp.id || '') + ' (适应度 ' + (snap.mvp.fitness || 0) + ')'; }
            html += '</div></div>';
        });
        container.innerHTML = html;
    }
})();
</script>

<style>
@keyframes cos-spin { to { transform: rotate(360deg); } }
</style>
