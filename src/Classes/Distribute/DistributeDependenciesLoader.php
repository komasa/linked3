<?php

declare(strict_types=1);
namespace Linked3\Classes\Distribute;
if (!defined('ABSPATH')) exit;

/**
 * Distribute dependencies loader.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Distribute
 * @since      27.1.0
 */

final class DistributeDependenciesLoader
{
    public static function load()
    : void {
        $files = [
            'Classes/Distribute/DistributeAdapter.php',
            'Classes/Distribute/Adapter/TwitterDistributor.php',
            'Classes/Distribute/Adapter/TelegramDistributor.php',
            'Classes/Distribute/Adapter/DiscordDistributor.php',
            'Classes/Distribute/Adapter/WeChatDistributor.php',
            'Classes/Distribute/Adapter/XiaohongshuDistributor.php',
            'Classes/Distribute/Adapter/ZhihuDistributor.php',  // v3.2.0 恢复
            'Classes/Distribute/Adapter/SMZDMDistributor.php',  // v3.2.0 恢复
            'Classes/Distribute/Adapter/WeiboDistributor.php',
            'Classes/Distribute/Adapter/JuejinDistributor.php',
            'Classes/Distribute/Adapter/CSDNDistributor.php',
            'Classes/Distribute/Adapter/BloggerDistributor.php',
            'Classes/Distribute/Adapter/MediumDistributor.php',
            'Classes/Distribute/Adapter/RedditDistributor.php',
            // v3.0.0: 移除 zhihu / smzdm (平台 API 已关停,误导用户)
            // v3.0.0: 新增 B2B 平台 (工厂出海核心渠道)
            'Classes/Distribute/Adapter/AlibabaDistributor.php',
            'Classes/Distribute/Adapter/Alibaba1688Distributor.php',
            'Classes/Distribute/DistributeManager.php',
            'Classes/Distribute/DistributeHooksRegistrar.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) require_once $path;
        }
    }
}
