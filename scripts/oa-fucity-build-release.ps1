param(
    [string]$ProjectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).ProviderPath,
    [string]$ReleaseBase = '',
    [string]$Domain = 'oa.fucity.cn',
    [string]$ReleaseId = (Get-Date -Format 'yyyyMMdd-HHmmss'),
    [switch]$SkipFrontendBuild,
    [switch]$SkipComposerInstall,
    [switch]$SkipReadiness,
    [switch]$NoZip
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Write-Step {
    param([string]$Message)
    Write-Host "[build-release] $Message"
}

function Invoke-CheckedCommand {
    param(
        [string]$FilePath,
        [string[]]$ArgumentList,
        [string]$WorkingDirectory
    )

    Write-Step ("run: {0} {1}" -f $FilePath, ($ArgumentList -join ' '))
    Push-Location -LiteralPath $WorkingDirectory
    try {
        & $FilePath @ArgumentList
        if ($LASTEXITCODE -ne 0) {
            throw "Command failed with exit code ${LASTEXITCODE}: $FilePath"
        }
    } finally {
        Pop-Location
    }
}

function Assert-ChildPath {
    param(
        [string]$Parent,
        [string]$Child
    )

    $parentPath = (Resolve-Path -LiteralPath $Parent).ProviderPath.TrimEnd('\', '/')
    $childPath = (Resolve-Path -LiteralPath $Child).ProviderPath
    if (-not $childPath.StartsWith($parentPath, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing to edit path outside release root: $childPath"
    }
}

function Reset-ReleaseSubdirectory {
    param(
        [string]$ReleaseRoot,
        [string]$RelativePath
    )

    $target = Join-Path $ReleaseRoot $RelativePath
    if (Test-Path -LiteralPath $target) {
        Assert-ChildPath -Parent $ReleaseRoot -Child $target
        Remove-Item -LiteralPath $target -Recurse -Force
    }
    New-Item -ItemType Directory -Force -Path $target | Out-Null
}

function Copy-DirectoryIfPresent {
    param(
        [string]$ProjectRoot,
        [string]$ReleaseRoot,
        [string]$RelativePath
    )

    $source = Join-Path $ProjectRoot $RelativePath
    if (Test-Path -LiteralPath $source -PathType Container) {
        Copy-Item -LiteralPath $source -Destination $ReleaseRoot -Recurse -Force
    }
}

function Copy-FileIfPresent {
    param(
        [string]$ProjectRoot,
        [string]$ReleaseRoot,
        [string]$RelativePath
    )

    $source = Join-Path $ProjectRoot $RelativePath
    if (Test-Path -LiteralPath $source -PathType Leaf) {
        Copy-Item -LiteralPath $source -Destination $ReleaseRoot -Force
    }
}

function New-ZipFromDirectory {
    param(
        [string]$SourceDirectory,
        [string]$DestinationPath
    )

    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $sourceRoot = (Resolve-Path -LiteralPath $SourceDirectory).ProviderPath.TrimEnd('\', '/')
    $archive = [System.IO.Compression.ZipFile]::Open(
        $DestinationPath,
        [System.IO.Compression.ZipArchiveMode]::Create
    )
    try {
        Get-ChildItem -LiteralPath $sourceRoot -Recurse -Directory -Force | ForEach-Object {
            $relativePath = $_.FullName.Substring($sourceRoot.Length).TrimStart('\', '/')
            $entryName = ($relativePath -replace '\\', '/') + '/'
            $entry = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
            $entry.LastWriteTime = $_.LastWriteTime
        }

        Get-ChildItem -LiteralPath $sourceRoot -Recurse -File -Force | ForEach-Object {
            $relativePath = $_.FullName.Substring($sourceRoot.Length).TrimStart('\', '/')
            $entryName = $relativePath -replace '\\', '/'
            $entry = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
            $entry.LastWriteTime = $_.LastWriteTime

            $entryStream = $entry.Open()
            try {
                $fileStream = [System.IO.File]::OpenRead($_.FullName)
                try {
                    $fileStream.CopyTo($entryStream)
                } finally {
                    $fileStream.Dispose()
                }
            } finally {
                $entryStream.Dispose()
            }
        }
    } finally {
        $archive.Dispose()
    }
}

$ProjectRoot = (Resolve-Path -LiteralPath $ProjectRoot).ProviderPath
if ([string]::IsNullOrWhiteSpace($ReleaseBase)) {
    $ReleaseBase = Join-Path (Split-Path -Parent $ProjectRoot) 'release'
}
$powerShellExe = Join-Path $PSHOME 'powershell.exe'
if (-not (Test-Path -LiteralPath $powerShellExe -PathType Leaf)) {
    $powerShellExe = 'powershell'
}

New-Item -ItemType Directory -Force -Path $ReleaseBase | Out-Null
$ReleaseRoot = Join-Path $ReleaseBase "$Domain-$ReleaseId"
if (Test-Path -LiteralPath $ReleaseRoot) {
    throw "Release root already exists: $ReleaseRoot"
}

if (-not $SkipFrontendBuild) {
    Invoke-CheckedCommand -FilePath 'npm' -ArgumentList @('run', 'build') -WorkingDirectory (Join-Path $ProjectRoot 'snowy-admin-web')
}

Write-Step "create release root: $ReleaseRoot"
New-Item -ItemType Directory -Force -Path $ReleaseRoot | Out-Null

foreach ($dir in @('app', 'config', 'extend', 'public', 'route', 'view')) {
    Copy-DirectoryIfPresent -ProjectRoot $ProjectRoot -ReleaseRoot $ReleaseRoot -RelativePath $dir
}

foreach ($file in @('composer.json', 'composer.lock', 'think', 'LICENSE.txt', 'README.md')) {
    Copy-FileIfPresent -ProjectRoot $ProjectRoot -ReleaseRoot $ReleaseRoot -RelativePath $file
}

Reset-ReleaseSubdirectory -ReleaseRoot $ReleaseRoot -RelativePath 'public\storage'
Reset-ReleaseSubdirectory -ReleaseRoot $ReleaseRoot -RelativePath 'public\upload'
$publicStorageGitignore = Join-Path $ProjectRoot 'public\storage\.gitignore'
if (Test-Path -LiteralPath $publicStorageGitignore -PathType Leaf) {
    Copy-Item -LiteralPath $publicStorageGitignore -Destination (Join-Path $ReleaseRoot 'public\storage') -Force
}

$frontendReleaseRoot = Join-Path $ReleaseRoot 'snowy-admin-web'
New-Item -ItemType Directory -Force -Path $frontendReleaseRoot | Out-Null
Copy-Item -LiteralPath (Join-Path $ProjectRoot 'snowy-admin-web\dist') -Destination $frontendReleaseRoot -Recurse -Force

$scriptReleaseRoot = Join-Path $ReleaseRoot 'scripts'
New-Item -ItemType Directory -Force -Path $scriptReleaseRoot | Out-Null
foreach ($script in @(
    'deployment-readiness.ps1',
    'deployment-readiness.sh',
    'migrate-legacy-files.php',
    'install-after-sales-module.php',
    'install-sale-project-travel-days.php',
    'oa-fucity-remote-deploy.sh'
)) {
    Copy-Item -LiteralPath (Join-Path $ProjectRoot "scripts\$script") -Destination $scriptReleaseRoot -Force
}

foreach ($dir in @(
    'runtime',
    'runtime\log',
    'runtime\cache',
    'runtime\temp',
    'runtime\storage',
    'runtime\upload',
    'runtime\backup',
    'public\storage',
    'public\upload'
)) {
    New-Item -ItemType Directory -Force -Path (Join-Path $ReleaseRoot $dir) | Out-Null
}

if (-not $SkipComposerInstall) {
    Invoke-CheckedCommand -FilePath 'composer' -ArgumentList @('install', '--no-dev', '--optimize-autoloader', '--no-interaction') -WorkingDirectory $ReleaseRoot
}

if (-not $SkipReadiness) {
    $readinessScript = Join-Path $ProjectRoot 'scripts\deployment-readiness.ps1'
    Write-Step "run release readiness policy"
    Invoke-CheckedCommand -FilePath $powerShellExe -ArgumentList @(
        '-NoProfile',
        '-ExecutionPolicy',
        'Bypass',
        '-File',
        $readinessScript,
        '-CheckReleasePackagePolicy',
        '-CheckFrontendBuildPolicy',
        '-ReleaseRoot',
        $ReleaseRoot
    ) -WorkingDirectory $ProjectRoot
}

$zipPath = ''
if (-not $NoZip) {
    $zipPath = "$ReleaseRoot.zip"
    if (Test-Path -LiteralPath $zipPath) {
        throw "Release zip already exists: $zipPath"
    }

    Write-Step "create zip: $zipPath"
    New-ZipFromDirectory -SourceDirectory $ReleaseRoot -DestinationPath $zipPath
}

[PSCustomObject]@{
    ReleaseRoot = $ReleaseRoot
    ZipPath = $zipPath
    Domain = $Domain
    ReleaseId = $ReleaseId
}
