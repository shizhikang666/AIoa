[CmdletBinding()]
param(
    [ValidateRange(1024, 65535)]
    [int]$Port = 18083
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$runtimeRoot = (Resolve-Path -LiteralPath (Join-Path $projectRoot 'runtime')).Path
$pointerPath = Join-Path $runtimeRoot 'isolated-r10-validation-active-private.json'
$php = 'E:\project\socket\AI\testPhp\files\tools\php\php.exe'
$server = $null
$stdoutPath = [IO.Path]::GetTempFileName()
$stderrPath = [IO.Path]::GetTempFileName()

try {
    if (Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort $Port -State Listen -ErrorAction SilentlyContinue) {
        throw 'readonly isolated health port is occupied'
    }
    $pointer = Get-Content -Raw -LiteralPath $pointerPath | ConvertFrom-Json
    if ([string]$pointer.database -notmatch '^oa2026_r10_validation_20260718_[a-f0-9]{8}$') {
        throw 'readonly isolated health pointer is invalid'
    }
    $runtimePath = (Resolve-Path -LiteralPath (
        Join-Path $projectRoot ([string]$pointer.serverRuntime -replace '/', '\')
    )).Path
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
    $env:OA_ISOLATED_PREFIX = 'r10_health_readonly'
    $env:OA_ISOLATED_HEALTH_NONCE = $nonce

    $server = Start-Process -FilePath $php `
        -ArgumentList @(
            '-S',
            "127.0.0.1:$Port",
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
        $listener = Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
        if ($null -ne $listener) {
            break
        }
        Start-Sleep -Milliseconds 100
    }
    if ($null -eq $listener -or [int]$listener.OwningProcess -ne [int]$server.Id) {
        throw 'readonly isolated health listener identity mismatch'
    }

    $health = Invoke-RestMethod -Method Get `
        -Uri "http://127.0.0.1:$Port/__oa_isolated_validation_health" `
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
    Remove-Item Env:OA_ISOLATED_PREFIX -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_HEALTH_NONCE -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $stdoutPath, $stderrPath -Force -ErrorAction SilentlyContinue
}
