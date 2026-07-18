#!/usr/bin/env php
<?php

declare(strict_types=1);

use app\support\FileDownloadUrl;
use app\support\LegacyFileSource;
use think\App;
use think\facade\Db;

const LEGACY_FILE_APPLY_CONFIRMATION_OPTION = 'confirm-apply';

if (!defined('OA_LEGACY_FILE_MIGRATION_LIBRARY_ONLY')) {
    exit(legacyFileMigrationMain($argv));
}

/** @param list<string> $argv */
function legacyFileMigrationMain(array $argv): int
{
    $manifest = null;
    $manifestPath = '';
    $database = '';
    $summary = [];

    try {
        $root = dirname(__DIR__);
        $options = parseLegacyFileMigrationOptions($argv);
        if (isset($options['help'])) {
            writeLegacyFileMigrationHelp();
            return 0;
        }

        $database = assertDatabaseCliBinding($options);
        $apply = isset($options['apply']);
        assertApplyCliBinding($options, $apply);
        $targetRoot = assertTargetRootCliBinding($options);
        $databaseMigrationCompleted = cliPath($options['database-migration-completed'] ?? '');
        if ($databaseMigrationCompleted === '') {
            throw new InvalidArgumentException('--database-migration-completed is required');
        }
        $databaseEvidence = validateDatabaseMigrationCompleted($databaseMigrationCompleted, $database);

        $sourceRoot = cliPath($options['source-root'] ?? '');
        if ($sourceRoot !== '' && !is_dir($sourceRoot)) {
            throw new InvalidArgumentException('source root does not exist');
        }
        $sourceDownloadUrl = trim((string)($options['source-download-url'] ?? ''));
        if ($sourceRoot === '' && $sourceDownloadUrl === '') {
            throw new InvalidArgumentException('provide --source-root, --source-download-url, or both');
        }
        $sourceCacheRoot = cliPath($options['source-cache-root'] ?? ($root . '/runtime/legacy-file-source-cache'));
        if ($sourceDownloadUrl !== '' && $sourceCacheRoot === '') {
            throw new InvalidArgumentException('source cache root is empty');
        }
        if ($sourceDownloadUrl !== '') {
            $sourceCacheRoot = canonicalProspectivePath($sourceCacheRoot);
            assertRootsStrictlySeparated($sourceCacheRoot, $targetRoot);
        }
        $targetRootIdentity = directoryIdentitySha256($targetRoot);

        $tenantId = trim((string)($options['tenant-id'] ?? ''));
        $fileId = trim((string)($options['file-id'] ?? ''));
        $limit = parseNonNegativeLimit($options['limit'] ?? null);
        $scopedRun = $tenantId !== '' || $fileId !== '' || $limit > 0;
        if ($apply && $scopedRun) {
            throw new InvalidArgumentException('apply requires a complete unscoped source inventory');
        }
        $privateManifestRoot = ensurePrivateManifestRoot($root);
        $approvedDryRun = null;
        if ($apply) {
            $approvedDryRun = readApprovedDryRunManifest(
                (string)$options['approved-dry-run-manifest'],
                (string)$options['approved-dry-run-manifest-sha256'],
                $privateManifestRoot
            );
        }
        $runId = bin2hex(random_bytes(12));
        $manifestPath = cliPath($options['manifest'] ?? (
            $privateManifestRoot . DIRECTORY_SEPARATOR
            . 'legacy-file-migration-' . date('Ymd-His') . '-' . substr($runId, 0, 8) . '.jsonl'
        ));
        $manifest = openExclusiveManifest($manifestPath, $privateManifestRoot);

        $summary = initialMigrationSummary($apply);
        $summary['scoped'] = $scopedRun;
        $summary['targetRoot'] = $targetRoot;
        $summary['databaseMigrationEvidenceSha256'] = $databaseEvidence['sha256'];
        manifest($manifest, [
            'type' => 'start',
            'time' => date(DATE_ATOM),
            'runId' => $runId,
            'mode' => $summary['mode'],
            'scoped' => $scopedRun,
            'databaseMigrationEvidenceSha256' => $databaseEvidence['sha256'],
            'sourceRoot' => $sourceRoot,
            'sourceDownloadConfigured' => $sourceDownloadUrl !== '',
            'sourceCacheRoot' => $sourceDownloadUrl !== '' ? $sourceCacheRoot : null,
            'targetRoot' => $targetRoot,
            'tenantScope' => $tenantId !== '',
            'fileScope' => $fileId !== '',
            'limit' => $limit ?: null,
        ]);

        require $root . '/vendor/autoload.php';
        (new App($root))->initialize();
        if ($sourceDownloadUrl !== '') {
            $sourceDownloadUrl = LegacyFileSource::validateDownloadUrlTemplate($sourceDownloadUrl);
            manifest($manifest, [
                'type' => 'source-download',
                'configured' => true,
                'host' => LegacyFileSource::host($sourceDownloadUrl),
            ]);
        }

        assertConnectedDatabase($database);
        Db::execute('SET SESSION TRANSACTION READ ONLY');
        $summary['preflightDatabaseReadOnly'] = true;
        $databaseSchemaSha256 = assertCurrentDatabaseSchemaMatches(
            $database,
            $databaseEvidence['finalSchemaSha256']
        );
        $summary['databaseBindingVerified'] = true;
        $summary['databaseSchemaSha256'] = $databaseSchemaSha256;
        manifest($manifest, [
            'type' => 'database-binding',
            'status' => 'verified',
            'databaseNameRecorded' => false,
        ]);
        manifest($manifest, [
            'type' => 'database-schema-binding',
            'status' => 'verified',
            'schemaSha256' => $databaseSchemaSha256,
        ]);
        $filePlan = [];
        $selectedRows = selectLegacyFileRows($tenantId, $fileId, $limit);
        $summary['sourceInventory']['selected'] = count($selectedRows);
        $targetLayout = buildSafeTargetLayout($selectedRows, $targetRoot);
        if ($sourceDownloadUrl !== '') {
            $sourceCacheRoot = preparePrivateSourceCacheRoot($sourceCacheRoot, $targetRoot);
        }
        foreach ($targetLayout as $layoutItem) {
            $filePlan[] = planFileRow(
                $layoutItem['row'],
                $sourceRoot,
                $sourceDownloadUrl,
                $sourceCacheRoot,
                $targetRoot,
                $layoutItem['relativeKey'],
                $layoutItem['targetPath'],
                !$apply,
                $manifest,
                $summary
            );
        }

        if ($scopedRun) {
            recordJsonStatus($summary, 'skipped');
            $legacyPlan = ['rows' => [], 'config' => null, 'scopedSkip' => true];
            manifest($manifest, [
                'type' => 'legacy-url-cleanup',
                'status' => 'skipped',
                'reason' => 'scoped file run',
            ]);
        } else {
            $legacyPlan = planLegacyUrls($targetRoot, $manifest, $summary);
        }

        $plan = buildMigrationPlan(
            $databaseEvidence['sha256'],
            $databaseSchemaSha256,
            $targetRoot,
            $scopedRun,
            $sourceRoot,
            $sourceDownloadUrl,
            $sourceCacheRoot,
            $filePlan,
            $legacyPlan,
            $summary
        );
        $summary['planVersion'] = $plan['version'];
        $summary['planSha256'] = $plan['sha256'];
        $summary['migrationCodeSha256'] = $plan['migrationCodeSha256'];
        manifest($manifest, [
            'type' => 'preflight-plan',
            'status' => 'computed',
            'planVersion' => $plan['version'],
            'planSha256' => $plan['sha256'],
            'migrationCodeSha256' => $plan['migrationCodeSha256'],
            'databaseSchemaSha256' => $databaseSchemaSha256,
            'sourceInventory' => $summary['sourceInventory'],
            'outcomes' => $summary['outcomes'],
            'errors' => $summary['errors'],
        ]);

        $unresolved = ($summary['files']['missing'] ?? 0)
            + ($summary['files']['conflict'] ?? 0)
            + $summary['errors'];
        if ($apply) {
            if ($unresolved !== 0) {
                throw new RuntimeException('apply preflight found unresolved source, conflict, or error outcomes');
            }
            assertApprovedDryRunMatches(
                $approvedDryRun,
                $plan,
                $summary,
                $targetRoot,
                $databaseEvidence['sha256']
            );
            assertRuntimePlanStable($filePlan, $legacyPlan, $targetRoot, $targetRootIdentity);
            $currentEvidence = validateDatabaseMigrationCompleted($databaseMigrationCompleted, $database);
            if (!hash_equals($databaseEvidence['sha256'], $currentEvidence['sha256'])) {
                throw new RuntimeException('database migration evidence changed after preflight');
            }
            assertCurrentDatabaseSchemaMatches($database, $currentEvidence['finalSchemaSha256']);
            assertTargetRootIdentity($targetRoot, $targetRootIdentity);
            Db::execute('SET SESSION TRANSACTION READ WRITE');
            $summary['approvedDryRunManifestSha256'] = $approvedDryRun['manifestSha256'];
            $summary['applyGatePassed'] = true;
            manifest($manifest, [
                'type' => 'apply-gate',
                'status' => 'approved',
                'planSha256' => $plan['sha256'],
                'approvedDryRunManifestSha256' => $approvedDryRun['manifestSha256'],
            ]);
            applyMigrationPlan(
                $filePlan,
                $legacyPlan,
                $targetRoot,
                $targetRootIdentity,
                $manifest,
                $summary
            );
        }

        $summary['completion'] = [
            'completed' => $unresolved === 0,
            'status' => $unresolved > 0 ? 'completed-with-issues' : 'completed',
            'time' => date(DATE_ATOM),
        ];
        writeManifestCompletion($manifest, $summary);
        fclose($manifest);
        $manifest = null;

        fwrite(STDOUT, json_encode([
            'manifest' => $manifestPath,
            'summary' => $summary,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);

        return $unresolved > 0 ? 2 : 0;
    } catch (Throwable $exception) {
        $safeMessage = safeMigrationError($exception, $database);
        if (is_resource($manifest)) {
            try {
                recordMigrationError($summary);
                $summary['completion'] = [
                    'completed' => false,
                    'status' => 'failed',
                    'time' => date(DATE_ATOM),
                ];
                manifest($manifest, [
                    'type' => 'fatal',
                    'status' => 'error',
                    'message' => $safeMessage,
                ]);
                writeManifestCompletion($manifest, $summary);
            } catch (Throwable) {
                $safeMessage .= '; manifest finalization also failed';
            } finally {
                fclose($manifest);
            }
        }
        fwrite(STDERR, '[legacy-files] ' . $safeMessage . PHP_EOL);
        return 1;
    }
}

function writeLegacyFileMigrationHelp(): void
{
    fwrite(STDOUT, <<<'TXT'
Usage:
  php scripts/migrate-legacy-files.php --database=NAME --confirm-database=NAME --source-root=/old/upload [options]
  php scripts/migrate-legacy-files.php --database=NAME --confirm-database=NAME --source-download-url='https://old.example/download?id={id}' [options]

Required safety options:
  --database=NAME_migrated    Intended migrated database; the live connection must resolve to this name
  --confirm-database=NAME     Independent repeat of --database; the two values must match exactly
  --database-migration-completed=PATH
                              Private completed.json from the successful database migration
  --target-root=PATH          Intended dev_file root
  --confirm-target-root=PATH  Independent repeat of --target-root; paths must match exactly

Options:
  --source-root=PATH          Existing legacy upload root; optional when a download URL is provided
  --source-download-url=URL   HTTP(S) fallback template containing exactly one {id} placeholder
  --source-cache-root=PATH    Verified remote source cache (default: runtime/legacy-file-source-cache)
  --manifest=PATH             New private JSONL manifest directly under runtime/backup
  --tenant-id=ID              Limit dev_file rows to one tenant
  --file-id=ID                Process one dev_file row for a targeted rehearsal or retry
  --limit=N                   Limit dev_file rows for a staged rehearsal
  --apply                     Copy files and update metadata/legacy URLs
  --confirm-apply             Required independent confirmation whenever --apply is present
  --approved-dry-run-manifest=PATH
                              Required full, clean dry-run manifest for apply
  --approved-dry-run-manifest-sha256=SHA256
                              Required exact SHA-256 of the approved dry-run manifest

Default mode is dry-run. Dry-run makes no database writes.
TXT);
}

/** @param list<string> $argv @return array<string, string|bool> */
function parseLegacyFileMigrationOptions(array $argv): array
{
    $valueOptions = [
        'database',
        'confirm-database',
        'database-migration-completed',
        'source-root',
        'source-download-url',
        'source-cache-root',
        'target-root',
        'confirm-target-root',
        'manifest',
        'tenant-id',
        'file-id',
        'limit',
        'approved-dry-run-manifest',
        'approved-dry-run-manifest-sha256',
    ];
    $flagOptions = ['apply', LEGACY_FILE_APPLY_CONFIRMATION_OPTION, 'help'];
    $options = [];

    foreach (array_slice($argv, 1) as $argument) {
        if (!str_starts_with($argument, '--')) {
            throw new InvalidArgumentException('all arguments must use --name=value or --flag');
        }
        [$name, $value] = array_pad(explode('=', substr($argument, 2), 2), 2, null);
        if ($name === '' || (!in_array($name, $valueOptions, true) && !in_array($name, $flagOptions, true))) {
            throw new InvalidArgumentException('unknown migration option');
        }
        if (array_key_exists($name, $options)) {
            throw new InvalidArgumentException('duplicate migration option');
        }
        if (in_array($name, $flagOptions, true)) {
            if ($value !== null) {
                throw new InvalidArgumentException('flag options must not have values');
            }
            $options[$name] = true;
            continue;
        }
        if ($value === null || trim($value) === '') {
            throw new InvalidArgumentException('value option is empty');
        }
        $options[$name] = $value;
    }

    return $options;
}

/** @param array<string, string|bool> $options */
function assertDatabaseCliBinding(array $options): string
{
    $database = trim((string)($options['database'] ?? ''));
    $confirmed = trim((string)($options['confirm-database'] ?? ''));
    if ($database === '' || $confirmed === '') {
        throw new InvalidArgumentException('--database and --confirm-database are both required');
    }
    if (strlen($database) > 64 || preg_match('/\A[A-Za-z0-9_]+_migrated\z/i', $database) !== 1) {
        throw new InvalidArgumentException('database name must be a safe identifier ending in _migrated');
    }
    if (!hash_equals($database, $confirmed)) {
        throw new InvalidArgumentException('database confirmation does not match');
    }

    return $database;
}

/** @param array<string, string|bool> $options */
function assertApplyCliBinding(array $options, bool $apply): void
{
    $confirmed = isset($options[LEGACY_FILE_APPLY_CONFIRMATION_OPTION]);
    $approvedManifest = trim((string)($options['approved-dry-run-manifest'] ?? ''));
    $approvedSha256 = strtolower(trim((string)($options['approved-dry-run-manifest-sha256'] ?? '')));
    if ($apply && !$confirmed) {
        throw new InvalidArgumentException('--apply requires the independent --confirm-apply flag');
    }
    if ($apply && ($approvedManifest === '' || preg_match('/\A[0-9a-f]{64}\z/', $approvedSha256) !== 1)) {
        throw new InvalidArgumentException('apply requires an approved dry-run manifest and its exact SHA-256');
    }
    if (!$apply && $confirmed) {
        throw new InvalidArgumentException('--confirm-apply is invalid without --apply');
    }
    if (!$apply && ($approvedManifest !== '' || $approvedSha256 !== '')) {
        throw new InvalidArgumentException('approved dry-run options are valid only with --apply');
    }
}

/** @param array<string, string|bool> $options */
function assertTargetRootCliBinding(array $options): string
{
    $targetRoot = cliPath($options['target-root'] ?? '');
    $confirmed = cliPath($options['confirm-target-root'] ?? '');
    if ($targetRoot === '' || $confirmed === '') {
        throw new InvalidArgumentException('--target-root and --confirm-target-root are both required');
    }
    if (!hash_equals($targetRoot, $confirmed)) {
        throw new InvalidArgumentException('target root confirmation does not match');
    }
    if (!is_dir($targetRoot)) {
        throw new InvalidArgumentException('confirmed target root must already exist as a directory');
    }
    assertNoSymlinkComponents($targetRoot);
    $real = realpath($targetRoot);
    if ($real === false) {
        throw new RuntimeException('confirmed target root cannot be resolved');
    }

    return cliPath($real);
}

function parseNonNegativeLimit(mixed $value): int
{
    if ($value === null) {
        return 0;
    }
    $value = trim((string)$value);
    if (preg_match('/\A\d+\z/', $value) !== 1) {
        throw new InvalidArgumentException('--limit must be a non-negative integer');
    }
    $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($limit === false) {
        throw new InvalidArgumentException('--limit is outside the supported integer range');
    }

    return $limit;
}

function ensurePrivateManifestRoot(string $projectRoot): string
{
    $root = $projectRoot . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'backup';
    if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
        throw new RuntimeException('cannot create private runtime/backup directory');
    }
    if (is_link($root)) {
        throw new RuntimeException('runtime/backup must not be a symbolic link');
    }
    assertNoSymlinkComponents($root);
    assertPrivatePermissions($root, true);
    $real = realpath($root);
    if ($real === false) {
        throw new RuntimeException('cannot resolve private runtime/backup directory');
    }

    return cliPath($real);
}

function assertManifestPathInPrivateRoot(string $path, string $privateRoot, bool $mustExist): string
{
    $portable = str_replace('\\', '/', $path);
    if ($path === ''
        || str_contains($path, "\0")
        || preg_match('#(?:^|/)\.\.?(?:/|$)#', $portable) === 1
        || !str_ends_with(strtolower(basename($portable)), '.jsonl')) {
        throw new InvalidArgumentException('manifest path is invalid');
    }
    $directory = realpath(dirname($path));
    $root = realpath($privateRoot);
    if ($directory === false || $root === false || !hash_equals(cliPath($root), cliPath($directory))) {
        throw new RuntimeException('manifest must be directly inside the private runtime/backup directory');
    }
    if (is_link($path)) {
        throw new RuntimeException('manifest must not be a symbolic link');
    }
    assertNoSymlinkComponents($mustExist ? $path : dirname($path));
    if ($mustExist) {
        if (!is_file($path)) {
            throw new RuntimeException('approved manifest is not a regular file');
        }
        assertPrivatePermissions($path, false);
    } elseif (file_exists($path)) {
        throw new RuntimeException('manifest path already exists');
    }

    return cliPath($path);
}

function assertPrivatePermissions(string $path, bool $directory): void
{
    if (DIRECTORY_SEPARATOR === '\\') {
        return;
    }
    $permissions = fileperms($path);
    if ($permissions === false || ($permissions & 0077) !== 0) {
        throw new RuntimeException($directory
            ? 'private migration directory permissions are too broad'
            : 'private migration file permissions are too broad');
    }
}

function assertNoSymlinkComponents(string $path): void
{
    [$anchor, $parts] = splitAbsoluteMigrationPath(absoluteMigrationPath($path));
    $current = $anchor;
    foreach ($parts as $part) {
        $current = appendMigrationPathPart($current, $part);
        clearstatcache(true, $current);
        $stat = @lstat($current);
        if ($stat === false) {
            continue;
        }
        if (pathComponentIsLinkOrReparse($current, $stat)) {
            throw new RuntimeException('private migration path contains a symlink or reparse-point component');
        }
    }
}

function absoluteMigrationPath(string $path): string
{
    $path = cliPath($path);
    if ($path === '' || str_contains($path, "\0")) {
        throw new RuntimeException('migration path is empty or invalid');
    }
    $portable = str_replace('\\', '/', $path);
    if (preg_match('#(?:\A|/)\.\.?(?:/|\z)#', $portable) === 1) {
        throw new RuntimeException('migration path contains traversal components');
    }
    $absolute = preg_match('#\A[A-Za-z]:/#', $portable) === 1
        || str_starts_with($portable, '/')
        || str_starts_with($portable, '//');
    if (!$absolute) {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new RuntimeException('cannot resolve migration path');
        }
        $path = cliPath($cwd . DIRECTORY_SEPARATOR . $path);
    }

    return $path;
}

