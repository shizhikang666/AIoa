param(
    [string]$RuntimeRoot = 'F:\project\socket\AI\testPhp\files',
    [string]$ExpectedDatabase = 'phpoa20026'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $ProjectRoot

function Invoke-TestStep {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][scriptblock]$Action
    )

    Write-Host "[test-agent-db] running: $Name"
    & $Action
    Write-Host "[test-agent-db] passed: $Name"
}

function Get-EnvMap {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        throw "Missing local env file: $Path"
    }

    $map = @{}
    foreach ($line in Get-Content -LiteralPath $Path) {
        $trimmed = $line.Trim()
        if ($trimmed -eq '' -or $trimmed.StartsWith('#')) {
            continue
        }

        $parts = $trimmed -split '=', 2
        if ($parts.Count -ne 2) {
            continue
        }

        $key = $parts[0].Trim()
        $value = $parts[1].Trim()
        if (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'"))) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        $map[$key] = $value
    }

    return $map
}

function Get-EnvValue {
    param(
        [Parameter(Mandatory = $true)][hashtable]$EnvMap,
        [Parameter(Mandatory = $true)][string]$Key,
        [string]$Default = ''
    )

    if ($EnvMap.ContainsKey($Key) -and [string]$EnvMap[$Key] -ne '') {
        return [string]$EnvMap[$Key]
    }

    return $Default
}

function Invoke-External {
    param(
        [Parameter(Mandatory = $true)][string]$FilePath,
        [Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments
    )

    & $FilePath @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Command failed: $FilePath $($Arguments -join ' ')"
    }
}

$envPath = Join-Path $ProjectRoot '.env'
$localEnv = Get-EnvMap -Path $envPath

$dbHost = Get-EnvValue -EnvMap $localEnv -Key 'DB_HOST' -Default '127.0.0.1'
$dbPort = Get-EnvValue -EnvMap $localEnv -Key 'DB_PORT' -Default '3306'
$dbName = Get-EnvValue -EnvMap $localEnv -Key 'DB_NAME' -Default ''
$dbUser = Get-EnvValue -EnvMap $localEnv -Key 'DB_USER' -Default 'root'
$dbPass = Get-EnvValue -EnvMap $localEnv -Key 'DB_PASS' -Default ''
$redisHost = Get-EnvValue -EnvMap $localEnv -Key 'REDIS_HOST' -Default '127.0.0.1'
$redisPort = Get-EnvValue -EnvMap $localEnv -Key 'REDIS_PORT' -Default '6379'
$redisPass = Get-EnvValue -EnvMap $localEnv -Key 'REDIS_PASSWD' -Default (Get-EnvValue -EnvMap $localEnv -Key 'REDIS_PASSWORD' -Default '')
$localSmokeAccount = Get-EnvValue -EnvMap $localEnv -Key 'LOCAL_SUPER_ADMIN_ACCOUNT' -Default 'bizAdmin'

if ($dbName -eq '') {
    throw 'DB_NAME is required in .env'
}

if ($dbName -ne $ExpectedDatabase) {
    throw "Expected DB_NAME=$ExpectedDatabase, got $dbName"
}

$mysql = Join-Path $RuntimeRoot 'tools\mysql\bin\mysql.exe'
$redisCli = Join-Path $RuntimeRoot 'tools\redis\redis-cli.exe'
if (-not (Test-Path -LiteralPath $mysql)) {
    throw "Missing bundled MySQL client: $mysql"
}
if (-not (Test-Path -LiteralPath $redisCli)) {
    throw "Missing bundled Redis client: $redisCli"
}

Write-Host "[test-agent-db] DB target: $dbHost`:$dbPort/$dbName as $dbUser"
Write-Host "[test-agent-db] Redis target: $redisHost`:$redisPort"

Invoke-TestStep 'phpoa20026 table count' {
    $oldMysqlPwd = $env:MYSQL_PWD
    try {
        $env:MYSQL_PWD = $dbPass
        $query = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$dbName';"
        $output = & $mysql --batch --skip-column-names --host=$dbHost --port=$dbPort --user=$dbUser $dbName --execute=$query 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "mysql table count query failed: $output"
        }

        $tableCount = [int](([string]($output | Select-Object -Last 1)).Trim())
        if ($tableCount -le 0) {
            throw "Expected $dbName table count > 0, got $tableCount"
        }

        Write-Host "[test-agent-db] table count: $tableCount"
    } finally {
        $env:MYSQL_PWD = $oldMysqlPwd
    }
}

Invoke-TestStep 'Redis ping' {
    $redisArgs = @('-h', $redisHost, '-p', $redisPort)
    if ($redisPass -ne '') {
        $redisArgs += @('-a', $redisPass)
    }
    $redisArgs += 'PING'

    $output = & $redisCli @redisArgs 2>&1
    $safeOutput = [string]($output | Out-String)
    if ($redisPass -ne '') {
        $safeOutput = $safeOutput.Replace($redisPass, '[redacted]')
    }
    $safeOutput = $safeOutput.Trim()
    if ($LASTEXITCODE -ne 0) {
        throw "redis ping failed: $safeOutput"
    }

    $pong = $output |
        ForEach-Object { [string]$_ } |
        Where-Object { $_.Trim() -ne '' -and -not $_.Trim().StartsWith('Warning:') } |
        Select-Object -Last 1
    if ([string]$pong -ne 'PONG') {
        throw "Expected Redis PING to return PONG, got $safeOutput"
    }
}

Invoke-TestStep 'DevFileService local download' {
$probe = @'
<?php

require getcwd() . '/vendor/autoload.php';

$app = (new think\App(getcwd()))->initialize();

$prefix = 'CODEX_DEV_FILE_' . date('YmdHis') . random_int(1000, 9999);
$baseId = 600000000000000000 + random_int(1000000, 9999999);
$localId = (string)$baseId;
$remoteId = (string)($baseId + 1);
$missingId = (string)($baseId + 2);
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '.txt';
$content = "codex dev file smoke\n";
$now = date('Y-m-d H:i:s');

file_put_contents($tmp, $content);

try {
    think\facade\Db::name('dev_file')->insert([
        'ID' => $localId,
        'ENGINE' => 'LOCAL',
        'BUCKET' => 'defaultBucketName',
        'NAME' => $prefix . '.txt',
        'SUFFIX' => 'txt',
        'SIZE_KB' => 1,
        'SIZE_INFO' => '1KB',
        'OBJ_NAME' => $prefix . '.txt',
        'STORAGE_PATH' => $tmp,
        'DOWNLOAD_PATH' => 'https://old.example/backend/dev/file/download?id=' . $localId,
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => $now,
        'CREATE_USER' => 'codex-smoke',
        'TENANT_ID' => '1',
    ]);
    think\facade\Db::name('dev_file')->insert([
        'ID' => $remoteId,
        'ENGINE' => 'ALIYUN',
        'BUCKET' => 'remote',
        'NAME' => $prefix . '-remote.txt',
        'SUFFIX' => 'txt',
        'STORAGE_PATH' => 'https://remote.example/file.txt',
        'DOWNLOAD_PATH' => 'https://remote.example/file.txt',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => $now,
        'CREATE_USER' => 'codex-smoke',
        'TENANT_ID' => '1',
    ]);
    think\facade\Db::name('dev_file')->insert([
        'ID' => $missingId,
        'ENGINE' => 'LOCAL',
        'BUCKET' => 'defaultBucketName',
        'NAME' => $prefix . '-missing.txt',
        'SUFFIX' => 'txt',
        'STORAGE_PATH' => $tmp . '.missing',
        'DOWNLOAD_PATH' => 'https://old.example/backend/dev/file/download?id=' . $missingId,
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => $now,
        'CREATE_USER' => 'codex-smoke',
        'TENANT_ID' => '1',
    ]);

    $service = new app\service\dev\FileService();
    $download = $service->download($localId);
    if (($download['filename'] ?? '') !== $prefix . '.txt') {
        throw new RuntimeException('download filename mismatch');
    }
    if (($download['content'] ?? '') !== $content) {
        throw new RuntimeException('download content mismatch');
    }
    if (($download['contentType'] ?? '') !== 'application/octet-stream;charset=UTF-8') {
        throw new RuntimeException('download content type mismatch');
    }

    $detail = $service->detail($localId);
    if (($detail['downloadPath'] ?? '') !== '/api/dev/file/download?id=' . rawurlencode($localId)) {
        throw new RuntimeException('local detail downloadPath was not normalized');
    }

    $remoteDetail = $service->detail($remoteId);
    if (($remoteDetail['downloadPath'] ?? '') !== 'https://remote.example/file.txt') {
        throw new RuntimeException('remote detail downloadPath should be preserved');
    }

    foreach ([$remoteId, $missingId, (string)($baseId + 3)] as $id) {
        $failed = false;
        try {
            $service->download($id);
        } catch (RuntimeException $exception) {
            $failed = $exception->getCode() === 500 && $exception->getMessage() !== '';
        }
        if (!$failed) {
            throw new RuntimeException('expected download failure for ' . $id);
        }
    }

    echo "DevFileService local download checks passed\n";
} finally {
    think\facade\Db::name('dev_file')->whereIn('ID', [$localId, $remoteId, $missingId])->delete();
    if (is_file($tmp)) {
        @unlink($tmp);
    }
}
'@

    $output = $probe | & php 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "php DevFileService download probe failed: $output"
    }

    Write-Host ([string]($output | Out-String)).Trim()
}

