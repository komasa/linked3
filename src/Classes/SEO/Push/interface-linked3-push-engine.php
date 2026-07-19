<?php
/**
 * Push engine interface — one per search engine.
 *
 * Implementations are responsible for:
 *   - Building the engine-specific request URL, headers, and body
 *   - Calling Linked3_Safe_Remote (never raw wp_remote_*)
 *   - Parsing the response into a normalised result array
 *
 * Implementations are NOT responsible for:
 *   - Plan gating (handled by Push_Manager)
 *   - Logging (handled by Push_Manager via Linked3_Push_Log_Repository)
 *   - Quota counting (handled by Linked3_SEO_Base_Ajax_Action)
 *
 * @package Linked3
 * @subpackage Classes\SEO\Push
 */

namespace Linked3\Classes\SEO\Push;

if (!defined('ABSPATH')) {
    exit;
}

interface Linked3_Push_Engine
{
    /**
     * @return string Engine slug (baidu|bing|google|toutiao|indexnow).
     */
    public function slug();

    /**
     * @return string Human-readable name for the admin UI.
     */
    public function label();

    /**
     * @return bool Whether this engine is configured (has the required API key / endpoint).
     */
    public function is_configured();

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
