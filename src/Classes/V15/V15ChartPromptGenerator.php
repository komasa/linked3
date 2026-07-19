<?php

declare(strict_types=1);
/**
 * V15 Chart Prompt Generator — 基于 V15 ChartDNA 30 图示生成图片提示词。
 *
 * v5.3.3 新增:
 *   - 根据 topic + V15 8 维度上下文, 调用 AI 生成多个图示的图片生成提示词
 *   - 每个提示词独立可复制 (用于 Midjourney/DALL-E/Stable Diffusion)
 *   - 支持按 category 筛选 (结构关系/流程时序/数据分析/战略分析/其他)
 *   - 提示词可同步到云模板视觉提示词 (pipeline_template: visual)
 *
 * @package Linked3
 * @subpackage Classes\V15
 */

namespace Linked3\Classes\V15;

use Linked3\Classes\Core\AIDispatcher;



if (!defined('ABSPATH')) {
    exit;
}
final class V15ChartPromptGenerator
{
    /**
     * 生成图示提示词。
     *
     * @param string $topic       主题
     * @param array  $v15_context V15 8 维度上下文
     * @param array  $opts {
     *     @type array  $chart_codes 要生成的图示 DNA 代码列表 (如 ['D01','D08']), 空则按 category 自动选
     *     @type string $category    按分类筛选 (结构关系/流程时序/数据分析/战略分析/其他)
     *     @type int    $count       生成数量 (当 chart_codes 为空时, 从 category 选 N 个)
     *     @type int    $user_id     用户 ID
     * }
     * @return array {
     *     @type array  $prompts 每个图示的提示词 [{dna_code, chart_name, prompt, category}, ...]
     *     @type string $usage
     *     @type string $provider
     * }
     * @throws \RuntimeException
     */
    public function generate($topic, array $v15_context = [], array $opts = []) : mixed {
        $chart_codes = $opts['chart_codes'] ?? [];
        $category = $opts['category'] ?? '';
        $count = max(1, min(10, (int) ($opts['count'] ?? 3)));
        $user_id = $opts['user_id'] ?? get_current_user_id();

        // 1. 取 ChartDNA 索引
        $all_charts = $this->get_chart_dna_index();

        // 2. 筛选目标图示
        $targets = [];
        if (!empty($chart_codes)) {
            foreach ($all_charts as $c) {
                if (in_array($c['dna_code'], $chart_codes, true)) {
                    $targets[] = $c;
                }
            }
        } elseif (!empty($category)) {
            foreach ($all_charts as $c) {
                if ($c['category'] === $category) {
                    $targets[] = $c;
                }
            }
            $targets = array_slice($targets, 0, $count);
        } else {
            // 默认取 5 个最常用的
            $default_codes = ['D18', 'D08', 'D03', 'D19', 'D21']; // 知识卡片/流程/思维导图/金字塔/矩阵
            foreach ($all_charts as $c) {
                if (in_array($c['dna_code'], $default_codes, true)) {
                    $targets[] = $c;
                }
            }
            $targets = array_slice($targets, 0, $count);
        }

        if (empty($targets)) {
            throw new \RuntimeException('未找到匹配的图示, 请检查 chart_codes 或 category 参数');
        }

        // 3. 构建 V15 上下文占位符
        $ctx = $this->build_v15_context($v15_context, ['topic' => $topic]);

        // 4. 构建 AI 提示词 — 一次性生成所有图示
        $chart_list = '';
        foreach ($targets as $i => $t) {
            $chart_list .= sprintf(
                "%d. %s (%s) — %s\n   用途: %s\n   基础模板: %s\n",
                $i + 1,
                $t['dna_code'],
                $t['chart_name_zh'],
                $t['chart_name_en'],
                $t['use_case'],
                $t['prompt_template']
            );
        }

        $prompt = $this->build_prompt($topic, $ctx, $chart_list, $targets);

        // 5. 调用 AI
        $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';

        try { // v19.3.0: AI 调用容错
        $result = AIDispatcher::instance()->chat(
            [['role' => 'user', 'content' => $prompt]],
            [
                'provider'    => $provider,
                'model'       => $model,
                'temperature' => 0.7,
                'max_tokens'  => count($targets) * 400 + 500,
                'module'      => 'visual',
                'user_id'     => $user_id,
            ],
            ['fallback_providers' => ['deepseek', 'zhipu']]
        );
        } catch (\Throwable $e) {
            return new \WP_Error('ai_failed', 'AI 调用失败: ' . $e->getMessage());
        }

        // 6. 解析 AI 输出 (期望 JSON 数组)
        $prompts = $this->parse_chart_prompts($result['content'] ?? '', $targets);

        return [
            'prompts'  => $prompts,
            'usage'    => $result['usage'] ?? [],
            'provider' => $result['provider'] ?? $provider,
            'model'    => $result['model'] ?? $model,
        ];
    }

