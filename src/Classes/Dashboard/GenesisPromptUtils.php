<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;
/**
 * GenesisPromptUtils — G8 extraction.
 * @since 27.13.0
 */
class GenesisPromptUtils
{
    public static function genesisBuildNodePrompt(array $node, string $styleName, string $platform, string $styleId = '', ?array $seedDNA = null): string
    {
        switch ($platform) {
            case 'sdxl':
                $platformParams = ' high quality, masterpiece, best quality';
                break;
            case 'dalle':
                $platformParams = ' photorealistic, cinematic, high-quality';
                break;
            default:
                $platformParams = ' --ar 2:3 --s 750 --style raw --no text';
                break;
        }

        $shotMap = ['远景' => 'wide shot', '全景' => 'full shot', '中景' => 'medium shot', '近景' => 'close-up', '特写' => 'extreme close-up', '鸟瞰' => 'bird eye view'];
        $angleMap = ['平视' => 'eye level', '仰视' => 'low angle', '俯视' => 'high angle', '荷兰角' => 'dutch angle'];
        $compMap = ['三分法' => 'rule of thirds', '对角线' => 'diagonal composition', '中心构图' => 'center composition', '对称式' => 'symmetric', '引导线' => 'leading lines'];
        $shotEn = $shotMap[$node['shot'] ?? ''] ?? 'medium shot';
        $angleEn = $angleMap[$node['angle'] ?? ''] ?? 'eye level';
        $compEn = $compMap[$node['comp'] ?? ''] ?? 'rule of thirds';

        $styleExample = \GenesisStyleEngine::getPromptExample($styleId);
        $styleConstraint = \GenesisStyleEngine::getStyleConstraintEn($styleId);
        $metaPrompt = \GenesisStyleEngine::getMetaPrompt($styleId);
        $negativeKeywords = \GenesisStyleEngine::getNegativeKeywords($styleId);

        $location = $node['location'] ?? 'scene';
        $characters = implode(', ', $node['characters'] ?? []) ?: 'a lone figure';
        $action = $node['action'] ?? 'standing still';
        $mood = $node['mood'] ?? 'tense';

        return sprintf(
            "Write an English image generation prompt for one manga panel. The prompt must be a single paragraph of 80 to 150 words.\n\n" .
            "Scene: %s. Characters: %s. Action: %s. Camera: %s from %s, %s. Mood: %s. Style: %s.\n\n" .
            "Meta: %s\n\n" .
            "%s\n\n" .
            "CRITICAL — You MUST include ALL 8 visual elements in the prompt:\n" .
            "1. SUBJECT: Who is in the frame? Describe appearance, age, expression, pose\n" .
            "2. ENVIRONMENT: Where are they? Describe the setting, background, location details\n" .
            "3. LIGHTING: What is the light source? Describe direction, quality, color temperature\n" .
            "4. COSTUME: What are they wearing? Describe clothing, accessories, textures\n" .
            "5. PROPS: What objects are visible? Describe key items, tools, weapons\n" .
            "6. COMPOSITION: How is the frame arranged? Describe camera angle, framing, depth\n" .
            "7. ATMOSPHERE: What is the mood? Describe emotional tone, weather, air quality\n" .
            "8. STYLE: What is the art style? Describe medium, technique, color palette\n\n" .
            "RULES:\n" .
            "- Extract scene from the story content above, do NOT use generic scenes\n" .
            "- Do NOT include any labels, headers, or template markers\n" .
            "- Do NOT write the words subject, environment, clause, or panel\n" .
            "- Do NOT repeat any word\n" .
            "- End with:%s\n" .
            "- Negative prompt to avoid: %s\n\n" .
            "Example prompt for a different scene (do not copy this content, just match the style and 8-element structure):\n" .
            "%s\n\n" .
            ($seedDNA ? "SEED DNA (MUST embed these character/scene/color details for consistency):\n%s\n\n" : "") .
            "Now write the prompt for the scene above. Output only the prompt, nothing else.",
            $location,
            $characters,
            $action,
            $shotEn,
            $angleEn,
            $compEn,
            $mood,
            $styleName,
            $metaPrompt,
            $styleConstraint,
            $platformParams,
            $negativeKeywords,
            $styleExample,
            $seedDNA ? \GenesisSeedDNA::embedInPrompt($seedDNA, '') : ''
        );
    }

