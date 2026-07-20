<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Prompt_Compiler — extracted from DiagramMETALayer.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramPromptCompiler {
    private DiagramMETALayer $metaLayer;
    private Linked3_Diagram_Script_Layer $scriptLayer;
    private Linked3_Diagram_Validation_Layer $validationLayer;

    public function __construct() {
        $this->metaLayer = new DiagramMETALayer();
        $this->scriptLayer = new Linked3_Diagram_Script_Layer();
        $this->validationLayer = new Linked3_Diagram_Validation_Layer();
    }

    /**
     * 编译三层 → 完整 Prompt。
     */
    public function compile(array $config): array {
        $meta = $this->metaLayer->build($config);
        $script = $this->scriptLayer->build($config);
        $validation = $this->validationLayer->build($config);

        $prompt = $this->metaLayer->render($meta);
        $prompt .= "\n" . $this->scriptLayer->render($script);
        $prompt .= "\n" . $this->validationLayer->render($validation);

        // 字符数检查
        $charCount = strlen($prompt);
        if ($charCount > 4500) {
            $compressor = new Linked3_Diagram_Prompt_Compressor();
            $prompt = $compressor->compress($prompt);
            $charCount = strlen($prompt);
        }

        return [
            'prompt' => $prompt,
            'meta' => $meta,
            'script' => $script,
            'validation' => $validation,
            'char_count' => $charCount,
        ];
    }
}

// =================================================================
// v6.2.0.5: Prompt压缩器
// =================================================================
