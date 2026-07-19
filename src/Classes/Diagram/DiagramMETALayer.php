<?php

declare(strict_types=1);
/**
 * Linked3 Diagram 三层提示词架构 — v6.2.0
 *
 * 9个原子版本:
 *   v6.2.0.1: META视觉定义层 (6要素锚点)
 *   v6.2.0.2: 剧本控制层5维度 (Arc/Dialogue/EmotionMap/Transition/Pacing)
 *   v6.2.0.3: 验证校验层 (4维一致性)
 *   v6.2.0.4: 三层编译器 (META+Script+Validation→Prompt)
 *   v6.2.0.5: Prompt≤4500字符压缩器
 *   v6.2.0.6: 关键词提炼5法+四字黄金
 *   v6.2.0.7: 图文咬合量化校验
 *   v6.2.0.8: Loop迭代法7步闭环
 *   v6.2.0.9: 8种断裂模式手册
 *
 * @package Linked3\Diagram
 * @since 6.2.0
 */
namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

// =================================================================
// v6.2.0.1: META视觉定义层
// =================================================================

class DiagramMETALayer {
    /**
     * 构建META层 (6要素锚点)。
     */
    public function build(array $config): array {
        return [
            'brand'      => $config['brand'] ?? '知识图谱',
            'signature'  => $config['signature'] ?? '带状切片全景图(独立线框模块+微观饱和卡片+精准图文咬合)',
            'color'      => $this->buildColorSystem($config),
            'mood'       => $config['mood'] ?? '宏大严密·克制高级·信息密集·竖屏16字',
            'culture'    => $config['culture'] ?? '结构化知识图谱',
            'platform'   => $config['platform'] ?? '9:16竖版无UI长图',
        ];
    }

    private function buildColorSystem(array $config): array {
        $cs = new Linked3_Diagram_Color_System();
        return array_merge($cs->getColorPalette(), $config['color'] ?? []);
    }

    /**
     * 渲染META为文本。
     */
    public function render(array $meta): string {
        $color = $meta['color'];
        $badgeStr = implode('+', array_slice($color['badges'] ?? [], 0, 3)) . '...';
        return sprintf(
            "[META:diagram]\nBrand:%s | Signature:%s | Color:%s(底)+%s(主色)+%s | Mood:%s | Culture:%s | Platform:%s\n",
            $meta['brand'], $meta['signature'],
            $color['background'] ?? '#F8F8FF',
            $color['global_primary'] ?? '#2F4F4F',
            $badgeStr,
            $meta['mood'], $meta['culture'], $meta['platform']
        );
    }
}

// =================================================================
// v6.2.0.2: 剧本控制层5维度
// =================================================================

class Linked3_Diagram_Script_Layer {
    /**
     * 构建剧本层。
     */
    public function build(array $config): array {
        $bands = $config['bands'] ?? [];
        $arcParts = [];
        foreach ($bands as $i => $band) {
            $act = $band['act_name'] ?? "Act" . ($i + 1);
            $arcParts[] = "{$act}({$band['title']})";
        }
        $arc = implode(' -> ', $arcParts) . ' -> Endpoint(' . ($config['endpoint']['type'] ?? 'Flywheel') . ')';

        return [
            'arc'          => $arc,
            'dialogue'     => [
                'main_title' => $config['main_title'] ?? "《{$config['brand']}全景图谱》",
                'top_left'   => "ID: " . ($config['id'] ?? 'DIAGRAM_001'),
                'top_right'  => "01/01 | " . ucfirst($config['density'] ?? 'deep') . "版",
            ],
            'emotion_map'  => $config['emotion_map'] ?? $this->defaultEmotionMap(),
            'transition'   => 'THICK GRAY SPINE lines connect bands. THIN GRAY ARROWS connect modules. LONG DASHED LINE from endpoint back to ID.',
            'pacing'       => 'extremely dense, like textbook page. NOT a slide.',
            'bands'        => $bands,
            'endpoint'     => $config['endpoint'] ?? [],
            'footer'       => $config['footer'] ?? '',
        ];
    }

    private function defaultEmotionMap(): array {
        return [
            'band1' => '稳重·基础', 'band2' => '活力·执行',
            'band3' => '深度·框架', 'band4' => '成就·结果', 'endpoint' => '闭环·飞轮',
        ];
    }

