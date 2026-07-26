<?php
/**
 * Partial: sfp-axis-ai
 * Extracted from: style-fusion-panel-v2.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
    <!-- ===== 轴②: 按策略AI推荐 (F-01~F-09 推荐引擎) ===== -->
    <div class="lk3-sfp-v2-section">
        <div class="lk3-sfp-v2-section-title"><?php echo esc_html__('② 按策略AI推荐', 'linked3'); ?><span class="lk3-sfp-v2-axis-tag"><?php echo esc_html__('轴: 推荐策略', 'linked3'); ?></span></div>
        <div class="lk3-sfp-v2-recommend-row">
            <select class="lk3-sfp-v2-select lk3-sfp-v2-mode-select" style="flex:1;min-width:200px;">
                <option value="auto"><?php echo esc_html__('F-01 一键智能推荐 (Top3候选)', 'linked3'); ?></option>
                <option value="beginner"><?php echo esc_html__('F-02 新手友好 (生产就绪优先)', 'linked3'); ?></option>
                <option value="designer"><?php echo esc_html__('F-03 设计师精选 (信息图+融合技法)', 'linked3'); ?></option>
                <option value="market"><?php echo esc_html__('F-04 万兴市场优选 (wondershare_ready)', 'linked3'); ?></option>
                <option value="industry"><?php echo esc_html__('F-05 行业专家 (行业匹配翻倍)', 'linked3'); ?></option>
                <option value="accessible"><?php echo esc_html__('F-06 无障碍优先 (高对比+信息图)', 'linked3'); ?></option>
                <option value="conversion"><?php echo esc_html__('F-07 高转化推荐 (真人摄影+CTA)', 'linked3'); ?></option>
                <option value="complex"><?php echo esc_html__('F-08 复杂内容 (融合技法+多模块)', 'linked3'); ?></option>
                <option value="cross-platform"><?php echo esc_html__('F-09 跨平台适配 (G5/G6生产级)', 'linked3'); ?></option>
            </select>
            <!-- v2.0: 合并原"AI自动适配"为开关, 消除双按钮冗余 -->
            <label class="lk3-sfp-v2-autopick">
                <input type="checkbox" class="lk3-sfp-v2-autopick-cb" checked>
                <span><?php echo esc_html__('☑ 自动选用Top1', 'linked3'); ?></span>
            </label>
            <button type="button" class="lk3-sfp-v2-btn lk3-sfp-v2-btn-recommend"><?php echo esc_html__('🤖 AI推荐', 'linked3'); ?></button>
        </div>
        <div class="lk3-sfp-v2-hint"><?php echo esc_html__('💡 勾选"自动选用Top1"=推荐后直接锁定最佳画风(原AI自动适配); 取消勾选=展示TopN候选卡片手动选(原AI推荐)', 'linked3'); ?></div>
    </div>

    <!-- 推荐结果展示区 -->
    <div class="lk3-sfp-v2-result"></div>
</div>

(function() {
    // ===== v2.0: 每实例独立初始化, 避免重复绑定 =====
    var panel = document.querySelector('.lk3-sfp-v2[data-instance="<?php echo esc_js($instance); ?>"]');
    if (!panel || panel.dataset.v2Init === '1') return;
    panel.dataset.v2Init = '1';

    var instance = panel.dataset.instance;
    var styleSelectId = panel.dataset.styleSelectId;
    var visualStyleSelectId = panel.dataset.visualStyleSelectId; // v2.0: 可选联动
    var topicInputId = panel.dataset.topicInputId;
    var nonce = panel.dataset.nonce;
    var ajaxUrl = panel.dataset.ajaxUrl;

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"']/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function getStyleSelect() { return document.getElementById(styleSelectId); }
    function getTopicText() {
        var el = document.getElementById(topicInputId);
        return el && el.value ? el.value.trim() : '';
    }
    function getResultEl() { return panel.querySelector('.lk3-sfp-v2-result'); }

    // ===== v2.0: 重建画风下拉时始终保留"自动适配"选项 (修复冲突-3) =====
    function rebuildStyleSelect(stylesObj) {
        var ss = getStyleSelect();
        if (!ss) return;
        var prevVal = ss.value; // 记住当前选择
        ss.innerHTML = '';
        // 始终首位保留"自动适配"
        var autoOpt = document.createElement('option');
        autoOpt.value = 'auto';
        autoOpt.textContent = '🤖 自动适配 (后端生成时推断最佳画风)';
        ss.appendChild(autoOpt);
        // 追加筛选结果
        Object.keys(stylesObj).forEach(function(sid) {
            var opt = document.createElement('option');
            opt.value = sid;
            opt.textContent = stylesObj[sid].label || sid;
            ss.appendChild(opt);
        });
        // 尝试恢复之前的选择
        for (var i = 0; i < ss.options.length; i++) {
            if (ss.options[i].value === prevVal) { ss.selectedIndex = i; break; }
        }
    }

    // ===== v2.0: 联动信息图技法下拉 (解耦硬编码, 通过参数传递) =====
    function syncVisualStyleAvailability(view) {
        if (!visualStyleSelectId) return; // 未配置则跳过 (genesis/video 实例)
        var vss = document.getElementById(visualStyleSelectId);
        if (!vss) return;
        var wrap = vss.closest('div');
        if (view === 'infographic' || view === 'all') {
            vss.disabled = false;
            vss.style.opacity = '1';
            vss.style.cursor = '';
            if (wrap) wrap.style.opacity = '1';
        } else {
            vss.disabled = true;
            vss.style.opacity = '0.5';
            vss.style.cursor = 'not-allowed';
            if (wrap) wrap.style.opacity = '0.6';
        }
    }

    // ===== 轴①: 视图过滤按钮 =====
    panel.querySelectorAll('.lk3-sfp-v2-view-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            panel.querySelectorAll('.lk3-sfp-v2-view-btn').forEach(function(b) {
                b.classList.remove('lk3-sfp-v2-view-active');
            });
            btn.classList.add('lk3-sfp-v2-view-active');

            var view = btn.dataset.view;
            var fd = new FormData();
            fd.append('action', 'linked3_genesis_styles_filtered');
            fd.append('nonce', nonce);
            fd.append('view', view);

            var resultEl = getResultEl();
            resultEl.innerHTML = '<div style="color:#71717A;font-size:12px;"><?php echo esc_html__('⏳ 加载风格列表...', 'linked3'); ?></div>';

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    if (!res.success) {
                        resultEl.innerHTML = '<div style="color:#EF4444;font-size:12px;"><?php echo esc_html__('❌ \' + escapeHtml(res.data && res.data.message || \'加载失败\') + \'', 'linked3'); ?></div>';
                        return;
                    }
                    var styles = res.data.styles || {};
                    var count = res.data.count || 0;
                    rebuildStyleSelect(styles); // v2.0: 保留auto选项
                    syncVisualStyleAvailability(view); // v2.0: 解耦联动
                    resultEl.innerHTML = '<div style="color:#10B981;font-size:12px;"><?php echo esc_html__('✅ 视图 [\' + escapeHtml(view) + \'] 筛选完成, 共 \' + count + \' 个风格已载入上方画风下拉 (自动适配选项已保留)', 'linked3'); ?></div>';
                })
                .catch(function(e) {
                    resultEl.innerHTML = '<div style="color:#EF4444;font-size:12px;"><?php echo esc_html__('❌ 网络错误: \' + escapeHtml(e.message) + \'', 'linked3'); ?></div>';
                });
        });
    });

    // ===== 轴②: AI推荐 (v2.0 合并双按钮为单按钮+开关) =====
    var recBtn = panel.querySelector('.lk3-sfp-v2-btn-recommend');
    if (recBtn) {
        recBtn.addEventListener('click', function() {
            var topic = getTopicText();
            if (!topic) {
                alert('请先在内容输入框填写内容, AI推荐将基于内容匹配最佳画风');
                return;
            }
            var modeSelect = panel.querySelector('.lk3-sfp-v2-mode-select');
            var mode = modeSelect ? modeSelect.value : 'auto';
            var autopick = panel.querySelector('.lk3-sfp-v2-autopick-cb').checked; // v2.0: 开关

            recBtn.disabled = true;
            recBtn.textContent = '⏳ AI分析中...';

            var fd = new FormData();
            fd.append('action', 'linked3_genesis_recommend');
            fd.append('nonce', nonce);
            fd.append('content', topic);
            fd.append('mode', mode);
            fd.append('industry', 'general');

            var resultEl = getResultEl();
            var autopickHint = autopick ? '⚡ 自动选用Top1模式' : '📋 候选卡片模式';
            resultEl.innerHTML = '<div style="color:#4f46e5;font-size:12px;"><?php echo esc_html__('🤖 AI引擎 [\' + escapeHtml(mode) + \'] 分析中 · \' + autopickHint + \'...', 'linked3'); ?></div>';

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    recBtn.disabled = false;
                    recBtn.textContent = '🤖 AI推荐';
                    if (!res.success) {
                        resultEl.innerHTML = '<div style="color:#EF4444;font-size:12px;"><?php echo esc_html__('❌ \' + escapeHtml(res.data && res.data.message || \'推荐失败\') + \'', 'linked3'); ?></div>';
                        return;
                    }
                    var recs = (res.data && res.data.recommendations) || [];
                    if (recs.length === 0) {
                        resultEl.innerHTML = '<div style="color:#F59E0B;font-size:12px;"><?php echo esc_html__('⚠️ 未找到匹配风格, 请尝试其他策略或调整内容', 'linked3'); ?></div>';
                        return;
                    }

                    // v2.0: 根据开关决定行为
                    if (autopick) {
                        // 自动选用Top1 (原AI自动适配行为)
                        var top1 = recs[0];
                        var sid = top1.style_id || top1.id || '';
                        var ss = getStyleSelect();
                        if (ss && sid) {
                            for (var i = 0; i < ss.options.length; i++) {
                                if (ss.options[i].value === sid) {
                                    ss.selectedIndex = i;
                                    ss.style.background = '#F4F4F5';
                                    setTimeout(function(){ ss.style.background = ''; }, 2000);
                                    break;
                                }
                            }
                        }
                    }

                    // 渲染候选卡片 (无论开关都展示, 方便用户切换)
                    var html = '<div style="font-size:11px;color:#4f46e5;margin-bottom:6px;font-weight:600;">🎯 Top' + recs.length + ' 推荐 (策略: ' + escapeHtml(mode) + ')' + (autopick ? ' · Top1已自动选中' : '') + '</div>';
                    html += '<div class="lk3-sfp-v2-rec-grid">';
                    recs.forEach(function(rec, idx) {
                        var score = rec.match_score || 0;
                        var isTop1 = idx === 0;
                        var cls = isTop1 ? 'lk3-sfp-v2-rec-card lk3-sfp-v2-rec-card-top1' : 'lk3-sfp-v2-rec-card lk3-sfp-v2-rec-card-other';
                        var label = isTop1 ? (autopick ? '✅ 已自动选中' : '⭐ Top1') : '点击选用';
                        html += '<div class="' + cls + '" data-style-id="' + escapeHtml(rec.style_id || rec.id || '') + '">';
                        html += '<div class="lk3-sfp-v2-rec-card-label" style="color:' + (isTop1 ? '#6366f1' : '#10B981') + ';">' + label + '</div>';
                        html += '<div class="lk3-sfp-v2-rec-card-name">' + (idx + 1) + '. ' + escapeHtml(rec.name_cn || rec.style_id || rec.id || '') + '</div>';
                        html += '<div class="lk3-sfp-v2-rec-card-cat">' + escapeHtml(rec.category || '') + '</div>';
                        html += '<div class="lk3-sfp-v2-rec-card-score"><?php echo esc_html__('匹配分:', 'linked3'); ?><strong>' + score + '</strong></div>';
                        if (rec.reason) html += '<div class="lk3-sfp-v2-rec-card-reason">' + escapeHtml(rec.reason) + '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                    html += '<div style="font-size:10px;color:#A1A1AA;margin-top:6px;"><?php echo esc_html__('💡 点击任意卡片可切换上方画风下拉', 'linked3'); ?></div>';
                    resultEl.innerHTML = html;

                    // 卡片点击 → 切换画风
                    panel.querySelectorAll('.lk3-sfp-v2-rec-card').forEach(function(card) {
                        card.addEventListener('click', function() {
                            var csid = this.dataset.styleId;
                            var ss = getStyleSelect();
                            if (ss) {
                                for (var j = 0; j < ss.options.length; j++) {
                                    if (ss.options[j].value === csid) {
                                        ss.selectedIndex = j;
                                        ss.style.background = '#F4F4F5';
                                        setTimeout(function(){ ss.style.background = ''; }, 2000);
                                        break;
                                    }
                                }
                            }
                        });
                    });
                })
                .catch(function(e) {
                    recBtn.disabled = false;
                    recBtn.textContent = '🤖 AI推荐';
                    resultEl.innerHTML = '<div style="color:#EF4444;font-size:12px;"><?php echo esc_html__('❌ 网络错误: \' + escapeHtml(e.message) + \'', 'linked3'); ?></div>';
                });
        });
    }
})();
</script>

