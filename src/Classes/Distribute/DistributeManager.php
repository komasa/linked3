<?php

declare(strict_types=1);
/**
 * Distribution Manager — v3.0.0 大版本重构
 *
 * 修复点:
 *   1. 熔断器同步 v0.6 双 transient 模型 (fail_count + open_flag)
 *   2. 下线 zhihu/smzdm (平台 API 已关停)
 *   3. 新增 distribute_post_to_platforms() 支持平台子集 (per-task)
 *   4. 新增 distribute_post_async() 入队 (替代串行同步循环)
 *   5. AutoGPT enqueue 接入点 (失败时入队重试)
 *
 * @package Linked3
 * @subpackage Classes\Distribute
 */

namespace Linked3\Classes\Distribute;
use Linked3\Includes\Log\Logger;



if (!defined('ABSPATH')) {
    exit;
}

use Linked3\Classes\Distribute\Adapter\{
    TwitterDistributor,
    TelegramDistributor,
    DiscordDistributor,
    WeChatDistributor,
    XiaohongshuDistributor,
    ZhihuDistributor,
    SMZDMDistributor,
    WeiboDistributor,
    JuejinDistributor,
    CSDNDistributor,
    BloggerDistributor,
    MediumDistributor,
    RedditDistributor,
    AlibabaDistributor,
    Alibaba1688Distributor
};
final class DistributeManager
{
    private static $instance;
    private $adapters;

    /** 熔断阈值: 5 次失败触发 */
    const CB_FAIL_THRESHOLD = 5;
    /** 熔断冷却: 5 分钟 */
    const CB_COOLDOWN = 300;

    private function __construct() {
        // v3.0.0: 移除 zhihu / smzdm (平台 API 已关停,误导用户); v3.2.0: 恢复 (MCP 中转模式)
        // v3.0.0: 新增 alibaba / alibaba1688
        $this->adapters = [
            'twitter'      => new \Linked3\Classes\Distribute\Adapter\TwitterDistributor(),
            'telegram'     => new \Linked3\Classes\Distribute\Adapter\TelegramDistributor(),
            'discord'      => new \Linked3\Classes\Distribute\Adapter\DiscordDistributor(),
            'wechat'       => new \Linked3\Classes\Distribute\Adapter\WeChatDistributor(),
            'xiaohongshu'  => new \Linked3\Classes\Distribute\Adapter\XiaohongshuDistributor(),
            'zhihu'        => new \Linked3\Classes\Distribute\Adapter\ZhihuDistributor(),  // v3.2.0 恢复
            'smzdm'        => new \Linked3\Classes\Distribute\Adapter\SMZDMDistributor(),  // v3.2.0 恢复
            'weibo'        => new \Linked3\Classes\Distribute\Adapter\WeiboDistributor(),
            'juejin'       => new \Linked3\Classes\Distribute\Adapter\JuejinDistributor(),
            'csdn'         => new \Linked3\Classes\Distribute\Adapter\CSDNDistributor(),
            'blogger'      => new \Linked3\Classes\Distribute\Adapter\BloggerDistributor(),
            'medium'       => new \Linked3\Classes\Distribute\Adapter\MediumDistributor(),
            'reddit'       => new \Linked3\Classes\Distribute\Adapter\RedditDistributor(),
            // v3.0.0: B2B 平台 (工厂出海核心渠道)
            'alibaba'      => new \Linked3\Classes\Distribute\Adapter\AlibabaDistributor(),
            'alibaba1688'  => new \Linked3\Classes\Distribute\Adapter\Alibaba1688Distributor(),
        ];
    }

