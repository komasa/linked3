<?php
/**
 * Dashboard partial: ☁ 云模版总控 v17.2.0
 *
 * 公理S: 云模版是总控母版库 — 写作生态/图示/漫画/视频所有非自定义模版的唯一真源
 * 公理T: 写作生态本地模版从云模版拉取母版, 隔离修改, 不污染云模版 (场景隔离)
 *
 * 功能:
 *   1. 母版库管理 (内置 + 自定义母版, CRUD)
 *   2. 跨生态分发 (写作/图示/漫画/视频消费同一母版池)
 *   3. Fork机制 (拉取母版到写作生态本地, 隔离修改)
 *   4. 母版分类: content/seo/social/video/comic/charts
 *
 * 数据存储: wp_options linked3_cloud_master_templates (母版, 只读)
 *           wp_options linked3_cloud_templates (本地fork, 可修改)
 *
 * @package Linked3
 * @version 17.2.0
 * @date 2026-06-24
 */
if (!defined('ABSPATH')) exit;

$nonce_cloud = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');

// 加载母版库 (内置 + 自定义)
$master_templates = [];
$industry_variants = []; // v11.4.1: 行业变体矩阵
if (class_exists('Linked3_Cloud_Template_Factory')) {
    $factory = new \Linked3_Cloud_Template_Factory();
    $master_categories = $factory->get_categories();
    $industries = $factory->get_industries(); // v11.4.1
    foreach ($master_categories as $cat) {
        try {
            $tpl = $factory->load_template_by_category($cat);
            $master_templates[$cat] = $tpl;
            // v11.4.1: 加载该分类的所有行业变体
            $industry_variants[$cat] = $factory->get_all_variants_for_category($cat);
        } catch (\Throwable $e) {}
    }
}

// 加载自定义母版 (用户添加到云模版池的)
$custom_masters = (array) get_option(LINKED3_OPTION_PREFIX . 'cloud_master_templates', []);

// 加载本地fork (写作生态本地模版)
$local_forks = (array) get_option(LINKED3_OPTION_PREFIX . 'cloud_templates', []);

// 跨生态消费统计
$eco_consumers = [
    'ecosystem' => ['name' => '🚀 写作生态', 'count' => count($local_forks)],
    'charts'    => ['name' => '📊 图示脚本', 'count' => 0],
    'genesis'   => ['name' => '🎨 漫画脚本', 'count' => 0],
    'video'     => ['name' => '🎬 视频脚本', 'count' => 0],
];
?>

<div class="wrap">
<h2>☁ 云模版总控 <span style="font-size:12px;color:#71717A;font-weight:normal;">v11.4.1 · 母版库 · 跨生态分发 · 行业多元化</span></h2>

<div class="notice notice-info inline"><p><strong>云模版公理:</strong> 云模版是所有非自定义模版的<strong>唯一真源</strong>。写作生态/图示/漫画/视频脚本从这里拉取母版, <strong>隔离修改不污染母版</strong>。v11.4.1新增<strong>行业多元化</strong>: 10类母版 × 5行业 = 50个场景化母版。</p></div>

<!-- v11.4.1: 行业多元化概览 -->
<?php if (!empty($industries) && !empty($industry_variants)): ?>
<div class="linked3-eco-card" style="margin:15px 0;">
    <h3>🏭 行业多元化母版矩阵 <span style="font-size:11px;color:#71717A;font-weight:normal;">10类 × 5行业 = 50场景</span></h3>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0;">
        <?php foreach ($industries as $ind_slug => $ind_meta): ?>
            <span style="padding:4px 10px;background:#fff;border:1px solid #d1d5db;border-radius:12px;font-size:12px;">
                <?php echo esc_html($ind_meta['icon'] . ' ' . $ind_meta['label']); ?>
            </span>
        <?php endforeach; ?>
    </div>
    <p style="font-size:12px;color:#71717A;margin:4px 0 0 0;">💡 每类母版支持通用/电商/教育/科技/医疗5种行业变体。创作时选择对应行业，AI将按行业调性生成内容（电商重转化、教育重循序渐进、科技重深度、医疗重合规）。</p>
