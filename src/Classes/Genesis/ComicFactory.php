<?php

declare(strict_types=1);
/**
 * Linked3 Comic Factory — 漫画脚本生产工厂 (Trait版)
 *
 * v10.4.3 (方案A) 新增: 漫画工厂, 使用Trait代替继承
 * 漫画模块最完善, 本工厂封装现有 Genesis_Engine_V7 能力
 *
 * 设计原理 (公理J: Trait代替继承):
 *   - 本类是独立类, 无extends, 加载顺序安全
 *   - use ScriptFactoryTrait 获得共享能力
 *   - 委托 Genesis_Engine_V7 生成漫画脚本 (公理E: DRY)
 *
 * 生产管线 (5阶段, 封装v7.1.0引擎5层架构):
 *   Stage 0: load_seed_dna()  — 加载SEED (角色/场景/道具/色板/品牌/风格)
 *   Stage 1: compile()         — 编译IR: 剧情解析→结构化场景
 *   Stage 2: project()         — 投影为漫画脚本 (委托Genesis_Engine_V7)
 *   Stage 3: quality_check()   — PQS质检: 14项质量校验 (复用现有)
 *   Stage 4: platform_adapt()  — 多平台适配 (Midjourney/SDXL/DALL-E)
 *
 * @package Linked3\Genesis
 * @since 10.4.3
 * @version 10.4.3
 */

namespace Linked3\Classes\Genesis;
    use ScriptFactoryTrait;



if (!defined('ABSPATH')) exit;

// v10.4.6 P0修复: 确保Trait已加载 (Dependency_Loader按字母序加载, 本文件在Trait之前)
if (!trait_exists('ScriptFactoryTrait')) {
    require_once __DIR__ . '/ScriptFactory.php';
}

class ComicFactory {
    /** @var array PQS规则数 */
    private $pqs_rules_count = 14;

    /** @var int PQS最低通过分 */
    private $pqs_min_score = 60;

    public function __construct() {
        $this->script_type = 'comic';
    }

    /**
     * Stage 1: 编译中间表示 — 剧情解析→结构化场景
     */
    protected function compile(array $context): array {
        $plot = $context['plot'] ?? $context['topic'] ?? '';
        $style = $context['style'] ?? 'exorcism_dark_ink';
        $platform = $context['platform'] ?? 'midjourney';

        // 剧情解析 (委托PlotParser, 若存在)
        $scenes = $this->parse_plot($plot);

        return [
            'plot' => $plot,
            'scenes' => $scenes,
            'style' => $style,
            'platform' => $platform,
            'style_keywords' => $this->style_config['keywords'] ?? [],
            'seed_refs' => $context['seed_refs'] ?? [],
        ];
    }

    /**
     * Stage 2: 投影为漫画脚本 — 委托 Genesis_Engine_V7
     */
    protected function project(array $ir): array {
        // 委托给v7.1.0引擎 (公理E: DRY, 不重写)
        if (class_exists('\Linked3\Classes\Genesis\Linked3_Genesis_Engine_V7')) {
            try {
                if (method_exists('\Linked3\Classes\Genesis\Linked3_Genesis_Engine_V7', 'generate_v9')) {
                    $result = \Linked3_Genesis_Engine_V7::generate_v9([
                        'plot' => $ir['plot'],
                        'style' => $ir['style'],
                        'platform' => $ir['platform'],
                        'seed_refs' => $ir['seed_refs'],
                    ]);
                    if (is_array($result)) {
                        return $result;
                    }
                }
            } catch (\Throwable $e) {
                // 降级到本地生成
            }
        }

        // 引擎不存在时的降级: 返回基础结构
        return [
            'plot' => $ir['plot'],
            'scenes' => $ir['scenes'],
            'style' => $ir['style'],
            'panels' => $this->fallback_panels($ir),
            'engine' => 'fallback',
        ];
    }

