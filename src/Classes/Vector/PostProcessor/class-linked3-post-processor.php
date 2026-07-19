<?php
/**
 * Post Processor — hooks save_post to auto-index content into the vector store.
 *
 * @package Linked3
 * @subpackage Classes\Vector\PostProcessor
 */

namespace Linked3\Classes\Vector\PostProcessor;

use Linked3\Classes\Vector\Linked3_Vector_Factory;



if (!defined('ABSPATH')) {
    exit;
}
final class Linked3_Post_Processor
{
    const INDEX_NAME = 'wp_content';

    /**
     * @param int     $post_id
     * @param \WP_Post $post
     * @param bool    $update
     * @return void
     */
    public static function on_save_post($post_id, $post, $update)
    : void {
        // Skip revisions + autosaves.
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        // Skip non-public post types.
        $pt = get_post_type_object($post->post_type);
        if (!$pt || !$pt->public) return;

        $config = get_option(LINKED3_OPTION_PREFIX . 'vector_config', []);
        if (empty($config['enabled'])) return;
        $provider_slug = $config['provider'] ?? 'local';
        $provider = Linked3_Vector_Factory::instance()->make($provider_slug);
        if (!$provider) return;

        $text = wp_strip_all_tags($post->post_content);
        $text = trim($text);
        if (mb_strlen($text) < 50) return; // too short to embed

        // Chunk long content (~1000 chars per chunk).
        $chunks = self::chunk($text, 1000);
        $vectors = [];
        foreach ($chunks as $i => $chunk) {
            $emb = $provider->embed($chunk, $config);
            if (is_wp_error($emb) || empty($emb)) continue;
            $vectors[] = [
                'id' => sprintf('%d_%d', $post_id, $i),
                'embedding' => $emb,
                'metadata' => [
                    'post_id' => $post_id,
                    'post_type' => $post->post_type,
                    'post_title' => $post->post_title,
                    'chunk_index' => $i,
                    'url' => get_permalink($post_id),
                ],
            ];
        }
        if (empty($vectors)) return;

        // Ensure index exists.
        $dim = count($vectors[0]['embedding']);
        $provider->create_index(self::INDEX_NAME, $dim, $config);
        $provider->upsert(self::INDEX_NAME, $vectors, $config);
    }

    /**
     * @param int $post_id
     * @return void
     */
    public static function on_delete_post($post_id)
    : void {
        $config = get_option(LINKED3_OPTION_PREFIX . 'vector_config', []);
        if (empty($config['enabled'])) return;
        $provider = Linked3_Vector_Factory::instance()->make($config['provider'] ?? 'local');
        if (!$provider) return;
        // We don't know chunk count; delete by querying first.
        // For local provider, we could use a filter. MVP: best-effort delete id_0..id_99.
        $ids = [];
        for ($i = 0; $i < 100; $i++) $ids[] = sprintf('%d_%d', $post_id, $i);
        $provider->delete(self::INDEX_NAME, $ids, $config);
    }

    /**
     * @param string $text
     * @param int    $size
     * @return string[]
     */
    private static function chunk($text, $size) : mixed {
        $text = trim($text);
        if (mb_strlen($text) <= $size) return [$text];
        $chunks = [];
        $len = mb_strlen($text);
        for ($i = 0; $i < $len; $i += $size) {
            $chunks[] = mb_substr($text, $i, $size);
        }
        return $chunks;
    }
}
