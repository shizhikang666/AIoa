#!/usr/bin/env php
<?php

declare(strict_types=1);

use Oa\DatabaseMigration\AssigneeRepair;
use Oa\DatabaseMigration\DetachedBytearrayPolicy;
use Oa\DatabaseMigration\DetachedOperationLogPolicy;
use Oa\DatabaseMigration\OrphanPolicy;
use Oa\DatabaseMigration\QuarantineManager;
use Oa\DatabaseMigration\WorkflowTenantRepair;
use Oa\DatabaseMigration\WorkflowVariableGate;

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

function local_smoke_throws_matching(callable $callback, string $expectedMessage, string $message): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if (str_contains($exception->getMessage(), $expectedMessage)) {
            return;
        }
        throw new RuntimeException($message . ': unexpected exception', 0, $exception);
    }

    throw new RuntimeException($message);
}

$options = local_smoke_options($argv);
$env = local_smoke_env($options['env'] ?? dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
$host = strtolower(trim($options['host'] ?? $env['DB_HOST'] ?? ''));
$normalizedHost = $host === '[::1]' ? '::1' : $host;
if (!in_array($normalizedHost, ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('temporary database smoke refuses every non-loopback MySQL host');
}
$dsnHost = $normalizedHost === '::1' ? '[::1]' : $normalizedHost;
$port = trim($options['port'] ?? $env['DB_PORT'] ?? '3306');
$user = trim($env['DB_USER'] ?? '');
$password = (string)($env['DB_PASS'] ?? '');
if ($user === '' || !preg_match('/^\d+$/', $port)) {
    throw new RuntimeException('local smoke database settings are incomplete');
}

$pdo = new PDO(
    "mysql:host={$dsnHost};port={$port};charset=utf8mb4",
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
$frameworkConnection = null;

try {
    $pdo->exec("CREATE DATABASE `{$targetDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("CREATE DATABASE `{$quarantineDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $db = "`{$targetDatabase}`";
    $definitions = [
        'tenants' => 'Tenant_ID varchar(64) NOT NULL, DELETE_FLAG varchar(20), PRIMARY KEY (Tenant_ID)',
        'act_re_deployment' => 'ID_ varchar(64) NOT NULL, PRIMARY KEY (ID_)',
        'act_re_procdef' => 'ID_ varchar(64) NOT NULL, KEY_ varchar(64) NOT NULL, DEPLOYMENT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_ru_task' => 'ID_ varchar(64) NOT NULL, PROC_INST_ID_ varchar(64), EXECUTION_ID_ varchar(64), PROC_DEF_ID_ varchar(64), TENANT_ID_ varchar(64), TASK_DEF_KEY_ varchar(64), ASSIGNEE_ varchar(128), PRIMARY KEY (ID_)',
        'act_ru_execution' => 'ID_ varchar(64) NOT NULL, PROC_INST_ID_ varchar(64), ROOT_PROC_INST_ID_ varchar(64), TENANT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_ru_identitylink' => 'ID_ varchar(64) NOT NULL, TASK_ID_ varchar(64), PROC_INST_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_ru_variable' => 'ID_ varchar(64) NOT NULL, PROC_INST_ID_ varchar(64), EXECUTION_ID_ varchar(64), TASK_ID_ varchar(64), NAME_ varchar(64), TYPE_ varchar(64), BYTEARRAY_ID_ varchar(64), TEXT_ varchar(4000), TENANT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_hi_procinst' => 'ID_ varchar(64) NOT NULL, PROC_INST_ID_ varchar(64) NOT NULL, START_USER_ID_ varchar(64), TENANT_ID_ varchar(64), PRIMARY KEY (ID_), UNIQUE KEY uk_hi_proc (PROC_INST_ID_)',
        'act_hi_taskinst' => 'ID_ varchar(64) NOT NULL, PROC_INST_ID_ varchar(64), EXECUTION_ID_ varchar(64), ASSIGNEE_ varchar(128), TENANT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_hi_actinst' => 'ID_ varchar(64) NOT NULL, PROC_INST_ID_ varchar(64), CALL_PROC_INST_ID_ varchar(64), TENANT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_hi_varinst' => 'ID_ varchar(64) NOT NULL, ROOT_PROC_INST_ID_ varchar(64), PROC_INST_ID_ varchar(64), TASK_ID_ varchar(64), NAME_ varchar(64), VAR_TYPE_ varchar(64), BYTEARRAY_ID_ varchar(64), TEXT_ varchar(4000), TENANT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_hi_detail' => 'ID_ varchar(64) NOT NULL, ROOT_PROC_INST_ID_ varchar(64), PROC_INST_ID_ varchar(64), TASK_ID_ varchar(64), BYTEARRAY_ID_ varchar(64), TENANT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_hi_dec_in' => 'ID_ varchar(64) NOT NULL, BYTEARRAY_ID_ varchar(64), ROOT_PROC_INST_ID_ varchar(64), TENANT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_hi_dec_out' => 'ID_ varchar(64) NOT NULL, BYTEARRAY_ID_ varchar(64), ROOT_PROC_INST_ID_ varchar(64), TENANT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_hi_attachment' => 'ID_ varchar(64) NOT NULL, TASK_ID_ varchar(64), PROC_INST_ID_ varchar(64), CONTENT_ID_ varchar(64), TENANT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_hi_ext_task_log' => 'ID_ varchar(64) NOT NULL, PROC_INST_ID_ varchar(64), ERROR_DETAILS_ID_ varchar(64), TENANT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_hi_job_log' => 'ID_ varchar(64) NOT NULL, PROCESS_INSTANCE_ID_ varchar(64), JOB_EXCEPTION_STACK_ID_ varchar(64), TENANT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_hi_identitylink' => 'ID_ varchar(64) NOT NULL, ROOT_PROC_INST_ID_ varchar(64), TENANT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_hi_op_log' => 'ID_ varchar(64) NOT NULL, DEPLOYMENT_ID_ varchar(64), PROC_DEF_ID_ varchar(64), '
            . 'PROC_DEF_KEY_ varchar(64), ROOT_PROC_INST_ID_ varchar(64), PROC_INST_ID_ varchar(64), '
            . 'EXECUTION_ID_ varchar(64), CASE_DEF_ID_ varchar(64), CASE_INST_ID_ varchar(64), '
            . 'CASE_EXECUTION_ID_ varchar(64), TASK_ID_ varchar(64), JOB_ID_ varchar(64), '
            . 'JOB_DEF_ID_ varchar(64), BATCH_ID_ varchar(64), USER_ID_ varchar(64), TIMESTAMP_ datetime, '
            . 'OPERATION_TYPE_ varchar(64), OPERATION_ID_ varchar(64), ENTITY_TYPE_ varchar(64), '
            . 'PROPERTY_ varchar(255), ORG_VALUE_ text, NEW_VALUE_ text, TENANT_ID_ varchar(64), '
            . 'REMOVAL_TIME_ datetime, CATEGORY_ varchar(64), EXTERNAL_TASK_ID_ varchar(64), '
            . 'ANNOTATION_ text, PRIMARY KEY (ID_)',
        'act_ru_ext_task' => 'ID_ varchar(64) NOT NULL, PROC_INST_ID_ varchar(64), EXECUTION_ID_ varchar(64), ERROR_DETAILS_ID_ varchar(64), TENANT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_ru_job' => 'ID_ varchar(64) NOT NULL, PROCESS_INSTANCE_ID_ varchar(64), EXECUTION_ID_ varchar(64), EXCEPTION_STACK_ID_ varchar(64), TENANT_ID_ varchar(64), PRIMARY KEY (ID_)',
        'act_ge_bytearray' => 'ID_ varchar(64) NOT NULL, REV_ int, NAME_ varchar(255), DEPLOYMENT_ID_ varchar(64), BYTES_ longblob, GENERATED_ tinyint, TENANT_ID_ varchar(64), TYPE_ int, CREATE_TIME_ datetime, ROOT_PROC_INST_ID_ varchar(64), REMOVAL_TIME_ datetime, PRIMARY KEY (ID_), KEY ACT_IDX_BYTEARRAY_ROOT_PI (ROOT_PROC_INST_ID_)',
        'sys_user' => 'ID varchar(64) NOT NULL, TENANT_ID varchar(64) NOT NULL, DELETE_FLAG varchar(20), PRIMARY KEY (ID)',
        'biz_sale_project_reissue_order' => 'ID varchar(64) NOT NULL, PROCESS_ID varchar(64) NOT NULL, TENANT_ID varchar(64) NOT NULL, PRIMARY KEY (ID)',
    ];
    foreach ($definitions as $table => $definition) {
        $pdo->exec(
            "CREATE TABLE {$db}.`{$table}` ({$definition}) "
            . 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    }
    $workflowBytes = "\x00\xfflocal-smoke-workflow";
    $byteInsert = $pdo->prepare("INSERT INTO {$db}.act_ge_bytearray (ID_, ROOT_PROC_INST_ID_, DEPLOYMENT_ID_, BYTES_, TENANT_ID_) VALUES ('bytes-workflow', NULL, NULL, ?, NULL)");
    $byteInsert->bindValue(1, $workflowBytes, PDO::PARAM_LOB);
    $byteInsert->execute();
    $pdo->exec(
        "INSERT INTO {$db}.act_ru_variable "
        . "(ID_, PROC_INST_ID_, EXECUTION_ID_, TASK_ID_, NAME_, TYPE_, BYTEARRAY_ID_, TEXT_, TENANT_ID_) "
        . "VALUES ('var-workflow','proc-workflow',NULL,NULL,'payload','serializable','bytes-workflow',NULL,'tenant-a')"
    );
    $pendingWorkflow = WorkflowVariableGate::pending($pdo, $targetDatabase);
    local_smoke_assert(
        count($pendingWorkflow) === 1
        && $pendingWorkflow[0]['bytearraySha256'] === hash('sha256', $workflowBytes),
        'bulk server-side workflow byte-array hash did not match PHP SHA-256'
    );
    $pdo->exec(
        "UPDATE {$db}.act_ru_variable SET TYPE_='string', BYTEARRAY_ID_=NULL, TEXT_='{\"ok\":true}' "
        . "WHERE ID_='var-workflow'"
    );
    WorkflowVariableGate::assertConverted($pdo, $targetDatabase, $pendingWorkflow);
    $pdo->exec(
        "INSERT INTO {$db}.act_ru_variable "
        . "(ID_, PROC_INST_ID_, EXECUTION_ID_, TASK_ID_, NAME_, TYPE_, BYTEARRAY_ID_, TEXT_, TENANT_ID_) "
        . "VALUES ('var-empty-byte','proc-workflow',NULL,NULL,'bad','serializable','',NULL,'tenant-a')"
    );
    local_smoke_throws(
        static fn () => WorkflowVariableGate::pending($pdo, $targetDatabase),
        'empty non-null workflow byte-array reference was accepted'
    );
    $pdo->exec("DELETE FROM {$db}.act_ru_variable WHERE ID_='var-empty-byte'");
    $pdo->exec("DELETE FROM {$db}.act_ru_variable WHERE ID_='var-workflow'");
    $pdo->exec(
        "ALTER TABLE {$db}.act_ru_variable ADD CONSTRAINT fk_smoke_ru_variable_bytes "
        . "FOREIGN KEY (BYTEARRAY_ID_) REFERENCES {$db}.act_ge_bytearray (ID_)"
    );
    $pdo->exec(
        "ALTER TABLE {$db}.act_ru_ext_task ADD CONSTRAINT fk_smoke_ru_ext_task_bytes "
        . "FOREIGN KEY (ERROR_DETAILS_ID_) REFERENCES {$db}.act_ge_bytearray (ID_)"
    );
    $pdo->exec(
        "ALTER TABLE {$db}.act_ru_job ADD CONSTRAINT fk_smoke_ru_job_bytes "
        . "FOREIGN KEY (EXCEPTION_STACK_ID_) REFERENCES {$db}.act_ge_bytearray (ID_)"
    );

    $pdo->exec("INSERT INTO {$db}.act_re_deployment VALUES ('dep-orphan'), ('dep-procure')");
    $pdo->exec("INSERT INTO {$db}.act_re_procdef VALUES ('def-orphan','Legacy_orphan','dep-orphan'), ('def-procure','Process_procure','dep-procure')");
    $pdo->exec("INSERT INTO {$db}.act_ru_execution (ID_,PROC_INST_ID_,ROOT_PROC_INST_ID_,TENANT_ID_) VALUES ('exec-orphan','proc-orphan','proc-orphan',NULL)");
    $pdo->exec("INSERT INTO {$db}.act_ru_task VALUES ('task-orphan','proc-orphan','exec-orphan','def-orphan','tenant-a','Activity_old','legacy-user')");
    $pdo->exec("INSERT INTO {$db}.act_ru_identitylink VALUES ('link-orphan','task-orphan','proc-orphan')");
    $pdo->exec("INSERT INTO {$db}.act_ge_bytearray (ID_,ROOT_PROC_INST_ID_,DEPLOYMENT_ID_,BYTES_,TENANT_ID_) VALUES ('bytes-root','proc-orphan',NULL,'raw-root',NULL), ('bytes-deploy',NULL,'dep-orphan','raw-deployment',NULL)");

    $pdo->exec("INSERT INTO {$db}.tenants VALUES ('tenant-a','NOT_DELETE'), ('tenant-b','NOT_DELETE')");
    for ($index = 1; $index <= 2; $index++) {
        $tenant = 'NULL';
        $pdo->exec("INSERT INTO {$db}.sys_user VALUES ('starter-{$index}','tenant-a','NOT_DELETE'), ('user-{$index}','tenant-a','NOT_DELETE')");
        $pdo->exec("INSERT INTO {$db}.act_hi_procinst (ID_,PROC_INST_ID_,START_USER_ID_,TENANT_ID_) VALUES ('hi-proc-{$index}','proc-valid-{$index}','starter-{$index}',NULL)");
        $pdo->exec("INSERT INTO {$db}.act_ru_execution (ID_,PROC_INST_ID_,ROOT_PROC_INST_ID_,TENANT_ID_) VALUES ('exec-valid-{$index}','proc-valid-{$index}','proc-valid-{$index}',NULL)");
        $pdo->exec("INSERT INTO {$db}.act_ru_task VALUES ('task-valid-{$index}','proc-valid-{$index}',NULL,'def-procure',{$tenant},'Activity_approval_procure',NULL)");
        $pdo->exec("INSERT INTO {$db}.act_hi_taskinst (ID_,PROC_INST_ID_,EXECUTION_ID_,ASSIGNEE_,TENANT_ID_) VALUES ('task-valid-{$index}','proc-valid-{$index}',NULL,NULL,NULL)");
        $pdo->exec("INSERT INTO {$db}.act_ru_variable VALUES ('var-user-{$index}','proc-valid-{$index}',NULL,NULL,'user','string',NULL,'user-{$index}',{$tenant})");
        $pdo->exec("INSERT INTO {$db}.act_ru_variable VALUES ('var-tenant-{$index}','proc-valid-{$index}',NULL,NULL,'tenantId','string',NULL,'tenant-a',NULL)");
        $pdo->exec("INSERT INTO {$db}.act_hi_varinst (ID_,PROC_INST_ID_,NAME_,VAR_TYPE_,BYTEARRAY_ID_,TEXT_,TENANT_ID_) VALUES ('hi-var-tenant-{$index}','proc-valid-{$index}','tenantId','string',NULL,'tenant-a',NULL)");
        $pdo->exec("INSERT INTO {$db}.act_hi_actinst (ID_,PROC_INST_ID_,TENANT_ID_) VALUES ('hi-act-{$index}','proc-valid-{$index}',NULL)");
        $pdo->exec("INSERT INTO {$db}.act_hi_detail (ID_,PROC_INST_ID_,TENANT_ID_) VALUES ('hi-detail-{$index}','proc-valid-{$index}',NULL)");
        $pdo->exec("INSERT INTO {$db}.act_hi_identitylink VALUES ('hi-link-{$index}','proc-valid-{$index}',NULL)");
        $pdo->exec(
            "INSERT INTO {$db}.act_hi_op_log (ID_,PROC_INST_ID_,ROOT_PROC_INST_ID_,TENANT_ID_) "
            . "VALUES ('hi-op-{$index}','proc-valid-{$index}',NULL,NULL)"
        );
        $pdo->exec("INSERT INTO {$db}.act_ge_bytearray (ID_,ROOT_PROC_INST_ID_,DEPLOYMENT_ID_,BYTES_,TENANT_ID_) VALUES ('bytes-valid-{$index}','proc-valid-{$index}',NULL,'workflow-bytes',NULL)");
    }
    $pdo->exec(
        "INSERT INTO {$db}.act_hi_op_log "
        . '(ID_,DEPLOYMENT_ID_,PROC_DEF_ID_,ROOT_PROC_INST_ID_,PROC_INST_ID_,USER_ID_,TIMESTAMP_,'
        . 'OPERATION_ID_,TENANT_ID_,REMOVAL_TIME_) VALUES '
        . "('hi-op-detached','dep-procure','def-procure','proc-op-detached','proc-op-detached',"
        . "'user-1',UTC_TIMESTAMP(),'operation-detached',NULL,UTC_TIMESTAMP())"
    );
    $pdo->exec(
        "INSERT INTO {$db}.act_ge_bytearray "
        . '(ID_,BYTES_,TENANT_ID_,TYPE_,CREATE_TIME_,ROOT_PROC_INST_ID_,REMOVAL_TIME_) VALUES '
        . "('bytes-detached','detached-payload',NULL,3,UTC_TIMESTAMP(),'proc-detached',UTC_TIMESTAMP()), "
        . "('bytes-business-detached','business-detached-payload','tenant-a',3,UTC_TIMESTAMP(),"
        . "'proc-business-detached',UTC_TIMESTAMP())"
    );
    $pdo->exec(
        "INSERT INTO {$db}.biz_sale_project_reissue_order (ID,PROCESS_ID,TENANT_ID) "
        . "VALUES ('reissue-detached','proc-business-detached','tenant-a')"
    );

    $consumerSpecs = [
        ['act_hi_attachment', 'CONTENT_ID_'],
        ['act_hi_dec_in', 'BYTEARRAY_ID_'],
        ['act_hi_dec_out', 'BYTEARRAY_ID_'],
        ['act_hi_detail', 'BYTEARRAY_ID_'],
        ['act_hi_ext_task_log', 'ERROR_DETAILS_ID_'],
        ['act_hi_job_log', 'JOB_EXCEPTION_STACK_ID_'],
        ['act_hi_varinst', 'BYTEARRAY_ID_'],
        ['act_ru_ext_task', 'ERROR_DETAILS_ID_'],
        ['act_ru_job', 'EXCEPTION_STACK_ID_'],
        ['act_ru_variable', 'BYTEARRAY_ID_'],
    ];
    $orphans = OrphanPolicy::detect($pdo, $targetDatabase);
    local_smoke_assert(count($orphans) === 1 && $orphans[0]['taskId'] === 'task-orphan', 'local orphan detection failed');
    $pdo->exec(
        "INSERT INTO {$db}.act_hi_procinst (ID_,PROC_INST_ID_) "
        . "VALUES ('hi-proc-orphan-case-shadow','PROC-ORPHAN')"
    );
    $binaryOrphans = OrphanPolicy::detect($pdo, $targetDatabase);
    local_smoke_assert(
        count($binaryOrphans) === 1 && $binaryOrphans[0]['taskId'] === 'task-orphan',
        'case-only history process id incorrectly hid an orphan task'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_procinst WHERE ID_='hi-proc-orphan-case-shadow'");
    foreach ($consumerSpecs as $index => [$consumerTable, $consumerColumn]) {
        $consumerId = "consumer-orphan-{$index}";
        $insertConsumer = $pdo->prepare(
            "INSERT INTO {$db}.`{$consumerTable}` (ID_, `{$consumerColumn}`) VALUES (?, 'bytes-root')"
        );
        $insertConsumer->execute([$consumerId]);
        local_smoke_throws(
            static fn () => OrphanPolicy::assertIsolationEligible($pdo, $targetDatabase, $orphans),
            "orphan byte-array consumer reference was accepted for {$consumerTable}.{$consumerColumn}"
        );
        $deleteConsumer = $pdo->prepare("DELETE FROM {$db}.`{$consumerTable}` WHERE ID_ = ?");
        $deleteConsumer->execute([$consumerId]);
    }
    foreach (['act_hi_identitylink', 'act_hi_op_log'] as $index => $rootReferenceTable) {
        $rootReferenceId = "orphan-root-reference-{$index}";
        $insertRootReference = $pdo->prepare(
            "INSERT INTO {$db}.`{$rootReferenceTable}` (ID_, ROOT_PROC_INST_ID_) VALUES (?, 'proc-orphan')"
        );
        $insertRootReference->execute([$rootReferenceId]);
        local_smoke_throws(
            static fn () => OrphanPolicy::assertIsolationEligible($pdo, $targetDatabase, $orphans),
            "orphan root-only history reference was accepted for {$rootReferenceTable}"
        );
        $deleteRootReference = $pdo->prepare(
            "DELETE FROM {$db}.`{$rootReferenceTable}` WHERE ID_ = ?"
        );
        $deleteRootReference->execute([$rootReferenceId]);
    }
    $eligibility = OrphanPolicy::assertIsolationEligible($pdo, $targetDatabase, $orphans);
    local_smoke_assert($eligibility['rootBytearrayRowsPreservedForQuarantine'] === 1, 'orphan eligibility byte-array audit failed');
    local_smoke_assert(
        array_sum($eligibility['rootBytearrayReferenceChecks']) === 0,
        'orphan byte-array reference checks were not frozen'
    );
    $orphanProcessIds = array_values(array_map(
        static fn (array $item): string => (string)$item['processId'],
        $orphans
    ));
    $detachedOperationLogPlan = DetachedOperationLogPolicy::audit(
        $pdo,
        $targetDatabase,
        $orphanProcessIds
    );
    DetachedOperationLogPolicy::assertExpected($detachedOperationLogPlan, 1, 1);
    local_smoke_assert(
        $detachedOperationLogPlan['candidateRows'] === 1
        && $detachedOperationLogPlan['distinctProcesses'] === 1
        && $detachedOperationLogPlan['operationSiblingRows'] === 0,
        'detached operation-log classification failed'
    );

    $pdo->exec(
        "UPDATE {$db}.act_hi_op_log SET ID_='hi-op-detached-drift' WHERE ID_='hi-op-detached'"
    );
    local_smoke_throws(
        static fn () => DetachedOperationLogPolicy::assertSamePlan(
            DetachedOperationLogPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
            $detachedOperationLogPlan
        ),
        'same-count detached operation-log identity drift was accepted'
    );
    $pdo->exec(
        "UPDATE {$db}.act_hi_op_log SET ID_='hi-op-detached' WHERE ID_='hi-op-detached-drift'"
    );

    $pdo->exec(
        "INSERT INTO {$db}.act_hi_actinst (ID_,CALL_PROC_INST_ID_) "
        . "VALUES ('hi-act-op-reference','proc-op-detached')"
    );
    local_smoke_throws(
        static fn () => DetachedOperationLogPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'workflow-engine call-process reference to detached operation log was accepted'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_actinst WHERE ID_='hi-act-op-reference'");

    $pdo->exec(
        "INSERT INTO {$db}.biz_sale_project_reissue_order (ID,PROCESS_ID,TENANT_ID) "
        . "VALUES ('reissue-op-reference','proc-op-detached','tenant-a')"
    );
    local_smoke_throws(
        static fn () => DetachedOperationLogPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'business process reference to detached operation log was accepted'
    );
    $pdo->exec("DELETE FROM {$db}.biz_sale_project_reissue_order WHERE ID='reissue-op-reference'");

    $pdo->exec("UPDATE {$db}.sys_user SET DELETE_FLAG='DELETE' WHERE ID='user-1'");
    local_smoke_throws(
        static fn () => DetachedOperationLogPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'inactive user support for detached operation log was accepted'
    );
    $pdo->exec("UPDATE {$db}.sys_user SET DELETE_FLAG='NOT_DELETE' WHERE ID='user-1'");

    $pdo->exec(
        "UPDATE {$db}.act_hi_op_log SET DEPLOYMENT_ID_='dep-orphan' WHERE ID_='hi-op-detached'"
    );
    local_smoke_throws(
        static fn () => DetachedOperationLogPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'mismatched process-definition deployment support was accepted'
    );
    $pdo->exec(
        "UPDATE {$db}.act_hi_op_log SET DEPLOYMENT_ID_='dep-procure' WHERE ID_='hi-op-detached'"
    );

    $pdo->exec(
        "UPDATE {$db}.act_hi_op_log SET CASE_DEF_ID_='case-def-smoke' WHERE ID_='hi-op-detached'"
    );
    local_smoke_throws(
        static fn () => DetachedOperationLogPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'case-definition-linked operation log was accepted as detached'
    );
    $pdo->exec("UPDATE {$db}.act_hi_op_log SET CASE_DEF_ID_=NULL WHERE ID_='hi-op-detached'");

    $pdo->exec(
        "INSERT INTO {$db}.act_hi_op_log (ID_,PROC_INST_ID_,ROOT_PROC_INST_ID_,OPERATION_ID_) "
        . "VALUES ('hi-op-detached-sibling','proc-valid-1','proc-valid-1','operation-detached')"
    );
    local_smoke_throws(
        static fn () => DetachedOperationLogPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'operation-group sibling was accepted as detached'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_op_log WHERE ID_='hi-op-detached-sibling'");

    $pdo->exec(
        "CREATE TABLE {$db}.act_op_log_reference ("
        . 'ID_ varchar(64) NOT NULL, OP_LOG_ID_ varchar(64), PRIMARY KEY (ID_), '
        . 'CONSTRAINT fk_smoke_op_log_reference FOREIGN KEY (OP_LOG_ID_) '
        . "REFERENCES {$db}.act_hi_op_log (ID_)) "
        . 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );
    local_smoke_throws(
        static fn () => DetachedOperationLogPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'unreviewed inbound operation-log foreign key was accepted'
    );
    $pdo->exec("DROP TABLE {$db}.act_op_log_reference");
    DetachedOperationLogPolicy::assertSamePlan(
        DetachedOperationLogPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        $detachedOperationLogPlan
    );

    $detachedPlan = DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds);
    local_smoke_assert(
        $detachedPlan['candidateRows'] === 2
        && $detachedPlan['distinctRoots'] === 2
        && $detachedPlan['fullyDetachedRows'] === 1
        && $detachedPlan['businessLinkedRows'] === 1
        && $detachedPlan['businessLinkedRoots'] === 1,
        'detached byte-array classification failed'
    );
    $indexSafePrerequisites = $detachedPlan['indexSafePrerequisites'] ?? null;
    local_smoke_assert(
        is_array($indexSafePrerequisites)
        && ($indexSafePrerequisites['trimCanonicality']['bytearrayRootProcessDifferences'] ?? -1) === 0
        && ($indexSafePrerequisites['trimCanonicality']['historyProcessDifferences'] ?? -1) === 0
        && ($indexSafePrerequisites['comparisonMetadata']['consumer']['compatible'] ?? false) === true
        && ($indexSafePrerequisites['comparisonMetadata']['processEvidence']['compatible'] ?? false) === true,
        'index-safe detached byte-array prerequisites were not bound into the plan'
    );
    $pdo->exec(
        "INSERT INTO {$db}.act_ge_bytearray "
        . "(ID_,BYTES_,TYPE_,CREATE_TIME_,ROOT_PROC_INST_ID_,REMOVAL_TIME_) VALUES "
        . "('bytes-detached-root-collision','collision',3,UTC_TIMESTAMP(),"
        . "'PROC-DETACHED',UTC_TIMESTAMP())"
    );
    local_smoke_throws(
        static fn () => DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'collation-equivalent candidate root collision bypassed the binary distinctness guard'
    );
    $pdo->exec("DELETE FROM {$db}.act_ge_bytearray WHERE ID_='bytes-detached-root-collision'");

    $pdo->exec(
        "INSERT INTO {$db}.act_ge_bytearray "
        . "(ID_,BYTES_,TENANT_ID_,TYPE_,CREATE_TIME_,ROOT_PROC_INST_ID_,REMOVAL_TIME_) VALUES "
        . "('bytes-detached-tenant-collision','collision','TENANT-A',3,UTC_TIMESTAMP(),"
        . "'proc-business-detached',UTC_TIMESTAMP())"
    );
    local_smoke_throws(
        static fn () => DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'collation-equivalent candidate tenant collision bypassed the binary distinctness guard'
    );
    $pdo->exec("DELETE FROM {$db}.act_ge_bytearray WHERE ID_='bytes-detached-tenant-collision'");

    $pdo->exec(
        "INSERT INTO {$db}.act_hi_procinst (ID_,PROC_INST_ID_) "
        . "VALUES ('hi-proc-detached-case-shadow','PROC-DETACHED')"
    );
    $caseShadowPlan = DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds);
    local_smoke_assert(
        $caseShadowPlan['candidateRows'] === $detachedPlan['candidateRows'],
        'case-only history process id hid a byte-exact detached candidate'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_procinst WHERE ID_='hi-proc-detached-case-shadow'");

    $pdo->exec(
        "INSERT INTO {$db}.act_hi_procinst (ID_,PROC_INST_ID_) "
        . "VALUES ('hi-proc-detached-exact','proc-detached')"
    );
    $exactHistoryPlan = DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds);
    local_smoke_assert(
        $exactHistoryPlan['candidateRows'] === $detachedPlan['candidateRows'] - 1,
        'exact history process id did not exclude its detached candidate'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_procinst WHERE ID_='hi-proc-detached-exact'");

    $pdo->exec(
        "INSERT INTO {$db}.act_hi_procinst (ID_,PROC_INST_ID_) "
        . "VALUES ('hi-proc-detached-nonnormalized',' proc-detached-nonnormalized ')"
    );
    local_smoke_throws(
        static fn () => DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'non-normalized history process id bypassed the indexed comparison guard'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_procinst WHERE ID_='hi-proc-detached-nonnormalized'");

    $pdo->exec(
        "INSERT INTO {$db}.act_ge_bytearray "
        . "(ID_,BYTES_,TYPE_,CREATE_TIME_,ROOT_PROC_INST_ID_,REMOVAL_TIME_) VALUES "
        . "('bytes-detached-nonnormalized','guard',3,UTC_TIMESTAMP(),"
        . "' proc-detached-nonnormalized ',UTC_TIMESTAMP())"
    );
    local_smoke_throws(
        static fn () => DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'non-normalized byte-array root bypassed the indexed comparison guard'
    );
    $pdo->exec("DELETE FROM {$db}.act_ge_bytearray WHERE ID_='bytes-detached-nonnormalized'");

    $pdo->exec("ALTER TABLE {$db}.act_ge_bytearray DROP INDEX ACT_IDX_BYTEARRAY_ROOT_PI");
    local_smoke_throws(
        static fn () => DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'missing byte-array root index bypassed the indexed comparison guard'
    );
    $pdo->exec(
        "ALTER TABLE {$db}.act_ge_bytearray "
        . 'ADD KEY ACT_IDX_BYTEARRAY_ROOT_PI (ROOT_PROC_INST_ID_)'
    );

    $pdo->exec("ALTER TABLE {$db}.act_hi_procinst DROP INDEX uk_hi_proc");
    local_smoke_throws(
        static fn () => DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'missing history process index bypassed the indexed comparison guard'
    );
    $pdo->exec(
        "ALTER TABLE {$db}.act_hi_procinst "
        . 'ADD UNIQUE KEY uk_hi_proc (PROC_INST_ID_)'
    );

    $pdo->exec(
        "ALTER TABLE {$db}.act_hi_detail "
        . 'MODIFY BYTEARRAY_ID_ varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL'
    );
    local_smoke_throws(
        static fn () => DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'comparison collation drift bypassed the indexed comparison guard'
    );
    $pdo->exec(
        "ALTER TABLE {$db}.act_hi_detail "
        . 'MODIFY BYTEARRAY_ID_ varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL'
    );

    $pdo->exec(
        "INSERT INTO {$db}.act_ge_bytearray "
        . "(ID_,BYTES_,TYPE_,CREATE_TIME_,ROOT_PROC_INST_ID_,REMOVAL_TIME_) VALUES "
        . "('bytes-detached-case-ref','case-ref',3,UTC_TIMESTAMP(),"
        . "'proc-detached-case-ref',UTC_TIMESTAMP())"
    );
    $pdo->exec(
        "INSERT INTO {$db}.act_hi_detail (ID_,BYTEARRAY_ID_) "
        . "VALUES ('detail-detached-case-ref','BYTES-DETACHED-CASE-REF')"
    );
    $caseReferencePlan = DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds);
    local_smoke_assert(
        $caseReferencePlan['candidateRows'] === $detachedPlan['candidateRows'] + 1,
        'case-only consumer reference was treated as byte-exact'
    );
    $pdo->exec(
        "UPDATE {$db}.act_hi_detail SET BYTEARRAY_ID_='bytes-detached-case-ref' "
        . "WHERE ID_='detail-detached-case-ref'"
    );
    local_smoke_throws(
        static fn () => DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'exact detached byte-array consumer reference was accepted'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_detail WHERE ID_='detail-detached-case-ref'");
    $pdo->exec("DELETE FROM {$db}.act_ge_bytearray WHERE ID_='bytes-detached-case-ref'");

    $pdo->exec(
        "INSERT INTO {$db}.act_ge_bytearray "
        . "(ID_,BYTES_,TYPE_,CREATE_TIME_,ROOT_PROC_INST_ID_,REMOVAL_TIME_) VALUES "
        . "('bytes-detached-pad-ref','pad-ref',3,UTC_TIMESTAMP(),"
        . "'proc-detached-pad-ref',UTC_TIMESTAMP())"
    );
    $insertPaddedConsumer = $pdo->prepare(
        "INSERT INTO {$db}.act_hi_detail (ID_,BYTEARRAY_ID_) VALUES (?, ?)"
    );
    $insertPaddedConsumer->execute(['detail-detached-pad-ref', 'bytes-detached-pad-ref ']);
    $paddedReferencePlan = DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds);
    local_smoke_assert(
        $paddedReferencePlan['candidateRows'] === $detachedPlan['candidateRows'] + 1,
        'padding-equivalent consumer reference was treated as byte-exact'
    );
    $pdo->exec(
        "UPDATE {$db}.act_hi_detail SET BYTEARRAY_ID_='bytes-detached-pad-ref' "
        . "WHERE ID_='detail-detached-pad-ref'"
    );
    local_smoke_throws(
        static fn () => DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'exact consumer reference was accepted after a padding-only non-match'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_detail WHERE ID_='detail-detached-pad-ref'");
    $pdo->exec("DELETE FROM {$db}.act_ge_bytearray WHERE ID_='bytes-detached-pad-ref'");

    $pdo->exec(
        "INSERT INTO {$db}.act_ge_bytearray "
        . "(ID_,BYTES_,TYPE_,CREATE_TIME_,ROOT_PROC_INST_ID_,REMOVAL_TIME_) VALUES "
        . "('bytes-detached-evidence-case','evidence',3,UTC_TIMESTAMP(),"
        . "'proc-detached-evidence-case',UTC_TIMESTAMP())"
    );
    $pdo->exec(
        "INSERT INTO {$db}.act_hi_detail (ID_,PROC_INST_ID_) "
        . "VALUES ('detail-detached-evidence-case','PROC-DETACHED-EVIDENCE-CASE')"
    );
    $caseEvidencePlan = DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds);
    local_smoke_assert(
        $caseEvidencePlan['candidateRows'] === $detachedPlan['candidateRows'] + 1,
        'case-only process evidence was treated as byte-exact'
    );
    $pdo->exec(
        "UPDATE {$db}.act_hi_detail SET PROC_INST_ID_='proc-detached-evidence-case' "
        . "WHERE ID_='detail-detached-evidence-case'"
    );
    local_smoke_throws(
        static fn () => DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'exact detached process evidence was accepted'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_detail WHERE ID_='detail-detached-evidence-case'");
    $pdo->exec("DELETE FROM {$db}.act_ge_bytearray WHERE ID_='bytes-detached-evidence-case'");
    $pdo->exec(
        "CREATE TABLE {$db}.act_unreviewed_byte_consumer ("
        . 'ID_ varchar(64) NOT NULL, PAYLOAD_REF_ varchar(64), PRIMARY KEY (ID_), '
        . "CONSTRAINT fk_smoke_unreviewed_bytes FOREIGN KEY (PAYLOAD_REF_) "
        . "REFERENCES {$db}.act_ge_bytearray (ID_)) "
        . 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );
    local_smoke_throws(
        static fn () => DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'unreviewed foreign-key byte-array consumer was accepted'
    );
    $pdo->exec("DROP TABLE {$db}.act_unreviewed_byte_consumer");

    $pdo->exec(
        "INSERT INTO {$db}.act_ge_bytearray "
        . "(ID_,BYTES_,TYPE_,CREATE_TIME_,ROOT_PROC_INST_ID_,REMOVAL_TIME_) VALUES "
        . "('bytes-detached-drift','drift',3,UTC_TIMESTAMP(),'proc-detached-drift',UTC_TIMESTAMP())"
    );
    local_smoke_throws(
        static fn () => DetachedBytearrayPolicy::assertSamePlan(
            DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
            $detachedPlan
        ),
        'detached byte-array plan drift was accepted'
    );
    $pdo->exec("DELETE FROM {$db}.act_ge_bytearray WHERE ID_='bytes-detached-drift'");

    $pdo->exec(
        "INSERT INTO {$db}.act_ge_bytearray "
        . "(ID_,DEPLOYMENT_ID_,BYTES_,TYPE_,CREATE_TIME_,ROOT_PROC_INST_ID_,REMOVAL_TIME_) VALUES "
        . "('bytes-detached-deployment','dep-procure','protected',3,UTC_TIMESTAMP(),"
        . "'proc-detached-deployment',UTC_TIMESTAMP())"
    );
    local_smoke_throws(
        static fn () => DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        'deployment-bound detached byte-array candidate was accepted'
    );
    $pdo->exec("DELETE FROM {$db}.act_ge_bytearray WHERE ID_='bytes-detached-deployment'");

    foreach ($consumerSpecs as $index => [$consumerTable, $consumerColumn]) {
        $byteId = "bytes-detached-referenced-{$index}";
        $rootId = "proc-detached-referenced-{$index}";
        $consumerId = "consumer-detached-{$index}";
        $insertByte = $pdo->prepare(
            "INSERT INTO {$db}.act_ge_bytearray "
            . '(ID_,BYTES_,TYPE_,CREATE_TIME_,ROOT_PROC_INST_ID_,REMOVAL_TIME_) '
            . 'VALUES (?, ?, 3, UTC_TIMESTAMP(), ?, UTC_TIMESTAMP())'
        );
        $insertByte->execute([$byteId, 'referenced', $rootId]);
        $insertConsumer = $pdo->prepare(
            "INSERT INTO {$db}.`{$consumerTable}` (ID_, `{$consumerColumn}`) VALUES (?, ?)"
        );
        $insertConsumer->execute([$consumerId, $byteId]);
        local_smoke_throws(
            static fn () => DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
            "referenced detached byte-array candidate was accepted for {$consumerTable}.{$consumerColumn}"
        );
        $deleteConsumer = $pdo->prepare("DELETE FROM {$db}.`{$consumerTable}` WHERE ID_ = ?");
        $deleteConsumer->execute([$consumerId]);
        $deleteByte = $pdo->prepare("DELETE FROM {$db}.act_ge_bytearray WHERE ID_ = ?");
        $deleteByte->execute([$byteId]);
    }
    DetachedBytearrayPolicy::assertSamePlan(
        DetachedBytearrayPolicy::audit($pdo, $targetDatabase, $orphanProcessIds),
        $detachedPlan
    );
    $preIsolationTenantPlan = WorkflowTenantRepair::audit(
        $pdo,
        $targetDatabase,
        2,
        2,
        2,
        $orphanProcessIds,
        [
            'act_ge_bytearray' => $detachedPlan['rootIds'],
            'act_hi_op_log' => $detachedOperationLogPlan['processIds'],
        ]
    );
    $pdo->exec(
        "INSERT INTO {$db}.act_hi_detail (ID_,PROC_INST_ID_,TENANT_ID_) "
        . "VALUES ('hi-detail-detached-scope','proc-detached',NULL)"
    );
    local_smoke_throws(
        static fn () => WorkflowTenantRepair::audit(
            $pdo,
            $targetDatabase,
            2,
            2,
            2,
            $orphanProcessIds,
            [
                'act_ge_bytearray' => $detachedPlan['rootIds'],
                'act_hi_op_log' => $detachedOperationLogPlan['processIds'],
            ]
        ),
        'act_ge_bytearray-specific detached ignore leaked into another workflow table'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_detail WHERE ID_='hi-detail-detached-scope'");
    $pdo->exec(
        "INSERT INTO {$db}.act_hi_detail (ID_,PROC_INST_ID_,TENANT_ID_) "
        . "VALUES ('hi-detail-op-scope','proc-op-detached',NULL)"
    );
    local_smoke_throws(
        static fn () => WorkflowTenantRepair::audit(
            $pdo,
            $targetDatabase,
            2,
            2,
            2,
            $orphanProcessIds,
            [
                'act_ge_bytearray' => $detachedPlan['rootIds'],
                'act_hi_op_log' => $detachedOperationLogPlan['processIds'],
            ]
        ),
        'act_hi_op_log-specific detached ignore leaked into another workflow table'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_detail WHERE ID_='hi-detail-op-scope'");

    $quarantineManager = new QuarantineManager(
        $pdo,
        $targetDatabase,
        $quarantineDatabase,
        $runId
    );
    $pdo->exec(
        "CREATE TRIGGER {$db}.`orphan_task_delete_failure` BEFORE DELETE ON {$db}.act_ru_task "
        . "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced orphan rollback'"
    );
    local_smoke_throws(
        static fn () => $quarantineManager->quarantine($orphans),
        'orphan delete failure did not roll back'
    );
    local_smoke_assert(
        (int)$pdo->query("SELECT COUNT(*) FROM {$db}.act_ru_task WHERE ID_='task-orphan'")->fetchColumn() === 1,
        'orphan runtime task changed after rollback'
    );
    local_smoke_assert(
        (int)$pdo->query("SELECT COUNT(*) FROM {$db}.act_ge_bytearray WHERE ID_='bytes-root'")->fetchColumn() === 1,
        'orphan root byte-array changed after rollback'
    );
    local_smoke_assert(
        (int)$pdo->query("SELECT COUNT(*) FROM {$db}.act_ru_identitylink WHERE ID_='link-orphan'")->fetchColumn() === 1
        && (int)$pdo->query("SELECT COUNT(*) FROM {$db}.act_ru_execution WHERE ID_='exec-orphan'")->fetchColumn() === 1,
        'earlier orphan delete-order rows changed after rollback'
    );
    local_smoke_assert(
        (int)$pdo->query('SELECT @@SESSION.FOREIGN_KEY_CHECKS')->fetchColumn() === 1,
        'foreign-key checks were not restored after orphan rollback'
    );
    local_smoke_assert(
        (int)$pdo->query("SELECT COUNT(*) FROM `{$quarantineDatabase}`.act_ru_task")->fetchColumn() === 1
        && (int)$pdo->query("SELECT COUNT(*) FROM `{$quarantineDatabase}`.act_ge_bytearray")->fetchColumn() === 2
        && (int)$pdo->query(
            "SELECT COUNT(*) FROM `{$quarantineDatabase}`.migration_quarantine_audit"
        )->fetchColumn() > 0,
        'failed orphan isolation evidence was not preserved'
    );
    $pdo->exec("DROP TRIGGER {$db}.`orphan_task_delete_failure`");
    $pdo->exec("DROP DATABASE `{$quarantineDatabase}`");
    $pdo->exec(
        "CREATE DATABASE `{$quarantineDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
    );
    $quarantineManager = new QuarantineManager(
        $pdo,
        $targetDatabase,
        $quarantineDatabase,
        $runId
    );
    $quarantine = $quarantineManager->quarantine($orphans);
    local_smoke_assert($quarantine['taskCount'] === 1, 'local quarantine task count failed');
    local_smoke_assert($quarantine['rootBytearrayRowsDeleted'] === 1, 'orphan root byte-array delete count failed');
    local_smoke_assert((int)$pdo->query("SELECT COUNT(*) FROM {$db}.act_ru_task WHERE ID_='task-orphan'")->fetchColumn() === 0, 'orphan remained active');
    local_smoke_assert((int)$pdo->query("SELECT COUNT(*) FROM `{$quarantineDatabase}`.act_ge_bytearray")->fetchColumn() === 2, 'raw byte arrays were not preserved');
    local_smoke_assert((int)$pdo->query("SELECT COUNT(*) FROM {$db}.act_ge_bytearray WHERE ID_='bytes-deploy'")->fetchColumn() === 1, 'shared deployment byte array was deleted');
    $postOrphanDetachedOperationLogPlan = DetachedOperationLogPolicy::audit($pdo, $targetDatabase, []);
    DetachedOperationLogPolicy::assertSamePlan(
        $postOrphanDetachedOperationLogPlan,
        $detachedOperationLogPlan
    );
    $detachedOperationLogBeforeRollback = $pdo->query(
        "SELECT * FROM {$db}.act_hi_op_log WHERE ID_='hi-op-detached'"
    )->fetch();
    $pdo->exec(
        "CREATE TRIGGER {$db}.`detached_op_log_delete_failure` BEFORE DELETE ON {$db}.act_hi_op_log "
        . "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced detached operation-log rollback'"
    );
    local_smoke_throws(
        static fn () => $quarantineManager->quarantineDetachedOperationLogs($detachedOperationLogPlan),
        'detached operation-log delete failure did not roll back'
    );
    local_smoke_assert(
        $pdo->query("SELECT * FROM {$db}.act_hi_op_log WHERE ID_='hi-op-detached'")->fetch()
            === $detachedOperationLogBeforeRollback,
        'detached operation-log target content changed after rollback'
    );
    local_smoke_assert(
        (int)$pdo->query(
            "SELECT COUNT(*) FROM `{$quarantineDatabase}`.act_hi_op_log_detached"
        )->fetchColumn() === 0,
        'detached operation-log quarantine copy was not rolled back'
    );
    local_smoke_assert(
        (int)$pdo->query(
            "SELECT COUNT(*) FROM `{$quarantineDatabase}`.migration_quarantine_audit "
            . "WHERE TABLE_NAME='act_hi_op_log_detached'"
        )->fetchColumn() === 0,
        'detached operation-log quarantine audit was not rolled back'
    );
    $pdo->exec("DROP TRIGGER {$db}.`detached_op_log_delete_failure`");
    $pdo->exec("DROP TABLE `{$quarantineDatabase}`.act_hi_op_log_detached");
    $detachedOperationLogAudit = $quarantineManager->quarantineDetachedOperationLogs(
        $detachedOperationLogPlan
    );
    local_smoke_assert(
        $detachedOperationLogAudit['rowCount'] === 1,
        'detached operation-log quarantine count failed'
    );
    $quarantineManager->assertDetachedOperationLogQuarantine($detachedOperationLogPlan);
    local_smoke_assert(
        (int)$pdo->query(
            "SELECT COUNT(*) FROM `{$quarantineDatabase}`.act_hi_op_log_detached"
        )->fetchColumn() === 1
        && (int)$pdo->query(
            "SELECT COUNT(*) FROM {$db}.act_hi_op_log WHERE ID_='hi-op-detached'"
        )->fetchColumn() === 0,
        'detached operation-log was not isolated exactly once'
    );
    $pdo->exec(
        "INSERT INTO {$db}.act_hi_op_log (ID_,PROC_INST_ID_,ROOT_PROC_INST_ID_,OPERATION_ID_) "
        . "VALUES ('hi-op-final-sibling','proc-valid-1','proc-valid-1','operation-detached')"
    );
    local_smoke_throws(
        static fn () => $quarantineManager->assertDetachedOperationLogQuarantine(
            $detachedOperationLogPlan
        ),
        'final detached operation-log assertion accepted a new operation-group sibling'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_op_log WHERE ID_='hi-op-final-sibling'");
    $quarantineManager->assertDetachedOperationLogQuarantine($detachedOperationLogPlan);

    DetachedBytearrayPolicy::assertSamePlan(
        DetachedBytearrayPolicy::audit($pdo, $targetDatabase, []),
        $detachedPlan
    );
    $detachedPayloadsBeforeRollback = $pdo->query(
        "SELECT ID_, HEX(BYTES_) AS payloadHex FROM {$db}.act_ge_bytearray "
        . "WHERE ID_ IN ('bytes-detached','bytes-business-detached') ORDER BY BINARY ID_"
    )->fetchAll();
    $pdo->exec(
        "CREATE TRIGGER {$db}.`detached_bytearray_delete_failure` BEFORE DELETE ON {$db}.act_ge_bytearray "
        . "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced detached byte-array rollback'"
    );
    local_smoke_throws(
        static fn () => $quarantineManager->quarantineDetachedBytearrays($detachedPlan),
        'detached byte-array delete failure did not roll back'
    );
    local_smoke_assert(
        (int)$pdo->query(
            "SELECT COUNT(*) FROM {$db}.act_ge_bytearray "
            . "WHERE ID_ IN ('bytes-detached','bytes-business-detached')"
        )->fetchColumn() === 2,
        'detached byte-array target rows changed after rollback'
    );
    local_smoke_assert(
        $pdo->query(
            "SELECT ID_, HEX(BYTES_) AS payloadHex FROM {$db}.act_ge_bytearray "
            . "WHERE ID_ IN ('bytes-detached','bytes-business-detached') ORDER BY BINARY ID_"
        )->fetchAll() === $detachedPayloadsBeforeRollback,
        'detached byte-array target content changed after rollback'
    );
    local_smoke_assert(
        (int)$pdo->query(
            "SELECT COUNT(*) FROM `{$quarantineDatabase}`.act_ge_bytearray_detached"
        )->fetchColumn() === 0,
        'detached byte-array quarantine copy was not rolled back'
    );
    local_smoke_assert(
        (int)$pdo->query(
            "SELECT COUNT(*) FROM `{$quarantineDatabase}`.migration_quarantine_audit "
            . "WHERE TABLE_NAME='act_ge_bytearray_detached'"
        )->fetchColumn() === 0,
        'detached byte-array quarantine audit was not rolled back'
    );
    $pdo->exec("DROP TRIGGER {$db}.`detached_bytearray_delete_failure`");
    $pdo->exec("DROP TABLE `{$quarantineDatabase}`.act_ge_bytearray_detached");
    $detachedAudit = $quarantineManager->quarantineDetachedBytearrays($detachedPlan);
    local_smoke_assert($detachedAudit['rowCount'] === 2, 'detached byte-array quarantine count failed');
    $quarantineManager->assertDetachedBytearrayQuarantine($detachedPlan);
    local_smoke_assert(
        (int)$pdo->query("SELECT COUNT(*) FROM `{$quarantineDatabase}`.act_ge_bytearray_detached")->fetchColumn() === 2,
        'detached byte-array payloads were not preserved'
    );
    local_smoke_assert(
        (int)$pdo->query("SELECT COUNT(*) FROM {$db}.act_ge_bytearray WHERE ID_ IN ('bytes-detached','bytes-business-detached')")->fetchColumn() === 0,
        'detached byte-array rows remained in the normal target'
    );
    local_smoke_assert(
        (int)$pdo->query("SELECT COUNT(*) FROM {$db}.biz_sale_project_reissue_order")->fetchColumn() === 1,
        'business-linked detached byte-array isolation changed the business row'
    );

    $tenantPlan = WorkflowTenantRepair::audit($pdo, $targetDatabase, 2, 2, 2);
    local_smoke_assert(
        $tenantPlan === $preIsolationTenantPlan,
        'scoped pre-isolation workflow tenant plan differs after quarantine'
    );
    $baseOperationLogRows = (int)$tenantPlan['tables']['act_hi_op_log']['totalRows'];
    $pdo->exec(
        "INSERT INTO {$db}.act_hi_op_log (ID_,PROC_INST_ID_,ROOT_PROC_INST_ID_,TENANT_ID_) "
        . "VALUES ('hi-op-reviewed-ignore','proc-reviewed-ignore',NULL,NULL)"
    );
    $matchedIgnorePlan = WorkflowTenantRepair::audit(
        $pdo,
        $targetDatabase,
        2,
        2,
        2,
        ['proc-reviewed-ignore']
    );
    local_smoke_assert(
        (int)$matchedIgnorePlan['tables']['act_hi_op_log']['totalRows'] === $baseOperationLogRows,
        'reviewed workflow ignore did not exclude its exact process reference'
    );
    $pdo->exec(
        "INSERT INTO {$db}.act_hi_op_log (ID_,PROC_INST_ID_,ROOT_PROC_INST_ID_,TENANT_ID_) "
        . "VALUES ('hi-op-null-secondary','proc-valid-1',NULL,NULL)"
    );
    $nullSecondaryPlan = WorkflowTenantRepair::audit(
        $pdo,
        $targetDatabase,
        2,
        2,
        2,
        ['proc-reviewed-ignore']
    );
    local_smoke_assert(
        (int)$nullSecondaryPlan['tables']['act_hi_op_log']['totalRows'] === $baseOperationLogRows + 1,
        'valid primary workflow reference with a null secondary reference was hidden by ignore filtering'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_op_log WHERE ID_='hi-op-null-secondary'");
    $pdo->exec(
        "INSERT INTO {$db}.act_hi_op_log (ID_,PROC_INST_ID_,ROOT_PROC_INST_ID_,TENANT_ID_) "
        . "VALUES ('hi-op-null-primary',NULL,'proc-valid-1',NULL)"
    );
    $nullPrimaryPlan = WorkflowTenantRepair::audit(
        $pdo,
        $targetDatabase,
        2,
        2,
        2,
        ['proc-reviewed-ignore']
    );
    local_smoke_assert(
        (int)$nullPrimaryPlan['tables']['act_hi_op_log']['totalRows'] === $baseOperationLogRows + 1,
        'valid secondary workflow reference with a null primary reference was hidden by ignore filtering'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_op_log WHERE ID_='hi-op-null-primary'");
    $pdo->exec("DELETE FROM {$db}.act_hi_op_log WHERE ID_='hi-op-reviewed-ignore'");
    $tenantByProcess = WorkflowTenantRepair::tenantByProcess($tenantPlan);
    $repairPlan = AssigneeRepair::audit($pdo, $targetDatabase, 2, $tenantByProcess);
    local_smoke_assert($repairPlan['repairCount'] === 2, 'strict assignee repair audit count failed');
    local_smoke_assert((int)$pdo->query("SELECT COUNT(*) FROM {$db}.act_ru_task WHERE ASSIGNEE_ IS NULL OR ASSIGNEE_='' ")->fetchColumn() === 2, 'assignee repair audit performed writes');

    $pdo->exec("INSERT INTO {$db}.act_hi_detail (ID_,PROC_INST_ID_,TENANT_ID_) VALUES ('hi-detail-unmapped','proc-unmapped',NULL)");
    local_smoke_throws(
        static fn () => WorkflowTenantRepair::audit($pdo, $targetDatabase, 2, 2, 2),
        'unmapped single-column workflow process reference was accepted'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_detail WHERE ID_='hi-detail-unmapped'");

    $pdo->exec(
        "INSERT INTO {$db}.act_hi_op_log (ID_,PROC_INST_ID_,ROOT_PROC_INST_ID_,TENANT_ID_) "
        . "VALUES ('hi-op-unmapped','proc-valid-1','proc-unmapped',NULL)"
    );
    local_smoke_throws(
        static fn () => WorkflowTenantRepair::audit($pdo, $targetDatabase, 2, 2, 2),
        'unmapped secondary workflow process reference was hidden by the primary reference'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_op_log WHERE ID_='hi-op-unmapped'");

    $pdo->exec(
        "INSERT INTO {$db}.act_hi_op_log (ID_,PROC_INST_ID_,ROOT_PROC_INST_ID_,TENANT_ID_) "
        . "VALUES ('hi-op-ignored-unmapped','proc-reviewed-ignore','proc-unmapped',NULL)"
    );
    local_smoke_throws_matching(
        static fn () => WorkflowTenantRepair::audit(
            $pdo,
            $targetDatabase,
            2,
            2,
            2,
            ['proc-reviewed-ignore']
        ),
        'unmapped process references in act_hi_op_log',
        'an ignored reference hid a different unmapped workflow reference'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_op_log WHERE ID_='hi-op-ignored-unmapped'");

    $pdo->exec("UPDATE {$db}.act_hi_detail SET TENANT_ID_='tenant-a ' WHERE ID_='hi-detail-1'");
    local_smoke_throws_matching(
        static fn () => WorkflowTenantRepair::audit($pdo, $targetDatabase, 2, 2, 2),
        'workflow tenant evidence conflicts with act_hi_detail',
        'a trailing-space persisted workflow tenant was treated as an exact match'
    );
    $pdo->exec("UPDATE {$db}.act_hi_detail SET TENANT_ID_=NULL WHERE ID_='hi-detail-1'");

    $pdo->exec("INSERT INTO {$db}.sys_user VALUES ('starter-cross-tenant','tenant-b','NOT_DELETE')");
    $pdo->exec("INSERT INTO {$db}.act_hi_procinst (ID_,PROC_INST_ID_,START_USER_ID_,TENANT_ID_) VALUES ('hi-proc-cross-tenant','proc-cross-tenant','starter-cross-tenant',NULL)");
    $pdo->exec("INSERT INTO {$db}.act_hi_varinst (ID_,PROC_INST_ID_,NAME_,VAR_TYPE_,BYTEARRAY_ID_,TEXT_,TENANT_ID_) VALUES ('hi-var-cross-tenant','proc-cross-tenant','tenantId','string',NULL,'tenant-b',NULL)");
    $pdo->exec(
        "INSERT INTO {$db}.act_hi_op_log (ID_,PROC_INST_ID_,ROOT_PROC_INST_ID_,TENANT_ID_) "
        . "VALUES ('hi-op-cross-tenant','proc-valid-1','proc-cross-tenant',NULL)"
    );
    local_smoke_throws_matching(
        static fn () => WorkflowTenantRepair::audit(
            $pdo,
            $targetDatabase,
            3,
            2,
            2,
            ['proc-valid-1']
        ),
        'conflicting process references in act_hi_op_log',
        'an ignored mapped reference hid a cross-tenant workflow conflict'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_op_log WHERE ID_='hi-op-cross-tenant'");
    $pdo->exec("DELETE FROM {$db}.act_hi_varinst WHERE PROC_INST_ID_='proc-cross-tenant'");
    $pdo->exec("DELETE FROM {$db}.act_hi_procinst WHERE PROC_INST_ID_='proc-cross-tenant'");
    $pdo->exec("DELETE FROM {$db}.sys_user WHERE ID='starter-cross-tenant'");

    $pdo->exec("INSERT INTO {$db}.sys_user VALUES ('starter-trimmed','tenant-a','NOT_DELETE')");
    $pdo->exec("INSERT INTO {$db}.act_hi_procinst (ID_,PROC_INST_ID_,START_USER_ID_,TENANT_ID_) VALUES ('hi-proc-trimmed',' proc-trimmed ','starter-trimmed',NULL)");
    $pdo->exec("INSERT INTO {$db}.act_hi_varinst (ID_,PROC_INST_ID_,NAME_,VAR_TYPE_,BYTEARRAY_ID_,TEXT_,TENANT_ID_) VALUES ('hi-var-trimmed','proc-trimmed','tenantId',' STRING ',NULL,' tenant-a ',NULL)");
    $pdo->exec("INSERT INTO {$db}.act_hi_detail (ID_,PROC_INST_ID_,TENANT_ID_) VALUES ('hi-detail-trimmed','proc-trimmed',NULL)");
    $trimmedPlan = WorkflowTenantRepair::audit($pdo, $targetDatabase, 3, 2, 2);
    local_smoke_assert($trimmedPlan['historyProcessCount'] === 3, 'trim-normalized workflow mapping was rejected');
    $pdo->exec("DELETE FROM {$db}.act_hi_detail WHERE ID_='hi-detail-trimmed'");
    $pdo->exec("DELETE FROM {$db}.act_hi_varinst WHERE ID_='hi-var-trimmed'");
    $pdo->exec("DELETE FROM {$db}.act_hi_procinst WHERE ID_='hi-proc-trimmed'");
    $pdo->exec("DELETE FROM {$db}.sys_user WHERE ID='starter-trimmed'");

    $pdo->exec("INSERT INTO {$db}.sys_user VALUES ('starter-control','tenant-a','NOT_DELETE')");
    $controlProcessId = "\tproc-control";
    $insertControlHistory = $pdo->prepare(
        "INSERT INTO {$db}.act_hi_procinst (ID_,PROC_INST_ID_,START_USER_ID_,TENANT_ID_) "
        . "VALUES ('hi-proc-control',?,'starter-control',NULL)"
    );
    $insertControlHistory->execute([$controlProcessId]);
    $insertControlVariable = $pdo->prepare(
        "INSERT INTO {$db}.act_hi_varinst "
        . "(ID_,PROC_INST_ID_,NAME_,VAR_TYPE_,BYTEARRAY_ID_,TEXT_,TENANT_ID_) "
        . "VALUES ('hi-var-control',?,'tenantId','string',NULL,'tenant-a',NULL)"
    );
    $insertControlVariable->execute([$controlProcessId]);
    $pdo->exec(
        "INSERT INTO {$db}.act_hi_detail (ID_,PROC_INST_ID_,TENANT_ID_) "
        . "VALUES ('hi-detail-control','proc-control',NULL)"
    );
    local_smoke_throws_matching(
        static fn () => WorkflowTenantRepair::audit($pdo, $targetDatabase, 3, 2, 2),
        'contains unsupported control whitespace',
        'control-character workflow identity was collapsed into a different byte identity'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_detail WHERE ID_='hi-detail-control'");
    $pdo->exec("DELETE FROM {$db}.act_hi_varinst WHERE ID_='hi-var-control'");
    $pdo->exec("DELETE FROM {$db}.act_hi_procinst WHERE ID_='hi-proc-control'");
    $pdo->exec("DELETE FROM {$db}.sys_user WHERE ID='starter-control'");
    local_smoke_assert(
        WorkflowTenantRepair::audit($pdo, $targetDatabase, 2, 2, 2) === $tenantPlan,
        'workflow tenant plan changed after strict mapping negative tests'
    );

    $rollbackRunId = $runId . '-rollback';
    $pdo->exec(
        "CREATE TRIGGER {$db}.workflow_tenant_repair_failure BEFORE UPDATE ON {$db}.act_hi_detail "
        . "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced tenant repair rollback'"
    );
    try {
        local_smoke_throws(
            static fn () => WorkflowTenantRepair::apply(
                $pdo,
                $targetDatabase,
                $quarantineDatabase,
                $rollbackRunId,
                2,
                2,
                2,
                $tenantPlan
            ),
            'workflow tenant repair did not roll back after an update failure'
        );
    } finally {
        $pdo->exec("DROP TRIGGER IF EXISTS {$db}.workflow_tenant_repair_failure");
    }
    local_smoke_assert(
        WorkflowTenantRepair::audit($pdo, $targetDatabase, 2, 2, 2) === $tenantPlan,
        'failed workflow tenant repair changed target rows'
    );
    $rollbackAudit = $pdo->prepare(
        "SELECT COUNT(*) FROM `{$quarantineDatabase}`.migration_workflow_tenant_repair_audit WHERE RUN_ID = ?"
    );
    $rollbackAudit->execute([$rollbackRunId]);
    local_smoke_assert((int)$rollbackAudit->fetchColumn() === 0, 'failed workflow tenant repair left audit rows');

    $destructiveRunId = $runId . '-destructive-trigger';
    $pdo->exec(
        "CREATE TRIGGER {$db}.workflow_tenant_repair_destructive AFTER UPDATE ON {$db}.act_ru_variable "
        . "FOR EACH ROW DELETE FROM act_ge_bytearray WHERE ID_='bytes-valid-1'"
    );
    try {
        local_smoke_throws(
            static fn () => WorkflowTenantRepair::apply(
                $pdo,
                $targetDatabase,
                $quarantineDatabase,
                $destructiveRunId,
                2,
                2,
                2,
                $tenantPlan
            ),
            'workflow tenant repair accepted a trigger-induced frozen-row deletion'
        );
    } finally {
        $pdo->exec("DROP TRIGGER IF EXISTS {$db}.workflow_tenant_repair_destructive");
    }
    local_smoke_assert(
        WorkflowTenantRepair::audit($pdo, $targetDatabase, 2, 2, 2) === $tenantPlan,
        'trigger-induced tenant repair failure did not roll back target rows'
    );
    $rollbackAudit->execute([$destructiveRunId]);
    local_smoke_assert((int)$rollbackAudit->fetchColumn() === 0, 'destructive tenant repair failure left audit rows');

    $identityDriftRunId = $runId . '-identity-drift';
    $pdo->exec(
        "CREATE TRIGGER {$db}.workflow_tenant_identity_drift AFTER UPDATE ON {$db}.act_ru_variable "
        . "FOR EACH ROW UPDATE act_hi_varinst SET ID_='hi-var-tenant-drifted' "
        . "WHERE ID_='hi-var-tenant-1'"
    );
    try {
        local_smoke_throws(
            static fn () => WorkflowTenantRepair::apply(
                $pdo,
                $targetDatabase,
                $quarantineDatabase,
                $identityDriftRunId,
                2,
                2,
                2,
                $tenantPlan
            ),
            'workflow tenant repair accepted same-count process evidence identity drift'
        );
    } finally {
        $pdo->exec("DROP TRIGGER IF EXISTS {$db}.workflow_tenant_identity_drift");
    }
    local_smoke_assert(
        WorkflowTenantRepair::audit($pdo, $targetDatabase, 2, 2, 2) === $tenantPlan,
        'identity-drift tenant repair failure did not roll back target rows'
    );
    $rollbackAudit->execute([$identityDriftRunId]);
    local_smoke_assert((int)$rollbackAudit->fetchColumn() === 0, 'identity-drift tenant repair failure left audit rows');

    $tenantRepair = WorkflowTenantRepair::apply(
        $pdo,
        $targetDatabase,
        $quarantineDatabase,
        $runId,
        2,
        2,
        2,
        $tenantPlan
    );
    local_smoke_assert($tenantRepair['rowsUpdated'] === 24, 'workflow tenant repair row count failed');
    $tenantValidation = WorkflowTenantRepair::audit($pdo, $targetDatabase, 2, 2, 2);
    WorkflowTenantRepair::assertApplied($tenantValidation, $tenantPlan);
    $pdo->beginTransaction();
    try {
        $pdo->exec("DELETE FROM {$db}.act_hi_detail WHERE ID_='hi-detail-1'");
        $shrunkTenantAudit = WorkflowTenantRepair::audit($pdo, $targetDatabase, 2, 2, 2);
        local_smoke_throws(
            static fn () => WorkflowTenantRepair::assertApplied($shrunkTenantAudit, $tenantPlan),
            'workflow tenant final gate accepted a deleted frozen mapping row'
        );
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    $pdo->beginTransaction();
    try {
        $pdo->exec(
            "UPDATE {$db}.act_hi_varinst SET ID_='hi-var-tenant-identity-change' "
            . "WHERE ID_='hi-var-tenant-1'"
        );
        $identityChangedAudit = WorkflowTenantRepair::audit($pdo, $targetDatabase, 2, 2, 2);
        local_smoke_throws(
            static fn () => WorkflowTenantRepair::assertApplied($identityChangedAudit, $tenantPlan),
            'workflow tenant final gate accepted same-count process evidence identity drift'
        );
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    local_smoke_assert(
        (int)$pdo->query("SELECT COUNT(*) FROM {$db}.act_ru_task WHERE TENANT_ID_='tenant-a'")->fetchColumn() === 2,
        'tenant-filtered pending workflow tasks remain hidden after repair'
    );
    local_smoke_assert(
        (int)$pdo->query("SELECT COUNT(*) FROM {$db}.act_hi_procinst WHERE TENANT_ID_='tenant-a'")->fetchColumn() === 2,
        'tenant-filtered history workflows remain hidden after repair'
    );
    $tenantIdempotent = WorkflowTenantRepair::apply(
        $pdo,
        $targetDatabase,
        $quarantineDatabase,
        $runId . '-second',
        2,
        2,
        2,
        $tenantValidation
    );
    local_smoke_assert($tenantIdempotent['rowsUpdated'] === 0, 'workflow tenant repair was not idempotent');

    local_smoke_assert(
        AssigneeRepair::audit($pdo, $targetDatabase, 2, $tenantByProcess) === $repairPlan,
        'assignee repair plan changed after workflow tenant repair'
    );

    $pdo->exec(
        "INSERT INTO {$db}.act_ru_variable "
        . "VALUES ('var-user-null-duplicate','proc-valid-1',NULL,NULL,'user','string',NULL,NULL,'tenant-a')"
    );
    local_smoke_throws(
        static fn () => AssigneeRepair::audit($pdo, $targetDatabase, 2, $tenantByProcess, false, true),
        'null duplicate user variable was excluded from the ambiguity gate'
    );
    $pdo->exec("DELETE FROM {$db}.act_ru_variable WHERE ID_='var-user-null-duplicate'");

    $pdo->exec("UPDATE {$db}.act_ru_variable SET PROC_INST_ID_='PROC-VALID-1' WHERE ID_='var-user-1'");
    local_smoke_throws(
        static fn () => AssigneeRepair::audit($pdo, $targetDatabase, 2, $tenantByProcess, false, true),
        'case-insensitive process matching was accepted for the user variable'
    );
    $pdo->exec("UPDATE {$db}.act_ru_variable SET PROC_INST_ID_='proc-valid-1' WHERE ID_='var-user-1'");

    $pdo->exec("UPDATE {$db}.act_ru_variable SET TEXT_='USER-1' WHERE ID_='var-user-1'");
    local_smoke_throws(
        static fn () => AssigneeRepair::audit($pdo, $targetDatabase, 2, $tenantByProcess, false, true),
        'case-insensitive user matching was accepted for assignee repair'
    );
    $pdo->exec("UPDATE {$db}.act_ru_variable SET TEXT_='user-1' WHERE ID_='var-user-1'");

    $pdo->exec("UPDATE {$db}.act_hi_taskinst SET ID_='TASK-VALID-1' WHERE ID_='task-valid-1'");
    local_smoke_throws(
        static fn () => AssigneeRepair::audit($pdo, $targetDatabase, 2, $tenantByProcess, false, true),
        'case-insensitive history task matching was accepted for assignee repair'
    );
    $pdo->exec("UPDATE {$db}.act_hi_taskinst SET ID_='task-valid-1' WHERE ID_='TASK-VALID-1'");

    $pdo->exec("UPDATE {$db}.act_hi_taskinst SET PROC_INST_ID_='proc-wrong' WHERE ID_='task-valid-1'");
    local_smoke_throws(
        static fn () => AssigneeRepair::audit($pdo, $targetDatabase, 2, $tenantByProcess, false, true),
        'history task with a different process was accepted for assignee repair'
    );
    $pdo->exec("UPDATE {$db}.act_hi_taskinst SET PROC_INST_ID_='proc-valid-1' WHERE ID_='task-valid-1'");
    $pdo->exec("UPDATE {$db}.act_hi_taskinst SET TENANT_ID_='tenant-b' WHERE ID_='task-valid-1'");
    local_smoke_throws(
        static fn () => AssigneeRepair::audit($pdo, $targetDatabase, 2, $tenantByProcess, false, true),
        'history task with a different tenant was accepted for assignee repair'
    );
    $pdo->exec("UPDATE {$db}.act_hi_taskinst SET TENANT_ID_='tenant-a' WHERE ID_='task-valid-1'");
    $pdo->exec("UPDATE {$db}.act_hi_taskinst SET ASSIGNEE_='unexpected-user' WHERE ID_='task-valid-1'");
    local_smoke_throws(
        static fn () => AssigneeRepair::audit($pdo, $targetDatabase, 2, $tenantByProcess, false, true),
        'history task with a pre-existing assignee was accepted for repair'
    );
    $pdo->exec("UPDATE {$db}.act_hi_taskinst SET ASSIGNEE_=NULL WHERE ID_='task-valid-1'");

    $staleRepairPlan = $repairPlan;
    $staleRepairPlan['tasks'][0]['userId'] = 'stale-user';
    $staleRepairRunId = $runId . '-stale-assignee';
    local_smoke_throws(
        static fn () => AssigneeRepair::apply(
            $pdo,
            $targetDatabase,
            $quarantineDatabase,
            $staleRepairRunId,
            2,
            $tenantByProcess,
            $staleRepairPlan
        ),
        'stale assignee repair plan was accepted inside the locked transaction'
    );

    $assigneeRollbackRunId = $runId . '-assignee-rollback';
    $pdo->exec(
        "CREATE TRIGGER {$db}.assignee_repair_failure BEFORE UPDATE ON {$db}.act_hi_taskinst "
        . "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced assignee rollback'"
    );
    try {
        local_smoke_throws(
            static fn () => AssigneeRepair::apply(
                $pdo,
                $targetDatabase,
                $quarantineDatabase,
                $assigneeRollbackRunId,
                2,
                $tenantByProcess,
                $repairPlan
            ),
            'assignee repair did not roll back after the history update failed'
        );
    } finally {
        $pdo->exec("DROP TRIGGER IF EXISTS {$db}.assignee_repair_failure");
    }
    local_smoke_assert(
        (int)$pdo->query(
            "SELECT COUNT(*) FROM {$db}.act_ru_task WHERE ASSIGNEE_ IS NULL OR TRIM(ASSIGNEE_) = ''"
        )->fetchColumn() === 2
        && (int)$pdo->query(
            "SELECT COUNT(*) FROM {$db}.act_hi_taskinst WHERE ASSIGNEE_ IS NULL OR TRIM(ASSIGNEE_) = ''"
        )->fetchColumn() === 2,
        'failed assignee repair did not roll back runtime and history rows'
    );
    $assigneeRollbackAudit = $pdo->prepare(
        "SELECT COUNT(*) FROM `{$quarantineDatabase}`.migration_assignee_repair_audit WHERE RUN_ID IN (?, ?)"
    );
    $assigneeRollbackAudit->execute([$staleRepairRunId, $assigneeRollbackRunId]);
    local_smoke_assert((int)$assigneeRollbackAudit->fetchColumn() === 0, 'failed assignee repair left audit rows');

    $repair = AssigneeRepair::apply(
        $pdo,
        $targetDatabase,
        $quarantineDatabase,
        $runId,
        2,
        $tenantByProcess,
        $repairPlan
    );
    local_smoke_assert($repair['repairCount'] === 2, 'strict assignee repair count failed');
    local_smoke_assert((int)$pdo->query("SELECT COUNT(*) FROM {$db}.act_ru_task WHERE ASSIGNEE_ IS NULL OR ASSIGNEE_='' ")->fetchColumn() === 0, 'blank assignees remain');
    WorkflowTenantRepair::assertApplied(
        WorkflowTenantRepair::audit($pdo, $targetDatabase, 2, 2, 0),
        $tenantPlan
    );

    $pdo->exec("INSERT INTO {$db}.sys_user VALUES ('starter-tenant-conflict','tenant-a','NOT_DELETE')");
    $pdo->exec("INSERT INTO {$db}.act_hi_procinst (ID_,PROC_INST_ID_,START_USER_ID_,TENANT_ID_) VALUES ('hi-proc-tenant-conflict','proc-tenant-conflict','starter-tenant-conflict','tenant-b')");
    $pdo->exec("INSERT INTO {$db}.act_hi_varinst (ID_,PROC_INST_ID_,NAME_,VAR_TYPE_,BYTEARRAY_ID_,TEXT_,TENANT_ID_) VALUES ('hi-var-tenant-conflict','proc-tenant-conflict','tenantId','string',NULL,'tenant-a',NULL)");
    local_smoke_throws(
        static fn () => WorkflowTenantRepair::audit($pdo, $targetDatabase, 3, 2, 0),
        'conflicting persisted workflow tenant metadata was accepted'
    );
    $pdo->exec("DELETE FROM {$db}.act_hi_varinst WHERE PROC_INST_ID_='proc-tenant-conflict'");
    $pdo->exec("DELETE FROM {$db}.act_hi_procinst WHERE PROC_INST_ID_='proc-tenant-conflict'");
    $pdo->exec("DELETE FROM {$db}.sys_user WHERE ID='starter-tenant-conflict'");

    $pdo->exec("INSERT INTO {$db}.sys_user VALUES ('starter-runtime-conflict','tenant-a','NOT_DELETE'), ('user-runtime-conflict','tenant-a','NOT_DELETE')");
    $pdo->exec("INSERT INTO {$db}.act_hi_procinst (ID_,PROC_INST_ID_,START_USER_ID_,TENANT_ID_) VALUES ('hi-proc-runtime-conflict','proc-runtime-conflict','starter-runtime-conflict',NULL)");
    $pdo->exec("INSERT INTO {$db}.act_hi_varinst (ID_,PROC_INST_ID_,NAME_,VAR_TYPE_,BYTEARRAY_ID_,TEXT_,TENANT_ID_) VALUES ('hi-var-runtime-conflict','proc-runtime-conflict','tenantId','string',NULL,'tenant-a',NULL)");
    $pdo->exec("INSERT INTO {$db}.act_ru_task VALUES ('task-runtime-conflict','proc-runtime-conflict',NULL,'def-procure',NULL,'Activity_approval_procure','user-runtime-conflict')");
    $pdo->exec("INSERT INTO {$db}.act_ru_variable VALUES ('var-runtime-conflict','proc-runtime-conflict',NULL,NULL,'tenantId','string',NULL,'tenant-b',NULL)");
    local_smoke_throws(
        static fn () => WorkflowTenantRepair::audit($pdo, $targetDatabase, 3, 3, 0),
        'conflicting runtime and history tenantId variables were accepted'
    );
    $pdo->exec("DELETE FROM {$db}.act_ru_variable WHERE PROC_INST_ID_='proc-runtime-conflict'");
    $pdo->exec("DELETE FROM {$db}.act_ru_task WHERE PROC_INST_ID_='proc-runtime-conflict'");
    $pdo->exec("DELETE FROM {$db}.act_hi_varinst WHERE PROC_INST_ID_='proc-runtime-conflict'");
    $pdo->exec("DELETE FROM {$db}.act_hi_procinst WHERE PROC_INST_ID_='proc-runtime-conflict'");
    $pdo->exec("DELETE FROM {$db}.sys_user WHERE ID IN ('starter-runtime-conflict','user-runtime-conflict')");

    $pdo->exec("INSERT INTO {$db}.act_hi_procinst (ID_,PROC_INST_ID_,START_USER_ID_,TENANT_ID_) VALUES ('hi-proc-ambiguous','proc-ambiguous','user-ambiguous','tenant-a')");
    $pdo->exec("INSERT INTO {$db}.act_ru_task VALUES ('task-ambiguous','proc-ambiguous',NULL,'def-procure','tenant-a','Activity_approval_procure',NULL)");
    $pdo->exec("INSERT INTO {$db}.act_hi_taskinst (ID_,PROC_INST_ID_,EXECUTION_ID_,ASSIGNEE_,TENANT_ID_) VALUES ('task-ambiguous','proc-ambiguous',NULL,NULL,'tenant-a')");
    $pdo->exec("INSERT INTO {$db}.sys_user VALUES ('user-ambiguous','tenant-a','NOT_DELETE')");
    $pdo->exec("INSERT INTO {$db}.act_ru_variable VALUES ('var-ambiguous-a','proc-ambiguous',NULL,NULL,'user','string',NULL,'user-ambiguous','tenant-a')");
    $pdo->exec("INSERT INTO {$db}.act_ru_variable VALUES ('var-ambiguous-b','proc-ambiguous',NULL,NULL,'user','string',NULL,'user-ambiguous','tenant-a')");
    local_smoke_throws(
        static fn () => AssigneeRepair::audit(
            $pdo,
            $targetDatabase,
            1,
            ['proc-ambiguous' => 'tenant-a']
        ),
        'duplicate same-tenant user variables were accepted for assignee repair'
    );
    local_smoke_assert(
        (string)$pdo->query("SELECT COALESCE(ASSIGNEE_,'') FROM {$db}.act_ru_task WHERE ID_='task-ambiguous'")->fetchColumn() === '',
        'rejected ambiguous repair changed the task assignee'
    );
    $pdo->exec("DELETE FROM {$db}.act_ru_variable WHERE PROC_INST_ID_='proc-ambiguous'");
    $pdo->exec("DELETE FROM {$db}.act_hi_taskinst WHERE PROC_INST_ID_='proc-ambiguous'");
    $pdo->exec("DELETE FROM {$db}.act_hi_procinst WHERE PROC_INST_ID_='proc-ambiguous'");
    $pdo->exec("DELETE FROM {$db}.act_ru_task WHERE PROC_INST_ID_='proc-ambiguous'");
    $pdo->exec("DELETE FROM {$db}.sys_user WHERE ID='user-ambiguous'");

    $pdo->exec("INSERT INTO {$db}.act_hi_procinst (ID_,PROC_INST_ID_,START_USER_ID_,TENANT_ID_) VALUES ('hi-proc-conflict','proc-conflict','user-conflict','tenant-a')");
    $pdo->exec("INSERT INTO {$db}.act_ru_task VALUES ('task-conflict','proc-conflict',NULL,'def-procure','tenant-a','Activity_approval_procure',NULL)");
    $pdo->exec("INSERT INTO {$db}.act_hi_taskinst (ID_,PROC_INST_ID_,EXECUTION_ID_,ASSIGNEE_,TENANT_ID_) VALUES ('task-conflict','proc-conflict',NULL,NULL,'tenant-a')");
    $pdo->exec("INSERT INTO {$db}.sys_user VALUES ('user-conflict','tenant-a','NOT_DELETE')");
    $pdo->exec("INSERT INTO {$db}.act_ru_variable VALUES ('var-conflict','proc-conflict',NULL,NULL,'user','string',NULL,'user-conflict','tenant-b')");
    local_smoke_throws(
        static fn () => AssigneeRepair::audit(
            $pdo,
            $targetDatabase,
            1,
            ['proc-conflict' => 'tenant-a']
        ),
        'conflicting inferred tenant evidence was accepted for assignee repair'
    );
    local_smoke_assert(
        (string)$pdo->query("SELECT COALESCE(ASSIGNEE_,'') FROM {$db}.act_ru_task WHERE ID_='task-conflict'")->fetchColumn() === '',
        'rejected inferred-tenant conflict changed the task assignee'
    );

    $quarantineManager->assertDetachedOperationLogQuarantine($detachedOperationLogPlan);
    $quarantineManager->assertDetachedBytearrayQuarantine($detachedPlan);

    require dirname(__DIR__) . '/vendor/autoload.php';
    $app = new think\App(dirname(__DIR__));
    $app->initialize();
    installer_target_configure([
        'hostname' => $dsnHost,
        'hostport' => $port,
        'username' => $user,
        'password' => $password,
        'database' => $targetDatabase,
        'charset' => 'utf8mb4',
    ]);
    $frameworkConnection = think\facade\Db::connect();
    local_smoke_assert(
        (string)$frameworkConnection->query('SELECT DATABASE() AS DB_NAME')[0]['DB_NAME'] === $targetDatabase,
        'installer target connection was not pinned to the temporary database'
    );
    $frameworkConnection->close();
    $frameworkConnection = null;

    $summary = [
        'status' => 'passed',
        'hostPolicy' => 'loopback-only',
        'temporarySchemasRemoved' => true,
        'quarantinedTasks' => 1,
        'orphanBinaryDetectionVerified' => true,
        'orphanBytearrayReferencesRejected' => true,
        'orphanRootOnlyHistoryReferencesRejected' => true,
        'payloadConsumerColumnsTested' => count($consumerSpecs),
        'unreviewedForeignKeyConsumerRejected' => true,
        'orphanIsolationRollbackVerified' => true,
        'detachedOperationLogsQuarantined' => 1,
        'detachedOperationLogRollbackVerified' => true,
        'detachedOperationLogFinalIsolationVerified' => true,
        'detachedOperationLogPlanDriftRejected' => true,
        'detachedOperationLogReferenceAndSupportGatesVerified' => true,
        'detachedBytearraysQuarantined' => 2,
        'businessLinkedDetachedBytearraysQuarantined' => 1,
        'detachedIsolationRollbackVerified' => true,
        'detachedFinalIsolationVerified' => true,
        'detachedBytearrayPlanDriftRejected' => true,
        'deploymentBytearrayIsolationRejected' => true,
        'referencedBytearrayIsolationRejected' => true,
        'workflowTenantRowsRepaired' => 24,
        'workflowTenantScopedIsolationIgnoreVerified' => true,
        'operationLogScopedIsolationIgnoreVerified' => true,
        'workflowTenantRepairIdempotent' => true,
        'workflowTenantRepairRollbackVerified' => true,
        'workflowTenantFrozenTotalsVerified' => true,
        'workflowTenantReferenceMappingVerified' => true,
        'workflowTenantNullReferenceFilteringVerified' => true,
        'workflowTenantFilteringVerified' => true,
        'workflowTenantConflictsRejected' => true,
        'assigneesRepaired' => 2,
        'assigneeLockedPlanVerified' => true,
        'assigneeRollbackVerified' => true,
        'assigneeHistoryBindingVerified' => true,
        'assigneeNullAmbiguityRejected' => true,
        'assigneeBinaryIdentityVerified' => true,
        'ambiguousAssigneeRepairRejected' => true,
        'conflictingTenantEvidenceRejected' => true,
    ];
} finally {
    if (is_object($frameworkConnection) && method_exists($frameworkConnection, 'close')) {
        try {
            $frameworkConnection->close();
        } catch (Throwable) {
            // Continue to the guarded schema cleanup below.
        }
        $frameworkConnection = null;
        gc_collect_cycles();
    }
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
