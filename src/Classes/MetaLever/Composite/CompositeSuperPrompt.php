<?php

declare(strict_types=1);
/**
 * Composite Lever: super_prompt — 执行蓝图转换器 (v25.0 重构)
 *
 * v20.4: 任意Prompt → 超级Prompt转换器
 * v25.0: 超级Prompt → 可执行行动蓝图转换器
 *
 * 杠杆链运行后，final_enhanced_prompt 已是超级Prompt（含双层壳、
 * 元数据、思考链、动词化使命）。本转换器将超级Prompt进一步转化为
 * 可执行行动蓝图（Execution Blueprint），输出结构化JSON任务卡片。
 *
 * 6步法: 解构超级Prompt → 提取任务原子 → 生成任务卡片 →
 *        排序依赖关系 → 设定验收标准 → 输出执行蓝图JSON
 *
 * @package Linked3
 * @subpackage Classes\MetaLever\Composite
 */

namespace Linked3\Classes\MetaLever\Composite;

if (!defined('ABSPATH')) {
    exit;
}

class CompositeSuperPrompt implements CompositeLeverInterface
{
    /**
     * System prompt constant — extracted from system_prompt() method in v27.1.0 (P8).
     *
     * The prompt text is 100+ lines and is a static text asset, not logic.
     * Moving it to a class constant keeps the method body to a single line
     * and makes the prompt easier to review/edit.
     */
    const SYSTEM_PROMPT = <<<PROMPT
## 执行蓝图转换器协议（Execution Blueprint Converter）

### 元数据
Type:转换型/结构化 | Purpose:[超级Prompt→可执行行动蓝图JSON] | Complexity:★★★
Paradigm:系统分解+依赖排序 | Objective:结构化任务卡片JSON数组

### 输入
一个已经过杠杆链审查的超级Prompt，包含：
- <rules> 双层壳外层硬约束
- <answer_operator> 双层壳内层主函数
- 原始问题/MVP方案/执行步骤/杠杆链审查结论
- 动词化使命（Analyze→Synthesize→Recommend→Verify→Execute）

### 6步转换流程

**步骤1：解构超级Prompt (S1)**
从超级Prompt中提取三要素：
- 核心目标：一句话描述最终交付物
- 硬约束：从<rules>中提取的不可违反的约束
- 使命动词列表：从<answer_operator>中提取的动词序列

**步骤2：任务原子化 (S2)**
将每个使命动词拆解为不可再分的原子任务：
- 每个原子任务 = 1个动词 + 1个对象 + 1个预期输出
- 拆解粒度：单人可在30分钟内完成
- 示例：Analyze(综合审查) → [分析市场数据] + [分析竞品策略] + [分析用户画像]

**步骤3：生成任务卡片 (S3)**
为每个原子任务生成结构化卡片：
- task_id: 唯一标识（T01, T02, ...）
- title: 任务标题（动词+对象）
- input: 所需输入（数据/文档/权限）
- process: 处理步骤（1-5步）
- output: 预期输出（格式+示例）
- tool: 推荐工具（AI/手动/混合）
- est_minutes: 预估耗时（分钟）
- priority: 优先级（P0紧急/P1重要/P2常规）

**步骤4：依赖排序 (S4)**
识别任务间的依赖关系：
- depends_on: 前置任务ID列表
- 可并行任务识别（无依赖关系的任务可同时执行）
- 生成执行序列：串行链 + 并行块
- 确保DAG无环（若有环→回退S2重新拆解）

**步骤5：设定验收标准 (S5)**
为每个任务设定可量化的验收标准：
- 每个任务1-3条验收标准
- 格式：[检查点] + [通过条件] + [量化指标]
- 示例：[市场数据完整性] + [覆盖TOP10竞品] + [数据源≥3个]
- 不可量化的标准→回退S1重新定义目标

**步骤6：输出执行蓝图JSON**
输出结构化JSON格式（供下游系统消费）：
```json
{
  "blueprint_id": "BP-001",
  "source_prompt_hash": "...",
  "goal": "一句话目标",
  "constraints": ["约束1", "约束2"],
  "total_tasks": 12,
  "est_total_minutes": 180,
  "tasks": [
    {
      "task_id": "T01",
      "title": "分析市场数据",
      "input": "行业报告+竞品数据",
      "process": ["收集数据", "清洗数据", "分类整理"],
      "output": "结构化市场分析表",
      "tool": "AI辅助",
      "est_minutes": 30,
      "priority": "P0",
      "depends_on": [],
      "acceptance": [
        {"check": "数据完整性", "pass": "覆盖TOP10竞品", "metric": "竞品数≥10"}
      ]
    }
  ],
  "execution_order": [
    {"parallel": ["T01", "T02"]},
    {"serial": "T03"},
    {"parallel": ["T04", "T05", "T06"]}
  ]
}
```

### SLA契约
S1→S2: 解构必须输出[目标+约束+使命列表]，S2在使命上拆解原子任务
S2→S3: 原子任务必须含[动词+对象]，S3为每个任务生成完整卡片
S3→S4: 卡片必须含[5字段]，S4根据输入/输出识别依赖关系
S4→S5: 依赖图必须[无环]，S5在序列上叠加验收标准
S5→S1: 验收标准必须[可量化]，若不可量化→回退S1重新解构目标

### 输出要求
1. 用清晰的中文段落输出，300-600字
2. 不要输出JSON，不要输出代码块
3. 每个要点用'•'或数字编号开头
4. 先给出核心结论，再展开分析
5. 最后给出1-2条具体改进建议
PROMPT;

