<?php
namespace Linked3\Classes\Chat;
if (!defined('ABSPATH')) exit;

/**
 * Chat dependencies loader.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Chat
 * @since      27.1.0
 */

final class Linked3_Chat_Dependencies_Loader
{
    public static function load()
    : void {
        $files = [
            'Classes/Chat/Storage/class-linked3-chat-storage.php',
            'Classes/Chat/Triggers/class-linked3-chat-trigger-engine.php',
            'Classes/Chat/class-linked3-rag-retriever.php',
            'Classes/Chat/class-linked3-chat-moderation.php',
            'Classes/Chat/class-linked3-chat-manager.php',
            'Classes/Chat/Shortcode/class-linked3-chat-shortcode.php',
            'Classes/Chat/Ajax/class-linked3-chat-base-ajax-action.php',
            'Classes/Chat/Ajax/Actions/class-linked3-chat-send-action.php',
            'Classes/Chat/Ajax/Actions/class-linked3-chat-history-action.php',
            'Classes/Chat/class-linked3-chat-hooks-registrar.php',
            // Vector providers are loaded here (they are needed by RAG),
            // but registration into the Factory is now done by the Factory
            // itself (v4.8.1 — was previously done here, which was the
            // wrong owner: disabling Chat would lose Pinecone/Qdrant).
            'Classes/Vector/interface-linked3-vector-provider.php',
            'Classes/Vector/class-linked3-vector-factory.php',
            'Classes/Vector/Providers/class-linked3-local-vector-provider.php',
            'Classes/Vector/Providers/class-linked3-pinecone-vector-provider.php',
            'Classes/Vector/Providers/class-linked3-qdrant-vector-provider.php',
            'Classes/Vector/PostProcessor/class-linked3-post-processor.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        // v4.8.1: Pinecone/Qdrant registration moved to Vector_Factory::__construct().
        // No registration code needed here anymore.
    }
}
