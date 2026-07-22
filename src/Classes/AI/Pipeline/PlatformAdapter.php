<?php

declare(strict_types=1);
/**
 * Platform Adapter v8.3.0 — M6 多平台适配
 *
 * 公理3·平台无关核心: 三层 Prompt (META/Script/Validation) 是平台无关的语义核,
 *   平台只是渲染层 — 切换平台 = 同一语义核按目标平台语法重渲染.
 *
 * 支持 5 平台:
 *   - midjourney (MJ):    [prompt] --ar 2:3 --s 750 --style raw --no text
 *   - sdxl (SD):          (keyword1:1.5), (keyword2:1.3), [prompt], Negative: (text:1.4)
 *   - flux:               自然语言长句描述 (无权重/无参数, 依赖模型语义)
 *   - dalle:              自然语言段落 (无参数, 鼓励叙事)
 *   - comfyui:            节点参数 JSON (供 ComfyUI API 调用)
 *
 * @package Linked3\AI\Pipeline
 * @since 8.3.0
 */

namespace Linked3\Classes\AI\Pipeline;

if (!defined('ABSPATH')) exit;

class PlatformAdapter
{
    /**
     * 平台白名单
     */
    public const PLATFORMS = ['midjourney', 'sdxl', 'flux', 'dalle', 'comfyui'];

    /**
     * 6.1 同一分镜, 切换平台自动适配
     *
     * @param string $prompt   源 prompt (通常是 Prompt_Assembler 输出的 MJ 风格英文 prompt)
     * @param string $platform 目标平台 (midjourney|sdxl|flux|dalle|comfyui)
     * @param array  $opts {
     *   keywords:    array  SD 关键词权重数组 [{keyword, weight}, ...]
     *   negative:    string 负面词
     *   ar:          string 覆盖默认比例 (e.g. "16:9")
     *   s:           int    覆盖默认 stylize
     *   style:       string 覆盖 style (raw / cute / expressive / ...)
     *   quality:     string (standard|hd|high)
     *   seed:        int    ComfyUI 种子
     *   steps:       int    ComfyUI/SD 采样步数
     *   cfg:         float  CFG scale
     *   sampler:     string 采样器 (euler / dpmpp_2m / ...)
     *   model:       string ComfyUI checkpoint 名
     * }
     * @return string 平台适配后的 prompt (ComfyUI 返回 JSON 字符串)
     */
    public static function adapt(string $prompt, string $platform, array $opts = []): string
    {
        $platform = strtolower($platform);
        if (!in_array($platform, self::PLATFORMS, true)) {
            $platform = 'midjourney';
        }

        // 先剥离源 prompt 中的 MJ 平台参数 (--ar/--s/--style/--no), 取语义核心
        $core = self::strip_mj_params($prompt);

        // v19.55-fix: match() is PHP 8.0+, plugin requires PHP 7.4 — convert to switch.
        switch ($platform) {
            case 'sdxl':
                return self::to_sdxl($core, $opts);
            case 'flux':
                return self::to_flux($core, $opts);
            case 'dalle':
                return self::to_dalle($core, $opts);
            case 'comfyui':
                return self::to_comfyui($core, $opts);
            default:
                return self::to_midjourney($core, $opts);
        }
    }

    /**
     * SD 权重滑块支持 — 将关键词包装为 (keyword:weight)
     *
     * @param string $keyword 关键词 (英文, 已翻译)
     * @param float  $weight  权重 0.1~3.0, 默认 1.5
     * @return string (keyword:weight)
     */
    public static function sd_weight_wrap(string $keyword, float $weight = 1.5): string
    {
        $keyword = trim($keyword);
        if ($keyword === '') return '';
        // 权重夹紧到 [0.1, 3.0]
        $weight = max(0.1, min(3.0, $weight));
        return sprintf('(%s:%.1f)', $keyword, $weight);
    }

    /**
     * 获取平台默认参数
     *
     * @param string $platform
     * @return array {ar, s, quality, negative, style, steps, cfg, sampler}
     */
    public static function get_defaults(string $platform): array
    {
        $platform = strtolower($platform);
        // v19.55-fix: match() is PHP 8.0+, plugin requires PHP 7.4 — convert to switch.
        switch ($platform) {
            case 'sdxl':
                return [
                    'ar'       => '2:3',
                    's'        => 0,
                    'quality'  => 'high',
                    'negative' => 'text, watermark, low quality, blurry, deformed, extra fingers',
                    'style'    => '',
                    'steps'    => 30,
                    'cfg'      => 7.0,
                    'sampler'  => 'dpmpp_2m',
                ];
            case 'flux':
                return [
                    'ar'       => '2:3',
                    's'        => 0,
                    'quality'  => 'standard',
                    'negative' => '',
                    'style'    => 'natural',
                    'steps'    => 28,
                    'cfg'      => 3.5,
                    'sampler'  => 'euler',
                ];
            case 'dalle':
                return [
                    'ar'       => '1024x1792',  // DALL-E 3 竖屏尺寸
                    's'        => 0,
                    'quality'  => 'hd',
                    'negative' => '',  // DALL-E 不支持负面词, 仅作记录
                    'style'    => 'vivid',
                    'steps'    => 0,
                    'cfg'      => 0,
                    'sampler'  => '',
                ];
            case 'comfyui':
                return [
                    'ar'       => '2:3',
                    's'        => 0,
                    'quality'  => 'high',
                    'negative' => 'text, watermark, low quality',
                    'style'    => '',
                    'steps'    => 30,
                    'cfg'      => 7.0,
                    'sampler'  => 'dpmpp_2m',
                    'model'    => 'sd_xl_base_1.0.safetensors',
                ];
            default:  // midjourney
                return [
                    'ar'       => '2:3',
                    's'        => 750,
                    'quality'  => 'high',
                    'negative' => 'text',
                    'style'    => 'raw',
                    'steps'    => 0,
                    'cfg'      => 0,
                    'sampler'  => '',
                ];
        }
    }

