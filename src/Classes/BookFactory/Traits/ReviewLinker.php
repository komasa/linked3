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
     * 执行审校 — 调用 AI 审阅草稿, 生成审校报告与修改建议。
     *
     * v27.6.18-fix: Added to fix "Call to undefined method do_review"
     * — BookReviewCoordinator calls $this->do_review() but the method
     * was never defined (ReviewLinker trait was empty).
     *
     * @param mixed $state BookProjectState instance.
     * @return array|WP_Error
     */
    protected function do_review( $state ) {
        $draft_markdown = $state->get( 'draft_markdown', '' );
        $book_title = $state->get( 'book_title', '' );

        if ( empty( $draft_markdown ) ) {
            return new \WP_Error( 'no_draft', __( '草稿为空, 无法审校', 'linked3-ai' ) );
        }

        // 截取前 8000 字供 AI 审阅 (避免 token 超限)
        $review_content = mb_substr( $draft_markdown, 0, 8000 );

        $prompt = sprintf(
            "书名: %s\n\n请审阅以下书稿内容, 给出:\n1. 整体评价 (100字以内)\n2. 主要问题 (列出3-5个)\n3. 修改建议 (针对每个问题)\n4. 质量评分 (1-10分)\n\n书稿内容:\n%s",
            $book_title,
            $review_content
        );

        try {
            if ( class_exists( '\\Linked3\\Classes\\Core\\AIDispatcher' ) ) {
                $dispatcher = \Linked3\Classes\Core\AIDispatcher::instance();
                $messages = array( array( 'role' => 'user', 'content' => $prompt ) );
                $options = array( 'temperature' => 0.3, 'max_tokens' => 2048 );
                $response = $dispatcher->chat( $messages, $options, array() );
            } else {
                return new \WP_Error( 'ai_unavailable', __( 'AI 引擎未加载', 'linked3-ai' ) );
            }
        } catch ( \Throwable $e ) {
            return new \WP_Error( 'ai_call_failed', $e->getMessage() );
        }

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $review_text = '';
        if ( isset( $response['choices'][0]['message']['content'] ) ) {
            $review_text = $response['choices'][0]['message']['content'];
        } elseif ( isset( $response['content'] ) ) {
            $review_text = $response['content'];
        }

        // 保存审校结果
        $state->set( 'review_result', $review_text );
        $state->set( 'reviewed_at', current_time( 'mysql' ) );
        $state->save_state();

        return array(
            'review'  => $review_text,
            'message' => __( '审校完成', 'linked3-ai' ),
        );
    }
}
