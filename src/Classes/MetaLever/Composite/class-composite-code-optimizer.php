<?php
/**
 * 代码质量优化器 — Composite Lever
 *
 * 融合: 元评估 + 元压力测试 + 元自我校准
 * 专门用于优化代码模块质量, 而非拆分文件。
 *
 * 应用场景: 对 God Class 进行质量评估、压力测试和优化建议,
 * 而不是简单拆分。保留业务逻辑完整性, 提升可维护性。
 *
 * @package Linked3
 * @subpackage Classes\MetaLever\Composite
 * @since      27.11.0
 */

namespace Linked3\Classes\MetaLever\Composite;

if (!defined('ABSPATH')) exit;

class Linked3_Composite_Code_Optimizer implements Linked3_Composite_Lever_Interface
{
    public function id(): string { return 'code_optimizer'; }
    public function label(): string { return __('代码质量优化器', 'linked3'); }
    public function description(): string { return __('评估×压测×校准 — 不拆分, 只优化', 'linked3'); }
    public function level(): string { return 'composite'; }

    public function orchestrated_levers(): array
    {
        return ['meta_evaluation', 'meta_stress_test', 'meta_self_calibration'];
    }

    public function departments(): array
    {
        return [
            'EVAL' => ['name' => '评估', 'mission' => '评估代码质量, 找出优化点', 'lever' => 'meta_evaluation'],
            'STRESS' => ['name' => '压测', 'mission' => '极端场景下的稳定性测试', 'lever' => 'meta_stress_test'],
            'CAL' => ['name' => '校准', 'mission' => '自我评分+优化建议', 'lever' => 'meta_self_calibration'],
        ];
    }

    public function sla_contracts(): array
    {
        return [
            'EVAL→STRESS' => '评估输出优化点清单 → 压测验证',
            'STRESS→CAL' => '压测通过 → 校准评分+建议',
        ];
    }

    public function evolution_cycle(): array
    {
        return [
            'G1' => '评估: 代码结构、命名规范、复杂度、重复度',
            'G2' => '压测: 高并发、大数据量、异常输入下的行为',
            'G3' => '校准: 质量评分+具体优化建议(不拆分, 只优化)',
        ];
    }

    public function system_prompt(): string
    {
        return <<<PROMPT
## 代码质量优化器 (Code Quality Optimizer)

针对代码模块进行三步质量优化, 不拆分文件, 只优化内部质量:

### Step 1: 评估 (Evaluation)
- 结构评估: 类的职责是否单一? 方法是否过长?
- 命名评估: 变量名/方法名是否表意清晰?
- 复杂度评估: 嵌套深度? 圈复杂度?
- 重复度评估: 是否有重复代码? 可否提取公共方法?

### Step 2: 压力测试 (Stress Test)
- 高并发: 多个请求同时调用会怎样?
- 大数据量: 输入超过预期会怎样?
- 异常输入: 空值/null/超长字符串会怎样?
- 依赖断裂: 被依赖的模块不存在会怎样?

### Step 3: 校准 (Calibration)
- 质量评分: 0-100分 (结构30 + 命名20 + 复杂度25 + 重复25)
- 优化建议: 3条可执行的具体优化 (不拆分文件)
  - 如: 提取公共方法、添加类型声明、减少嵌套
- 置信度: 高/中/低

**输出: 质量评分 + 优化建议清单**
PROMPT;
    }

    public function scene_tags(): array
    {
        return ['code_review', 'optimization', 'quality', 'maintenance'];
    }
}
