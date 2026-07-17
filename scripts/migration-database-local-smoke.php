#!/usr/bin/env php
<?php

declare(strict_types=1);

use Oa\DatabaseMigration\AssigneeRepair;
use Oa\DatabaseMigration\OrphanPolicy;
use Oa\DatabaseMigration\QuarantineManager;

require __DIR__ . '/lib/oa-database-migration.php';
require __DIR__ . '/lib/installer-target.php';

/** @return array<string, string> */
function local_smoke_options(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--') || !str_contains($argument, '=')) {
            throw new RuntimeException('local smoke options must use --name=value');
        }
        [$name, $value] = explode('=', substr($argument, 2), 2);
        $options[$name] = $value;
    }

    return $options;
}

/** @return array<string, string> */
function local_smoke_env(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('local smoke .env is not readable');
    }
    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (!preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$/', $line, $match)) {
            continue;
        }
        $value = trim($match[2]);
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"'))
            || ($value[0] === "'" && str_ends_with($value, "'")))
        ) {
            $value = substr($value, 1, -1);
        }
        $values[$match[1]] = $value;
    }

    return $values;
}

function local_smoke_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function local_smoke_throws(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
}

$options = local_smoke_options($argv);
$env = local_smoke_env($options['env'] ?? dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
$host = trim($options['host'] ?? $env['DB_HOST'] ?? '');
if (!in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('temporary database smoke refuses every non-loopback MySQL host');
}
$port = trim($options['port'] ?? $env['DB_PORT'] ?? '3306');
$user = trim($env['DB_USER'] ?? '');
$password = (string)($env['DB_PASS'] ?? '');
if ($user === '' || !preg_match('/^\d+$/', $port)) {
    throw new RuntimeException('local smoke database settings are incomplete');
}

$pdo = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => true,
    ]
);
$suffix = strtolower(bin2hex(random_bytes(5)));
$targetDatabase = "oa_migration_smoke_{$suffix}_migrated";
$quarantineDatabase = "oa_migration_smoke_{$suffix}_quarantine_" . gmdate('Ymd');
$runId = 'local-smoke-' . $suffix;

