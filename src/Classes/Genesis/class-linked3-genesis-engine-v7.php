<?php
namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class Linked3_Genesis_SeedLibrary {
    private static ?Linked3_Genesis_SeedLibrary $instance = null;
    private array $characters = [];
    private array $scenes = [];
    private array $styles = [];
    private array $operators = [];
    private string $libDir;

        public static function instance() : mixed { return Linked3_Genesis_V7_Extras::instance(); }

        public function __construct() { return Linked3_Genesis_V7_Generator::__construct(); }

        public function loadAll() : mixed { return Linked3_Genesis_V7_Extras::loadAll(); }

    public function getCharacter(string $id): ?array {
        return $this->characters[$id] ?? null;
    }
    public function getScene(string $id): ?array {
        return $this->scenes[$id] ?? null;
    }
    public function getStyle(string $id): ?array {
        return $this->styles[$id] ?? null;
    }
    public function getOperators(): array {
        return $this->operators;
    }
    public function forkCharacter(string $id, string $newId, array $overrides = []): ?array {
        $base = $this->getCharacter($id);
        if (!$base) return null;
        $forked = array_merge($base, $overrides);
        $forked['id'] = $newId;
        $forked['forked_from'] = $id;
        return $forked;
    }
}

class Linked3_Genesis_IRCompiler {
    public function validateAtom(array $atom): array {
        $errors = [];
        if (empty($atom['seeds']['character'])) $errors[] = 'missing character seed';
        if (empty($atom['seeds']['scene'])) $errors[] = 'missing scene seed';
        if (empty($atom['seeds']['style'])) $errors[] = 'missing style seed';
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function compileIR(array $atom): array {
        $lib = Linked3_Genesis_SeedLibrary::instance();
        $charId = explode(':', $atom['seeds']['character'] ?? 'C1:calm')[0];
        $charState = explode(':', $atom['seeds']['character'] ?? 'C1:calm')[1] ?? 'calm';
        $character = $lib->getCharacter($charId);
        $scene = $lib->getScene($atom['seeds']['scene'] ?? 'S1');
        $style = $lib->getStyle($atom['seeds']['style'] ?? 'ST1');

        $ops = $atom['variant_ops'] ?? [];

        $subject = $this->buildSubject($character, $charState);
        $environment = $this->buildEnvironment($scene, $ops);
        $camera = $this->opsToEn($ops);

        return [
            'subject' => $subject,
            'environment' => $environment,
            'camera' => $camera,
            'style_mod' => $style['style_mod'] ?? '',
            'style_render' => $style['style_render'] ?? '',
            'style_lighting' => $style['style_lighting'] ?? '',
            'style_negative' => $style['style_negative'] ?? '',
            'atmosphere' => $style['atmosphere'] ?? '',
            'colors' => $style['colors'] ?? [],
            'negative' => $style['negative'] ?? '',
            'camera_enhanced' => $style['camera_enhanced'] ?? '',
            'skin_detail' => $style['skin_detail'] ?? '',
            'lens_params' => $style['lens_params'] ?? '',
        ];
    }

    private function buildSubject(?array $character, string $state): string {
        if (!$character) return 'a figure';
        $parts = [];
        $appearance = $character['appearance'] ?? [];
        if (!empty($appearance['gender'])) $parts[] = $appearance['gender'];
        if (!empty($appearance['age_range'])) $parts[] = $appearance['age_range'];
        if (!empty($appearance['build'])) $parts[] = $appearance['build'];

        $stateMap = $character['states'][$state] ?? $character['states']['calm'] ?? [];
        if (!empty($stateMap['expression'])) $parts[] = $stateMap['expression'];
        if (!empty($stateMap['pose'])) $parts[] = $stateMap['pose'];
        if (!empty($stateMap['clothing'])) $parts[] = $stateMap['clothing'];

        if (!empty($appearance['distinctive_features'])) {
            foreach ($appearance['distinctive_features'] as $f) $parts[] = $f;
        }
        return implode(', ', $parts);
    }

    private function buildEnvironment(?array $scene, array $ops): string {
        if (!$scene) return 'a location';
        $parts = [];
        $parts[] = $scene['location_type'] ?? '';
        $parts[] = $scene['time_of_day'] ?? '';
        $parts[] = $scene['weather'] ?? '';
        if (!empty($scene['atmosphere_elements'])) {
            foreach ($scene['atmosphere_elements'] as $e) $parts[] = $e;
        }
        if (!empty($ops['particle'])) {
            $particleMap = [
                'rain' => 'rain falling', 'snow' => 'snow falling',
                'fog_wisps' => 'wisps of fog', 'embers' => 'floating embers',
            ];
            if (isset($particleMap[$ops['particle']])) $parts[] = $particleMap[$ops['particle']];
        }
        return implode(', ', array_filter($parts));
    }

    public function toMidjourney(array $ir): string {
        $parts = array_filter([
            $ir['style_mod'], $ir['subject'], $ir['environment'],
            $ir['camera'], $ir['style_render'], $ir['style_lighting'],
        ]);
        if (!empty($ir['camera_enhanced'])) $parts[] = $ir['camera_enhanced'];
        if (!empty($ir['skin_detail'])) $parts[] = $ir['skin_detail'];
        if (!empty($ir['lens_params'])) $parts[] = $ir['lens_params'];
        $prompt = implode('. ', $parts) . '.';
        $negatives = array_filter([$ir['negative'], $ir['style_negative']]);
        if (!empty($negatives)) {
            $prompt .= ' --no ' . implode(', ', $negatives);
        }
        return $prompt . ' --ar 2:3 --s 750 --style raw --niji 6';
    }

    public function toStableDiffusion(array $ir): string {
        $parts = array_filter([
            $ir['style_mod'], '(' . $ir['subject'] . ':1.3)', $ir['environment'],
            $ir['camera'], $ir['style_render'], $ir['style_lighting'],
        ]);
        if (!empty($ir['camera_enhanced'])) $parts[] = $ir['camera_enhanced'];
        if (!empty($ir['skin_detail'])) $parts[] = $ir['skin_detail'];
        $prompt = implode(', ', $parts);
        $negatives = array_filter([$ir['negative'], $ir['style_negative']]);
        $negative = 'Negative: ' . implode(', ', $negatives);
        return $prompt . "\n[Params] Steps:30 CFG:7 Sampler:DPM++ 2M Karras Size:768x1024\n" . $negative;
    }

    public function toDallE(array $ir): string {
        $parts = array_filter([
            $ir['style_mod'], $ir['subject'], $ir['environment'],
            $ir['camera'], $ir['style_render'], $ir['style_lighting'],
            $ir['atmosphere'] ? $ir['atmosphere'] . ' atmosphere' : '',
        ]);
        if (!empty($ir['camera_enhanced'])) $parts[] = $ir['camera_enhanced'];
        if (!empty($ir['skin_detail'])) $parts[] = $ir['skin_detail'];
        return 'A photorealistic image: ' . implode(', ', $parts) . '. The image should have a cinematic, high-quality feel.';
    }

    private function opsToEn(array $ops): string {
        $shotMap = [
            'extreme_wide' => 'extreme wide shot', 'wide' => 'wide shot',
            'medium_wide' => 'medium wide shot', 'medium' => 'medium shot',
            'medium_close' => 'medium close-up', 'close_up' => 'close-up',
            'extreme_close_up' => 'extreme close-up', 'macro' => 'macro shot',
        ];
        $angleMap = [
            'eye_level' => 'eye level', 'low_angle' => 'low angle',
            'high_angle' => 'high angle', 'bird_eye' => 'bird eye view',
            'dutch_angle' => 'dutch angle', 'worm_eye' => 'worm eye view',
        ];
        $lightMap = [
            'natural' => 'natural lighting', 'side_light' => 'side lighting',
            'back_light' => 'backlight', 'rim_light_blue' => 'blue rim light',
            'rim_light_warm' => 'warm rim light', 'hard_shadow' => 'hard shadow',
            'soft_diffused' => 'soft diffused light', 'volumetric' => 'volumetric lighting',
            'practical_warm' => 'warm practical light', 'neon_glow' => 'neon glow',
        ];
        $moodMap = [
            'calm' => 'calm mood', 'tense' => 'tense atmosphere',
            'melancholy' => 'melancholy mood', 'rage' => 'rage expression',
            'mysterious' => 'mysterious atmosphere', 'hopeful' => 'hopeful mood',
            'desperate' => 'desperate atmosphere', 'serene' => 'serene mood',
            'horror' => 'horror atmosphere', 'epic' => 'epic scale',
        ];
        $compMap = [
            'rule_of_thirds' => 'rule of thirds composition', 'diagonal' => 'diagonal composition',
            'center' => 'center composition', 'symmetric' => 'symmetric composition',
            'leading_lines' => 'leading lines composition', 'golden_ratio' => 'golden ratio composition',
            'frame_within_frame' => 'frame within frame',
        ];
        $parts = [];
        if (!empty($ops['shot'])) $parts[] = $shotMap[$ops['shot']] ?? $ops['shot'];
        if (!empty($ops['angle'])) $parts[] = $angleMap[$ops['angle']] ?? $ops['angle'];
        if (!empty($ops['light'])) $parts[] = $lightMap[$ops['light']] ?? $ops['light'];
        if (!empty($ops['mood'])) $parts[] = $moodMap[$ops['mood']] ?? $ops['mood'];
        if (!empty($ops['composition'])) $parts[] = $compMap[$ops['composition']] ?? $ops['composition'];
        return implode(', ', $parts);
    }
}

class Linked3_Genesis_Mutator {
    private array $operators;

