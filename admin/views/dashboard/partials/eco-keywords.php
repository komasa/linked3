<?php
/**
 * 关键词子面板 v17.0 — 全功能链整合 (热词采集 + 三维度生成 + 历史)
 *
 * v17.0 更新:
 *   - UI全量优化: 参照Linear/Notion的关键词管理界面规范
 *   - 保留全部功能: 热词采集/AI长尾词/三维度分类/批量文章入口
 *
 * @package Linked3
 * @version 17.2.0
 * @date 2026-06-28
 */
if (!defined('ABSPATH')) exit;
$nonce_kw = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');
$kw_seed_preset = isset($_GET['kw_seed']) ? sanitize_text_field($_GET['kw_seed']) : '';

// 加载持久化热词库/长尾词库
$saved_hot = (array) get_option(LINKED3_OPTION_PREFIX . 'hot_keywords', []);
$saved_tail = (array) get_option(LINKED3_OPTION_PREFIX . 'tail_keywords', []);
$saved_hot_str = implode("\n", $saved_hot);
$saved_tail_str = implode("\n", $saved_tail);
$saved_hot_count = count($saved_hot);
$saved_tail_count = count($saved_tail);

// v16.0.14 [公理α: H↓ 消除"用过没"不确定性] [公理β: dim↓ 0维自动替代手动记忆]
// 长尾词使用状态持久化: 记录每个长尾词是否已用于生成文章
$saved_tail_used = (array) get_option(LINKED3_OPTION_PREFIX . 'tail_keywords_used', []);
$saved_tail_used_json = wp_json_encode($saved_tail_used);
$saved_tail_used_count = count($saved_tail_used);
?>