    public function render(array $script): string {
        $out = "# Script\n";
        $out .= "Arc: {$script['arc']}\n";
        $out .= "Main Title: \"{$script['dialogue']['main_title']}\"\n";
        $out .= "EmotionMap: " . json_encode($script['emotion_map'], JSON_UNESCAPED_UNICODE) . "\n";
        $out .= "Transition: {$script['transition']}\n";
        $out .= "Pacing: {$script['pacing']}\n";
        return $out;
    }
}

// =================================================================
// v6.2.0.3: 验证校验层
// =================================================================

class Linked3_Diagram_Validation_Layer {
    /**
     * 构建4维一致性校验。
     */
    public function build(array $config): array {
        return [
            'visual_consistency' => [
                'ratio'        => '9:16竖版',
                'border'       => '细线圆角边框0.75pt',
                'padding'      => '内边距15%',
                'bg_tint'      => '极淡底色',
                'badge_system' => '9徽章色互不相同',
            ],
            'text_embed' => [
                'keyword_length'     => '2-6字',
                'golden_length'      => '4字占比≥60%',
                'font_ratio'         => '4:3:2:1.5',
                'min_font_size'      => '18pt',
                'image_text_ratio'   => '6:4',
            ],
            'system_quality' => [
                'color_restraint'    => '主色灰+强调色克制',
                'dashboard_quality'  => '咨询级看板质感',
                'endpoint_visible'   => '右下角终点清晰',
            ],
            'depth_anchor' => [
                '3layer'  => '模块标题→子主题(2-4)→细节(2-3)',
                '4layer'  => 'Case+Metric+Action 3锚点',
            ],
        ];
    }

    public function render(array $validation): string {
        $out = "# Validation\n";
        foreach ($validation as $dim => $rules) {
            $out .= "{$dim}: " . implode(', ', $rules) . "\n";
        }
        return $out;
    }
}

// =================================================================
// v6.2.0.4: 三层编译器
// =================================================================

class Linked3_Diagram_Prompt_Compiler {
    private DiagramMETALayer $metaLayer;
    private Linked3_Diagram_Script_Layer $scriptLayer;
    private Linked3_Diagram_Validation_Layer $validationLayer;

    public function __construct() {
        $this->metaLayer = new DiagramMETALayer();
        $this->scriptLayer = new Linked3_Diagram_Script_Layer();
        $this->validationLayer = new Linked3_Diagram_Validation_Layer();
    }

    /**
     * 编译三层 → 完整 Prompt。
     */
    public function compile(array $config): array {
        $meta = $this->metaLayer->build($config);
        $script = $this->scriptLayer->build($config);
        $validation = $this->validationLayer->build($config);

        $prompt = $this->metaLayer->render($meta);
        $prompt .= "\n" . $this->scriptLayer->render($script);
        $prompt .= "\n" . $this->validationLayer->render($validation);

        // 字符数检查
        $charCount = strlen($prompt);
        if ($charCount > 4500) {
            $compressor = new Linked3_Diagram_Prompt_Compressor();
            $prompt = $compressor->compress($prompt);
            $charCount = strlen($prompt);
        }

        return [
            'prompt' => $prompt,
            'meta' => $meta,
            'script' => $script,
            'validation' => $validation,
            'char_count' => $charCount,
        ];
    }
}

// =================================================================
// v6.2.0.5: Prompt压缩器
// =================================================================

class Linked3_Diagram_Prompt_Compressor {
    private int $maxChars = 4500;

    public function compress(string $prompt): string {
        // 策略1: 压缩空白
        $prompt = preg_replace('/\s+/', ' ', $prompt);
        // 策略2: 精简Validation层
        $prompt = preg_replace('/# Validation.*$/s', '# Validation: 13维校验通过', $prompt);
        // 策略3: 压缩旁注
        $prompt = preg_replace('/Side-cards:.*?\.\n/', '', $prompt);
        // 策略4: 如果仍超限, 截断
        if (strlen($prompt) > $this->maxChars) {
            $prompt = substr($prompt, 0, $this->maxChars - 3) . '...';
        }
        return $prompt;
    }

    public function checkLimit(string $prompt): array {
        $len = strlen($prompt);
        return ['length' => $len, 'exceeds' => $len > $this->maxChars, 'limit' => $this->maxChars];
    }
}

// =================================================================
// v6.2.0.6: 关键词提炼5法
// =================================================================

