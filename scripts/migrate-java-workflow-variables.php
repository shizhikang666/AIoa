<?php

declare(strict_types=1);

use app\support\migration\PdoWorkflowVariableMigrationStore;
use app\support\migration\WorkflowVariableMigrationException;
use app\support\migration\WorkflowVariableMigrationService;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/support/migration/WorkflowVariableMigrationException.php';
require dirname(__DIR__) . '/app/support/migration/WorkflowVariableMigrationStore.php';
require dirname(__DIR__) . '/app/support/migration/JavaSerializationDecoder.php';
require dirname(__DIR__) . '/app/support/migration/PdoWorkflowVariableMigrationStore.php';
require dirname(__DIR__) . '/app/support/migration/WorkflowVariableMigrationService.php';

foreach (array_slice($argv, 1) as $rawArgument) {
    if ($rawArgument === '--password' || str_starts_with($rawArgument, '--password=')) {
        fwrite(STDERR, "workflow variable migration failed: PLAINTEXT_PASSWORD_OPTION_REJECTED\n");
        exit(1);
    }
    if (str_starts_with($rawArgument, '--apply=')) {
        fwrite(STDERR, "workflow variable migration failed: APPLY_FLAG_FORMAT_REJECTED\n");
        exit(1);
    }
}

function workflowVariableMigrationUsage(): string
{
    return <<<'TEXT'
Usage:
  php scripts/migrate-java-workflow-variables.php \
    --database=oa2026_migrated \
    --confirm-target=oa2026_migrated \
    --user=MIGRATION_DB_USER [--password-env=OA_MIGRATION_DB_PASSWORD] [--apply]

Safety:
  - Default mode is dry-run. Only --apply writes.
  - The target name must end in _migrated, _migration, or _rehearsal.
  - --confirm-target must exactly repeat --database.
  - Remote hosts are refused unless --allow-remote-target is explicit.
  - Plaintext password command-line options are intentionally unsupported.
TEXT;
}

$options = getopt('', [
    'database:',
    'confirm-target:',
    'host:',
    'port:',
    'user:',
    'password-env:',
    'apply',
    'allow-remote-target',
    'help',
]);

if (isset($options['help'])) {
    fwrite(STDOUT, workflowVariableMigrationUsage() . PHP_EOL);
    exit(0);
}

try {
    $database = trim((string)($options['database'] ?? ''));
    $confirmation = trim((string)($options['confirm-target'] ?? ''));
    if ($database === '' || !preg_match('/^[A-Za-z0-9_]+$/', $database)) {
        throw new WorkflowVariableMigrationException('TARGET_DATABASE_REQUIRED');
    }
    if (!preg_match('/_(?:migrated|migration|rehearsal)(?:_[A-Za-z0-9]+)*$/i', $database)) {
        throw new WorkflowVariableMigrationException('TARGET_DATABASE_SUFFIX_REJECTED');
    }
    if ($confirmation !== $database) {
        throw new WorkflowVariableMigrationException('TARGET_DATABASE_CONFIRMATION_MISMATCH');
    }

    $host = strtolower(trim((string)($options['host'] ?? '127.0.0.1')));
    $normalizedHost = $host === '[::1]' ? '::1' : $host;
    if ($normalizedHost !== '::1' && !preg_match('/^[a-z0-9.-]+$/', $normalizedHost)) {
        throw new WorkflowVariableMigrationException('TARGET_HOST_REJECTED');
    }
    $localHosts = ['127.0.0.1', 'localhost', '::1'];
    if (!in_array($normalizedHost, $localHosts, true) && !isset($options['allow-remote-target'])) {
        throw new WorkflowVariableMigrationException('REMOTE_TARGET_REQUIRES_EXPLICIT_FLAG');
    }
    $dsnHost = $normalizedHost === '::1' ? '[::1]' : $normalizedHost;
    $port = (int)($options['port'] ?? 3306);
    if ($port < 1 || $port > 65535) {
        throw new WorkflowVariableMigrationException('TARGET_PORT_REJECTED');
    }

    $user = trim((string)($options['user'] ?? (getenv('OA_MIGRATION_DB_USER') ?: '')));
    if ($user === '') {
        throw new WorkflowVariableMigrationException('TARGET_DATABASE_USER_REQUIRED');
    }
    $passwordEnv = trim((string)($options['password-env'] ?? 'OA_MIGRATION_DB_PASSWORD'));
    if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $passwordEnv)) {
        throw new WorkflowVariableMigrationException('PASSWORD_ENV_NAME_REJECTED');
    }
    $passwordValue = getenv($passwordEnv);
    $password = $passwordValue === false ? '' : $passwordValue;

    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dsnHost, $port, $database),
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (Throwable) {
        throw new WorkflowVariableMigrationException('TARGET_DATABASE_CONNECTION_FAILED');
    } finally {
        $password = '';
        $passwordValue = false;
    }

    $service = new WorkflowVariableMigrationService(
        new PdoWorkflowVariableMigrationStore($pdo, $database)
    );
    $summary = $service->run(isset($options['apply']));
    fwrite(STDOUT, json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
} catch (WorkflowVariableMigrationException $exception) {
    fwrite(STDERR, 'workflow variable migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "workflow variable migration failed: UNEXPECTED_FAILURE\n");
    exit(1);
}
