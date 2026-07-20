<?php

declare(strict_types=1);
/**
 * BookFactory 步骤注册表 (v18.11 新增)
 *
 * 替代 v18.10.3 中 Book_Factory::run_step() 的 switch-case 硬编码路由。
 * 步骤通过 register() 注册, run_step() 通过 get_step() 查找对应处理器。
 *
 * 支持第三方插件通过 linked3_book_register_step 钩子注册自定义步骤,
 * 实现步骤的可插拔扩展。
 *
 * @package Linked3\BookFactory
 * @since   18.11
 */

// Exit if accessed directly.
namespace Linked3\Classes\BookFactory;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class BookStepRegistry
 */
class BookStepRegistry {

        /**
         * 已注册的步骤实例。
         *
         * @var array<string, BookStepInterface>
         */
        private static $steps = array();

        /**
         * 步骤顺序 (用于排序与链式调度)。
         *
         * @var array<string>
         */
        private static $step_order = array();

        /**
         * 是否已初始化。
         *
         * @var bool
         */
        private static $initialized = false;

        /**
         * 初始化注册表, 从 steps.yaml 加载步骤并触发钩子允许第三方扩展。
         */
        public static function init() : void {
                if ( self::$initialized ) {
                        return;
                }
                self::$initialized = true;

                // v19.0: 从 steps.yaml 配置文件加载步骤定义。
                $steps_config = self::load_steps_config();

                if ( ! empty( $steps_config ) ) {
                        foreach ( $steps_config as $step_config ) {
                                // 跳过禁用的步骤。
                                if ( isset( $step_config['enabled'] ) && ! $step_config['enabled'] ) {
                                        continue;
                                }
                                self::register( new BookStepAdapter(
                                        $step_config['id'],
                                        $step_config['label'],
                                        $step_config['method'],
                                        $step_config['next'] ?? null
                                ) );
                        }
                } else {
                        // 回退: 如果 YAML 加载失败, 使用硬编码默认值。
                        self::register( new BookStepAdapter( 'step1_demo', '演示', 'execute_step1_demo', 'step2_explore' ) );
                        self::register( new BookStepAdapter( 'step2_explore', '探索', 'execute_step2_explore', 'step3_outline' ) );
                        self::register( new BookStepAdapter( 'step3_outline', '大纲', 'execute_step3_outline_iter', 'step4_expand' ) );
                        self::register( new BookStepAdapter( 'step4_expand', '扩写', 'execute_step4_expand_one', 'step5_complete' ) );
                        self::register( new BookStepAdapter( 'step5_complete', '拼接', 'execute_step5_complete', 'step6_review' ) );
                        self::register( new BookStepAdapter( 'step6_review', '审阅', 'execute_step6_review', null ) );
                }

                // 触发钩子, 允许第三方插件注册自定义步骤。
                do_action( 'linked3_book_register_step', self::class );
        }

        /**
         * v19.0: 从 steps.yaml 加载步骤配置。
         *
         * @return array 步骤配置数组, 加载失败返回空数组。
         */
        private static function load_steps_config() : mixed {
                $config_path = LINKED3_DIR . 'src/Classes/BookFactory/config/steps.yaml';

                if ( ! file_exists( $config_path ) ) {
                        return array();
                }

                $yaml_content = file_get_contents( $config_path );
                // v19.54: 检查 file_get_contents 失败
                if ( $yaml_content === false ) {
                        return array();
                }

                // 使用 Symfony YAML 解析器 (如果可用)。
                if ( class_exists( 'Symfony\Component\Yaml\Yaml' ) ) {
                        $parsed = \Symfony\Component\Yaml\Yaml::parse( $yaml_content );
                        return $parsed['steps'] ?? array();
                }

                // 回退: 简易 YAML 解析 (仅支持 steps 列表格式)。
                return self::parse_simple_yaml( $yaml_content );
        }

        /**
         * v19.0: 简易 YAML 解析器 (无需外部依赖)。
         *
         * 仅支持 steps.yaml 的特定格式, 不支持完整 YAML 规范。
         * 用于无 Symfony YAML 组件的环境。
         *
         * @param string $content YAML 内容。
         * @return array 步骤配置数组。
         */
        private static function parse_simple_yaml( $content ) : mixed {
                $steps   = array();
                $current = array();
                $in_list = false;

                $lines = explode( "\n", $content );
                foreach ( $lines as $line ) {
                        // 跳过注释与空行。
                        $trimmed = trim( $line );
                        if ( '' === $trimmed || '#' === $trimmed[0] ) {
                                continue;
                        }

                        // 检测列表项开始 (- id: xxx)。
                        if ( preg_match( '/^-\s+id:\s*(.+)$/', $line, $m ) ) {
                                if ( ! empty( $current ) ) {
                                        $steps[] = $current;
                                }
                                $current  = array( 'id' => trim( $m[1] ) );
                                $in_list  = true;
                                continue;
                        }

                        // 解析键值对 (label:, method:, next:, enabled:)。
                        if ( $in_list && preg_match( '/^\s+(\w+):\s*(.*)$/', $line, $m ) ) {
                                $key   = $m[1];
                                $value = trim( $m[2] );
                                if ( 'null' === $value || '' === $value ) {
                                        $value = null;
                                } elseif ( 'true' === $value ) {
                                        $value = true;
                                } elseif ( 'false' === $value ) {
                                        $value = false;
                                }
                                $current[ $key ] = $value;
                        }
                }

                if ( ! empty( $current ) ) {
                        $steps[] = $current;
                }

                return $steps;
        }

        /**
         * 注册步骤。
         *
         * @param BookStepInterface $step 步骤实例。
         */
        public static function register( BookStepInterface $step ) : void {
                $step_id = $step->get_step_id();
                self::$steps[ $step_id ]      = $step;
                self::$step_order[ $step_id ] = count( self::$step_order );
        }

        /**
         * 获取步骤实例。
         *
         * @param string $step_id 步骤 ID。
         * @return BookStepInterface|null
         */
        public static function get_step( $step_id ) {
                self::init();
                return self::$steps[ $step_id ] ?? null;
        }

}
