<?php

declare(strict_types=1);
/**
 * Linked3 Central Legacy Alias Registry
 * ======================================
 *
 * Provides backward compatibility for code that still references
 * old Linked3_* class names after the PSR-4 migration.
 *
 * This file registers three types of aliases:
 * 1. Same-namespace: Linked3\Classes\X\Linked3_Old → Linked3\Classes\X\NewName
 * 2. Cross-namespace: Linked3\Classes\Dashboard\Linked3_XXX → Linked3\Classes\Genesis\NewName
 * 3. Global (bare name): Linked3_XXX → Linked3\Classes\X\NewName
 *
 * Uses lazy resolution via spl_autoload_register to avoid
 * class_exists false-positives — aliases are only created on demand.
 *
 * @package Linked3\Includes\Compat
 * @since 27.0.0
 */

namespace Linked3\Includes\Compat;

if (!defined('ABSPATH')) {
    exit;
}

if (defined('LINKED3_LEGACY_ALIAS_LOADED')) {
    return;
}
define('LINKED3_LEGACY_ALIAS_LOADED', true);

/**
 * Central alias registry with lazy autoloading.
 *
 * Aliases are registered at file-load time but only resolved
 * when the old class name is actually referenced. This avoids
 * polluting the global class table with unused aliases.
 */
class LegacyAliasRegistry
{
    /** @var array<string,string> old_fqcn → new_fqcn */
    private static $pending = [];

    /** @var bool */
    private static $registered = false;

    /**
     * Queue an alias for lazy resolution.
     *
     * @param string $old_class Full FQCN of old class name
     * @param string $new_class Full FQCN of new class name
     * @return void
     */
    public static function add(string $old_class, string $new_class): void
    {
        self::$pending[$old_class] = $new_class;
        if (!self::$registered) {
            spl_autoload_register([self::class, 'autoload']);
            self::$registered = true;
        }
    }

    /**
     * spl_autoload callback — resolves a pending alias on first access.
     *
     * @param string $class The class name being autoloaded
     * @return void
     */
    public static function autoload(string $class): void
    {
        if (!isset(self::$pending[$class])) {
            return;
        }
        $new_class = self::$pending[$class];
        // Try to load the new class via the main autoloader
        if (!class_exists($new_class, true) && !interface_exists($new_class, true) && !trait_exists($new_class, true)) {
            return;
        }
        if (!class_exists($class, false) && !interface_exists($class, false) && !trait_exists($class, false)) {
            @trigger_error(
                sprintf(
                    'Linked3: %s is deprecated since v27.0.0. Use %s instead.',
                    $class,
                    $new_class
                ),
                E_USER_DEPRECATED
            );
            class_alias($new_class, $class);
        }
    }
}

