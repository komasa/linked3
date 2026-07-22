<?php

declare(strict_types=1);
/**
 * WooCommerce Token Package — v3.1.0
 *
 * 功能:
 *   1. 注册自定义产品类型 "linked3_token_package"
 *   2. 产品编辑页 Meta Box: 配置 Token 数量 / 套餐升级
 *   3. 订单完成时自动给用户加 Token / 升级套餐
 *
 * 用法:
 *   - WooCommerce 后台新建产品,类型选 "Token Package"
 *   - 设置 Token 数量 (如 100000) 或套餐升级 (如 pro)
 *   - 用户购买并完成订单后,自动授权
 *
 * @package Linked3
 * @subpackage Classes\WooCommerce
 */

namespace Linked3\Classes\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

final class WcTokenPackage
{
    const META_TOKENS = '_linked3_tokens_to_grant';
    const META_PLAN = '_linked3_plan_to_grant';
    const META_USER_BALANCE = '_linked3_token_balance'; // 用户 Token 余额 (额外购买)

    public static function init(): void {
        // 注册产品类型 (WC init 后)
        add_filter('product_type_selector', [__CLASS__, 'add_product_type']);
        add_filter('woocommerce_product_class', [__CLASS__, 'product_class'], 10, 4);

        // 产品编辑页 Meta Box
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post_product', [__CLASS__, 'save_meta_box']);

        // 订单完成时自动授权
        add_action('woocommerce_order_status_completed', [__CLASS__, 'grant_on_order_complete']);

        // 产品类型 label
        add_filter('woocommerce_product_single_add_to_cart_text', [__CLASS__, 'add_to_cart_text']);
    }

    /**
     * 注册 "Token Package" 产品类型到下拉。
     */
    public static function add_product_type($types) : mixed {
        $types['linked3_token_package'] = __('Token Package', 'linked3');
        return $types;
    }

    /**
     * WC 产品类映射 (用简单产品类即可,无需自定义类)。
     */
    public static function product_class($classname, $product_type, $post_type, $product_id) : mixed     {
        if ($product_type === 'linked3_token_package') {
            return 'WC_Product_Simple';
        }
        return $classname;
    }

    /**
     * 产品编辑页 Meta Box。
     */
    public static function add_meta_box(): void {
        add_meta_box(
            'linked3_token_package',
            __('Linked3 Token Package', 'linked3'),
            [__CLASS__, 'render_meta_box'],
            'product',
            'side',
            'high'
        );
    }

    public static function render_meta_box($post): void {
        wp_nonce_field('linked3_token_package', 'linked3_token_package_nonce');
        $tokens = get_post_meta($post->ID, self::META_TOKENS, true);
        $plan = get_post_meta($post->ID, self::META_PLAN, true);
        $is_token_pkg = get_post_meta($post->ID, '_product_type', true) === 'linked3_token_package' ? '1' : '0';
        // 若是新建,从 $_GET 判断
        if (!$is_token_pkg && isset($_GET['product_type']) && sanitize_text_field(wp_unslash($_GET['product_type'])) === 'linked3_token_package') {
            $is_token_pkg = '1';
        }
        ?>
        <p>
            <label><?php esc_html_e('Token 数量', 'linked3'); ?>
                <input type="number" name="linked3_tokens_to_grant" value="<?php echo esc_attr($tokens); ?>" min="0" step="1000" style="width:100%;" placeholder="如 100000" />
            </label>
        </p>
        <p>
            <label><?php esc_html_e('套餐升级 (可选)', 'linked3'); ?>
                <select name="linked3_plan_to_grant" style="width:100%;">
                    <option value=""><?php esc_html_e('不升级,仅加 Token', 'linked3'); ?></option>
                    <option value="pro" <?php selected($plan, 'pro'); ?>><?php esc_html_e('Pro', 'linked3'); ?></option>
                    <option value="premium" <?php selected($plan, 'premium'); ?>><?php esc_html_e('Premium', 'linked3'); ?></option>
                </select>
            </label>
        </p>
        <p class="description"><?php esc_html_e('用户购买并完成订单后,自动获得 Token 或套餐升级。', 'linked3'); ?></p>
        <?php
    }

