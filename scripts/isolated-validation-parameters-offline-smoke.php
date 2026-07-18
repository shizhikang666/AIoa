#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/lib/isolated-validation-parameters.php';
require __DIR__ . '/prepare-r10-isolated-validation.php';

use function Oa\IsolatedValidationParameters\approvalComment;
use function Oa\IsolatedValidationParameters\databaseIdentifier;
use function Oa\IsolatedValidationParameters\environmentConfiguration;
use function Oa\IsolatedValidationParameters\expectedCount;
use function Oa\IsolatedValidationParameters\legacyPosthocMode;
use function Oa\IsolatedValidationParameters\loopbackHost;
use function Oa\IsolatedValidationParameters\runDate;
use function Oa\IsolatedValidationParameters\runLabel;

function parameter_smoke_expect_failure(callable $callback): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException('expected isolated validation parameter failure was not raised');
}

databaseIdentifier('fixture_canonical', 'canonical');
runLabel('formal-fixture');
runDate('20300102');
expectedCount('8', 'expected table count', false);
expectedCount('0', 'expected foreign key count', true);
loopbackHost('127.0.0.1');
loopbackHost('localhost');
approvalComment('formal isolated approval fixture');

parameter_smoke_expect_failure(static fn () => databaseIdentifier('mysql', 'canonical'));
parameter_smoke_expect_failure(static fn () => databaseIdentifier('fixture-name', 'canonical'));
parameter_smoke_expect_failure(static fn () => runLabel('R'));
parameter_smoke_expect_failure(static fn () => runDate('20300230'));
parameter_smoke_expect_failure(static fn () => expectedCount('0', 'expected table count', false));
parameter_smoke_expect_failure(static fn () => loopbackHost('192.0.2.1'));
parameter_smoke_expect_failure(static fn () => approvalComment('short'));

$prepare = prepareParseOptions([
    'prepare',
    '--canonical-db=fixture_canonical',
    '--confirm-canonical-db=fixture_canonical',
    '--target-db=fixture_target',
    '--database-host=127.0.0.1',
    '--run-label=formal-fixture',
    '--run-date=20300102',
    '--expected-table-count=8',
    '--expected-foreign-key-count=2',
    '--manifest-dir=runtime/fixture-manifest',
    '--pointer-path=runtime/fixture-pointer.json',
    '--target-final-marker=runtime/fixture-target-final.json',
]);
if (count($prepare) !== 11) {
    throw new RuntimeException('prepare option parser lost an explicit option');
}
parameter_smoke_expect_failure(static fn () => prepareParseOptions([
    'prepare',
    '--canonical-db=fixture_canonical',
    '--confirm-canonical-db=wrong_fixture',
    '--target-db=fixture_target',
    '--database-host=127.0.0.1',
    '--run-label=formal-fixture',
    '--run-date=20300102',
    '--expected-table-count=8',
    '--expected-foreign-key-count=2',
    '--manifest-dir=runtime/fixture-manifest',
    '--pointer-path=runtime/fixture-pointer.json',
    '--target-final-marker=runtime/fixture-target-final.json',
]));

$environmentNames = [
    'OA_ISOLATED_CANONICAL_DB',
    'OA_ISOLATED_DB_NAME',
    'OA_ISOLATED_DB_HOST',
    'OA_ISOLATED_RUN_LABEL',
    'OA_ISOLATED_RUN_DATE',
    'OA_ISOLATED_EXPECTED_TABLE_COUNT',
    'OA_ISOLATED_EXPECTED_FOREIGN_KEY_COUNT',
    'OA_ISOLATED_LEGACY_POSTHOC',
];
$previous = [];
foreach ($environmentNames as $name) {
    $previous[$name] = getenv($name);
}
try {
    putenv('OA_ISOLATED_CANONICAL_DB=fixture_canonical');
    putenv('OA_ISOLATED_DB_NAME=fixture_target');
    putenv('OA_ISOLATED_DB_HOST=localhost');
    putenv('OA_ISOLATED_RUN_LABEL=formal-fixture');
    putenv('OA_ISOLATED_RUN_DATE=20300102');
    putenv('OA_ISOLATED_EXPECTED_TABLE_COUNT=8');
    putenv('OA_ISOLATED_EXPECTED_FOREIGN_KEY_COUNT=2');
    putenv('OA_ISOLATED_LEGACY_POSTHOC=0');
    $configuration = environmentConfiguration();
    if ($configuration['canonicalDatabase'] !== 'fixture_canonical'
        || $configuration['targetDatabase'] !== 'fixture_target'
        || $configuration['databaseHost'] !== '127.0.0.1'
        || $configuration['runLabel'] !== 'formal-fixture'
        || $configuration['runDate'] !== '20300102'
        || $configuration['expectedTableCount'] !== 8
        || $configuration['expectedForeignKeyCount'] !== 2
        || legacyPosthocMode()
    ) {
        throw new RuntimeException('environment configuration normalization changed');
    }
    putenv('OA_ISOLATED_CANONICAL_DB');
    parameter_smoke_expect_failure(static fn () => environmentConfiguration());
    putenv('OA_ISOLATED_CANONICAL_DB=fixture_canonical');
    putenv('OA_ISOLATED_DB_NAME=fixture_canonical');
    parameter_smoke_expect_failure(static fn () => environmentConfiguration());
    putenv('OA_ISOLATED_DB_NAME=fixture_target');
    putenv('OA_ISOLATED_LEGACY_POSTHOC=1');
    if (!legacyPosthocMode()) {
        throw new RuntimeException('explicit historical posthoc compatibility flag was ignored');
    }
    putenv('OA_ISOLATED_LEGACY_POSTHOC=unexpected');
    parameter_smoke_expect_failure(static fn () => legacyPosthocMode());
} finally {
    foreach ($previous as $name => $value) {
        if ($value === false) {
            putenv($name);
        } else {
            putenv($name . '=' . $value);
        }
    }
}

echo "isolated validation parameter offline smoke passed\n";
