<?php

declare(strict_types=1);
/**
 * Linked3 OSWidget 15.0.0-rc8
 *
 * 小工具(Widget)
 *
 * 侧边栏小工具: 入流进度/质量门禁
 *
 * @package Linked3\Integration
 * @since 15.0.0-rc8
 * @version 15.0.0-rc8
 */

namespace Linked3\Classes\OS\Api;

/**
 * OS Module — OS Widget
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Api/V18Widget.php
 * Original class: V18_Widget
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSWidget extends \WP_Widget {


    /**
     * 注册小工具
     */
    public static function register_widget(): void {
        register_widget('OSWidget');
    }

    /**
     * 小工具构造
     */
    public function __construct() {
        parent::__construct(
            'linked3_v18_widget',
            'V18集成面板',
            ['description' => '显示V18模块状态和入流进度']
        );
    }

    /**
     * 前端显示
     */
    public function widget($args, $instance): void {
        printf('%s', $args['before_widget']); // WordPress widget args — trusted HTML from theme
        
        if (!empty($instance['title'])) {
            printf('%s', $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title']); // WordPress widget args — trusted HTML from theme
        }
        
        echo '<div class="linked3-v18-widget">';
        
        // 显示模块状态
        if ($instance['show_modules'] ?? true) {
            echo self::render_widget_modules();
        }
        
        // 显示入流进度
        if ($instance['show_ruliu'] ?? false) {
            echo self::render_widget_ruliu();
        }
        
        echo '</div>';
        printf('%s', $args['after_widget']); // WordPress widget args — trusted HTML from theme
    }

    /**
     * 后台表单
     */
    public function form($instance): void {
        $title = $instance['title'] ?? 'V18集成面板';
        $show_modules = $instance['show_modules'] ?? true;
        $show_ruliu = $instance['show_ruliu'] ?? false;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">标题:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <input class="checkbox" type="checkbox"
                   id="<?php echo esc_attr($this->get_field_id('show_modules')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_modules')); ?>"
                   <?php checked($show_modules); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_modules')); ?>">显示模块状态</label>
        </p>
        <p>
            <input class="checkbox" type="checkbox"
                   id="<?php echo esc_attr($this->get_field_id('show_ruliu')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_ruliu')); ?>"
                   <?php checked($show_ruliu); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_ruliu')); ?>">显示入流进度</label>
        </p>
        <?php
    }

    /**
     * 更新实例
     */
    public function update($new_instance, $old_instance): array {
        $instance = [];
        $instance['title'] = sanitize_text_field($new_instance['title'] ?? '');
        $instance['show_modules'] = isset($new_instance['show_modules']);
        $instance['show_ruliu'] = isset($new_instance['show_ruliu']);
        return $instance;
    }

    /**
     * 渲染模块状态
     */
    private static function render_widget_modules(): string {
        $modules = [
            'Linked3_Reverse_Engine',
            'Linked3_Neng_Suo_Structure',
            'Linked3_Svg_Meta_Stats',
            'Linked3_Three_Layer_Consciousness',
            'Linked3_Ru_Liu_Tracker',
        ];
        
        $html = '<div class="widget-modules"><h4>模块状态</h4><ul>';
        foreach ($modules as $class) {
            $short_name = str_replace('Linked3_', '', $class);
            $status = class_exists($class) ? '✓' : '✗';
            $html .= '<li>' . $status . ' ' . esc_html($short_name) . '</li>';
        }
        $html .= '</ul></div>';
        return $html;
    }

    /**
     * 渲染入流进度
     */
    private static function render_widget_ruliu(): string {
        if (!class_exists('\Linked3\Classes\OS\Api\Linked3_Ru_Liu_Tracker')) {
            return '<div class="widget-ruliu">入流追踪器未加载</div>';
        }
        
        $states = Linked3_Ru_Liu_Tracker::get_ru_liu_states();
        $html = '<div class="widget-ruliu"><h4>入流四状态</h4><ul>';
        foreach ($states as $key => $state) {
            $html .= '<li>' . esc_html($state['label'] ?? $key) . '</li>';
        }
        $html .= '</ul></div>';
        return $html;
    }

    /**
     * 注册
     */
    public static function register(): void {
        add_action('widgets_init', [__CLASS__, 'register_widget']);
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'module_version' => '15.0.0-rc8',
            'title' => '小工具(Widget)',
            'widget_id' => 'linked3_v18_widget',
        ];
    }

}

// 注册模块
if (class_exists('\Linked3\Classes\OS\Api\OSWidget')) {
    add_action('init', ['\Linked3\Classes\OS\Api\OSWidget', 'register'], 10);
}
