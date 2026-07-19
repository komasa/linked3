<?php

declare(strict_types=1);
/**
 * AI Forms — shortcode-based forms with AI analysis on submission.
 *
 * [linked3_form id="N"] renders a form; submissions are stored + AI-analyzed.
 *
 * @package Linked3
 * @subpackage Classes\AIForms
 */

namespace Linked3\Classes\AIForms;

use Linked3\Classes\Core\AIDispatcher;



if (!defined('ABSPATH')) {
    exit;
}
final class AiFormManager
{
    /**
     * Register shortcode.
     *
     * @return void
     */
    public static function register()
    : void {
        add_shortcode('linked3_form', [__CLASS__, 'render']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
    }

    /**
     * Register admin AJAX endpoints for form CRUD (v1.0.0 FINAL-AUDIT fix).
     *
     * Previously the admin page (admin/views/forms/dashboard.php) was read-only:
     * it could list existing forms + submissions but had no UI to create,
     * edit, or delete forms — admins had to write PHP code against
     * `linked3_ai_forms` option. This wires up three admin-only AJAX actions
     * used by the new CRUD UI.
     *
     * @return void
     */
    public static function register_admin_ajax()
    : void {
        add_action('wp_ajax_linked3_form_create', [__CLASS__, 'handle_create']);
        add_action('wp_ajax_linked3_form_update', [__CLASS__, 'handle_update']);
        add_action('wp_ajax_linked3_form_delete', [__CLASS__, 'handle_delete']);
    }

    /**
     * AJAX: create a new form. POST params: title, submit_label, ai_prompt,
     * notify_email, fields (JSON: [{label, type, required, options?}]).
     *
     * @return void
     */
    public static function handle_create()
    : void {
        self::verify_admin('linked3_forms_admin');
        $form = self::validate_form_input();
        if (is_wp_error($form)) {
            wp_send_json_error(['message' => $form->get_error_message()], 400);
        }
        $forms = get_option(LINKED3_OPTION_PREFIX . 'ai_forms', []);
        if (!is_array($forms)) $forms = [];
        // Use max existing key + 1 (or 1 if empty). Forms are stored as
        // id => form-array; ids are stable across deletions.
        $next_id = empty($forms) ? 1 : (max(array_keys($forms)) + 1);
        $forms[$next_id] = $form;
        update_option(LINKED3_OPTION_PREFIX . 'ai_forms', $forms);
        wp_send_json_success(['id' => $next_id, 'form' => $form]);
    }

    /**
     * AJAX: update an existing form. POST params: id + same fields as create.
     *
     * @return void
     */
    public static function handle_update()
    : void {
        self::verify_admin('linked3_forms_admin');
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(['message' => __('需要表单 ID。', 'linked3')], 400);
        }
        $form = self::validate_form_input();
        if (is_wp_error($form)) {
            wp_send_json_error(['message' => $form->get_error_message()], 400);
        }
        $forms = get_option(LINKED3_OPTION_PREFIX . 'ai_forms', []);
        if (!is_array($forms) || !isset($forms[$id])) {
            wp_send_json_error(['message' => __('表单未找到。', 'linked3')], 404);
        }
        $forms[$id] = $form;
        update_option(LINKED3_OPTION_PREFIX . 'ai_forms', $forms);
        wp_send_json_success(['id' => $id, 'form' => $form]);
    }