try {
    $pdo->exec("CREATE DATABASE `{$targetDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("CREATE DATABASE `{$quarantineDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $db = "`{$targetDatabase}`";
    $definitions = [
        'act_re_deployment' => 'ID_ varchar(64) NOT NULL, PRIMARY KEY (ID_)',
        'act_re_procdef' => 'ID_ varchar(64) NOT NULL, KEY_ varchar(64) NOT NULL, DEPLOYMENT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_ru_task' => 'ID_ varchar(64) NOT NULL, PROC_INST_ID_ varchar(64), EXECUTION_ID_ varchar(64), PROC_DEF_ID_ varchar(64), TENANT_ID_ varchar(64), TASK_DEF_KEY_ varchar(64), ASSIGNEE_ varchar(128), PRIMARY KEY (ID_)',
        'act_ru_execution' => 'ID_ varchar(64) NOT NULL, PROC_INST_ID_ varchar(64), ROOT_PROC_INST_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_ru_identitylink' => 'ID_ varchar(64) NOT NULL, TASK_ID_ varchar(64), PROC_INST_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_ru_variable' => 'ID_ varchar(64) NOT NULL, PROC_INST_ID_ varchar(64), EXECUTION_ID_ varchar(64), TASK_ID_ varchar(64), NAME_ varchar(64), TYPE_ varchar(64), BYTEARRAY_ID_ varchar(64), TEXT_ varchar(4000), TENANT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_hi_procinst' => 'ID_ varchar(64) NOT NULL, PROC_INST_ID_ varchar(64) NOT NULL, PRIMARY KEY (ID_), UNIQUE KEY uk_hi_proc (PROC_INST_ID_)',
        'act_hi_taskinst' => 'ID_ varchar(64) NOT NULL, PROC_INST_ID_ varchar(64), EXECUTION_ID_ varchar(64), ASSIGNEE_ varchar(128), PRIMARY KEY (ID_)',
        'act_ge_bytearray' => 'ID_ varchar(64) NOT NULL, ROOT_PROC_INST_ID_ varchar(64), DEPLOYMENT_ID_ varchar(64), BYTES_ longblob, PRIMARY KEY (ID_)',
        'sys_user' => 'ID varchar(64) NOT NULL, TENANT_ID varchar(64) NOT NULL, DELETE_FLAG varchar(20), PRIMARY KEY (ID)',
    ];
    foreach ($definitions as $table => $definition) {
        $pdo->exec("CREATE TABLE {$db}.`{$table}` ({$definition}) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    $pdo->exec("INSERT INTO {$db}.act_re_deployment VALUES ('dep-orphan'), ('dep-procure')");
    $pdo->exec("INSERT INTO {$db}.act_re_procdef VALUES ('def-orphan','Legacy_orphan','dep-orphan'), ('def-procure','Process_procure','dep-procure')");
    $pdo->exec("INSERT INTO {$db}.act_ru_execution VALUES ('exec-orphan','proc-orphan','proc-orphan')");
    $pdo->exec("INSERT INTO {$db}.act_ru_task VALUES ('task-orphan','proc-orphan','exec-orphan','def-orphan','tenant-a','Activity_old','legacy-user')");
    $pdo->exec("INSERT INTO {$db}.act_ru_identitylink VALUES ('link-orphan','task-orphan','proc-orphan')");
    $pdo->exec("INSERT INTO {$db}.act_ge_bytearray VALUES ('bytes-root','proc-orphan',NULL,'raw-root'), ('bytes-deploy',NULL,'dep-orphan','raw-deployment')");

    for ($index = 1; $index <= 2; $index++) {
        $pdo->exec("INSERT INTO {$db}.act_hi_procinst VALUES ('hi-proc-{$index}','proc-valid-{$index}')");
        $pdo->exec("INSERT INTO {$db}.act_ru_task VALUES ('task-valid-{$index}','proc-valid-{$index}',NULL,'def-procure','tenant-a','Activity_approval_procure',NULL)");
        $pdo->exec("INSERT INTO {$db}.act_hi_taskinst VALUES ('task-valid-{$index}','proc-valid-{$index}',NULL,NULL)");
        $pdo->exec("INSERT INTO {$db}.act_ru_variable VALUES ('var-user-{$index}','proc-valid-{$index}',NULL,NULL,'user','string',NULL,'user-{$index}','tenant-a')");
        $pdo->exec("INSERT INTO {$db}.sys_user VALUES ('user-{$index}','tenant-a','NOT_DELETE')");
    }

    $orphans = OrphanPolicy::detect($pdo, $targetDatabase);
    local_smoke_assert(count($orphans) === 1 && $orphans[0]['taskId'] === 'task-orphan', 'local orphan detection failed');
    $eligibility = OrphanPolicy::assertIsolationEligible($pdo, $targetDatabase, $orphans);
    local_smoke_assert($eligibility['rootBytearrayRowsPreservedForQuarantine'] === 1, 'orphan eligibility byte-array audit failed');
    $quarantine = (new QuarantineManager(
        $pdo,
        $targetDatabase,
        $quarantineDatabase,
        $runId
    ))->quarantine($orphans);
    local_smoke_assert($quarantine['taskCount'] === 1, 'local quarantine task count failed');
    local_smoke_assert((int)$pdo->query("SELECT COUNT(*) FROM {$db}.act_ru_task WHERE ID_='task-orphan'")->fetchColumn() === 0, 'orphan remained active');
    local_smoke_assert((int)$pdo->query("SELECT COUNT(*) FROM `{$quarantineDatabase}`.act_ge_bytearray")->fetchColumn() === 2, 'raw byte arrays were not preserved');
    local_smoke_assert((int)$pdo->query("SELECT COUNT(*) FROM {$db}.act_ge_bytearray WHERE ID_='bytes-deploy'")->fetchColumn() === 1, 'shared deployment byte array was deleted');

    $repair = AssigneeRepair::apply($pdo, $targetDatabase, $quarantineDatabase, $runId, 2);
    local_smoke_assert($repair['repairCount'] === 2, 'strict assignee repair count failed');
    local_smoke_assert((int)$pdo->query("SELECT COUNT(*) FROM {$db}.act_ru_task WHERE ASSIGNEE_ IS NULL OR ASSIGNEE_='' ")->fetchColumn() === 0, 'blank assignees remain');

    $pdo->exec("INSERT INTO {$db}.act_hi_procinst VALUES ('hi-proc-ambiguous','proc-ambiguous')");
    $pdo->exec("INSERT INTO {$db}.act_ru_task VALUES ('task-ambiguous','proc-ambiguous',NULL,'def-procure','tenant-a','Activity_approval_procure',NULL)");
    $pdo->exec("INSERT INTO {$db}.act_hi_taskinst VALUES ('task-ambiguous','proc-ambiguous',NULL,NULL)");
    $pdo->exec("INSERT INTO {$db}.sys_user VALUES ('user-ambiguous','tenant-a','NOT_DELETE')");
    $pdo->exec("INSERT INTO {$db}.act_ru_variable VALUES ('var-ambiguous-a','proc-ambiguous',NULL,NULL,'user','string',NULL,'user-ambiguous','tenant-a')");
    $pdo->exec("INSERT INTO {$db}.act_ru_variable VALUES ('var-ambiguous-b','proc-ambiguous',NULL,NULL,'user','string',NULL,'user-ambiguous','tenant-a')");
    local_smoke_throws(
        static fn () => AssigneeRepair::apply($pdo, $targetDatabase, $quarantineDatabase, $runId, 1),
        'duplicate same-tenant user variables were accepted for assignee repair'
    );
    local_smoke_assert(
        (string)$pdo->query("SELECT COALESCE(ASSIGNEE_,'') FROM {$db}.act_ru_task WHERE ID_='task-ambiguous'")->fetchColumn() === '',
        'rejected ambiguous repair changed the task assignee'
    );

    require dirname(__DIR__) . '/vendor/autoload.php';
    $app = new think\App(dirname(__DIR__));
    $app->initialize();
    installer_target_configure([
        'hostname' => $host,
        'hostport' => $port,
        'username' => $user,
        'password' => $password,
        'database' => $targetDatabase,
        'charset' => 'utf8mb4',
    ]);
    local_smoke_assert(
        (string)think\facade\Db::query('SELECT DATABASE() AS DB_NAME')[0]['DB_NAME'] === $targetDatabase,
        'installer target connection was not pinned to the temporary database'
    );

    $summary = [
        'status' => 'passed',
        'hostPolicy' => 'loopback-only',
        'temporarySchemasRemoved' => true,
        'quarantinedTasks' => 1,
        'assigneesRepaired' => 2,
        'ambiguousAssigneeRepairRejected' => true,
    ];
} finally {
    foreach ([$quarantineDatabase, $targetDatabase] as $database) {
        if (!preg_match('/^oa_migration_smoke_[a-f0-9]{10}_(?:migrated|quarantine_\d{8})$/', $database)) {
            throw new RuntimeException('temporary schema cleanup guard rejected an unexpected name');
        }
        $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
    }
}

fwrite(STDOUT, json_encode(
    $summary,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
) . PHP_EOL);
