<?php

declare(strict_types=1);
/**
 * Dashboard Tab Registry — v28 PR-05
 *
 * 从 tabs.php 提取的 Tab 元数据注册表。
 * 单一数据源 → tabs.php / Command Palette / SEO schema 共用。
 *
 * 设计原则:
 *   - tabs.php 降级为 thin template, 只读取 Registry 数据
 *   - 新增 Tab 只修改此文件, 不触碰模板
 *   - legacy redirect 表集中管理, 不散落在模板中
 *
 * @package Linked3\Classes\Dashboard\Registry
 * @since   28.0
 */

namespace Linked3\Classes\Dashboard\Registry;

if (!defined('ABSPATH')) {
    exit;
}

final class DashboardTabRegistry
{
    /**
     * 6 Super-Tab 元数据 (按用户意图分组).
     *
     * @return array<string, array{label:string, icon:string, color:string, desc:string, short:string}>
     */
    public static function tabs(): array
    {
        return [
            'overview' => [
                'label'  => __('总览', 'linked3'),
                'icon'   => '🏠',
                'color'  => '#6366F1',
                'desc'   => __('数据看板 · 快速概览', 'linked3'),
                'short'  => __('总览', 'linked3'),
            ],
            'cognitive-os' => [
                'label'  => __('认知OS', 'linked3'),
                'icon'   => '🧠',
                'color'  => '#667eea',
                'desc'   => __('双公理 · 五部门 · 三代演化 · 十二杠杆', 'linked3'),
                'short'  => __('认知OS', 'linked3'),
            ],
            'creation' => [
                'label'  => __('创作中心', 'linked3'),
                'icon'   => '✍️',
                'color'  => '#0F172A',
                'desc'   => __('写作生态 · 视觉生态 · 云模版', 'linked3'),
                'short'  => __('创作', 'linked3'),
            ],
            'distribution' => [
                'label'  => __('分发中心', 'linked3'),
                'icon'   => '📤',
                'color'  => '#059669',
                'desc'   => __('发布采集 · 社交分发 · 电商表单', 'linked3'),
                'short'  => __('分发', 'linked3'),
            ],
            'automation' => [
                'label'  => __('自动化', 'linked3'),
                'icon'   => '🤖',
                'color'  => '#7C3AED',
                'desc'   => __('自动Agent · AI对话 · 定时任务', 'linked3'),
                'short'  => __('自动化', 'linked3'),
            ],
            'v18' => [
                'label'  => __('拆解OS', 'linked3'),
                'icon'   => '🔮',
                'color'  => '#DB2777',
                'desc'   => __('前沿实验 · 创新功能', 'linked3'),
                'short'  => __('实验室', 'linked3'),
            ],
            'system' => [
                'label'  => __('系统设置', 'linked3'),
                'icon'   => '⚙️',
                'color'  => '#475569',
                'desc'   => __('API · SEO · 语音 · 授权 · 安全', 'linked3'),
                'short'  => __('系统', 'linked3'),
            ],
        ];
    }

    /**
     * 旧 Tab → 新 Super-Tab 重定向表 (100% 向后兼容).
     *
     * 格式: '旧tab' => ['新tab', '子参数名', '子参数值']
     *
     * @return array<string, array{0:string, 1:string, 2:string}>
     */
    public static function legacyRedirectMap(): array
    {
        return [
            // → 创作中心
            'ecosystem'     => ['creation', 'cr_sub', 'ecosystem'],
            'visual'        => ['creation', 'cr_sub', 'visual'],
            'cloud'         => ['creation', 'cr_sub', 'cloud'],
            'style-library' => ['creation', 'cr_sub', 'visual'],
            // → 分发中心
            'publish'    => ['distribution', 'di_sub', 'publish'],
            'distribute' => ['distribution', 'di_sub', 'distribute'],
            'commerce'   => ['distribution', 'di_sub', 'commerce'],
            // → 自动化
            'autogpt' => ['automation', 'au_sub', 'autogpt'],
            'chat'    => ['automation', 'au_sub', 'chat'],
            // → 系统设置
            'api'      => ['system', 'sy_sub', 'api'],
            'seo'      => ['system', 'sy_sub', 'seo'],
            'speech'   => ['system', 'sy_sub', 'speech'],
            'license'  => ['system', 'sy_sub', 'license'],
            'security' => ['system', 'sy_sub', 'security'],
        ];
    }

    /**
     * v10.7.3 遗留重定向: 写作生态子面板.
     *
     * @return array<string, string>
     */
    public static function ecoRedirectMap(): array
    {
        return [
            'content'   => 'content',
            'keywords'  => 'keywords',
            'templates' => 'templates',
            'images'    => 'images',
        ];
    }

    /**
     * v10.7.3 遗留重定向: 视觉生态子面板.
     *
     * @return array<string, string>
     */
    public static function visualRedirectMap(): array
    {
        return [
            'charts'  => 'charts',
            'genesis' => 'genesis',
            'video'   => 'video',
            'xhs'     => 'xhs',
        ];
    }

