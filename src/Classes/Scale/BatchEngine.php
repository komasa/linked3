<?php

declare(strict_types=1);
/**
 * Linked3_Batch_Engine — extracted from VectorIncremental.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Scale

namespace Linked3\Classes\Scale;

if (!defined('ABSPATH')) exit;

class Linked3_Batch_Engine {
    private static ?Linked3_Batch_Engine $instance = null;
    private int $batchSize = 10;

    public static function instance(): Linked3_Batch_Engine {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function batchGenerate(array $topics, string $template, array $options = []): string {
        $batchId = uniqid('batch_');
        $queue = AsyncQueue::instance();

        $batches = array_chunk($topics, $this->batchSize);
        foreach ($batches as $i => $batch) {
            foreach ($batch as $topic) {
                $queue->enqueue('Linked3_Batch_Generate_Handler', [
                    'batch_id' => $batchId,
                    'topic' => $topic,
                    'template' => $template,
                    'options' => $options,
                ], $i);
            }
        }

        linked3_dispatch('linked3.batch.started', [
            'batch_id' => $batchId, 'total' => count($topics), 'batches' => count($batches),
        ]);
        return $batchId;
    }

    public function getBatchStatus(string $batchId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_async_queue';
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(status = 'completed') as completed,
                SUM(status = 'failed') as failed,
                SUM(status = 'pending') as pending,
                SUM(status = 'processing') as processing
             FROM {$table} WHERE payload LIKE %s",
            '%' . $batchId . '%'
        ), ARRAY_A);
        return ['batch_id' => $batchId] + ($stats ?: []);
    }
}
