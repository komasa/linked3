<?php
if (!defined('ABSPATH')) exit;
// 统一 API 设置 — 完整版 (参照原版 v2.9.6)
// 功能: 每个 provider 的 API 地址/模型/Key + 自定义 API 站点增删 + 多 Key 轮询

$keys = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
$api_bases = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_api_bases', []);
$models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
$synced = (array) get_option(LINKED3_OPTION_PREFIX . 'synced_models', []);
$custom_apis = (array) get_option(LINKED3_OPTION_PREFIX . 'custom_apis', []);
$default_provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
$rotation = get_option(LINKED3_OPTION_PREFIX . 'key_rotation', 'disabled');
$ai_suffix_enabled = get_option(LINKED3_OPTION_PREFIX . 'ai_suffix_enabled', 0);
$ai_suffix_text = get_option(LINKED3_OPTION_PREFIX . 'ai_suffix_text', '');
$advanced = wp_parse_args((array) get_option(LINKED3_OPTION_PREFIX . 'advanced_settings', []), [
    'require_html' => false, 'require_tag' => false, 'enable_ai_summary' => false,
    'time_window_enabled' => false, 'time_window_start' => '09:00', 'time_window_end' => '18:00',
]);

// 内置 Provider 列表 (含默认 API 地址 + 模型)
$builtin = [
    'openai' => ['label' => 'OpenAI (GPT-4o)', 'default_base' => 'https://api.openai.com/v1', 'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'], 'placeholder' => 'sk-...'],
    'deepseek' => ['label' => 'DeepSeek', 'default_base' => 'https://api.deepseek.com/v1', 'models' => ['deepseek-chat', 'deepseek-reasoner'], 'placeholder' => 'sk-...'],
    'kimi' => ['label' => 'Kimi (月之暗面)', 'default_base' => 'https://api.moonshot.cn/v1', 'models' => ['moonshot-v1-8k', 'moonshot-v1-32k', 'moonshot-v1-128k'], 'placeholder' => 'sk-...'],
    'qwen' => ['label' => '通义千问 (阿里百炼)', 'default_base' => 'https://dashscope.aliyuncs.com/compatible-mode/v1', 'models' => ['qwen-plus', 'qwen-max', 'qwen-turbo', 'deepseek-r1', 'qwen-long'], 'placeholder' => 'sk-...'],
    'doubao' => ['label' => '豆包 (火山引擎)', 'default_base' => 'https://ark.cn-beijing.volces.com/api/v3', 'models' => ['doubao-pro-4k', 'doubao-pro-32k', 'doubao-lite-4k', 'deepseek-r1-250120'], 'placeholder' => '...'],
    'zhipu' => ['label' => '智谱 GLM', 'default_base' => 'https://open.bigmodel.cn/api/paas/v4', 'models' => ['glm-4', 'glm-4-plus', 'glm-4-flash', 'glm-4v', 'glm-3-turbo'], 'placeholder' => '...'],
    'zai' => ['label' => 'z.ai (GLM)', 'default_base' => 'https://api.z.ai/api/paas/v4', 'models' => ['glm-4', 'glm-4-plus', 'glm-4-flash', 'glm-4v'], 'placeholder' => '...'],
    'siliconflow' => ['label' => '硅基流动', 'default_base' => 'https://api.siliconflow.cn/v1', 'models' => ['Qwen/Qwen2.5-7B-Instruct', 'Qwen/Qwen2.5-14B-Instruct', 'Qwen/Qwen2.5-32B-Instruct', 'Qwen/Qwen2.5-72B-Instruct', 'Pro/deepseek-ai/DeepSeek-V3', 'Pro/deepseek-ai/DeepSeek-R1', 'deepseek-ai/DeepSeek-V3', 'deepseek-ai/DeepSeek-R1'], 'placeholder' => 'sk-...'],
    'hunyuan' => ['label' => '腾讯混元', 'default_base' => 'https://hunyuan.tencentcloudapi.com', 'models' => ['hunyuan-pro', 'hunyuan-standard', 'hunyuan-lite', 'hunyuan-vision'], 'placeholder' => 'SecretId/SecretKey'],
    'tencent_lke' => ['label' => '腾讯 LKE 智能体', 'default_base' => 'https://lke.cloud.tencent.com/v1', 'models' => ['lke-bot'], 'placeholder' => 'Bot-App-Key'],
];

