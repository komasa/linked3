<?php

namespace Linked3\Classes\Dashboard;

if (!defined('ABSPATH')) exit;

class Linked3_Genesis_Helpers
{
    public static function genesisParallelGeneratePrompts(array $nodes, string $styleId, string $platform, string $styleName): array
    {
        if (empty($nodes)) return ['__mode' => 'empty'];

        $providerSlug = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $savedModels  = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model        = $savedModels[$providerSlug] ?? 'Qwen/Qwen2.5-7B-Instruct';

        if (function_exists('curl_multi_init')) {
            $result = self::genesisCurlMultiPrompts($nodes, $providerSlug, $model, $styleName, $platform, $styleId, $seedDNA);
            if ($result !== null) {
                $result['__mode'] = 'curl_multi';
                return $result;
            }
        }

        $result = self::genesisSerialPrompts($nodes, $providerSlug, $model, $styleName, $platform, $styleId, $seedDNA);
        $result['__mode'] = 'serial';
        return $result;
    }

    public static function genesisSerialPrompts(array $nodes, string $providerSlug, string $model, string $styleName, string $platform, string $styleId = "", ?array $seedDNA = null): array
    {
        $results = [];
        foreach ($nodes as $i => $node) {
            $prompt = self::genesisBuildNodePrompt($node, $styleName, $platform, $styleId, $seedDNA);
            try {
                $result = Linked3_AI_Dispatcher::instance()->chat(
                    [['role' => 'user', 'content' => $prompt]],
                    [
                        'provider'          => $providerSlug,
                        'model'             => $model,
                        'temperature'       => 0.6,    // v7.1.1: 0.7→0.6
                        'max_tokens'        => 800,    // v7.1.1: 500→800
                        'frequency_penalty' => 0.3,    // v7.1.1: 防循环
                        'presence_penalty'  => 0.2,
                        'module'            => 'genesis',
                    ],
                    ['fallback_providers' => ['deepseek', 'zhipu'], 'force_bypass_circuit' => true]
                );
                $results[$i] = [
                    'ok'      => true,
                    'content' => $result['content'] ?? '',
                    'usage'   => $result['usage'] ?? [],
                ];
            } catch (\Throwable $e) {
                $results[$i] = ['ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

        public static function genesisBuildNodePrompt(array $node, string $styleName, string $platform, string $styleId = '', ?array $seedDNA = null) : mixed { return Linked3_Genesis_Prompt_Utils::genesisBuildNodePrompt($node, $styleName, $platform, $styleId, $seedDNA); }

        public static function cleanAIPrompt(string $raw, string $platform) : mixed { return Linked3_Genesis_Prompt_Utils::cleanAIPrompt($raw, $platform); }

        public static function isAIPromptDegraded(string $prompt) : mixed { return Linked3_Genesis_Prompt_Utils::isAIPromptDegraded($prompt); }

        public static function enforcePanelCount(array $nodes, int $targetPanels, string $script, string $styleName) : mixed { return Linked3_Genesis_Panel_Utils::enforcePanelCount($nodes, $targetPanels, $script, $styleName); }

        public static function splitByChapters(string $script, string $marker = 'auto') : mixed { return Linked3_Genesis_Panel_Utils::splitByChapters($script, $marker); }

        public static function genesisRefineAndSplit(string $script, int $targetPanels, string $styleName, string $styleId = '') : mixed { return Linked3_Genesis_Panel_Utils::genesisRefineAndSplit($script, $targetPanels, $styleName, $styleId); }

        public static function getStyleAdaptiveExamples(string $styleId, string $styleName) : mixed { return Linked3_Genesis_Prompt_Utils::getStyleAdaptiveExamples($styleId, $styleName); }

        public static function getStyleHint(string $styleId, string $styleName) : mixed { return Linked3_Genesis_Prompt_Utils::getStyleHint($styleId, $styleName); }

        public static function genesisFPExtractCores(string $script, int $targetPanels, string $styleName, bool $isAuto = false, string $styleId = '') : mixed { return Linked3_Genesis_Panel_Utils::genesisFPExtractCores($script, $targetPanels, $styleName, $isAuto, $styleId); }

        public static function parseFPNodesJson(string $raw) : mixed { return Linked3_Genesis_Panel_Utils::parseFPNodesJson($raw); }

        public static function normalizeFPNodes(array $nodes) : mixed { return Linked3_Genesis_Panel_Utils::normalizeFPNodes($nodes); }

        public static function v7ParsePanels(string $raw) : mixed { return Linked3_Genesis_Panel_Utils::v7ParsePanels($raw); }

        public static function normalizePanels(array $panels) : mixed { return Linked3_Genesis_Panel_Utils::normalizePanels($panels); }

        public static function genesisAIGeneratePanels(string $script, int $targetPanels, string $styleId, bool $isAuto) : mixed { return Linked3_Genesis_Panel_Utils::genesisAIGeneratePanels($script, $targetPanels, $styleId, $isAuto); }

        public static function fallbackParsePanels(string $raw, string $originalScript) : mixed { return Linked3_Genesis_Panel_Utils::fallbackParsePanels($raw, $originalScript); }

        public static function parseGenesisPanelsJson(string $raw) : mixed { return Linked3_Genesis_Panel_Utils::parseGenesisPanelsJson($raw); }

        public static function formatGenesisPanel(array $panel, array $assembled, array $pqs) : mixed { return Linked3_Genesis_Panel_Utils::formatGenesisPanel($panel, $assembled, $pqs); }

}