Invoke-TestStep 'DevFileService local upload' {
$probe = @'
<?php

require getcwd() . '/vendor/autoload.php';

$app = (new think\App(getcwd()))->initialize();

$service = new app\service\dev\FileService();
$payload = [
    'user_id' => 'codex-smoke',
    'tenant_id' => '1',
];
$prefix = 'CODEX_UPLOAD_' . date('YmdHis') . random_int(1000, 9999);
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '.txt';
$content = "codex upload smoke\n";
$createdIds = [];
$storedPaths = [];

file_put_contents($tmp, $content);

function smoke_uploaded_file(string $path, string $name): think\file\UploadedFile {
    return new think\file\UploadedFile($path, $name, 'text/plain', UPLOAD_ERR_OK, true);
}

try {
    $file = $service->uploadReturnFile('LOCAL', smoke_uploaded_file($tmp, $prefix . '.txt'), $payload);
    $createdIds[] = (string)$file['id'];
    $storedPaths[] = (string)$file['storagePath'];
    if (($file['engine'] ?? '') !== 'LOCAL') {
        throw new RuntimeException('upload file engine mismatch');
    }
    if (($file['bucket'] ?? '') !== 'defaultBucketName') {
        throw new RuntimeException('upload file bucket mismatch');
    }
    if (($file['name'] ?? '') !== $prefix . '.txt') {
        throw new RuntimeException('upload file name mismatch');
    }
    if (($file['suffix'] ?? '') !== 'txt') {
        throw new RuntimeException('upload suffix mismatch');
    }
    if (($file['downloadPath'] ?? '') !== '/api/dev/file/download?id=' . rawurlencode((string)$file['id'])) {
        throw new RuntimeException('upload downloadPath mismatch');
    }
    if (!is_file((string)$file['storagePath'])) {
        throw new RuntimeException('uploaded file missing on disk');
    }

    $download = $service->download((string)$file['id']);
    if (($download['content'] ?? '') !== $content) {
        throw new RuntimeException('uploaded file download content mismatch');
    }

    $id = $service->uploadReturnId('LOCAL', smoke_uploaded_file($tmp, $prefix . '-id.txt'), $payload);
    $createdIds[] = $id;
    $idRow = think\facade\Db::name('dev_file')->where('ID', $id)->find();
    if (!is_array($idRow) || $idRow === []) {
        throw new RuntimeException('uploadReturnId row missing');
    }
    $storedPaths[] = (string)$idRow['STORAGE_PATH'];

    $url = $service->uploadReturnUrl(null, smoke_uploaded_file($tmp, $prefix . '-dynamic.txt'), $payload);
    $dynamicId = substr($url, strrpos($url, '=') + 1);
    $createdIds[] = $dynamicId;
    $dynamicRow = think\facade\Db::name('dev_file')->where('ID', $dynamicId)->find();
    if (!is_array($dynamicRow) || $dynamicRow === []) {
        throw new RuntimeException('dynamic upload row missing');
    }
    $storedPaths[] = (string)$dynamicRow['STORAGE_PATH'];
    if ($url !== '/api/dev/file/download?id=' . rawurlencode($dynamicId)) {
        throw new RuntimeException('uploadReturnUrl mismatch');
    }

    $unsupported = false;
    try {
        $service->uploadReturnUrl('ALIYUN', smoke_uploaded_file($tmp, $prefix . '-cloud.txt'), $payload);
    } catch (RuntimeException $exception) {
        $unsupported = $exception->getCode() === 501;
    }
    if (!$unsupported) {
        throw new RuntimeException('cloud upload should be unsupported in this slice');
    }

    echo "DevFileService local upload checks passed\n";
} finally {
    if ($createdIds !== []) {
        think\facade\Db::name('dev_file')->whereIn('ID', $createdIds)->delete();
    }
    foreach ($storedPaths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    if (is_file($tmp)) {
        @unlink($tmp);
    }
}
'@

    $output = $probe | & php 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "php DevFileService upload probe failed: $output"
    }

    Write-Host ([string]($output | Out-String)).Trim()
}

Invoke-TestStep 'DevFileService logical delete' {
$probe = @'
<?php

require getcwd() . '/vendor/autoload.php';

$app = (new think\App(getcwd()))->initialize();

$service = new app\service\dev\FileService();
$prefix = 'CODEX_DELETE_' . date('YmdHis') . random_int(1000, 9999);
$baseId = 600100000000000000 + random_int(1000000, 9999999);
$localId = (string)$baseId;
$cloudId = (string)($baseId + 1);
$otherTenantId = (string)($baseId + 2);
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '.txt';
$now = date('Y-m-d H:i:s');

file_put_contents($tmp, "codex delete smoke\n");

try {
    think\facade\Db::name('dev_file')->insertAll([
        [
            'ID' => $localId,
            'ENGINE' => 'LOCAL',
            'BUCKET' => 'defaultBucketName',
            'NAME' => $prefix . '.txt',
            'SUFFIX' => 'txt',
            'SIZE_KB' => 1,
            'SIZE_INFO' => '1KB',
            'OBJ_NAME' => $prefix . '.txt',
            'STORAGE_PATH' => $tmp,
            'DOWNLOAD_PATH' => '/api/dev/file/download?id=' . $localId,
            'DELETE_FLAG' => 'NOT_DELETE',
            'CREATE_TIME' => $now,
            'CREATE_USER' => 'codex-smoke',
            'TENANT_ID' => '1',
        ],
        [
            'ID' => $cloudId,
            'ENGINE' => 'ALIYUN',
            'BUCKET' => 'remote',
            'NAME' => $prefix . '-cloud.txt',
            'SUFFIX' => 'txt',
            'SIZE_KB' => 1,
            'SIZE_INFO' => '1KB',
            'OBJ_NAME' => $prefix . '-cloud.txt',
            'STORAGE_PATH' => 'https://remote.example/file.txt',
            'DOWNLOAD_PATH' => 'https://remote.example/file.txt',
            'DELETE_FLAG' => 'NOT_DELETE',
            'CREATE_TIME' => $now,
            'CREATE_USER' => 'codex-smoke',
            'TENANT_ID' => '1',
        ],
        [
            'ID' => $otherTenantId,
            'ENGINE' => 'LOCAL',
            'BUCKET' => 'defaultBucketName',
            'NAME' => $prefix . '-tenant2.txt',
            'SUFFIX' => 'txt',
            'SIZE_KB' => 1,
            'SIZE_INFO' => '1KB',
            'OBJ_NAME' => $prefix . '-tenant2.txt',
            'STORAGE_PATH' => $tmp,
            'DOWNLOAD_PATH' => '/api/dev/file/download?id=' . $otherTenantId,
            'DELETE_FLAG' => 'NOT_DELETE',
            'CREATE_TIME' => $now,
            'CREATE_USER' => 'codex-smoke',
            'TENANT_ID' => '2',
        ],
    ]);

    $service->delete([$localId, $cloudId, $otherTenantId], [
        'user_id' => 'codex-delete-smoke',
        'tenant_id' => '1',
    ]);

    foreach ([$localId, $cloudId] as $id) {
        $row = think\facade\Db::name('dev_file')->where('ID', $id)->find();
        if (($row['DELETE_FLAG'] ?? '') !== 'DELETED') {
            throw new RuntimeException('expected logical delete for ' . $id);
        }
        if (($row['UPDATE_USER'] ?? '') !== 'codex-delete-smoke') {
            throw new RuntimeException('delete update user mismatch for ' . $id);
        }
    }
    $otherFlag = think\facade\Db::name('dev_file')->where('ID', $otherTenantId)->value('DELETE_FLAG');
    if ($otherFlag !== 'NOT_DELETE') {
        throw new RuntimeException('cross-tenant delete should not update the row');
    }
    if (!is_file($tmp)) {
        throw new RuntimeException('logical delete should not remove physical file');
    }
    if ($service->detail($localId) !== null) {
        throw new RuntimeException('deleted file should be hidden from detail');
    }

    foreach ([
        [[], ['tenant_id' => '1'], 'empty delete payload should fail'],
        [[$otherTenantId], [], 'missing tenant should fail'],
    ] as $case) {
        $failed = false;
        try {
            $service->delete($case[0], $case[1]);
        } catch (RuntimeException $exception) {
            $failed = $exception->getCode() === 400;
        }
        if (!$failed) {
            throw new RuntimeException($case[2]);
        }
    }

    echo "DevFileService logical delete checks passed\n";
} finally {
    think\facade\Db::name('dev_file')->whereIn('ID', [$localId, $cloudId, $otherTenantId])->delete();
    if (is_file($tmp)) {
        @unlink($tmp);
    }
}
'@

    $output = $probe | & php 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "php DevFileService delete probe failed: $output"
    }

    Write-Host ([string]($output | Out-String)).Trim()
}

