<?php

declare(strict_types=1);
/**
 * Cognitive Operating System — 五部门引擎 (v20.4)
 *
 * 五部门协同架构 (FP/EX/C/O/A):
 *   FP 部 (Foundational Premise)  — 定义公理和信息核
 *   EX 部 (Exploration)           — 生成方案种群 (真实 AI 生成 + 结构化降级)
 *   C  部 (Culling)               — 绞杀弱者 (风险>8 或可行<4 直接抹杀)
 *   O  部 (Observation)           — 检测盲区与幻觉
 *   A  部 (Archive)               — 结晶锁定 MVP, 提取固化规则, 物理归档
 *
 * v20.4 修复:
 *   - EX 部: 用真实 AI 调用替代 rand() 占位, 方案携带真实 approach 文本
 *   - A  部: 从 MVP 提取真实固化规则 (rules), 不再是空数组
 *   - AI 不可用时降级为结构化模板, 保证流水线不中断
 *
 * @package Linked3\CognitiveOS\Core
 * @since   20.0
 * @patched 20.4
 */

namespace Linked3\Classes\CognitiveOS\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class COSDepartments
 *
 * 五部门引擎 — 每个部门是一个独立的执行单元。
 */
class COSDepartments
{
    /**
     * FP 部 — 定义公理和信息核。
     *
     * @param array $input { problem: string, context: array }
     * @return array { department, status, deliverables, message }
     */
    public static function fp_department(array $input): array
    {
        $problem = $input['problem'] ?? '';
        $context = $input['context'] ?? [];

        if (empty($problem)) {
            return [
                'department'   => 'FP',
                'status'       => 'fail',
                'deliverables' => [],
                'message'      => __('FP部: 问题描述为空, 无法定义信息核', 'linked3-ai'),
            ];
        }

        // 信息核: 问题 + 上下文 + 约束
        $info_core = [
            'problem'       => $problem,
            'context'       => $context,
            'constraints'   => $context['constraints'] ?? [],
            'success_criteria' => $context['success_criteria'] ?? [],
            'entropy_before' => $context['entropy_before'] ?? 10,
        ];

        return [
            'department'   => 'FP',
            'status'       => 'pass',
            'deliverables' => ['info_core' => $info_core],
            'message'      => __('FP部: 信息核已定义', 'linked3-ai'),
        ];
    }

    /**
     * EX 部 — 生成方案种群 (真实 AI 生成 + 结构化降级)。
     *
     * v20.4: 不再使用 rand() 占位。优先调用 AI 生成真实方案文本,
     * AI 不可用时降级为结构化模板 (仍携带可读 approach, 非空占位)。
     *
     * @param array $input { info_core: array, generation: string, baseline: array }
     * @return array { department, status, deliverables, message }
     */
    public static function ex_department(array $input): array
    {
        $info_core  = $input['info_core'] ?? [];
        $generation = $input['generation'] ?? 'G1';
        $baseline   = $input['baseline'] ?? [];

        $problem = $info_core['problem'] ?? '未定义问题';

        // v20.4-fix9: 大幅减少方案数量, 确保单次 AI 调用在 60 秒内完成
        // G1: 初代涌现 5 个方案 (原 10)
        // G2: 重组变异 3 个 (原 6)
        // G3: 终极坍缩 2 个 (原 3)
        $count = $generation === 'G1' ? 5 : ($generation === 'G2' ? 3 : 2);

        // v20.4: 优先用真实 AI 生成方案
        $variants = self::generate_variants_via_ai($problem, $generation, $count, $baseline, $info_core);

        // AI 不可用或返回不足时, 降级为结构化模板
        if (count($variants) < 2) {
            $variants = self::generate_variants_fallback($problem, $generation, $count, $baseline);
        }

        return [
            'department'   => 'EX',
            'status'       => 'pass',
            'deliverables' => ['variants' => $variants],
            'message'      => sprintf('EX部: %s 生成 %d 个方案', $generation, count($variants)),
        ];
    }

