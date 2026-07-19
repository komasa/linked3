<?php
if (!defined('ABSPATH')) exit;

// 获取图示结构列表 (8种结构替代旧4Band一刀切)
$structures = [];
if (class_exists('\\Linked3\\Classes\\Diagram\\Linked3_Diagram_Structure_Registry')) {
    $structures = \Linked3\Classes\Diagram\Linked3_Diagram_Structure_Registry::all();
}
$structure_count = count($structures);
if ($structure_count === 0) {
    $structure_count = 8; // fallback
}
?>
<div class="wrap linked3-create-center">
    <h2>🚀 创作中心</h2>
    <p class="description">统一创作入口 · 选择类型 → 输入素材 → 生成</p>

    <!-- Step 1: Content Type -->
    <h3>① 选择内容类型</h3>
    <div class="l3-type-selector" style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
        <label class="l3-type-card" style="flex:1;min-width:140px;border:2px solid #ddd;border-radius:8px;padding:16px;cursor:pointer;text-align:center;">
            <input type="radio" name="l3_content_type" value="article" checked onchange="l3_switch()">
            <div style="font-size:32px;">📝</div>
            <div style="font-weight:600;margin-top:8px;">文章写作</div>
            <div style="font-size:12px;color:#666;">SEO文章 · 博客 · 长文</div>
        </label>
        <label class="l3-type-card" style="flex:1;min-width:140px;border:2px solid #ddd;border-radius:8px;padding:16px;cursor:pointer;text-align:center;">
            <input type="radio" name="l3_content_type" value="comic" onchange="l3_switch()">
            <div style="font-size:32px;">🎨</div>
            <div style="font-weight:600;margin-top:8px;">漫画脚本</div>
            <div style="font-size:12px;color:#666;">分镜 · 画面描述 · 角色设计</div>
        </label>
        <label class="l3-type-card" style="flex:1;min-width:140px;border:2px solid #ddd;border-radius:8px;padding:16px;cursor:pointer;text-align:center;">
            <input type="radio" name="l3_content_type" value="diagram" onchange="l3_switch()">
            <div style="font-size:32px;">📊</div>
            <div style="font-weight:600;margin-top:8px;">知识图谱</div>
            <div style="font-size:12px;color:#666;">信息图 · <?php echo $structure_count; ?>种结构 · 单图</div>
        </label>
        <label class="l3-type-card" style="flex:1;min-width:140px;border:2px solid #ddd;border-radius:8px;padding:16px;cursor:pointer;text-align:center;">
            <input type="radio" name="l3_content_type" value="video" onchange="l3_switch()">
            <div style="font-size:32px;">🎬</div>
            <div style="font-weight:600;margin-top:8px;">视频脚本</div>
            <div style="font-size:12px;color:#666;">分镜 · 运镜 · Motion</div>
        </label>
        <label class="l3-type-card" style="flex:1;min-width:140px;border:2px solid #ddd;border-radius:8px;padding:16px;cursor:pointer;text-align:center;">
            <input type="radio" name="l3_content_type" value="xhs" onchange="l3_switch()">
            <div style="font-size:32px;">📕</div>
            <div style="font-weight:600;margin-top:8px;">小红书</div>
            <div style="font-size:12px;color:#666;">爆款图文 · 多页笔记</div>
        </label>
        <label class="l3-type-card" style="flex:1;min-width:140px;border:2px solid #ddd;border-radius:8px;padding:16px;cursor:pointer;text-align:center;">
            <input type="radio" name="l3_content_type" value="book" onchange="l3_switch()">
            <div style="font-size:32px;">📚</div>
            <div style="font-weight:600;margin-top:8px;">书籍生成</div>
            <div style="font-size:12px;color:#666;">BookFactory · 整书输出</div>
        </label>
    </div>

    <!-- Step 2: Input -->
    <h3>② 输入素材</h3>
    <table class="form-table">
        <tr id="l3-topic-row">
            <th><label>主题 / 关键词</label></th>
            <td><input type="text" id="l3_topic" class="large-text" placeholder="输入主题或关键词" /></td>
        </tr>
        <tr id="l3-script-row" style="display:none;">
            <th><label>剧本 / 文章内容</label></th>
            <td><textarea id="l3_script" rows="6" class="large-text" placeholder="粘贴剧本、故事或文章内容"></textarea></td>
        </tr>
    </table>

    <!-- Step 2b: Diagram Structure Selector (when diagram is selected) -->
    <table class="form-table" id="l3-structure-row" style="display:none;">
        <tr>
            <th><label>📊 图示结构</label></th>
            <td>
                <select id="l3_structure" class="regular-text">
                    <option value="auto">🤖 自动适配 (推荐)</option>
                    <?php if (!empty($structures)): foreach ($structures as $sid => $s): ?>
                        <option value="<?php echo esc_attr($sid); ?>">
                            <?php echo esc_html($s['icon'] ?? '📌'); ?> <?php echo esc_html($s['label'] ?? $sid); ?>
                            — <?php echo esc_html(mb_substr($s['description'] ?? '', 0, 40)); ?>
                        </option>
                    <?php endforeach; else: ?>
                        <option value="4band">4Band · 经典四段式</option>
                        <option value="timeline">⏳ Timeline · 时间线</option>
                        <option value="flowchart">🔄 Flowchart · 流程图</option>
                        <option value="comparison">⚖️ Comparison · 对比图</option>
                        <option value="data_chart">📈 DataChart · 数据图</option>
                        <option value="checklist">✅ Checklist · 清单</option>
                        <option value="mindmap">🧠 MindMap · 思维导图</option>
                        <option value="quote_card">💬 QuoteCard · 金句卡</option>
                    <?php endif; ?>
                </select>
                <p class="description">选择信息图结构 (v19.52+ 已替代旧4Band一刀切，支持<?php echo $structure_count; ?>种结构)</p>
            </td>
        </tr>
    </table>

    <!-- Step 2c: Generation Config (复合杠杆配置) -->
    <table class="form-table" id="l3-config-row" style="display:none;">
        <tr>
            <th><label>⚙️ 生成配置</label></th>
            <td>
                <div style="display:flex;gap:16px;flex-wrap:wrap;">
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" id="l3_cfg_composite" value="1" checked>
                        <span>🧠 复合杠杆增强</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" id="l3_cfg_cos" value="1">
                        <span>🔄 COS三代演化</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" id="l3_cfg_seo" value="1" checked>
                        <span>📈 SEO优化</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" id="l3_cfg_risk" value="1">
                        <span>🛡️ 风险审查</span>
                    </label>
                </div>
                <p class="description">复合杠杆: 17种高级认知能力可选, COS: 三代演化生成最优方案</p>
            </td>
        </tr>
    </table>

    <!-- Step 3: Style (unified) -->
    <?php
    // v27.17.9-fix1: 动态获取风格数量
    $style_count = 71;
    $style_dir = dirname(__DIR__, 4) . '/src/Classes/Genesis/styles';
    if (is_dir($style_dir)) {
        $style_files = glob($style_dir . '/*.json');
        if ($style_files) $style_count = count($style_files);
    }
    ?>
    <h3>③ 选择画风 <span style="font-weight:normal;font-size:12px;color:#666;">(共用统一风格库 · <?php echo $style_count; ?>种画风 × 9推荐策略)</span></h3>
    <table class="form-table">
        <tr>
            <th><label>画风</label></th>
            <td>
                <select id="l3_style" class="regular-text">
                    <option value="auto">🤖 自动适配 (推荐)</option>
                    <optgroup label="📐 信息图示 (F01-F57)">
                        <option value="flat_infographic">扁平信息图 · 蓝橙紫三色</option>
                        <option value="isometric">等轴测图</option>
                        <option value="minimal_chart">极简图表</option>
                    </optgroup>
                    <optgroup label="🎨 艺术插画 (Y01-Y05)">
                        <option value="watercolor">水彩</option>
                        <option value="oil_painting">油画</option>
                        <option value="ink_wash">水墨</option>
                    </optgroup>
                    <optgroup label="📷 商业摄影 (S01-S06)">
                        <option value="documentary_photo">纪实摄影</option>
                        <option value="studio_product">产品摄影</option>
                    </optgroup>
                    <optgroup label="🔬 概念实验 (G01-G03)">
                        <option value="cyberpunk">赛博朋克</option>
                        <option value="surreal">超现实</option>
                    </optgroup>
                </select>
                <p class="description">自动适配 = AI根据内容类型和主题智能选择最佳画风</p>
            </td>
        </tr>
        <tr id="l3-platform-row" style="display:none;">
            <th><label>目标平台</label></th>
            <td>
                <select id="l3_platform">
                    <option value="midjourney">Midjourney</option>
                    <option value="dall-e">DALL-E 3</option>
                    <option value="stable-diffusion">Stable Diffusion</option>
                    <option value="flux">Flux</option>
                </select>
            </td>
        </tr>
        <tr id="l3-count-row">
            <th><label id="l3-count-label">字数</label></th>
            <td>
                <input type="number" id="l3_count" value="1200" min="100" max="10000" step="100" />
                <span id="l3-count-hint" class="description"></span>
            </td>
        </tr>
    </table>

    <!-- Step 4: Generate -->
    <p style="margin-top:20px;">
        <button type="button" id="l3_generate_btn" class="button button-primary button-large" onclick="l3_generate()">
            ⚡ 生成
        </button>
        <span id="l3_progress" style="margin-left:12px;color:#666;"></span>
    </p>

    <div id="l3_result" style="margin-top:20px;"></div>
