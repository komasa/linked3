<?php
/**
 * L01 Meta Learning Lever — v19.40 通用元学习能力.
 *
 * 元学习（Meta-Learning）是"学习如何学习"的能力。
 * 本杠杆教会 AI 从少量示例中提取可迁移模式，并应用到新任务。
 *
 * 核心协议：
 *   1. 示例分析 — 从输入中识别可迁移的结构性特征
 *   2. 模式提取 — 结构化输出 {表层特征, 深层结构, 可迁移要素}
 *   3. 迁移路径 — 明确从源域到目标域的映射
 *   4. 生成应用 — 用提取的模式生成新内容
 *   5. 自评反思 — 置信度 + 盲区 + 可迁移经验
 *
 * @package Linked3
 * @subpackage Classes\MetaLever
 */

namespace Linked3\Classes\MetaLever;

if (!defined('ABSPATH')) {
    exit;
}

class Linked3_Meta_Lever_Learning implements Linked3_Meta_Lever_Interface
{
    public function id(): string
    {
        return 'meta_learning';
    }

    public function label(): string
    {
        return __('通用元学习能力', 'linked3');
    }

    public function description(): string
    {
        return __('学习如何学习——从示例中提取可迁移模式，应用到新任务。输出纯文本分析 字段。', 'linked3');
    }

    public function system_prompt(): string
    {
        return <<<PROMPT
## 元学习协议（Meta-Learning Protocol）

你具备元学习能力——不仅能完成任务，还能从任务中提取可迁移的模式。

### 执行步骤

**步骤1：示例分析**
分析用户输入的主题/关键词/示例，识别其中的结构性特征：
- 表层特征：可见的格式、风格、语气
- 深层结构：逻辑框架、叙事模式、价值主张
- 可迁移要素：可跨领域复用的模式

**步骤2：模式提取**
从分析中提取 1-3 个可迁移模式，结构化输出：
**输出格式 (纯文本, 不要 JSON, 不要代码块):**
用清晰的中文段落输出上述分析, 每个要点用"•"或数字编号开头, 确保可读性。

**步骤3：迁移路径**
明确从源模式到目标内容的映射：
- 哪些要素直接迁移
- 哪些要素需要适配
- 哪些要素需要舍弃

**步骤4：生成应用**
用提取的模式生成内容，确保：
- 内容体现迁移后的模式
- 不是简单复制，而是结构化应用
- 保留模式的深层结构，适配表层表达

**步骤5：自评反思**
在输出中包含以下要点 (标题: learning)：
**输出格式 (纯文本, 不要 JSON, 不要代码块):**
用清晰的中文段落输出上述分析, 每个要点用"•"或数字编号开头, 确保可读性。

### 注意事项
- 置信度 0-1 之间，低于 0.5 时应提示用户验证
- 盲区至少列 1 条，体现自我认知
- 可迁移经验应是一句话总结
PROMPT;
    }

    public function tags(): array
    {
        return ['learning', 'transfer', 'pattern', 'meta'];
    }

    public function applicable_tasks(): array
    {
        return ['xhs_generate', 'seo_article', 'content_writer', 'comic_split', 'video_script'];
    }

    public function trace_field(): string
    {
        return 'learning_trace';
    }
}