    public static function cleanAIPrompt(string $raw, string $platform): string
    {
        $text = trim($raw);
        $text = preg_replace('/^```(?:\w+)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text, '"\'');
        return $text;
    }

    public static function isAIPromptDegraded(string $prompt): bool
    {
        $len = mb_strlen($prompt);
        if ($len < 50) return true;
        if ($len > 2000) return true;

        $words = preg_split('/\s+/', strtolower($prompt));
        $words = array_filter($words, fn($w) => mb_strlen($w) > 0);
        if (count($words) < 10) return true;

        $cleanWords = [];
        foreach ($words as $w) {
            $cw = preg_replace('/[^\w\-]/', '', $w);
            if ($cw !== '') $cleanWords[] = $cw;
        }
        $totalWords = count($cleanWords);
        if ($totalWords < 10) return true;

        $maxConsecutive = 0;
        $currentRepeat = 0;
        $prevWord = '';
        foreach ($cleanWords as $w) {
            if ($w === $prevWord) {
                $currentRepeat++;
                $maxConsecutive = max($maxConsecutive, $currentRepeat);
            } else {
                $currentRepeat = 0;
            }
            $prevWord = $w;
        }
        if ($maxConsecutive >= 3) return true;

        $windowSize = 20;
        $repeatThreshold = 5;
        for ($i = 0; $i <= $totalWords - $windowSize; $i++) {
            $window = array_slice($cleanWords, $i, $windowSize);
            $counts = array_count_values($window);
            $maxCount = max($counts);
            if ($maxCount >= $repeatThreshold) return true;
        }

        $bigrams = [];
        for ($i = 0; $i < $totalWords - 1; $i++) {
            $bg = $cleanWords[$i] . ' ' . $cleanWords[$i + 1];
            $bigrams[$bg] = ($bigrams[$bg] ?? 0) + 1;
        }
        foreach ($bigrams as $bg => $cnt) {
            if ($cnt >= 3) return true;
        }

        $wordCounts = array_count_values($cleanWords);
        foreach ($wordCounts as $word => $count) {
            if (mb_strlen($word) < 3) continue;  // 跳过 2 字符以内
            if ($count / $totalWords > 0.25) return true;
        }

        $chineseCount = preg_match_all('/[\x{4e00}-\x{9fff}]/u', $prompt);
        if ($chineseCount / max(1, $len) > 0.2) return true;

        $bracketCount = preg_match_all('/\[[^\]]+\]/', $prompt);
        if ($bracketCount >= 2) return true;
        if (stripos($prompt, 'params:') !== false) return true;
        $leakPatterns = [
            '/panel info/i',
            '/output (only )?the prompt/i',
            '/generate one english/i',
            '/write an english image/i',
            '/now write the prompt/i',
        ];
        foreach ($leakPatterns as $pat) {
            if (preg_match($pat, $prompt)) return true;
        }

        $allDashes = preg_match_all('/--(\w+)/', $prompt, $m);
        $validMjParams = ['ar', 's', 'style', 'no', 'v', 'niji', 'q', 'chaos', 'tile', 'fast', 'relax'];
        $invalidDashCount = 0;
        foreach ($m[1] as $param) {
            if (!in_array(strtolower($param), $validMjParams)) {
                $invalidDashCount++;
            }
        }
        if ($invalidDashCount >= 1) return true;

        return false;
    }

    public static function getStyleAdaptiveExamples(string $styleId, string $styleName): array
    {
        return \GenesisStyleEngine::getFpExamples($styleId);
    }

    public static function getStyleHint(string $styleId, string $styleName): string
    {
        return \GenesisStyleEngine::getStyleConstraintCn($styleId);
    }

}