Invoke-TestStep 'Dev email and SMS logical delete' {
$probe = @'
<?php
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
$emailService = new app\service\dev\EmailService();
$smsService = new app\service\dev\SmsService();
$prefix = 'CODEX_DEV_NOTIFY_' . date('YmdHis') . random_int(1000, 9999);
$base = 600200000000000000 + random_int(1000000, 9999999);
$emailId = (string)$base;
$emailTenant2 = (string)($base + 1);
$smsId = (string)($base + 2);
$smsTenant2 = (string)($base + 3);
$now = date('Y-m-d H:i:s');
$payload = ['user_id' => 'codex-notify-smoke', 'tenant_id' => '1'];
try {
    think\facade\Db::name('dev_email')->insert([
        'ID' => $emailId, 'ENGINE' => 'LOCAL', 'SEND_ACCOUNT' => 'codex@example.invalid',
        'SEND_USER' => 'codex', 'RECEIVE_ACCOUNTS' => 'receiver@example.invalid',
        'SUBJECT' => $prefix . ' email', 'CONTENT' => 'codex email smoke',
        'DELETE_FLAG' => 'NOT_DELETE', 'CREATE_TIME' => $now, 'CREATE_USER' => 'codex-smoke', 'TENANT_ID' => '1',
    ]);
    think\facade\Db::name('dev_email')->insert([
        'ID' => $emailTenant2, 'ENGINE' => 'LOCAL', 'SEND_ACCOUNT' => 'codex@example.invalid',
        'SEND_USER' => 'codex', 'RECEIVE_ACCOUNTS' => 'receiver@example.invalid',
        'SUBJECT' => $prefix . ' tenant2', 'CONTENT' => 'codex email smoke',
        'DELETE_FLAG' => 'NOT_DELETE', 'CREATE_TIME' => $now, 'CREATE_USER' => 'codex-smoke', 'TENANT_ID' => '2',
    ]);
    think\facade\Db::name('dev_sms')->insert([
        'ID' => $smsId, 'ENGINE' => 'ALIYUN', 'PHONE_NUMBERS' => '13800138000',
        'SIGN_NAME' => $prefix, 'TEMPLATE_CODE' => 'CODEX_TEMPLATE', 'TEMPLATE_PARAM' => '{}',
        'DELETE_FLAG' => 'NOT_DELETE', 'CREATE_TIME' => $now, 'CREATE_USER' => 'codex-smoke', 'TENANT_ID' => '1',
    ]);
    think\facade\Db::name('dev_sms')->insert([
        'ID' => $smsTenant2, 'ENGINE' => 'ALIYUN', 'PHONE_NUMBERS' => '13800138001',
        'SIGN_NAME' => $prefix, 'TEMPLATE_CODE' => 'CODEX_TEMPLATE_2', 'TEMPLATE_PARAM' => '{}',
        'DELETE_FLAG' => 'NOT_DELETE', 'CREATE_TIME' => $now, 'CREATE_USER' => 'codex-smoke', 'TENANT_ID' => '2',
    ]);
    $emailService->delete([$emailId, $emailTenant2], $payload);
    $smsService->delete([$smsId, $smsTenant2], $payload);
    $ok = (string)think\facade\Db::name('dev_email')->where('ID', $emailId)->value('DELETE_FLAG') == 'DELETED';
    if ($ok == false) { throw new RuntimeException('email delete failed'); }
    $ok = (string)think\facade\Db::name('dev_sms')->where('ID', $smsId)->value('DELETE_FLAG') == 'DELETED';
    if ($ok == false) { throw new RuntimeException('sms delete failed'); }
    $ok = (string)think\facade\Db::name('dev_email')->where('ID', $emailTenant2)->value('DELETE_FLAG') == 'NOT_DELETE';
    if ($ok == false) { throw new RuntimeException('cross-tenant email delete failed'); }
    $ok = (string)think\facade\Db::name('dev_sms')->where('ID', $smsTenant2)->value('DELETE_FLAG') == 'NOT_DELETE';
    if ($ok == false) { throw new RuntimeException('cross-tenant sms delete failed'); }
    $ok = $emailService->detail($emailId, '1') == null;
    if ($ok == false) { throw new RuntimeException('deleted email detail visible'); }
    $ok = $smsService->detail($smsId, '1') == null;
    if ($ok == false) { throw new RuntimeException('deleted sms detail visible'); }
    $ok = (int)$emailService->page(['searchKey' => $prefix], '1')['total'] == 0;
    if ($ok == false) { throw new RuntimeException('deleted email page visible'); }
    $ok = (int)$smsService->page(['searchKey' => '13800138000'], '1')['total'] == 0;
    if ($ok == false) { throw new RuntimeException('deleted sms page visible'); }
    $failed = false;
    try { $emailService->delete([], ['tenant_id' => '1']); } catch (RuntimeException $exception) { $failed = true; }
    if ($failed == false) { throw new RuntimeException('empty email delete payload should fail'); }
    $failed = false;
    try { $smsService->delete([], ['tenant_id' => '1']); } catch (RuntimeException $exception) { $failed = true; }
    if ($failed == false) { throw new RuntimeException('empty sms delete payload should fail'); }
    echo "Dev email/SMS logical delete checks passed\n";
} finally {
    think\facade\Db::name('dev_email')->whereIn('ID', array($emailId, $emailTenant2))->delete();
    think\facade\Db::name('dev_sms')->whereIn('ID', array($smsId, $smsTenant2))->delete();
}
'@
    $tmpProbe = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-dev-email-sms-delete-{0}.php" -f ([Guid]::NewGuid().ToString('N')))
    try {
        [System.IO.File]::WriteAllText($tmpProbe, $probe, [System.Text.UTF8Encoding]::new($false))
        $output = & php $tmpProbe 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "php Dev email/SMS delete probe failed: $output"
        }
        Write-Host ([string]($output | Out-String)).Trim()
    } finally {
        if (Test-Path -LiteralPath $tmpProbe) {
            Remove-Item -LiteralPath $tmpProbe -Force
        }
    }
}

Invoke-TestStep 'DevConfigService BIZ_DEFINE writes' {
$probe = @'
<?php
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
$service = new app\service\dev\ConfigService();
$prefix = 'CODEX_CONFIG_' . date('YmdHis') . random_int(1000, 9999);
$key = $prefix . '_KEY';
$secretKey = $prefix . '_SECRET';
$payload = ['user_id' => 'codex-config-smoke'];
$created = [];
$sysId = '600500000000000000' . random_int(10, 99);
try {
    $row = $service->add(['configKey' => $key, 'configValue' => 'value-a', 'remark' => 'codex config smoke', 'sortCode' => 99], $payload);
    $id = (string)$row['id'];
    $created[] = $id;
    if ($row['category'] !== 'BIZ_DEFINE') { throw new RuntimeException('add should force BIZ_DEFINE'); }
    $failed = false;
    try { $service->add(['configKey' => $key, 'configValue' => 'value-b', 'sortCode' => 99], $payload); } catch (RuntimeException $exception) { $failed = $exception->getCode() === 400; }
    if (!$failed) { throw new RuntimeException('duplicate config key should fail'); }
    $edited = $service->edit(['id' => $id, 'configKey' => $key . '_EDITED', 'configValue' => 'value-c', 'remark' => 'edited', 'sortCode' => 88], $payload);
    if ($edited['configKey'] !== $key . '_EDITED' || $edited['configValue'] !== 'value-c' || (int)$edited['sortCode'] !== 88) { throw new RuntimeException('edit failed'); }
    $secret = $service->add(['configKey' => $secretKey, 'configValue' => 'secret-value', 'sortCode' => 77], $payload);
    $secretId = (string)$secret['id'];
    $created[] = $secretId;
    if ($secret['configValue'] !== '******' || $secret['sensitive'] !== true) { throw new RuntimeException('sensitive add should mask value'); }
    $service->edit(['id' => $secretId, 'configKey' => $secretKey, 'configValue' => '******', 'sortCode' => 76], $payload);
    $storedSecret = (string)think\facade\Db::name('dev_config')->where('ID', $secretId)->value('CONFIG_VALUE');
    if ($storedSecret !== 'secret-value') { throw new RuntimeException('masked edit should preserve stored secret'); }
    think\facade\Db::name('dev_config')->insert(['ID' => $sysId, 'CONFIG_KEY' => $prefix . '_SYS', 'CONFIG_VALUE' => 'sys', 'CATEGORY' => 'SYS_BASE', 'SORT_CODE' => 1, 'DELETE_FLAG' => 'NOT_DELETE', 'CREATE_TIME' => date('Y-m-d H:i:s'), 'CREATE_USER' => 'codex-smoke']);
    $failed = false;
    try { $service->delete([$sysId], $payload); } catch (RuntimeException $exception) { $failed = $exception->getCode() === 400; }
    if (!$failed) { throw new RuntimeException('sys config delete should fail'); }
    $service->delete([$id, $secretId], $payload);
    if ((int)$service->page(['searchKey' => $prefix])['total'] !== 0) { throw new RuntimeException('deleted configs should be hidden'); }
    echo "DevConfigService BIZ_DEFINE write checks passed\n";
} finally {
    think\facade\Db::name('dev_config')->whereIn('ID', array_merge($created, [$sysId]))->delete();
}
'@
    $tmpProbe = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-dev-config-write-{0}.php" -f ([Guid]::NewGuid().ToString('N')))
    try {
        [System.IO.File]::WriteAllText($tmpProbe, $probe, [System.Text.UTF8Encoding]::new($false))
        $output = & php $tmpProbe 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "php DevConfigService write probe failed: $output"
        }
        Write-Host ([string]($output | Out-String)).Trim()
    } finally {
        if (Test-Path -LiteralPath $tmpProbe) {
            Remove-Item -LiteralPath $tmpProbe -Force
        }
    }
}

Invoke-TestStep 'DevLogService category delete' {
$probe = @'
<?php
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
$service = new app\service\dev\LogService();
$prefix = 'CODEX_LOG_' . date('YmdHis') . random_int(1000, 9999);
$category = $prefix . '_TARGET';
$otherCategory = $prefix . '_OTHER';
$targetId = '60070000000000' . random_int(100000, 999999);
$sameCategoryOtherTenantId = '60070000000001' . random_int(100000, 999999);
$otherCategoryId = '60070000000002' . random_int(100000, 999999);
$rows = [
    ['ID' => $targetId, 'CATEGORY' => $category, 'NAME' => 'codex target log', 'EXE_STATUS' => 'SUCCESS', 'TENANT_ID' => '1', 'CREATE_TIME' => date('Y-m-d H:i:s'), 'OP_TIME' => date('Y-m-d H:i:s')],
    ['ID' => $sameCategoryOtherTenantId, 'CATEGORY' => $category, 'NAME' => 'codex other tenant log', 'EXE_STATUS' => 'SUCCESS', 'TENANT_ID' => '2', 'CREATE_TIME' => date('Y-m-d H:i:s'), 'OP_TIME' => date('Y-m-d H:i:s')],
    ['ID' => $otherCategoryId, 'CATEGORY' => $otherCategory, 'NAME' => 'codex other category log', 'EXE_STATUS' => 'SUCCESS', 'TENANT_ID' => '1', 'CREATE_TIME' => date('Y-m-d H:i:s'), 'OP_TIME' => date('Y-m-d H:i:s')],
];
try {
    think\facade\Db::name('dev_log')->insertAll($rows);
    $service->delete($category, '1');
    $targetCount = (int)think\facade\Db::name('dev_log')->where('ID', $targetId)->count();
    $sameCategoryOtherTenantCount = (int)think\facade\Db::name('dev_log')->where('ID', $sameCategoryOtherTenantId)->count();
    $otherCategoryCount = (int)think\facade\Db::name('dev_log')->where('ID', $otherCategoryId)->count();
    if ($targetCount !== 0 || $sameCategoryOtherTenantCount !== 1 || $otherCategoryCount !== 1) {
        throw new RuntimeException('category delete affected unexpected log rows');
    }
    $failed = false;
    try { $service->delete('', '1'); } catch (RuntimeException $exception) { $failed = $exception->getCode() === 400; }
    if (!$failed) { throw new RuntimeException('empty category should fail'); }
    echo "DevLogService category delete checks passed\n";
} finally {
    think\facade\Db::name('dev_log')->whereIn('ID', [$targetId, $sameCategoryOtherTenantId, $otherCategoryId])->delete();
}
'@
    $tmpProbe = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-dev-log-delete-{0}.php" -f ([Guid]::NewGuid().ToString('N')))
    try {
        [System.IO.File]::WriteAllText($tmpProbe, $probe, [System.Text.UTF8Encoding]::new($false))
        $output = & php $tmpProbe 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "php DevLogService delete probe failed: $output"
        }
        Write-Host ([string]($output | Out-String)).Trim()
    } finally {
        if (Test-Path -LiteralPath $tmpProbe) {
            Remove-Item -LiteralPath $tmpProbe -Force
        }
    }
}

