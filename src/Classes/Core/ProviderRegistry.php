<?php
declare(strict_types=1);
namespace Linked3\Classes\Core;
if (!defined('ABSPATH')) exit;

/**
 * ProviderRegistry — v30 MVP
 * Single entry for providers. Fully delegates to existing ProviderFactory (100% compatible).
 */
class ProviderRegistry {
    public static function make(string $slug) {
        if (class_exists(\Linked3\Classes\Core\Providers\ProviderFactory::class)) {
            return \Linked3\Classes\Core\Providers\ProviderFactory::instance()->make($slug);
        }
        throw new \Exception("Provider {$slug} not available");
    }

    public static function getRotationService(): KeyRotationService {
        return new KeyRotationService();
    }
}

class KeyRotationService {
    public function getKey(string $slug): string {
        $keys = (array) get_option((defined('LINKED3_OPTION_PREFIX') ? LINKED3_OPTION_PREFIX : 'linked3_') . 'provider_keys', []);
        $raw = $keys[$slug] ?? '';
        $list = array_filter(array_map('trim', explode("\n", (string) $raw)));
        if (empty($list)) return '';
        $mode = get_option((defined('LINKED3_OPTION_PREFIX') ? LINKED3_OPTION_PREFIX : 'linked3_') . 'key_rotation', 'disabled');
        if ($mode === 'round_robin') {
            $idx = (int) get_transient("linked3_rr_{$slug}") % count($list);
            set_transient("linked3_rr_{$slug}", $idx + 1, HOUR_IN_SECONDS);
            return $list[$idx];
        }
        return $list[0];
    }
}
