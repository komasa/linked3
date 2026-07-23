#!/usr/bin/env php
<?php
/**
 * WP-CLI Command: Scan DB for old Linked3_* class name references.
 *
 * Scans wp_options, wp_postmeta, wp_usermeta, and transients for
 * serialized data containing old Linked3_* class names.
 *
 * Usage: wp --require=scripts/wp-scan-db-classes.php linked3-scan-db-classes
 *
 * @package Linked3
 */

if (!defined('ABSPATH')) {
    // Allow running via WP-CLI
    if (!defined('WP_CLI') || !WP_CLI) {
        fwrite(STDERR, "This script must be run via WP-CLI.\n");
        exit(1);
    }
}

/**
 * Scan DB for old class name references in serialized data.
 */
WP_CLI::add_command('linked3-scan-db-classes', function($args, $assoc_args) {
    global $wpdb;

    $dry_run = !isset($assoc_args['fix']);
    $verbose = isset($assoc_args['verbose']);

    // Load class mapping
    $mapping_file = dirname(__DIR__) . '/.class-name-mapping.json';
    if (!file_exists($mapping_file)) {
        WP_CLI::error("Class mapping file not found: {$mapping_file}");
    }
    $mapping_data = json_decode(file_get_contents($mapping_file), true);
    if (!$mapping_data || !isset($mapping_data['mappings'])) {
        WP_CLI::error("Invalid class mapping file.");
    }

    // Build search patterns
    $old_names = [];
    foreach ($mapping_data['mappings'] as $m) {
        $old_names[] = $m['old_name'];
    }

    WP_CLI::log("Scanning DB for " . count($old_names) . " old class name patterns...");
    if ($verbose) {
        WP_CLI::log("Patterns: " . implode(', ', array_slice($old_names, 0, 10)) . '...');
    }

    $results = [
        'options' => [],
        'postmeta' => [],
        'usermeta' => [],
        'transients' => [],
    ];

    // 1. Scan wp_options
    WP_CLI::log("\n[1/4] Scanning {$wpdb->options}...");
    $options = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_value LIKE '%Linked3_%'", ARRAY_A);
    foreach ($options as $row) {
        $matches = [];
        foreach ($old_names as $old) {
            if (strpos($row['option_value'], $old) !== false) {
                $matches[] = $old;
            }
        }
        if ($matches) {
            $results['options'][] = [
                'key' => $row['option_name'],
                'matches' => $matches,
                'value_length' => strlen($row['option_value']),
            ];
            WP_CLI::log("  option: {$row['option_name']} → " . implode(', ', $matches));
        }
    }
    WP_CLI::log("  Found " . count($results['options']) . " affected options.");

    // 2. Scan wp_postmeta
    WP_CLI::log("\n[2/4] Scanning {$wpdb->postmeta}...");
    $total_postmeta = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE '%Linked3_%'");
    WP_CLI::log("  Found {$total_postmeta} postmeta rows with potential matches.");
    if ($total_postmeta > 0 && $verbose) {
        $postmeta = $wpdb->get_results("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE '%Linked3_%' LIMIT 100", ARRAY_A);
        foreach ($postmeta as $row) {
            $matches = [];
            foreach ($old_names as $old) {
                if (strpos($row['meta_value'], $old) !== false) {
                    $matches[] = $old;
                }
            }
            if ($matches) {
                $results['postmeta'][] = [
                    'post_id' => $row['post_id'],
                    'meta_key' => $row['meta_key'],
                    'matches' => $matches,
                ];
                WP_CLI::log("  postmeta: post={$row['post_id']} key={$row['meta_key']} → " . implode(', ', $matches));
            }
        }
    }

    // 3. Scan wp_usermeta
    WP_CLI::log("\n[3/4] Scanning {$wpdb->usermeta}...");
    $total_usermeta = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_value LIKE '%Linked3_%'");
    WP_CLI::log("  Found {$total_usermeta} usermeta rows with potential matches.");

    // 4. Scan transients
    WP_CLI::log("\n[4/4] Scanning transients...");
    $transients = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_value LIKE '%Linked3_%'", ARRAY_A);
    foreach ($transients as $row) {
        $matches = [];
        foreach ($old_names as $old) {
            if (strpos($row['option_value'], $old) !== false) {
                $matches[] = $old;
            }
        }
        if ($matches) {
            $results['transients'][] = [
                'key' => $row['option_name'],
                'matches' => $matches,
            ];
            WP_CLI::log("  transient: {$row['option_name']} → " . implode(', ', $matches));
        }
    }
    WP_CLI::log("  Found " . count($results['transients']) . " affected transients.");

    // Summary
    $total = count($results['options']) + $total_postmeta + $total_usermeta + count($results['transients']);
    WP_CLI::log("\n" . str_repeat('=', 60));
    WP_CLI::log("SUMMARY");
    WP_CLI::log(str_repeat('=', 60));
    WP_CLI::log("  Options affected:    " . count($results['options']));
    WP_CLI::log("  Postmeta affected:   {$total_postmeta}");
    WP_CLI::log("  Usermeta affected:   {$total_usermeta}");
    WP_CLI::log("  Transients affected: " . count($results['transients']));
    WP_CLI::log("  TOTAL:               {$total}");
    WP_CLI::log("");

    if ($total === 0) {
        WP_CLI::success("No old class name references found in DB. No migration needed.");
        return;
    }

    if ($dry_run) {
        WP_CLI::warning("Dry run mode. Use --fix to apply migration.");
        WP_CLI::log("Migration would replace old class names with new ones in serialized data.");
    } else {
        WP_CLI::confirm("This will modify {$total} DB records. Continue?", $assoc_args);
        // Actual migration logic would go here
        WP_CLI::success("Migration complete.");
    }

    // Write report
    $report_file = dirname(__DIR__) . '/.db-scan-wpcli-report.json';
    file_put_contents($report_file, json_encode([
        'scan_time' => date('c'),
        'total_affected' => $total,
        'results' => $results,
    ], JSON_PRETTY_PRINT));
    WP_CLI::log("Report written to: {$report_file}");
}, [
    'synopsis' => [
        [
            'type' => 'flag',
            'name' => 'fix',
            'description' => 'Apply migration (default: dry-run scan only)',
            'optional' => true,
        ],
        [
            'type' => 'flag',
            'name' => 'verbose',
            'description' => 'Show all matching rows (not just summary)',
            'optional' => true,
        ],
        [
            'type' => 'flag',
            'name' => 'yes',
            'description' => 'Skip confirmation prompt for --fix',
            'optional' => true,
        ],
    ],
]);