    /**
     * 构建 AI 提示词。
     */
    private function build_prompt($topic, array $ctx, $chart_list, array $targets) : mixed     {
        $count = count($targets);
        return sprintf(
            "你是一位 V15 视觉提示词工程师, 精通 30 种图示 (ChartDNA) 的图片生成提示词。\n\n" .
            "【任务】为以下主题生成 %d 张图示的图片生成提示词, 可直接用于 Midjourney/DALL-E/Stable Diffusion。\n\n" .
            "【视觉系统 V15 8 维度】\n" .
            "- 品牌: %s\n- 创作者签名: %s\n- 色彩体系: %s\n- 风格调性: %s\n" .
            "- 文化背景: %s\n- 发布平台: %s\n- 信息密度: %s\n- 产品类型: %s\n\n" .
            "【主题】%s\n\n" .
            "【需要生成的图示清单】\n%s\n" .
            "【提示词生成要求】\n" .
            "1. 输出英文提示词 (Midjourney/DALL-E/Stable Diffusion 通用格式)\n" .
            "2. 每个提示词包含: 画面主体描述 + 构图布局 + 色彩方案 + 光影氛围 + 风格参考 + 细节质感\n" .
            "3. 色彩方案必须与 %s 一致\n" .
            "4. 画面风格必须符合 %s 调性\n" .
            "5. 每个图示必须有自己的视觉特征 (不能千篇一律)\n" .
            "6. 信息密度按 %s 调整 (节点数)\n\n" .
            "【输出格式 — 严格遵守】\n" .
            "返回 JSON 数组, 每个元素:\n" .
            "{\"dna_code\":\"D01\",\"chart_name\":\"架构图\",\"prompt\":\"完整英文提示词\"}\n\n" .
            "只返回 JSON 数组, 不要 markdown 代码块标记, 不要额外说明。",
            $count,
            $ctx['brand'], $ctx['signature'], $ctx['color'], $ctx['mood'],
            $ctx['culture'], $ctx['platform'], $ctx['density'], $ctx['product_type'],
            $topic,
            $chart_list,
            $ctx['color'], $ctx['mood'], $ctx['density']
        );
    }

    /**
     * 解析 AI 输出的图示提示词 JSON。
     */
    private function parse_chart_prompts($raw, array $targets) : mixed {
        if (empty($raw)) return [];

        $text = trim($raw);
        // 去 markdown 包裹
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            // 提取第一个 JSON 数组
            if (preg_match('/\[[\s\S]*\]/', $text, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }

        if (!is_array($decoded)) {
            // 解析失败: 为每个 target 生成基础提示词
            return array_map(fn($t) => [
                    'dna_code'   => $t['dna_code'],
                    'chart_name' => $t['chart_name_zh'],
                    'category'   => $t['category'],
                    'prompt'     => $t['prompt_template'] . ' (AI 解析失败, 使用基础模板)',
                    'raw'        => '',
                ], $targets);
        }

        // 标准化
        $out = [];
        $by_code = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) continue;
            $code = $item['dna_code'] ?? '';
            if ($code) $by_code[$code] = $item;
        }

