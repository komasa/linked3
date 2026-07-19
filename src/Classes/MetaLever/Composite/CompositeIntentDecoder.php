<?php

declare(strict_types=1);
namespace Linked3\Classes\MetaLever\Composite;
if (!defined('ABSPATH')) exit;

class CompositeIntentDecoder implements CompositeLeverInterface
{
    public function id(): string { return 'intent_decoder'; }
    public function label(): string { return __('用户意图解码器', 'linked3'); }
    public function description(): string { return __('语境×情绪×说服 — 深度理解用户真实意图', 'linked3'); }
    public function level(): string { return 'composite'; }
    public function orchestrated_levers(): array { return ['meta_context', 'meta_emotion', 'meta_persuasion']; }
    public function departments(): array {
        return [
            'CTX' => ['name' => '语境感知', 'mission' => '理解用户所处的语境和背景', 'lever' => 'meta_context'],
            'EMO' => ['name' => '情绪识别', 'mission' => '识别用户的情绪状态和需求', 'lever' => 'meta_emotion'],
            'PER' => ['name' => '说服路径', 'mission' => '设计最佳说服路径', 'lever' => 'meta_persuasion'],
        ];
    }
    public function sla_contracts(): array { return ['CTX→EMO' => '语境分析 → 情绪识别', 'EMO→PER' => '情绪理解 → 说服设计']; }
    public function evolution_cycle(): array { return ['G1' => '语境: 用户在什么场景下需要这个内容?', 'G2' => '情绪: 用户当前的情绪状态是什么?', 'G3' => '说服: 如何最有效地打动这个用户?']; }
    public function system_prompt(): string {
        return <<<PROMPT
## 用户意图解码器 (Intent Decoder)

深度理解用户真实意图, 生成精准匹配的内容:

### Step 1: 语境感知 (Context)
- 用户在什么场景下搜索/使用这个内容? (工作/学习/娱乐/决策)
- 用户的知识水平? 行业背景?
- 时间紧迫性? (快速浏览 vs 深度阅读)

### Step 2: 情绪识别 (Emotion)
- 用户当前可能的情绪状态? (焦虑/好奇/兴奋/困惑)
- 用户希望获得什么情绪价值? (安心/启发/认同/惊喜)
- 内容应该营造什么情绪氛围?

### Step 3: 说服路径 (Persuasion)
- 基于语境和情绪, 设计最佳说服路径
- 理性说服(数据/逻辑) vs 感性说服(故事/共鸣)
- CTA设计: 用户读完后的下一步行动

**输出: 生成内容时自动融入以上三层理解**
PROMPT;
    }
    public function scene_tags(): array { return ['writing', 'marketing', 'content', 'seo']; }
}
