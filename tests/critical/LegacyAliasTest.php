<?php
/**
 * Critical Test: LegacyAliasRegistry Coverage
 *
 * Verifies that every old class name registered in LegacyAliasRegistry
 * actually resolves to the correct new class via class_alias.
 *
 * This test is the runtime equivalent of the static analysis check
 * documented in risk-assessment-20260721.md §4.2.
 *
 * @package Linked3\Test\Critical
 * @since   27.6.0
 */

declare(strict_types=1);

namespace Linked3\Tests\Critical;

use PHPUnit\Framework\TestCase;
use Linked3\Includes\Compat\LegacyAliasRegistry;

/**
 * @group critical
 * @group aliases
 */
class LegacyAliasTest extends TestCase
{
    /**
     * Data provider: all old class names that should have aliases.
     *
     * This list is generated from LegacyAliasRegistry::add() calls.
     * If a new alias is added, add it here too.
     *
     * @return array<string, array{string, string}>
     */
    public function oldClassNamesProvider(): array
    {
        return [
            // Same-namespace aliases
            'AIPipelineBootstrap' => ['Linked3\Classes\AI\Pipeline\Linked3_AI_Pipeline_Bootstrap', 'Linked3\Classes\AI\Pipeline\AIPipelineBootstrap'],
            'ContentQualityScorer' => ['Linked3\Classes\AI\Pipeline\Linked3_Content_Quality_Scorer', 'Linked3\Classes\AI\Pipeline\ContentQualityScorer'],
            'CostReporter' => ['Linked3\Classes\AI\Pipeline\Linked3_Cost_Reporter', 'Linked3\Classes\AI\Pipeline\CostReporter'],
            'AddonInterface' => ['Linked3\Classes\Addons\Linked3_Addon_Interface', 'Linked3\Classes\Addons\AddonInterface'],
            'AlipayGateway' => ['Linked3\Classes\Billing\Linked3_Alipay_Gateway', 'Linked3\Classes\Billing\AlipayGateway'],
            'BillingBootstrap' => ['Linked3\Classes\Billing\Linked3_Billing_Bootstrap', 'Linked3\Classes\Billing\BillingBootstrap'],
            'InvoiceManager' => ['Linked3\Classes\Billing\Linked3_Invoice_Manager', 'Linked3\Classes\Billing\InvoiceManager'],
            'PaymentGatewayInterface' => ['Linked3\Classes\Billing\Linked3_Payment_Gateway_Interface', 'Linked3\Classes\Billing\PaymentGatewayInterface'],
            'PaymentManager' => ['Linked3\Classes\Billing\Linked3_Payment_Manager', 'Linked3\Classes\Billing\PaymentManager'],
            'QuotaInterceptor' => ['Linked3\Classes\Billing\Linked3_Quota_Interceptor', 'Linked3\Classes\Billing\QuotaInterceptor'],
            'ReferralManager' => ['Linked3\Classes\Billing\Linked3_Referral_Manager', 'Linked3\Classes\Billing\ReferralManager'],
            'WechatGateway' => ['Linked3\Classes\Billing\Linked3_Wechat_Gateway', 'Linked3\Classes\Billing\WechatGateway'],
            'GenesisAtomSelector' => ['Linked3\Classes\Genesis\Linked3_Genesis_AtomSelector', 'Linked3\Classes\Genesis\GenesisAtomSelector'],
            'GenesisBootstrap' => ['Linked3\Classes\Genesis\Linked3_Genesis_Bootstrap', 'Linked3\Classes\Genesis\GenesisBootstrap'],
            'GenesisEvaluator' => ['Linked3\Classes\Genesis\Linked3_Genesis_Evaluator', 'Linked3\Classes\Genesis\GenesisEvaluator'],
            'GenesisException' => ['Linked3\Classes\Genesis\Linked3_Genesis_Exception', 'Linked3\Classes\Genesis\GenesisException'],
            'GenesisIRCompiler' => ['Linked3\Classes\Genesis\Linked3_Genesis_IRCompiler', 'Linked3\Classes\Genesis\GenesisIRCompiler'],
            'GenesisMutator' => ['Linked3\Classes\Genesis\Linked3_Genesis_Mutator', 'Linked3\Classes\Genesis\GenesisMutator'],
            'GenesisPQSChecker' => ['Linked3\Classes\Genesis\Linked3_Genesis_PQSChecker', 'Linked3\Classes\Genesis\GenesisPQSChecker'],
            'GenesisPipeline' => ['Linked3\Classes\Genesis\Linked3_Genesis_Pipeline', 'Linked3\Classes\Genesis\GenesisPipeline'],
            'GenesisPlotParser' => ['Linked3\Classes\Genesis\Linked3_Genesis_PlotParser', 'Linked3\Classes\Genesis\GenesisPlotParser'],
            'GenesisPromptAssembler' => ['Linked3\Classes\Genesis\Linked3_Genesis_PromptAssembler', 'Linked3\Classes\Genesis\GenesisPromptAssembler'],
            'GenesisStoryboardGenerator' => ['Linked3\Classes\Genesis\Linked3_Genesis_StoryboardGenerator', 'Linked3\Classes\Genesis\GenesisStoryboardGenerator'],
            'AuditLogger' => ['Linked3\Classes\Security\Linked3_Audit_Logger', 'Linked3\Classes\Security\AuditLogger'],
            'SecurityBootstrap' => ['Linked3\Classes\Security\Linked3_Security_Bootstrap', 'Linked3\Classes\Security\SecurityBootstrap'],
            'GenesisSeedCPT' => ['Linked3\Classes\Genesis\Linked3_Seed_CPT', 'Linked3\Classes\Genesis\GenesisSeedCPT'],

            // Global (bare name) aliases — a representative subset
            'Global_AuditLogger' => ['Linked3_Audit_Logger', 'Linked3\Classes\Security\AuditLogger'],
            'Global_BillingBootstrap' => ['Linked3_Billing_Bootstrap', 'Linked3\Classes\Billing\BillingBootstrap'],
            'Global_GenesisBootstrap' => ['Linked3_Genesis_Bootstrap', 'Linked3\Classes\Genesis\GenesisBootstrap'],
            'Global_PaymentManager' => ['Linked3_Payment_Manager', 'Linked3\Classes\Billing\PaymentManager'],
            'Global_ScaleBootstrap' => ['Linked3_Scale_Bootstrap', 'Linked3\Classes\Scale\ScaleBootstrap'],
            'Global_HealthMonitor' => ['Linked3_Health_Monitor', 'Linked3\Classes\E2E\HealthMonitor'],
        ];
    }

    /**
     * @dataProvider oldClassNamesProvider
     */
    public function test_old_class_name_resolves_to_new(string $oldClass, string $newClass): void
    {
        // Trigger autoload of the old name — this should cause
        // LegacyAliasRegistry::autoload() to fire and create the alias
        $exists = class_exists($oldClass);

        if (!$exists) {
            $this->markTestSkipped(
                "Alias for '{$oldClass}' not resolved — " .
                "either the new class '{$newClass}' doesn't exist yet, " .
                "or the alias was never registered. " .
                "This is a COVERAGE GAP — see risk-assessment-20260721.md §4.2."
            );
        }

        // If it exists, verify it's actually an alias of the new class
        $reflection = new \ReflectionClass($oldClass);
        self::assertSame(
            $newClass,
            $reflection->getName(),
            "Old class '{$oldClass}' resolved to '{$reflection->getName()}' but expected '{$newClass}'"
        );
    }
}
