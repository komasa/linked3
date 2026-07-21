<?php

declare(strict_types=1);
/**
 * 视频提示词构建 — 从 VideoGenerator 拆分 (v5.4.0)。
 *
 * 职责:
 *   - V15 8 维度上下文构建
 *   - 占位符替换 (支持 {placeholder} 语法)
 *   - 各模式 (script / frames / outline / scene_segment / frame_segment) 提示词构建
 *
 * 原 VideoGenerator 中 build_v15_context / resolve_placeholders 两个 private
 * 方法 + DEFAULT_PROMPT_TEMPLATE 常量 + generate_frames_script 内联模板
 * 整体迁移至此。内联模板提取为 FRAMES_PROMPT_TEMPLATE 常量, 并修复 DRY 违规:
 * 原代码用 str_replace 手动替换 11 个占位符, 现统一用 resolve_placeholders。
 *
 * @package Linked3
 * @subpackage Classes\Media
 * @since 27.4.1
 */

namespace Linked3\Classes\Media;

if (!defined('ABSPATH')) {
    exit;
}

final class VideoPromptBuilder
{
    /**
     * v5.3.2 默认提示词 — 当无 custom_prompt 时使用。
     * 包含 V15 8 维度占位符,即使无品牌配置也提供结构化引导。
     */
    const DEFAULT_PROMPT_TEMPLATE = <<<'PROMPT'
你是一位专业的短视频脚本编剧,精通 9 页 SOP 分镜结构。请为以下主题生成一个 {duration} 秒的短视频脚本。

【视觉系统 V15 8 维度】
- 品牌: {brand}
- 创作者签名: {signature}
- 色彩体系: {color}
- 风格调性: {mood}
- 文化背景: {culture}
- 发布平台: {platform}
- 信息密度: {density}
- 产品类型: {product_type}

【脚本设计原则 — 写书式学习】
1. 知识颗粒化:每个分镜聚焦一个最小知识点 (5-10 秒)
2. 认知阶梯:从现象→问题→原理→方法→案例,层层递进
3. 视觉锚定:每个分镜必须有具体可执行的画面描述 (能给到剪辑师)
4. 旁白口语化:符合 {platform} 平台观众阅读习惯, 风格 {mood}
5. 字幕精炼:每条字幕 8-15 字, 突出关键词
6. 品牌闭环:开头 3 秒 hook + 结尾 CTA + 品牌色 {color} 元素自然融入

【9 页 SOP 分镜结构】
P01 封面钩子 (3-5s) → P02 问题定义 → P03 原理拆解 → P04 案例佐证 → P05 方法步骤 → P06 常见误区 → P07 进阶技巧 → P08 总结升华 → P09 品牌闭环 (CTA)

主题: {topic}
关键词: {keyword}
品牌: {brand}
色彩: {color}
风格: {mood}
平台: {platform}
时长: {duration} 秒

【输出格式 — 严格遵守】
返回 JSON 对象, 包含 scenes 数组, 每个元素:
{"scene": 序号, "page": "P01-P09", "visual": "画面描述", "narration": "旁白文案", "text": "字幕", "duration": 秒数}

只返回 JSON, 不要 markdown 代码块标记, 不要额外说明文字。
PROMPT;