// ─── Same-namespace aliases ─────────────────────────────────────────────
// Old class name within the same namespace → new class name
//
LegacyAliasRegistry::add('Linked3\Classes\AI\Pipeline\Linked3_AI_Pipeline_Bootstrap', 'Linked3\Classes\AI\Pipeline\AIPipelineBootstrap');  // Linked3_AI_Pipeline_Bootstrap → AIPipelineBootstrap
LegacyAliasRegistry::add('Linked3\Classes\AI\Pipeline\Linked3_Content_Quality_Scorer', 'Linked3\Classes\AI\Pipeline\ContentQualityScorer');  // Linked3_Content_Quality_Scorer → ContentQualityScorer
LegacyAliasRegistry::add('Linked3\Classes\AI\Pipeline\Linked3_Cost_Reporter', 'Linked3\Classes\AI\Pipeline\CostReporter');  // Linked3_Cost_Reporter → CostReporter
LegacyAliasRegistry::add('Linked3\Classes\Addons\Linked3_Addon_Interface', 'Linked3\Classes\Addons\AddonInterface');  // Linked3_Addon_Interface → AddonInterface
LegacyAliasRegistry::add('Linked3\Classes\Billing\Linked3_Alipay_Gateway', 'Linked3\Classes\Billing\AlipayGateway');  // Linked3_Alipay_Gateway → AlipayGateway
LegacyAliasRegistry::add('Linked3\Classes\Billing\Linked3_Billing_Bootstrap', 'Linked3\Classes\Billing\BillingBootstrap');  // Linked3_Billing_Bootstrap → BillingBootstrap
LegacyAliasRegistry::add('Linked3\Classes\Billing\Linked3_Invoice_Manager', 'Linked3\Classes\Billing\InvoiceManager');  // Linked3_Invoice_Manager → InvoiceManager
LegacyAliasRegistry::add('Linked3\Classes\Billing\Linked3_Payment_Gateway_Interface', 'Linked3\Classes\Billing\PaymentGatewayInterface');  // Linked3_Payment_Gateway_Interface → PaymentGatewayInterface
LegacyAliasRegistry::add('Linked3\Classes\Billing\Linked3_Payment_Manager', 'Linked3\Classes\Billing\PaymentManager');  // Linked3_Payment_Manager → PaymentManager
LegacyAliasRegistry::add('Linked3\Classes\Billing\Linked3_Quota_Interceptor', 'Linked3\Classes\Billing\QuotaInterceptor');  // Linked3_Quota_Interceptor → QuotaInterceptor
LegacyAliasRegistry::add('Linked3\Classes\Billing\Linked3_Referral_Manager', 'Linked3\Classes\Billing\ReferralManager');  // Linked3_Referral_Manager → ReferralManager
LegacyAliasRegistry::add('Linked3\Classes\Billing\Linked3_Wechat_Gateway', 'Linked3\Classes\Billing\WechatGateway');  // Linked3_Wechat_Gateway → WechatGateway
LegacyAliasRegistry::add('Linked3\Classes\BookFactory\Linked3_Book_Cost_Tracker_Interface', 'Linked3\Classes\BookFactory\BookCostTrackerInterface');  // Linked3_Book_Cost_Tracker_Interface → BookCostTrackerInterface
LegacyAliasRegistry::add('Linked3\Classes\BookFactory\Linked3_Book_Prompt_Provider_Interface', 'Linked3\Classes\BookFactory\BookPromptProviderInterface');  // Linked3_Book_Prompt_Provider_Interface → BookPromptProviderInterface
LegacyAliasRegistry::add('Linked3\Classes\BookFactory\Linked3_Book_State_Repository_Interface', 'Linked3\Classes\BookFactory\BookStateRepositoryInterface');  // Linked3_Book_State_Repository_Interface → BookStateRepositoryInterface
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_3D_AR_Subsystem', 'Linked3\Classes\Diagram\Diagram3DARSubsystem');  // Linked3_Diagram_3D_AR_Subsystem → Diagram3DARSubsystem
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_4Layer_Anchor', 'Linked3\Classes\Diagram\Diagram4LayerAnchor');  // Linked3_Diagram_4Layer_Anchor → Diagram4LayerAnchor
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_8System_CrossRef', 'Linked3\Classes\Diagram\Diagram8SystemCrossRef');  // Linked3_Diagram_8System_CrossRef → Diagram8SystemCrossRef
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_BaseReuse_Flywheel', 'Linked3\Classes\Diagram\DiagramBaseReuseFlywheel');  // Linked3_Diagram_BaseReuse_Flywheel → DiagramBaseReuseFlywheel
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Bootstrap', 'Linked3\Classes\Diagram\DiagramBootstrap');  // Linked3_Diagram_Bootstrap → DiagramBootstrap
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_BrandSystem_5D', 'Linked3\Classes\Diagram\DiagramBrandSystem5D');  // Linked3_Diagram_BrandSystem_5D → DiagramBrandSystem5D
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Cognitive_6Level', 'Linked3\Classes\Diagram\DiagramCognitive6Level');  // Linked3_Diagram_Cognitive_6Level → DiagramCognitive6Level
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Color_System', 'Linked3\Classes\Diagram\DiagramColorSystem');  // Linked3_Diagram_Color_System → DiagramColorSystem
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Commercial_Hardening', 'Linked3\Classes\Diagram\DiagramCommercialHardening');  // Linked3_Diagram_Commercial_Hardening → DiagramCommercialHardening
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Complexity_Reduction', 'Linked3\Classes\Diagram\DiagramComplexityReduction');  // Linked3_Diagram_Complexity_Reduction → DiagramComplexityReduction
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Density_4Level', 'Linked3\Classes\Diagram\DiagramDensity4Level');  // Linked3_Diagram_Density_4Level → DiagramDensity4Level
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_E2E_TestSuite', 'Linked3\Classes\Diagram\DiagramE2ETestSuite');  // Linked3_Diagram_E2E_TestSuite → DiagramE2ETestSuite
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Endpoint_DecisionTree', 'Linked3\Classes\Diagram\DiagramEndpointDecisionTree');  // Linked3_Diagram_Endpoint_DecisionTree → DiagramEndpointDecisionTree
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Failure_Diagnosis', 'Linked3\Classes\Diagram\DiagramFailureDiagnosis');  // Linked3_Diagram_Failure_Diagnosis → DiagramFailureDiagnosis
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Failure_Handbook', 'Linked3\Classes\Diagram\DiagramFailureHandbook');  // Linked3_Diagram_Failure_Handbook → DiagramFailureHandbook
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Followup_Registry', 'Linked3\Classes\Diagram\DiagramFollowupRegistry');  // Linked3_Diagram_Followup_Registry → DiagramFollowupRegistry
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_FooterFollowup_Matrix', 'Linked3\Classes\Diagram\DiagramFooterFollowupMatrix');  // Linked3_Diagram_FooterFollowup_Matrix → DiagramFooterFollowupMatrix
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Footer_Registry', 'Linked3\Classes\Diagram\DiagramFooterRegistry');  // Linked3_Diagram_Footer_Registry → DiagramFooterRegistry
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Keyword_Refiner', 'Linked3\Classes\Diagram\DiagramKeywordRefiner');  // Linked3_Diagram_Keyword_Refiner → DiagramKeywordRefiner
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Layout_Engine', 'Linked3\Classes\Diagram\DiagramLayoutEngine');  // Linked3_Diagram_Layout_Engine → DiagramLayoutEngine
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Loop_Character_Integration', 'Linked3\Classes\Diagram\DiagramLoopCharacterIntegration');  // Linked3_Diagram_Loop_Character_Integration → DiagramLoopCharacterIntegration
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Loop_Iterator', 'Linked3\Classes\Diagram\DiagramLoopIterator');  // Linked3_Diagram_Loop_Iterator → DiagramLoopIterator
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_ProductSeed_Manager', 'Linked3\Classes\Diagram\DiagramProductSeedManager');  // Linked3_Diagram_ProductSeed_Manager → DiagramProductSeedManager
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Production_Bootstrap', 'Linked3\Classes\Diagram\DiagramProductionBootstrap');  // Linked3_Diagram_Production_Bootstrap → DiagramProductionBootstrap
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Prompt_Compiler', 'Linked3\Classes\Diagram\DiagramPromptCompiler');  // Linked3_Diagram_Prompt_Compiler → DiagramPromptCompiler
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Prompt_Compressor', 'Linked3\Classes\Diagram\DiagramPromptCompressor');  // Linked3_Diagram_Prompt_Compressor → DiagramPromptCompressor
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Relationship_Encoder', 'Linked3\Classes\Diagram\DiagramRelationshipEncoder');  // Linked3_Diagram_Relationship_Encoder → DiagramRelationshipEncoder
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Script_Layer', 'Linked3\Classes\Diagram\DiagramScriptLayer');  // Linked3_Diagram_Script_Layer → DiagramScriptLayer
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Seed_Check', 'Linked3\Classes\Diagram\DiagramSeedCheck');  // Linked3_Diagram_Seed_Check → DiagramSeedCheck
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Seed_Compiler', 'Linked3\Classes\Diagram\DiagramSeedCompiler');  // Linked3_Diagram_Seed_Compiler → DiagramSeedCompiler
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Seed_Lock', 'Linked3\Classes\Diagram\DiagramSeedLock');  // Linked3_Diagram_Seed_Lock → DiagramSeedLock
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Seed_Reference', 'Linked3\Classes\Diagram\DiagramSeedReference');  // Linked3_Diagram_Seed_Reference → DiagramSeedReference
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Selection_DecisionTree', 'Linked3\Classes\Diagram\DiagramSelectionDecisionTree');  // Linked3_Diagram_Selection_DecisionTree → DiagramSelectionDecisionTree
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_SeriesDNA_4Lock', 'Linked3\Classes\Diagram\DiagramSeriesDNA4Lock');  // Linked3_Diagram_SeriesDNA_4Lock → DiagramSeriesDNA4Lock
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_TextEmbed_Validator', 'Linked3\Classes\Diagram\DiagramTextEmbedValidator');  // Linked3_Diagram_TextEmbed_Validator → DiagramTextEmbedValidator
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Validation_13Dim', 'Linked3\Classes\Diagram\DiagramValidation13Dim');  // Linked3_Diagram_Validation_13Dim → DiagramValidation13Dim
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Validation_Layer', 'Linked3\Classes\Diagram\DiagramValidationLayer');  // Linked3_Diagram_Validation_Layer → DiagramValidationLayer
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_VisualScript_Transform', 'Linked3\Classes\Diagram\DiagramVisualScriptTransform');  // Linked3_Diagram_VisualScript_Transform → DiagramVisualScriptTransform
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Diagram_Visual_Frequency', 'Linked3\Classes\Diagram\DiagramVisualFrequency');  // Linked3_Diagram_Visual_Frequency → DiagramVisualFrequency
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_Auto_Rollback', 'Linked3\Classes\E2E\AutoRollback');  // Linked3_Auto_Rollback → AutoRollback
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_Final_Bootstrap', 'Linked3\Classes\E2E\FinalBootstrap');  // Linked3_Final_Bootstrap → FinalBootstrap
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_Health_Monitor', 'Linked3\Classes\E2E\HealthMonitor');  // Linked3_Health_Monitor → HealthMonitor
LegacyAliasRegistry::add('Linked3\Classes\Genesis\Linked3_Genesis_AtomSelector', 'Linked3\Classes\Genesis\GenesisAtomSelector');  // Linked3_Genesis_AtomSelector → GenesisAtomSelector
LegacyAliasRegistry::add('Linked3\Classes\Genesis\Linked3_Genesis_Bootstrap', 'Linked3\Classes\Genesis\GenesisBootstrap');  // Linked3_Genesis_Bootstrap → GenesisBootstrap
LegacyAliasRegistry::add('Linked3\Classes\Genesis\Linked3_Genesis_Evaluator', 'Linked3\Classes\Genesis\GenesisEvaluator');  // Linked3_Genesis_Evaluator → GenesisEvaluator
LegacyAliasRegistry::add('Linked3\Classes\Genesis\Linked3_Genesis_Exception', 'Linked3\Classes\Genesis\GenesisException');  // Linked3_Genesis_Exception → GenesisException
LegacyAliasRegistry::add('Linked3\Classes\Genesis\Linked3_Genesis_IRCompiler', 'Linked3\Classes\Genesis\GenesisIRCompiler');  // Linked3_Genesis_IRCompiler → GenesisIRCompiler
LegacyAliasRegistry::add('Linked3\Classes\Genesis\Linked3_Genesis_Mutator', 'Linked3\Classes\Genesis\GenesisMutator');  // Linked3_Genesis_Mutator → GenesisMutator
LegacyAliasRegistry::add('Linked3\Classes\Genesis\Linked3_Genesis_PQSChecker', 'Linked3\Classes\Genesis\GenesisPQSChecker');  // Linked3_Genesis_PQSChecker → GenesisPQSChecker
LegacyAliasRegistry::add('Linked3\Classes\Genesis\Linked3_Genesis_Pipeline', 'Linked3\Classes\Genesis\GenesisPipeline');  // Linked3_Genesis_Pipeline → GenesisPipeline
LegacyAliasRegistry::add('Linked3\Classes\Genesis\Linked3_Genesis_PlotParser', 'Linked3\Classes\Genesis\GenesisPlotParser');  // Linked3_Genesis_PlotParser → GenesisPlotParser
LegacyAliasRegistry::add('Linked3\Classes\Genesis\Linked3_Genesis_PromptAssembler', 'Linked3\Classes\Genesis\GenesisPromptAssembler');  // Linked3_Genesis_PromptAssembler → GenesisPromptAssembler
LegacyAliasRegistry::add('Linked3\Classes\Genesis\Linked3_Genesis_StoryboardGenerator', 'Linked3\Classes\Genesis\GenesisStoryboardGenerator');  // Linked3_Genesis_StoryboardGenerator → GenesisStoryboardGenerator
LegacyAliasRegistry::add('Linked3\Classes\Scale\Linked3_Batch_Engine', 'Linked3\Classes\Scale\BatchEngine');  // Linked3_Batch_Engine → BatchEngine
LegacyAliasRegistry::add('Linked3\Classes\Scale\Linked3_Batch_Generate_Handler', 'Linked3\Classes\Scale\BatchGenerateHandler');  // Linked3_Batch_Generate_Handler → BatchGenerateHandler
LegacyAliasRegistry::add('Linked3\Classes\Scale\Linked3_MultiSite_Publisher', 'Linked3\Classes\Scale\MultiSitePublisher');  // Linked3_MultiSite_Publisher → MultiSitePublisher
LegacyAliasRegistry::add('Linked3\Classes\Scale\Linked3_Performance_Cache', 'Linked3\Classes\Scale\PerformanceCache');  // Linked3_Performance_Cache → PerformanceCache
LegacyAliasRegistry::add('Linked3\Classes\Scale\Linked3_Scale_Bootstrap', 'Linked3\Classes\Scale\ScaleBootstrap');  // Linked3_Scale_Bootstrap → ScaleBootstrap
LegacyAliasRegistry::add('Linked3\Classes\Scale\Linked3_i18n_Manager', 'Linked3\Classes\Scale\I18nManager');  // Linked3_i18n_Manager → I18nManager
LegacyAliasRegistry::add('Linked3\Classes\Security\Linked3_Audit_Logger', 'Linked3\Classes\Security\AuditLogger');  // Linked3_Audit_Logger → AuditLogger
LegacyAliasRegistry::add('Linked3\Classes\Security\Linked3_Security_Bootstrap', 'Linked3\Classes\Security\SecurityBootstrap');  // Linked3_Security_Bootstrap → SecurityBootstrap
LegacyAliasRegistry::add('Linked3\Classes\Addons\Linked3_IP_Anonymization_Addon', 'Linked3\Classes\Addons\IPAnonymizationAddon');  // Linked3_IP_Anonymization_Addon → IPAnonymizationAddon
LegacyAliasRegistry::add('Linked3\Classes\Addons\Linked3_Consent_Compliance_Addon', 'Linked3\Classes\Addons\ConsentComplianceAddon');  // Linked3_Consent_Compliance_Addon → ConsentComplianceAddon
LegacyAliasRegistry::add('Linked3\Classes\Genesis\Linked3_Seed_CPT', 'Linked3\Classes\Genesis\GenesisSeedCPT');  // Linked3_Seed_CPT → GenesisSeedCPT

