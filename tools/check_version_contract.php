#!/usr/bin/env php
<?php
/**
 * Version contract checker — PR-01 (plan §3.1)
 *
 * Verifies version consistency across:
 *   1. linked3.php plugin header  (Version: X.Y.Z)
 *   2. linked3.php LINKED3_VERSION constant
 *   3. composer.json "version" field
 *   4. linked3.php "Requires PHP" header
 *   5. composer.json "php" require
 *   6. linked3.php runtime version_compare() guard
 *
 * Exit code 0 = all consistent, 1 = mismatch found.
 *
 * Usage: php tools/check_version_contract.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);

$errors = [];

// 1. Plugin header version
$main_file = $root . '/linked3.php';
$main_src  = file_get_contents($main_file);
if (!preg_match('/^\s*\*\s*Version:\s*(\d+\.\d+\.\d+)/m', $main_src, $m)) {
    $errors[] = 'Cannot find "Version: X.Y.Z" in linked3.php header';
    $header_version = null;
} else {
    $header_version = $m[1];
}

// 2. LINKED3_VERSION constant
if (!preg_match("/define\\(\\s*['\"]LINKED3_VERSION['\"]\\s*,\\s*['\"](\\d+\\.\\d+\\.\\d+)['\"]/", $main_src, $m)) {
    $errors[] = 'Cannot find LINKED3_VERSION define() in linked3.php';
    $const_version = null;
} else {
    $const_version = $m[1];
}

// 3. Runtime PHP guard
if (!preg_match("/version_compare\\(PHP_VERSION,\\s*['\"](\\d+\\.\\d+\\.\\d+)['\"]/", $main_src, $m)) {
    $errors[] = 'Cannot find version_compare(PHP_VERSION, ...) guard in linked3.php';
    $guard_php = null;
} else {
    $guard_php = $m[1];
}

// 4. Plugin header Requires PHP
if (!preg_match('/^\s*\*\s*Requires PHP:\s*(\S+)/m', $main_src, $m)) {
    $errors[] = 'Cannot find "Requires PHP:" in linked3.php header';
    $header_php = null;
} else {
    $header_php = $m[1];
}

// 5. composer.json
$composer_file = $root . '/composer.json';
$composer      = json_decode(file_get_contents($composer_file), true);
if (null === $composer) {
    $errors[] = 'composer.json is not valid JSON';
    $composer_version = null;
    $composer_php     = null;
} else {
    $composer_version = $composer['version'] ?? null;
    $composer_php     = $composer['require']['php'] ?? null;
}

// 6. Check for PHP 8+ syntax in src/
$php8_match_files = null;
if (is_dir($root . '/src')) {
    $php8_match_files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') continue;
        $src = file_get_contents($file->getPathname());
        // match expression: = match(
        if (preg_match('/=\\s*match\\s*\\(/', $src)) {
            $php8_match_files[] = str_replace($root . '/', '', $file->getPathname());
        }
        // named arguments: func(name: $value)
        if (preg_match('/\\b\\w+\\s*:\\s*\\$\\w+/', $src)) {
            // This is noisy — skip named args for now
        }
        // union types: int|string
        if (preg_match('/function\\s+\\w+\\s*\\([^)]*\\b(?:int|string|float|bool|array|null)\\|(?:int|string|float|bool|array|null)\\b/', $src)) {
            $php8_match_files[] = str_replace($root . '/', '', $file->getPathname()) . ' (union type)';
        }
    }
    $php8_match_files = array_unique($php8_match_files);
}

// ── Report ──
echo "═══ Version Contract Check ═══\n";
echo "Plugin header Version:   {$header_version}\n";
echo "LINKED3_VERSION const:   {$const_version}\n";
echo "composer.json version:   {$composer_version}\n";
echo "Plugin header Requires PHP: {$header_php}\n";
echo "composer.json php req:   {$composer_php}\n";
echo "Runtime guard PHP >=:    {$guard_php}\n";

if ($php8_match_files) {
    echo "PHP 8+ syntax files:     " . count($php8_match_files) . "\n";
    foreach (array_slice($php8_match_files, 5) as $f) {
        echo "  → {$f}\n";
    }
    if (count($php8_match_files) > 5) {
        echo "  ... and " . (count($php8_match_files) - 5) . " more\n";
    }
}

echo "\n";

// ── Cross-check ──
$ok = true;

if ($header_version && $const_version && $header_version !== $const_version) {
    $errors[] = "Header version ({$header_version}) ≠ LINKED3_VERSION ({$const_version})";
    $ok = false;
}

if ($header_version && $composer_version && $header_version !== $composer_version) {
    $errors[] = "Header version ({$header_version}) ≠ composer.json version ({$composer_version})";
    $ok = false;
}

if ($header_php && $guard_php) {
    // Normalize: "8.0" vs "8.0.0"
    $h = preg_replace('/^(\\d+\\.\\d+)\\.0$/', '$1', $header_php);
    $g = preg_replace('/^(\\d+\\.\\d+)\\.0$/', '$1', $guard_php);
    if ($h !== $g) {
        $errors[] = "Header Requires PHP ({$header_php}) ≠ runtime guard ({$guard_php})";
        $ok = false;
    }
}

if ($composer_php && $guard_php) {
    // composer: ">=8.0", guard: "8.0.0"
    $c = preg_replace('/[^0-9.]/', '', $composer_php);
    $g = $guard_php;
    // Compare major.minor
    $c_mm = implode('.', array_slice(explode('.', $c), 0, 2));
    $g_mm = implode('.', array_slice(explode('.', $g), 0, 2));
    if ($c_mm !== $g_mm) {
        $errors[] = "composer.json php ({$composer_php}) ≠ runtime guard ({$guard_php})";
        $ok = false;
    }
}

if ($php8_match_files && $guard_php && version_compare($guard_php, '8.0.0', '<')) {
    $errors[] = "Source uses PHP 8+ syntax but guard allows PHP {$guard_php}";
    $ok = false;
}

if ($errors) {
    echo "❌ MISMATCHES:\n";
    foreach ($errors as $e) {
        echo "  • {$e}\n";
    }
    exit(1);
}

echo "✅ All version contracts consistent.\n";
exit(0);
