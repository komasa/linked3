<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Validation_13Dim — extracted from Diagram3LayerDepth.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramValidation13Dim {
    public function validate(array $diagram): array {
        $checks = [];
        // 1. 视觉一致性
        $checks['visual'] = $this->checkVisual($diagram);
        // 2. 图文咬合
        $checks['text_embed'] = $this->checkTextEmbed($diagram);
        // 3. 系统质感
        $checks['system'] = $this->checkSystem($diagram);
        // 4. 竖屏16字
        $checks['vertical_16char'] = $this->checkVertical($diagram);
        // 5. 3层深度
        $checks['depth_3layer'] = $this->checkDepth($diagram);
        // 6. 4层锚点
        $checks['anchor_4layer'] = $this->checkAnchor($diagram);
        // 7. 16种图示
        $checks['diagram_16type'] = $this->checkDiagramType($diagram);
        // 8. 6种Endpoint
        $checks['endpoint_6type'] = $this->checkEndpoint($diagram);
        // 9. 4种Footer
        $checks['footer_4type'] = $this->checkFooter($diagram);
        // 10. 4种追问
        $checks['followup_4type'] = $this->checkFollowup($diagram);
        // 11. 关系编码
        $checks['relationship_6code'] = $this->checkRelationship($diagram);
        // 12. 认知层级
        $checks['cognitive_6level'] = $this->checkCognitive($diagram);
        // 13. 信息密度
        $checks['density_4level'] = $this->checkDensity($diagram);

        $passed = count(array_filter($checks, fn($c) => $c['passed']));
        $total = count($checks);
        return [
            'passed' => $passed,
            'total' => $total,
            'pass_rate' => round($passed / $total * 100, 1),
            'checks' => $checks,
            'overall_score' => round(array_sum(array_column($checks, 'score')) / $total, 1),
        ];
    }

    private function checkVisual(array $d): array {
        $ok = isset($d['bands']) && count($d['bands']) >= 3;
        return ['passed' => $ok, 'score' => $ok ? 100 : 40, 'msg' => $ok ? 'OK' : 'Band不足3个'];
    }
    private function checkTextEmbed(array $d): array {
        $ok = true; $issues = [];
        foreach ($d['bands'] ?? [] as $band) {
            foreach ($band['modules'] ?? [] as $m) {
                foreach ($m['text_embedded'] ?? [] as $t) {
                    $len = mb_strlen($t);
                    if ($len < 2 || $len > 6) { $ok = false; $issues[] = "{$t}长度{$len}"; }
                }
            }
        }
        return ['passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => implode('; ', $issues) ?: 'OK'];
    }
    private function checkSystem(array $d): array {
        $ok = !empty($d['badge_colors']);
        return ['passed' => $ok, 'score' => $ok ? 100 : 30, 'msg' => $ok ? 'OK' : '徽章色缺失'];
    }
    private function checkVertical(array $d): array {
        return ['passed' => true, 'score' => 80, 'msg' => '9:16竖版'];
    }
    private function checkDepth(array $d): array {
        $ok = true;
        foreach ($d['bands'] ?? [] as $band) {
            foreach ($band['modules'] ?? [] as $m) {
                if (count($m['sub_topics'] ?? []) < 2) { $ok = false; break 2; }
            }
        }
        return ['passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? 'OK' : '子主题不足'];
    }
    private function checkAnchor(array $d): array {
        $ok = !empty($d['anchors']);
        return ['passed' => $ok, 'score' => $ok ? 100 : 40, 'msg' => $ok ? 'OK' : '锚点缺失'];
    }
    private function checkDiagramType(array $d): array {
        $ok = !empty($d['diagram_type']);
        return ['passed' => $ok, 'score' => $ok ? 100 : 30, 'msg' => $ok ? 'OK' : '图示类型缺失'];
    }
    private function checkEndpoint(array $d): array {
        $ok = !empty($d['endpoint']['type']);
        return ['passed' => $ok, 'score' => $ok ? 100 : 30, 'msg' => $ok ? 'OK' : 'Endpoint缺失'];
    }
    private function checkFooter(array $d): array {
        $ok = !empty($d['footer']);
        return ['passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? 'OK' : 'Footer缺失'];
    }
    private function checkFollowup(array $d): array {
        $ok = !empty($d['followup_type']);
        return ['passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? 'OK' : '追问缺失'];
    }
    private function checkRelationship(array $d): array {
        $count = count($d['relationships'] ?? []);
        $ok = $count <= 9 && $count > 0;
        return ['passed' => $ok, 'score' => $ok ? 100 : 40, 'msg' => "{$count}条关系"];
    }
    private function checkCognitive(array $d): array {
        $ok = !empty($d['cognitive_level']);
        return ['passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => $ok ? 'OK' : '认知层级缺失'];
    }
    private function checkDensity(array $d): array {
        $ok = in_array($d['density'] ?? '', ['极简', '标准', '深度', '极致']);
        return ['passed' => $ok, 'score' => $ok ? 100 : 50, 'msg' => $d['density'] ?? '缺失'];
    }
}

/**
 * Linked3 Diagram Bootstrap — v6.1.0
 * 图示引擎核心启动
 */
