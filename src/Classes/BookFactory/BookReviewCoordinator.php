<?php

declare(strict_types=1);
/**
 * BookFactory 审校协调器 (v19.0 从 Book_Factory 拆分)
 *
 * 职责: 审校协调 — 调用 AI 审阅草稿, 生成审校报告与修改建议。
 * v18.x 中此逻辑在 Review_Linker Trait 中, 被 Book_Factory 使用。
 *
 * @package Linked3\BookFactory
 * @since   19.0
 */

// Exit if accessed directly.
namespace Linked3\Classes\BookFactory;



        use \Linked3\Classes\BookFactory\Traits\ReviewLinker;



if ( ! defined( 'ABSPATH' ) ) {
        exit;
}
/**
 * Class BookReviewCoordinator
 */
class BookReviewCoordinator {
        use ReviewLinker;

        /**
         * AI 调用器。
         *
         * @var BookAICallerInterface
         */
        protected $ai_caller;

        /**
         * 提示词提供者。
         *
         * @var BookPromptProviderInterface
         */
        protected $prompt_provider;

        /**
         * 构造函数 — 依赖注入。
         *
         * @param BookAICallerInterface|null       $ai_caller       AI 调用器。
         * @param BookPromptProviderInterface|null $prompt_provider 提示词提供者。
         */
        public function __construct(
                BookAICallerInterface $ai_caller = null,
                BookPromptProviderInterface $prompt_provider = null
        ) {
                $this->ai_caller       = $ai_caller ?: new BookDefaultAICaller();
                $this->prompt_provider = $prompt_provider ?: new BookPromptManager();
        }

        /**
         * 执行审校。
         *
         * @param BookProjectState $state 项目状态。
         * @return array|WP_Error 返回审校结果或 WP_Error。
         */
        public function review(BookProjectState $state) : mixed {
                // 委托给 Review_Linker Trait 的方法。
                return $this->do_review( $state );
        }
}
