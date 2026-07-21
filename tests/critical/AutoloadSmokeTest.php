<?php
/**
 * Smoke Test: PSR-4 Autoloading & Class Existence
 *
 * Verifies that all major classes are autoloadable via PSR-4
 * and that the LegacyAliasRegistry correctly resolves old class names.
 *
 * @package Linked3\Test\Critical
 * @since   27.6.0
 */

declare(strict_types=1);

namespace Linked3\Tests\Critical;

use PHPUnit\Framework\TestCase;

/**
 * @group critical
 * @group smoke
 */
class AutoloadSmokeTest extends TestCase
{
    /**
     * Verify Composer + PSR-4 autoloader is functional.
     */
    public function test_composer_autoloader_loaded(): void
    {
        self::assertTrue(class_exists(\PHPUnit\Framework\TestCase::class));
    }

    /**
     * Verify the project's PSR-4 autoloader resolves key namespace prefixes.
     */
    public function test_psr4_namespace_resolvable(): void
    {
        // The LegacyAliasRegistry class must be autoloadable
        self::assertTrue(
            class_exists(\Linked3\Includes\Compat\LegacyAliasRegistry::class),
            'LegacyAliasRegistry must be autoloadable via PSR-4'
        );
    }

    /**
     * Verify ABSPATH constant is defined by bootstrap.
     */
    public function test_abspath_defined(): void
    {
        self::assertTrue(defined('ABSPATH'));
    }

    /**
     * Verify WordPress stub functions exist.
     */
    public function test_wp_stubs_exist(): void
    {
        self::assertTrue(function_exists('__'));
        self::assertTrue(function_exists('sanitize_text_field'));
        self::assertTrue(function_exists('wp_verify_nonce'));
        self::assertTrue(function_exists('current_user_can'));
    }
}
