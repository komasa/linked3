<?php

declare(strict_types=1);
/**
 * Cognitive Operating System — 引擎门面 (v20.0)
 *
 * COS 的统一入口 — 整合核心引擎、存储层、杠杆模块。
 * 对外暴露:
 *   - evolve(): 运行完整三代演化
 *   - run_lever(): 调用单个杠杆
 *   - chain_levers(): 串联多个杠杆
 *   - get_skill(): 获取固化的 Skill
 *   - get_archive(): 获取演化归档
 *
 * 变异-绞杀流程:
 *   本类是 COS 的门面, 同时也是 MetaLever 的"绞杀者"。
 *   旧的 MetaLever Registry 仍然存在 (向后兼容),
 *   但新的决策路径优先走 COS 引擎。
 *   当 COS 引擎稳定后, MetaLever 的 enhance_prompt() 将被废弃。
 *
 * @package Linked3\Classes\CognitiveOS
 * @since   20.0
 */

namespace Linked3\Classes\CognitiveOS;

use Linked3\Classes\CognitiveOS\Core\COSAxioms;
use Linked3\Classes\CognitiveOS\Core\COSDepartments;
use Linked3\Classes\CognitiveOS\Core\COSSLA;
use Linked3\Classes\CognitiveOS\Core\COSEvolution;
use Linked3\Classes\CognitiveOS\Storage\COSSkillLibrary;
use Linked3\Classes\CognitiveOS\Storage\COSEvolutionArchive;



if (!defined('ABSPATH')) {
    exit;
}

// 显式加载 COS 核心类 (自动加载器无法解析无命名空间路径)
require_once __DIR__ . '/Core/class-linked3-cos-axioms.php';
require_once __DIR__ . '/Core/class-linked3-cos-departments.php';
require_once __DIR__ . '/Core/class-linked3-cos-sla.php';
require_once __DIR__ . '/Core/class-linked3-cos-evolution.php';
require_once __DIR__ . '/Storage/class-linked3-cos-skill-library.php';
require_once __DIR__ . '/Storage/class-linked3-cos-evolution-archive.php';
// v20.4-fix17: 加载复合杠杆注册表
require_once __DIR__ . '/../MetaLever/Composite/interface-linked3-composite-lever.php';
require_once __DIR__ . '/../MetaLever/Composite/class-linked3-composite-lever-registry.php';
/**
 * Class COSEngine
 *
 * COS 引擎门面 — 统一入口。
 */
class COSEngine
{
    /** @var COSEngine|null */
    private static $instance = null;

    /**
     * v20.4-fix2: 版本探针 — 用于验证部署的代码版本。
     * 如果此方法不存在或返回值不匹配, 说明旧代码仍在运行。
     */
    const COS_PATCH_VERSION = 'v27.17.9';

        public static function patch_version() : mixed { return COSEngineUtils::patch_version(); }

    /**
     * 单例访问器。
     *
     * @return self
     */
        public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 运行完整三代演化。
     *
     * @param string $problem  待解决的问题
     * @param array  $context  上下文
     * @return array 演化结果
     */
        public function evolve(string $problem, array $context = []): array
    {
        return \Linked3\Classes\CognitiveOS\Core\COSEvolution::evolve($problem, $context);
    }

    /**
     * v20.4-fix8: 运行单代演化 (异步模式 — 每代一个 AJAX 请求)。
     */
        public function evolve_single_gen(string $problem, array $context, string $gen, ?array $baseline): array
    {
        return \Linked3\Classes\CognitiveOS\Core\COSEvolution::run_generation($gen, $problem, $context, $baseline);
    }

    /**
     * v20.4-fix8: 最终结晶 — G3 完成后调用, 保存 Skill。
     */
        public function finalize_evolution(string $problem, array $context, array $final_mvp, array $generations_summary): array
    {
        $this->crystallize_skill($problem, $context, $final_mvp, $generations_summary);
        return ['final_status' => 'success', 'final_mvp' => $final_mvp];
    }