    const DEFAULT_VARIANT_COUNT = 3;

    private static $strategies = [
        ['shot' => 'wide', 'angle' => 'high_angle', 'light' => 'back_light', 'mood' => 'epic', 'composition' => 'diagonal'],
        ['shot' => 'medium', 'angle' => 'eye_level', 'light' => 'side_light', 'mood' => 'tense', 'composition' => 'rule_of_thirds'],
        ['shot' => 'extreme_close_up', 'angle' => 'eye_level', 'light' => 'rim_light_warm', 'mood' => 'rage', 'composition' => 'center'],
        ['shot' => 'medium_wide', 'angle' => 'dutch_angle', 'light' => 'hard_shadow', 'mood' => 'horror', 'composition' => 'frame_within_frame'],
        ['shot' => 'close_up', 'angle' => 'low_angle', 'light' => 'volumetric', 'mood' => 'hopeful', 'composition' => 'golden_ratio'],
        ['shot' => 'medium', 'angle' => 'eye_level', 'light' => 'soft_diffused', 'mood' => 'melancholy', 'composition' => 'symmetric', 'particle' => 'fog_wisps'],
        ['shot' => 'wide', 'angle' => 'bird_eye', 'light' => 'natural', 'mood' => 'serene', 'composition' => 'leading_lines'],
        ['shot' => 'close_up', 'angle' => 'eye_level', 'light' => 'neon_glow', 'mood' => 'mysterious', 'composition' => 'rule_of_thirds'],
    ];

