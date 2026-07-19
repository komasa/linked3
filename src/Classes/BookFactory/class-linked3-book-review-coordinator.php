<?php
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



        use \Linked3\Classes\BookFactory\Traits\Linked3_Review_Linker;



if ( ! defined( 'ABSPATH' ) ) {
        exit;
}
/**
 * Class Linked3_Book_Review_Coordinator
 */
class Linked3_Book_Review_Coordinator {
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
         * 执行审校。
         *
         * @param Linked3_Book_Project_State $state 项目状态。
         * @return array|WP_Error 返回审校结果或 WP_Error。
         */
        public function review( $state ) : mixed {
                // 委托给 Review_Linker Trait 的方法。
                return $this->do_review( $state );
        }
}
