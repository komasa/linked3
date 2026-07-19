<?php

declare(strict_types=1);
/**
 * Linked3_Invoice_Manager — extracted from StripeGateway.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Billing

namespace Linked3\Classes\Billing;

if (!defined('ABSPATH')) exit;

class Linked3_Invoice_Manager {
    private static ?Linked3_Invoice_Manager $instance = null;

    public static function instance(): Linked3_Invoice_Manager {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->ensureTable();
    }

    public function create(int $userId, float $amount, string $currency, string $plan, string $chargeId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_invoices';
        $invoiceNo = 'INV-' . date('Y') . '-' . str_pad((string) mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

        $wpdb->insert($table, [
            'user_id' => $userId,
            'invoice_no' => $invoiceNo,
            'amount' => $amount,
            'currency' => $currency,
            'plan' => $plan,
            'charge_id' => $chargeId,
            'status' => 'paid',
            'created_at' => current_time('mysql'),
        ]);

        $invoiceId = $wpdb->insert_id;
        linked3_dispatch('linked3.billing.invoice.created', [
            'invoice_id' => $invoiceId, 'invoice_no' => $invoiceNo, 'user_id' => $userId,
        ]);
        return ['invoice_id' => $invoiceId, 'invoice_no' => $invoiceNo, 'amount' => $amount];
    }

    public function getByUser(int $userId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_invoices';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC",
            $userId
        ), ARRAY_A);
    }

    private function ensureTable(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_invoices';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED,
                invoice_no VARCHAR(50) NOT NULL,
                amount DECIMAL(10,2),
                currency VARCHAR(10) DEFAULT 'CNY',
                plan VARCHAR(50),
                charge_id VARCHAR(100),
                status VARCHAR(20) DEFAULT 'paid',
                created_at DATETIME,
                INDEX idx_user (user_id),
                INDEX idx_invoice (invoice_no)
            ) {$charset};";
            if (!function_exists('dbDelta')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }
            dbDelta($sql);
        }
    }
}

// =================================================================
// v5.8.0.5: 推荐返佣
// =================================================================
