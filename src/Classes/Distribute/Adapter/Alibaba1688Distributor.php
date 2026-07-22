<?php

declare(strict_types=1);
/**
 * 1688 开放平台分发器 — v3.0.0
 *
 * 工厂场景: 把产品介绍分发到 1688 平台。
 *
 * 鉴权方式: OAuth2 + 签名 (与阿里国际站类似但有差异)
 *   - app_key + app_secret
 *  - access_token (OAuth2 授权码换取)
 *  - 签名算法: HMAC-SHA1 + base64 (与阿里国际站的 MD5 不同)
 *
 * 主要 API:
 *   - alibaba.trade.product.publish  产品发布
 *  - alibaba.trade.product.image.upload  图片上传
 *  - alibaba.trade.product.list  产品列表
 *
 * @package Linked3
 * @subpackage Classes\Distribute\Adapter
 */

namespace Linked3\Classes\Distribute\Adapter;

use Linked3\Classes\Distribute\DistributeAdapterInterface;
use Linked3\Includes\Http\SafeRemote;



if (!defined('ABSPATH')) {
    exit;
}
final class Alibaba1688Distributor implements DistributeAdapterInterface
{
    public function slug() : string { return 'alibaba1688'; }
    public function label() : string { return '1688 开放平台'; }

    const API_BASE = 'https://gw.open.1688.com/openapi';

    public function publish(array $post_data, array $config) {
        $app_key = $config['app_key'] ?? '';
        $app_secret = $config['app_secret'] ?? '';
        $access_token = $config['access_token'] ?? '';
        $member_id = $config['member_id'] ?? '';

        if (!$app_key || !$app_secret || !$access_token) {
            return ['ok' => false, 'remote_id' => '', 'message' => __('缺少 app_key/app_secret/access_token', 'linked3-ai')];
        }

        // 步骤 1: 上传产品主图
        $image_url = '';
        if (!empty($post_data['image_url'])) {
            $img_result = $this->upload_image($post_data['image_url'], $config);
            if (!empty($img_result['image_url'])) {
                $image_url = $img_result['image_url'];
            }
        }

        // 步骤 2: 构造产品数据
        $product = [
            'subject'         => mb_substr($post_data['title'] ?? '', 0, 128),
            'description'     => mb_substr($post_data['content'] ?? '', 0, 50000),
            'mainImage'       => $image_url,
            'sourceUrl'       => $post_data['url'] ?? '',
        ];

        // 步骤 3: 调用产品发布 API
        $api_name = 'com.alibaba.product/alibaba.product.add';
        $api_params = [
            'access_token' => $access_token,
            'appKey'       => $app_key,
            'memberId'     => $member_id,
            'productInfo'  => wp_json_encode($product),
        ];
        $url = $this->build_signed_url($api_name, $api_params, $app_key, $app_secret);

        $resp = SafeRemote::post($url, [
            'timeout' => 60,
            'body'    => $api_params,
            'allowed_hosts' => ['gw.open.1688.com'],
        ]);
        if (is_wp_error($resp)) {
            return ['ok' => false, 'remote_id' => '', 'message' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);

        if ($code >= 400 || !empty($body['errorCode'])) {
            $msg = $body['errorMessage'] ?? "HTTP {$code}";
            return ['ok' => false, 'remote_id' => '', 'message' => sprintf('1688 推送失败: %s', $msg)];
        }

        $product_id = (string) ($body['result']['productID'] ?? '');
        return ['ok' => true, 'remote_id' => $product_id, 'message' => sprintf('已推送到 1688 (产品 ID: %s)', $product_id)];
    }

    public function test(array $config) {
        $app_key = $config['app_key'] ?? '';
        $app_secret = $config['app_secret'] ?? '';
        $access_token = $config['access_token'] ?? '';
        if (!$app_key || !$app_secret || !$access_token) {
            return ['ok' => false, 'message' => __('缺少 app_key/app_secret/access_token', 'linked3-ai')];
        }
        // v3.0.0: 调用产品列表 API 验证凭证
        $api_name = 'com.alibaba.product/alibaba.product.list';
        $api_params = [
            'access_token' => $access_token,
            'appKey'       => $app_key,
            'page'         => '1',
            'size'         => '1',
        ];
        $url = $this->build_signed_url($api_name, $api_params, $app_key, $app_secret);

        $resp = SafeRemote::post($url, [
            'timeout' => 15,
            'body'    => $api_params,
            'allowed_hosts' => ['gw.open.1688.com'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code === 200 && empty($body['errorCode'])) {
            $total = $body['result']['totalCount'] ?? 0;
            return ['ok' => true, 'message' => sprintf('1688 已连接 (现有产品: %s)', $total)];
        }
        $msg = $body['errorMessage'] ?? "HTTP {$code}";
        return ['ok' => false, 'message' => sprintf('1688 连接失败: %s', $msg)];
    }

    /**
     * 上传产品图片到 1688。
     */
    private function upload_image($image_url, array $config): array {
        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) return ['image_url' => ''];

        $app_key = $config['app_key'] ?? '';
        $app_secret = $config['app_secret'] ?? '';
        $access_token = $config['access_token'] ?? '';

        $api_name = 'com.alibaba.product/alibaba.product.image.upload';
        $api_params = [
            'access_token' => $access_token,
            'appKey'       => $app_key,
        ];
        $url = $this->build_signed_url($api_name, $api_params, $app_key, $app_secret);

        // multipart 上传
        $boundary = wp_generate_password(24, false);
        $body = '';
        foreach ($api_params as $k => $v) {
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="' . $k . "\"\r\n\r\n";
            $body .= $v . "\r\n";
        }
        $filename = 'linked3-' . time() . '.jpg';
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="image"; filename="' . $filename . "\"\r\n";
        $body .= "Content-Type: image/jpeg\r\n\r\n";
        $body .= file_get_contents($tmp) . "\r\n";
        $body .= '--' . $boundary . "--\r\n";
        @unlink($tmp);

        $resp = SafeRemote::post($url, [
            'timeout' => 60,
            'headers' => ['Content-Type' => 'multipart/form-data; boundary=' . $boundary],
            'body'    => $body,
            'allowed_hosts' => ['gw.open.1688.com'],
        ]);
        if (is_wp_error($resp)) return ['image_url' => ''];
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        return ['image_url' => $json['result']['imageUrl'] ?? ''];
    }

    /**
     * 1688 签名算法 (HMAC-SHA1 + base64)。
     *
     * 算法:
     *   1. URL = API_BASE + "/param/2/" + api_name + "/" + app_key
     *   2. 拼接签名串: url + 所有可能参数(按 key 排序) + app_secret
     *   3. sign = base64_encode(hash_hmac('sha1', sign_string, app_secret, true))
     *   4. 最终 URL = url + "?url_sign=" + sign
     */
    private function build_signed_url($api_name, array $params, $app_key, $app_secret) : mixed {
        $url = self::API_BASE . '/param/2/' . $api_name . '/' . $app_key;

        // 签名串: url + 排序后的所有参数 (keyvalue 形式) + app_secret
        ksort($params);
        $sign_string = $url;
        foreach ($params as $k => $v) {
            $sign_string .= $k . $v;
        }
        $sign_string .= $app_secret;

        // HMAC-SHA1 + base64
        $sign = base64_encode(hash_hmac('sha1', $sign_string, $app_secret, true));
        $sign = urlencode($sign);

        return $url . '?url_sign=' . $sign;
    }
}
