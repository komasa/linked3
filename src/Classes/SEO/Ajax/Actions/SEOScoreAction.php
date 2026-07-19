<?php

declare(strict_types=1);
/**
 * SEO Score Action — compute and return the 0-100 scorecard for a post.
 *
 * Plan gating: Pro+ (require_seo_plan). The scorecard itself is computed
 * by SEOScorecard; this action is the AJAX bridge.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Ajax\Actions
 */

namespace Linked3\Classes\SEO\Ajax\Actions;

use Linked3\Classes\SEO\Ajax\SEOBaseAjaxAction;
use Linked3\Classes\SEO\Scoring\SEOScorecard;



if (!defined('ABSPATH')) {
    exit;
}
final class SEOScoreAction extends SEOBaseAjaxAction
{
    const NONCE_ACTION = 'linked3_seo';
    const CAPABILITY = 'edit_posts';

    public function dispatch()
    : void {
        $this->verify(static::NONCE_ACTION, static::CAPABILITY);
        // v0.4.x architecture requirement: SEO scorecard is Pro+.
        $this->require_seo_plan('free'); // Free 用户也可使用
        $this->handle();
    }

    public function handle()
    : void {
        $post_id = (int) ($_POST['post_id'] ?? 0);
        if ($post_id <= 0) {
            $this->send_error(__('需要有效的文章 ID。', 'linked3'), 400);
        }
        $post = get_post($post_id);
        if (!$post) {
            $this->send_error(__('文章未找到。', 'linked3'), 404);
        }
        if (!current_user_can('edit_post', $post_id)) {
            $this->forbidden(__('您无法评分此文章。', 'linked3'));
        }
        $scorecard = new \Linked3\Classes\SEO\Scoring\SEOScorecard();
        $report = $scorecard->evaluate($post);
        $this->send_success($report);
    }
}
