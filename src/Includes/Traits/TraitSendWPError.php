<?php

declare(strict_types=1);
/**
 * Trait: unified WP_Error / exception → JSON response helpers.
 *
 * @package Linked3
 * @subpackage Includes\Traits
 */

namespace Linked3\Includes\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait TraitSendWPError
{
    /**
     * @param \WP_Error|string $error
     * @param int              $status_code
     * @return never
     */
    protected function send_error(WP_Error|string $error, int $status_code = 400): void {
        if (is_wp_error($error)) {
            $data = [
                'code'    => $error->get_error_code() ?: 'linked3_error',
                'message' => $error->get_error_message(),
            ];
            $extra = $error->get_error_data();
            if (!empty($extra)) {
                $data['data'] = $extra;
            }
        } else {
            $data = ['code' => 'linked3_error', 'message' => (string) $error];
        }
        wp_send_json_error($data, $status_code);
    }

    /**
     * @param mixed $data
     * @param int   $status_code
     * @return never
     */
    protected function send_success($data = null, int $status_code = 200): void {
        wp_send_json_success($data, $status_code);
    }
}
