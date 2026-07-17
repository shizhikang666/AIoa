#!/usr/bin/env php
<?php

declare(strict_types=1);

use Oa\DatabaseMigration\MigrationOptions;
use Oa\DatabaseMigration\MigrationRunner;

require __DIR__ . '/lib/oa-database-migration.php';

$help = in_array('--help', $argv, true) || in_array('-h', $argv, true);
if ($help) {
    fwrite(STDOUT, <<<'HELP'
Safely rehearse or apply the Java-to-ThinkPHP database migration.

Default mode is read-only dry-run. Apply creates only a new *_migrated target
and a new *_quarantine_YYYYMMDD schema; it never modifies the source schema.

Required options:
  --source-defaults=PATH       Read-only source MySQL [client] option file
  --source-db=NAME             Frozen Java source database
  --target-defaults=PATH       Target-server MySQL [client] option file
  --template-db=NAME           Current PHP schema template (read only)
  --target-db=NAME_migrated    New migration target; must not already exist
  --quarantine-db=NAME_quarantine_YYYYMMDD
  --allow-target=NAME_migrated Explicit target whitelist entry (repeatable)

Review options:
  --known-orphans=PATH         Reviewed JSON containing exactly 20 taskIds
  --workflow-converter=PATH    External converter; this tool does not decode Java objects
  --allow-remote-workflow-converter
                               Extra gate when converter DB host is non-loopback
  --manifest-dir=PATH          Audit output (default: runtime/backup/...)
  --mysql-bin=PATH             mysql client (default: mysql)
  --mysqldump-bin=PATH         mysqldump client (default: mysqldump)
  --php-bin=PATH               PHP CLI for converter/installers

Apply-only gates:
  --apply
  --confirm-token=TOKEN        Exact token printed by the matching dry-run
  --source-freeze-token=JAVA_STOPPED_AND_SOURCE_FROZEN

Audited baselines are fixed in the reviewed tool: 121/1,836 source structure,
124/1,882 template structure, 20 allowlisted orphans, and 2 assignee repairs.
HELP
    );
    exit(0);
}

try {
    $root = dirname(__DIR__);
    chdir($root);
    $options = MigrationOptions::fromArgv($argv);
    $summary = (new MigrationRunner($root))->run($options);
    fwrite(STDOUT, json_encode(
        $summary,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode([
        'status' => 'failed',
        'message' => $exception->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}
