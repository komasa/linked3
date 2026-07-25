<?php
/**
 * Unit test: TabRegistry (PR-02).
 *
 * @package Linked3
 */

namespace Linked3\Tests\Unit;

use PHPUnit\Framework\TestCase;

// Guard: only run if the class exists (requires composer autoload or manual require)
if (!class_exists('\Linked3\Dashboard\Tabs\TabRegistry')) {
    // Try manual require
    $candidate = __DIR__ . '/../../src/Classes/Dashboard/Tabs/TabRegistry.php';
    if (file_exists($candidate)) {
        require_once $candidate;
    }
}

class TabRegistryTest extends TestCase
{
    public function testTabRegistryClassExists(): void
    {
        $candidate = __DIR__ . '/../../src/Classes/Dashboard/Tabs/TabRegistry.php';
        if (!file_exists($candidate)) {
            $this->markTestSkipped('PR-02: TabRegistry not yet merged into base');
        }
        $this->assertTrue(
            class_exists('\Linked3\Dashboard\Tabs\TabRegistry') || interface_exists('\Linked3\Dashboard\Tabs\TabRegistry'),
            'TabRegistry class/interface must exist (PR-02)'
        );
    }

    public function testTabsPhpIsThinTemplate(): void
    {
        $tabsFile = __DIR__ . '/../../admin/views/dashboard/tabs.php';
        if (!file_exists($tabsFile)) {
            $this->markTestSkipped('tabs.php not found');
        }
        $lineCount = count(file($tabsFile));
        $this->assertLessThan(
            250,
            $lineCount,
            "tabs.php should be a thin template (≤250 lines), got {$lineCount}"
        );
    }
}