        foreach ($targets as $t) {
            $item = $by_code[$t['dna_code']] ?? [];
            $out[] = [
                'dna_code'   => $t['dna_code'],
                'chart_name' => $t['chart_name_zh'],
                'category'   => $t['category'],
                'prompt'     => $item['prompt'] ?? $t['prompt_template'],
                'raw'        => $raw,
            ];
        }
        return $out;
    }

    /**
     * 取 ChartDNA 索引 (30 种图示)。
     */
    public function get_chart_dna_index()
    : array {
        // 内联 30 种图示 (避免依赖 DB 表, v5.3.3 简化)
        return [
            ['dna_code' => 'D01', 'chart_name_zh' => '架构图', 'chart_name_en' => 'Architecture', 'category' => '结构关系', 'use_case' => '系统架构展示', 'prompt_template' => 'Draw a system architecture diagram showing component hierarchy'],
            ['dna_code' => 'D02', 'chart_name_zh' => '框架图', 'chart_name_en' => 'Framework', 'category' => '结构关系', 'use_case' => '方法论框架', 'prompt_template' => 'Draw a framework diagram showing methodology 4 elements'],
            ['dna_code' => 'D03', 'chart_name_zh' => '思维导图', 'chart_name_en' => 'MindMap', 'category' => '结构关系', 'use_case' => '知识体系展开', 'prompt_template' => 'Draw a mind map with central topic expanding outward'],
            ['dna_code' => 'D04', 'chart_name_zh' => '韦恩图', 'chart_name_en' => 'Venn', 'category' => '结构关系', 'use_case' => '集合交集分析', 'prompt_template' => 'Draw a Venn diagram showing 3 set intersections'],
            ['dna_code' => 'D05', 'chart_name_zh' => 'ER图', 'chart_name_en' => 'ER', 'category' => '结构关系', 'use_case' => '实体关系建模', 'prompt_template' => 'Draw an ER diagram showing entity relationships'],
            ['dna_code' => 'D06', 'chart_name_zh' => '网络图', 'chart_name_en' => 'Network', 'category' => '结构关系', 'use_case' => '社交网络/拓扑', 'prompt_template' => 'Draw a network graph showing node connections'],
            ['dna_code' => 'D07', 'chart_name_zh' => '树形图', 'chart_name_en' => 'Tree', 'category' => '结构关系', 'use_case' => '组织结构/分类', 'prompt_template' => 'Draw a tree diagram showing hierarchy'],
            ['dna_code' => 'D08', 'chart_name_zh' => '流程图', 'chart_name_en' => 'Flowchart', 'category' => '流程时序', 'use_case' => '操作流程', 'prompt_template' => 'Draw a flowchart showing step transitions'],
            ['dna_code' => 'D09', 'chart_name_zh' => '时序图', 'chart_name_en' => 'Sequence', 'category' => '流程时序', 'use_case' => '交互时序', 'prompt_template' => 'Draw a sequence diagram showing message order'],
            ['dna_code' => 'D10', 'chart_name_zh' => '类图', 'chart_name_en' => 'ClassDiagram', 'category' => '流程时序', 'use_case' => 'UML类关系', 'prompt_template' => 'Draw a UML class diagram'],
            ['dna_code' => 'D11', 'chart_name_zh' => '甘特图', 'chart_name_en' => 'Gantt', 'category' => '流程时序', 'use_case' => '项目排期', 'prompt_template' => 'Draw a Gantt chart showing task timeline'],
            ['dna_code' => 'D12', 'chart_name_zh' => '泳道图', 'chart_name_en' => 'Swimlane', 'category' => '流程时序', 'use_case' => '跨角色流程', 'prompt_template' => 'Draw a swimlane diagram showing roles'],
            ['dna_code' => 'D13', 'chart_name_zh' => '时间线', 'chart_name_en' => 'Timeline', 'category' => '流程时序', 'use_case' => '发展历程', 'prompt_template' => 'Draw a timeline showing key milestones'],
            ['dna_code' => 'D14', 'chart_name_zh' => '图表', 'chart_name_en' => 'Chart', 'category' => '数据分析', 'use_case' => '数据可视化', 'prompt_template' => 'Draw a data chart'],
            ['dna_code' => 'D15', 'chart_name_zh' => '科研绘图', 'chart_name_en' => 'Scientific', 'category' => '数据分析', 'use_case' => '实验数据', 'prompt_template' => 'Draw a scientific data plot'],
            ['dna_code' => 'D16', 'chart_name_zh' => '技术路线图', 'chart_name_en' => 'TechRoadmap', 'category' => '数据分析', 'use_case' => '技术演进', 'prompt_template' => 'Draw a technology roadmap'],
            ['dna_code' => 'D17', 'chart_name_zh' => '信息图', 'chart_name_en' => 'Infographic', 'category' => '数据分析', 'use_case' => '信息传播', 'prompt_template' => 'Draw an infographic with data + visuals'],
            ['dna_code' => 'D18', 'chart_name_zh' => '知识卡片', 'chart_name_en' => 'KnowledgeCard', 'category' => '数据分析', 'use_case' => '知识浓缩', 'prompt_template' => 'Draw a knowledge card with key info + visual'],
            ['dna_code' => 'D19', 'chart_name_zh' => '金字塔', 'chart_name_en' => 'Pyramid', 'category' => '数据分析', 'use_case' => '层次优先级', 'prompt_template' => 'Draw a pyramid showing hierarchy priority'],
            ['dna_code' => 'D20', 'chart_name_zh' => '鱼骨图', 'chart_name_en' => 'Fishbone', 'category' => '数据分析', 'use_case' => '因果分析', 'prompt_template' => 'Draw a fishbone diagram analyzing root causes'],
            ['dna_code' => 'D21', 'chart_name_zh' => '矩阵', 'chart_name_en' => 'Matrix', 'category' => '数据分析', 'use_case' => '多维对比', 'prompt_template' => 'Draw a matrix for 2D comparison'],
            ['dna_code' => 'D22', 'chart_name_zh' => '堆叠图', 'chart_name_en' => 'Stacked', 'category' => '数据分析', 'use_case' => '构成分析', 'prompt_template' => 'Draw a stacked chart showing proportions'],
            ['dna_code' => 'D23', 'chart_name_zh' => 'SWOT', 'chart_name_en' => 'SWOT', 'category' => '战略分析', 'use_case' => '优劣势分析', 'prompt_template' => 'Draw a SWOT analysis diagram'],
            ['dna_code' => 'D24', 'chart_name_zh' => 'PEST', 'chart_name_en' => 'PEST', 'category' => '战略分析', 'use_case' => '宏观环境', 'prompt_template' => 'Draw a PEST analysis diagram'],
            ['dna_code' => 'D25', 'chart_name_zh' => '用户画像', 'chart_name_en' => 'Persona', 'category' => '战略分析', 'use_case' => '目标用户', 'prompt_template' => 'Draw a user persona profile'],
            ['dna_code' => 'D26', 'chart_name_zh' => '用户故事', 'chart_name_en' => 'UserStory', 'category' => '战略分析', 'use_case' => '需求描述', 'prompt_template' => 'Draw a user story map'],
            ['dna_code' => 'D27', 'chart_name_zh' => '精益画布', 'chart_name_en' => 'LeanCanvas', 'category' => '战略分析', 'use_case' => '商业模式', 'prompt_template' => 'Draw a lean canvas'],
            ['dna_code' => 'D28', 'chart_name_zh' => '矩形树图', 'chart_name_en' => 'Treemap', 'category' => '其他', 'use_case' => '层级占比', 'prompt_template' => 'Draw a treemap'],
            ['dna_code' => 'D29', 'chart_name_zh' => '简易流程', 'chart_name_en' => 'SimpleFlowchart', 'category' => '其他', 'use_case' => '封面钩子', 'prompt_template' => 'Draw a simple flowchart'],
            ['dna_code' => 'D30', 'chart_name_zh' => '辐射图', 'chart_name_en' => 'Radial', 'category' => '其他', 'use_case' => '总结升华', 'prompt_template' => 'Draw a radial diagram with center expanding outward'],
        ];
    }

    /**
     * 按 category 分组返回图示索引 (前端 UI 用)。
     */
    public function get_chart_dna_grouped() : mixed     {
        $all = $this->get_chart_dna_index();
        $grouped = [];
        foreach ($all as $c) {
            $cat = $c['category'];
            if (!isset($grouped[$cat])) $grouped[$cat] = [];
            $grouped[$cat][] = $c;
        }
        return $grouped;
    }

    /**
     * 构建 V15 8 维度上下文。
     */
    private function build_v15_context(array $user_ctx, array $extra = [])
    {
        return array_merge([
            'brand'        => $user_ctx['brand'] ?? '我的品牌',
            'signature'    => $user_ctx['signature'] ?? '',
            'color'        => $user_ctx['color'] ?? '#1B3A5C/#C8403C/#E8E4DD/#C9A961',
            'mood'         => $user_ctx['mood'] ?? '冷静理性',
            'culture'      => $user_ctx['culture'] ?? '中国大陆一二线城市/28-45岁/企业主与中产',
            'platform'     => $user_ctx['platform'] ?? '小红书',
            'density'      => $user_ctx['density'] ?? '标准16节点',
            'product_type' => $user_ctx['product_type'] ?? '单图Card',
            'topic'        => $extra['topic'] ?? '',
        ], $extra, $user_ctx);
    }

    /**
     * v5.3.4: 生成单个图示脚本 (分段模式, AI 只生成 1 个, JSON 极小)。
     *
     * 每个图示脚本包含 6 个字段 (不只是 prompt):
     *   - description_zh: 中文画面说明 (50字内)
     *   - prompt_en: 英文图片生成提示词 (Midjourney/DALL-E 格式)
     *   - caption_zh: 图上中文文案 (20字内)
     *   - design_notes: 设计要点 (色彩/构图/风格, 30字内)
     *   - placement: 适用位置 (文章开头/段落间/结尾)
     *   - dna_code + chart_name + category
     *
     * @param string $topic
     * @param array  $chart_item {dna_code, chart_name_zh, chart_name_en, category, use_case, prompt_template}
     * @param array  $v15_context V15 8 维度
     * @param array  $opts {user_id}
     * @return array{script:array, usage:array, provider:string, model:string}
     * @throws \RuntimeException
     */
    public function generate_single($topic, array $chart_item, array $v15_context = [], array $opts = [])
    {
        $ctx = $this->build_v15_context($v15_context, ['topic' => $topic]);

        $prompt = sprintf(
            "你是一位 V15 视觉系统工程师 + 信息图设计师。请只为以下单个图示生成完整的图示脚本。\n\n" .
            "【视觉系统 V15 8 维度】\n- 品牌: %s\n- 签名: %s\n- 色彩: %s\n- 调性: %s\n- 文化: %s\n- 平台: %s\n- 密度: %s\n- 产品类型: %s\n\n" .
            "【主题】%s\n\n" .
            "【图示信息】\n- DNA 代码: %s\n- 图示名: %s (%s)\n- 分类: %s\n- 用途: %s\n- 基础模板: %s\n\n" .
            "【输出格式 — 严格遵守】\n" .
            "返回 JSON 对象:\n" .
            "{\"dna_code\":\"%s\",\"chart_name\":\"%s\",\"description_zh\":\"中文画面说明(50字内)\",\"prompt_en\":\"完整英文图片生成提示词(Midjourney/DALL-E 格式)\",\"caption_zh\":\"图上中文文案(20字内)\",\"design_notes\":\"设计要点(色彩/构图/风格,30字内)\",\"placement\":\"适用位置(如:文章开头/段落间/结尾)\"}\n\n" .
            "要求:\n" .
            "1. prompt_en 必须融入色彩 %s 和调性 %s\n" .
            "2. description_zh 要具体可执行\n" .
            "3. caption_zh 要精炼有力\n" .
            "4. design_notes 要点明色彩/构图/风格\n\n" .
            "只返回 JSON 对象, 不要 markdown, 不要说明。",
            $ctx['brand'], $ctx['signature'], $ctx['color'], $ctx['mood'],
            $ctx['culture'], $ctx['platform'], $ctx['density'], $ctx['product_type'],
            $topic,
            $chart_item['dna_code'], $chart_item['chart_name_zh'], $chart_item['chart_name_en'],
            $chart_item['category'], $chart_item['use_case'], $chart_item['prompt_template'],
            $chart_item['dna_code'], $chart_item['chart_name_zh'],
            $ctx['color'], $ctx['mood']
        );

        $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';

        try { // v19.3.0: AI 调用容错
        $result = AIDispatcher::instance()->chat(
            [['role' => 'user', 'content' => $prompt]],
            [
                'provider'    => $provider,
                'model'       => $model,
                'temperature' => 0.7,
                'max_tokens'  => 700,
                'module'      => 'visual',
                'user_id'     => $opts['user_id'] ?? get_current_user_id(),
            ],
            ['fallback_providers' => ['deepseek', 'zhipu']]
        );
        } catch (\Throwable $e) {
            return new \WP_Error('ai_failed', 'AI 调用失败: ' . $e->getMessage());
        }

        $script = $this->parse_single_chart_script($result['content'] ?? '', $chart_item);

        return [
            'script'  => $script,
            'usage'   => $result['usage'] ?? [],
            'provider'=> $result['provider'] ?? $provider,
            'model'   => $result['model'] ?? $model,
        ];
    }

    /**
     * v5.3.4: 解析单个图示脚本 JSON (容错 + 兜底)。
     */
    private function parse_single_chart_script($raw, array $chart_item)
    {
        $default = [
            'dna_code'      => $chart_item['dna_code'],
            'chart_name'    => $chart_item['chart_name_zh'],
            'category'      => $chart_item['category'],
            'description_zh'=> $chart_item['use_case'],
            'prompt_en'     => $chart_item['prompt_template'],
            'caption_zh'    => '',
            'design_notes'  => '',
            'placement'     => '段落间',
        ];

        if (empty($raw)) return $default;
        $text = trim($raw);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }
        if (!is_array($decoded)) return $default;

        return [
            'dna_code'      => $decoded['dna_code'] ?? $chart_item['dna_code'],
            'chart_name'    => $decoded['chart_name'] ?? $chart_item['chart_name_zh'],
            'category'      => $decoded['category'] ?? $chart_item['category'],
            'description_zh'=> $decoded['description_zh'] ?? $chart_item['use_case'],
            'prompt_en'     => $decoded['prompt_en'] ?? $chart_item['prompt_template'],
            'caption_zh'    => $decoded['caption_zh'] ?? '',
            'design_notes'  => $decoded['design_notes'] ?? '',
            'placement'     => $decoded['placement'] ?? '段落间',
        ];
    }
}
