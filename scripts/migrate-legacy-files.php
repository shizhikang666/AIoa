#!/usr/bin/env php
<?php

declare(strict_types=1);

use app\support\FileDownloadUrl;
use think\App;
use think\facade\Db;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
(new App($root))->initialize();

$options = getopt('', [
    'source-root:',
    'target-root:',
    'manifest:',
    'tenant-id:',
    'limit:',
    'apply',
    'help',
]);

if (isset($options['help'])) {
    fwrite(STDOUT, <<<TXT
Usage:
  php scripts/migrate-legacy-files.php --source-root=/old/upload [options]

Options:
  --target-root=PATH  New dev_file root (default: DEV_FILE_LOCAL_ROOT or public/upload/dev_file)
  --manifest=PATH     JSONL audit manifest (default: runtime/backup/legacy-file-migration-*.jsonl)
  --tenant-id=ID      Limit dev_file rows to one tenant
  --limit=N           Limit dev_file rows for a staged rehearsal
  --apply             Copy files and update metadata/legacy URLs; omitted means dry-run

TXT);
    exit(0);
}

$sourceRoot = cliPath($options['source-root'] ?? '');
if ($sourceRoot === '' || !is_dir($sourceRoot)) {
    fail('source root does not exist: ' . $sourceRoot);
}

$configuredTarget = trim((string)(getenv('DEV_FILE_LOCAL_ROOT') ?: ''));
$targetRoot = cliPath($options['target-root'] ?? ($configuredTarget !== ''
    ? $configuredTarget
    : $root . '/public/upload/dev_file'));
if ($targetRoot === '') {
    fail('target root is empty');
}

$apply = isset($options['apply']);
$tenantId = trim((string)($options['tenant-id'] ?? ''));
$limit = max(0, (int)($options['limit'] ?? 0));
$manifestPath = cliPath($options['manifest'] ?? (
    $root . '/runtime/backup/legacy-file-migration-' . date('Ymd-His') . '.jsonl'
));
$manifestDir = dirname($manifestPath);
if (!is_dir($manifestDir) && !mkdir($manifestDir, 0775, true) && !is_dir($manifestDir)) {
    fail('cannot create manifest directory: ' . $manifestDir);
}
$manifest = fopen($manifestPath, 'ab');
if ($manifest === false) {
    fail('cannot open manifest: ' . $manifestPath);
}

$summary = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'files' => [],
    'json' => [],
    'errors' => 0,
];

manifest($manifest, [
    'type' => 'start',
    'time' => date(DATE_ATOM),
    'mode' => $summary['mode'],
    'sourceRoot' => $sourceRoot,
    'targetRoot' => $targetRoot,
    'tenantId' => $tenantId !== '' ? $tenantId : null,
    'limit' => $limit ?: null,
]);

