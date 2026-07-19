<?php

declare(strict_types=1);
/**
 * Composite Lever: content_engine — 内容引擎 (v20.4-fix20)
 *
 * 变异-绞杀发现的高评分复合杠杆：
 * 叙事构建→情绪共鸣→说服力工程→语境适配→认知折叠
 * 五步内容生成流水线，从叙事到传播的完整链路。
 *
 * 评分: 风险=3 · 可行=9 · 新颖=8 · 适应度=20 (高评分存活)
 *
 * @package Linked3
 * @subpackage Classes\MetaLever\Composite
 */

namespace Linked3\Classes\MetaLever\Composite;

if (!defined('ABSPATH')) {
    exit;
}

class CompositeContentEngine implements CompositeLeverInterface
{
    public function id(): string { return 'content_engine'; }
    public function label(): string { return __('内容引擎', 'linked3'); }
    public function description(): string { return __('叙事构建→情绪共鸣→说服力→语境适配→认知折叠，五步内容生成流水线。', 'linked3'); }
    public function level(): string { return 'composite'; }

    public function orchestrated_levers(): array
    {
        return ['meta_narrative', 'meta_emotion', 'meta_persuasion', 'meta_context', 'meta_folding'];
    }

    public function departments(): array
    {
        return [
            'C1' => ['name' => '叙事构建部', 'mission' => '设计叙事结构(英雄之旅/三幕剧/起承转合)+故事弧线+开头抓注意力', 'lever' => 'meta_narrative', 'kpi' => '叙事结构完整+开头3秒抓人'],
            'C2' => ['name' => '情绪共鸣部', 'mission' => '识别受众核心情感需求+设计情感触点+从知道升级为感受到', 'lever' => 'meta_emotion', 'kpi' => '≥3个情感共鸣点'],
            'C3' => ['name' => '说服力工程部', 'mission' => '修辞手法运用+论证结构设计+影响力技巧植入', 'lever' => 'meta_persuasion', 'kpi' => '论证链完整+修辞≥2种'],
            'C4' => ['name' => '语境适配部', 'mission' => '情境理解+文化敏感+语境适配+平台调性匹配', 'lever' => 'meta_context', 'kpi' => '平台调性零违和'],
            'C5' => ['name' => '认知折叠部', 'mission' => '将内容压缩为可记忆的核心模型+视觉锚点+听觉锚点', 'lever' => 'meta_folding', 'kpi' => '1句话核心+1个视觉锚点'],
        ];
    }

    public function sla_contracts(): array
    {
        return [
            'C1→C2' => '叙事必须含[故事弧线]，C2在弧线关键节点植入情感触点',
            'C2→C3' => '情感必须含[共鸣点清单]，C3在每个共鸣点上叠加说服力技巧',
            'C3→C4' => '说服力必须含[论证链]，C4将论证链适配到目标平台语境',
            'C4→C5' => '语境适配必须含[平台调性]，C5将适配后内容折叠为可记忆模型',
            'C5→C1' => '折叠发现叙事缺陷→回退C1重新设计故事弧线',
        ];
    }

    public function evolution_cycle(): array
    {
        return [
            'G1' => 'C1叙事构建 → C2情绪共鸣 → C5认知折叠 → 输出V1内容草案',
            'G2' => 'C3说服力工程 → C4语境适配 → C2二次情绪优化 → C5二次折叠 → 输出V2',
            'G3' => 'C5终极折叠(能否用一句话说清核心) → C4终极语境(跨平台适配) → C1终极叙事 → 输出终稿',
        ];
    }

    public function scene_tags(): array
    {
        return ['内容创作', '小红书', 'SEO文章', '视频脚本', '文案', '品牌叙事'];
    }

    public function system_prompt(): string
    {
        return <<<PROMPT
## 内容引擎协议（Content Engine）

### 元数据
Type:创作/内容 | Purpose:[从叙事到传播的完整内容生成链路] | Complexity:★★★
评分: 风险=3 · 可行=9 · 新颖=8 · 适应度=20 (变异-绞杀存活)

### 五步内容生成流水线
C1|叙事构建部|主:meta_narrative
  动作:叙事结构设计(英雄之旅/三幕剧/起承转合) + 故事弧线 + 开头抓注意力
  输出:[叙事骨架] + [开头钩子]

C2|情绪共鸣部|主:meta_emotion
  动作:识别受众核心情感需求 + 设计情感触点 + 从"知道"升级为"感受到"
  输出:[情感触点清单] ≥3个

C3|说服力工程部|主:meta_persuasion
  动作:修辞手法运用 + 论证结构设计 + 影响力技巧植入
  输出:[论证链] + [修辞清单] ≥2种

C4|语境适配部|主:meta_context
  动作:情境理解 + 文化敏感 + 语境适配 + 平台调性匹配
  输出:[平台适配版] 零调性违和

C5|认知折叠部|主:meta_folding
  动作:将内容压缩为可记忆的核心模型 + 视觉锚点 + 听觉锚点
  输出:[1句话核心] + [1个视觉锚点]

### SLA契约
C1→C2: 叙事必须含[故事弧线]，C2在弧线关键节点植入情感触点
C2→C3: 情感必须含[共鸣点清单]，C3在每个共鸣点上叠加说服力技巧
C3→C4: 说服力必须含[论证链]，C4将论证链适配到目标平台语境
C4→C5: 语境适配必须含[平台调性]，C5将适配后内容折叠为可记忆模型
C5→C1: 折叠发现叙事缺陷→回退C1重新设计故事弧线

### 输出要求
1. 用清晰的中文段落输出，300-600字
2. 不要输出JSON，不要输出代码块
3. 每个要点用'•'或数字编号开头
4. 先给出核心结论，再展开分析
5. 最后给出1-2条具体改进建议
PROMPT;
    }
}
