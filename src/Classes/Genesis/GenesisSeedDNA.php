<?php

declare(strict_types=1);
/**
 * Genesis Seed DNA System v8.0.0
 *
 * 角色场景一致性系统 — 为长篇漫画生成可复用的 seed DNA
 *
 * 核心概念:
 *   - Seed DNA = 角色外观 + 场景特征 + 色彩基调 + 风格指纹
 *   - 生成后可保存,后续分镜 prompt 嵌入 seed DNA 保持一致性
 *   - 支持 seed 导出为 JSON,供生图软件使用
 *
 * @package Linked3\Genesis
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisSeedDNA
{
    const SEED_OPTION = 'linked3_seed_dna_library';
    const SEED_PREFIX = 'seed_';

    /**
     * 生成 seed DNA (AI 调用)
     *
     * @param string $script 剧本
     * @param string $styleId 风格 ID
     * @param string $styleName 风格名
     * @return array {seed_id, characters, scenes, color_palette, style_fingerprint}
     */
    public static function generate(string $script, string $styleId, string $styleName): array
    {
        $styleConfig = \GenesisStyleEngine::load($styleId);
        $promptKeywords = $styleConfig['prompt_keywords'] ?? '';
        $metaPrompt = $styleConfig['meta_prompt'] ?? '';

        $scriptTrimmed = mb_substr($script, 0, 3000);

        $prompt = sprintf(
            "你是漫画角色设计师。分析以下故事,提取可复用的 Seed DNA (角色外观+场景特征+色彩基调)。\n\n" .
            "【视觉风格】%s\n%s\n\n" .
            "【故事内容】\n%s\n\n" .
            "【任务】分析故事,生成 Seed DNA JSON,包含:\n" .
            "1. characters: 故事中的主要角色 (最多5个),每个角色包含 name/appearance/clothing/distinctive_features\n" .
            "2. scenes: 故事涉及的主要场景 (最多5个),每个场景包含 name/description/lighting/atmosphere\n" .
            "3. color_palette: 适合此故事的色彩基调 (primary/secondary/accent)\n" .
            "4. style_fingerprint: 风格指纹 (与 %s 风格匹配的关键词)\n\n" .
            "【输出格式】\n" .
            "返回 JSON:\n" .
            "{\"characters\":[{\"name\":\"角色名\",\"appearance\":\"外观描述\",\"clothing\":\"服装描述\",\"distinctive_features\":\"显著特征\"}],\"scenes\":[{\"name\":\"场景名\",\"description\":\"场景描述\",\"lighting\":\"光照\",\"atmosphere\":\"氛围\"}],\"color_palette\":{\"primary\":\"主色\",\"secondary\":\"辅色\",\"accent\":\"点缀色\"},\"style_fingerprint\":\"风格关键词\"}\n\n" .
            "只返回 JSON,不要解释。",
            $styleName, $metaPrompt, $scriptTrimmed, $styleName
        );

        $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $savedModels = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model = $savedModels[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';

        try {
            $result = \AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => $prompt]],
                ['provider' => $provider, 'model' => $model, 'temperature' => 0.5, 'max_tokens' => 2000, 'module' => 'genesis_seed'],
                ['fallback_providers' => ['deepseek', 'zhipu']]
            );

            $raw = $result['content'] ?? '';
            $dna = self::parseSeedDNA($raw);

            // 生成 seed_id
            $seedId = self::SEED_PREFIX . wp_generate_password(8, false);
            $dna['seed_id'] = $seedId;
            $dna['style_id'] = $styleId;
            $dna['style_name'] = $styleName;
            $dna['created_at'] = current_time('mysql');
            $dna['script_preview'] = mb_substr($script, 0, 200);

            return $dna;
        } catch (\Throwable $e) {
            return [
                'seed_id' => self::SEED_PREFIX . 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 解析 AI 返回的 seed DNA JSON
     */
    private static function parseSeedDNA(string $raw): array
    {
        $text = trim($raw);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode($text, true);
        if (is_array($decoded)) return $decoded;

        // 尝试提取 {...}
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) return $decoded;
        }

        return [
            'characters' => [],
            'scenes' => [],
            'color_palette' => [],
            'style_fingerprint' => '',
            'parse_error' => true,
            'raw' => mb_substr($raw, 0, 500),
        ];
    }

    /**
     * 保存 seed DNA 到库
     */
    public static function save(array $dna): bool
    {
        $library = self::getAll();
        $library[] = $dna;
        return update_option(self::SEED_OPTION, $library);
    }

    /**
     * 获取所有 seed DNA
     */
    public static function getAll(): array
    {
        return (array) get_option(self::SEED_OPTION, []);
    }

    /**
     * v9.1.2: listAll() — 向后兼容别名, 返回前端期望格式
     *
     * 旧 ajax_genesis_seed_list 调用了不存在的 listAll() 导致 Fatal Error。
     * 此方法包装 getAll() 并映射字段为前端期望的 {seed_id, name, category}。
     * 优先委托给 CPT 版本 (GenesisSeedCPT::listAll)。
     */
    public static function listAll(int $limit = 200): array
    {
        // 优先委托 CPT (如果可用)
        if (class_exists('\Linked3\Classes\Genesis\GenesisSeedCPT') && method_exists('\Linked3\Classes\Genesis\GenesisSeedCPT', 'listAll')) {
            return \GenesisSeedCPT::listAll($limit);
        }
        // 兜底: 旧 option 存储
        $out   = [];
        $legacy = (array) self::getAll();
        foreach ($legacy as $dna) {
            $sid = $dna['seed_id'] ?? '';
            if (empty($sid)) continue;
            $out[] = [
                'seed_id'  => $sid,
                'name'     => $dna['name'] ?? $dna['title'] ?? $sid,
                'category' => $dna['seed_category'] ?? $dna['category'] ?? '',
            ];
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /**
     * 获取单个 seed DNA
     */
    public static function get(string $seedId): ?array
    {
        $library = self::getAll();
        foreach ($library as $dna) {
            if (($dna['seed_id'] ?? '') === $seedId) return $dna;
        }
        return null;
    }

    /**
     * 删除 seed DNA
     */
    public static function delete(string $seedId): bool
    {
        $library = self::getAll();
        $library = array_filter($library, fn($d) => ($d['seed_id'] ?? '') !== $seedId);
        return update_option(self::SEED_OPTION, array_values($library));
    }

    /**
     * 导出 seed DNA 为 JSON (供生图软件使用)
     */
    public static function exportJSON(string $seedId): string
    {
        $dna = self::get($seedId);
        if (!$dna) return '{}';
        return wp_json_encode($dna, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
