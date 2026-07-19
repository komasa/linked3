<?php
namespace Linked3\Classes\AutoGPT\Processors;
if (!defined('ABSPATH')) exit;

/**
 * Interface Autogpt processor.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.AutoGPT.Processors
 * @since      27.1.0
 */

interface Linked3_AutoGPT_Processor_Interface
{
    /**
     * @param array $task {id, task_type, config, user_id}
     * @return array{ok:bool, message:string, items_processed:int}
     */
    public function process(array $task);
}
