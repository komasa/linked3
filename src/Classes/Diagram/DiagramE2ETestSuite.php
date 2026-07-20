<?php

declare(strict_types=1);
/**
 * DiagramE2ETestSuite — extracted from Diagram30Spectrum.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramE2ETestSuite {
    public function runAll(): array {
        $tests = [];

        // 测试1: 主模板生成
        $tests['master_template'] = $this->testMasterTemplate();

        // 测试2: 16种图示类型注册
        $tests['type_registry'] = $this->testTypeRegistry();

        // 测试3: 30种全谱
        $tests['spectrum_30'] = $this->testSpectrum30();

        // 测试4: 6种Endpoint
        $tests['endpoint_6'] = $this->testEndpoint6();

        // 测试5: 13维校验
        $tests['validation_13dim'] = $this->testValidation13Dim();

        // 测试6: 三层编译器
        $tests['prompt_compiler'] = $this->testPromptCompiler();

        // 测试7: Seed系统
        $tests['seed_system'] = $this->testSeedSystem();

        // 测试8: 商业加固
        $tests['commercial_hardening'] = $this->testCommercialHardening();

        $passed = count(array_filter($tests, fn($t) => $t['passed']));
        return [
            'total' => count($tests),
            'passed' => $passed,
            'pass_rate' => round($passed / count($tests) * 100, 1) . '%',
            'tests' => $tests,
        ];
    }

    private function testMasterTemplate(): array {
        $ok = class_exists('\Linked3\Classes\Diagram\DiagramMasterTemplate');
        return ['passed' => $ok, 'msg' => $ok ? 'OK' : '主模板缺失'];
    }
    private function testTypeRegistry(): array {
        $count = DiagramTypeRegistry::instance()->getStats()['total'] ?? 0;
        return ['passed' => $count === 16, 'msg' => "16种图示: {$count}/16"];
    }
    private function testSpectrum30(): array {
        $count = Diagram30Spectrum::instance()->count();
        return ['passed' => $count === 30, 'msg' => "30种全谱: {$count}/30"];
    }
    private function testEndpoint6(): array {
        $count = count(DiagramEndpointRegistry::instance()->all());
        return ['passed' => $count === 6, 'msg' => "6种Endpoint: {$count}/6"];
    }
    private function testValidation13Dim(): array {
        $ok = class_exists('\Linked3\Classes\Diagram\DiagramValidation13Dim');
        return ['passed' => $ok, 'msg' => $ok ? 'OK' : '13维校验缺失'];
    }
    private function testPromptCompiler(): array {
        $ok = class_exists('\Linked3\Classes\Diagram\DiagramPromptCompiler');
        return ['passed' => $ok, 'msg' => $ok ? 'OK' : '编译器缺失'];
    }
    private function testSeedSystem(): array {
        $ok = class_exists('\Linked3\Classes\Diagram\DiagramCharacterSeedManager') && class_exists('\Linked3\Classes\Diagram\DiagramProductSeedManager');
        return ['passed' => $ok, 'msg' => $ok ? 'OK' : 'Seed系统缺失'];
    }
    private function testCommercialHardening(): array {
        $result = (new DiagramCommercialHardening())->harden();
        return ['passed' => $result['hardened'], 'msg' => "加固: {$result['passed']}/{$result['total']}"];
    }
}

// =================================================================
// v6.5.0.9: 生产级启动器
// =================================================================
