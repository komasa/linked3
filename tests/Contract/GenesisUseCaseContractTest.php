<?php
declare(strict_types=1);
/**
 * Contract test: GenesisUseCase — PR-03 (plan §3.3)
 *
 * Verifies:
 *   1. Empty script → validation error, generator NOT called
 *   2. Invalid platform → validation error
 *   3. Valid request → generator called, result mapped correctly
 *   4. Generator throws → caught, result has error message
 *   5. Signature matches GenesisProcessor::genesisGenerateMultiInternal
 *
 * Uses a mock generator (no real AI calls).
 *
 * Run: php tests/Contract/GenesisUseCaseContractTest.php
 *
 * @package Linked3\Tests\Contract
 * @since   27.9.4
 */

$plugin_root = dirname(__DIR__, 2);

// Stub WordPress functions for standalone execution
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($v) { return is_string($v) ? trim($v) : ''; }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($v) { return is_string($v) ? trim($v) : ''; }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
}
if (!function_exists('__')) {
    function __($t, $d = '') { return $t; }
}
if (!function_exists('esc_html')) {
    function esc_html($v) { return $v; }
}
if (!defined('ABSPATH')) {
    define('ABSPATH', true);
}

require_once $plugin_root . '/src/Classes/Genesis/Application/GenesisUseCase.php';

use Linked3\Classes\Genesis\Application\{
    GenesisUseCase,
    GenesisRequest,
    GenesisResult,
    GenesisGeneratorInterface,
};

$pass = 0;
$fail = 0;

function assert_true(bool $cond, string $msg): void {
    global $pass, $fail;
    if ($cond) { echo "  ✅ {$msg}\n"; $pass++; }
    else { echo "  ❌ {$msg}\n"; $fail++; }
}

function assert_equals($expected, $actual, string $msg): void {
    global $pass, $fail;
    if ($expected === $actual) { echo "  ✅ {$msg}\n"; $pass++; }
    else { echo "  ❌ {$msg} (expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")\n"; $fail++; }
}

/**
 * Mock generator — records calls, returns configurable results.
 */
final class MockGenesisGenerator implements GenesisGeneratorInterface
{
    public array $calls = [];
    public ?array $return_value = null;
    public ?\Throwable $throw = null;

    public function generate(
        string $script,
        string $styleId,
        string $platform,
        string $panelCountRaw,
        ?callable $progressCb = null,
        array $extraOptions = [],
    ): array {
        $this->calls[] = compact('script', 'styleId', 'platform', 'panelCountRaw', 'extraOptions');
        if ($this->throw) { throw $this->throw; }
        return $this->return_value ?? ['success' => true, 'panels' => [['id' => 'P1']]];
    }
}

echo "=== GenesisUseCase Contract Tests ===\n\n";

// Test 1: Empty script → validation error
echo "Test 1: Empty script validation\n";
$mock = new MockGenesisGenerator();
$useCase = GenesisUseCase::withGenerator($mock);
$req = new GenesisRequest(script: '', styleId: 's1', platform: 'midjourney', panelCountRaw: '4');
$result = $useCase->execute($req);
assert_true(!$result->success, "Empty script → failure");
assert_true(!empty($result->message), "Has error message");
assert_equals(0, count($mock->calls), "Generator NOT called for invalid input");

// Test 2: Invalid platform
echo "\nTest 2: Invalid platform validation\n";
$req = new GenesisRequest(script: 'test', styleId: 's1', platform: 'invalid_platform', panelCountRaw: '4');
$result = $useCase->execute($req);
assert_true(!$result->success, "Invalid platform → failure");
assert_equals(0, count($mock->calls), "Generator NOT called");

// Test 3: Valid request → success
echo "\nTest 3: Valid request\n";
$mock->return_value = ['success' => true, 'panels' => [['id' => 'P1'], ['id' => 'P2']], 'message' => 'ok'];
$req = new GenesisRequest(script: 'A story', styleId: 'manga', platform: 'sdxl', panelCountRaw: '4');
$result = $useCase->execute($req);
assert_true($result->success, "Valid request → success");
assert_equals(2, count($result->panels), "Has 2 panels");
assert_equals('ok', $result->message, "Message preserved");
assert_equals(1, count($mock->calls), "Generator called exactly once");
assert_equals('A story', $mock->calls[0]['script'], "Script passed through");
assert_equals('sdxl', $mock->calls[0]['platform'], "Platform passed through");

// Test 4: Generator throws → caught
echo "\nTest 4: Generator exception handling\n";
$mock = new MockGenesisGenerator();
$mock->throw = new \RuntimeException('AI timeout');
$useCase = GenesisUseCase::withGenerator($mock);
$req = new GenesisRequest(script: 'test', styleId: 's1', platform: 'midjourney', panelCountRaw: '4');
$result = $useCase->execute($req);
assert_true(!$result->success, "Exception → failure");
assert_equals('AI timeout', $result->message, "Exception message captured");
assert_true($result->meta['exception'] === 'RuntimeException', "Exception class in meta");

// Test 5: Signature compatibility
echo "\nTest 5: Signature compatibility\n";
// The GenesisGeneratorInterface::generate signature MUST match
// GenesisProcessor::genesisGenerateMultiInternal
$interface_method = new ReflectionMethod(GenesisGeneratorInterface::class, 'generate');
$params = $interface_method->getParameters();
assert_equals(6, count($params), "Interface method has 6 params");
assert_equals('script', $params[0]->getName(), "First param is 'script'");
assert_equals('styleId', $params[1]->getName(), "Second param is 'styleId'");
assert_equals('platform', $params[2]->getName(), "Third param is 'platform'");
assert_equals('panelCountRaw', $params[3]->getName(), "Fourth param is 'panelCountRaw'");
assert_true($params[4]->allowsNull(), "progressCb is nullable");

// Test 6: fromArray factory
echo "\nTest 6: fromArray factory\n";
$req = GenesisRequest::fromArray([
    'script'       => '  test script  ',
    'style_id'     => ' manga ',
    'platform'     => 'midjourney',
    'panel_count'  => '4',
]);
assert_equals('test script', $req->script, "Script sanitized (trimmed)");
assert_equals('manga', $req->styleId, "Style ID sanitized (trimmed)");
assert_equals('midjourney', $req->platform, "Platform preserved");

echo "\n=== Results: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
