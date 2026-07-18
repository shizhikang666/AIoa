[CmdletBinding()]
param(
    [ValidateRange(1024, 65535)]
    [int]$Port = 18082,
    [ValidateRange(600, 14400)]
    [int]$ClientTimeoutSeconds = 3600
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$runtimeRoot = (Resolve-Path (Join-Path $projectRoot 'runtime')).Path
$pointerPath = Join-Path $runtimeRoot 'isolated-r10-validation-active-private.json'
$php = 'E:\project\socket\AI\testPhp\files\tools\php\php.exe'
$server = $null
$client = $null
$manifest = $null
$serverRuntime = $null
$validationDatabase = $null
$legacyStartedPath = $null
$mutationStartedPath = $null
$completionPath = $null
$failurePath = $null
$invalidPath = $null
$stage = 'preflight'
$mutationMarkerExists = $false
$targetNonReusable = $false
$reuseVerification = 'unknown'
$result = $null
$failure = $null

function Write-PrivateJsonCreateNew([string]$Path, $Value) {
    $json = ($Value | ConvertTo-Json -Depth 8) + [Environment]::NewLine
    $bytes = [System.Text.UTF8Encoding]::new($false).GetBytes($json)
    $stream = [System.IO.File]::Open($Path, [System.IO.FileMode]::CreateNew, [System.IO.FileAccess]::Write, [System.IO.FileShare]::None)
    try {
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Flush($true)
    } finally {
        $stream.Dispose()
    }
}

function Test-ContainedPath([string]$Child, [string]$Parent) {
    $childPrefix = $Child.TrimEnd('\') + '\'
    $parentPrefix = $Parent.TrimEnd('\') + '\'
    return $childPrefix.StartsWith($parentPrefix, [StringComparison]::OrdinalIgnoreCase)
}

function Test-ReparsePoint([string]$Path) {
    $item = Get-Item -LiteralPath $Path -Force
    return (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0)
}

try {
    if ($Port -lt 1024 -or $Port -gt 65535) {
        throw 'invalid isolated port'
    }
    if (!(Test-Path -LiteralPath $pointerPath)) {
        throw 'private validation pointer is missing'
    }
    $pointer = Get-Content -Raw -LiteralPath $pointerPath | ConvertFrom-Json
    if ([string]$pointer.database -notmatch '^oa2026_r10_validation_20260718_[a-f0-9]{8}$') {
        throw 'private validation database is invalid'
    }
    $validationDatabase = [string]$pointer.database
    $manifestRelative = [string]$pointer.manifest -replace '/', '\'
    $serverRuntimeRelative = [string]$pointer.serverRuntime -replace '/', '\'
    if ([IO.Path]::IsPathRooted($manifestRelative) -or [IO.Path]::IsPathRooted($serverRuntimeRelative)) {
        throw 'private validation paths must be relative'
    }
    $manifest = (Resolve-Path -LiteralPath (Join-Path $projectRoot $manifestRelative)).Path
    $serverRuntime = (Resolve-Path -LiteralPath (Join-Path $projectRoot $serverRuntimeRelative)).Path
    if (!(Test-ContainedPath $manifest $runtimeRoot) `
        -or !(Test-ContainedPath $serverRuntime $manifest) `
        -or (Test-ReparsePoint $runtimeRoot) `
        -or (Test-ReparsePoint $manifest) `
        -or (Test-ReparsePoint $serverRuntime)) {
        throw 'private validation paths escaped runtime'
    }
    $cloneMarker = Get-Content -Raw -LiteralPath (Join-Path $manifest 'clone-completed.json') | ConvertFrom-Json
    if (([string]$cloneMarker.status -ne 'completed') -or
        ([string]$cloneMarker.sourceDatabase -ne 'oa2026_rehearsal_r6_20260718_r10_migrated') -or
        ([string]$cloneMarker.targetDatabase -ne [string]$pointer.database) -or
        ([int]$cloneMarker.tableCount -ne 124) -or
        ([int]$cloneMarker.foreignKeyConstraintCount -ne 42) -or
        (![bool]$cloneMarker.foreignKeyDefinitionsMatch) -or
        (![bool]$cloneMarker.contentChecksumsMatch) -or
        (![bool]$cloneMarker.sourceConsistencyWindowPassed) -or
        (![bool]$cloneMarker.nonTableObjectsAbsent) -or
        ([string]$cloneMarker.schemaSha256 -notmatch '^[a-f0-9]{64}$') -or
        ([bool]$cloneMarker.sourceWritesPerformed)
    ) {
        throw 'clone evidence is incomplete'
    }
    $checksumProperties = @($cloneMarker.tableChecksums.PSObject.Properties)
    $rowCountProperties = @($cloneMarker.rowCounts.PSObject.Properties)
    if ($checksumProperties.Count -ne 124 -or $rowCountProperties.Count -ne 124) {
        throw 'clone fingerprint evidence is incomplete'
    }
    $checksumNames = @($checksumProperties.Name | Sort-Object)
    $rowCountNames = @($rowCountProperties.Name | Sort-Object)
    if (($checksumNames -join "`n") -cne ($rowCountNames -join "`n")) {
        throw 'clone fingerprint table sets differ'
    }

    $legacyStartedPath = Join-Path $manifest 'validation-started.json'
    $mutationStartedPath = Join-Path $manifest 'validation-mutation-started.json'
    $completionPath = Join-Path $manifest 'validation-completed.json'
    $failurePath = Join-Path $manifest 'validation-failed.json'
    $invalidPath = Join-Path $manifest 'validation-invalid.json'
    if (Test-Path -LiteralPath $legacyStartedPath) {
        throw 'legacy started validation must never be reused'
    }
    if (Test-Path -LiteralPath $mutationStartedPath) {
        throw 'mutation-started validation must never be reused'
    }
    if (Test-Path -LiteralPath $completionPath) {
        throw 'completed validation must never be reused'
    }
    if (Test-Path -LiteralPath $failurePath) {
        throw 'failed validation must never be reused'
    }
    if (Test-Path -LiteralPath $invalidPath) {
        throw 'invalid validation target must never be reused'
    }
    if ($null -ne (Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)) {
        throw 'isolated port is already in use'
    }

    $hash = [System.Security.Cryptography.SHA256]::Create().ComputeHash(
        [System.Text.Encoding]::UTF8.GetBytes([string]$pointer.database)
    )
    $prefix = 'r10_' + (($hash | ForEach-Object { $_.ToString('x2') }) -join '').Substring(0, 12)
    $nonceBytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $rng.GetBytes($nonceBytes)
    } finally {
        $rng.Dispose()
    }
    $nonce = ($nonceBytes | ForEach-Object { $_.ToString('x2') }) -join ''

    $env:OA_ISOLATED_PROJECT_ROOT = $projectRoot
    $env:OA_ISOLATED_RUNTIME_PATH = $serverRuntime
    $env:OA_ISOLATED_DB_NAME = [string]$pointer.database
    $env:OA_ISOLATED_PREFIX = $prefix
    $env:OA_ISOLATED_HEALTH_NONCE = $nonce
    $env:OA_ISOLATED_PORT = [string]$Port
    $env:OA_ISOLATED_MUTATION_MARKER_PATH = $mutationStartedPath

    $stage = 'server-start'
    $serverStdout = Join-Path $manifest 'isolated-server.stdout.log'
    $serverStderr = Join-Path $manifest 'isolated-server.stderr.log'
    $server = Start-Process -FilePath $php `
        -ArgumentList @('-S', "127.0.0.1:$Port", '-t', (Join-Path $projectRoot 'public'), (Join-Path $PSScriptRoot 'isolated-validation-router.php')) `
        -WorkingDirectory $projectRoot `
        -WindowStyle Hidden `
        -RedirectStandardOutput $serverStdout `
        -RedirectStandardError $serverStderr `
        -PassThru

    $listener = $null
    for ($attempt = 0; $attempt -lt 100; $attempt++) {
        if ($server.HasExited) {
            throw 'isolated server exited before readiness'
        }
        $listener = Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
        if ($null -ne $listener) {
            break
        }
        Start-Sleep -Milliseconds 100
    }
    if ($null -eq $listener -or [int]$listener.OwningProcess -ne [int]$server.Id) {
        throw 'isolated server listener identity mismatch'
    }

    $env:OA_ISOLATED_EXPECTED_SERVER_PID = [string]$server.Id
    $stage = 'validation-client'
    $clientStartedAt = [DateTime]::UtcNow
    $clientStdout = Join-Path $manifest 'isolated-client.stdout.log'
    $clientStderr = Join-Path $manifest 'isolated-client.stderr.log'
    $client = Start-Process -FilePath $php `
        -ArgumentList @((Join-Path $PSScriptRoot 'isolated-approval-validation-client.php')) `
        -WorkingDirectory $projectRoot `
        -WindowStyle Hidden `
        -RedirectStandardOutput $clientStdout `
        -RedirectStandardError $clientStderr `
        -PassThru
    if (!$client.WaitForExit($ClientTimeoutSeconds * 1000)) {
        Stop-Process -Id $client.Id -Force -ErrorAction SilentlyContinue
        $null = $client.WaitForExit(5000)
        throw 'isolated validation client timed out'
    }
    $clientDurationSeconds = [Math]::Round(([DateTime]::UtcNow - $clientStartedAt).TotalSeconds, 3)
    if ($client.ExitCode -ne 0) {
        throw 'isolated validation client failed'
    }
    $result = ((Get-Content -Raw -LiteralPath $clientStdout) | ConvertFrom-Json)
    if (([string]$result.status -ne 'completed') -or
        (![bool]$result.serverIdentityVerified) -or
        (![bool]$result.approvalContinuationPassed) -or
        (![bool]$result.currentTaskReadApisPassed) -or
        (![bool]$result.nextTaskReadApisPassed) -or
        (![bool]$result.nextTaskCountMatchedDatabase) -or
        (![bool]$result.businessFingerprintsUnchanged) -or
        (![bool]$result.canonicalFingerprintsUnchanged) -or
        (![bool]$result.databaseStructuresUnchanged) -or
        (![bool]$result.isolatedExpectedTableChangesOnly) -or
        (![bool]$result.exactSingleTransitionMatched) -or
        (![bool]$result.validationWritesIsolated)
    ) {
        throw 'isolated validation client evidence is incomplete'
    }
    if (!(Test-Path -LiteralPath $mutationStartedPath)) {
        throw 'isolated mutation-started marker is missing'
    }
    $mutationMarker = Get-Content -Raw -LiteralPath $mutationStartedPath | ConvertFrom-Json
    if (([string]$mutationMarker.status -ne 'mutation-started') -or
        (![bool]$mutationMarker.targetMustNotBeReusedIfInterrupted) -or
        (![bool]$mutationMarker.readonlyPreflightPassed) -or
        (![bool]$mutationMarker.serverIdentityVerified)
    ) {
        throw 'isolated mutation-started marker is incomplete'
    }
} catch {
    $failure = $stage
} finally {
    $cleanupPassed = $true
    if ($null -ne $client) {
        try {
            if (!$client.HasExited) {
                Stop-Process -Id $client.Id -Force
            }
            $clientExited = $client.WaitForExit(5000)
            if (!$clientExited -or !$client.HasExited) {
                $cleanupPassed = $false
            }
        } catch {
            $cleanupPassed = $false
        }
    }
    if ($null -ne $server) {
        try {
            if (!$server.HasExited) {
                Stop-Process -Id $server.Id -Force
            }
            $serverExited = $server.WaitForExit(5000)
            if (!$serverExited -or !$server.HasExited) {
                $cleanupPassed = $false
            }
        } catch {
            $cleanupPassed = $false
        }
    }
    for ($attempt = 0; $attempt -lt 50; $attempt++) {
        if ($null -eq (Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)) {
            break
        }
        Start-Sleep -Milliseconds 100
    }
    if ($null -ne (Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)) {
        $cleanupPassed = $false
    }
    if (!$cleanupPassed -and $null -eq $failure) {
        $failure = 'server-cleanup'
    }

    $mutationMarkerExists = ($null -ne $mutationStartedPath -and (Test-Path -LiteralPath $mutationStartedPath))
    $legacyMarkerExists = ($null -ne $legacyStartedPath -and (Test-Path -LiteralPath $legacyStartedPath))
    $completionMarkerExists = ($null -ne $completionPath -and (Test-Path -LiteralPath $completionPath))
    $failureMarkerExists = ($null -ne $failurePath -and (Test-Path -LiteralPath $failurePath))
    $invalidMarkerExists = ($null -ne $invalidPath -and (Test-Path -LiteralPath $invalidPath))
    $targetNonReusable = $mutationMarkerExists -or $legacyMarkerExists -or $completionMarkerExists -or $failureMarkerExists -or $invalidMarkerExists

    if ($null -ne $failure `
        -and !$targetNonReusable `
        -and $cleanupPassed `
        -and $null -ne $manifest `
        -and $null -ne $serverRuntime `
        -and $null -ne $validationDatabase) {
        try {
            $env:OA_ISOLATED_PROJECT_ROOT = $projectRoot
            $env:OA_ISOLATED_RUNTIME_PATH = $serverRuntime
            $env:OA_ISOLATED_DB_NAME = $validationDatabase
            $env:OA_ISOLATED_PREFIX = 'r10_reuse_verify'
            $verificationRaw = @(& $php (Join-Path $PSScriptRoot 'verify-isolated-validation-reusability.php') 2>$null)
            $verificationExitCode = $LASTEXITCODE
            $verification = (($verificationRaw -join "`n") | ConvertFrom-Json)
            if ($verificationExitCode -eq 0 `
                -and [string]$verification.status -eq 'reusable' `
                -and [bool]$verification.reusable) {
                $reuseVerification = 'reusable'
            } elseif ($verificationExitCode -eq 2 `
                -and [string]$verification.status -eq 'invalid' `
                -and ![bool]$verification.reusable) {
                try {
                    Write-PrivateJsonCreateNew $invalidPath ([ordered]@{
                        status = 'invalid'
                        reason = 'pre-mutation-state-drift'
                        targetMustNotBeReused = $true
                        detectedAt = [DateTime]::UtcNow.ToString('o')
                    })
                    $invalidMarkerExists = $true
                    $targetNonReusable = $true
                    $reuseVerification = 'invalid'
                } catch {
                    if (Test-Path -LiteralPath $invalidPath) {
                        $invalidMarkerExists = $true
                        $targetNonReusable = $true
                        $reuseVerification = 'invalid'
                    } else {
                        $reuseVerification = 'unknown'
                    }
                }
            }
        } catch {
            $reuseVerification = 'unknown'
        }
    }

    Remove-Item Env:OA_ISOLATED_EXPECTED_SERVER_PID -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_PROJECT_ROOT -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_RUNTIME_PATH -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_DB_NAME -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_PREFIX -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_HEALTH_NONCE -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_PORT -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_MUTATION_MARKER_PATH -ErrorAction SilentlyContinue
}

if ($null -eq $failure) {
    $evidence = [ordered]@{
        status = 'completed'
        serverIdentityVerified = $true
        serverProcessStopped = $true
        clientProcessStopped = $true
        readonlyPreflightCompletedBeforeMutationMarker = $true
        mutationStartedImmediatelyBeforeApproval = $true
        approvalContinuationPassed = $true
        currentTaskReadApisPassed = $true
        nextTaskReadApisPassed = $true
        nextTaskCountMatchedDatabase = $true
        businessFingerprintsUnchanged = $true
        canonicalFingerprintsUnchanged = $true
        databaseStructuresUnchanged = $true
        isolatedExpectedTableChangesOnly = $true
        exactSingleTransitionMatched = $true
        validationWritesIsolated = $true
        clientDurationSeconds = $clientDurationSeconds
        completedAt = [DateTime]::UtcNow.ToString('o')
    }
    try {
        Write-PrivateJsonCreateNew $completionPath $evidence
    } catch {
        $failure = 'completion-evidence'
    }
}

if ($null -ne $failure) {
    if (($mutationMarkerExists -or $legacyMarkerExists) -and $null -ne $failurePath) {
        try {
            Write-PrivateJsonCreateNew $failurePath ([ordered]@{
                status = 'failed'
                stage = $failure
                targetMustNotBeReused = $true
                failedAt = [DateTime]::UtcNow.ToString('o')
            })
        } catch {
        }
    }
    $reuseState = if ($targetNonReusable) {
        'target is permanently non-reusable'
    } elseif ($reuseVerification -eq 'reusable') {
        'independent structure and content verification proved the target reusable'
    } else {
        'target reuse state is unknown; do not reuse until independently verified'
    }
    Write-Error ("R10 isolated validation failed at stage: " + $failure + '; ' + $reuseState)
    exit 1
}

$evidence | ConvertTo-Json -Compress
