<?php

declare(strict_types=1);
/**
 * SEO module — dependency loader.
 *
 * Mirrors Content Writer's loader pattern: pure require_once, file_exists
 * guarded, file list declared statically for ordering clarity. Loaded
 * eagerly via the hard-registered entry in
 * Linked3_Dependency_Loader::$core_files (see v0.4.1).
 *
 * @package Linked3
 * @subpackage Classes\SEO
 */

namespace Linked3\Classes\SEO;

if (!defined('ABSPATH')) {
    exit;
}

final class SEODependenciesLoader
{
    /**
     * @return void
     */
    public static function load()
    : void {
        $files = [
            // v0.4.1 — config registry
            'Classes/SEO/class-linked3-seo-config.php',

            // v0.4.2 — keyword extraction (TF-IDF + TextRank + Chinese regex + hotwords)
            'Classes/SEO/Keyword/class-linked3-hotwords.php',
            'Classes/SEO/Keyword/class-linked3-keyword-tfidf.php',
            'Classes/SEO/Keyword/class-linked3-keyword-textrank.php',
            'Classes/SEO/Keyword/class-linked3-keyword-extractor.php',

            // v0.4.3 — interlinking + relationship graph
            'Classes/SEO/Interlink/interface-linked3-interlink-strategy.php',
            'Classes/SEO/Interlink/class-linked3-interlink-strategy-frequent.php',
            'Classes/SEO/Interlink/class-linked3-interlink-strategy-recent.php',
            'Classes/SEO/Interlink/class-linked3-interlink-strategy-popular.php',
            'Classes/SEO/Interlink/class-linked3-interlink-builder.php',

            // v0.4.4 — external link processor
            'Classes/SEO/Links/class-linked3-external-link-processor.php',

            // v0.4.5 — Schema Markup (JSON-LD)
            'Classes/SEO/Schema/interface-linked3-schema-builder.php',
            'Classes/SEO/Schema/class-linked3-schema-article.php',
            'Classes/SEO/Schema/class-linked3-schema-blogposting.php',
            'Classes/SEO/Schema/class-linked3-schema-faq.php',
            'Classes/SEO/Schema/class-linked3-schema-product.php',
            'Classes/SEO/Schema/class-linked3-schema-howto.php',
            'Classes/SEO/Schema/class-linked3-schema-markup.php',

            // v0.4.6 — multi-search-engine push (5 engines via Safe_Remote)
            'Classes/SEO/Push/interface-linked3-push-engine.php',
            'Classes/SEO/Push/class-linked3-push-engine-baidu.php',
            'Classes/SEO/Push/class-linked3-push-engine-bing.php',
            'Classes/SEO/Push/class-linked3-push-engine-google-jwt.php',
            'Classes/SEO/Push/class-linked3-push-engine-toutiao.php',
            'Classes/SEO/Push/class-linked3-push-engine-indexnow.php',
            'Classes/SEO/Push/class-linked3-push-engine-factory.php',
            'Classes/SEO/Push/class-linked3-push-log-repository.php',
            'Classes/SEO/Push/class-linked3-push-manager.php',

            // v0.4.8 — Indexnow save_post hook (instant push on publish)
            'Classes/SEO/Hooks/class-linked3-indexnow-save-post-hook.php',

            // v0.4.9 — SEO scorecard
            'Classes/SEO/Scoring/class-linked3-seo-scorecard.php',

            // v0.4.10 — SEO adapter (Yoast / RankMath / AIOSEO compat)
            'Classes/SEO/Adapter/interface-linked3-seo-adapter.php',
            'Classes/SEO/Adapter/class-linked3-seo-adapter-yoast.php',
            'Classes/SEO/Adapter/class-linked3-seo-adapter-rankmath.php',
            'Classes/SEO/Adapter/class-linked3-seo-adapter-aioseo.php',
            'Classes/SEO/Adapter/class-linked3-seo-adapter-detector.php',

            // v4.7.0 — GEO Enhancer (AI search engine optimization / llms.txt)
            'Classes/SEO/class-linked3-geo-enhancer.php',

            // AJAX base + actions (v0.4.7 retry + v0.4.9 scorecard + manual push)
            'Classes/SEO/Ajax/class-linked3-seo-base-ajax-action.php',
            'Classes/SEO/Ajax/Actions/class-linked3-push-retry-action.php',
            'Classes/SEO/Ajax/Actions/class-linked3-push-now-action.php',
            'Classes/SEO/Ajax/Actions/class-linked3-seo-score-action.php',

            // v0.4.1 — hooks registrar (always last so it can see all classes)
            'Classes/SEO/class-linked3-seo-hooks-registrar.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