<div class="linked3-eco-card">
    <h3>🔑 关键词全功能链 — 热词采集 + AI生成 + 三维度分类</h3>
    <p style="color:#71717A;font-size:12px;margin-bottom:16px;">① 热词采集 → ② AI长尾词生成 → ③ 三维度分类 → ④ 批量文章入口</p>

    <!-- ① 热词采集 -->
    <h4 style="font-size:13px;margin:12px 0 6px;color:#3F3F46;">🔥 第①步:热词采集 (多源)</h4>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;align-items:center;">
        <select class="linked3-eco-select" id="kw-source" style="width:120px;">
            <?php
            // v16.0.15 [公理α: H↓ 消除选源不确定性] [公理β: dim↓ 0维默认替代1维选择]
            // 默认"全部源": 用户无需决策即可获得最大覆盖, 单源作为高级选项
            $kw_sources = [
                'all'    => '🌐 全部源 (推荐)',
                'baidu'  => '百度',
                'sogou'  => '搜狗',
                '360'    => '360',
                'zhihu'  => '知乎',
                'weibo'  => '微博',
                'douyin' => '抖音',
            ];
            $kw_source_default = 'all'; // v16.0.15: 默认全部源
            foreach ($kw_sources as $src_val => $src_label) {
                $selected = ($src_val === $kw_source_default) ? ' selected' : '';
                echo '<option value="' . esc_attr($src_val) . '"' . $selected . '>' . esc_html($src_label) . '</option>';
            }
            ?>
        </select>
        <input type="text" class="linked3-eco-input" id="kw-seed" placeholder="种子词(可选, 留空采集实时热榜)" style="flex:1;min-width:200px;" value="<?php echo esc_attr($kw_seed_preset); ?>">
        <button class="linked3-eco-btn linked3-eco-btn-secondary" id="kw-fetch-hot">🔥 采集热词</button>
    </div>

    <!-- 热词库 (持久化) -->
    <div style="margin-bottom:12px;">
        <label style="font-size:12px;color:#71717A;">
            📋 热词库 <span id="kw-hot-count" style="color:#999;">(<?php echo (int)$saved_hot_count; ?>个, 自动保存)</span>
        </label>
        <textarea id="kw-hot-list" rows="6" class="linked3-eco-input" style="width:100%;font-family:monospace;line-height:1.6;" placeholder="点击「采集热词」后结果会显示在这里。也可手动输入, 每行一个。编辑后自动保存。"><?php echo esc_textarea($saved_hot_str); ?></textarea>
    </div>

    <!-- ② AI长尾词生成 (v17.2.0 R1: 支持多热词批量) -->
    <h4 style="font-size:13px;margin:12px 0 6px;color:#3F3F46;">🔑 第②步:AI 生成长尾关键词</h4>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;align-items:center;">
        <span style="font-size:12px;color:#71717A;">生成</span>
        <input type="number" class="linked3-eco-input" id="kw-count" value="20" min="5" max="100" style="width:70px;">
        <span style="font-size:12px;color:#71717A;">个</span>
        <!-- v17.2.0 R1: 单种子词 vs 全热词库批量 -->
        <button class="linked3-eco-btn" id="kw-generate">🔑 单种子生成长尾词</button>
        <button class="linked3-eco-btn linked3-eco-btn-secondary" id="kw-generate-multi">🔥 用全部热词批量生成</button>
        <label style="font-size:12px;color:#71717A;"><input type="checkbox" id="kw-append" checked> 追加到长尾词库</label>
    </div>
    <p style="font-size:11px;color:#9ca3af;margin:0 0 8px 0;">💡 单种子: 基于输入的一个种子词生成长尾词 | 全热词: 遍历热词库每个热词各生成长尾词(覆盖面广)</p>

    <!-- 长尾词库 (持久化) v11.9.1: 增强可见性 — 醒目卡片+空状态引导 -->
    <!-- v16.0.14: 增加使用状态徽章 (已用/未用) -->
    <div style="margin-bottom:12px;background:#FAFAFA;border:2px solid #0F172A;border-radius:8px;padding:12px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
            <label style="font-size:14px;font-weight:600;color:#0F172A;">
                📋 长尾词库 <span id="kw-tail-count" style="color:#0F172A;font-weight:normal;">(<?php echo (int)$saved_tail_count; ?>个)</span>
                <span id="kw-tail-used-count" class="lk3-badge lk3-badge-success" style="margin-left:8px;font-size:11px;">已用 <?php echo (int)$saved_tail_used_count; ?></span>
                <span id="kw-tail-unused-count" class="lk3-badge lk3-badge-warning" style="margin-left:4px;font-size:11px;">未用 <?php echo (int)max(0, $saved_tail_count - $saved_tail_used_count); ?></span>
            </label>
            <div style="display:flex;gap:6px;">
                <button class="linked3-eco-btn linked3-eco-btn-sm" id="kw-tail-export" style="font-size:11px;">⬇️ 导出</button>
                <button class="linked3-eco-btn linked3-eco-btn-sm" id="kw-tail-clear" style="font-size:11px;color:#DC2626;">🗑️ 清空</button>
                <button class="linked3-eco-btn linked3-eco-btn-sm" id="kw-tail-reset-used" style="font-size:11px;color:#71717A;" title="重置所有使用状态">↺ 重置状态</button>
            </div>
        </div>
        <textarea id="kw-tail-list" rows="6" class="linked3-eco-input" style="width:100%;font-family:monospace;line-height:1.6;background:#fff;" placeholder="长尾词库为空。请先: ①采集热词 → ②点击「单种子生成长尾词」或「用全部热词批量生成」→ 长尾词会自动保存到这里。&#10;&#10;也可手动输入, 每行一个长尾词, 编辑后自动保存。"><?php echo esc_textarea($saved_tail_str); ?></textarea>
        <div id="kw-tail-status-preview" style="margin-top:6px;font-size:11px;color:#71717A;"></div>
        <?php if ($saved_tail_count == 0) : ?>
        <p style="font-size:11px;color:#0F172A;margin:6px 0 0 0;">💡 长尾词库当前为空。生成长尾词后会自动保存到这里, 后续可用于CSV批量生成文章或一键生态生产。</p>
        <?php endif; ?>
    </div>

    <!-- ③ 三维度分类结果 -->
    <h4 style="font-size:13px;margin:12px 0 6px;color:#3F3F46;">📊 第③步:三维度分类结果</h4>
    <div id="kw-result" style="margin-top:8px;"></div>

    <!-- ④ 批量生成文章入口 -->
    <h4 style="font-size:13px;margin:12px 0 6px;color:#3F3F46;">📝 第④步:用长尾词库生成文章</h4>
    <div style="background:#F4F4F5;border:1px solid #86efac;border-radius:6px;padding:12px;">
        <p style="font-size:12px;color:#166534;margin:0 0 10px 0;">当前长尾词库: <strong id="kw-tail-count-display"><?php echo esc_html($saved_tail_count); ?></strong> 个词。选择生成方式:</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <!-- v11.0.6 #7: 一键跳转到CSV批量, 自动填入长尾词库 -->
            <button class="linked3-eco-btn" id="kw-to-csv-batch">📊 用长尾词库CSV批量生成</button>
            <!-- v11.0.6 #7: 一键跳转到生态协同, 自动填入第一个长尾词作为主题 -->
            <button class="linked3-eco-btn linked3-eco-btn-secondary" id="kw-to-synergy">🚀 用首个长尾词一键生态生产</button>
        </div>
        <p style="font-size:11px;color:#71717A;margin:8px 0 0 0;">💡 CSV批量: 每个长尾词生成一篇文章 (适合批量生产)<br>💡 生态协同: 用第一个长尾词作为主题, 走完整5阶段流程 (适合单篇精修)</p>
    </div>

    <!-- ⑤ 定时任务 -->
    <details style="margin-top:12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:8px 12px;">
        <summary style="cursor:pointer;font-weight:600;color:#666;font-size:12px;">⏰ 定时获取热词 + 生成长尾词 (AutoGPT)</summary>
        <div style="margin-top:8px;font-size:12px;">
            <p style="color:#71717A;">设置定时任务, 自动采集热词并生成长尾词, 追加到长尾词库。</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <label>频率:
                    <select class="linked3-eco-select" id="kw-cron-freq" style="width:120px;">
                        <option value="hourly">每小时</option>
                        <option value="twicedaily" selected>每天两次</option>
                        <option value="daily">每天</option>
                    </select>
                </label>
                <label>每次生成:
                    <input type="number" class="linked3-eco-input" id="kw-cron-count" value="30" min="5" max="100" style="width:60px;">
                    个
                </label>
                <button class="linked3-eco-btn linked3-eco-btn-secondary" id="kw-cron-enable">启用定时任务</button>
                <button class="linked3-eco-btn linked3-eco-btn-secondary" id="kw-cron-disable">禁用</button>
            </div>
            <div id="kw-cron-status" style="margin-top:6px;"></div>
        </div>
    </details>
