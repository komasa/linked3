<?php
namespace Linked3\Classes\Distribute;
if (!defined('ABSPATH')) exit;

/**
 * Distribute dependencies loader.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Distribute
 * @since      27.1.0
 */

final class Linked3_Distribute_Dependencies_Loader
{
    public static function load()
    : void {
        $files = [
            'Classes/Distribute/interface-linked3-distribute-adapter.php',
            'Classes/Distribute/Adapter/class-linked3-twitter-distributor.php',
            'Classes/Distribute/Adapter/class-linked3-telegram-distributor.php',
            'Classes/Distribute/Adapter/class-linked3-discord-distributor.php',
            'Classes/Distribute/Adapter/class-linked3-wechat-distributor.php',
            'Classes/Distribute/Adapter/class-linked3-xiaohongshu-distributor.php',
            'Classes/Distribute/Adapter/class-linked3-zhihu-distributor.php',  // v3.2.0 恢复
            'Classes/Distribute/Adapter/class-linked3-smzdm-distributor.php',  // v3.2.0 恢复
            'Classes/Distribute/Adapter/class-linked3-weibo-distributor.php',
            'Classes/Distribute/Adapter/class-linked3-juejin-distributor.php',
            'Classes/Distribute/Adapter/class-linked3-csdn-distributor.php',
            'Classes/Distribute/Adapter/class-linked3-blogger-distributor.php',
            'Classes/Distribute/Adapter/class-linked3-medium-distributor.php',
            'Classes/Distribute/Adapter/class-linked3-reddit-distributor.php',
            // v3.0.0: 移除 zhihu / smzdm (平台 API 已关停,误导用户)
            // v3.0.0: 新增 B2B 平台 (工厂出海核心渠道)
            'Classes/Distribute/Adapter/class-linked3-alibaba-distributor.php',
            'Classes/Distribute/Adapter/class-linked3-alibaba1688-distributor.php',
            'Classes/Distribute/class-linked3-distribute-manager.php',
            'Classes/Distribute/class-linked3-distribute-hooks-registrar.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) require_once $path;
        }
    }
}
