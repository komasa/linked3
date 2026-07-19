<?php
/**
 * V15 Brand Profile Manager — 6 要素品牌配置管理。
 *
 * v5.2.0: 管理用户的品牌配置 (brand_profiles 表):
 *   1. Brand (品牌名+Logo+字体)
 *   2. Signature (创作者签名)
 *   3. Color (4色体系)
 *   4. Mood (主+副调性)
 *   5. Culture (地域+年龄+职业+亚文化)
 *   6. Platform (平台+尺寸+比例+密度+产品类型)
 *
 * @package Linked3
 * @subpackage Classes\V15
 */

namespace Linked3\Classes\V15;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_V15_Brand_Profile_Manager
{
    /** @var self|null */
    private static $instance;

    public static function instance() : mixed {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Get the default brand profile for a user (or the system default).
     *
     * @param int $user_id
     * @return array|null
     */
    public function get_profile($user_id = 0) : void     {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_v15_brand_profiles';

        // Try user's default profile first.
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND is_default = 1 LIMIT 1",
            $user_id
        ), ARRAY_A);

        if (!$row) {
            // Fall back to any profile for this user.
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id ASC LIMIT 1",
                $user_id
            ), ARRAY_A);
        }

        if (!$row) {
            return $this->get_default_profile();
        }

        return $this->format_profile($row);
    }

    /**
     * Get all profiles for a user.
     *
     * @param int $user_id
     * @return array
     */
    public function get_all_profiles($user_id = 0) : mixed {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_v15_brand_profiles';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY is_default DESC, id ASC",
            $user_id
        ), ARRAY_A);
        if (!$rows) return [];
        return array_map([$this, 'format_profile'], $rows);
    }

    /**
     * Save a brand profile (create or update).
     *
     * @param int   $user_id
     * @param array $data
     * @return int Profile ID (0 on failure).
     */
    public function save_profile($user_id, array $data) : mixed     {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_v15_brand_profiles';

        $clean = [
            'user_id'            => (int) $user_id,
            'profile_name'       => sanitize_text_field($data['profile_name'] ?? ''),
            'brand_name'         => sanitize_text_field($data['brand_name'] ?? ''),
            'brand_logo'         => sanitize_text_field($data['brand_logo'] ?? ''),
            'brand_font'         => sanitize_text_field($data['brand_font'] ?? ''),
            'signature_name'     => sanitize_text_field($data['signature_name'] ?? ''),
            'signature_title'    => sanitize_text_field($data['signature_title'] ?? ''),
            'signature_slogan'   => sanitize_textarea_field($data['signature_slogan'] ?? ''),
            'color_primary'      => sanitize_hex_color($data['color_primary'] ?? '#1B3A5C'),
            'color_secondary'    => sanitize_hex_color($data['color_secondary'] ?? '#C8403C'),
            'color_neutral'      => sanitize_hex_color($data['color_neutral'] ?? '#E8E4DD'),
            'color_accent'       => sanitize_hex_color($data['color_accent'] ?? '#C9A961'),
            'mood_primary'       => sanitize_text_field($data['mood_primary'] ?? '冷静理性'),
            'mood_secondary'     => sanitize_text_field($data['mood_secondary'] ?? '严肃紧迫'),
            'culture_region'     => sanitize_text_field($data['culture_region'] ?? ''),
            'culture_age'        => sanitize_text_field($data['culture_age'] ?? ''),
            'culture_occupation' => sanitize_text_field($data['culture_occupation'] ?? ''),
            'culture_subculture' => sanitize_text_field($data['culture_subculture'] ?? ''),
            'platform_name'      => sanitize_text_field($data['platform_name'] ?? '小红书'),
            'platform_size'      => sanitize_text_field($data['platform_size'] ?? '1080x1440'),
            'platform_ratio'     => sanitize_text_field($data['platform_ratio'] ?? '3:4'),
            'density'            => sanitize_text_field($data['density'] ?? '标准16节点'),
            'product_type'       => sanitize_text_field($data['product_type'] ?? '单图Card'),
        ];

        $id = (int) ($data['id'] ?? 0);
        if ($id > 0) {
            $wpdb->update($table, $clean, ['id' => $id, 'user_id' => $user_id]);
            return $id;
        }

        // If this is the first profile, make it default.
        $existing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id));
        if ($existing == 0) {
            $clean['is_default'] = 1;
        }

        $wpdb->insert($table, $clean);
        return (int) $wpdb->insert_id;
    }

    /**
     * Get the system default profile (used when user has no profile).
     *
     * @return array
     */
    public function get_default_profile()
    : array {
        return [
            'profile_name'       => '默认品牌',
            'brand_name'         => '我的品牌',
            'brand_logo'         => '',
            'brand_font'         => '思源宋体+思源黑体',
            'signature_name'     => '',
            'signature_title'    => '',
            'signature_slogan'   => '',
            'color_primary'      => '#1B3A5C',
            'color_secondary'    => '#C8403C',
            'color_neutral'      => '#E8E4DD',
            'color_accent'       => '#C9A961',
            'mood_primary'       => '冷静理性',
            'mood_secondary'     => '严肃紧迫',
            'culture_region'     => '中国大陆一二线城市',
            'culture_age'        => '28-45岁',
            'culture_occupation' => '企业主与中产',
            'culture_subculture' => '',
            'platform_name'      => '小红书',
            'platform_size'      => '1080x1440',
            'platform_ratio'     => '3:4',
            'density'            => '标准16节点',
            'product_type'       => '单图Card',
        ];
    }

    /**
     * Format a DB row into a structured profile array.
     *
     * @param array $row
     * @return array
     */
    private function format_profile($row)
    : array {
        return [
            'id'                 => (int) $row['id'],
            'profile_name'       => $row['profile_name'],
            'brand_name'         => $row['brand_name'],
            'brand_logo'         => $row['brand_logo'],
            'brand_font'         => $row['brand_font'],
            'signature_name'     => $row['signature_name'],
            'signature_title'    => $row['signature_title'],
            'signature_slogan'   => $row['signature_slogan'],
            'color_primary'      => $row['color_primary'],
            'color_secondary'    => $row['color_secondary'],
            'color_neutral'      => $row['color_neutral'],
            'color_accent'       => $row['color_accent'],
            'mood_primary'       => $row['mood_primary'],
            'mood_secondary'     => $row['mood_secondary'],
            'culture_region'     => $row['culture_region'],
            'culture_age'        => $row['culture_age'],
            'culture_occupation' => $row['culture_occupation'],
            'culture_subculture' => $row['culture_subculture'],
            'platform_name'      => $row['platform_name'],
            'platform_size'      => $row['platform_size'],
            'platform_ratio'     => $row['platform_ratio'],
            'density'            => $row['density'],
            'product_type'       => $row['product_type'],
            'is_default'         => (int) $row['is_default'],
        ];
    }

    /**
     * Convert a profile to V15 placeholder context.
     *
     * Returns key-value pairs matching the V15 placeholder names:
     * {brand} {signature} {color} {mood} {culture} {platform} {density} {product_type}
     *
     * @param array $profile
     * @return array
     */
    public function profile_to_placeholders($profile)
    : array {
        return [
            'brand'        => $profile['brand_name'] ?? '',
            'signature'    => trim(($profile['signature_name'] ?? '') . ' ' . ($profile['signature_title'] ?? '')),
            'color'        => sprintf('%s/%s/%s/%s',
                $profile['color_primary'] ?? '',
                $profile['color_secondary'] ?? '',
                $profile['color_neutral'] ?? '',
                $profile['color_accent'] ?? ''
            ),
            'mood'         => ($profile['mood_primary'] ?? '') . '+' . ($profile['mood_secondary'] ?? ''),
            'culture'      => sprintf('%s/%s/%s',
                $profile['culture_region'] ?? '',
                $profile['culture_age'] ?? '',
                $profile['culture_occupation'] ?? ''
            ),
            'platform'     => $profile['platform_name'] ?? '',
            'density'      => $profile['density'] ?? '',
            'product_type' => $profile['product_type'] ?? '',
        ];
    }
}
