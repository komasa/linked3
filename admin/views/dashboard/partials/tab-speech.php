<?php
/**
 * Dashboard partial: speech tab.
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

                echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:15px;margin:15px 0;">';
                echo '<h3 style="margin-top:0;">使用场景</h3>';
                echo '<table class="widefat" style="font-size:13px;"><thead><tr><th>场景</th><th>操作</th><th>适用人群</th></tr></thead><tbody>';
                echo '<tr><td><strong>无障碍博客</strong></td><td>用 [linked3_tts text="文字"] 嵌入朗读按钮</td><td>无障碍站点</td></tr>';
                echo '<tr><td><strong>播客音频版</strong></td><td>长文章转语音,嵌入audio播放器</td><td>内容创作者</td></tr>';
                echo '<tr><td><strong>会议记录转文章</strong></td><td>STT上传录音→转文字→编辑成文</td><td>会议记录员</td></tr>';
                echo '</tbody></table>';
                echo '</div>';
                echo '<h2>语音 TTS / STT</h2>';
                echo '<div class="notice notice-info inline"><p><strong>功能说明:</strong></p>';
                echo '<ul style="list-style:disc;margin-left:20px;">';
                echo '<li><strong>TTS(文字转语音):</strong>用短代码 <code>[linked3_tts text="文字" voice="alloy"]</code> 嵌入朗读按钮。</li>';
                echo '<li><strong>STT(语音转文字):</strong>通过 REST API 上传音频获取转写。</li>';
                echo '<li>语音:alloy, echo, fable, onyx, nova, shimmer(OpenAI)。</li>';
                echo '<li>需要 Pro+ 套餐。速率限制:10次/小时。</li>';
                echo '</ul></div>';
                // v11.3.6: 套餐检测引导
                $speech_license = get_option('linked3_license', []);
                $plan = $speech_license['plan'] ?? 'free';
                if ($plan === 'free' || $plan === 'basic') {
                    echo '<div class="notice notice-warning inline"><p>⚠️ 当前套餐为 ' . esc_html(strtoupper($plan)) . '。语音 TTS/STT 需要 <strong>Pro+</strong> 套餐。前往 <a href="' . esc_url(admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=license')) . '">授权与套餐</a> 升级。</p></div>';
                }
                echo '<p><a href="' . esc_url(admin_url('admin.php?page=linked3-speech')) . '" class="button button-primary">语音设置</a></p>';