    /**
     * 保存 Meta Box。
     */
    public static function save_meta_box($post_id): void {
        if (!isset($_POST['linked3_token_package_nonce'])) return;
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['linked3_token_package_nonce'])), 'linked3_token_package')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $tokens = (int) (wp_unslash($_POST['linked3_tokens_to_grant'] ?? 0));
        $plan = sanitize_text_field(wp_unslash($_POST['linked3_plan_to_grant'] ?? ''));
        update_post_meta($post_id, self::META_TOKENS, $tokens);
        update_post_meta($post_id, self::META_PLAN, $plan);
    }

    /**
     * 订单完成时给用户加 Token / 升级套餐。
     */
    public static function grant_on_order_complete($order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $user_id = (int) $order->get_user_id();
        if (!$user_id) {
            // 记录日志,无用户无法授权
            \Linked3\Includes\Log\Logger::instance()->warning('wc_token', "Order #{$order_id} has no user, cannot grant tokens");
            return;
        }

        // 防重复授权 (订单已处理过则跳过)
        $already_granted = $order->get_meta('_linked3_granted');
        if ($already_granted) {
            return;
        }

        $total_tokens = 0;
        $plan_to_grant = '';

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if (!$product_id) continue;

            $tokens = (int) get_post_meta($product_id, self::META_TOKENS, true);
            $plan = get_post_meta($product_id, self::META_PLAN, true);
            $qty = (int) $item->get_quantity();

            if ($tokens > 0) {
                $total_tokens += $tokens * $qty;
            }
            if ($plan && !$plan_to_grant) {
                $plan_to_grant = $plan; // 取第一个套餐
            }
        }

        // 加 Token 余额
        if ($total_tokens > 0) {
            $current_balance = (int) get_user_meta($user_id, self::META_USER_BALANCE, true);
            update_user_meta($user_id, self::META_USER_BALANCE, $current_balance + $total_tokens);
            \Linked3\Includes\Log\Logger::instance()->info('wc_token', "Granted {$total_tokens} tokens to user #{$user_id} (order #{$order_id})");
        }

        // 套餐升级 (写 user_meta,License_Service 读)
        if ($plan_to_grant) {
            update_user_meta($user_id, '_linked3_granted_plan', $plan_to_grant);
            update_user_meta($user_id, '_linked3_granted_at', current_time('mysql'));
            \Linked3\Includes\Log\Logger::instance()->info('wc_token', "Upgraded user #{$user_id} to {$plan_to_grant} (order #{$order_id})");
        }

        // 标记已授权
        $order->update_meta_data('_linked3_granted', current_time('mysql'));
        $order->save();

        // 发邮件通知用户
        $user = get_userdata($user_id);
        if ($user) {
            $subject = __('您的 Linked3 Token / 套餐已到账', 'linked3');
            $body = sprintf(
                __("您好,\n\n您的订单 #%d 已完成,以下权益已到账:\n\n", 'linked3'),
                $order_id
            );
            if ($total_tokens > 0) {
                $body .= sprintf(__("- Token 数量: %d\n", 'linked3'), $total_tokens);
            }
            if ($plan_to_grant) {
                $body .= sprintf(__("- 套餐升级: %s\n", 'linked3'), ucfirst($plan_to_grant));
            }
            $body .= __("\n感谢您的购买!\n\nLinked3", 'linked3');
            @wp_mail($user->user_email, $subject, $body);
        }
    }

    /**
     * 自定义"加入购物车"按钮文案。
     */
    public static function add_to_cart_text($text) : mixed {
        global $product;
        if ($product && $product->get_type() === 'linked3_token_package') {
            return __('购买 Token', 'linked3');
        }
        return $text;
    }

    /**
     * 获取用户的额外 Token 余额 (购买的)。
     */
    public static function get_user_balance($user_id) : mixed     {
        return (int) get_user_meta($user_id, self::META_USER_BALANCE, true);
    }

}

// 只在 WC 激活时初始化
if (function_exists('WC')) {
    add_action('init', [WcTokenPackage::class, 'init']);
}
