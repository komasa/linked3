<?php

declare(strict_types=1);
/**
 * 小红书脚本生成器 — v19.2 视觉生态新成员.
 *
 * 吸收独立小红书生成器的精华模式，融入 Linked3 AI 插件生态：
 *   - 结构化 JSON 输出（title + main_content + pages[]）
 *   - 多页图文架构（3-8 页，每页含标题+正文+配图提示词）
 *   - 风格定制（预设风格 + 自定义风格提示词）
 *   - 封面特殊处理（首页获得增强提示词）
 *   - V15 八维度上下文集成
 *   - 平台适配（小红书特有：emoji、话题标签、3:4 比例）
 *
 * @package Linked3
 * @subpackage Classes\XHS
 */

namespace Linked3\Classes\XHS;

use Linked3\Classes\Visual\VisualScriptGeneratorInterface;
use Linked3\Classes\Core\AIDispatcher;



if (!defined('ABSPATH')) {
    exit;
}
final class XHSGenerator implements VisualScriptGeneratorInterface
{
    /**
     * 小红书系统提示词模板 — 吸收独立生成器的 prompt 精华。
     */
    const PROMPT_TEMPLATE = <<<'PROMPT'
你是一位专业的小红书内容创作者，精通爆款图文笔记的创作技巧。请为以下主题生成一篇小红书图文笔记。

【创作要求】
1. 标题：15-20字，使用emoji点缀，制造好奇心或价值感
2. 正文：200-400字，分段清晰，每段2-3句，使用emoji作为段落标记
3. 页面数：{page_count}
4. 每页结构：标题(10-15字) + 内容(50-100字) + 配图提示词(英文，详细描述画面)
5. 封面页（第1页）：标题要最吸引眼球，配图提示词要描述最具视觉冲击力的画面
6. 语气：{tone}
7. 结尾添加3-5个相关话题标签（#格式）
8. 适当使用emoji但不要过度（每段1-2个）

【V15 视觉系统上下文】
- 品牌调性: {brand}
- 色彩体系: {color}
- 风格调性: {mood}
- 文化背景: {culture}
- 信息密度: {density}

【配图提示词要求】
- 必须用英文描述
- 包含：主体 + 场景 + 光线 + 色调 + 构图 + 风格
- 封面页配图要最具吸引力
- 比例：3:4 竖版（小红书标准）

【输出格式】
严格输出以下JSON格式，不要包含任何其他文字：
{
  "title": "笔记标题（含emoji）",
  "main_content": "正文摘要（用于笔记描述）",
  "tags": ["标签1", "标签2", "标签3"],
  "pages": [
    {
      "title": "第1页标题（封面）",
      "content": "第1页正文内容",
      "image_prompt": "English prompt for cover image, detailed scene description",
      "is_cover": true
    },
    {
      "title": "第2页标题",
      "content": "第2页正文内容",
      "image_prompt": "English prompt for this page image",
      "is_cover": false
    }
  ]
}

主题: {topic}
关键词: {keyword}
风格: {style}
自定义风格: {custom_style}
PROMPT;

    /**
     * 预设风格列表 — 吸收小红书平台的流行风格模式。
     */
    const STYLES = [
        [
            'id'           => 'lifestyle',
            'label'        => '生活方式',
            'prompt_suffix'=> '生活化、真实感、日常分享风格，像朋友间的推荐',
        ],
        [
            'id'           => 'tutorial',
            'label'        => '教程干货',
            'prompt_suffix'=> '干货教程风格，步骤清晰、重点突出、实用性强',
        ],
        [
            'id'           => 'aesthetic',
            'label'        => '美学文艺',
            'prompt_suffix'=> '文艺美学风格，意境优美、文字精致、画面感强',
        ],
        [
            'id'           => 'trending',
            'label'        => '热门爆款',
            'prompt_suffix'=> '爆款网感风格，标题党、情绪共鸣、话题性强',
        ],
        [
            'id'           => 'professional',
            'label'        => '专业科普',
            'prompt_suffix'=> '专业科普风格，权威感、数据支撑、逻辑清晰',
        ],
        [
            'id'           => 'story',
            'label'        => '故事叙事',
            'prompt_suffix'=> '故事叙事风格，有情节、有转折、有情感',
        ],
        [
            'id'           => 'compare',
            'label'        => '对比测评',
            'prompt_suffix'=> '对比测评风格，客观比较、优缺点分析、给出建议',
        ],
        [
            'id'           => 'checkin',
            'label'        => '打卡探店',
            'prompt_suffix'=> '探店打卡风格，现场感、体验感、推荐指数',
        ],
    ];

