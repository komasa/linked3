<?php
/**
 * Composite Lever: cognitive_audit — 认知审计 (v20.4-fix19)
 *
 * 认知偏差检测→逻辑谬误审查→证据强度评估→盲区扫描→校准建议
 * 五步认知审计流水线，全面检测方案中的认知缺陷。
 *
 * @package Linked3
 * @subpackage Classes\MetaLever\Composite
 */

namespace Linked3\Classes\MetaLever\Composite;

if (!defined('ABSPATH')) {
    exit;
}

class Linked3_Composite_Cognitive_Audit implements Linked3_Composite_Lever_Interface
{
    public function id(): string { return 'cognitive_audit'; }
    public function label(): string { return __('认知审计', 'linked3'); }
    public function description(): string { return __('偏差检测→谬误审查→证据评估→盲区扫描→校准建议，五步认知审计。', 'linked3'); }
    public function level(): string { return 'composite'; }

    public function orchestrated_levers(): array
    {
        return ['meta_self_calibration', 'meta_logic', 'meta_evaluation', 'meta_cognition', 'meta_questioning'];
    }

    public function departments(): array
    {
        return [
            'A1' => ['name' => '偏差检测部', 'mission' => '扫描确认偏差/锚定效应/幸存者偏差/群体思维', 'lever' => 'meta_self_calibration', 'kpi' => '识别≥3个认知偏差'],
            'A2' => ['name' => '谬误审查部', 'mission' => '检测滑坡/虚假两难/诉诸权威/循环论证/稻草人', 'lever' => 'meta_logic', 'kpi' => '识别≥2个逻辑谬误'],
            'A3' => ['name' => '证据评估部', 'mission' => '评估证据强度(强/中/弱/无)+样本偏差+数据可靠性', 'lever' => 'meta_evaluation', 'kpi' => '每条结论有证据评级'],
            'A4' => ['name' => '盲区扫描部', 'mission' => '识别未知未知+隐性假设+边界条件失效', 'lever' => 'meta_cognition', 'kpi' => '识别≥2个盲区'],
            'A5' => ['name' => '校准建议部', 'mission' => '置信度校准+纠偏方案+持续改进循环', 'lever' => 'meta_questioning', 'kpi' => '输出校准报告'],
        ];
    }

    public function sla_contracts(): array
    {
        return [
            'A1→A2' => '偏差检测必须输出[偏差清单]，A2在偏差上检查对应谬误',
            'A2→A3' => '谬误审查必须输出[谬误清单]，A3对每条谬误评估证据强度',
            'A3→A4' => '证据评估必须输出[证据评级]，A4在弱证据处扫描盲区',
            'A4→A5' => '盲区扫描必须输出[盲区清单]，A5对每个盲区提出校准方案',
            'A5→A1' => '校准方案实施后发现新偏差→回退A1重新检测',
        ];
    }

    public function evolution_cycle(): array
    {
        return [
            'G1' => 'A1偏差检测 → A2谬误审查 → A5校准预判 → 输出V1审计报告',
            'G2' => 'A3证据评估 → A4盲区扫描 → A2二次谬误审查 → A5二次校准 → 输出V2',
            'G3' => 'A5终极校准(如果全错了会怎样) → A4终极盲区 → A1终极偏差 → 输出终稿',
        ];
    }

    public function scene_tags(): array
    {
        return ['认知审计', '偏差检测', '逻辑审查', '证据评估', '盲区扫描'];
    }

    public function system_prompt(): string
    {
        return <<<PROMPT
## 认知审计协议（Cognitive Audit）

### 元数据
Type:审计/校准 | Purpose:[全面检测方案中的认知缺陷] | Complexity:★★★

### 五步认知审计流水线
A1|偏差检测部|主:meta_self_calibration
  动作:扫描确认偏差/锚定效应/幸存者偏差/群体思维/可得性偏差
  输出:[认知偏差清单] ≥3个

A2|谬误审查部|主:meta_logic
  动作:检测滑坡谬误/虚假两难/诉诸权威/循环论证/稻草人/以偏概全
  输出:[逻辑谬误清单] ≥2个

A3|证据评估部|主:meta_evaluation
  动作:评估每条结论的证据强度(强/中/弱/无)+样本偏差+数据可靠性
  输出:[证据强度评级表]

A4|盲区扫描部|主:meta_cognition
  动作:识别未知未知+隐性假设+边界条件失效+反常识盲点
  输出:[盲区清单] ≥2个

A5|校准建议部|主:meta_questioning
  动作:置信度校准+纠偏方案+持续改进循环建立
  输出:[校准报告] + [改进路线图]

### SLA契约
A1→A2: 偏差检测必须输出[偏差清单]，A2在偏差上检查对应谬误
A2→A3: 谬误审查必须输出[谬误清单]，A3对每条谬误评估证据强度
A3→A4: 证据评估必须输出[证据评级]，A4在弱证据处扫描盲区
A4→A5: 盲区扫描必须输出[盲区清单]，A5对每个盲区提出校准方案
A5→A1: 校准方案实施后发现新偏差→回退A1重新检测

### 输出要求
1. 用清晰的中文段落输出，300-600字
2. 不要输出JSON，不要输出代码块
3. 每个要点用'•'或数字编号开头
4. 先给出核心结论，再展开分析
5. 最后给出1-2条具体改进建议
PROMPT;
    }
}
