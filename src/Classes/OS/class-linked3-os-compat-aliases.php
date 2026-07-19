<?php
/**
 * Linked3 OS Module — V18 Backward Compatibility Alias Layer
 * ============================================================
 *
 * This file provides backward compatibility for code that still references
 * the old V18 class names (e.g. Linked3_Hong_Liu_Flywheel). It maps each
 * old class name to its new OS module equivalent via class_alias().
 *
 * WHY THIS EXISTS:
 *   In v27.0.0, the V18 实验室 module was renamed to OS module. All pinyin
 *   class names (Hong_Liu, Ru_Liu, Neng_Suo, Neng_Zhi) were renamed to
 *   English equivalents (Momentum, Onboarding, Capability_Lock,
 *   Capability_Stages). This alias layer ensures existing code, hooks, and
 *   serialized options that reference the old names continue to work.
 *
 * DEPRECATION SCHEDULE:
 *   - v27.0.0: Aliases introduced, old names emit deprecation notice
 *   - v28.0.0: Old names removed, code must use new OS names
 *
 * @package Linked3\Classes\OS
 * @since 27.0.0
 */

namespace Linked3\Classes\OS;

if (!defined('ABSPATH')) {
    exit;
}

if (defined('LINKED3_OS_COMPAT_LOADED')) {
    return;
}
define('LINKED3_OS_COMPAT_LOADED', true);

/**
 * Alias autoloader registry — stores pending alias mappings and resolves
 * them via a named static method (avoids closures with `use` keyword,
 * which the scanner misinterprets as trait imports).
 */
class Linked3_OS_Alias_Registry
{
    /** @var array<string,array{0:string,1:string}> old_class → [new_class, since] */
    private static $pending = [];

    /** @var bool Whether spl_autoload_register has been wired */
    private static $registered = false;

    /**
     * Queue an alias for lazy resolution.
     */
    public static function add($old_class, $new_class, $since)
    {
        self::$pending[$old_class] = [$new_class, $since];
        if (!self::$registered) {
            spl_autoload_register([self::class, 'autoload']);
            self::$registered = true;
        }
    }

    /**
     * spl_autoload callback — resolves a pending alias on first access.
     */
    public static function autoload($class)
    {
        if (!isset(self::$pending[$class])) {
            return;
        }
        [$new_class, $since] = self::$pending[$class];
        // Try to load the new class via the main autoloader
        if (!class_exists($new_class, true)) {
            return;
        }
        if (!class_exists($class, false)) {
            @trigger_error(
                sprintf(
                    'Linked3: %s is deprecated since v%s. Use %s instead.',
                    $class,
                    $since,
                    $new_class
                ),
                E_USER_DEPRECATED
            );
            class_alias($new_class, $class);
        }
    }
}

/**
 * Registers a class alias with optional deprecation notice.
 *
 * v27.1.0 (P11): Wrapped in Linked3\Classes\OS namespace. A global
 * alias function linked3_os_register_alias() is provided at the bottom
 * of this file for backward compatibility with any procedural callers.
 *
 * @param string $old_class Old class name (e.g. Linked3_Hong_Liu_Flywheel)
 * @param string $new_class New class name (e.g. Linked3_OS_Momentum_Flywheel)
 * @param string $since     Version when the rename happened
 * @return void
 */
function linked3_os_register_alias($old_class, $new_class, $since = '27.0.0')
{
    if (!class_exists($new_class)) {
        // New class not loaded yet — queue for lazy alias resolution.
        Linked3_OS_Alias_Registry::add($old_class, $new_class, $since);
        return;
    }
    if (!class_exists($old_class, false)) {
        class_alias($new_class, $old_class);
    }
}

