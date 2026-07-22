<?php

declare(strict_types=1);
namespace Linked3\Classes\WooCommerce;
if (!defined('ABSPATH')) exit;

/**
 * Wc forms speech dependencies loader.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.WooCommerce
 * @since      27.1.0
 */

final class WcFormsSpeechDependenciesLoader
{
    public static function load(): void {
        $files = [
            'Classes/WooCommerce/WcAiGenerator.php',
            'Classes/AIForms/AiFormManager.php',
            'Classes/Speech/TtsManager.php',
            'Classes/STT/SttManager.php',
            'Classes/WooCommerce/WcTokenPackage.php', // v3.1.0
            'Classes/WooCommerce/WcFormsSpeechHooksRegistrar.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) require_once $path;
        }
    }
}
