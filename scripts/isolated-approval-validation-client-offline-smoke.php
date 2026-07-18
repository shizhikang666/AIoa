#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/isolated-approval-validation-client.php';

function clientSmokeExpectFailure(callable $callback): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException('expected isolated client policy failure was not raised');
}

if (!jsonContainsIdentifier([
    'records' => [['taskId' => 'task-exact']],
], 'task-exact')) {
    throw new RuntimeException('exact task identifier was not found');
}
if (jsonContainsIdentifier([
    'records' => [['taskId' => 'prefix-task-exact-suffix']],
], 'task-exact')) {
    throw new RuntimeException('task identifier substring was accepted');
}
if (jsonContainsIdentifier([
    'records' => [['description' => 'task-exact']],
], 'task-exact')) {
    throw new RuntimeException('task identifier in an unrelated field was accepted');
}

$before = [
    'a' => ['rowCount' => 1, 'checksum' => '1'],
    'b' => ['rowCount' => 2, 'checksum' => '2'],
];
$after = $before;
$after['b']['checksum'] = '3';
if (changedFingerprintTables($before, $after) !== ['b']) {
    throw new RuntimeException('changed fingerprint table detection failed');
}
clientSmokeExpectFailure(static fn () => changedFingerprintTables($before, ['a' => $before['a']]));
clientSmokeExpectFailure(static fn () => requireApiSuccess(['code' => 500]));
requireApiSuccess(['code' => 200]);

$previousMarkerPath = getenv('OA_ISOLATED_MUTATION_MARKER_PATH');
$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oa-isolated-marker-' . bin2hex(random_bytes(8));
$temporaryManifest = $temporaryRoot . DIRECTORY_SEPARATOR . 'manifest';
$temporaryRuntime = $temporaryManifest . DIRECTORY_SEPARATOR . 'server-runtime';
$temporaryMarker = $temporaryManifest . DIRECTORY_SEPARATOR . 'validation-mutation-started.json';
if (!mkdir($temporaryRuntime, 0700, true) && !is_dir($temporaryRuntime)) {
    throw new RuntimeException('temporary marker smoke directory could not be created');
}
try {
    putenv('OA_ISOLATED_MUTATION_MARKER_PATH=' . $temporaryMarker);
    writeMutationStartedMarker($temporaryRuntime);
    $marker = json_decode((string) file_get_contents($temporaryMarker), true, 512, JSON_THROW_ON_ERROR);
    if (($marker['status'] ?? null) !== 'mutation-started'
        || ($marker['targetMustNotBeReusedIfInterrupted'] ?? null) !== true
        || ($marker['readonlyPreflightPassed'] ?? null) !== true
        || ($marker['serverIdentityVerified'] ?? null) !== true
    ) {
        throw new RuntimeException('mutation marker evidence is incomplete');
    }
    clientSmokeExpectFailure(static fn () => writeMutationStartedMarker($temporaryRuntime));
} finally {
    if ($previousMarkerPath === false) {
        putenv('OA_ISOLATED_MUTATION_MARKER_PATH');
    } else {
        putenv('OA_ISOLATED_MUTATION_MARKER_PATH=' . $previousMarkerPath);
    }
    @unlink($temporaryMarker);
    @rmdir($temporaryRuntime);
    @rmdir($temporaryManifest);
    @rmdir($temporaryRoot);
}

echo "isolated approval validation client offline smoke passed\n";
