<?php

declare(strict_types=1);

/**
 * Parse an optional MySQL client option file before ThinkPHP boots.
 *
 * Installers keep their historical no-argument behavior. Migration tooling can
 * pass both --target-defaults and --database to pin the installer to a newly
 * created rehearsal database without exposing credentials on the command line.
 *
 * @return array<string, string>
 */
function installer_target_prepare(array $arguments): array
{
    $options = [];
    foreach ($arguments as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--')) {
            continue;
        }
        $separator = strpos($argument, '=');
        if ($separator === false) {
            continue;
        }
        $options[substr($argument, 2, $separator - 2)] = substr($argument, $separator + 1);
    }

    $defaultsPath = trim((string)($options['target-defaults'] ?? ''));
    $database = trim((string)($options['database'] ?? ''));
    if ($defaultsPath === '' && $database === '') {
        return [];
    }
    if ($defaultsPath === '' || $database === '') {
        throw new InvalidArgumentException('--target-defaults and --database must be provided together');
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
        throw new InvalidArgumentException('installer database contains unsupported characters');
    }
    if (!is_file($defaultsPath) || !is_readable($defaultsPath)) {
        throw new InvalidArgumentException('installer target defaults file is not readable');
    }

    $parsed = parse_ini_file($defaultsPath, true, INI_SCANNER_RAW);
    if (!is_array($parsed)) {
        throw new RuntimeException('unable to parse installer target defaults file');
    }
    $client = $parsed['client'] ?? $parsed;
    if (!is_array($client)) {
        throw new RuntimeException('installer target defaults file has no client section');
    }

    $mapping = [
        'host' => 'hostname',
        'port' => 'hostport',
        'user' => 'username',
        'password' => 'password',
        'default-character-set' => 'charset',
    ];
    $connection = ['database' => $database];
    foreach ($mapping as $option => $configKey) {
        if (array_key_exists($option, $client)) {
            $connection[$configKey] = (string)$client[$option];
        }
    }
    if (isset($client['socket']) && trim((string)$client['socket']) !== '') {
        $connection['hostname'] = 'localhost';
        $connection['hostport'] = '';
        $connection['params'] = [PDO::MYSQL_ATTR_UNIX_SOCKET => (string)$client['socket']];
    }

    return $connection;
}

/** @param array<string, string|array<int|string, mixed>> $override */
function installer_target_configure(array $override): void
{
    if ($override === []) {
        return;
    }

    $database = think\facade\Config::get('database', []);
    $connection = $database['connections']['mysql'] ?? [];
    if (!is_array($connection)) {
        throw new RuntimeException('ThinkPHP MySQL connection configuration is unavailable');
    }
    $database['connections']['mysql'] = array_replace($connection, $override);
    think\facade\Config::set($database, 'database');

    $rows = think\facade\Db::query('SELECT DATABASE() AS DB_NAME');
    $actual = (string)($rows[0]['DB_NAME'] ?? $rows[0]['db_name'] ?? '');
    $expected = (string)$override['database'];
    if ($actual !== $expected) {
        throw new RuntimeException('installer database safety check failed');
    }
}
