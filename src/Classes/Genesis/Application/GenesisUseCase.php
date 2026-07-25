<?php
declare(strict_types=1);
/**
 * Genesis UseCase Facade — PR-03 (plan §3.3)
 *
 * Provides a single, well-documented entry point for Genesis multi-panel
 * comic/manga script generation. Existing callers (GenesisAjaxCore,
 * GenesisJobRunner, ComicPipeline) continue to call
 * GenesisProcessor::genesisGenerateMultiInternal() — this facade does NOT
 * replace it. Instead, new code SHOULD use this UseCase, which:
 *
 *   1. Accepts a strongly-typed GenesisRequest DTO
 *   2. Validates input before dispatching
 *   3. Delegates to the existing GenesisProcessor (zero behavior change)
 *   4. Returns a strongly-typed GenesisResult DTO
 *   5. Is unit-testable with a mocked GenesisGeneratorInterface
 *
 * Migration path:
 *   Old: GenesisProcessor::genesisGenerateMultiInternal($script, $styleId, ...)
 *   New: GenesisUseCase::execute(GenesisRequest::fromArray($_POST))
 *
 * @package Linked3\Classes\Genesis\Application
 * @since   27.9.4
 */

namespace Linked3\Classes\Genesis\Application;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Request DTO for Genesis multi-panel generation.
 */
final class GenesisRequest
{
    public function __construct(
        public readonly string $script,
        public readonly string $styleId,
        public readonly string $platform,
        public readonly string $panelCountRaw,
        public readonly ?callable $progressCb = null,
        public readonly array $extraOptions = [],
    ) {
    }

    /**
     * Factory from raw POST data (sanitizes and validates).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            script:        sanitize_textarea_field(wp_unslash($data['script'] ?? '')),
            styleId:       sanitize_text_field(wp_unslash($data['style_id'] ?? '')),
            platform:      sanitize_text_field(wp_unslash($data['platform'] ?? 'midjourney')),
            panelCountRaw: sanitize_text_field(wp_unslash($data['panel_count'] ?? '4')),
            progressCb:    null,
            extraOptions:  [],
        );
    }

    /**
     * Validate the request. Returns array of error messages (empty = valid).
     */
    public function validate(): array
    {
        $errors = [];
        if (empty($this->script)) {
            $errors[] = __('剧本内容不能为空', 'linked3');
        }
        if (empty($this->styleId)) {
            $errors[] = __('请选择风格', 'linked3');
        }
        if (!in_array($this->platform, ['midjourney', 'sdxl', 'flux', 'dalle', 'comfyui'], true)) {
            $errors[] = sprintf(__('不支持的平台: %s', 'linked3'), esc_html($this->platform));
        }
        return $errors;
    }
}

/**
 * Result DTO for Genesis multi-panel generation.
 */
final class GenesisResult
{
    public function __construct(
        public readonly bool $success,
        public readonly array $panels,
        public readonly string $message,
        public readonly ?array $meta = null,
    ) {
    }

    public static function fromArray(array $raw): self
    {
        $success = !empty($raw['success']) || !empty($raw['panels']);
        return new self(
            success: $success,
            panels:  $raw['panels'] ?? [],
            message: $raw['message'] ?? '',
            meta:    $raw['meta'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'panels'  => $this->panels,
            'message' => $this->message,
            'meta'    => $this->meta,
        ];
    }
}

/**
 * Contract — what the UseCase needs to generate panels.
 *
 * This interface allows unit tests to mock the generator without
 * touching GenesisProcessor. In production, the ConcreteGenesisGenerator
 * delegates to GenesisProcessor::genesisGenerateMultiInternal.
 */
interface GenesisGeneratorInterface
{
    public function generate(
        string $script,
        string $styleId,
        string $platform,
        string $panelCountRaw,
        ?callable $progressCb = null,
        array $extraOptions = [],
    ): array;
}

/**
 * Production implementation — delegates to GenesisProcessor.
 */
final class ConcreteGenesisGenerator implements GenesisGeneratorInterface
{
    public function generate(
        string $script,
        string $styleId,
        string $platform,
        string $panelCountRaw,
        ?callable $progressCb = null,
        array $extraOptions = [],
    ): array {
        // Delegate to the existing entry point — zero behavior change.
        return \Linked3\Classes\Dashboard\GenesisProcessor::genesisGenerateMultiInternal(
            $script,
            $styleId,
            $platform,
            $panelCountRaw,
            $progressCb,
            $extraOptions,
        );
    }
}

/**
 * UseCase — orchestrates validation, generation, and result mapping.
 *
 * Usage:
 *   $useCase = GenesisUseCase::create();  // uses ConcreteGenesisGenerator
 *   $result  = $useCase->execute($request);
 *   if ($result->success) { ... $result->panels ... }
 */
final class GenesisUseCase
{
    public function __construct(
        private readonly GenesisGeneratorInterface $generator,
    ) {
    }

    /**
     * Factory for production use.
     */
    public static function create(): self
    {
        return new self(new ConcreteGenesisGenerator());
    }

    /**
     * Factory for testing — accepts a mock generator.
     */
    public static function withGenerator(GenesisGeneratorInterface $generator): self
    {
        return new self($generator);
    }

    /**
     * Execute the Genesis multi-panel generation UseCase.
     *
     * @param GenesisRequest $request Validated request DTO
     * @return GenesisResult
     */
    public function execute(GenesisRequest $request): GenesisResult
    {
        // Validate input
        $errors = $request->validate();
        if (!empty($errors)) {
            return new GenesisResult(
                success: false,
                panels:  [],
                message: implode(' | ', $errors),
            );
        }

        // Delegate to generator
        try {
            $raw = $this->generator->generate(
                $request->script,
                $request->styleId,
                $request->platform,
                $request->panelCountRaw,
                $request->progressCb,
                $request->extraOptions,
            );
            return GenesisResult::fromArray($raw);
        } catch (\Throwable $e) {
            return new GenesisResult(
                success: false,
                panels:  [],
                message: $e->getMessage(),
                meta:    ['exception' => get_class($e), 'file' => basename($e->getFile()), 'line' => $e->getLine()],
            );
        }
    }
}
