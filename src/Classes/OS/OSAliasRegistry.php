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
    public static function add($old_class, $new_class, $since): void
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
    public static function autoload($class): void
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
// Bug fix: linked3_os_register_alias() was called but never defined, causing
// fatal error on load. Replaced with direct OSAliasRegistry::add() calls.
// Bug fix: target class names now use full FQCN (was bare names that could
// not resolve via PSR-4 autoloader).
//
// Core concept classes (namespace: Linked3\Classes\OS\Core)
OSAliasRegistry::add('Linked3_Hong_Liu_Flywheel',          'Linked3\Classes\OS\Core\OSMomentumFlywheel',         '27.0.0');
OSAliasRegistry::add('Linked3_Ru_Liu_Tracker',             'Linked3\Classes\OS\Core\OSOnboardingTracker',        '27.0.0');
OSAliasRegistry::add('Linked3_Neng_Suo_Structure',         'Linked3\Classes\OS\Core\OSCapabilityLock',           '27.0.0');
OSAliasRegistry::add('Linked3_Neng_Zhi_Three_Stages',      'Linked3\Classes\OS\Core\OSCapabilityStages',         '27.0.0');
OSAliasRegistry::add('Linked3_Reverse_Dimensions',         'Linked3\Classes\OS\Core\OSReverseDimensions',        '27.0.0');
OSAliasRegistry::add('Linked3_Reverse_Engine',             'Linked3\Classes\OS\Core\OSReverseEngine',            '27.0.0');
OSAliasRegistry::add('Linked3_Reverse_Engineer_Registry',  'Linked3\Classes\OS\Core\OSEngineerRegistry',         '27.0.0');
OSAliasRegistry::add('Linked3_Reverse_Quality_Gate',       'Linked3\Classes\OS\Core\OSQualityGate',              '27.0.0');
OSAliasRegistry::add('Linked3_Reverse_Text_Creation',      'Linked3\Classes\OS\Core\OSTextCreation',             '27.0.0');
OSAliasRegistry::add('Linked3_Svg_Meta_Stats',             'Linked3\Classes\OS\Core\OSVisualAnalytics',          '27.0.0');
OSAliasRegistry::add('Linked3_Three_Layer_Consciousness',  'Linked3\Classes\OS\Core\OSConsciousnessLayer',       '27.0.0');

// Ajax classes (namespace: Linked3\Classes\OS\Ajax)
OSAliasRegistry::add('Linked3_Consciousness_Ajax',         'Linked3\Classes\OS\Ajax\OSConsciousnessAjax',        '27.0.0');
OSAliasRegistry::add('Linked3_Engineer_Registry_Ajax',     'Linked3\Classes\OS\Ajax\OSEngineerRegistryAjax',     '27.0.0');
OSAliasRegistry::add('Linked3_Hong_Liu_Ajax',              'Linked3\Classes\OS\Ajax\OSMomentumAjax',             '27.0.0');
OSAliasRegistry::add('Linked3_Neng_Suo_Ajax',              'Linked3\Classes\OS\Ajax\OSCapabilityLockAjax',       '27.0.0');
OSAliasRegistry::add('Linked3_Neng_Zhi_Ajax',              'Linked3\Classes\OS\Ajax\OSCapabilityStagesAjax',     '27.0.0');
OSAliasRegistry::add('Linked3_Quality_Gate_Ajax',          'Linked3\Classes\OS\Ajax\OSQualityGateAjax',          '27.0.0');
OSAliasRegistry::add('Linked3_Reverse_Ajax',               'Linked3\Classes\OS\Ajax\OSReverseAjax',              '27.0.0');
OSAliasRegistry::add('Linked3_Reverse_Text_Ajax',          'Linked3\Classes\OS\Ajax\OSTextCreationAjax',         '27.0.0');
OSAliasRegistry::add('Linked3_Ru_Liu_Ajax',                'Linked3\Classes\OS\Ajax\OSOnboardingAjax',           '27.0.0');
OSAliasRegistry::add('Linked3_Svg_Stats_Ajax',             'Linked3\Classes\OS\Ajax\OSVisualAnalyticsAjax',      '27.0.0');

// Admin classes (namespace: Linked3\Classes\OS\Admin)
OSAliasRegistry::add('V18_Dashboard',              'Linked3\Classes\OS\Admin\OSDashboard',              '27.0.0');
OSAliasRegistry::add('V18_Reverse_Panel',          'Linked3\Classes\OS\Admin\OSReversePanel',           '27.0.0');
OSAliasRegistry::add('V18_Ruliu_Panel',            'Linked3\Classes\OS\Admin\OSOnboardingPanel',        '27.0.0');
OSAliasRegistry::add('V18_Svg_Stats_Panel',        'Linked3\Classes\OS\Admin\OSVisualAnalyticsPanel',   '27.0.0');

// API classes (namespace: Linked3\Classes\OS\Api)
OSAliasRegistry::add('V18_Cli',                    'Linked3\Classes\OS\Api\OSCli',                       '27.0.0');
OSAliasRegistry::add('V18_Db_Schema',              'Linked3\Classes\OS\Api\OSDbSchema',                 '27.0.0');
OSAliasRegistry::add('V18_Integration_Hub',        'Linked3\Classes\OS\Api\OSIntegrationHub',           '27.0.0');
OSAliasRegistry::add('V18_Integration_Hub_V2',     'Linked3\Classes\OS\Api\OSIntegrationHubV2',         '27.0.0');
OSAliasRegistry::add('V18_Rest_Api',               'Linked3\Classes\OS\Api\OSRestApi',                  '27.0.0');
OSAliasRegistry::add('V18_Shortcodes',             'Linked3\Classes\OS\Api\OSShortcodes',               '27.0.0');
OSAliasRegistry::add('V18_Widget',                 'Linked3\Classes\OS\Api\OSWidget',                   '27.0.0');

// Facade & bridge (namespace: Linked3\Classes\OS)
// Note: V18_Facade previously mapped to Linked3_OS_Facade which never existed
// as a class. The actual facade class is V18 (in Linked3\Classes\OS\V18).
OSAliasRegistry::add('V18_Facade',                 'Linked3\Classes\OS\V18',                            '27.0.0');
OSAliasRegistry::add('V18_Genesis_Bridge',         'Linked3\Classes\OS\OSGenesisBridge',                '27.0.0');
OSAliasRegistry::add('V18_Dependencies_Loader',    'Linked3\Classes\OS\OSDependenciesLoader',           '27.0.0');