    // =================================================================
    // AJAX Handler — 切换平台预览
    // =================================================================

    /**
     * AJAX: 切换平台预览
     * POST: nonce, prompt, platform, opts (JSON)
     */
    public static function ajax_switch_platform(): void {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_platform')) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }
        $prompt = (string)($_POST['prompt'] ?? '');
        $platform = sanitize_text_field($_POST['platform'] ?? 'midjourney');
        if ($prompt === '') {
            wp_send_json_error(['message' => __('prompt 不能为空', 'linked3')], 400);
        }
        $opts = [];
        if (!empty($_POST['opts'])) {
            $decoded = json_decode(wp_unslash($_POST['opts']), true);
            if (is_array($decoded)) $opts = $decoded;
        }
        $adapted = self::adapt($prompt, $platform, $opts);
        $defaults = self::get_defaults($platform);
        wp_send_json_success([
            'platform' => $platform,
            'prompt'   => $adapted,
            'defaults' => $defaults,
        ]);
    }

    // =================================================================
    // Private: 各平台渲染
    // =================================================================

    /**
     * Midjourney 渲染: [prompt] --ar 2:3 --s 750 --style raw --no text
     */
    private static function to_midjourney(string $core, array $opts): string
    {
        $defaults = self::get_defaults('midjourney');
        $ar = $opts['ar'] ?? $defaults['ar'];
        $s = (int)($opts['s'] ?? $defaults['s']);
        $style = $opts['style'] ?? $defaults['style'];
        $neg = $opts['negative'] ?? $defaults['negative'];

        $suffix = " --ar {$ar} --s {$s}";
        if ($style !== '') $suffix .= " --style {$style}";
        if ($neg !== '') $suffix .= " --no {$neg}";
        if (!empty($opts['seed'])) $suffix .= ' --seed ' . (int)$opts['seed'];

        return $core . $suffix;
    }

    /**
     * SD/SDXL 渲染: (keyword1:1.5), (keyword2:1.3), [prompt], Negative: (text:1.4)
     */
    private static function to_sdxl(string $core, array $opts): string
    {
        $defaults = self::get_defaults('sdxl');
        $neg = $opts['negative'] ?? $defaults['negative'];

        // 前置加权关键词
        $kw_parts = [];
        if (!empty($opts['keywords']) && is_array($opts['keywords'])) {
            foreach ($opts['keywords'] as $kw) {
                if (is_array($kw) && !empty($kw['keyword'])) {
                    $kw_parts[] = self::sd_weight_wrap((string)$kw['keyword'], (float)($kw['weight'] ?? 1.5));
                } elseif (is_string($kw)) {
                    $kw_parts[] = self::sd_weight_wrap($kw, 1.5);
                }
            }
        }
        // 兜底: 从 core 提取前 3 个逗号分隔短语作为加权关键词
        if (empty($kw_parts)) {
            $tokens = array_slice(array_filter(array_map('trim', explode(',', $core))), 0, 3);
            foreach ($tokens as $t) {
                $kw_parts[] = self::sd_weight_wrap($t, 1.3);
            }
        }

        $quality = $opts['quality'] ?? $defaults['quality'];
        $quality_tag = $quality === 'hd' ? '(masterpiece, best quality:1.4), ' : '(high quality:1.2), ';

        $prompt = $quality_tag . implode(', ', $kw_parts) . ', ' . $core;

        // 负面词
        if ($neg !== '') {
            // 负面词也用权重包裹
            $neg_tokens = array_filter(array_map('trim', explode(',', $neg)));
            $neg_weighted = array_map(fn($t) => self::sd_weight_wrap($t, 1.4), $neg_tokens);
            $prompt .= ' Negative: ' . implode(', ', $neg_weighted);
        }

        // 步数 / CFG / 采样器作为注释 (供下游 ComfyUI/A1111 解析)
        $steps = (int)($opts['steps'] ?? $defaults['steps']);
        $cfg = (float)($opts['cfg'] ?? $defaults['cfg']);
        $sampler = $opts['sampler'] ?? $defaults['sampler'];
        $prompt .= sprintf(' [Steps:%d CFG:%.1f Sampler:%s]', $steps, $cfg, $sampler);

        return $prompt;
    }