/** @return array{0:string,1:list<string>} */
function splitAbsoluteMigrationPath(string $path): array
{
    $portable = str_replace('\\', '/', absoluteMigrationPath($path));
    if (preg_match('#\A([A-Za-z]:)/(.*)\z#', $portable, $match) === 1) {
        $anchor = $match[1] . DIRECTORY_SEPARATOR;
        $tail = $match[2];
    } elseif (str_starts_with($portable, '//')) {
        $unc = array_values(array_filter(explode('/', substr($portable, 2)), 'strlen'));
        if (count($unc) < 2) {
            throw new RuntimeException('UNC migration path has no share component');
        }
        $anchor = DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR . $unc[0] . DIRECTORY_SEPARATOR . $unc[1];
        $tail = implode('/', array_slice($unc, 2));
    } elseif (str_starts_with($portable, '/')) {
        $anchor = DIRECTORY_SEPARATOR;
        $tail = ltrim($portable, '/');
    } else {
        throw new RuntimeException('migration path is not absolute');
    }
    $parts = array_values(array_filter(explode('/', $tail), 'strlen'));
    foreach ($parts as $part) {
        if ($part === '.' || $part === '..' || str_contains($part, "\0")) {
            throw new RuntimeException('migration path contains an unsafe component');
        }
    }

    return [$anchor, $parts];
}