    public function __construct() {
        $this->operators = Linked3_Genesis_SeedLibrary::instance()->getOperators();
    }

    public function generateVariants(array $baseAtom, ?int $count = null): array {
        if ($count === null) {
            $count = intval(get_option(LINKED3_OPTION_PREFIX . 'v7_variant_count', self::DEFAULT_VARIANT_COUNT));
        }
        $count = max(1, min(8, $count)); // 限制1-8, 避免过度调用

        $variants = [];
        $stratCount = count(self::$strategies);

        for ($i = 0; $i < $count; $i++) {
            $variant = $baseAtom;
            $variant['id'] = ($baseAtom['id'] ?? 'atom') . '_v' . ($i + 1);

            $strategy = self::$strategies[$i % $stratCount];
            $variant['variant_ops'] = array_merge($baseAtom['variant_ops'] ?? [], $strategy);
            $variant['diff'] = $strategy;

            $variants[] = $variant;
        }

        if (class_exists('\Linked3\Classes\Genesis\Linked3_Genesis_Logger')) {
            Linked3_Genesis_Logger::stage('v7_mutate', '生成 ' . $count . ' 个变异体', [
                'atom_id' => $baseAtom['id'] ?? '',
                'strategies_used' => array_slice(array_keys(self::$strategies), 0, $count),
            ]);
        }

        return $variants;
    }

