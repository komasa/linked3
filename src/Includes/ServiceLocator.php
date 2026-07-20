<?php

declare(strict_types=1);
namespace Linked3\Includes;
if (!defined('ABSPATH')) exit;
/**
 * Service locator.
 *
 * @package    Linked3
 * @subpackage Linked3.Includes
 * @since      27.1.0
 */
final class ServiceLocator
{
    const SERVICE_MAP = [
        'dispatcher' => 'Linked3\Classes\Core\AIDispatcher',
        'token_manager' => 'Linked3\Classes\Core\TokenManager',
        'license' => 'Linked3\Classes\License\LicenseService',
        'logger' => 'Linked3\Includes\Log\Logger',
        'container' => 'Linked3\Includes\Container',
        'options' => 'Linked3\Includes\OptionRepository',
        'secrets' => 'Linked3\Includes\SecretVault',
        'request' => 'Linked3\Includes\Request',
        'perf' => 'Linked3\Includes\PerformanceMonitor',
        'config' => 'Linked3\Includes\ConfigRegistry',
        'http' => 'Linked3\Includes\Http\SafeRemote',
        'crypto' => 'Linked3\Includes\Crypto',
        'guard' => 'Linked3\Includes\AjaxGuard',
        'vector' => 'Linked3\Classes\Vector\VectorFactory',
        'chat' => 'Linked3\Classes\Chat\ChatManager',
        'publish' => 'Linked3\Classes\Publish\PublishManager',
        'distribute' => 'Linked3\Classes\Distribute\DistributeManager',
        'addons' => 'Linked3\Classes\Addons\AddonManager',
        'schema' => 'Linked3\Classes\SEO\Schema\SchemaMarkup',
        'push' => 'Linked3\Classes\SEO\Push\PushManager',
    ];
    public static function get($name) { if (!isset(self::SERVICE_MAP[$name])) return null; $fqcn = self::SERVICE_MAP[$name]; if (class_exists('Linked3\Includes\Container')) { $c = Container::instance(); if ($c->has($fqcn)) return $c->get($fqcn); } if (method_exists($fqcn, 'instance')) return $fqcn::instance(); if (class_exists($fqcn)) return new $fqcn(); return null; }
    public static function has($name) { return isset(self::SERVICE_MAP[$name]); }
    public static function names() { return array_keys(self::SERVICE_MAP); }
}
