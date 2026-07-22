<?php

declare(strict_types=1);
/**
 * 万能思维新三法 — Composite Lever
 *
 * 融合3个基础杠杆为1个万能组合:
 *   1. 元本质追问 (essence) — 剥离表象, 找到问题的第一性原理
 *   2. 元反向思考 (reverse) — 从结果倒推, 发现隐藏假设
 *   3. 元系统思维 (system) — 拉高视角, 看到全局因果链
 *
 * 适用场景: 任何需要深度思考的任务 — 写作选题、产品决策、问题诊断
 *
 * @package Linked3
 * @subpackage Classes\MetaLever\Composite
 * @since      27.7.0
 */

namespace Linked3\Classes\MetaLever\Composite;

if (!defined('ABSPATH')) exit;

class CompositeUniversalTrio implements CompositeLeverInterface
{
    public function id(): string { return 'universal_trio'; }
    public function label(): string { return __('万能思维新三法', 'linked3'); }
    public function description(): string { return __('本质追问×反向思考×系统思维 — 三步万能深度思考法', 'linked3'); }
    public function level(): string { return 'composite'; }

    public function orchestrated_levers(): array
    {
        return ['meta_essence', 'meta_reverse', 'meta_system'];
    }

    public function departments(): array
    {
        return [
            'FP' => ['name' => '本质追问', 'mission' => '剥离表象→找到第一性原理', 'lever' => 'meta_essence'],
            'EX' => ['name' => '反向思考', 'mission' => '从终局倒推→发现隐藏假设', 'lever' => 'meta_reverse'],
            'SYS' => ['name' => '系统思维', 'mission' => '拉高视角→看到全局因果', 'lever' => 'meta_system'],
        ];
    }

    public function system_prompt(): string
    {
        return <<<PROMPT
## 万能思维新三法 (Universal Thinking Trio)

你同时具备三种深度思维能力, 按顺序执行:

### 第一法: 本质追问 (Essence)
- 这个问题/主题的本质是什么? 剥离所有修饰和表象
- 如果只能用一个词概括, 是什么?
- 这个问题的第一性原理是什么? (不可再分解的基本真理)
- 输出: [本质定义] + [第一性原理]

### 第二法: 反向思考 (Reverse)
- 如果这个方案最终失败了, 最可能的3个原因是什么?
- 从失败终局倒推, 现在最容易忽略的假设是什么?
- 如果完全反过来做(逆向), 会怎样?
- 输出: [隐藏假设] + [失败路径] + [逆向可能]

### 第三法: 系统思维 (System)
- 拉高视角: 这个问题在更大系统中处于什么位置?
- 上游(原因)和下游(影响)分别是什么?
- 系统中有哪些反馈环? 正反馈(增强)还是负反馈(平衡)?
- 输出: [系统位置] + [因果链] + [杠杆点]

### 最终综合
将三法输出融合为一段可执行的行动建议(不超过3条)。

**输出格式 (纯文本, 不要 JSON):**
用清晰的中文段落输出, 每个要点用数字编号。
PROMPT;
    }

    public function scene_tags(): array
    {
        return ['writing', 'strategy', 'decision', 'diagnosis', 'planning'];
    }
}