function appendMigrationPathPart(string $base, string $part): string
{
    return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $part;
}

/** @param array<int|string, mixed> $stat */
function pathComponentIsLinkOrReparse(string $path, array $stat): bool
{
    $mode = (int)($stat['mode'] ?? $stat[2] ?? 0);
    if (($mode & 0170000) === 0120000 || is_link($path)) {
        return true;
    }
    // PHP reports Windows junction/reparse-point lstat mode as 0 instead of a directory/file mode.
    return PHP_OS_FAMILY === 'Windows' && $mode === 0;
}

function pathComparisonKey(string $path): string
{
    $portable = rtrim(str_replace('\\', '/', absoluteMigrationPath($path)), '/');
    if (PHP_OS_FAMILY === 'Windows') {
        $portable = strtolower($portable);
    }

    return $portable;
}

function pathIsSameOrDescendant(string $candidate, string $root): bool
{
    $candidateKey = pathComparisonKey($candidate);
    $rootKey = pathComparisonKey($root);

    return $candidateKey === $rootKey || str_starts_with($candidateKey, $rootKey . '/');
}

function canonicalProspectivePath(string $path): string
{
    $path = absoluteMigrationPath($path);
    [$anchor, $parts] = splitAbsoluteMigrationPath($path);
    $current = $anchor;
    $missing = [];
    foreach ($parts as $part) {
        if ($missing !== []) {
            $missing[] = $part;
            continue;
        }
        $next = appendMigrationPathPart($current, $part);
        clearstatcache(true, $next);
        if (@lstat($next) === false) {
            $missing[] = $part;
            continue;
        }
        assertNoSymlinkComponents($next);
        $real = realpath($next);
        if ($real === false) {
            throw new RuntimeException('existing migration path component cannot be resolved');
        }
        $current = cliPath($real);
    }
    foreach ($missing as $part) {
        $current = appendMigrationPathPart($current, $part);
    }

    return cliPath($current);
}

function assertRootsStrictlySeparated(string $sourceCacheRoot, string $targetRoot): void
{
    $cache = canonicalProspectivePath($sourceCacheRoot);
    $target = canonicalProspectivePath($targetRoot);
    if (pathIsSameOrDescendant($cache, $target) || pathIsSameOrDescendant($target, $cache)) {
        throw new RuntimeException('source cache root and target root must be strictly separate trees');
    }
}

function preparePrivateSourceCacheRoot(string $sourceCacheRoot, string $targetRoot): string
{
    $sourceCacheRoot = canonicalProspectivePath($sourceCacheRoot);
    assertRootsStrictlySeparated($sourceCacheRoot, $targetRoot);
    [$anchor, $parts] = splitAbsoluteMigrationPath($sourceCacheRoot);
    $current = $anchor;
    foreach ($parts as $part) {
        $current = appendMigrationPathPart($current, $part);
        clearstatcache(true, $current);
        $stat = @lstat($current);
        if ($stat === false) {
            if (!@mkdir($current, 0700, false)) {
                clearstatcache(true, $current);
                if (!is_dir($current)) {
                    throw new RuntimeException('cannot create private source cache directory');
                }
            }
            clearstatcache(true, $current);
            $stat = @lstat($current);
        }
        if (!is_array($stat) || pathComponentIsLinkOrReparse($current, $stat) || !is_dir($current)) {
            throw new RuntimeException('source cache path contains an unsafe directory component');
        }
    }
    if (DIRECTORY_SEPARATOR !== '\\' && !@chmod($sourceCacheRoot, 0700)) {
        throw new RuntimeException('cannot enforce private source cache permissions');
    }
    assertPrivatePermissions($sourceCacheRoot, true);
    assertNoSymlinkComponents($sourceCacheRoot);
    $real = realpath($sourceCacheRoot);
    if ($real === false) {
        throw new RuntimeException('cannot resolve private source cache root');
    }
    assertRootsStrictlySeparated($real, $targetRoot);

    return cliPath($real);
}

function directoryIdentitySha256(string $directory): string
{
    clearstatcache(true, $directory);
    $stat = @lstat($directory);
    $real = realpath($directory);
    if (!is_array($stat) || $real === false || !is_dir($directory)
        || pathComponentIsLinkOrReparse($directory, $stat)) {
        throw new RuntimeException('target root identity cannot be established safely');
    }

    return canonicalSha256([
        'realpath' => pathComparisonKey($real),
        'device' => (int)($stat['dev'] ?? $stat[0] ?? 0),
        'inode' => (int)($stat['ino'] ?? $stat[1] ?? 0),
        'mode' => (int)($stat['mode'] ?? $stat[2] ?? 0),
    ]);
}

function assertTargetRootIdentity(string $targetRoot, string $expectedIdentity): void
{
    assertNoSymlinkComponents($targetRoot);
    if (!hash_equals($expectedIdentity, directoryIdentitySha256($targetRoot))) {
        throw new RuntimeException('target root identity changed after the zero-write gate');
    }
}

function assertSafeTargetPath(string $targetRoot, string $target): void
{
    $targetRoot = cliPath((string)realpath($targetRoot));
    $target = absoluteMigrationPath($target);
    if ($targetRoot === '' || !pathIsSameOrDescendant($target, $targetRoot)
        || pathComparisonKey($target) === pathComparisonKey($targetRoot)) {
        throw new RuntimeException('target path escaped or equals the migration root');
    }
    assertNoSymlinkComponents($targetRoot);
    $rootPortable = str_replace('\\', '/', rtrim($targetRoot, '/\\'));
    $targetPortable = str_replace('\\', '/', $target);
    $relative = substr($targetPortable, strlen($rootPortable) + 1);
    $parts = array_values(array_filter(explode('/', $relative), 'strlen'));
    $current = $targetRoot;
    foreach ($parts as $index => $part) {
        $current = appendMigrationPathPart($current, $part);
        clearstatcache(true, $current);
        $stat = @lstat($current);
        if ($stat === false) {
            continue;
        }
        if (pathComponentIsLinkOrReparse($current, $stat)) {
            throw new RuntimeException('target path contains a symlink or reparse-point component');
        }
        $leaf = $index === count($parts) - 1;
        if ((!$leaf && !is_dir($current)) || ($leaf && !is_file($current))) {
            throw new RuntimeException('target path has a file-versus-directory conflict');
        }
    }
}

function ensureSafeTargetDirectory(
    string $targetRoot,
    string $directory,
    string $expectedRootIdentity
): void {
    assertTargetRootIdentity($targetRoot, $expectedRootIdentity);
    if (!pathIsSameOrDescendant($directory, $targetRoot)) {
        throw new RuntimeException('target directory escaped the migration root');
    }
    $rootPortable = str_replace('\\', '/', rtrim($targetRoot, '/\\'));
    $directoryPortable = str_replace('\\', '/', absoluteMigrationPath($directory));
    $relative = substr($directoryPortable, strlen($rootPortable) + 1);
    $current = $targetRoot;
    foreach (array_values(array_filter(explode('/', $relative), 'strlen')) as $part) {
        $current = appendMigrationPathPart($current, $part);
        clearstatcache(true, $current);
        $stat = @lstat($current);
        if ($stat === false) {
            if (!@mkdir($current, 0775, false)) {
                clearstatcache(true, $current);
                if (!is_dir($current)) {
                    throw new RuntimeException('cannot create target directory safely');
                }
            }
            clearstatcache(true, $current);
            $stat = @lstat($current);
        }
        if (!is_array($stat) || pathComponentIsLinkOrReparse($current, $stat) || !is_dir($current)) {
            throw new RuntimeException('target directory contains an unsafe component');
        }
    }
    assertTargetRootIdentity($targetRoot, $expectedRootIdentity);
}

/** @return resource */
function openExclusiveManifest(string $path, string $privateRoot)
{
    $path = assertManifestPathInPrivateRoot($path, $privateRoot, false);
    $handle = @fopen($path, 'x+b');
    if ($handle === false) {
        throw new RuntimeException('manifest cannot be created exclusively');
    }
    if (DIRECTORY_SEPARATOR !== '\\' && !@chmod($path, 0600)) {
        fclose($handle);
        @unlink($path);
        throw new RuntimeException('cannot enforce private manifest permissions');
    }

    return $handle;
}