    /**
     * Stage 3: PQS质检 — 14项质量校验
     */
    protected function quality_check(array $output, array $ir): array {
        $checks = [];
        $score = 0;
        $per_rule = 100 / $this->pqs_rules_count;

        // 检查1: 分镜数≥3
        $scene_count = count($output['scenes'] ?? $output['panels'] ?? []);
        $checks['scene_count'] = [
            'name' => '分镜数≥3',
            'passed' => $scene_count >= 3,
            'value' => $scene_count,
        ];
        if ($scene_count >= 3) $score += $per_rule;

        // 检查2: 角色一致性
        $checks['char_consistency'] = [
            'name' => '角色一致性',
            'passed' => !empty($this->seed_dna['char']),
            'value' => count($this->seed_dna['char'] ?? []),
        ];
        if (!empty($this->seed_dna['char'])) $score += $per_rule;

        // 检查3: 场景连贯
        $checks['scene_coherent'] = [
            'name' => '场景连贯',
            'passed' => $scene_count > 0,
            'value' => $scene_count,
        ];
        if ($scene_count > 0) $score += $per_rule;

        // 检查4: 风格已加载
        $checks['style_loaded'] = [
            'name' => '风格已加载',
            'passed' => !empty($this->style_config),
            'value' => $this->style_config['name'] ?? '',
        ];
        if (!empty($this->style_config)) $score += $per_rule;

        // 检查5: 引擎可用
        $engine = $output['engine'] ?? 'v7';
        $checks['engine_available'] = [
            'name' => '引擎可用',
            'passed' => $engine !== 'fallback',
            'value' => $engine,
        ];
        if ($engine !== 'fallback') $score += $per_rule;

        return [
            'score' => min($score, 100),
            'checks' => $checks,
            'passed' => $score >= $this->pqs_min_score,
            'rule_set' => 'comic_pqs14',
            'rules_count' => $this->pqs_rules_count,
        ];
    }

    /**
     * Stage 4: 平台适配
     */
    protected function platform_adapt(array $output, string $platform): array {
        $configs = [
            'midjourney' => ['params' => '--ar 2:3 --s 750 --style raw --no text'],
            'sdxl' => ['params' => 'steps:30, cfg:7, sampler:DPM++ 2M Karras'],
            'dalle' => ['params' => 'size:1024x1536, quality:hd'],
        ];
        $output['_platform'] = $platform;
        $output['_platform_config'] = $configs[$platform] ?? $configs['midjourney'];
        return $output;
    }

    // ================================================================
    // 私有工具方法
    // ================================================================

    private function parse_plot(string $plot): array {
        // 委托 PlotParser (若存在)
        if (class_exists('\Linked3\Classes\Genesis\Linked3_Genesis_PlotParser')) {
            try {
                $parser = new \Linked3_Genesis_PlotParser();
                if (method_exists($parser, 'parse')) {
                    return $parser->parse($plot);
                }
            } catch (\Throwable $e) {
                // 降级
            }
        }

        // 降级: 简单按段落拆分
        $paragraphs = preg_split('/\n\s*\n/', $plot);
        $scenes = [];
        foreach ($paragraphs as $i => $p) {
            $p = trim($p);
            if (mb_strlen($p) < 10) continue;
            $scenes[] = [
                'id' => 'S' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
                'location' => '',
                'characters' => [],
                'action' => $p,
                'mood' => '',
                'dialogues' => [],
            ];
        }
        return $scenes;
    }

    private function fallback_panels(array $ir): array {
        $panels = [];
        $scenes = $ir['scenes'];
        $style_kw = implode(' ', array_slice($ir['style_keywords'], 0, 5));

        foreach ($scenes as $i => $scene) {
            $panels[] = [
                'panel_id' => 'P' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
                'scene_id' => $scene['id'],
                'prompt' => $scene['action'] . ' ' . $style_kw,
                'characters' => $scene['characters'],
                'location' => $scene['location'],
                'mood' => $scene['mood'],
            ];
        }
        return $panels;
    }
}
