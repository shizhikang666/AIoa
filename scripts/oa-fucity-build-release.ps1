param(
    [string]$ProjectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).ProviderPath,
    [string]$ReleaseBase = '',
    [string]$Domain = 'oa.fucity.cn',
    [string]$ReleaseId = (Get-Date -Format 'yyyyMMdd-HHmmss'),
    [string]$NpmBinary = '',
    [string]$ComposerBinary = '',
    [string]$TarBinary = '',
    [switch]$SkipFrontendBuild,
    [switch]$SkipComposerInstall,
    [switch]$SkipReadiness,
    [switch]$NoZip,
    [switch]$NoTarGz,
    [switch]$AllowDirtySource,
    [switch]$AllowUntaggedSource,
    [switch]$ContractSelfTest
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$script:ReleaseIdPattern = '^[A-Za-z0-9](?:[A-Za-z0-9._-]{0,78}[A-Za-z0-9])?$'
$script:ReleaseTagPattern = '^(?:candidate|release)/oa-.+$'

function Test-ReleaseIdContract {
    param([string]$Value)
    return -not [string]::IsNullOrEmpty($Value) -and $Value -match $script:ReleaseIdPattern
}

function Test-ReleaseTagContract {
    param([string]$Value)
    return -not [string]::IsNullOrEmpty($Value) -and $Value -match $script:ReleaseTagPattern
}

function Test-DiagnosticBuildContract {
    param(
        [bool]$Dirty,
        [bool]$HasReleaseTag,
        [bool]$DirtyOverride,
        [bool]$UntaggedOverride,
        [bool]$FrontendSkipped,
        [bool]$ComposerSkipped,
        [bool]$ReadinessSkipped
    )
    return [bool](
        $DirtyOverride -or
        $UntaggedOverride -or
        $Dirty -or
        -not $HasReleaseTag -or
        $FrontendSkipped -or
        $ComposerSkipped -or
        $ReadinessSkipped
    )
}

if ($ContractSelfTest) {
    $validReleaseIds = @('a', 'release-20260719', ('a' + ('b' * 78) + 'c'))
    $invalidReleaseIds = @('', '-bad', 'bad-', ('a' * 81), 'bad/tag')
    foreach ($value in $validReleaseIds) {
        if (-not (Test-ReleaseIdContract -Value $value)) { throw "Contract rejected valid ReleaseId" }
    }
    foreach ($value in $invalidReleaseIds) {
        if (Test-ReleaseIdContract -Value $value) { throw "Contract accepted invalid ReleaseId" }
    }
    foreach ($value in @('candidate/oa-x', 'release/oa-x')) {
        if (-not (Test-ReleaseTagContract -Value $value)) { throw "Contract rejected valid release tag" }
    }
    foreach ($value in @('candidate/oa-', 'release/other', 'unrelated/tag')) {
        if (Test-ReleaseTagContract -Value $value) { throw "Contract accepted invalid release tag" }
    }
    $clean = Test-DiagnosticBuildContract -Dirty $false -HasReleaseTag $true -DirtyOverride $false -UntaggedOverride $false -FrontendSkipped $false -ComposerSkipped $false -ReadinessSkipped $false
    if ($clean) { throw 'Clean full build was incorrectly marked diagnostic' }
    foreach ($skip in @('frontend', 'composer', 'readiness')) {
        $diagnostic = Test-DiagnosticBuildContract `
            -Dirty $false -HasReleaseTag $true -DirtyOverride $false -UntaggedOverride $false `
            -FrontendSkipped ($skip -eq 'frontend') -ComposerSkipped ($skip -eq 'composer') -ReadinessSkipped ($skip -eq 'readiness')
        if (-not $diagnostic) { throw "Skipped $skip gate was not marked diagnostic" }
    }
    Write-Host '[build-release-contract-smoke] passed: ReleaseId, release tag, and diagnostic skip gates'
    exit 0
}

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

function Resolve-CommandBinary {
    param(
        [string]$PreferredPath,
        [string[]]$CommandNames
    )

    if (-not [string]::IsNullOrWhiteSpace($PreferredPath)) {
        if (Test-Path -LiteralPath $PreferredPath -PathType Leaf) {
            return (Resolve-Path -LiteralPath $PreferredPath).ProviderPath
        }

        $preferredCommand = Get-Command $PreferredPath -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($null -ne $preferredCommand) {
            return $preferredCommand.Source
        }

        throw "Command not found: $PreferredPath"
    }

    foreach ($commandName in $CommandNames) {
        $command = Get-Command $commandName -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($null -ne $command) {
            return $command.Source
        }
    }

    throw "None of the required commands were found: $($CommandNames -join ', ')"
}