    /**
     * AJAX: delete a form. POST params: id.
     *
     * @return void
     */
    public static function handle_delete()
    : void {
        self::verify_admin('linked3_forms_admin');
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(['message' => __('需要表单 ID。', 'linked3')], 400);
        }
        $forms = get_option(LINKED3_OPTION_PREFIX . 'ai_forms', []);
        if (!is_array($forms) || !isset($forms[$id])) {
            wp_send_json_error(['message' => __('表单未找到。', 'linked3')], 404);
        }
        unset($forms[$id]);
        update_option(LINKED3_OPTION_PREFIX . 'ai_forms', $forms);
        wp_send_json_success(['id' => $id]);
    }

    /**
     * Verify admin nonce + capability. Used by CRUD AJAX handlers.
     *
     * @param string $nonce_action
     * @return void
     */
    private static function verify_admin($nonce_action)
    : void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限。', 'linked3')], 403);
        }
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, $nonce_action)) {
            wp_send_json_error(['message' => __('安全校验失败。', 'linked3')], 403);
        }
    }

    /**
     * Validate + sanitize form fields from $_POST.
     *
     * @return array|\WP_Error
     */
    private static function validate_form_input() : mixed {
        $title = sanitize_text_field($_POST['title'] ?? '');
        if ($title === '') {
            return new \WP_Error('bad_title', __('表单标题必填。', 'linked3'));
        }
        $fields_json = wp_unslash($_POST['fields'] ?? '[]');
        $fields = json_decode($fields_json, true);
        if (!is_array($fields)) {
            return new \WP_Error('bad_fields', __('字段必须是有效的 JSON。', 'linked3'));
        }
        $clean_fields = [];
        foreach ($fields as $f) {
            $label = sanitize_text_field($f['label'] ?? '');
            if ($label === '') continue;
            $type = in_array($f['type'] ?? 'text', ['text', 'email', 'url', 'tel', 'textarea', 'select', 'number'], true) ? $f['type'] : 'text';
            $opts = [];
            if ($type === 'select' && !empty($f['options']) && is_array($f['options'])) {
                foreach ($f['options'] as $opt) {
                    $opts[] = sanitize_text_field($opt);
                }
            }
            $clean_fields[] = [
                'label'    => $label,
                'type'     => $type,
                'required' => !empty($f['required']),
                'options'  => $opts,
            ];
        }
        if (empty($clean_fields)) {
            return new \WP_Error('no_fields', __('至少需要一个字段。', 'linked3'));
        }
        return [
            'title'         => $title,
            'submit_label'  => sanitize_text_field($_POST['submit_label'] ?? __('提交', 'linked3')),
            'ai_prompt'     => sanitize_textarea_field($_POST['ai_prompt'] ?? ''),
            'notify_email'  => sanitize_email($_POST['notify_email'] ?? ''),
            'fields'        => $clean_fields,
        ];
    }

    public static function render($atts) : mixed     {
        $atts = shortcode_atts(['id' => '0'], $atts, 'linked3_form');
        $id = (int) $atts['id'];
        $forms = get_option(LINKED3_OPTION_PREFIX . 'ai_forms', []);
        if (!isset($forms[$id])) return '<!-- linked3_form: not found -->';
        $form = $forms[$id];
        $nonce = wp_create_nonce('linked3_form_' . $id);
        $ajax_url = admin_url('admin-ajax.php');

        ob_start();
        ?>
        <form class="linked3-ai-form" data-id="<?php echo esc_attr($id); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" data-ajax="<?php echo esc_attr($ajax_url); ?>">
            <h3><?php echo esc_html($form['title']); ?></h3>
            <?php foreach ($form['fields'] as $f) :
                $name = 'f_' . sanitize_key($f['label']);
                $type = $f['type'] ?? 'text';
            ?>
                <p>
                    <label><?php echo esc_html($f['label']); ?>
                        <?php if ($type === 'textarea') : ?>
                            <textarea name="<?php echo esc_attr($name); ?>" rows="4" <?php echo !empty($f['required']) ? 'required' : ''; ?>></textarea>
                        <?php elseif ($type === 'select') : ?>
                            <select name="<?php echo esc_attr($name); ?>">
                                <?php foreach (($f['options'] ?? []) as $opt) : ?>
                                    <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else : ?>
                            <input type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($name); ?>" <?php echo !empty($f['required']) ? 'required' : ''; ?> />
                        <?php endif; ?>
                    </label>
                </p>
            <?php endforeach; ?>
            <p><button type="submit" class="button"><?php echo esc_html($form['submit_label'] ?? __('提交', 'linked3')); ?></button></p>
            <div class="linked3-ai-form-result" style="display:none;"></div>
        </form>
        <script>
        (function(){
            var f=document.querySelector('.linked3-ai-form[data-id="<?php echo esc_js($id); ?>"]');
            if(!f||f.dataset.init)return;f.dataset.init='1';
            f.addEventListener('submit',function(e){
                e.preventDefault();
                var fd=new FormData(f);fd.append('action','linked3_form_submit');fd.append('form_id',f.dataset.id);fd.append('nonce',f.dataset.nonce);
                fetch(f.dataset.ajax,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(res){
                    var el=f.querySelector('.linked3-ai-form-result');el.style.display='block';
                    if(res.success){el.innerHTML='<div class="linked3-form-ok">'+(res.data.analysis||'<?php echo esc_js(__('Thank you!','linked3'));?>')+'</div>';}
                    else{el.innerHTML='<div class="linked3-form-err">'+(res.data.message||'Error')+'</div>';}
                });
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public static function enqueue()
    : void {
        add_action('wp_head', static function () {
            echo '<style>.linked3-ai-form{max-width:600px;margin:1em 0;}.linked3-ai-form label{display:block;margin-bottom:8px;font-weight:600;}.linked3-ai-form input,.linked3-ai-form textarea,.linked3-ai-form select{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;font-weight:400;}.linked3-ai-form button{padding:10px 20px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer;}.linked3-form-ok{background:#dcfce7;padding:12px;border-radius:4px;margin-top:12px;}.linked3-form-err{background:#fee2e2;padding:12px;border-radius:4px;margin-top:12px;}</style>';
        });
    }

    /**
     * Handle form submission + AI analysis.
     *
     * @return void
     */
    public static function handle_submission()
    : void {
        $form_id = (int) ($_POST['form_id'] ?? 0);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_form_' . $form_id)) {
            wp_send_json_error(['message' => __('安全校验失败。', 'linked3')], 403);
        }

        // Rate limit: 5 submissions/min/IP.
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
        $bucket = 'linked3_form_rl_' . md5($ip);
        $count = (int) get_transient($bucket);
        if ($count >= 5) {
            wp_send_json_error(['message' => __('提交过于频繁,请稍候。', 'linked3')], 429);
        }
        set_transient($bucket, $count + 1, MINUTE_IN_SECONDS);

        $forms = get_option(LINKED3_OPTION_PREFIX . 'ai_forms', []);
        if (!isset($forms[$form_id])) {
            wp_send_json_error(['message' => __('表单未找到。', 'linked3')], 404);
        }
        $form = $forms[$form_id];

        // Collect field values.
        $values = [];
        foreach ($form['fields'] as $f) {
            $name = 'f_' . sanitize_key($f['label']);
            $values[$f['label']] = sanitize_textarea_field($_POST[$name] ?? '');
        }

        // Store submission.
        $submissions = get_option(LINKED3_OPTION_PREFIX . 'ai_form_submissions', []);
        $submissions[] = ['form_id' => $form_id, 'values' => $values, 'ip' => $ip, 'ts' => time(), 'user_id' => get_current_user_id()];
        update_option(LINKED3_OPTION_PREFIX . 'ai_form_submissions', array_slice($submissions, -1000)); // cap 1000

        // AI analysis (optional).
        $analysis = '';
        if (!empty($form['ai_prompt'])) {
            try {
                $prompt = $form['ai_prompt'] . "\n\n" . wp_json_encode($values, JSON_UNESCAPED_UNICODE);
                $result = AIDispatcher::instance()->chat(
                    [['role' => 'user', 'content' => $prompt]],
                    ['provider' => get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow'), 'model' => 'gpt-4o-mini', 'temperature' => 0.3, 'max_tokens' => 500, 'module' => 'forms'],
                    ['fallback_providers' => []]
                );
                $analysis = $result['content'] ?? '';
            } catch (\Exception $e) {
                $analysis = __('分析不可用。', 'linked3');
            }
        }

        // Email notification.
        if (!empty($form['notify_email'])) {
            wp_mail($form['notify_email'], sprintf(__('[Linked3] 新表单提交:%s', 'linked3'), $form['title']), wp_json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        wp_send_json_success(['analysis' => $analysis, 'values' => $values]);
    }
}
