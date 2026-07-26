<?php
/**
 * Partial: cos-archive
 * Extracted from: tab-cognitive-os.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
    <!-- ═══════════════════════════════════════════════════════════════
         演化归档 — 历史快照
    ═══════════════════════════════════════════════════════════════ -->
    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h2 style="margin: 0 0 8px; font-size: 16px; font-weight: 600; color: #1f2937; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 20px;">📚</span> 演化归档 — 历史快照
        </h2>
        <p style="margin: 0 0 12px; font-size: 12px; color: #6b7280;">
            <strong><?php echo esc_html__('这是什么:', 'linked3'); ?></strong><?php echo esc_html__('每代演化 (G1/G2/G3) 的完整快照, 包含方案种群、评分、MVP。', 'linked3'); ?><br>
            <strong><?php echo esc_html__('怎么用:', 'linked3'); ?></strong> 用于回溯历史演化过程, 对比不同问题的演化结果。
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

