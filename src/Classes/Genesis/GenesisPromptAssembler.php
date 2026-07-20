<?php

declare(strict_types=1);
/**
 * GenesisPromptAssembler — extracted from GenesisAtomIndex.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Genesis

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
        $styleType = $styleConfig['style_type'] ?? 'painted'; // painted | realistic

        $styleKeywords = $styleConfig['prompt_keywords'] ?? '';
        $styleNegative = $styleConfig['negative_keywords'] ?? '';
        $styleRender = $styleConfig['render'] ?? '';
        $styleLighting = $styleConfig['lighting'] ?? '';
        $styleAtmosphere = $styleConfig['atmosphere'] ?? [];
        $styleSymbols = $styleConfig['symbols'] ?? [];
        $styleLine = $styleConfig['line_style'] ?? '';

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

        $sceneFields = $atoms['scene']['fields'] ?? [];
        $sceneParts = array_filter([
            $sceneFields['scene_name'] ?? '',
            $sceneFields['location_desc'] ?? '',
            $sceneFields['weather'] ?? '',
            $sceneFields['time'] ?? '',
            $sceneFields['atmosphere'] ?? '',
        ]);
        $sceneDesc = str_replace(['"', "'"], '', implode(', ', $sceneParts));

        $actionDesc = $panel['action'] ?? '';
        $actionEn = $this->translateActionEn($actionDesc);

        $aiShot = $panel['shot'] ?? '';
        $aiAngle = $panel['angle'] ?? '';
        $cameraFields = $atoms['camera']['fields'] ?? [];
        $shotMap = ['远景' => 'wide shot', '全景' => 'full shot', '中景' => 'medium shot', '近景' => 'close-up', '特写' => 'extreme close-up', '鸟瞰' => 'bird eye view', '荷兰角' => 'dutch angle'];
        $angleMap = ['平视' => 'eye level', '仰视' => 'low angle', '俯视' => 'high angle'];
        $shotEn = $shotMap[$aiShot] ?? ($cameraFields['prompt_kw'] ?? 'medium shot');
        $angleEn = $angleMap[$aiAngle] ?? 'eye level';
        $cameraDesc = $shotEn . ' ' . $angleEn;

        $aiComp = $panel['comp'] ?? '';
        $compFields = $atoms['composition']['fields'] ?? [];
        $compMap = ['三分法' => 'rule of thirds', '对角线' => 'diagonal composition', '中心构图' => 'center composition', '对称式' => 'symmetric composition', '引导线' => 'leading lines'];
        $compEn = $compMap[$aiComp] ?? 'rule of thirds';

        $aiMood = $panel['mood'] ?? '';
        $atmoFields = $atoms['atmosphere']['fields'] ?? [];
        $moodMap = ['阴森神秘' => 'eerie mysterious', '肃杀紧张' => 'tense murderous', '恐怖压迫' => 'horror oppressive', '宿命沉重' => 'fatalistic heavy', '凄美哀婉' => 'poignant sorrowful'];
        $moodEn = $moodMap[$aiMood] ?? 'eerie mysterious';
        $atmoStr = implode(', ', $styleAtmosphere);

        $colorPalette = $styleConfig['color_palette'] ?? [];
        $colorParts = [];
        foreach (($colorPalette['primary'] ?? []) as $c) {
            $colorParts[] = $c['hex'] . ' ' . ($c['use'] ?? '');
        }
        foreach (($colorPalette['secondary'] ?? []) as $c) {
            $colorParts[] = $c['hex'] . ' ' . ($c['use'] ?? '');
        }
        foreach (($colorPalette['accent'] ?? []) as $c) {
            $colorParts[] = $c['hex'] . ' ' . ($c['use'] ?? '');
        }
        $colorDesc = 'color palette: ' . implode(', ', $colorParts);

        $allSymbols = array_unique(array_merge($styleSymbols, explode('+', $charDetails[0]['detail'] ?? '')));
        $symbolDesc = implode(', ', array_filter($allSymbols));

        $promptParts = array_filter([
            $styleKeywords,                               // 风格基底
            implode('; ', $charPrompts),                  // 角色 DNA
            $sceneDesc,                                   // 场景
            $actionEn,                                    // 动作
            $cameraDesc,                                  // 镜头
            $compEn,                                      // 构图
            $moodEn . ' ' . $atmoStr . ' atmosphere',    // 氛围
            $colorDesc,                                   // 色彩
            $styleRender,                                 // 渲染
            $styleLighting,                               // 光影
            $styleLine,                                   // 线条
            'symbols: ' . $symbolDesc,                    // 符号
        ]);
        $promptEn = implode('. ', $promptParts) . '.';

        if ($styleType === 'realistic') {
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
        }

        if ($styleNegative) {
            $promptEn .= ' || negative: ' . $styleNegative;
        }

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
            'characters' => $charDetails,
            'scene' => $sceneFields,
            'camera' => $cameraFields,
            'composition' => $compFields,
            'atmosphere' => $atmoFields,
            'color_mapping' => $atoms['color_mapping']['fields'] ?? [],
            'template' => $atoms['template']['fields'] ?? [],
            'style_config' => $styleConfig,
            'enhanced' => $styleType === 'realistic',
        ];
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
