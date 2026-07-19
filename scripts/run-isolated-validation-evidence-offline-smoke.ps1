[CmdletBinding()]
param(
    [string]$PhpPath = ''
)

$ErrorActionPreference = 'Stop'
$runner = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot 'run-r10-isolated-approval-validation.ps1')).Path
$projectRoot = Split-Path -Parent $PSScriptRoot
$runtimeRoot = (Resolve-Path -LiteralPath (Join-Path $projectRoot 'runtime')).Path
if ([string]::IsNullOrWhiteSpace($PhpPath)) {
    throw 'offline evidence smoke requires an explicit PHP executable path'
}
$php = (Resolve-Path -LiteralPath $PhpPath).Path
$powershell = Join-Path $PSHOME 'powershell.exe'
$fixtureName = 'isolated-evidence-smoke-' + [Guid]::NewGuid().ToString('N')
$fixtureRelative = 'runtime/' + $fixtureName
$fixtureRoot = Join-Path $runtimeRoot $fixtureName
$manifest = Join-Path $fixtureRoot 'manifest'
$serverRuntime = Join-Path $manifest 'server-runtime'
$clonePath = Join-Path $manifest 'clone-completed.json'
$targetFinalPath = Join-Path $fixtureRoot 'target-final.json'
$pointerPath = Join-Path $fixtureRoot 'pointer.json'
$exitProbeStdout = Join-Path $fixtureRoot 'exit-probe.stdout.log'
$exitProbeStderr = Join-Path $fixtureRoot 'exit-probe.stderr.log'
$exitProbe = $null
$nonzeroProbeStdout = Join-Path $fixtureRoot 'nonzero-probe.stdout.log'
$nonzeroProbeStderr = Join-Path $fixtureRoot 'nonzero-probe.stderr.log'
$nonzeroProbe = $null
$timeoutProbeStdout = Join-Path $fixtureRoot 'timeout-probe.stdout.log'
$timeoutProbeStderr = Join-Path $fixtureRoot 'timeout-probe.stderr.log'
$timeoutProbe = $null
$utf8 = [Text.UTF8Encoding]::new($false)

function Write-Utf8Json([string]$Path, $Value) {
    $json = ($Value | ConvertTo-Json -Depth 12) + [Environment]::NewLine
    [IO.File]::WriteAllText($Path, $json, $utf8)
}

function Get-Sha256([string]$Path) {
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}

function Invoke-EvidencePreflight {
    $arguments = @(
        '-NoProfile',
        '-ExecutionPolicy',
        'Bypass',
        '-File',
        $runner,
        '-PointerPath',
        ($fixtureRelative + '/pointer.json'),
        '-CanonicalDatabase',
        'fixture_canonical',
        '-ConfirmCanonicalDatabase',
        'fixture_canonical',
        '-ValidationDatabase',
        'fixture_target',
        '-ConfirmValidationDatabase',
        'fixture_target',
        '-DatabaseHost',
        '127.0.0.1',
        '-RunLabel',
        'formal-fixture',
        '-RunDate',
        '20300102',
        '-ExpectedTableCount',
        '1',
        '-ExpectedForeignKeyCount',
        '0',
        '-TargetFinalMarkerPath',
        ($fixtureRelative + '/target-final.json'),
        '-PhpPath',
        $php,
        '-ListenAddress',
        '127.0.0.1',
        '-ApprovalComment',
        'formal isolated evidence fixture',
        '-EvidencePreflightOnly'
    )
    $previousPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $output = @(& $powershell @arguments 2>$null)
        return [pscustomobject]@{
            ExitCode = $LASTEXITCODE
            Output = ($output -join "`n")
        }
    } finally {
        $ErrorActionPreference = $previousPreference
    }
}

