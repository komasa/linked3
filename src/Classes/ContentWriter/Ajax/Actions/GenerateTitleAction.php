<?php

declare(strict_types=1);
namespace Linked3\Classes\ContentWriter\Ajax\Actions;
use Linked3\Classes\ContentWriter\Ajax\ContentWriterBaseAjaxAction;
use Linked3\Classes\License\PlanDefinitions;
use Linked3\Classes\License\LicenseService;


if (!defined('ABSPATH')) exit;
/**
 * Generate title action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.ContentWriter.Ajax.Actions
 * @since      27.1.0
 */

final class GenerateTitleAction extends ContentWriterBaseAjaxAction
{
    public function handle()
    : void {
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $requested = max(1, (int) ($_POST['count'] ?? 5));
        if (!$keyword) $this->send_error(__('需要关键词。', 'linked3'), 400);

        // v0.4.0: plan-gated batch cap (Free=10/Pro=100/Premium=1000).
        $plan = LicenseService::instance()->plan();
        $plan_cap = (int) PlanDefinitions::feature($plan, 'batch_per_run');
        if ($plan_cap <= 0) {
            $plan_cap = 10;
        }
        $count = min($requested, $plan_cap);

        try { // v19.3.0: AI 调用容错
            $result = $this->dispatcher()->chat(
                [
                    ['role' => 'system', 'content' => __('You generate engaging, SEO-optimized article titles.', 'linked3')],
                    ['role' => 'user', 'content' => sprintf(__('为关键词「%s」生成 %d 个文章标题,每行一个,不要编号。', 'linked3'), $count, $keyword)],
                ],
                ['provider' => get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow'), 'model' => 'Qwen/Qwen2.5-7B-Instruct', 'temperature' => 0.9, 'max_tokens' => 300, 'module' => 'content_writer'],
                ['fallback_providers' => []]
            );
        } catch (\Throwable $e) {
            $this->send_error('AI 调用失败: ' . $e->getMessage(), 502);
        }
        $titles = array_filter(array_map('trim', explode("\n", $result['content'])));
        \Linked3\Classes\Core\TokenManager::instance()->record(get_current_user_id(), '', $result['usage']['total_tokens']);
        $this->send_success(['titles' => array_slice(array_values($titles), 0, $count)]);
    }
}
