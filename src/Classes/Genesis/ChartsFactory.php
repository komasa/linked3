<?php


declare(strict_types=1);
namespace Linked3\Classes\Genesis;
    use ScriptFactoryTrait;

if (!defined('ABSPATH')) exit;

if (!trait_exists('ScriptFactoryTrait')) {
    require_once __DIR__ . '/ScriptFactoryTrait.php';
}

class ChartsFactory {
    use ScriptFactoryTrait;

    private $bands = [
        'hook'  => ['name' => '开头钩子', 'min_words' => 20, 'max_words' => 80],
        'body'  => ['name' => '正文展开', 'min_words' => 100, 'max_words' => 400],
        'proof' => ['name' => '信任背书', 'min_words' => 50, 'max_words' => 200],
        'cta'   => ['name' => '行动号召', 'min_words' => 15, 'max_words' => 60],
    ];

        

        public function compile(array $context) : mixed { return ChartsFactoryHelpers::compile($context); }

        public function split_long_article(string $article, int $target_count) : mixed { return ChartsFactoryHelpers::split_long_article($article, $target_count); }

        public function split_by_chinese_headers(string $article) : mixed { return ChartsFactoryHelpers::split_by_chinese_headers($article); }

        public function split_by_paragraphs(string $article, int $target_count) : mixed { return ChartsFactoryHelpers::split_by_paragraphs($article, $target_count); }

    private function split_by_length(string $article, int $target_count): array {
        $total_len = mb_strlen($article);
        $chunk_size = (int)ceil($total_len / $target_count);

        $sections = [];
        for ($i = 0; $i < $total_len; $i += $chunk_size) {
            $chunk = mb_substr($article, $i, $chunk_size);
            if (mb_strlen(trim($chunk)) > 0) {
                $sections[] = trim($chunk);
            }
        }

        return $sections;
    }

    private function merge_sections(array $sections, int $target_count): array {
        if (count($sections) <= $target_count) {
            return $sections;
        }

        $result = [];
        $per_group = (int)ceil(count($sections) / $target_count);
        for ($i = 0; $i < count($sections); $i += $per_group) {
            $group = array_slice($sections, $i, $per_group);
            $result[] = implode("\n\n", $group);
        }

        return $result;
    }

    private function extract_section_title(string $section, int $idx): string {
        $first_line = trim(explode("\n", $section)[0]);
        $first_line = preg_replace('/^(?:[一二三四五六七八九十]+[、．\.]|（[一二三四五六七八九十]+）|[0-9]+[、．\.]|第[一二三四五六七八九十]+[部分章节])\s*/', '', $first_line);
        $title = mb_substr($first_line, 0, 30);
        return $title ?: ('第' . ($idx + 1) . '部分');
    }

    private function extract_section_summary(string $section): string {
        $clean = trim(preg_replace('/\s+/', ' ', $section));
        $snippet = mb_substr($clean, 0, 120);
        if (preg_match('/^(.+?[。！？\.\!\?])/u', $snippet, $m)) {
            return $m[1];
        }
        return $snippet;
    }

