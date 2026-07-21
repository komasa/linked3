<?php

declare(strict_types=1);
/**
 * SEOAdapterNone — extracted from SEOAdapterDetector.php during PSR-4 migration.
 *
 * @package Linked3\Classes\SEO\Adapter
 */

namespace Linked3\Classes\SEO\Adapter;

if (!defined('ABSPATH')) exit;

/**
 * Null-object adapter used when no 3rd-party SEO plugin is active.
 * Linked3 emits its own schema / meta in this case.
 */
final class SEOAdapterNone implements SEOAdapter
{
    public function slug(): string
    {
        return 'none';
    }

    public function label(): string {
        return __('Linked3 原生 SEO(无第三方适配器)', 'linked3');
    }

    public function is_active(): bool
    {
        return true; // always — represents the default path.
    }

    public function handles_schema(): bool
    {
        return false;
    }

    public function handles_meta_description(): bool
    {
        return false;
    }

}
