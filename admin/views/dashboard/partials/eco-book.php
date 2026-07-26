<?php
/**
 * 写书式学习垂直模块 v18.5 — 完整体系 + 写书工厂
 *
 * v18.5 新增: 写书工厂控制台 (YAML驱动6步自动执行)
 * 保留: v17.2 手动模式 (提示词生成器, 折叠在"手动模式"标签页)
 *
 * 核心哲学: 痛苦精进法 = 自学 + 写书式学习 + 精深练习
 * 第一性原理: 好书都是改出来的
 * 方法论: 写书式学习 = AI搭架子 + 语音主力 + 手写辅助
 * 目标: 每个人每年写3-5本电子书
 *
 * 6步流程: ①AI演示 → ②探索主题 → ③撰写大纲 → ④扩写小节 → ⑤完成初稿 → ⑥阅读修改
 *
 * @package Linked3
 * @version 18.5.0
 */
if (!defined('ABSPATH')) exit;
$nonce_book = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');

// 加载写书式学习完整知识库
$book_kb = [];
$book_kb_path = LINKED3_DIR . 'src/Classes/ContentWriter/book_templates/_index.json';
if (file_exists($book_kb_path)) {
    $book_kb = json_decode(file_get_contents($book_kb_path), true) ?: [];
}
$types = $book_kb['types'] ?? [];
$thinking_modes = $book_kb['six_steps']['step4_expand']['thinking_modes'] ?? [];
$tools = $book_kb['tools'] ?? [];
$core = $book_kb['core_philosophy'] ?? [];
$knowledge_systems = $book_kb['knowledge_systems'] ?? [];
$reading_prompts = $book_kb['reading_prompts'] ?? [];

// v18.5: 写书工厂路由表
$factory_types  = class_exists('TypeModeRouter') ? TypeModeRouter::get_all_types() : [];
$factory_modes  = class_exists('TypeModeRouter') ? TypeModeRouter::get_all_modes() : [];
$factory_levels = class_exists('TypeModeRouter') ? TypeModeRouter::get_all_iteration_levels() : [];
$factory_nonce  = wp_create_nonce('linked3_book_factory');
$current_project_id = isset($_GET['book_project']) ? sanitize_text_field($_GET['book_project']) : '';
$progress_nonce = $current_project_id && class_exists('BookAjaxActions') ? BookAjaxActions::generate_progress_nonce($current_project_id) : '';
?>

<?php // v29.1.0 Step 5: Template split into 2 partials
include __DIR__ . '/eco-book-main.php';
include __DIR__ . '/eco-book-progress.php';
