<?php

declare(strict_types=1);
namespace Linked3\Classes\Chat;
if (!defined('ABSPATH')) exit;

/**
 * Chat dependencies loader.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Chat
 * @since      27.1.0
 */

final class ChatDependenciesLoader
{
    static function load(): void {
        $files = [
            'Classes/Chat/Storage/ChatStorage.php',
            'Classes/Chat/Triggers/ChatTriggerEngine.php',
            'Classes/Chat/RAGRetriever.php',
            'Classes/Chat/ChatModeration.php',
            'Classes/Chat/ChatManager.php',
            'Classes/Chat/Shortcode/ChatShortcode.php',
            'Classes/Chat/Ajax/ChatBaseAjaxAction.php',
            'Classes/Chat/Ajax/Actions/ChatSendAction.php',
            'Classes/Chat/Ajax/Actions/ChatHistoryAction.php',
            'Classes/Chat/ChatHooksRegistrar.php',
            // Vector providers are loaded here (they are needed by RAG),
            // but registration into the Factory is now done by the Factory
            // itself (v4.8.1 — was previously done here, which was the
            // wrong owner: disabling Chat would lose Pinecone/Qdrant).
            'Classes/Vector/VectorProviderInterface.php',
            'Classes/Vector/VectorFactory.php',
            'Classes/Vector/Providers/LocalVectorProvider.php',
            'Classes/Vector/Providers/PineconeVectorProvider.php',
            'Classes/Vector/Providers/QdrantVectorProvider.php',
            'Classes/Vector/PostProcessor/PostProcessor.php',
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
