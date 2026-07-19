<?php
namespace Linked3\Includes;
if (!defined('ABSPATH')) exit;
/**
 * Service locator.
 *
 * @package    Linked3
 * @subpackage Linked3.Includes
 * @since      27.1.0
 */
final class Linked3_Service_Locator
{
    const SERVICE_MAP = [
        'dispatcher' => 'Linked3\Classes\Core\Linked3_AI_Dispatcher',
        'token_manager' => 'Linked3\Classes\Core\Linked3_Token_Manager',
        'license' => 'Linked3\Classes\License\Linked3_License_Service',
        'logger' => 'Linked3\Includes\Log\Linked3_Logger',
        'container' => 'Linked3\Includes\Linked3_Container',
        'options' => 'Linked3\Includes\Linked3_Option_Repository',
        'secrets' => 'Linked3\Includes\Linked3_Secret_Vault',
        'request' => 'Linked3\Includes\Linked3_Request',
        'perf' => 'Linked3\Includes\Linked3_Performance_Monitor',
        'config' => 'Linked3\Includes\Linked3_Config_Registry',
        'http' => 'Linked3\Includes\Http\Linked3_Safe_Remote',
        'crypto' => 'Linked3\Includes\Linked3_Crypto',
        'guard' => 'Linked3\Includes\Linked3_Ajax_Guard',
        'vector' => 'Linked3\Classes\Vector\Linked3_Vector_Factory',
        'chat' => 'Linked3\Classes\Chat\Linked3_Chat_Manager',
        'publish' => 'Linked3\Classes\Publish\Linked3_Publish_Manager',
        'distribute' => 'Linked3\Classes\Distribute\Linked3_Distribute_Manager',
        'addons' => 'Linked3\Classes\Addons\Linked3_Addon_Manager',
        'schema' => 'Linked3\Classes\SEO\Schema\Linked3_Schema_Markup',
        'push' => 'Linked3\Classes\SEO\Push\Linked3_Push_Manager',
    ];
    public static function get($name) { if (!isset(self::SERVICE_MAP[$name])) return null; $fqcn = self::SERVICE_MAP[$name]; if (class_exists('Linked3\Includes\Linked3_Container')) { $c = Linked3_Container::instance(); if ($c->has($fqcn)) return $c->get($fqcn); } if (method_exists($fqcn, 'instance')) return $fqcn::instance(); if (class_exists($fqcn)) return new $fqcn(); return null; }
    public static function has($name) { return isset(self::SERVICE_MAP[$name]); }
    public static function fqcn($name) { return self::SERVICE_MAP[$name] ?? null; }
    public static function names() { return array_keys(self::SERVICE_MAP); }
}
