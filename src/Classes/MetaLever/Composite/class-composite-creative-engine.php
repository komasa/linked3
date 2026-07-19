<?php
/**
 * 创意生成引擎 — Composite Lever
 *
 * 融合: 元创造 + 元类比 + 元跨界 → 产生真正新颖且有价值的创意
 *
 * @package Linked3
 * @subpackage Classes\MetaLever\Composite
 * @since      27.7.0
 */

namespace Linked3\Classes\MetaLever\Composite;

if (!defined('ABSPATH')) exit;

class Linked3_Composite_Creative_Engine implements Linked3_Composite_Lever_Interface
{
    public function id(): string { return 'creative_engine'; }
    public function label(): string { return __('创意生成引擎', 'linked3'); }
    public function description(): string { return __('创造×类比×跨界 — 从0到1的创意生成法', 'linked3'); }
    public function level(): string { return 'composite'; }

    public function orchestrated_levers(): array
    {
        return ['meta_creativity', 'meta_analogy', 'meta_crossover'];
    }

    public function departments(): array
    {
        return [
            'GEN' => ['name' => '生成', 'mission' => '无约束发散→产生原始素材', 'lever' => 'meta_creativity'],
            'MAP' => ['name' => '类比', 'mission' => '跨域映射→找到结构同构', 'lever' => 'meta_analogy'],
            'CROSS' => ['name' => '跨界', 'mission' => '异域融合→产生新颖组合', 'lever' => 'meta_crossover'],
        ];
    }

    public function sla_contracts(): array
    {
        return [
            'GEN→MAP' => '生成至少5个原始创意 → 类比筛选结构同构性最高的',
            'MAP→CROSS' => '类比输出映射对 → 跨界融合产生最终创意',
        ];
    }

    public function evolution_cycle(): array
    {
        return [
            'G1' => '生成: 不设限发散, 产出5+原始创意方向',
            'G2' => '类比: 找到每个创意的跨域映射, 筛选结构同构性',
            'G3' => '跨界: 将最优映射进行异域融合, 产出最终创意',
        ];
    }

    public function system_prompt(): string
    {
        return <<<PROMPT
## 创意生成引擎 (Creative Engine)

三步创意生成法, 从0到1:

### Step 1: 无约束生成 (Creativity)
- 围绕主题, 生成至少5个创意方向
- 不评判, 不筛选, 追求数量和多样性
- 每个方向用一句话描述

### Step 2: 跨域类比 (Analogy)
- 对每个创意方向, 找到自然界/其他行业/历史中的类比
- 寻找结构同构: "A之于B, 犹如X之于Y"
- 筛选类比强度最高的2-3个

### Step 3: 跨界融合 (Crossover)
- 将最优类比进行异域融合
- 把其他行业的解决方案跨界应用到当前主题
- 产出1-2个最终创意, 附带执行路径

**输出格式 (纯文本):**
编号清晰, 每步独立段落。
PROMPT;
    }

    public function scene_tags(): array
    {
        return ['writing', 'marketing', 'naming', 'design', 'content'];
    }
}