</div>

<script>
function l3_switch() {
    var t=document.querySelector('input[name="l3_content_type"]:checked').value;
    var showScript=(t==='comic'||t==='diagram'||t==='video'||t==='xhs');
    var showPlatform=(t==='comic'||t==='video');
    var showCount=(t==='article'||t==='comic'||t==='diagram'||t==='video'||t==='xhs');
    var showStructure=(t==='diagram');
    var showConfig=(t==='article'||t==='diagram'||t==='xhs'||t==='book');
    var showTopic=(t!=='book'); // book uses script input
    document.getElementById('l3-script-row').style.display=showScript?'':'none';
    document.getElementById('l3-platform-row').style.display=showPlatform?'':'none';
    document.getElementById('l3-topic-row').style.display=showTopic?'':'none';
    document.getElementById('l3-count-row').style.display=showCount?'':'none';
    document.getElementById('l3-structure-row').style.display=showStructure?'':'none';
    document.getElementById('l3-config-row').style.display=showConfig?'':'none';
    
    // Update count label and hints
    var labels={article:['字数','建议 800-3000 字'],comic:['分镜数量','5-15 镜'],diagram:['镜数量','1镜=1张完整信息图'],video:['视频组数','每组5-10秒'],xhs:['页数','3-8页小红书笔记'],book:['章节数','5-20章整书输出']};
    var hints={article:'建议 800-3000 字',comic:'5-15 镜',diagram:'1镜=1张完整信息图(8种结构可选)',video:'每组5-10秒',xhs:'3-8页小红书笔记',book:'5-20章整书输出'};
    document.getElementById('l3-count-label').textContent=labels[t][0];
    document.getElementById('l3-count-hint').textContent=hints[t]||'';
    
    // Update count defaults
    var defaults={article:1200,comic:8,diagram:1,video:5,xhs:5,book:10};
    document.getElementById('l3_count').value=defaults[t]||8;
    document.getElementById('l3_count').min=t==='diagram'?1:3;
    document.getElementById('l3_count').max=t==='article'?10000:(t==='book'?50:15);
}