try {
    $null = New-Item -ItemType Directory -Path $serverRuntime
    Write-Utf8Json $clonePath ([ordered]@{
        status = 'completed'
        sourceDatabase = 'fixture_canonical'
        targetDatabase = 'fixture_target'
        runLabel = 'formal-fixture'
        runDate = '20300102'
        databaseHost = '127.0.0.1'
        expectedTableCount = 1
        expectedForeignKeyConstraintCount = 0
        tableCount = 1
        foreignKeyConstraintCount = 0
        foreignKeyDefinitionsMatch = $true
        contentChecksumsMatch = $true
        sourceConsistencyWindowPassed = $true
        nonTableObjectsAbsent = $true
        structureHashAlgorithm = 'show-create-structure-v1'
        schemaSha256 = ('a' * 64)
        sourceWritesPerformed = $false
        tableChecksums = [ordered]@{ fixture_table = '123' }
        rowCounts = [ordered]@{ fixture_table = 1 }
    })
    Write-Utf8Json $targetFinalPath ([ordered]@{
        status = 'completed'
        database = 'fixture_canonical'
    })
    $cloneSha256 = Get-Sha256 $clonePath
    $targetFinalSha256 = Get-Sha256 $targetFinalPath
    Write-Utf8Json $pointerPath ([ordered]@{
        version = 2
        canonicalDatabase = 'fixture_canonical'
        database = 'fixture_target'
        databaseHost = '127.0.0.1'
        runLabel = 'formal-fixture'
        runDate = '20300102'
        expectedTableCount = 1
        expectedForeignKeyConstraintCount = 0
        manifest = ($fixtureRelative + '/manifest')
        cloneCompletedMarker = ($fixtureRelative + '/manifest/clone-completed.json')
        cloneCompletedMarkerSha256 = $cloneSha256
        serverRuntime = ($fixtureRelative + '/manifest/server-runtime')
        targetFinalMarker = ($fixtureRelative + '/target-final.json')
        targetFinalMarkerSha256 = $targetFinalSha256
    })

    $intact = Invoke-EvidencePreflight
    if ($intact.ExitCode -ne 0) {
        throw 'runner rejected intact pinned evidence'
    }
    $intactEvidence = $intact.Output | ConvertFrom-Json
    if ([string]$intactEvidence.status -ne 'evidence-valid' `
        -or [int]$intactEvidence.databaseConnectionsOpened -ne 0 `
        -or [bool]$intactEvidence.databaseWritesPerformed `
        -or [int]$intactEvidence.networkConnectionsOpened -ne 0 `
        -or ![bool]$intactEvidence.pinnedEvidenceVerified) {
        throw 'runner evidence preflight result is incomplete'
    }

    Write-Utf8Json $clonePath ([ordered]@{ status = 'same-path-replacement' })
    if ((Invoke-EvidencePreflight).ExitCode -eq 0) {
        throw 'runner accepted a same-path clone marker replacement'
    }

    Write-Utf8Json $clonePath ([ordered]@{
        status = 'completed'
        sourceDatabase = 'fixture_canonical'
        targetDatabase = 'fixture_target'
        runLabel = 'formal-fixture'
        runDate = '20300102'
        databaseHost = '127.0.0.1'
        expectedTableCount = 1
        expectedForeignKeyConstraintCount = 0
        tableCount = 1
        foreignKeyConstraintCount = 0
        foreignKeyDefinitionsMatch = $true
        contentChecksumsMatch = $true
        sourceConsistencyWindowPassed = $true
        nonTableObjectsAbsent = $true
        structureHashAlgorithm = 'show-create-structure-v1'
        schemaSha256 = ('a' * 64)
        sourceWritesPerformed = $false
        tableChecksums = [ordered]@{ fixture_table = '123' }
        rowCounts = [ordered]@{ fixture_table = 1 }
    })
    if ((Get-Sha256 $clonePath) -cne $cloneSha256) {
        throw 'runner smoke could not restore the original clone marker bytes'
    }
    Write-Utf8Json $targetFinalPath ([ordered]@{ status = 'same-path-replacement' })
    if ((Invoke-EvidencePreflight).ExitCode -eq 0) {
        throw 'runner accepted a same-path target-final marker replacement'
    }

    $exitProbe = Start-Process -FilePath $php `
        -ArgumentList @((Join-Path $PSScriptRoot 'isolated-validation-parameters-offline-smoke.php')) `
        -WorkingDirectory $projectRoot `
        -WindowStyle Hidden `
        -RedirectStandardOutput $exitProbeStdout `
        -RedirectStandardError $exitProbeStderr `
        -PassThru
    $null = $exitProbe.Handle
    if (!$exitProbe.WaitForExit(60000)) {
        throw 'runner handle-pinning probe timed out'
    }
    if ($exitProbe.ExitCode -ne 0 `
        -or (Get-Item -LiteralPath $exitProbeStderr).Length -ne 0 `
        -or (Get-Item -LiteralPath $exitProbeStdout).Length -eq 0) {
        throw 'runner handle-pinning probe did not preserve the child exit code'
    }

    $nonzeroProbe = Start-Process -FilePath $php `
        -ArgumentList @('-r', 'exit(7);') `
        -WorkingDirectory $projectRoot `
        -WindowStyle Hidden `
        -RedirectStandardOutput $nonzeroProbeStdout `
        -RedirectStandardError $nonzeroProbeStderr `
        -PassThru
    $null = $nonzeroProbe.Handle
    if (!$nonzeroProbe.WaitForExit(60000)) {
        throw 'runner nonzero-exit probe timed out'
    }
    if ($nonzeroProbe.ExitCode -ne 7 `
        -or (Get-Item -LiteralPath $nonzeroProbeStderr).Length -ne 0) {
        throw 'runner handle-pinning probe masked a real nonzero child exit code'
    }

    $timeoutProbe = Start-Process -FilePath $php `
        -ArgumentList @('-r', 'sleep(5);') `
        -WorkingDirectory $projectRoot `
        -WindowStyle Hidden `
        -RedirectStandardOutput $timeoutProbeStdout `
        -RedirectStandardError $timeoutProbeStderr `
        -PassThru
    $null = $timeoutProbe.Handle
    if ($timeoutProbe.WaitForExit(100)) {
        throw 'runner timeout probe exited before the timeout path was exercised'
    }
    Stop-Process -Id $timeoutProbe.Id -Force -ErrorAction Stop
    if (!$timeoutProbe.WaitForExit(5000) -or !$timeoutProbe.HasExited) {
        throw 'runner timeout probe cleanup did not stop the child process'
    }

    'isolated validation runner evidence offline smoke passed'
} finally {
    foreach ($probe in @($timeoutProbe, $nonzeroProbe, $exitProbe)) {
        if ($null -ne $probe -and !$probe.HasExited) {
            Stop-Process -Id $probe.Id -Force -ErrorAction SilentlyContinue
            $null = $probe.WaitForExit(5000)
        }
    }
    foreach ($path in @(
        $timeoutProbeStdout,
        $timeoutProbeStderr,
        $nonzeroProbeStdout,
        $nonzeroProbeStderr,
        $exitProbeStdout,
        $exitProbeStderr,
        $pointerPath,
        $targetFinalPath,
        $clonePath
    )) {
        if (Test-Path -LiteralPath $path) {
            Remove-Item -LiteralPath $path -Force
        }
    }
    foreach ($path in @($serverRuntime, $manifest, $fixtureRoot)) {
        if (Test-Path -LiteralPath $path) {
            Remove-Item -LiteralPath $path -Force
        }
    }
}
