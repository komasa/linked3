<?php
/**
 * Partial: eco-book-main
 * Extracted from: eco-book.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
<!-- ═══════════════════════════════════════════════════════════════
     v18.5 写书工厂控制台
     ═══════════════════════════════════════════════════════════════ -->
<div class="linked3-eco-card" style="background:linear-gradient(135deg,#0F172A 0%,#1E293B 100%);color:#fff;border:none;margin-bottom:20px;">
    <h3 style="color:#fff;margin-top:0;"><?php echo esc_html__('🚀 写书工厂 v18.5 — 一键自动出书', 'linked3'); ?></h3>
    <p style="color:#CBD5E1;margin-bottom:18px;"><?php echo esc_html__('选类型 → 选模式 → 选档位 → 一键启动 → 自动6步执行 → 下载书稿', 'linked3'); ?></p>

    <!-- 工厂输入区 -->
    <div id="lk3-book-factory-input" style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <input type="text" id="lk3-bf-book-title" placeholder="<?php echo esc_attr__('输入书名,如《AI产品经理实战手册》', 'linked3'); ?>"
               style="flex:1;min-width:240px;padding:10px 12px;border-radius:6px;border:1px solid #334155;background:#1E293B;color:#fff;">

        <select id="lk3-bf-type" style="padding:10px 12px;border-radius:6px;border:1px solid #334155;background:#1E293B;color:#fff;">
<?php foreach ($factory_types as $tkey => $tlabel): ?>
            <option value="<?php echo esc_attr($tkey); ?>"><?php echo esc_html($tlabel); ?></option>
<?php endforeach; ?>
        </select>

        <select id="lk3-bf-mode" style="padding:10px 12px;border-radius:6px;border:1px solid #334155;background:#1E293B;color:#fff;">
<?php foreach ($factory_modes as $mkey => $mlabel): ?>
            <option value="<?php echo esc_attr($mkey); ?>"><?php echo esc_html($mlabel); ?></option>
<?php endforeach; ?>
        </select>

        <select id="lk3-bf-level" style="padding:10px 12px;border-radius:6px;border:1px solid #334155;background:#1E293B;color:#fff;">
<?php foreach ($factory_levels as $lkey => $ldata): ?>
            <option value="<?php echo esc_attr($lkey); ?>" title="<?php echo esc_attr($ldata['description']); ?>"><?php echo esc_html($ldata['label']); ?></option>
<?php endforeach; ?>
        </select>

        <button id="lk3-bf-start" type="button"
                style="padding:10px 24px;border-radius:6px;border:none;background:#3B82F6;color:#fff;font-weight:600;cursor:pointer;">
            🚀 一键启动写书工厂
        </button>
    </div>

    <!-- 错误提示 -->
    <div id="lk3-bf-error" style="display:none;background:#7F1D1D;color:#FECACA;padding:10px 12px;border-radius:6px;margin-bottom:12px;"></div>

    <!-- 进度面板 -->
    <div id="lk3-bf-progress-panel" style="display:none;background:#1E293B;padding:16px;border-radius:6px;border:1px solid #334155;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h4 style="color:#fff;margin:0;"><?php echo esc_html__('📊 写书进度', 'linked3'); ?></h4>
            <span id="lk3-bf-status" style="color:#94A3B8;font-size:13px;"><?php echo esc_html__('准备中...', 'linked3'); ?></span>
        </div>

        <div style="background:#0F172A;border-radius:4px;height:8px;overflow:hidden;margin-bottom:12px;">
            <div id="lk3-bf-progress-bar" style="background:linear-gradient(90deg,#3B82F6,#10B981);height:100%;width:0%;transition:width 0.5s;"></div>
        </div>

        <div style="display:flex;gap:16px;margin-bottom:12px;font-size:13px;color:#CBD5E1;flex-wrap:wrap;">
            <div><?php echo esc_html__('当前步骤:', 'linked3'); ?><span id="lk3-bf-current-step" style="color:#10B981;font-weight:600;">-</span></div>
            <div><?php echo esc_html__('章节进度:', 'linked3'); ?><span id="lk3-bf-chapter-progress" style="color:#10B981;font-weight:600;">0/0</span></div>
            <div><?php echo esc_html__('💰 已用:', 'linked3'); ?><span id="lk3-bf-cost" style="color:#FBBF24;font-weight:600;">$0.00</span></div>
            <div>📊 Token: <span id="lk3-bf-tokens" style="color:#FBBF24;font-weight:600;">0</span></div>
            <div><?php echo esc_html__('⏱️ 耗时:', 'linked3'); ?><span id="lk3-bf-elapsed" style="color:#FBBF24;font-weight:600;">00:00</span></div>
        </div>

        <div style="margin-bottom:12px;">
            <div style="color:#94A3B8;font-size:12px;margin-bottom:4px;"><?php echo esc_html__('实时日志:', 'linked3'); ?></div>
            <pre id="lk3-bf-log-content" style="background:#0F172A;color:#A5F3FC;padding:10px;border-radius:4px;max-height:180px;overflow-y:auto;font-size:12px;line-height:1.5;margin:0;white-space:pre-wrap;"></pre>
        </div>

        <!-- N4: 当前提示词显示区 (执行时可见) -->
        <div id="lk3-bf-current-prompt-area" style="display:none;margin-bottom:12px;background:#0F172A;padding:10px;border-radius:6px;border:1px solid #475569;">
            <div style="color:#A5F3FC;font-size:12px;margin-bottom:4px;font-weight:600;"><?php echo esc_html__('📝 当前正在使用的提示词:', 'linked3'); ?></div>
            <pre id="lk3-bf-current-prompt" style="background:#1E293B;color:#E2E8F0;padding:8px;border-radius:4px;max-height:120px;overflow-y:auto;font-size:11px;line-height:1.4;margin:0;white-space:pre-wrap;"></pre>
        </div>

        <!-- v18.10: 实时输出显示区 (AI生成的完整内容) -->
        <div id="lk3-bf-current-output-area" style="display:none;margin-bottom:12px;background:#0F172A;padding:10px;border-radius:6px;border:1px solid #10B981;">
            <div style="color:#10B981;font-size:12px;margin-bottom:4px;font-weight:600;"><?php echo esc_html__('📄 AI实时输出内容:', 'linked3'); ?></div>
            <pre id="lk3-bf-current-output" style="background:#1E293B;color:#F0FDF4;padding:8px;border-radius:4px;max-height:200px;overflow-y:auto;font-size:11px;line-height:1.5;margin:0;white-space:pre-wrap;"></pre>
        </div>

        <!-- v18.10: 增量下载区 (随时可下载, 不等全部完成) -->
        <div id="lk3-bf-incremental-download" style="display:none;margin-bottom:12px;background:#1E293B;padding:8px;border-radius:6px;border:1px solid #475569;">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span style="color:#94A3B8;font-size:11px;"><?php echo esc_html__('📥 增量下载(进行中也可下载):', 'linked3'); ?></span>
                <button class="lk3-bf-dl-btn" data-format="markdown" style="padding:4px 10px;border-radius:3px;border:none;background:#475569;color:#fff;cursor:pointer;font-size:11px;">📄 Markdown</button>
                <button class="lk3-bf-dl-btn" data-format="html" style="padding:4px 10px;border-radius:3px;border:none;background:#475569;color:#fff;cursor:pointer;font-size:11px;">🌐 HTML</button>
            </div>
        </div>

        <div id="lk3-bf-download-area" style="display:none;background:#0F172A;padding:12px;border-radius:6px;border:1px solid #10B981;">
            <div style="color:#10B981;font-weight:600;margin-bottom:8px;"><?php echo esc_html__('✅ 书稿已完成! 选择下载格式:', 'linked3'); ?></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button class="lk3-bf-dl-btn" data-format="markdown" style="padding:8px 16px;border-radius:4px;border:none;background:#10B981;color:#fff;cursor:pointer;"><?php echo esc_html__('📄 下载 Markdown', 'linked3'); ?></button>
                <button class="lk3-bf-dl-btn" data-format="html" style="padding:8px 16px;border-radius:4px;border:none;background:#3B82F6;color:#fff;cursor:pointer;"><?php echo esc_html__('🌐 下载 HTML', 'linked3'); ?></button>
                <button id="lk3-bf-copy-clipboard" type="button" style="padding:8px 16px;border-radius:4px;border:none;background:#6366F1;color:#fff;cursor:pointer;"><?php echo esc_html__('📋 复制到剪贴板', 'linked3'); ?></button>
            </div>
        </div>
    </div>
</div>


<!-- N3: 提示词预览/编辑面板 -->
<div class="linked3-eco-card" style="margin-top:16px;border:1px solid #E4E4E7;">
    <details>
        <summary style="cursor:pointer;padding:12px;font-weight:600;color:#0F172A;"><?php echo esc_html__('📝 提示词预览与编辑 (可见·可改·可保存, 点击展开)', 'linked3'); ?></summary>
        <div style="padding:12px;">
            <p style="font-size:12px;color:#71717A;margin:0 0 12px 0;"><?php echo esc_html__('提示词默认从知识库加载, 根据书名/类型/模式自动填充变量。你可以预览、修改并保存自定义提示词, 工厂执行时优先使用你保存的版本。', 'linked3'); ?></p>
            <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;align-items:center;">
                <label style="font-size:12px;color:#0F172A;"><?php echo esc_html__('选择步骤:', 'linked3'); ?></label>
                <select id="lk3-bf-prompt-step" style="padding:6px 10px;border-radius:4px;border:1px solid #D4D4D8;">
                    <option value="step1_demo"><?php echo esc_html__('① AI演示', 'linked3'); ?></option>
                    <option value="step2_explore"><?php echo esc_html__('② 探索主题', 'linked3'); ?></option>
                    <option value="step3_outline" selected><?php echo esc_html__('③ 撰写大纲', 'linked3'); ?></option>
                    <option value="step4_expand"><?php echo esc_html__('④ 扩写小节', 'linked3'); ?></option>
                    <option value="step6_review"><?php echo esc_html__('⑥ 阅读修改', 'linked3'); ?></option>
                </select>
                <button id="lk3-bf-preview-prompt" type="button" style="padding:6px 14px;border-radius:4px;border:none;background:#3B82F6;color:#fff;cursor:pointer;font-size:12px;"><?php echo esc_html__('👁 预览(填充变量)', 'linked3'); ?></button>
                <button id="lk3-bf-save-prompt" type="button" style="padding:6px 14px;border-radius:4px;border:none;background:#10B981;color:#fff;cursor:pointer;font-size:12px;"><?php echo esc_html__('💾 保存自定义提示词', 'linked3'); ?></button>
            </div>
            <div style="margin-bottom:8px;">
                <div style="font-size:11px;color:#71717A;margin-bottom:4px;">提示词模板 (变量: {book_title} {book_type} {type_unit} {chapter_title} {section_title} {word_count}):</div>
                <textarea id="lk3-bf-prompt-editor" style="width:100%;min-height:120px;padding:10px;border-radius:4px;border:1px solid #D4D4D8;font-size:12px;font-family:monospace;background:#FAFAFA;" placeholder="<?php echo esc_attr__('点击预览查看填充后的提示词, 或直接编辑后保存', 'linked3'); ?>"></textarea>
            </div>
            <div id="lk3-bf-prompt-vars" style="font-size:11px;color:#71717A;background:#FAFAFA;padding:8px;border-radius:4px;border:1px solid #E4E4E7;"></div>
        </div>
    </details>
</div>

<!-- v18.5 工厂 JS -->
<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-book.js ?>


