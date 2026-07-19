<?php

declare(strict_types=1);
/**
 * SEO module — dependency loader.
 *
 * Mirrors Content Writer's loader pattern: pure require_once, file_exists
 * guarded, file list declared statically for ordering clarity. Loaded
 * eagerly via the hard-registered entry in
 * DependencyLoader::$core_files (see v0.4.1).
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
            'Classes/SEO/SeoConfig.php',

            // v0.4.2 — keyword extraction (TF-IDF + TextRank + Chinese regex + hotwords)
            'Classes/SEO/Keyword/Hotwords.php',
            'Classes/SEO/Keyword/KeywordTfidf.php',
            'Classes/SEO/Keyword/KeywordTextrank.php',
            'Classes/SEO/Keyword/KeywordExtractor.php',

            // v0.4.3 — interlinking + relationship graph
            'Classes/SEO/Interlink/InterlinkStrategy.php',
            'Classes/SEO/Interlink/InterlinkStrategyFrequent.php',
            'Classes/SEO/Interlink/InterlinkStrategyRecent.php',
            'Classes/SEO/Interlink/InterlinkStrategyPopular.php',
            'Classes/SEO/Interlink/InterlinkBuilder.php',

            // v0.4.4 — external link processor
            'Classes/SEO/Links/ExternalLinkProcessor.php',

            // v0.4.5 — Schema Markup (JSON-LD)
            'Classes/SEO/Schema/SchemaBuilder.php',
            'Classes/SEO/Schema/SchemaArticle.php',
            'Classes/SEO/Schema/SchemaBlogposting.php',
            'Classes/SEO/Schema/SchemaFaq.php',
            'Classes/SEO/Schema/SchemaProduct.php',
            'Classes/SEO/Schema/SchemaHowto.php',
            'Classes/SEO/Schema/SchemaMarkup.php',

            // v0.4.6 — multi-search-engine push (5 engines via Safe_Remote)
            'Classes/SEO/Push/PushEngine.php',
            'Classes/SEO/Push/PushEngineBaidu.php',
            'Classes/SEO/Push/PushEngineBing.php',
            'Classes/SEO/Push/PushEngineGoogleJwt.php',
            'Classes/SEO/Push/PushEngineToutiao.php',
            'Classes/SEO/Push/PushEngineIndexnow.php',
            'Classes/SEO/Push/PushEngineFactory.php',
            'Classes/SEO/Push/PushLogRepository.php',
            'Classes/SEO/Push/PushManager.php',

            // v0.4.8 — Indexnow save_post hook (instant push on publish)
            'Classes/SEO/Hooks/IndexnowSavePostHook.php',

            // v0.4.9 — SEO scorecard
            'Classes/SEO/Scoring/SeoScorecard.php',

            // v0.4.10 — SEO adapter (Yoast / RankMath / AIOSEO compat)
            'Classes/SEO/Adapter/SeoAdapter.php',
            'Classes/SEO/Adapter/SeoAdapterYoast.php',
            'Classes/SEO/Adapter/SeoAdapterRankmath.php',
            'Classes/SEO/Adapter/SeoAdapterAioseo.php',
            'Classes/SEO/Adapter/SeoAdapterDetector.php',

            // v4.7.0 — GEO Enhancer (AI search engine optimization / llms.txt)
            'Classes/SEO/GeoEnhancer.php',

            // AJAX base + actions (v0.4.7 retry + v0.4.9 scorecard + manual push)
            'Classes/SEO/Ajax/SeoBaseAjaxAction.php',
            'Classes/SEO/Ajax/Actions/PushRetryAction.php',
            'Classes/SEO/Ajax/Actions/PushNowAction.php',
            'Classes/SEO/Ajax/Actions/SeoScoreAction.php',

            // v0.4.1 — hooks registrar (always last so it can see all classes)
            'Classes/SEO/SeoHooksRegistrar.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