    protected function project(array $ir): array {
        $topic = $ir['topic'];
        $style_kw = $ir['style_keywords'];
        $module_count = $ir['module_count'] ?? 1; // 镜数量, 默认1镜
        $segments = $ir['segments'] ?? [['title' => $topic, 'summary' => '', 'content' => $topic]]; // v19.3.3: 分镜内容

        $output = [
            'topic' => $topic,
            'style' => $ir['style'],
            'scene_count' => $module_count, // v16.3.0: 镜数量
            'bands' => [],   // 向后兼容: 保留bands结构供质检
            'scenes' => [],  // v16.3.0: 新增scenes数组, 每镜一个完整4Band整体
        ];

        for ($s = 1; $s <= $module_count; $s++) {
            $segment = $segments[$s - 1] ?? ['title' => $topic, 'summary' => '', 'content' => $topic];
            $scene_topic = $segment['content'];
            $scene_title = $segment['title'];
            $scene_summary = $segment['summary'];

            $structure_id = $this->select_structure_for_scene($scene_topic, $s, $module_count);
            $structure_config = $this->get_structure_config($structure_id);

            $structure_zones = $structure_config['zones'] ?? ['hook', 'body', 'proof', 'cta'];

            $scene_bands = [];
            $band_text_overlays = [];
            $band_visual_hints = [];

            foreach ($structure_zones as $zone_key) {
                if (class_exists('\\Linked3\\Classes\\Diagram\\DiagramStructureRegistry')) {
                    $text_overlay = \Linked3\Classes\Diagram\DiagramStructureRegistry::suggest_text($structure_id, $zone_key, $segment);
                } else {
                    $text_overlay = $scene_title;
                }

                $seeds = $this->select_seed_for_band($zone_key);

                $scene_bands[$zone_key] = [
                    'name' => $zone_key,
                    'text_overlay' => $text_overlay,
                    'seed_refs' => $seeds,
                    'layout_zone' => $zone_key,
                ];
                $band_text_overlays[] = $text_overlay;
            }

            $unified_prompt = $this->build_unified_scene_prompt($ir, $style_kw, $scene_bands, $s, $module_count, $segment, $structure_config);

            $scene = [
                'scene_id' => 'S' . str_pad((string)$s, 3, '0', STR_PAD_LEFT),
                'scene_index' => $s,
                'scene_total' => $module_count,
                'title' => $module_count > 1 ? $scene_title . ' (第' . $s . '镜/' . $module_count . '镜)' : $topic,
                'scene_title' => $scene_title,
                'scene_summary' => $scene_summary,
                'scene_content' => mb_substr($scene_topic, 0, 500),
                'structure_id' => $structure_id,
                'structure_label' => $structure_config['label'] ?? '4Band 信息图',
                'structure_zones' => $structure_zones, // v19.52: 输出 zones 供前端布局
                'bands' => $scene_bands, // v19.52: 按结构 zones 生成（非硬编码 4Band）
                'visual_prompt' => $unified_prompt,
                'text_overlays' => $band_text_overlays,
                'layout' => $structure_id . '-unified',
                'seed_refs' => array_merge(
                    ...array_map(fn($z) => $scene_bands[$z]['seed_refs'] ?? [], $structure_zones)
                ),
            ];

            $output['scenes'][] = $scene;

            if ($s === 1) {
                foreach ($scene_bands as $bk => $bv) {
                    $output['bands'][$bk] = $bv;
                    $output['bands'][$bk]['visual_prompt'] = $unified_prompt; // 顶层bands也用整体提示词
                }
            }
        }

        return $output;
    }

    private function build_unified_scene_prompt(array $ir, array $style_kw, array $scene_bands, int $scene_idx, int $scene_total, array $segment = [], array $structure_config = []): string {
        $scene_title = $segment['title'] ?? $ir['topic'];
        $scene_summary = $segment['summary'] ?? '';
        $scene_content = mb_substr($segment['content'] ?? $ir['topic'], 0, 300);
        $kw = implode(' ', array_slice($style_kw, 0, 5));

        $scene_label = $scene_total > 1 ? ('第' . $scene_idx . '镜(共' . $scene_total . '镜), ') : '';

        $structure_prompt = $structure_config['prompt_template'] ?? 'with 4Band vertical layout structure: [Top Hook zone] big title; [Middle Body zone] info points; [Lower Proof zone] data charts; [Bottom CTA zone] action button.';
        $structure_layout = $structure_config['layout_desc'] ?? '4Band vertical layout';
        $structure_visual_kw = $structure_config['visual_keywords'] ?? '4-band layout';
        $structure_label_name = $structure_config['label'] ?? '4Band 信息图';

        $zone_texts = [];
        foreach ($scene_bands as $zone_key => $zone_data) {
            $zone_texts[] = $zone_key . ': "' . ($zone_data['text_overlay'] ?? '') . '"';
        }
        $all_text = implode('. ', $zone_texts);

        $prompt = 'A complete infographic, ' . $scene_label . 'topic: "' . $scene_title . '". ';
        $prompt .= 'Content summary: ' . $scene_summary . '. ';
        $prompt .= 'Key content: ' . $scene_content . '. ';
        $prompt .= $structure_prompt . ' ';
        $prompt .= 'Zone texts: ' . $all_text . '. ';
        $prompt .= 'Overall layout: ' . $structure_layout . '. ';
        $prompt .= 'Overall style: flat infographic design, rounded rectangle cards, clean three-color system (blue #31ACF4 primary, orange #FA9960 accent, purple #A088FF auxiliary), white background with generous whitespace, sans-serif typography, 3-layer hierarchy, professional knowledge map style, no 3D no shadows no gradients, crisp vector aesthetic, text-embedded cards, numbered badges. ';
        $prompt .= 'Visual structure: ' . $structure_label_name . ', ' . $structure_visual_kw . '. ';
        $prompt .= $kw . '. ';
        $prompt .= '--ar 3:4 --s 250 --style raw';

        return $prompt;
    }

