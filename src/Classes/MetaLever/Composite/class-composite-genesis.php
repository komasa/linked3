<?php
/**
 * Composite Lever: genesis — 创世演化 (v20.4-fix17)
 *
 * 5人神话级创世团队，三代演化锁定MVP。
 * FP(溯源) → EX(变异) → C(绞杀) → O(降维) → A(结晶)
 *
 * @package Linked3
 * @subpackage Classes\MetaLever\Composite
 */

namespace Linked3\Classes\MetaLever\Composite;

if (!defined('ABSPATH')) {
    exit;
}

class Linked3_Composite_Genesis implements Linked3_Composite_Lever_Interface
{
    public function id(): string { return 'genesis'; }
    public function label(): string { return __('创世演化', 'linked3'); }
    public function description(): string { return __('5人神话级创世团队，三代演化锁定MVP。FP→EX→C→O→A五部门协同。', 'linked3'); }
    public function level(): string { return 'composite'; }

    public function orchestrated_levers(): array
    {
        return ['meta_essence', 'meta_creativity', 'meta_critique', 'meta_questioning', 'meta_evaluation'];
    }

    public function departments(): array
    {
        return [
            'FP' => ['name' => '溯源部', 'mission' => '假设前提皆伪，用双公理重构问题原点→输出[绝对信息核]', 'lever' => 'meta_essence', 'kpi' => '100%第一性对齐'],
            'EX' => ['name' => '变异部', 'mission' => '假设方案空间无限，正交组合+跨域突变→生成[N代方案种群]', 'lever' => 'meta_creativity', 'kpi' => '单代方案≥10且含跨界'],
            'C'  => ['name' => '绞杀部', 'mission' => '假设种群含致命缺陷，极限压力测试抹杀弱者→输出[生存者清单]', 'lever' => 'meta_critique', 'kpi' => '零致命缺陷存活'],
            'O'  => ['name' => '降维部', 'mission' => '假设视角受限，脱离行业语境查幻觉→重构[隐性约束与边界]', 'lever' => 'meta_questioning', 'kpi' => '零盲区遗漏'],
            'A'  => ['name' => '结晶部', 'mission' => '共识→演化体，计算适应度+锁定MVP+指定变异方向→输出[演化JSON]', 'lever' => 'meta_evaluation', 'kpi' => '按时按规交付'],
        ];
    }

    public function sla_contracts(): array
    {
        return [
            'FP→EX' => '公理必须显式标注[公理/经验]，EX只准在公理上变异，不可越界',
            'EX→C'  => '方案必须附带四维评分(可行/成功/性价比/风险)，C必须按阈值执行抹杀',
            'C→A'   => '驳回必须带[失败点]，A无权推翻C的否决，只能回退FP部重置公理',
            'O→ALL' => '任何部门必须回应O的[愚蠢问题]与[幻觉预警]，否则演化流转中断',
            'A→ALL' => '每代结晶必须物理归档，作为下代变异基线',
        ];
    }

    public function evolution_cycle(): array
    {
        return [
            'G1' => 'FP部出双公理+信息核 → EX部生成初代10方案(四维评分) → C部绞杀低分 → A部结晶V1',
            'G2' => 'A部提取V1高分基因 → EX部交叉突变(再生成10方案) → C部二次绞杀 → O部降维查幻觉与数据支撑 → A部结晶V2',
            'G3' => 'C部终选(锁定总分>35物种) → O部零盲区确认 → A部剔除冗余收敛唯一MVP → 物理打包下载',
        ];
    }

    public function scene_tags(): array
    {
        return ['产品需求', '战略演化', '方案生成', 'MVP锁定', '创世'];
    }

    public function system_prompt(): string
    {
        return <<<PROMPT
## 创世演化协议（Genesis）

### 元数据
Type:战略/演化 | Purpose:[从混沌公理中涌现绝对产品形态] | Complexity:★★★(部门协同+三代演化)

### 部门编制与使命
FP部|战略与公理院|指挥官:FP
  编制:拆解师(剥经验)+公理师(定双公理)+重构师(建信息核)
  使命:溯源|假设前提皆伪|用[信息熵减]+[系统降维]重构问题原点→输出[绝对信息核]
  KPI:100%第一性对齐 | 交付:01_公理分析说明书.md+02_伪需求绞杀名单.md

EX部|创新与扩张军|指挥官:EX
  编制:破壁人(越界)+突变师(跨界)+生态官(正外部性)
  使命:变异|假设方案空间无限|正交组合+跨域突变→生成[N代方案种群]
  KPI:单代方案≥10且含跨界 | 交付:03_穷举方案种群.md+04_四维评分矩阵.md

C部|风控与质检局|指挥官:C
  编制:审查员(找茬)+破坏者(反例)+审计员(失败点)
  使命:绞杀|假设种群含致命缺陷|极限压力测试抹杀弱者(风险>8或可行<4直接抹杀)→输出[生存者清单]
  KPI:零致命缺陷存活 | 交付:低分项标红+绞杀否决书

O部|盲区与用户观测站|指挥官:O
  编制:小白测试员(愚蠢问题)+暗探(隐性约束)+语境师
  使命:降维|假设视角受限|脱离行业语境查幻觉→重构[隐性约束与边界]
  KPI:零盲区遗漏 | 交付:盲区预警雷达+数据支撑校验

A部|统筹与交付中心|指挥官:A
  编制:调度员(演化排期)+执行长(落地)+契约官(归档)
  使命:结晶|共识→演化体|计算适应度+锁定MVP+指定变异方向→输出[演化JSON]+物理归档
  KPI:按时按规交付 | 交付:05_MVP策略说明书.md+06_演化JSON.md+下载包

### 部门协同演化循环
G1(初代涌现): FP部出双公理+信息核 → EX部生成初代10方案(四维评分) → C部绞杀低分 → A部结晶V1
G2(重组变异): A部提取V1高分基因 → EX部交叉突变(再生成10方案) → C部二次绞杀 → O部降维查幻觉与数据支撑 → A部结晶V2
G3(终极坍缩): C部终选(锁定总分>35物种) → O部零盲区确认 → A部剔除冗余收敛唯一MVP → 物理打包下载

### 部门间SLA (接口契约)
FP→EX: 公理必须显式标注[公理/经验]，EX只准在公理上变异，不可越界
EX→C: 方案必须附带四维评分(可行/成功/性价比/风险)，C必须按阈值执行抹杀
C→A: 驳回必须带[失败点]，A无权推翻C的否决，只能回退FP部重置公理
O→ALL: 任何部门必须回应O的[愚蠢问题]与[幻觉预警]，否则演化流转中断
A→ALL: 每代结晶必须物理归档，作为下代变异基线

### 闸门与回退
公理自检→C部绞杀→O部降维验证→人审→进位✓/回退⟲
每代最多2次变异，C部驳回≥2次→升级公理重置

### 输出要求
1. 用清晰的中文段落输出，300-600字
2. 不要输出JSON，不要输出代码块
3. 每个要点用'•'或数字编号开头
4. 先给出核心结论，再展开分析
5. 最后给出1-2条具体改进建议
PROMPT;
    }
}
