<?php

declare(strict_types=1);
/**
 * IPAnonymizationAddon — extracted from AddonManager.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Addons

namespace Linked3\Classes\Addons;

if (!defined('ABSPATH')) exit;

final class IPAnonymizationAddon implements AddonInterface
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
