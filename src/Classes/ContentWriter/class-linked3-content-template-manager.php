<?php
/**
 * Content template manager — CRUD on linked3_content_templates table.
 *
 * @package Linked3
 * @subpackage Classes\ContentWriter
 */

namespace Linked3\Classes\ContentWriter;


if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_Content_Template_Manager
{
    // v4.8.0: share the canonical starter-template definitions with
    // TemplateManager so both managers stay in sync.
    use \Linked3\Classes\Templates\TemplateSeedTrait;

    /**
     * @return array  v4.8.0: delegates to the shared seed trait.
     */
    public function starter_templates() : mixed {
        return $this->seed_templates_db();
    }

    /**
     * @param int $user_id
     * @return void
     */
    public function ensure_defaults($user_id = 0)
    : void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_content_templates';
        foreach ($this->starter_templates() as $tpl) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE user_id = %d AND template_name = %s AND template_type = %s AND is_starter = 1 LIMIT 1",
                $user_id, $tpl['template_name'], $tpl['template_type']
            ));
            if (!$exists) {
                $wpdb->insert($table, [
                    'user_id'       => $user_id,
                    'template_name' => $tpl['template_name'],
                    'template_type' => $tpl['template_type'],
                    'config'        => wp_json_encode($tpl['config']),
                    'is_starter'    => 1,
                ], ['%d', '%s', '%s', '%s', '%d']);
            }
        }
    }

    /**
     * @param int    $user_id
     * @param string $template_type Optional filter.
     * @return array
     */
    public function get_for_user($user_id, $template_type = '') : mixed     {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_content_templates';
        $sql = "SELECT * FROM {$table} WHERE user_id = %d";
        $args = [$user_id];
        if ($template_type) {
            $sql .= " AND template_type = %s";
            $args[] = $template_type;
        }
        $sql .= " ORDER BY is_starter DESC, id ASC";
        return $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);
    }

    /**
     * @param int   $id
     * @param int   $user_id
     * @return array|null
     */
    public function get($id, $user_id) : mixed {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_content_templates';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
            $id, $user_id
        ), ARRAY_A);
        if ($row) {
            $row['config'] = json_decode($row['config'], true) ?: [];
        }
        return $row;
    }

    /**
     * @param array $data
     * @return int|\WP_Error Inserted ID or error.
     */
    public function create(array $data) : mixed     {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_content_templates';
        $config = $this->sanitize_config($data['config'] ?? []);
        $wpdb->insert($table, [
            'user_id'       => (int) ($data['user_id'] ?? get_current_user_id()),
            'template_name' => sanitize_text_field($data['template_name'] ?? ''),
            'template_type' => sanitize_text_field($data['template_type'] ?? 'article'),
            'config'        => wp_json_encode($config),
            'post_type'     => sanitize_text_field($data['post_type'] ?? 'post'),
            'post_status'   => sanitize_text_field($data['post_status'] ?? 'publish'),
            'post_author'   => (int) ($data['post_author'] ?? get_current_user_id()),
            'schedule_datetime' => !empty($data['schedule_datetime']) ? sanitize_text_field($data['schedule_datetime']) : null,
            'categories'    => isset($data['categories']) ? sanitize_text_field(implode(',', (array) $data['categories'])) : '',
            'is_starter'    => 0,
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d']);
        if ($wpdb->last_error) {
            return new \WP_Error('db_error', $wpdb->last_error);
        }
        return (int) $wpdb->insert_id;
    }

    /**
     * @param int   $id
     * @param int   $user_id
     * @param array $data
     * @return bool|\WP_Error
     */
    public function update($id, $user_id, array $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_content_templates';
        $update = [];
        $fmt = [];
        if (isset($data['template_name'])) { $update['template_name'] = sanitize_text_field($data['template_name']); $fmt[] = '%s'; }
        if (isset($data['template_type'])) { $update['template_type'] = sanitize_text_field($data['template_type']); $fmt[] = '%s'; }
        if (isset($data['config'])) { $update['config'] = wp_json_encode($this->sanitize_config($data['config'])); $fmt[] = '%s'; }
        if (isset($data['post_type'])) { $update['post_type'] = sanitize_text_field($data['post_type']); $fmt[] = '%s'; }
        if (isset($data['post_status'])) { $update['post_status'] = sanitize_text_field($data['post_status']); $fmt[] = '%s'; }
        if (isset($data['schedule_datetime'])) { $update['schedule_datetime'] = sanitize_text_field($data['schedule_datetime']); $fmt[] = '%s'; }
        if (isset($data['categories'])) { $update['categories'] = sanitize_text_field(implode(',', (array) $data['categories'])); $fmt[] = '%s'; }
        if (empty($update)) return true;
        $wpdb->update($table, $update, ['id' => $id, 'user_id' => $user_id], $fmt, ['%d', '%d']);
        if ($wpdb->last_error) return new \WP_Error('db_error', $wpdb->last_error);
        return true;
    }

    /**
     * @param int $id
     * @param int $user_id
     * @return bool
     */
    public function delete($id, $user_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_content_templates';
        return (bool) $wpdb->delete($table, ['id' => $id, 'user_id' => $user_id, 'is_starter' => 0], ['%d', '%d', '%d']);
    }

    /**
     * Sanitize a template config array for storage.
     *
     * v0.4.0 hardening notes:
     *   - All scalar string fields run through sanitize_text_field, which is
     *     STRICTLY STRONGER than wp_kses_post for non-HTML fields (it strips
     *     ALL tags + control chars, vs wp_kses_post which allows a safe subset).
     *     For fields that should never contain HTML (tone, language, model,
     *     provider, complexity), sanitize_text_field is the correct choice.
     *   - The `notes` field is the ONE exception: it is the only config field
     *     that may legitimately contain user-formatted HTML (e.g. `<br>`,
     *     `<strong>`), so it runs through wp_kses_post first to allow a safe
     *     subset, then is length-bounded.
     *   - Enum-like fields are validated against whitelists so a tampered
     *     config can't smuggle in an arbitrary provider/model slug that
     *     could later be used to route AI calls to an attacker endpoint.
     *   - All string fields are length-bounded to prevent DB bloat / DoS.
     *
     * @param array $config
     * @return array
     */
    public function sanitize_config(array $config)
    : array {
        $tone_whitelist = ['professional', 'casual', 'instructional', 'balanced', 'persuasive', 'informative', 'friendly'];
        $complexity_whitelist = ['beginner', 'intermediate', 'expert'];
        $language_whitelist = ['zh-CN', 'zh-TW', 'en', 'ja', 'ko', 'fr', 'de', 'es', 'ru'];
        $provider_whitelist = ['openai', 'deepseek', 'kimi', 'qwen', 'doubao', 'anthropic', 'custom'];

        $tone = sanitize_text_field((string) ($config['tone'] ?? 'professional'));
        $complexity = sanitize_text_field((string) ($config['complexity'] ?? 'intermediate'));
        $language = sanitize_text_field((string) ($config['language'] ?? 'zh-CN'));
        $provider = sanitize_text_field((string) ($config['provider'] ?? 'openai'));
        $model = sanitize_text_field((string) ($config['model'] ?? 'Qwen/Qwen2.5-7B-Instruct'));
        $role = sanitize_text_field((string) ($config['role'] ?? 'expert content writer'));

        return [
            'word_count'     => max(100, min(20000, (int) ($config['word_count'] ?? 1200))),
            'tone'           => in_array($tone, $tone_whitelist, true) ? $tone : 'professional',
            'language'       => in_array($language, $language_whitelist, true) ? $language : 'zh-CN',
            'complexity'     => in_array($complexity, $complexity_whitelist, true) ? $complexity : 'intermediate',
            'seo_focus'      => (bool) ($config['seo_focus'] ?? true),
            'role'           => mb_substr($role, 0, 200),
            'max_tags'       => max(1, min(50, (int) ($config['max_tags'] ?? 8))),
            'provider'       => in_array($provider, $provider_whitelist, true) ? $provider : 'openai',
            'model'          => mb_substr($model, 0, 100),
            'temperature'    => max(0.0, min(2.0, (float) ($config['temperature'] ?? 0.7))),
            'max_tokens'     => max(1, min(32000, (int) ($config['max_tokens'] ?? 2000))),
            // notes is the ONE field that may carry safe HTML (line breaks,
            // emphasis). wp_kses_post allows a safe subset; everything else
            // is stripped. Length-bounded to prevent DB bloat.
            'notes'          => mb_substr(wp_kses_post((string) ($config['notes'] ?? '')), 0, 2000),
        ];
    }
}