    /**
     * v5.3.3 分镜图片提示词模式模板 (原 generate_frames_script 内联, 现提取为常量)。
     * DRY 修复: 原 str_replace 手动替换 11 个占位符, 现统一用 resolve_placeholders。
     */
    const FRAMES_PROMPT_TEMPLATE = <<<'PROMPT'
你是一位 V15 视觉系统工程师 + 短视频脚本编剧。请为以下主题生成"分镜图片提示词模式"的视频脚本。

【视觉系统 V15 8 维度】
- 品牌: {brand}
- 创作者签名: {signature}
- 色彩体系: {color}
- 风格调性: {mood}
- 文化背景: {culture}
- 发布平台: {platform}
- 信息密度: {density}
- 产品类型: {product_type}

【主题】{topic}
【时长】{duration} 秒
【分镜数量】{image_count} 个图片分镜 + 对应数量的剧本分镜

【输出结构 — 严格遵守】
返回 JSON 对象, 包含 frames 数组, 每个元素是以下两种类型之一:

1. 图片分镜 (type="image"):
   {"type":"image","index":1,"visual_prompt":"完整英文图片生成提示词 (Midjourney/DALL-E 格式)","duration":5,"description":"画面中文说明"}
   - 图片分镜时长 5-10 秒
   - visual_prompt 必须包含: 画面主体 + 构图 + 色彩 (与 {color} 一致) + 光影 + 风格 (符合 {mood})
   - 必须融入品牌色 {color} 和调性 {mood}

2. 剧本分镜 (type="scene"):
   {"type":"scene","index":2,"script":"剧本/旁白文案 (中文, 口语化, 适合 {platform} 平台)","voiceover":"画外音脚本 (中文, 描述音效/音乐/语气)","duration":8}
   - 剧本分镜时长 8-12 秒
   - script 是角色台词或旁白
   - voiceover 是画外音指导 (音效/音乐/语气提示)

【排列规则】
- 第 1 个必须是 image (封面钩子)
- 之后交替: scene → image → scene → image → ...
- 最后一个必须是 scene (CTA + 品牌闭环)
- 总时长 ≈ {duration} 秒

【示例结构 (60s 视频, 4 图 + 3 剧)】
1. image (5s) — 封面钩子
2. scene (8s) — 开场旁白 + 音效
3. image (5s) — 问题定义画面
4. scene (10s) — 原理讲解 + 配音
5. image (5s) — 方法步骤画面
6. scene (12s) — 案例佐证 + 配音
7. image (5s) — 总结升华画面
8. scene (10s) — CTA + 品牌闭环

只返回 JSON 对象: {"frames":[...]}
不要 markdown 代码块, 不要额外说明。
PROMPT;

    /**
     * v5.3.2: 构建 V15 8 维度上下文 (合并用户输入 + 默认占位)。
     *
     * @param array $user_ctx  用户/品牌提供的上下文
     * @param array $extra     额外占位符 (topic/keyword/duration 等)
     * @return array 完整占位符映射
     */
    public function build_v15_context(array $user_ctx, array $extra = []): mixed
    {
        return array_merge([
            // V15 8 维度
            'brand'        => $user_ctx['brand'] ?? '我的品牌',
            'signature'    => $user_ctx['signature'] ?? '',
            'color'        => $user_ctx['color'] ?? '#1B3A5C/#C8403C/#E8E4DD/#C9A961',
            'mood'         => $user_ctx['mood'] ?? '冷静理性',
            'culture'      => $user_ctx['culture'] ?? '中国大陆一二线城市/28-45岁/企业主与中产',
            'platform'     => $user_ctx['platform'] ?? '抖音',
            'density'      => $user_ctx['density'] ?? '标准16节点',
            'product_type' => $user_ctx['product_type'] ?? '单图Card',
            // 业务字段
            'topic'        => $extra['topic'] ?? '',
            'keyword'      => $extra['keyword'] ?? '',
            'duration'     => $extra['duration'] ?? '60',
            'style'        => $extra['style'] ?? '解说',
            'voice'        => $extra['voice'] ?? '男声',
        ], $extra, $user_ctx);
    }

    /**
     * v5.3.2: 占位符替换 (支持 {placeholder} 语法)。
     *
     * @param string $text
     * @param array  $ctx
     * @return string
     */
    public function resolve_placeholders(string $text, array $ctx): mixed
    {
        foreach ($ctx as $key => $value) {
            $text = str_replace('{' . $key . '}', (string) $value, $text);
        }
        // 清理未匹配的占位符 (避免 {xxx} 残留)
        $text = preg_replace('/\{[a-z_]+\}/i', '', $text);
        return $text;
    }

    // ─── 各模式提示词构建 ─────────────────────────────────────────────

