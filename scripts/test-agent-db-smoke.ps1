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

Write-Host '[test-agent-db] db smoke run completed'
