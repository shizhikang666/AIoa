param(
    [Parameter(Mandatory = $true)]
    [string]$ServerHost,

    [string]$ServerUser = 'root',
    [int]$ServerPort = 22,
    [string]$SshKeyPath = '',
    [string]$ProjectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).ProviderPath,
    [string]$RemoteRoot = '/www/wwwroot/oa.fucity.cn',
    [string]$PublicBaseUrl = 'https://oa.fucity.cn',
    [string]$CorsProbeOrigin = 'https://oa.fucity.cn',
    [string]$ReleaseZip = '',
    [string]$SshPath = '',
    [string]$ScpPath = '',
    [string]$ReleaseId = (Get-Date -Format 'yyyyMMdd-HHmmss'),
    [string]$RemoteOwner = 'www:www',
    [switch]$ConfirmDeploy,
    [switch]$DryRun,
    [switch]$SkipFrontendBuild,
    [switch]$SkipComposerInstall,
    [switch]$SkipLocalReadiness,
    [switch]$SkipDbBackup,
    [switch]$SkipRemoteReadiness,
    [switch]$CheckCors,
    [switch]$CheckSecurityHeaders,
    [switch]$ConfigureNginx,
    [switch]$ReloadNginx
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Write-Step {
    param([string]$Message)
    Write-Host "[deploy] $Message"
}

function ConvertTo-BashSingleQuoted {
    param([string]$Value)
    $quote = [string][char]39
    $escapedQuote = $quote + '\' + $quote + $quote
    return $quote + $Value.Replace($quote, $escapedQuote) + $quote
}

function Invoke-CheckedNative {
    param(
        [string]$FilePath,
        [string[]]$ArgumentList
    )

    Write-Step ("run: {0} {1}" -f $FilePath, ($ArgumentList -join ' '))
    if ($DryRun) {
        return
    }

    & $FilePath @ArgumentList
    if ($LASTEXITCODE -ne 0) {
        throw "Command failed with exit code ${LASTEXITCODE}: $FilePath"
    }
}

function New-SshOptions {
    $options = @('-p', [string]$ServerPort)
    if (-not [string]::IsNullOrWhiteSpace($SshKeyPath)) {
        $options += @('-i', $SshKeyPath)
    }
    return $options
}

function New-ScpOptions {
    $options = @('-P', [string]$ServerPort)
    if (-not [string]::IsNullOrWhiteSpace($SshKeyPath)) {
        $options += @('-i', $SshKeyPath)
    }
    return $options
}

function Resolve-CommandPath {
    param(
        [string]$PreferredPath,
        [string[]]$CandidatePaths,
        [string]$CommandName
    )

    if (-not [string]::IsNullOrWhiteSpace($PreferredPath)) {
        return $PreferredPath
    }

    $command = Get-Command $CommandName -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($null -ne $command) {
        return $command.Source
    }

    foreach ($candidate in $CandidatePaths) {
        if (Test-Path -LiteralPath $candidate -PathType Leaf) {
            return $candidate
        }
    }

    return $CommandName
}

function Invoke-RemoteCommand {
    param([string]$Command)

    $sshTarget = "$ServerUser@$ServerHost"
    $args = @()
    $args += New-SshOptions
    $args += @($sshTarget, $Command)
    Invoke-CheckedNative -FilePath $SshPath -ArgumentList $args
}

if (-not $DryRun -and -not $ConfirmDeploy) {
    throw "Refusing to deploy without -ConfirmDeploy. Use -DryRun to print commands only."
}

$ProjectRoot = (Resolve-Path -LiteralPath $ProjectRoot).ProviderPath
$SshPath = Resolve-CommandPath -PreferredPath $SshPath -CandidatePaths @(
    "$env:WINDIR\System32\OpenSSH\ssh.exe",
    'C:\Program Files\Git\usr\bin\ssh.exe'
) -CommandName 'ssh'
$ScpPath = Resolve-CommandPath -PreferredPath $ScpPath -CandidatePaths @(
    "$env:WINDIR\System32\OpenSSH\scp.exe",
    'C:\Program Files\Git\usr\bin\scp.exe'
) -CommandName 'scp'
$powerShellExe = Join-Path $PSHOME 'powershell.exe'
if (-not (Test-Path -LiteralPath $powerShellExe -PathType Leaf)) {
    $powerShellExe = 'powershell'
}

if ([string]::IsNullOrWhiteSpace($ReleaseZip)) {
    $buildScript = Join-Path $ProjectRoot 'scripts\oa-fucity-build-release.ps1'
    $buildArgs = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $buildScript, '-ProjectRoot', $ProjectRoot, '-ReleaseId', $ReleaseId)
    if ($SkipFrontendBuild) { $buildArgs += '-SkipFrontendBuild' }
    if ($SkipComposerInstall) { $buildArgs += '-SkipComposerInstall' }
    if ($SkipLocalReadiness) { $buildArgs += '-SkipReadiness' }
    Invoke-CheckedNative -FilePath $powerShellExe -ArgumentList $buildArgs

    $releaseBase = Join-Path (Split-Path -Parent $ProjectRoot) 'release'
    $ReleaseZip = Join-Path $releaseBase "oa.fucity.cn-$ReleaseId.zip"
}

if (-not $DryRun) {
    $ReleaseZip = (Resolve-Path -LiteralPath $ReleaseZip).ProviderPath
}
$remoteWorkDir = "/tmp/oa-fucity-deploy-$ReleaseId"
$remoteArchive = "$remoteWorkDir/package.zip"
$remoteScript = "$remoteWorkDir/oa-fucity-remote-deploy.sh"
$sshTarget = "$ServerUser@$ServerHost"

Invoke-RemoteCommand ("mkdir -p " + (ConvertTo-BashSingleQuoted $remoteWorkDir))

$scpArgs = @()
$scpArgs += New-ScpOptions
$scpArgs += @($ReleaseZip, "${sshTarget}:$remoteArchive")
Invoke-CheckedNative -FilePath $ScpPath -ArgumentList $scpArgs

$remoteDeployScript = Join-Path $ProjectRoot 'scripts\oa-fucity-remote-deploy.sh'
$scpScriptArgs = @()
$scpScriptArgs += New-ScpOptions
$scpScriptArgs += @($remoteDeployScript, "${sshTarget}:$remoteScript")
Invoke-CheckedNative -FilePath $ScpPath -ArgumentList $scpScriptArgs

$remoteArgs = @(
    'bash',
    $remoteScript,
    '--archive', $remoteArchive,
    '--target', $RemoteRoot,
    '--release-id', $ReleaseId,
    '--public-base-url', $PublicBaseUrl,
    '--cors-probe-origin', $CorsProbeOrigin
)

if (-not [string]::IsNullOrWhiteSpace($RemoteOwner)) {
    $remoteArgs += @('--owner', $RemoteOwner)
}
if ($SkipDbBackup) { $remoteArgs += '--skip-db-backup' }
if ($SkipRemoteReadiness) { $remoteArgs += '--skip-readiness' }
if ($CheckCors) { $remoteArgs += '--check-cors' }
if ($CheckSecurityHeaders) { $remoteArgs += '--check-security-headers' }
if ($ConfigureNginx) { $remoteArgs += '--configure-nginx' }
if ($ReloadNginx) { $remoteArgs += '--reload-nginx' }

$remoteCommand = ($remoteArgs | ForEach-Object { ConvertTo-BashSingleQuoted $_ }) -join ' '
Invoke-RemoteCommand $remoteCommand

Write-Step "deployment command completed"
Write-Step "remote package kept at $remoteArchive"
