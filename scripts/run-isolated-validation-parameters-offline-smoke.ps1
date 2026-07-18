[CmdletBinding()]
param(
    [string]$PhpPath = ''
)

$ErrorActionPreference = 'Stop'
$runner = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot 'run-r10-isolated-approval-validation.ps1')).Path
if ([string]::IsNullOrWhiteSpace($PhpPath)) {
    throw 'offline runner smoke requires an explicit PHP executable path'
}
$php = (Resolve-Path -LiteralPath $PhpPath).Path
$powershell = Join-Path $PSHOME 'powershell.exe'
$arguments = @(
    '-NoProfile',
    '-ExecutionPolicy',
    'Bypass',
    '-File',
    $runner,
    '-PointerPath',
    'runtime/fixture-pointer.json',
    '-CanonicalDatabase',
    'fixture_canonical',
    '-ValidationDatabase',
    'fixture_target',
    '-DatabaseHost',
    '127.0.0.1',
    '-RunLabel',
    'formal-fixture',
    '-RunDate',
    '20300102',
    '-ExpectedTableCount',
    '8',
    '-ExpectedForeignKeyCount',
    '0',
    '-TargetFinalMarkerPath',
    'runtime/fixture-target-final.json',
    '-PhpPath',
    $php,
    '-ListenAddress',
    '127.0.0.1',
    '-ApprovalComment',
    'formal isolated approval fixture',
    '-InvocationPreflightOnly'
)

function Invoke-ExpectedFailure([string]$CanonicalConfirmation, [string]$TargetConfirmation) {
    $previousPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        & $powershell @arguments `
            -ConfirmCanonicalDatabase $CanonicalConfirmation `
            -ConfirmValidationDatabase $TargetConfirmation 1>$null 2>$null
        return $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousPreference
    }
}

$positive = @(& $powershell @arguments `
    -ConfirmCanonicalDatabase fixture_canonical `
    -ConfirmValidationDatabase fixture_target 2>$null)
if ($LASTEXITCODE -ne 0) {
    throw 'runner rejected a complete explicit offline invocation'
}
$evidence = (($positive -join "`n") | ConvertFrom-Json)
if ([string]$evidence.status -ne 'invocation-valid' `
    -or [bool]$evidence.databaseWritesPerformed `
    -or [bool]$evidence.privateEvidenceRead) {
    throw 'runner invocation preflight evidence is incomplete'
}

if ((Invoke-ExpectedFailure 'wrong_fixture' 'fixture_target') -eq 0) {
    throw 'runner accepted a mismatched canonical confirmation'
}
if ((Invoke-ExpectedFailure 'fixture_canonical' 'wrong_fixture') -eq 0) {
    throw 'runner accepted a mismatched target confirmation'
}

'isolated validation runner parameter offline smoke passed'
