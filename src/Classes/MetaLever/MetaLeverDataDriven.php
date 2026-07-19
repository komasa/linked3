<?php

declare(strict_types=1);
/**
 * Data-Driven Meta Lever — replaces 45 individual lever classes with one.
 *
 * G3.5: All 45 meta levers follow the same interface, differing only in
 * prompt text and metadata. This class loads prompts from
 * meta-lever-prompts.json and implements MetaLeverInterface
 * dynamically.
 *
 * @package Linked3
 * @subpackage Classes\MetaLever
 * @since      27.3.0
 */

namespace Linked3\Classes\MetaLever;

if (!defined('ABSPATH')) {
    exit;
}

final class MetaLeverDataDriven implements MetaLeverInterface
{
    private string $id;
    private string $label;
    private string $description;
    private string $prompt;
    private array $tags;
    private array $applicable_tasks;
    private string $trace_field;

    public function __construct(
        string $id,
        string $label,
        string $description,
        string $prompt,
        array $tags = [],
        array $applicable_tasks = [],
        string $trace_field = 'meta_trace'
    ) {
        $this->id = $id;
        $this->label = $label;
        $this->description = $description;
        $this->prompt = $prompt;
        $this->tags = $tags;
        $this->applicable_tasks = $applicable_tasks;
        $this->trace_field = $trace_field;
    }

    public function id(): string { return $this->id; }
    public function label(): string { return $this->label; }
    public function description(): string { return $this->description; }
    public function system_prompt(): string { return $this->prompt; }
    public function tags(): array { return $this->tags; }
    public function applicable_tasks(): array { return $this->applicable_tasks; }
    public function trace_field(): string { return $this->trace_field; }

    /**
     * Load all levers from JSON and return instantiated objects.
     *
     * @return self[]
     */
    public static function load_all(): array
    {
        $json_path = __DIR__ . '/meta-lever-prompts.json';
        if (!file_exists($json_path)) {
            return [];
        }

        $data = json_decode(file_get_contents($json_path), true);
        if (!is_array($data)) {
            return [];
        }

        $levers = [];
        foreach ($data as $entry) {
            $levers[] = new self(
                $entry['id'] ?? '',
                $entry['label'] ?? '',
                $entry['description'] ?? '',
                $entry['prompt'] ?? '',
                $entry['tags'] ?? [],
                $entry['applicable_tasks'] ?? [],
                $entry['trace_field'] ?? 'meta_trace'
            );
        }
        return $levers;
    }
}