/** @return array{sha256:string,finalSchemaSha256:string} */
function validateDatabaseMigrationCompleted(string $path, string $expectedDatabase): array
{
    $path = cliPath($path);
    if ($path === '' || basename($path) !== 'completed.json' || !is_file($path) || is_link($path)) {
        throw new RuntimeException('database migration evidence must be a regular completed.json file');
    }
    assertNoSymlinkComponents($path);
    $parent = dirname($path);
    if (is_link($parent)) {
        throw new RuntimeException('database migration evidence directory must not be a symbolic link');
    }
    assertPrivatePermissions($parent, true);
    assertPrivatePermissions($path, false);
    $raw = file_get_contents($path);
    if ($raw === false || strlen($raw) > 8 * 1024 * 1024) {
        throw new RuntimeException('database migration evidence is unreadable or unexpectedly large');
    }
    $evidence = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($evidence)) {
        throw new RuntimeException('database migration evidence is not a JSON object');
    }
    $status = $evidence['status'] ?? 'completed';
    if ($status !== 'completed'
        || ($evidence['mode'] ?? null) !== 'apply'
        || ($evidence['readyForApply'] ?? null) !== true
        || ($evidence['writesPerformed'] ?? null) !== true
        || ($evidence['targetCreatedFromTemplateSchemaOnly'] ?? null) !== true
        || ($evidence['sourceCreateOrDropImported'] ?? null) !== false
        || ($evidence['installerIdempotencyVerified'] ?? null) !== true
        || !is_array($evidence['source'] ?? null)
        || !is_array($evidence['template'] ?? null)
        || !is_array($evidence['schemaComparison'] ?? null)
        || ($evidence['schemaComparison']['valid'] ?? null) !== true
    ) {
        throw new RuntimeException('database migration completed.json has an invalid completion status or structure');
    }
    if (!is_string($evidence['targetDatabase'] ?? null)
        || !hash_equals($expectedDatabase, $evidence['targetDatabase'])) {
        throw new RuntimeException('database migration evidence target does not match the confirmed database');
    }
    $finalSchemaSha256 = strtolower(trim((string)($evidence['finalSchemaSha256'] ?? '')));
    if (preg_match('/\A[0-9a-f]{64}\z/', $finalSchemaSha256) !== 1) {
        throw new RuntimeException('database migration evidence has no valid final schema digest');
    }
    $manifestDirectoryValue = trim((string)($evidence['manifestDirectory'] ?? ''));
    if ($manifestDirectoryValue === '') {
        throw new RuntimeException('database migration evidence has no owning manifest directory');
    }
    $manifestDirectory = realpath($manifestDirectoryValue);
    $evidenceDirectory = realpath($parent);
    if ($manifestDirectory === false
        || $evidenceDirectory === false
        || !hash_equals(cliPath($evidenceDirectory), cliPath($manifestDirectory))) {
        throw new RuntimeException('database migration evidence is detached from its owning manifest directory');
    }
    assertNoSymlinkComponents($manifestDirectoryValue);
    $sha256 = hash('sha256', $raw);
    $sha256AfterRead = hash_file('sha256', $path);
    if (!is_string($sha256AfterRead) || !hash_equals($sha256, strtolower($sha256AfterRead))) {
        throw new RuntimeException('cannot hash database migration evidence');
    }

    return [
        'sha256' => $sha256,
        'finalSchemaSha256' => $finalSchemaSha256,
    ];
}

/** @return array<string, mixed> */
function readApprovedDryRunManifest(string $path, string $expectedSha256, string $privateRoot): array
{
    $path = assertManifestPathInPrivateRoot(cliPath($path), $privateRoot, true);
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('approved dry-run manifest cannot be opened');
    }
    $recordCount = 0;
    $start = null;
    $summarySeen = false;
    $summaryRecord = null;
    $planRecord = null;
    $completion = null;
    $hashContext = hash_init('sha256');
    try {
        while (($line = fgets($handle)) !== false) {
            hash_update($hashContext, $line);
            if (trim($line) === '') {
                continue;
            }
            $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($record)) {
                throw new RuntimeException('approved dry-run manifest contains a non-object record');
            }
            $recordCount++;
            $type = (string)($record['type'] ?? '');
            if ($recordCount === 1) {
                if ($type !== 'start' || ($record['mode'] ?? null) !== 'dry-run') {
                    throw new RuntimeException('approved dry-run manifest has an invalid start record');
                }
                $start = $record;
            }
            if ($type === 'fatal') {
                throw new RuntimeException('approved dry-run manifest contains a fatal record');
            }
            if ($completion !== null) {
                throw new RuntimeException('approved dry-run manifest contains records after completion');
            }
            if ($type === 'summary') {
                if ($summarySeen || !is_array($record['summary'] ?? null)) {
                    throw new RuntimeException('approved dry-run manifest has duplicate or invalid summary records');
                }
                $summarySeen = true;
                $summaryRecord = $record['summary'];
            } elseif ($type === 'preflight-plan') {
                if ($planRecord !== null) {
                    throw new RuntimeException('approved dry-run manifest has duplicate preflight plans');
                }
                $planRecord = $record;
            } elseif ($type === 'completion') {
                $completion = $record;
            }
        }
        if (!feof($handle)) {
            throw new RuntimeException('approved dry-run manifest could not be read completely');
        }
    } finally {
        fclose($handle);
    }
    $actualSha256 = hash_final($hashContext);
    $sha256AfterRead = hash_file('sha256', $path);
    if (!is_string($sha256AfterRead)
        || !hash_equals($actualSha256, strtolower($sha256AfterRead))
        || !hash_equals(strtolower($expectedSha256), $actualSha256)) {
        throw new RuntimeException('approved dry-run manifest SHA-256 does not match or changed during review');
    }
    if (!is_array($start) || !$summarySeen || !is_array($summaryRecord)
        || !is_array($planRecord) || !is_array($completion)) {
        throw new RuntimeException('approved dry-run manifest is incomplete');
    }
    $outcomes = $completion['outcomes'] ?? null;
    $inventory = $completion['sourceInventory'] ?? null;
    if (($completion['mode'] ?? null) !== 'dry-run'
        || ($completion['scoped'] ?? null) !== false
        || ($completion['status'] ?? null) !== 'completed'
        || ($completion['completed'] ?? null) !== true
        || ($completion['errors'] ?? null) !== 0
        || ($completion['databaseWriteStatements'] ?? null) !== 0
        || ($completion['databaseRowsAffected'] ?? null) !== 0
        || ($completion['filesCopied'] ?? null) !== 0
        || ($completion['legacyUrlsUpdated'] ?? null) !== 0
        || ($completion['configUpdated'] ?? null) !== false
        || !is_array($outcomes)
        || (int)($outcomes['missing'] ?? -1) !== 0
        || (int)($outcomes['conflict'] ?? -1) !== 0
        || (int)($outcomes['error'] ?? -1) !== 0
        || !is_array($inventory)
        || !is_array($inventory['byKind'] ?? null)
        || (int)($inventory['selected'] ?? -1) < 0
        || preg_match('/\A[0-9a-f]{64}\z/', (string)($completion['planSha256'] ?? '')) !== 1
        || preg_match('/\A[0-9a-f]{64}\z/', (string)($completion['migrationCodeSha256'] ?? '')) !== 1
        || preg_match('/\A[0-9a-f]{64}\z/', (string)($completion['databaseMigrationEvidenceSha256'] ?? '')) !== 1
        || preg_match('/\A[0-9a-f]{64}\z/', (string)($completion['databaseSchemaSha256'] ?? '')) !== 1
        || trim((string)($completion['targetRoot'] ?? '')) === ''
        || ($start['scoped'] ?? null) !== false
        || !hash_equals((string)($start['targetRoot'] ?? ''), (string)$completion['targetRoot'])
        || !hash_equals(
            (string)($start['databaseMigrationEvidenceSha256'] ?? ''),
            (string)$completion['databaseMigrationEvidenceSha256']
        )
        || ($planRecord['status'] ?? null) !== 'computed'
        || (int)($planRecord['planVersion'] ?? 0) !== (int)($completion['planVersion'] ?? -1)
        || !hash_equals((string)($planRecord['planSha256'] ?? ''), (string)$completion['planSha256'])
        || !hash_equals(
            (string)($planRecord['migrationCodeSha256'] ?? ''),
            (string)$completion['migrationCodeSha256']
        )
        || !hash_equals(
            (string)($planRecord['databaseSchemaSha256'] ?? ''),
            (string)$completion['databaseSchemaSha256']
        )
        || canonicalSha256($planRecord['sourceInventory'] ?? null) !== canonicalSha256($inventory)
        || canonicalSha256($planRecord['outcomes'] ?? null) !== canonicalSha256($outcomes)
        || (int)($planRecord['errors'] ?? -1) !== 0
        || ($summaryRecord['completion']['completed'] ?? null) !== true
        || ($summaryRecord['completion']['status'] ?? null) !== 'completed'
        || !hash_equals((string)($summaryRecord['planSha256'] ?? ''), (string)$completion['planSha256'])
        || !hash_equals(
            (string)($summaryRecord['databaseSchemaSha256'] ?? ''),
            (string)$completion['databaseSchemaSha256']
        )
        || canonicalSha256($summaryRecord['sourceInventory'] ?? null) !== canonicalSha256($inventory)
        || canonicalSha256($summaryRecord['outcomes'] ?? null) !== canonicalSha256($outcomes)
    ) {
        throw new RuntimeException('approved dry-run manifest is scoped, unresolved, writable, or structurally invalid');
    }

    $completion['manifestSha256'] = $actualSha256;
    return $completion;
}

