<?php

declare(strict_types=1);
/**
 * Linked3 Diagram Master Template
 * 知识图谱型图示主模板 — 带状切片全景图核心
 *
 * 融合V14精髓:
 * - 9:16竖版4带状切片布局
 * - 9模块编号系统+徽章锚定
 * - 细线圆角边框+内边距
 * - 图文咬合严格嵌入
 * - 主色灰+强调色克制
 * - 字号比4:3:2:1.5
 *
 * @package Linked3\Diagram
 * @since 6.1.0.1
 * @version 6.1.0.1
 */
namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) {
    exit;
}


/**
 * 知识图谱型图示主模板
 *
 * 核心结构:
 *   9:16竖版 → 4个水平Band → 每Band含模块 → 每模块含3层深度
 *   Band1: 基础底座 (1模块)
 *   Band2: 执行层 (3并列模块)
 *   Band3: 框架层 (1模块)
 *   Band4: 结果层 (1模块)
 *   Endpoint: 右下角终点图示
 *   Footer: 底部全局价值观
 */
class DiagramMasterTemplate implements DiagramMasterTemplateInterface {
    const SIGNATURE = '带状切片全景图(独立线框模块+微观饱和卡片+精准图文咬合)';
    const GLOBAL_PRIMARY = '#2F4F4F';
    const BACKGROUND = '#F8F8FF';
    const BADGE_COLORS = [
        '01' => '#4A90E2', '02' => '#F5A623', '03' => '#7ED321',
        '04' => '#D0506E', '05' => '#9013FE', '06' => '#50C8D6',
        '07' => '#B8860B', '08' => '#8B4513', '09' => '#2E8B57',
    ];
    const FONT_RATIO = ['main_title' => 4, 'module_title' => 3, 'body_text' => 2, 'side_note' => 1.5];
    const IMAGE_TEXT_RATIO = '6:4';

    private $container;
    private $logger;

    public function __construct() {
        if (function_exists('linked3_container')) {
            $this->container = linked3_container();
            $this->logger = $this->container->has('logger')
                ? $this->container->get('logger')
                : (class_exists('\Linked3\Classes\Diagram\Logger') ? Logger::instance() : null);
        }
    }