    /**
     * 构建 generate_script 提示词。
     *
     * @return array{prompt:string, max_tokens:int, time_limit:int}
     */
    public function build_script_prompt(string $title, string $content, array $opts = []): array
    {
        $style         = $opts['style'] ?? '解说';
        $duration      = (int) ($opts['duration'] ?? 60);
        $voice         = $opts['voice'] ?? '男声';
        $custom_prompt = $opts['custom_prompt'] ?? '';
        $v15_context   = $opts['v15_context'] ?? [];

        $ctx = $this->build_v15_context($v15_context, [
            'topic'    => $title,
            'keyword'  => $title,
            'duration' => (string) $duration,
            'style'    => $style,
            'voice'    => $voice,
        ]);

        $prompt = !empty($custom_prompt)
            ? $this->resolve_placeholders($custom_prompt, $ctx)
            : $this->resolve_placeholders(self::DEFAULT_PROMPT_TEMPLATE, $ctx);

        $content_clean = mb_substr(wp_strip_all_tags($content), 0, 3000);
        if (!empty($content_clean)) {
            $prompt .= "\n\n【文章参考内容】\n" . $content_clean;
        }

        $max_tokens = $duration <= 60 ? 2500 : ($duration <= 120 ? 4000 : 6000);
        $timeout    = $duration <= 60 ? 120 : 180;
        $time_limit = $timeout + 30;

        return [
            'prompt'     => $prompt,
            'max_tokens' => $max_tokens,
            'time_limit' => $time_limit,
        ];
    }

    /**
     * 构建 generate_frames_script 提示词。
     * DRY 修复: 原 str_replace 手动替换 11 个占位符, 现统一用 resolve_placeholders。
     *
     * @return array{prompt:string, max_tokens:int, time_limit:int}
     */
    public function build_frames_prompt(string $title, string $content, array $opts = []): array
    {
        $duration      = (int) ($opts['duration'] ?? 60);
        $custom_prompt = $opts['custom_prompt'] ?? '';
        $v15_context   = $opts['v15_context'] ?? [];

        $ctx = $this->build_v15_context($v15_context, [
            'topic'    => $title,
            'keyword'  => $title,
            'duration' => (string) $duration,
            'style'    => $opts['style'] ?? '解说',
            'voice'    => $opts['voice'] ?? '男声',
        ]);

        $image_count = max(3, (int) ceil($duration / 15));

        // DRY 修复: 统一用 resolve_placeholders (原代码用 str_replace 手动替换 11 个占位符)
        $ctx['image_count'] = (string) $image_count;
        $prompt = $this->resolve_placeholders(self::FRAMES_PROMPT_TEMPLATE, $ctx);

        if (!empty($custom_prompt)) {
            $prompt .= "\n\n【附加要求】\n" . $custom_prompt;
        }

        $content_clean = mb_substr(wp_strip_all_tags($content), 0, 2000);
        if (!empty($content_clean)) {
            $prompt .= "\n\n【文章参考】\n" . $content_clean;
        }

        $timeout    = $duration <= 60 ? 120 : 180;
        $time_limit = $timeout + 60;
        $max_tokens = $image_count * 600 + 500;

        return [
            'prompt'     => $prompt,
            'max_tokens' => $max_tokens,
            'time_limit' => $time_limit,
        ];
    }

    /**
     * 构建 generate_outline 提示词。
     *
     * @return array{prompt:string, max_tokens:int, temperature:float, segment_count:int, output_mode:string}
     */
    public function build_outline_prompt(string $title, string $content, array $opts = []): array
    {
        $duration     = (int) ($opts['duration'] ?? 60);
        $output_mode  = $opts['output_mode'] ?? 'scenes';
        $v15_context  = $opts['v15_context'] ?? [];

        $ctx = $this->build_v15_context($v15_context, [
            'topic'    => $title,
            'keyword'  => $title,
            'duration' => (string) $duration,
            'style'    => $opts['style'] ?? '解说',
        ]);

        $segment_count = $output_mode === 'frames'
            ? max(3, (int) ceil($duration / 15))
            : max(4, (int) ceil($duration / 8));

        $mode_desc = $output_mode === 'frames'
            ? "图片分镜+剧本分镜交替 (每对约 13-22 秒, 图片 5-10s + 剧本 8-12s)"
            : "纯分镜脚本 (每个 5-10 秒, 旁白+字幕)";

        $prompt = sprintf(
            "你是一位短视频脚本编剧。为以下主题生成 %d 秒视频的大纲 (轻量)。\n\n" .
            "【主题】%s\n【时长】%d 秒\n【模式】%s\n【分镜数量】%d 个\n\n" .
            "【9页SOP 参考】\nP01 封面钩子 / P02 问题定义 / P03 原理拆解 / P04 案例佐证 / P05 方法步骤 / P06 常见误区 / P07 进阶技巧 / P08 总结升华 / P09 品牌闭环\n\n" .
            "【V15 平台】%s | 调性 %s\n\n" .
            "【输出格式 — 严格遵守】\n" .
            "返回 JSON 对象: {\"outline\":[{\"index\":1,\"page\":\"P01\",\"title\":\"封面钩子的简短标题\",\"duration\":5}]}\n" .
            "只返回 JSON, 不要 markdown, 不要说明文字。",
            $duration, $title, $duration, $mode_desc, $segment_count,
            $ctx['platform'], $ctx['mood']
        );

        return [
            'prompt'        => $prompt,
            'max_tokens'    => 800,
            'temperature'   => 0.6,
            'segment_count' => $segment_count,
            'output_mode'   => $output_mode,
        ];
    }

