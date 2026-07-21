<?php

declare(strict_types=1);
/**
 * Distribution module — auto-publish to social platforms.
 *
 * Replaces v2.9.6's misleadingly-named auto-social-sync.php (which was
 * actually WooCommerce AI, not social). This is the REAL social distribution:
 *   - WeChat Official Account (公众号)
 *   - Weibo (微博)
 *   - Twitter/X
 *   - Facebook
 *   - Telegram
 *   - Discord
 *
 * Each platform implements Distribute_Adapter_Interface. Triggers on
 * publish_post / publish_product. Configurable per-post-type + per-platform.
 *
 * @package Linked3
 * @subpackage Classes\Distribute
 */

namespace Linked3\Classes\Distribute;

if (!defined('ABSPATH')) {
    exit;
}

interface DistributeAdapterInterface
{
    /** @return string platform slug */
    public function slug(): string ;
    /** @return string human label */
    public function label(): string ;
    /**
     * @param array $post_data {title, content, url, excerpt, image_url}
     * @param array $config platform-specific credentials
     * @return array{ok:bool, remote_id:string, message:string}
     */
    public function publish(array $post_data, array $config);
    /** @return array{ok:bool, message:string} test connection */
    public function test(array $config);
}