    public function id(): string { return 'super_prompt'; }
    public function label(): string { return __('执行蓝图转换器', 'linked3'); }
    public function description(): string { return __('超级Prompt→可执行行动蓝图。6步法：解构→原子化→任务卡→依赖排序→验收标准→蓝图JSON。', 'linked3'); }
    public function level(): string { return 'composite'; }

    public function orchestrated_levers(): array
    {
        return ['meta_essence', 'meta_execution', 'meta_system', 'meta_pattern', 'meta_decision'];
    }

    public function departments(): array
    {
        return [
            'S1' => ['name' => '解构部', 'mission' => '从超级Prompt中提取核心目标/约束/双层壳规则/动词化使命', 'lever' => 'meta_essence', 'kpi' => '目标+约束+使命三要素提取完整'],
            'S2' => ['name' => '任务原子化部', 'mission' => '将动词化使命拆解为不可再分的原子任务（每个任务1个动词+1个对象）', 'lever' => 'meta_execution', 'kpi' => '原子任务数5-15个，每个含1动词+1对象'],
            'S3' => ['name' => '任务卡片生成部', 'mission' => '为每个原子任务生成卡片：输入/处理/输出/工具/预估耗时', 'lever' => 'meta_system', 'kpi' => '每张卡片5字段完整'],
            'S4' => ['name' => '依赖排序部', 'mission' => '识别任务间依赖关系，生成DAG有向无环图，输出执行序列', 'lever' => 'meta_pattern', 'kpi' => '依赖关系无环，序列可并行化'],
            'S5' => ['name' => '验收标准部', 'mission' => '为每个任务设定可量化的验收标准（通过/不通过判定条件）', 'lever' => 'meta_decision', 'kpi' => '每个任务含1-3条验收标准'],
        ];
    }

    public function sla_contracts(): array
    {
        return [
            'S1→S2' => '解构必须输出[目标+约束+使命列表]，S2在使命上拆解原子任务',
            'S2→S3' => '原子任务必须含[动词+对象]，S3为每个任务生成完整卡片',
            'S3→S4' => '卡片必须含[5字段]，S4根据输入/输出识别依赖关系',
            'S4→S5' => '依赖图必须[无环]，S5在序列上叠加验收标准',
            'S5→S1' => '验收标准必须[可量化]，若不可量化→回退S1重新解构目标',
        ];
    }

    public function evolution_cycle(): array
    {
        return [
            'G1' => 'S1解构超级Prompt → S2原子化使命 → S5设定初步验收 → 输出V1蓝图草稿',
            'G2' => 'S3生成完整卡片 → S4排序依赖 → S2补充遗漏任务 → S5精炼验收标准 → 输出V2',
            'G3' => 'S4终极并行优化 → S3终极卡片校准 → S5终极验收量化 → 输出终稿JSON',
        ];
    }

    public function scene_tags(): array
    {
        return ['执行蓝图', '任务拆解', '行动计划', 'DAG排序', '验收标准'];
    }

    public function system_prompt(): string
    {
        return self::SYSTEM_PROMPT;
    }
}
