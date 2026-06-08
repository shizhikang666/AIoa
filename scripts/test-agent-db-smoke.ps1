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
