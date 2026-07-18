<?php

declare(strict_types=1);

namespace Oa\IsolatedValidationParameters;

use DateTimeImmutable;
use RuntimeException;

/** @return array<string, string> */
function parseNamedOptions(array $argv, array $allowed, array $required): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--') || !str_contains($argument, '=')) {
            throw new RuntimeException('isolated validation options must use --name=value');
        }
        [$name, $value] = explode('=', substr($argument, 2), 2);
        if (!in_array($name, $allowed, true) || array_key_exists($name, $options)) {
            throw new RuntimeException('isolated validation received an unsupported or duplicate option');
        }
        $options[$name] = trim($value);
    }
    foreach ($required as $name) {
        if (($options[$name] ?? '') === '') {
            throw new RuntimeException("isolated validation is missing --{$name}");
        }
    }

    return $options;
}

function databaseIdentifier(string $value, string $label): string
{
    $value = trim($value);
    if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $value) !== 1
        || in_array(strtolower($value), ['information_schema', 'mysql', 'performance_schema', 'sys'], true)
    ) {
        throw new RuntimeException("{$label} database identifier is invalid");
    }

    return $value;
}

function runLabel(string $value): string
{
    $value = strtolower(trim($value));
    if (preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $value) !== 1) {
        throw new RuntimeException('isolated validation run label is invalid');
    }

    return $value;
}

function runDate(string $value): string
{
    $value = trim($value);
    if (preg_match('/^[0-9]{8}$/', $value) !== 1) {
        throw new RuntimeException('isolated validation run date must use YYYYMMDD');
    }
    $date = DateTimeImmutable::createFromFormat('!Ymd', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date instanceof DateTimeImmutable
        || ($errors !== false && (($errors['warning_count'] ?? 0) !== 0 || ($errors['error_count'] ?? 0) !== 0))
        || $date->format('Ymd') !== $value
    ) {
        throw new RuntimeException('isolated validation run date is invalid');
    }

    return $value;
}

function expectedCount(string $value, string $label, bool $allowZero): int
{
    if (preg_match('/^[0-9]+$/', trim($value)) !== 1) {
        throw new RuntimeException("{$label} must be an integer");
    }
    $count = (int) $value;
    if (($allowZero && $count < 0) || (!$allowZero && $count < 1) || $count > 100000) {
        throw new RuntimeException("{$label} is outside the accepted range");
    }

    return $count;
}

/** @param null|callable(string):array<int, string>|false $resolver */
function loopbackHost(string $value, ?callable $resolver = null): string
{
    $host = strtolower(trim($value));
    if ($host === '') {
        throw new RuntimeException('isolated validation database host is required');
    }
    if ($host === '::1') {
        return '::1';
    }
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        if (preg_match('/^127(?:\.[0-9]{1,3}){3}$/', $host) !== 1) {
            throw new RuntimeException('isolated validation refuses a non-loopback database host');
        }

        return $host;
    }
    if ($resolver !== null) {
        $addresses = $resolver($host);
    } elseif ($host === 'localhost') {
        $addresses = ['127.0.0.1'];
    } else {
        $addresses = false;
    }
    if (!is_array($addresses) || $addresses === []) {
        throw new RuntimeException('isolated validation refuses an unresolved database host');
    }
    foreach ($addresses as $address) {
        if ($address === '::1') {
            continue;
        }
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            || preg_match('/^127(?:\.[0-9]{1,3}){3}$/', $address) !== 1
        ) {
            throw new RuntimeException('isolated validation refuses a non-loopback database host');
        }
    }

    if (count($addresses) !== 1) {
        throw new RuntimeException('isolated validation database host resolves to multiple loopback addresses');
    }

    return (string) $addresses[0];
}

function approvalComment(string $value): string
{
    $value = trim($value);
    if (strlen($value) < 8 || strlen($value) > 200 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
        throw new RuntimeException('isolated validation approval comment is invalid');
    }

    return $value;
}

function requiredEnvironment(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || trim($value) === '') {
        throw new RuntimeException("isolated validation environment {$name} is required");
    }

    return trim($value);
}

function legacyPosthocMode(): bool
{
    $value = getenv('OA_ISOLATED_LEGACY_POSTHOC');
    if ($value === false || trim((string) $value) === '' || trim((string) $value) === '0') {
        return false;
    }
    if (trim((string) $value) !== '1') {
        throw new RuntimeException('isolated validation legacy posthoc flag must be 0 or 1');
    }

    return true;
}

/**
 * @return array{
 *   canonicalDatabase:string,
 *   targetDatabase:string,
 *   databaseHost:string,
 *   runLabel:string,
 *   runDate:string,
 *   expectedTableCount:int,
 *   expectedForeignKeyCount:int
 * }
 */
function environmentConfiguration(): array
{
    $canonical = databaseIdentifier(requiredEnvironment('OA_ISOLATED_CANONICAL_DB'), 'canonical');
    $target = databaseIdentifier(requiredEnvironment('OA_ISOLATED_DB_NAME'), 'target');
    if (hash_equals(strtolower($canonical), strtolower($target))) {
        throw new RuntimeException('isolated validation target must differ from canonical database');
    }

    return [
        'canonicalDatabase' => $canonical,
        'targetDatabase' => $target,
        'databaseHost' => loopbackHost(requiredEnvironment('OA_ISOLATED_DB_HOST')),
        'runLabel' => runLabel(requiredEnvironment('OA_ISOLATED_RUN_LABEL')),
        'runDate' => runDate(requiredEnvironment('OA_ISOLATED_RUN_DATE')),
        'expectedTableCount' => expectedCount(
            requiredEnvironment('OA_ISOLATED_EXPECTED_TABLE_COUNT'),
            'expected table count',
            false
        ),
        'expectedForeignKeyCount' => expectedCount(
            requiredEnvironment('OA_ISOLATED_EXPECTED_FOREIGN_KEY_COUNT'),
            'expected foreign key count',
            true
        ),
    ];
}
