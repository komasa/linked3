<?php
/**
 * Display: SEO Push Logs.
 *
 * @package Linked3
 * @subpackage Admin\Views\SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

$nonce = wp_create_nonce('linked3_seo');
$ajax_url = admin_url('admin-ajax.php');

$filter_engine = isset($_GET['engine']) ? sanitize_key($_GET['engine']) : '';
$filter_status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
$paged = max(1, (int) ($_GET['paged'] ?? 1));
$per_page = 25;

$filters = ['limit' => $per_page, 'offset' => ($paged - 1) * $per_page];
if ($filter_engine !== '') {
    $filters['engine'] = $filter_engine;
}
if ($filter_status !== '') {
    $filters['status'] = $filter_status;
}

$rows = [];
if (class_exists('\Linked3\Classes\SEO\Push\Linked3_Push_Log_Repository')) {
    $rows = \Linked3\Classes\SEO\Push\Linked3_Push_Log_Repository::query($filters);
}
$total = \Linked3\Classes\SEO\Push\Linked3_Push_Log_Repository::count_all();
$total_pages = max(1, (int) ceil($total / $per_page));
?>
<div class="wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <h1 style="margin:0;">Push-Logs</h1>
        <a href="admin.php?page=linked3-dashboard" class="button">← 返回总览</a>
    </div>
    <h1><?php echo esc_html__('推送日志', 'linked3'); ?></h1>

    <form method="get" action="">
        <input type="hidden" name="page" value="linked3-seo-push-logs" />
        <select name="engine">
            <option value=""><?php echo esc_html__('所有引擎', 'linked3'); ?></option>
            <?php foreach (['baidu','bing','google','toutiao','indexnow'] as $e) : ?>
                <option value="<?php echo esc_attr($e); ?>" <?php selected($filter_engine, $e); ?>><?php echo esc_html($e); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value=""><?php echo esc_html__('所有状态', 'linked3'); ?></option>
            <?php foreach (['success','fail','pending'] as $s) : ?>
                <option value="<?php echo esc_attr($s); ?>" <?php selected($filter_status, $s); ?>><?php echo esc_html($s); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="button"><?php echo esc_html__('筛选', 'linked3'); ?></button>
    </form>

    <form id="linked3-push-retry-form" method="post">
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><input type="checkbox" id="linked3-select-all" /></th>
                    <th><?php echo esc_html__('ID', 'linked3'); ?></th>
                    <th><?php echo esc_html__('引擎', 'linked3'); ?></th>
                    <th><?php echo esc_html__('URL', 'linked3'); ?></th>
                    <th><?php echo esc_html__('状态', 'linked3'); ?></th>
                    <th><?php echo esc_html__('代码', 'linked3'); ?></th>
                    <th><?php echo esc_html__('消息', 'linked3'); ?></th>
                    <th><?php echo esc_html__('时间', 'linked3'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)) : ?>
                <tr><td colspan="8"><?php echo esc_html__('未找到推送日志。', 'linked3'); ?></td></tr>
            <?php else : foreach ($rows as $r) : ?>
                <tr>
                    <td>
                        <?php if ($r['status'] === 'fail') : ?>
                            <input type="checkbox" class="linked3-log-id" name="log_ids[]" value="<?php echo esc_attr($r['id']); ?>" />
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($r['id']); ?></td>
                    <td><code><?php echo esc_html($r['engine']); ?></code></td>
                    <td><a href="<?php echo esc_url($r['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(mb_substr($r['url'], 0, 60)); ?></a></td>
                    <td>
                        <?php if ($r['status'] === 'success') : ?>
                            <span class="linked3-tag linked3-tag-ok"><?php echo esc_html($r['status']); ?></span>
                        <?php elseif ($r['status'] === 'fail') : ?>
                            <span class="linked3-tag linked3-tag-danger"><?php echo esc_html($r['status']); ?></span>
                        <?php else : ?>
                            <span class="linked3-tag linked3-tag-warn"><?php echo esc_html($r['status']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($r['response_code']); ?></td>
                    <td><?php echo esc_html(mb_substr($r['message'], 0, 80)); ?></td>
                    <td><?php echo esc_html($r['created_at']); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            $base = admin_url('admin.php?page=linked3-seo-push-logs') . '&engine=' . $filter_engine . '&status=' . $filter_status;
            for ($i = 1; $i <= $total_pages; $i++) {
                $cls = $i === $paged ? 'button button-primary' : 'button';
                echo '<a class="' . esc_attr($cls) . '" href="' . esc_url($base . '&paged=' . $i) . '">' . esc_html($i) . '</a> ';
            }
            echo '</div></div>';
        }
        ?>

        <p>
            <button type="button" class="button button-primary" id="linked3-retry-selected">
                <?php echo esc_html__('Retry Selected Failed', 'linked3'); ?>
            </button>
        </p>
    </form>

    <script>
    (function () {
        var nonce = <?php echo wp_json_encode($nonce); ?>;
        var ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
        document.getElementById('linked3-select-all').addEventListener('change', function (e) {
            document.querySelectorAll('.linked3-log-id').forEach(function (cb) { cb.checked = e.target.checked; });
        });
        document.getElementById('linked3-retry-selected').addEventListener('click', function () {
            var ids = [];
            document.querySelectorAll('.linked3-log-id:checked').forEach(function (cb) { ids.push(cb.value); });
            if (ids.length === 0) { alert(<?php echo wp_json_encode(__('Select at least one failed log.', 'linked3')); ?>); return; }
            var body = new FormData();
            body.append('action', 'linked3_push_retry');
            body.append('nonce', nonce);
            ids.forEach(function (id) { body.append('log_ids[]', id); });
            fetch(ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        alert((res.data.results ? Object.keys(res.data.results).length : 0) + ' engines re-pushed.');
                        window.location.reload();
                    } else {
                        alert((res.data && res.data.message) || 'Error');
                    }
                })
                .catch(function (e) { alert(String(e)); });
        });
    })();
    </script>
</div>
