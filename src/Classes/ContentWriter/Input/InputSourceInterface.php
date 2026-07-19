<?php

declare(strict_types=1);
/**
 * Input source interface — pluggable content input (RSS / CSV / URL / manual).
 *
 * @package Linked3
 * @subpackage Classes\ContentWriter\Input
 */

namespace Linked3\Classes\ContentWriter\Input;

if (!defined('ABSPATH')) {
    exit;
}

interface InputSourceInterface
{
    /**
     * @return string Source slug.
     */
    public function slug();

    /**
     * Fetch items from this source.
     *
     * @param array $config Source-specific config.
     * @param int   $limit  Max items to return.
     * @return array<int,array{title:string, content:string, url:string, guid:string}>
     */
    public function fetch(array $config, $limit = 10);

    /**
     * @return string Human-readable label.
     */
    public function label();
}