/** @return array<string, mixed> */
function initialMigrationSummary(bool $apply): array
{
    return [
        'mode' => $apply ? 'apply' : 'dry-run',
        'databaseBindingVerified' => false,
        'databaseSchemaSha256' => null,
        'preflightDatabaseReadOnly' => false,
        'applyGatePassed' => false,
        'databaseWriteStatements' => 0,
        'databaseRowsAffected' => 0,
        'filesCopied' => 0,
        'legacyUrlsUpdated' => 0,
        'configUpdated' => false,
        'sourceInventory' => [
            'selected' => 0,
            'byKind' => [],
        ],
        'outcomes' => [
            'success' => 0,
            'missing' => 0,
            'conflict' => 0,
            'error' => 0,
        ],
        'files' => [],
        'json' => [],
        'errors' => 0,
        'completion' => [
            'completed' => false,
            'status' => 'running',
            'time' => null,
        ],
    ];
}

function assertConnectedDatabase(string $expected): void
{
    $rows = Db::query('SELECT DATABASE() AS selected_database');
    $row = is_array($rows[0] ?? null) ? $rows[0] : [];
    $actual = trim((string)($row['selected_database'] ?? $row['SELECTED_DATABASE'] ?? ''));
    if ($actual === '' || !hash_equals($expected, $actual)) {
        throw new RuntimeException('connected database does not match the explicitly confirmed target');
    }
}

function assertCurrentDatabaseSchemaMatches(string $database, string $expectedSha256): string
{
    if (preg_match('/\A[0-9a-f]{64}\z/', $expectedSha256) !== 1) {
        throw new RuntimeException('expected database schema digest is invalid');
    }
    $actual = currentDatabaseSchemaSha256($database);
    if (!hash_equals($expectedSha256, $actual)) {
        throw new RuntimeException('connected target schema does not match the completed database migration evidence');
    }

    return $actual;
}

function currentDatabaseSchemaSha256(string $database): string
{
    if (strlen($database) > 64 || preg_match('/\A[A-Za-z0-9_]+_migrated\z/i', $database) !== 1) {
        throw new RuntimeException('schema audit database identifier is invalid');
    }
    $tables = Db::query(
        "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES "
        . "WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
        [$database]
    );
    $columns = Db::query(
        'SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, '
        . 'CHARACTER_SET_NAME, COLLATION_NAME, GENERATION_EXPRESSION FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION',
        [$database]
    );
    $indexes = Db::query(
        'SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART, COLLATION, INDEX_TYPE '
        . 'FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? '
        . 'ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX',
        [$database]
    );
    $foreignKeys = Db::query(
        'SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION, k.COLUMN_NAME, '
        . 'k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE '
        . 'FROM information_schema.KEY_COLUMN_USAGE k '
        . 'JOIN information_schema.REFERENTIAL_CONSTRAINTS r '
        . 'ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME '
        . 'AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME '
        . 'WHERE k.CONSTRAINT_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL '
        . 'ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
        [$database]
    );

    return databaseSchemaSha256FromMetadata($tables, $columns, $indexes, $foreignKeys);
}

/**
 * @param list<array<string, mixed>> $tableRows
 * @param list<array<string, mixed>> $columnRows
 * @param list<array<string, mixed>> $indexRows
 * @param list<array<string, mixed>> $foreignRows
 */
function databaseSchemaSha256FromMetadata(
    array $tableRows,
    array $columnRows,
    array $indexRows,
    array $foreignRows
): string {
    $tables = [];
    foreach ($tableRows as $row) {
        $name = (string)informationSchemaValue($row, 'TABLE_NAME');
        if ($name === '' || isset($tables[$name])) {
            throw new RuntimeException('database schema metadata contains an invalid or duplicate table');
        }
        $tables[$name] = [
            'engine' => (string)informationSchemaValue($row, 'ENGINE'),
            'collation' => (string)informationSchemaValue($row, 'TABLE_COLLATION'),
            'columns' => [],
            'indexes' => [],
            'foreignKeys' => [],
        ];
    }
    foreach ($columnRows as $row) {
        $table = (string)informationSchemaValue($row, 'TABLE_NAME');
        $column = (string)informationSchemaValue($row, 'COLUMN_NAME');
        if (!isset($tables[$table]) || $column === '' || isset($tables[$table]['columns'][$column])) {
            throw new RuntimeException('database schema column metadata is inconsistent');
        }
        $tables[$table]['columns'][$column] = [
            'ordinal' => (int)informationSchemaValue($row, 'ORDINAL_POSITION'),
            'type' => strtolower((string)informationSchemaValue($row, 'COLUMN_TYPE')),
            'nullable' => (string)informationSchemaValue($row, 'IS_NULLABLE'),
            'default' => informationSchemaValue($row, 'COLUMN_DEFAULT'),
            'extra' => strtolower((string)informationSchemaValue($row, 'EXTRA')),
            'charset' => informationSchemaValue($row, 'CHARACTER_SET_NAME'),
            'collation' => informationSchemaValue($row, 'COLLATION_NAME'),
            'generationExpression' => informationSchemaValue($row, 'GENERATION_EXPRESSION'),
        ];
    }
    foreach ($indexRows as $row) {
        $table = (string)informationSchemaValue($row, 'TABLE_NAME');
        $index = (string)informationSchemaValue($row, 'INDEX_NAME');
        if (!isset($tables[$table]) || $index === '') {
            throw new RuntimeException('database schema index metadata is inconsistent');
        }
        if (!isset($tables[$table]['indexes'][$index])) {
            $tables[$table]['indexes'][$index] = [
                'unique' => (string)informationSchemaValue($row, 'NON_UNIQUE') === '0',
                'type' => (string)informationSchemaValue($row, 'INDEX_TYPE'),
                'columns' => [],
            ];
        }
        $subPart = informationSchemaValue($row, 'SUB_PART');
        $tables[$table]['indexes'][$index]['columns'][] = [
            'name' => informationSchemaValue($row, 'COLUMN_NAME'),
            'prefix' => $subPart === null ? null : (int)$subPart,
            'collation' => informationSchemaValue($row, 'COLLATION'),
        ];
    }
    foreach ($foreignRows as $row) {
        $table = (string)informationSchemaValue($row, 'TABLE_NAME');
        $constraint = (string)informationSchemaValue($row, 'CONSTRAINT_NAME');
        if (!isset($tables[$table]) || $constraint === '') {
            throw new RuntimeException('database schema foreign-key metadata is inconsistent');
        }
        if (!isset($tables[$table]['foreignKeys'][$constraint])) {
            $tables[$table]['foreignKeys'][$constraint] = [
                'referencedTable' => (string)informationSchemaValue($row, 'REFERENCED_TABLE_NAME'),
                'updateRule' => (string)informationSchemaValue($row, 'UPDATE_RULE'),
                'deleteRule' => (string)informationSchemaValue($row, 'DELETE_RULE'),
                'columns' => [],
            ];
        }
        $tables[$table]['foreignKeys'][$constraint]['columns'][] = [
            'name' => (string)informationSchemaValue($row, 'COLUMN_NAME'),
            'referenced' => (string)informationSchemaValue($row, 'REFERENCED_COLUMN_NAME'),
        ];
    }

    return canonicalSha256($tables);
}

/** @param array<string, mixed> $row */
function informationSchemaValue(array $row, string $name): mixed
{
    if (array_key_exists($name, $row)) {
        return $row[$name];
    }
    $lower = strtolower($name);
    if (array_key_exists($lower, $row)) {
        return $row[$lower];
    }
    foreach ($row as $key => $value) {
        if (strtolower((string)$key) === $lower) {
            return $value;
        }
    }

    return null;
}

/** @param array<string, mixed> $values @param array<string, mixed> $summary */
function applyDatabaseUpdate(mixed $query, array $values, bool $apply, array &$summary): int
{
    if (!$apply) {
        throw new LogicException('database update was blocked because migration is in dry-run mode');
    }
    if (!is_object($query) || !method_exists($query, 'update')) {
        throw new LogicException('database update target is invalid');
    }
    $affected = (int)$query->update($values);
    $summary['databaseWriteStatements'] = (int)($summary['databaseWriteStatements'] ?? 0) + 1;
    $summary['databaseRowsAffected'] = (int)($summary['databaseRowsAffected'] ?? 0) + max(0, $affected);

    return $affected;
}