</div>

<!-- v11.6.1: A/B变体预览 (G5-P0) — 同分类多行业并排对比 -->
<div class="linked3-eco-card" style="margin:15px 0;">
    <h3>🔬 A/B 变体预览 <span style="font-size:11px;color:#71717A;font-weight:normal;">选分类 → 并排对比5个行业变体差异</span></h3>
    <p style="font-size:12px;color:#71717A;margin:4px 0 8px 0;">选择一个分类，下方并排展示该分类在5个行业下的角色调性、风格、目标差异，辅助决策用哪个行业变体。</p>
    <select id="lk3-ab-category" class="linked3-eco-select" style="margin-bottom:12px;max-width:300px;">
        <?php foreach ($master_categories as $cat): ?>
        <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html(ucfirst($cat)); ?></option>
        <?php endforeach; ?>
    </select>
    <div id="lk3-ab-result" style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;"></div>
</div>

<script>
(function(){
    // v11.6.1: A/B变体数据 (PHP注入)
    var abData = <?php echo json_encode($industry_variants ?? []); ?>;
    var sel = document.getElementById('lk3-ab-category');
    var result = document.getElementById('lk3-ab-result');
    if (!sel || !result) return;

    function renderAB(cat) {
        var variants = abData[cat] || [];
        if (!variants.length) { result.innerHTML = '<p style="color:#9ca3af;">无数据</p>'; return; }
        result.innerHTML = variants.map(function(v){
            var cfg = (v.template && v.template.config) || {};
            return '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:10px;">'
                + '<div style="font-size:14px;margin-bottom:6px;">' + v.industry_icon + ' <strong>' + v.industry_label + '</strong></div>'
                + '<div style="font-size:10px;color:#71717A;margin-bottom:4px;"><strong>角色:</strong> ' + (cfg.role || '—').substring(0, 40) + '</div>'
                + '<div style="font-size:10px;color:#71717A;margin-bottom:4px;"><strong>风格:</strong> ' + (cfg.style || '—') + '</div>'
                + '<div style="font-size:10px;color:#71717A;"><strong>目标:</strong> ' + ((cfg.goals || []).slice(0,2).join(', ') || '—') + '</div>'
                + '</div>';
        }).join('');
    }
    sel.addEventListener('change', function(){ renderAB(this.value); });
    renderAB(sel.value);
})();
</script>
<?php endif; ?>

<!-- 跨生态分发概览 (v11.4.4: 增强消费可视化) -->
<div class="linked3-eco-card">
    <h3>跨生态分发概览 <span style="font-size:11px;color:#71717A;font-weight:normal;">云模版被哪些生态消费</span></h3>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:8px;">
        <?php foreach ($eco_consumers as $tab => $info): ?>
        <div style="background:#f9fafb;padding:12px;border-radius:4px;text-align:center;border:1px solid #e5e7eb;">
            <div style="font-size:24px;margin-bottom:4px;"><?php echo esc_html(explode(' ', $info['name'])[0]); ?></div>
            <div style="font-size:13px;font-weight:600;"><?php echo esc_html(explode(' ', $info['name'], 2)[1]); ?></div>
            <div style="font-size:11px;color:#71717A;margin-top:4px;">
                本地实例: <strong style="color:<?php echo $info['count'] > 0 ? '#16a34a' : '#9ca3af'; ?>;"><?php echo esc_html($info['count']); ?></strong>
            </div>
            <?php if ($info['count'] > 0): ?>
            <span style="display:inline-block;margin-top:4px;padding:2px 6px;background:#dcfce7;color:#16a34a;border-radius:8px;font-size:10px;">✓ 活跃消费</span>
            <?php else: ?>
            <span style="display:inline-block;margin-top:4px;padding:2px 6px;background:#f3f4f6;color:#9ca3af;border-radius:8px;font-size:10px;">未消费</span>
            <?php endif; ?>
            <br><a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=' . $tab)); ?>" style="font-size:11px;color:#0F172A;text-decoration:none;">进入 →</a>
        </div>
        <?php endforeach; ?>
    </div>
    <p style="font-size:11px;color:#71717A;margin:8px 0 0 0;">💡 "本地实例"表示该生态从云模版Fork的模版数量。数值为0表示该生态尚未消费云模版——前往对应生态创建内容时会自动拉取母版。</p>
