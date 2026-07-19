<?php
namespace Linked3\Includes;
if (!defined('ABSPATH')) exit;
/**
 * Config registry.
 *
 * @package    Linked3
 * @subpackage Linked3.Includes
 * @since      27.1.0
 */
final class Linked3_Config_Registry
{
    const AI_DEFAULT_TIMEOUT = 30; const AI_MAX_RETRIES = 3; const AI_MAX_TOKENS_DEFAULT = 4096; const AI_TEMPERATURE_DEFAULT = 0.7;
    const RATE_LIMIT_FREE = 100; const RATE_LIMIT_PRO = 1000; const RATE_LIMIT_PREMIUM = 10000;
    const CONTENT_MAX_LENGTH = 50000; const CONTENT_BATCH_SIZE = 10;
    const BOOK_MAX_CHAPTERS = 50; const BOOK_AI_TIMEOUT = 120;
    const CACHE_TTL_HOUR = 3600; const CACHE_TTL_DAY = 86400; const CACHE_TTL_WEEK = 604800;
    const VECTOR_MAX_RESULTS = 20; const VECTOR_MIN_SIMILARITY = 0.7;
    const PAGINATION_DEFAULT = 20; const PAGINATION_MAX = 100;
    public static function get($key, $default = null) { $c = 'LINKED3_' . $key; if (defined($c)) return constant($c); if (defined("self::$key")) return constant("self::$key"); return $default; }
}