function Get-GitOutput {
    param(
        [string[]]$ArgumentList,
        [string]$WorkingDirectory
    )

    Push-Location -LiteralPath $WorkingDirectory
    try {
        $output = & git @ArgumentList 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Git command failed: git $($ArgumentList -join ' ')"
        }

        return @($output)
    } finally {
        Pop-Location
    }
}

function Write-ReleaseManifest {
    param(
        [string]$ReleaseRoot,
        [string]$ReleaseId,
        [string]$GitCommit,
        [string[]]$GitTags,
        [string[]]$ReleaseTags,
        [bool]$SourceDirty,
        [bool]$Diagnostic
    )

    $rootPath = (Resolve-Path -LiteralPath $ReleaseRoot).ProviderPath.TrimEnd('\', '/')
    $files = @(
        Get-ChildItem -LiteralPath $rootPath -Recurse -File -Force |
            Where-Object { $_.Name -ne 'RELEASE-MANIFEST.json' } |
            ForEach-Object {
                [PSCustomObject]@{
                    path = ($_.FullName.Substring($rootPath.Length).TrimStart('\', '/') -replace '\\', '/')
                    bytes = $_.Length
                    sha256 = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
                }
            } |
            Sort-Object path
    )

    $manifest = [ordered]@{
        manifestVersion = 1
        releaseId = $ReleaseId
        gitCommit = $GitCommit
        gitTags = @($GitTags)
        releaseTags = @($ReleaseTags)
        sourceDirty = $SourceDirty
        diagnostic = $Diagnostic
        createdAtUtc = [DateTime]::UtcNow.ToString('yyyy-MM-ddTHH:mm:ssZ')
        fileCount = $files.Count
        files = $files
    }

    $manifestPath = Join-Path $ReleaseRoot 'RELEASE-MANIFEST.json'
    $manifest | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $manifestPath -Encoding UTF8
}

function Write-ArchiveHash {
    param([string]$ArchivePath)

    $hash = (Get-FileHash -LiteralPath $ArchivePath -Algorithm SHA256).Hash.ToLowerInvariant()
    $sidecarPath = "$ArchivePath.sha256"
    "$hash  $([System.IO.Path]::GetFileName($ArchivePath))" | Set-Content -LiteralPath $sidecarPath -Encoding ASCII
    return $sidecarPath
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
if ($Domain -notmatch '^[A-Za-z0-9][A-Za-z0-9.-]{0,252}[A-Za-z0-9]$' -or $Domain.Contains('..')) {
    throw "Domain contains unsupported release path characters"
}
if (-not (Test-ReleaseIdContract -Value $ReleaseId)) {
    throw "ReleaseId must match the deploy contract: 1-80 safe characters with alphanumeric first and last characters"
}
if ([string]::IsNullOrWhiteSpace($ReleaseBase)) {
    $ReleaseBase = Join-Path (Split-Path -Parent $ProjectRoot) 'release'
}
$powerShellExe = Join-Path $PSHOME 'powershell.exe'
if (-not (Test-Path -LiteralPath $powerShellExe -PathType Leaf)) {
    $powerShellExe = 'powershell'
}

$gitCommit = (Get-GitOutput -ArgumentList @('rev-parse', 'HEAD') -WorkingDirectory $ProjectRoot | Select-Object -First 1).ToString().Trim()
if ($gitCommit -notmatch '^[0-9a-fA-F]{40}$') {
    throw "Unable to resolve an exact Git commit for release packaging"
}

$dirtyEntries = @(Get-GitOutput -ArgumentList @('status', '--porcelain=v1') -WorkingDirectory $ProjectRoot)
$sourceDirty = $dirtyEntries.Count -gt 0
if ($sourceDirty -and -not $AllowDirtySource) {
    throw "Refusing to build a release from a dirty Git worktree. Commit the reviewed changes or pass -AllowDirtySource for a non-production diagnostic build."
}
if ($sourceDirty) {
    Write-Step "WARNING: diagnostic release source is dirty; the manifest will mark sourceDirty=true"
}

$gitTags = @(
    Get-GitOutput -ArgumentList @('tag', '--points-at', 'HEAD') -WorkingDirectory $ProjectRoot |
        ForEach-Object { $_.ToString().Trim() } |
        Where-Object { $_ -ne '' } |
        Sort-Object -Unique
)
$releaseTags = @($gitTags | Where-Object { Test-ReleaseTagContract -Value $_ })
if (-not $AllowUntaggedSource -and $releaseTags.Count -eq 0) {
    throw "Refusing to build without a reviewed candidate/oa-* or release/oa-* tag. Create the reviewed release tag or pass -AllowUntaggedSource for a diagnostic build."
}

$ignoredPackageSources = @(
    Get-GitOutput -ArgumentList @(
        'ls-files', '--others', '--ignored', '--exclude-standard', '--',
        'app', 'config', 'extend', 'public', 'route', 'view'
    ) -WorkingDirectory $ProjectRoot |
        ForEach-Object { $_.ToString().Trim() -replace '\\', '/' } |
        Where-Object {
            $_ -ne '' -and
            $_ -notmatch '^public/(?:upload|storage)(?:/|$)'
        }
)
if ($ignoredPackageSources.Count -gt 0) {
    throw "Refusing ignored files inside packaged source directories; clean or explicitly package them through a reviewed allowlist."
}

if (-not $SkipFrontendBuild) {
    $NpmBinary = Resolve-CommandBinary -PreferredPath $NpmBinary -CommandNames @('npm.cmd', 'npm')
}
if (-not $SkipComposerInstall) {
    $ComposerBinary = Resolve-CommandBinary -PreferredPath $ComposerBinary -CommandNames @('composer.bat', 'composer.cmd', 'composer')
}
if (-not $NoTarGz) {
    $TarBinary = Resolve-CommandBinary -PreferredPath $TarBinary -CommandNames @('tar.exe', 'tar')
}

New-Item -ItemType Directory -Force -Path $ReleaseBase | Out-Null
$ReleaseBase = (Resolve-Path -LiteralPath $ReleaseBase).ProviderPath.TrimEnd('\', '/')
$ReleaseRoot = Join-Path $ReleaseBase "$Domain-$ReleaseId"
$releaseRootFullPath = [System.IO.Path]::GetFullPath($ReleaseRoot)
$releaseBasePrefix = $ReleaseBase + [System.IO.Path]::DirectorySeparatorChar
if (-not $releaseRootFullPath.StartsWith($releaseBasePrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Refusing release path outside ReleaseBase"
}
if (Test-Path -LiteralPath $ReleaseRoot) {
    throw "Release root already exists: $ReleaseRoot"
}

if (-not $SkipFrontendBuild) {
    Invoke-CheckedCommand -FilePath $NpmBinary -ArgumentList @('run', 'build') -WorkingDirectory (Join-Path $ProjectRoot 'snowy-admin-web')
}
$postBuildDirtyEntries = @(Get-GitOutput -ArgumentList @('status', '--porcelain=v1') -WorkingDirectory $ProjectRoot)
if ($postBuildDirtyEntries.Count -gt 0 -and -not $AllowDirtySource) {
    throw "Frontend build changed the Git worktree; refusing to package uncommitted output."
}
$sourceDirty = $sourceDirty -or $postBuildDirtyEntries.Count -gt 0

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
    'legacy-sm4-smoke.php',
    'install-after-sales-module.php',
    'install-sale-project-delivery-plan.php',
    'install-sale-project-travel-days.php'
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
    Invoke-CheckedCommand -FilePath $ComposerBinary -ArgumentList @('install', '--no-dev', '--optimize-autoloader', '--no-interaction') -WorkingDirectory $ReleaseRoot
}

$finalGitCommit = (Get-GitOutput -ArgumentList @('rev-parse', 'HEAD') -WorkingDirectory $ProjectRoot | Select-Object -First 1).ToString().Trim()
if (-not $finalGitCommit.Equals($gitCommit, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Git HEAD changed while the release was being built"
}
$finalGitTags = @(
    Get-GitOutput -ArgumentList @('tag', '--points-at', 'HEAD') -WorkingDirectory $ProjectRoot |
        ForEach-Object { $_.ToString().Trim() } |
        Where-Object { $_ -ne '' } |
        Sort-Object -Unique
)
$tagDifference = @(Compare-Object -ReferenceObject @($gitTags) -DifferenceObject @($finalGitTags))
if ($tagDifference.Count -gt 0) {
    throw "Git tags changed while the release was being built"
}
$finalDirtyEntries = @(Get-GitOutput -ArgumentList @('status', '--porcelain=v1') -WorkingDirectory $ProjectRoot)
if ($finalDirtyEntries.Count -gt 0 -and -not $AllowDirtySource) {
    throw "Git worktree changed while the release was being built"
}
$sourceDirty = $sourceDirty -or $finalDirtyEntries.Count -gt 0
$diagnostic = Test-DiagnosticBuildContract `
    -Dirty $sourceDirty `
    -HasReleaseTag ($releaseTags.Count -gt 0) `
    -DirtyOverride ([bool]$AllowDirtySource) `
    -UntaggedOverride ([bool]$AllowUntaggedSource) `
    -FrontendSkipped ([bool]$SkipFrontendBuild) `
    -ComposerSkipped ([bool]$SkipComposerInstall) `
    -ReadinessSkipped ([bool]$SkipReadiness)

Set-Content -LiteralPath (Join-Path $ReleaseRoot 'RELEASE-ID') -Value $ReleaseId -Encoding ASCII
Set-Content -LiteralPath (Join-Path $ReleaseRoot 'RELEASE-COMMIT') -Value $gitCommit.ToLowerInvariant() -Encoding ASCII
Set-Content -LiteralPath (Join-Path $ReleaseRoot 'RELEASE-TAGS') -Value @($gitTags) -Encoding UTF8
Set-Content -LiteralPath (Join-Path $ReleaseRoot 'RELEASE-SOURCE-DIRTY') -Value ($sourceDirty.ToString().ToLowerInvariant()) -Encoding ASCII
Set-Content -LiteralPath (Join-Path $ReleaseRoot 'RELEASE-DIAGNOSTIC') -Value ($diagnostic.ToString().ToLowerInvariant()) -Encoding ASCII
Write-Step "write release manifest"
Write-ReleaseManifest -ReleaseRoot $ReleaseRoot -ReleaseId $ReleaseId -GitCommit $gitCommit.ToLowerInvariant() -GitTags $gitTags -ReleaseTags $releaseTags -SourceDirty $sourceDirty -Diagnostic $diagnostic

if (-not $SkipReadiness) {
    $readinessScript = Join-Path $ProjectRoot 'scripts\deployment-readiness.ps1'
    Write-Step "run release readiness policy"
    Invoke-CheckedCommand -FilePath $powerShellExe -ArgumentList @(
        '-NoProfile',
        '-ExecutionPolicy',
        'Bypass',
        '-File',
        $readinessScript,
        '-ReleasePackageBuild',
        '-CheckReleasePackagePolicy',
        '-ReleaseRoot',
        $ReleaseRoot
    ) -WorkingDirectory $ProjectRoot
}

$zipPath = ''
$zipHashPath = ''
if (-not $NoZip) {
    $zipPath = "$ReleaseRoot.zip"
    if (Test-Path -LiteralPath $zipPath) {
        throw "Release zip already exists: $zipPath"
    }

    Write-Step "create zip: $zipPath"
    New-ZipFromDirectory -SourceDirectory $ReleaseRoot -DestinationPath $zipPath
    $zipHashPath = Write-ArchiveHash -ArchivePath $zipPath
}

$tarGzPath = ''
$tarGzHashPath = ''
if (-not $NoTarGz) {
    $tarGzPath = "$ReleaseRoot.tar.gz"
    if (Test-Path -LiteralPath $tarGzPath) {
        throw "Release tar.gz already exists: $tarGzPath"
    }

    Write-Step "create tar.gz: $tarGzPath"
    Invoke-CheckedCommand -FilePath $TarBinary -ArgumentList @('-czf', $tarGzPath, '-C', $ReleaseRoot, '.') -WorkingDirectory $ReleaseBase
    $tarGzHashPath = Write-ArchiveHash -ArchivePath $tarGzPath
}

[PSCustomObject]@{
    ReleaseRoot = $ReleaseRoot
    ZipPath = $zipPath
    ZipSha256Path = $zipHashPath
    TarGzPath = $tarGzPath
    TarGzSha256Path = $tarGzHashPath
    Domain = $Domain
    ReleaseId = $ReleaseId
    GitCommit = $gitCommit.ToLowerInvariant()
    GitTags = @($gitTags)
    ReleaseTags = @($releaseTags)
    SourceDirty = $sourceDirty
    Diagnostic = $diagnostic
}