Invoke-TestStep 'DevJobService logical delete' {
$probe = @'
<?php
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
$service = new app\service\dev\JobService();
$prefix = 'CODEX_JOB_' . date('YmdHis') . random_int(1000, 9999);
$jobId = '60090000000000' . random_int(100000, 999999);
$otherId = '60090000000001' . random_int(100000, 999999);
$payload = ['user_id' => 'codex-job-smoke'];
$rows = [
    ['ID' => $jobId, 'NAME' => $prefix . '_TARGET', 'CODE' => $prefix . '_TARGET', 'CATEGORY' => 'LOCAL', 'ACTION_CLASS' => 'codex.TargetJob', 'CRON_EXPRESSION' => '0 0 * * * ?', 'JOB_STATUS' => 'STOPPED', 'SORT_CODE' => 99, 'DELETE_FLAG' => 'NOT_DELETE', 'CREATE_TIME' => date('Y-m-d H:i:s'), 'CREATE_USER' => 'codex-smoke'],
    ['ID' => $otherId, 'NAME' => $prefix . '_OTHER', 'CODE' => $prefix . '_OTHER', 'CATEGORY' => 'LOCAL', 'ACTION_CLASS' => 'codex.OtherJob', 'CRON_EXPRESSION' => '0 0 * * * ?', 'JOB_STATUS' => 'STOPPED', 'SORT_CODE' => 99, 'DELETE_FLAG' => 'NOT_DELETE', 'CREATE_TIME' => date('Y-m-d H:i:s'), 'CREATE_USER' => 'codex-smoke'],
];
try {
    think\facade\Db::name('dev_job')->insertAll($rows);
    $failed = false;
    try { $service->delete([$jobId, '60990000000000999999'], $payload); } catch (RuntimeException $exception) { $failed = $exception->getCode() === 404; }
    if (!$failed) { throw new RuntimeException('bad id set should fail before deleting'); }
    $flag = (string)think\facade\Db::name('dev_job')->where('ID', $jobId)->value('DELETE_FLAG');
    if ($flag !== 'NOT_DELETE') { throw new RuntimeException('bad id set should not partially delete'); }
    $service->delete([$jobId], $payload);
    $deletedFlag = (string)think\facade\Db::name('dev_job')->where('ID', $jobId)->value('DELETE_FLAG');
    $otherFlag = (string)think\facade\Db::name('dev_job')->where('ID', $otherId)->value('DELETE_FLAG');
    if ($deletedFlag !== 'DELETED' || $otherFlag !== 'NOT_DELETE') { throw new RuntimeException('job delete flag mismatch'); }
    if ((int)$service->page(['searchKey' => $prefix])['total'] !== 1) { throw new RuntimeException('deleted job should be hidden from page'); }
    echo "DevJobService logical delete checks passed\n";
} finally {
    think\facade\Db::name('dev_job')->whereIn('ID', [$jobId, $otherId])->delete();
}
'@
    $tmpProbe = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-dev-job-delete-{0}.php" -f ([Guid]::NewGuid().ToString('N')))
    try {
        [System.IO.File]::WriteAllText($tmpProbe, $probe, [System.Text.UTF8Encoding]::new($false))
        $output = & php $tmpProbe 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "php DevJobService delete probe failed: $output"
        }
        Write-Host ([string]($output | Out-String)).Trim()
    } finally {
        if (Test-Path -LiteralPath $tmpProbe) {
            Remove-Item -LiteralPath $tmpProbe -Force
        }
    }
}

Invoke-TestStep 'GenConfigService editBatch' {
$probe = @'
<?php

require getcwd() . '/vendor/autoload.php';

(new think\App(getcwd()))->initialize();

$service = new app\service\gen\ConfigService();
$prefix = 'CODEX_GEN_CONFIG_' . date('YmdHis') . random_int(1000, 9999);
$baseId = '601000000000' . random_int(100000, 999999);
$idA = $baseId;
$idB = (string)((int)$baseId + 1);
$deletedId = (string)((int)$baseId + 2);
$basicId = '601100000000' . random_int(100000, 999999);
$now = date('Y-m-d H:i:s');

function gen_config_smoke_row(string $id, string $basicId, string $name, string $deleteFlag, string $now): array {
    return [
        'ID' => $id,
        'BASIC_ID' => $basicId,
        'IS_TABLE_KEY' => 'N',
        'FIELD_NAME' => $name,
        'FIELD_REMARK' => $name . ' remark',
        'FIELD_TYPE' => 'varchar(255)',
        'FIELD_JAVA_TYPE' => 'String',
        'EFFECT_TYPE' => 'input',
        'DICT_TYPE_CODE' => null,
        'WHETHER_TABLE' => 'Y',
        'WHETHER_RETRACT' => 'N',
        'WHETHER_ADD_UPDATE' => 'Y',
        'WHETHER_REQUIRED' => 'N',
        'QUERY_WHETHER' => 'N',
        'QUERY_TYPE' => null,
        'SORT_CODE' => 10,
        'DELETE_FLAG' => $deleteFlag,
        'CREATE_TIME' => $now,
        'CREATE_USER' => 'codex-smoke',
    ];
}

try {
    think\facade\Db::name('gen_config')->insertAll([
        gen_config_smoke_row($idA, $basicId, $prefix . '_FIELD_A', 'NOT_DELETE', $now),
        gen_config_smoke_row($idB, $basicId, $prefix . '_FIELD_B', 'NOT_DELETE', $now),
        gen_config_smoke_row($deletedId, $basicId, $prefix . '_FIELD_DELETED', 'DELETED', $now),
    ]);

    $service->editBatch([
        [
            'id' => $idA,
            'basicId' => $basicId,
            'isTableKey' => 'N',
            'fieldName' => $prefix . '_FIELD_A',
            'fieldRemark' => $prefix . '_A_EDITED',
            'fieldType' => 'varchar(128)',
            'fieldJavaType' => 'String',
            'effectType' => 'input',
            'dictTypeCode' => '',
            'whetherTable' => 'Y',
            'whetherRetract' => 'Y',
            'whetherAddUpdate' => 'Y',
            'whetherRequired' => 'Y',
            'queryWhether' => 'Y',
            'queryType' => 'like',
            'sortCode' => '',
            'deleteFlag' => 'DELETED',
            'createTime' => '2000-01-01 00:00:00',
            'updateUser' => 'client-spoof',
        ],
        [
            'id' => $idB,
            'basicId' => $basicId,
            'isTableKey' => 'N',
            'fieldName' => $prefix . '_FIELD_B',
            'fieldRemark' => $prefix . '_B_EDITED',
            'fieldType' => 'int',
            'fieldJavaType' => 'Integer',
            'effectType' => 'inputNumber',
            'dictTypeCode' => null,
            'whetherTable' => true,
            'whetherRetract' => false,
            'whetherAddUpdate' => true,
            'whetherRequired' => false,
            'queryWhether' => false,
            'queryType' => null,
            'sortCode' => 22,
        ],
    ], ['user_id' => 'codexGenCfg']);

    $rowA = think\facade\Db::name('gen_config')->where('ID', $idA)->find();
    $rowB = think\facade\Db::name('gen_config')->where('ID', $idB)->find();
    if (($rowA['FIELD_REMARK'] ?? '') !== $prefix . '_A_EDITED' || ($rowA['QUERY_TYPE'] ?? '') !== 'like') {
        throw new RuntimeException('row A did not update expected fields');
    }
    if (($rowA['DICT_TYPE_CODE'] ?? null) !== null || !array_key_exists('SORT_CODE', $rowA) || $rowA['SORT_CODE'] !== null) {
        throw new RuntimeException('optional empty fields were not saved as null');
    }
    if (($rowA['DELETE_FLAG'] ?? '') !== 'NOT_DELETE' || ($rowA['CREATE_TIME'] ?? '') !== $now) {
        throw new RuntimeException('client audit/delete fields should not be written');
    }
    if (($rowA['UPDATE_USER'] ?? '') !== 'codexGenCfg') {
        throw new RuntimeException('update user mismatch');
    }
    if (($rowB['FIELD_JAVA_TYPE'] ?? '') !== 'Integer' || ($rowB['WHETHER_TABLE'] ?? '') !== 'Y' || ($rowB['WHETHER_RETRACT'] ?? '') !== 'N') {
        throw new RuntimeException('row B boolean normalization failed');
    }

    $failed = false;
    try {
        $service->editBatch([
            [
                'id' => $idA,
                'basicId' => $basicId,
                'isTableKey' => 'N',
                'fieldName' => $prefix . '_FIELD_A',
                'fieldRemark' => $prefix . '_SHOULD_ROLL_BACK',
                'fieldType' => 'varchar(128)',
                'fieldJavaType' => 'String',
                'effectType' => 'input',
                'whetherTable' => 'Y',
                'whetherRetract' => 'Y',
                'whetherAddUpdate' => 'Y',
                'whetherRequired' => 'Y',
                'queryWhether' => 'Y',
            ],
            [
                'id' => $deletedId,
                'basicId' => $basicId,
                'isTableKey' => 'N',
                'fieldName' => $prefix . '_FIELD_DELETED',
                'fieldRemark' => $prefix . '_DELETED',
                'fieldType' => 'varchar(128)',
                'fieldJavaType' => 'String',
                'effectType' => 'input',
                'whetherTable' => 'Y',
                'whetherRetract' => 'Y',
                'whetherAddUpdate' => 'Y',
                'whetherRequired' => 'Y',
                'queryWhether' => 'Y',
            ],
        ], ['user_id' => 'codexGenCfg']);
    } catch (RuntimeException $exception) {
        $failed = $exception->getCode() === 404;
    }
    if (!$failed) {
        throw new RuntimeException('deleted row batch should fail');
    }

    $remarkAfterFailure = think\facade\Db::name('gen_config')->where('ID', $idA)->value('FIELD_REMARK');
    if ($remarkAfterFailure !== $prefix . '_A_EDITED') {
        throw new RuntimeException('failed batch partially updated row A');
    }

    echo "GenConfigService editBatch checks passed\n";
} finally {
    think\facade\Db::name('gen_config')->whereIn('ID', [$idA, $idB, $deletedId])->delete();
}
'@

    $tmpProbe = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-gen-config-edit-batch-{0}.php" -f ([Guid]::NewGuid().ToString('N')))
    try {
        [System.IO.File]::WriteAllText($tmpProbe, $probe, [System.Text.UTF8Encoding]::new($false))
        $output = & php $tmpProbe 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "php GenConfigService editBatch probe failed: $output"
        }
        Write-Host ([string]($output | Out-String)).Trim()
    } finally {
        if (Test-Path -LiteralPath $tmpProbe) {
            Remove-Item -LiteralPath $tmpProbe -Force
        }
    }
}