    /**
     * 解析当前 Tab slug, 处理 legacy redirect.
     * 如果需要重定向, 执行 wp_safe_redirect 并 exit.
     *
     * @param string $raw_tab 原始 tab 参数
     * @return string 合法化后的 tab slug
     */
    public static function resolveTab(string $raw_tab): string
    {
        $current_tab = sanitize_key($raw_tab);

        // legacy redirect
        $legacy = self::legacyRedirectMap();
        if (isset($legacy[$current_tab])) {
            [$new_tab, $sub_key, $sub_val] = $legacy[$current_tab];
            $url = admin_url('admin.php?page=linked3-dashboard&tab=' . $new_tab . '&' . $sub_key . '=' . $sub_val);
            wp_safe_redirect($url);
            exit;
        }

        // queue → 自动化 > Agent > 队列
        if ($current_tab === 'queue') {
            wp_safe_redirect(admin_url('admin.php?page=linked3-dashboard&tab=automation&au_sub=autogpt&sub=queue'));
            exit;
        }

        // 写作生态遗留
        $eco = self::ecoRedirectMap();
        if (isset($eco[$current_tab])) {
            wp_safe_redirect(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=' . $eco[$current_tab]));
            exit;
        }

        // 视觉生态遗留
        $visual = self::visualRedirectMap();
        if (isset($visual[$current_tab])) {
            wp_safe_redirect(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=visual&vs_sub=' . $visual[$current_tab]));
            exit;
        }

        // Whitelist
        $tabs = self::tabs();
        if (!array_key_exists($current_tab, $tabs)) {
            $current_tab = 'overview';
        }

        return $current_tab;
    }

    /**
     * 获取 partial 文件路径.
     *
     * @param string $tab
     * @return string 绝对路径
     */
    public static function partialPath(string $tab): string
    {
        return LINKED3_DIR . 'admin/views/dashboard/partials/tab-' . $tab . '.php';
    }

    /**
     * Command Palette 命令列表.
     *
     * @return array<int, array{label:string, desc:string, url:string}>
     */
    public static function commandPaletteCommands(): array
    {
        return [
            ['label' => __('🏠 总览', 'linked3'), 'desc' => __('Dashboard首页', 'linked3'), 'url' => admin_url('admin.php?page=linked3-dashboard')],
            ['label' => __('✍️ 创作中心 · 写作生态', 'linked3'), 'desc' => __('关键词/模版/内容写作/图片', 'linked3'), 'url' => admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem')],
            ['label' => __('✍️ 创作中心 · 视觉生态', 'linked3'), 'desc' => __('图示/漫画/视频/小红书脚本', 'linked3'), 'url' => admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=visual')],
            ['label' => __('✍️ 创作中心 · 云模版', 'linked3'), 'desc' => __('50场景母版库', 'linked3'), 'url' => admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=cloud')],
            ['label' => __('📤 分发中心 · 发布与采集', 'linked3'), 'desc' => __('多目标发布+URL采集', 'linked3'), 'url' => admin_url('admin.php?page=linked3-dashboard&tab=distribution&di_sub=publish')],
            ['label' => __('📤 分发中心 · 社交分发', 'linked3'), 'desc' => __('15+平台同步', 'linked3'), 'url' => admin_url('admin.php?page=linked3-dashboard&tab=distribution&di_sub=distribute')],
            ['label' => __('📤 分发中心 · 电商与表单', 'linked3'), 'desc' => __('WooCommerce+AI表单', 'linked3'), 'url' => admin_url('admin.php?page=linked3-dashboard&tab=distribution&di_sub=commerce')],
            ['label' => __('🤖 自动化 · 自动Agent', 'linked3'), 'desc' => __('定时任务+队列', 'linked3'), 'url' => admin_url('admin.php?page=linked3-dashboard&tab=automation&au_sub=autogpt')],
            ['label' => __('🤖 自动化 · AI对话', 'linked3'), 'desc' => __('浮动客服+RAG', 'linked3'), 'url' => admin_url('admin.php?page=linked3-dashboard&tab=automation&au_sub=chat')],
            ['label' => __('⚙️ 系统设置 · API密钥', 'linked3'), 'desc' => __('AI Provider配置', 'linked3'), 'url' => admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=api')],
            ['label' => __('⚙️ 系统设置 · SEO优化', 'linked3'), 'desc' => __('关键词/内链/Schema/推送', 'linked3'), 'url' => admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=seo')],
            ['label' => __('⚙️ 系统设置 · 授权套餐', 'linked3'), 'desc' => __('License+套餐对比', 'linked3'), 'url' => admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=license')],
            ['label' => __('⚙️ 系统设置 · 安全审计', 'linked3'), 'desc' => __('AJAX端点扫描', 'linked3'), 'url' => admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=security')],
        ];
    }
}
