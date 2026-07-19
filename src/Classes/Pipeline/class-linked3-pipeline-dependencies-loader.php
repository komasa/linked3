<?php
/**
 * Pipeline module — dependency loader.
 *
 * v5.1.0: loads the Pipeline Orchestrator + Stage interface + base class.
 * Concrete stage implementations (Collect, Generate, Enhance, Publish,
 * Distribute) are registered dynamically by their respective modules.
 *
 * @package Linked3
 * @subpackage Classes\Pipeline
 */

namespace Linked3\Classes\Pipeline;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_Pipeline_Dependencies_Loader
{
    public static function load()
    : void {
        $files = [
            'Classes/Pipeline/interface-linked3-pipeline-stage.php',
            'Classes/Pipeline/class-linked3-pipeline-stage.php',
            'Classes/Pipeline/class-linked3-pipeline-orchestrator.php',
            // v5.1.5: Placeholder resolver for pipeline template prompts.
            'Classes/Pipeline/class-linked3-pipeline-placeholder-resolver.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
