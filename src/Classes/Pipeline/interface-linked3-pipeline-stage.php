<?php
/**
 * Pipeline Stage Interface — contract for pipeline stages.
 *
 * v5.1.0: Each stage of the production pipeline implements this interface.
 * The Orchestrator calls execute() with the accumulated input and expects
 * a structured result that feeds into the next stage.
 *
 * @package Linked3
 * @subpackage Classes\Pipeline
 */

namespace Linked3\Classes\Pipeline;

if (!defined('ABSPATH')) {
    exit;
}

interface Linked3_Pipeline_Stage_Interface
{
    /**
     * Get the stage slug (e.g. 'collect', 'generate', 'enhance').
     *
     * @return string
     */
    public function slug(): string;

    /**
     * Get the human-readable stage name.
     *
     * @return string
     */
    public function label(): string;

    /**
     * Execute the stage.
     *
     * @param array $input   The accumulated pipeline data from previous stages.
     * @param array $config  Stage-specific configuration.
     * @return array{ok:bool, data:array, message:string}
     */
    public function execute(array $input, array $config): array;
}