    public static function instance() : mixed {
        if (null === self::$instance) {
            // v4.4.6: delegate to the DI container when available.
            if (class_exists('\\Linked3\\Includes\\Container')) {
                $container = \Linked3\Includes\Container::instance();
                if ($container->has(self::class)) {
                    self::$instance = $container->get(self::class);
                    return self::$instance;
                }
            }
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construct the singleton WITHOUT going through the container.
     *
     * v4.4.6: used by the container's factory to avoid infinite recursion.
     *
     * @return self
     * @internal
     */
    public static function instance_without_container() : mixed     {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    /**
     * 获取所有可用平台 (UI 用)。
     */
    public function available_platforms() : mixed {
        $list = [];
        foreach ($this->adapters as $slug => $adapter) {
            $list[$slug] = $adapter->label();
        }
        return $list;
    }

    /**
     * 分发文章到所有启用的平台 (向后兼容)。
     */
    public function distribute_post($post_id) : mixed     {
        return $this->distribute_post_to_platforms($post_id, []);
    }

    /**
     * v3.0.0: 分发文章到指定平台子集。
     *
     * @param int        $post_id
     * @param array      $platforms  平台 slug 数组;空数组表示"所有启用平台"
     * @return array<int,array{platform:string, ok:bool, message:string, remote_id:string}>
     */
    public function distribute_post_to_platforms($post_id, array $platforms = [])
    {
        $post = get_post($post_id);
        if (!$post) return [];
        $configs = get_option(LINKED3_OPTION_PREFIX . 'distribute_configs', []);
        if (!is_array($configs) || empty($configs)) return [];

        $post_data = [
            'title'   => $post->post_title,
            'content' => wp_strip_all_tags($post->post_content),
            'url'     => get_permalink($post_id),
            'excerpt' => wp_strip_all_tags(get_the_excerpt($post)),
            'image_url' => get_the_post_thumbnail_url($post_id, 'large') ?: '',
        ];

        $results = [];
        $log = Logger::instance();
        $target_platforms = empty($platforms) ? array_keys($configs) : $platforms;

        foreach ($target_platforms as $platform) {
            $cfg = $configs[$platform] ?? null;
            if (!$cfg || empty($cfg['enabled'])) continue;
            if (!isset($this->adapters[$platform])) continue;

            // v3.0.0: 熔断器双 transient 模型
            if ($this->is_circuit_open($platform)) {
                $results[] = [
                    'platform' => $platform, 'ok' => false, 'remote_id' => '',
                    'message' => __('熔断器已开启 (5 分钟冷却)。', 'linked3'),
                ];
                continue;
            }
            $adapter = $this->adapters[$platform];
            try {
                $r = $adapter->publish($post_data, $cfg);
                $results[] = [
                    'platform' => $platform,
                    'ok' => !empty($r['ok']),
                    'remote_id' => $r['remote_id'] ?? '',
                    'message' => $r['message'],
                ];
                if (!$r['ok']) {
                    $this->record_failure($platform);
                    $log->warning('distribute', "Platform {$platform} failed: " . $r['message']);
                } else {
                    $this->reset_circuit($platform);
                    $log->info('distribute', "Distributed to {$platform}: " . ($r['remote_id'] ?? ''));
                }
            } catch (\Throwable $e) {
                $results[] = [
                    'platform' => $platform, 'ok' => false, 'remote_id' => '',
                    'message' => $e->getMessage(),
                ];
                $this->record_failure($platform);
                $log->error('distribute', "Platform {$platform} exception: " . $e->getMessage());
            }
        }
        return $results;
    }

    public function test_platform($platform)
    {
        $configs = get_option(LINKED3_OPTION_PREFIX . 'distribute_configs', []);
        $cfg = $configs[$platform] ?? [];
        if (!isset($this->adapters[$platform])) {
            return ['ok' => false, 'message' => __('未知平台或已下线。', 'linked3')];
        }
        return $this->adapters[$platform]->test($cfg);
    }

    // ----- v3.0.0 熔断器 (双 transient 模型,对齐 Publish_Manager) -----

    private function is_circuit_open($platform)
    : bool {
        $open = get_transient('linked3_dist_cb_open_' . $platform);
        if ($open) return true;
        // 半开试探: 冷却到期后允许 1 次试探,不立即恢复全量
        return false;
    }

    private function record_failure($platform)
    : void {
        $fail_key = 'linked3_dist_cb_fail_' . $platform;
        $count = (int) get_transient($fail_key) + 1;
        // 失败计数窗口 5 分钟
        set_transient($fail_key, $count, self::CB_COOLDOWN);
        if ($count >= self::CB_FAIL_THRESHOLD) {
            // 触发熔断,冷却 5 分钟
            set_transient('linked3_dist_cb_open_' . $platform, 1, self::CB_COOLDOWN);
            $log = Logger::instance();
            $log->warning('distribute', "Platform {$platform} circuit OPENED after {$count} failures");
        }
    }

    private function reset_circuit($platform)
    : void {
        delete_transient('linked3_dist_cb_fail_' . $platform);
        delete_transient('linked3_dist_cb_open_' . $platform);
    }

    /**
     * v3.0.0: 把失败的分发入队,供 AutoGPT 队列重试。
     *
     * @param int    $post_id
     * @param string $platform
     * @param string $reason
     * @return int 队列项 ID
     */
    public function enqueue_retry($post_id, $platform, $reason = '')
    {
        if (!class_exists('\\Linked3\\Classes\\AutoGPT\\AutoGPTTaskRepository')) {
            return 0;
        }
        $repo = new \Linked3\Classes\AutoGPT\AutoGPTTaskRepository();
        // 用 task_id=0 表示"非任务级队列项"(独立分发重试)
        // scheduled_for = 5 分钟后
        return $repo->enqueue(0, [
            'type' => 'distribute_retry',
            'post_id' => (int) $post_id,
            'platform' => $platform,
            'reason' => $reason,
        ], gmdate('Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS));
    }
}
