<?php

declare(strict_types=1);
/**
 * Linked3 Book Factory — 写书工厂门面 (v19.0: 外观模式)
 *
 * v18.x: 上帝类, 1420 行, 承担流程编排+AI调用+成本核算+草稿重建等多重职责
 * v19.0: 保留为向后兼容的外观类 (Facade), 静态 API 不变,
 *        新代码应使用 BookPipelineOrchestrator (依赖注入, 可测试)
 *
 * 方案: S5 (G1) + S15 (G2内置State) + S16 (G2断点续作) + S17 (G2速率控制)
 * 公理: 工厂门面 — 对外暴露 run_step()，内部协调6步管线
 *
 * @package Linked3\BookFactory
 * @since   18.5.0
 * @deprecated 19.0 新代码请使用 BookPipelineOrchestrator
 */

namespace Linked3\Classes\BookFactory;



    use \Linked3\Classes\BookFactory\Traits\OutlineMerger;
    use \Linked3\Classes\BookFactory\Traits\SectionExpander;
    use \Linked3\Classes\BookFactory\Traits\ReviewLinker;
    use \Linked3\Classes\BookFactory\Traits\CostTracker;



if ( ! defined( 'ABSPATH' ) ) exit;
// 显式加载 Trait (自动加载器无法解析 trait 的 Old_Style_Prefixed 命名)
$trait_dir = __DIR__ . '/Traits/';
require_once $trait_dir . 'OutlineMerger.php';
require_once $trait_dir . 'SectionExpander.php';
require_once $trait_dir . 'ReviewLinker.php';
require_once $trait_dir . 'CostTracker.php';

class BookFactory {

    /** @var array 管线配置 (从 book.yaml 加载) */
    private $pipeline_config = null;

    /** @var BookProjectState 项目状态 */
    private $state = null;

    /** @var array 路由配置 */
    private $route = null;

    /** @var float 上次AI调用时间戳 (S17 速率控制) */
    private $last_api_call = 0;

    /**
     * 执行管线 (wp_cron 触发)
     *
     * v19.0.1: execute() 已删除, 改为委托 run_step() (单步执行 + 异步链式调度)
     *
     * @param string $project_id
     */
    public static function run_pipeline(string $project_id) : void {
        self::run_step( $project_id );
    }

    /**
     * v18.7: 智能路由分步执行 (每次只1次AI调用, 避免PHP超时)
     *
     * 根据 current_step 路由到对应步骤:
     *   step1_demo    → 执行1次AI演示, 完成后→step2_explore
     *   step2_explore → 执行1次AI探索, 完成后→step3_outline
     *   step3_outline → 执行1次大纲迭代, 迭代完→step4_expand
     *   step4_expand  → 执行1节扩写, 全部完→step5_complete
     *   step5_complete→ 拼接书稿(零AI), →step6_review
     *   step6_review  → 执行1次AI审阅, →done
     *
     * @param string $project_id
     * @return array|WP_Error
     */
    public static function run_step(string $project_id): array|WP_Error {
        // v19.0: 委托到 BookPipelineOrchestrator (外观模式)
        $orchestrator = new BookPipelineOrchestrator();
        return $orchestrator->run_step($project_id);
    }

    /**
     * v18.7: 执行step1演示 (1次AI调用)
     */
        public function execute_step1_demo( $state ) { $s = new BookFactorySteps(); return $s->execute_step1_demo($state); }

    /**
     * v18.7: 执行step2探索 (1次AI调用)
     */
        public function execute_step2_explore( $state ) { $s = new BookFactorySteps(); return $s->execute_step2_explore($state); }

    /**
     * v18.7: 执行step3单次大纲迭代 (1次AI调用)
     */
        public function execute_step3_outline_iter( $state ) { $s = new BookFactorySteps(); return $s->execute_step3_outline_iter($state); }

    /**
     * v18.7: 执行step4单节扩写 (1次AI调用)
     */
        public function execute_step4_expand_one( $state ) { $s = new BookFactorySteps(); return $s->execute_step4_expand_one($state); }

    /**
     * v18.7: 执行step5拼接 (零AI调用)
     */
        public function execute_step5_complete( $state ) { $s = new BookFactorySteps(); return $s->execute_step5_complete($state); }

    /**
     * v18.7: 执行step6审阅 (1次AI调用)
     */
        public function execute_step6_review( $state ) { $s = new BookFactorySteps(); return $s->execute_step6_review($state); }

    /**
     * 加载管线配置 (book.yaml)
     *
     * @return array
     */
        public function load_pipeline_config(): array { return BookFactoryUtils::load_pipeline_config(); }

    private function smart_split_outline( $content ): array {
        return BookFactoryUtils::smart_split_outline( $content );
    }
    private function parse_outline( $content ): array {
        return BookFactoryUtils::parse_outline( $content );
    }
    private function call_ai_with_rate_limit( $prompt ): array {
        $min_interval = 1.0;
        $elapsed = microtime( true ) - $this->last_api_call;
        if ( $elapsed < $min_interval ) usleep( (int) ( ( $min_interval - $elapsed ) * 1000000 ) );
        $this->last_api_call = microtime( true );
        try {
            $dispatcher = AIDispatcher::instance();
            $messages = array( array( 'role' => 'user', 'content' => $prompt ) );
            $options = array( 'temperature' => 0.7, 'max_tokens' => 4096 );
            $config = TokenManager::get_active_config();
            $response = $dispatcher->chat( $messages, $options, $config );
        } catch ( \Throwable $e ) {
            throw new \RuntimeException( 'AI call failed: ' . $e->getMessage(), 0, $e );
        }
        if ( is_wp_error( $response ) ) return $response;
        $content = ''; $tokens_in = 0; $tokens_out = 0;
        if ( isset( $response['choices'][0]['message']['content'] ) ) $content = $response['choices'][0]['message']['content'];
        elseif ( isset( $response['content'] ) ) $content = $response['content'];
        if ( isset( $response['usage']['prompt_tokens'] ) ) $tokens_in = intval( $response['usage']['prompt_tokens'] );
        if ( isset( $response['usage']['completion_tokens'] ) ) $tokens_out = intval( $response['usage']['completion_tokens'] );
        $cost = $this->calculate_cost( $tokens_in, $tokens_out );
        $this->log_cost_to_state( $this->state, 'ai_call', $tokens_in, $tokens_out, $cost );
        return array( 'content' => $content, 'tokens_in' => $tokens_in, 'tokens_out' => $tokens_out, 'cost' => $cost );
    }
}