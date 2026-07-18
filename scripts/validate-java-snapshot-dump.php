#!/usr/bin/env php
<?php

declare(strict_types=1);

/** @return array<string, string> */
function snapshot_dump_options(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--') || !str_contains($argument, '=')) {
            throw new RuntimeException('snapshot dump validator options must use --name=value');
        }
        [$name, $value] = explode('=', substr($argument, 2), 2);
        if (!in_array($name, ['path', 'sha256', 'source-db'], true) || isset($options[$name])) {
            throw new RuntimeException('snapshot dump validator received an unknown or duplicate option');
        }
        $options[$name] = $value;
    }

    return $options;
}

function snapshot_dump_fail(int $lineNumber, string $reason): never
{
    throw new RuntimeException("snapshot dump {$reason} at line {$lineNumber}");
}

function snapshot_dump_string_literal_end(string $sql, int $start, int $lineNumber): int
{
    if (($sql[$start] ?? '') !== "'") {
        snapshot_dump_fail($lineNumber, 'contains an invalid string literal start');
    }
    $length = strlen($sql);
    $cursor = $start + 1;
    while ($cursor < $length) {
        $quote = strpos($sql, "'", $cursor);
        if ($quote === false) {
            break;
        }
        $backslashes = 0;
        for ($index = $quote - 1; $index > $start && $sql[$index] === '\\'; $index--) {
            $backslashes++;
        }
        if (($backslashes % 2) === 1) {
            $cursor = $quote + 1;
            continue;
        }
        if (($sql[$quote + 1] ?? '') === "'") {
            $cursor = $quote + 2;
            continue;
        }

        return $quote + 1;
    }

    snapshot_dump_fail($lineNumber, 'contains an unterminated string literal');
}

/** Replace complete single-quoted MySQL literals with NULL. */
function snapshot_dump_without_string_literals(string $sql, int $lineNumber): string
{
    $result = '';
    $cursor = 0;
    while (($start = strpos($sql, "'", $cursor)) !== false) {
        $result .= substr($sql, $cursor, $start - $cursor) . 'NULL';
        $cursor = snapshot_dump_string_literal_end($sql, $start, $lineNumber);
    }
    $result .= substr($sql, $cursor);

    return $result;
}

/** @return array{version:string,body:string}|null */
function snapshot_dump_versioned_body(string $line): ?array
{
    if (preg_match('/^\/\*!([0-9]{5})\s+(.+?)\s*\*\/;$/', $line, $match) !== 1) {
        return null;
    }

    return [
        'version' => $match[1],
        'body' => strtoupper((string)(preg_replace('/\s+/', ' ', trim($match[2])) ?? '')),
    ];
}

/** @return array{kind:string,table?:string,state?:string}|null */
function snapshot_dump_allowed_versioned_statement(string $line): ?array
{
    $parsed = snapshot_dump_versioned_body($line);
    if ($parsed === null) {
        return null;
    }
    $identity = $parsed['version'] . ' ' . $parsed['body'];
    $allowedSetStatements = [
        '40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT',
        '40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS',
        '40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION',
        '50503 SET NAMES UTF8MB4',
        '40103 SET @OLD_TIME_ZONE=@@TIME_ZONE',
        "40103 SET TIME_ZONE='+00:00'",
        '40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0',
        '40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0',
        "40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO'",
        '40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0',
        '40101 SET @SAVED_CS_CLIENT = @@CHARACTER_SET_CLIENT',
        '50503 SET CHARACTER_SET_CLIENT = UTF8MB4',
        '40101 SET CHARACTER_SET_CLIENT = @SAVED_CS_CLIENT',
        '40103 SET TIME_ZONE=@OLD_TIME_ZONE',
        '40101 SET SQL_MODE=@OLD_SQL_MODE',
        '40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS',
        '40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS',
        '40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT',
        '40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS',
        '40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION',
        '40111 SET SQL_NOTES=@OLD_SQL_NOTES',
    ];
    if (in_array($identity, $allowedSetStatements, true)) {
        return ['kind' => 'set'];
    }
    if ($parsed['version'] === '40000'
        && preg_match(
            '/^ALTER TABLE `([A-Z0-9_]+)` (DISABLE|ENABLE) KEYS$/',
            $parsed['body'],
            $match
        ) === 1
    ) {
        return [
            'kind' => 'alter-keys',
            'table' => strtolower($match[1]),
            'state' => strtolower($match[2]),
        ];
    }

    return null;
}

