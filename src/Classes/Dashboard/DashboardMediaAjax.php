<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;
/**
 * DashboardMediaAjax — G8 extraction (Facade).
 *
 * v2026.07.25: Facade+Trait refactor —
 *   - Diagram methods → DashboardDiagramTrait
 *   - Chart methods → DashboardChartTrait
 *   This class is now a ≤40-line Facade. All public static signatures preserved.
 *
 * @since 27.13.0
 */

require_once __DIR__ . '/Traits/DashboardDiagramTrait.php';
require_once __DIR__ . '/Traits/DashboardChartTrait.php';

class DashboardMediaAjax
{
    use Traits\DashboardDiagramTrait;
    use Traits\DashboardChartTrait;

    // ── Video delegates (implementation in DashboardVideoAjax) ────────
    public static function ajax_video_generate_script() : mixed { return DashboardVideoAjax::ajax_video_generate_script(); }
    public static function ajax_video_outline() : mixed { return DashboardVideoAjax::ajax_video_outline(); }
    public static function ajax_video_segment() : mixed { return DashboardVideoAjax::ajax_video_segment(); }
}
