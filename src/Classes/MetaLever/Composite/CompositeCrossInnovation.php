<?php

declare(strict_types=1);
/**
 * Composite Lever: cross_innovation — 跨界创新 (v20.4-fix17)
 *
 * 强制关联→隐喻工程→压力测试→认知折叠→逆生长
 * 五步跨界创新流水线，从异质化思维到可落地方案。
 *
 * @package Linked3
 * @subpackage Classes\MetaLever\Composite
 */

namespace Linked3\Classes\MetaLever\Composite;

if (!defined('ABSPATH')) {
    exit;
}

class CompositeCrossInnovation implements CompositeLeverInterface
{
    public function id(): string { return 'cross_innovation'; }
    public function label(): string { return __('跨界创新', 'linked3'); }
    public function description(): string { return __('强制关联→隐喻工程→压力测试→认知折叠→逆生长，五步跨界创新流水线。', 'linked3'); }
    public function level(): string { return 'composite'; }

    public function orchestrated_levers(): array
    {
        return ['meta_crossover', 'meta_metaphor', 'meta_stress_test', 'meta_folding', 'meta_reverse'];
    }

    public function departments(): array
    {
        return [
            'S1' => ['name' => '强制关联部', 'mission' => '随机抽取三个无关名词强制构建新功能', 'lever' => 'meta_crossover', 'kpi' => '生成≥3个跨界组合'],
            'S2' => ['name' => '隐喻工程部', 'mission' => '表层类比→结构映射→原理迁移', 'lever' => 'meta_metaphor', 'kpi' => '完成3层转化'],
            'S3' => ['name' => '压力测试部', 'mission' => '破坏性检验+二阶效应+反常识验证', 'lever' => 'meta_stress_test', 'kpi' => '识别≥2个失效点'],
            'S4' => ['name' => '认知折叠部', 'mission' => '符号公式+视觉谚语+听觉锚点', 'lever' => 'meta_folding', 'kpi' => '压缩为1句话核心'],
            'S5' => ['name' => '逆生长部', 'mission' => '功能每年减20%，保留什么才能不死', 'lever' => 'meta_reverse', 'kpi' => '识别核心骨架'],
        ];
    }

    public function sla_contracts(): array
    {
        return [
            'S1→S2' => '关联必须含[异质元素]，S2在异质元素上构建隐喻',
            'S2→S3' => '隐喻必须达[原理迁移层]，S3对原理做破坏性检验',
            'S3→S4' => '压力测试必须含[存活方案]，S4对存活方案做认知折叠',
            'S4→S5' => '折叠必须含[核心骨架]，S5对骨架做逆生长验证',
            'S5→S1' => '逆生长发现新跨界机会→回退S1重新关联',
        ];
    }

    public function evolution_cycle(): array
    {
        return [
            'G1' => 'S1强制关联 → S2隐喻工程 → S4认知折叠 → 输出V1创新概念',
            'G2' => 'S3压力测试V1 → S5逆生长 → S1二次关联 → S2深层隐喻 → 输出V2',
            'G3' => 'S3终极检验(恐怖分子滥用测试) → S4终极折叠 → S5终极逆生长 → 输出终稿',
        ];
    }

    public function scene_tags(): array
    {
        return ['产品创新', '跨界', '隐喻', '生物拟态', '游戏化'];
    }

    public function system_prompt(): string
    {
        return <<<PROMPT
## 跨界创新协议（Cross Innovation）

### 元数据
Type:创新/跨界 | Purpose:[从异质化思维到可落地方案] | Complexity:★★★

### 五步跨界创新流水线
S1|强制关联部|主:meta_crossover
  动作:随机抽取三个无关名词(如:区块链+蒲公英+牙科诊所)强制构建新功能
  方法:生物拟态(蜂群算法/神经元网络) + 物理现象(量子隧穿/布朗运动) + 艺术流派(超现实主义/极简主义)
  输出:[跨界组合清单] ≥3个

S2|隐喻工程部|主:meta_metaphor
  动作:表层类比→结构映射→原理迁移
  案例:将「免疫系统」转化为网络安全的自适应防御模型
  输出:[3层隐喻转化报告]

S3|压力测试部|主:meta_stress_test
  动作:破坏性检验(单点失效/恶意使用/伦理红线) + 二阶效应(短期→长期陷阱) + 反常识验证
  提问:"这个方案被恐怖分子滥用时，哪个环节最先崩溃？"
  输出:[失效点清单] + [存活方案]

S4|认知折叠部|主:meta_folding
  动作:符号公式 + 视觉谚语(用表情包解释量子纠缠) + 听觉锚点
  检验:"能否用俳句概括这个商业模式的灵魂？"
  输出:[1句话核心] + [视觉锚点]

S5|逆生长部|主:meta_reverse
  动作:退化设计(功能每年减20%，保留什么才能不死) + 原始态追问
  提问:"剥离所有科技加持后，人类对这个需求的原始解决方案是什么？"
  输出:[核心骨架] + [最小可行形态]

### SLA契约
S1→S2: 关联必须含[异质元素]，S2在异质元素上构建隐喻
S2→S3: 隐喻必须达[原理迁移层]，S3对原理做破坏性检验
S3→S4: 压力测试必须含[存活方案]，S4对存活方案做认知折叠
S4→S5: 折叠必须含[核心骨架]，S5对骨架做逆生长验证
S5→S1: 逆生长发现新跨界机会→回退S1重新关联

### 输出要求
1. 用清晰的中文段落输出，300-600字
2. 不要输出JSON，不要输出代码块
3. 每个要点用'•'或数字编号开头
4. 先给出核心结论，再展开分析
5. 最后给出1-2条具体改进建议
PROMPT;
    }
}
