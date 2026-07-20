<?php

declare(strict_types=1);
/**
 * ConsentComplianceAddon — extracted from AddonManager.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Addons

namespace Linked3\Classes\Addons;

if (!defined('ABSPATH')) exit;

final class ConsentComplianceAddon implements AddonInterface
{
    public function slug() : string { return 'consent-compliance'; }
    public function is_required() : bool { return false; }
    public function is_active() : bool { return (bool) get_option(LINKED3_OPTION_PREFIX . 'addon_consent', false); }
    public function execute() : void
    {
        add_action('wp_footer', [$this, 'render_banner']);
    }
    public function render_banner() : void
    {
        if (is_admin()) return;
        echo '<div id="linked3-consent" style="position:fixed;bottom:0;left:0;right:0;background:#1f2937;color:#fff;padding:12px;text-align:center;z-index:99998;font-size:14px;">'
            . esc_html__('我们使用 Cookie 改善您的体验,继续使用即表示同意。', 'linked3')
            . ' <button onclick="document.getElementById(\'linked3-consent\').style.display=\'none\';localStorage.setItem(\'linked3_consent\',\'1\');" style="margin-left:10px;padding:4px 12px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer;">'
            . esc_html__('成功', 'linked3') . '</button></div>'
            . '<script>if(localStorage.getItem(\'linked3_consent\')){document.getElementById(\'linked3-consent\').style.display=\'none\';}</script>';
    }
}
