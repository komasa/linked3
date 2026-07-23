<?php

declare(strict_types=1);
/**
 * SEO adapter interface — compatibility shim for Yoast / RankMath / AIOSEO.
 *
 * When a 3rd-party SEO plugin is active, Linked3 should NOT emit its own
 * meta tags / schema markup (avoid duplicate Schema.org, duplicate og:,
 * duplicate canonical). Adapters translate Linked3's intent ("score this
 * post", "set this meta description", "emit this schema") into the active
 * plugin's API.
 *
 * Adapters are responsible for:
 *   - Reporting whether the active plugin handles schema / meta / sitemap
 *   - Surfacing the active plugin's stored meta description for a post
 *   - Forwarding Linked3 score-card recommendations to the active plugin
 *     (where possible)
 *
 * Adapters are NOT responsible for:
 *   - Configuring the active plugin itself (admin's job)
 *   - Duplicating the active plugin's UI
 *
 * @package Linked3
 * @subpackage Classes\SEO\Adapter
 */

namespace Linked3\Classes\SEO\Adapter;

if (!defined('ABSPATH')) {
    exit;
}

interface SEOAdapter
{
    /**
     * @return string Adapter slug (yoast|rankmath|aioseo|none).
     */
    public function slug(): string ;

    /**
     * @return string Human-readable name.
     */
    public function label(): string ;

    /**
     * @return bool Whether the active plugin is present + active.
     */
    public function is_active(): bool ;

    /**
     * Does the active plugin emit its own Schema.org JSON-LD?
     * If true, SchemaMarkup output is suppressed.
     *
     * @return bool
     */
    public function handles_schema(): bool ;

    /**
     * Does the active plugin emit its own meta description tag?
     * If true, Linked3 will not emit a duplicate <meta name="description">.
     *
     * @return bool
     */
    public function handles_meta_description(): bool ;

}
