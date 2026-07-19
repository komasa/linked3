<?php

declare(strict_types=1);
/**
 * 视频生成 — v5.3.2 系统化重构 (结合 V15 视觉系统)。
 *
 * 功能:
 *   - AI 生成视频脚本(分镜/旁白/字幕)
 *   - 文章转视频脚本(用于剪映/PR 等后期)
 *   - v5.3.2: 接受 custom_prompt + V15 8 维度上下文 + 容错 JSON 解析
 *   - v5.3.2: 失败抛 RuntimeException (让 AJAX handler 能 catch)
 *   - v5.3.2: 模型从 provider_models 选项读取 (而非硬编码 gpt-4o)
 *   - v5.3.2: 动态超时 (60s 视频 → 120s, 120s+ 视频 → 180s)
 *
 * @package Linked3
 * @subpackage Classes\Media
 */

namespace Linked3\Classes\Media;

use Linked3\Classes\Core\AIDispatcher;



if (!defined('ABSPATH')) {
    exit;
}
final class VideoGenerator
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
     * 根据文章内容生成视频脚本 (v5.3.2 系统化重构)。
     *
     * @param string $title 文章标题
     * @param string $content 文章内容
     * @param array  $opts {
     *     @type string $style          风格 (解说/新闻/故事/教程)
     *     @type int    $duration       目标时长 (秒)
     *     @type string $voice          旁白音色
     *     @type string $custom_prompt  v5.2.2+: 自定义提示词 (优先于默认)
     *     @type array  $v15_context    v5.3.2: V15 8 维度占位符上下文
     *     @type int    $user_id        用户 ID (配额记账)
     *     @type string $provider       指定 provider (默认读 option)
     * }
     * @return array{script:string, scenes:array, total_duration:int, usage:array, provider:string, model:string}
     * @throws \RuntimeException 当 AI 调用或 JSON 解析失败时
     */
    public function generate_script(string $title, string $content, array $opts = []) : mixed {
        $style       = $opts['style'] ?? '解说';
        $duration    = (int) ($opts['duration'] ?? 60);
        $voice       = $opts['voice'] ?? '男声';
        $custom_prompt = $opts['custom_prompt'] ?? '';
        $v15_context = $opts['v15_context'] ?? [];

        // v5.3.2: 合并 V15 上下文 (8 维度, 缺失用占位)
        $ctx = $this->build_v15_context($v15_context, [
            'topic'    => $title,
            'keyword'  => $title,
            'duration' => (string) $duration,
            'style'    => $style,
            'voice'    => $voice,
        ]);

        // v5.3.2: 选择提示词 (custom_prompt 优先, 否则用默认模板)
        $prompt = !empty($custom_prompt)
            ? $this->resolve_placeholders($custom_prompt, $ctx)
            : $this->resolve_placeholders(self::DEFAULT_PROMPT_TEMPLATE, $ctx);

        // 追加文章内容 (剪到 3000 字内, 避免 token 溢出)
        $content_clean = mb_substr(wp_strip_all_tags($content), 0, 3000);
        if (!empty($content_clean)) {
            $prompt .= "\n\n【文章参考内容】\n" . $content_clean;
        }

        // v5.3.2: 动态 max_tokens — 长视频需要更多分镜
        $max_tokens = $duration <= 60 ? 2500 : ($duration <= 120 ? 4000 : 6000);

        // v5.3.2: 从 provider_models 选项读取模型 (而非硬编码 gpt-4o)
        $provider = $opts['provider'] ?? get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';

        // v5.3.2: 动态超时 — 视频脚本生成比聊天慢
        $timeout = $duration <= 60 ? 120 : 180;
        if (function_exists('set_time_limit')) {
            @set_time_limit($timeout + 30);
        }

        // v5.3.2: 调用 AI Dispatcher — 失败抛 RuntimeException
        try { // v19.3.0: AI 调用容错
        // v19.50: 绞杀模式 — system_prompt 可被元提示词杠杆增强
        $video_system = apply_filters('linked3_video_system_prompt', '你是专业的短视频脚本编剧。', ['task' => 'video_script']);
        $result = AIDispatcher::instance()->chat(
            [['role' => 'system', 'content' => $video_system], ['role' => 'user', 'content' => $prompt]],
            [
                'provider'    => $provider,
                'model'       => $model,
                'temperature' => 0.7,
                'max_tokens'  => $max_tokens,
                'module'      => 'video',
                'user_id'     => $opts['user_id'] ?? get_current_user_id(),
            ],
            ['fallback_providers' => ['deepseek', 'zhipu']]
        );
        } catch (\Throwable $e) {
            return new \WP_Error('ai_failed', 'AI 调用失败: ' . $e->getMessage());
        }

        $raw_content = $result['content'] ?? '';

        // v5.3.2: 容错 JSON 解析 (同时支持 {scenes:[...]} 和 [...] 两种格式)
        $scenes = $this->parse_scenes_json($raw_content);
        $total_duration = 0;
        foreach ($scenes as $s) {
            $total_duration += (int) ($s['duration'] ?? 0);
        }

        return [
            'script'         => $raw_content,
            'scenes'         => $scenes,
            'total_duration' => $total_duration,
            'usage'          => $result['usage'] ?? [],
            'provider'       => $result['provider'] ?? $provider,
            'model'          => $result['model'] ?? $model,
        ];
    }

    /**
     * v5.3.2: 构建 V15 8 维度上下文 (合并用户输入 + 默认占位)。
     *
     * @param array $user_ctx  用户/品牌提供的上下文
     * @param array $extra     额外占位符 (topic/keyword/duration 等)
     * @return array 完整占位符映射
     */
    private function build_v15_context(array $user_ctx, array $extra = []) : mixed     {
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
    private function resolve_placeholders(string $text, array $ctx) : mixed {
        foreach ($ctx as $key => $value) {
            $text = str_replace('{' . $key . '}', (string) $value, $text);
        }
        // 清理未匹配的占位符 (避免 {xxx} 残留)
        $text = preg_replace('/\{[a-z_]+\}/i', '', $text);
        return $text;
    }

    /**
     * v5.3.2: 容错 JSON 解析 — 同时支持多种格式。
     *
     * 支持:
     *   - {"scenes": [...]}
     *   - [...] (直接数组)
     *   - ```json ... ``` 包裹
     *   - 前后有解释文字
     *
     * @param string $raw
     * @return array 解析出的 scenes 数组 (失败返回空数组)
     */
    private function parse_scenes_json(string $raw) : mixed     {
        if (empty($raw)) return [];

        $text = trim($raw);

        // 1. 去除 markdown 代码块包裹
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        // 2. 尝试直接解析
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            // 2a. {"scenes": [...]} 格式
            if (isset($decoded['scenes']) && is_array($decoded['scenes'])) {
                return $this->normalize_scenes($decoded['scenes']);
            }
            // 2b. [...] 直接数组
            if ($this->is_indexed_array($decoded)) {
                return $this->normalize_scenes($decoded);
            }
        }

        // 3. 提取第一个 JSON 对象/数组 (容错: 前后有解释文字)
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $obj = json_decode($m[0], true);
            if (is_array($obj) && isset($obj['scenes']) && is_array($obj['scenes'])) {
                return $this->normalize_scenes($obj['scenes']);
            }
        }
        if (preg_match('/\[[\s\S]*\]/', $text, $m)) {
            $arr = json_decode($m[0], true);
            if (is_array($arr) && $this->is_indexed_array($arr)) {
                return $this->normalize_scenes($arr);
            }
        }

        // 4. 全部失败 → 返回空 (AJAX handler 会把原始 script 一并返回让用户排查)
        return [];
    }

    /**
     * v5.3.2: 标准化 scenes 数组 (补全缺失字段)。
     *
     * @param array $scenes
     * @return array
     */
    private function normalize_scenes(array $scenes) : array {
        $result = [];
        $i = 1;
        foreach ($scenes as $s) {
            if (!is_array($s)) continue;
            $result[] = [
                'scene'     => $s['scene'] ?? $i,
                'page'      => $s['page'] ?? sprintf('P%02d', min($i, 9)),
                'visual'    => $s['visual'] ?? $s['画面'] ?? '',
                'narration' => $s['narration'] ?? $s['旁白'] ?? '',
                'text'      => $s['text'] ?? $s['字幕'] ?? '',
                'duration'  => (int) ($s['duration'] ?? $s['时长'] ?? 5),
            ];
            $i++;
        }
        return $result;
    }

    /**
     * 判断是否为索引数组 (非关联数组)。
     *
     * @param array $arr
     * @return bool
     */
    private function is_indexed_array(array $arr) : bool {
        if (empty($arr)) return false;
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    /**
     * 生成短视频文案 (TikTok/抖音风格) — v5.3.2 同步加固。
     *
     * @param string $topic
     * @param int    $duration
     * @return array{script:string, usage:array}
     * @throws \RuntimeException
     */
    public function generate_short_video_copy(string $topic, $duration = 30) : mixed {
        $prompt = sprintf(
            "为话题「%s」生成一个 %d 秒的短视频脚本(抖音/TikTok 风格)。\n" .
            "包含: 开场hook(前3秒抓眼球), 主体(3-4个要点), 结尾(引导互动)。\n" .
            "每段标注预计时长。输出纯文本。",
            $topic, $duration
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
                'temperature' => 0.9,
                'max_tokens'  => 1000,
                'module'      => 'video',
                'user_id'     => get_current_user_id(),
            ],
            ['fallback_providers' => ['deepseek', 'zhipu']]
        );
        } catch (\Throwable $e) {
            return new \WP_Error('ai_failed', 'AI 调用失败: ' . $e->getMessage());
        }

        return [
            'script' => $result['content'],
            'usage'  => $result['usage'] ?? [],
        ];
    }

    /**
     * v5.3.3: 生成分镜图片提示词模式 — 图片提示词 + 剧本 + 画外音交替输出。
     *
     * 输出结构 (frames 数组):
     *   [
     *     {type:"image", visual_prompt:"完整图片生成提示词", duration:5},
     *     {type:"scene", script:"剧本/旁白", voiceover:"画外音", duration:8},
     *     {type:"image", visual_prompt:"...", duration:5},
     *     {type:"scene", script:"...", voiceover:"...", duration:8},
     *     ...
     *   ]
     *
     * 每个图片分镜之间是一个剧本+画外音分镜, 图片剧情 5-10 秒。
     *
     * @param string $title
     * @param string $content
     * @param array  $opts 同 generate_script + output_mode="frames"
     * @return array{frames:array, script:string, usage:array, provider:string, model:string}
     * @throws \RuntimeException
     */
    public function generate_frames_script(string $title, string $content, array $opts = []) : mixed {
        $duration    = (int) ($opts['duration'] ?? 60);
        $custom_prompt = $opts['custom_prompt'] ?? '';
        $v15_context = $opts['v15_context'] ?? [];

        // 合并 V15 上下文
        $ctx = $this->build_v15_context($v15_context, [
            'topic'    => $title,
            'keyword'  => $title,
            'duration' => (string) $duration,
            'style'    => $opts['style'] ?? '解说',
            'voice'    => $opts['voice'] ?? '男声',
        ]);

        // 计算分镜数量: 图片 5-10s, 剧本+画外音 8-12s, 交替
        // 60s 视频大约: 4 个图片 + 3 个剧本 = 7 个分镜
        $image_count = max(3, (int) ceil($duration / 15));

        $prompt_template = <<<'PROMPT'
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

        $prompt = str_replace(
            ['{brand}','{signature}','{color}','{mood}','{culture}','{platform}','{density}','{product_type}','{topic}','{duration}','{image_count}'],
            [$ctx['brand'],$ctx['signature'],$ctx['color'],$ctx['mood'],$ctx['culture'],$ctx['platform'],$ctx['density'],$ctx['product_type'],$ctx['topic'],$ctx['duration'],$image_count],
            $prompt_template
        );
        // 兼容 custom_prompt: 如果有, 追加到末尾
        if (!empty($custom_prompt)) {
            $prompt .= "\n\n【附加要求】\n" . $custom_prompt;
        }

        $content_clean = mb_substr(wp_strip_all_tags($content), 0, 2000);
        if (!empty($content_clean)) {
            $prompt .= "\n\n【文章参考】\n" . $content_clean;
        }

        $provider = $opts['provider'] ?? get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';

        $timeout = $duration <= 60 ? 120 : 180;
        if (function_exists('set_time_limit')) {
            @set_time_limit($timeout + 60);
        }

        try { // v19.3.0: AI 调用容错
        $result = AIDispatcher::instance()->chat(
            [['role' => 'user', 'content' => $prompt]],
            [
                'provider'    => $provider,
                'model'       => $model,
                'temperature' => 0.7,
                'max_tokens'  => $image_count * 600 + 500,
                'module'      => 'video',
                'user_id'     => $opts['user_id'] ?? get_current_user_id(),
            ],
            ['fallback_providers' => ['deepseek', 'zhipu']]
        );
        } catch (\Throwable $e) {
            return new \WP_Error('ai_failed', 'AI 调用失败: ' . $e->getMessage());
        }

        $raw = $result['content'] ?? '';
        $frames = $this->parse_frames_json($raw);

        return [
            'frames'  => $frames,
            'script'  => $raw,
            'usage'   => $result['usage'] ?? [],
            'provider'=> $result['provider'] ?? $provider,
            'model'   => $result['model'] ?? $model,
        ];
    }

    /**
     * v5.3.3: 解析 frames JSON。
     */
    private function parse_frames_json(string $raw) : mixed {
        if (empty($raw)) return [];
        $text = trim($raw);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }
        if (!is_array($decoded)) return [];

        $frames = $decoded['frames'] ?? $decoded['scenes'] ?? [];
        if (!is_array($frames)) return [];

        // 标准化
        $out = [];
        $i = 1;
        foreach ($frames as $f) {
            if (!is_array($f)) continue;
            $type = $f['type'] ?? 'scene';
            $out[] = [
                'type'           => $type,
                'index'          => $f['index'] ?? $i,
                'visual_prompt'  => $f['visual_prompt'] ?? $f['visual'] ?? '',
                'script'         => $f['script'] ?? $f['narration'] ?? '',
                'voiceover'      => $f['voiceover'] ?? '',
                'description'    => $f['description'] ?? '',
                'duration'       => (int) ($f['duration'] ?? ($type === 'image' ? 5 : 8)),
            ];
            $i++;
        }
        return $out;
    }

    // =================================================================
    // v5.3.4: 分段生成 (类似长文写作的逐段生成)
    // 解决"AI 一次性生成 8 个分镜导致 JSON 解析失败"问题
    // 流程: generate_outline() → 循环 generate_scene_segment() / generate_frame_segment()
    // =================================================================

    /**
     * v5.3.4: 生成视频大纲 (轻量) — 只返回 N 个分镜的页码+标题+时长。
     *
     * AI 只需返回 N 个简短标题, JSON 极小, 不会解析失败。
     *
     * @return array{outline:array, usage:array, provider:string, model:string, output_mode:string}
     * @throws \RuntimeException
     */
    public function generate_outline(string $title, string $content, array $opts = []) : mixed {
        $duration = (int) ($opts['duration'] ?? 60);
        $output_mode = $opts['output_mode'] ?? 'scenes';
        $v15_context = $opts['v15_context'] ?? [];

        $ctx = $this->build_v15_context($v15_context, [
            'topic' => $title, 'keyword' => $title,
            'duration' => (string) $duration,
            'style' => $opts['style'] ?? '解说',
        ]);

        // 计算分镜数: scenes 模式每 8s 一个, frames 模式每 15s 一对
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

        $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';

        try { // v19.3.0: AI 调用容错
        $result = AIDispatcher::instance()->chat(
            [['role' => 'user', 'content' => $prompt]],
            [
                'provider' => $provider, 'model' => $model,
                'temperature' => 0.6, 'max_tokens' => 800,
                'module' => 'video',
                'user_id' => $opts['user_id'] ?? get_current_user_id(),
            ],
            ['fallback_providers' => ['deepseek', 'zhipu']]
        );
        } catch (\Throwable $e) {
            return new \WP_Error('ai_failed', 'AI 调用失败: ' . $e->getMessage());
        }

        $outline = $this->parse_outline_json($result['content'] ?? '', $segment_count, $output_mode);

        return [
            'outline'  => $outline,
            'usage'    => $result['usage'] ?? [],
            'provider' => $result['provider'] ?? $provider,
            'model'    => $result['model'] ?? $model,
            'output_mode' => $output_mode,
        ];
    }

    /**
     * v5.3.4: 解析大纲 JSON (容错 + 失败兜底)。
     */
    private function parse_outline_json(string $raw, int $expected_count, string $output_mode) : mixed {
        if (empty($raw)) return $this->default_outline($expected_count, $output_mode);
        $text = trim($raw);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }
        $items = [];
        if (is_array($decoded)) {
            $items = $decoded['outline'] ?? $decoded['scenes'] ?? $decoded;
            if (!is_array($items)) $items = [];
        }

        if (empty($items)) {
            return $this->default_outline($expected_count, $output_mode);
        }

        $out = [];
        foreach ($items as $i => $item) {
            if (!is_array($item)) {
                $item = ['title' => (string) $item];
            }
            $out[] = [
                'index'    => (int) ($item['index'] ?? ($i + 1)),
                'page'     => $item['page'] ?? sprintf('P%02d', min($i + 1, 9)),
                'title'    => $item['title'] ?? ('分镜 ' . ($i + 1)),
                'duration' => (int) ($item['duration'] ?? ($output_mode === 'frames' ? 15 : 8)),
            ];
        }
        return $out;
    }

    /**
     * v5.3.4: 默认大纲 (解析失败兜底)。
     */
    private function default_outline(int $expected_count, string $output_mode) : array {
        $default_pages = ['P01', 'P02', 'P03', 'P05', 'P08', 'P09'];
        $default_titles = ['封面钩子', '问题定义', '原理拆解', '方法步骤', '总结升华', '品牌闭环'];
        $out = [];
        $count = min($expected_count, count($default_pages));
        for ($i = 0; $i < $count; $i++) {
            $out[] = [
                'index'    => $i + 1,
                'page'     => $default_pages[$i],
                'title'    => $default_titles[$i],
                'duration' => $output_mode === 'frames' ? 15 : 10,
            ];
        }
        return $out;
    }

    /**
     * v5.3.4: 生成单个 scene 分镜 (纯分镜脚本模式)。
     *
     * AI 只需生成 1 个分镜, JSON 极小, 不会解析失败。
     *
     * @param array $outline_item {index, page, title, duration}
     * @param array $opts {title, content, v15_context, user_id, previous_summary}
     * @return array{scene:array, usage:array}
     * @throws \RuntimeException
     */
    public function generate_scene_segment(array $outline_item, array $opts = []) : mixed {
        $title = $opts['title'] ?? '';
        $v15_context = $opts['v15_context'] ?? [];
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

        $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';

        try { // v19.3.0: AI 调用容错
        $result = AIDispatcher::instance()->chat(
            [['role' => 'user', 'content' => $prompt]],
            [
                'provider' => $provider, 'model' => $model,
                'temperature' => 0.7, 'max_tokens' => 600,
                'module' => 'video',
                'user_id' => $opts['user_id'] ?? get_current_user_id(),
            ],
            ['fallback_providers' => ['deepseek', 'zhipu']]
        );
        } catch (\Throwable $e) {
            return new \WP_Error('ai_failed', 'AI 调用失败: ' . $e->getMessage());
        }

        $scene = $this->parse_single_scene_json($result['content'] ?? '', $outline_item);

        return [
            'scene'  => $scene,
            'usage'  => $result['usage'] ?? [],
        ];
    }

    /**
     * v5.3.4: 解析单个 scene JSON (容错 + 兜底)。
     * v5.3.5: 改用平衡括号法提取第一个完整 JSON 对象, 解决贪婪匹配失败。
     */
    private function parse_single_scene_json(string $raw, array $outline_item) : mixed {
        if (empty($raw)) return $this->default_scene($outline_item);
        $text = trim($raw);
        // 去 markdown 代码块
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        // 先尝试直接解析
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $this->normalize_scene($decoded, $outline_item);
        }

        // v5.3.5: 平衡括号法提取第一个完整 JSON 对象
        $json_str = $this->extract_first_json_object($text);
        if (!empty($json_str)) {
            $decoded = json_decode($json_str, true);
            if (is_array($decoded)) {
                return $this->normalize_scene($decoded, $outline_item);
            }
        }

        return $this->default_scene($outline_item);
    }

    /**
     * v5.3.5: 标准化 scene (从 AI 返回的任意结构提取字段)。
     */
    private function normalize_scene(array $decoded, array $outline_item) : array {
        return [
            'scene'     => (int) ($decoded['scene'] ?? $decoded['index'] ?? $outline_item['index']),
            'page'      => $decoded['page'] ?? $outline_item['page'],
            'visual'    => $decoded['visual'] ?? $decoded['画面'] ?? $decoded['description'] ?? '',
            'narration' => $decoded['narration'] ?? $decoded['旁白'] ?? $decoded['script'] ?? '',
            'text'      => $decoded['text'] ?? $decoded['字幕'] ?? $decoded['caption'] ?? '',
            'duration'  => (int) ($decoded['duration'] ?? $decoded['时长'] ?? $outline_item['duration']),
        ];
    }

    /**
     * v5.3.4: 默认 scene (解析失败兜底)。
     */
    private function default_scene(array $outline_item) : array {
        return [
            'scene'     => $outline_item['index'],
            'page'      => $outline_item['page'],
            'visual'    => $outline_item['title'] . ' 的画面 (AI 解析失败, 请重试)',
            'narration' => $outline_item['title'] . ' 的旁白',
            'text'      => $outline_item['title'],
            'duration'  => $outline_item['duration'],
        ];
    }

    /**
     * v5.3.7: 生成动画分镜 (V14 三层结构) — frames 模式。
     *
     * 用户需求 (基于 V14 动画系统):
     *   - 根据剧情拆解多个 10 秒分镜
     *   - 每个分镜 = 1 个图片提示词 (Layer 1 META + 英文画面, 用于生成静态图)
     *   - 每 2 个分镜之间 = 1 个剧本控制层提示词 (Layer 2 Script, 用于 chatglm 生成动画过渡)
     *
     * V14 三层结构:
     *   Layer 1 META: [META:animation] Brand/Signature/Color/Mood/Action/Camera/FrameRate/CharacterSeed
     *   Layer 2 Script: Arc/EmotionMap/SoundDesign/Keyframe Narrative/Atmosphere/Transition
     *   Layer 3 Validation: 视觉一致性/叙事完整性/角色一致性/镜头签名
     *
     * 每次生成 1 对:
     *   image_prompt: 当前分镜的图片生成提示词 (Layer 1 META + 英文画面描述)
     *   script_prompt: 从当前分镜过渡到下一个分镜的剧本控制层 (Layer 2 Script)
     *     - 最后一个分镜无 script_prompt (或改为收尾闭环 CTA)
     *
     * @param array $outline_item {index, page, title, duration}
     * @param array $opts {title, content, v15_context, user_id, previous_summary, next_title, is_last}
     * @return array{frames:array, usage:array}
     *   frames = [{type:"animation", index, image_prompt, script_prompt, duration}]
     * @throws \RuntimeException
     */
    public function generate_frame_segment(array $outline_item, array $opts = []) : mixed {
        $title = $opts['title'] ?? '';
        $v15_context = $opts['v15_context'] ?? [];
        $previous_summary = $opts['previous_summary'] ?? '';
        $next_title = $opts['next_title'] ?? '';
        $is_last = !empty($opts['is_last']);

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

        $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';

        try { // v19.3.0: AI 调用容错
        $result = AIDispatcher::instance()->chat(
            [['role' => 'user', 'content' => $prompt]],
            [
                'provider' => $provider, 'model' => $model,
                'temperature' => 0.7, 'max_tokens' => 1200,
                'module' => 'video',
                'user_id' => $opts['user_id'] ?? get_current_user_id(),
            ],
            ['fallback_providers' => ['deepseek', 'zhipu']]
        );
        } catch (\Throwable $e) {
            return new \WP_Error('ai_failed', 'AI 调用失败: ' . $e->getMessage());
        }

        $segment = $this->parse_animation_segment_json($result['content'] ?? '', $outline_item, $ctx);

        return [
            'frames' => [$segment],
            'usage'  => $result['usage'] ?? [],
        ];
    }

    /**
     * v5.3.7: 解析动画分镜 JSON (容错 + 兜底)。
     * 支持 V14 三层结构: image_prompt (Layer1) + script_prompt (Layer2)
     */
    private function parse_animation_segment_json(string $raw, array $outline_item, array $ctx) : mixed {
        $default_image = sprintf(
            "[META:animation_kf%02d] Brand:%s | Signature:%s | Color:%s | Mood:%s | FrameRate:24fps\nA 9:16 vertical animation keyframe, frame %d for %s. %s (AI 解析失败, 请重试)",
            $outline_item['index'], $ctx['brand'], $ctx['signature'], $ctx['color'], $ctx['mood'],
            $outline_item['index'], $ctx['topic'], $outline_item['title']
        );
        $default_script = sprintf(
            "# Script: %s (AI 解析失败, 请重试)\nArc: 起(0-30%%)->转(30-70%%)->合(70-100%%)\nEmotionMap: 待生成\nSoundDesign: 待生成\nKeyframe: K1(0s) K2(%ds) K3(%ds)\nAtmosphere: 待生成",
            $outline_item['title'],
            (int)($outline_item['duration'] * 0.3),
            (int)($outline_item['duration'] * 0.7)
        );

        $default = [
            'index'         => $outline_item['index'],
            'page'          => $outline_item['page'],
            'title'         => $outline_item['title'],
            'image_prompt'  => $default_image,
            'script_prompt' => $default_script,
            'duration'      => $outline_item['duration'],
            'type'          => 'animation',
        ];

        if (empty($raw)) return $default;
        $text = trim($raw);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        // 先尝试直接解析
        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            // v5.3.7: 平衡括号法提取第一个完整 JSON 对象
            $json_str = $this->extract_first_json_object($text);
            if (!empty($json_str)) {
                $decoded = json_decode($json_str, true);
            }
        }
        if (!is_array($decoded)) return $default;

        return [
            'index'         => (int) ($decoded['index'] ?? $decoded['section'] ?? $outline_item['index']),
            'page'          => $decoded['page'] ?? $outline_item['page'],
            'title'         => $decoded['title'] ?? $outline_item['title'],
            'image_prompt'  => $decoded['image_prompt'] ?? $decoded['imagePrompt'] ?? $default_image,
            'script_prompt' => $decoded['script_prompt'] ?? $decoded['scriptPrompt'] ?? $default_script,
            'duration'      => (int) ($decoded['duration'] ?? $outline_item['duration']),
            'type'          => 'animation',
        ];
    }

    /**
     * v5.3.5: 用平衡括号法提取第一个完整 JSON 对象。
     * 解决 AI 返回 markdown 包裹 + 前后说明文字导致 preg_match 贪婪匹配失败的问题。
     *
     * @param string $text
     * @return string 完整的 JSON 对象字符串 (含大括号), 失败返回空字符串
     */
    private function extract_first_json_object(string $text) : ?string {
        if (empty($text)) return '';
        $start = strpos($text, '{');
        if ($start === false) return '';

        $depth = 0;
        $in_string = false;
        $escape = false;
        $len = strlen($text);

        for ($i = $start; $i < $len; $i++) {
            $ch = $text[$i];

            if ($escape) {
                $escape = false;
                continue;
            }
            if ($ch === '\\' && $in_string) {
                $escape = true;
                continue;
            }
            if ($ch === '"') {
                $in_string = !$in_string;
                continue;
            }
            if ($in_string) continue;

            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }
        return '';
    }

    /**
     * v5.3.5: 用平衡括号法提取第一个完整 JSON 数组。
     */
    private function extract_first_json_array(string $text) : ?string {
        if (empty($text)) return '';
        $start = strpos($text, '[');
        if ($start === false) return '';

        $depth = 0;
        $in_string = false;
        $escape = false;
        $len = strlen($text);

        for ($i = $start; $i < $len; $i++) {
            $ch = $text[$i];

            if ($escape) { $escape = false; continue; }
            if ($ch === '\\' && $in_string) { $escape = true; continue; }
            if ($ch === '"') { $in_string = !$in_string; continue; }
            if ($in_string) continue;

            if ($ch === '[') $depth++;
            elseif ($ch === ']') {
                $depth--;
                if ($depth === 0) return substr($text, $start, $i - $start + 1);
            }
        }
        return '';
    }
}