Invoke-TestStep 'SaleProjectBillingService invoicing complete' {
$probe = @'
<?php

require getcwd() . '/vendor/autoload.php';

(new think\App(getcwd()))->initialize();

$service = new app\service\biz\SaleProjectBillingService();
$prefix = 'CODEX_INVOICING_' . date('YmdHis') . random_int(1000, 9999);
$projectId = '602000000000' . random_int(100000, 999999);
$invoicingId = (string)((int)$projectId + 1);
$otherTenantId = (string)((int)$projectId + 2);
$now = date('Y-m-d H:i:s');

function invoicing_smoke_project(string $id, string $prefix, string $tenantId, string $now): array {
    return [
        'ID' => $id,
        'CUSTOMER' => '0',
        'PROJECT_NAME' => $prefix . '_PROJECT',
        'PROJECT_STATE' => 'SHIPPED',
        'PLAY_STATE' => 'NORMAL',
        'VISIBILITY' => 'PUBLIC',
        'INIT_PRICE' => 0,
        'TOTAL_PRICE' => 0,
        'AMOUNT_COLLECTED' => 0,
        'PROJECT_CATEGORY' => 'SALE_PROJECT',
        'USER' => 'codexUser',
        'ORG' => '0',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => $now,
        'CREATE_USER' => 'codex-smoke',
        'TENANT_ID' => $tenantId,
        'VERSION' => 0,
        'DEAL_AMOUNT' => 0,
        'HISTORY_AMOUNT' => 0,
    ];
}

function invoicing_smoke_row(string $id, string $projectId, string $prefix, string $tenantId, string $state, string $now): array {
    return [
        'ID' => $id,
        'PROJECT_ID' => $projectId,
        'AMOUNT' => 123.45,
        'INVOICING_STATE' => $state,
        'INVOICING_CATEGORY' => 'COMMON',
        'PROCESS_ID' => $prefix . '_PROCESS',
        'REMARK' => 'codex smoke',
        'COMPANY_NAME' => $prefix . '_COMPANY',
        'CUSTOMER_COMPANY' => $prefix . '_CUSTOMER',
        'UNIT' => $prefix . '_UNIT',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => $now,
        'CREATE_USER' => 'codex-smoke',
        'TENANT_ID' => $tenantId,
    ];
}

try {
    think\facade\Db::name('biz_sale_project')->insertAll([
        invoicing_smoke_project($projectId, $prefix, '1', $now),
        invoicing_smoke_project($otherTenantId, $prefix . '_TENANT2', '2', $now),
    ]);
    think\facade\Db::name('biz_sale_project_invoicing')->insertAll([
        invoicing_smoke_row($invoicingId, $projectId, $prefix, '1', 'INVOICING_STATE_WAIT', $now),
        invoicing_smoke_row($otherTenantId, $otherTenantId, $prefix . '_TENANT2', '2', 'INVOICING_STATE_WAIT', $now),
    ]);

    $service->invoicingComplete($invoicingId, [
        'user_id' => 'codexBilling',
        'tenant_id' => '1',
        'account' => 'bizAdmin',
        'role_codes' => ['bizAdmin'],
    ]);
    $state = think\facade\Db::name('biz_sale_project_invoicing')->where('ID', $invoicingId)->value('INVOICING_STATE');
    if ($state !== 'INVOICING_STATE_COMPLETE') {
        throw new RuntimeException('invoicing complete did not update state');
    }
    $updateUser = think\facade\Db::name('biz_sale_project_invoicing')->where('ID', $invoicingId)->value('UPDATE_USER');
    if ($updateUser !== 'codexBilling') {
        throw new RuntimeException('invoicing complete update user mismatch');
    }

    $service->invoicingComplete($invoicingId, [
        'user_id' => 'codexBilling',
        'tenant_id' => '1',
        'account' => 'bizAdmin',
        'role_codes' => ['bizAdmin'],
    ]);

    $failed = false;
    try {
        $service->invoicingComplete($otherTenantId, [
            'user_id' => 'codexBilling',
            'tenant_id' => '1',
            'account' => 'bizAdmin',
            'role_codes' => ['bizAdmin'],
        ]);
    } catch (RuntimeException $exception) {
        $failed = $exception->getCode() === 404;
    }
    if (!$failed) {
        throw new RuntimeException('cross-tenant invoicing complete should fail');
    }
    $otherState = think\facade\Db::name('biz_sale_project_invoicing')->where('ID', $otherTenantId)->value('INVOICING_STATE');
    if ($otherState !== 'INVOICING_STATE_WAIT') {
        throw new RuntimeException('cross-tenant invoicing complete modified row');
    }

    echo "SaleProjectBillingService invoicing complete checks passed\n";
} finally {
    think\facade\Db::name('biz_sale_project_invoicing')->whereIn('ID', [$invoicingId, $otherTenantId])->delete();
    think\facade\Db::name('biz_sale_project')->whereIn('ID', [$projectId, $otherTenantId])->delete();
}
'@

    $tmpProbe = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-invoicing-complete-{0}.php" -f ([Guid]::NewGuid().ToString('N')))
    try {
        [System.IO.File]::WriteAllText($tmpProbe, $probe, [System.Text.UTF8Encoding]::new($false))
        $output = & php $tmpProbe 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "php SaleProjectBillingService invoicing complete probe failed: $output"
        }
        Write-Host ([string]($output | Out-String)).Trim()
    } finally {
        if (Test-Path -LiteralPath $tmpProbe) {
            Remove-Item -LiteralPath $tmpProbe -Force
        }
    }
}

