<?php

declare(strict_types=1);
/**
 * GenesisBootstrap — extracted from GenesisAtomIndex.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Genesis
 */

namespace Linked3\Classes\Genesis;

use Linked3\Includes\EventBus;

if (!defined('ABSPATH')) exit;

class GenesisBootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        if (!function_exists('linked3_container')) {
            return;
        }

        $container = linked3_container();
        $container->set('genesis.atom_index', fn() => GenesisAtomIndex::instance());
        $container->set('genesis.plot_parser', fn() => new GenesisPlotParser());
        $container->set('genesis.atom_selector', fn() => new GenesisAtomSelector());
        $container->set('genesis.prompt_assembler', fn() => new GenesisPromptAssembler());
        $container->set('genesis.pqs_checker', fn() => new GenesisPQSChecker());
        $container->set('genesis.storyboard', fn() => new GenesisStoryboardGenerator());

        if (function_exists('linked3_dispatch')) {
            EventBus::dispatch('linked3.genesis.boot', ['version' => LINKED3_VERSION]);
        }
    }
}

// NOTE: The three helper functions linked3_genesis_detect_content_type(),
// linked3_genesis_detect_characters(), and linked3_genesis_get_character_keywords()
// were previously duplicated here AND in GenesisAtomIndex.php.
//
// BUG: Both files use `namespace Linked3\Classes\Genesis;` — so the `if (!function_exists('bare_name'))`
// guard checks the GLOBAL scope, but the `function bare_name()` declaration creates
// `Linked3\Classes\Genesis\bare_name` in the NAMESPACE scope. The guard NEVER matches
// the namespaced function, so both files declare the function → "Cannot redeclare"
// fatal error.
//
// FIX v27.6.9: Removed the duplicate declarations from this file.
// The canonical definitions live in GenesisAtomIndex.php (lines 68–121).
// If you need these functions from outside the namespace, call them as:
//   \Linked3\Classes\Genesis\linked3_genesis_detect_content_type($text)
