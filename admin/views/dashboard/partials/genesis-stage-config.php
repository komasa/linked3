<?php
/**
 * Partial: genesis-stage-config
 * Extracted from: tab-genesis.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
<!-- ===== Stage 2: 生成配置 ===== -->
<div class="lk3-stage" id="lk3-stage-2">
    <div class="lk3-stage-header">
        <h3 class="lk3-stage-title"><span class="lk3-stage-icon">⚙️</span><?php echo esc_html__('Stage 2 · 生成配置', 'linked3'); ?></h3>
    </div>
    <p class="lk3-stage-desc"><?php echo esc_html__('配置画风、平台、分镜数量与三轴路由。三轴决定骨架模板的选择。', 'linked3'); ?></p>

    <!-- 基础配置 -->
    <div class="lk3-form-grid" style="margin-bottom:16px;">

        <!-- v2.0: 画风风格库融合面板 (内嵌画风下拉, 视觉绑定, 修复"看不见"; 合并双AI按钮) -->
        <?php
        $style_select_id        = 'linked3-genesis-style';
        $topic_input_id         = 'linked3-genesis-script';
        $visual_style_select_id = ''; // 漫画脚本无信息图技法下拉, 留空不联动
        $nonce                  = wp_create_nonce('linked3_content_writer');
        $ajax_url               = admin_url('admin-ajax.php');
        $instance               = 'genesis';
        include __DIR__ . '/style-fusion-panel-v2.php';
        ?>

        <div>
            <label class="lk3-form-label"><?php echo esc_html__('🖥️ 目标平台', 'linked3'); ?></label>
            <select id="linked3-genesis-platform" class="lk3-form-control">
                <option value="midjourney">Midjourney</option>
                <option value="stable_diffusion">Stable Diffusion</option>
                <option value="dalle3">DALL·E 3</option>
                <option value="flux">Flux</option>
                <option value="niji">Niji Journey</option>
            </select>
        </div>
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('📊 分镜数量', 'linked3'); ?><span style="font-size:10px;color:#A1A1AA;font-weight:normal;"><?php echo esc_html__('(fixed模式严格生效)', 'linked3'); ?></span></label>
            <input type="number" id="linked3-genesis-panel-count" class="lk3-form-control" value="8" min="1" max="50">
        </div>
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('✂️ 分镜模式', 'linked3'); ?></label>
            <select id="linked3-genesis-split-mode" class="lk3-form-control">
                <option value="auto"><?php echo esc_html__('auto (动态: AI按剧情拆分, 最多15)', 'linked3'); ?></option>
                <option value="fixed"><?php echo esc_html__('fixed (固定: 严格按"分镜数量"生成)', 'linked3'); ?></option>
                <option value="sentence"><?php echo esc_html__('sentence (按句: 每句1分镜)', 'linked3'); ?></option>
            </select>
        </div>
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('📑 章节标记', 'linked3'); ?><span style="font-size:10px;color:#A1A1AA;font-weight:normal;"><?php echo esc_html__('(留空=自动按段落拆分)', 'linked3'); ?></span></label>
            <input type="text" id="linked3-genesis-chapter-marker" class="lk3-form-control" value="" placeholder="<?php echo esc_attr__('留空=自动拆分; 或输入分隔符如: 第X章', 'linked3'); ?>">
        </div>
    </div>

    <!-- v11.0: 漫画分镜布局+画幅比例+渲染技法 (参照图示脚本大格局补全) -->
    <div class="lk3-form-grid" style="margin-bottom:16px;">
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('📐 分镜布局', 'linked3'); ?><span style="font-size:10px;color:#A1A1AA;font-weight:normal;"><?php echo esc_html__('(影响画面构图与节奏)', 'linked3'); ?></span></label>
            <select id="linked3-genesis-panel-layout" class="lk3-form-control">
                <option value="auto"><?php echo esc_html__('🤖 自动适配 (AI根据剧情节奏选最佳布局)', 'linked3'); ?></option>
                <option value="grid-4"><?php echo esc_html__('grid-4 四格网格 (经典漫画, 适合日常叙事)', 'linked3'); ?></option>
                <option value="grid-6"><?php echo esc_html__('grid-6 六格网格 (信息密度高, 适合快节奏)', 'linked3'); ?></option>
                <option value="grid-8"><?php echo esc_html__('grid-8 八格网格 (密集叙事, 适合动作戏)', 'linked3'); ?></option>
                <option value="splash"><?php echo esc_html__('splash 全页大格 (冲击力强, 适合高潮/扉页)', 'linked3'); ?></option>
                <option value="full-width"><?php echo esc_html__('full-width 通栏横幅 (宽幅场景, 适合风景/全景)', 'linked3'); ?></option>
                <option value="vertical-strip"><?php echo esc_html__('vertical-strip 竖条漫 (韩式Webtoon, 适合手机阅读)', 'linked3'); ?></option>
                <option value="manga-classic"><?php echo esc_html__('manga-classic 日漫经典 (2×3变格, 节奏感强)', 'linked3'); ?></option>
                <option value="bd-european"><?php echo esc_html__('bd-european 欧漫条带 (3行横条, 适合法比BD)', 'linked3'); ?></option>
                <option value="cinematic-widescreen"><?php echo esc_html__('cinematic-widescreen 电影宽屏 (16:9, 适合影视感)', 'linked3'); ?></option>
                <option value="dynamic-asymmetric"><?php echo esc_html__('dynamic-asymmetric 动态非对称 (美漫英雄, 破格冲击)', 'linked3'); ?></option>
            </select>
        </div>
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('🖼️ 画幅比例', 'linked3'); ?><span style="font-size:10px;color:#A1A1AA;font-weight:normal;"><?php echo esc_html__('(单格画面比例)', 'linked3'); ?></span></label>
            <select id="linked3-genesis-aspect-ratio" class="lk3-form-control">
                <option value="3:4"><?php echo esc_html__('3:4 竖版 (经典漫画单格)', 'linked3'); ?></option>
                <option value="1:1"><?php echo esc_html__('1:1 方形 (社交媒体/Instagram)', 'linked3'); ?></option>
                <option value="4:3"><?php echo esc_html__('4:3 横版 (传统漫画宽格)', 'linked3'); ?></option>
                <option value="16:9"><?php echo esc_html__('16:9 宽屏 (电影感/影视分镜)', 'linked3'); ?></option>
                <option value="9:16"><?php echo esc_html__('9:16 竖屏 (手机全屏/Webtoon)', 'linked3'); ?></option>
                <option value="2:3"><?php echo esc_html__('2:3 竖版窄 (书籍/杂志封面)', 'linked3'); ?></option>
            </select>
        </div>
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('🖌️ 渲染技法', 'linked3'); ?><span style="font-size:10px;color:#A1A1AA;font-weight:normal;"><?php echo esc_html__('(影响画面质感)', 'linked3'); ?></span></label>
            <select id="linked3-genesis-rendering-tech" class="lk3-form-control">
                <option value="auto"><?php echo esc_html__('🤖 自动 (跟随画风风格)', 'linked3'); ?></option>
                <option value="cel-shading"><?php echo esc_html__('cel-shading 赛璐璐平涂 (日漫标准)', 'linked3'); ?></option>
                <option value="ink-wash"><?php echo esc_html__('ink-wash 水墨渲染 (东方写意)', 'linked3'); ?></option>
                <option value="watercolor"><?php echo esc_html__('watercolor 水彩渲染 (柔彩绘本)', 'linked3'); ?></option>
                <option value="oil-painting"><?php echo esc_html__('oil-painting 油画质感 (厚重写实)', 'linked3'); ?></option>
                <option value="digital-painting"><?php echo esc_html__('digital-painting 数码绘画 (现代主流)', 'linked3'); ?></option>
                <option value="pencil-sketch"><?php echo esc_html__('pencil-sketch 铅笔素描 (草稿感)', 'linked3'); ?></option>
                <option value="halftone-print"><?php echo esc_html__('halftone-print 半调印刷 (复古美漫)', 'linked3'); ?></option>
                <option value="flat-design"><?php echo esc_html__('flat-design 扁平设计 (现代极简)', 'linked3'); ?></option>
                <option value="3d-render"><?php echo esc_html__('3d-render 3D渲染 (CG质感)', 'linked3'); ?></option>
            </select>
        </div>
    </div>

    <!-- v9 三轴路由 (默认启用, 移除checkbox) -->
    <div id="linked3-genesis-v9-options" style="display:block;">
        <div style="background:#FAFAFA;border:1px solid #E4E4E7;border-radius:8px;padding:14px;margin-bottom:12px;">
            <div style="font-size:13px;font-weight:700;margin-bottom:4px;color:#52525B;"><?php echo esc_html__('🎯 三轴路由 (v9 集成模式)', 'linked3'); ?></div>
            <div style="font-size:11px;color:#71717A;margin-bottom:10px;"><?php echo esc_html__('💡 三轴决定骨架模板。选"无"可跳过该轴, 仅用画风风格控制画面。', 'linked3'); ?></div>
            <div class="lk3-axis-grid">
                <div class="lk3-axis-card">
                    <div class="lk3-axis-label"><?php echo esc_html__('L1 · 题材类型', 'linked3'); ?></div>
                    <select id="linked3-genesis-l1" class="lk3-form-control">
                        <option value="auto"><?php echo esc_html__('自动检测', 'linked3'); ?></option>
                        <option value="none"><?php echo esc_html__('无 (跳过L1)', 'linked3'); ?></option>
                        <option value="story"><?php echo esc_html__('故事叙事', 'linked3'); ?></option>
                        <option value="documentary"><?php echo esc_html__('纪录片', 'linked3'); ?></option>
                        <option value="commercial"><?php echo esc_html__('商业广告', 'linked3'); ?></option>
                        <option value="art"><?php echo esc_html__('艺术表达', 'linked3'); ?></option>
                    </select>
                </div>
                <div class="lk3-axis-card">
                    <div class="lk3-axis-label"><?php echo esc_html__('L2 · 视觉栏目', 'linked3'); ?></div>
                    <select id="linked3-genesis-l2" class="lk3-form-control">
                        <option value="auto"><?php echo esc_html__('自动检测', 'linked3'); ?></option>
                        <option value="none"><?php echo esc_html__('无 (跳过L2)', 'linked3'); ?></option>
                        <option value="documentary"><?php echo esc_html__('纪录片摄影', 'linked3'); ?></option>
                        <option value="cyber"><?php echo esc_html__('赛博朋克', 'linked3'); ?></option>
                        <option value="noir"><?php echo esc_html__('黑色悬疑', 'linked3'); ?></option>
                        <option value="watercolor"><?php echo esc_html__('水彩治愈', 'linked3'); ?></option>
                        <option value="floral"><?php echo esc_html__('花系唯美', 'linked3'); ?></option>
                        <option value="guochao"><?php echo esc_html__('国潮东方', 'linked3'); ?></option>
                        <option value="pet"><?php echo esc_html__('萌宠可爱', 'linked3'); ?></option>
                        <option value="suspense"><?php echo esc_html__('悬疑', 'linked3'); ?></option>
                        <option value="healing"><?php echo esc_html__('治愈', 'linked3'); ?></option>
                    </select>
                </div>
                <div class="lk3-axis-card">
                    <div class="lk3-axis-label"><?php echo esc_html__('L3 · 灵魂风格', 'linked3'); ?><span style="font-size:10px;color:#A1A1AA;font-weight:normal;"><?php echo esc_html__('(括号内为适用场景)', 'linked3'); ?></span></div>
                    <select id="linked3-genesis-l3" class="lk3-form-control">
                        <option value="auto"><?php echo esc_html__('自动检测', 'linked3'); ?></option>
                        <option value="none"><?php echo esc_html__('无 (跳过L3, 仅用画风风格)', 'linked3'); ?></option>
                        <optgroup label="─ 主流商业 ─">
                        <option value="cinematic"><?php echo esc_html__('电影感 (影视剧照/品牌大片/高端商业)', 'linked3'); ?></option>
                        <option value="magazine"><?php echo esc_html__('杂志感 (时尚封面/品牌广告/高端产品)', 'linked3'); ?></option>
                        <option value="xiaohongshu"><?php echo esc_html__('小红书感 (种草图文/生活方式/美妆穿搭)', 'linked3'); ?></option>
                        <option value="documentary"><?php echo esc_html__('纪实感 (新闻报道/人文纪实/故事叙事)', 'linked3'); ?></option>
                        </optgroup>
                        <optgroup label="─ 大师风格 ─">
                        <option value="miyazaki"><?php echo esc_html__('宫崎骏 (治愈动漫/奇幻冒险/温暖童话)', 'linked3'); ?></option>
                        <option value="mucha"><?php echo esc_html__('穆夏 (装饰海报/复古插画/唯美女性)', 'linked3'); ?></option>
                        <option value="klimt"><?php echo esc_html__('克里姆特 (装饰艺术/金色奢华/情感表达)', 'linked3'); ?></option>
                        <option value="hopper"><?php echo esc_html__('霍珀 (都市孤独/光影叙事/情绪场景)', 'linked3'); ?></option>
                        <option value="banksy"><?php echo esc_html__('班克西 (涂鸦艺术/社会讽刺/街头文化)', 'linked3'); ?></option>
                        </optgroup>
                        <optgroup label="─ 传奇/小众 ─">
                        <option value="playboy_retro"><?php echo esc_html__('花花公子复古 (复古性感/男性生活方式/奢华派对)', 'linked3'); ?></option>
                        <option value="warhol"><?php echo esc_html__('安迪·沃霍尔 (波普艺术/重复复制/消费文化)', 'linked3'); ?></option>
                        <option value="dali"><?php echo esc_html__('达利超现实 (超现实/梦境/潜意识探索)', 'linked3'); ?></option>
                        <option value="picasso"><?php echo esc_html__('毕加索 (立体主义/抽象表达/艺术实验)', 'linked3'); ?></option>
                        <option value="basquiat"><?php echo esc_html__('巴斯奎特 (新表现主义/街头艺术/原始力量)', 'linked3'); ?></option>
                        <option value="wes_anderson"><?php echo esc_html__('韦斯·安德森 (对称构图/复古色调/quirky美学)', 'linked3'); ?></option>
                        <option value="tim_burton"><?php echo esc_html__('蒂姆·伯顿 (哥特暗黑/怪诞童话/诡异可爱)', 'linked3'); ?></option>
                        </optgroup>
                        <optgroup label="─ 东方美学 ─">
                        <option value="ukiyoe"><?php echo esc_html__('浮世绘 (和风古典/木版画/东方传统)', 'linked3'); ?></option>
                        <option value="song_dynasty"><?php echo esc_html__('宋画意境 (水墨留白/文人雅趣/禅意山水)', 'linked3'); ?></option>
                        <option value="dunhuang"><?php echo esc_html__('敦煌壁画 (佛教艺术/飞天纹样/西域色彩)', 'linked3'); ?></option>
                        </optgroup>
                    </select>
                </div>
            </div>
            <div class="lk3-axis-hint" id="linked3-genesis-skeleton-hint"><?php echo esc_html__('骨架路由: 故事叙事 × 纪录片摄影 × 电影感 →', 'linked3'); ?><strong>documentary_photo</strong></div>
        </div>
    </div>

    <!-- 保留 v9 checkbox (hidden, 默认checked, JS兼容) -->
    <input type="checkbox" id="linked3-genesis-v9-mode" checked style="display:none;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;">
        <button type="button" class="lk3-btn lk3-btn-sm" onclick="lk3GoStage(1)"><?php echo esc_html__('← 上一步', 'linked3'); ?></button>
        <button type="button" class="lk3-btn lk3-btn-sm lk3-btn-primary" onclick="lk3GoStage(3)"><?php echo esc_html__('下一步: 生成执行 →', 'linked3'); ?></button>
    </div>
</div>