    public function applyTextDirective(array $atom, string $text): array {
        $newAtom = $atom;
        $newAtom['id'] = ($atom['id'] ?? 'atom') . '_text_' . time();

        $emotionMap = [
            '绝望' => 'desperate', '崩溃' => 'desperate',
            '愤怒' => 'rage', '怒' => 'rage', '暴怒' => 'rage',
            '悲伤' => 'melancholy', '哭' => 'melancholy', '哀' => 'melancholy',
            '希望' => 'hopeful', '期待' => 'hopeful',
            '平静' => 'calm', '宁静' => 'serene',
            '神秘' => 'mysterious', '诡异' => 'mysterious',
            '恐怖' => 'horror', '惊悚' => 'horror',
            '史诗' => 'epic', '壮阔' => 'epic',
        ];
        foreach ($emotionMap as $kw => $mood) {
            if (mb_strpos($text, $kw) !== false) {
                $newAtom['variant_ops']['mood'] = $mood;
                $charRef = $newAtom['seeds']['character'] ?? 'C1:calm';
                $charId = explode(':', $charRef)[0];
                $stateMap = ['desperate' => 'exhausted', 'rage' => 'angry', 'melancholy' => 'sad'];
                $state = $stateMap[$mood] ?? 'calm';
                $newAtom['seeds']['character'] = $charId . ':' . $state;
                break; // 只取第一个匹配的情绪
            }
        }

        if (preg_match('/暗|黑暗|阴暗/', $text)) {
            $newAtom['variant_ops']['light'] = 'hard_shadow';
        } elseif (preg_match('/霓虹|赛博/', $text)) {
            $newAtom['variant_ops']['light'] = 'neon_glow';
        } elseif (preg_match('/柔光|柔和/', $text)) {
            $newAtom['variant_ops']['light'] = 'soft_diffused';
        } elseif (preg_match('/体积光|耶稣光/', $text)) {
            $newAtom['variant_ops']['light'] = 'volumetric';
        }

        if (preg_match('/远景|全景/', $text)) {
            $newAtom['variant_ops']['shot'] = 'wide';
        } elseif (preg_match('/特写|近景/', $text)) {
            $newAtom['variant_ops']['shot'] = 'close_up';
        } elseif (preg_match('/中景/', $text)) {
            $newAtom['variant_ops']['shot'] = 'medium';
        } elseif (preg_match('/鸟瞰|俯瞰/', $text)) {
            $newAtom['variant_ops']['shot'] = 'bird_eye';
        }

        if (preg_match('/仰视|仰角/', $text)) {
            $newAtom['variant_ops']['angle'] = 'low_angle';
        } elseif (preg_match('/俯视|俯角/', $text)) {
            $newAtom['variant_ops']['angle'] = 'high_angle';
        } elseif (preg_match('/荷兰角|倾斜/', $text)) {
            $newAtom['variant_ops']['angle'] = 'dutch_angle';
        }

        $particleMap = [
            '雨' => 'rain', '雪' => 'snow', '雾' => 'fog_wisps',
            '火' => 'embers', '火焰' => 'embers', '火星' => 'embers',
        ];
        foreach ($particleMap as $kw => $particle) {
            if (mb_strpos($text, $kw) !== false) {
                $newAtom['variant_ops']['particle'] = $particle;
                break;
            }
        }

        if (preg_match('/三分法/', $text)) {
            $newAtom['variant_ops']['composition'] = 'rule_of_thirds';
        } elseif (preg_match('/对角线/', $text)) {
            $newAtom['variant_ops']['composition'] = 'diagonal';
        } elseif (preg_match('/对称/', $text)) {
            $newAtom['variant_ops']['composition'] = 'symmetric';
        } elseif (preg_match('/引导线/', $text)) {
            $newAtom['variant_ops']['composition'] = 'leading_lines';
        }

        return $newAtom;
    }
}

class Linked3_Genesis_Evaluator {
    private int $threshold;

