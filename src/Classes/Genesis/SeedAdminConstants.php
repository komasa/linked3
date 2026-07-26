<?php

declare(strict_types=1);
/**
 * Seed Admin shared constants.
 *
 * v27.8.3: Extracted from the 4 SeedAdmin* classes (Render/Ajax/Export/Pages)
 * that each duplicated the same 6 constants after the G4.1 split. This Trait
 * is the single source of truth — adding a new constant here makes it
 * available to all 4 classes automatically.
 *
 * Usage:
 *   class SeedAdminRender { use SeedAdminConstants; ... }
 *   class SeedAdminAjax   { use SeedAdminConstants; ... }
 *
 * @package Linked3
 * @subpackage Classes\\Genesis
 * @since      27.8.3
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

trait SeedAdminConstants
{
    /** List page slug — used in add_submenu_page() and URL routing. */
    const PAGE_SLUG_LIST = 'linked3-seed-list';

    /** Edit page slug — ?page=linked3-seed-edit&seed_id=N. */
    const PAGE_SLUG_EDIT = 'linked3-seed-edit';

    /** New-seed page slug — ?page=linked3-seed-new. */
    const PAGE_SLUG_NEW  = 'linked3-seed-new';

    /** WordPress capability required for all Seed Admin actions. */
    const CAPABILITY     = 'manage_options';

    /** Nonce action for general Seed Admin AJAX operations. */
    const NONCE_ACTION        = 'linked3_seed_admin';

    /** Nonce action for trash/delete operations (separate for tighter scoping). */
    const NONCE_ACTION_TRASH  = 'linked3_seed_trash';

    // ── v27.8.3: Seed taxonomy & field maps ──────────────────────────
    // These were referenced as self::$CATEGORIES / self::$TYPES etc. in
    // SeedAdminExport and SeedAdminPages but never defined, causing
    // "Access to undeclared static property" Fatal Errors.
    // Defined as const arrays (PHP 5.6+) for immutability + trait compatibility.

    /** Seed category → human label map. Used in list/edit/export views. */
    const CATEGORIES = [
        'char'    => __('角色', 'linked3'),
        'scene'   => __('场景', 'linked3'),
        'prop'    => __('道具', 'linked3'),
        'style'   => __('风格', 'linked3'),
        'palette' => __('色板', 'linked3'),
        'brand'   => __('品牌', 'linked3'),
        'soul'    => __('灵魂', 'linked3'),
    ];

    /** Seed type → human label map. fixed = locked DNA, variable = evolving. */
    const TYPES = [
        'fixed'    => __('固定', 'linked3'),
        'variable' => __('可变', 'linked3'),
    ];

    /** Visual DNA field key → label map. Used in new-seed wizard + export. */
    const VISUAL_FIELDS = [
        'color_palette'  => __('色彩调色板', 'linked3'),
        'lighting'       => __('光影风格', 'linked3'),
        'composition'    => __('构图法则', 'linked3'),
        'camera_angle'   => __('镜头角度', 'linked3'),
        'texture'        => __('材质纹理', 'linked3'),
        'mood'           => __('情绪氛围', 'linked3'),
    ];

    /** Personality DNA field key → label map. Used in edit + export. */
    const PERSONALITY_FIELDS = [
        'voice'       => __('语调', 'linked3'),
        'tone'        => __('语气', 'linked3'),
        'vocabulary'  => __('词汇偏好', 'linked3'),
        'humor'       => __('幽默感', 'linked3'),
        'formality'   => __('正式程度', 'linked3'),
    ];

    /** Priority group key → label map. Used in new-seed wizard + export. */
    const PRIORITY_GROUPS = [
        'must_have'    => __('必须包含', 'linked3'),
        'should_have'  => __('应当包含', 'linked3'),
        'could_have'   => __('可以包含', 'linked3'),
        'wont_have'    => __('排除项', 'linked3'),
    ];

    /** AI platform key → label map. Used in adapter export. */
    const AI_PLATFORMS = [
        'midjourney' => 'Midjourney',
        'sdxl'       => 'Stable Diffusion XL',
        'flux'       => 'Flux',
        'dalle'      => 'DALL·E 3',
        'comfyui'    => 'ComfyUI',
    ];
}
