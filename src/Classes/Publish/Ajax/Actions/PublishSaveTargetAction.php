<?php

declare(strict_types=1);
namespace Linked3\Classes\Publish\Ajax\Actions;
use Linked3\Classes\Publish\Ajax\PublishBaseAjaxAction;


if (!defined('ABSPATH')) exit;
/**
 * Publish save target action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Publish.Ajax.Actions
 * @since      27.1.0
 */

final class PublishSaveTargetAction extends PublishBaseAjaxAction
{
    /**
     * Allowed target types. Anything else is rejected with 400 to keep
     * the plan-gate in the base class meaningful (it branches on `type`).
     */
    const ALLOWED_TYPES = ['local', 'remote_wp', 'remote_db', 'custom_api'];

    public function handle()
    : void {
        $id = (int) ($_POST['id'] ?? 0);
        $type = sanitize_text_field($_POST['type'] ?? 'local');
        // v0.6.0 hardening: reject unknown types up-front so a malformed
        // request can neither bypass the plan gate (by sending type='') nor
        // smuggle an attacker-chosen adapter slug into the Repository.
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            $this->send_error(__('无效的目标类型。', 'linked3'), 400);
        }
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'type' => $type,
            'config' => $this->parse_config($_POST),
            'is_default' => !empty($_POST['is_default']),
        ];
        $repo = $this->repo();
        if ($id) {
            $res = $repo->update($id, get_current_user_id(), $data);
        } else {
            $res = $repo->create(array_merge($data, ['user_id' => get_current_user_id()]));
        }
        if (is_wp_error($res)) $this->send_error($res->get_error_message(), 400);
        $this->send_success(['id' => $res]);
    }

    private function parse_config($post) : mixed {
        $type = sanitize_text_field($post['type'] ?? 'local');
        $cfg = [];
        switch ($type) {
            case 'remote_wp':
                $cfg['site_url'] = esc_url_raw($post['site_url'] ?? '');
                $cfg['username'] = sanitize_text_field($post['username'] ?? '');
                // Application Passwords are alphanumeric+spaces only, but be
                // permissive: strip tags/line-breaks without mangling chars.
                $cfg['app_password'] = $this->sanitize_secret($post['app_password'] ?? '');
                break;
            case 'remote_db':
                $cfg['db_host'] = sanitize_text_field($post['db_host'] ?? '');
                $cfg['db_user'] = sanitize_text_field($post['db_user'] ?? '');
                $cfg['db_password'] = $this->sanitize_secret($post['db_password'] ?? '');
                $cfg['db_name'] = sanitize_text_field($post['db_name'] ?? '');
                // v0.6.0 hardening: table_prefix is concatenated into raw
                // SQL by the remote-db adapter; restrict to the WP-safe set
                // [A-Za-z0-9_] to prevent SQL injection via config.
                $prefix = isset($post['table_prefix']) ? (string) $post['table_prefix'] : 'wp_';
                $cfg['table_prefix'] = preg_replace('/[^A-Za-z0-9_]/', '', $prefix) ?: 'wp_';
                $cfg['remote_author_id'] = (int) ($post['remote_author_id'] ?? 1);
                break;
            case 'custom_api':
                $cfg['webhook_url'] = esc_url_raw($post['webhook_url'] ?? '');
                $cfg['webhook_secret'] = $this->sanitize_secret($post['webhook_secret'] ?? '');
                break;
        }
        return $cfg;
    }

    /**
     * Sanitize a secret (password / token) for at-rest storage. Strips tags
     * and line breaks but preserves special characters that may be part of
     * the secret. The value is then encrypted by the Repository before
     * being written to the DB, so we don't need further encoding here.
     *
     * @param string $value
     * @return string
     */
    private function sanitize_secret(string $value) : mixed     {
        $value = is_string($value) ? $value : '';
        // Strip tags, line breaks, and control chars; keep all printable chars.
        $value = wp_strip_all_tags($value);
        $value = preg_replace('/[\r\n\t]+/', '', $value);
        return trim($value);
    }
}
