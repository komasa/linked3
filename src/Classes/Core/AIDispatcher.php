<?php

declare(strict_types=1);
/**
 * AI Dispatcher — single entry point for all AI calls in linked3.0 (Facade).
 *
 * v2026.07.25: Facade+Trait refactor —
 *   - chat() + call_single() → AIDispatcherChatTrait
 *   - log_usage() + estimate_cost_usd() → AIDispatcherBillingTrait
 *   - is_circuit_open() + reset_circuit() + record_failure() → AIDispatcherCircuitTrait
 *   This class is now an ~80-line Facade. All public signatures (chat,
 *   instance, instance_without_container) are preserved.
 *
 * Responsibilities:
 *   1) Resolve provider via Factory + pick API key via KeyRotator
 *   2) Build payload via Provider Strategy
 *   3) Send request via Safe_Remote (SSRF-hardened, circuit-broken)
 *   4) Log every call to linked3_usage_logs (tokens, cost, status)
 *   5) Mark failed keys unhealthy in the Rotator
 *   6) Provider-level circuit breaker: if a provider fails >5 times in 5 min,
 *      fall back to the next configured provider
 *
 * @package Linked3
 * @subpackage Classes\Core
 */

namespace Linked3\Classes\Core;

use Linked3\Classes\Core\Providers\ProviderFactory;
use Linked3\Includes\Log\Logger;

require_once __DIR__ . '/Traits/AIDispatcherChatTrait.php';
require_once __DIR__ . '/Traits/AIDispatcherBillingTrait.php';
require_once __DIR__ . '/Traits/AIDispatcherCircuitTrait.php';

use Linked3\Classes\Core\Traits\AIDispatcherChatTrait;
use Linked3\Classes\Core\Traits\AIDispatcherBillingTrait;
use Linked3\Classes\Core\Traits\AIDispatcherCircuitTrait;

if (!defined('ABSPATH')) {
    exit;
}

final class AIDispatcher
{
    use AIDispatcherChatTrait;
    use AIDispatcherBillingTrait;
    use AIDispatcherCircuitTrait;

    /** @var self|null */
    private static $instance;

    /** @var Logger */
    private $log;

    /** @var ProviderFactory */
    private $factory;

    /** @var TokenManager|null */
    private $tokens;

    /** HTTP status codes that constitution §4 says must evict the API key. */
    const KEY_EVICT_CODES = [401, 403, 429];

    /** Constitution §3: provider circuit opens after this many failures / 5 min. */
    const CIRCUIT_THRESHOLD = 5;

    private function __construct() {
        $this->log     = Logger::instance();
        $this->factory = ProviderFactory::instance();
        $this->tokens  = class_exists('\\Linked3\\Classes\\Core\\TokenManager')
            ? TokenManager::instance()
            : null;
    }

    /**
     * Get the singleton instance.
     *
     * v4.4.2: delegates to the Container so call sites can be
     * migrated to dependency injection gradually.
     *
     * @return self
     */
    public static function instance() : mixed {
        if (null === self::$instance) {
            if (class_exists('\\Linked3\\Includes\\Container')) {
                $container = \Linked3\Includes\Container::instance();
                if ($container->has(self::class)) {
                    self::$instance = $container->get(self::class);
                    return self::$instance;
                }
            }
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construct the singleton WITHOUT going through the container.
     *
     * @return self
     * @internal Called only by Container::register_defaults().
     */
    public static function instance_without_container() : mixed {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
