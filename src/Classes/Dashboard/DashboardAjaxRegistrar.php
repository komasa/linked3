<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard;

if (!defined('ABSPATH')) exit;

/**
 * Adaptive Delegate Router — temporary bridge layer.
 *
 * PSR-4 refactoring legacy: 6 Dashboard*Actions classes have delegate
 * methods that reference Dashboard\DashboardAjaxRegistrar::ajax_X(),
 * but this class was never created. Method implementations actually
 * live in DashboardConfigAjax / DashboardMediaAjax / DashboardVideoAjax.
 *
 * This class uses the __callStatic magic method to provide adaptive
 * delegate routing, fixing all 28 breakpoint delegates in a single file
 * while logging calls for future cleanup decisions.
 *
 * @internal  Temporary bridge layer, not a public API. When all delegate
 *            methods have been migrated or deleted, this class should be
 *            deleted as well.
 * @since     27.3.5
 */
final class DashboardAjaxRegistrar
{
    /**
     * Method name → [ImplementationClass, method_name] mapping table.
     *
     * Format: 'ajax_method_name' => ['ImplementationClass', 'method_name']
     * A value of null means the method never had an implementation (ghost method).
     *
     * @var array<string, array{class-string, string}|null>
     */
    private static array $delegateMap = [
        // ── DashboardContentActions delegates ──
        'ajax_generate_outline'       => null,  // Ghost method: original never implemented
        'ajax_generate_section'       => null,  // Ghost method: original never implemented
        'ajax_generate_chart_prompts' => [DashboardMediaAjax::class, 'ajax_generate_chart_prompts'],

        // ── DashboardChartActions delegates ──
        'ajax_chart_outline'          => [DashboardMediaAjax::class, 'ajax_chart_outline'],
        'ajax_chart_segment'          => [DashboardMediaAjax::class, 'ajax_chart_segment'],

        // ── DashboardKeywordActions delegates ──
        'ajax_kw_save_library'        => [DashboardConfigAjax::class, 'ajax_kw_save_library'],
        'ajax_kw_cron_enable'         => [DashboardConfigAjax::class, 'ajax_kw_cron_enable'],
        'ajax_kw_cron_disable'        => [DashboardConfigAjax::class, 'ajax_kw_cron_disable'],
        'ajax_kw_cron_status'         => [DashboardConfigAjax::class, 'ajax_kw_cron_status'],

        // ── DashboardAIConfigActions delegates ──
        'ajax_sync_models'            => [DashboardConfigAjax::class, 'ajax_sync_models'],
        'ajax_save_ai_suffix'         => [DashboardConfigAjax::class, 'ajax_save_ai_suffix'],
        'ajax_save_advanced'          => [DashboardConfigAjax::class, 'ajax_save_advanced'],
        'ajax_save_custom_apis'       => [DashboardConfigAjax::class, 'ajax_save_custom_apis'],
        'ajax_save_provider_config'   => [DashboardConfigAjax::class, 'ajax_save_provider_config'],
        'ajax_save_seo_enhance'       => [DashboardConfigAjax::class, 'ajax_save_seo_enhance'],
        'ajax_save_image_settings'    => [DashboardConfigAjax::class, 'ajax_save_image_settings'],
        'ajax_test_image_station'     => [DashboardConfigAjax::class, 'ajax_test_image_station'],
        'ajax_sync_image_models'      => [DashboardConfigAjax::class, 'ajax_sync_image_models'],
        'ajax_save_geo'               => null,  // Ghost method: original never implemented
        'ajax_save_ai_search_keys'    => [DashboardConfigAjax::class, 'ajax_save_ai_search_keys'],
        'ajax_regen_llms_txt'         => [DashboardConfigAjax::class, 'ajax_regen_llms_txt'],

        // ── DashboardVideoActions delegates ──
        'ajax_video_generate_script'  => [DashboardMediaAjax::class, 'ajax_video_generate_script'],
        'ajax_video_outline'          => [DashboardMediaAjax::class, 'ajax_video_outline'],
        'ajax_video_segment'          => [DashboardMediaAjax::class, 'ajax_video_segment'],

        // ── DashboardDiagramActions delegates ──
        'ajax_diagram_generate'       => [DashboardMediaAjax::class, 'ajax_diagram_generate'],
        'ajax_diagram_validate'       => [DashboardMediaAjax::class, 'ajax_diagram_validate'],
        'ajax_diagram_types'          => [DashboardMediaAjax::class, 'ajax_diagram_types'],
        'ajax_diagram_generate_multi' => [DashboardMediaAjax::class, 'ajax_diagram_generate_multi'],
    ];

    /**
     * Adaptive delegate routing.
     *
     * @param string $name      The method name being called (e.g. 'ajax_sync_models').
     * @param array  $arguments Arguments (AJAX handlers typically take none).
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        // Log the call for future cleanup decisions.
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[DashboardAjaxRegistrar Router] %s called from %s',
                $name,
                wp_debug_backtrace_summary(__FILE__, 0, false)
            ));
        }

        $target = self::$delegateMap[$name] ?? null;

        // Ghost method: never had an implementation.
        if ($target === null) {
            wp_send_json_error([
                'message' => sprintf(
                    __('AJAX endpoint "%s" is not implemented. This is a known issue from PSR-4 migration.', 'linked3'),
                    $name
                ),
                'code' => 'ghost_method',
            ], 501);
        }

        [$class, $method] = $target;

        // Safety check: implementation class exists.
        if (!class_exists($class)) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Implementation class "%s" not found for endpoint "%s".', 'linked3'),
                    $class,
                    $name
                ),
                'code' => 'missing_implementation_class',
            ], 500);
        }

        // Safety check: method exists.
        if (!method_exists($class, $method)) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Method "%s::%s" not found.', 'linked3'),
                    $class,
                    $method
                ),
                'code' => 'missing_implementation_method',
            ], 500);
        }

        return $class::$method(...$arguments);
    }
}
