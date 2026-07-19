<?php
namespace Linked3\Classes\Genesis;
use Linked3\Classes\Content\Pipeline\ContentPipelineInterface;
use Linked3\Classes\Dashboard\Linked3_Genesis_Processor;
if (!defined('ABSPATH')) exit;

final class Linked3_Comic_Pipeline implements ContentPipelineInterface
{
    public static function type(): string { return 'comic'; }
    public static function label(): string { return __('漫画脚本', 'linked3'); }

    public function prepare(array $input): array
    {
        $script = $input['script'] ?? $input['topic'] ?? '';
        if (empty($script)) throw new \InvalidArgumentException(__('请输入剧本或故事', 'linked3'));
        return ['script' => wp_strip_all_tags($script), 'style_id' => $input['style'] ?? 'exorcism_dark_ink', 'platform' => $input['platform'] ?? 'midjourney', 'panel_count' => $input['options']['panel_count'] ?? 'auto', 'extra_options' => $input['options'] ?? []];
    }

    public function generate(array $context, ?callable $progressCb = null): array
    {
        if ($progressCb) $progressCb(5, 'preflight', __('预检中...', 'linked3'));
        $result = Linked3_Genesis_Processor::genesisGenerateMultiInternal($context['script'], $context['style_id'], $context['platform'], $context['panel_count'], $progressCb, $context['extra_options']);
        if ($progressCb) $progressCb(100, 'done', __('完成', 'linked3'));
        return ['panels' => $result['panels'] ?? [], 'total_panels' => $result['total_panels'] ?? 0, 'style' => $context['style_id'], 'platform' => $context['platform']];
    }

    public function deliver(array $result): array
    {
        return ['success' => true, 'panels' => $result['panels'], 'total_panels' => $result['total_panels'], 'style' => $result['style'], 'platform' => $result['platform']];
    }

    public static function get_styles(): array
    {
        return [['id' => 'exorcism_dark_ink', 'name' => __('暗黑水墨', 'linked3')], ['id' => 'cyberpunk', 'name' => __('赛博朋克', 'linked3')], ['id' => 'cinematic', 'name' => __('电影质感', 'linked3')]];
    }
}
