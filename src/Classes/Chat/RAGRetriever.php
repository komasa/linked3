<?php

declare(strict_types=1);
/**
 * RAG Retriever — embeds user query, retrieves top-K context, injects into prompt.
 *
 * @package Linked3
 * @subpackage Classes\Chat
 */

namespace Linked3\Classes\Chat;

use Linked3\Classes\Vector\VectorFactory;
use Linked3\Classes\Vector\PostProcessor\PostProcessor;



if (!defined('ABSPATH')) {
    exit;
}
final class RAGRetriever
{
    /**
     * @param string $query
     * @param int    $top_k
     * @return array<int,array{content:string, title:string, url:string, score:float}>
     */
    public function retrieve(string $query, int $top_k = 5) : mixed {
        $config = get_option(LINKED3_OPTION_PREFIX . 'vector_config', []);
        if (empty($config['enabled'])) return [];
        $provider = VectorFactory::instance()->make($config['provider'] ?? 'local');
        if (!$provider) return [];

        $emb = $provider->embed($query, $config);
        if (is_wp_error($emb) || empty($emb)) return [];

        $results = $provider->query(PostProcessor::INDEX_NAME, $emb, $top_k, [], $config);
        $out = [];
        foreach ($results as $r) {
            $meta = $r['metadata'] ?? [];
            $post = get_post($meta['post_id'] ?? 0);
            if (!$post) continue;
            // Re-fetch the chunk text from the post (we only stored metadata).
            $content = wp_strip_all_tags($post->post_content);
            $out[] = [
                'content' => mb_substr($content, 0, 2000),
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'score' => (float) $r['score'],
            ];
        }
        return $out;
    }

    /**
     * Build a system prompt augmentation with retrieved context.
     *
     * @param array $context
     * @return string
     */
    public function build_context_prompt(array $context) : mixed     {
        if (empty($context)) return '';
        $lines = [__('使用以下站点知识库的上下文来回答。通过 URL 引用来源。如果上下文不足,请说明。', 'linked3')];
        foreach ($context as $i => $c) {
            $lines[] = sprintf("[%d] %s\nURL: %s\n%s", $i + 1, $c['title'], $c['url'], $c['content']);
        }
        return implode("\n\n", $lines);
    }
}
