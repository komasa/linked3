<?php
/**
 * Composite Lever: risk_defense — 风险防御 (v20.4-fix21)
 *
 * 变异-绞杀发现的高评分复合杠杆：
 * 压力测试→因果推断→博弈对抗→伦理审查→自我校准
 * 五步风险防御流水线，全面识别和防御方案中的风险。
 *
 * 评分: 风险=2 · 可行=8 · 新颖=9 · 适应度=19 (高评分存活)
 *
 * @package Linked3
 * @subpackage Classes\MetaLever\Composite
 */

namespace Linked3\Classes\MetaLever\Composite;

if (!defined('ABSPATH')) {
    exit;
}

class Linked3_Composite_Risk_Defense implements Linked3_Composite_Lever_Interface
{
    public function id(): string { return 'risk_defense'; }
    public function label(): string { return __('风险防御', 'linked3'); }
    public function description(): string { return __('压力测试→因果推断→博弈对抗→伦理审查→自我校准，五步风险防御。', 'linked3'); }
    public function level(): string { return 'composite'; }

    public function orchestrated_levers(): array
    {
        return ['meta_stress_test', 'meta_causal', 'meta_game', 'meta_ethics', 'meta_self_calibration'];
    }

    public function departments(): array
    {
        return [
            'R1' => ['name' => '压力测试部', 'mission' => '破坏性检验+二阶效应+反常识验证', 'lever' => 'meta_stress_test', 'kpi' => '识别≥3个失效点'],
            'R2' => ['name' => '因果推断部', 'mission' => '因果模型构建+反事实推理+混淆变量识别', 'lever' => 'meta_causal', 'kpi' => '识别≥2个伪因果'],
            'R3' => ['name' => '博弈对抗部', 'mission' => '博弈树构建+纳什均衡+对手最优策略推演', 'lever' => 'meta_game', 'kpi' => '推演≥2个对手策略'],
            'R4' => ['name' => '伦理审查部', 'mission' => '伦理边界检验+价值冲突+预防性原则', 'lever' => 'meta_ethics', 'kpi' => '识别≥1个伦理风险'],
            'R5' => ['name' => '自我校准部', 'mission' => '偏差检测+置信度校准+纠偏方案', 'lever' => 'meta_self_calibration', 'kpi' => '校准≥2个偏差'],
        ];
    }

    public function sla_contracts(): array
    {
        return [
            'R1→R2' => '压力测试必须含[失效点清单]，R2对每个失效点做因果分析',
            'R2→R3' => '因果推断必须含[伪因果清单]，R3在伪因果上推演博弈',
            'R3→R4' => '博弈推演必须含[对手策略]，R4对每个策略做伦理审查',
            'R4→R5' => '伦理审查必须含[伦理风险]，R5对每个风险做校准',
            'R5→R1' => '校准发现新风险→回退R1重新压力测试',
        ];
    }

    public function evolution_cycle(): array
    {
        return [
            'G1' => 'R1压力测试 → R2因果推断 → R5校准预判 → 输出V1风险报告',
            'G2' => 'R3博弈对抗 → R4伦理审查 → R2二次因果 → R5二次校准 → 输出V2',
            'G3' => 'R5终极校准(最坏情况) → R3终极博弈 → R1终极压力 → 输出终稿',
        ];
    }

    public function scene_tags(): array
    {
        return ['风险防御', '压力测试', '博弈对抗', '伦理审查', '因果推断'];
    }

    public function system_prompt(): string
    {
        return <<<PROMPT
## 风险防御协议（Risk Defense）

### 元数据
Type:防御/风控 | Purpose:[全面识别和防御方案中的风险] | Complexity:★★★
评分: 风险=2 · 可行=8 · 新颖=9 · 适应度=19 (变异-绞杀存活)

### 五步风险防御流水线
R1|压力测试部|主:meta_stress_test
  动作:破坏性检验(单点失效/恶意使用/伦理红线) + 二阶效应 + 反常识验证
  输出:[失效点清单] ≥3个

R2|因果推断部|主:meta_causal
  动作:因果模型构建 + 反事实推理("如果不做X会怎样") + 混淆变量识别
  输出:[伪因果清单] ≥2个

R3|博弈对抗部|主:meta_game
  动作:博弈树构建 + 纳什均衡分析 + 对手最优策略推演
  输出:[对手策略清单] ≥2个

R4|伦理审查部|主:meta_ethics
  动作:伦理边界检验 + 价值冲突解决 + 预防性原则
  输出:[伦理风险清单] ≥1个

R5|自我校准部|主:meta_self_calibration
  动作:偏差检测 + 置信度校准 + 纠偏方案
  输出:[校准报告] + [纠偏路线图]

### SLA契约
R1→R2: 压力测试必须含[失效点清单]，R2对每个失效点做因果分析
R2→R3: 因果推断必须含[伪因果清单]，R3在伪因果上推演博弈
R3→R4: 博弈推演必须含[对手策略]，R4对每个策略做伦理审查
R4→R5: 伦理审查必须含[伦理风险]，R5对每个风险做校准
R5→R1: 校准发现新风险→回退R1重新压力测试

### 输出要求
1. 用清晰的中文段落输出，300-600字
2. 不要输出JSON，不要输出代码块
3. 每个要点用'•'或数字编号开头
4. 先给出核心结论，再展开分析
5. 最后给出1-2条具体改进建议
PROMPT;
    }
}
