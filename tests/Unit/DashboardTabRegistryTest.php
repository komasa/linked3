<?php
declare(strict_types=1);
/**
 * Unit test: DashboardTabRegistry — PR-02 (plan §3.2)
 *
 * Verifies:
 *   1. All 7 tabs are registered with required keys
 *   2. Legacy redirect map covers all documented old tab slugs
 *   3. Eco/visual redirect maps are correct
 *   4. Command palette has expected entries
 *   5. has() returns true for known tabs, false for unknown
 *
 * Run: php tests/Unit/DashboardTabRegistryTest.php
 *
 * @package Linked3\Tests\Unit
 * @since   27.9.4
 */

// Bootstrapping for standalone execution (no Composer/PHPUnit needed).
// In a real environment, use vendor/bin/phpunit.

$plugin_root = dirname(__DIR__, 2);
require_once $plugin_root . '/src/Classes/Dashboard/Presentation/DashboardTabRegistry.php';

use Linked3\Classes\Dashboard\Presentation\DashboardTabRegistry;

$pass = 0;
$fail = 0;

function assert_true(bool $cond, string $msg): void
{
    global $pass, $fail;
    if ($cond) {
        echo "  ✅ {$msg}\n";
        $pass++;
    } else {
        echo "  ❌ {$msg}\n";
        $fail++;
    }
}

function assert_equals($expected, $actual, string $msg): void
{
    global $pass, $fail;
    if ($expected === $actual) {
        echo "  ✅ {$msg}\n";
        $pass++;
    } else {
        echo "  ❌ {$msg} (expected: " . json_encode($expected) . ", got: " . json_encode($actual) . ")\n";
        $fail++;
    }
}

echo "=== DashboardTabRegistry Test ===\n\n";

$reg = DashboardTabRegistry::instance();

// 1. All 7 tabs registered
echo "Test 1: Tab registration\n";
$tabs = $reg->tabs();
$expected_slugs = ['overview', 'cognitive-os', 'creation', 'distribution', 'automation', 'v18', 'system'];
foreach ($expected_slugs as $slug) {
    assert_true(isset($tabs[$slug]), "Tab '{$slug}' is registered");
}

// Each tab has required keys
foreach ($tabs as $slug => $tab) {
    foreach (['label', 'icon', 'color', 'desc', 'short'] as $key) {
        assert_true(!empty($tab[$key]), "Tab '{$slug}' has '{$key}'");
    }
}

// 2. Legacy redirects
echo "\nTest 2: Legacy redirects\n";
$expected_redirects = [
    'ecosystem'     => ['creation', 'cr_sub', 'ecosystem'],
    'visual'        => ['creation', 'cr_sub', 'visual'],
    'cloud'         => ['creation', 'cr_sub', 'cloud'],
    'style-library' => ['creation', 'cr_sub', 'visual'],
    'publish'       => ['distribution', 'di_sub', 'publish'],
    'distribute'    => ['distribution', 'di_sub', 'distribute'],
    'commerce'      => ['distribution', 'di_sub', 'commerce'],
    'autogpt'       => ['automation', 'au_sub', 'autogpt'],
    'chat'          => ['automation', 'au_sub', 'chat'],
    'api'           => ['system', 'sy_sub', 'api'],
    'seo'           => ['system', 'sy_sub', 'seo'],
    'speech'        => ['system', 'sy_sub', 'speech'],
    'license'       => ['system', 'sy_sub', 'license'],
    'security'      => ['system', 'sy_sub', 'security'],
];
foreach ($expected_redirects as $old_tab => $expected) {
    $actual = $reg->legacy_redirect($old_tab);
    assert_equals($expected, $actual, "Legacy redirect '{$old_tab}' → '{$expected[0]}'");
}

// Unknown old tab returns null
assert_true($reg->legacy_redirect('nonexistent') === null, "Unknown legacy tab returns null");

// 3. Queue redirect
echo "\nTest 3: Queue redirect\n";
assert_true($reg->is_queue_redirect('queue') === true, "'queue' is queue redirect");
assert_true($reg->is_queue_redirect('overview') === false, "'overview' is not queue redirect");

// 4. Eco/Visual redirects
echo "\nTest 4: Eco/Visual redirects\n";
assert_equals('content', $reg->eco_redirect('content'), "Eco redirect 'content'");
assert_equals('keywords', $reg->eco_redirect('keywords'), "Eco redirect 'keywords'");
assert_equals(null, $reg->eco_redirect('overview'), "Eco redirect unknown → null");

assert_equals('charts', $reg->visual_redirect('charts'), "Visual redirect 'charts'");
assert_equals('genesis', $reg->visual_redirect('genesis'), "Visual redirect 'genesis'");
assert_equals(null, $reg->visual_redirect('overview'), "Visual redirect unknown → null");

// 5. has()
echo "\nTest 5: has()\n";
assert_true($reg->has('overview') === true, "has('overview') = true");
assert_true($reg->has('nonexistent') === false, "has('nonexistent') = false");

// 6. Command palette
echo "\nTest 6: Command palette\n";
$palette = $reg->command_palette();
assert_true(is_array($palette) && count($palette) >= 10, "Command palette has ≥10 entries");
assert_true(!empty($palette[0]['label']), "First palette entry has label");
assert_true(!empty($palette[0]['url']), "First palette entry has url");

echo "\n=== Results: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