    /**
     * v20.4-fix8: 提取结晶逻辑为独立方法。
     */
    private function crystallize_skill(string $problem, array $context, array $mvp, array $generations_summary): void
    {
        $domain_slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($context['domain'] ?? 'general')) ?: 'general';
        $short_hash = substr(md5($problem . microtime(true)), 0, 6);
        $skill_name = $domain_slug . '_skill_' . $short_hash;

        $rules = COSDepartments::extract_rules($mvp);

        COSSkillLibrary::save($skill_name, [
            'domain'       => !empty($context['domain']) ? $context['domain'] : 'general',
            'rules'        => $rules,
            'fitness'      => (float) ($mvp['fitness'] ?? 5.0),
            'problem'      => $problem,
            'mvp_id'       => $mvp['id'] ?? '',
            'mvp_approach' => $mvp['approach'] ?? '',
            'mvp_steps'    => $mvp['steps'] ?? '',
            'mvp_scores'   => [
                'risk'        => $mvp['score']['risk'] ?? 0,
                'feasibility' => $mvp['score']['feasibility'] ?? 0,
                'novelty'     => $mvp['score']['novelty'] ?? 0,
            ],
            'generations_summary' => $generations_summary,
            'created_at'   => current_time('mysql'),
        ]);
        // G4.4: Trigger fitness recalculation after new SKILL generation
        if (class_exists("\\Linked3\\Classes\\MetaLever\\MetaLeverFitnessTracker")) {
            \Linked3\Classes\MetaLever\MetaLeverFitnessTracker::recalculate();
        }
    }

    /**
     * 调用单个杠杆 — v20.4: 真实调用 AI, 返回分析输出而非空 trace。
     *
     * @param string $lever_id  杠杆 ID (如 'meta_learning', 'logic')
     * @param array  $input     输入数据 { problem, approach, steps, ... }
     * @return array { lever, status, trace, analysis, enhanced_prompt }
     */
    public function run_lever(string $lever_id, array $input): array
    {
        // v20.4-fix22: 先检查是否是复合杠杆
        $system_prompt = '';
        $trace_field = $lever_id . '_trace';
        $lever_label = $lever_id;

        if (class_exists('\\Linked3\\Classes\\MetaLever\\Composite\\CompositeLeverRegistry')) {
            $composite = \Linked3\Classes\MetaLever\Composite\CompositeLeverRegistry::get($lever_id);
            if ($composite) {
                $system_prompt = $composite->system_prompt();
                $lever_label = $composite->label();
                $trace_field = $lever_id . '_trace';
            }
        }

        // 如果不是复合杠杆, 桥接到基础 MetaLever Registry
        if (empty($system_prompt)) {
            if (!class_exists('\\Linked3\\Classes\\MetaLever\\MetaLeverRegistry')) {
                return ['lever' => $lever_id, 'status' => 'not_found'];
            }

            $lever = \Linked3\Classes\MetaLever\MetaLeverRegistry::get($lever_id);
            if (!$lever) {
                return ['lever' => $lever_id, 'status' => 'not_found'];
            }

            $system_prompt = $lever->system_prompt();
            $trace_field   = $lever->trace_field();
            $lever_label   = $lever->label();
        }

        // v20.4: 构造用户消息, 真实调用 AI 做认知审查
        $problem   = $input['problem']   ?? '';
        $approach  = $input['approach']  ?? '';
        $steps     = $input['steps']     ?? '';
        $prev_analysis = $input['accumulated_analysis'] ?? '';

        $user_msg = "## 待审查的认知方案\n\n";
        $user_msg .= "### 原始问题\n{$problem}\n\n";
        $user_msg .= "### 最优方案 (MVP)\n{$approach}\n\n";
        if (!empty($steps)) {
            $user_msg .= "### 执行步骤\n{$steps}\n\n";
        }
        if (!empty($prev_analysis)) {
            // v20.4-fix12: 截断前序分析, 避免累积过长导致 AI 超时
            // 旧实现把全部前序分析传入, 第 6 个杠杆的 prompt 可能 >5000 字,
            // AI 处理时间 >60s → web server 掐断 → "Failed to fetch"
            $prev_trimmed = mb_strlen($prev_analysis) > 1500
                ? mb_substr($prev_analysis, 0, 700) . "\n...(已截断, 仅保留前序要点)...\n" . mb_substr($prev_analysis, -700)
                : $prev_analysis;
            $user_msg .= "### 前序杠杆的审查结论 (摘要)\n{$prev_trimmed}\n\n";
        }
        $user_msg .= "请运用你的元认知能力, 对上述方案做深度审查。\n\n";
        $user_msg .= "**关键约束 — 差异化审查 (v20.4-fix23):**\n";
        $user_msg .= "- 你的审查必须与前序杠杆的审查结论**显著不同**, 聚焦于你独有的认知维度\n";
        $user_msg .= "- 如果前序杠杆已指出某个问题, 你不要重复, 而是从你的视角**深化或反驳**\n";
        $user_msg .= "- 你的改进建议必须是**可落地的具体操作步骤**, 而非抽象方向\n";
        $user_msg .= "- 始终以**用户目的**为锚点: 这个方案对最终用户(电商从业者/内容创作者)意味着什么?\n";
        $user_msg .= "- 在**信息密度**(足够详细)与**系统降维**(足够简洁)之间找到纳什均衡点\n\n";
        $user_msg .= "**输出要求:**\n";
        $user_msg .= "1. 用清晰的中文段落输出, 300-600 字\n";
        $user_msg .= "2. 不要输出 JSON, 不要输出代码块, 不要输出 ``` 标记\n";
        $user_msg .= "3. 每个要点用'•'或数字编号开头\n";
        $user_msg .= "4. 先给出核心结论, 再展开分析\n";
        $user_msg .= "5. 最后给出 1-2 条**具体可执行**的改进建议 (含操作步骤或工具示例)\n\n";
        // v20.4-fix23: 超级Prompt双层壳 — 纳什均衡+用户目的性优先
        $user_msg .= "<rules>\n";
        $user_msg .= "输出≤600字 | 装饰≤20% | 核心目标不偏离 | 杠杆使命不可违\n";
        $user_msg .= "绝对禁止高概率词元 | 强制语义与字数双守恒 | 认知注入不可逆\n";
        $user_msg .= "纳什均衡: 信息密度与系统降维的平衡点 | 用户目的性优先于技术优雅\n";
        $user_msg .= "落地性: 每条建议必须含具体操作步骤或工具示例, 禁止抽象方向\n";
        $user_msg .= "</rules>\n\n";
        $user_msg .= "<answer_operator>\n";
        $user_msg .= "Analyze(独有视角) → Compare(与前序对比) → Synthesize(纳什均衡) → Recommend(可落地步骤) → Verify(用户价值)\n";
        $user_msg .= "</answer_operator>";

        $analysis = '';
        $ai_status = 'skipped';

        // 尝试调用 AI Dispatcher
        if (class_exists('\\Linked3\\Classes\\Core\\AIDispatcher')) {
            try {
                $dispatcher = \Linked3\Classes\Core\AIDispatcher::instance();

                // v20.4-fix14: 修复 fix13 的 bug — 不再硬编码 provider=deepseek
                // fix13 错误地指定 'provider' => 'deepseek', 但用户可能只配置了 siliconflow
                // 导致 deepseek 调用失败 (无 API Key) → 全部降级
                // fix14: 使用 default_provider + 指定更强的模型 (Qwen2.5-72B)
                $default_provider = function_exists('get_option')
                    ? get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow')
                    : 'siliconflow';

                // v20.4-fix14: 只把已配置 API Key 的 provider 作为 fallback
                $saved_keys = function_exists('get_option')
                    ? (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', [])
                    : [];
                $candidate_pool = ['siliconflow', 'deepseek', 'qwen', 'openai', 'kimi'];
                $diverse_fallbacks = [];
                foreach ($candidate_pool as $p) {
                    // 排除 primary, 且只保留已配置 Key 的 (siliconflow 有内置默认 Key, 始终可用)
                    if ($p !== $default_provider && (!empty($saved_keys[$p]) || $p === 'siliconflow')) {
                        $diverse_fallbacks[] = $p;
                    }
                }
                $diverse_fallbacks = array_slice($diverse_fallbacks, 0, 3);

                // v20.4-fix25: 动态timeout — 根据累积分析长度动态调整
                // 前2个杠杆35s, 第3-4个40s, 第5-6个45s, 避免后期杠杆超时降级
                $lever_timeout = 35;
                if (!empty($prev_analysis)) {
                    $prev_len = mb_strlen($prev_analysis);
                    if ($prev_len > 1200) {
                        $lever_timeout = 45; // 第5-6个杠杆
                    } elseif ($prev_len > 600) {
                        $lever_timeout = 40; // 第3-4个杠杆
                    }
                }

                // v20.4-fix26: 配额耗尽容错 — 捕获Quota exhausted异常, 尝试更轻量模型
                $models_to_try = ['Qwen/Qwen2.5-32B-Instruct', 'Qwen/Qwen2.5-7B-Instruct'];
                $ai_result = null;
                $last_error = '';

                foreach ($models_to_try as $model) {
                    try {
                        $ai_result = $dispatcher->chat(
                            [
                                ['role' => 'system', 'content' => $system_prompt],
                                ['role' => 'user',   'content' => $user_msg],
                            ],
                            [
                                'temperature' => 0.7,
                                'max_tokens'  => 800,
                                'module'      => 'cos_lever',
                                'user_id'     => get_current_user_id(),
                                'timeout'     => $lever_timeout,
                                'model'       => $model,
                            ],
                            [
                                'fallback_providers' => $diverse_fallbacks,
                                'force_bypass_circuit' => true,
                            ]
                        );

                        if (!empty($ai_result['content'])) {
                            $analysis = $ai_result['content'];
                            $ai_status = 'success';
                            break; // 成功则不再尝试其他模型
                        } elseif (!empty($ai_result['error'])) {
                            $last_error = $ai_result['error'];
                            // 如果是配额耗尽, 尝试下一个模型
                            if (strpos($last_error, 'Quota exhausted') !== false) {
                                continue;
                            }
                            // 其他错误直接记录
                            $ai_status = 'error: ' . substr($last_error, 0, 200);
                            break;
                        }
                    } catch (\Throwable $e) {
                        $last_error = $e->getMessage();
                        // 如果是配额耗尽, 尝试下一个模型
                        if (strpos($last_error, 'Quota exhausted') !== false) {
                            continue;
                        }
                        $ai_status = 'error: ' . substr($last_error, 0, 200);
                        break;
                    }
                }

                // v20.4-fix26: 如果所有模型都失败, 记录最后的错误
                if (empty($analysis) && !empty($last_error)) {
                    $ai_status = 'error: ' . substr($last_error, 0, 200);
                }
            } catch (\Throwable $e) {
                $ai_status = 'error: ' . $e->getMessage();
            }
        }

        // 降级: AI 不可用时返回结构化占位 (非空)
        if (empty($analysis)) {
            $analysis = $lever_label . " 审查 (降级模式, AI 未调用):\n";
            $analysis .= "- 审查对象: {$problem}\n";
            $analysis .= "- 方案摘要: " . mb_substr($approach, 0, 200) . "\n";
            $analysis .= "- {$trace_field}: 杠杆已注入, 但 AI 调用失败 ({$ai_status}), 请检查 AI 配置后重试。\n";
            $analysis .= "- 建议: 确认 AI Dispatcher 已配置有效的 API Key。";
        }

        // v20.4-fix13: 清理 AI 输出, 去掉 JSON 代码块和乱码
        $analysis = self::clean_ai_output($analysis);

        return [
            'lever'           => $lever_id,
            'lever_name'      => $lever_label,
            'status'          => 'success',
            'ai_status'       => $ai_status,
            'prompt'          => $system_prompt,
            'trace'           => $trace_field,
            'analysis'        => $analysis,
            'accumulated_analysis' => ($input['accumulated_analysis'] ?? '') . "\n\n--- {$lever_label} ---\n" . $analysis,
        ];
    }

    /**
     * 串联多个杠杆 — v20.4: 真实链式增强, 生成最终增强 prompt。
     *
     * 前一个杠杆的分析输出作为后一个杠杆的输入 (accumulated_analysis),
     * 最终将所有杠杆的审查结论合并为增强后的 system_prompt。
     *
     * @param array  $lever_ids  杠杆 ID 列表
     * @param array  $input      初始输入 { problem, approach, steps }
     * @return array { results, final_enhanced_prompt }
     */
    public function chain_levers(array $lever_ids, array $input): array
    {
        $chain = [];
        $current = $input;
        $accumulated = '';

        foreach ($lever_ids as $lid) {
            $current['accumulated_analysis'] = $accumulated;
            $result = $this->run_lever($lid, $current);
            $chain[] = $result;
            if ($result['status'] === 'success') {
                $accumulated = $result['accumulated_analysis'] ?? ($accumulated . "\n" . ($result['analysis'] ?? ''));
                $current = array_merge($current, $result);
            } else {
                // v20.4-fix10: 单个杠杆失败不中断整条链, 继续后续杠杆 (降级模式)
                // 旧实现 break 会导致一个杠杆超时整条链作废, 与分块串行前端的容错策略一致
                $accumulated .= "\n\n--- " . ($result['lever_name'] ?? $lid) . " (失败, 跳过) ---\n"
                    . ($result['analysis'] ?? ($result['error'] ?? '未知错误'));
            }
        }

        // v20.4: 生成最终增强后的 system_prompt
        // v20.4-fix13: 清理累积分析中的乱码, 确保最终 prompt 干净可读
        $problem  = $input['problem']  ?? '';
        $approach = $input['approach'] ?? '';
        $steps    = $input['steps']    ?? '';

        // v20.4-fix13: 清理累积分析 — 去掉 JSON 代码块、多余空格、重复字符
        $accumulated_clean = self::clean_ai_output($accumulated);

        $enhanced  = "你是一个经过认知操作系统 (COS) 三代演化 + 杠杆链深度审查的专家。\n\n";
        $enhanced .= "<rules>\n";
        $enhanced .= "输出≤3×原始 | 装饰≤20% | 核心目标不偏离 | 杠杆使命不可违\n";
        $enhanced .= "公理刚性：需求必由[信息熵减]+[系统降维]推导 | 证伪至死：风险>8或可行<4直接抹杀\n";
        $enhanced .= "纳什均衡：信息密度与系统降维的平衡点 | 用户目的性优先于技术优雅\n";
        $enhanced .= "落地性：每条建议必须含具体操作步骤或工具示例, 禁止抽象方向\n";
        $enhanced .= "差异化：各杠杆审查结论已去重, 请综合而非重复\n";
        $enhanced .= "</rules>\n\n";
        $enhanced .= "## 原始问题\n{$problem}\n\n";
        $enhanced .= "## 最优方案 (MVP)\n{$approach}\n\n";
        if (!empty($steps)) {
            $enhanced .= "## 执行步骤\n{$steps}\n\n";
        }
        $enhanced .= "## 杠杆链审查结论 (经 " . count($chain) . " 个元认知杠杆深度审查)\n";
        $enhanced .= "以下是各杠杆对方案的审查分析, 请在执行时严格遵守其中的修正建议:\n\n";
        $enhanced .= $accumulated_clean;
        $enhanced .= "\n\n## 工作要求\n";
        $enhanced .= "<answer_operator>\n";
        $enhanced .= "Analyze(综合审查) → Synthesize(纳什均衡) → Recommend(可落地步骤) → Verify(用户价值) → Execute\n";
        $enhanced .= "</answer_operator>\n";
        $enhanced .= "1. 基于上述方案和审查结论完成用户的内容生成任务\n";
        $enhanced .= "2. 优先采纳杠杆链审查中指出的修正方向\n";
        $enhanced .= "3. 规避审查中识别的盲区和风险\n";
        $enhanced .= "4. 始终以用户目的为锚点, 输出必须可落地执行\n";
        $enhanced .= "5. 在信息密度与系统降维之间找到纳什均衡点\n";

        return [
            'results'                => $chain,
            'final_enhanced_prompt'  => $enhanced,
            'accumulated_analysis'   => $accumulated_clean,
        ];
    }

    /**
     * v20.4-fix13: 清理 AI 输出 — 去掉 JSON 代码块、多余空格、重复字符。
     * 弱模型 (Qwen2.5-7B) 在生成 JSON 时容易产生乱码, 此方法确保输出干净可读。
     *
     * @param string $text
     * @return string
     */
    public static function clean_ai_output(string $text): string
    {
        if (empty($text)) {
            return '';
        }
        // 去掉 ```json ... ``` 和 ``` ... ``` 代码块标记
        $text = preg_replace('/```json\s*/i', '', $text);
        $text = preg_replace('/```\s*/', '', $text);
        // 去掉行首尾的 { } [ ] (JSON 残留)
        $text = preg_replace('/^[\s{}\[\]]+/', '', $text);
        $text = preg_replace('/[\s{}\[\]]+$/', '', $text);
        // 去掉连续 3 个以上的空格 (乱码特征)
        $text = preg_replace('/ {3,}/', ' ', $text);
        // 去掉连续 3 个以上的换行
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        // 去掉连续 5 个以上的相同字符 (重复乱码)
        $text = preg_replace('/(.)\1{4,}/', '$1$1', $text);
        // 去掉行首的引号残留
        $text = preg_replace('/^\s*["""\']+/m', '', $text);
        return trim($text);
    }

    /**
     * 获取 Skill 库统计。
     *
     * @return array
     */
    public function skill_stats(): array
    {
        return COSSkillLibrary::stats();
    }

    /**
     * 获取演化归档统计。
     *
     * @return array
     */
    public function archive_stats(): array
    {
        return COSEvolutionArchive::stats();
    }

    /**
     * 获取最近的演化快照。
     *
     * @param int $n
     * @return array
     */
    public function recent_evolutions(int $n = 10): array
    {
        return COSEvolutionArchive::recent($n);
    }

    /**
     * 获取 Top-K Skill。
     *
     * @param int $top_k
     * @return array
     */
    public function top_skills(int $top_k = 10): array
    {
        return COSSkillLibrary::top_k($top_k);
    }

    /**
     * COS 系统总览 — 用于 UI 仪表盘。
     *
     * @return array
     */
    public function dashboard_overview(): array
    {
        $skill_stats   = $this->skill_stats();
        $archive_stats = $this->archive_stats();

        return [
            'version'        => '20.0',
            'axioms'         => ['信息熵减', '系统降维'],
            'departments'    => ['FP', 'EX', 'C', 'O', 'A'],
            'generations'    => ['G1', 'G2', 'G3'],
            'skill_count'    => $skill_stats['count'],
            'avg_fitness'    => $skill_stats['avg_fitness'],
            'total_skill_usage' => $skill_stats['total_usage'],
            'evolution_count'   => $archive_stats['count'],
            'evolution_success_rate' => $archive_stats['success_rate'],
            'by_generation'  => $archive_stats['by_generation'],
        ];
    }
}
