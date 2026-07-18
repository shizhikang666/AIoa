[CmdletBinding()]
param(
    [string]$PointerPath = '',
    [string]$CanonicalDatabase = '',
    [string]$ConfirmCanonicalDatabase = '',
    [string]$ValidationDatabase = '',
    [string]$ConfirmValidationDatabase = '',
    [string]$DatabaseHost = '',
    [string]$RunLabel = '',
    [string]$RunDate = '',
    [int]$ExpectedTableCount = 0,
    [int]$ExpectedForeignKeyCount = -1,
    [string]$PhpPath = '',
    [string]$ListenAddress = '',
    [ValidateRange(1024, 65535)]
    [int]$Port = 18083
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$runtimeRoot = (Resolve-Path -LiteralPath (Join-Path $projectRoot 'runtime')).Path
$server = $null
$stdoutPath = [IO.Path]::GetTempFileName()
$stderrPath = [IO.Path]::GetTempFileName()

function Assert-SafeDatabaseIdentifier([string]$Value, [string]$Label) {
    if ($Value -cnotmatch '^[A-Za-z][A-Za-z0-9_]{0,63}$' `
        -or @('information_schema', 'mysql', 'performance_schema', 'sys') -contains $Value.ToLowerInvariant()) {
        throw "$Label database identifier is invalid"
    }
}

function Get-DatabaseLoopback([string]$Value) {
    $normalized = $Value.Trim().ToLowerInvariant()
    if ($normalized -eq 'localhost') {
        return '127.0.0.1'
    }
    [Net.IPAddress]$address = $null
    if ([Net.IPAddress]::TryParse($normalized, [ref]$address) -and [Net.IPAddress]::IsLoopback($address)) {
        return $address.ToString()
    }
    throw 'database host must be an explicit loopback address'
}

function Get-HttpLoopback([string]$Value) {
    $normalized = $Value.Trim().ToLowerInvariant()
    [Net.IPAddress]$address = $null
    if (![Net.IPAddress]::TryParse($normalized, [ref]$address) `
        -or $address.AddressFamily -ne [Net.Sockets.AddressFamily]::InterNetwork `
        -or ![Net.IPAddress]::IsLoopback($address)) {
        throw 'HTTP listen address must be an explicit IPv4 loopback address'
    }
    return $address.ToString()
}

function Test-ContainedPath([string]$Child, [string]$Parent) {
    return ($Child.TrimEnd('\') + '\').StartsWith(
        $Parent.TrimEnd('\') + '\',
        [StringComparison]::OrdinalIgnoreCase
    )
}

function Resolve-PrivateFile([string]$Value, [string]$Label) {
    if ([string]::IsNullOrWhiteSpace($Value) -or [IO.Path]::IsPathRooted($Value)) {
        throw "$Label must be a relative private runtime path"
    }
    $candidate = Join-Path $projectRoot $Value
    $candidateItem = Get-Item -LiteralPath $candidate -Force
    $resolved = (Resolve-Path -LiteralPath $candidate).Path
    $item = Get-Item -LiteralPath $resolved -Force
    if (!(Test-Path -LiteralPath $resolved -PathType Leaf) `
        -or !(Test-ContainedPath $resolved $runtimeRoot) `
        -or (($candidateItem.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) `
        -or (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0)) {
        throw "$Label is missing or outside private runtime"
    }
    return $resolved
}

try {
    Assert-SafeDatabaseIdentifier $CanonicalDatabase 'canonical'
    if ($CanonicalDatabase -cne $ConfirmCanonicalDatabase) {
        throw 'canonical database requires exact explicit confirmation'
    }
    Assert-SafeDatabaseIdentifier $ValidationDatabase 'target'
    if ($ValidationDatabase -cne $ConfirmValidationDatabase) {
        throw 'validation database requires exact explicit confirmation'
    }
    if ($ValidationDatabase.ToLowerInvariant() -eq $CanonicalDatabase.ToLowerInvariant()) {
        throw 'validation target must differ from canonical database'
    }
    $DatabaseHost = Get-DatabaseLoopback $DatabaseHost
    $ListenAddress = Get-HttpLoopback $ListenAddress
    if ($RunLabel -cnotmatch '^[a-z][a-z0-9_-]{1,31}$' -or $RunDate -notmatch '^[0-9]{8}$') {
        throw 'validation run identity is invalid'
    }
    $parsedRunDate = [DateTime]::MinValue
    if (![DateTime]::TryParseExact(
        $RunDate,
        'yyyyMMdd',
        [Globalization.CultureInfo]::InvariantCulture,
        [Globalization.DateTimeStyles]::None,
        [ref]$parsedRunDate
    )) {
        throw 'validation run date is invalid'
    }
    if ($ExpectedTableCount -lt 1 -or $ExpectedForeignKeyCount -lt 0) {
        throw 'validation expected counts are invalid'
    }
    $pointerResolvedPath = Resolve-PrivateFile $PointerPath 'private validation pointer'
    $php = (Resolve-Path -LiteralPath $PhpPath).Path
    if (!(Test-Path -LiteralPath $php -PathType Leaf)) {
        throw 'PHP executable path is invalid'
    }
    if (Get-NetTCPConnection -LocalAddress $ListenAddress -LocalPort $Port -State Listen -ErrorAction SilentlyContinue) {
        throw 'readonly isolated health port is occupied'
    }
    $pointer = Get-Content -Raw -LiteralPath $pointerResolvedPath | ConvertFrom-Json
    Assert-SafeDatabaseIdentifier ([string]$pointer.database) 'target'
    if ([string]$pointer.database -cne $ValidationDatabase `
        -or $null -eq $pointer.expectedForeignKeyConstraintCount `
        -or [int]$pointer.version -ne 2 `
        -or [string]$pointer.canonicalDatabase -cne $CanonicalDatabase `
        -or [string]$pointer.databaseHost -cne $DatabaseHost `
        -or [string]$pointer.runLabel -cne $RunLabel `
        -or [string]$pointer.runDate -cne $RunDate `
        -or [int]$pointer.expectedTableCount -ne $ExpectedTableCount `
        -or [int]$pointer.expectedForeignKeyConstraintCount -ne $ExpectedForeignKeyCount) {
        throw 'readonly isolated health pointer differs from the explicit invocation'
    }
    $runtimePath = (Resolve-Path -LiteralPath (
        Join-Path $projectRoot ([string]$pointer.serverRuntime -replace '/', '\')
    )).Path
    if (!(Test-ContainedPath $runtimePath $runtimeRoot)) {
        throw 'readonly isolated health runtime escaped private runtime'
    }
    $nonceBytes = New-Object byte[] 32
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $rng.GetBytes($nonceBytes)
    } finally {
        $rng.Dispose()
    }
    $nonce = ($nonceBytes | ForEach-Object { $_.ToString('x2') }) -join ''

    $env:OA_ISOLATED_PROJECT_ROOT = $projectRoot
    $env:OA_ISOLATED_RUNTIME_PATH = $runtimePath
    $env:OA_ISOLATED_DB_NAME = [string]$pointer.database
    $env:OA_ISOLATED_CANONICAL_DB = $CanonicalDatabase
    $env:OA_ISOLATED_DB_HOST = $DatabaseHost
    $env:OA_ISOLATED_RUN_LABEL = $RunLabel
    $env:OA_ISOLATED_RUN_DATE = $RunDate
    $env:OA_ISOLATED_EXPECTED_TABLE_COUNT = [string]$ExpectedTableCount
    $env:OA_ISOLATED_EXPECTED_FOREIGN_KEY_COUNT = [string]$ExpectedForeignKeyCount
    $env:OA_ISOLATED_LEGACY_POSTHOC = '0'
    $env:OA_ISOLATED_PREFIX = ($RunLabel -replace '-', '_') + '_health_readonly'
    $env:OA_ISOLATED_HEALTH_NONCE = $nonce

    $server = Start-Process -FilePath $php `
        -ArgumentList @(
            '-S',
            "${ListenAddress}:$Port",
            '-t',
            (Join-Path $projectRoot 'public'),
            (Join-Path $PSScriptRoot 'isolated-validation-router.php')
        ) `
        -WorkingDirectory $projectRoot `
        -WindowStyle Hidden `
        -RedirectStandardOutput $stdoutPath `
        -RedirectStandardError $stderrPath `
        -PassThru
    $listener = $null
    for ($attempt = 0; $attempt -lt 100; $attempt++) {
        if ($server.HasExited) {
            throw 'readonly isolated health server exited before readiness'
        }
        $listener = Get-NetTCPConnection -LocalAddress $ListenAddress -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
        if ($null -ne $listener) {
            break
        }
        Start-Sleep -Milliseconds 100
    }
    if ($null -eq $listener -or [int]$listener.OwningProcess -ne [int]$server.Id) {
        throw 'readonly isolated health listener identity mismatch'
    }

    $health = Invoke-RestMethod -Method Get `
        -Uri "http://${ListenAddress}:$Port/__oa_isolated_validation_health" `
        -TimeoutSec 30
    $hmac = [Security.Cryptography.HMACSHA256]::new([Text.Encoding]::UTF8.GetBytes($nonce))
    try {
        $expectedProof = ($hmac.ComputeHash(
            [Text.Encoding]::UTF8.GetBytes([string]$pointer.database)
        ) | ForEach-Object { $_.ToString('x2') }) -join ''
    } finally {
        $hmac.Dispose()
    }
    if (([int]$health.pid -ne [int]$server.Id) -or
        (![bool]$health.databaseVerified) -or
        (![string]::Equals($expectedProof, [string]$health.proof, [StringComparison]::Ordinal))
    ) {
        throw 'readonly isolated health proof failed'
    }

    'isolated validation health binding passed'
} finally {
    if ($null -ne $server) {
        try {
            if (!$server.HasExited) {
                Stop-Process -Id $server.Id -Force
            }
            if (!$server.WaitForExit(5000) -or !$server.HasExited) {
                throw 'readonly isolated health server did not stop'
            }
        } catch {
            Write-Error 'readonly isolated health cleanup failed'
        }
    }
    Remove-Item Env:OA_ISOLATED_PROJECT_ROOT -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_RUNTIME_PATH -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_DB_NAME -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_CANONICAL_DB -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_DB_HOST -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_RUN_LABEL -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_RUN_DATE -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_EXPECTED_TABLE_COUNT -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_EXPECTED_FOREIGN_KEY_COUNT -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_LEGACY_POSTHOC -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_PREFIX -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_HEALTH_NONCE -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $stdoutPath, $stderrPath -Force -ErrorAction SilentlyContinue
}