function snapshot_dump_assert_create_body_line(string $line, int $lineNumber): void
{
    $withoutStrings = snapshot_dump_without_string_literals($line, $lineNumber);
    if (str_contains($withoutStrings, ';')
        || preg_match('/(?:\/\*|\*\/|--|#|\\!|\\\.)/', $withoutStrings) === 1
        || preg_match('/`[A-Za-z0-9_]+`\s*\.\s*`/', $withoutStrings) === 1
    ) {
        snapshot_dump_fail($lineNumber, 'contains an unsafe CREATE TABLE body line');
    }
    $trimmed = trim($withoutStrings);
    if (preg_match('/^`[A-Za-z0-9_]+`\s+.+,?$/', $trimmed) === 1) {
        return;
    }
    if (preg_match('/^(?:PRIMARY KEY|UNIQUE KEY|KEY)\s+/', $trimmed) === 1) {
        $tokens = preg_replace('/`[A-Za-z0-9_]+`|[(),0-9\s]/', '', $trimmed);
        if (in_array($tokens, [
            'PRIMARYKEY',
            'PRIMARYKEYUSINGBTREE',
            'UNIQUEKEY',
            'UNIQUEKEYUSINGBTREE',
            'KEY',
            'KEYUSINGBTREE',
        ], true)) {
            return;
        }
    }
    if (preg_match(
        '/^CONSTRAINT `[A-Za-z0-9_]+` FOREIGN KEY '
        . '\((?:`[A-Za-z0-9_]+`(?:,\s*)?)+\) REFERENCES `[A-Za-z0-9_]+` '
        . '\((?:`[A-Za-z0-9_]+`(?:,\s*)?)+\)'
        . '(?: ON DELETE (?:RESTRICT|CASCADE|SET NULL|NO ACTION))?'
        . '(?: ON UPDATE (?:RESTRICT|CASCADE|SET NULL|NO ACTION))?,?$/i',
        $trimmed
    ) === 1) {
        return;
    }

    snapshot_dump_fail($lineNumber, 'contains an unsupported CREATE TABLE body line');
}

function snapshot_dump_assert_create_tail(string $line, int $lineNumber): void
{
    $withoutStrings = snapshot_dump_without_string_literals($line, $lineNumber);
    if (preg_match(
        '/^\) ENGINE=InnoDB(?: AUTO_INCREMENT=[1-9][0-9]*)? '
        . 'DEFAULT CHARSET=(?:utf8|utf8mb4)'
        . '(?: COLLATE=(?:utf8_bin|utf8mb4_general_ci|utf8mb4_unicode_ci))?'
        . '(?: ROW_FORMAT=DYNAMIC)?(?: COMMENT=NULL)?;$/',
        $withoutStrings
    ) !== 1) {
        snapshot_dump_fail($lineNumber, 'contains unsupported CREATE TABLE options');
    }
}

