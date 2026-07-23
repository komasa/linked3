<?php

declare(strict_types=1);
namespace Linked3\Classes\Diagram;
use Linked3\Classes\Content\Pipeline\ContentPipelineInterface;
if (!defined('ABSPATH')) exit;

final class DiagramPipeline implements ContentPipelineInterface
{
    public static function type(): string { return 'diagram'; }
    public static function label(): string { return __('知识图谱', 'linked3'); }

    public function prepare(array $input): array
    {
        $topic = sanitize_text_field($input['topic'] ?? '');
        $content = wp_strip_all_tags($input['script'] ?? '');
        if (empty($topic) && empty($content)) throw new \InvalidArgumentException(__('请填写主题或粘贴文章内容', 'linked3'));
        
        // v27.17.9-fix1: 读取前端发送的 structure 参数 (替代旧 diagram_type)
        $structure = sanitize_key($input['structure'] ?? ($input['options']['diagram_type'] ?? 'auto'));
        $density = $input['options']['density'] ?? 'auto';
        $brand = $input['options']['brand'] ?? '知识图谱';
        
        return [
            'topic'     => $topic,
            'content'   => $content,
            'brand'     => $brand,
            'structure' => $structure,
            'density'   => $density,
            // v27.17.9-fix1: 传递生成配置
            'cfg_composite' => !empty($input['cfg_composite']),
            'cfg_cos'       => !empty($input['cfg_cos']),
            'cfg_seo'       => !empty($input['cfg_seo']),
            'cfg_risk'      => !empty($input['cfg_risk']),
            'composite_levers' => $input['composite_levers'] ?? [],
        ];
    }

    public function generate(array $context, ?callable $progressCb = null): array
    {
        if ($progressCb) $progressCb(50, 'generating', __('生成图示脚本...', 'linked3'));
        if (!class_exists('\Linked3\Classes\Diagram\DiagramMasterTemplate')) throw new \RuntimeException(__('图示引擎未加载', 'linked3'));
        
        // v27.17.9-fix1: 根据结构选择生成 zones (替代旧4Band硬编码)
        $structure_id = $context['structure'] ?? 'auto';
        $zones = [];
        if ($structure_id === 'auto' || $structure_id === '4band') {
            // 自动模式: 使用默认4Band或由 Master_Template 决定
            $zones = ['hook', 'body', 'proof', 'cta'];
        } elseif (class_exists('\Linked3\Classes\Diagram\DiagramStructureRegistry')) {
            $struct = \Linked3\Classes\Diagram\DiagramStructureRegistry::get($structure_id);
            if ($struct && isset($struct['zones'])) {
                $zones = $struct['zones'];
            }
        }
        
        $config = [
            'id'         => 'DIAGRAM_' . date('Ymd_His'),
            'brand'      => $context['brand'],
            'main_title' => "《{$context['topic']}全景图谱》",
            'bands'      => $zones, // v27.17.9-fix1: 使用结构 zones 替代硬编码4Band
            'structure'  => $structure_id,
            'endpoint'   => ['type' => 'auto'],
            // v27.17.9-fix1: 传递生成配置到模板
            'cfg_composite' => $context['cfg_composite'] ?? false,
            'cfg_cos'       => $context['cfg_cos'] ?? false,
            'cfg_seo'       => $context['cfg_seo'] ?? false,
            'cfg_risk'      => $context['cfg_risk'] ?? false,
            'composite_levers' => $context['composite_levers'] ?? [],
        ];
        $template = new \DiagramMasterTemplate();
        $result = $template->generate($config);
        if ($progressCb) $progressCb(100, 'done', __('完成', 'linked3'));
        return ['diagram' => $result, 'config' => $config];
    }

    public function deliver(array $result): array
    {
        return ['success' => true, 'diagram' => $result['diagram']];
    }

}
