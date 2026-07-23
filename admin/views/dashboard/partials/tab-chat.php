<?php
/**
 * Dashboard partial: chat tab.
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

                echo '<h2>AI 对话</h2>';
                echo '<div class="notice notice-info inline"><p><strong>功能说明:</strong>浮动客服窗(前台显示)、RAG 知识库检索(从文章提取上下文)、会话历史、三层 Moderation 审核(封禁词/封禁IP/OpenAI审核)。</p></div>';
                // 内联对话设置
                $floating_enabled = get_option(LINKED3_OPTION_PREFIX . 'chat_floating_enabled', 0);
                $system_prompt = get_option(LINKED3_OPTION_PREFIX . 'chat_system_prompt', '');
                $use_rag = get_option(LINKED3_OPTION_PREFIX . 'chat_use_rag', 0);
                $guest_limit = get_option(LINKED3_OPTION_PREFIX . 'guest_chat_limit', 10);
                echo '<table class="form-table">';
                echo '<tr><th>浮动客服窗</th><td>' . ($floating_enabled ? '✓ 已启用' : '✗ 未启用') . '</td></tr>';
                echo '<tr><th>系统提示词</th><td>' . esc_html(mb_substr($system_prompt, 0, 80)) . (mb_strlen($system_prompt) > 80 ? '...' : '') . '</td></tr>';
                echo '<tr><th>RAG 知识库</th><td>' . ($use_rag ? '✓ 已启用' : '✗ 未启用') . '</td></tr>';
                echo '<tr><th>游客每日限制</th><td>' . (int)$guest_limit . ' 条</td></tr>';
                echo '</table>';
                // v11.3.5: 未启用浮动窗时给出引导
                if (!$floating_enabled) {
                    echo '<div class="notice notice-info inline"><p>💡 浮动客服窗未启用。前往「对话设置」开启后, 前台将显示可拖动的客服气泡, 访客可直接与 AI 对话。</p></div>';
                }
                echo '<p><a href="' . esc_url(admin_url('admin.php?page=linked3-chat')) . '" class="button button-primary">对话设置</a></p>';
                echo '<p>短代码:<code>[linked3_chat]</code>(浮动) <code>[linked3_chat embedded="1"]</code>(内嵌)</p>';
