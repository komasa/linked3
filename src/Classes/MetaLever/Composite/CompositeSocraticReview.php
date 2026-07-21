<?php

declare(strict_types=1);
/**
 * Composite Lever: socratic_review — 苏格拉底审查 (v20.4-fix17)
 *
 * 澄清→挑战假设→追问证据→探索替代→检验影响
 * 五步递进式苏格拉底追问，适用于深度审查和批判性分析。
 *
 * @package Linked3
 * @subpackage Classes\MetaLever\Composite
 */

namespace Linked3\Classes\MetaLever\Composite;

if (!defined('ABSPATH')) {
    exit;
}

class CompositeSocraticReview implements CompositeLeverInterface
{
    public function id(): string { return 'socratic_review'; }
    public function label(): string { return __('苏格拉底审查', 'linked3'); }
    public function description(): string { return __('澄清→挑战假设→追问证据→探索替代→检验影响，五步递进式苏格拉底追问。', 'linked3'); }
    public function level(): string { return 'composite'; }

    public function orchestrated_levers(): array
    {
        return ['meta_socratic', 'meta_questioning', 'meta_essence', 'meta_reverse', 'meta_evaluation'];
    }

    public function departments(): array
    {
        return [
            'D1' => ['name' => '澄清部', 'mission' => '你所说的XX具体指什么？定义模糊概念', 'lever' => 'meta_socratic', 'kpi' => '所有关键词定义清晰'],
            'D2' => ['name' => '假设挑战部', 'mission' => '这个结论依赖什么假设？假设是否成立？', 'lever' => 'meta_questioning', 'kpi' => '识别≥3个隐藏假设'],
            'D3' => ['name' => '证据追问部', 'mission' => '有什么数据支持？数据来源是否可靠？', 'lever' => 'meta_essence', 'kpi' => '证据链完整度评估'],
            'D4' => ['name' => '替代探索部', 'mission' => '是否有其他解释？第三种可能性？', 'lever' => 'meta_reverse', 'kpi' => '提出≥2个替代方案'],
            'D5' => ['name' => '影响检验部', 'mission' => '如果这个结论错了，会怎样？', 'lever' => 'meta_evaluation', 'kpi' => '影响评估矩阵'],
        ];
    }

    public function scene_tags(): array
    {
        return ['深度审查', '批判性分析', '假设检验', '证据评估', '苏格拉底'];
    }

    public function system_prompt(): string
    {
        return <<<PROMPT
## 苏格拉底审查协议（Socratic Review）

### 元数据
Type:审查/批判 | Purpose:[五步递进式苏格拉底追问] | Complexity:★★

### 五步递进架构
D1|澄清部|主:meta_socratic
  核心提问:"你所说的XX具体指什么？"
  动作:识别模糊概念 → 追问定义 → 区分概念层次
  输出:[概念定义表]

D2|假设挑战部|主:meta_questioning
  核心提问:"这个结论依赖什么假设？假设是否成立？"
  动作:识别隐藏假设 → 质疑预设 → 检查逻辑谬误(滑坡/虚假两难/诉诸权威)
  输出:[假设清单] + [谬误检测报告]

D3|证据追问部|主:meta_essence
  核心提问:"有什么数据支持？数据来源是否可靠？"
  动作:追溯证据链 → 评估数据质量 → 检查样本偏差/幸存者偏差
  输出:[证据强度评级] (强/中/弱/无)

D4|替代探索部|主:meta_reverse
  核心提问:"是否有其他解释？第三种可能性？"
  动作:逆向假设 → 角色对抗(魔鬼代言人) → 跨界类比质疑
  输出:[替代方案] ≥2个

D5|影响检验部|主:meta_evaluation
  核心提问:"如果这个结论错了，会怎样？"
  动作:影响矩阵(短期/长期/直接/间接) → 最坏情况推演 → 韧性评估
  输出:[影响评估矩阵] + [风险等级]

### SLA契约
D1→D2: 澄清必须输出[概念定义表]，D2在定义上挑战假设
D2→D3: 假设挑战必须输出[假设清单]，D3对每个假设追问证据
D3→D4: 证据追问必须输出[证据强度评级]，D4对弱证据探索替代
D4→D5: 替代探索必须输出[替代方案]，D5对每个方案评估影响
D5→D1: 影响检验发现概念模糊→回退D1重新澄清

### 实战应用模板
1. 快速质疑:"这个【结论】依赖的【假设】是什么？是否有数据表明【相反案例】存在？"
2. 深度解构:"当我们在说【关键词】时，A群体和B群体的理解是否存在【概念偷换】？"
3. 终极检验:"如果必须用一句话摧毁这个观点，你会攻击它的【证据链】还是【价值预设】？"

### 输出要求
1. 用清晰的中文段落输出，300-600字
2. 不要输出JSON，不要输出代码块
3. 每个要点用'•'或数字编号开头
4. 先给出核心结论，再展开分析
5. 最后给出1-2条具体改进建议
PROMPT;
    }
}