Invoke-TestStep 'BizFileRelationService writes' {
$probe = @'
<?php

require getcwd() . '/vendor/autoload.php';

$app = (new think\App(getcwd()))->initialize();

$fileService = new app\service\dev\FileService();
$relationService = new app\service\biz\FileRelationService();
$payload = [
    'user_id' => 'codex-smoke',
    'tenant_id' => '1',
];
$prefix = 'CODEX_REL_' . date('YmdHis') . random_int(1000, 9999);
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '.txt';
$content = "codex relation smoke\n";
$objectId = $prefix . '_OBJECT';
$createdFileIds = [];
$storedPaths = [];
$relationIds = [];

file_put_contents($tmp, $content);

function relation_smoke_uploaded_file(string $path, string $name): think\file\UploadedFile {
    return new think\file\UploadedFile($path, $name, 'text/plain', UPLOAD_ERR_OK, true);
}

try {
    $fileA = $fileService->uploadReturnFile('LOCAL', relation_smoke_uploaded_file($tmp, $prefix . '-a.txt'), $payload);
    $fileB = $fileService->uploadReturnFile('LOCAL', relation_smoke_uploaded_file($tmp, $prefix . '-b.txt'), $payload);
    foreach ([$fileA, $fileB] as $file) {
        $createdFileIds[] = (string)$file['id'];
        $storedPaths[] = (string)$file['storagePath'];
    }

    $relationService->add([
        'objectId' => $objectId,
        'targetId' => (string)$fileA['id'],
        'category' => 'SALE_PROJECT',
    ], $payload);

    $row = think\facade\Db::name('biz_file_relation')
        ->where('OBJECT_ID', $objectId)
        ->where('TARGET_ID', (string)$fileA['id'])
        ->where('CATEGORY', 'SALE_PROJECT')
        ->where('TENANT_ID', '1')
        ->where('DELETE_FLAG', 'NOT_DELETE')
        ->find();
    if (!is_array($row) || $row === []) {
        throw new RuntimeException('relation row missing after add');
    }
    $relationIds[] = (string)$row['ID'];
    if ((string)$row['FILE_NAME'] !== $prefix . '-a.txt') {
        throw new RuntimeException('relation fileName did not copy dev_file.NAME');
    }

    $list = $relationService->list(['objectId' => $objectId, 'category' => 'SALE_PROJECT'], $payload);
    if (count($list) !== 1) {
        throw new RuntimeException('relation list did not return one active row');
    }
    if (($list[0]['downloadPath'] ?? '') !== '/api/dev/file/download?id=' . rawurlencode((string)$fileA['id'])) {
        throw new RuntimeException('relation downloadPath was not normalized');
    }

    $spoofedTenantList = $relationService->list([
        'objectId' => $objectId,
        'category' => 'SALE_PROJECT',
        'tenantId' => '2',
    ], $payload);
    if (count($spoofedTenantList) !== 1) {
        throw new RuntimeException('client tenantId should not override token tenant');
    }

    $tenantTwoObjectId = $prefix . '_TENANT2_OBJECT';
    $tenantTwoRelationId = '7' . (string)random_int(10000000000000000, 99999999999999999);
    think\facade\Db::name('biz_file_relation')->insert([
        'ID' => $tenantTwoRelationId,
        'OBJECT_ID' => $tenantTwoObjectId,
        'TARGET_ID' => (string)$fileA['id'],
        'CATEGORY' => 'SALE_PROJECT',
        'FILE_NAME' => $prefix . '-tenant2.txt',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => date('Y-m-d H:i:s'),
        'CREATE_USER' => 'codex-smoke',
        'EXT_JSON' => null,
        'TENANT_ID' => '2',
    ]);
    $relationIds[] = $tenantTwoRelationId;
    $crossTenantRead = $relationService->list([
        'objectId' => $tenantTwoObjectId,
        'category' => 'SALE_PROJECT',
        'tenantId' => '2',
    ], $payload);
    if ($crossTenantRead !== []) {
        throw new RuntimeException('cross-tenant relation read was not blocked');
    }

    $edit = $relationService->edit([
        'id' => $relationIds[0],
        'objectId' => $objectId,
        'targetId' => (string)$fileB['id'],
        'category' => 'SALE_PROJECT_CASE',
    ], $payload);
    if (($edit['id'] ?? '') !== $relationIds[0] || (int)($edit['count'] ?? 0) !== 1) {
        throw new RuntimeException('relation edit result mismatch');
    }

    $detail = $relationService->detail($relationIds[0], $payload);
    if (($detail['targetId'] ?? '') !== (string)$fileB['id'] || ($detail['category'] ?? '') !== 'SALE_PROJECT_CASE') {
        throw new RuntimeException('relation detail did not reflect edit');
    }
    if (($detail['fileName'] ?? '') !== $prefix . '-b.txt') {
        throw new RuntimeException('relation edit did not refresh fileName');
    }

    $badCategory = false;
    try {
        $relationService->add([
            'objectId' => $objectId,
            'targetId' => (string)$fileB['id'],
            'category' => 'Process_procure',
        ], $payload);
    } catch (RuntimeException $exception) {
        $badCategory = $exception->getCode() === 400;
    }
    if (!$badCategory) {
        throw new RuntimeException('bad category was not rejected');
    }

    $missingFile = false;
    try {
        $relationService->add([
            'objectId' => $objectId,
            'targetId' => '999999999999999999',
            'category' => 'SALE_PROJECT',
        ], $payload);
    } catch (RuntimeException $exception) {
        $missingFile = $exception->getCode() === 404;
    }
    if (!$missingFile) {
        throw new RuntimeException('missing file was not rejected');
    }

    think\facade\Db::name('dev_file')->where('ID', (string)$fileB['id'])->update(['TENANT_ID' => '2']);
    $crossTenant = false;
    try {
        $relationService->add([
            'objectId' => $objectId,
            'targetId' => (string)$fileB['id'],
            'category' => 'SALE_PROJECT',
        ], $payload);
    } catch (RuntimeException $exception) {
        $crossTenant = $exception->getCode() === 404;
    }
    think\facade\Db::name('dev_file')->where('ID', (string)$fileB['id'])->update(['TENANT_ID' => '1']);
    if (!$crossTenant) {
        throw new RuntimeException('cross-tenant file binding was not rejected');
    }

    $relationService->delete([$relationIds[0]], $payload);
    $activeCount = think\facade\Db::name('biz_file_relation')
        ->where('ID', $relationIds[0])
        ->where('DELETE_FLAG', 'NOT_DELETE')
        ->count();
    if ((int)$activeCount !== 0) {
        throw new RuntimeException('relation delete did not mark row deleted');
    }
    $remainingFileCount = think\facade\Db::name('dev_file')
        ->whereIn('ID', $createdFileIds)
        ->where('DELETE_FLAG', 'NOT_DELETE')
        ->count();
    if ((int)$remainingFileCount !== 2) {
        throw new RuntimeException('relation delete affected dev_file rows');
    }

    echo "BizFileRelationService write checks passed\n";
} finally {
    if ($relationIds !== []) {
        think\facade\Db::name('biz_file_relation')->whereIn('ID', $relationIds)->delete();
    }
    think\facade\Db::name('biz_file_relation')->whereLike('OBJECT_ID', $prefix . '%')->delete();
    if ($createdFileIds !== []) {
        think\facade\Db::name('dev_file')->whereIn('ID', $createdFileIds)->delete();
    }
    foreach ($storedPaths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    if (is_file($tmp)) {
        @unlink($tmp);
    }
}
'@

    $probePath = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-file-relation-smoke-$([guid]::NewGuid()).php")
    try {
        Set-Content -LiteralPath $probePath -Value $probe -Encoding UTF8
        $output = & php $probePath 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "php BizFileRelationService write probe failed: $output"
        }
    } finally {
        if (Test-Path -LiteralPath $probePath) {
            Remove-Item -LiteralPath $probePath -Force
        }
    }

    Write-Host ([string]($output | Out-String)).Trim()
}

Invoke-TestStep 'UserDirectoryService exports' {
$probe = @'
<?php

require getcwd() . '/vendor/autoload.php';

$app = (new think\App(getcwd()))->initialize();

$payload = [
    'account' => 'superadmin',
    'role_codes' => ['superadmin'],
];

$userId = think\facade\Db::name('sys_user')
    ->where('DELETE_FLAG', 'NOT_DELETE')
    ->order('ID', 'asc')
    ->value('ID');

if (!is_string($userId) || trim($userId) === '') {
    throw new RuntimeException('No active sys_user row found');
}

$service = new app\service\user\UserDirectoryService();
$results = [
    'exportUsers(false)' => $service->exportUsers([], $payload, false),
    'exportUsers(true)' => $service->exportUsers([], $payload, true),
    'exportUserInfoFile' => $service->exportUserInfoFile($userId, $payload, false),
];

foreach ($results as $name => $result) {
    if (!is_array($result)) {
        throw new RuntimeException($name . ' did not return array');
    }

    foreach (['filename', 'contentType', 'content'] as $key) {
        if (!isset($result[$key]) || !is_string($result[$key]) || $result[$key] === '') {
            throw new RuntimeException($name . ' missing string field ' . $key);
        }
    }

    if (str_contains($result['content'], 'PASSWORD')) {
        throw new RuntimeException($name . ' content leaked PASSWORD');
    }
}

echo "UserDirectoryService export checks passed\n";
'@

    $output = $probe | & php 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "php UserDirectoryService export probe failed: $output"
    }

    Write-Host ([string]($output | Out-String)).Trim()
}

