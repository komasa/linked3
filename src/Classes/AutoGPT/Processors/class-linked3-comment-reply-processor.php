<?php
namespace Linked3\Classes\AutoGPT\Processors;
use Linked3\Classes\Chat\ChatManager;


if (!defined('ABSPATH')) exit;
/**
 * Comment reply processor.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.AutoGPT.Processors
 * @since      27.1.0
 */

final class Linked3_Comment_Reply_Processor implements Linked3_AutoGPT_Processor_Interface
{
    public function process(array $task)
    : array {
        $cfg = $task['config'];
        // v3.1.0: sentiment_filter 是死代码 (读取了但永不使用),保留兼容但不处理
        $processed = 0;
        $failed = 0;
        $repo = new \Linked3\Classes\AutoGPT\Linked3_AutoGPT_Task_Repository();
        // v16.3.0 性能优化: 将 get_option 提取到循环外, 避免N+1配置读取 (原每条评论读2次option)
        $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';
        $bot_name = get_option(LINKED3_OPTION_PREFIX . 'comment_bot_name', __('助手', 'linked3'));
        $admin_email = get_option('admin_email');
        // Find unapproved comments awaiting reply.
        $comments = get_comments(['status' => 'approve', 'number' => 20, 'meta_query' => [['key' => '_linked3_replied', 'compare' => 'NOT EXISTS']]]);
        foreach ($comments as $c) {
            // Skip if author is the post author (don't reply to yourself).
            $post = get_post($c->comment_post_ID);
            if ($post && $post->post_author == $c->user_id) continue;
            try {
                // v16.3.0: provider/model/bot_name/admin_email 已在循环外读取, 不再重复get_option
                $result = ChatManager::instance()->chat(
                    wp_generate_password(24, false),
                    sprintf(__('Write a friendly, helpful reply to this comment on the article "%s": "%s"'), $post ? $post->post_title : '', $c->comment_content),
                    0,
                    [
                        'provider'      => $provider,
                        'model'         => $model,
                        'system_prompt' => __('您撰写简洁友好的博客评论回复,保持切题,不要提及自己是 AI。', 'linked3'),
                        'temperature'   => 0.7,
                        'max_tokens'    => 300,
                        'user_id'       => $task['user_id'],
                    ]
                );
                if (!empty($result['ok'])) {
                    wp_insert_comment([
                        'comment_post_ID' => $c->comment_post_ID,
                        'comment_author' => $bot_name,
                        'comment_author_email' => $admin_email,
                        'comment_content' => $result['reply'],
                        'comment_parent' => $c->comment_ID,
                        'comment_approved' => 0, // hold for review
                        'comment_meta' => ['_linked3_auto_reply' => 1],
                    ]);
                    update_comment_meta($c->comment_ID, '_linked3_replied', 1);
                    $processed++;
                } else {
                    $failed++;
                    // v3.1.0: 失败入队重试
                    $repo->enqueue($task['id'], [
                        'type' => 'comment_retry',
                        'comment_id' => $c->comment_ID,
                        'reason' => $result['message'] ?? 'chat failed',
                    ], gmdate('Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS));
                }
            } catch (\Exception $e) {
                $failed++;
                // v3.1.0: 异常入队重试
                $repo->enqueue($task['id'], [
                    'type' => 'comment_retry',
                    'comment_id' => $c->comment_ID,
                    'reason' => $e->getMessage(),
                ], gmdate('Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS));
                continue;
            }
        }
        // v0.8.0: ok:false when there were comments to handle but every
        // reply failed (so the circuit breaker can advance). No comments at
        // all → ok:true (no work to do).
        $ok = ($failed === 0) || ($processed > 0);
        $message = $processed > 0
            ? sprintf(__('已回复 %d 条评论。', 'linked3'), $processed)
            : ($failed > 0
                ? sprintf(__('%d 条评论回复失败。', 'linked3'), $failed)
                : __('暂无待回复评论。', 'linked3'));
        return ['ok' => $ok, 'message' => $message, 'items_processed' => $processed];
    }
}
