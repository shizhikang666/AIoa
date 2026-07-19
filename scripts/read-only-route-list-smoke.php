#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$routeListFile = $root . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'route_list.php';

require $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (!str_starts_with($class, 'app\\')) {
        return;
    }

    $file = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
}, true, true);

$snapshot = static function (string $path): array {
    clearstatcache(true, $path);
    if (!is_file($path)) {
        return ['exists' => false, 'hash' => null, 'mtime' => null, 'size' => null];
    }

    return [
        'exists' => true,
        'hash' => hash_file('sha256', $path),
        'mtime' => filemtime($path),
        'size' => filesize($path),
    ];
};

$before = $snapshot($routeListFile);
$app = new think\App($root);
$stdout = $app->console->call('route:list')->fetch();
if (!str_contains((string)$stdout, 'biz/process/detail')) {
    throw new RuntimeException('route:list output is missing a known route');
}

$after = $snapshot($routeListFile);
if ($after !== $before) {
    throw new RuntimeException('route:list mutated runtime/route_list.php');
}

echo "read-only route:list smoke passed\n";
