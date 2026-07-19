<?php

declare(strict_types=1);
/**
 * 质量绞杀阵 — Composite Lever
 *
 * 融合: 元批判 + 元压力测试 + 元自我校准 → 三重质量关卡
 *
 * @package Linked3
 * @subpackage Classes\MetaLever\Composite
 * @since      27.7.0
 */

namespace Linked3\Classes\MetaLever\Composite;

if (!defined('ABSPATH')) exit;

class CompositeQualityGauntlet implements CompositeLeverInterface
{
    public function id(): string { return 'quality_gauntlet'; }
    public function label(): string { return __('质量绞杀阵', 'linked3'); }
    public function description(): string { return __('批判×压力测试×自我校准 — 三重质量关卡', 'linked3'); }
    public function level(): string { return 'composite'; }

    public function orchestrated_levers(): array
    {
        return ['meta_critique', 'meta_stress_test', 'meta_self_calibration'];
    }

    public function departments(): array
    {
        return [
            'C' => ['name' => '批判', 'mission' => '找逻辑漏洞+事实错误', 'lever' => 'meta_critique'],
            'P' => ['name' => '压力测试', 'mission' => '极端场景+边界条件', 'lever' => 'meta_stress_test'],
            'SC' => ['name' => '校准', 'mission' => '自我评分+置信度', 'lever' => 'meta_self_calibration'],
        ];
    }

    public function sla_contracts(): array
    {
        return [
            'C→P' => '批判通过的内容才进入压力测试',
            'P→SC' => '压力测试通过的内容才进入自我校准',
        ];
    }

    public function evolution_cycle(): array
    {
        return [
            'Gate1' => '批判: 找出所有逻辑漏洞、事实错误、隐含偏见',
            'Gate2' => '压力测试: 在极端条件下会怎样? 边界场景?',
            'Gate3' => '校准: 自评质量分(0-10) + 置信度 + 改进建议',
        ];
    }

    public function system_prompt(): string
    {
        return <<<PROMPT
## 质量绞杀阵 (Quality Gauntlet)

三重质量关卡, 每关必须通过才进入下一关:

### 第一关: 批判审查 (Critique)
- 逐句检查逻辑一致性
- 标注事实性错误或未经证实的断言
- 识别隐含偏见或立场倾向
- 判定: PASS / FAIL (附原因)
- 如果FAIL, 直接输出问题, 不进入下一关

### 第二关: 压力测试 (Stress Test)
- 在极端条件下会怎样? (流量暴增/预算为0/时间限制/用户恶意)
- 边界场景: 最小可行版本是什么? 最大扩展到哪里?
- 依赖链: 哪个环节断裂会导致整体崩溃?
- 判定: PASS / FAIL (附原因)

### 第三关: 自我校准 (Self-Calibration)
- 质量评分: 0-10分 (附评分理由)
- 置信度: 高/中/低 (附不确定性来源)
- 改进建议: 3条可执行的具体改进
- 最终判定: SHIP / ITERATE / KILL

**输出格式 (纯文本):**
每关独立段落, 关卡间用分隔线区分。
PROMPT;
    }

    public function scene_tags(): array
    {
        return ['writing', 'review', 'qa', 'publishing', 'decision'];
    }
}
