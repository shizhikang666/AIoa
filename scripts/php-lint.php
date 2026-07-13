<?php

/**
 * Cross-platform PHP syntax gate.
 *
 * Runs `php -l` on the given files, or on all *.php under app/config/route
 * when no files are passed. Exits non-zero if any file has a syntax error.
 *
 * Usage:
 *   php scripts/php-lint.php                 # lint app, config, route
 *   php scripts/php-lint.php a.php b.php ...  # lint only the given files
 *
 * Reused by `composer check` and the pre-commit hook so the same baseline
 * runs everywhere. See docs/tasks/regression-checklist.md section 1.
 */

$root = dirname(__DIR__);
$phpBinary = PHP_BINARY;

$args = array_slice($argv, 1);

/** @return string[] */
$collectDefault = static function () use ($root): array {
    $dirs = ['app', 'config', 'route'];
    $files = [];
    foreach ($dirs as $dir) {
        $base = $root . DIRECTORY_SEPARATOR . $dir;
        if (!is_dir($base)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }
    return $files;
};

if ($args === []) {
    $files = $collectDefault();
} else {
    // Only lint existing *.php files; silently skip deleted/renamed or non-PHP paths.
    $files = [];
    foreach ($args as $path) {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') {
            continue;
        }
        $abs = $path;
        if (!is_file($abs)) {
            $abs = $root . DIRECTORY_SEPARATOR . $path;
        }
        if (is_file($abs)) {
            $files[] = $abs;
        }
    }
}

if ($files === []) {
    echo "php-lint: no PHP files to check." . PHP_EOL;
    exit(0);
}

$failures = [];
foreach ($files as $file) {
    $cmd = escapeshellarg($phpBinary) . ' -l ' . escapeshellarg($file) . ' 2>&1';
    $output = [];
    $status = 0;
    exec($cmd, $output, $status);
    if ($status !== 0) {
        $failures[] = $file . PHP_EOL . '    ' . trim(implode(PHP_EOL . '    ', $output));
    }
}

$count = count($files);
if ($failures === []) {
    echo "php-lint: OK ({$count} file(s), 0 syntax errors)." . PHP_EOL;
    exit(0);
}

echo "php-lint: FAILED (" . count($failures) . " of {$count} file(s) with syntax errors):" . PHP_EOL;
foreach ($failures as $failure) {
    echo '  - ' . $failure . PHP_EOL;
}
exit(1);
