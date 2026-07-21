<?php

declare(strict_types=1);
/**
 * Push engine interface — one per search engine.
 *
 * Implementations are responsible for:
 *   - Building the engine-specific request URL, headers, and body
 *   - Calling SafeRemote (never raw wp_remote_*)
 *   - Parsing the response into a normalised result array
 *
 * Implementations are NOT responsible for:
 *   - Plan gating (handled by Push_Manager)
 *   - Logging (handled by Push_Manager via PushLogRepository)
 *   - Quota counting (handled by SEOBaseAjaxAction)
 *
 * @package Linked3
 * @subpackage Classes\SEO\Push
 */

namespace Linked3\Classes\SEO\Push;

if (!defined('ABSPATH')) {
    exit;
}

interface PushEngine
{
    /**
     * @return string Engine slug (baidu|bing|google|toutiao|indexnow).
     */
    public function slug(): string ;

    /**
     * @return string Human-readable name for the admin UI.
     */
    public function label(): string ;

    /**
     * @return bool Whether this engine is configured (has the required API key / endpoint).
     */
    public function is_configured(): bool ;

    /**
     * Push a list of URLs to this engine.
     *
     * @param string[] $urls
     * @return array{
     *     ok:bool,
     *     code:int,
     *     body:string,
     *     message:string,
     *     pushed:int,
     *     raw:array|null
     * }
     */
    public function push(array $urls);
}
