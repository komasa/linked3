<?php

declare(strict_types=1);
/**
 * DiagramBootstrap — extracted from Diagram3LayerDepth.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramBootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        $container = linked3_container();
        $container->set('diagram.master_template', fn() => new DiagramMasterTemplate());
        $container->set('diagram.type_registry', fn() => DiagramTypeRegistry::instance());
        $container->set('diagram.depth_3layer', fn() => new Diagram3LayerDepth());
        $container->set('diagram.anchor_4layer', fn() => new Diagram4LayerAnchor());
        $container->set('diagram.decision_tree', fn() => new DiagramSelectionDecisionTree());
        $container->set('diagram.complexity_reduction', fn() => new DiagramComplexityReduction());
        $container->set('diagram.layout_engine', fn() => new DiagramLayoutEngine());
        $container->set('diagram.color_system', fn() => new DiagramColorSystem());
        $container->set('diagram.validation_13dim', fn() => new DiagramValidation13Dim());

        // v6.2.0: 三层提示词架构
        $container->set('diagram.meta_layer', fn() => new DiagramMETALayer());
        $container->set('diagram.script_layer', fn() => new DiagramScriptLayer());
        $container->set('diagram.validation_layer', fn() => new DiagramValidationLayer());
        $container->set('diagram.prompt_compiler', fn() => new DiagramPromptCompiler());
        $container->set('diagram.prompt_compressor', fn() => new DiagramPromptCompressor());
        $container->set('diagram.keyword_refiner', fn() => new DiagramKeywordRefiner());
        $container->set('diagram.textembed_validator', fn() => new DiagramTextEmbedValidator());
        $container->set('diagram.loop_iterator', fn() => new DiagramLoopIterator());
        $container->set('diagram.failure_handbook', fn() => new DiagramFailureHandbook());

        // v6.3.0: Endpoint与追问系统
        $container->set('diagram.endpoint_registry', fn() => DiagramEndpointRegistry::instance());
        $container->set('diagram.endpoint_decision_tree', fn() => new DiagramEndpointDecisionTree());
        $container->set('diagram.followup_registry', fn() => DiagramFollowupRegistry::instance());
        $container->set('diagram.footer_registry', fn() => DiagramFooterRegistry::instance());
        $container->set('diagram.footer_followup_matrix', fn() => new DiagramFooterFollowupMatrix());
        $container->set('diagram.relationship_encoder', fn() => new DiagramRelationshipEncoder());
        $container->set('diagram.cognitive_6level', fn() => new DiagramCognitive6Level());
        $container->set('diagram.density_4level', fn() => new DiagramDensity4Level());
        $container->set('diagram.visual_frequency', fn() => new DiagramVisualFrequency());

        // v6.4.0: 视觉DNA与Seed系统
        $container->set('diagram.character_seed', fn() => DiagramCharacterSeedManager::instance());
        $container->set('diagram.product_seed', fn() => DiagramProductSeedManager::instance());
        $container->set('diagram.seed_reference', fn() => new DiagramSeedReference());
        $container->set('diagram.seed_lock', fn() => new DiagramSeedLock());
        $container->set('diagram.seed_check', fn() => new DiagramSeedCheck());
        $container->set('diagram.series_dna', fn() => new DiagramSeriesDNA4Lock());
        $container->set('diagram.failure_diagnosis', fn() => new DiagramFailureDiagnosis());
        $container->set('diagram.loop_character', fn() => new DiagramLoopCharacterIntegration());
        $container->set('diagram.seed_compiler', fn() => new DiagramSeedCompiler());

        linked3_dispatch('linked3.diagram.boot', ['version' => LINKED3_VERSION]);
    }
}
