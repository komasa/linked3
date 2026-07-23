<?php

declare(strict_types=1);
namespace Linked3\Classes\AutoGPT\Processors;
use Linked3\Classes\Vector\VectorFactory;
use Linked3\Classes\Vector\PostProcessor\PostProcessor;


if (!defined('ABSPATH')) exit;
/**
 * Content indexing processor.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.AutoGPT.Processors
 * @since      27.1.0
 */

final class ContentIndexingProcessor implements AutoGPTProcessorInterface
{
    public function process(array $task): array {
        $cfg = $task['config'];
        $batch = (int) ($cfg['batch_size'] ?? 100);
        $processed = 0;
        $config = get_option(LINKED3_OPTION_PREFIX . 'vector_config', []);
        if (empty($config['enabled'])) {
            return ['ok' => false, 'message' => __('向量索引已禁用。', 'linked3'), 'items_processed' => 0];
        }
        $provider = VectorFactory::instance()->make($config['provider'] ?? 'local');
        if (!$provider) return ['ok' => false, 'message' => __('无向量服务。', 'linked3'), 'items_processed' => 0];

        // Find posts not yet indexed (use a postmeta flag).
        $posts = get_posts([
            'post_type' => 'any',
            'post_status' => 'publish',
            'numberposts' => $batch,
            'meta_query' => [['key' => '_linked3_indexed', 'compare' => 'NOT EXISTS']],
        ]);
        foreach ($posts as $p) {
            PostProcessor::on_save_post($p->ID, $p, true);
            update_post_meta($p->ID, '_linked3_indexed', 1);
            $processed++;
        }
        return ['ok' => true, 'message' => sprintf(__('已索引 %d 篇文章。', 'linked3'), $processed), 'items_processed' => $processed];
    }
}