</div>

<!-- 母版库 -->
<div class="linked3-eco-card">
    <h3>母版库 (只读真源) <button class="linked3-eco-btn linked3-eco-btn-secondary" id="cloud-add-master" style="float:right;">+ 添加自定义母版</button></h3>
    <p style="color:#71717A;font-size:13px;">内置母版由系统提供, 自定义母版可编辑/删除。所有母版对4个生态只读可见。</p>

    <table class="widefat striped" style="margin-top:10px;">
        <thead>
            <tr>
                <th style="width:120px;">分类</th>
                <th>母版名称</th>
                <th style="width:100px;">类型</th>
                <th style="width:150px;">适用生态</th>
                <th style="width:180px;">操作</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($master_templates as $cat => $tpl): ?>
            <?php
            // v10.8.0: 内置母版锁定机制 — 默认灰色锁定, 解锁=Fork可编辑副本
            $unlocked_forks = array_filter($local_forks, function($f) use ($cat) {
                return ($f['source_master'] ?? '') === 'builtin_' . $cat;
            });
            $is_unlocked = !empty($unlocked_forks);
            ?>
            <tr style="<?php echo $is_unlocked ? '' : 'background:#f9fafb;'; ?>">
                <td><code style="background:#F4F4F5;color:#0F172A;padding:2px 6px;border-radius:3px;"><?php echo esc_html($cat); ?></code></td>
                <td>
                    <strong><?php echo esc_html($tpl['name'] ?? $cat . '_default'); ?></strong>
                    <?php if (!$is_unlocked): ?>
                        <span style="font-size:11px;color:#9ca3af;" title="内置母版已锁定, 修改请先解锁(Fork)">🔒 锁定</span>
                    <?php else: ?>
                        <span style="font-size:11px;color:#10B981;" title="已解锁, 存在可编辑本地副本">🔓 已解锁</span>
                    <?php endif; ?>
                </td>
                <td><span style="font-size:11px;color:#71717A;">内置母版</span></td>
                <td><span style="font-size:11px;">写作/图示/漫画/视频</span></td>
                <td>
                    <?php if ($is_unlocked): ?>
                        <button class="button button-small cloud-fork-btn" data-cat="<?php echo esc_attr($cat); ?>" data-source="builtin">📥 再次Fork</button>
                        <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=templates&edit=' . esc_attr(array_key_first($unlocked_forks)))); ?>">✏ 编辑副本</a>
                    <?php else: ?>
                        <button class="button button-small cloud-unlock-btn" data-cat="<?php echo esc_attr($cat); ?>" data-source="builtin" title="解锁=Fork一个可编辑本地副本, 不影响母版">🔓 解锁编辑</button>
                        <button class="button button-small cloud-preview-btn" data-cat="<?php echo esc_attr($cat); ?>">👁 预览</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php foreach ($custom_masters as $mid => $tpl): ?>
            <tr style="background:#fefce8;">
                <td><code style="background:#FEF3C7;color:#92400E;padding:2px 6px;border-radius:3px;"><?php echo esc_html($tpl['type'] ?? 'custom'); ?></code></td>
                <td><strong><?php echo esc_html($tpl['name'] ?? $mid); ?></strong> <span style="font-size:10px;color:#92400E;">(自定义)</span></td>
                <td><span style="font-size:11px;color:#92400E;">自定义母版</span></td>
                <td><span style="font-size:11px;">写作/图示/漫画/视频</span></td>
                <td>
                    <button class="button button-small cloud-fork-btn" data-cat="<?php echo esc_attr($tpl['type'] ?? 'custom'); ?>" data-master-id="<?php echo esc_attr($mid); ?>" data-source="custom">📥 Fork</button>
                    <button class="button button-small cloud-edit-master-btn" data-master-id="<?php echo esc_attr($mid); ?>">✏ 编辑</button>
                    <button class="button button-small button-link-delete cloud-del-master-btn" data-master-id="<?php echo esc_attr($mid); ?>">🗑 删除</button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($master_templates) && empty($custom_masters)): ?>
            <tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:20px;">暂无自定义母版。上方内置母版可直接 Fork 使用, 或点击右上角「+ 添加自定义母版」。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 本地Fork列表 (跨生态) -->