function l3_generate(){
    var btn=document.getElementById('l3_generate_btn');
    btn.disabled=true;btn.textContent='⏳ 生成中...';
    document.getElementById('l3_progress').textContent='正在处理...';
    
    var fd=new FormData();
    fd.append('action','linked3_content_generate');
    fd.append('nonce','<?php echo wp_create_nonce("linked3_content_writer"); ?>');
    fd.append('content_type',document.querySelector('input[name="l3_content_type"]:checked').value);
    fd.append('topic',document.getElementById('l3_topic').value);
    fd.append('script',document.getElementById('l3_script').value);
    fd.append('style',document.getElementById('l3_style').value);
    fd.append('platform',document.getElementById('l3_platform').value);
    // v27.17.9-fix1: 发送结构选择和生成配置到后端
    var structSel=document.getElementById('l3_structure');
    if(structSel) fd.append('structure',structSel.value);
    fd.append('options',JSON.stringify({
        word_count:document.getElementById('l3_count').value,
        panel_count:document.getElementById('l3_count').value,
        video_groups:document.getElementById('l3_count').value,
        page_count:document.getElementById('l3_count').value,
        chapter_count:document.getElementById('l3_count').value,
        cfg_composite:document.getElementById('l3_cfg_composite')?document.getElementById('l3_cfg_composite').checked:false,
        cfg_cos:document.getElementById('l3_cfg_cos')?document.getElementById('l3_cfg_cos').checked:false,
        cfg_seo:document.getElementById('l3_cfg_seo')?document.getElementById('l3_cfg_seo').checked:false,
        cfg_risk:document.getElementById('l3_cfg_risk')?document.getElementById('l3_cfg_risk').checked:false
    }));
    
    fetch(ajaxurl,{method:'POST',body:fd})
    .then(function(r){return r.json()})
    .then(function(r){
        btn.disabled=false;btn.textContent='⚡ 生成';
        document.getElementById('l3_progress').textContent='';
        var d=document.getElementById('l3_result');
        if(r.success){
            var h='<div class="notice notice-success inline"><p>✅ 生成成功</p></div>';
            var data=r.data;
            if(data.edit_url)h+='<p><a href="'+data.edit_url+'" class="button button-primary">编辑文章 →</a></p>';
            if(data.panels)h+='<p>共生成 '+data.total_panels+' 个分镜</p>';
            if(data.diagram)h+='<p>知识图谱已生成</p>';
            d.innerHTML=h;
        }else{
            d.innerHTML='<div class="notice notice-error inline"><p>'+(r.data.message||'生成失败')+'</p></div>';
        }
    })
    .catch(function(e){
        btn.disabled=false;btn.textContent='⚡ 生成';
        document.getElementById('l3_progress').textContent='';
        document.getElementById('l3_result').innerHTML='<div class="notice notice-error inline"><p>网络错误: '+e+'</p></div>';
    });
}
l3_switch();
</script>