    /**
     * 构建 generate_scene_segment 提示词。
     *
     * @return array{prompt:string, max_tokens:int}
     */
    public function build_scene_segment_prompt(array $outline_item, array $opts = []): array
    {
        $title            = $opts['title'] ?? '';
        $v15_context      = $opts['v15_context'] ?? [];
        $previous_summary = $opts['previous_summary'] ?? '';

        $ctx = $this->build_v15_context($v15_context, ['topic' => $title, 'keyword' => $title]);

        $prev_hint = !empty($previous_summary)
            ? "\n【上一分镜的结尾 (保持连贯)】\n" . mb_substr($previous_summary, 0, 200) . "\n"
            : '';

        $prompt = sprintf(
            "你是一位短视频脚本编剧。请只生成第 %d 个分镜 (%s) 的完整内容。\n\n" .
            "【主题】%s\n【分镜标题】%s\n【分镜时长】%d 秒\n【页面类型】%s\n" .
            "【V15 平台】%s | 调性 %s | 品牌 %s | 色彩 %s\n%s\n" .
            "【输出格式 — 绝对严格遵守】\n" .
            "只返回一个 JSON 对象, 绝对不要 markdown 代码块, 绝对不要前后说明文字:\n" .
            "{\"scene\":%d,\"page\":\"%s\",\"visual\":\"画面描述\",\"narration\":\"旁白文案\",\"text\":\"字幕\",\"duration\":%d}",
            $outline_item['index'], $outline_item['page'],
            $title, $outline_item['title'], $outline_item['duration'], $outline_item['page'],
            $ctx['platform'], $ctx['mood'], $ctx['brand'], $ctx['color'],
            $prev_hint,
            $outline_item['index'], $outline_item['page'], $outline_item['duration']
        );

        return [
            'prompt'     => $prompt,
            'max_tokens' => 600,
        ];
    }