    /**
     * Flux 渲染: 自然语言长句描述 (无权重/无参数)
     * Flux 对自然语言叙事理解最好, 因此将关键词列表重组为流畅长句
     */
    private static function to_flux(string $core, array $opts): string
    {
        // 移除 MJ 风格的逗号堆叠, 重组为流畅句子
        $tokens = array_filter(array_map('trim', explode(',', $core)));
        $sentence = implode(', ', $tokens);
        // 简单句式: A cinematic photograph of {sentence}, captured with dramatic lighting and rich detail.
        $flavored = $sentence;
        if (!preg_match('#\b(cinematic|photograph|painting|illustration)\b#i', $flavored)) {
            $flavored = 'A cinematic scene: ' . $flavored;
        }
        $flavored .= ', captured with dramatic lighting, rich detail, high dynamic range, photorealistic finish.';
        return $flavored;
    }

    /**
     * DALL-E 渲染: 自然语言段落 (鼓励叙事, 长度可较长)
     */
    private static function to_dalle(string $core, array $opts): string
    {
        $defaults = self::get_defaults('dalle');
        $style = $opts['style'] ?? $defaults['style'];  // vivid | natural
        $tokens = array_filter(array_map('trim', explode(',', $core)));
        $paragraph = implode(', ', $tokens);
        $style_hint = $style === 'vivid' ? ' with vivid, hyper-realistic detail and bold composition' : ' in a natural, restrained photographic style';
        return sprintf(
            'Create a vertical 9:16 illustration. The scene depicts %s%s. Emphasize mood and atmosphere, ensure visual storytelling clarity, and avoid any text or watermarks.',
            $paragraph,
            $style_hint
        );
    }

    /**
     * ComfyUI 渲染: 节点参数 JSON
     * 输出可直接喂给 ComfyUI API 的 workflow 片段 (CheckpointLoader + CLIPTextEncode + KSampler)
     */
    private static function to_comfyui(string $core, array $opts): string
    {
        $defaults = self::get_defaults('comfyui');
        $neg = $opts['negative'] ?? $defaults['negative'];
        $steps = (int)($opts['steps'] ?? $defaults['steps']);
        $cfg = (float)($opts['cfg'] ?? $defaults['cfg']);
        $sampler = $opts['sampler'] ?? $defaults['sampler'];
        $model = $opts['model'] ?? $defaults['model'];
        $seed = isset($opts['seed']) ? (int)$opts['seed'] : random_int(1, 2 ** 31 - 1);

        // 简易 3 节点 workflow (Loader + Positive + Negative + KSampler)
        $workflow = [
            '3' => [
                'class_type' => 'KSampler',
                'inputs' => [
                    'seed'     => $seed,
                    'steps'    => $steps,
                    'cfg'      => $cfg,
                    'sampler_name' => $sampler,
                    'scheduler'   => 'normal',
                    'denoise'    => 1.0,
                    'model'      => ['4', 0],
                    'positive'   => ['6', 0],
                    'negative'   => ['7', 0],
                    'latent_image' => ['5', 0],
                ],
            ],
            '4' => [
                'class_type' => 'CheckpointLoaderSimple',
                'inputs' => [
                    'ckpt_name' => $model,
                ],
            ],
            '5' => [
                'class_type' => 'EmptyLatentImage',
                'inputs' => [
                    'width'  => 832,   // ~2:3 竖屏
                    'height' => 1248,
                    'batch_size' => 1,
                ],
            ],
            '6' => [
                'class_type' => 'CLIPTextEncode',
                'inputs' => [
                    'text' => $core,
                    'clip' => ['4', 1],
                ],
            ],
            '7' => [
                'class_type' => 'CLIPTextEncode',
                'inputs' => [
                    'text' => $neg,
                    'clip' => ['4', 1],
                ],
            ],
        ];

        return wp_json_encode($workflow, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 剥离 MJ 平台参数 (--ar/--s/--style/--no/--seed 等), 保留语义核心
     */
    private static function strip_mj_params(string $prompt): string
    {
        // 移除所有 --xxx 参数
        $core = preg_replace('#\s*--\w+(?:\s+\S+)?#', '', $prompt);
        // 移除 [Steps:.. CFG:.. Sampler:..] 注释
        $core = preg_replace('#\s*\[Steps:[^\]]+\]#', '', $core);
        // 移除 "Negative: ..." 段
        $core = preg_replace('#\s*Negative:.*$#i', '', $core);
        // 移除 SD 权重括号: (kw:1.5) -> kw
        $core = preg_replace_callback('#\(([^():]+):[\d.]+)\)#', fn($m) => $m[1], $core);
        return trim($core);
    }
}
