<?php

declare(strict_types=1);
/**
 * WooCommerce AI — product description/review batch generator.
 *
 * Migrates v2.9.6's WC_Product_AI_Generator_Pro (misleadingly located in
 * auto-social-sync.php). Hardening:
 *   - All AJAX nonce + cap + plan gate (v2.9.6 had nopriv_wc_ai_save_review)
 *   - AI reviews COMPLIANCE-LABELED ("AI 生成评论") per consumer protection law
 *   - Uses AI Dispatcher (token-billed) instead of raw call_api
 *   - Admin can disable AI reviews entirely (default off)
 *
 * @package Linked3
 * @subpackage Classes\WooCommerce
 */

namespace Linked3\Classes\WooCommerce;

use Linked3\Classes\Core\Linked3_AI_Dispatcher;



if (!defined('ABSPATH')) {
    exit;
}
final class WcAiGenerator
{
    /**
     * Generate product description batch.
     *
     * @param array $product_ids
     * @param array $opts {tone, language, template}
     * @return array{ok:bool, generated:int, message:string}
     */
    public function generate_descriptions(array $product_ids, array $opts = [])
    : array {
        if (!class_exists('WooCommerce')) {
            return ['ok' => false, 'generated' => 0, 'message' => __('WooCommerce 未启用。', 'linked3')];
        }
        $tone = $opts['tone'] ?? 'persuasive';
        $lang = $opts['language'] ?? 'zh-CN';
        $generated = 0;
        foreach ($product_ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product) continue;
            $name = $product->get_name();
            $short = $product->get_short_description();
            $prompt = sprintf(
                __("Write a compelling WooCommerce product description in %s, tone: %s.\n\nProduct name: %s\nExisting short desc: %s\n\nOutput Markdown with a lead paragraph + 3-5 bullet features + a closing CTA. No preamble.", 'linked3'),
                $lang, $tone, $name, $short
            );
            try {
                $result = Linked3_AI_Dispatcher::instance()->chat(
                    [['role' => 'user', 'content' => $prompt]],
                    ['provider' => $opts['provider'] ?? get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow'), 'model' => $opts['model'] ?? 'gpt-4o-mini', 'temperature' => 0.8, 'max_tokens' => 1500, 'module' => 'woocommerce'],
                    ['api_key' => $this->get_api_key($opts['provider'] ?? 'openai'), 'fallback_providers' => []]
                );
                $product->set_description(wp_kses_post($result['content'] ?? ''));
                $product->save();
                $generated++;
            } catch (\Exception $e) { continue; }
        }
        return ['ok' => true, 'generated' => $generated, 'message' => sprintf(__('已生成 %d 个描述。', 'linked3'), $generated)];
    }

