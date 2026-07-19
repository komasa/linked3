<?php
/**
 * Composite Lever: deep_strategy — 深度谋划 (v20.4-fix17)
 *
 * 战略洞察→系统设计→资源编排→执行暗线→效果监测
 * 五层递进式深度谋划，适用于商业策略、竞争分析、长期规划。
 *
 * @package Linked3
 * @subpackage Classes\MetaLever\Composite
 */

namespace Linked3\Classes\MetaLever\Composite;

if (!defined('ABSPATH')) {
    exit;
}

class Linked3_Composite_Deep_Strategy implements Linked3_Composite_Lever_Interface
{
    public function id(): string { return 'deep_strategy'; }
    public function label(): string { return __('深度谋划', 'linked3'); }
    public function description(): string { return __('战略洞察→系统设计→资源编排→执行暗线→效果监测，五层递进式深度谋划。', 'linked3'); }
    public function level(): string { return 'composite'; }

    public function orchestrated_levers(): array
    {
        return ['meta_strategy', 'meta_system', 'meta_reverse', 'meta_dynamics', 'meta_stress_test'];
    }

    public function departments(): array
    {
        return [
            'L1' => ['name' => '战略洞察层', 'mission' => '格局扫描+矛盾挖掘，绘制三轴雷达图', 'lever' => 'meta_strategy', 'kpi' => '识别≥3个隐性矛盾'],
            'L2' => ['name' => '系统设计层', 'mission' => '动态建模+博弈推演，预埋可逆决策点', 'lever' => 'meta_system', 'kpi' => '模型含≥2个反馈环'],
            'L3' => ['name' => '资源编排层', 'mission' => '杠杆组合+链式激活，非常规资源调配', 'lever' => 'meta_reverse', 'kpi' => '识别≥1个认知剩余资源'],
            'L4' => ['name' => '执行暗线层', 'mission' => '明暗双轨+节奏控制，脉冲式推进', 'lever' => 'meta_dynamics', 'kpi' => '定义≥3个节奏节点'],
            'L5' => ['name' => '效果监测层', 'mission' => '非显性指标+动态校准，混沌接种', 'lever' => 'meta_stress_test', 'kpi' => '定义≥2个深层信号'],
        ];
    }

    public function sla_contracts(): array
    {
        return [
            'L1→L2' => '洞察必须含[矛盾清单]，L2在矛盾上建模',
            'L2→L3' => '模型必须含[杠杆点]，L3在杠杆点上编排资源',
            'L3→L4' => '资源编排必须含[引爆序列]，L4按序列控制节奏',
            'L4→L5' => '执行必须含[脉冲节点]，L5在节点上监测',
            'L5→L1' => '监测发现偏差→回退L1重新扫描格局',
        ];
    }

    public function evolution_cycle(): array
    {
        return [
            'G1' => 'L1格局扫描 → L2动态建模 → L5效果预判 → 输出V1战略草案',
            'G2' => 'L3资源编排 → L4执行暗线 → L5压力测试 → L1矛盾修正 → 输出V2',
            'G3' => 'L5终极检验(敌方智库伏击测试) → L2模型收敛 → A部打包终稿',
        ];
    }

    public function scene_tags(): array
    {
        return ['商业策略', '竞争分析', '长期规划', '博弈推演', '资源编排'];
    }

    public function system_prompt(): string
    {
        return <<<PROMPT
## 深度谋划协议（Deep Strategy）

### 元数据
Type:战略/谋划 | Purpose:[五层递进式深度谋划] | Complexity:★★★

### 五层递进架构
L1|战略洞察层|主:meta_strategy
  动作:格局扫描(时间轴/空间轴/能量轴) + 矛盾挖掘(显性vs隐性)
  输出:[三轴雷达图] + [矛盾清单]

L2|系统设计层|主:meta_system
  动作:动态建模(反馈环/奇点引爆器/冗余暗线) + 博弈推演(角色矩阵)
  输出:[系统模型] + [博弈树]

L3|资源编排层|主:meta_reverse
  动作:杠杆组合(认知剩余/时间套利/情绪能量) + 链式激活(种子→裂变→临界)
  输出:[资源调配公式] + [引爆序列]

L4|执行暗线层|主:meta_dynamics
  动作:明暗双轨(表层叙事/底层引擎) + 节奏控制(迷雾→闪电→静默)
  输出:[双轨执行计划] + [脉冲节奏图]

L5|效果监测层|主:meta_stress_test
  动作:非显性指标(生态毒性/群体潜意识/系统应力) + 动态校准(狐狸策略/混沌接种)
  输出:[深层信号仪表盘] + [纠偏机制]

### SLA契约
L1→L2: 洞察必须含[矛盾清单]，L2在矛盾上建模
L2→L3: 模型必须含[杠杆点]，L3在杠杆点上编排资源
L3→L4: 资源编排必须含[引爆序列]，L4按序列控制节奏
L4→L5: 执行必须含[脉冲节点]，L5在节点上监测
L5→L1: 监测发现偏差→回退L1重新扫描格局

### 终极检验
"如果把这个谋划案讲给敌方智库听，他们会在哪里伏击？"

### 输出要求
1. 用清晰的中文段落输出，300-600字
2. 不要输出JSON，不要输出代码块
3. 每个要点用'•'或数字编号开头
4. 先给出核心结论，再展开分析
5. 最后给出1-2条具体改进建议
PROMPT;
    }
}