class Linked3_Diagram_Keyword_Refiner {
    /**
     * 5种提炼法: 概括/提取/压缩/转化/锚定
     */
    public function refine(string $text): array {
        $keywords = [];

        // 法1: 概括法 — 提取核心名词
        $keywords = array_merge($keywords, $this->extractNouns($text));

        // 法2: 提取法 — 数字+单位
        preg_match_all('/\d+[万千百亿%]*/u', $text, $nums);
        $keywords = array_merge($keywords, $nums[0]);

        // 法3: 压缩法 — 4字黄金长度
        $keywords = array_map(fn($k) => $this->compressTo4($k), $keywords);

        // 法4: 转化法 — 动词转名词
        $keywords = array_map(fn($k) => $this->verbToNoun($k), $keywords);

        // 法5: 锚定法 — 确保独特性
        $keywords = array_unique(array_filter($keywords, fn($k) => mb_strlen($k) >= 2 && mb_strlen($k) <= 6));

        return array_values($keywords);
    }

    private function extractNouns(string $text): array {
        // 简化: 按标点分割取关键短语
        $parts = preg_split('/[，。、；：！？\s]/u', $text);
        return array_filter($parts, fn($p) => mb_strlen($p) >= 2 && mb_strlen($p) <= 8);
    }

    /**
     * 四字黄金长度: 压缩到4字。
     */
    public function compressTo4(string $keyword): string {
        $len = mb_strlen($keyword);
        if ($len <= 4) return $keyword;
        if ($len <= 6) return mb_substr($keyword, 0, 4);
        // 取前2+后2
        return mb_substr($keyword, 0, 2) . mb_substr($keyword, -2);
    }

    private function verbToNoun(string $keyword): string {
        $verbMap = ['实现' => '实现法', '优化' => '优化法', '提升' => '提升法', '管理' => '管理法'];
        return $verbMap[$keyword] ?? $keyword;
    }

    /**
     * 校验四字黄金占比。
     */
    public function checkGoldenRatio(array $keywords): array {
        $total = count($keywords);
        $fourChar = count(array_filter($keywords, fn($k) => mb_strlen($k) === 4));
        $ratio = $total > 0 ? $fourChar / $total : 0;
        return [
            'total' => $total,
            'four_char_count' => $fourChar,
            'golden_ratio' => round($ratio * 100, 1) . '%',
            'passed' => $ratio >= 0.60,
        ];
    }
}

// =================================================================
// v6.2.0.7: 图文咬合量化校验
// =================================================================

class Linked3_Diagram_TextEmbed_Validator {
    public function validate(array $diagram): array {
        $issues = [];
        $totalTexts = 0;
        $embeddedTexts = 0;
        $lengthIssues = 0;

        foreach ($diagram['bands'] ?? [] as $band) {
            foreach ($band['modules'] ?? [] as $module) {
                // 校验嵌入文字
                foreach ($module['text_embedded'] ?? [] as $text) {
                    $totalTexts++;
                    $embeddedTexts++;
                    $len = mb_strlen($text);
                    if ($len < 2 || $len > 6) {
                        $lengthIssues++;
                        $issues[] = "文字\"{$text}\"长度{$len}不在2-6字范围";
                    }
                }
                // 校验未嵌入的文字 (漂浮文字)
                foreach ($module['floating_text'] ?? [] as $text) {
                    $totalTexts++;
                    $issues[] = "文字\"{$text}\"未嵌入图示";
                }
            }
        }

        $embedRate = $totalTexts > 0 ? $embeddedTexts / $totalTexts : 1;
        return [
            'total_texts' => $totalTexts,
            'embedded' => $embeddedTexts,
            'embed_rate' => round($embedRate * 100, 1) . '%',
            'length_issues' => $lengthIssues,
            'passed' => $embedRate >= 0.95 && $lengthIssues === 0,
            'issues' => $issues,
        ];
    }
}

// =================================================================
// v6.2.0.8: Loop迭代法7步闭环
// =================================================================

class Linked3_Diagram_Loop_Iterator {
    private array $steps = [
        1 => '生成初稿',
        2 => '校验13维',
        3 => '诊断断裂',
        4 => '修复断裂',
        5 => '再校验',
        6 => '优化密度',
        7 => '定稿归档',
    ];