/** @return array{table:string} */
function snapshot_dump_validate_insert(string $line, int $lineNumber): array
{
    if (preg_match('/^INSERT INTO `([A-Za-z0-9_]+)` VALUES /', $line, $match) !== 1
        || !str_ends_with($line, ';')
    ) {
        snapshot_dump_fail($lineNumber, 'contains a non-standard INSERT statement');
    }
    $cursor = strlen($match[0]);
    $end = strlen($line) - 1;
    while ($cursor < $end) {
        $character = $line[$cursor];
        if (str_contains(" \t(),+-", $character)) {
            $cursor++;
            continue;
        }
        if ($character === "'") {
            $cursor = snapshot_dump_string_literal_end($line, $cursor, $lineNumber);
            continue;
        }
        if (strncasecmp(substr($line, $cursor, 4), 'NULL', 4) === 0) {
            $next = $line[$cursor + 4] ?? '';
            if ($next === '' || str_contains(" \t(),;", $next)) {
                $cursor += 4;
                continue;
            }
        }
        if ($character === '0' && (($line[$cursor + 1] ?? '') === 'x' || ($line[$cursor + 1] ?? '') === 'X')) {
            $cursor += 2;
            $hexStart = $cursor;
            while ($cursor < $end && ctype_xdigit($line[$cursor])) {
                $cursor++;
            }
            if ($cursor > $hexStart) {
                continue;
            }
        }
        if (ctype_digit($character) || $character === '.') {
            $cursor++;
            $cursor += strspn($line, '0123456789.eE+-', $cursor, $end - $cursor);
            continue;
        }
        if (($character === 'b' || $character === 'B') && ($line[$cursor + 1] ?? '') === "'") {
            $literalEnd = snapshot_dump_string_literal_end($line, $cursor + 1, $lineNumber);
            $bits = substr($line, $cursor + 2, $literalEnd - $cursor - 3);
            if ($bits !== '' && preg_match('/^[01]+$/', $bits) === 1) {
                $cursor = $literalEnd;
                continue;
            }
        }

        snapshot_dump_fail($lineNumber, 'contains executable INSERT expressions');
    }

    return ['table' => $match[1]];
}

