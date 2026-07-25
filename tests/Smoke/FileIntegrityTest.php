<?php
/**
 * Smoke test: Key files exist & are syntactically valid PHP.
 *
 * @package Linked3
 */

namespace Linked3\Tests\Smoke;

use PHPUnit\Framework\TestCase;

class FileIntegrityTest extends TestCase
{
    /**
     * @dataProvider criticalFileProvider
     */
    public function testCriticalFileExists(string $relativePath): void
    {
        $fullPath = __DIR__ . '/../../' . $relativePath;
        $this->assertFileExists($fullPath, "Missing critical file: {$relativePath}");
    }

    public function criticalFileProvider(): array
    {
        return [
            'main plugin'      => ['linked3.php'],
            'uninstall'        => ['uninstall.php'],
            'composer.json'    => ['composer.json'],
            'phpunit.xml'      => ['phpunit.xml'],
            'fetch.js'         => ['admin/js/linked3-fetch.js'],
            'tabs.php'         => ['admin/views/dashboard/tabs.php'],
        ];
    }

    /**
     * @dataProvider phpFileProvider
     */
    public function testPhpFileHasNoSyntaxErrors(string $relativePath): void
    {
        $fullPath = __DIR__ . '/../../' . $relativePath;
        if (!file_exists($fullPath)) {
            $this->markTestSkipped("File not found: {$relativePath}");
        }
        $output = shell_exec("php -l " . escapeshellarg($fullPath) . " 2>&1");
        $this->assertStringContainsString('No syntax errors', $output, $output);
    }

    public function phpFileProvider(): array
    {
        return [
            'linked3.php'  => ['linked3.php'],
            'uninstall.php' => ['uninstall.php'],
        ];
    }
}