/** @return list<array<string, mixed>> */
function selectLegacyFileRows(string $tenantId, string $fileId, int $limit): array
{
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
    if ($fileId !== '') {
        $query->where('ID', $fileId);
    }
    if ($limit > 0) {
        $query->limit($limit);
    }

    return $query->select()->toArray();
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array{row:array<string,mixed>,relativeKey:string,targetPath:string,canonicalTarget:string}>
 */
function buildSafeTargetLayout(array $rows, string $targetRoot): array
{
    $layout = [];
    foreach ($rows as $row) {
        $relativeKey = relativeStorageKey($row);
        $target = safeJoin($targetRoot, $relativeKey);
        assertSafeTargetPath($targetRoot, $target);
        $layout[] = [
            'row' => $row,
            'relativeKey' => $relativeKey,
            'targetPath' => $target,
            'canonicalTarget' => pathComparisonKey($target),
        ];
    }
    assertTargetLayoutHasNoConflicts($layout);

    return $layout;
}

/** @param list<array{canonicalTarget:string}> $layout */
function assertTargetLayoutHasNoConflicts(array $layout): void
{
    $targets = array_map(static fn (array $item): string => $item['canonicalTarget'], $layout);
    usort($targets, static fn (string $left, string $right): int =>
        strlen($left) <=> strlen($right) ?: strcmp($left, $right));
    $accepted = [];
    foreach ($targets as $target) {
        foreach ($accepted as $ancestor) {
            if (hash_equals($ancestor, $target)) {
                throw new RuntimeException('multiple database rows resolve to the same canonical target path');
            }
            if (str_starts_with($target, rtrim($ancestor, '/') . '/')) {
                throw new RuntimeException('planned targets contain a file-versus-directory prefix conflict');
            }
        }
        $accepted[] = $target;
    }
}

/**
 * @param array<string, mixed> $row
 * @param resource $manifest
 * @param array<string, mixed> $summary
 * @return array<string, mixed>
 */
function planFileRow(
    array $row,
    string $sourceRoot,
    string $sourceDownloadUrl,
    string $sourceCacheRoot,
    string $targetRoot,
    string $relativeKey,
    string $target,
    bool $allowRemoteFetch,
    $manifest,
    array &$summary
): array {
    $id = trim((string)($row['ID'] ?? ''));
    $sourceRecorded = false;
    try {
        assertSafeTargetPath($targetRoot, $target);
        $source = findSource(
            $row,
            $sourceRoot,
            $sourceDownloadUrl,
            $sourceCacheRoot,
            $target,
            $relativeKey,
            $allowRemoteFetch
        );
        $targetExists = is_file($target);

        if ($source === null && !$targetExists) {
            recordSourceStatus($summary, 'unresolved');
            $sourceRecorded = true;
            recordFileStatus($summary, 'missing');
            manifest($manifest, [
                'type' => 'file',
                'id' => $id,
                'status' => 'missing',
                'target' => $target,
                'relativeKey' => $relativeKey,
            ]);
            return [
                'id' => $id,
                'rowSha256' => canonicalSha256($row),
                'relativeKey' => $relativeKey,
                'status' => 'missing',
                'sourceKind' => 'unresolved',
                'sourcePath' => null,
                'targetPath' => $target,
                'size' => null,
                'sha256' => null,
                'downloadPath' => null,
            ];
        }

        $sourcePath = $source['path'] ?? null;
        $sourceKind = $source['kind'] ?? 'target-existing';
        recordSourceStatus($summary, $sourceKind);
        $sourceRecorded = true;

        if ($sourcePath !== null && $targetExists && !sameFile($sourcePath, $target)) {
            if (hash_file('sha256', $sourcePath) !== hash_file('sha256', $target)) {
                recordFileStatus($summary, 'conflict');
                manifest($manifest, [
                    'type' => 'file',
                    'id' => $id,
                    'status' => 'conflict',
                    'sourceKind' => $sourceKind,
                    'source' => $sourcePath,
                    'target' => $target,
                ]);
                return [
                    'id' => $id,
                    'rowSha256' => canonicalSha256($row),
                    'relativeKey' => $relativeKey,
                    'status' => 'conflict',
                    'sourceKind' => $sourceKind,
                    'sourcePath' => $sourcePath,
                    'targetPath' => $target,
                    'size' => null,
                    'sha256' => null,
                    'downloadPath' => null,
                ];
            }
        }

        $status = $targetExists ? 'existing' : 'ready';
        $effectiveFile = is_file($target) ? $target : $sourcePath;
        if ($effectiveFile === null || !is_file($effectiveFile)) {
            throw new RuntimeException('resolved file is not readable');
        }
        $size = filesize($effectiveFile);
        $sha256 = hash_file('sha256', $effectiveFile);
        if ($size === false || !is_string($sha256)) {
            throw new RuntimeException('resolved file size or digest is unavailable');
        }
        $downloadPath = FileDownloadUrl::normalize($id, 'LOCAL', $row['DOWNLOAD_PATH'] ?? null);

        recordFileStatus($summary, $status);
        manifest($manifest, [
            'type' => 'file',
            'id' => $id,
            'status' => $status,
            'sourceKind' => $sourceKind,
            'source' => $sourcePath,
            'target' => $target,
            'size' => $size,
            'sha256' => $sha256,
            'downloadPath' => $downloadPath,
        ]);
        return [
            'id' => $id,
            'rowSha256' => canonicalSha256($row),
            'relativeKey' => $relativeKey,
            'status' => $status,
            'sourceKind' => $sourceKind,
            'sourcePath' => $sourcePath,
            'targetPath' => $target,
            'size' => $size,
            'sha256' => $sha256,
            'downloadPath' => $downloadPath,
        ];
    } catch (Throwable $exception) {
        if (!$sourceRecorded) {
            recordSourceStatus($summary, 'resolution-error');
        }
        recordMigrationError($summary, false);
        recordFileStatus($summary, 'error');
        manifest($manifest, [
            'type' => 'file',
            'id' => $id,
            'status' => 'error',
            'message' => safeMigrationError($exception),
        ]);
        return [
            'id' => $id,
            'rowSha256' => canonicalSha256($row),
            'relativeKey' => null,
            'status' => 'error',
            'sourceKind' => 'resolution-error',
            'sourcePath' => null,
            'targetPath' => null,
            'size' => null,
            'sha256' => null,
            'downloadPath' => null,
        ];
    }
}

/**
 * @param resource $manifest
 * @param array<string, mixed> $summary
 * @return array{rows:list<array<string,mixed>>,config:?array<string,mixed>,scopedSkip:bool}
 */
function planLegacyUrls(string $targetRoot, $manifest, array &$summary): array
{
    $targets = legacyUrlTargets();

    $plan = ['rows' => [], 'config' => null, 'scopedSkip' => false];
    foreach ($targets as $target) {
        $table = $target['table'];
        $column = $target['column'];
        try {
            $rows = Db::name($table)
                ->field(['ID', $column])
                ->whereLike($column, '%dev/file/download%')
                ->order('ID', 'asc')
                ->select()
                ->toArray();
        } catch (Throwable $exception) {
            recordMigrationError($summary);
            manifest($manifest, [
                'type' => 'legacy-url-table',
                'table' => $table,
                'status' => 'error',
                'message' => safeMigrationError($exception),
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
                    recordMigrationError($summary);
                    recordJsonStatus($summary, 'invalid');
                    manifest($manifest, [
                        'type' => 'legacy-url',
                        'table' => $table,
                        'id' => $id,
                        'status' => 'invalid-json',
                    ]);
                    $plan['rows'][] = [
                        'table' => $table,
                        'column' => $column,
                        'id' => $id,
                        'rawSha256' => hash('sha256', $raw),
                        'normalizedSha256' => null,
                        'normalized' => null,
                        'status' => 'invalid-json',
                    ];
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

            $status = $changed ? 'ready' : 'unchanged';
            recordJsonStatus($summary, $status);
            manifest($manifest, [
                'type' => 'legacy-url',
                'table' => $table,
                'column' => $column,
                'id' => $id,
                'status' => $status,
            ]);
            $plan['rows'][] = [
                'table' => $table,
                'column' => $column,
                'id' => $id,
                'rawSha256' => hash('sha256', $raw),
                'normalizedSha256' => hash('sha256', $normalized),
                'normalized' => $normalized,
                'status' => $status,
            ];
        }
    }

    $config = Db::name('dev_config')->where('CONFIG_KEY', 'SNOWY_FILE_LOCAL_FOLDER_FOR_UNIX')->find();
    if (is_array($config) && $config !== []) {
        $rawConfig = (string)($config['CONFIG_VALUE'] ?? '');
        $status = $rawConfig === $targetRoot ? 'unchanged' : 'ready';
        recordJsonStatus($summary, $status);
        manifest($manifest, [
            'type' => 'legacy-config',
            'key' => 'SNOWY_FILE_LOCAL_FOLDER_FOR_UNIX',
            'status' => $status,
            'value' => $targetRoot,
        ]);
        $plan['config'] = [
            'id' => (string)$config['ID'],
            'rawSha256' => hash('sha256', $rawConfig),
            'normalizedSha256' => hash('sha256', $targetRoot),
            'normalized' => $targetRoot,
            'status' => $status,
        ];
    }

    return $plan;
}

/** @return list<array{table:string,column:string,json:bool}> */
function legacyUrlTargets(): array
{
    return [
        ['table' => 'biz_draft', 'column' => 'EXT_JSON', 'json' => true],
        ['table' => 'biz_product', 'column' => 'COVER_IMAGE', 'json' => false],
        ['table' => 'product_relation', 'column' => 'EXT_JSON', 'json' => true],
        ['table' => 'sale_project_follow_up', 'column' => 'EXT_JSON', 'json' => true],
        ['table' => 'sale_project_rate', 'column' => 'EXT_JSON', 'json' => true],
    ];
}

/**
 * @param list<array<string, mixed>> $filePlan
 * @param array{rows:list<array<string,mixed>>,config:?array<string,mixed>,scopedSkip:bool} $legacyPlan
 * @param array<string, mixed> $summary
 * @return array{version:int,sha256:string,migrationCodeSha256:string}
 */
function buildMigrationPlan(
    string $databaseEvidenceSha256,
    string $databaseSchemaSha256,
    string $targetRoot,
    bool $scoped,
    string $sourceRoot,
    string $sourceDownloadUrl,
    string $sourceCacheRoot,
    array $filePlan,
    array $legacyPlan,
    array $summary
): array {
    $files = array_map(static fn (array $item): array => [
        'id' => $item['id'],
        'rowSha256' => $item['rowSha256'],
        'relativeKey' => $item['relativeKey'],
        'status' => $item['status'],
        'sourceKind' => $item['sourceKind'],
        'sourcePath' => $item['sourcePath'],
        'targetPath' => $item['targetPath'],
        'size' => $item['size'],
        'sha256' => $item['sha256'],
        'downloadPath' => $item['downloadPath'],
    ], $filePlan);
    $legacyRows = array_map(static fn (array $item): array => [
        'table' => $item['table'],
        'column' => $item['column'],
        'id' => $item['id'],
        'rawSha256' => $item['rawSha256'],
        'normalizedSha256' => $item['normalizedSha256'],
        'status' => $item['status'],
    ], $legacyPlan['rows']);
    $config = $legacyPlan['config'];
    if (is_array($config)) {
        unset($config['normalized']);
    }
    $migrationCodeSha256 = legacyFileMigrationCodeSha256();
    $payload = [
        'version' => 3,
        'migrationCodeSha256' => $migrationCodeSha256,
        'databaseMigrationEvidenceSha256' => $databaseEvidenceSha256,
        'databaseSchemaSha256' => $databaseSchemaSha256,
        'targetRoot' => $targetRoot,
        'scoped' => $scoped,
        'sourceRoot' => $sourceRoot,
        'sourceDownloadHost' => $sourceDownloadUrl === '' ? null : LegacyFileSource::host($sourceDownloadUrl),
        'sourceCacheRoot' => $sourceDownloadUrl === '' ? null : $sourceCacheRoot,
        'sourceInventory' => $summary['sourceInventory'],
        'outcomes' => $summary['outcomes'],
        'errors' => $summary['errors'],
        'files' => $files,
        'legacyRows' => $legacyRows,
        'legacyConfig' => $config,
        'legacyScopedSkip' => $legacyPlan['scopedSkip'],
    ];

    return [
        'version' => 3,
        'sha256' => canonicalSha256($payload),
        'migrationCodeSha256' => $migrationCodeSha256,
    ];
}

function legacyFileMigrationCodeSha256(): string
{
    $root = dirname(__DIR__);
    $paths = [
        'entry' => __FILE__,
        'download-url-support' => $root . '/app/support/FileDownloadUrl.php',
        'legacy-source-support' => $root . '/app/support/LegacyFileSource.php',
    ];
    ksort($paths);
    $digests = [];
    foreach ($paths as $label => $path) {
        $digest = is_file($path) ? hash_file('sha256', $path) : false;
        if (!is_string($digest)) {
            throw new RuntimeException('legacy file migration code bundle is incomplete');
        }
        $digests[$label] = $digest;
    }

    return canonicalSha256($digests);
}

/** @param array<string, mixed>|null $approved @param array{version:int,sha256:string,migrationCodeSha256:string} $plan */
function assertApprovedDryRunMatches(
    ?array $approved,
    array $plan,
    array $summary,
    string $targetRoot,
    string $databaseEvidenceSha256
): void {
    if (!is_array($approved)
        || (int)($approved['planVersion'] ?? 0) !== $plan['version']
        || !hash_equals((string)$approved['planSha256'], $plan['sha256'])
        || !hash_equals((string)$approved['databaseMigrationEvidenceSha256'], $databaseEvidenceSha256)
        || !hash_equals(
            (string)($approved['databaseSchemaSha256'] ?? ''),
            (string)($summary['databaseSchemaSha256'] ?? '')
        )
        || !hash_equals(cliPath((string)$approved['targetRoot']), $targetRoot)
        || canonicalSha256($approved['sourceInventory']) !== canonicalSha256($summary['sourceInventory'])
        || ($summary['scoped'] ?? true) !== false
        || (int)($summary['errors'] ?? -1) !== 0
        || (int)($summary['outcomes']['missing'] ?? -1) !== 0
        || (int)($summary['outcomes']['conflict'] ?? -1) !== 0
        || (int)($summary['outcomes']['error'] ?? -1) !== 0
        || (int)($summary['outcomes']['success'] ?? -1)
            !== (int)($summary['sourceInventory']['selected'] ?? -2)
    ) {
        throw new RuntimeException('current read-only preflight does not exactly match the approved full dry-run plan');
    }
}

/**
 * @param list<array<string, mixed>> $filePlan
 * @param array{rows:list<array<string,mixed>>,config:?array<string,mixed>,scopedSkip:bool} $legacyPlan
 */
function assertRuntimePlanStable(
    array $filePlan,
    array $legacyPlan,
    string $targetRoot,
    string $targetRootIdentity
): void
{
    assertTargetRootIdentity($targetRoot, $targetRootIdentity);
    $currentRows = selectLegacyFileRows('', '', 0);
    if (count($currentRows) !== count($filePlan)) {
        throw new RuntimeException('file database inventory changed after the approved plan');
    }
    $rowDigests = [];
    foreach ($currentRows as $row) {
        $id = (string)($row['ID'] ?? '');
        if ($id === '' || isset($rowDigests[$id])) {
            throw new RuntimeException('file database inventory contains an invalid or duplicate identifier');
        }
        $rowDigests[$id] = canonicalSha256($row);
    }
    foreach ($filePlan as $item) {
        $id = (string)$item['id'];
        if (!isset($rowDigests[$id]) || !hash_equals((string)$item['rowSha256'], $rowDigests[$id])) {
            throw new RuntimeException('file database row changed after the approved plan');
        }
        if (!in_array($item['status'], ['ready', 'existing'], true)) {
            throw new RuntimeException('unresolved file plan reached the apply stability gate');
        }
        $target = (string)$item['targetPath'];
        assertSafeTargetPath($targetRoot, $target);
        $effective = $item['status'] === 'existing' ? $target : (string)$item['sourcePath'];
        if ($item['status'] === 'ready' && file_exists($target)) {
            throw new RuntimeException('file target appeared after the approved plan');
        }
        assertFileDigest($effective, (int)$item['size'], (string)$item['sha256']);
        $source = (string)($item['sourcePath'] ?? '');
        if ($item['status'] === 'existing' && $source !== '' && !sameFile($source, $target)) {
            assertFileDigest($source, (int)$item['size'], (string)$item['sha256']);
        }
    }

    $currentLegacy = [];
    foreach (legacyUrlTargets() as $target) {
        $rows = Db::name($target['table'])
            ->field(['ID', $target['column']])
            ->whereLike($target['column'], '%dev/file/download%')
            ->order('ID', 'asc')
            ->select()
            ->toArray();
        foreach ($rows as $row) {
            $key = $target['table'] . "\0" . $target['column'] . "\0" . (string)($row['ID'] ?? '');
            $currentLegacy[$key] = hash('sha256', (string)($row[$target['column']] ?? ''));
        }
    }
    if (count($currentLegacy) !== count($legacyPlan['rows'])) {
        throw new RuntimeException('legacy URL inventory changed after the approved plan');
    }
    foreach ($legacyPlan['rows'] as $item) {
        $key = $item['table'] . "\0" . $item['column'] . "\0" . $item['id'];
        if (!isset($currentLegacy[$key]) || !hash_equals((string)$item['rawSha256'], $currentLegacy[$key])) {
            throw new RuntimeException('legacy URL source changed after the approved plan');
        }
    }
    $currentConfig = Db::name('dev_config')->where('CONFIG_KEY', 'SNOWY_FILE_LOCAL_FOLDER_FOR_UNIX')->find();
    if ($legacyPlan['config'] === null) {
        if (is_array($currentConfig) && $currentConfig !== []) {
            throw new RuntimeException('legacy file-root configuration appeared after the approved plan');
        }
    } elseif (!is_array($currentConfig)
        || $currentConfig === []
        || !hash_equals((string)$legacyPlan['config']['id'], (string)($currentConfig['ID'] ?? ''))
        || !hash_equals(
            (string)$legacyPlan['config']['rawSha256'],
            hash('sha256', (string)($currentConfig['CONFIG_VALUE'] ?? ''))
        )) {
        throw new RuntimeException('legacy file-root configuration changed after the approved plan');
    }
    assertTargetRootIdentity($targetRoot, $targetRootIdentity);
}

function assertFileDigest(string $path, int $expectedSize, string $expectedSha256): void
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('planned source file is no longer readable');
    }
    $size = filesize($path);
    $sha256 = hash_file('sha256', $path);
    if ($size === false || !is_string($sha256)
        || $size !== $expectedSize
        || !hash_equals($expectedSha256, $sha256)) {
        throw new RuntimeException('planned source file changed after the approved dry-run');
    }
}

/**
 * @param list<array<string, mixed>> $filePlan
 * @param array{rows:list<array<string,mixed>>,config:?array<string,mixed>,scopedSkip:bool} $legacyPlan
 * @param resource $manifest
 * @param array<string, mixed> $summary
 */
function applyMigrationPlan(
    array $filePlan,
    array $legacyPlan,
    string $targetRoot,
    string $targetRootIdentity,
    $manifest,
    array &$summary
): void
{
    foreach ($filePlan as $item) {
        assertTargetRootIdentity($targetRoot, $targetRootIdentity);
        assertSafeTargetPath($targetRoot, (string)$item['targetPath']);
        if ($item['status'] === 'ready') {
            copyVerified(
                (string)$item['sourcePath'],
                (string)$item['targetPath'],
                (int)$item['size'],
                (string)$item['sha256'],
                $targetRoot,
                $targetRootIdentity
            );
            $summary['filesCopied']++;
        }
        applyDatabaseUpdate(Db::name('dev_file')->where('ID', (string)$item['id']), [
            'ENGINE' => 'LOCAL',
            'BUCKET' => firstPathPart((string)$item['relativeKey']),
            'OBJ_NAME' => basename((string)$item['targetPath']),
            'STORAGE_PATH' => (string)$item['targetPath'],
            'DOWNLOAD_PATH' => (string)$item['downloadPath'],
            'SIZE_KB' => (string)(int)round((int)$item['size'] / 1024),
            'SIZE_INFO' => readableSize((int)$item['size']),
            'UPDATE_TIME' => date('Y-m-d H:i:s'),
        ], true, $summary);
        manifest($manifest, [
            'type' => 'file-apply',
            'id' => $item['id'],
            'status' => $item['status'] === 'ready' ? 'copied-and-updated' : 'existing-and-updated',
            'sha256' => $item['sha256'],
        ]);
    }
    foreach ($legacyPlan['rows'] as $item) {
        if ($item['status'] !== 'ready') {
            continue;
        }
        applyDatabaseUpdate(
            Db::name((string)$item['table'])->where('ID', (string)$item['id']),
            [(string)$item['column'] => (string)$item['normalized']],
            true,
            $summary
        );
        $summary['legacyUrlsUpdated']++;
        manifest($manifest, [
            'type' => 'legacy-url-apply',
            'table' => $item['table'],
            'column' => $item['column'],
            'id' => $item['id'],
            'status' => 'updated',
        ]);
    }
    $config = $legacyPlan['config'];
    if (is_array($config) && $config['status'] === 'ready') {
        applyDatabaseUpdate(
            Db::name('dev_config')->where('ID', (string)$config['id']),
            [
                'CONFIG_VALUE' => (string)$config['normalized'],
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
            ],
            true,
            $summary
        );
        $summary['configUpdated'] = true;
        manifest($manifest, [
            'type' => 'legacy-config-apply',
            'status' => 'updated',
        ]);
    }
}

function canonicalSha256(mixed $value): string
{
    return hash('sha256', json_encode(
        canonicalValue($value),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ));
}

function canonicalValue(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    if (!array_is_list($value)) {
        ksort($value);
    }
    foreach ($value as $key => $item) {
        $value[$key] = canonicalValue($item);
    }

    return $value;
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
    $date = strtotime((string)($row['CREATE_TIME'] ?? ''));
    $date = $date === false ? 0 : $date;

    return safeRelativePath($bucket . '/' . date('Y/n/j', $date) . '/' . $objName);
}

/** @param array<string, mixed> $row @return array{path:string,kind:string}|null */
function findSource(
    array $row,
    string $sourceRoot,
    string $sourceDownloadUrl,
    string $sourceCacheRoot,
    string $target,
    string $relativeKey,
    bool $allowRemoteFetch
): ?array
{
    $candidates = [[
        'path' => trim((string)($row['STORAGE_PATH'] ?? '')),
        'kind' => 'record-storage-path',
    ]];
    if ($sourceRoot !== '') {
        $candidates[] = [
            'path' => safeJoin($sourceRoot, $relativeKey),
            'kind' => 'source-root',
        ];
        $sourceBase = basename(str_replace('\\', '/', rtrim($sourceRoot, '/\\')));
        if ($sourceBase === firstPathPart($relativeKey)) {
            $withoutBucket = implode('/', array_slice(explode('/', $relativeKey), 1));
            $candidates[] = [
                'path' => safeJoin($sourceRoot, $withoutBucket),
                'kind' => 'source-root-without-bucket',
            ];
        }
    }
    if ($sourceDownloadUrl !== '') {
        $candidates[] = [
            'path' => safeJoin($sourceCacheRoot, $relativeKey),
            'kind' => 'download-cache',
        ];
    }
    $candidates[] = [
        'path' => $target,
        'kind' => 'target-existing',
    ];

    $seen = [];
    foreach ($candidates as $candidate) {
        $candidatePath = cliPath($candidate['path']);
        $candidateKey = str_replace('\\', '/', $candidatePath);
        if (DIRECTORY_SEPARATOR === '\\') {
            $candidateKey = strtolower($candidateKey);
        }
        if ($candidatePath === '' || isset($seen[$candidateKey])) {
            continue;
        }
        $seen[$candidateKey] = true;
        if (is_file($candidatePath) && is_readable($candidatePath)) {
            return [
                'path' => $candidatePath,
                'kind' => $candidate['kind'],
            ];
        }
    }

    if ($sourceDownloadUrl === '' || !$allowRemoteFetch) {
        return null;
    }

    $id = trim((string)($row['ID'] ?? ''));
    $cachePath = safeJoin($sourceCacheRoot, $relativeKey);
    $fetched = LegacyFileSource::fetchToCache($sourceDownloadUrl, $id, $cachePath, $sourceCacheRoot);
    if ($fetched === null) {
        return null;
    }

    return [
        'path' => $fetched,
        'kind' => 'download-cache',
    ];
}

function copyVerified(
    ?string $source,
    string $target,
    int $expectedSize,
    string $expectedSha256,
    string $targetRoot,
    string $targetRootIdentity
): void
{
    if ($source === null || !is_file($source)) {
        throw new RuntimeException('source file is missing');
    }
    assertTargetRootIdentity($targetRoot, $targetRootIdentity);
    assertSafeTargetPath($targetRoot, $target);
    clearstatcache(true, $target);
    if (@lstat($target) !== false) {
        throw new RuntimeException('target path already exists before verified copy');
    }
    assertFileDigest($source, $expectedSize, $expectedSha256);
    $directory = dirname($target);
    ensureSafeTargetDirectory($targetRoot, $directory, $targetRootIdentity);

    $temporary = $target . '.migrate-' . getmypid() . '-' . bin2hex(random_bytes(6));
    assertSafeTargetPath($targetRoot, $temporary);
    assertTargetRootIdentity($targetRoot, $targetRootIdentity);
    $sourceHandle = fopen($source, 'rb');
    $targetHandle = @fopen($temporary, 'x+b');
    if ($sourceHandle === false || $targetHandle === false) {
        if (is_resource($sourceHandle)) {
            fclose($sourceHandle);
        }
        if (is_resource($targetHandle)) {
            fclose($targetHandle);
        }
        @unlink($temporary);
        throw new RuntimeException('cannot create verified temporary target file');
    }
    $copied = stream_copy_to_stream($sourceHandle, $targetHandle);
    $durable = $copied !== false
        && fflush($targetHandle)
        && function_exists('fsync')
        && fsync($targetHandle);
    fclose($sourceHandle);
    fclose($targetHandle);
    if (!$durable || $copied !== $expectedSize) {
        @unlink($temporary);
        throw new RuntimeException('cannot copy complete source file');
    }
    try {
        assertFileDigest($temporary, $expectedSize, $expectedSha256);
    } catch (Throwable $exception) {
        @unlink($temporary);
        throw $exception;
    }
    assertTargetRootIdentity($targetRoot, $targetRootIdentity);
    assertSafeTargetPath($targetRoot, $target);
    clearstatcache(true, $target);
    if (@lstat($target) !== false) {
        @unlink($temporary);
        throw new RuntimeException('target path appeared before atomic no-clobber finalization');
    }
    if (!@link($temporary, $target)) {
        @unlink($temporary);
        throw new RuntimeException('cannot finalize target file without overwriting an existing path');
    }
    if (!@unlink($temporary)) {
        throw new RuntimeException('target file was created but temporary hard link cleanup failed');
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
    $outcome = match ($status) {
        'missing' => 'missing',
        'conflict' => 'conflict',
        'error' => 'error',
        default => 'success',
    };
    $summary['outcomes'][$outcome] = (int)($summary['outcomes'][$outcome] ?? 0) + 1;
}

/** @param array<string, mixed> $summary */
function recordSourceStatus(array &$summary, string $kind): void
{
    $summary['sourceInventory']['byKind'][$kind] =
        (int)($summary['sourceInventory']['byKind'][$kind] ?? 0) + 1;
}

/** @param array<string, mixed> $summary */
function recordMigrationError(array &$summary, bool $includeOutcome = true): void
{
    $summary['errors'] = (int)($summary['errors'] ?? 0) + 1;
    if ($includeOutcome) {
        $summary['outcomes']['error'] = (int)($summary['outcomes']['error'] ?? 0) + 1;
    }
}

/** @param array<string, mixed> $summary */
function recordJsonStatus(array &$summary, string $status): void
{
    $summary['json'][$status] = ($summary['json'][$status] ?? 0) + 1;
}

/** @param resource $handle @param array<string, mixed> $record */
function manifest($handle, array $record): void
{
    $line = json_encode(
        $record,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
    $written = fwrite($handle, $line);
    if ($written === false
        || $written !== strlen($line)
        || !fflush($handle)
        || !function_exists('fsync')
        || !fsync($handle)) {
        throw new RuntimeException('cannot write complete migration manifest record');
    }
}

/** @param resource $handle @param array<string, mixed> $summary */
function writeManifestCompletion($handle, array $summary): void
{
    manifest($handle, [
        'type' => 'summary',
        'time' => date(DATE_ATOM),
        'summary' => $summary,
    ]);
    manifest($handle, [
        'type' => 'completion',
        'time' => $summary['completion']['time'] ?? date(DATE_ATOM),
        'mode' => $summary['mode'] ?? null,
        'scoped' => (bool)($summary['scoped'] ?? true),
        'status' => $summary['completion']['status'] ?? 'failed',
        'completed' => (bool)($summary['completion']['completed'] ?? false),
        'targetRoot' => $summary['targetRoot'] ?? null,
        'planVersion' => $summary['planVersion'] ?? null,
        'planSha256' => $summary['planSha256'] ?? null,
        'migrationCodeSha256' => $summary['migrationCodeSha256'] ?? null,
        'databaseMigrationEvidenceSha256' => $summary['databaseMigrationEvidenceSha256'] ?? null,
        'databaseSchemaSha256' => $summary['databaseSchemaSha256'] ?? null,
        'approvedDryRunManifestSha256' => $summary['approvedDryRunManifestSha256'] ?? null,
        'sourceInventory' => $summary['sourceInventory'] ?? [],
        'outcomes' => $summary['outcomes'] ?? [],
        'errors' => (int)($summary['errors'] ?? 0),
        'databaseWriteStatements' => (int)($summary['databaseWriteStatements'] ?? 0),
        'databaseRowsAffected' => (int)($summary['databaseRowsAffected'] ?? 0),
        'filesCopied' => (int)($summary['filesCopied'] ?? 0),
        'legacyUrlsUpdated' => (int)($summary['legacyUrlsUpdated'] ?? 0),
        'configUpdated' => (bool)($summary['configUpdated'] ?? false),
    ]);
}

function safeMigrationError(Throwable $exception, string $database = ''): string
{
    $type = strtolower(get_debug_type($exception));
    if (str_contains($type, 'pdoexception') || str_contains($type, 'db\\exception')) {
        return 'database operation failed without exposing connection details';
    }

    $message = trim($exception->getMessage());
    if ($database !== '') {
        $message = str_replace($database, '<database>', $message);
    }
    $message = preg_replace('/\b[A-Za-z0-9_]+_migrated\b/i', '<database>', $message) ?? $message;
    $message = preg_replace('#://[^/@\s]+@#', '://<redacted>@', $message) ?? $message;
    $message = preg_replace(
        '/\b(password|passwd|pwd|token)\s*[=:]\s*[^\s&,;]+/i',
        '$1=<redacted>',
        $message
    ) ?? $message;
    $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message) ?? $message;
    $message = trim($message);
    if ($message === '') {
        return 'migration failed without a safe diagnostic message';
    }

    return strlen($message) > 500 ? substr($message, 0, 500) : $message;
}
