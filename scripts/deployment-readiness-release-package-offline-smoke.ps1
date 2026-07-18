$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$readinessScript = Join-Path $PSScriptRoot 'deployment-readiness.ps1'
$powerShellExe = Join-Path $PSHOME 'powershell.exe'
if (-not (Test-Path -LiteralPath $powerShellExe -PathType Leaf)) {
    $powerShellExe = 'powershell'
}

$tempRoot = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath()).TrimEnd('\', '/')
$smokeRoot = Join-Path $tempRoot ('oa-release-package-smoke-' + [guid]::NewGuid().ToString('N'))
$smokeRootFull = [System.IO.Path]::GetFullPath($smokeRoot)
$tempPrefix = $tempRoot + [System.IO.Path]::DirectorySeparatorChar
if (-not $smokeRootFull.StartsWith($tempPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'Unsafe release-package smoke path.'
}

function Write-FixtureFile {
    param(
        [string]$RelativePath,
        [string]$Content = 'fixture'
    )

    $path = Join-Path $smokeRoot ($RelativePath -replace '/', [System.IO.Path]::DirectorySeparatorChar)
    $parent = Split-Path -Parent $path
    New-Item -ItemType Directory -Force -Path $parent | Out-Null
    Set-Content -LiteralPath $path -Value $Content -Encoding UTF8
}

function New-ReleaseFixture {
    New-Item -ItemType Directory -Force -Path $smokeRoot | Out-Null
    foreach ($directory in @(
        'app', 'config', 'route', 'extend', 'vendor', 'public',
        'snowy-admin-web/dist/assets', 'snowy-admin-web/dist/.vite', 'runtime'
    )) {
        New-Item -ItemType Directory -Force -Path (Join-Path $smokeRoot ($directory -replace '/', '\')) | Out-Null
    }

    $files = @(
        'RELEASE-ID', 'RELEASE-COMMIT', 'RELEASE-TAGS', 'RELEASE-SOURCE-DIRTY',
        'RELEASE-DIAGNOSTIC', 'RELEASE-MANIFEST.json', 'think', 'composer.json',
        'composer.lock', 'vendor/autoload.php', 'vendor/composer/installed.php',
        'vendor/composer/installed.json', 'vendor/composer/platform_check.php',
        'public/index.php', 'public/router.php', 'public/.htaccess', 'config/app.php',
        'config/database.php', 'config/cache.php', 'config/log.php',
        'config/filesystem.php', 'snowy-admin-web/dist/index.html',
        'snowy-admin-web/dist/assets/app.js', 'snowy-admin-web/dist/.vite/manifest.json',
        'runtime/.gitignore'
    )
    foreach ($file in $files) {
        Write-FixtureFile -RelativePath $file
    }
}

function Get-FixtureSnapshot {
    return @(
        Get-ChildItem -LiteralPath $smokeRoot -Recurse -Force |
            ForEach-Object {
                if ($_.PSIsContainer) {
                    '{0}|DIR' -f $_.FullName
                } else {
                    $hash = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash
                    '{0}|FILE|{1}|{2}|{3}' -f $_.FullName, $_.Length, $_.LastWriteTimeUtc.Ticks, $hash
                }
            } |
            Sort-Object
    ) -join "`n"
}

function Invoke-PackagePolicy {
    param([string[]]$ExtraArguments = @())

    $arguments = @(
        '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $readinessScript,
        '-ReleasePackageBuild', '-CheckReleasePackagePolicy', '-ReleaseRoot', $smokeRoot
    ) + $ExtraArguments
    $savedErrorPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $output = @(& $powerShellExe @arguments 2>&1)
        $exitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $savedErrorPreference
    }
    return [pscustomobject]@{
        ExitCode = $exitCode
        Output = ($output -join "`n")
    }
}

function Assert-Pass {
    param([object]$Result, [string]$Name)
    if ($Result.ExitCode -ne 0) {
        throw "$Name should pass but exited $($Result.ExitCode): $($Result.Output)"
    }
}

function Assert-Fail {
    param([object]$Result, [string]$Name, [string]$Signal)
    if ($Result.ExitCode -eq 0 -or -not $Result.Output.Contains($Signal)) {
        throw "$Name should fail with '$Signal': $($Result.Output)"
    }
}

try {
    New-ReleaseFixture
    $before = Get-FixtureSnapshot
    $savedPath = $env:PATH
    try {
        $env:PATH = ''
        $valid = Invoke-PackagePolicy
    } finally {
        $env:PATH = $savedPath
    }
    Assert-Pass -Result $valid -Name 'valid host-independent artifact'
    $after = Get-FixtureSnapshot
    if ($before -cne $after) {
        $difference = Compare-Object -ReferenceObject @($before -split "`n") -DifferenceObject @($after -split "`n")
        throw "Artifact-only readiness changed fixture contents or timestamps: $($difference | Out-String)"
    }

    Write-FixtureFile -RelativePath '.env' -Content 'MUST_NOT_BE_PACKAGED=true'
    Assert-Fail -Result (Invoke-PackagePolicy) -Name 'packaged env' -Signal 'Release excluded entries'
    Remove-Item -LiteralPath (Join-Path $smokeRoot '.env') -Force

    $autoload = Join-Path $smokeRoot 'vendor\autoload.php'
    $autoloadHeld = "$autoload.held"
    Move-Item -LiteralPath $autoload -Destination $autoloadHeld
    Assert-Fail -Result (Invoke-PackagePolicy) -Name 'missing Composer autoload' -Signal 'Release Composer autoload'
    Move-Item -LiteralPath $autoloadHeld -Destination $autoload

    $devDependency = Join-Path $smokeRoot 'vendor\symfony\var-dumper'
    New-Item -ItemType Directory -Force -Path $devDependency | Out-Null
    Assert-Fail -Result (Invoke-PackagePolicy) -Name 'Composer dev dependency' -Signal 'Release excluded entries'
    Remove-Item -LiteralPath $devDependency -Force
    Remove-Item -LiteralPath (Split-Path -Parent $devDependency) -Force

    Write-FixtureFile -RelativePath 'snowy-admin-web/dist/.env.production' -Content 'SECRET=true'
    Assert-Fail -Result (Invoke-PackagePolicy) -Name 'frontend dist secret' -Signal 'Release excluded entries'
    Remove-Item -LiteralPath (Join-Path $smokeRoot 'snowy-admin-web\dist\.env.production') -Force

    Write-FixtureFile -RelativePath 'snowy-admin-web/dist/assets/app.js.map'
    Assert-Fail -Result (Invoke-PackagePolicy) -Name 'frontend sourcemap' -Signal 'Release excluded entries'
    Remove-Item -LiteralPath (Join-Path $smokeRoot 'snowy-admin-web\dist\assets\app.js.map') -Force

    $databaseConfig = Join-Path $smokeRoot 'config\database.php'
    $databaseConfigHeld = "$databaseConfig.held"
    Move-Item -LiteralPath $databaseConfig -Destination $databaseConfigHeld
    Assert-Fail -Result (Invoke-PackagePolicy) -Name 'missing database config' -Signal 'Release config database'
    Move-Item -LiteralPath $databaseConfigHeld -Destination $databaseConfig

    $assets = Join-Path $smokeRoot 'snowy-admin-web\dist\assets'
    $assetsHeld = "$assets.held"
    Move-Item -LiteralPath $assets -Destination $assetsHeld
    Assert-Fail -Result (Invoke-PackagePolicy) -Name 'missing frontend assets' -Signal 'Release frontend assets'
    Move-Item -LiteralPath $assetsHeld -Destination $assets

    $manifest = Join-Path $smokeRoot 'snowy-admin-web\dist\.vite\manifest.json'
    $manifestHeld = "$manifest.held"
    Move-Item -LiteralPath $manifest -Destination $manifestHeld
    Assert-Fail -Result (Invoke-PackagePolicy) -Name 'missing frontend manifest' -Signal 'Release frontend manifest'
    Move-Item -LiteralPath $manifestHeld -Destination $manifest

    $contractFailure = Invoke-PackagePolicy -ExtraArguments @('-PhpBinary', 'missing-php')
    Assert-Fail -Result $contractFailure -Name 'artifact-only parameter contract' -Signal 'accepts only'

    Write-Host '[release-package-offline-smoke] passed: fail-closed, host-independent, read-only artifact policy'
} finally {
    if (Test-Path -LiteralPath $smokeRootFull) {
        if (-not $smokeRootFull.StartsWith($tempPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
            throw 'Refusing unsafe release-package smoke cleanup.'
        }
        Remove-Item -LiteralPath $smokeRootFull -Recurse -Force
    }
}
