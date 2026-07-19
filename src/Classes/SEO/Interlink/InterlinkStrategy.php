<?php

declare(strict_types=1);
/**
 * Interlink strategy interface.
 *
 * Implementations produce a candidate target post list for a given source
 * post + keyword set. Strategies are chosen via SEOConfig
 * `interlink.priority` (frequent|recent|popular).
 *
 * @package Linked3
 * @subpackage Classes\SEO\Interlink
 */

namespace Linked3\Classes\SEO\Interlink;

if (!defined('ABSPATH')) {
    exit;
}

interface InterlinkStrategy
{
    /**
     * @param int      $source_post_id  Post receiving the interlinks.
     * @param string[] $keywords        Keyword suggestions to match anchors against.
     * @param int      $limit           Max candidates.
     * @param int      $max_per_target  Hard ceiling per target post (anti-over-opt).
     * @return array<int,array{post_id:int,title:string,url:string,anchor:string,score:float}>
     */
    public function candidates($source_post_id, array $keywords, $limit, $max_per_target);
}
