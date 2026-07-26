<?php

declare(strict_types=1);
/**
 * Linked3 Ecosystem AJAX Handler v10.7.0 — 写作生态统一API (Facade)
 *
 * 注册AJAX endpoint (17个):
 *   wp_ajax_linked3_eco_synergy / _keywords / _content / _template_save / _image_save
 *   wp_ajax_linked3_eco_hot_collect / _keywords_save / _tail_used_save
 *   wp_ajax_linked3_eco_longform_outline / _longform_section / _csv_batch
 *   wp_ajax_linked3_eco_cron_enable / _cron_disable / _save_draft
 *   wp_ajax_linked3_save_image_api / _eco_generate_images
 *
 * v2026.07.25: Facade+Trait refactor (模式B HookLedger + 模式C Facade+Trait)
 *   - Hook bindings → EcosystemHookLedger trait (single source of truth)
 *   - Synergy/content/template/draft → EcosystemSynergyTrait
 *   - Keyword/tail/hot → EcosystemKeywordTrait
 *   - Cron/image API/delegates → EcosystemCronTrait
 *   This class is now a ≤60-line Facade. All public static method signatures
 *   are preserved by the traits — zero downstream breakage.
 *
 * @package Linked3\Content
 * @version 10.7.2
 */

namespace Linked3\Classes\Content;

use Linked3\Includes\Log\Logger;

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/EcosystemHookLedger.php';
require_once __DIR__ . '/Traits/EcosystemSynergyTrait.php';
require_once __DIR__ . '/Traits/EcosystemKeywordTrait.php';
require_once __DIR__ . '/Traits/EcosystemCronTrait.php';

class EcosystemAjax
{
    use EcosystemHookLedger;
    use EcosystemSynergyTrait;
    use EcosystemKeywordTrait;
    use EcosystemCronTrait;

    // ================================================================
    // Backward-compat delegates — keep public API stable
    // ================================================================

    /**
     * Public AI dispatch entry point — delegates to EcosystemImageService.
     * Kept for backward compat: external code may call EcosystemAjax::call_ai_internal().
     */
    public static function call_ai_internal(string $prompt, int $max_tokens = 2000): string {
        return EcosystemImageService::call_ai_internal($prompt, $max_tokens);
    }
}