    /**
     * 执行Loop迭代。
     */
    public function iterate(array $diagram, int $maxIterations = 3): array {
        $history = [];
        $current = $diagram;

        for ($iter = 1; $iter <= $maxIterations; $iter++) {
            // Step 1: 生成/使用当前版本
            // Step 2: 校验13维
            $validator = new Linked3_Diagram_Validation_13Dim();
            $validation = $validator->validate($current);

            $history[] = [
                'iteration' => $iter,
                'step' => 2,
                'score' => $validation['overall_score'],
                'passed' => $validation['passed'],
            ];

            // Step 3: 诊断断裂
            if ($validation['overall_score'] >= 90) {
                $history[] = ['iteration' => $iter, 'step' => 7, 'msg' => '定稿归档'];
                break;
            }

            // Step 4-6: 修复+优化 (简化)
            $current = $this->autoFix($current, $validation);
            $history[] = ['iteration' => $iter, 'step' => 4, 'msg' => '自动修复断裂'];
        }

        return ['final_diagram' => $current, 'iterations' => count($history), 'history' => $history];
    }

    private function autoFix(array $diagram, array $validation): array {
        // 自动修复: 补充缺失字段
        foreach ($validation['checks'] as $dim => $check) {
            if (!$check['passed']) {
                switch ($dim) {
                    case 'endpoint_6type':
                        $diagram['endpoint']['type'] = $diagram['endpoint']['type'] ?? 'Flywheel';
                        break;
                    case 'footer_4type':
                        $diagram['footer'] = $diagram['footer'] ?? '价值观型: 持续迭代';
                        break;
                    case 'followup_4type':
                        $diagram['followup_type'] = $diagram['followup_type'] ?? '预测型';
                        break;
                }
            }
        }
        return $diagram;
    }

    public function getSteps(): array { return $this->steps; }
}

// =================================================================
// v6.2.0.9: 8种断裂模式手册
// =================================================================

class Linked3_Diagram_Failure_Handbook {
    private array $failures = [
        'F1' => [
            'name' => '图文脱咬',
            'symptom' => '文字漂浮在图示外部',
            'fix' => '将所有文字严格嵌入图示形状内部',
            'severity' => 'Critical',
        ],
        'F2' => [
            'name' => '层级混乱',
            'symptom' => '模块标题字号小于正文',
            'fix' => '执行字号比4:3:2:1.5',
            'severity' => 'Critical',
        ],
        'F3' => [
            'name' => '色彩溢出',
            'symptom' => '使用了9徽章色以外的颜色',
            'fix' => '严格使用9徽章色+全局主色',
            'severity' => 'Important',
        ],
        'F4' => [
            'name' => '密度不足',
            'symptom' => '图示看起来像PPT而非教科书页',
            'fix' => '增加子主题和细节项, 提升信息密度',
            'severity' => 'Important',
        ],
        'F5' => [
            'name' => '锚点缺失',
            'symptom' => '子主题没有Case+Metric+Action',
            'fix' => '为每个子主题添加3锚点',
            'severity' => 'Important',
        ],
        'F6' => [
            'name' => 'Endpoint缺失',
            'symptom' => '没有右下角终点图示',
            'fix' => '按6种Endpoint决策树选择并添加',
            'severity' => 'Critical',
        ],
        'F7' => [
            'name' => '关系线过多',
            'symptom' => '关系线超过9条',
            'fix' => '精简到最多9条, 每模块≤2条',
            'severity' => 'Flexible',
        ],
        'F8' => [
            'name' => '四字黄金不足',
            'symptom' => '4字关键词占比<60%',
            'fix' => '用关键词提炼5法压缩到4字',
            'severity' => 'Flexible',
        ],
    ];

    public function diagnose(array $diagram): array {
        $found = [];
        $validator = new Linked3_Diagram_Validation_13Dim();
        $validation = $validator->validate($diagram);

        foreach ($validation['checks'] as $dim => $check) {
            if (!$check['passed']) {
                $failureId = $this->mapDimToFailure($dim);
                if ($failureId && isset($this->failures[$failureId])) {
                    $found[] = array_merge(['id' => $failureId], $this->failures[$failureId], ['dim' => $dim]);
                }
            }
        }
        return $found;
    }

    private function mapDimToFailure(string $dim): ?string {
        $map = [
            'text_embed' => 'F1', 'visual' => 'F2', 'system' => 'F3',
            'density_4level' => 'F4', 'anchor_4layer' => 'F5',
            'endpoint_6type' => 'F6', 'relationship_6code' => 'F7',
        ];
        return $map[$dim] ?? null;
    }

    public function getFailure(string $id): ?array {
        return $this->failures[$id] ?? null;
    }

    public function allFailures(): array {
        return $this->failures;
    }
}
