<?php
namespace Linked3\Classes\Content\Pipeline;
if (!defined('ABSPATH')) exit;

final class Linked3_Content_Pipeline_Registry
{
    private static array $pipelines = [];
    private static bool $initialized = false;

    public static function register(string $type, string $class): void
    {
        if (!in_array(Linked3_Content_Pipeline_Interface::class, class_implements($class) ?: [], true)) return;
        self::$pipelines[$type] = $class;
    }

    public static function list(): array
    {
        self::ensure_initialized();
        $result = [];
        foreach (self::$pipelines as $type => $class) {
            $result[$type] = ['type' => $type, 'label' => $class::label(), 'class' => $class];
        }
        return $result;
    }

    public static function dispatch(string $type, array $input, ?callable $progressCb = null): array
    {
        self::ensure_initialized();
        if (!isset(self::$pipelines[$type])) {
            return ['success' => false, 'message' => sprintf(__('Unknown content type: %s', 'linked3'), esc_html($type))];
        }
        $class = self::$pipelines[$type];
        try {
            $pipeline = new $class();
            $context = $pipeline->prepare($input);
            $result = $pipeline->generate($context, $progressCb);
            return $pipeline->deliver($result);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => WP_DEBUG ? $e->getMessage() : __('Internal error', 'linked3')];
        }
    }

    private static function ensure_initialized(): void
    {
        if (self::$initialized) return;
        self::$initialized = true;
        $defaults = [
            'article' => \Linked3\Classes\ContentWriter\ArticlePipeline::class,
            'comic'   => \Linked3\Classes\Genesis\Linked3_Comic_Pipeline::class,
            'diagram' => \Linked3\Classes\Diagram\Linked3_Diagram_Pipeline::class,
            'video'   => \Linked3\Classes\Media\Linked3_Video_Pipeline::class,
            'xhs'     => \Linked3\Classes\XHS\Linked3_XHS_Pipeline::class,
            'book'    => \Linked3\Classes\BookFactory\Linked3_Book_Pipeline::class,
        ];
        foreach ($defaults as $type => $class) {
            if (class_exists($class) && in_array(Linked3_Content_Pipeline_Interface::class, class_implements($class) ?: [], true)) {
                self::$pipelines[$type] = $class;
            }
        }
        self::$pipelines = apply_filters('linked3/content_pipelines', self::$pipelines);
    }

    public static function register_ajax(): void
    {
        add_action('wp_ajax_linked3_content_generate', [self::class, 'ajax_generate']);
        add_action('wp_ajax_linked3_content_list_types', [self::class, 'ajax_list_types']);
    }

    public static function ajax_generate(): void
    {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        $type = sanitize_key(wp_unslash($_POST['content_type'] ?? ''));
        
        // v27.17.9-fix1: 读取结构选择和生成配置参数
        $options = json_decode(wp_unslash($_POST['options'] ?? '{}'), true) ?: [];
        $structure = sanitize_key(wp_unslash($_POST['structure'] ?? 'auto'));
        
        $input = [
            'script'    => wp_strip_all_tags(wp_unslash($_POST['script'] ?? '')),
            'topic'     => sanitize_text_field(wp_unslash($_POST['topic'] ?? '')),
            'style'     => sanitize_text_field(wp_unslash($_POST['style'] ?? '')),
            'platform'  => sanitize_text_field(wp_unslash($_POST['platform'] ?? '')),
            'structure' => $structure,
            'options'   => $options,
            // 展开生成配置到顶层，方便 pipeline 读取
            'cfg_composite' => !empty($options['cfg_composite']),
            'cfg_cos'       => !empty($options['cfg_cos']),
            'cfg_seo'       => !empty($options['cfg_seo']),
            'cfg_risk'      => !empty($options['cfg_risk']),
            // 数量参数
            'word_count'    => intval($options['word_count'] ?? 0),
            'panel_count'   => intval($options['panel_count'] ?? 0),
            'video_groups'  => intval($options['video_groups'] ?? 0),
            'page_count'    => intval($options['page_count'] ?? 0),
            'chapter_count' => intval($options['chapter_count'] ?? 0),
        ];
        
        // v27.17.9-fix1: 如果启用了复合杠杆，注入到 input
        if ($input['cfg_composite'] && class_exists('\Linked3\Classes\MetaLever\Composite\Linked3_Composite_Lever_Registry')) {
            $input['composite_levers'] = \Linked3\Classes\MetaLever\Composite\Linked3_Composite_Lever_Registry::info();
        }
        
        // v27.17.9-fix1: 如果启用了 COS，标记使用三代演化
        if ($input['cfg_cos']) {
            $input['use_cos_evolution'] = true;
        }
        
        $result = self::dispatch($type, $input);
        if ($result['success'] ?? false) wp_send_json_success($result);
        else wp_send_json_error($result, 400);
    }

    public static function ajax_list_types(): void
    {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        wp_send_json_success(['types' => self::list()]);
    }
}
