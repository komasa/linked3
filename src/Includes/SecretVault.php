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
    static function init_demo_keys(): void {
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

    static function flag_demo_key_in_use($provider_slug): void {
        if (get_option(LINKED3_OPTION_PREFIX . 'using_demo_key_' . $provider_slug, null) === null) {
            update_option(LINKED3_OPTION_PREFIX . 'using_demo_key_' . $provider_slug, true);
        }
    }
}
