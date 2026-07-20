<?php

declare(strict_types=1);
/**
 * 视频生成 Facade — v5.4.0 God Class 拆分。
 *
 * 原始 VideoGenerator (1021 行 / 20 方法) 已拆分为 4 个协作类:
 *
 *   VideoGenerator (本文件, ~180 行)  — 业务编排层, 保留所有 public 方法签名
 *   VideoPromptBuilder  (~300 行)     — 提示词构建 + V15 上下文 + 占位符替换
 *   VideoAIClient       (~80 行)      — AI 调用封装 (DRY Keystone, 消除 6 处重复)
 *   VideoResponseParser (~350 行)     — JSON 解析 & 标准化 (含兜底)
 *
 * 通用基础设施:
 *   JsonExtractor trait  (~70 行)     — 平衡括号法提取 JSON (供 ResponseParser use)
 *
 * 删除的死代码:
 *   - generate_short_video_copy()  — public 但零调用 (破局创新者发现)
 *   - extract_first_json_array()   — private 且零调用 (破局创新者发现)
 *
 * 公共 API (方法签名) 保持不变, DashboardVideoAjax + VideoPipeline 无需修改。
 *
 * @package Linked3
 * @subpackage Classes\Media
 */

namespace Linked3\Classes\Media;

if (!defined('ABSPATH')) {
    exit;
}

final class VideoGenerator
{
    /** @var VideoPromptBuilder */
    private $prompt_builder;

    /** @var VideoAIClient */
    private $ai_client;

    /** @var VideoResponseParser */
    private $response_parser;

    /**
     * 构造函数 — 支持依赖注入 (测试时可传入 mock), 默认自动创建实例。
     */
    public function __construct(
        ?VideoPromptBuilder $prompt_builder = null,
        ?VideoAIClient $ai_client = null,
        ?VideoResponseParser $response_parser = null
    ) {
        $this->prompt_builder   = $prompt_builder   ?? new VideoPromptBuilder();
        $this->ai_client        = $ai_client        ?? new VideoAIClient();
        $this->response_parser  = $response_parser  ?? new VideoResponseParser();
    }

    // ─── public API (签名不变) ────────────────────────────────────────

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
     * @return array{script:string, scenes:array, total_duration:int, usage:array, provider:string, model:string}|\WP_Error
     */
    public function generate_script(string $title, string $content, array $opts = []) : mixed {
        $p = $this->prompt_builder->build_script_prompt($title, $content, $opts);

        // v19.50: 绞杀模式 — system_prompt 可被元提示词杠杆增强
        $system = apply_filters(
            'linked3_video_system_prompt',
            '你是专业的短视频脚本编剧。',
            ['task' => 'video_script']
        );

        $result = $this->ai_client->chat(
            [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $p['prompt']],
            ],
            [
                'max_tokens'  => $p['max_tokens'],
                'temperature' => 0.7,
                'time_limit'  => $p['time_limit'],
                'user_id'     => $opts['user_id'] ?? get_current_user_id(),
                'provider'    => $opts['provider'] ?? null,
            ]
        );
        if (is_wp_error($result)) return $result;

        $raw_content = $result['content'] ?? '';

        $scenes = $this->response_parser->parse_scenes_json($raw_content);
        $total_duration = 0;
        foreach ($scenes as $s) {
            $total_duration += (int) ($s['duration'] ?? 0);
        }