</div>

<script>
(function(){
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
    var nonce = '<?php echo esc_js($nonce_kw); ?>';
    var synergyUrl = '<?php echo esc_js(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=synergy')); ?>';

    function escHtml(s) {
        if (s == null) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function updateCount() {
        var hotLines = document.getElementById('kw-hot-list').value.split('\n').filter(function(s){return s.trim();});
        var tailLines = document.getElementById('kw-tail-list').value.split('\n').filter(function(s){return s.trim();});
        document.getElementById('kw-hot-count').textContent = '(' + hotLines.length + '个, 自动保存)';
        document.getElementById('kw-tail-count').textContent = '(' + tailLines.length + '个, 自动保存)';
    }

    function saveKeywordLib() {
        // v16.0.24修复: 分别保存热词库和长尾词库 (原代码未传type, 导致长尾词永远存到hot_keywords, 刷新后丢失)
        var hot = document.getElementById('kw-hot-list').value;
        var tail = document.getElementById('kw-tail-list').value;

        // 保存热词库
        var fdHot = new FormData();
        fdHot.append('action', 'linked3_eco_keywords_save');
        fdHot.append('nonce', nonce);
        fdHot.append('type', 'hot');
        fdHot.append('keywords', hot);
        fetch(ajaxUrl, {method:'POST', body:fdHot, credentials:'same-origin'}).then(function(r){return r.json();}).catch(function(){});

        // 保存长尾词库
        var fdTail = new FormData();
        fdTail.append('action', 'linked3_eco_keywords_save');
        fdTail.append('nonce', nonce);
        fdTail.append('type', 'tail');
        fdTail.append('keywords', tail);
        fetch(ajaxUrl, {method:'POST', body:fdTail, credentials:'same-origin'}).then(function(r){return r.json();}).catch(function(){});
    }

    document.addEventListener('DOMContentLoaded', function(){
        updateCount();

        // 热词库/长尾词库自动保存
        var saveTimer;
        ['kw-hot-list', 'kw-tail-list'].forEach(function(id){
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', function(){
                    updateCount();
                    clearTimeout(saveTimer);
                    saveTimer = setTimeout(saveKeywordLib, 1500);
                });
            }
        });

        // 采集热词
        var fetchBtn = document.getElementById('kw-fetch-hot');
        if (fetchBtn) {
            fetchBtn.addEventListener('click', function(){
                var source = document.getElementById('kw-source').value;
                var seed = document.getElementById('kw-seed').value.trim();
                fetchBtn.disabled = true;
                fetchBtn.textContent = '采集中...';

                var fd = new FormData();
                fd.append('action', 'linked3_eco_hot_collect');
                fd.append('nonce', nonce);
                fd.append('source', source);
                fd.append('seed', seed);

                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(function(data){
                        fetchBtn.disabled = false;
                        fetchBtn.textContent = '🔥 采集热词';
                        // v11.0.2 #2: 修复字段名 hot_words (原代码误用 keywords)
                        if (data.success && (data.data.hot_words || data.data.keywords)) {
                            var newKw = data.data.hot_words || data.data.keywords || [];
                            var existing = document.getElementById('kw-hot-list').value.trim();
                            var combined = existing ? existing + '\n' + newKw.join('\n') : newKw.join('\n');
                            document.getElementById('kw-hot-list').value = combined;
                            updateCount();
                            saveKeywordLib();
                            // v11.0.2 #2: 显示具体采集到的热词 (不只是"采集成功")
                            var previewHtml = '<div class="notice notice-success inline"><p>✅ 采集到 ' + newKw.length + ' 个热词 (来源: ' + escHtml(source) + ')</p>';
                            previewHtml += '<details style="margin-top:6px;"><summary style="cursor:pointer;font-size:12px;">查看热词列表</summary><div style="background:#f9fafb;padding:8px;border-radius:4px;margin-top:4px;font-size:12px;line-height:1.8;">';
                            newKw.forEach(function(kw, i){ previewHtml += '<span style="display:inline-block;background:#F4F4F5;color:#0F172A;padding:2px 8px;border-radius:3px;margin:2px;">' + escHtml(kw) + '</span>'; });
                            previewHtml += '</div></details></div>';
                            document.getElementById('kw-result').innerHTML = previewHtml;
                        } else {
                            document.getElementById('kw-result').innerHTML =
                                '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '采集失败') + '</p></div>';
                        }
                    })
                    .catch(function(e){
                        fetchBtn.disabled = false;
                        fetchBtn.textContent = '🔥 采集热词';
                        document.getElementById('kw-result').innerHTML =
                            '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                    });
            });
        }

        // AI生成长尾词
        var genBtn = document.getElementById('kw-generate');
        if (genBtn) {
            genBtn.addEventListener('click', function(){
                generateKeywords('single');
            });
        }

        // v17.2.0 R1: 用全部热词批量生成长尾词
        var genMultiBtn = document.getElementById('kw-generate-multi');
        if (genMultiBtn) {
            genMultiBtn.addEventListener('click', function(){
                generateKeywords('multi');
            });
        }

        // v17.2.0 R1: 统一关键词生成函数 (支持single/multi模式)
        function generateKeywords(mode) {
            var seedInput = document.getElementById('kw-seed').value.trim();
            var hotListRaw = document.getElementById('kw-hot-list').value;
            var hotLines = hotListRaw ? hotListRaw.split('\n').map(function(s){return s.trim();}).filter(function(s){return s;}) : [];
            var hotFirst = hotLines[0] || '';
            var seed = seedInput || hotFirst || '';
            var count = parseInt(document.getElementById('kw-count').value) || 20;
            var append = document.getElementById('kw-append').checked;

            if (mode === 'multi') {
                if (hotLines.length === 0) {
                    alert('热词库为空, 请先采集热词或手动输入热词后再用"全部热词批量生成"');
                    return;
                }
            } else {
                if (!seed) {
                    alert('请输入种子词, 或先采集热词后从热词库选一个作为种子。');
                    return;
                }
            }

            var btn = mode === 'multi' ? genMultiBtn : genBtn;
            btn.disabled = true;
            btn.textContent = '生成中...';

            var fd = new FormData();
            fd.append('action', 'linked3_eco_keywords');
            fd.append('nonce', nonce);
            fd.append('count', count);
            fd.append('mode', mode);
            if (mode === 'multi') {
                fd.append('seeds', hotLines.join('\n'));
            } else {
                fd.append('seed', seed);
            }

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(function(data){
                        btn.disabled = false;
                        btn.textContent = mode === 'multi' ? '🔥 用全部热词批量生成' : '🔑 单种子生成长尾词';
                        if (data.success && data.data.keywords) {
                            var kw = data.data.keywords;
                            var classified = data.data.classified || {};
                            var primary = classified.primary || [];
                            var longTail = classified.long_tail || [];
                            var question = classified.question || [];

                            // 追加到长尾词库
                            if (append) {
                                var existing = document.getElementById('kw-tail-list').value.trim();
                                var combined = existing ? existing + '\n' + kw.join('\n') : kw.join('\n');
                                document.getElementById('kw-tail-list').value = combined;
                                updateCount();
                                saveKeywordLib();
                            }

                            // 三维度分类展示
                            var html = '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:8px;">';
                            html += '<div style="background:#F4F4F5;padding:8px;border-radius:4px;"><strong>主词 (' + primary.length + ')</strong><div style="margin-top:4px;font-size:12px;">' + (primary.slice(0,5).map(escHtml).join(', ') || '无') + '</div></div>';
                            html += '<div style="background:#dcfce7;padding:8px;border-radius:4px;"><strong>长尾 (' + longTail.length + ')</strong><div style="margin-top:4px;font-size:12px;">' + (longTail.slice(0,5).map(escHtml).join(', ') || '无') + '</div></div>';
                            html += '<div style="background:#FEF3C7;padding:8px;border-radius:4px;"><strong>疑问 (' + question.length + ')</strong><div style="margin-top:4px;font-size:12px;">' + (question.slice(0,5).map(escHtml).join(', ') || '无') + '</div></div>';
                            html += '</div>';
                            html += '<div style="background:#f9fafb;padding:8px;border-radius:4px;margin-top:8px;"><strong>全部关键词 (' + kw.length + ')</strong><div style="margin-top:4px;font-size:12px;">' + kw.map(escHtml).join(', ') + '</div></div>';
                            html += '<div style="margin-top:8px;"><a class="linked3-eco-btn linked3-eco-btn-secondary" style="display:inline-block;text-decoration:none;" href="' + synergyUrl + '&topic=' + encodeURIComponent(seed) + '">→ 送入生态生产</a></div>';
                            document.getElementById('kw-result').innerHTML = html;
                        } else {
                            document.getElementById('kw-result').innerHTML =
                                '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '生成失败') + '</p></div>';
                        }
                    })
                    .catch(function(e){
                        btn.disabled = false;
                        btn.textContent = mode === 'multi' ? '🔥 用全部热词批量生成' : '🔑 单种子生成长尾词';
                        document.getElementById('kw-result').innerHTML =
                            '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                    });
        }

        // v17.2.0 R2: 长尾词库导出
        var tailExport = document.getElementById('kw-tail-export');
        if (tailExport) {
            tailExport.addEventListener('click', function(){
                var tailList = document.getElementById('kw-tail-list').value.trim();
                if (!tailList) { alert('长尾词库为空'); return; }
                var blob = new Blob([tailList], {type:'text/plain;charset=utf-8'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'linked3-tail-keywords-' + Date.now() + '.txt';
                a.click();
                setTimeout(function(){ URL.revokeObjectURL(url); }, 1000);
            });
        }

        // v17.2.0 R2: 长尾词库清空
        var tailClear = document.getElementById('kw-tail-clear');
        if (tailClear) {
            tailClear.addEventListener('click', function(){
                if (!confirm('确定清空长尾词库? 此操作不可撤销。')) return;
                document.getElementById('kw-tail-list').value = '';
                updateCount();
                saveKeywordLib();
                alert('✅ 长尾词库已清空');
            });
        }

        // 定时任务启用
        var cronEnable = document.getElementById('kw-cron-enable');
        if (cronEnable) {
            cronEnable.addEventListener('click', function(){
                var freq = document.getElementById('kw-cron-freq').value;
                var count = document.getElementById('kw-cron-count').value;
                var fd = new FormData();
                fd.append('action', 'linked3_eco_cron_enable');
                fd.append('nonce', nonce);
                fd.append('freq', freq);
                fd.append('count', count);

                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){return r.json();})
                    .then(function(data){
                        document.getElementById('kw-cron-status').innerHTML =
                            '<div class="notice notice-success inline"><p>✅ ' + escHtml(data.data && data.data.message ? data.data.message : '定时任务已启用') + '</p></div>';
                    });
            });
        }

        // 定时任务禁用
        var cronDisable = document.getElementById('kw-cron-disable');
        if (cronDisable) {
            cronDisable.addEventListener('click', function(){
                var fd = new FormData();
                fd.append('action', 'linked3_eco_cron_disable');
                fd.append('nonce', nonce);
                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){return r.json();})
                    .then(function(data){
                        document.getElementById('kw-cron-status').innerHTML =
                            '<div class="notice notice-info inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '定时任务已禁用') + '</p></div>';
                    });
            });
        }

        // v11.0.6 #7: 长尾词库 → 生成文章入口
        // v17.2.0 R4: 修复路由 — 旧tab=creation&cr_sub=ecosystem改为tab=creation&cr_sub=ecosystem
        var csvBtn = document.getElementById('kw-to-csv-batch');
        if (csvBtn) {
            csvBtn.addEventListener('click', function(){
                var tailList = document.getElementById('kw-tail-list').value.trim();
                if (!tailList) { alert('长尾词库为空, 请先生成长尾词'); return; }
                var topics = tailList.split('\n').map(function(s){return s.trim();}).filter(function(s){return s;}).join('\n');
                var baseUrl = '<?php echo esc_js(admin_url("admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=content&cw_mode=csv")); ?>';
                window.location.href = baseUrl + '&tail_topics=' + encodeURIComponent(topics);
            });
        }
        var synBtn = document.getElementById('kw-to-synergy');
        if (synBtn) {
            synBtn.addEventListener('click', function(){
                var tailList = document.getElementById('kw-tail-list').value.trim();
                if (!tailList) { alert('长尾词库为空, 请先生成长尾词'); return; }
                var firstTail = tailList.split('\n').map(function(s){return s.trim();}).filter(function(s){return s;})[0];
                if (!firstTail) { alert('长尾词库为空'); return; }
                var baseUrl = '<?php echo esc_js(admin_url("admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=synergy")); ?>';
                window.location.href = baseUrl + '&topic=' + encodeURIComponent(firstTail);
                // v16.0.14: 标记该长尾词为已使用
                markTailUsed(firstTail);
            });
        }

        // v16.0.14 [公理α/β]: 长尾词使用状态管理 — 自动持久化 + 徽章更新
        var tailUsedMap = <?php echo $saved_tail_used_json ?: '{}'; ?>;

        function updateTailUsedBadges() {
            var tailLines = document.getElementById('kw-tail-list').value.split('\n')
                .map(function(s){return s.trim();}).filter(function(s){return s;});
            var total = tailLines.length;
            var used = 0;
            tailLines.forEach(function(kw){
                if (tailUsedMap[kw]) used++;
            });
            var usedEl = document.getElementById('kw-tail-used-count');
            var unusedEl = document.getElementById('kw-tail-unused-count');
            if (usedEl) usedEl.textContent = '已用 ' + used;
            if (unusedEl) unusedEl.textContent = '未用 ' + Math.max(0, total - used);
            // 预览前5个未用词
            var previewEl = document.getElementById('kw-tail-status-preview');
            if (previewEl) {
                var unused = tailLines.filter(function(kw){return !tailUsedMap[kw];}).slice(0, 5);
                if (unused.length > 0) {
                    previewEl.innerHTML = '📌 待用词 (前5): ' + unused.map(escHtml).join(' · ');
                } else if (total > 0) {
                    previewEl.innerHTML = '<span style="color:#16a34a;">✓ 全部长尾词已使用</span>';
                }
            }
        }

        function markTailUsed(keyword) {
            if (!keyword) return;
            tailUsedMap[keyword] = 1;
            saveTailUsed();
            updateTailUsedBadges();
        }

        function saveTailUsed() {
            var fd = new FormData();
            fd.append('action', 'linked3_eco_tail_used_save');
            fd.append('nonce', nonce);
            fd.append('used_map', JSON.stringify(tailUsedMap));
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'}).catch(function(){});
        }

        // CSV批量也标记已用
        if (csvBtn) {
            csvBtn.addEventListener('click', function(){
                var tailList = document.getElementById('kw-tail-list').value.trim();
                if (!tailList) return;
                tailList.split('\n').forEach(function(s){
                    var kw = s.trim();
                    if (kw) tailUsedMap[kw] = 1;
                });
                saveTailUsed();
            }, true); // capture phase, 先于原 handler 执行
        }

        // 重置使用状态
        var resetBtn = document.getElementById('kw-tail-reset-used');
        if (resetBtn) {
            resetBtn.addEventListener('click', function(){
                if (!confirm('确认重置所有长尾词的使用状态？此操作不可撤销。')) return;
                tailUsedMap = {};
                saveTailUsed();
                updateTailUsedBadges();
            });
        }

        updateTailUsedBadges();
    });
})();
</script>
