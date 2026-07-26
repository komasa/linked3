<?php
declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;

/**
 * GenesisPromptUseCase — v30 MVP
 * Business logic extracted from GenesisPromptUtils. Static facade remains for compatibility.
 */
class GenesisPromptUseCase {
    public function buildNodePrompt(array $node, string $styleName, string $platform, string $styleId = '', ?array $seedDNA = null): string {
        $prompt = \Linked3\Classes\Dashboard\GenesisPromptUtils::genesisBuildNodePrompt($node, $styleName, $platform, $styleId, $seedDNA);
        if (\Linked3\Classes\Dashboard\GenesisPromptUtils::isAIPromptDegraded($prompt)) {
            return $this->fallbackPrompt($node, $styleName);
        }
        return $prompt;
    }

    private function fallbackPrompt(array $node, string $styleName): string {
        $location = $node['location'] ?? 'scene';
        $action   = $node['action'] ?? 'action';
        return "High quality scene: {$location}, {$action}, style {$styleName}";
    }
}
