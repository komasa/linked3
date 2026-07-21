<?php

declare(strict_types=1);
/**
 * Schema markup orchestrator — chooses the right Schema builder for the
 * current request and emits a single JSON-LD <script> block.
 *
 * Mirrors v2.9.6 add_schema_markup (which emitted one big switch). The
 * orchestrator-delegates pattern lets future Schema types (VideoObject,
 * LocalBusiness, Review, etc.) be added as new builders + a register()
 * entry — no edits to the central class.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Schema
 */

namespace Linked3\Classes\SEO\Schema;

use Linked3\Classes\SEO\SEOConfig;



if (!defined('ABSPATH')) {
    exit;
}
final class SchemaMarkup
{
    /** @var self|null */
    private static $instance;

    /** @var array<string,SchemaBuilder> type → builder */
    private $builders = [];

    /** @var array<string,bool> type → enabled? */
    private $enabled;

    private function __construct() {
        $this->enabled = array_fill_keys((array) SEOConfig::get('schema.enabled_types', []), true);
        $this->register_default_builders();
    }

    /**
     * @return self
     */
    public static function instance() : mixed {
        if (null === self::$instance) {
            // v4.4.6: delegate to the DI container when available.
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
     * v4.4.6: used by the container's factory to avoid infinite recursion.
     *
     * @return self
     * @internal
     */
    public static function instance_without_container() : mixed     {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @return void
     */
    private function register_default_builders()
    : void {
        $this->register(new SchemaArticle());
        $this->register(new SchemaBlogPosting());
        $this->register(new SchemaFAQ());
        $this->register(new SchemaProduct());
        $this->register(new SchemaHowTo());
        /**
         * Allow third-party / Pro builders to register.
         */
        do_action_ref_array('linked3/seo_register_schema', [&$this]);
    }

    /**
     * @param SchemaBuilder $builder
     * @return void
     */
    public function register(SchemaBuilder $builder)
    : void {
        $this->builders[$builder->type()] = $builder;
    }

    /**
     * Enable / disable a schema type at runtime.
     *
     * @param string $type
     * @param bool   $enabled
     * @return void
     */
    public function set_enabled(string $type, bool $enabled)
    : void {
        $this->enabled[$type] = (bool) $enabled;
    }

    /**
     * Build JSON-LD for the current frontend request. Returns the JSON
     * string (without the <script> wrapper) or '' if nothing applicable.
     *
     * @return string
     */
    public function for_current_request() : mixed {
        if (!is_singular()) {
            return '';
        }
        $post = get_queried_object();
        if (!($post instanceof \WP_Post)) {
            return '';
        }
        return $this->for_post($post);
    }

    /**
     * @param \WP_Post $post
     * @return string
     */
    public function for_post(WP_Post $post) : mixed     {
        $payloads = [];
        foreach ($this->builders as $type => $builder) {
            if (!empty($this->enabled) && empty($this->enabled[$type])) {
                continue;
            }
            $arr = $builder->build($post);
            if (is_array($arr)) {
                $payloads[] = $arr;
            }
        }
        if (empty($payloads)) {
            return '';
        }
        // JSON_HEX_TAG is critical when emitting JSON-LD inside <script> —
        // without it, a `</script>` substring in any user-controlled field
        // (title, excerpt, FAQ answer) would break out of the script tag
        // and allow XSS. Mirror the WP core pattern used by
        // wp_localize_script().
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        // If a single payload, emit as a single object; multiple → array.
        if (count($payloads) === 1) {
            return wp_json_encode($payloads[0], $flags);
        }
        return wp_json_encode($payloads, $flags);
    }
}