<div class="linked3-eco-card">
    <h3>本地实例 (Fork副本, 可修改)</h3>
    <p style="color:#71717A;font-size:13px;">从母版Fork的本地副本, 修改不影响母版。写作生态本地模版在此管理。</p>
    <table class="widefat striped" style="margin-top:10px;">
        <thead>
            <tr>
                <th>本地实例名</th>
                <th style="width:100px;">来源母版</th>
                <th style="width:120px;">所属生态</th>
                <th style="width:150px;">更新时间</th>
                <th style="width:150px;">操作</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($local_forks as $fid => $tpl): ?>
            <tr>
                <td><strong><?php echo esc_html($tpl['name'] ?? $fid); ?></strong></td>
                <td><code style="font-size:11px;"><?php echo esc_html($tpl['type'] ?? 'content'); ?></code></td>
                <td><span style="font-size:11px;">🚀 写作生态</span></td>
                <td><span style="font-size:11px;color:#71717A;"><?php echo esc_html($tpl['updated_at'] ?? '-'); ?></span></td>
                <td>
                    <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=templates&edit=' . $fid)); ?>">✏ 编辑</a>
                    <button class="button button-small cloud-sync-btn" data-fork-id="<?php echo esc_attr($fid); ?>" title="从源母版拉取最新内容覆盖本地">🔄 同步</button>
                    <button class="button button-small cloud-promote-btn" data-fork-id="<?php echo esc_attr($fid); ?>" title="将本地副本收录为自定义母版">⬆ 收录</button>
                    <button class="button button-small button-link-delete cloud-del-fork-btn" data-fork-id="<?php echo esc_attr($fid); ?>">🗑 删除</button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($local_forks)): ?>
            <tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:20px;">暂无本地实例。请点击上方母版库的「🔓 解锁编辑」或「📥 Fork」按钮创建本地副本。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</div>

<!-- 母版预览对话框 -->
<div id="cloud-preview-dialog" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;max-width:600px;max-height:80vh;overflow-y:auto;z-index:99999;box-shadow:0 4px 20px rgba(0,0,0,0.15);">
    <h3 id="cloud-preview-title">母版预览</h3>
    <div id="cloud-preview-body" style="margin-top:10px;"></div>
    <button class="button" onclick="document.getElementById('cloud-preview-dialog').style.display='none';" style="margin-top:10px;">关闭</button>
</div>

<!-- 添加/编辑母版对话框 -->
<div id="cloud-master-dialog" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;max-width:650px;max-height:85vh;overflow-y:auto;z-index:99999;box-shadow:0 4px 20px rgba(0,0,0,0.15);">
    <h3 id="cloud-master-title">添加自定义母版</h3>
    <input type="hidden" id="cloud-master-edit-id" value="">
    <table class="form-table" style="margin-top:10px;">
        <tr><th>母版名称</th><td><input type="text" id="cloud-master-name" class="regular-text" placeholder="如: 小红书种草文母版"></td></tr>
        <tr><th>分类</th><td>
            <select id="cloud-master-type">
                <option value="content">内容(content)</option>
                <option value="seo">SEO(seo)</option>
                <option value="social">社媒(social)</option>
                <option value="video">视频(video)</option>
                <option value="comic">漫画(comic)</option>
                <option value="charts">图示(charts)</option>
            </select>
        </td></tr>
        <tr><th>Profile</th><td><textarea id="cloud-master-profile" rows="2" class="large-text" placeholder="角色画像..."></textarea></td></tr>
        <tr><th>Role</th><td><textarea id="cloud-master-role" rows="2" class="large-text" placeholder="职责..."></textarea></td></tr>
        <tr><th>Scene</th><td><textarea id="cloud-master-scene" rows="2" class="large-text" placeholder="场景..."></textarea></td></tr>
        <tr><th>Style</th><td><input type="text" id="cloud-master-style" class="regular-text" placeholder="如: professional"></td></tr>
        <tr><th>Goals (逗号分隔)</th><td><input type="text" id="cloud-master-goals" class="large-text" placeholder="目标1, 目标2"></td></tr>
        <tr><th>Output</th><td><textarea id="cloud-master-output" rows="3" class="large-text" placeholder="输出格式..."></textarea></td></tr>
    </table>
    <div style="margin-top:15px;">
        <button class="button button-primary" id="cloud-master-save">保存母版</button>
        <button class="button" onclick="document.getElementById('cloud-master-dialog').style.display='none';">取消</button>
    </div>
