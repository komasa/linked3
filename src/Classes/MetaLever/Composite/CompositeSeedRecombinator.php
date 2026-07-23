<?php

declare(strict_types=1);
namespace Linked3\Classes\MetaLever\Composite;
if (!defined('ABSPATH')) exit;

class CompositeSeedRecombinator implements CompositeLeverInterface
{
    public function id(): string { return 'seed_recombinator'; }
    public function label(): string { return __('SEED基因重组器', 'linked3'); }
    public function description(): string { return __('模式×递归×范式 — 从已有SEED提取模式重组创新', 'linked3'); }
    public function level(): string { return 'composite'; }
    public function orchestrated_levers(): array { return ['meta_pattern', 'meta_recursion', 'meta_paradigm']; }
    public function departments(): array {
        return [
            'PAT' => ['name' => '模式提取', 'mission' => '从成功SEED中提取可迁移模式', 'lever' => 'meta_pattern'],
            'REC' => ['name' => '递归验证', 'mission' => '递归验证模式在不同层级的有效性', 'lever' => 'meta_recursion'],
            'PAR' => ['name' => '范式转换', 'mission' => '将验证后的模式做范式级转换', 'lever' => 'meta_paradigm'],
        ];
    }
    public function system_prompt(): string {
        return <<<PROMPT
## SEED基因重组器 (Gene Recombinator)

从已有成功案例中提取基因, 重组为新创新:

### Step 1: 模式提取 (Pattern)
- 从现有SEED库中识别重复出现的结构模式
- 提取"成功基因": 哪些元素反复出现在高分输出中?
- 输出: [模式列表] + [基因标记]

### Step 2: 递归验证 (Recursion)
- 对每个模式, 在不同主题/风格/平台下测试
- 递归检查: 模式在子层级是否仍然有效?
- 输出: [鲁棒性评分] + [适用范围]

### Step 3: 范式转换 (Paradigm)
- 将验证通过的模式做范式级转换
- 从"旧范式→新范式"的映射
- 输出: [创新重组方案]
PROMPT;
    }
    public function scene_tags(): array { return ['comic', 'diagram', 'video', 'creation', 'seed']; }
}
