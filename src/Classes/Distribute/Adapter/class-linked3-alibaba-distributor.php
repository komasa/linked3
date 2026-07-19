<?php
/**
 * 阿里国际站 (Alibaba.com Open Platform) 分发器 — v3.0.0
 *
 * 工厂场景: 把产品介绍/产品文档分发到阿里国际站。
 *
 * 鉴权方式: OAuth2 + app_signature (MD5 复合签名)
 *   1. 用户在阿里开放平台注册应用,获得 app_key + app_secret
 *  2. OAuth2 授权获取 access_token + refresh_token
 *  3. 每个 API 请求需计算签名: sign = MD5(排序拼接的所有参数 + app_secret)
 *
 * 主要 API:
 *   - alibaba.solution.product.push    推送商品
 *  - alibaba.solution.product.upload.image  上传图片
 *  - alibaba.solution.product.list    商品列表
 *
 * 配置字段:
 *   - app_key:        应用 Key
 *   - app_secret:     应用 Secret
 *   - access_token:   OAuth2 访问令牌
 *  - refresh_token:   OAuth2 刷新令牌 (可选,用于自动续期)
 *  - company_id:      公司 ID (AliExpress 卖家 ID)
 *
 * @package Linked3
 * @subpackage Classes\Distribute\Adapter
 */

namespace Linked3\Classes\Distribute\Adapter;

use Linked3\Classes\Distribute\Linked3_Distribute_Adapter_Interface;
use Linked3\Includes\Http\Linked3_Safe_Remote;



if (!defined('ABSPATH')) {
    exit;
}
final class Linked3_Alibaba_Distributor implements Linked3_Distribute_Adapter_Interface
{
    public function slug() : string { return 'alibaba'; }
    public function label() : string { return '阿里国际站 (Alibaba.com)'; }

    const API_BASE = 'https://api.alibaba.com/rest';
    const AUTH_BASE = 'https://oauth.alibaba.com';

