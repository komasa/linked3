<?php

declare(strict_types=1);
namespace Linked3\Classes\AI\Pipeline;
if (!defined('ABSPATH')) exit;
/**
 * Provider registry.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.AI.Pipeline
 * @since      27.1.0
 */
final class ProviderRegistry
{
    const BUILTIN_PROVIDERS = [
        'siliconflow' => ['name' => 'SiliconFlow', 'api_base' => 'https://api.siliconflow.cn/v1', 'has_default_key' => true, 'default_model' => 'Qwen/Qwen2.5-7B-Instruct'],
        'deepseek' => ['name' => 'DeepSeek', 'api_base' => 'https://api.deepseek.com/v1', 'has_default_key' => false, 'default_model' => 'deepseek-chat'],
        'openai' => ['name' => 'OpenAI', 'api_base' => 'https://api.openai.com/v1', 'has_default_key' => false, 'default_model' => 'gpt-4o-mini'],
        'zai' => ['name' => 'Z.AI', 'api_base' => 'https://api.z.ai/v1', 'has_default_key' => false, 'default_model' => 'glm-4-flash'],
    ];
    public static function get_provider($slug) : mixed { if (!isset(self::BUILTIN_PROVIDERS[$slug])) return null; $c = self::BUILTIN_PROVIDERS[$slug]; $sk = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []); $sm = get_option(LINKED3_OPTION_PREFIX . 'provider_models', []); $sb = get_option(LINKED3_OPTION_PREFIX . 'provider_api_bases', []); $c['configured'] = !empty($sk[$slug]); $c['model'] = $sm[$slug] ?? $c['default_model']; $c['api_base'] = $sb[$slug] ?? $c['api_base']; return $c; }
    public static function get_model($slug) : mixed { $p = self::get_provider($slug); return $p['model'] ?? ''; }
    public static function all() { $r = []; foreach (array_keys(self::BUILTIN_PROVIDERS) as $s) $r[$s] = self::get_provider($s); return $r; }
    public static function is_configured($slug) { $p = self::get_provider($slug); return $p['configured'] ?? false; }
}
