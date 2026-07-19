<?php
/**
 * Provider Strategy Factory.
 *
 * Singleton-cached. Returns the right Linked3_Provider_Strategy_Interface
 * for a given provider slug. Built-in providers are pre-registered; custom
 * OpenAI-compatible endpoints are created on demand.
 *
 * Mirrors aipower's ProviderStrategyFactory — providers are cheap objects,
 * but caching avoids re-instantiating per request.
 *
 * @package Linked3
 * @subpackage Classes\Core\Providers
 */

namespace Linked3\Classes\Core\Providers;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_Provider_Factory
{
    /** @var self|null */
    private static $instance;

    /** @var array<string, Linked3_Provider_Strategy_Interface> */
    private $instances = [];

    /** @var array<string, callable> Provider builders, keyed by slug. */
    private $builders = [];

    /** @var Linked3_Key_Rotator|null */
    private $rotator;

    private function __construct() {
        $this->rotator = new Linked3_Key_Rotator();

        // Register the 5 built-in OpenAI-compatible providers (v0.1.5).
        $this->register('openai',   static function () { return new Linked3_OpenAI_Compat_Provider('openai',   'https://api.openai.com/v1'); });
        $this->register('deepseek', static function () { return new Linked3_OpenAI_Compat_Provider('deepseek', 'https://api.deepseek.com/v1'); });
        $this->register('kimi',     static function () { return new Linked3_OpenAI_Compat_Provider('kimi',     'https://api.moonshot.cn/v1'); });
        $this->register('qwen',     static function () { return new Linked3_OpenAI_Compat_Provider('qwen',     'https://dashscope.aliyuncs.com/compatible-mode/v1'); });
        $this->register('doubao',   static function () { return new Linked3_OpenAI_Compat_Provider('doubao',   'https://ark.cn-beijing.volces.com/api/v3'); });

        // v1.1.1: 腾讯混元 + 腾讯 LKE 智能体 (原版 v2.9.6 内置 provider)
        $this->register('hunyuan',     static function () { return new Linked3_Hunyuan_Provider(); });
        $this->register('tencent_lke', static function () { return new Linked3_Tencent_LKE_Provider(); });

        // v1.4.0: 智谱 GLM + z.ai + 硅基流动 (OpenAI 兼容)
        $this->register('zhipu',       static function () { return new Linked3_OpenAI_Compat_Provider('zhipu',       'https://open.bigmodel.cn/api/paas/v4'); });
        $this->register('zai',         static function () { return new Linked3_OpenAI_Compat_Provider('zai',         'https://api.z.ai/api/paas/v4'); });
        $this->register('siliconflow', static function () { return new Linked3_OpenAI_Compat_Provider('siliconflow', 'https://api.siliconflow.cn/v1'); });
        // 硅基流动默认模型
        // 注意:必须在 API 设置里选择实际可用的模型 (如 Qwen/Qwen2.5-7B-Instruct)

        /**
         * Let Pro / addons register additional providers (Anthropic, Gemini,
         * GLM, 混元, Tencent LKE, local Ollama, etc.) — to be added in v0.1.5
         * extension modules and v0.2.x Pro loader.
         */
        do_action_ref_array('linked3/register_providers', [&$this]);
    }

    /**
     * @return self
     */
    public static function instance() : mixed     {
        if (null === self::$instance) {
            // v4.4.2: delegate to the DI container when available.
            if (class_exists('\\Linked3\\Includes\\Linked3_Container')) {
                $container = \Linked3\Includes\Linked3_Container::instance();
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
     * v4.4.2: used by the container's factory to avoid infinite recursion.
     *
     * @return self
     * @internal
     */
    public static function instance_without_container() : mixed {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param string   $slug
     * @param callable $builder Returns a Linked3_Provider_Strategy_Interface.
     * @return void
     */
    public function register($slug, callable $builder)
    : void {
        $this->builders[$slug] = $builder;
    }

    /**
     * @param string $slug
     * @return bool
     */
    public function has($slug) : mixed     {
        return isset($this->builders[$slug]);
    }

    /**
     * @param string $slug
     * @return Linked3_Provider_Strategy_Interface|null
     */
    public function make($slug)
    {
        if (isset($this->instances[$slug])) {
            return $this->instances[$slug];
        }
        if (!isset($this->builders[$slug])) {
            // Unknown slug → fall back to a generic OpenAI-compatible provider
            // using whatever api_base the caller supplies in $config. This is
            // how v2.9.6's zdycustom endpoints migrate cleanly.
            $provider = new Linked3_OpenAI_Compat_Provider($slug, '');
        } else {
            $provider = call_user_func($this->builders[$slug]);
        }
        $this->instances[$slug] = $provider;
        return $provider;
    }

    /**
     * @return Linked3_Key_Rotator
     */
    public function rotator()
    {
        return $this->rotator;
    }

    /**
     * @return string[] All registered provider slugs.
     */
    public function slugs()
    {
        return array_keys($this->builders);
    }
}
