<?php

declare(strict_types=1);
/**
 * Publish Target Repository — CRUD on linked3_publish_targets with Crypto.
 *
 * v4.5.4: now extends BaseRepository. Custom encrypt/decrypt +
 * plan-limit logic is preserved. The all_for_user/get/get_default methods
 * keep user-scoping (Base_Repository only filters by PK).
 *
 * All sensitive fields (app_password, db_password, webhook_secret) are
 * encrypted via Crypto before storage and decrypted on read.
 *
 * @package Linked3
 * @subpackage Classes\Publish
 */

namespace Linked3\Classes\Publish;

use Linked3\Includes\DB\BaseRepository;

use WP_Error;
if (!defined('ABSPATH')) {
    exit;
}

final class PublishTargetRepository extends BaseRepository
{
    const SENSITIVE_FIELDS = ['app_password', 'db_password', 'webhook_secret'];

    /**
     * {@inheritdoc}
     */
    protected function table_name(): string
    {
        return 'linked3_publish_targets';
    }

    /**
     * {@inheritdoc}
     */
    protected function primary_key(): string
    {
        return 'id';
    }

    /**
     * {@inheritdoc}
     */
    protected function fillable(): array
    {
        return ['user_id', 'name', 'type', 'config', 'is_default', 'status'];
    }

    /**
     * @param int $user_id
     * @return array
     */
    public function all_for_user(int $user_id) : mixed {
        global $wpdb;
        $table = $this->get_table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND status = 'active' ORDER BY is_default DESC, id ASC",
            $user_id
        ), ARRAY_A);
        if (!$rows) {
            return [];
        }
        foreach ($rows as &$r) {
            $r['config'] = $this->decrypt_config($r['config']);
        }
        return $rows;
    }

    /**
     * @param int $id
     * @param int $user_id
     * @return array|null
     */
    public function get(int $id, int $user_id) : mixed     {
        global $wpdb;
        $table = $this->get_table();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
            $id, $user_id
        ), ARRAY_A);
        if (!$row) {
            return null;
        }
        $row['config'] = $this->decrypt_config($row['config']);
        return $row;
    }

    /**
     * @param array $data {user_id, name, type, config, is_default}
     * @return int|\WP_Error
     */
    public function create(array $data) : mixed     {
        global $wpdb;
        $table = $this->get_table();
        $user_id = (int) ($data['user_id'] ?? get_current_user_id());

        // Plan limit check.
        $limit = $this->plan_limit($user_id);
        if ($limit >= 0) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status = 'active'",
                $user_id
            ));
            if ((int) $count >= $limit) {
                return new \WP_Error('plan_limit', sprintf(
                    __('当前套餐允许 %d 个发布目标,升级可添加更多。', 'linked3'),
                    $limit
                ));
            }
        }

        $config = $this->encrypt_config($data['config'] ?? []);
        $is_default = !empty($data['is_default']) ? 1 : 0;

        // If setting as default, clear previous default.
        if ($is_default) {
        $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET is_default = %d WHERE user_id = %d",
                0, $user_id
            ));
        }

        $id = parent::insert_row([
            'user_id'    => $user_id,
            'name'       => sanitize_text_field($data['name'] ?? ''),
            'type'       => sanitize_text_field($data['type'] ?? 'local'),
            'config'     => $config,
            'is_default' => $is_default,
            'status'     => 'active',
        ]);
        if ($id === null) {
            return new \WP_Error('db', $wpdb->last_error);
        }
        return $id;
    }

    /**
     * @param int   $id
     * @param int   $user_id
     * @param array $data
     * @return bool|\WP_Error
     */
    public function update(int $id, int $user_id, array $data): bool|WP_Error
    {
        global $wpdb;
        $table = $this->get_table();
        $update = [];
        $fmt = [];
        if (isset($data['name'])) {
            $update['name'] = sanitize_text_field($data['name']);
            $fmt[] = '%s';
        }
        if (isset($data['type'])) {
            $update['type'] = sanitize_text_field($data['type']);
            $fmt[] = '%s';
        }
        if (isset($data['config'])) {
            $update['config'] = $this->encrypt_config($data['config']);
            $fmt[] = '%s';
        }
        if (isset($data['is_default'])) {
            $update['is_default'] = $data['is_default'] ? 1 : 0;
            $fmt[] = '%d';
            if ($data['is_default']) {
            $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET is_default = %d WHERE user_id = %d",
                    0, $user_id
                ));
            }
        }
        if (empty($update)) {
            return true;
        }
        // User-scoped update (Base_Repository::update only filters by PK).
        $wpdb->update($table, $update, ['id' => $id, 'user_id' => $user_id], $fmt, ['%d', '%d']); // $wpdb->prepare equivalent via format params
        if ($wpdb->last_error) {
            return new \WP_Error('db', $wpdb->last_error);
        }
        return true;
    }

    /**
     * @param int $id
     * @param int $user_id
     * @return bool
     */
    public function delete(int $id, int $user_id): bool
    {
        global $wpdb;
        $table = $this->get_table();
        // Soft delete.
        return (bool) $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = %s WHERE id = %d AND user_id = %d",
            'deleted', $id, $user_id
        ));
    }

    /**
     * @param int $user_id
     * @return int -1 for unlimited.
     */
    private function plan_limit(int $user_id): int
    {
        $plan = \Linked3\Classes\License\LicenseService::instance()->plan();
        $limits = ['free' => 1, 'pro' => 5, 'premium' => -1];
        return $limits[$plan] ?? 1;
    }

    /**
     * @param array $config
     * @return string JSON with sensitive fields encrypted.
     */
    private function encrypt_config(array $config): string
    {
        if (class_exists('\\Linked3\\Includes\\Crypto')) {
            foreach (self::SENSITIVE_FIELDS as $field) {
                if (!empty($config[$field])) {
                    $enc = \Linked3\Includes\Crypto::encrypt($config[$field]);
                    if ($enc !== null) {
                        $config[$field] = $enc;
                    }
                }
            }
        }
        return wp_json_encode($config);
    }

    /**
     * @param string $json
     * @return array
     */
    private function decrypt_config(string $json): array
    {
        $config = json_decode($json, true);
        if (!is_array($config)) {
            return [];
        }
        if (class_exists('\\Linked3\\Includes\\Crypto')) {
            foreach (self::SENSITIVE_FIELDS as $field) {
                if (!empty($config[$field])) {
                    $dec = \Linked3\Includes\Crypto::decrypt($config[$field]);
                    if ($dec !== null) {
                        $config[$field] = $dec;
                    }
                }
            }
        }
        return $config;
    }
}

