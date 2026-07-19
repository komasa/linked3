<?php
/**
 * Display: SEO Dashboard.
 *
 * @package Linked3
 * @subpackage Admin\Views\SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

$nonce = wp_create_nonce('linked3_seo');
$ajax_url = admin_url('admin-ajax.php');
$engines = [];
if (class_exists('\Linked3\Classes\SEO\Push\PushEngineFactory')) {
    foreach (\Linked3\Classes\SEO\Push\PushEngineFactory::all() as $slug => $engine) {
        $engines[] = [
            'slug'      => $slug,
            'label'     => $engine->label(),
            'configured' => $engine->is_configured(),
        ];
    }
}
$total_logs = 0;
$fail_count = 0;
$success_count = 0;
if (class_exists('\Linked3\Classes\SEO\Push\PushLogRepository')) {
    $total_logs   = \Linked3\Classes\SEO\Push\PushLogRepository::count_all();
    $success_count = count(\Linked3\Classes\SEO\Push\PushLogRepository::query(['status' => 'success', 'limit' => 100000]));
    $fail_count    = count(\Linked3\Classes\SEO\Push\PushLogRepository::query(['status' => 'fail',    'limit' => 100000]));
}

$adapter = null;
if (class_exists('\Linked3\Classes\SEO\Adapter\SEOAdapterDetector')) {
    $adapter = \Linked3\Classes\SEO\Adapter\SEOAdapterDetector::resolve();
}
?>
<div class="wrap" id="linked3-seo-dashboard">
    <h1><?php echo esc_html__('Linked3 SEO', 'linked3'); ?></h1>

    <h2><?php echo esc_html__('推送引擎', 'linked3'); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php echo esc_html__('引擎', 'linked3'); ?></th>
                <th><?php echo esc_html__('状态', 'linked3'); ?></th>
                <th><?php echo esc_html__('操作', 'linked3'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($engines)) : ?>
            <tr><td colspan="3"><?php echo esc_html__('无已注册的推送引擎。', 'linked3'); ?></td></tr>
        <?php else : foreach ($engines as $e) : ?>
            <tr>
                <td><code><?php echo esc_html($e['slug']); ?></code> — <?php echo esc_html($e['label']); ?></td>
                <td>
                    <?php if ($e['configured']) : ?>
                        <span class="linked3-tag linked3-tag-ok"><?php echo esc_html__('已配置', 'linked3'); ?></span>
                    <?php else : ?>
                        <span class="linked3-tag linked3-tag-warn"><?php echo esc_html__('未配置', 'linked3'); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <button type="button" class="button linked3-push-now"
                            data-engine="<?php echo esc_attr($e['slug']); ?>"
                            <?php echo $e['configured'] ? '' : 'disabled'; ?>>
                        <?php echo esc_html__('推送当前站点 URL', 'linked3'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-seo-settings')); ?>" class="button button-small">配置</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>

    <h2><?php echo esc_html__('推送统计', 'linked3'); ?></h2>
    <p>
        <span class="linked3-badge linked3-badge-info">
            <?php echo esc_html(sprintf(/* translators: %d: total push count. */ __('Total pushes: %d', 'linked3'), $total_logs)); ?>
        </span>
        <span class="linked3-badge linked3-badge-ok">
            <?php echo esc_html(sprintf(/* translators: %d: success count. */ __('Success: %d', 'linked3'), $success_count)); ?>
        </span>
        <span class="linked3-badge linked3-badge-warn">
            <?php echo esc_html(sprintf(/* translators: %d: fail count. */ __('失败:%d', 'linked3'), $fail_count)); ?>
        </span>
    </p>

    <h2><?php echo esc_html__('SEO Adapter', 'linked3'); ?></h2>
    <?php if ($adapter) : ?>
        <p>
            <?php
            echo esc_html(sprintf(
                /* translators: 1: adapter label, 2: handles schema? yes/no, 3: handles meta? yes/no. */
                __('活跃适配器:%1$s。处理 Schema:%2$s。处理 Meta 描述:%3$s。', 'linked3'),
                $adapter->label(),
                $adapter->handles_schema() ? __('yes', 'linked3') : __('no', 'linked3'),
                $adapter->handles_meta_description() ? __('yes', 'linked3') : __('no', 'linked3')
            ));
            ?>
        </p>
    <?php endif; ?>

    <script>
    (function () {
        var nonce = <?php echo wp_json_encode($nonce); ?>;
        var ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
        document.querySelectorAll('.linked3-push-now').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.disabled = true;
                var body = new FormData();
                body.append('action', 'linked3_push_now');
                body.append('nonce', nonce);
                body.append('engine', btn.dataset.engine);
                body.append('url', <?php echo wp_json_encode(esc_url_raw(site_url())); ?>);
                fetch(ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        btn.disabled = false;
                        if (res.success) {
                            var r = res.data.results[btn.dataset.engine] || { ok: false };
                            alert(r.message || (r.ok ? 'OK' : 'Failed'));
                        } else {
                            alert((res.data && res.data.message) || 'Error');
                        }
                    })
                    .catch(function (e) { btn.disabled = false; alert(String(e)); });
            });
        });
    })();
    </script>
</div>
