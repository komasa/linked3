<?php

declare(strict_types=1);
/**
 * BookFactory 章节展开器 (v19.0 从 Book_Factory 拆分)
 *
 * 职责: 章节展开 — 调用 AI 将大纲节点展开为完整章节内容。
 * v18.x 中此逻辑在 Section_Expander Trait 中, 被 Book_Factory 使用。
 *
 * @package Linked3\BookFactory
 * @since   19.0
 */

// Exit if accessed directly.
namespace Linked3\Classes\BookFactory;



        use \Linked3\Classes\BookFactory\Traits\SectionExpander;



if ( ! defined( 'ABSPATH' ) ) {
        exit;
}
/**
 * Class BookSectionExpanderService
 *
 * 章节展开服务, 通过依赖注入接收 AI 调用器与提示词提供者。
 */
class BookSectionExpanderService {
        use SectionExpander;

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
         * 重新生成指定章节。
         *
         * @param BookProjectState $state           项目状态。
         * @param int                         $chapter_index  章索引。
         * @param int                         $section_index  节索引。
         * @return array|WP_Error
         */
        public function regenerate_section(BookProjectState $state, int $chapter_index, int $section_index) : mixed {
                return $this->do_regenerate_section( $state, $chapter_index, $section_index );
        }
}