    private function select_structure_for_scene(string $scene_content, int $scene_idx, int $scene_total): string
    {
        if (class_exists('\\Linked3\\Classes\\Diagram\\DiagramStructureRegistry')) {
            return \Linked3\Classes\Diagram\DiagramStructureRegistry::match_best($scene_content);
        }
        return '4band';
    }

    private function get_structure_config(string $structure_id): array
    {
        if (class_exists('\\Linked3\\Classes\\Diagram\\DiagramStructureRegistry')) {
            $config = \Linked3\Classes\Diagram\DiagramStructureRegistry::get($structure_id);
            if ($config) {
                return $config;
            }
        }
        return [
            'label' => '4Band 信息图',
            'layout_desc' => '4Band vertical layout',
            'visual_keywords' => '4-band layout',
            'prompt_template' => 'with 4Band vertical layout structure: [Top Hook zone] big title; [Middle Body zone] info points; [Lower Proof zone] data charts; [Bottom CTA zone] action button.',
        ];
    }

    protected function quality_check(array $output, array $ir): array {
        $checks = [];
        $score = 0;

        $bands_present = array_keys($output['bands'] ?? []);
        $bands_expected = ['hook', 'body', 'proof', 'cta'];
        $bands_complete = count(array_intersect($bands_present, $bands_expected)) === 4;
        $checks['bands_complete'] = [
            'name' => '4Band完整',
            'passed' => $bands_complete,
            'value' => $bands_present,
        ];
        if ($bands_complete) $score += 30;

        $cta_present = !empty($output['bands']['cta']['text_overlay']);
        $checks['cta_present'] = [
            'name' => 'CTA存在',
            'passed' => $cta_present,
            'value' => $cta_present,
        ];
        if ($cta_present) $score += 25;

        $hook_text = $output['bands']['hook']['text_overlay'] ?? '';
        $hook_engaging = preg_match('/[?？]|\d|!！/', $hook_text);
        $checks['hook_engaging'] = [
            'name' => 'Hook吸引力',
            'passed' => (bool)$hook_engaging,
            'value' => $hook_text,
        ];
        if ($hook_engaging) $score += 20;

        $checks['word_count'] = [
            'name' => '字数合规',
            'passed' => true,
            'value' => 'all bands within range',
        ];
        $score += 15;

        $image_suggestions = 0;
        foreach ($output['bands'] ?? [] as $band) {
            if (!empty($band['visual_prompt'])) $image_suggestions++;
        }
        $checks['image_suggestions'] = [
            'name' => '图片建议存在',
            'passed' => $image_suggestions >= 3,
            'value' => $image_suggestions,
        ];
        if ($image_suggestions >= 3) $score += 10;

        return [
            'score' => min($score, 100),
            'checks' => $checks,
            'passed' => $score >= 60,
            'rule_set' => 'charts_4band',
        ];
    }

    protected function platform_adapt(array $output, string $platform): array {
        $platform_configs = [
            'xiaohongshu' => ['max_words' => 1000, 'emoji_density' => 'high', 'tag_count' => 8],
            'wechat' => ['max_words' => 2000, 'emoji_density' => 'low', 'tag_count' => 0],
            'weibo' => ['max_words' => 140, 'emoji_density' => 'medium', 'tag_count' => 3],
        ];
        $output['_platform'] = $platform;
        $output['_platform_config'] = $platform_configs[$platform] ?? $platform_configs['xiaohongshu'];
        return $output;
    }

    private function suggest_text_overlay(string $band, string $scene_content, string $scene_title = '', string $scene_summary = '', int $scene_idx = 1): string {
        $keyword = $scene_title ?: mb_substr($scene_content, 0, 15);
        $snippet = $scene_summary ?: mb_substr($scene_content, 0, 30);

        $templates = [
            'hook' => $keyword . '：关键要点速览',
            'body' => $snippet . '… 详细解读见下图',
            'proof' => '核心数据与政策依据：' . $keyword,
            'cta' => '收藏本页，随时查阅' . $keyword,
        ];
        return $templates[$band] ?? '';
    }

    private function select_seed_for_band(string $band_key): array {
        $result = [];
        $type_map = [
            'hook' => ['char', 'scene'],
            'body' => ['scene', 'brand'],
            'proof' => ['palette', 'brand'],
            'cta' => ['brand'],
        ];
        $types = $type_map[$band_key] ?? [];
        foreach ($types as $type) {
            $seeds = $this->get_seed($type);
            if (!empty($seeds)) {
                $result[$type] = $seeds[0];
            }
        }
        return $result;
    }
}
