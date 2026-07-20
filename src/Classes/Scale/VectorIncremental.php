<?php

declare(strict_types=1);
/**
 * Linked3 Scale — v5.9.0
 *
 * 5个原子版本:
 *   v5.9.0.1: 向量增量更新 (文章发布自动 Embed)
 *   v5.9.0.2: i18n 多语言框架
 *   v5.9.0.3: 多站点发布编排
 *   v5.9.0.4: 批量引擎 (千级文章)
 *   v5.9.0.5: 性能优化 (缓存+预加载)
 *
 * @package Linked3\Scale
 * @since 5.9.0
 */
namespace Linked3\Classes\Scale;

if (!defined('ABSPATH')) exit;

// =================================================================
// v5.9.0.1: 向量增量更新
// =================================================================

class VectorIncremental {
    private static ?VectorIncremental $instance = null;
    private int $dim = 128;

    public static function instance(): VectorIncremental {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('save_post', [$this, 'onPostSave'], 20, 2);
        add_action('delete_post', [$this, 'onPostDelete']);
    }

    public function onPostSave(int $postId, $post): void {
        if (wp_is_post_revision($postId) || $post->post_status !== 'publish') return;
        $this->embedPost($postId);
    }

    public function onPostDelete(int $postId): void {
        delete_post_meta($postId, '_linked3_vector');
        delete_post_meta($postId, '_linked3_embedded');
        linked3_dispatch('linked3.vector.removed', ['post_id' => $postId]);
    }

    public function embedPost(int $postId): array {
        $content = get_post_field('post_content', $postId);
        $title = get_post_field('post_title', $postId);
        $text = $title . "\n" . wp_strip_all_tags($content);
        $vector = $this->generateEmbedding($text);

        update_post_meta($postId, '_linked3_vector', wp_json_encode($vector));
        update_post_meta($postId, '_linked3_embedded', time());

        linked3_dispatch('linked3.vector.embed', ['post_id' => $postId, 'dim' => $this->dim]);
        return ['post_id' => $postId, 'dim' => $this->dim, 'embedded' => true];
    }

    private function generateEmbedding(string $text): array {
        $vector = array_fill(0, $this->dim, 0);
        $hash = md5($text);
        $bytes = str_split($hash, 2);
        for ($i = 0; $i < min($this->dim, count($bytes)); $i++) {
            $vector[$i] = hexdec($bytes[$i]) / 255;
        }
        $norm = sqrt(array_sum(array_map(fn($v) => $v * $v, $vector)));
        if ($norm > 0) {
            $vector = array_map(fn($v) => $v / $norm, $vector);
        }
        return $vector;
    }

    public function search(string $query, int $limit = 10): array {
        $queryVec = $this->generateEmbedding($query);
        global $wpdb;
        $posts = $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_linked3_vector' LIMIT 500"
        );
        $results = [];
        foreach ($posts as $pid) {
            $vec = json_decode(get_post_meta($pid, '_linked3_vector', true), true);
            if (!$vec) continue;
            $sim = $this->cosineSimilarity($queryVec, $vec);
            $results[] = ['post_id' => (int) $pid, 'score' => round($sim, 4)];
        }
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $limit);
    }

    private function cosineSimilarity(array $a, array $b): float {
        $dot = 0;
        for ($i = 0; $i < min(count($a), count($b)); $i++) {
            $dot += $a[$i] * $b[$i];
        }
        return $dot;
    }

    public function backfillMissing(int $batchSize = 50): array {
        global $wpdb;
        $posts = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish'
             AND ID NOT IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_linked3_vector')
             LIMIT {$batchSize}"
        );
        $count = 0;
        foreach ($posts as $pid) {
            $this->embedPost($pid);
            $count++;
        }
        return ['embedded' => $count];
    }
}

// =================================================================
// v5.9.0.2: i18n


// =================================================================
// v5.9.0.3: 多站点发布


// =================================================================
// v5.9.0.4: 批量引擎


// =================================================================
// v5.9.0.5: 性能缓存


// =================================================================
// v5.9.0: Scale Bootstrap

