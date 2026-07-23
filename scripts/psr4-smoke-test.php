<?php
/**
 * PSR-4 Autoload Smoke Test
 *
 * Verifies that PSR-4 autoload correctly resolves class names to file paths.
 * Uses ReflectionClass::getFileName() to verify the actual loaded file path,
 * not just class_exists() (which class_alias would make pass falsely).
 *
 * Usage: php -f scripts/psr4-smoke-test.php
 * Or via WP-CLI: wp --require=scripts/psr4-smoke-test.php linked3-psr4-smoke
 *
 * @package Linked3
 */

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('linked3-psr4-smoke', function() {
        $results = run_smoke_test();
        WP_CLI::success("PSR-4 smoke test complete: {$results['passed']} passed, {$results['failed']} failed");
        if ($results['failed'] > 0) {
            foreach ($results['failures'] as $f) {
                WP_CLI::warning("FAIL: {$f['class']} → {$f['expected']} (got: {$f['actual']})");
            }
            WP_CLI::halt(1);
        }
    });
    return;
}

// Direct PHP execution
$results = run_smoke_test();
echo "\nPSR-4 Smoke Test Results:\n";
echo "  Passed: {$results['passed']}\n";
echo "  Failed: {$results['failed']}\n";
if ($results['failed'] > 0) {
    echo "\nFailures:\n";
    foreach ($results['failures'] as $f) {
        echo "  FAIL: {$f['class']}\n    Expected: {$f['expected']}\n    Actual:   {$f['actual']}\n";
    }
    exit(1);
}
echo "\nAll checks passed!\n";
exit(0);


function run_smoke_test(): array
{
    $passed = 0;
    $failed = 0;
    $failures = [];

    // Core classes that must be loadable via PSR-4
    $critical_classes = [
        // Entry point
        'Linked3\Includes\Container' => 'src/Includes/Container.php',
        'Linked3\Includes\HookManager' => 'src/Includes/HookManager.php',
        'Linked3\Includes\DependencyLoader' => 'src/Includes/DependencyLoader.php',

        // Billing
        'Linked3\Classes\Billing\StripeGateway' => 'src/Classes/Billing/StripeGateway.php',
        'Linked3\Classes\Billing\PaymentManager' => 'src/Classes/Billing/PaymentManager.php',
        'Linked3\Classes\Billing\QuotaInterceptor' => 'src/Classes/Billing/QuotaInterceptor.php',
        'Linked3\Classes\Billing\BillingBootstrap' => 'src/Classes/Billing/BillingBootstrap.php',

        // Security
        'Linked3\Classes\Security\RateLimiterV2' => 'src/Classes/Security/RateLimiterV2.php',
        'Linked3\Classes\Security\AuditLogger' => 'src/Classes/Security/AuditLogger.php',

        // AI
        'Linked3\Classes\AI\Pipeline\PromptEngine' => 'src/Classes/AI/Pipeline/PromptEngine.php',
        'Linked3\Classes\AI\Pipeline\PromptCache' => 'src/Classes/AI/Pipeline/PromptCache.php',

        // AutoGPT
        'Linked3\Classes\AutoGPT\AutoGPTDependenciesLoader' => 'src/Classes/AutoGPT/AutoGPTDependenciesLoader.php',

        // ContentWriter
        'Linked3\Classes\ContentWriter\ContentWriterDependenciesLoader' => 'src/Classes/ContentWriter/ContentWriterDependenciesLoader.php',

        // SEO
        'Linked3\Classes\SEO\SEODependenciesLoader' => 'src/Classes/SEO/SEODependenciesLoader.php',

        // Genesis
        'Linked3\Classes\Genesis\GenesisBootstrap' => 'src/Classes/Genesis/GenesisBootstrap.php',

        // Scale
        'Linked3\Classes\Scale\ScaleBootstrap' => 'src/Classes/Scale/ScaleBootstrap.php',

        // Core
        'Linked3\Classes\Core\ModelConfig' => 'src/Classes/Core/ModelConfig.php',
        'Linked3\Classes\Core\TokenManager' => 'src/Classes/Core/TokenManager.php',

        // CognitiveOS
        'Linked3\Classes\CognitiveOS\COSEngine' => 'src/Classes/CognitiveOS/COSEngine.php',
    ];

    foreach ($critical_classes as $fqcn => $expected_path) {
        if (!class_exists($fqcn) && !interface_exists($fqcn) && !trait_exists($fqcn)) {
            $failed++;
            $failures[] = [
                'class' => $fqcn,
                'expected' => $expected_path,
                'actual' => 'CLASS NOT FOUND',
            ];
            continue;
        }

        try {
            $ref = new ReflectionClass($fqcn);
            $actual_file = $ref->getFileName();
            $linked3_dir = defined('LINKED3_DIR') ? LINKED3_DIR : dirname(__DIR__) . '/';

            // Normalize paths for comparison
            $actual_relative = str_replace($linked3_dir, '', $actual_file);
            $actual_relative = ltrim($actual_relative, '/');

            if ($actual_relative === $expected_path) {
                $passed++;
            } else {
                $failed++;
                $failures[] = [
                    'class' => $fqcn,
                    'expected' => $expected_path,
                    'actual' => $actual_relative,
                ];
            }
        } catch (Throwable $e) {
            $failed++;
            $failures[] = [
                'class' => $fqcn,
                'expected' => $expected_path,
                'actual' => 'ERROR: ' . $e->getMessage(),
            ];
        }
    }

    return [
        'passed' => $passed,
        'failed' => $failed,
        'failures' => $failures,
    ];
}
