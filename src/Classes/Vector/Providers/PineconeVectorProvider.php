<?php

declare(strict_types=1);
/**
 * Pinecone vector provider — cloud-hosted vector DB.
 *
 * @package Linked3
 * @subpackage Classes\Vector\Providers
 */

namespace Linked3\Classes\Vector\Providers;

use Linked3\Classes\Vector\VectorProviderInterface;
use Linked3\Includes\Http\SafeRemote;



if (!defined('ABSPATH')) {
    exit;
}
final class PineconeVectorProvider implements VectorProviderInterface
{
    public function slug() : string { return 'pinecone'; }

    public function connect(array $config)
    : array {
        $key = $config['api_key'] ?? '';
        $host = $config['index_host'] ?? '';
        if (!$key || !$host) return ['ok' => false, 'message' => __('缺少 api_key / index_host。', 'linked3')];
        return ['ok' => true, 'message' => __('Pinecone 已配置。', 'linked3')];
    }

    public function create_index($name, $dimensions, array $config)
    : array {
        // Pinecone indexes are created via the control plane API; for MVP
        // we assume the index already exists. Return ok so callers proceed.
        return ['ok' => true, 'message' => "Index assumed to exist ({$name})"];
    }

    public function upsert($index, array $vectors, array $config) : mixed {
        $key = $config['api_key'] ?? '';
        $host = rtrim($config['index_host'] ?? '', '/');
        if (!$key || !$host) return ['ok' => false, 'message' => __('缺少 api_key / index_host。', 'linked3')];
        $namespace = $config['namespace'] ?? 'default';

        $body = ['vectors' => []];
        foreach ($vectors as $v) {
            $body['vectors'][] = [
                'id' => (string) $v['id'],
                'values' => array_map('floatval', $v['embedding']),
                'metadata' => (object) ($v['metadata'] ?? new \stdClass()),
            ];
        }
        $resp = SafeRemote::post("{$host}/vectors/upsert", [
            'timeout' => 30,
            'headers' => [
                'Api-Key' => $key,
                'Content-Type' => 'application/json',
                'X-Pinecone-API-Version' => '2024-07',
            ],
            'body' => wp_json_encode($body),
            'allowed_hosts' => [wp_parse_url($host, PHP_URL_HOST)],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        return $code < 400
            ? ['ok' => true, 'message' => sprintf(__('已写入 %d 个向量。', 'linked3'), count($vectors))]
            : ['ok' => false, 'message' => sprintf('Pinecone HTTP %d: %s', $code, substr(wp_remote_retrieve_body($resp), 0, 200))];
    }

    public function query($index, array $query_vector, $top_k = 5, array $filters = [], array $config = []) : mixed     {
        $key = $config['api_key'] ?? '';
        $host = rtrim($config['index_host'] ?? '', '/');
        if (!$key || !$host) return [];
        $body = [
            'vector' => array_map('floatval', $query_vector),
            'topK' => (int) $top_k,
            'includeMetadata' => true,
        ];
        if (!empty($filters['post_type'])) {
            $body['filter'] = ['post_type' => ['$eq' => sanitize_text_field($filters['post_type'])]];
        }
        $resp = SafeRemote::post("{$host}/query", [
            'timeout' => 30,
            'headers' => [
                'Api-Key' => $key,
                'Content-Type' => 'application/json',
                'X-Pinecone-API-Version' => '2024-07',
            ],
            'body' => wp_json_encode($body),
            'allowed_hosts' => [wp_parse_url($host, PHP_URL_HOST)],
        ]);
        if (is_wp_error($resp)) return [];
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($json['matches'])) return [];
        $out = [];
        foreach ($json['matches'] as $m) {
            $out[] = [
                'id' => (string) ($m['id'] ?? ''),
                'score' => (float) ($m['score'] ?? 0),
                'metadata' => (array) ($m['metadata'] ?? []),
            ];
        }
        return $out;
    }

    public function delete($index, array $ids, array $config) : mixed {
        $key = $config['api_key'] ?? '';
        $host = rtrim($config['index_host'] ?? '', '/');
        if (!$key || !$host) return ['ok' => false, 'message' => __('缺少 api_key / index_host。', 'linked3')];
        $resp = SafeRemote::post("{$host}/vectors/delete", [
            'timeout' => 30,
            'headers' => ['Api-Key' => $key, 'Content-Type' => 'application/json', 'X-Pinecone-API-Version' => '2024-07'],
            'body' => wp_json_encode(['ids' => array_map('strval', $ids)]),
            'allowed_hosts' => [wp_parse_url($host, PHP_URL_HOST)],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        return $code < 400
            ? ['ok' => true, 'message' => sprintf(__('已删除 %d 个向量。', 'linked3'), count($ids))]
            : ['ok' => false, 'message' => sprintf('Pinecone HTTP %d', $code)];
    }

    public function embed($text, array $config) : mixed     {
        // Pinecone does not host embedding models; defer to AI Dispatcher's
        // embed via OpenAI-compatible provider (same as Local provider).
        $provider = \Linked3\Classes\Core\Providers\ProviderFactory::instance()->make($config['embed_provider'] ?? 'openai');
        if (!$provider) return new \WP_Error('no_provider', __('无嵌入 Provider。', 'linked3'));
        $payload = $provider->format_embed_payload($text, ['model' => $config['embed_model'] ?? 'text-embedding-3-small'], $config);
        $url = $provider->build_api_url('embed', $config);
        $resp = SafeRemote::post($url, [
            'timeout' => 30,
            'headers' => $provider->get_api_headers($config),
            'body' => wp_json_encode($payload),
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
        ]);
        if (is_wp_error($resp)) return $resp;
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $vectors = $provider->parse_embed_response($body, $config);
        return $vectors[0] ?? [];
    }
}
