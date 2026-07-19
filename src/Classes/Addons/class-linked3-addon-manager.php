<?php
/**
 * Addon system — modular extensions with IsRequired/IsActive/Executor pattern.
 *
 * Built-in addons:
 *   - consent-compliance: GDPR/CCPA cookie consent
 *   - ip-anonymization:  GDPR IP anonymization
 *   - ollama:            local model provider
 *
 * @package Linked3
 * @subpackage Classes\Addons
 */

namespace Linked3\Classes\Addons;

if (!defined('ABSPATH')) {
    exit;
}

interface Linked3_Addon_Interface
{
    /** @return string slug */
    public function slug() : string;
    /** @return bool required (cannot be disabled) */
    public function is_required() : bool;
    /** @return bool active */
    public function is_active() : bool;
    /** @return void execute/initialise */
    public function execute() : void;
}

final class Linked3_Addon_Manager
{
    private static $instance;
    private $addons = [];

    public static function instance() : static
    {
        if (null === self::$instance) {
            // v4.4.6: delegate to the DI container when available.
            if (class_exists('\\Linked3\\Includes\\Linked3_Container')) {
                $container = \Linked3\Includes\Linked3_Container::instance();
                if ($container->has(self::class)) {
                    self::$instance = $container->get(self::class);
                    return self::$instance;
                }
            }
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construct the singleton WITHOUT going through the container.
     *
     * v4.4.6: used by the container's factory to avoid infinite recursion.
     *
     * @return self
     * @internal
     */
    public static function instance_without_container() : static
    {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public function register(Linked3_Addon_Interface $addon) : void
    {
        $this->addons[$addon->slug()] = $addon;
    }

    public function init_all() : void
    {
        foreach ($this->addons as $slug => $addon) {
            if ($addon->is_active() || $addon->is_required()) {
                try {
                    $addon->execute();
                } catch (\Throwable $e) {
                    if (class_exists('\\Linked3\\Includes\\Log\\Linked3_Logger')) {
                        \Linked3\Includes\Log\Linked3_Logger::instance()->error('general', "Addon {$slug} failed: " . $e->getMessage());
                    }
                }
            }
        }
    }

    public function all() : array
    {
        $out = [];
        foreach ($this->addons as $slug => $a) {
            $out[] = ['slug' => $slug, 'required' => $a->is_required(), 'active' => $a->is_active()];
        }
        return $out;
    }
}

// Built-in: IP Anonymization (GDPR).
final class Linked3_IP_Anonymization_Addon implements Linked3_Addon_Interface
{
    public function slug() : string { return 'ip-anonymization'; }
    public function is_required() : bool { return false; }
    public function is_active() : bool { return (bool) get_option(LINKED3_OPTION_PREFIX . 'addon_ip_anon', true); }
    public function execute() : void
    {
        add_filter('linked3/log_ip', static function ($ip) {
            // Zero out last octet for IPv4, last 80 bits for IPv6.
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return preg_replace('/\.\d+$/', '.0', $ip);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return preg_replace('/:[0-9a-f]{1,4}:[0-9a-f]{1,4}:[0-9a-f]{1,4}:[0-9a-f]{1,4}$/', '::', $ip);
            }
            return $ip;
        });
    }
}

// Built-in: Consent Compliance (GDPR/CCPA cookie banner).
final class Linked3_Consent_Compliance_Addon implements Linked3_Addon_Interface
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
