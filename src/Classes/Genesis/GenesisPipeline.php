<?php

declare(strict_types=1);
/**
 * GenesisPipeline — extracted from GenesisSeedLibrary.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Genesis

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisPipeline {
    private GenesisIRCompiler $compiler;
    private GenesisMutator $mutator;
    private GenesisEvaluator $evaluator;

    public function __construct() {
        $this->compiler = new GenesisIRCompiler();
        $this->mutator = new GenesisMutator();
        $threshold = intval(get_option(LINKED3_OPTION_PREFIX . 'v7_cull_threshold', 28));
        $this->evaluator = new GenesisEvaluator($threshold);
    }

    public function run(array $panels, string $styleId = 'ST1', string $platform = 'mj'): array {
        $lockedIRs = [];
        $manifest = [
            'version' => '7.1.0',
            'style' => $styleId,
            'platform' => $platform,
            'panels' => [],
            'total' => 0,
            'survivors' => 0,
            'culled' => 0,
        ];

        foreach ($panels as $panel) {
            $panelId = $panel['panel_id'] ?? ('P' . str_pad((string)(count($lockedIRs) + 1), 4, '0', STR_PAD_LEFT));

            $baseAtom = $this->buildBaseAtom($panel, $styleId);

            $validation = $this->compiler->validateAtom($baseAtom);
            if (!$validation['valid']) {
                $manifest['panels'][] = [
                    'id' => $panelId, 'status' => 'invalid',
                    'errors' => $validation['errors'],
                ];
                continue;
            }

            $variants = $this->mutator->generateVariants($baseAtom);

            $scored = [];
            foreach ($variants as $v) {
                $ir = $this->compiler->compileIR($v);
                $result = $this->evaluator->scoreVariant($ir);
                $scored[] = [
                    'variant' => $v,
                    'ir' => $ir,
                    'score' => $result['score'],
                    'feedback' => $result['feedback'],
                ];
            }

            $cullResult = $this->evaluator->cull($scored);
            $survivors = $cullResult['survivors'];
            $culled = $cullResult['culled'];

            if (empty($survivors)) {
                $lockedIR = $this->compiler->compileIR($baseAtom);
                $lockedIRs[] = $lockedIR;
                $manifest['panels'][] = [
                    'id' => $panelId, 'status' => 'fallback',
                    'reason' => 'all variants culled',
                    'culled_count' => count($culled),
                ];
                if (class_exists('\Linked3\Classes\Genesis\GenesisLogger')) {
                    GenesisLogger::warn('v7_pipeline', '全灭兜底: ' . $panelId, [
                        'culled_count' => count($culled),
                        'feedback' => $culled[0]['feedback'] ?? [],
                    ]);
                }
            } else {
                usort($survivors, fn($a, $b) => $b['score'] <=> $a['score']);
                $best = $survivors[0];
                $lockedIRs[] = $best['ir'];
                $manifest['panels'][] = [
                    'id' => $panelId, 'status' => 'locked',
                    'best_variant' => $best['variant']['id'] ?? '',
                    'score' => $best['score'],
                    'survivors' => count($survivors),
                    'culled' => count($culled),
                    'feedback' => $best['feedback'],
                ];
            }

            $manifest['survivors'] += count($survivors);
            $manifest['culled'] += count($culled);
        }

        $manifest['total'] = count($lockedIRs);

        $prompts = [];
        foreach ($lockedIRs as $ir) {
            switch ($platform) {
                case 'sd':
                    $prompt = $this->compiler->toStableDiffusion($ir);
                    break;
                case 'dalle':
                    $prompt = $this->compiler->toDallE($ir);
                    break;
                default:
                    $prompt = $this->compiler->toMidjourney($ir);
                    break;
            }
            $prompts[] = $prompt;
        }

        if (class_exists('\Linked3\Classes\Genesis\GenesisLogger')) {
            GenesisLogger::info('v7_pipeline', 'Pipeline完成', [
                'total' => $manifest['total'],
                'survivors' => $manifest['survivors'],
                'culled' => $manifest['culled'],
                'style' => $styleId,
                'platform' => $platform,
            ]);
        }

        return [
            'manifest' => $manifest,
            'prompts' => $prompts,
            'locked_irs' => $lockedIRs,
        ];
    }

    private function buildBaseAtom(array $panel, string $styleId): array {
        $shotMap = [
            '远景' => 'wide', '全景' => 'medium_wide', '中景' => 'medium',
            '近景' => 'close_up', '特写' => 'extreme_close_up', '鸟瞰' => 'bird_eye',
        ];
        $angleMap = [
            '平视' => 'eye_level', '仰视' => 'low_angle',
            '俯视' => 'high_angle', '荷兰角' => 'dutch_angle',
        ];
        $compMap = [
            '三分法' => 'rule_of_thirds', '对角线' => 'diagonal',
            '中心构图' => 'center', '对称式' => 'symmetric', '引导线' => 'leading_lines',
        ];

        $shot = $shotMap[$panel['shot'] ?? ''] ?? 'medium';
        $angle = $angleMap[$panel['angle'] ?? ''] ?? 'eye_level';
        $comp = $compMap[$panel['comp'] ?? ''] ?? 'rule_of_thirds';

        $charRef = 'C1:calm';
        $charText = $panel['action'] ?? '';
        if (mb_strpos($charText, '愤怒') !== false || mb_strpos($charText, '怒') !== false) {
            $charRef = 'C1:angry';
        } elseif (mb_strpos($charText, '悲伤') !== false || mb_strpos($charText, '哭') !== false) {
            $charRef = 'C1:sad';
        } elseif (mb_strpos($charText, '绝望') !== false) {
            $charRef = 'C1:exhausted';
        }

        $sceneId = 'S1';
        $location = $panel['location'] ?? '';
        if (mb_strpos($location, '古宅') !== false) $sceneId = 'S2';
        elseif (mb_strpos($location, '荒野') !== false || mb_strpos($location, '战场') !== false) $sceneId = 'S3';
        elseif (mb_strpos($location, '山') !== false) $sceneId = 'S4';
        elseif (mb_strpos($location, '地府') !== false || mb_strpos($location, '阴间') !== false) $sceneId = 'S5';

        $moodMap = [
            '阴森神秘' => 'mysterious', '肃杀紧张' => 'tense', '恐怖压迫' => 'horror',
            '宿命沉重' => 'melancholy', '凄美哀婉' => 'melancholy',
        ];
        $mood = $moodMap[$panel['mood'] ?? ''] ?? 'tense';

        $lightMap = [
            'mysterious' => 'rim_light_blue', 'tense' => 'hard_shadow',
            'horror' => 'hard_shadow', 'melancholy' => 'soft_diffused',
        ];
        $light = $lightMap[$mood] ?? 'side_light';

        return [
            'id' => $panel['panel_id'] ?? 'P0001',
            'seeds' => [
                'character' => $charRef,
                'scene' => $sceneId,
                'style' => $styleId,
            ],
            'variant_ops' => [
                'shot' => $shot,
                'angle' => $angle,
                'light' => $light,
                'mood' => $mood,
                'composition' => $comp,
            ],
            'raw' => [
                'action' => $panel['action'] ?? '',
                'location' => $panel['location'] ?? '',
                'mood' => $panel['mood'] ?? '',
            ],
        ];
    }
}
