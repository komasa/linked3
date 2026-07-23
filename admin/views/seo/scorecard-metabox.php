<?php
/**
 * Display: SEO Scorecard metabox.
 *
 * @package Linked3
 * @subpackage Admin\Views\SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @var int $post_id */
/** @var string $nonce */
$post_id = (int) ($post_id ?? 0);
$nonce = (string) ($nonce ?? '');
?>
<div id="linked3-seo-scorecard-metabox" data-post-id="<?php echo esc_attr($post_id); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
    <div class="linked3-score-wrap">
        <div class="linked3-score-number"><?php echo esc_html('—'); ?></div>
        <div class="linked3-score-grade"><?php echo esc_html__('评级:?', 'linked3'); ?></div>
    </div>
    <button type="button" class="button linked3-score-recompute">
        <?php echo esc_html__('计算评分', 'linked3'); ?>
    </button>
    <ul class="linked3-score-tips"></ul>
    <p class="linked3-score-error"></p>
</div>
<style>
.linked3-score-wrap { display:flex; align-items:center; gap:12px; padding:6px 0; }
.linked3-score-number { font-size:28px; font-weight:700; min-width:54px; text-align:center; }
.linked3-score-grade { font-size:14px; }
.linked3-score-tips { margin-top:8px; padding-left:18px; }
.linked3-score-tips li { color:#666; }
.linked3-score-error { color:#a00; }
</style>
