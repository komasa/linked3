<?php

declare(strict_types=1);
namespace Linked3\Classes\MetaLever\Composite;
if (!defined('ABSPATH')) exit;

class CompositeWritingDepth implements CompositeLeverInterface
{
    public function id(): string { return 'writing_depth'; }
    public function label(): string { return __('写作深度增强器', 'linked3'); }
    public function description(): string { return __('认知×元认知×折叠 — 让文章有思想深度', 'linked3'); }
    public function level(): string { return 'composite'; }
    public function orchestrated_levers(): array { return ['meta_cognition', 'meta_metacognition', 'meta_folding']; }
    public function departments(): array {
        return [
            'COG' => ['name' => '认知层', 'mission' => '分析主题的认知结构', 'lever' => 'meta_cognition'],
            'META' => ['name' => '元认知层', 'mission' => '反思思考过程本身', 'lever' => 'meta_metacognition'],
            'FOLD' => ['name' => '折叠层', 'mission' => '多层信息压缩为精炼表达', 'lever' => 'meta_folding'],
        ];
    }
    public function system_prompt(): string {
        return <<<PROMPT
## 写作深度增强器 (Writing Depth Enhancer)

三步让文章有思想深度:

### Step 1: 认知层 (Cognition)
- 这个主题的核心认知结构是什么?
- 读者目前的认知水平 vs 目标认知水平
- 需要跨越哪些认知障碍?

### Step 2: 元认知层 (Metacognition)
- 读者读完这篇文章后, 会怎样反思自己的思考过程?
- 文章是否引导读者"思考自己的思考"?
- 哪些段落能触发"啊哈时刻"?

### Step 3: 折叠层 (Folding)
- 将多层次的思考折叠为精炼的表达
- 每个观点不超过3句, 但内涵3层意义
- 用"冰山模型": 水面上是文字, 水面下是思考深度

**输出: 在写作时自动注入以上三层思考**
PROMPT;
    }
    public function scene_tags(): array { return ['writing', 'article', 'blog', 'essay']; }
}