try {
    $query = Db::name('dev_file')
        ->field([
            'ID', 'ENGINE', 'BUCKET', 'NAME', 'SUFFIX', 'SIZE_KB', 'SIZE_INFO',
            'OBJ_NAME', 'STORAGE_PATH', 'DOWNLOAD_PATH', 'CREATE_TIME', 'TENANT_ID',
        ])
        ->where('ENGINE', 'LOCAL')
        ->where(function ($query): void {
            $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
        })
        ->order('ID', 'asc');
    if ($tenantId !== '') {
        $query->where('TENANT_ID', $tenantId);
    }
    if ($limit > 0) {
        $query->limit($limit);
    }

    foreach ($query->select()->toArray() as $row) {
        migrateFileRow($row, $sourceRoot, $targetRoot, $apply, $manifest, $summary);
    }

    migrateLegacyUrls($targetRoot, $apply, $manifest, $summary);
} catch (Throwable $exception) {
    $summary['errors']++;
    manifest($manifest, [
        'type' => 'fatal',
        'status' => 'error',
        'message' => $exception->getMessage(),
    ]);
    fclose($manifest);
    fwrite(STDERR, '[legacy-files] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

manifest($manifest, [
    'type' => 'summary',
    'time' => date(DATE_ATOM),
    'summary' => $summary,
]);
fclose($manifest);

fwrite(STDOUT, json_encode([
    'manifest' => $manifestPath,
    'summary' => $summary,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);

$unresolved = ($summary['files']['missing'] ?? 0) + ($summary['files']['conflict'] ?? 0) + $summary['errors'];
exit($unresolved > 0 ? 2 : 0);

/**
 * @param array<string, mixed> $row
 * @param resource $manifest
 * @param array<string, mixed> $summary
 */
function migrateFileRow(
    array $row,
    string $sourceRoot,
    string $targetRoot,
    bool $apply,
    $manifest,
    array &$summary
): void {
    $id = trim((string)($row['ID'] ?? ''));
    try {
        $relativeKey = relativeStorageKey($row);
        $target = safeJoin($targetRoot, $relativeKey);
        $source = findSource($row, $sourceRoot, $target, $relativeKey);
        $targetExists = is_file($target);

        if ($source === null && !$targetExists) {
            recordFileStatus($summary, 'missing');
            manifest($manifest, [
                'type' => 'file',
                'id' => $id,
                'status' => 'missing',
                'target' => $target,
                'relativeKey' => $relativeKey,
            ]);
            return;
        }

        if ($source !== null && $targetExists && !sameFile($source, $target)) {
            if (hash_file('sha256', $source) !== hash_file('sha256', $target)) {
                recordFileStatus($summary, 'conflict');
                manifest($manifest, [
                    'type' => 'file',
                    'id' => $id,
                    'status' => 'conflict',
                    'source' => $source,
                    'target' => $target,
                ]);
                return;
            }
        }

        $status = $targetExists ? 'existing' : 'ready';
        if ($apply && !$targetExists) {
            copyVerified($source, $target);
            $status = 'copied';
        }

        $effectiveFile = is_file($target) ? $target : $source;
        if ($effectiveFile === null || !is_file($effectiveFile)) {
            throw new RuntimeException('resolved file is not readable');
        }
        $size = filesize($effectiveFile);
        $size = $size === false ? 0 : $size;
        $downloadPath = FileDownloadUrl::normalize($id, 'LOCAL', $row['DOWNLOAD_PATH'] ?? null);

        if ($apply) {
            Db::name('dev_file')->where('ID', $id)->update([
                'ENGINE' => 'LOCAL',
                'BUCKET' => firstPathPart($relativeKey),
                'OBJ_NAME' => basename($target),
                'STORAGE_PATH' => $target,
                'DOWNLOAD_PATH' => $downloadPath,
                'SIZE_KB' => (string)(int)round($size / 1024),
                'SIZE_INFO' => readableSize($size),
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
            ]);
        }

        recordFileStatus($summary, $status);
        manifest($manifest, [
            'type' => 'file',
            'id' => $id,
            'status' => $status,
            'source' => $source,
            'target' => $target,
            'size' => $size,
            'sha256' => hash_file('sha256', $effectiveFile),
            'downloadPath' => $downloadPath,
        ]);
    } catch (Throwable $exception) {
        $summary['errors']++;
        recordFileStatus($summary, 'error');
        manifest($manifest, [
            'type' => 'file',
            'id' => $id,
            'status' => 'error',
            'message' => $exception->getMessage(),
        ]);
    }
}

/**
 * @param resource $manifest
 * @param array<string, mixed> $summary
 */
function migrateLegacyUrls(string $targetRoot, bool $apply, $manifest, array &$summary): void
{
    $targets = [
        ['table' => 'biz_draft', 'column' => 'EXT_JSON', 'json' => true],
        ['table' => 'biz_product', 'column' => 'COVER_IMAGE', 'json' => false],
        ['table' => 'product_relation', 'column' => 'EXT_JSON', 'json' => true],
        ['table' => 'sale_project_follow_up', 'column' => 'EXT_JSON', 'json' => true],
        ['table' => 'sale_project_rate', 'column' => 'EXT_JSON', 'json' => true],
    ];

    foreach ($targets as $target) {
        $table = $target['table'];
        $column = $target['column'];
        try {
            $rows = Db::name($table)
                ->field(['ID', $column])
                ->whereLike($column, '%dev/file/download%')
                ->select()
                ->toArray();
        } catch (Throwable $exception) {
            $summary['errors']++;
            manifest($manifest, [
                'type' => 'legacy-url-table',
                'table' => $table,
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
            continue;
        }

        foreach ($rows as $row) {
            $id = (string)($row['ID'] ?? '');
            $raw = (string)($row[$column] ?? '');
            $changed = false;
            if ($target['json']) {
                $value = json_decode($raw, true);
                if (!is_array($value)) {
                    recordJsonStatus($summary, 'invalid');
                    manifest($manifest, [
                        'type' => 'legacy-url',
                        'table' => $table,
                        'id' => $id,
                        'status' => 'invalid-json',
                    ]);
                    continue;
                }
                normalizeJsonUrls($value, $changed);
                $normalized = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($normalized === false) {
                    throw new RuntimeException("cannot encode {$table}.{$column} for {$id}");
                }
            } else {
                $normalized = FileDownloadUrl::normalizeLegacy($raw) ?? '';
                $changed = $normalized !== $raw;
            }

            $status = $changed ? ($apply ? 'updated' : 'ready') : 'unchanged';
            if ($changed && $apply) {
                Db::name($table)->where('ID', $id)->update([$column => $normalized]);
            }
            recordJsonStatus($summary, $status);
            manifest($manifest, [
                'type' => 'legacy-url',
                'table' => $table,
                'column' => $column,
                'id' => $id,
                'status' => $status,
            ]);
        }
    }

    $config = Db::name('dev_config')->where('CONFIG_KEY', 'SNOWY_FILE_LOCAL_FOLDER_FOR_UNIX')->find();
    if (is_array($config) && $config !== []) {
        $status = (string)($config['CONFIG_VALUE'] ?? '') === $targetRoot ? 'unchanged' : ($apply ? 'updated' : 'ready');
        if ($apply && $status === 'updated') {
            Db::name('dev_config')->where('ID', (string)$config['ID'])->update([
                'CONFIG_VALUE' => $targetRoot,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
            ]);
        }
        recordJsonStatus($summary, $status);
        manifest($manifest, [
            'type' => 'legacy-config',
            'key' => 'SNOWY_FILE_LOCAL_FOLDER_FOR_UNIX',
            'status' => $status,
            'value' => $targetRoot,
        ]);
    }
}

function normalizeJsonUrls(mixed &$value, bool &$changed): void
{
    if (is_array($value)) {
        foreach ($value as &$item) {
            normalizeJsonUrls($item, $changed);
        }
        unset($item);
        return;
    }
    if (!is_string($value)) {
        return;
    }

    $normalized = FileDownloadUrl::normalizeLegacy($value);
    if ($normalized !== null && $normalized !== $value) {
        $value = $normalized;
        $changed = true;
    }
}

/** @param array<string, mixed> $row */
function relativeStorageKey(array $row): string
{
    $bucket = trim((string)($row['BUCKET'] ?? '')) ?: 'defaultBucketName';
    $bucket = safePathPart($bucket);
    $storagePath = str_replace('\\', '/', trim((string)($row['STORAGE_PATH'] ?? '')));
    $marker = '/' . $bucket . '/';
    $position = stripos($storagePath, $marker);
    if ($position !== false) {
        $tail = substr($storagePath, $position + strlen($marker));
        return safeRelativePath($bucket . '/' . $tail);
    }

    $objName = trim((string)($row['OBJ_NAME'] ?? '')) ?: trim((string)($row['ID'] ?? ''));
    $date = strtotime((string)($row['CREATE_TIME'] ?? '')) ?: time();

    return safeRelativePath($bucket . '/' . date('Y/n/j', $date) . '/' . $objName);
}

/** @param array<string, mixed> $row */
function findSource(array $row, string $sourceRoot, string $target, string $relativeKey): ?string
{
    $candidates = [trim((string)($row['STORAGE_PATH'] ?? '')), safeJoin($sourceRoot, $relativeKey)];
    $sourceBase = basename(str_replace('\\', '/', rtrim($sourceRoot, '/\\')));
    if ($sourceBase === firstPathPart($relativeKey)) {
        $withoutBucket = implode('/', array_slice(explode('/', $relativeKey), 1));
        $candidates[] = safeJoin($sourceRoot, $withoutBucket);
    }
    $candidates[] = $target;

    foreach (array_unique($candidates) as $candidate) {
        if ($candidate !== '' && is_file($candidate) && is_readable($candidate)) {
            return cliPath($candidate);
        }
    }

    return null;
}

function copyVerified(?string $source, string $target): void
{
    if ($source === null || !is_file($source)) {
        throw new RuntimeException('source file is missing');
    }
    $directory = dirname($target);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('cannot create target directory');
    }

    $temporary = $target . '.migrate-' . getmypid();
    if (!copy($source, $temporary)) {
        throw new RuntimeException('cannot copy source file');
    }
    if (hash_file('sha256', $source) !== hash_file('sha256', $temporary)) {
        @unlink($temporary);
        throw new RuntimeException('copied file checksum mismatch');
    }
    if (!rename($temporary, $target)) {
        @unlink($temporary);
        throw new RuntimeException('cannot finalize target file');
    }
}

function sameFile(string $left, string $right): bool
{
    $leftReal = realpath($left);
    $rightReal = realpath($right);

    return $leftReal !== false && $rightReal !== false && $leftReal === $rightReal;
}

function safeJoin(string $root, string $relative): string
{
    $root = rtrim(cliPath($root), '/\\');
    $relative = safeRelativePath($relative);
    $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $normalizedRoot = strtolower(str_replace('\\', '/', $root)) . '/';
    $normalizedTarget = strtolower(str_replace('\\', '/', $target));
    if (!str_starts_with($normalizedTarget, $normalizedRoot)) {
        throw new RuntimeException('target path escaped migration root');
    }

    return $target;
}

function safeRelativePath(string $value): string
{
    $parts = array_values(array_filter(explode('/', str_replace('\\', '/', trim($value, '/\\'))), 'strlen'));
    if ($parts === []) {
        throw new RuntimeException('empty relative storage path');
    }

    return implode('/', array_map('safePathPart', $parts));
}

function safePathPart(string $value): string
{
    $value = trim($value);
    if ($value === '' || $value === '.' || $value === '..' || str_contains($value, "\0")) {
        throw new RuntimeException('unsafe storage path');
    }

    return $value;
}

function firstPathPart(string $relative): string
{
    return explode('/', str_replace('\\', '/', $relative), 2)[0];
}

function cliPath(mixed $value): string
{
    return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim((string)$value)), DIRECTORY_SEPARATOR);
}

function readableSize(int $bytes): string
{
    if ($bytes >= 1024 ** 3) {
        return round($bytes / (1024 ** 3), 2) . ' GB';
    }
    if ($bytes >= 1024 ** 2) {
        return round($bytes / (1024 ** 2), 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }

    return $bytes . ' B';
}

/** @param array<string, mixed> $summary */
function recordFileStatus(array &$summary, string $status): void
{
    $summary['files'][$status] = ($summary['files'][$status] ?? 0) + 1;
}

/** @param array<string, mixed> $summary */
function recordJsonStatus(array &$summary, string $status): void
{
    $summary['json'][$status] = ($summary['json'][$status] ?? 0) + 1;
}

/** @param resource $handle @param array<string, mixed> $record */
function manifest($handle, array $record): void
{
    fwrite($handle, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, '[legacy-files] ' . $message . PHP_EOL);
    exit(1);
}