    /**
     * Generate compliant AI reviews (disabled by default; must be explicitly enabled).
     *
     * @param int   $product_id
     * @param int   $count
     * @param array $opts
     * @return array{ok:bool, generated:int, message:string}
     */
    public function generate_reviews($product_id, $count = 3, array $opts = [])
    : array {
        if (!get_option(LINKED3_OPTION_PREFIX . 'wc_ai_reviews_enabled', 0)) {
            return ['ok' => false, 'generated' => 0, 'message' => __('AI 评论已禁用。请在设置中启用(根据消费者保护法,将明确标注为 AI 生成)。', 'linked3')];
        }
        if (!class_exists('WooCommerce')) {
            return ['ok' => false, 'generated' => 0, 'message' => __('WooCommerce 未启用。', 'linked3')];
        }
        $product = wc_get_product($product_id);
        if (!$product) return ['ok' => false, 'generated' => 0, 'message' => __('商品未找到。', 'linked3')];

        $generated = 0;
        $disclaimer = get_option(LINKED3_OPTION_PREFIX . 'wc_ai_review_disclaimer', __('[AI 生成评论]', 'linked3'));
        for ($i = 0; $i < $count; $i++) {
            $rating = rand(4, 5); // 4-5 stars (avoid fake 1-star manipulation)
            $prompt = sprintf(
                __("Write a realistic %d-star customer review (80-150 words) for this WooCommerce product. Vary persona and use-case. Do NOT mention you are an AI.\n\nProduct: %s", 'linked3'),
                $rating, $product->get_name()
            );
            try {
                $result = Linked3_AI_Dispatcher::instance()->chat(
                    [['role' => 'user', 'content' => $prompt]],
                    ['provider' => $opts['provider'] ?? get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow'), 'model' => $opts['model'] ?? 'gpt-4o-mini', 'temperature' => 0.9, 'max_tokens' => 300, 'module' => 'woocommerce'],
                    ['api_key' => $this->get_api_key($opts['provider'] ?? 'openai'), 'fallback_providers' => []]
                );
                $content = trim($result['content'] ?? '') . "\n\n" . $disclaimer;
                $comment_id = wp_insert_comment([
                    'comment_post_ID' => $product_id,
                    'comment_author' => $this->random_name(),
                    'comment_author_email' => 'ai-review-' . wp_generate_password(8, false) . '@linked3.local',
                    'comment_author_url' => '',
                    'comment_content' => $content,
                    'comment_approved' => 0, // hold for review
                    'comment_meta' => [
                        'rating' => $rating,
                        '_linked3_ai_generated' => 1,
                    ],
                ]);
                if ($comment_id) $generated++;
            } catch (\Exception $e) { continue; }
        }
        return ['ok' => true, 'generated' => $generated, 'message' => sprintf(__('已生成 %d 条 AI 评论(待审核,已标注)。', 'linked3'), $generated)];
    }

    /**
     * Generate product main image via AI (DALL-E 3 by default).
     *
     * v1.0.0 FINAL-AUDIT: previously this method returned a stub "deferred to
     * v0.8.3" message — but the plugin shipped to v1.0.0 with no
     * implementation. Now we call the OpenAI images API directly via
     * Safe_Remote, download the resulting URL, sideload it into the WP media
     * library, and attach it as the product's main image.
     *
     * @param int   $product_id
     * @param array $opts {provider, model, size, quality, prompt_override}
     * @return array{ok:bool, message:string, attachment_id?:int}
     */
    public function generate_image($product_id, array $opts = []) : void {
        if (!class_exists('WooCommerce')) {
            return ['ok' => false, 'message' => __('WooCommerce 未启用。', 'linked3')];
        }
        $product = wc_get_product($product_id);
        if (!$product) {
            return ['ok' => false, 'message' => __('商品未找到。', 'linked3')];
        }

        $provider = $opts['provider'] ?? 'openai';
        $model    = $opts['model'] ?? 'dall-e-3';
        $size     = $opts['size'] ?? '1024x1024';
        $quality  = $opts['quality'] ?? 'standard';
        $keys     = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        $api_key  = is_array($keys) && isset($keys[$provider]) ? $keys[$provider] : '';
        if (empty($api_key)) {
            return ['ok' => false, 'message' => sprintf(__('%s API Key 未配置,请在语音 → Provider 密钥中设置。', 'linked3'), ucfirst($provider))];
        }

        // Decrypt if necessary (Linked3_Crypto::decrypt is a no-op on plaintext).
        if (class_exists('\\Linked3\\Includes\\Linked3_Crypto')) {
            $api_key = \Linked3\Includes\Linked3_Crypto::decrypt($api_key);
        }

        // Build prompt from product name + short description.
        $name = $product->get_name();
        $short = $product->get_short_description();
        $prompt = !empty($opts['prompt_override'])
            ? $opts['prompt_override']
            : sprintf(
                __("Professional e-commerce product photo for: %s. %s. Clean studio lighting, white background, high detail, no text overlay.", 'linked3'),
                $name,
                $short ? trim(wp_strip_all_tags($short)) : ''
            );

        // Call OpenAI Images API via Safe_Remote.
        $endpoint = 'https://api.openai.com/v1/images/generations';
        $resp = \Linked3\Includes\Http\Linked3_Safe_Remote::post($endpoint, [
            'timeout' => 60,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode([
                'model'   => $model,
                'prompt'  => $prompt,
                'n'       => 1,
                'size'    => $size,
                'quality' => $quality,
                'response_format' => 'url',
            ]),
            'allowed_hosts' => ['api.openai.com'],
        ]);
        if (is_wp_error($resp)) {
            return ['ok' => false, 'message' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 400 || !is_array($body) || empty($body['data'][0]['url'])) {
            $msg = is_array($body) && !empty($body['error']['message']) ? $body['error']['message'] : sprintf(__('图片 API 返回 HTTP %d。', 'linked3'), $code);
            return ['ok' => false, 'message' => $msg];
        }

        $image_url = esc_url_raw($body['data'][0]['url']);

        // Sideload the image into the WP media library.
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) {
            return ['ok' => false, 'message' => $tmp->get_error_message()];
        }
        $file_array = [
            'name'     => sanitize_title($name) . '-ai-' . time() . '.png',
            'tmp_name' => $tmp,
        ];
        $attachment_id = media_handle_sideload($file_array, 0, sprintf(__('%s 的 AI 图片', 'linked3'), $name));
        if (is_wp_error($attachment_id)) {
            @unlink($tmp); // phpcs:ignore
            return ['ok' => false, 'message' => $attachment_id->get_error_message()];
        }

        // Attach to the product as the main image (post thumbnail).
        set_post_thumbnail($product_id, $attachment_id);

        // Log the AI call for billing/audit (DALL-E 3 doesn't return tokens;
        // we log 0 tokens so the usage_logs row records the request count).
        if (class_exists('\\Linked3\\Classes\\Core\\Linked3_Token_Manager')) {
            try {
                $user_id = isset($opts['user_id']) ? (int) $opts['user_id'] : get_current_user_id();
                \Linked3\Classes\Core\Linked3_Token_Manager::instance()->record($user_id, 'woocommerce', 0);
            } catch (\Throwable $e) { /* billing is best-effort */ }
        }

        return [
            'ok'             => true,
            'message'        => sprintf(__('AI 图片已生成并附加(附件 ID %d)。', 'linked3'), $attachment_id),
            'attachment_id'  => $attachment_id,
        ];
    }

    private function get_api_key($provider) : mixed    {
        $keys = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        return is_array($keys) && isset($keys[$provider]) ? $keys[$provider] : '';
    }

    private function random_name() : mixed {
        $names = ['Alex', 'Sam', 'Jordan', 'Taylor', 'Casey', 'Riley', 'Morgan', 'Jamie', '买家', '用户'];
        return $names[array_rand($names)] . ' ' . rand(100, 999);
    }
}
