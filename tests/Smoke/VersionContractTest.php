<?php
/**
 * Smoke test: Plugin header & version contract.
 *
 * @package Linked3
 */

namespace Linked3\Tests\Smoke;

use PHPUnit\Framework\TestCase;

class VersionContractTest extends TestCase
{
    public function testPluginHeaderHasVersion2800(): void
    {
        $content = file_get_contents(__DIR__ . '/../../linked3.php');
        // Use regex to handle variable whitespace in header
        $this->assertMatchesRegularExpression('/^\s*\*\s*Version:\s*28\.0\.0/m', $content);
    }

    public function testVersionConstantIs2800(): void
    {
        $content = file_get_contents(__DIR__ . '/../../linked3.php');
        $this->assertStringContainsString("'28.0.0'", $content);
        $this->assertStringContainsString('LINKED3_VERSION', $content);
    }

    public function testComposerJsonVersionIs2800(): void
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../../composer.json'), true);
        $this->assertEquals('28.0.0', $composer['version']);
    }

    public function testRequiresPhp8(): void
    {
        $content = file_get_contents(__DIR__ . '/../../linked3.php');
        $this->assertMatchesRegularExpression('/^\s*\*\s*Requires PHP:\s*8\.0/m', $content);
    }

    public function testPhpGuardAtEightZero(): void
    {
        $content = file_get_contents(__DIR__ . '/../../linked3.php');
        $this->assertStringContainsString("version_compare(PHP_VERSION, '8.0.0'", $content);
    }
}
