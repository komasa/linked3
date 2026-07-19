<?php
/**
 * 自动更新检查 — 迁移 v2.9.6 handle_auto_update_plugin。
 *
 * 原版从私有服务器下载 zip 覆盖安装(有中间人风险)。
 * 本版改为:
 *   - 只检查 linked3.com 官方更新 API
 *   - 显示更新通知,用户手动点击更新
 *   - 不自动覆盖文件
 *
 * @package Linked3
 * @subpackage Classes\Admin
 */

namespace Linked3\Classes\Admin;

use Linked3\Includes\Http\Linked3_Safe_Remote;



if (!defined('ABSPATH')) {
    exit;
}
final class Linked3_Update_Checker
{
    // v4.7.2/v4.7.4: default to the LINKED3_UPDATE_API_URL constant (empty
    // in local mode). The old hardcoded 'https://linked3.com/api/updates'
    // was a fake domain that caused silent HTTP failures every 12h.
    const UPDATE_API_DEFAULT = '';

    /**
     * Get the update API URL (filterable, empty in local mode).
     *
     * @return string
     */
    public static function update_api_url() : mixed {
        $default = defined('LINKED3_UPDATE_API_URL') ? LINKED3_UPDATE_API_URL : self::UPDATE_API_DEFAULT;
        return (string) apply_filters('linked3/update_api_url', $default);
    }

    public static function register()
    : void {
        // v4.7.4: only register update hooks if a real update API URL is
        // configured. In local mode (empty URL), skip entirely so no
        // HTTP requests are made and no transients are polled.
        if (self::update_api_url() === '') {
            return;
        }
        add_action('admin_init', [__CLASS__, 'check_for_updates']);
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'inject_update']);
        add_filter('plugins_api', [__CLASS__, 'plugin_api_info'], 20, 3);
    }

    /**
     * 定期检查更新(每 12 小时)。
     */
    public static function check_for_updates()
    : void {
        // v4.7.4: double-check the URL is non-empty (defensive).
        $api_url = self::update_api_url();
        if ($api_url === '') {
            return;
        }

        $last = (int) get_option(LINKED3_OPTION_PREFIX . 'last_update_check', 0);
        if (time() - $last < 12 * HOUR_IN_SECONDS) {
            return;
        }
        update_option(LINKED3_OPTION_PREFIX . 'last_update_check', time());

        try {
            $resp = Linked3_Safe_Remote::get($api_url . '?version=' . LINKED3_VERSION, [
                'timeout' => 10,
                'allowed_hosts' => [wp_parse_url($api_url, PHP_URL_HOST)],
            ]);
            if (is_wp_error($resp)) {
                return;
            }
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            if (isset($body['latest_version'])) {
                update_option(LINKED3_OPTION_PREFIX . 'latest_version', $body['latest_version']);
                update_option(LINKED3_OPTION_PREFIX . 'update_package_url', $body['package_url'] ?? '');
                update_option(LINKED3_OPTION_PREFIX . 'update_changelog', $body['changelog'] ?? '');
            }
        } catch (\Throwable $e) {
            // 静默失败,不影响站点
        }
    }

    /**
     * 注入 WP 的更新瞬态。
     */
    public static function inject_update($transient) : mixed     {
        if (empty($transient)) return $transient;
        $latest = get_option(LINKED3_OPTION_PREFIX . 'latest_version', '');
        $package = get_option(LINKED3_OPTION_PREFIX . 'update_package_url', '');
        if (!$latest || version_compare($latest, LINKED3_VERSION, '<=')) {
            return $transient;
        }
        $obj = new \stdClass();
        $obj->slug = 'linked3';
        $obj->plugin = LINKED3_BASENAME;
        $obj->new_version = $latest;
        $obj->package = $package;
        $transient->response[LINKED3_BASENAME] = $obj;
        return $transient;
    }

    /**
     * 更新详情弹窗。
     */
    public static function plugin_api_info($result, $action, $args)
    {
        if ($action !== 'plugin_information') return $result;
        if (!isset($args->slug) || $args->slug !== 'linked3') return $result;

        $latest = get_option(LINKED3_OPTION_PREFIX . 'latest_version', LINKED3_VERSION);
        $changelog = get_option(LINKED3_OPTION_PREFIX . 'update_changelog', '');

        $obj = new \stdClass();
        $obj->name = 'Linked3 AI';
        $obj->version = $latest;
        $obj->last_updated = gmdate('Y-m-d');
        $obj->sections = [
            'description' => '商业自进化 AI 引擎 — 多模型 AI、SEO、内容自动化、SaaS 计费。',
            'changelog' => $changelog ?: '更新日志见 linked3.com',
        ];
        return $obj;
    }
}
