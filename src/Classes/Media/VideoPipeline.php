<?php

declare(strict_types=1);
/**
 * Video Pipeline — adapter for unified ContentPipeline.
 *
 * G5.3: Wraps the existing Video_Generator behind the ContentPipeline_Interface.
 *
 * @package Linked3
 * @subpackage Classes\Media
 * @since      27.6.0
 */

namespace Linked3\Classes\Media;

use Linked3\Classes\Content\Pipeline\ContentPipelineInterface;

if (!defined('ABSPATH')) exit;

final class VideoPipeline implements ContentPipelineInterface
{
    public static function type(): string { return 'video'; }
    public static function label(): string { return __('视频脚本', 'linked3'); }

    public function prepare(array $input): array
    {
        $topic = sanitize_text_field($input['topic'] ?? '');
        $script = wp_strip_all_tags($input['script'] ?? '');
        if (empty($topic) && empty($script)) {
            throw new \InvalidArgumentException(__('请输入主题或剧本', 'linked3'));
        }
        return [
            'topic'     => $topic ?: mb_substr($script, 0, 50),
            'script'    => $script,
            'style'     => $input['style'] ?? 'auto',
            'groups'    => max(1, min(10, (int)($input['options']['video_groups'] ?? 5))),
        ];
    }

    public function generate(array $context, ?callable $progressCb = null): array
    {
        if ($progressCb) $progressCb(10, 'analyzing', __('分析剧本...', 'linked3'));

        // Delegate to existing Video_Generator if available
        if (class_exists('\Linked3\Classes\Media\VideoGenerator')) {
            $generator = new \Linked3\Classes\Media\VideoGenerator();
            $result = $generator->generate_script($context['script'] ?: $context['topic'], [
                'group_count' => $context['groups'],
                'style'       => $context['style'],
            ]);
            if ($progressCb) $progressCb(100, 'done', __('完成', 'linked3'));
            return ['frames' => $result['frames'] ?? [], 'total' => count($result['frames'] ?? [])];
        }

        throw new \RuntimeException(__('视频生成器未加载', 'linked3'));
    }

    public function deliver(array $result): array
    {
        return [
            'success' => true,
            'frames'  => $result['frames'] ?? [],
            'total'   => $result['total'] ?? 0,
        ];
    }

    public static function get_styles(): array
    {
        return [
            ['id' => 'auto',         'name' => __('自动适配', 'linked3')],
            ['id' => 'cinematic',    'name' => __('电影质感', 'linked3')],
            ['id' => 'documentary',  'name' => __('纪实风格', 'linked3')],
            ['id' => 'animation',    'name' => __('动画风格', 'linked3')],
        ];
    }
}
