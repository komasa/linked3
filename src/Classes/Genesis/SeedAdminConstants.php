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
        'char'    => '角色',
        'scene'   => '场景',
        'prop'    => '道具',
        'style'   => '风格',
        'palette' => '色板',
        'brand'   => '品牌',
        'soul'    => '灵魂',
    ];

    /** Seed type → human label map. fixed = locked DNA, variable = evolving. */
    const TYPES = [
        'fixed'    => '固定',
        'variable' => '可变',
    ];

    /** Visual DNA field key → label map. Used in new-seed wizard + export. */
    const VISUAL_FIELDS = [
        'color_palette'  => '色彩调色板',
        'lighting'       => '光影风格',
        'composition'    => '构图法则',
        'camera_angle'   => '镜头角度',
        'texture'        => '材质纹理',
        'mood'           => '情绪氛围',
    ];

    /** Personality DNA field key → label map. Used in edit + export. */
    const PERSONALITY_FIELDS = [
        'voice'       => '语调',
        'tone'        => '语气',
        'vocabulary'  => '词汇偏好',
        'humor'       => '幽默感',
        'formality'   => '正式程度',
    ];

    /** Priority group key → label map. Used in new-seed wizard + export. */
    const PRIORITY_GROUPS = [
        'must_have'    => '必须包含',
        'should_have'  => '应当包含',
        'could_have'   => '可以包含',
        'wont_have'    => '排除项',
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
