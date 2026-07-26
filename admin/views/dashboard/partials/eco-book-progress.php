<?php
/**
 * Partial: eco-book-progress
 * Extracted from: eco-book.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
<!-- ═══════════════════════════════════════════════════════════════
     v17.2 手动模式 (折叠保留, 向后兼容)
     ═══════════════════════════════════════════════════════════════ -->
<details style="margin-top:20px;">
<summary style="cursor:pointer;padding:12px;background:#F1F5F9;border-radius:6px;font-weight:600;color:#0F172A;"><?php echo esc_html__('📝 手动模式 (v17.2 提示词生成器, 点击展开)', 'linked3'); ?></summary>

<div class="linked3-eco-card" style="margin-top:12px;">
<div class="linked3-eco-card">
    <h3><?php echo esc_html__('📖 写书式学习 — 完整垂直生态', 'linked3'); ?></h3>

    <!-- 核心哲学 -->
    <?php if (!empty($core)): ?>
    <div style="background:#FAFAFA;border:1px solid #E4E4E7;border-left:3px solid #0F172A;border-radius:6px;padding:12px;margin-bottom:16px;">
        <div style="font-size:13px;font-weight:600;color:#0F172A;margin-bottom:6px;">🧭 核心哲学: <?php echo esc_html($core['name'] ?? '痛苦精进法'); ?></div>
        <div style="font-size:12px;color:#52525B;line-height:1.8;">
            <strong>公式:</strong> <?php echo esc_html($core['formula'] ?? ''); ?><br>
            <strong>第一性原理:</strong> <?php echo esc_html($core['first_principle'] ?? ''); ?><br>
            <strong>方法论:</strong> <?php echo esc_html($core['methodology'] ?? ''); ?><br>
            <strong>目标:</strong> <?php echo esc_html($core['goal'] ?? ''); ?><br>
            <strong>忌讳:</strong> <span style="color:#DC2626;"><?php echo esc_html($core['taboo'] ?? ''); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- 工具链推荐 -->
    <?php if (!empty($tools)): ?>
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;padding:10px;background:#FAFAFA;border:1px solid #E4E4E7;border-radius:6px;">
        <span style="font-size:11px;color:#71717A;align-self:center;"><?php echo esc_html__('🔧 工具链:', 'linked3'); ?></span>
        <?php foreach ($tools as $category => $tool_list): ?>
            <?php foreach ($tool_list as $tname => $tdesc): ?>
                <span style="font-size:11px;padding:2px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;" title="<?php echo esc_attr($tdesc); ?>"><?php echo esc_html($tname); ?></span>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 写作类型选择 -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
        <label class="lk3-form-label" style="margin:0;white-space:nowrap;"><?php echo esc_html__('写作类型:', 'linked3'); ?></label>
        <select class="linked3-eco-select" id="lk3-book-type" style="width:140px;" onchange="lk3BookTypeChange(this.value)">
            <?php foreach ($types as $tid => $tinfo): ?>
                <option value="<?php echo esc_attr($tid); ?>"><?php echo esc_html($tinfo['icon'] . ' ' . $tinfo['name_cn']); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" class="linked3-eco-input" id="lk3-book-title" placeholder="<?php echo esc_attr__('书名/论文标题/剧本名...', 'linked3'); ?>" style="flex:1;min-width:200px;" value="<?php echo esc_attr__('写书式学习', 'linked3'); ?>">
    </div>

    <!-- 6步流程 -->
    <?php
    $steps = $book_kb['six_steps'] ?? [];
    $step_icons = ['①', '②', '③', '④', '⑤', '⑥'];
    $step_colors = ['#0F172A', '#059669', '#7C3AED', '#DB2777', '#475569', '#6366F1'];
    foreach ($steps as $step_key => $step_info):
        $idx = intval(substr($step_key, 4, 1)) - 1;
        $icon = $step_icons[$idx] ?? '';
        $color = $step_colors[$idx] ?? '#0F172A';
    ?>
    <div style="border:1px solid #E4E4E7;border-left:3px solid <?php echo esc_attr($color); ?>;border-radius:6px;padding:14px;margin-bottom:12px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
            <span style="font-size:16px;"><?php echo esc_html($icon); ?></span>
            <strong style="font-size:13px;color:<?php echo esc_attr($color); ?>;"><?php echo esc_html($step_info['name'] ?? ''); ?></strong>
            <span style="font-size:11px;color:#71717A;"><?php echo esc_html($step_info['desc'] ?? ''); ?></span>
        </div>

        <?php
        // 渲染提示词
        $all_prompts = [];
        if (isset($step_info['prompts'])) $all_prompts = $step_info['prompts'];
        if (isset($step_info['prompts_simple'])) $all_prompts = array_merge($all_prompts, $step_info['prompts_simple']);
        if (isset($step_info['prompts_advanced'])) $all_prompts = array_merge($all_prompts, $step_info['prompts_advanced']);
        foreach ($all_prompts as $p):
        ?>
        <div style="margin-bottom:8px;">
            <div style="display:flex;gap:6px;align-items:flex-start;">
                <textarea class="linked3-eco-input" readonly style="flex:1;font-size:12px;min-height:60px;background:#FAFAFA;" id="lk3-book-<?php echo esc_attr($step_key); ?>-<?php echo esc_attr($p['id'] ?? ''); ?>"><?php echo esc_textarea($p['text'] ?? ''); ?></textarea>
                <button class="linked3-eco-btn linked3-eco-btn-sm" onclick="lk3CopyPrompt('lk3-book-<?php echo esc_attr($step_key); ?>-<?php echo esc_attr($p['id'] ?? ''); ?>')" style="white-space:nowrap;">📋 复制</button>
            </div>
            <?php if (!empty($p['note'])): ?>
            <div style="font-size:10px;color:#A1A1AA;margin-top:2px;">💡 <?php echo esc_html($p['note']); ?> · 🔧 <?php echo esc_html($p['tool'] ?? '任意大模型'); ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if (isset($step_info['variables']) && $step_key === 'step4_expand'): ?>
        <!-- 第四步: 扩写变量控制 -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;margin-top:8px;padding:10px;background:#FAFAFA;border-radius:4px;">
            <div>
                <label style="font-size:10px;color:#71717A;display:block;margin-bottom:2px;"><?php echo esc_html__('语言', 'linked3'); ?></label>
                <select class="linked3-eco-select" id="lk3-book-s4-lang" style="font-size:11px;" onchange="lk3GenPromptS4()">
                    <option value="<?php echo esc_attr__('中文', 'linked3'); ?>">中文</option>
                    <option value="English">English</option>
                    <option value="<?php echo esc_attr__('日本語', 'linked3'); ?>">日本語</option>
                    <option value="<?php echo esc_attr__('法语', 'linked3'); ?>">法语</option>
                    <option value="<?php echo esc_attr__('德语', 'linked3'); ?>">德语</option>
                </select>
            </div>
            <div>
                <label style="font-size:10px;color:#71717A;display:block;margin-bottom:2px;"><?php echo esc_html__('读者人群', 'linked3'); ?></label>
                <input type="text" class="linked3-eco-input" id="lk3-book-s4-readers" value="<?php echo esc_attr__('所有人群', 'linked3'); ?>" style="font-size:11px;" onchange="lk3GenPromptS4()">
            </div>
            <div>
                <label style="font-size:10px;color:#71717A;display:block;margin-bottom:2px;"><?php echo esc_html__('思维模式', 'linked3'); ?></label>
                <select class="linked3-eco-select" id="lk3-book-s4-thinking" style="font-size:11px;" onchange="lk3GenPromptS4()">
                    <?php foreach ($thinking_modes as $mode): ?>
                        <option value="<?php echo esc_attr($mode); ?>"><?php echo esc_html($mode); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:10px;color:#71717A;display:block;margin-bottom:2px;"><?php echo esc_html__('小节名', 'linked3'); ?></label>
                <input type="text" class="linked3-eco-input" id="lk3-book-s4-section" value="<?php echo esc_attr__('1.1 写书式学习的起源与发展', 'linked3'); ?>" style="font-size:11px;" onchange="lk3GenPromptS4()">
            </div>
            <div>
                <label style="font-size:10px;color:#71717A;display:block;margin-bottom:2px;"><?php echo esc_html__('字数', 'linked3'); ?></label>
                <input type="number" class="linked3-eco-input" id="lk3-book-s4-words" value="3000" style="font-size:11px;" onchange="lk3GenPromptS4()">
            </div>
            <div>
                <label style="font-size:10px;color:#71717A;display:block;margin-bottom:2px;"><?php echo esc_html__('例子数', 'linked3'); ?></label>
                <input type="text" class="linked3-eco-input" id="lk3-book-s4-examples" value="2-3" style="font-size:11px;" onchange="lk3GenPromptS4()">
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($step_info['checklist'])): ?>
        <!-- 检查清单 -->
        <div style="margin-top:8px;padding:8px;background:#FAFAFA;border-radius:4px;">
            <div style="font-size:11px;font-weight:600;color:#3F3F46;margin-bottom:4px;"><?php echo esc_html__('✅ 检查清单', 'linked3'); ?></div>
            <?php foreach ($step_info['checklist'] as $item): ?>
                <label style="display:block;font-size:11px;color:#52525B;margin-bottom:2px;"><input type="checkbox"> <?php echo esc_html($item); ?></label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- 知识体系库 -->
    <?php if (!empty($knowledge_systems)): ?>
    <div style="border:1px solid #E4E4E7;border-radius:6px;padding:14px;margin-bottom:12px;">
        <div style="font-size:13px;font-weight:600;color:#0F172A;margin-bottom:10px;"><?php echo esc_html__('🧠 知识体系库', 'linked3'); ?></div>
        <?php foreach ($knowledge_systems as $ks_id => $ks_info): ?>
        <details style="margin-bottom:8px;">
            <summary style="font-size:12px;font-weight:600;color:#3F3F46;cursor:pointer;padding:4px 0;"><?php echo esc_html($ks_info['name'] ?? $ks_id); ?> — <?php echo esc_html($ks_info['desc'] ?? ''); ?></summary>
            <div style="padding:8px 12px;font-size:11px;color:#52525B;line-height:1.8;">
                <?php if (!empty($ks_info['prompts'])): ?>
                    <?php foreach ($ks_info['prompts'] as $p): ?>
                    <div style="margin-bottom:6px;padding:6px;background:#FAFAFA;border-radius:4px;">
                        <?php echo esc_html($p); ?>
                        <button class="linked3-eco-btn linked3-eco-btn-sm" style="margin-left:4px;font-size:10px;" onclick="navigator.clipboard.writeText('<?php echo esc_js($p); ?>').then(function(){alert('已复制');})">📋</button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($ks_info['phases'])): ?>
                    <?php foreach ($ks_info['phases'] as $phase_id => $phase): ?>
                    <div><strong><?php echo esc_html($phase_id); ?>:</strong> <?php echo esc_html($phase['name'] ?? ''); ?> — <?php echo esc_html($phase['desc'] ?? ''); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($ks_info['layers'])): ?>
                    <?php foreach ($ks_info['layers'] as $lid => $layer): ?>
                    <div><strong><?php echo esc_html($lid); ?>:</strong> <?php echo esc_html($layer['name'] ?? ''); ?> — <?php echo esc_html($layer['desc'] ?? ''); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($ks_info['levels'])): ?>
                    <?php foreach ($ks_info['levels'] as $level => $desc): ?>
                    <div><strong><?php echo esc_html($level); ?>:</strong> <?php echo esc_html($desc); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($ks_info['template'])): ?>
                <div style="margin-top:6px;padding:6px;background:#FAFAFA;border-radius:4px;">
                    <strong>模板:</strong> <?php echo esc_html($ks_info['template']); ?>
                </div>
                <?php endif; ?>
            </div>
        </details>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 阅读提示词库 -->
    <?php if (!empty($reading_prompts)): ?>
    <div style="border:1px solid #E4E4E7;border-radius:6px;padding:14px;margin-bottom:12px;">
        <div style="font-size:13px;font-weight:600;color:#0F172A;margin-bottom:10px;"><?php echo esc_html__('📚 阅读提示词库', 'linked3'); ?></div>
        <?php foreach ($reading_prompts as $rp_id => $rp_text): ?>
        <div style="margin-bottom:6px;padding:6px;background:#FAFAFA;border-radius:4px;font-size:11px;color:#52525B;">
            <strong><?php echo esc_html($rp_id); ?>:</strong> <?php echo esc_html($rp_text); ?>
            <button class="linked3-eco-btn linked3-eco-btn-sm" style="margin-left:4px;font-size:10px;" onclick="navigator.clipboard.writeText('<?php echo esc_js($rp_text); ?>').then(function(){alert('已复制');})">📋</button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 万能协作法 -->
    <?php if (!empty($book_kb['collaboration_method'])): ?>
    <div style="border:1px solid #E4E4E7;border-radius:6px;padding:14px;margin-bottom:12px;">
        <div style="font-size:13px;font-weight:600;color:#0F172A;margin-bottom:10px;">🤝 <?php echo esc_html($book_kb['collaboration_method']['name'] ?? '万能协作法'); ?></div>
        <div style="font-size:11px;color:#52525B;margin-bottom:8px;"><?php echo esc_html($book_kb['collaboration_method']['desc'] ?? ''); ?></div>
        <?php foreach ($book_kb['collaboration_method']['steps'] ?? [] as $step): ?>
        <div style="margin-bottom:6px;padding:6px;background:#FAFAFA;border-radius:4px;font-size:11px;color:#52525B;">
            <strong>步骤<?php echo esc_html($step['step']); ?>:</strong> <?php echo esc_html($step['name']); ?> — <?php echo esc_html($step['desc']); ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- AI原生工作流 -->
    <?php if (!empty($book_kb['workflow'])): ?>
    <div style="border:1px solid #E4E4E7;border-radius:6px;padding:14px;margin-bottom:12px;">
        <div style="font-size:13px;font-weight:600;color:#0F172A;margin-bottom:10px;">⚡ <?php echo esc_html($book_kb['workflow']['name'] ?? 'AI原生工作流'); ?></div>
        <div style="font-size:11px;color:#52525B;margin-bottom:8px;"><?php echo esc_html($book_kb['workflow']['desc'] ?? ''); ?></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;">
            <?php foreach ($book_kb['workflow']['roles'] ?? [] as $role_id => $role_desc): ?>
            <div style="padding:6px;background:#FAFAFA;border-radius:4px;font-size:11px;color:#52525B;">
                <strong><?php echo esc_html($role_id); ?>:</strong> <?php echo esc_html($role_desc); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($book_kb['workflow']['metrics'])): ?>
        <div style="margin-top:8px;font-size:11px;color:#52525B;">
            <strong><?php echo esc_html__('实测数据:', 'linked3'); ?></strong>
            日迭代<?php echo esc_html($book_kb['workflow']['metrics']['daily_iterations'] ?? ''); ?> ·
            并行项目<?php echo esc_html($book_kb['workflow']['metrics']['parallel_projects'] ?? ''); ?> ·
            人效提升<?php echo esc_html($book_kb['workflow']['metrics']['efficiency_boost'] ?? ''); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 出版社资源 -->
    <?php if (!empty($book_kb['publishing'])): ?>
    <details style="border:1px solid #E4E4E7;border-radius:6px;padding:14px;margin-bottom:12px;">
        <summary style="font-size:13px;font-weight:600;color:#0F172A;cursor:pointer;">🏢 <?php echo esc_html($book_kb['publishing']['name'] ?? '出版社资源'); ?> (共<?php echo esc_html($book_kb['publishing']['total'] ?? 100); ?>家)</summary>
        <div style="font-size:11px;color:#52525B;margin-top:8px;line-height:1.8;">
            <?php echo esc_html($book_kb['publishing']['desc'] ?? ''); ?>
            <div style="margin-top:6px;display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:4px;">
                <?php foreach ($book_kb['publishing']['categories'] ?? [] as $cat => $count): ?>
                <div style="padding:4px 6px;background:#FAFAFA;border-radius:4px;"><?php echo esc_html($cat); ?>类: <?php echo esc_html($count); ?>家</div>
                <?php endforeach; ?>
            </div>
        </div>
    </details>
    <?php endif; ?>
</div>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-book.js
// Pass partial-local $current_project_id to JS via inline script
wp_add_inline_script('linked3-eco-book', 'window.linked3_eco_book.project_id = ' . wp_json_encode($current_project_id ?? 0) . ';', 'after');
?>
</details><!-- /手动模式 details -->


