<?php
/**
 * Composite Lever: knowledge_synthesis — 知识综合 (v20.4-fix19)
 *
 * 知识图谱构建→模式提取→跨域连接→认知折叠→知识缺口识别
 * 五步知识综合流水线，从碎片信息到结构化知识体系。
 *
 * @package Linked3
 * @subpackage Classes\MetaLever\Composite
 */

namespace Linked3\Classes\MetaLever\Composite;

if (!defined('ABSPATH')) {
    exit;
}

class Linked3_Composite_Knowledge_Synthesis implements Linked3_Composite_Lever_Interface
{
    public function id(): string { return 'knowledge_synthesis'; }
    public function label(): string { return __('知识综合', 'linked3'); }
    public function description(): string { return __('图谱构建→模式提取→跨域连接→认知折叠→缺口识别，五步知识综合。', 'linked3'); }
    public function level(): string { return 'composite'; }

    public function orchestrated_levers(): array
    {
        return ['meta_knowledge_graph', 'meta_pattern', 'meta_analogy', 'meta_folding', 'meta_abstraction'];
    }

    public function departments(): array
    {
        return [
            'K1' => ['name' => '图谱构建部', 'mission' => '知识节点提取+关系映射+层级结构', 'lever' => 'meta_knowledge_graph', 'kpi' => '节点≥10+关系≥15'],
            'K2' => ['name' => '模式提取部', 'mission' => '从图谱中识别重复模式+结构模式+演化模式', 'lever' => 'meta_pattern', 'kpi' => '识别≥3个模式'],
            'K3' => ['name' => '跨域连接部', 'mission' => '将模式映射到其他领域+发现远距离类比', 'lever' => 'meta_analogy', 'kpi' => '建立≥2个跨域连接'],
            'K4' => ['name' => '认知折叠部', 'mission' => '将复杂图谱压缩为可记忆的核心模型', 'lever' => 'meta_folding', 'kpi' => '压缩为1-3个核心概念'],
            'K5' => ['name' => '缺口识别部', 'mission' => '识别知识图谱中的缺失节点和断裂连接', 'lever' => 'meta_abstraction', 'kpi' => '识别≥2个知识缺口'],
        ];
    }

    public function sla_contracts(): array
    {
        return [
            'K1→K2' => '图谱必须含[节点+关系]，K2在图谱上提取模式',
            'K2→K3' => '模式必须含[结构描述]，K3将结构映射到其他领域',
            'K3→K4' => '跨域连接必须含[映射关系]，K4将映射折叠为核心模型',
            'K4→K5' => '折叠必须含[核心概念]，K5在核心概念周围识别缺口',
            'K5→K1' => '缺口识别发现新节点→回退K1补充图谱',
        ];
    }

    public function evolution_cycle(): array
    {
        return [
            'G1' => 'K1图谱构建 → K2模式提取 → K4认知折叠 → 输出V1知识体系',
            'G2' => 'K3跨域连接 → K5缺口识别 → K1图谱补充 → K2二次模式 → 输出V2',
            'G3' => 'K5终极缺口扫描 → K4终极折叠 → K3终极跨域 → 输出终稿',
        ];
    }

    public function scene_tags(): array
    {
        return ['知识管理', '知识图谱', '跨域连接', '认知折叠', '知识缺口'];
    }

    public function system_prompt(): string
    {
        return <<<PROMPT
## 知识综合协议（Knowledge Synthesis）

### 元数据
Type:综合/结构化 | Purpose:[从碎片信息到结构化知识体系] | Complexity:★★★

### 五步知识综合流水线
K1|图谱构建部|主:meta_knowledge_graph
  动作:知识节点提取+关系映射+层级结构构建
  输出:[知识图谱] 节点≥10+关系≥15

K2|模式提取部|主:meta_pattern
  动作:从图谱中识别重复模式+结构模式+演化模式
  输出:[模式清单] ≥3个

K3|跨域连接部|主:meta_analogy
  动作:将模式映射到其他领域+发现远距离类比
  输出:[跨域映射表] ≥2个

K4|认知折叠部|主:meta_folding
  动作:将复杂图谱压缩为可记忆的核心模型
  输出:[核心模型] 1-3个概念

K5|缺口识别部|主:meta_abstraction
  动作:识别知识图谱中的缺失节点和断裂连接
  输出:[缺口清单] ≥2个

### SLA契约
K1→K2: 图谱必须含[节点+关系]，K2在图谱上提取模式
K2→K3: 模式必须含[结构描述]，K3将结构映射到其他领域
K3→K4: 跨域连接必须含[映射关系]，K4将映射折叠为核心模型
K4→K5: 折叠必须含[核心概念]，K5在核心概念周围识别缺口
K5→K1: 缺口识别发现新节点→回退K1补充图谱

### 输出要求
1. 用清晰的中文段落输出，300-600字
2. 不要输出JSON，不要输出代码块
3. 每个要点用'•'或数字编号开头
4. 先给出核心结论，再展开分析
5. 最后给出1-2条具体改进建议
PROMPT;
    }
}
