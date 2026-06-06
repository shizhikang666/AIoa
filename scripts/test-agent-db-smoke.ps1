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
    $oldRedisAuth = $env:REDISCLI_AUTH
    try {
        if ($redisPass -ne '') {
            $env:REDISCLI_AUTH = $redisPass
        } else {
            Remove-Item Env:\REDISCLI_AUTH -ErrorAction SilentlyContinue
        }

        $pong = (& $redisCli -h $redisHost -p $redisPort PING 2>&1 | Out-String).Trim()
        if ($LASTEXITCODE -ne 0) {
            throw "redis ping failed: $pong"
        }
        if ($pong -ne 'PONG') {
            throw "Expected Redis PING to return PONG, got $pong"
        }
    } finally {
        if ($null -eq $oldRedisAuth) {
            Remove-Item Env:\REDISCLI_AUTH -ErrorAction SilentlyContinue
        } else {
            $env:REDISCLI_AUTH = $oldRedisAuth
        }
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

Write-Host '[test-agent-db] db smoke run completed'
