<?php

declare(strict_types=1);
/**
 * GenesisPromptAssembler — extracted from GenesisAtomIndex.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Genesis
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisPromptAssembler {
    private GenesisAtomIndex $index;

    public function __construct() {
        $this->index = GenesisAtomIndex::instance();
    }

    public function assemble(array $atoms, string $styleId, string $platform = 'midjourney'): array {
        return $this->assembleFull($atoms, [], $styleId, $platform);
    }

    public function assembleFull(array $atoms, array $panel, string $styleId, string $platform = 'midjourney'): array {
        $styleConfig = $this->index->getStyleConfig($styleId);
        $styleName = $styleConfig['name_cn'] ?? $styleId;
        $styleType = $styleConfig['style_type'] ?? 'painted';

        $charData = $this->buildCharacterPrompts($atoms);
        $sceneDesc = $this->buildSceneDescription($atoms);
        $cameraDesc = $this->buildCameraDescription($atoms, $panel);
        $compEn = $this->buildComposition($atoms, $panel);
        [$moodEn, $atmoStr] = $this->buildMoodAndAtmosphere($atoms, $panel, $styleConfig);
        $colorDesc = $this->buildColorPalette($styleConfig);
        $symbolDesc = $this->buildSymbols($styleConfig, $charData['details']);
        $styleParts = $this->buildStyleParts($styleConfig);
        $actionEn = $this->translateActionEn($panel['action'] ?? '');

        $promptEn = $this->assemblePrompt(
            $styleParts['keywords'], $charData['prompts'], $sceneDesc, $actionEn,
            $cameraDesc, $compEn, $moodEn, $atmoStr, $colorDesc, $styleParts, $symbolDesc
        );

        $promptEn = $this->applyRealisticEnhancements($promptEn, $styleConfig, $styleType);
        $promptEn = $this->applyNegativePrompt($promptEn, $styleParts['negative']);

        $platformParams = $this->getPlatformParams($platform);
        $promptWithParams = $this->adaptPlatform($promptEn, $platform, $platformParams);

        return [
            'prompt_en' => $promptEn,
            'prompt_with_params' => $promptWithParams,
            'style' => $styleId,
            'style_name' => $styleName,
            'style_type' => $styleType,
            'platform' => $platform,
            'platform_params' => $platformParams,
            'characters' => $charData['details'],
            'scene' => $atoms['scene']['fields'] ?? [],
            'camera' => $atoms['camera']['fields'] ?? [],
            'composition' => $atoms['composition']['fields'] ?? [],
            'atmosphere' => $atoms['atmosphere']['fields'] ?? [],
            'color_mapping' => $atoms['color_mapping']['fields'] ?? [],
            'template' => $atoms['template']['fields'] ?? [],
            'style_config' => $styleConfig,
            'enhanced' => $styleType === 'realistic',
        ];
    }

    /**
     * Build character prompts and details from atoms.
     */
    private function buildCharacterPrompts(array $atoms): array {
        $charPrompts = [];
        $charDetails = [];
        foreach ($atoms['characters'] as $charId) {
            $char = $this->index->getAtom($charId);
            if (!$char) continue;
            $f = $char['fields'];

            $dnaParts = array_filter([
                $f['hair'] ?? '', $f['face'] ?? '', $f['body'] ?? '',
                $f['costume'] ?? '', $f['accessory'] ?? '',
                $f['pose'] ?? '', $f['expression'] ?? '', $f['special_mark'] ?? '',
            ]);
            $dnaStr = str_replace(['"', "'"], '', implode(', ', $dnaParts));
            $charPrompts[] = $f['prompt_kw'] . ' (' . $dnaStr . ')';

            $charDetails[] = [
                'id' => $charId,
                'role' => $f['role'] ?? '',
                'prompt_kw' => $f['prompt_kw'] ?? '',
                'detail' => $dnaStr,
            ];
        }
        return ['prompts' => $charPrompts, 'details' => $charDetails];
    }

    /**
     * Build scene description from scene atom fields.
     */
    private function buildSceneDescription(array $atoms): string {
        $sceneFields = $atoms['scene']['fields'] ?? [];
        $sceneParts = array_filter([
            $sceneFields['scene_name'] ?? '',
            $sceneFields['location_desc'] ?? '',
            $sceneFields['weather'] ?? '',
            $sceneFields['time'] ?? '',
            $sceneFields['atmosphere'] ?? '',
        ]);
        return str_replace(['"', "'"], '', implode(', ', $sceneParts));
    }

    /**
     * Build camera description from panel and camera atom.
     */
    private function buildCameraDescription(array $atoms, array $panel): string {
        $aiShot = $panel['shot'] ?? '';
        $aiAngle = $panel['angle'] ?? '';
        $cameraFields = $atoms['camera']['fields'] ?? [];
        $shotMap = ['远景' => 'wide shot', '全景' => 'full shot', '中景' => 'medium shot', '近景' => 'close-up', '特写' => 'extreme close-up', '鸟瞰' => 'bird eye view', '荷兰角' => 'dutch angle'];
        $angleMap = ['平视' => 'eye level', '仰视' => 'low angle', '俯视' => 'high angle'];
        $shotEn = $shotMap[$aiShot] ?? ($cameraFields['prompt_kw'] ?? 'medium shot');
        $angleEn = $angleMap[$aiAngle] ?? 'eye level';
        return $shotEn . ' ' . $angleEn;
    }

    /**
     * Build composition from panel.
     */
    private function buildComposition(array $atoms, array $panel): string {
        $aiComp = $panel['comp'] ?? '';
        $compMap = ['三分法' => 'rule of thirds', '对角线' => 'diagonal composition', '中心构图' => 'center composition', '对称式' => 'symmetric composition', '引导线' => 'leading lines'];
        return $compMap[$aiComp] ?? 'rule of thirds';
    }

    /**
     * Build mood and atmosphere strings.
     */
    private function buildMoodAndAtmosphere(array $atoms, array $panel, array $styleConfig): array {
        $aiMood = $panel['mood'] ?? '';
        $moodMap = ['阴森神秘' => 'eerie mysterious', '肃杀紧张' => 'tense murderous', '恐怖压迫' => 'horror oppressive', '宿命沉重' => 'fatalistic heavy', '凄美哀婉' => 'poignant sorrowful'];
        $moodEn = $moodMap[$aiMood] ?? 'eerie mysterious';
        $styleAtmosphere = $styleConfig['atmosphere'] ?? [];
        $atmoStr = implode(', ', $styleAtmosphere);
        return [$moodEn, $atmoStr];
    }

    /**
     * Build color palette description from style config.
     */
    private function buildColorPalette(array $styleConfig): string {
        $colorPalette = $styleConfig['color_palette'] ?? [];
        $colorParts = [];
        foreach (['primary', 'secondary', 'accent'] as $tier) {
            foreach (($colorPalette[$tier] ?? []) as $c) {
                $colorParts[] = $c['hex'] . ' ' . ($c['use'] ?? '');
            }
        }
        return 'color palette: ' . implode(', ', $colorParts);
    }

    /**
     * Build symbol description from style symbols and character details.
     */
    private function buildSymbols(array $styleConfig, array $charDetails): string {
        $styleSymbols = $styleConfig['symbols'] ?? [];
        $allSymbols = array_unique(array_merge($styleSymbols, explode('+', $charDetails[0]['detail'] ?? '')));
        return implode(', ', array_filter($allSymbols));
    }

    /**
     * Extract style-related parts from config.
     */
    private function buildStyleParts(array $styleConfig): array {
        return [
            'keywords' => $styleConfig['prompt_keywords'] ?? '',
            'negative' => $styleConfig['negative_keywords'] ?? '',
            'render' => $styleConfig['render'] ?? '',
            'lighting' => $styleConfig['lighting'] ?? '',
            'line' => $styleConfig['line_style'] ?? '',
        ];
    }

    /**
     * Assemble the final English prompt from all parts.
     */
    private function assemblePrompt(
        string $styleKeywords, array $charPrompts, string $sceneDesc,
        string $actionEn, string $cameraDesc, string $compEn,
        string $moodEn, string $atmoStr, string $colorDesc, array $styleParts, string $symbolDesc
    ): string {
        $promptParts = array_filter([
            $styleKeywords,
            implode('; ', $charPrompts),
            $sceneDesc,
            $actionEn,
            $cameraDesc,
            $compEn,
            $moodEn . ' ' . $atmoStr . ' atmosphere',
            $colorDesc,
            $styleParts['render'],
            $styleParts['lighting'],
            $styleParts['line'],
            'symbols: ' . $symbolDesc,
        ]);
        return implode('. ', $promptParts) . '.';
    }

    /**
     * Apply realistic style enhancements if applicable.
     */
    private function applyRealisticEnhancements(string $promptEn, array $styleConfig, string $styleType): string {
        if ($styleType !== 'realistic') return $promptEn;

        $enhancements = [];
        if (!empty($styleConfig['camera'])) $enhancements[] = $styleConfig['camera'];
        if (!empty($styleConfig['skin_detail'])) $enhancements[] = $styleConfig['skin_detail'];
        if (!empty($styleConfig['lens_params'])) {
            $lp = $styleConfig['lens_params'];
            $enhancements[] = ($lp['focal_length'] ?? '') . ' ' . ($lp['aperture'] ?? '') . ' ' . ($lp['sensor'] ?? '');
        }
        if (!empty($enhancements)) {
            $promptEn .= '. ' . implode(', ', $enhancements);
        }
        return $promptEn;
    }

    /**
     * Append negative prompt if present.
     */
    private function applyNegativePrompt(string $promptEn, string $styleNegative): string {
        if ($styleNegative) {
            $promptEn .= ' || negative: ' . $styleNegative;
        }
        return $promptEn;
    }

    private function adaptPlatform(string $prompt, string $platform, string $params): string {
        switch ($platform) {
            case 'midjourney':
                return $prompt . ' ' . $params;
            case 'sdxl':
                return '(' . $prompt . '), high quality, masterpiece, best quality' .
                    "\n[Params] Steps:30 CFG:7 Sampler:DPM++ 2M Karras Size:768x1024";
            case 'dalle':
                return 'A photorealistic image: ' . $prompt . '. The image should have a cinematic, high-quality feel.';
            default:
                return $prompt . ' ' . $params;
        }
    }

    private function translateActionEn(string $action): string {
        if (empty($action)) return '';
        $map = [
            '驱魔师' => 'exorcist', '白龙' => 'white dragon', '老道长' => 'old taoist master',
            '妖魔' => 'demon', '撑伞' => 'holding umbrella', '站在' => 'standing at',
            '现身' => 'appearing', '对峙' => 'confronting', '战斗' => 'fighting',
            '出鞘' => 'drawing sword', '施法' => 'casting spell', '封印' => 'sealing',
            '倾听' => 'listening', '回忆' => 'remembering', '飞向' => 'flying toward',
            '目送' => 'watching', '手持' => 'holding', '进入' => 'entering',
            '坐在' => 'sitting at', '对视' => 'eye contact', '逼近' => 'approaching',
            '桃木剑' => 'peach wood sword', '拂尘' => 'whisk', '符箓' => 'talisman',
            '古宅' => 'mansion', '雨夜' => 'rainy night', '内堂' => 'hall',
            '后院' => 'backyard', '屋顶' => 'rooftop', '门前' => 'doorway',
            '村民' => 'villager', '说话' => 'talking', '对话' => 'dialogue',
            '逃跑' => 'running away', '追' => 'chasing', '倒下' => 'falling',
            '抬头' => 'looking up', '转身' => 'turning around', '伸手' => 'reaching out',
            '跪下' => 'kneeling', '站起' => 'standing up', '流泪' => 'crying',
            '微笑' => 'smiling', '怒吼' => 'roaring', '低语' => 'whispering',
        ];
        $en = $action;
        foreach ($map as $zh => $e) {
            $en = str_replace($zh, $e, $en);
        }
        return $en;
    }

    private function getPlatformParams(string $platform): string {
        $params = $this->index->getByType('platform_param');
        foreach ($params as $pid) {
            $atom = $this->index->getAtom($pid);
            if ($atom && ($atom['fields']['platform'] ?? '') === $platform) {
                return $atom['fields']['params'] ?? '';
            }
        }
        return '--ar 2:3 --s 750 --style raw --no text';
    }
}