    /**
     * 生成小红书脚本。
     *
     * @param array $params
     * @return array|\WP_Error
     */
    public function generate_script(array $params) : mixed {
        $topic       = sanitize_text_field($params['topic'] ?? '');
        $keyword     = sanitize_text_field($params['keyword'] ?? '');
        $style_id    = sanitize_text_field($params['style'] ?? 'lifestyle');
        $custom_style = sanitize_textarea_field($params['custom_style'] ?? '');
        $page_count  = (int) ($params['page_count'] ?? 0);
        $model       = sanitize_text_field($params['model'] ?? '');
        $v15         = $params['v15_context'] ?? [];

        if (empty($topic) && empty($keyword)) {
            return new \WP_Error('missing_input', __('需要主题或关键词。', 'linked3'), ['status' => 400]);
        }

        // 自动页数：3-6 页
        if ($page_count <= 0) {
            $page_count = 5;
        }
        $page_count = max(3, min(8, $page_count));

        // 解析风格
        $style_prompt = '';
        foreach (self::STYLES as $s) {
            if ($s['id'] === $style_id) {
                $style_prompt = $s['prompt_suffix'];
                break;
            }
        }
        if (!empty($custom_style)) {
            $style_prompt .= "\n自定义风格要求: " . $custom_style;
        }

        // V15 上下文
        $brand   = $v15['brand'] ?? '通用';
        $color   = $v15['color'] ?? '温暖明亮';
        $mood    = $v15['mood'] ?? '亲切自然';
        $culture = $v15['culture'] ?? '中文互联网';
        $density = $v15['density'] ?? '中等';

        // 语气映射
        $tone_map = [
            'lifestyle'   => '亲切自然，像闺蜜分享',
            'tutorial'    => '专业但易懂，像老师教学',
            'aesthetic'   => '文艺优雅，有画面感',
            'trending'    => '活泼网感，有梗有料',
            'professional'=> '权威专业，有理有据',
            'story'       => '叙事感强，有情感共鸣',
            'compare'     => '客观中立，有数据感',
            'checkin'     => '现场体验感，真实生动',
        ];
        $tone = $tone_map[$style_id] ?? '亲切自然';

        // 构建提示词
        $prompt = strtr(self::PROMPT_TEMPLATE, [
            '{topic}'        => $topic ?: $keyword,
            '{keyword}'      => $keyword,
            '{page_count}'   => $page_count,
            '{style}'        => $style_prompt ?: '自由发挥',
            '{custom_style}' => $custom_style ?: '无',
            '{tone}'         => $tone,
            '{brand}'        => $brand,
            '{color}'        => $color,
            '{mood}'         => $mood,
            '{culture}'      => $culture,
            '{density}'      => $density,
        ]);

        // 获取模型
        if (empty($model)) {
            $model = get_option(LINKED3_OPTION_PREFIX . 'default_chat_model', 'gpt-4o-mini');
        }

        // 调用 AI — v19.2.1 修复：chat() 签名为 ($messages, $options, $config) 三参，
        // 旧代码误传单数组导致 PHP ArgumentCountError → WP fatal handler 输出
        // "<p>There has been a critical error...</p>" → 前端 JSON.parse 失败。
        // 同时改用 ::instance() 单例（构造器为 private，new 会再触发 fatal）。
        $dispatcher = AIDispatcher::instance();
        // v19.40: 绞杀模式 — system_prompt 通过 apply_filters 可被元提示词杠杆增强
        $base_system_prompt = '你是专业的小红书内容创作者，精通爆款图文笔记创作。必须严格输出JSON格式。';
        $system_prompt = apply_filters('linked3_xhs_system_prompt', $base_system_prompt, $params);
        $messages = [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user',   'content' => $prompt],
        ];
        $options = [
            'model'       => $model,
            'temperature' => 0.8,
            'max_tokens'  => 3000,
            'module'      => 'xhs',
            'user_id'     => get_current_user_id(),
        ];
        $config = ['fallback_providers' => ['deepseek', 'zhipu']];

        try {
            $result = $dispatcher->chat($messages, $options, $config);
        } catch (\RuntimeException $e) {
            return new \WP_Error('ai_failed', $e->getMessage(), ['status' => 502]);
        }

        // Dispatcher 成功返回 ['content','usage','provider','model','raw']；
        // 失败时抛异常（已在上方捕获），不再有 $result['ok'] 字段。

        // 解析 JSON（容错处理 — 吸收小红书生成器的 JSON 提取模式）
        $content = $result['content'] ?? '';
        $parsed = $this->parse_json_response($content);

        if ($parsed === null) {
            return new \WP_Error('parse_failed', __('AI 返回内容无法解析为 JSON。', 'linked3'), ['status' => 500]);
        }

        // 规范化输出
        return $this->normalize_output($parsed, $page_count);
    }