/** @return array<string, mixed> */
function snapshot_dump_validate_file(string $path, string $expectedHash, string $sourceDatabase): array
{
    $expectedHash = strtolower($expectedHash);
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('snapshot dump is missing, empty, or unreadable');
    }
    if (preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1
        || preg_match('/^[A-Za-z0-9_]+$/', $sourceDatabase) !== 1
    ) {
        throw new RuntimeException('snapshot dump validation identity is invalid');
    }
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('snapshot dump cannot be opened');
    }
    $fileStat = fstat($handle);
    if (!is_array($fileStat) || (int)($fileStat['size'] ?? 0) <= 0) {
        fclose($handle);
        throw new RuntimeException('snapshot dump is missing, empty, or unreadable');
    }
    $hashContext = hash_init('sha256');
    $bytesRead = 0;
    $createdTables = [];
    $droppedTables = [];
    $insertStatements = 0;
    $lineNumber = 0;
    $creatingTable = null;
    $lockedTable = null;
    try {
        while (($rawLine = fgets($handle)) !== false) {
            $lineNumber++;
            hash_update($hashContext, $rawLine);
            $bytesRead += strlen($rawLine);
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $rawLine) === 1) {
                snapshot_dump_fail($lineNumber, 'contains an unsupported control byte');
            }
            $line = rtrim($rawLine, "\r\n");
            if ($line === '' || preg_match('/^--(?:\s.*)?$/', $line) === 1) {
                continue;
            }

            if ($creatingTable !== null) {
                if (str_starts_with($line, ')')) {
                    snapshot_dump_assert_create_tail($line, $lineNumber);
                    $createdTables[$creatingTable] = true;
                    $creatingTable = null;
                } else {
                    snapshot_dump_assert_create_body_line($line, $lineNumber);
                }
                continue;
            }

            if (str_starts_with(ltrim($line), '/*!')) {
                $versioned = snapshot_dump_allowed_versioned_statement($line);
                if ($versioned === null) {
                    snapshot_dump_fail($lineNumber, 'contains an unapproved versioned executable comment');
                }
                if ($versioned['kind'] === 'alter-keys') {
                    $table = (string)$versioned['table'];
                    if ($lockedTable !== $table || !isset($createdTables[$table])) {
                        snapshot_dump_fail($lineNumber, 'contains ALTER KEYS outside its table lock');
                    }
                }
                continue;
            }

            if (preg_match('/^DROP TABLE IF EXISTS `([A-Za-z0-9_]+)`;$/', $line, $match) === 1) {
                $table = $match[1];
                if ($lockedTable !== null || isset($droppedTables[$table])) {
                    snapshot_dump_fail($lineNumber, 'contains a duplicate or misplaced DROP TABLE');
                }
                $droppedTables[$table] = true;
                continue;
            }
            if (preg_match('/^CREATE TABLE `([A-Za-z0-9_]+)` \($/', $line, $match) === 1) {
                $table = $match[1];
                if ($lockedTable !== null || !isset($droppedTables[$table]) || isset($createdTables[$table])) {
                    snapshot_dump_fail($lineNumber, 'contains a duplicate or unpaired CREATE TABLE');
                }
                $creatingTable = $table;
                continue;
            }
            if (preg_match('/^LOCK TABLES `([A-Za-z0-9_]+)` WRITE;$/', $line, $match) === 1) {
                $table = $match[1];
                if ($lockedTable !== null || !isset($createdTables[$table])) {
                    snapshot_dump_fail($lineNumber, 'contains a duplicate or unknown table lock');
                }
                $lockedTable = $table;
                continue;
            }
            if (str_starts_with($line, 'INSERT INTO ')) {
                $insert = snapshot_dump_validate_insert($line, $lineNumber);
                if ($lockedTable !== $insert['table'] || !isset($createdTables[$insert['table']])) {
                    snapshot_dump_fail($lineNumber, 'contains INSERT outside its table lock');
                }
                $insertStatements++;
                continue;
            }
            if ($line === 'UNLOCK TABLES;') {
                if ($lockedTable === null) {
                    snapshot_dump_fail($lineNumber, 'contains an unmatched UNLOCK TABLES');
                }
                $lockedTable = null;
                continue;
            }

            snapshot_dump_fail($lineNumber, 'contains a statement outside the strict mysqldump allowlist');
        }
        if (!feof($handle)) {
            throw new RuntimeException('snapshot dump stream ended unexpectedly');
        }
    } finally {
        fclose($handle);
    }

    $actualHash = strtolower(hash_final($hashContext));
    if (!hash_equals($expectedHash, $actualHash)) {
        throw new RuntimeException('snapshot dump SHA-256 differs from the reviewed value');
    }

    ksort($createdTables);
    ksort($droppedTables);
    if ($creatingTable !== null
        || $lockedTable !== null
        || count($createdTables) !== 121
        || array_keys($createdTables) !== array_keys($droppedTables)
        || $insertStatements < 1
    ) {
        throw new RuntimeException('snapshot dump structure differs from the audited 121-table full export');
    }

    return [
        'status' => 'passed',
        'sha256' => $actualHash,
        'size' => $bytesRead,
        'sourceDatabase' => $sourceDatabase,
        'createTableCount' => count($createdTables),
        'dropTableCount' => count($droppedTables),
        'insertStatementCount' => $insertStatements,
        'forbiddenStatements' => 0,
        'validationPolicy' => 'strict-mysqldump-allowlist-v1',
    ];
}

function snapshot_dump_main(array $argv): int
{
    try {
        $options = snapshot_dump_options($argv);
        $result = snapshot_dump_validate_file(
            $options['path'] ?? '',
            $options['sha256'] ?? '',
            $options['source-db'] ?? ''
        );
        fwrite(STDOUT, json_encode(
            $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . PHP_EOL);

        return 0;
    } catch (Throwable $exception) {
        fwrite(STDERR, 'snapshot dump validation failed: ' . $exception->getMessage() . PHP_EOL);

        return 1;
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(snapshot_dump_main($argv));
}