// ─── Cross-namespace aliases ────────────────────────────────────────────
// Old class name referenced from a different namespace → actual new class
// (e.g. Dashboard\Linked3_Genesis_PlotParser → Genesis\GenesisPlotParser)
//
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_Content_Quality_Scorer', 'Linked3\Classes\AI\Pipeline\ContentQualityScorer');  // Linked3\Classes\E2E\Linked3_Content_Quality_Scorer → Linked3\Classes\AI\Pipeline\ContentQualityScorer
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_Cost_Reporter', 'Linked3\Classes\AI\Pipeline\CostReporter');  // Linked3\Classes\E2E\Linked3_Cost_Reporter → Linked3\Classes\AI\Pipeline\CostReporter
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_Audit_Logger', 'Linked3\Classes\Security\AuditLogger');  // Linked3\Classes\E2E\Linked3_Audit_Logger → Linked3\Classes\Security\AuditLogger
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_Payment_Manager', 'Linked3\Classes\Billing\PaymentManager');  // Linked3\Classes\E2E\Linked3_Payment_Manager → Linked3\Classes\Billing\PaymentManager
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_Quota_Interceptor', 'Linked3\Classes\Billing\QuotaInterceptor');  // Linked3\Classes\E2E\Linked3_Quota_Interceptor → Linked3\Classes\Billing\QuotaInterceptor
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_Invoice_Manager', 'Linked3\Classes\Billing\InvoiceManager');  // Linked3\Classes\E2E\Linked3_Invoice_Manager → Linked3\Classes\Billing\InvoiceManager
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_Referral_Manager', 'Linked3\Classes\Billing\ReferralManager');  // Linked3\Classes\E2E\Linked3_Referral_Manager → Linked3\Classes\Billing\ReferralManager
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_i18n_Manager', 'Linked3\Classes\Scale\I18nManager');  // Linked3\Classes\E2E\Linked3_i18n_Manager → Linked3\Classes\Scale\I18nManager
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_MultiSite_Publisher', 'Linked3\Classes\Scale\MultiSitePublisher');  // Linked3\Classes\E2E\Linked3_MultiSite_Publisher → Linked3\Classes\Scale\MultiSitePublisher
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_Batch_Engine', 'Linked3\Classes\Scale\BatchEngine');  // Linked3\Classes\E2E\Linked3_Batch_Engine → Linked3\Classes\Scale\BatchEngine
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_Performance_Cache', 'Linked3\Classes\Scale\PerformanceCache');  // Linked3\Classes\E2E\Linked3_Performance_Cache → Linked3\Classes\Scale\PerformanceCache
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_AI_Pipeline_Bootstrap', 'Linked3\Classes\AI\Pipeline\AIPipelineBootstrap');  // Linked3\Classes\E2E\Linked3_AI_Pipeline_Bootstrap → Linked3\Classes\AI\Pipeline\AIPipelineBootstrap
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_Security_Bootstrap', 'Linked3\Classes\Security\SecurityBootstrap');  // Linked3\Classes\E2E\Linked3_Security_Bootstrap → Linked3\Classes\Security\SecurityBootstrap
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_Billing_Bootstrap', 'Linked3\Classes\Billing\BillingBootstrap');  // Linked3\Classes\E2E\Linked3_Billing_Bootstrap → Linked3\Classes\Billing\BillingBootstrap
LegacyAliasRegistry::add('Linked3\Classes\E2E\Linked3_Scale_Bootstrap', 'Linked3\Classes\Scale\ScaleBootstrap');  // Linked3\Classes\E2E\Linked3_Scale_Bootstrap → Linked3\Classes\Scale\ScaleBootstrap
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Performance_Cache', 'Linked3\Classes\Scale\PerformanceCache');  // Linked3\Classes\Diagram\Linked3_Performance_Cache → Linked3\Classes\Scale\PerformanceCache
LegacyAliasRegistry::add('Linked3\Classes\Diagram\Linked3_Audit_Logger', 'Linked3\Classes\Security\AuditLogger');  // Linked3\Classes\Diagram\Linked3_Audit_Logger → Linked3\Classes\Security\AuditLogger
LegacyAliasRegistry::add('Linked3\Classes\Dashboard\Linked3_Genesis_PromptAssembler', 'Linked3\Classes\Genesis\GenesisPromptAssembler');  // Linked3\Classes\Dashboard\Linked3_Genesis_PromptAssembler → Linked3\Classes\Genesis\GenesisPromptAssembler
LegacyAliasRegistry::add('Linked3\Classes\Dashboard\Linked3_Genesis_PQSChecker', 'Linked3\Classes\Genesis\GenesisPQSChecker');  // Linked3\Classes\Dashboard\Linked3_Genesis_PQSChecker → Linked3\Classes\Genesis\GenesisPQSChecker
LegacyAliasRegistry::add('Linked3\Classes\Dashboard\Linked3_Diagram_Validation_13Dim', 'Linked3\Classes\Diagram\DiagramValidation13Dim');  // Linked3\Classes\Dashboard\Linked3_Diagram_Validation_13Dim → Linked3\Classes\Diagram\DiagramValidation13Dim
LegacyAliasRegistry::add('Linked3\Classes\Dashboard\Linked3_Genesis_PlotParser', 'Linked3\Classes\Genesis\GenesisPlotParser');  // Linked3\Classes\Dashboard\Linked3_Genesis_PlotParser → Linked3\Classes\Genesis\GenesisPlotParser

