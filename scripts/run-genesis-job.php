<?php
/**
 * Genesis Job Runner CLI 入口 v7.1.5
 *
 * 用法: php run-genesis-job.php <job_id> <wp_load_path>
 *
 * 这个脚本在独立 PHP 进程中执行任务, 不受 web server 超时限制。
 * 由 Linked3_Genesis_JobRunner::spawnCli() 调用。
 */

if ($argc < 3) {
    fwrite(STDERR, "Usage: php run-genesis-job.php <job_id> <wp_load_path>\n");
    exit(1);
}

$jobId = $argv[1];
$wpLoadPath = $argv[2];

if (!file_exists($wpLoadPath)) {
    fwrite(STDERR, "wp-load.php not found at: $wpLoadPath\n");
    exit(1);
}

// 加载 WordPress 环境
define('ABSPATH', dirname($wpLoadPath) . '/');
define('WP_USE_THEMES', false);
require $wpLoadPath;

// 移除时间限制
@set_time_limit(0);
@ini_set('memory_limit', '1024M');

// 执行任务
if (class_exists('Linked3_Genesis_JobRunner')) {
    Linked3_Genesis_JobRunner::runJob($jobId);
    echo "Job $jobId completed.\n";
    exit(0);
} else {
    fwrite(STDERR, "Linked3_Genesis_JobRunner class not found\n");
    exit(1);
}
