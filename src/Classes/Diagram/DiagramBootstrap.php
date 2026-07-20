<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Bootstrap — extracted from Diagram3LayerDepth.php during PSR-4 migration.
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
        $container->set('diagram.anchor_4layer', fn() => new Linked3_Diagram_4Layer_Anchor());
        $container->set('diagram.decision_tree', fn() => new Linked3_Diagram_Selection_DecisionTree());
        $container->set('diagram.complexity_reduction', fn() => new Linked3_Diagram_Complexity_Reduction());
        $container->set('diagram.layout_engine', fn() => new Linked3_Diagram_Layout_Engine());
        $container->set('diagram.color_system', fn() => new Linked3_Diagram_Color_System());
        $container->set('diagram.validation_13dim', fn() => new Linked3_Diagram_Validation_13Dim());

        // v6.2.0: 三层提示词架构
        $container->set('diagram.meta_layer', fn() => new DiagramMETALayer());
        $container->set('diagram.script_layer', fn() => new Linked3_Diagram_Script_Layer());
        $container->set('diagram.validation_layer', fn() => new Linked3_Diagram_Validation_Layer());
        $container->set('diagram.prompt_compiler', fn() => new Linked3_Diagram_Prompt_Compiler());
        $container->set('diagram.prompt_compressor', fn() => new Linked3_Diagram_Prompt_Compressor());
        $container->set('diagram.keyword_refiner', fn() => new Linked3_Diagram_Keyword_Refiner());
        $container->set('diagram.textembed_validator', fn() => new Linked3_Diagram_TextEmbed_Validator());
        $container->set('diagram.loop_iterator', fn() => new Linked3_Diagram_Loop_Iterator());
        $container->set('diagram.failure_handbook', fn() => new Linked3_Diagram_Failure_Handbook());

        // v6.3.0: Endpoint与追问系统
        $container->set('diagram.endpoint_registry', fn() => DiagramEndpointRegistry::instance());
        $container->set('diagram.endpoint_decision_tree', fn() => new Linked3_Diagram_Endpoint_DecisionTree());
        $container->set('diagram.followup_registry', fn() => Linked3_Diagram_Followup_Registry::instance());
        $container->set('diagram.footer_registry', fn() => Linked3_Diagram_Footer_Registry::instance());
        $container->set('diagram.footer_followup_matrix', fn() => new Linked3_Diagram_FooterFollowup_Matrix());
        $container->set('diagram.relationship_encoder', fn() => new Linked3_Diagram_Relationship_Encoder());
        $container->set('diagram.cognitive_6level', fn() => new Linked3_Diagram_Cognitive_6Level());
        $container->set('diagram.density_4level', fn() => new Linked3_Diagram_Density_4Level());
        $container->set('diagram.visual_frequency', fn() => new Linked3_Diagram_Visual_Frequency());

        // v6.4.0: 视觉DNA与Seed系统
        $container->set('diagram.character_seed', fn() => DiagramCharacterSeedManager::instance());
        $container->set('diagram.product_seed', fn() => Linked3_Diagram_ProductSeed_Manager::instance());
        $container->set('diagram.seed_reference', fn() => new Linked3_Diagram_Seed_Reference());
        $container->set('diagram.seed_lock', fn() => new Linked3_Diagram_Seed_Lock());
        $container->set('diagram.seed_check', fn() => new Linked3_Diagram_Seed_Check());
        $container->set('diagram.series_dna', fn() => new Linked3_Diagram_SeriesDNA_4Lock());
        $container->set('diagram.failure_diagnosis', fn() => new Linked3_Diagram_Failure_Diagnosis());
        $container->set('diagram.loop_character', fn() => new Linked3_Diagram_Loop_Character_Integration());
        $container->set('diagram.seed_compiler', fn() => new Linked3_Diagram_Seed_Compiler());

        linked3_dispatch('linked3.diagram.boot', ['version' => LINKED3_VERSION]);
    }
}
