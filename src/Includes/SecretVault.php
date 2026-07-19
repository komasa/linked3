<?php

declare(strict_types=1);
namespace Linked3\Includes;
if (!defined('ABSPATH')) exit;
/**
 * Secret vault.
 *
 * @package    Linked3
 * @subpackage Linked3.Includes
 * @since      27.1.0
 */
final class SecretVault
{
    /**
     * Demo keys for trial/evaluation purposes only.
     *
     * SECURITY FIX v27.0.0 (P9): Previously the SiliconFlow demo key was
     * hardcoded in plaintext. It is now loaded from a constant
     * LINKED3_DEMO_SF_KEY (defined in wp-config.php) or the
     * LINKED3_SF_KEY environment variable. If neither is set, no demo key
     * is available and the user must provide their own key.
     *
     * To enable the demo key, add to wp-config.php:
     *   define('LINKED3_DEMO_SF_KEY', 'sk-your-demo-key-here');
     */
    const DEMO_KEYS = [];  // Populated at runtime via init_demo_keys()
    const CONSTANT_TEMPLATE = ['siliconflow' => 'LINKED3_SF_KEY'];

    /**
     * Runtime-populated demo keys (overrides the empty const above).
     *
     * @var array
     */
    private static $runtime_demo_keys = [];

    /**
     * Initialize demo keys from secure sources (constants/env).
     * Called once on first access.
     */
    private static function init_demo_keys()
    : void {
        if (!empty(self::$runtime_demo_keys)) {
            return;
        }
        // SiliconFlow demo key from wp-config.php constant or env var
        if (defined('LINKED3_DEMO_SF_KEY') && LINKED3_DEMO_SF_KEY) {
            self::$runtime_demo_keys['siliconflow'] = LINKED3_DEMO_SF_KEY;
        } else {
            $env_demo = getenv('LINKED3_DEMO_SF_KEY');
            if (!empty($env_demo)) {
                self::$runtime_demo_keys['siliconflow'] = $env_demo;
            }
        }
    }

    public static function resolve_keys($provider_slug)
    {
        if (isset(self::CONSTANT_TEMPLATE[$provider_slug]) && defined(self::CONSTANT_TEMPLATE[$provider_slug])) {
            return [constant(self::CONSTANT_TEMPLATE[$provider_slug])];
        }
        $saved = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        if (!empty($saved[$provider_slug])) {
            $keys = array_filter(array_map('trim', explode("\n", $saved[$provider_slug])));
            if (!empty($keys)) return $keys;
        }
        $env_key = 'LINKED3_' . strtoupper($provider_slug) . '_KEY';
        $env = getenv($env_key);
        if (!empty($env)) return [$env];
        self::init_demo_keys();
        if (isset(self::$runtime_demo_keys[$provider_slug])) {
            self::flag_demo_key_in_use($provider_slug);
            return [self::$runtime_demo_keys[$provider_slug]];
        }
        return [];
    }
    public static function is_using_demo_key($provider_slug) { return (bool) get_option(LINKED3_OPTION_PREFIX . 'using_demo_key_' . $provider_slug, false); }
    public static function store_key($provider_slug, $plaintext_key)
    : bool {
        $saved = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        if (!is_array($saved)) $saved = [];
        $saved[$provider_slug] = $plaintext_key;
        update_option(LINKED3_OPTION_PREFIX . 'provider_keys', $saved);
        if (isset(self::DEMO_KEYS[$provider_slug]) && $plaintext_key !== self::DEMO_KEYS[$provider_slug]) {
            update_option(LINKED3_OPTION_PREFIX . 'using_demo_key_' . $provider_slug, false);
        }
        return true;
    }
    private static function flag_demo_key_in_use($provider_slug)
    : void {
        if (get_option(LINKED3_OPTION_PREFIX . 'using_demo_key_' . $provider_slug, null) === null) {
            update_option(LINKED3_OPTION_PREFIX . 'using_demo_key_' . $provider_slug, true);
        }
    }
}