</div>

<div id="cloud-status" style="margin-top:10px;"></div>

<script>
(function(){
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
    var nonce = '<?php echo esc_js($nonce_cloud); ?>';
    function escHtml(s){s=String(s==null?'':s);return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}

    // Fork母版到写作生态本地
    document.querySelectorAll('.cloud-fork-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var cat = btn.getAttribute('data-cat');
            var source = btn.getAttribute('data-source');
            var masterId = btn.getAttribute('data-master-id');
            if (!confirm('确认Fork此母版到写作生态本地? (本地副本可修改, 不影响母版)')) return;

            var fd = new FormData();
            fd.append('action', 'linked3_cloud_fork');
            fd.append('nonce', nonce);
            fd.append('category', cat);
            fd.append('source', source);
            fd.append('master_id', masterId);

            btn.disabled = true;
            btn.textContent = 'Forking...';

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
                .then(function(data){
                    btn.disabled = false;
                    btn.textContent = '📥 Fork到写作生态';
                    var status = document.getElementById('cloud-status');
                    if (data.success) {
                        var ecoUrl = '<?php echo esc_js(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=templates')); ?>';
                        status.innerHTML = '<div class="notice notice-success inline"><p>✅ ' + escHtml(data.data.message) + ' <a href="' + ecoUrl + '">→ 去编辑本地模版</a></p></div>';
                        setTimeout(function(){ location.reload(); }, 2000);
                    } else {
                        status.innerHTML = '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : 'Fork失败') + '</p></div>';
                    }
                })
                .catch(function(e){
                    btn.disabled = false;
                    btn.textContent = '📥 Fork到写作生态';
                    document.getElementById('cloud-status').innerHTML = '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                });
        });
    });

    // v10.8.0: 解锁内置母版 (= Fork可编辑副本, 母版保持锁定)
    document.querySelectorAll('.cloud-unlock-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var cat = btn.getAttribute('data-cat');
            if (!confirm('解锁内置母版 [' + cat + ']?\n\n解锁 = 创建一个可编辑的本地副本 (Fork),\n内置母版本身保持锁定不变。\n\n修改请编辑本地副本。')) return;

            var fd = new FormData();
            fd.append('action', 'linked3_cloud_fork');
            fd.append('nonce', nonce);
            fd.append('category', cat);
            fd.append('source', 'builtin');

            btn.disabled = true;
            btn.textContent = '解锁中...';

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
                .then(function(data){
                    btn.disabled = false;
                    btn.textContent = '🔓 解锁编辑';
                    var status = document.getElementById('cloud-status');
                    if (data.success) {
                        status.innerHTML = '<div class="notice notice-success inline"><p>✅ ' + escHtml(data.data.message) + '</p><p>母版已解锁, 页面刷新后可编辑本地副本。</p></div>';
                        setTimeout(function(){ location.reload(); }, 1500);
                    } else {
                        status.innerHTML = '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '解锁失败') + '</p></div>';
                    }
                })
                .catch(function(e){
                    btn.disabled = false;
                    btn.textContent = '🔓 解锁编辑';
                    document.getElementById('cloud-status').innerHTML = '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                });
        });
    });

    // 预览母版
    document.querySelectorAll('.cloud-preview-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var cat = btn.getAttribute('data-cat');
            var fd = new FormData();
            fd.append('action', 'linked3_cloud_preview');
            fd.append('nonce', nonce);
            fd.append('category', cat);

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        var tpl = data.data.template;
                        document.getElementById('cloud-preview-title').textContent = '母版预览: ' + escHtml(tpl.name || cat);
                        var html = '<table class="widefat"><tbody>';
                        Object.keys(tpl).forEach(function(k){
                            var v = tpl[k];
                            if (typeof v === 'object') v = JSON.stringify(v, null, 2);
                            html += '<tr><th style="width:100px;">' + escHtml(String(k)) + '</th><td><pre style="white-space:pre-wrap;margin:0;font-size:12px;">' + escHtml(String(v)) + '</pre></td></tr>';
                        });
                        html += '</tbody></table>';
                        document.getElementById('cloud-preview-body').innerHTML = html;
                        document.getElementById('cloud-preview-dialog').style.display = 'block';
                    }
                });
        });
    });

    // 添加母版对话框
    document.getElementById('cloud-add-master').addEventListener('click', function(){
        document.getElementById('cloud-master-title').textContent = '添加自定义母版';
        document.getElementById('cloud-master-edit-id').value = '';
        ['name','profile','role','scene','style','goals','output'].forEach(function(f){
            var el = document.getElementById('cloud-master-' + f);
            if (el) el.value = '';
        });
        document.getElementById('cloud-master-dialog').style.display = 'block';
    });

    // 编辑母版
    document.querySelectorAll('.cloud-edit-master-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var mid = btn.getAttribute('data-master-id');
            var fd = new FormData();
            fd.append('action', 'linked3_cloud_preview');
            fd.append('nonce', nonce);
            fd.append('master_id', mid);

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        var tpl = data.data.template;
                        document.getElementById('cloud-master-title').textContent = '编辑自定义母版';
                        document.getElementById('cloud-master-edit-id').value = mid;
                        document.getElementById('cloud-master-name').value = tpl.name || '';
                        document.getElementById('cloud-master-type').value = tpl.type || 'content';
                        var c = tpl.config || {};
                        document.getElementById('cloud-master-profile').value = c.profile || '';
                        document.getElementById('cloud-master-role').value = c.role || '';
                        document.getElementById('cloud-master-scene').value = c.scene || '';
                        document.getElementById('cloud-master-style').value = c.style || '';
                        document.getElementById('cloud-master-goals').value = Array.isArray(c.goals) ? c.goals.join(', ') : '';
                        document.getElementById('cloud-master-output').value = c.output || '';
                        document.getElementById('cloud-master-dialog').style.display = 'block';
                    }
                });
        });
    });

    // 保存母版
    document.getElementById('cloud-master-save').addEventListener('click', function(){
        var editId = document.getElementById('cloud-master-edit-id').value;
        var tplData = {
            name: document.getElementById('cloud-master-name').value,
            type: document.getElementById('cloud-master-type').value,
            config: {
                profile: document.getElementById('cloud-master-profile').value,
                role: document.getElementById('cloud-master-role').value,
                scene: document.getElementById('cloud-master-scene').value,
                style: document.getElementById('cloud-master-style').value,
                goals: document.getElementById('cloud-master-goals').value,
                output: document.getElementById('cloud-master-output').value,
            }
        };
        if (!tplData.name) { alert('请输入母版名称'); return; }

        var fd = new FormData();
        fd.append('action', 'linked3_cloud_master_save');
        fd.append('nonce', nonce);
        fd.append('master_id', editId);
        fd.append('template', JSON.stringify(tplData));

        var saveBtn = document.getElementById('cloud-master-save');
        saveBtn.disabled = true;
        saveBtn.textContent = '保存中...';

        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
            .then(function(data){
                saveBtn.disabled = false;
                saveBtn.textContent = '保存母版';
                if (data.success) {
                    document.getElementById('cloud-master-dialog').style.display = 'none';
                    document.getElementById('cloud-status').innerHTML = '<div class="notice notice-success inline"><p>✅ ' + escHtml(data.data.message) + '</p></div>';
                    setTimeout(function(){ location.reload(); }, 1500);
                } else {
                    document.getElementById('cloud-status').innerHTML = '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '保存失败') + '</p></div>';
                }
            })
            .catch(function(e){
                saveBtn.disabled = false;
                saveBtn.textContent = '保存母版';
                document.getElementById('cloud-status').innerHTML = '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
            });
    });

    // 删除母版
    document.querySelectorAll('.cloud-del-master-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var mid = btn.getAttribute('data-master-id');
            if (!confirm('确认删除此自定义母版? (内置母版不可删除, 已Fork的本地副本不受影响)')) return;
            var fd = new FormData();
            fd.append('action', 'linked3_cloud_master_delete');
            fd.append('nonce', nonce);
            fd.append('master_id', mid);

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        document.getElementById('cloud-status').innerHTML = '<div class="notice notice-success inline"><p>✅ ' + escHtml(data.data.message) + '</p></div>';
                        setTimeout(function(){ location.reload(); }, 1500);
                    }
                });
        });
    });

    // 删除本地Fork
    document.querySelectorAll('.cloud-del-fork-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var fid = btn.getAttribute('data-fork-id');
            if (!confirm('确认删除此本地实例? (不影响母版)')) return;
            var fd = new FormData();
            fd.append('action', 'linked3_cloud_fork_delete');
            fd.append('nonce', nonce);
            fd.append('fork_id', fid);

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        document.getElementById('cloud-status').innerHTML = '<div class="notice notice-success inline"><p>✅ ' + escHtml(data.data.message) + '</p></div>';
                        setTimeout(function(){ location.reload(); }, 1500);
                    }
                });
        });
    });

    // v10.8.1: 同步 (生产→本地: 拉取母版最新内容覆盖本地Fork)
    document.querySelectorAll('.cloud-sync-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var fid = btn.getAttribute('data-fork-id');
            if (!confirm('确认从源母版同步最新内容?\n\n注意: 本地修改将被覆盖!')) return;
            var fd = new FormData();
            fd.append('action', 'linked3_cloud_sync_to_local');
            fd.append('nonce', nonce);
            fd.append('fork_id', fid);

            btn.disabled = true; btn.textContent = '同步中...';
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    btn.disabled = false; btn.textContent = '🔄 同步';
                    var msg = data.success ? '✅ ' + escHtml(data.data.message) : escHtml(data.data && data.data.message ? data.data.message : '同步失败');
                    document.getElementById('cloud-status').innerHTML = '<div class="notice ' + (data.success ? 'notice-success' : 'notice-error') + ' inline"><p>' + msg + '</p></div>';
                    if (data.success) setTimeout(function(){ location.reload(); }, 1500);
                })
                .catch(function(e){
                    btn.disabled = false; btn.textContent = '🔄 同步';
                    document.getElementById('cloud-status').innerHTML = '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                });
        });
    });

    // v10.8.1: 收录 (本地→生产: 将本地Fork提升为自定义母版)
    document.querySelectorAll('.cloud-promote-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var fid = btn.getAttribute('data-fork-id');
            if (!confirm('确认将此本地副本收录为自定义母版?\n\n收录后, 该模版将出现在母版库, 可被所有生态Fork使用。')) return;
            var fd = new FormData();
            fd.append('action', 'linked3_cloud_promote');
            fd.append('nonce', nonce);
            fd.append('fork_id', fid);

            btn.disabled = true; btn.textContent = '收录中...';
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    btn.disabled = false; btn.textContent = '⬆ 收录';
                    var msg = data.success ? '✅ ' + escHtml(data.data.message) : escHtml(data.data && data.data.message ? data.data.message : '收录失败');
                    document.getElementById('cloud-status').innerHTML = '<div class="notice ' + (data.success ? 'notice-success' : 'notice-error') + ' inline"><p>' + msg + '</p></div>';
                    if (data.success) setTimeout(function(){ location.reload(); }, 1500);
                })
                .catch(function(e){
                    btn.disabled = false; btn.textContent = '⬆ 收录';
                    document.getElementById('cloud-status').innerHTML = '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                });
        });
    });
})();
</script>
