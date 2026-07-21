<?php
/**
 * Dashboard partial: commerce tab.
 *
 * Extracted from tabs.php in v4.4.1 to keep the router file under
 * 100 lines. Each partial owns its own HTML fragment and is
 * included by tabs.php inside the .linked3-tab-content wrapper.
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 */

if (!defined('ABSPATH')) {
    exit;
}

                echo '<h2>电商与表单</h2>';
                echo '<div class="notice notice-info inline"><p><strong>功能说明:</strong>WooCommerce 商品 AI 生成 + Token 套餐变现 + AI 智能表单。</p></div>';

                // v3.2.0: 使用场景说明 (修正之前的错误说明)
                echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:15px;margin:15px 0;">';
                echo '<h3 style="margin-top:0;">📦 商品 AI (需安装 WooCommerce)</h3>';
                echo '<p style="color:#666;font-size:13px;">批量生成商品描述、AI 评价(合规标注)、DALL-E 3 商品主图。适合 WooCommerce 店主批量运营商品。</p>';
                echo '<table class="widefat" style="font-size:13px;"><thead><tr><th>使用场景</th><th>操作</th><th>适用人群</th></tr></thead><tbody>';
                echo '<tr><td><strong>批量补全商品描述</strong></td><td>导入 100 个商品只有标题 → 一键批量生成描述 → 自动写入 post_content</td><td>WooCommerce 店主、跨境独立站</td></tr>';
                echo '<tr><td><strong>新品冷启动评价</strong></td><td>新商品 0 评价 → 生成 3 条 4-5 星 AI 评价(待审核,带 [AI 生成评论] 标注) → 人工审核后上线</td><td>新店冷启动(已合规标注)</td></tr>';
                echo '<tr><td><strong>无图商品主图生成</strong></td><td>商品图缺失 → DALL-E 3 根据商品名生成主图 → 自动设为特色图片</td><td>代发货、虚拟商品</td></tr>';
                echo '<tr><td><strong>卖 Token 套餐变现</strong></td><td>站长把 AI 写作能力包装成 Token 包商品 → 用户下单完成 → 自动加 Token 余额</td><td>AI SaaS 站长</td></tr>';
                echo '<tr><td><strong>套餐升级销售</strong></td><td>Token 包产品配置"套餐升级=pro" → 用户购买 → 自动升级 license plan</td><td>AI SaaS 站长</td></tr>';
                echo '</tbody></table>';
                echo '<p style="color:#DC2626;font-size:12px;">⚠️ 场景 1-3 需安装 WooCommerce 插件。场景 4-5 (Token 套餐) 同样需要 WooCommerce。</p>';
                echo '</div>';

                echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:15px;margin:15px 0;">';
                echo '<h3 style="margin-top:0;">📝 AI 智能表单</h3>';
                echo '<p style="color:#666;font-size:13px;">用短代码嵌入表单,提交后 AI 分析内容,邮件通知。支持 7 种字段类型。</p>';
                echo '<table class="widefat" style="font-size:13px;"><thead><tr><th>使用场景</th><th>操作</th><th>适用人群</th></tr></thead><tbody>';
                echo '<tr><td><strong>客户咨询 + AI 初筛</strong></td><td>嵌入表单(姓名/电话/需求)→ AI 分析"客户意向等级+推荐产品" → 邮件通知销售</td><td>B2B 官网、SaaS 落地页</td></tr>';
                echo '<tr><td><strong>Bug 反馈 + AI 分类</strong></td><td>表单(模块/严重程度/复现步骤)→ AI 自动分类(前端/后端/数据库)+ 优先级评估 → 邮件通知开发</td><td>软件公司</td></tr>';
                echo '<tr><td><strong>简历投递 + AI 筛选</strong></td><td>表单(姓名/经验/期望薪资)→ AI 评估"匹配度评分+推荐岗位" → HR 邮件收到结构化分析</td><td>HR</td></tr>';
                echo '<tr><td><strong>售后投诉 + AI 情感分析</strong></td><td>表单(订单号/问题描述)→ AI 判断"情感倾向(愤怒/中性)+紧急程度" → 客服邮件</td><td>电商客服</td></tr>';
                echo '<tr><td><strong>活动报名 + AI 摘要</strong></td><td>表单(姓名/公司/职位/兴趣)→ AI 生成"参会者画像摘要"给主办方</td><td>线下活动主办方</td></tr>';
                echo '</tbody></table>';
                echo '</div>';

                echo '<p>';
                // v11.3.4: WooCommerce 未激活时给出引导, 避免点击跳转到404
                if (!class_exists('WooCommerce')) {
                    echo '<div class="notice notice-warning inline"><p>⚠️ 未检测到 WooCommerce 插件。场景 1-5 需先安装并启用 <a href="' . esc_url(admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')) . '" target="_blank">WooCommerce</a>。</p></div>';
                    echo '<a href="' . esc_url(admin_url('admin.php?page=linked3-forms')) . '" class="button button-primary">AI 表单管理</a>';
                } else {
                    echo '<a href="' . esc_url(admin_url('admin.php?page=linked3-wc')) . '" class="button button-primary">商品 AI 设置</a> ';
                    echo '<a href="' . esc_url(admin_url('admin.php?page=linked3-forms')) . '" class="button button-primary">AI 表单管理</a>';
                }
                echo '</p>';
                echo '<p class="description">AI 表单短代码:<code>[linked3_form id="0"]</code> — 嵌入到任意文章/页面。提交后 AI 自动分析 + 邮件通知。</p>';
