<?php

declare(strict_types=1);
namespace Linked3\Classes\Chat\Ajax;
    use \Linked3\Includes\Traits\Trait_Check_Frontend_Permissions;
    use \Linked3\Includes\Traits\Trait_Send_WP_Error;


if (!defined('ABSPATH')) exit;

/**
 * Chat base ajax action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Chat.Ajax
 * @since      27.1.0
 */

abstract class ChatBaseAjaxAction
{
    const NONCE_ACTION = 'linked3_chat';

    abstract public function handle();

    public function dispatch()
    : void {
        // Chat supports both logged-in (verify) and guest (verify_public).
        $pub = !empty($_REQUEST['guest']);
        if ($pub) {
            $this->verify_public(static::NONCE_ACTION, 'nonce', 30);
        } else {
            $this->verify(static::NONCE_ACTION);
        }
        $this->handle();
    }

    protected function manager() : mixed {
        return \Linked3\Classes\Chat\ChatManager::instance();
    }
}
