<?php
/**
 * Display: AJAX Security Audit page.
 *
 * @package Linked3
 * @subpackage Admin\Views\Security
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @var \Linked3\Classes\Security\AjaxAuditor $auditor */
$auditor = $args['auditor'] ?? null;
if (!$auditor) {
    return;
}

$rows   = $auditor->scan();
$summary = $auditor->summary();
?>
<div class="wrap">
    <h1><?php echo esc_html__('Linked3 — AJAX 安全审计', 'linked3'); ?></h1>
    <p><?php echo esc_html__('This tool scans every registered wp_ajax_* and wp_ajax_nopriv_* hook on this site and flags anonymous (nopriv) endpoints that may be exposed without authentication. Endpoints in red should be reviewed manually — any nopriv endpoint that performs privileged work is a security bug.', 'linked3'); ?></p>

    <div class="linked3-audit-summary">
        <span class="linked3-badge linked3-badge-info">
            <?php echo esc_html(sprintf(/* translators: %d: total count. */ __('AJAX 端点总数:%d', 'linked3'), $summary['total_count'])); ?>
        </span>
        <span class="linked3-badge linked3-badge-warn">
            <?php echo esc_html(sprintf(/* translators: %d: nopriv count. */ __('匿名(nopriv)端点数:%d', 'linked3'), $summary['nopriv_count'])); ?>
        </span>
    </div>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php echo esc_html__('端点', 'linked3'); ?></th>
                <th><?php echo esc_html__('匿名?', 'linked3'); ?></th>
                <th><?php echo esc_html__('回调', 'linked3'); ?></th>
                <th><?php echo esc_html__('优先级', 'linked3'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)) : ?>
            <tr><td colspan="4"><?php echo esc_html__('暂无注册的 AJAX 端点。', 'linked3'); ?></td></tr>
        <?php else : foreach ($rows as $r) : ?>
            <tr<?php echo $r['is_nopriv'] ? ' class="linked3-row-risk"' : ''; ?>>
                <td><code><?php echo esc_html($r['endpoint']); ?></code></td>
                <td>
                    <?php if ($r['is_nopriv']) : ?>
                        <span class="linked3-tag linked3-tag-danger"><?php echo esc_html__('Yes — review', 'linked3'); ?></span>
                    <?php else : ?>
                        <span class="linked3-tag linked3-tag-ok"><?php echo esc_html__('否', 'linked3'); ?></span>
                    <?php endif; ?>
                </td>
                <td><code><?php echo esc_html($r['callback']); ?></code></td>
                <td><?php echo esc_html($r['priority']); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
