<?php

declare(strict_types=1);
/**
 * Linked3 Review Linker — 审阅模块联动
 *
 * 方案: S8 (G1) + S13 (G2增量拼接)
 * 公理: 联动SEO/改写/标题模块 → 增量审阅
 *
 * @package Linked3\BookFactory\Traits
 * @since   18.5.0
 */

namespace Linked3\Classes\BookFactory\Traits;

if ( ! defined( 'ABSPATH' ) ) exit;

trait ReviewLinker {

    /**
     * 调用SEO模块审阅
     *
     * @param string $draft
     * @return array|WP_Error
     */
    protected function call_seo_review( $draft ) : mixed {
        if ( ! class_exists( '\Linked3\Classes\BookFactory\Traits\Linked3_SEO_Prompt_Builder' ) ) {
            return new WP_Error( 'no_seo', 'SEO模块未启用' );
        }

        $prompt = Linked3_SEO_Prompt_Builder::build( $draft );
        $response = $this->call_ai_with_rate_limit( $prompt );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return array(
            'suggestions' => $response['content'],
            'tokens_used' => $response['tokens_in'] + $response['tokens_out'],
        );
    }

    /**
     * 调用改写模块审阅
     *
     * @param string $draft
     * @return array|WP_Error
     */
    protected function call_rewrite_review( $draft ) : mixed {
        if ( ! class_exists( '\Linked3\Classes\BookFactory\Traits\Linked3_Rewrite_Prompt_Builder' ) ) {
            return new WP_Error( 'no_rewrite', '改写模块未启用' );
        }

        $prompt = Linked3_Rewrite_Prompt_Builder::build( $draft );
        $response = $this->call_ai_with_rate_limit( $prompt );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return array(
            'suggestions' => $response['content'],
            'tokens_used' => $response['tokens_in'] + $response['tokens_out'],
        );
    }

    /**
     * 调用标题模块审阅
     *
     * @param string $draft
     * @param string $book_title
     * @return array|WP_Error
     */
    protected function call_title_review( $draft, $book_title ) {
        if ( ! class_exists( '\Linked3\Classes\BookFactory\Traits\Linked3_Title_Prompt_Builder' ) ) {
            return new WP_Error( 'no_title', '标题模块未启用' );
        }

        $prompt = Linked3_Title_Prompt_Builder::build( $draft, $book_title );
        $response = $this->call_ai_with_rate_limit( $prompt );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return array(
            'suggestions' => $response['content'],
            'tokens_used' => $response['tokens_in'] + $response['tokens_out'],
        );
    }

    /**
     * 应用审阅建议 (增量拼接, S13)
     *
     * @param string $draft
     * @param array $suggestions
     * @return string
     */
    protected function apply_review_suggestions( $draft, $suggestions ) {
        // 默认: 不自动应用建议，仅附加到末尾供用户参考
        // (避免AI自动改写破坏原稿)
        $reviewed = $draft;

        if ( ! empty( $suggestions ) ) {
            $reviewed .= "\n\n---\n\n";
            $reviewed .= "<!-- 审阅建议 (可手动应用) -->\n\n";

            if ( isset( $suggestions['seo'] ) && ! is_wp_error( $suggestions['seo'] ) ) {
                $reviewed .= "## SEO建议\n\n" . $suggestions['seo']['suggestions'] . "\n\n";
            }
            if ( isset( $suggestions['rewrite'] ) && ! is_wp_error( $suggestions['rewrite'] ) ) {
                $reviewed .= "## 改写建议\n\n" . $suggestions['rewrite']['suggestions'] . "\n\n";
            }
            if ( isset( $suggestions['title'] ) && ! is_wp_error( $suggestions['title'] ) ) {
                $reviewed .= "## 标题建议\n\n" . $suggestions['title']['suggestions'] . "\n\n";
            }
        }

        return $reviewed;
    }
}
