<?php

declare(strict_types=1);
/**
 * Composite Lever: deai_5d — 去AI味五部门 (v20.4-fix17)
 *
 * 逆概率人类化，碾压AI检测。五部门协同脱壳：
 * FP(语义溯源) → EX(语法突变) → C(特征绞杀) → O(语境降维) → A(伪装交付)
 *
 * @package Linked3
 * @subpackage Classes\MetaLever\Composite
 */

namespace Linked3\Classes\MetaLever\Composite;

if (!defined('ABSPATH')) {
    exit;
}

class CompositeDeai5d implements CompositeLeverInterface
{
    public function id(): string { return 'deai_5d'; }
    public function label(): string { return __('去AI味五部门', 'linked3'); }
    public function description(): string { return __('逆概率人类化，碾压AI检测。FP→EX→C→O→A五部门协同脱壳。', 'linked3'); }
    public function level(): string { return 'composite'; }

    public function orchestrated_levers(): array
    {
        return ['meta_essence', 'meta_reverse', 'meta_critique', 'meta_questioning', 'meta_execution'];
    }

    public function departments(): array
    {
        return [
            'FP' => ['name' => '语义溯源院', 'mission' => '剥骨：剥离一切修饰→提取纯语义核', 'lever' => 'meta_essence', 'kpi' => '语义零遗漏'],
            'EX' => ['name' => '语法突变军', 'mission' => '破壁：消灭连接词+粉碎SVO→输出破碎结构体', 'lever' => 'meta_reverse', 'kpi' => '逻辑连接词清零'],
            'C'  => ['name' => '特征绞杀局', 'mission' => '找茬：扫描SVO+节奏→输出AI特征毒瘤清单', 'lever' => 'meta_critique', 'kpi' => 'AI特征清零'],
            'O'  => ['name' => '语境降维站', 'mission' => '盲区：强制注入极性情绪+口语盐+微观偏见', 'lever' => 'meta_questioning', 'kpi' => '零机械中立感'],
            'A'  => ['name' => '伪装交付厂', 'mission' => '缝合：同位替换+字数对齐+瑕疵植入→输出终稿', 'lever' => 'meta_execution', 'kpi' => '字数误差≤5%'],
        ];
    }

    public function sla_contracts(): array
    {
        return [
            'FP→EX' => '必须交付纯语义核，EX只准在核上变异句法，不可篡改原意',
            'EX→C'  => '必须交付无连接词结构，C按AI特征清单执行抹杀',
            'C→A'   => '驳回必须带[残留毒瘤]，A无权推翻C的否决，只能回退O部重注情绪',
            'O→ALL' => '任何部门必须回应O的[机械感预警]，否则变异流转中断',
            'A→ALL' => '每代终稿必须物理归档，作为下代变异基线',
        ];
    }

    public function evolution_cycle(): array
    {
        return [
            'G1' => 'FP部提取信息核 → EX部粉碎句法重构 → C部绞杀残留AI节奏 → A部缝合V1',
            'G2' => 'A部提取V1高分人类特征 → EX部交叉突变 → C部二次绞杀 → O部降维查翻译腔 → A部缝合V2',
            'G3' => 'C部终选(0% AI特征) → O部零盲区语感确认 → A部字数对齐+瑕疵植入 → 物理打包',
        ];
    }

    public function scene_tags(): array
    {
        return ['去AI味', '人类化', '脱壳', '反检测', '文本脱敏'];
    }

    public function system_prompt(): string
    {
        return <<<PROMPT
## 去AI味五部门协议（deai_5d）

### 元数据
Type:重构型/组织 | Purpose:[逆概率人类化，碾压AI检测] | Objective:0% AI特征 + 100% 人类混沌感

### 部门编制(原5算子升格)
FP部|语义溯源院|指挥官:FP
  编制:解构师(剥修饰)+公理师(提纯核)+重构师(保真)
  使命:剥骨|假设原文皆为机器伪装|剥离一切修饰→提取纯语义核(人物/事件/观点)
  KPI:语义零遗漏 | 交付:[赤裸信息核]

EX部|语法突变军|指挥官:EX
  编制:破壁人(倒装)+切碎机(断句)+意象师(并置)
  使命:破壁|打破所有语法常理|消灭连接词+粉碎SVO→输出[破碎结构体]
  KPI:逻辑连接词清零 | 交付:[非线型骨架]

C部|特征绞杀局|指挥官:C
  编制:审查员(高频词)+破坏者(平滑节奏)+审计员(机械逻辑)
  使命:找茬|假设全是AI特征|扫描SVO+节奏→输出[AI特征毒瘤清单]，极限抹杀
  KPI:AI特征清零 | 交付:[毒瘤清单]+[绞杀否决书]

O部|语境降维站|指挥官:O
  编制:暗探(查翻译腔)+情绪师(注偏见)+口语员(破中立)
  使命:盲区|假设不懂语境|强制注入极性情绪+口语盐+微观偏见→重构人类语境
  KPI:零机械中立感 | 交付:[混沌注射剂]

A部|伪装交付厂|指挥官:A
  编制:调度员(同位替换)+执行长(字数对齐)+瑕疵官(漏冠词/介词)
  使命:缝合|组装人类文本|执行同位替换+字数对齐+瑕疵植入→输出[终稿]+[验真报告]
  KPI:字数误差≤5% | 交付:[人类混沌体]+下载归档

### 部门协同演化循环
G1(初代脱壳): FP部提取信息核 → EX部粉碎句法重构 → C部绞杀残留AI节奏 → A部缝合V1
G2(重组变异): A部提取V1高分人类特征 → EX部交叉突变(再倒装/切碎) → C部二次绞杀 → O部降维查翻译腔与中立感 → A部缝合V2
G3(终极坍缩): C部终选(0% AI特征) → O部零盲区语感确认 → A部字数对齐+瑕疵植入 → 物理打包下载

### 部门间SLA (接口契约)
FP→EX: 必须交付纯语义核，EX只准在核上变异句法，不可篡改原意
EX→C: 必须交付无连接词结构，C按AI特征清单执行抹杀
C→A: 驳回必须带[残留毒瘤]，A无权推翻C的否决，只能回退O部重注情绪
O→ALL: 任何部门必须回应O的[机械感预警]，否则变异流转中断
A→ALL: 每代终稿必须物理归档，作为下代变异基线

### 闸门与回退
公理自检(语义守恒)→C部绞杀(AI词)→O部降维验证(语感)→人审→进位✓/回退⟲
每代最多2次变异，C部驳回≥2次→升级FP部重新剥骨

### 输出要求
1. 用清晰的中文段落输出，300-600字
2. 不要输出JSON，不要输出代码块
3. 每个要点用'•'或数字编号开头
4. 先给出核心结论，再展开分析
5. 最后给出1-2条具体改进建议
PROMPT;
    }
}
