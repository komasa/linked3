<?php

declare(strict_types=1);
/**
 * Qdrant vector provider — self-hosted or cloud vector DB.
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
final class QdrantVectorProvider implements VectorProviderInterface
{
    public function slug() : string { return 'qdrant'; }

    public function connect(array $config) : mixed {
        $url = rtrim($config['host_url'] ?? '', '/');
        $key = $config['api_key'] ?? '';
        if (!$url) return ['ok' => false, 'message' => __('缺少 host_url。', 'linked3')];
        $headers = ['Content-Type' => 'application/json'];
        if ($key) $headers['api-key'] = $key;
        $resp = SafeRemote::get($url . '/collections', [
            'timeout' => 15,
            'headers' => $headers,
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        return $code < 400
            ? ['ok' => true, 'message' => __('Qdrant 可访问。', 'linked3')]
            : ['ok' => false, 'message' => sprintf('Qdrant HTTP %d', $code)];
    }

    public function create_index($name, $dimensions, array $config) : mixed     {
        $url = rtrim($config['host_url'] ?? '', '/');
        $key = $config['api_key'] ?? '';
        $headers = ['Content-Type' => 'application/json'];
        if ($key) $headers['api-key'] = $key;
        $body = wp_json_encode([
            'vectors' => ['size' => (int) $dimensions, 'distance' => 'Cosine'],
        ]);
        $resp = SafeRemote::put("{$url}/collections/" . sanitize_key($name), [
            'timeout' => 30,
            'headers' => $headers,
            'body' => $body,
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        return $code < 400
            ? ['ok' => true, 'message' => "Collection {$name} ready"]
            : ['ok' => false, 'message' => sprintf('Qdrant HTTP %d', $code)];
    }

    public function upsert($index, array $vectors, array $config) : mixed {
        $url = rtrim($config['host_url'] ?? '', '/');
        $key = $config['api_key'] ?? '';
        $headers = ['Content-Type' => 'application/json'];
        if ($key) $headers['api-key'] = $key;
        $points = [];
        foreach ($vectors as $i => $v) {
            // Qdrant requires integer point IDs; hash the string ID.
            $points[] = [
                'id' => abs(crc32((string) $v['id'])),
                'vector' => array_map('floatval', $v['embedding']),
                'payload' => array_merge(['string_id' => (string) $v['id']], (array) ($v['metadata'] ?? [])),
            ];
        }
        $resp = SafeRemote::put("{$url}/collections/" . sanitize_key($index) . "/points?wait=true", [
            'timeout' => 30,
            'headers' => $headers,
            'body' => wp_json_encode(['points' => $points]),
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        return $code < 400
            ? ['ok' => true, 'message' => sprintf(__('已写入 %d 个向量。', 'linked3'), count($vectors))]
            : ['ok' => false, 'message' => sprintf('Qdrant HTTP %d: %s', $code, substr(wp_remote_retrieve_body($resp), 0, 200))];
    }

    public function query($index, array $query_vector, $top_k = 5, array $filters = [], array $config = []) : mixed     {
        $url = rtrim($config['host_url'] ?? '', '/');
        $key = $config['api_key'] ?? '';
        $headers = ['Content-Type' => 'application/json'];
        if ($key) $headers['api-key'] = $key;
        $body = [
            'vector' => array_map('floatval', $query_vector),
            'limit' => (int) $top_k,
            'with_payload' => true,
        ];
        if (!empty($filters['post_type'])) {
            $body['filter'] = ['must' => [['key' => 'post_type', 'match' => ['value' => sanitize_text_field($filters['post_type'])]]]];
        }
        $resp = SafeRemote::post("{$url}/collections/" . sanitize_key($index) . "/points/search", [
            'timeout' => 30,
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
        ]);
        if (is_wp_error($resp)) return [];
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($json['result'])) return [];
        $out = [];
        foreach ($json['result'] as $r) {
            $payload = (array) ($r['payload'] ?? []);
            $out[] = [
                'id' => (string) ($payload['string_id'] ?? $r['id']),
                'score' => (float) ($r['score'] ?? 0),
                'metadata' => $payload,
            ];
        }
        return $out;
    }

    public function delete($index, array $ids, array $config)
    {
        $url = rtrim($config['host_url'] ?? '', '/');
        $key = $config['api_key'] ?? '';
        $headers = ['Content-Type' => 'application/json'];
        if ($key) $headers['api-key'] = $key;
        $point_ids = array_map(static function ($id) { return abs(crc32((string) $id)); }, $ids);
        $resp = SafeRemote::post("{$url}/collections/" . sanitize_key($index) . "/points/delete?wait=true", [
            'timeout' => 30,
            'headers' => $headers,
            'body' => wp_json_encode(['points' => $point_ids]),
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        return $code < 400
            ? ['ok' => true, 'message' => sprintf(__('已删除 %d 个向量。', 'linked3'), count($ids))]
            : ['ok' => false, 'message' => sprintf('Qdrant HTTP %d', $code)];
    }

    public function embed($text, array $config)
    {
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
