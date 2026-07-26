<?php
/**
 * Partial: cos-skills
 * Extracted from: tab-cognitive-os.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
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
            <strong><?php echo esc_html__('这是什么:', 'linked3'); ?></strong><?php echo esc_html__('每次演化成功后, 最优方案 (MVP) 自动结晶为 Skill, 包含原始问题、方案、固化规则和适应度。', 'linked3'); ?><br>
            <strong><?php echo esc_html__('怎么用:', 'linked3'); ?></strong> 点击 Skill 的"应用"按钮, 生成 system_prompt, 可复制到小红书/SEO/长文/视频生成器使用。
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
                        <th style="text-align: left; padding: 8px; border-bottom: 2px solid #e5e7eb;"><?php echo esc_html__('Skill 名称', 'linked3'); ?></th>
                        <th style="padding: 8px; border-bottom: 2px solid #e5e7eb; text-align: center;"><?php echo esc_html__('适应度', 'linked3'); ?></th>
                        <th style="padding: 8px; border-bottom: 2px solid #e5e7eb; text-align: center;"><?php echo esc_html__('使用', 'linked3'); ?></th>
                        <th style="padding: 8px; border-bottom: 2px solid #e5e7eb; text-align: center;"><?php echo esc_html__('领域', 'linked3'); ?></th>
                        <th style="text-align: left; padding: 8px; border-bottom: 2px solid #e5e7eb;"><?php echo esc_html__('原始问题', 'linked3'); ?></th>
                        <th style="text-align: left; padding: 8px; border-bottom: 2px solid #e5e7eb;"><?php echo esc_html__('方案预览', 'linked3'); ?></th>
                        <th style="padding: 8px; border-bottom: 2px solid #e5e7eb; text-align: center;"><?php echo esc_html__('操作', 'linked3'); ?></th>
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

