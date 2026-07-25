<?php

declare(strict_types=1);
/**
 * Main plugin class. Intentionally thin — delegates everything to the
 * three-layer orchestrator (DependencyLoader → HookManager → ModuleInitializer).
 *
 * @package Linked3
 * @subpackage Includes
 */

namespace Linked3\Includes;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    private static $instance = null;

    /** @var string */
    private $version;

    /**
     * @param string $version
     */
    private function __construct($version) {
        $this->version = $version;
    }

    /**
     * Singleton accessor — no args on subsequent calls.
     *
     * @param string $version Optional, only used on first call.
     * @return self
     */
    public static function instance(string $version = ''): self
    {
        if (null === self::$instance) {
            self::$instance = new self($version ?: LINKED3_VERSION);
        }
        return self::$instance;
    }

    /**
     * @return string
     */
    public function version(): string
    {
        return $this->version;
    }

    /**
     * Bootstrap entry. Three static calls — main class owns no require/hook.
     *
     * @return void
     */
    public function run(): void {
        // 1) Load all required files (pure require_once, no hooks).
        DependencyLoader::load();

        // 1.5) Register AjaxNonceGuard — intercept all wp_ajax_* before handlers.
        if (class_exists(__NAMESPACE__ . '\\AjaxNonceGuard')) {
            add_action('admin_init', [__NAMESPACE__ . '\\AjaxNonceGuard', 'guard_all_ajax'], 0);
            add_action('wp_ajax_linked3_create_nonce', function() {
                wp_send_json_success(['nonce' => \Linked3\Includes\AjaxNonceGuard::create_nonce()]);
            });
        }

        // 2) Register all WordPress hooks (instantiates handlers, registers actions).
        HookManager::register_hooks($this->version);

        // 3) Initialize sub-systems (dashboard, modules, etc).
        ModuleInitializer::init($this->version);
    }
}
