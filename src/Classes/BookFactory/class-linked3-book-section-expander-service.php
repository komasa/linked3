<?php
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



        use \Linked3\Classes\BookFactory\Traits\Linked3_Section_Expander;



if ( ! defined( 'ABSPATH' ) ) {
        exit;
}
/**
 * Class Linked3_Book_Section_Expander_Service
 *
 * 章节展开服务, 通过依赖注入接收 AI 调用器与提示词提供者。
 */
class Linked3_Book_Section_Expander_Service {
        /**
         * AI 调用器。
         *
         * @var Linked3_Book_AI_Caller_Interface
         */
        protected $ai_caller;

        /**
         * 提示词提供者。
         *
         * @var Linked3_Book_Prompt_Provider_Interface
         */
        protected $prompt_provider;

        /**
         * 构造函数 — 依赖注入。
         *
         * @param Linked3_Book_AI_Caller_Interface|null       $ai_caller       AI 调用器。
         * @param Linked3_Book_Prompt_Provider_Interface|null $prompt_provider 提示词提供者。
         */
        public function __construct(
                Linked3_Book_AI_Caller_Interface $ai_caller = null,
                Linked3_Book_Prompt_Provider_Interface $prompt_provider = null
        ) {
                $this->ai_caller       = $ai_caller ?: new Linked3_Book_Default_AI_Caller();
                $this->prompt_provider = $prompt_provider ?: new Linked3_Book_Prompt_Manager();
        }

        /**
         * 展开单个章节。
         *
         * @param Linked3_Book_Project_State $state           项目状态。
         * @param int                         $chapter_index  章索引。
         * @param int                         $section_index  节索引。
         * @return array|WP_Error 返回展开结果或 WP_Error。
         */
        public function expand_section( $state, $chapter_index, $section_index ) : mixed {
                // 委托给 Section_Expander Trait 的方法。
                return $this->do_expand_section( $state, $chapter_index, $section_index );
        }

        /**
         * 重新生成指定章节。
         *
         * @param Linked3_Book_Project_State $state           项目状态。
         * @param int                         $chapter_index  章索引。
         * @param int                         $section_index  节索引。
         * @return array|WP_Error
         */
        public function regenerate_section( $state, $chapter_index, $section_index ) : mixed {
                return $this->do_regenerate_section( $state, $chapter_index, $section_index );
        }
}
