<?php

declare(strict_types=1);
/**
 * Provider Strategy Factory.
 *
 * Singleton-cached. Returns the right ProviderStrategyInterface
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

final class ProviderFactory
{
    /** @var self|null */
    private static $instance;

    /** @var array<string, ProviderStrategyInterface> */
    private $instances = [];

    /** @var array<string, callable> Provider builders, keyed by slug. */
    private $builders = [];

    /** @var KeyRotator|null */
    private $rotator;

    private function __construct() {
        $this->rotator = new KeyRotator();

        // Register the 5 built-in OpenAI-compatible providers (v0.1.5).
        $this->register('openai',   static function () { return new OpenAICompatProvider('openai',   'https://api.openai.com/v1'); });
        $this->register('deepseek', static function () { return new OpenAICompatProvider('deepseek', 'https://api.deepseek.com/v1'); });
        $this->register('kimi',     static function () { return new OpenAICompatProvider('kimi',     'https://api.moonshot.cn/v1'); });
        $this->register('qwen',     static function () { return new OpenAICompatProvider('qwen',     'https://dashscope.aliyuncs.com/compatible-mode/v1'); });
        $this->register('doubao',   static function () { return new OpenAICompatProvider('doubao',   'https://ark.cn-beijing.volces.com/api/v3'); });

        // v1.1.1: 腾讯混元 + 腾讯 LKE 智能体 (原版 v2.9.6 内置 provider)
        $this->register('hunyuan',     static function () { return new HunyuanProvider(); });
        $this->register('tencent_lke', static function () { return new TencentLKEProvider(); });

        // v1.4.0: 智谱 GLM + z.ai + 硅基流动 (OpenAI 兼容)
        $this->register('zhipu',       static function () { return new OpenAICompatProvider('zhipu',       'https://open.bigmodel.cn/api/paas/v4'); });
        $this->register('zai',         static function () { return new OpenAICompatProvider('zai',         'https://api.z.ai/api/paas/v4'); });
        $this->register('siliconflow', static function () { return new OpenAICompatProvider('siliconflow', 'https://api.siliconflow.cn/v1'); });
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
     * @param callable $builder Returns a ProviderStrategyInterface.
     * @return void
     */
    public function register(string $slug, callable $builder)
    : void {
        $this->builders[$slug] = $builder;
    }

    /**
     * @param string $slug
     * @return bool
     */
    public function has(string $slug) : mixed     {
        return isset($this->builders[$slug]);
    }

    /**
     * @param string $slug
     * @return ProviderStrategyInterface|null
     */
    public function make(string $slug): ?ProviderStrategyInterface
    {
        if (isset($this->instances[$slug])) {
            return $this->instances[$slug];
        }
        if (!isset($this->builders[$slug])) {
            // Unknown slug → fall back to a generic OpenAI-compatible provider
            // using whatever api_base the caller supplies in $config. This is
            // how v2.9.6's zdycustom endpoints migrate cleanly.
            $provider = new OpenAICompatProvider($slug, '');
        } else {
            $provider = call_user_func($this->builders[$slug]);
        }
        $this->instances[$slug] = $provider;
        return $provider;
    }

    /**
     * @return KeyRotator
     */
    public function rotator(): KeyRotator
    {
        return $this->rotator;
    }

}
