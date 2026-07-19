<?php

declare(strict_types=1);
/**
 * Push engine factory — singleton registry of all configured engines.
 *
 * Mirrors the Provider Strategy Factory pattern: instantiate each engine
 * once, cache by slug, expose via get() / all(). The factory is the only
 * entry point for Push_Manager — engines are never new'd outside it.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Push
 */

namespace Linked3\Classes\SEO\Push;

if (!defined('ABSPATH')) {
    exit;
}

final class PushEngineFactory
{
    /** @var array<string,PushEngine>|null */
    private static $engines;

    /**
     * @return array<string,PushEngine> slug → engine
     */
    public static function all() : mixed {
        if (self::$engines === null) {
            $list = [
                'baidu'    => new PushEngineBaidu(),
                'bing'     => new PushEngineBing(),
                'google'   => new PushEngineGoogleJWT(),
                'toutiao'  => new PushEngineToutiao(),
                'indexnow' => new PushEngineIndexnow(),
            ];
            /**
             * Allow Pro / third-party engines to register.
             */
            self::$engines = (array) apply_filters('linked3/push_engines', $list);
        }
        return self::$engines;
    }

    /**
     * @param string $slug
     * @return PushEngine|null
     */
    public static function get($slug) : mixed     {
        $all = self::all();
        return $all[$slug] ?? null;
    }

    /**
     * @return string[] Configured engine slugs (skips engines missing creds).
     */
    public static function configured_slugs() : mixed {
        $out = [];
        foreach (self::all() as $slug => $engine) {
            if ($engine->is_configured()) {
                $out[] = $slug;
            }
        }
        return $out;
    }
}