    /**
     * 构建 generate_frame_segment 提示词。
     *
     * @return array{prompt:string, max_tokens:int, ctx:array}
     */
    public function build_frame_segment_prompt(array $outline_item, array $opts = []): array
    {
        $title            = $opts['title'] ?? '';
        $v15_context      = $opts['v15_context'] ?? [];
        $previous_summary = $opts['previous_summary'] ?? '';
        $next_title       = $opts['next_title'] ?? '';
        $is_last          = !empty($opts['is_last']);

        $ctx = $this->build_v15_context($v15_context, ['topic' => $title, 'keyword' => $title]);

        $prev_hint = !empty($previous_summary)
            ? "\n【上一分镜画面 (保持视觉连贯)】\n" . mb_substr($previous_summary, 0, 200) . "\n"
            : '';

        // 过渡目标描述
        if ($is_last) {
            $transition_target = "收尾闭环 (从当前画面过渡到品牌Logo + CTA 定格)";
        } else {
            $next_hint = !empty($next_title) ? "「" . $next_title . "」" : "下一个分镜";
            $transition_target = "从当前画面过渡到" . $next_hint;
        }

        $prompt = sprintf(
            "你是一位 V14 动画系统工程师。请只生成第 %d 个动画分镜 (%s · %s), 包含 2 部分:\n" .
            "1. image_prompt: 当前分镜的图片生成提示词 (静态画面, 用于 Midjourney/DALL-E 生成关键帧图)\n" .
            "2. script_prompt: 从当前分镜过渡到下一个分镜的剧本控制层 (用于 chatglm 生成动画过渡)\n\n" .
            "【主题】%s\n【分镜标题】%s\n【页面类型】%s\n【分镜时长】%d 秒\n" .
            "【V14 8 维度】\n- 品牌: %s\n- 签名: %s\n- 色彩: %s\n- 调性: %s\n- 文化: %s\n- 平台: %s\n- 密度: %s\n- 产品类型: %s\n%s\n" .
            "【过渡目标】%s\n\n" .
            "【image_prompt 要求 — Layer 1 META + 英文画面】\n" .
            "- 格式: [META:animation_kf%02d] 开头, 然后英文画面描述\n" .
            "- META 行: Brand:%s | Signature:%s | Color:%s | Mood:%s | Action:起/转/合 | Camera:镜头运动 | FrameRate:24fps\n" .
            "- 英文画面: 包含画面主体 + 构图 + 色彩 + 光影 + 风格 + 细节质感 (50-80词)\n" .
            "- 必须融入色彩 %s 和调性 %s\n\n" .
            "【script_prompt 要求 — Layer 2 剧本控制层】\n" .
            "- 格式: # Script: 开头, 中文\n" .
            "- 包含 5 个维度:\n" .
            "  * Arc (叙事弧线): 起转合三段式, 标注百分比\n" .
            "  * EmotionMap (情绪映射): 情绪轨迹 + 映射到动作\n" .
            "  * SoundDesign (声音设计): 脚步/BGM/音效提示\n" .
            "  * Keyframe (关键帧叙事): 2-3 个关键时间点 (0s/3s/7s/10s)\n" .
            "  * Atmosphere (场景氛围): 色彩/光影/运动模糊/粒子\n" .
            "- 描述 %s 的动画过渡 (镜头运动 + 画面变化 + 情绪转变)\n\n" .
            "【输出格式 — 绝对严格遵守】\n" .
            "只返回一个 JSON 对象, 绝对不要 markdown 代码块, 绝对不要前后说明文字:\n" .
            "{\"index\":%d,\"page\":\"%s\",\"title\":\"%s\",\"image_prompt\":\"[META:animation_kf%02d] Brand:%s | Signature:%s | Color:%s | Mood:%s | FrameRate:24fps\\nA 9:16 vertical animation keyframe, frame %d of 9 for %s. <英文画面描述>\",\"script_prompt\":\"# Script: %s 过渡到 %s\\nArc: 起(0-30%%)->转(30-70%%)->合(70-100%%)\\nEmotionMap: <情绪轨迹>\\nSoundDesign: <声音设计>\\nKeyframe: K1(0s)<画面> K2(3s)<画面> K3(7s)<画面> K4(10s)<画面>\\nAtmosphere: <场景氛围>\",\"duration\":%d}",
            $outline_item['index'], $outline_item['page'], $outline_item['title'],
            $title, $outline_item['title'], $outline_item['page'], $outline_item['duration'],
            $ctx['brand'], $ctx['signature'], $ctx['color'], $ctx['mood'], $ctx['culture'], $ctx['platform'], $ctx['density'], $ctx['product_type'],
            $prev_hint,
            $transition_target,
            $outline_item['index'],
            $ctx['brand'], $ctx['signature'], $ctx['color'], $ctx['mood'],
            $ctx['color'], $ctx['mood'],
            $transition_target,
            $outline_item['index'], $outline_item['page'], $outline_item['title'], $outline_item['index'],
            $ctx['brand'], $ctx['signature'], $ctx['color'], $ctx['mood'],
            $outline_item['index'], $title,
            $outline_item['title'], $next_title ?: '收尾',
            $outline_item['duration']
        );

        return [
            'prompt'     => $prompt,
            'max_tokens' => 1200,
            'ctx'        => $ctx,
        ];
    }
}