        return [
            'script'         => $raw_content,
            'scenes'         => $scenes,
            'total_duration' => $total_duration,
            'usage'          => $result['usage'] ?? [],
            'provider'       => $result['provider'] ?? '',
            'model'          => $result['model'] ?? '',
        ];
    }

    /**
     * v5.3.3: 生成分镜图片提示词模式 — 图片提示词 + 剧本 + 画外音交替输出。
     *
     * @param string $title
     * @param string $content
     * @param array  $opts 同 generate_script + output_mode="frames"
     * @return array{frames:array, script:string, usage:array, provider:string, model:string}|\WP_Error
     */
    public function generate_frames_script(string $title, string $content, array $opts = []) : mixed {
        $p = $this->prompt_builder->build_frames_prompt($title, $content, $opts);

        $result = $this->ai_client->chat(
            [['role' => 'user', 'content' => $p['prompt']]],
            [
                'max_tokens'  => $p['max_tokens'],
                'temperature' => 0.7,
                'time_limit'  => $p['time_limit'],
                'user_id'     => $opts['user_id'] ?? get_current_user_id(),
                'provider'    => $opts['provider'] ?? null,
            ]
        );
        if (is_wp_error($result)) return $result;

        $raw    = $result['content'] ?? '';
        $frames = $this->response_parser->parse_frames_json($raw);

        return [
            'frames'   => $frames,
            'script'   => $raw,
            'usage'    => $result['usage'] ?? [],
            'provider' => $result['provider'] ?? '',
            'model'    => $result['model'] ?? '',
        ];
    }

    /**
     * v5.3.4: 生成视频大纲 (轻量) — 只返回 N 个分镜的页码+标题+时长。
     *
     * @return array{outline:array, usage:array, provider:string, model:string, output_mode:string}|\WP_Error
     */
    public function generate_outline(string $title, string $content, array $opts = []) : mixed {
        $p = $this->prompt_builder->build_outline_prompt($title, $content, $opts);

        $result = $this->ai_client->chat(
            [['role' => 'user', 'content' => $p['prompt']]],
            [
                'max_tokens'  => $p['max_tokens'],
                'temperature' => $p['temperature'],
                'user_id'     => $opts['user_id'] ?? get_current_user_id(),
            ]
        );
        if (is_wp_error($result)) return $result;

        $outline = $this->response_parser->parse_outline_json(
            $result['content'] ?? '',
            $p['segment_count'],
            $p['output_mode']
        );

        return [
            'outline'     => $outline,
            'usage'       => $result['usage'] ?? [],
            'provider'    => $result['provider'] ?? '',
            'model'       => $result['model'] ?? '',
            'output_mode' => $p['output_mode'],
        ];
    }

    /**
     * v5.3.4: 生成单个 scene 分镜 (纯分镜脚本模式)。
     *
     * @param array $outline_item {index, page, title, duration}
     * @param array $opts {title, content, v15_context, user_id, previous_summary}
     * @return array{scene:array, usage:array}|\WP_Error
     */
    public function generate_scene_segment(array $outline_item, array $opts = []) : mixed {
        $p = $this->prompt_builder->build_scene_segment_prompt($outline_item, $opts);

        $result = $this->ai_client->chat(
            [['role' => 'user', 'content' => $p['prompt']]],
            [
                'max_tokens'  => $p['max_tokens'],
                'temperature' => 0.7,
                'user_id'     => $opts['user_id'] ?? get_current_user_id(),
            ]
        );
        if (is_wp_error($result)) return $result;

        $scene = $this->response_parser->parse_single_scene_json(
            $result['content'] ?? '',
            $outline_item
        );

        return [
            'scene' => $scene,
            'usage' => $result['usage'] ?? [],
        ];
    }

    /**
     * v5.3.7: 生成动画分镜 (V14 三层结构) — frames 模式。
     *
     * @param array $outline_item {index, page, title, duration}
     * @param array $opts {title, content, v15_context, user_id, previous_summary, next_title, is_last}
     * @return array{frames:array, usage:array}|\WP_Error
     */
    public function generate_frame_segment(array $outline_item, array $opts = []) : mixed {
        $p = $this->prompt_builder->build_frame_segment_prompt($outline_item, $opts);

        $result = $this->ai_client->chat(
            [['role' => 'user', 'content' => $p['prompt']]],
            [
                'max_tokens'  => $p['max_tokens'],
                'temperature' => 0.7,
                'user_id'     => $opts['user_id'] ?? get_current_user_id(),
            ]
        );
        if (is_wp_error($result)) return $result;

        $segment = $this->response_parser->parse_animation_segment_json(
            $result['content'] ?? '',
            $outline_item,
            $p['ctx']
        );

        return [
            'frames' => [$segment],
            'usage'  => $result['usage'] ?? [],
        ];
    }
}
