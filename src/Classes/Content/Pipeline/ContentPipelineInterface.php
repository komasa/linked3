<?php

declare(strict_types=1);
namespace Linked3\Classes\Content\Pipeline;
if (!defined('ABSPATH')) exit;
interface ContentPipelineInterface
{
    public static function type(): string;
    public function prepare(array $input): array;
    public function generate(array $context, ?callable $progressCb = null): array;
    public function deliver(array $result): array;
    public static function get_styles(): array;
    public static function label(): string;
}