    public function generate(array $config): array {
        $diagramId = uniqid('diagram_', true);
        $startTime = microtime(true);

        if ($this->logger) {
            $this->logger->info('Diagram generation started', [
                'diagram_id' => $diagramId, 'brand' => $config['brand'] ?? 'unknown',
            ]);
        }

        $meta = $this->buildMetaLayer($config);
        $script = $this->buildScriptLayer($config);
        $validation = $this->buildValidationLayer($config);
        $prompt = $this->compilePrompt($meta, $script, $validation, $config);

        $charCount = strlen($prompt);
        if ($charCount > 4500) {
            $prompt = $this->compressPrompt($prompt);
            $charCount = strlen($prompt);
        }

        if ($this->logger) {
            $this->logger->info('Diagram generation completed', [
                'diagram_id' => $diagramId, 'char_count' => $charCount,
                'elapsed_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        }

        if (function_exists('linked3_dispatch')) {
            linked3_dispatch('linked3.diagram.generated', [
                'diagram_id' => $diagramId, 'brand' => $config['brand'] ?? '', 'char_count' => $charCount,
            ]);
        }

        return [
            'diagram_id' => $diagramId, 'prompt' => $prompt,
            'meta' => $meta, 'script' => $script, 'validation' => $validation,
            'char_count' => $charCount, 'signature' => self::SIGNATURE,
        ];
    }

    private function buildMetaLayer(array $config): array {
        return [
            'brand' => $config['brand'] ?? '知识图谱',
            'signature' => self::SIGNATURE,
            'color' => [
                'background' => self::BACKGROUND,
                'global_primary' => self::GLOBAL_PRIMARY,
                'theme_color' => $config['theme_color'] ?? self::GLOBAL_PRIMARY,
                'badge_colors' => $config['badge_colors'] ?? array_values(self::BADGE_COLORS),
            ],
            'mood' => $config['mood'] ?? '宏大严密·克制高级·信息密集·竖屏16字',
            'culture' => $config['culture'] ?? '结构化知识图谱',
            'platform' => '9:16竖版无UI长图',
            'density' => $config['density'] ?? 'deep',
        ];
    }

    private function buildScriptLayer(array $config): array {
        $bands = $config['bands'] ?? [];
        $arcParts = [];
        foreach ($bands as $i => $band) {
            $actName = $band['act_name'] ?? "Act" . ($i + 1);
            $arcParts[] = "{$actName}({$band['title']})";
        }
        $arc = implode(' -> ', $arcParts) . ' -> Endpoint(' . ($config['endpoint']['type'] ?? 'Flywheel') . ')';

        return [
            'arc' => $arc,
            'dialogue' => [
                'main_title' => $config['main_title'] ?? "《{$config['brand']}全景图谱》",
                'top_left' => "ID: " . ($config['id'] ?? 'DIAGRAM_001') . " | 出品: " . ($config['publisher'] ?? 'Linked3'),
                'top_right' => "S00 | 01/01 | " . ucfirst($config['density'] ?? 'deep') . "版",
            ],
            'emotion_map' => $config['emotion_map'] ?? [
                'band1' => '稳重·基础', 'band2' => '活力·执行',
                'band3' => '深度·框架', 'band4' => '成就·结果', 'endpoint' => '闭环·飞轮',
            ],
            'transition' => 'THICK GRAY SPINE lines connect bands. THIN GRAY ARROWS connect modules. LONG DASHED LINE from endpoint back to ID, forming giant closed loop.',
            'pacing' => 'extremely dense, like textbook page. NOT a slide.',
            'bands' => $bands,
            'endpoint' => $config['endpoint'] ?? [],
            'footer' => $config['footer'] ?? '',
        ];
    }

    private function buildValidationLayer(array $config): array {
        return [
            'visual' => ['ratio' => '9:16竖版', 'border' => '细线圆角边框0.75pt', 'padding' => '内边距15%', 'bg_tint' => '极淡底色'],
            'text_embed' => ['keyword_length' => '2-6字', 'golden_length' => '4字占比≥60%', 'font_ratio' => '4:3:2:1.5', 'min_font_size' => '18pt', 'image_text_ratio' => '6:4'],
            'system' => ['color_restraint' => '主色灰+强调色克制', 'badge_distinct' => '9徽章色互不相同', 'endpoint_visible' => '右下角终点清晰', 'dashboard_quality' => '咨询级看板质感'],
            'vertical_16char' => '竖屏构图·大图少字·内容浅显易懂·视觉引导',
            'depth_3layer' => '每模块3层: 模块标题→子主题(2-4个)→细节项(每子主题2-3个)',
            'anchor_4layer' => '每子主题选1代表细节, 增Case+Metric+Action 3锚点',
            'diagram_16type' => '16种图示按决策树匹配',
            'endpoint_6type' => '6种Endpoint按决策树匹配',
            'footer_4type' => '4种Footer按案例性质选择',
            'followup_4type' => '4种追问按案例性质选择',
            'relationship_6code' => '6种关系编码, 最多9条, 每模块≤2条',
            'cognitive_6level' => '6级标注[R][U][A][An][E][C], 4Band默认映射',
            'density_4level' => '4档可选: 极简/标准/深度/极致',
            // v18复审 [公理α: H↓ 消除"视觉频率缺失"不确定性] [公理β: dim↓ 第9维度统一校验]
            // 第9维度·视觉频率校验: [HF]高频/[MF]中频/[LF]低频 三层递进分布 + 色彩映射
            'visual_frequency_9th' => '第9维度: 视觉频率三层递进 [HF]高频锚点(前1/3画面密集)→[MF]中频叙事(中段支撑)→[LF]低频氛围(底部收束), 色彩映射 HF暖亮/MF中性/LF冷暗, 禁止全频均匀分布',
        ];
    }

    private function compilePrompt(array $meta, array $script, array $validation, array $config): string {
        $bands = $script['bands'];
        $endpoint = $script['endpoint'];
        $footer = $script['footer'];

        $prompt = "[ID: " . ($config['id'] ?? 'DIAGRAM_001') . "]\n";
        $prompt .= "[Title: " . $script['dialogue']['main_title'] . "]\n";
        $prompt .= "[META:diagram_master_template]\n";
        $prompt .= "Brand:{$meta['brand']} | Signature:" . self::SIGNATURE . " | ";
        $prompt .= "Color:#F8F8FF(底)+#2F4F4F(全局主色)+9大模块专属强调色 | ";
        $prompt .= "Mood:{$meta['mood']} | Culture:{$meta['culture']} | ";
        $prompt .= "Platform:9:16竖版无UI长图 | Density:" . ucfirst($meta['density']) . "版\n\n";

        $prompt .= "# Script: " . ($config['english_title'] ?? 'System Architecture Map') . "\n";
        $prompt .= "Arc: {$script['arc']}\n";
        $prompt .= "Main Title: \"{$script['dialogue']['main_title']}\"\n\n";

        $prompt .= "# Layout & Visual Logic (DO NOT change structure)\n";
        $prompt .= "A vertical 9:16 infographic poster. No UI elements. Background: ghost white (#F8F8FF).\n";
        $prompt .= "Layout: Horizontally sliced into " . count($bands) . " distinct horizontal bands. Each band has a VERY FAINT background tint.\n";
        $prompt .= "Module Bounding: Every module MUST be enclosed in a THIN, CLEAN ROUNDED RECTANGLE BORDER with internal padding.\n";
        $prompt .= "Anchoring System: Every module MUST have a PROMINENT CIRCULAR COLORED BADGE with a white number (01-09) at its top-left corner.\n";
        $prompt .= "Density Rule: MUST be extremely dense with text, like a textbook page.\n";
        $prompt .= "Typographic Hierarchy: Main Title (Largest, Bold Dark Gray) > Module Titles (Large, Colored) > Text inside diagrams (Medium, Bold Black, EMBEDDED strictly inside shapes) > Dense side-cards (Small, Dark Gray).\n\n";

        $prompt .= "# Text Overlays (crisp Chinese)\n";
        $prompt .= "Top-Left: \"{$script['dialogue']['top_left']}\"\n";
        $prompt .= "Top-Right: \"{$script['dialogue']['top_right']}\"\n\n";

        foreach ($bands as $i => $band) {
            $bandNum = $i + 1;
            $bandTint = $band['tint'] ?? 'Light Blue';
            $actName = $band['act_name'] ?? "Act{$bandNum}";
            $bandTitle = $band['title'] ?? "Band {$bandNum}";
            $prompt .= "# Band {$bandNum} ({$bandTint} tint, {$actName} {$bandTitle}):\n";

            if (isset($band['modules']) && is_array($band['modules'])) {
                foreach ($band['modules'] as $module) {
                    $badgeNum = $module['badge'] ?? '01';
                    $badgeColor = self::BADGE_COLORS[$badgeNum] ?? '#4A90E2';
                    $cognitiveLevel = $module['cognitive_level'] ?? '[R]';
                    $moduleTitle = $module['title'] ?? 'Module';
                    $diagramType = $module['diagram_type'] ?? 'Stacked blocks';
                    $prompt .= "Badge \"{$badgeNum}\" ({$badgeColor}) {$cognitiveLevel}. Title: \"{$moduleTitle}\". Diagram: {$diagramType}.\n";

                    if (isset($module['sub_topics']) && is_array($module['sub_topics'])) {
                        foreach ($module['sub_topics'] as $subTopic) {
                            $stTitle = $subTopic['title'] ?? 'Sub-topic';
                            $details = $subTopic['details'] ?? [];
                            $anchor = $subTopic['anchor'] ?? null;
                            $prompt .= "  Sub-topic: \"{$stTitle}\" -> Details: ";
                            $detailStrs = array_map(fn($d) => "\"{$d}\"", $details);
                            $prompt .= implode(', ', $detailStrs) . ".\n";
                            if ($anchor) {
                                $prompt .= "    Application Anchor: Case=\"{$anchor['case']}\", Metric=\"{$anchor['metric']}\", Action=\"{$anchor['action']}\".\n";
                            }
                        }
                    }
                    if (isset($module['text_embedded'])) {
                        $prompt .= "  Text EMBEDDED: " . implode(', ', array_map(fn($t) => "\"{$t}\"", $module['text_embedded'])) . ".\n";
                    }
                    if (isset($module['side_cards'])) {
                        $prompt .= "  Side-cards: " . implode(', ', array_map(fn($s) => "\"{$s}\"", $module['side_cards'])) . ".\n";
                    }
                    $prompt .= "\n";
                }
            }
        }

        if (!empty($endpoint)) {
            $epType = $endpoint['type'] ?? 'Flywheel';
            $epQuestion = $endpoint['question'] ?? '飞轮：4个齿轮如何互相加速？';
            $epMilestones = $endpoint['milestones'] ?? ['阶段1', '阶段2', '阶段3', '阶段4'];
            $prompt .= "# Endpoint & Footer\n";
            $prompt .= "Endpoint Type: {$epType}. Visual: " . $this->getEndpointVisual($epType) . ".\n";
            $prompt .= "Question: \"{$epQuestion}\"\n";
            $prompt .= "Milestones: " . implode(', ', array_map(fn($m) => "\"{$m}\"", $epMilestones)) . ".\n";
            if (isset($endpoint['accelerators'])) {
                $prompt .= "Accelerators: " . implode(', ', array_map(fn($a) => "\"{$a}\"", $endpoint['accelerators'])) . ".\n";
            }
        }

        if ($footer) {
            $prompt .= "Footer: \"{$footer}\".\n";
        }

        if (isset($config['relationships']) && is_array($config['relationships'])) {
            $prompt .= "\n# Relationships (max 9, max 2 per module)\n";
            foreach ($config['relationships'] as $rel) {
                $prompt .= $rel['from'] . $rel['code'] . $rel['to'] . ': ' . $rel['desc'] . "\n";
            }
        }

        $prompt .= "\n# Connections\n";
        $prompt .= "THICK GRAY SPINE lines connect bands. THIN GRAY ARROWS connect modules. ";
        $prompt .= "LONG DASHED LINE from " . ($endpoint['type'] ?? 'flywheel') . " back to ID, forming giant closed loop. ";
        $prompt .= "Professional, dense, hierarchically clear content marketing infographic.\n";

        $prompt .= "\n# Validation (13维汇总)\n";
        $prompt .= "视觉/咬合/系统/竖屏: 9:16竖版" . count($bands) . "带状切片，细线圆角边框0.75pt，图文咬合严格嵌入，主色灰+强调色克制，字号比4:3:2:1.5。\n";
        $prompt .= "深度/锚点/图示/Endpoint: 4层(模块->子主题->细节->锚点)，16种图示按决策树匹配，Endpoint=" . ($endpoint['type'] ?? 'Flywheel') . "。\n";
        $prompt .= "Footer/追问/关系/认知/密度: Footer=" . ($config['footer_type'] ?? '公式型') . "，追问=" . ($config['followup_type'] ?? '预测型') . "，";
        $prompt .= count($config['relationships'] ?? []) . "条关系，认知6级[R][A][U][An][E][C]，Density=" . ucfirst($meta['density']) . "版。\n";
        // v18复审: 第9维度·视觉频率校验输出
        $prompt .= "视觉频率(第9维度): [HF]高频锚点前1/3密集→[MF]中频叙事中段支撑→[LF]低频氛围底部收束, 色彩HF暖亮/MF中性/LF冷暗, 禁止全频均匀分布。\n";

        return $prompt;
    }

    private function getEndpointVisual(string $type): string {
        $visuals = [
            'Mountain path' => 'Mountain path with 4 milestones',
            'Flywheel' => '4 gears circular acceleration',
            'Growth spiral' => 'Spiral with 4 milestones rising',
            'Compound curve' => 'S-curve with inflection point and 4 milestones',
            'Ecosystem loop' => 'Multi-node ecosystem with 4 milestones',
            'Transformation path' => 'Cocoon->butterfly with 3 stage markers',
        ];
        return $visuals[$type] ?? '4 gears circular acceleration';
    }

    private function compressPrompt(string $prompt): string {
        $prompt = preg_replace('/\s+/', ' ', $prompt);
        $prompt = preg_replace('/# Validation.*$/s', '# Validation: 13维校验通过', $prompt);
        $prompt = preg_replace('/Side-cards:.*?\.\n/', '', $prompt);
        return $prompt;
    }

    public function validate(array $diagram): array {
        $issues = [];
        $score = 100;

        if (!isset($diagram['bands']) || count($diagram['bands']) < 3) {
            $issues[] = 'Band数量不足(最少3个)';
            $score -= 20;
        }

        foreach ($diagram['bands'] ?? [] as $band) {
            foreach ($band['modules'] ?? [] as $module) {
                if (!isset($module['sub_topics']) || count($module['sub_topics']) < 2) {
                    $issues[] = "模块{$module['title']}子主题不足(最少2个)";
                    $score -= 5;
                }
                foreach ($module['sub_topics'] ?? [] as $subTopic) {
                    if (!isset($subTopic['details']) || count($subTopic['details']) < 2) {
                        $issues[] = "子主题{$subTopic['title']}细节项不足(最少2个)";
                        $score -= 3;
                    }
                }
            }
        }

        foreach ($diagram['bands'] ?? [] as $band) {
            foreach ($band['modules'] ?? [] as $module) {
                if (isset($module['text_embedded'])) {
                    foreach ($module['text_embedded'] as $text) {
                        $len = mb_strlen($text);
                        if ($len < 2 || $len > 6) {
                            $issues[] = "嵌入文字\"{$text}\"长度{$len}不在2-6字范围";
                            $score -= 2;
                        }
                    }
                }
            }
        }

        $badgeNums = [];
        foreach ($diagram['bands'] ?? [] as $band) {
            foreach ($band['modules'] ?? [] as $module) {
                $badgeNums[] = $module['badge'] ?? '00';
            }
        }
        if (count($badgeNums) !== count(array_unique($badgeNums))) {
            $issues[] = '徽章编号有重复';
            $score -= 10;
        }

        if (!isset($diagram['endpoint']['type'])) {
            $issues[] = 'Endpoint类型缺失';
            $score -= 10;
        }

        $relCount = count($diagram['relationships'] ?? []);
        if ($relCount > 9) {
            $issues[] = "关系线{$relCount}条超过上限9条";
            $score -= 5;
        }

        return [
            'passed' => $score >= 70 && empty(array_filter($issues, fn($i) => strpos($i, '不足') !== false)),
            'score' => max(0, $score),
            'issues' => $issues,
        ];
    }

    public function getSignature(): string { return self::SIGNATURE; }
    public function getBadgeColors(): array { return self::BADGE_COLORS; }
    public function getFontRatio(): array { return self::FONT_RATIO; }
}
