<?php

declare(strict_types=1);
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
class OSAliasRegistry
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

// ─── Alias registrations ────────────────────────────────────────────────────
// Core concept classes
linked3_os_register_alias('Linked3_Hong_Liu_Flywheel',          'OSMomentumFlywheel');
linked3_os_register_alias('Linked3_Ru_Liu_Tracker',             'OSOnboardingTracker');
linked3_os_register_alias('Linked3_Neng_Suo_Structure',         'OSCapabilityLock');
linked3_os_register_alias('Linked3_Neng_Zhi_Three_Stages',      'OSCapabilityStages');
linked3_os_register_alias('Linked3_Reverse_Dimensions',         'OSReverseDimensions');
linked3_os_register_alias('Linked3_Reverse_Engine',             'OSReverseEngine');
linked3_os_register_alias('Linked3_Reverse_Engineer_Registry',  'OSEngineerRegistry');
linked3_os_register_alias('Linked3_Reverse_Quality_Gate',       'OSQualityGate');
linked3_os_register_alias('Linked3_Reverse_Text_Creation',      'OSTextCreation');
linked3_os_register_alias('Linked3_Svg_Meta_Stats',             'OSVisualAnalytics');
linked3_os_register_alias('Linked3_Three_Layer_Consciousness',  'OSConsciousnessLayer');

// Ajax classes
linked3_os_register_alias('Linked3_Consciousness_Ajax',         'OSConsciousnessAjax');
linked3_os_register_alias('Linked3_Engineer_Registry_Ajax',     'OSEngineerRegistryAjax');
linked3_os_register_alias('Linked3_Hong_Liu_Ajax',              'OSMomentumAjax');
linked3_os_register_alias('Linked3_Neng_Suo_Ajax',              'OSCapabilityLockAjax');
linked3_os_register_alias('Linked3_Neng_Zhi_Ajax',              'OSCapabilityStagesAjax');
linked3_os_register_alias('Linked3_Quality_Gate_Ajax',          'OSQualityGateAjax');
linked3_os_register_alias('Linked3_Reverse_Ajax',               'OSReverseAjax');
linked3_os_register_alias('Linked3_Reverse_Text_Ajax',          'OSTextCreationAjax');
linked3_os_register_alias('Linked3_Ru_Liu_Ajax',                'OSOnboardingAjax');
linked3_os_register_alias('Linked3_Svg_Stats_Ajax',             'OSVisualAnalyticsAjax');

// Admin classes
linked3_os_register_alias('V18_Dashboard',              'OSDashboard');
linked3_os_register_alias('V18_Reverse_Panel',          'OSReversePanel');
linked3_os_register_alias('V18_Ruliu_Panel',            'OSOnboardingPanel');
linked3_os_register_alias('V18_Svg_Stats_Panel',        'OSVisualAnalyticsPanel');

// API classes
linked3_os_register_alias('V18_Cli',                    'OSCli');
linked3_os_register_alias('V18_Db_Schema',              'OSDbSchema');
linked3_os_register_alias('V18_Integration_Hub',        'OSIntegrationHub');
linked3_os_register_alias('V18_Integration_Hub_V2',     'OSIntegrationHubV2');
linked3_os_register_alias('V18_Rest_Api',               'OSRestApi');
linked3_os_register_alias('V18_Shortcodes',             'OSShortcodes');
linked3_os_register_alias('V18_Widget',                 'OSWidget');

// Facade & bridge
linked3_os_register_alias('V18_Facade',                 'Linked3_OS_Facade');
linked3_os_register_alias('V18_Genesis_Bridge',         'OSGenesisBridge');
linked3_os_register_alias('V18_Dependencies_Loader',    'OSDependenciesLoader');
