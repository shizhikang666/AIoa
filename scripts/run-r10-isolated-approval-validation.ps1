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
    [string]$TargetFinalMarkerPath = '',
    [string]$PhpPath = '',
    [string]$ListenAddress = '',
    [string]$ApprovalComment = '',
    [ValidateRange(1024, 65535)]
    [int]$Port = 18082,
    [ValidateRange(600, 14400)]
    [int]$ClientTimeoutSeconds = 3600,
    [switch]$InvocationPreflightOnly,
    [switch]$EvidencePreflightOnly
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$runtimeRoot = (Resolve-Path (Join-Path $projectRoot 'runtime')).Path
$pointerResolvedPath = $null
$targetFinalResolvedPath = $null
$php = $null
$server = $null
$client = $null
$manifest = $null
$serverRuntime = $null
$isolatedDatabase = $null
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
$listenAddressValidated = $false

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

function Resolve-PrivateExistingFile([string]$Value, [string]$Label) {
    if ([string]::IsNullOrWhiteSpace($Value) -or [IO.Path]::IsPathRooted($Value)) {
        throw "$Label must be a relative private runtime path"
    }
    $candidate = Join-Path $projectRoot $Value
    $candidateItem = Get-Item -LiteralPath $candidate -Force
    $resolved = (Resolve-Path -LiteralPath $candidate).Path
    if (!(Test-Path -LiteralPath $resolved -PathType Leaf) `
        -or !(Test-ContainedPath $resolved $runtimeRoot) `
        -or (($candidateItem.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) `
        -or (Test-ReparsePoint $resolved)) {
        throw "$Label is missing or outside private runtime"
    }
    return $resolved
}

function Read-PinnedJsonFile([string]$Path, [string]$ExpectedSha256, [string]$Label) {
    if ($ExpectedSha256 -cnotmatch '^[a-f0-9]{64}$') {
        throw "$Label SHA256 is missing or invalid"
    }
    $item = Get-Item -LiteralPath $Path -Force
    if (!(Test-Path -LiteralPath $Path -PathType Leaf) `
        -or (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) `
        -or (Test-ReparsePoint $Path)) {
        throw "$Label is missing or unsafe"
    }
    $bytes = [IO.File]::ReadAllBytes($Path)
    $hasher = [Security.Cryptography.SHA256]::Create()
    try {
        $actualSha256 = (($hasher.ComputeHash($bytes) | ForEach-Object { $_.ToString('x2') }) -join '')
    } finally {
        $hasher.Dispose()
    }
    if ($actualSha256 -cne $ExpectedSha256) {
        throw "$Label SHA256 differs from the private pointer"
    }
    $utf8 = [Text.UTF8Encoding]::new($false, $true)
    return ($utf8.GetString($bytes) | ConvertFrom-Json)
}

function Test-RunDate([string]$Value) {
    if ($Value -notmatch '^[0-9]{8}$') {
        throw 'run date must use YYYYMMDD'
    }
    $parsed = [DateTime]::MinValue
    if (![DateTime]::TryParseExact(
        $Value,
        'yyyyMMdd',
        [Globalization.CultureInfo]::InvariantCulture,
        [Globalization.DateTimeStyles]::None,
        [ref]$parsed
    )) {
        throw 'run date is invalid'
    }
}

try {
    $stage = 'invocation'
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
    $listenAddressValidated = $true
    if ($RunLabel -cnotmatch '^[a-z][a-z0-9_-]{1,31}$') {
        throw 'run label is invalid'
    }
    Test-RunDate $RunDate
    if ($ExpectedTableCount -lt 1 -or $ExpectedTableCount -gt 100000) {
        throw 'expected table count is invalid'
    }
    if ($ExpectedForeignKeyCount -lt 0 -or $ExpectedForeignKeyCount -gt 100000) {
        throw 'expected foreign key count is invalid'
    }
    if ([string]::IsNullOrWhiteSpace($PointerPath) -or [IO.Path]::IsPathRooted($PointerPath)) {
        throw 'pointer path must be an explicit relative private runtime path'
    }
    if ([string]::IsNullOrWhiteSpace($TargetFinalMarkerPath) -or [IO.Path]::IsPathRooted($TargetFinalMarkerPath)) {
        throw 'target-final marker path must be an explicit relative private runtime path'
    }
    $approvalCommentBytes = [Text.Encoding]::UTF8.GetByteCount($ApprovalComment)
    if ([string]::IsNullOrWhiteSpace($ApprovalComment) `
        -or $approvalCommentBytes -lt 8 `
        -or $approvalCommentBytes -gt 200 `
        -or $ApprovalComment -match '[\x00-\x1F\x7F]') {
        throw 'approval comment is invalid'
    }
    if ([string]::IsNullOrWhiteSpace($PhpPath)) {
        throw 'PHP executable path is required'
    }
    $php = (Resolve-Path -LiteralPath $PhpPath).Path
    if (!(Test-Path -LiteralPath $php -PathType Leaf) -or (Test-ReparsePoint $php)) {
        throw 'PHP executable path is invalid'
    }
    if ($Port -lt 1024 -or $Port -gt 65535) {
        throw 'invalid isolated port'
    }
    if ($InvocationPreflightOnly -and $EvidencePreflightOnly) {
        throw 'only one preflight mode may be selected'
    }
    if ($InvocationPreflightOnly) {
        [ordered]@{
            status = 'invocation-valid'
            databaseWritesPerformed = $false
            privateEvidenceRead = $false
        } | ConvertTo-Json -Compress
        exit 0
    }
    $pointerResolvedPath = Resolve-PrivateExistingFile $PointerPath 'private validation pointer'
    $targetFinalResolvedPath = Resolve-PrivateExistingFile $TargetFinalMarkerPath 'target-final marker'
    $pointer = Get-Content -Raw -LiteralPath $pointerResolvedPath | ConvertFrom-Json
    $isolatedDatabase = [string]$pointer.database
    Assert-SafeDatabaseIdentifier $isolatedDatabase 'target'
    if ($isolatedDatabase -cne $ValidationDatabase) {
        throw 'private validation target differs from the explicit invocation'
    }
    if ($null -eq $pointer.expectedForeignKeyConstraintCount -or
        [int]$pointer.version -ne 2 -or
        [string]$pointer.canonicalDatabase -cne $CanonicalDatabase -or
        [string]$pointer.databaseHost -cne $DatabaseHost -or
        [string]$pointer.runLabel -cne $RunLabel -or
        [string]$pointer.runDate -cne $RunDate -or
        [int]$pointer.expectedTableCount -ne $ExpectedTableCount -or
        [int]$pointer.expectedForeignKeyConstraintCount -ne $ExpectedForeignKeyCount) {
        throw 'private validation pointer metadata differs from the explicit invocation'
    }
    $pointerTargetFinalPath = Resolve-PrivateExistingFile ([string]$pointer.targetFinalMarker) 'pointer target-final marker'
    if ($pointerTargetFinalPath -cne $targetFinalResolvedPath) {
        throw 'private validation target-final marker differs from the explicit invocation'
    }
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
    $cloneMarkerResolvedPath = Resolve-PrivateExistingFile ([string]$pointer.cloneCompletedMarker) 'pointer clone-completed marker'
    $expectedCloneMarkerPath = (Resolve-Path -LiteralPath (Join-Path $manifest 'clone-completed.json')).Path
    if ($cloneMarkerResolvedPath -cne $expectedCloneMarkerPath `
        -or !(Test-ContainedPath $cloneMarkerResolvedPath $manifest)) {
        throw 'private validation clone marker differs from the manifest'
    }
    $cloneMarker = Read-PinnedJsonFile `
        $cloneMarkerResolvedPath `
        ([string]$pointer.cloneCompletedMarkerSha256) `
        'clone-completed marker'
    $null = Read-PinnedJsonFile `
        $targetFinalResolvedPath `
        ([string]$pointer.targetFinalMarkerSha256) `
        'target-final marker'
    if (([string]$cloneMarker.status -ne 'completed') -or
        ([string]$cloneMarker.sourceDatabase -cne $CanonicalDatabase) -or
        ([string]$cloneMarker.targetDatabase -cne $isolatedDatabase) -or
        ([string]$cloneMarker.runLabel -cne $RunLabel) -or
        ([string]$cloneMarker.runDate -cne $RunDate) -or
        ([string]$cloneMarker.databaseHost -cne $DatabaseHost) -or
        ([int]$cloneMarker.expectedTableCount -ne $ExpectedTableCount) -or
        ($null -eq $cloneMarker.expectedForeignKeyConstraintCount) -or
        ([int]$cloneMarker.expectedForeignKeyConstraintCount -ne $ExpectedForeignKeyCount) -or
        ([int]$cloneMarker.tableCount -ne $ExpectedTableCount) -or
        ([int]$cloneMarker.foreignKeyConstraintCount -ne $ExpectedForeignKeyCount) -or
        (![bool]$cloneMarker.foreignKeyDefinitionsMatch) -or
        (![bool]$cloneMarker.contentChecksumsMatch) -or
        (![bool]$cloneMarker.sourceConsistencyWindowPassed) -or
        (![bool]$cloneMarker.nonTableObjectsAbsent) -or
        ([string]$cloneMarker.structureHashAlgorithm -cne 'show-create-structure-v1') -or
        ([string]$cloneMarker.schemaSha256 -notmatch '^[a-f0-9]{64}$') -or
        ([bool]$cloneMarker.sourceWritesPerformed)
    ) {
        throw 'clone evidence is incomplete'
    }
    $checksumProperties = @($cloneMarker.tableChecksums.PSObject.Properties)
    $rowCountProperties = @($cloneMarker.rowCounts.PSObject.Properties)
    if ($checksumProperties.Count -ne $ExpectedTableCount -or $rowCountProperties.Count -ne $ExpectedTableCount) {
        throw 'clone fingerprint evidence is incomplete'
    }
    $checksumNames = @($checksumProperties.Name | Sort-Object)
    $rowCountNames = @($rowCountProperties.Name | Sort-Object)
    if (($checksumNames -join "`n") -cne ($rowCountNames -join "`n")) {
        throw 'clone fingerprint table sets differ'
    }

    if ($EvidencePreflightOnly) {
        [ordered]@{
            status = 'evidence-valid'
            databaseConnectionsOpened = 0
            databaseWritesPerformed = $false
            networkConnectionsOpened = 0
            pinnedEvidenceVerified = $true
        } | ConvertTo-Json -Compress
        exit 0
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
    if ($null -ne (Get-NetTCPConnection -LocalAddress $ListenAddress -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)) {
        throw 'isolated port is already in use'
    }

    $hash = [System.Security.Cryptography.SHA256]::Create().ComputeHash(
        [System.Text.Encoding]::UTF8.GetBytes([string]$pointer.database)
    )
    $prefix = ($RunLabel -replace '-', '_') + '_' + (($hash | ForEach-Object { $_.ToString('x2') }) -join '').Substring(0, 12)
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
    $env:OA_ISOLATED_CANONICAL_DB = $CanonicalDatabase
    $env:OA_ISOLATED_DB_HOST = $DatabaseHost
    $env:OA_ISOLATED_RUN_LABEL = $RunLabel
    $env:OA_ISOLATED_RUN_DATE = $RunDate
    $env:OA_ISOLATED_EXPECTED_TABLE_COUNT = [string]$ExpectedTableCount
    $env:OA_ISOLATED_EXPECTED_FOREIGN_KEY_COUNT = [string]$ExpectedForeignKeyCount
    $env:OA_ISOLATED_POINTER_PATH = $pointerResolvedPath
    $env:OA_ISOLATED_TARGET_FINAL_MARKER_PATH = $targetFinalResolvedPath
    $env:OA_ISOLATED_APPROVAL_COMMENT = $ApprovalComment
    $env:OA_ISOLATED_LISTEN_ADDRESS = $ListenAddress
    $env:OA_ISOLATED_LEGACY_POSTHOC = '0'
    $env:OA_ISOLATED_PREFIX = $prefix
    $env:OA_ISOLATED_HEALTH_NONCE = $nonce
    $env:OA_ISOLATED_PORT = [string]$Port
    $env:OA_ISOLATED_MUTATION_MARKER_PATH = $mutationStartedPath

    $stage = 'server-start'
    $serverStdout = Join-Path $manifest 'isolated-server.stdout.log'
    $serverStderr = Join-Path $manifest 'isolated-server.stderr.log'
    $server = Start-Process -FilePath $php `
        -ArgumentList @('-S', "${ListenAddress}:$Port", '-t', (Join-Path $projectRoot 'public'), (Join-Path $PSScriptRoot 'isolated-validation-router.php')) `
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
        $listener = Get-NetTCPConnection -LocalAddress $ListenAddress -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
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
    if ($listenAddressValidated) {
        for ($attempt = 0; $attempt -lt 50; $attempt++) {
            if ($null -eq (Get-NetTCPConnection -LocalAddress $ListenAddress -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)) {
                break
            }
            Start-Sleep -Milliseconds 100
        }
        if ($null -ne (Get-NetTCPConnection -LocalAddress $ListenAddress -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)) {
            $cleanupPassed = $false
        }
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
        -and $null -ne $isolatedDatabase) {
        try {
            $env:OA_ISOLATED_PROJECT_ROOT = $projectRoot
            $env:OA_ISOLATED_RUNTIME_PATH = $serverRuntime
            $env:OA_ISOLATED_DB_NAME = $isolatedDatabase
            $env:OA_ISOLATED_CANONICAL_DB = $CanonicalDatabase
            $env:OA_ISOLATED_DB_HOST = $DatabaseHost
            $env:OA_ISOLATED_RUN_LABEL = $RunLabel
            $env:OA_ISOLATED_RUN_DATE = $RunDate
            $env:OA_ISOLATED_EXPECTED_TABLE_COUNT = [string]$ExpectedTableCount
            $env:OA_ISOLATED_EXPECTED_FOREIGN_KEY_COUNT = [string]$ExpectedForeignKeyCount
            $env:OA_ISOLATED_POINTER_PATH = $pointerResolvedPath
            $env:OA_ISOLATED_TARGET_FINAL_MARKER_PATH = $targetFinalResolvedPath
            $env:OA_ISOLATED_APPROVAL_COMMENT = $ApprovalComment
            $env:OA_ISOLATED_LISTEN_ADDRESS = $ListenAddress
            $env:OA_ISOLATED_LEGACY_POSTHOC = '0'
            $env:OA_ISOLATED_PREFIX = ($RunLabel -replace '-', '_') + '_reuse_verify'
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
    Remove-Item Env:OA_ISOLATED_CANONICAL_DB -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_DB_HOST -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_RUN_LABEL -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_RUN_DATE -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_EXPECTED_TABLE_COUNT -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_EXPECTED_FOREIGN_KEY_COUNT -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_POINTER_PATH -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_TARGET_FINAL_MARKER_PATH -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_APPROVAL_COMMENT -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_LISTEN_ADDRESS -ErrorAction SilentlyContinue
    Remove-Item Env:OA_ISOLATED_LEGACY_POSTHOC -ErrorAction SilentlyContinue
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
    Write-Error ("isolated validation failed at stage: " + $failure + '; ' + $reuseState)
    exit 1
}

$evidence | ConvertTo-Json -Compress
