<?php
namespace Linked3\Classes\Publish\Ajax\Actions;
use Linked3\Classes\Publish\Ajax\Linked3_Publish_Base_Ajax_Action;


if (!defined('ABSPATH')) exit;
/**
 * Publish now action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Publish.Ajax.Actions
 * @since      27.1.0
 */

final class Linked3_Publish_Now_Action extends Linked3_Publish_Base_Ajax_Action
{
    public function handle()
    : void {
        $post_id = (int) ($_POST['post_id'] ?? 0);
        $target_id = (int) ($_POST['target_id'] ?? 0);
        $all = !empty($_POST['all_targets']);
        if (!$post_id) $this->send_error(__('需要文章 ID。', 'linked3'), 400);

        $post = get_post($post_id, ARRAY_A);
        if (!$post) $this->send_error(__('文章未找到。', 'linked3'), 404);

        $publish_data = [
            'ID'           => $post_id,
            'post_title'   => $post['post_title'],
            'post_content' => $post['post_content'],
            'post_excerpt' => $post['post_excerpt'],
            'post_status'  => $post['post_status'],
            'post_type'    => $post['post_type'],
            'post_author'  => $post['post_author'],
        ];

        if ($all) {
            $results = $this->manager()->publish_to_all(get_current_user_id(), $publish_data);
            $this->send_success(['results' => $results]);
        } else {
            if (!$target_id) $this->send_error(__('需要目标 ID(或设置 all_targets=1)。', 'linked3'), 400);
            $r = $this->manager()->publish_to_target($target_id, get_current_user_id(), $publish_data);
            if ($r['ok']) {
                $this->send_success($r);
            } else {
                $this->send_error($r['message'], 502);
            }
        }
    }
}