    public function publish(array $post_data, array $config)
    : array {
        $app_key = $config['app_key'] ?? '';
        $app_secret = $config['app_secret'] ?? '';
        $access_token = $config['access_token'] ?? '';
        $company_id = $config['company_id'] ?? '';

        if (!$app_key || !$app_secret || !$access_token) {
            return ['ok' => false, 'remote_id' => '', 'message' => __('缺少 app_key/app_secret/access_token', 'linked3-ai')];
        }

        // 步骤 1: 上传产品主图 (如果有的话)
        $image_url = '';
        if (!empty($post_data['image_url'])) {
            $img_result = $this->upload_image($post_data['image_url'], $config);
            if (!empty($img_result['image_url'])) {
                $image_url = $img_result['image_url'];
            }
        }

        // 步骤 2: 构造商品数据 (简化版,实际场景需用户扩展字段映射)
        $product = [
            'subject'       => mb_substr($post_data['title'] ?? '', 0, 128),
            'description'   => mb_substr($post_data['content'] ?? '', 0, 50000),
            'keyword'       => $post_data['title'] ?? '',
            'image'         => $image_url,
            'source_url'    => $post_data['url'] ?? '',
        ];

        // 步骤 3: 调用商品推送 API
        $api_params = [
            'app_key'       => $app_key,
            'access_token'  => $access_token,
            'method'        => 'alibaba.solution.product.push',
            'timestamp'     => date('Y-m-d H:i:s'),
            'sign_method'   => 'md5',
            'company_id'    => $company_id,
            'product'       => wp_json_encode($product),
        ];
        $api_params['sign'] = $this->compute_signature($api_params, $app_secret);

        $resp = Linked3_Safe_Remote::post(self::API_BASE, [
            'timeout' => 60,
            'body'    => $api_params,
            'allowed_hosts' => ['api.alibaba.com'],
        ]);
        if (is_wp_error($resp)) {
            return ['ok' => false, 'remote_id' => '', 'message' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);

        if ($code >= 400 || empty($body['result']['success'])) {
            $msg = $body['error_message'] ?? ($body['result']['error_message'] ?? "HTTP {$code}");
            return ['ok' => false, 'remote_id' => '', 'message' => sprintf('阿里国际站推送失败: %s', $msg)];
        }

        $product_id = (string) ($body['result']['product_id'] ?? '');
        return ['ok' => true, 'remote_id' => $product_id, 'message' => sprintf('已推送到阿里国际站 (产品 ID: %s)', $product_id)];
    }

    public function test(array $config)
    : array {
        $app_key = $config['app_key'] ?? '';
        $app_secret = $config['app_secret'] ?? '';
        $access_token = $config['access_token'] ?? '';
        if (!$app_key || !$app_secret || !$access_token) {
            return ['ok' => false, 'message' => __('缺少 app_key/app_secret/access_token', 'linked3-ai')];
        }
        // v3.0.0: 调用 alibaba.solution.product.list 验证凭证
        $api_params = [
            'app_key'       => $app_key,
            'access_token'  => $access_token,
            'method'        => 'alibaba.solution.product.list',
            'timestamp'     => date('Y-m-d H:i:s'),
            'sign_method'   => 'md5',
            'page_size'     => '1',
            'current_page'  => '1',
        ];
        $api_params['sign'] = $this->compute_signature($api_params, $app_secret);

        $resp = Linked3_Safe_Remote::post(self::API_BASE, [
            'timeout' => 15,
            'body'    => $api_params,
            'allowed_hosts' => ['api.alibaba.com'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code === 200 && !empty($body['result']['success'])) {
            $count = $body['result']['total_count'] ?? 0;
            return ['ok' => true, 'message' => sprintf('阿里国际站已连接 (现有商品: %s)', $count)];
        }
        $msg = $body['error_message'] ?? "HTTP {$code}";
        return ['ok' => false, 'message' => sprintf('阿里国际站连接失败: %s', $msg)];
    }

    /**
     * 上传产品图片到阿里国际站。
     */
    private function upload_image($image_url, array $config)
    : array {
        $app_key = $config['app_key'] ?? '';
        $app_secret = $config['app_secret'] ?? '';
        $access_token = $config['access_token'] ?? '';

        // 下载图片到临时文件
        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) return ['image_url' => ''];

        $api_params = [
            'app_key'       => $app_key,
            'access_token'  => $access_token,
            'method'        => 'alibaba.solution.product.upload.image',
            'timestamp'     => date('Y-m-d H:i:s'),
            'sign_method'   => 'md5',
            'image_type'    => 'product',
        ];
        $api_params['sign'] = $this->compute_signature($api_params, $app_secret);

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

        $resp = Linked3_Safe_Remote::post(self::API_BASE, [
            'timeout' => 60,
            'headers' => ['Content-Type' => 'multipart/form-data; boundary=' . $boundary],
            'body'    => $body,
            'allowed_hosts' => ['api.alibaba.com'],
        ]);
        if (is_wp_error($resp)) return ['image_url' => ''];
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        return ['image_url' => $json['result']['image_url'] ?? ''];
    }

    /**
     * 阿里国际站签名算法 (MD5 复合签名)。
     *
     * 算法:
     *   1. 所有请求参数按 key 字典序排序
     *   2. 拼接成 key1value1key2value2... 格式
     *   3. 前后各拼 app_secret
     *   4. MD5 取大写
     */
    private function compute_signature(array $params, $app_secret) : mixed {
        // 移除 sign 字段本身
        unset($params['sign']);
        // 按 key 字典序排序
        ksort($params);
        // 拼接
        $string_to_sign = $app_secret;
        foreach ($params as $k => $v) {
            $string_to_sign .= $k . $v;
        }
        $string_to_sign .= $app_secret;
        // MD5 大写
        return strtoupper(md5($string_to_sign));
    }
}
