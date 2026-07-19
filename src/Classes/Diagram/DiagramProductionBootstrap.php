<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Production_Bootstrap — extracted from Diagram30Spectrum.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Linked3_Diagram_Production_Bootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        // 确保 Diagram_Bootstrap 已启动
        if (class_exists('\Linked3\Classes\Diagram\Linked3_Diagram_Bootstrap')) {
            Linked3_Diagram_Bootstrap::boot();
        }

        $container = linked3_container();

        // v6.5.0: 30种全谱 + 商业加固
        $container->set('diagram.spectrum_30', fn() => Diagram30Spectrum::instance());
        $container->set('diagram.base_reuse', fn() => new Linked3_Diagram_BaseReuse_Flywheel());
        $container->set('diagram.3d_ar', fn() => new Linked3_Diagram_3D_AR_Subsystem());
        $container->set('diagram.visual_script_transform', fn() => new Linked3_Diagram_VisualScript_Transform());
        $container->set('diagram.brand_5d', fn() => new Linked3_Diagram_BrandSystem_5D());
        $container->set('diagram.cross_ref_8system', fn() => new Linked3_Diagram_8System_CrossRef());
        $container->set('diagram.commercial_hardening', fn() => new Linked3_Diagram_Commercial_Hardening());
        $container->set('diagram.e2e_test', fn() => new Linked3_Diagram_E2E_TestSuite());

        // E2E 测试
        $testResult = (new Linked3_Diagram_E2E_TestSuite())->runAll();
        if ($container->has('logger')) {
            $container->get('logger')->info('Diagram E2E test result', $testResult);
        }

        linked3_dispatch('linked3.diagram.production.ready', [
            'version' => LINKED3_VERSION,
            'e2e_pass_rate' => $testResult['pass_rate'],
            'spectrum_count' => Diagram30Spectrum::instance()->count(),
        ]);
    }
}