Invoke-TestStep 'UserDirectoryService import' {
$probe = @'
<?php

require getcwd() . '/vendor/autoload.php';

$app = (new think\App(getcwd()))->initialize();

function xlsx_col(int $idx): string {
    $name = '';
    $idx++;
    while ($idx > 0) {
        $idx--;
        $name = chr(65 + ($idx % 26)) . $name;
        $idx = intdiv($idx, 26);
    }
    return $name;
}

function xlsx_text(string $value): string {
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function xlsx_sheet(array $rows): string {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach ($rows as $rowIndex => $row) {
        $xml .= '<row r="' . ($rowIndex + 1) . '">';
        foreach ($row as $columnIndex => $value) {
            $ref = xlsx_col($columnIndex) . ($rowIndex + 1);
            $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . xlsx_text((string)$value) . '</t></is></c>';
        }
        $xml .= '</row>';
    }
    return $xml . '</sheetData></worksheet>';
}

function write_xlsx(string $path, array $rows): void {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('open xlsx failed');
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="UserImport" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', xlsx_sheet($rows));
    $zip->close();
}

$service = new app\service\user\UserDirectoryService();
$payload = [
    'account' => 'superadmin',
    'role_codes' => ['superadmin'],
    'tenant_id' => '1',
    'user_id' => 'codex-smoke',
];
$prefix = 'CODEX_IMPORT_' . date('YmdHis') . random_int(1000, 9999);
$account = strtolower($prefix);
$orgRoot = $prefix . '_ORG';
$orgChild = $prefix . '_DEPT';
$position = $prefix . '_POS';
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '.xlsx';
$duplicatePhone = (string)think\facade\Db::name('sys_user')
    ->where('DELETE_FLAG', 'NOT_DELETE')
    ->whereNotNull('PHONE')
    ->where('PHONE', '<>', '')
    ->value('PHONE');

try {
    $template = $service->downloadImportUserTemplate($payload);
    if (($template['filename'] ?? '') !== 'userImportTemplate.xlsx') {
        throw new RuntimeException('bad template filename');
    }
    if (!str_contains((string)$template['contentType'], 'spreadsheetml')) {
        throw new RuntimeException('bad template content type');
    }
    if (substr((string)$template['content'], 0, 2) !== 'PK') {
        throw new RuntimeException('template is not xlsx zip');
    }

    write_xlsx($tmp, [
        ['note'],
        ['account', 'name', 'orgName', 'positionName', 'phone', 'email', 'directorName', 'empNo', 'entryDate', 'positionLevel', 'nickname', 'gender'],
        [$account, $prefix . '_NAME', $orgRoot . '-' . $orgChild, $position, $duplicatePhone, $account . '@example.com', '', $prefix . '_EMP', '2026-06-08', 'P7', $prefix . '_NICK', 'M'],
    ]);
    $result = $service->importUsers($tmp, $payload);
    if ($result['totalCount'] !== 1 || $result['successCount'] !== 1 || $result['errorCount'] !== 0) {
        throw new RuntimeException('unexpected import result: ' . json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    $user = think\facade\Db::name('sys_user')
        ->where('ACCOUNT', $account)
        ->where('DELETE_FLAG', 'NOT_DELETE')
        ->find();
    if (!$user) {
        throw new RuntimeException('imported user missing');
    }
    if ((string)$user['PHONE'] !== '') {
        throw new RuntimeException('duplicate phone was not skipped');
    }
    if ((string)$user['EMAIL'] !== $account . '@example.com') {
        throw new RuntimeException('email not imported');
    }

    write_xlsx($tmp, [
        ['note'],
        ['account', 'name', 'orgName', 'positionName', 'phone', 'email'],
        [$account, $prefix . '_NAME_EDIT', $orgRoot . '-' . $orgChild, $position, '', $account . '2@example.com'],
    ]);
    $result = $service->importUsers($tmp, $payload);
    if ($result['successCount'] !== 1 || $result['errorCount'] !== 0) {
        throw new RuntimeException('unexpected update result: ' . json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    $count = think\facade\Db::name('sys_user')
        ->where('ACCOUNT', $account)
        ->where('DELETE_FLAG', 'NOT_DELETE')
        ->count();
    if ((int)$count !== 1) {
        throw new RuntimeException('update created duplicate account');
    }

    write_xlsx($tmp, [
        ['note'],
        ['account', 'name', 'orgName', 'positionName'],
        ['', 'Missing Account', $orgRoot, $position],
    ]);
    $bad = $service->importUsers($tmp, $payload);
    if ($bad['totalCount'] !== 1 || $bad['successCount'] !== 0 || $bad['errorCount'] !== 1) {
        throw new RuntimeException('bad row did not return error detail');
    }

    $denied = false;
    try {
        $service->importUsers($tmp, ['account' => 'limited']);
    } catch (RuntimeException $exception) {
        $denied = $exception->getCode() === 403;
    }
    if (!$denied) {
        throw new RuntimeException('limited payload was not denied');
    }

    $scopedPayload = [
        'account' => 'importer',
        'permission_codes' => ['sysUserImport', 'sysUserAdd'],
        'tenant_id' => '1',
        'user_id' => 'codex-limited',
    ];
    write_xlsx($tmp, [
        ['note'],
        ['account', 'name', 'orgName', 'positionName'],
        [$account . '_limited', $prefix . '_LIMITED', $prefix . '_NO_ORG', $prefix . '_NO_POS'],
    ]);
    $limited = $service->importUsers($tmp, $scopedPayload);
    if ($limited['totalCount'] !== 1 || $limited['successCount'] !== 0 || $limited['errorCount'] !== 1) {
        throw new RuntimeException('limited import unexpectedly created org or user');
    }

    echo "UserDirectoryService import checks passed\n";
} finally {
    if (is_file($tmp)) {
        @unlink($tmp);
    }

    $userIds = think\facade\Db::name('sys_user')
        ->whereLike('ACCOUNT', strtolower($prefix) . '%')
        ->column('ID');
    if ($userIds !== []) {
        think\facade\Db::name('sys_user')->whereIn('ID', $userIds)->delete();
    }

    $orgIds = think\facade\Db::name('sys_org')
        ->whereLike('NAME', $prefix . '%')
        ->column('ID');
    if ($orgIds !== []) {
        think\facade\Db::name('sys_position')->whereIn('ORG_ID', $orgIds)->delete();
        think\facade\Db::name('sys_org')->whereIn('ID', $orgIds)->delete();
    }
}
'@

    $probePath = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-user-import-smoke-$([guid]::NewGuid()).php")
    try {
        Set-Content -LiteralPath $probePath -Value $probe -Encoding UTF8
        $output = & php $probePath 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "php UserDirectoryService import probe failed: $output"
        }
    } finally {
        if (Test-Path -LiteralPath $probePath) {
            Remove-Item -LiteralPath $probePath -Force
        }
    }

    Write-Host ([string]($output | Out-String)).Trim()
}

Invoke-TestStep 'Biz dictionary writes' {
$probe = @'
<?php

require getcwd() . '/vendor/autoload.php';

$app = (new think\App(getcwd()))->initialize();

$service = new app\service\dev\DictService();
$payload = [
    'user_id' => 'codex-smoke',
    'tenant_id' => '1',
    'role_codes' => ['superadmin'],
];
$prefix = 'CODEX_BIZ_DICT_' . date('YmdHis') . random_int(1000, 9999);
$created = [];

try {
    $root = $service->addBizDict([
        'parentId' => '0',
        'dictLabel' => $prefix . '_ROOT',
        'dictValue' => $prefix . '_ROOT_VALUE',
        'category' => 'BIZ',
        'sortCode' => 98,
        'viewState' => 'NOT_VISIBLE',
        'editState' => 'EDIT',
    ], $payload);
    $created[] = $root['id'];

    $child = $service->addBizDict([
        'parentId' => $root['id'],
        'dictLabel' => $prefix . '_CHILD',
        'dictValue' => $prefix . '_CHILD_VALUE',
        'category' => 'BIZ',
        'sortCode' => 99,
        'viewState' => 'NOT_VISIBLE',
        'editState' => 'EDIT',
    ], $payload);
    $created[] = $child['id'];

    $edited = $service->editBizDict([
        'id' => $child['id'],
        'parentId' => $root['id'],
        'dictLabel' => $prefix . '_CHILD_DEV_EDIT',
        'dictValue' => $prefix . '_CHILD_DEV_VALUE',
        'category' => 'BIZ',
        'sortCode' => 97,
        'viewState' => 'NOT_VISIBLE',
        'editState' => 'EDIT',
    ], $payload);
    if ($edited['dictValue'] !== $prefix . '_CHILD_DEV_VALUE') {
        throw new RuntimeException('dev edit did not update dictValue');
    }

    $bizEdited = $service->editBizDictBusiness([
        'id' => $child['id'],
        'parentId' => '0',
        'dictLabel' => $prefix . '_CHILD_BIZ_EDIT',
        'dictValue' => $prefix . '_SHOULD_NOT_WRITE',
        'sortCode' => 96,
    ], $payload);
    if ($bizEdited['parentId'] !== $root['id']) {
        throw new RuntimeException('biz edit changed parentId');
    }
    if ($bizEdited['dictValue'] !== $prefix . '_CHILD_DEV_VALUE') {
        throw new RuntimeException('biz edit changed dictValue');
    }

    $deleted = $service->deleteBizDicts([$root['id']], $payload);
    if (!in_array($child['id'], $deleted['ids'], true)) {
        throw new RuntimeException('delete did not include child id');
    }
    $remaining = think\facade\Db::name('dev_dict')
        ->whereIn('ID', [$root['id'], $child['id']])
        ->where('DELETE_FLAG', 'NOT_DELETE')
        ->count();
    if ($remaining !== 0) {
        throw new RuntimeException('soft delete did not mark all smoke rows');
    }

    echo "Biz dictionary write checks passed\n";
} finally {
    if ($created !== []) {
        think\facade\Db::name('dev_dict')->whereIn('ID', $created)->delete();
    }
}
'@

    $output = $probe | & php 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "php Biz dictionary write probe failed: $output"
    }

    Write-Host ([string]($output | Out-String)).Trim()
}

Invoke-TestStep 'ResourceService module write compatibility' {
$probe = @'
<?php

require getcwd() . '/vendor/autoload.php';

(new think\App(getcwd()))->initialize();

$service = new app\service\sys\ResourceService();
$payload = ['userId' => 'codex-smoke'];
$prefix = 'CODEX_SYS_MODULE_' . date('YmdHis') . random_int(1000, 9999);
$moduleId = '';
$menuId = '';
$relationId = '';

try {
    $created = $service->moduleAdd([
        'title' => $prefix . '_MODULE',
        'icon' => 'AppstoreOutlined',
        'color' => '#1677FF',
        'sortCode' => 99,
        'extJson' => ['smoke' => true],
    ], $payload);
    $moduleId = (string)($created['id'] ?? '');
    if ($moduleId === '') {
        throw new RuntimeException('module add did not return id');
    }
    if (($created['category'] ?? '') !== 'MODULE' || (string)($created['code'] ?? '') === '') {
        throw new RuntimeException('module add did not set category/code');
    }

    $duplicateFailed = false;
    try {
        $service->moduleAdd([
            'title' => $prefix . '_MODULE',
            'icon' => 'AppstoreOutlined',
            'color' => '#1677FF',
            'sortCode' => 98,
        ], $payload);
    } catch (RuntimeException) {
        $duplicateFailed = true;
    }
    if (!$duplicateFailed) {
        throw new RuntimeException('duplicate module title should fail');
    }

    $edited = $service->moduleEdit([
        'id' => $moduleId,
        'title' => $prefix . '_EDITED',
        'icon' => 'SettingOutlined',
        'color' => '#13C2C2',
        'sortCode' => 97,
        'extJson' => '{}',
    ], $payload);
    if (($edited['title'] ?? '') !== $prefix . '_EDITED' || ($edited['sortCode'] ?? 0) !== 97) {
        throw new RuntimeException('module edit did not update title/sortCode');
    }

    $menuId = 'codex-menu-' . random_int(100000, 999999);
    think\facade\Db::name('sys_resource')->insert([
        'ID' => $menuId,
        'PARENT_ID' => '0',
        'TITLE' => $prefix . '_MENU',
        'CODE' => $prefix . '_MENU',
        'CATEGORY' => 'MENU',
        'MODULE' => $moduleId,
        'SORT_CODE' => 9999,
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => date('Y-m-d H:i:s'),
        'CREATE_USER' => 'codex-smoke',
    ]);
    $relationId = 'codex-rel-' . random_int(100000, 999999);
    think\facade\Db::name('sys_relation')->insert([
        'ID' => $relationId,
        'OBJECT_ID' => 'codex-role',
        'TARGET_ID' => $menuId,
        'CATEGORY' => 'SYS_ROLE_HAS_RESOURCE',
        'EXT_JSON' => '{}',
    ]);

    $deleted = $service->moduleDelete([['id' => $moduleId]], $payload);
    if (($deleted['count'] ?? 0) < 2) {
        throw new RuntimeException('module delete did not include module/menu rows');
    }

    $flags = think\facade\Db::name('sys_resource')
        ->whereIn('ID', [$moduleId, $menuId])
        ->column('DELETE_FLAG', 'ID');
    if (($flags[$moduleId] ?? '') !== 'DELETED' || ($flags[$menuId] ?? '') !== 'DELETED') {
        throw new RuntimeException('module delete did not logically delete module and menu');
    }
    $relationCount = think\facade\Db::name('sys_relation')->where('ID', $relationId)->count();
    if ($relationCount !== 0) {
        throw new RuntimeException('module delete did not remove role resource relation');
    }

    echo "ResourceService module write checks passed\n";
} finally {
    if ($relationId !== '') {
        think\facade\Db::name('sys_relation')->where('ID', $relationId)->delete();
    }
    foreach ([$menuId, $moduleId] as $id) {
        if ($id !== '') {
            think\facade\Db::name('sys_resource')->where('ID', $id)->delete();
        }
    }
}
'@

    $probePath = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-resource-module-smoke-$([guid]::NewGuid()).php")
    try {
        Set-Content -LiteralPath $probePath -Value $probe -Encoding UTF8
        $output = & php $probePath 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "php ResourceService module write probe failed: $output"
        }
    } finally {
        if (Test-Path -LiteralPath $probePath) {
            Remove-Item -LiteralPath $probePath -Force
        }
    }

    Write-Host ([string]($output | Out-String)).Trim()
}

Invoke-TestStep 'ResourceService button write compatibility' {
$probe = @'
<?php

require getcwd() . '/vendor/autoload.php';

(new think\App(getcwd()))->initialize();

$service = new app\service\sys\ResourceService();
$payload = ['userId' => 'codex-smoke'];
$prefix = 'CODEX_SYS_BUTTON_' . date('YmdHis') . random_int(1000, 9999);
$createdIds = [];
$relationId = '';
$createdMenu = false;

try {
    $parentId = (string)think\facade\Db::name('sys_resource')
        ->where('CATEGORY', 'MENU')
        ->where(function ($query): void {
            $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
        })
        ->value('ID');

    if ($parentId === '') {
        $parentId = 'codex-menu-' . random_int(100000, 999999);
        $createdMenu = true;
        think\facade\Db::name('sys_resource')->insert([
            'ID' => $parentId,
            'TITLE' => $prefix . '_MENU',
            'CODE' => $prefix . '_MENU',
            'CATEGORY' => 'MENU',
            'SORT_CODE' => 9999,
            'DELETE_FLAG' => 'NOT_DELETE',
            'CREATE_TIME' => date('Y-m-d H:i:s'),
            'CREATE_USER' => 'codex-smoke',
        ]);
    }

    $created = $service->buttonAdd([
        'parentId' => $parentId,
        'title' => $prefix . '_BUTTON',
        'code' => $prefix . '_CODE',
        'sortCode' => 9999,
        'extJson' => ['smoke' => true],
    ], $payload);
    $buttonId = (string)($created['id'] ?? '');
    if ($buttonId === '') {
        throw new RuntimeException('button add did not return id');
    }
    $createdIds[] = $buttonId;

    $duplicateFailed = false;
    try {
        $service->buttonAdd([
            'parentId' => $parentId,
            'title' => $prefix . '_DUP',
            'code' => $prefix . '_CODE',
            'sortCode' => 9998,
        ], $payload);
    } catch (RuntimeException) {
        $duplicateFailed = true;
    }
    if (!$duplicateFailed) {
        throw new RuntimeException('duplicate button code should fail');
    }

    $edited = $service->buttonEdit([
        'id' => $buttonId,
        'parentId' => $parentId,
        'title' => $prefix . '_EDITED',
        'code' => $prefix . '_CODE_EDITED',
        'sortCode' => 9997,
        'extJson' => '{}',
    ], $payload);
    if (($edited['title'] ?? '') !== $prefix . '_EDITED' || ($edited['sortCode'] ?? 0) !== 9997) {
        throw new RuntimeException('button edit did not update title/sortCode');
    }

    $relationId = 'codex-rel-' . random_int(100000, 999999);
    think\facade\Db::name('sys_relation')->insert([
        'ID' => $relationId,
        'OBJECT_ID' => 'codex-role',
        'TARGET_ID' => $parentId,
        'CATEGORY' => 'SYS_ROLE_HAS_RESOURCE',
        'EXT_JSON' => json_encode(['buttonInfo' => [$buttonId, 'keep-button']], JSON_UNESCAPED_SLASHES),
    ]);

    $deleted = $service->buttonDelete([['id' => $buttonId]], $payload);
    if (($deleted['count'] ?? 0) !== 1) {
        throw new RuntimeException('button delete count mismatch');
    }

    $deleteFlag = (string)think\facade\Db::name('sys_resource')->where('ID', $buttonId)->value('DELETE_FLAG');
    if ($deleteFlag !== 'DELETED') {
        throw new RuntimeException('button was not logically deleted');
    }

    $extJson = json_decode((string)think\facade\Db::name('sys_relation')->where('ID', $relationId)->value('EXT_JSON'), true);
    if (($extJson['buttonInfo'] ?? []) !== ['keep-button']) {
        throw new RuntimeException('role resource buttonInfo cleanup failed');
    }

    echo "ResourceService button write checks passed\n";
} finally {
    if ($relationId !== '') {
        think\facade\Db::name('sys_relation')->where('ID', $relationId)->delete();
    }
    if ($createdIds !== []) {
        think\facade\Db::name('sys_resource')->whereIn('ID', $createdIds)->delete();
    }
    if ($createdMenu) {
        think\facade\Db::name('sys_resource')->where('ID', $parentId)->delete();
    }
}
'@

    $probePath = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-resource-button-smoke-$([guid]::NewGuid()).php")
    try {
        Set-Content -LiteralPath $probePath -Value $probe -Encoding UTF8
        $output = & php $probePath 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "php ResourceService button write probe failed: $output"
        }
    } finally {
        if (Test-Path -LiteralPath $probePath) {
            Remove-Item -LiteralPath $probePath -Force
        }
    }

    Write-Host ([string]($output | Out-String)).Trim()
}

Invoke-TestStep 'TeamProjectService base write compatibility' {
$probe = @'
<?php

require getcwd() . '/vendor/autoload.php';

(new think\App(getcwd()))->initialize();

$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '__LOCAL_SMOKE_ACCOUNT__')->find();
if (!is_array($user) || $user === []) {
    throw new RuntimeException('local smoke account not found');
}

$payload = [
    'user_id' => (string)$user['ID'],
    'tenant_id' => (string)$user['TENANT_ID'],
    'org_id' => $user['ORG_ID'] ?? null,
];
$service = new app\service\biz\TeamProjectService();
$projectId = null;

try {
    $created = $service->projectAdd([
        'name' => 'CODEX_TP_SMOKE',
        'description' => 'codex add smoke',
    ], $payload);
    $projectId = (string)$created['id'];

    $detail = $service->projectDetail($projectId, $payload);
    if (($detail['project']['name'] ?? '') !== 'CODEX_TP_SMOKE') {
        throw new RuntimeException('project add detail name mismatch');
    }
    if (($detail['user']['roleType'] ?? '') !== 'LEADER') {
        throw new RuntimeException('project add did not create leader member');
    }
    $memberId = (string)($detail['user']['id'] ?? '');
    if ($memberId === '') {
        throw new RuntimeException('project add did not expose leader member id');
    }

    $relation = think\facade\Db::name('biz_relation')
        ->where('OBJECT_ID', $projectId)
        ->where('TARGET_ID', (string)$user['ID'])
        ->where('CATEGORY', 'TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION')
        ->find();
    if (!is_array($relation) || $relation === []) {
        throw new RuntimeException('leader permission relation was not created');
    }
    $permissionCodes = json_decode((string)$relation['EXT_JSON'], true);
    if (!is_array($permissionCodes) || !in_array('delProject', $permissionCodes, true)) {
        throw new RuntimeException('leader relation missing delProject permission');
    }

    $service->memberEdit([
        'id' => $memberId,
        'roleType' => 'MEMBER',
    ], $payload);
    $roleTypeAfterEdit = (string)think\facade\Db::name('biz_team_project_user')->where('ID', $memberId)->value('ROLE_TYPE');
    $memberAfterEdit = think\facade\Db::name('biz_team_project_user')->where('ID', $memberId)->find();
    $relationAfterEdit = (string)think\facade\Db::name('biz_relation')
        ->where('OBJECT_ID', $projectId)
        ->where('TARGET_ID', (string)$user['ID'])
        ->where('CATEGORY', 'TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION')
        ->value('EXT_JSON');
    if ($roleTypeAfterEdit !== 'LEADER' || !str_contains($relationAfterEdit, 'delProject')) {
        throw new RuntimeException('member edit should not mutate role or permissions');
    }
    if (($memberAfterEdit['UPDATE_USER'] ?? '') !== (string)$user['ID'] || empty($memberAfterEdit['UPDATE_TIME'])) {
        throw new RuntimeException('member edit did not refresh audit fields');
    }

    $service->projectEdit([
        'id' => $projectId,
        'description' => 'codex edited smoke',
        'projectStatus' => 'COMPLETE',
        'completionTime' => '2026-06-08 10:00:00',
    ], $payload);
    $edited = think\facade\Db::name('biz_team_project')->where('ID', $projectId)->find();
    if (($edited['DESCRIPTION'] ?? '') !== 'codex edited smoke' || ($edited['PROJECT_STATUS'] ?? '') !== 'COMPLETE') {
        throw new RuntimeException('project edit did not persist expected fields');
    }
    if ((int)($edited['VERSION'] ?? 0) < 1) {
        throw new RuntimeException('project edit did not increment version');
    }

    $service->projectDelete([['id' => $projectId]], $payload);
    $projectFlag = (string)think\facade\Db::name('biz_team_project')->where('ID', $projectId)->value('DELETE_FLAG');
    $memberFlag = (string)think\facade\Db::name('biz_team_project_user')->where('TEAM_PROJECT_ID', $projectId)->value('DELETE_FLAG');
    if ($projectFlag !== 'DELETED' || $memberFlag !== 'DELETED') {
        throw new RuntimeException('project delete did not soft-delete project and leader member');
    }

    echo "Team project base write checks passed\n";
} finally {
    if ($projectId !== null) {
        think\facade\Db::name('biz_team_project_user')->where('TEAM_PROJECT_ID', $projectId)->delete();
        think\facade\Db::name('biz_relation')
            ->where('OBJECT_ID', $projectId)
            ->where('CATEGORY', 'TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION')
            ->delete();
        think\facade\Db::name('biz_team_project')->where('ID', $projectId)->delete();
    }
}
?>
'@

    $probe = $probe.Replace('__LOCAL_SMOKE_ACCOUNT__', $localSmokeAccount.Replace("'", "\'"))

    $probeFile = Join-Path ([System.IO.Path]::GetTempPath()) ('team_project_probe_' + [System.Guid]::NewGuid().ToString('N') + '.php')
    try {
        Set-Content -LiteralPath $probeFile -Value $probe -Encoding ASCII
        $output = & php $probeFile 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "php TeamProjectService base write probe failed: $output"
        }
    } finally {
        if (Test-Path -LiteralPath $probeFile) {
            Remove-Item -LiteralPath $probeFile -Force
        }
    }

    Write-Host ([string]($output | Out-String)).Trim()
}

Write-Host '[test-agent-db] db smoke run completed'
