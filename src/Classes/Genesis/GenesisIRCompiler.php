<?php

declare(strict_types=1);
/**
 * Linked3_Genesis_IRCompiler — extracted from GenesisSeedLibrary.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Genesis

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisIRCompiler {
    public function validateAtom(array $atom): array {
        $errors = [];
        if (empty($atom['seeds']['character'])) $errors[] = 'missing character seed';
        if (empty($atom['seeds']['scene'])) $errors[] = 'missing scene seed';
        if (empty($atom['seeds']['style'])) $errors[] = 'missing style seed';
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function compileIR(array $atom): array {
        $lib = GenesisSeedLibrary::instance();
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