// 默认自定义 API 示例 (只留1个)
$default_custom = [
    'custom_1' => ['name' => '自定义 API 示例', 'url' => 'https://api.example.com/v1/chat/completions', 'model' => 'your-model-name', 'key' => ''],
];
// v11.3.6: 仅在option从未设置时填充默认值, 避免每次访问重置用户删除的自定义API
if (empty($custom_apis) && get_option(LINKED3_OPTION_PREFIX . 'custom_apis') === false) {
    $custom_apis = $default_custom;
    update_option(LINKED3_OPTION_PREFIX . 'custom_apis', $custom_apis);
} elseif (empty($custom_apis)) {
    $custom_apis = $default_custom; // 仅用于显示, 不写回
}
?>
<div class="wrap">
    <h1>API 设置</h1>
    <p>配置各 AI 服务商的 API Key、API 地址、模型。所有 Key 加密存储。支持自定义 API 站点添加/删除 + 多 Key 轮询。</p>

    <!-- 默认 Provider 选择 -->
    <h2>默认 Provider</h2>
    <form method="post" action="options.php">
        <?php settings_fields('linked3_api_settings'); ?>
        <table class="form-table">
            <tr>
                <th><label for="default_provider">默认 AI 服务</label></th>
                <td>
                    <select id="default_provider" name="linked3_default_provider">
                        <?php foreach ($builtin as $slug => $info) : ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($default_provider, $slug); ?>><?php echo esc_html($info['label']); ?></option>
                        <?php endforeach; ?>
                        <?php foreach ($custom_apis as $cid => $c) : ?>
                            <option value="custom_<?php echo esc_attr($cid); ?>" <?php selected($default_provider, 'custom_' . $cid); ?>>自定义: <?php echo esc_html($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">所有 AI 调用的默认 Provider,可在任务级别覆盖。</p>
                </td>
            </tr>
            <tr>
                <th><label for="key_rotation">多 Key 轮询</th></th>
                <td>
                    <select id="key_rotation" name="linked3_key_rotation">
                        <option value="disabled" <?php selected($rotation, 'disabled'); ?>>禁用</option>
                        <option value="round_robin" <?php selected($rotation, 'round_robin'); ?>>轮询 (Round Robin)</option>
                        <option value="failover" <?php selected($rotation, 'failover'); ?>>故障转移 (Failover)</option>
                    </select>
                    <p class="description">多 Key 时启用轮询可分摊负载,故障转移自动跳过失败 Key。</p>
                </td>
            </tr>
        </table>

        <!-- 内置 Provider 配置 -->
        <h2>内置 Provider 配置</h2>
        <p>每个 Provider 可配置 API 地址(支持代理/镜像站)、模型、API Key。多 Key 用换行分隔。</p>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:120px;">Provider</th>
                    <th>API 地址</th>
                    <th style="width:180px;">默认模型</th>
                    <th>API Key (多个用换行分隔)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($builtin as $slug => $info) : 
                $base = $api_bases[$slug] ?? $info['default_base'];
                $model = $models[$slug] ?? ($info['models'][0] ?? '');
                $key = $keys[$slug] ?? '';
            ?>
                <tr>
                    <td><strong><?php echo esc_html($info['label']); ?></strong></td>
                    <td>
                        <input type="text" name="linked3_provider_api_bases[<?php echo esc_attr($slug); ?>]"
                               value="<?php echo esc_attr($base); ?>" class="large-text" placeholder="<?php echo esc_attr($info['default_base']); ?>" />
                    </td>
                    <td>
                        <select name="linked3_provider_models[<?php echo esc_attr($slug); ?>]" id="model_<?php echo esc_attr($slug); ?>">
                            <?php
                            // 合并内置模型 + 同步的模型
                            $synced_models = $synced[$slug] ?? [];
                            $all_models = array_unique(array_merge($info['models'], $synced_models));
                            sort($all_models);
                            foreach ($all_models as $m) :
                            ?>
                                <option value="<?php echo esc_attr($m); ?>" <?php selected($model, $m); ?>><?php echo esc_html($m); ?></option>
                            <?php endforeach; ?>
                            <?php if ($model && !in_array($model, $all_models)) : ?>
                                <option value="<?php echo esc_attr($model); ?>" selected><?php echo esc_html($model); ?></option>
                            <?php endif; ?>
                        </select>
                        <button type="button" class="button linked3-sync-models" data-provider="<?php echo esc_attr($slug); ?>">同步模型</button>
                        <span class="linked3-sync-status" data-provider="<?php echo esc_attr($slug); ?>"></span>
                    </td>
                    <td>
                        <textarea name="linked3_provider_keys[<?php echo esc_attr($slug); ?>]" rows="2" cols="40"
                                  placeholder="<?php echo esc_attr($info['placeholder']); ?>"><?php echo esc_textarea($key); ?></textarea>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php submit_button('保存 Provider 配置', 'primary', 'linked3-save-provider-config', false); ?>
        <span id="linked3-provider-save-status" style="margin-left:10px;"></span>
    </form>

    <!-- 自定义 API 站点 -->
    <h2>自定义 API 站点</h2>
    <p>添加任意 OpenAI 兼容的 API 站点(支持代理/镜像/第三方平台)。多 Key 用换行分隔,支持轮询。</p>
    
    <div id="linked3-custom-apis">
        <?php foreach ($custom_apis as $cid => $c) : ?>
        <div class="custom-api-row" data-id="<?php echo esc_attr($cid); ?>" style="background:#fff;border:1px solid #ddd;padding:12px;margin:8px 0;border-radius:4px;">
            <table class="form-table" style="margin:0;">
                <tr>
                    <th style="width:120px;">名称</th>
                    <td><input type="text" class="custom-api-name regular-text" value="<?php echo esc_attr($c['name']); ?>" /></td>
                    <td style="width:80px;"><button type="button" class="button button-link-delete custom-api-delete">删除</button></td>
                </tr>
                <tr>
                    <th>API 地址</th>
                    <td colspan="2"><input type="text" class="custom-api-url large-text" value="<?php echo esc_attr($c['url']); ?>" placeholder="https://api.example.com/v1/chat/completions" /></td>
                </tr>
                <tr>
                    <th>模型</th>
                    <td colspan="2"><input type="text" class="custom-api-model regular-text" value="<?php echo esc_attr($c['model']); ?>" placeholder="deepseek-r1" /></td>
                </tr>
                <tr>
                    <th>API Key</th>
                    <td colspan="2"><textarea class="custom-api-key" rows="2" cols="60" placeholder="多个 Key 用换行分隔"><?php echo esc_textarea($c['key']); ?></textarea></td>
                </tr>
            </table>
        </div>
        <?php endforeach; ?>
    </div>
    <p>
        <button type="button" class="button button-primary" id="linked3-add-custom-api">+ 添加自定义 API 站点</button>
        <button type="button" class="button" id="linked3-save-custom-apis">保存自定义 API</button>
        <span id="linked3-custom-save-status"></span>
    </p>

    <script>
    // 全局 nonce/ajaxUrl — 供本页所有按钮复用 (v2.6.0 修复作用域问题)
    window.linked3Nonce = <?php echo wp_json_encode(wp_create_nonce('linked3_settings')); ?>;
    window.linked3AjaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

    // v3.1.0: Provider 配置 AJAX 保存 (不刷新页面)
    (function(){
        var form = document.querySelector('form[action="options.php"]');
        if (!form) return;
        var saveBtn = document.getElementById('linked3-save-provider-config');
        if (!saveBtn) return;

        form.addEventListener('submit', function(e){
            e.preventDefault();
            saveBtn.disabled = true;
            saveBtn.value = '保存中...';
            var status = document.getElementById('linked3-provider-save-status');
            status.textContent = '保存中...';
            status.style.color = '#666';

            var fd = new FormData();
            fd.append('action', 'linked3_save_provider_config');
            fd.append('nonce', window.linked3Nonce);
            fd.append('default_provider', document.getElementById('default_provider').value);
            fd.append('key_rotation', document.getElementById('key_rotation').value);

            // 收集所有 linked3_provider_api_bases[slug] / linked3_provider_models[slug] / linked3_provider_keys[slug]
            var inputs = form.querySelectorAll('input[name], select[name], textarea[name]');
            inputs.forEach(function(inp){
                var name = inp.name;
                if (!name) return;
                // v3.1.1 修复: 匹配带 linked3_ 前缀的字段名
                if (name.indexOf('linked3_provider_api_bases[') === 0 ||
                    name.indexOf('linked3_provider_models[') === 0 ||
                    name.indexOf('linked3_provider_keys[') === 0) {
                    // 去掉 linked3_ 前缀,后端读 provider_keys/provider_models/provider_api_bases
                    var fieldName = name.replace('linked3_', '');
                    fd.append(fieldName, inp.value);
                }
            });

            fetch(window.linked3AjaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){return r.json();})
                .then(function(res){
                    saveBtn.disabled = false;
                    saveBtn.value = '保存 Provider 配置';
                    if (res.success) {
                        status.textContent = '✓ ' + (res.data.message || '已保存');
                        status.style.color = '#080';
                        // 3 秒后清空提示
                        setTimeout(function(){ status.textContent = ''; }, 3000);
                    } else {
                        status.textContent = '✗ ' + (res.data && res.data.message ? res.data.message : '保存失败');
                        status.style.color = '#800';
                    }
                })
                .catch(function(e){
                    saveBtn.disabled = false;
                    saveBtn.value = '保存 Provider 配置';
                    status.textContent = '✗ 网络错误: ' + e.message;
                    status.style.color = '#800';
                });
        });
    })();

    (function(){
        var nonce = window.linked3Nonce;
        var ajaxUrl = window.linked3AjaxUrl;

        // 添加自定义 API
        document.getElementById('linked3-add-custom-api').addEventListener('click', function(){
            var id = 'custom_' + Date.now();
            var html = '<div class="custom-api-row" data-id="' + id + '" style="background:#fff;border:1px solid #ddd;padding:12px;margin:8px 0;border-radius:4px;">' +
                '<table class="form-table" style="margin:0;">' +
                '<tr><th style="width:120px;">名称</th><td><input type="text" class="custom-api-name regular-text" value="" placeholder="自定义 API 名称" /></td><td style="width:80px;"><button type="button" class="button button-link-delete custom-api-delete">删除</button></td></tr>' +
                '<tr><th>API 地址</th><td colspan="2"><input type="text" class="custom-api-url large-text" value="" placeholder="https://api.example.com/v1/chat/completions" /></td></tr>' +
                '<tr><th>模型</th><td colspan="2"><input type="text" class="custom-api-model regular-text" value="" placeholder="deepseek-r1" /></td></tr>' +
                '<tr><th>API Key</th><td colspan="2"><textarea class="custom-api-key" rows="2" cols="60" placeholder="多个 Key 用换行分隔"></textarea></td></tr>' +
                '</table></div>';
            document.getElementById('linked3-custom-apis').insertAdjacentHTML('beforeend', html);
        });

        // 删除自定义 API (事件委托)
        document.getElementById('linked3-custom-apis').addEventListener('click', function(e){
            if (e.target.classList.contains('custom-api-delete')) {
                if (confirm('确认删除此自定义 API?')) {
                    e.target.closest('.custom-api-row').remove();
                }
            }
        });

        // 保存自定义 API
        document.getElementById('linked3-save-custom-apis').addEventListener('click', function(){
            var apis = {};
            document.querySelectorAll('.custom-api-row').forEach(function(row){
                var id = row.dataset.id;
                apis[id] = {
                    name: row.querySelector('.custom-api-name').value,
                    url: row.querySelector('.custom-api-url').value,
                    model: row.querySelector('.custom-api-model').value,
                    key: row.querySelector('.custom-api-key').value
                };
            });
            var fd = new FormData();
            fd.append('action', 'linked3_save_custom_apis');
            fd.append('nonce', nonce);
            fd.append('apis', JSON.stringify(apis));
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){return r.json();})
                .then(function(res){
                    var s = document.getElementById('linked3-custom-save-status');
                    s.textContent = res.success ? '已保存' : '保存失败';
                    s.style.color = res.success ? '#080' : '#800';
                    setTimeout(function(){ s.textContent = ''; }, 2000);
                });
        });

        // 同步模型
        document.querySelectorAll('.linked3-sync-models').forEach(function(btn){
            btn.addEventListener('click', function(){
                var provider = btn.dataset.provider;
                var status = document.querySelector('.linked3-sync-status[data-provider="' + provider + '"]');
                status.textContent = '同步中...';
                status.style.color = '#666';
                var fd = new FormData();
                fd.append('action', 'linked3_sync_models');
                fd.append('nonce', nonce);
                fd.append('provider', provider);
                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){return r.json();})
                    .then(function(res){
                        if (res.success) {
                            var models = res.data.models;
                            var select = document.getElementById('model_' + provider);
                            var current = select.value;
                            select.innerHTML = '';
                            models.forEach(function(m){
                                var opt = document.createElement('option');
                                opt.value = m; opt.textContent = m;
                                if (m === current) opt.selected = true;
                                select.appendChild(opt);
                            });
                            status.textContent = '已同步 ' + res.data.count + ' 个模型';
                            status.style.color = '#080';
                        } else {
                            status.textContent = res.data.message || '失败';
                            status.style.color = '#800';
                        }
                        setTimeout(function(){ status.textContent = ''; }, 3000);
                    });
            });
        });
    })();
    </script>

    <!-- AI 标识符后缀 -->
    <h2>AI 标识符后缀</h2>
    <p>AI 生成的内容自动追加免责声明后缀,用于合规标注(防 AI 内容检测)。</p>
    <table class="form-table">
        <tr>
            <th>启用</th>
            <td>
                <label><input type="checkbox" id="ai_suffix_enabled" <?php checked($ai_suffix_enabled); ?> /> 追加 AI 标识符后缀到生成内容</label>
            </td>
        </tr>
        <tr>
            <th>后缀内容</th>
            <td>
                <textarea id="ai_suffix_text" rows="3" cols="60" class="large-text" placeholder="例如:本文基于公开技术资料和厂商官方信息整合撰写,以确保信息的时效性与客观性。"><?php echo esc_textarea($ai_suffix_text); ?></textarea>
                <p class="description">留空则使用默认后缀。该后缀会追加到所有 AI 生成文章末尾。</p>
            </td>
        </tr>
    </table>
    <p>
        <button type="button" class="button button-primary" id="linked3-save-suffix">保存后缀</button>
        <span id="linked3-suffix-status"></span>
    </p>
    <script>
    document.getElementById('linked3-save-suffix').addEventListener('click', function(){
        var btn = this;
        var s = document.getElementById('linked3-suffix-status');
        btn.disabled = true;
        s.textContent = '保存中...';
        s.style.color = '#666';
        var fd = new FormData();
        fd.append('action', 'linked3_save_ai_suffix');
        fd.append('nonce', window.linked3Nonce);
        fd.append('enabled', document.getElementById('ai_suffix_enabled').checked ? 1 : 0);
        fd.append('suffix', document.getElementById('ai_suffix_text').value);
        fetch(window.linked3AjaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(res){
                btn.disabled = false;
                s.textContent = res.success ? '已保存' : ('保存失败: ' + (res.data && res.data.message ? res.data.message : '未知'));
                s.style.color = res.success ? '#080' : '#800';
                setTimeout(function(){ s.textContent = ''; }, 3000);
            })
            .catch(function(e){
                btn.disabled = false;
                s.textContent = '网络错误: ' + e.message;
                s.style.color = '#800';
            });
    });
    </script>

    <!-- 高级设置 (原版隐藏功能) -->
    <h2>高级设置</h2>
    <p>AI 生成内容的格式要求与运行限制(原版隐藏功能)。</p>
    <table class="form-table">
        <tr>
            <th>HTML 格式</th>
            <td>
                <label><input type="checkbox" id="adv_require_html" <?php checked($advanced['require_html']); ?> /> AI 返回 HTML 标签格式(而非 Markdown)</label>
                <p class="description">勾选后 AI 返回 H1/H2/p 等标签,不含 CSS/DOCTYPE。</p>
            </td>
        </tr>
        <tr>
            <th>AI 摘要</th>
            <td>
                <label><input type="checkbox" id="adv_enable_summary" <?php checked($advanced['enable_ai_summary']); ?> /> 文章尾部自动生成搜索引擎精选摘要</label>
                <p class="description">格式:摘要:xxx(适配 Google 精选摘要)。</p>
            </td>
        </tr>
        <tr>
            <th>文章标签</th>
            <td>
                <label><input type="checkbox" id="adv_require_tag" <?php checked($advanced['require_tag']); ?> /> AI 自动生成文章 Tag 标签</label>
                <p class="description">格式:{1、标签1}{2、标签2}。</p>
            </td>
        </tr>
        <tr>
            <th>时间段限制</th>
            <td>
                <label><input type="checkbox" id="adv_time_window" <?php checked($advanced['time_window_enabled']); ?> /> 只在指定时间段运行自动任务</label>
                <br />
                <label>开始 <input type="time" id="adv_time_start" value="<?php echo esc_attr($advanced['time_window_start']); ?>" style="width:100px;" /></label>
                <label style="margin-left:10px;">结束 <input type="time" id="adv_time_end" value="<?php echo esc_attr($advanced['time_window_end']); ?>" style="width:100px;" /></label>
                <p class="description">限制 AutoGPT 只在此时段运行(如 9:00-18:00),避免深夜发布。</p>
            </td>
        </tr>
    </table>
    <p>
        <button type="button" class="button button-primary" id="linked3-save-advanced">保存高级设置</button>
        <span id="linked3-advanced-status"></span>
    </p>
    <script>
    document.getElementById('linked3-save-advanced').addEventListener('click', function(){
        var btn = this;
        var s = document.getElementById('linked3-advanced-status');
        btn.disabled = true;
        s.textContent = '保存中...';
        s.style.color = '#666';
        var fd = new FormData();
        fd.append('action', 'linked3_save_advanced');
        fd.append('nonce', window.linked3Nonce);
        fd.append('require_html', document.getElementById('adv_require_html').checked ? 1 : 0);
        fd.append('enable_ai_summary', document.getElementById('adv_enable_summary').checked ? 1 : 0);
        fd.append('require_tag', document.getElementById('adv_require_tag').checked ? 1 : 0);
        fd.append('time_window_enabled', document.getElementById('adv_time_window').checked ? 1 : 0);
        fd.append('time_window_start', document.getElementById('adv_time_start').value);
        fd.append('time_window_end', document.getElementById('adv_time_end').value);
        fetch(window.linked3AjaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(res){
                btn.disabled = false;
                s.textContent = res.success ? '已保存' : ('保存失败: ' + (res.data && res.data.message ? res.data.message : '未知'));
                s.style.color = res.success ? '#080' : '#800';
                setTimeout(function(){ s.textContent = ''; }, 3000);
            })
            .catch(function(e){
                btn.disabled = false;
                s.textContent = '网络错误: ' + e.message;
                s.style.color = '#800';
            });
    });
    </script>

    <!-- v10.7.4: 图片生成 API 设置 -->
    <h2>🎨 图片生成 API</h2>
    <p>配置图片生成服务。默认使用硅基流动(与文本API共用Key),也支持OpenAI DALL-E、阿里通义万相等。</p>
    <?php
    $img_provider = get_option(LINKED3_OPTION_PREFIX . 'image_provider', 'siliconflow');
    $img_model = get_option(LINKED3_OPTION_PREFIX . 'image_model', 'Kwai-Kolors/Kolors');
    $img_api_base = get_option(LINKED3_OPTION_PREFIX . 'image_api_base', '');
    $img_api_key = get_option(LINKED3_OPTION_PREFIX . 'image_api_key', '');
    $img_width = get_option(LINKED3_OPTION_PREFIX . 'image_width', 1024);
    $img_height = get_option(LINKED3_OPTION_PREFIX . 'image_height', 1024);
    $img_providers = [
        'siliconflow' => ['label' => '硅基流动 (默认, 共用文本API Key)', 'models' => ['Kwai-Kolors/Kolors', 'stabilityai/stable-diffusion-3-5-large', 'black-forest-labs/FLUX.1-schnell'], 'default_base' => 'https://api.siliconflow.cn/v1'],
        'openai' => ['label' => 'OpenAI DALL-E 3', 'models' => ['dall-e-3', 'dall-e-2'], 'default_base' => 'https://api.openai.com/v1'],
        'tongyi' => ['label' => '阿里通义万相', 'models' => ['wanx-v1', 'wanx2.0-t2i-turbo'], 'default_base' => 'https://dashscope.aliyuncs.com/api/v1'],
        'custom' => ['label' => '自定义 API', 'models' => [], 'default_base' => ''],
    ];
    ?>
    <table class="form-table">
        <tr><th><label for="img_provider">图片供应商</label></th><td>
            <select id="img_provider"><?php foreach ($img_providers as $slug => $info): ?><option value="<?php echo esc_attr($slug); ?>" <?php selected($img_provider, $slug); ?>><?php echo esc_html($info['label']); ?></option><?php endforeach; ?></select>
            <p class="description">硅基流动默认优先, 共用上方配置的硅基流动API Key。</p>
        </td></tr>
        <tr><th><label for="img_model">图片模型</label></th><td>
            <select id="img_model"><?php $cm = $img_providers[$img_provider]['models'] ?? []; foreach ($cm as $m): ?><option value="<?php echo esc_attr($m); ?>" <?php selected($img_model, $m); ?>><?php echo esc_html($m); ?></option><?php endforeach; ?><?php if (!in_array($img_model, $cm) && $img_model): ?><option value="<?php echo esc_attr($img_model); ?>" selected><?php echo esc_html($img_model); ?></option><?php endif; ?></select>
            <input type="text" id="img_model_custom" placeholder="或输入自定义模型名" style="width:200px;margin-left:8px;" value="<?php echo esc_attr($img_model); ?>" />
        </td></tr>
        <tr><th><label for="img_api_base">API 地址 (可选)</label></th><td>
            <input type="url" id="img_api_base" class="regular-text" value="<?php echo esc_attr($img_api_base); ?>" placeholder="留空则使用供应商默认地址" />
            <p class="description">硅基流动: <code>https://api.siliconflow.cn/v1</code></p>
        </td></tr>
        <tr><th><label for="img_api_key">API Key (可选)</label></th><td>
            <input type="password" id="img_api_key" class="regular-text" value="<?php echo esc_attr($img_api_key); ?>" placeholder="留空则共用硅基流动文本API Key" />
        </td></tr>
        <tr><th><label>图片尺寸</label></th><td>
            <input type="number" id="img_width" value="<?php echo esc_attr($img_width); ?>" style="width:80px;" /> × <input type="number" id="img_height" value="<?php echo esc_attr($img_height); ?>" style="width:80px;" /> px
        </td></tr>
    </table>
    <p><button type="button" class="button button-primary" id="linked3-save-image-api">保存图片API设置</button> <span id="linked3-image-api-status"></span></p>
    <script>
    document.getElementById('linked3-save-image-api').addEventListener('click', function(){
        var b=this,s=document.getElementById('linked3-image-api-status');b.disabled=true;s.textContent='保存中...';s.style.color='#666';
        var fd=new FormData();fd.append('action','linked3_save_image_api');fd.append('nonce',window.linked3Nonce);
        fd.append('provider',document.getElementById('img_provider').value);
        var cm=document.getElementById('img_model_custom').value.trim();fd.append('model',cm||document.getElementById('img_model').value);
        fd.append('api_base',document.getElementById('img_api_base').value);fd.append('api_key',document.getElementById('img_api_key').value);
        fd.append('width',document.getElementById('img_width').value);fd.append('height',document.getElementById('img_height').value);
        fetch(window.linked3AjaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(res){b.disabled=false;s.textContent=res.success?'✅ 已保存':('❌ '+(res.data&&res.data.message?res.data.message:'未知'));s.style.color=res.success?'#080':'#800';setTimeout(function(){s.textContent='';},3000);}).catch(function(e){b.disabled=false;s.textContent='❌ '+e.message;s.style.color='#800';});
    });
    // v16.1.0: 图片供应商切换 — 展开压缩代码, 提升可读性; 重建模型列表时保留当前选择
    document.getElementById('img_provider').addEventListener('change', function() {
        var providers = <?php echo json_encode($img_providers); ?>;
        var sl = this.value;
        var providerCfg = providers[sl] || {};
        var models = providerCfg.models || [];
        var sel = document.getElementById('img_model');
        var prevModel = sel.value; // v16.1.0: 记住当前模型, 重建后尝试恢复
        sel.innerHTML = '';
        models.forEach(function(m) {
            var o = document.createElement('option');
            o.value = m;
            o.textContent = m;
            if (m === prevModel) o.selected = true; // v16.1.0: 恢复之前选择
            sel.appendChild(o);
        });
        if (providerCfg.default_base) {
            document.getElementById('img_api_base').placeholder = providerCfg.default_base;
        }
    });
    </script>

</div>
