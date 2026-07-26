<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap linked3-writing-center">
    <h2><?php echo esc_html__('✍️ 写作中心', 'linked3'); ?></h2>
    <p class="description"><?php echo esc_html__('从选题到发布 · 五步写出高质量文章', 'linked3'); ?></p>

    <!-- Progress indicator -->
    <div class="l3-progress-bar" style="display:flex;margin-bottom:20px;border-radius:4px;overflow:hidden;">
        <div class="l3-step active" data-step="1" style="flex:1;padding:10px;text-align:center;background:#2271b1;color:#fff;cursor:pointer;font-size:13px;"><?php echo esc_html__('① 选题', 'linked3'); ?></div>
        <div class="l3-step" data-step="2" style="flex:1;padding:10px;text-align:center;background:#ddd;cursor:pointer;font-size:13px;"><?php echo esc_html__('② 大纲', 'linked3'); ?></div>
        <div class="l3-step" data-step="3" style="flex:1;padding:10px;text-align:center;background:#ddd;cursor:pointer;font-size:13px;"><?php echo esc_html__('③ 正文', 'linked3'); ?></div>
        <div class="l3-step" data-step="4" style="flex:1;padding:10px;text-align:center;background:#ddd;cursor:pointer;font-size:13px;"><?php echo esc_html__('④ 优化', 'linked3'); ?></div>
        <div class="l3-step" data-step="5" style="flex:1;padding:10px;text-align:center;background:#ddd;cursor:pointer;font-size:13px;"><?php echo esc_html__('⑤ 发布', 'linked3'); ?></div>
    </div>

    <!-- Step 1: Topic Selection -->
    <div class="l3-panel" id="l3-step-1">
        <h3><?php echo esc_html__('① 选题 · 确定写什么', 'linked3'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label><?php echo esc_html__('关键词 / 主题', 'linked3'); ?></label></th>
                <td><input type="text" id="l3_wt_topic" class="large-text" placeholder="<?php echo esc_attr__('输入关键词或主题', 'linked3'); ?>" /></td>
            </tr>
            <tr>
                <th><label><?php echo esc_html__('热词采集', 'linked3'); ?></label></th>
                <td>
                    <button type="button" class="button" onclick="l3_fetch_hotwords()"><?php echo esc_html__('🔥 采集热词', 'linked3'); ?></button>
                    <span id="l3_hotwords_result" style="margin-left:10px;"></span>
                </td>
            </tr>
            <tr>
                <th><label><?php echo esc_html__('写作风格', 'linked3'); ?></label></th>
                <td>
                    <select id="l3_wt_style">
                        <option value="professional"><?php echo esc_html__('专业严谨', 'linked3'); ?></option>
                        <option value="casual"><?php echo esc_html__('轻松口语', 'linked3'); ?></option>
                        <option value="academic"><?php echo esc_html__('学术规范', 'linked3'); ?></option>
                        <option value="marketing"><?php echo esc_html__('营销转化', 'linked3'); ?></option>
                        <option value="storytelling"><?php echo esc_html__('故事叙事', 'linked3'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label><?php echo esc_html__('字数', 'linked3'); ?></label></th>
                <td>
                    <input type="number" id="l3_wt_wordcount" value="1200" min="300" max="5000" step="100" />
                    <span class="description"><?php echo esc_html__('建议 800-2000 字', 'linked3'); ?></span>
                </td>
            </tr>
            <tr>
                <th><label><?php echo esc_html__('🧠 思维杠杆', 'linked3'); ?></label></th>
                <td>
                    <select id="l3_wt_lever">
                        <option value=""><?php echo esc_html__('不使用杠杆', 'linked3'); ?></option>
                        <option value="universal_trio"><?php echo esc_html__('万能思维新三法 (本质×反向×系统)', 'linked3'); ?></option>
                        <option value="creative_engine"><?php echo esc_html__('创意生成引擎 (创造×类比×跨界)', 'linked3'); ?></option>
                        <option value="quality_gauntlet"><?php echo esc_html__('质量绞杀阵 (批判×压测×校准)', 'linked3'); ?></option>
                        <option value="meta_socratic"><?php echo esc_html__('苏格拉底追问', 'linked3'); ?></option>
                        <option value="meta_essence"><?php echo esc_html__('本质追问', 'linked3'); ?></option>
                    </select>
                    <p class="description"><?php echo esc_html__('应用思维杠杆提升文章深度 — 杠杆会在生成时自动注入AI提示词', 'linked3'); ?></p>
                </td>
            </tr>
        </table>
        <p><button type="button" class="button button-primary" onclick="l3_generate_outline()"><?php echo esc_html__('→ 生成大纲', 'linked3'); ?></button></p>
    </div>

    <!-- Step 2: Outline -->
    <div class="l3-panel" id="l3-step-2" style="display:none;">
        <h3><?php echo esc_html__('② 大纲 · 结构设计', 'linked3'); ?></h3>
        <textarea id="l3_wt_outline" rows="10" class="large-text" placeholder="<?php echo esc_attr__('大纲将在此显示, 可编辑修改', 'linked3'); ?>"></textarea>
        <p>
            <button type="button" class="button" onclick="l3_show_step(1)"><?php echo esc_html__('← 返回选题', 'linked3'); ?></button>
            <button type="button" class="button button-primary" onclick="l3_generate_content()"><?php echo esc_html__('→ 生成正文', 'linked3'); ?></button>
        </p>
    </div>

    <!-- Step 3: Content -->
    <div class="l3-panel" id="l3-step-3" style="display:none;">
        <h3><?php echo esc_html__('③ 正文 · AI 写作', 'linked3'); ?></h3>
        <div id="l3_wt_progress" style="margin-bottom:10px;"></div>
        <textarea id="l3_wt_content" rows="20" class="large-text" placeholder="<?php echo esc_attr__('正文将在此显示, 可编辑修改', 'linked3'); ?>"></textarea>
        <p>
            <button type="button" class="button" onclick="l3_show_step(2)"><?php echo esc_html__('← 返回大纲', 'linked3'); ?></button>
            <button type="button" class="button button-primary" onclick="l3_optimize_seo()"><?php echo esc_html__('→ SEO 优化', 'linked3'); ?></button>
        </p>
    </div>

    <!-- Step 4: SEO Optimization -->
    <div class="l3-panel" id="l3-step-4" style="display:none;">
        <h3><?php echo esc_html__('④ 优化 · SEO + 质量评分', 'linked3'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label><?php echo esc_html__('SEO 标题', 'linked3'); ?></label></th>
                <td><input type="text" id="l3_wt_seo_title" class="large-text" /></td>
            </tr>
            <tr>
                <th><label><?php echo esc_html__('Meta 描述', 'linked3'); ?></label></th>
                <td><textarea id="l3_wt_meta_desc" rows="3" class="large-text"></textarea></td>
            </tr>
            <tr>
                <th><label><?php echo esc_html__('标签', 'linked3'); ?></label></th>
                <td><input type="text" id="l3_wt_tags" class="large-text" placeholder="<?php echo esc_attr__('逗号分隔', 'linked3'); ?>" /></td>
            </tr>
            <tr>
                <th><label><?php echo esc_html__('质量评分', 'linked3'); ?></label></th>
                <td id="l3_wt_quality"></td>
            </tr>
        </table>
        <p>
            <button type="button" class="button" onclick="l3_show_step(3)"><?php echo esc_html__('← 返回正文', 'linked3'); ?></button>
            <button type="button" class="button button-primary" onclick="l3_publish()"><?php echo esc_html__('→ 发布', 'linked3'); ?></button>
        </p>
    </div>

    <!-- Step 5: Publish -->
    <div class="l3-panel" id="l3-step-5" style="display:none;">
        <h3><?php echo esc_html__('⑤ 发布', 'linked3'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label><?php echo esc_html__('发布状态', 'linked3'); ?></label></th>
                <td>
                    <select id="l3_wt_status">
                        <option value="draft"><?php echo esc_html__('草稿', 'linked3'); ?></option>
                        <option value="publish"><?php echo esc_html__('立即发布', 'linked3'); ?></option>
                        <option value="pending"><?php echo esc_html__('待审', 'linked3'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label><?php echo esc_html__('分类', 'linked3'); ?></label></th>
                <td>
                    <?php wp_dropdown_categories(['hide_empty' => 0, 'name' => 'l3_wt_category', 'show_option_none' => __('选择分类', 'linked3')]); ?>
                </td>
            </tr>
        </table>
        <p>
            <button type="button" class="button" onclick="l3_show_step(4)"><?php echo esc_html__('← 返回优化', 'linked3'); ?></button>
            <button type="button" class="button button-primary button-large" onclick="l3_do_publish()"><?php echo esc_html__('⚡ 发布文章', 'linked3'); ?></button>
        </p>
        <div id="l3_publish_result"></div>
    </div>
</div>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-writing-center.js ?>