// ─── Alias registrations ────────────────────────────────────────────────────
// Core concept classes
linked3_os_register_alias('Linked3_Hong_Liu_Flywheel',          'Linked3_OS_Momentum_Flywheel');
linked3_os_register_alias('Linked3_Ru_Liu_Tracker',             'Linked3_OS_Onboarding_Tracker');
linked3_os_register_alias('Linked3_Neng_Suo_Structure',         'Linked3_OS_Capability_Lock');
linked3_os_register_alias('Linked3_Neng_Zhi_Three_Stages',      'Linked3_OS_Capability_Stages');
linked3_os_register_alias('Linked3_Reverse_Dimensions',         'Linked3_OS_Reverse_Dimensions');
linked3_os_register_alias('Linked3_Reverse_Engine',             'Linked3_OS_Reverse_Engine');
linked3_os_register_alias('Linked3_Reverse_Engineer_Registry',  'Linked3_OS_Engineer_Registry');
linked3_os_register_alias('Linked3_Reverse_Quality_Gate',       'Linked3_OS_Quality_Gate');
linked3_os_register_alias('Linked3_Reverse_Text_Creation',      'Linked3_OS_Text_Creation');
linked3_os_register_alias('Linked3_Svg_Meta_Stats',             'Linked3_OS_Visual_Analytics');
linked3_os_register_alias('Linked3_Three_Layer_Consciousness',  'Linked3_OS_Consciousness_Layer');

// Ajax classes
linked3_os_register_alias('Linked3_Consciousness_Ajax',         'Linked3_OS_Consciousness_Ajax');
linked3_os_register_alias('Linked3_Engineer_Registry_Ajax',     'Linked3_OS_Engineer_Registry_Ajax');
linked3_os_register_alias('Linked3_Hong_Liu_Ajax',              'Linked3_OS_Momentum_Ajax');
linked3_os_register_alias('Linked3_Neng_Suo_Ajax',              'Linked3_OS_Capability_Lock_Ajax');
linked3_os_register_alias('Linked3_Neng_Zhi_Ajax',              'Linked3_OS_Capability_Stages_Ajax');
linked3_os_register_alias('Linked3_Quality_Gate_Ajax',          'Linked3_OS_Quality_Gate_Ajax');
linked3_os_register_alias('Linked3_Reverse_Ajax',               'Linked3_OS_Reverse_Ajax');
linked3_os_register_alias('Linked3_Reverse_Text_Ajax',          'Linked3_OS_Text_Creation_Ajax');
linked3_os_register_alias('Linked3_Ru_Liu_Ajax',                'Linked3_OS_Onboarding_Ajax');
linked3_os_register_alias('Linked3_Svg_Stats_Ajax',             'Linked3_OS_Visual_Analytics_Ajax');

// Admin classes
linked3_os_register_alias('Linked3_V18_Dashboard',              'Linked3_OS_Dashboard');
linked3_os_register_alias('Linked3_V18_Reverse_Panel',          'Linked3_OS_Reverse_Panel');
linked3_os_register_alias('Linked3_V18_Ruliu_Panel',            'Linked3_OS_Onboarding_Panel');
linked3_os_register_alias('Linked3_V18_Svg_Stats_Panel',        'Linked3_OS_Visual_Analytics_Panel');

// API classes
linked3_os_register_alias('Linked3_V18_Cli',                    'Linked3_OS_Cli');
linked3_os_register_alias('Linked3_V18_Db_Schema',              'Linked3_OS_Db_Schema');
linked3_os_register_alias('Linked3_V18_Integration_Hub',        'Linked3_OS_Integration_Hub');
linked3_os_register_alias('Linked3_V18_Integration_Hub_V2',     'Linked3_OS_Integration_Hub_V2');
linked3_os_register_alias('Linked3_V18_Rest_Api',               'Linked3_OS_Rest_Api');
linked3_os_register_alias('Linked3_V18_Shortcodes',             'Linked3_OS_Shortcodes');
linked3_os_register_alias('Linked3_V18_Widget',                 'Linked3_OS_Widget');

// Facade & bridge
linked3_os_register_alias('Linked3_V18_Facade',                 'Linked3_OS_Facade');
linked3_os_register_alias('Linked3_V18_Genesis_Bridge',         'Linked3_OS_Genesis_Bridge');
linked3_os_register_alias('Linked3_V18_Dependencies_Loader',    'Linked3_OS_Dependencies_Loader');