// ─── Global (bare name) aliases ─────────────────────────────────────────
// Bare global class name → fully qualified new class name
// (used in linked3.php bootstrap and procedural code)
//
LegacyAliasRegistry::add('Linked3_AI_Pipeline_Bootstrap', 'Linked3\Classes\AI\Pipeline\AIPipelineBootstrap');  // Linked3_AI_Pipeline_Bootstrap → AIPipelineBootstrap
LegacyAliasRegistry::add('Linked3_Addon_Interface', 'Linked3\Classes\Addons\AddonInterface');  // Linked3_Addon_Interface → AddonInterface
LegacyAliasRegistry::add('Linked3_Alipay_Gateway', 'Linked3\Classes\Billing\AlipayGateway');  // Linked3_Alipay_Gateway → AlipayGateway
LegacyAliasRegistry::add('Linked3_Consent_Compliance_Addon', 'Linked3\Classes\Addons\ConsentComplianceAddon');  // Linked3_Consent_Compliance_Addon → ConsentComplianceAddon
LegacyAliasRegistry::add('Linked3_IP_Anonymization_Addon', 'Linked3\Classes\Addons\IPAnonymizationAddon');  // Linked3_IP_Anonymization_Addon → IPAnonymizationAddon
LegacyAliasRegistry::add('Linked3_Seed_CPT', 'Linked3\Classes\Genesis\GenesisSeedCPT');  // Linked3_Seed_CPT → GenesisSeedCPT
LegacyAliasRegistry::add('Linked3_Audit_Logger', 'Linked3\Classes\Security\AuditLogger');  // Linked3_Audit_Logger → AuditLogger
LegacyAliasRegistry::add('Linked3_Auto_Rollback', 'Linked3\Classes\E2E\AutoRollback');  // Linked3_Auto_Rollback → AutoRollback
LegacyAliasRegistry::add('Linked3_Batch_Engine', 'Linked3\Classes\Scale\BatchEngine');  // Linked3_Batch_Engine → BatchEngine
LegacyAliasRegistry::add('Linked3_Batch_Generate_Handler', 'Linked3\Classes\Scale\BatchGenerateHandler');  // Linked3_Batch_Generate_Handler → BatchGenerateHandler
LegacyAliasRegistry::add('Linked3_Billing_Bootstrap', 'Linked3\Classes\Billing\BillingBootstrap');  // Linked3_Billing_Bootstrap → BillingBootstrap
LegacyAliasRegistry::add('Linked3_Book_Cost_Tracker_Interface', 'Linked3\Classes\BookFactory\BookCostTrackerInterface');  // Linked3_Book_Cost_Tracker_Interface → BookCostTrackerInterface
LegacyAliasRegistry::add('Linked3_Book_Prompt_Provider_Interface', 'Linked3\Classes\BookFactory\BookPromptProviderInterface');  // Linked3_Book_Prompt_Provider_Interface → BookPromptProviderInterface
LegacyAliasRegistry::add('Linked3_Book_State_Repository_Interface', 'Linked3\Classes\BookFactory\BookStateRepositoryInterface');  // Linked3_Book_State_Repository_Interface → BookStateRepositoryInterface
LegacyAliasRegistry::add('Linked3_Content_Quality_Scorer', 'Linked3\Classes\AI\Pipeline\ContentQualityScorer');  // Linked3_Content_Quality_Scorer → ContentQualityScorer
LegacyAliasRegistry::add('Linked3_Cost_Reporter', 'Linked3\Classes\AI\Pipeline\CostReporter');  // Linked3_Cost_Reporter → CostReporter
LegacyAliasRegistry::add('Linked3_Diagram_3D_AR_Subsystem', 'Linked3\Classes\Diagram\Diagram3DARSubsystem');  // Linked3_Diagram_3D_AR_Subsystem → Diagram3DARSubsystem
LegacyAliasRegistry::add('Linked3_Diagram_4Layer_Anchor', 'Linked3\Classes\Diagram\Diagram4LayerAnchor');  // Linked3_Diagram_4Layer_Anchor → Diagram4LayerAnchor
LegacyAliasRegistry::add('Linked3_Diagram_8System_CrossRef', 'Linked3\Classes\Diagram\Diagram8SystemCrossRef');  // Linked3_Diagram_8System_CrossRef → Diagram8SystemCrossRef
LegacyAliasRegistry::add('Linked3_Diagram_BaseReuse_Flywheel', 'Linked3\Classes\Diagram\DiagramBaseReuseFlywheel');  // Linked3_Diagram_BaseReuse_Flywheel → DiagramBaseReuseFlywheel
LegacyAliasRegistry::add('Linked3_Diagram_Bootstrap', 'Linked3\Classes\Diagram\DiagramBootstrap');  // Linked3_Diagram_Bootstrap → DiagramBootstrap
LegacyAliasRegistry::add('Linked3_Diagram_BrandSystem_5D', 'Linked3\Classes\Diagram\DiagramBrandSystem5D');  // Linked3_Diagram_BrandSystem_5D → DiagramBrandSystem5D
LegacyAliasRegistry::add('Linked3_Diagram_Cognitive_6Level', 'Linked3\Classes\Diagram\DiagramCognitive6Level');  // Linked3_Diagram_Cognitive_6Level → DiagramCognitive6Level
LegacyAliasRegistry::add('Linked3_Diagram_Color_System', 'Linked3\Classes\Diagram\DiagramColorSystem');  // Linked3_Diagram_Color_System → DiagramColorSystem
LegacyAliasRegistry::add('Linked3_Diagram_Commercial_Hardening', 'Linked3\Classes\Diagram\DiagramCommercialHardening');  // Linked3_Diagram_Commercial_Hardening → DiagramCommercialHardening
LegacyAliasRegistry::add('Linked3_Diagram_Complexity_Reduction', 'Linked3\Classes\Diagram\DiagramComplexityReduction');  // Linked3_Diagram_Complexity_Reduction → DiagramComplexityReduction
LegacyAliasRegistry::add('Linked3_Diagram_Density_4Level', 'Linked3\Classes\Diagram\DiagramDensity4Level');  // Linked3_Diagram_Density_4Level → DiagramDensity4Level
LegacyAliasRegistry::add('Linked3_Diagram_E2E_TestSuite', 'Linked3\Classes\Diagram\DiagramE2ETestSuite');  // Linked3_Diagram_E2E_TestSuite → DiagramE2ETestSuite
LegacyAliasRegistry::add('Linked3_Diagram_Endpoint_DecisionTree', 'Linked3\Classes\Diagram\DiagramEndpointDecisionTree');  // Linked3_Diagram_Endpoint_DecisionTree → DiagramEndpointDecisionTree
LegacyAliasRegistry::add('Linked3_Diagram_Failure_Diagnosis', 'Linked3\Classes\Diagram\DiagramFailureDiagnosis');  // Linked3_Diagram_Failure_Diagnosis → DiagramFailureDiagnosis
LegacyAliasRegistry::add('Linked3_Diagram_Failure_Handbook', 'Linked3\Classes\Diagram\DiagramFailureHandbook');  // Linked3_Diagram_Failure_Handbook → DiagramFailureHandbook
LegacyAliasRegistry::add('Linked3_Diagram_Followup_Registry', 'Linked3\Classes\Diagram\DiagramFollowupRegistry');  // Linked3_Diagram_Followup_Registry → DiagramFollowupRegistry
LegacyAliasRegistry::add('Linked3_Diagram_FooterFollowup_Matrix', 'Linked3\Classes\Diagram\DiagramFooterFollowupMatrix');  // Linked3_Diagram_FooterFollowup_Matrix → DiagramFooterFollowupMatrix
LegacyAliasRegistry::add('Linked3_Diagram_Footer_Registry', 'Linked3\Classes\Diagram\DiagramFooterRegistry');  // Linked3_Diagram_Footer_Registry → DiagramFooterRegistry
LegacyAliasRegistry::add('Linked3_Diagram_Keyword_Refiner', 'Linked3\Classes\Diagram\DiagramKeywordRefiner');  // Linked3_Diagram_Keyword_Refiner → DiagramKeywordRefiner
LegacyAliasRegistry::add('Linked3_Diagram_Layout_Engine', 'Linked3\Classes\Diagram\DiagramLayoutEngine');  // Linked3_Diagram_Layout_Engine → DiagramLayoutEngine
LegacyAliasRegistry::add('Linked3_Diagram_Loop_Character_Integration', 'Linked3\Classes\Diagram\DiagramLoopCharacterIntegration');  // Linked3_Diagram_Loop_Character_Integration → DiagramLoopCharacterIntegration
LegacyAliasRegistry::add('Linked3_Diagram_Loop_Iterator', 'Linked3\Classes\Diagram\DiagramLoopIterator');  // Linked3_Diagram_Loop_Iterator → DiagramLoopIterator
LegacyAliasRegistry::add('Linked3_Diagram_ProductSeed_Manager', 'Linked3\Classes\Diagram\DiagramProductSeedManager');  // Linked3_Diagram_ProductSeed_Manager → DiagramProductSeedManager
LegacyAliasRegistry::add('Linked3_Diagram_Production_Bootstrap', 'Linked3\Classes\Diagram\DiagramProductionBootstrap');  // Linked3_Diagram_Production_Bootstrap → DiagramProductionBootstrap
LegacyAliasRegistry::add('Linked3_Diagram_Prompt_Compiler', 'Linked3\Classes\Diagram\DiagramPromptCompiler');  // Linked3_Diagram_Prompt_Compiler → DiagramPromptCompiler
LegacyAliasRegistry::add('Linked3_Diagram_Prompt_Compressor', 'Linked3\Classes\Diagram\DiagramPromptCompressor');  // Linked3_Diagram_Prompt_Compressor → DiagramPromptCompressor
LegacyAliasRegistry::add('Linked3_Diagram_Relationship_Encoder', 'Linked3\Classes\Diagram\DiagramRelationshipEncoder');  // Linked3_Diagram_Relationship_Encoder → DiagramRelationshipEncoder
LegacyAliasRegistry::add('Linked3_Diagram_Script_Layer', 'Linked3\Classes\Diagram\DiagramScriptLayer');  // Linked3_Diagram_Script_Layer → DiagramScriptLayer
LegacyAliasRegistry::add('Linked3_Diagram_Seed_Check', 'Linked3\Classes\Diagram\DiagramSeedCheck');  // Linked3_Diagram_Seed_Check → DiagramSeedCheck
LegacyAliasRegistry::add('Linked3_Diagram_Seed_Compiler', 'Linked3\Classes\Diagram\DiagramSeedCompiler');  // Linked3_Diagram_Seed_Compiler → DiagramSeedCompiler
LegacyAliasRegistry::add('Linked3_Diagram_Seed_Lock', 'Linked3\Classes\Diagram\DiagramSeedLock');  // Linked3_Diagram_Seed_Lock → DiagramSeedLock
LegacyAliasRegistry::add('Linked3_Diagram_Seed_Reference', 'Linked3\Classes\Diagram\DiagramSeedReference');  // Linked3_Diagram_Seed_Reference → DiagramSeedReference
LegacyAliasRegistry::add('Linked3_Diagram_Selection_DecisionTree', 'Linked3\Classes\Diagram\DiagramSelectionDecisionTree');  // Linked3_Diagram_Selection_DecisionTree → DiagramSelectionDecisionTree
LegacyAliasRegistry::add('Linked3_Diagram_SeriesDNA_4Lock', 'Linked3\Classes\Diagram\DiagramSeriesDNA4Lock');  // Linked3_Diagram_SeriesDNA_4Lock → DiagramSeriesDNA4Lock
LegacyAliasRegistry::add('Linked3_Diagram_TextEmbed_Validator', 'Linked3\Classes\Diagram\DiagramTextEmbedValidator');  // Linked3_Diagram_TextEmbed_Validator → DiagramTextEmbedValidator
LegacyAliasRegistry::add('Linked3_Diagram_Validation_13Dim', 'Linked3\Classes\Diagram\DiagramValidation13Dim');  // Linked3_Diagram_Validation_13Dim → DiagramValidation13Dim
LegacyAliasRegistry::add('Linked3_Diagram_Validation_Layer', 'Linked3\Classes\Diagram\DiagramValidationLayer');  // Linked3_Diagram_Validation_Layer → DiagramValidationLayer
LegacyAliasRegistry::add('Linked3_Diagram_VisualScript_Transform', 'Linked3\Classes\Diagram\DiagramVisualScriptTransform');  // Linked3_Diagram_VisualScript_Transform → DiagramVisualScriptTransform
LegacyAliasRegistry::add('Linked3_Diagram_Visual_Frequency', 'Linked3\Classes\Diagram\DiagramVisualFrequency');  // Linked3_Diagram_Visual_Frequency → DiagramVisualFrequency
LegacyAliasRegistry::add('Linked3_Final_Bootstrap', 'Linked3\Classes\E2E\FinalBootstrap');  // Linked3_Final_Bootstrap → FinalBootstrap
LegacyAliasRegistry::add('Linked3_Genesis_AtomSelector', 'Linked3\Classes\Genesis\GenesisAtomSelector');  // Linked3_Genesis_AtomSelector → GenesisAtomSelector
LegacyAliasRegistry::add('Linked3_Genesis_Bootstrap', 'Linked3\Classes\Genesis\GenesisBootstrap');  // Linked3_Genesis_Bootstrap → GenesisBootstrap
LegacyAliasRegistry::add('Linked3_Genesis_Evaluator', 'Linked3\Classes\Genesis\GenesisEvaluator');  // Linked3_Genesis_Evaluator → GenesisEvaluator
LegacyAliasRegistry::add('Linked3_Genesis_Exception', 'Linked3\Classes\Genesis\GenesisException');  // Linked3_Genesis_Exception → GenesisException
LegacyAliasRegistry::add('Linked3_Genesis_IRCompiler', 'Linked3\Classes\Genesis\GenesisIRCompiler');  // Linked3_Genesis_IRCompiler → GenesisIRCompiler
LegacyAliasRegistry::add('Linked3_Genesis_Mutator', 'Linked3\Classes\Genesis\GenesisMutator');  // Linked3_Genesis_Mutator → GenesisMutator
LegacyAliasRegistry::add('Linked3_Genesis_PQSChecker', 'Linked3\Classes\Genesis\GenesisPQSChecker');  // Linked3_Genesis_PQSChecker → GenesisPQSChecker
LegacyAliasRegistry::add('Linked3_Genesis_Pipeline', 'Linked3\Classes\Genesis\GenesisPipeline');  // Linked3_Genesis_Pipeline → GenesisPipeline
LegacyAliasRegistry::add('Linked3_Genesis_PlotParser', 'Linked3\Classes\Genesis\GenesisPlotParser');  // Linked3_Genesis_PlotParser → GenesisPlotParser
LegacyAliasRegistry::add('Linked3_Genesis_PromptAssembler', 'Linked3\Classes\Genesis\GenesisPromptAssembler');  // Linked3_Genesis_PromptAssembler → GenesisPromptAssembler
LegacyAliasRegistry::add('Linked3_Genesis_StoryboardGenerator', 'Linked3\Classes\Genesis\GenesisStoryboardGenerator');  // Linked3_Genesis_StoryboardGenerator → GenesisStoryboardGenerator
LegacyAliasRegistry::add('Linked3_Health_Monitor', 'Linked3\Classes\E2E\HealthMonitor');  // Linked3_Health_Monitor → HealthMonitor
LegacyAliasRegistry::add('Linked3_Invoice_Manager', 'Linked3\Classes\Billing\InvoiceManager');  // Linked3_Invoice_Manager → InvoiceManager
LegacyAliasRegistry::add('Linked3_MultiSite_Publisher', 'Linked3\Classes\Scale\MultiSitePublisher');  // Linked3_MultiSite_Publisher → MultiSitePublisher
LegacyAliasRegistry::add('Linked3_Payment_Gateway_Interface', 'Linked3\Classes\Billing\PaymentGatewayInterface');  // Linked3_Payment_Gateway_Interface → PaymentGatewayInterface
LegacyAliasRegistry::add('Linked3_Payment_Manager', 'Linked3\Classes\Billing\PaymentManager');  // Linked3_Payment_Manager → PaymentManager
LegacyAliasRegistry::add('Linked3_Performance_Cache', 'Linked3\Classes\Scale\PerformanceCache');  // Linked3_Performance_Cache → PerformanceCache
LegacyAliasRegistry::add('Linked3_Quota_Interceptor', 'Linked3\Classes\Billing\QuotaInterceptor');  // Linked3_Quota_Interceptor → QuotaInterceptor
LegacyAliasRegistry::add('Linked3_Referral_Manager', 'Linked3\Classes\Billing\ReferralManager');  // Linked3_Referral_Manager → ReferralManager
LegacyAliasRegistry::add('Linked3_Scale_Bootstrap', 'Linked3\Classes\Scale\ScaleBootstrap');  // Linked3_Scale_Bootstrap → ScaleBootstrap
LegacyAliasRegistry::add('Linked3_Security_Bootstrap', 'Linked3\Classes\Security\SecurityBootstrap');  // Linked3_Security_Bootstrap → SecurityBootstrap
LegacyAliasRegistry::add('Linked3_Wechat_Gateway', 'Linked3\Classes\Billing\WechatGateway');  // Linked3_Wechat_Gateway → WechatGateway
LegacyAliasRegistry::add('Linked3_i18n_Manager', 'Linked3\Classes\Scale\I18nManager');  // Linked3_i18n_Manager → I18nManager