    /**
     * v20.4: 通过真实 AI 调用生成方案种群。
     *
     * 向 AI 发送结构化 prompt, 要求返回 JSON 数组, 每个元素包含
     * approach (方案描述) / steps (操作步骤) / risk / feasibility / novelty。
     * AI 不可用时返回空数组, 由 fallback 接管。
     *
     * @param string $problem
     * @param string $generation
     * @param int    $count
     * @param array  $baseline  上一代 MVP (G2/G3 用于变异基线)
     * @param array  $info_core
     * @return array
     */
    private static function generate_variants_via_ai(string $problem, string $generation, int $count, array $baseline, array $info_core): array
    {
        // 检查 AI Dispatcher 是否可用
        if (!class_exists('\Linked3\Classes\Core\AIDispatcher')) {
            return [];
        }

        $domain = $info_core['context']['domain'] ?? '通用';

        // 构建 system prompt — 教 AI 如何生成多样化方案
        $sys_prompt = "你是一位认知操作系统 (COS) 的方案生成专家。"
            . "你的任务是为给定问题生成 {$count} 个**不同思路**的解决方案。\n\n"
            . "要求:\n"
            . "1. 每个方案必须有独立的 approach (方案描述, 50-100字)\n"
            . "2. 每个方案必须有 steps (3-5个可操作步骤, 用分号分隔)\n"
            . "3. 对每个方案给出 risk (风险1-10) / feasibility (可行性1-10) / novelty (创新性1-10) 评分\n"
            . "4. 方案之间思路必须差异化\n"
            . "5. 必须严格输出 JSON 数组格式\n\n"
            . "输出格式 (纯JSON, 不要markdown代码块):\n"
            . '[{"approach":"方案描述","steps":"步骤1;步骤2;步骤3","risk":5,"feasibility":8,"novelty":7}]';

        // 构建 user prompt
        $user_prompt = "问题: {$problem}\n";
        $user_prompt .= "领域: {$domain}\n";
        $user_prompt .= "代际: {$generation} (需生成 {$count} 个方案)\n";

        if (!empty($baseline) && $generation !== 'G1') {
            $baseline_text = $baseline['approach'] ?? '';
            $user_prompt .= "\n上一代 MVP 方案 (作为变异基线, 请在其基础上重组/变异/优化, 但也要有全新思路):\n";
            $user_prompt .= $baseline_text . "\n";
        }

        $user_prompt .= "\n请生成 {$count} 个差异化方案, 严格输出 JSON 数组。";

        $messages = [
            ['role' => 'system', 'content' => $sys_prompt],
            ['role' => 'user',   'content' => $user_prompt],
        ];

        // v20.4-fix15: 降级模型 72B→32B, max_tokens 1200→800, timeout 40→35
        // 72B 太慢 (40-60s), 32B 平衡质量与速度 (15-25s)
        $options = [
            'temperature' => 0.9,
            'max_tokens'  => 800,
            'module'      => 'cos_ex',
            'user_id'     => get_current_user_id(),
            'timeout'     => 35,
            'model'       => 'Qwen/Qwen2.5-32B-Instruct',
        ];
        // v20.4-fix14: 只把已配置 API Key 的 provider 作为 fallback (与 run_lever 一致)
        $default_provider = function_exists('get_option')
            ? get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow')
            : 'siliconflow';
        $saved_keys = function_exists('get_option')
            ? (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', [])
            : [];
        $candidate_pool = ['siliconflow', 'deepseek', 'qwen', 'openai', 'kimi'];
        $diverse_fallbacks = [];
        foreach ($candidate_pool as $p) {
            if ($p !== $default_provider && (!empty($saved_keys[$p]) || $p === 'siliconflow')) {
                $diverse_fallbacks[] = $p;
            }
        }
        $diverse_fallbacks = array_slice($diverse_fallbacks, 0, 2);
        $config = [
            'fallback_providers'    => $diverse_fallbacks,
            'force_bypass_circuit'  => true,
        ];

        try {
            $dispatcher = \Linked3\Classes\Core\AIDispatcher::instance();
            $result = $dispatcher->chat($messages, $options, $config);
            $content = $result['content'] ?? '';
        } catch (\Exception $e) {
            // AI 调用失败, 返回空让 fallback 接管
            return [];
        }

        if (empty($content)) {
            return [];
        }

        // 解析 JSON (容错: 去除 markdown 代码块包裹)
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        $parsed = json_decode($content, true);
        if (!is_array($parsed)) {
            // 尝试提取第一个 JSON 数组
            if (preg_match('/\[.*\]/s', $content, $m)) {
                $parsed = json_decode($m[0], true);
            }
        }

        if (!is_array($parsed) || empty($parsed)) {
            return [];
        }

        // 构建 variants
        $variants = [];
        $i = 1;
        foreach ($parsed as $item) {
            if ($i > $count) {
                break;
            }
            if (!is_array($item) || empty($item['approach'])) {
                continue;
            }

            $approach = (string) $item['approach'];
            $steps    = (string) ($item['steps'] ?? '');
            $risk       = max(1, min(10, (int) ($item['risk'] ?? 5)));
            $feasibility = max(1, min(10, (int) ($item['feasibility'] ?? 6)));
            $novelty    = max(1, min(10, (int) ($item['novelty'] ?? 5)));

            $variants[] = [
                'id'         => $generation . '_V' . str_pad((string)$i, 2, '0', STR_PAD_LEFT),
                'generation' => $generation,
                'approach'   => $approach,
                'steps'      => $steps,
                'score'      => [
                    'risk'       => $risk,
                    'feasibility' => $feasibility,
                    'novelty'    => $novelty,
                ],
                'source'     => 'ai',
            ];
            $i++;
        }

        return $variants;
    }

    /**
     * v20.4: 结构化降级方案生成 (AI 不可用时使用)。
     *
     * 不再使用 rand() 随机分数, 而是基于问题文本生成有意义的差异化方案,
     * 分数基于方案特征启发式计算。
     *
     * @param string $problem
     * @param string $generation
     * @param int    $count
     * @param array  $baseline
     * @return array
     */
    private static function generate_variants_fallback(string $problem, string $generation, int $count, array $baseline): array
    {
        // 6 种差异化思路模板
        $strategies = [
            [
                'tag'  => '数据驱动',
                'desc' => '通过采集和分析平台数据 (搜索量/互动率/转化率) 来定位机会, 用数据验证假设而非凭直觉决策',
                'steps' => '采集平台公开数据;建立选品评分模型;用历史数据回测;筛选Top候选;小批量验证',
                'risk' => 3, 'feas' => 8, 'nov' => 5,
            ],
            [
                'tag'  => '趋势跟随',
                'desc' => '监控热点话题和上升期品类, 借势流量红利, 在趋势早期快速入场获取自然流量',
                'steps' => '监控热点榜单;识别上升品类;分析竞争密度;快速选品上架;借势内容投放',
                'risk' => 6, 'feas' => 7, 'nov' => 6,
            ],
            [
                'tag'  => '差异化定位',
                'desc' => '在红海品类中寻找细分人群或使用场景的空白, 通过差异化卖点避开正面竞争',
                'steps' => '分析竞品盲区;定位细分人群;提炼差异化卖点;设计专属内容;建立心智壁垒',
                'risk' => 4, 'feas' => 6, 'nov' => 8,
            ],
            [
                'tag'  => '供应链优势',
                'desc' => '从自身供应链/成本优势出发, 选择能发挥价格或品质壁垒的品类, 用硬实力碾压',
                'steps' => '盘点供应链资源;计算成本优势;选择壁垒品类;定价策略设计;规模化铺货',
                'risk' => 5, 'feas' => 7, 'nov' => 4,
            ],
            [
                'tag'  => '内容先行',
                'desc' => '先验证内容传播力再决定选品, 用爆款内容反推选品方向, 降低库存风险',
                'steps' => '测试内容选题;分析爆款规律;反推选品方向;小批量备货;内容驱动转化',
                'risk' => 4, 'feas' => 5, 'nov' => 8,
            ],
            [
                'tag'  => 'AI辅助决策',
                'desc' => '用AI工具批量分析商品评论/竞品笔记/搜索词, 从海量非结构化数据中挖掘选品机会',
                'steps' => '采集评论和笔记;AI情感分析;提取痛点关键词;聚类需求图谱;匹配供应链',
                'risk' => 3, 'feas' => 6, 'nov' => 9,
            ],
            [
                'tag'  => '季节性预判',
                'desc' => '基于季节/节日/周期性需求提前1-2个月布局, 在需求爆发前完成内容和库存准备',
                'steps' => '梳理季节日历;预判需求品类;提前备货;节点前内容预热;爆发期收割',
                'risk' => 5, 'feas' => 8, 'nov' => 5,
            ],
            [
                'tag'  => '用户共创',
                'desc' => '通过社群/评论区与目标用户直接对话, 让用户参与选品决策, 降低试错成本',
                'steps' => '建立用户社群;发起选品投票;收集真实需求;小批量试销;用户反馈迭代',
                'risk' => 3, 'feas' => 6, 'nov' => 7,
            ],
            [
                'tag'  => '跨平台迁移',
                'desc' => '从其他平台 (抖音/淘宝/TikTok) 的爆款中筛选适合小红书调性的品类, 跨平台降维',
                'steps' => '监控多平台爆款;筛选调性匹配;分析迁移可行性;适配小红书内容;快速复制',
                'risk' => 4, 'feas' => 7, 'nov' => 6,
            ],
            [
                'tag'  => '长尾深耕',
                'desc' => '放弃头部红海, 聚焦搜索量低但转化率高的长尾品类, 用精准流量获得稳定出单',
                'steps' => '挖掘长尾关键词;评估转化潜力;精选长尾品类;SEO内容布局;持续优化',
                'risk' => 3, 'feas' => 7, 'nov' => 7,
            ],
        ];

        // 根据代际选择策略子集
        if ($generation === 'G1') {
            $selected = array_slice($strategies, 0, min($count, count($strategies)));
        } elseif ($generation === 'G2' && !empty($baseline)) {
            // G2: 在基线基础上重组变异
            $selected = array_slice($strategies, 2, min($count, count($strategies) - 2));
        } else {
            $selected = array_slice($strategies, 0, min($count, count($strategies)));
        }

        $variants = [];
        $i = 1;
        foreach ($selected as $s) {
            $approach = "【{$s['tag']}】针对「{$problem}」, {$s['desc']}。核心动作: {$s['steps']}。";
            $variants[] = [
                'id'         => $generation . '_V' . str_pad((string)$i, 2, '0', STR_PAD_LEFT),
                'generation' => $generation,
                'approach'   => $approach,
                'steps'      => $s['steps'],
                'score'      => [
                    'risk'       => $s['risk'],
                    'feasibility' => $s['feas'],
                    'novelty'    => $s['nov'],
                ],
                'source'     => 'fallback',
            ];
            $i++;
        }

        return $variants;
    }

    /**
     * C 部 — 绞杀弱者 (风险>8 或可行<4 直接抹杀)。
     *
     * @param array $input { variants: array }
     * @return array { department, status, deliverables, message }
     */
    public static function c_department(array $input): array
    {
        $variants = $input['variants'] ?? [];
        $survivors = [];
        $killed    = [];

        foreach ($variants as $v) {
            $score       = $v['score'] ?? [];
            $risk        = (int) ($score['risk'] ?? 0);
            $feasibility = (int) ($score['feasibility'] ?? 0);

            // 证伪至死: 风险>8 或 可行<4 直接抹杀
            if ($risk > 8 || $feasibility < 4) {
                $killed[] = [
                    'id'     => $v['id'],
                    'reason' => sprintf('风险%d>8 或 可行%d<4', $risk, $feasibility),
                ];
            } else {
                $survivors[] = $v;
            }
        }

        $status = !empty($survivors) ? 'pass' : 'kill';

        return [
            'department'   => 'C',
            'status'       => $status,
            'deliverables' => ['survivors' => $survivors, 'killed' => $killed],
            'message'      => sprintf('C部: 绞杀 %d 个, 存活 %d 个', count($killed), count($survivors)),
        ];
    }

    /**
     * O 部 — 盲区与用户观测站 (降维, 脱离行业语境查幻觉)。
     *
     * @param array $input { survivors: array }
     * @return array { department, status, deliverables, message }
     */
    public static function o_department(array $input): array
    {
        $survivors = $input['survivors'] ?? [];

        $blind_spots    = self::detect_blind_spots($survivors);
        $hallucinations = self::check_hallucinations($survivors);

        return [
            'department'   => 'O',
            'status'       => 'pass',
            'deliverables' => ['blind_spots' => $blind_spots, 'hallucinations' => $hallucinations],
            'message'      => sprintf('O部: 检测到 %d 个盲区, %d 个幻觉', count($blind_spots), count($hallucinations)),
        ];
    }

    /**
     * A 部 — 统筹与交付中心 (结晶, 锁定 MVP, 提取固化规则, 物理归档)。
     *
     * v20.4: 从 MVP 的 approach + steps 提取真实固化规则 (rules),
     * 不再是空数组或占位文本。
     *
     * @param array $input { survivors: array, generation: string, problem: string }
     * @return array { department, status, deliverables, message }
     */
    public static function a_department(array $input): array
    {
        $survivors  = $input['survivors'] ?? [];
        $generation = $input['generation'] ?? 'G1';
        $problem     = $input['problem'] ?? '';

        if (empty($survivors)) {
            return [
                'department'   => 'A',
                'status'       => 'fail',
                'deliverables' => [],
                'message'      => __('A部: 无存活方案, 无法结晶', 'linked3-ai'),
            ];
        }

        // 计算适应度 = sum(score)
        $best = null;
        $best_score = -1;
        foreach ($survivors as $v) {
            $score = array_sum($v['score'] ?? []);
            if ($score > $best_score) {
                $best_score = $score;
                $best = $v;
            }
        }

        // v20.4: 从 MVP 提取真实固化规则
        $rules = self::extract_rules($best);

        // 锁定 MVP
        $mvp = [
            'id'           => $best['id'],
            'generation'   => $generation,
            'problem'      => $problem,
            'approach'     => $best['approach'],
            'steps'        => $best['steps'] ?? '',
            'score'        => $best['score'],
            'fitness'      => $best_score,
            'rules'        => $rules,
            'source'       => $best['source'] ?? 'unknown',
            'locked_at'    => current_time('mysql'),
        ];

        return [
            'department'   => 'A',
            'status'       => 'pass',
            'deliverables' => ['mvp' => $mvp],
            'message'      => sprintf('A部: MVP 已锁定 (%s, 适应度 %d, 规则 %d 条)', $mvp['id'], $mvp['fitness'], count($rules)),
        ];
    }

    /**
     * v20.4: 从 MVP 方案提取固化规则。
     *
     * 将 approach + steps 拆解为可执行的规则列表,
     * 供 system_prompt 注入和后续生成器复用。
     *
     * @param array $mvp
     * @return array 规则字符串数组
     */
    public static function extract_rules(array $mvp): array
    {
        $rules = [];
        $approach = $mvp['approach'] ?? '';
        $steps    = $mvp['steps'] ?? '';

        // 从 steps 提取规则 (分号或换行分隔)
        if (!empty($steps)) {
            $step_arr = preg_split('/[;；\n]+/u', $steps);
            foreach ($step_arr as $idx => $step) {
                $step = trim($step);
                if (mb_strlen($step) >= 2) {
                    $rules[] = sprintf('步骤%d: %s', $idx + 1, $step);
                }
            }
        }

        // 从 approach 提取核心思路作为总纲规则
        if (!empty($approach) && mb_strlen($approach) > 10) {
            // 提取【标签】部分作为策略名
            if (preg_match('/【([^】]+)】/u', $approach, $m)) {
                $rules[] = '策略: ' . $m[1];
            }
            // 截取 approach 前 120 字作为核心思路
            $summary = mb_substr($approach, 0, 120);
            $rules[] = '核心思路: ' . $summary;
        }

        // 如果规则仍为空, 至少返回一条兜底
        if (empty($rules)) {
            $rules[] = '执行方案: ' . mb_substr($approach, 0, 100);
        }

        return $rules;
    }

    /**
     * 盲区检测 — 脱离行业语境, 检查隐性约束。
     */
    private static function detect_blind_spots(array $survivors): array
    {
        $spots = [];
        if (count($survivors) < 3) {
            $spots[] = '存活方案过少 (<3), 可能存在未探索的方案空间';
        }
        // 检查是否所有方案都来自同一思路
        $approaches = array_unique(array_map(function($v) { return $v['approach'] ?? ''; }, $survivors));
        if (count($approaches) < count($survivors) * 0.5) {
            $spots[] = '方案同质化严重, 缺乏多样性';
        }
        $spots[] = '未考虑失败案例的反向验证';
        return $spots;
    }

    /**
     * 幻觉检测 — 检查方案中是否存在脱离实际的假设。
     */
    private static function check_hallucinations(array $survivors): array
    {
        $hallucinations = [];
        foreach ($survivors as $v) {
            $approach = $v['approach'] ?? '';
            // 简单启发式: 检查是否包含"100%"、"绝对"、"一定"等过度自信词汇
            if (preg_match('/(100%|绝对|一定|必然|不可能失败)/u', $approach)) {
                $hallucinations[] = $v['id'] . ': 包含过度自信表述';
            }
        }
        return $hallucinations;
    }
}