    public function __construct(int $threshold = 28) {
        $this->threshold = $threshold;
    }

    public function scoreVariant(array $ir): array {
        $score = 0;
        $feedback = [];

        $subWords = explode(',', $ir['subject'] ?? '');
        $subCount = count($subWords);
        if ($subCount >= 3 && $subCount <= 12) {
            $score += 8;
        } else {
            $score += 3;
            $feedback[] = "主体描述词数异常 ({$subCount}词)";
        }

        $env = strtolower($ir['environment'] ?? '');
        $light = strtolower($ir['camera'] ?? '');
        $hasConflict = false;
        if (strpos($env, 'rain') !== false && strpos($light, 'bright') !== false) {
            $score += 1;
            $feedback[] = "雨天与明亮光线冲突";
            $hasConflict = true;
        }
        if (strpos($env, 'dark') !== false && strpos($light, 'natural') !== false) {
            $score += 2;
            $feedback[] = "暗场景与自然光冲突";
            $hasConflict = true;
        }
        if (!$hasConflict) $score += 9;

        if (!empty($ir['negative']) || !empty($ir['style_negative'])) {
            $score += 5;
        } else {
            $feedback[] = "缺少负向提示词";
        }

        $totalLen = strlen($ir['subject'] ?? '') + strlen($ir['environment'] ?? '');
        if ($totalLen < 400) {
            $score += 5;
        } elseif ($totalLen < 600) {
            $score += 3;
            $feedback[] = "提示词较长";
        } else {
            $score += 1;
            $feedback[] = "提示词过长可能被截断";
        }

        $styleMod = strtolower($ir['style_mod'] ?? '');
        if (preg_match('/ink-wash|manga|cinematic|cyberpunk|photography|painting|gothic|ukiyo|steampunk|zen/i', $styleMod)) {
            $score += 5;
        } else {
            $score += 1;
            $feedback[] = "缺少风格锚点关键词";
        }

        if (!empty($ir['colors'])) {
            $score += 5;
        } else {
            $score += 1;
            $feedback[] = "缺少色彩方案";
        }

        return ['score' => $score, 'feedback' => $feedback, 'max_score' => 40];
    }

    public function cull(array $variantsData): array {
        $survivors = [];
        $culled = [];
        foreach ($variantsData as $v) {
            if (($v['score'] ?? 0) >= $this->threshold) {
                $survivors[] = $v;
            } else {
                $culled[] = $v;
            }
        }
        return ['survivors' => $survivors, 'culled' => $culled];
    }
}

class Linked3_Genesis_Pipeline {
    private Linked3_Genesis_IRCompiler $compiler;
    private Linked3_Genesis_Mutator $mutator;
    private Linked3_Genesis_Evaluator $evaluator;

    public function __construct() {
        $this->compiler = new Linked3_Genesis_IRCompiler();
        $this->mutator = new Linked3_Genesis_Mutator();
        $threshold = intval(get_option(LINKED3_OPTION_PREFIX . 'v7_cull_threshold', 28));
        $this->evaluator = new Linked3_Genesis_Evaluator($threshold);
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
                if (class_exists('\Linked3\Classes\Genesis\Linked3_Genesis_Logger')) {
                    Linked3_Genesis_Logger::warn('v7_pipeline', '全灭兜底: ' . $panelId, [
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

        if (class_exists('\Linked3\Classes\Genesis\Linked3_Genesis_Logger')) {
            Linked3_Genesis_Logger::info('v7_pipeline', 'Pipeline完成', [
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
