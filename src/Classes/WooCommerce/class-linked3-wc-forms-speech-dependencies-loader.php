<?php
namespace Linked3\Classes\WooCommerce;
if (!defined('ABSPATH')) exit;

/**
 * Wc forms speech dependencies loader.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.WooCommerce
 * @since      27.1.0
 */

final class Linked3_WC_Forms_Speech_Dependencies_Loader
{
    public static function load()
    : void {
        $files = [
            'Classes/WooCommerce/class-linked3-wc-ai-generator.php',
            'Classes/AIForms/class-linked3-ai-form-manager.php',
            'Classes/Speech/class-linked3-tts-manager.php',
            'Classes/STT/SttManager.php',
            'Classes/WooCommerce/class-linked3-wc-token-package.php', // v3.1.0
            'Classes/WooCommerce/class-linked3-wc-forms-speech-hooks-registrar.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) require_once $path;
        }
    }
}