    /**
     * 容错 JSON 解析 — 吸收小红书生成器的 JSON 提取模式。
     * 尝试多种方式从 AI 返回文本中提取 JSON。
     *
     * @param string $content
     * @return array|null
     */
    private function parse_json_response($content) : mixed     {
        // 尝试 1: 直接 json_decode
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // 尝试 2: 提取 ```json ... ``` 代码块
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // 尝试 3: 提取第一个 { ... } 块
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // 尝试 4: 移除可能的 BOM + 重试
        $cleaned = str_replace("\xEF\xBB\xBF", '', $content);
        $decoded = json_decode($cleaned, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return null;
    }

    /**
     * 规范化输出 — 确保所有字段存在且格式正确。
     *
     * @param array $data
     * @param int $expected_pages
     * @return array
     */
    private function normalize_output($data, $expected_pages)
    : array {
        $title = (string) ($data['title'] ?? '');
        $main_content = (string) ($data['main_content'] ?? '');
        $tags = (array) ($data['tags'] ?? []);

        $pages = [];
        $raw_pages = (array) ($data['pages'] ?? []);

        foreach ($raw_pages as $i => $page) {
            $pages[] = [
                'title'        => (string) ($page['title'] ?? '第' . ($i + 1) . '页'),
                'content'      => (string) ($page['content'] ?? ''),
                'image_prompt' => (string) ($page['image_prompt'] ?? ''),
                'is_cover'     => $i === 0 ? true : (bool) ($page['is_cover'] ?? false),
            ];
        }

        // 如果页数不足，补充空页
        while (count($pages) < $expected_pages) {
            $idx = count($pages);
            $pages[] = [
                'title'        => '第' . ($idx + 1) . '页',
                'content'      => '',
                'image_prompt' => '',
                'is_cover'     => false,
            ];
        }

        return [
            'title'        => $title,
            'main_content' => $main_content,
            'tags'         => $tags,
            'pages'        => $pages,
            'platform'     => $this->platform(),
        ];
    }

    public function platform()
    : string {
        return 'xhs';
    }

    public function platform_label() : mixed {
        return __('小红书图文', 'linked3');
    }

    public function default_prompt_template() : mixed     {
        return self::PROMPT_TEMPLATE;
    }

    public function available_styles()
    {
        return self::STYLES;
    }
}
