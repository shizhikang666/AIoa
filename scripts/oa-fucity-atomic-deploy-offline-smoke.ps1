param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Write-Utf8NoBom {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Content
    )

    $parent = Split-Path -Parent $Path
    if (-not [string]::IsNullOrWhiteSpace($parent)) {
        New-Item -ItemType Directory -Force -Path $parent | Out-Null
    }
    $encoding = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, ($Content -replace "`r`n", "`n"), $encoding)
}

function Convert-ToBashPath {
    param([Parameter(Mandatory = $true)][string]$Path)

    $full = [System.IO.Path]::GetFullPath($Path)
    if ($full -notmatch '^([A-Za-z]):\\(.*)$') {
        throw "Unsupported smoke path: $full"
    }
    return '/' + $Matches[1].ToLowerInvariant() + '/' + ($Matches[2] -replace '\\', '/')
}

function Invoke-Bash {
    param(
        [Parameter(Mandatory = $true)][string]$Command,
        [switch]$ExpectFailure
    )

    $oldPreference = $ErrorActionPreference
    $oldMsys = $env:MSYS
    try {
        $env:MSYS = 'winsymlinks:nativestrict'
        $ErrorActionPreference = 'Continue'
        $output = & $script:BashPath -c $Command 2>&1
        $exitCode = $LASTEXITCODE
    } finally {
        $env:MSYS = $oldMsys
        $ErrorActionPreference = $oldPreference
    }
    if ($ExpectFailure) {
        if ($exitCode -eq 0) {
            throw "Expected Bash command to fail: $Command"
        }
    } elseif ($exitCode -ne 0) {
        throw "Bash command failed ($exitCode): $Command`n$($output -join "`n")"
    }
    return @($output)
}

function Invoke-Atomic {
    param(
        [Parameter(Mandatory = $true)][string[]]$Arguments,
        [switch]$ExpectFailure
    )

    $oldPath = $env:PATH
    $oldMsys = $env:MSYS
    $oldPreference = $ErrorActionPreference
    try {
        $env:PATH = "$script:StubDirectory;$oldPath"
        $env:MSYS = 'winsymlinks:nativestrict'
        $ErrorActionPreference = 'Continue'
        $output = & $script:BashPath -c 'export PATH="$1:$PATH"; shift; exec /usr/bin/bash "$@"' `
            'atomic-smoke' $script:StubDirectoryBash $script:AtomicScriptBash @Arguments 2>&1
        $exitCode = $LASTEXITCODE
    } finally {
        $env:PATH = $oldPath
        $env:MSYS = $oldMsys
        $ErrorActionPreference = $oldPreference
    }

    if ($ExpectFailure) {
        if ($exitCode -eq 0) {
            throw "Expected atomic deploy command to fail: $($Arguments -join ' ')"
        }
    } elseif ($exitCode -ne 0) {
        throw "Atomic deploy command failed ($exitCode): $($Arguments -join ' ')`n$($output -join "`n")"
    }
    return @($output)
}

function Assert-Bash {
    param(
        [Parameter(Mandatory = $true)][string]$Expression,
        [Parameter(Mandatory = $true)][string]$Message
    )

    $oldMsys = $env:MSYS
    try {
        $env:MSYS = 'winsymlinks:nativestrict'
        & $script:BashPath -c $Expression 2>$null
        $exitCode = $LASTEXITCODE
    } finally {
        $env:MSYS = $oldMsys
    }
    if ($exitCode -ne 0) {
        throw $Message
    }
}

function Assert-CurrentRelease {
    param(
        [Parameter(Mandatory = $true)][string]$Root,
        [Parameter(Mandatory = $true)][string]$ReleaseId,
        [Parameter(Mandatory = $true)][string]$Message
    )

    $oldPreference = $ErrorActionPreference
    $oldMsys = $env:MSYS
    try {
        $env:MSYS = 'winsymlinks:nativestrict'
        $ErrorActionPreference = 'Continue'
        $target = & $script:BashPath -c 'readlink "$1"' 'atomic-smoke' "$Root/current" 2>&1
        $exitCode = $LASTEXITCODE
    } finally {
        $env:MSYS = $oldMsys
        $ErrorActionPreference = $oldPreference
    }
    if ($exitCode -ne 0 -or (($target -join '').Trim()) -ne "releases/$ReleaseId") {
        throw $Message
    }
}

function Get-TextSha256 {
    param([Parameter(Mandatory = $true)][string]$Value)

    $algorithm = [System.Security.Cryptography.SHA256]::Create()
    try {
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($Value)
        return (($algorithm.ComputeHash($bytes) | ForEach-Object { $_.ToString('x2') }) -join '')
    } finally {
        $algorithm.Dispose()
    }
}

function New-ReleaseFixture {
    param([Parameter(Mandatory = $true)][string]$Root)

    foreach ($directory in @(
        'app',
        'config',
        'extend',
        'public',
        'public/upload',
        'public/storage',
        'route',
        'runtime',
        'runtime/log',
        'runtime/cache',
        'runtime/temp',
        'runtime/storage',
        'runtime/upload',
        'runtime/backup',
        'scripts',
        'snowy-admin-web/dist',
        'vendor',
        'view'
    )) {
        New-Item -ItemType Directory -Force -Path (Join-Path $Root $directory) | Out-Null
    }

    Write-Utf8NoBom (Join-Path $Root 'public/index.php') '<?php echo "ok";'
    Write-Utf8NoBom (Join-Path $Root 'vendor/autoload.php') '<?php return true;'
    Write-Utf8NoBom (Join-Path $Root 'snowy-admin-web/dist/index.html') '<!doctype html><title>OA</title>'
    Write-Utf8NoBom (Join-Path $Root 'composer.json') '{}'
    Write-Utf8NoBom (Join-Path $Root 'composer.lock') '{}'
    Write-Utf8NoBom (Join-Path $Root 'think') '#!/usr/bin/env php'
    Write-Utf8NoBom (Join-Path $Root 'public/storage/.gitignore') "*`n!.gitignore"
}

function Write-ReleaseProvenance {
    param(
        [Parameter(Mandatory = $true)][string]$Root,
        [Parameter(Mandatory = $true)][string]$ReleaseId,
        [string[]]$GitTags = @('candidate/oa-offline'),
        [bool]$SourceDirty = $false,
        [bool]$Diagnostic = $false
    )

    $commit = '0123456789abcdef0123456789abcdef01234567'
    $sourceDirtyText = $SourceDirty.ToString().ToLowerInvariant()
    $diagnosticText = $Diagnostic.ToString().ToLowerInvariant()
    Write-Utf8NoBom (Join-Path $Root 'RELEASE-ID') ($ReleaseId + "`n")
    Write-Utf8NoBom (Join-Path $Root 'RELEASE-COMMIT') ($commit + "`n")
    Write-Utf8NoBom (Join-Path $Root 'RELEASE-TAGS') (($GitTags -join "`n") + "`n")
    Write-Utf8NoBom (Join-Path $Root 'RELEASE-SOURCE-DIRTY') ($sourceDirtyText + "`n")
    Write-Utf8NoBom (Join-Path $Root 'RELEASE-DIAGNOSTIC') ($diagnosticText + "`n")

    $rootFull = [System.IO.Path]::GetFullPath($Root).TrimEnd('\', '/')
    $files = @(
        Get-ChildItem -LiteralPath $Root -Recurse -File |
            Where-Object { $_.Name -ne 'RELEASE-MANIFEST.json' } |
            ForEach-Object {
                $relative = $_.FullName.Substring($rootFull.Length).TrimStart('\', '/') -replace '\\', '/'
                [ordered]@{
                    path = $relative
                    bytes = [int64]$_.Length
                    sha256 = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
                }
            } |
            Sort-Object { $_.path }
    )
    $releaseTags = @($GitTags | Where-Object { $_ -match '^(candidate|release)/oa-' })
    $manifest = [ordered]@{
        manifestVersion = 1
        releaseId = $ReleaseId
        gitCommit = $commit
        gitTags = @($GitTags)
        releaseTags = $releaseTags
        sourceDirty = $SourceDirty
        diagnostic = $Diagnostic
        createdAtUtc = '2026-07-19T00:00:00Z'
        fileCount = $files.Count
        files = $files
    }
    Write-Utf8NoBom (Join-Path $Root 'RELEASE-MANIFEST.json') (($manifest | ConvertTo-Json -Depth 8) + "`n")
}

$projectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).ProviderPath
$atomicScript = Join-Path $PSScriptRoot 'oa-fucity-atomic-deploy.sh'
$buildScript = Join-Path $PSScriptRoot 'oa-fucity-build-release.ps1'
if (-not (Test-Path -LiteralPath $atomicScript -PathType Leaf)) {
    throw "Atomic deploy script missing: $atomicScript"
}

$bashCandidates = @(
    'C:\Program Files\Git\bin\bash.exe',
    'C:\Program Files\Git\usr\bin\bash.exe'
)
$script:BashPath = ''
foreach ($candidate in $bashCandidates) {
    if (Test-Path -LiteralPath $candidate -PathType Leaf) {
        $script:BashPath = $candidate
        break
    }
}
if ([string]::IsNullOrWhiteSpace($script:BashPath)) {
    $bashCommand = Get-Command bash -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($null -eq $bashCommand) {
        throw 'Git Bash is required for the atomic deployment offline smoke.'
    }
    $script:BashPath = $bashCommand.Source
}

$validatorPhpCandidates = @(
    'E:\project\socket\AI\testPhp\files\tools\php\php.exe',
    'C:\php\php.exe'
)
$validatorPhp = $null
foreach ($candidate in $validatorPhpCandidates) {
    if (Test-Path -LiteralPath $candidate -PathType Leaf) {
        $validatorPhp = $candidate
        break
    }
}
if ($null -eq $validatorPhp) {
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($null -eq $phpCommand) {
        throw 'A local PHP 8.1+ CLI is required to execute provenance validation in the offline smoke.'
    }
    $validatorPhp = $phpCommand.Source
}
$validatorPhpBash = Convert-ToBashPath $validatorPhp

$smokeRoot = Join-Path ([System.IO.Path]::GetTempPath()) ('oa-atomic-smoke-' + [guid]::NewGuid().ToString('N'))
$siteRoot = Join-Path $smokeRoot 'site'
$candidateSource = Join-Path $smokeRoot 'candidate-source'
$candidateTwoSource = Join-Path $smokeRoot 'candidate-two-source'
$toctouSource = Join-Path $smokeRoot 'toctou-source'
$dirtySource = Join-Path $smokeRoot 'dirty-source'
$diagnosticSource = Join-Path $smokeRoot 'diagnostic-source'
$untaggedSource = Join-Path $smokeRoot 'untagged-source'
$tamperedSource = Join-Path $smokeRoot 'tampered-source'
$candidateEnv = Join-Path $smokeRoot 'candidate.env'
$toctouEnv = Join-Path $smokeRoot 'toctou.env'
$archive = Join-Path $smokeRoot 'candidate.tar.gz'
$archiveTwo = Join-Path $smokeRoot 'candidate-two.tar.gz'
$toctouArchive = Join-Path $smokeRoot 'toctou.tar.gz'
$dirtyArchive = Join-Path $smokeRoot 'dirty.tar.gz'
$diagnosticArchive = Join-Path $smokeRoot 'diagnostic.tar.gz'
$untaggedArchive = Join-Path $smokeRoot 'untagged.tar.gz'
$tamperedArchive = Join-Path $smokeRoot 'tampered.tar.gz'
$badArchive = Join-Path $smokeRoot 'candidate-traversal.tar.gz'
$linkArchive = Join-Path $smokeRoot 'candidate-link.tar.gz'
$stubDirectory = Join-Path $smokeRoot 'stubs'
$stubState = Join-Path $smokeRoot 'stub-state'
$vhostFile = Join-Path $smokeRoot 'oa.fucity.cn.conf'
$rewriteFile = Join-Path $smokeRoot 'oa.fucity.cn.rewrite.conf'

$script:StubDirectory = $stubDirectory
$script:StubDirectoryBash = Convert-ToBashPath $stubDirectory
$script:AtomicScriptBash = Convert-ToBashPath $atomicScript
$siteRootBash = Convert-ToBashPath $siteRoot
$candidateSourceBash = Convert-ToBashPath $candidateSource
$candidateTwoSourceBash = Convert-ToBashPath $candidateTwoSource
$toctouSourceBash = Convert-ToBashPath $toctouSource
$dirtySourceBash = Convert-ToBashPath $dirtySource
$diagnosticSourceBash = Convert-ToBashPath $diagnosticSource
$untaggedSourceBash = Convert-ToBashPath $untaggedSource
$tamperedSourceBash = Convert-ToBashPath $tamperedSource
$candidateEnvBash = Convert-ToBashPath $candidateEnv
$toctouEnvBash = Convert-ToBashPath $toctouEnv
$archiveBash = Convert-ToBashPath $archive
$archiveTwoBash = Convert-ToBashPath $archiveTwo
$toctouArchiveBash = Convert-ToBashPath $toctouArchive
$dirtyArchiveBash = Convert-ToBashPath $dirtyArchive
$diagnosticArchiveBash = Convert-ToBashPath $diagnosticArchive
$untaggedArchiveBash = Convert-ToBashPath $untaggedArchive
$tamperedArchiveBash = Convert-ToBashPath $tamperedArchive
$badArchiveBash = Convert-ToBashPath $badArchive
$linkArchiveBash = Convert-ToBashPath $linkArchive
$stubDirectoryBash = Convert-ToBashPath $stubDirectory
$stubStateBash = Convert-ToBashPath $stubState
$vhostFileBash = Convert-ToBashPath $vhostFile
$rewriteFileBash = Convert-ToBashPath $rewriteFile

try {
    New-Item -ItemType Directory -Force -Path `
        $siteRoot, $candidateSource, $candidateTwoSource, $toctouSource, $dirtySource, $diagnosticSource, $untaggedSource, $tamperedSource, `
        $stubDirectory, $stubState | Out-Null
    New-ReleaseFixture -Root $siteRoot
    New-ReleaseFixture -Root $candidateSource
    New-ReleaseFixture -Root $candidateTwoSource
    New-ReleaseFixture -Root $toctouSource
    New-ReleaseFixture -Root $dirtySource
    New-ReleaseFixture -Root $diagnosticSource
    New-ReleaseFixture -Root $untaggedSource
    New-ReleaseFixture -Root $tamperedSource
    Write-ReleaseProvenance -Root $candidateSource -ReleaseId 'candidate-one'
    Write-ReleaseProvenance -Root $candidateTwoSource -ReleaseId 'candidate-two' -GitTags @('release/oa-offline')
    Write-ReleaseProvenance -Root $toctouSource -ReleaseId 'toctou-safe'
    Write-ReleaseProvenance -Root $dirtySource -ReleaseId 'dirty-source' -SourceDirty $true
    Write-ReleaseProvenance -Root $diagnosticSource -ReleaseId 'diagnostic-source' -Diagnostic $true
    Write-ReleaseProvenance -Root $untaggedSource -ReleaseId 'untagged-source' -GitTags @('unrelated/tag')
    Write-ReleaseProvenance -Root $tamperedSource -ReleaseId 'tampered-source'
    Write-Utf8NoBom (Join-Path $tamperedSource 'composer.json') '{"tampered":true}'

    Write-Utf8NoBom (Join-Path $siteRoot '.env') "APP_DEBUG = false`nDB_NAME = legacy_fixture`n"
    Write-Utf8NoBom (Join-Path $siteRoot '.user.ini') "open_basedir=$siteRootBash/:/tmp/`n"
    Write-Utf8NoBom $candidateEnv "APP_DEBUG = false`nDB_NAME = migrated_fixture`n"
    Write-Utf8NoBom $toctouEnv "APP_DEBUG = false`nDB_NAME = toctou_fixture`n"

    Write-Utf8NoBom $vhostFile @"
server {
    root $siteRootBash/snowy-admin-web/dist;
    include $rewriteFileBash;
}
"@
    Write-Utf8NoBom $rewriteFile @"
location ^~ /backend/ {
    fastcgi_param SCRIPT_FILENAME $siteRootBash/public/index.php;
    fastcgi_param DOCUMENT_ROOT $siteRootBash/public;
}
location ^~ /storage/ {
    alias $siteRootBash/public/storage/;
}
"@

    Write-Utf8NoBom (Join-Path $stubDirectory 'php83') @"
#!/usr/bin/env bash
if [ "`${1:-}" = "-r" ] && [[ "`${2:-}" == *PHP_MAJOR_VERSION* ]]; then
    printf '8.3'
    exit 0
fi
exec '$validatorPhpBash' "`$@"
"@
    Write-Utf8NoBom (Join-Path $stubDirectory 'nginx') @"
#!/usr/bin/env bash
printf 'nginx:%s\n' "`$*" >> '$stubStateBash/commands.log'
if [ -f '$stubStateBash/fail-nginx-once' ] && [ "`${1:-}" = '-t' ]; then
    rm -f '$stubStateBash/fail-nginx-once'
    exit 41
fi
exit 0
"@
    Write-Utf8NoBom (Join-Path $stubDirectory 'php-fpm-83') @"
#!/usr/bin/env bash
printf 'fpm:%s\n' "`$*" >> '$stubStateBash/commands.log'
if [ -f '$stubStateBash/fail-fpm-once' ]; then
    rm -f '$stubStateBash/fail-fpm-once'
    exit 42
fi
if [ -f '$stubStateBash/fail-fpm-always' ]; then
    exit 43
fi
exit 0
"@
    Write-Utf8NoBom (Join-Path $stubDirectory 'curl') @"
#!/usr/bin/env bash
printf 'curl:%s\n' "`$*" >> '$stubStateBash/commands.log'
exit 0
"@
    Write-Utf8NoBom (Join-Path $stubDirectory 'flock') @'
#!/usr/bin/env bash
exit 0
'@
    Write-Utf8NoBom (Join-Path $stubDirectory 'id') @'
#!/usr/bin/env bash
if [ "${1:-}" = "-u" ]; then
    printf '0\n'
    exit 0
fi
exec /usr/bin/id "$@"
'@
    Write-Utf8NoBom (Join-Path $stubDirectory 'chown') @"
#!/usr/bin/env bash
printf 'chown:%s\n' "`$*" >> '$stubStateBash/commands.log'
exit 0
"@
    $findmntStub = @'
#!/usr/bin/env bash
target=''
while [ "$#" -gt 0 ]; do
    case "$1" in
        -T)
            target="${2:-}"
            shift 2
            ;;
        *)
            shift
            ;;
    esac
done
if [ -n "$target" ]; then
    if [ -f '__STUB_STATE__/ancestor-mount-prefix' ]; then
        prefix="$(cat '__STUB_STATE__/ancestor-mount-prefix')"
        case "$target" in
            "$prefix"|"$prefix"/*)
                printf '%s\n' "$prefix"
                exit 0
                ;;
        esac
    fi
    printf '/\n'
fi
exit 0
'@
    Write-Utf8NoBom (Join-Path $stubDirectory 'findmnt') ($findmntStub.Replace('__STUB_STATE__', $stubStateBash))
    Write-Utf8NoBom (Join-Path $stubDirectory 'chmod') @"
#!/usr/bin/env bash
printf 'chmod:%s\n' "`$*" >> '$stubStateBash/commands.log'
exec /usr/bin/chmod "`$@"
"@
    Write-Utf8NoBom (Join-Path $stubDirectory 'stat') @"
#!/usr/bin/env bash
format=''
for argument in "`$@"; do
    case "`$argument" in
        %*) format="`$argument" ;;
    esac
done
path="`${@: -1}"
real_path="`$path"
case "`$path" in
    /dev/fd/*)
        parent_descriptor="`${path##*/}"
        if [ -e "/proc/`$PPID/fd/`$parent_descriptor" ]; then
            real_path="/proc/`$PPID/fd/`$parent_descriptor"
        fi
        ;;
esac
run_real_stat() {
    exec /usr/bin/stat "`$1" "`$2" "`$3" "`$real_path"
}
case "`$format" in
    '%u'|'%g') printf '0\n'; exit 0 ;;
    '%U:%G')
        case "`$path" in
            '$siteRootBash') printf 'root:www\n' ;;
            '$siteRootBash'/app|'$siteRootBash'/app/*|'$siteRootBash'/config|'$siteRootBash'/config/*|\
            '$siteRootBash'/extend|'$siteRootBash'/extend/*|'$siteRootBash'/route|'$siteRootBash'/route/*|\
            '$siteRootBash'/scripts|'$siteRootBash'/scripts/*|'$siteRootBash'/snowy-admin-web|'$siteRootBash'/snowy-admin-web/*|\
            '$siteRootBash'/vendor|'$siteRootBash'/vendor/*|'$siteRootBash'/view|'$siteRootBash'/view/*|\
            '$siteRootBash'/composer.json|'$siteRootBash'/composer.lock|'$siteRootBash'/think) printf 'www:www\n' ;;
            '$siteRootBash'/releases/*/runtime/cache|'$siteRootBash'/releases/*/runtime/temp|\
            '$siteRootBash'/runtime/log|'$siteRootBash'/runtime/session|'$siteRootBash'/runtime/storage|'$siteRootBash'/runtime/upload|'$siteRootBash'/runtime/backup|\
            '$siteRootBash'/public/upload|'$siteRootBash'/public/storage) printf 'www:www\n' ;;
            '$siteRootBash'/shared|'$siteRootBash'/shared/env|'$siteRootBash'/shared/env/*.env|'$siteRootBash'/runtime|'$siteRootBash'/releases/*/runtime) printf 'root:www\n' ;;
            *) printf 'root:root\n' ;;
        esac
        exit 0
        ;;
    '%a')
        case "`$path" in
            '$siteRootBash') printf '750\n' ;;
            '$siteRootBash'/app|'$siteRootBash'/app/*|'$siteRootBash'/config|'$siteRootBash'/config/*|\
            '$siteRootBash'/extend|'$siteRootBash'/extend/*|'$siteRootBash'/route|'$siteRootBash'/route/*|\
            '$siteRootBash'/scripts|'$siteRootBash'/scripts/*|'$siteRootBash'/snowy-admin-web|'$siteRootBash'/snowy-admin-web/*|\
            '$siteRootBash'/vendor|'$siteRootBash'/vendor/*|'$siteRootBash'/view|'$siteRootBash'/view/*|\
            '$siteRootBash'/composer.json|'$siteRootBash'/composer.lock|'$siteRootBash'/think)
                if [ -d "`$path" ]; then printf '775\n'; else printf '664\n'; fi
                ;;
            '$siteRootBash'/.deploy/staging) printf '700\n' ;;
            '$siteRootBash'/.deploy/staging/*) printf '600\n' ;;
            '$siteRootBash'/shared|'$siteRootBash'/shared/env) printf '750\n' ;;
            '$siteRootBash'/shared/env/*.env) printf '640\n' ;;
            '$siteRootBash'/runtime|'$siteRootBash'/releases/*/runtime) printf '750\n' ;;
            '$siteRootBash'/releases/*/runtime/cache|'$siteRootBash'/releases/*/runtime/temp) printf '750\n' ;;
            '$siteRootBash'/releases/*/.release-*|'$siteRootBash'/releases/*/.baseline-content-manifest.json) printf '600\n' ;;
            '$siteRootBash'/.deploy/manifests/*|'$siteRootBash'/.deploy/provenance/*) printf '600\n' ;;
            *) run_real_stat "`$@" ;;
        esac
        exit 0
        ;;
esac
run_real_stat "`$@"
"@
    Write-Utf8NoBom (Join-Path $stubDirectory 'dd') @"
#!/usr/bin/env bash
/usr/bin/dd "`$@"
status=`$?
[ "`$status" -eq 0 ] || exit "`$status"
destination=`$(/usr/bin/readlink -f /proc/`$$/fd/1 2>/dev/null || /usr/bin/readlink -f /dev/fd/1 2>/dev/null || true)
case "`$destination" in
    *'/archive-'*)
        if [ -f '$stubStateBash/mutate-archive-after-copy' ]; then
            source_path=`$(cat '$stubStateBash/mutate-archive-after-copy')
            printf 'MUTATED-AFTER-STAGING\n' >> "`$source_path"
            rm -f '$stubStateBash/mutate-archive-after-copy'
        fi
        ;;
    *'/env-'*)
        if [ -f '$stubStateBash/mutate-env-after-copy' ]; then
            source_path=`$(cat '$stubStateBash/mutate-env-after-copy')
            printf 'APP_DEBUG = false\nDB_NAME = attacker_fixture\n' > "`$source_path"
            rm -f '$stubStateBash/mutate-env-after-copy'
        fi
        ;;
esac
exit 0
"@
    Write-Utf8NoBom (Join-Path $stubDirectory 'readlink') @"
#!/usr/bin/env bash
if [ -f '$stubStateBash/external-current-once' ] && compgen -G '$siteRootBash/.current-*' >/dev/null; then
    IFS='|' read -r site replacement < '$stubStateBash/external-current-once'
    rm -f "`$site/current"
    ln -s "releases/`$replacement" "`$site/current"
    rm -f '$stubStateBash/external-current-once'
fi
exec /usr/bin/readlink "`$@"
"@
    Write-Utf8NoBom (Join-Path $stubDirectory 'mv') @"
#!/usr/bin/env bash
destination="`${@: -1}"
if [ -f '$stubStateBash/fail-audit-commit-once' ]; then
    case "`$destination" in
        '$siteRootBash'/.deploy/manifests/*-activate-*.txt)
            rm -f '$stubStateBash/fail-audit-commit-once'
            exit 88
            ;;
    esac
fi
exec /usr/bin/mv "`$@"
"@
    Write-Utf8NoBom (Join-Path $stubDirectory 'rsync') @'
#!/usr/bin/env bash
set -eu
arguments=("$@")
count=${#arguments[@]}
source_path=${arguments[$((count - 2))]}
destination=${arguments[$((count - 1))]}
for name in app config extend public route scripts snowy-admin-web vendor view composer.json composer.lock think LICENSE.txt README.md; do
    if [ -e "$source_path/$name" ]; then
        cp -a "$source_path/$name" "$destination/"
    fi
done
rm -rf "$destination/runtime" "$destination/public/upload" "$destination/public/storage"
'@

    Invoke-Bash -Command "chmod +x '$stubDirectoryBash/php83' '$stubDirectoryBash/nginx' '$stubDirectoryBash/php-fpm-83' '$stubDirectoryBash/curl' '$stubDirectoryBash/flock' '$stubDirectoryBash/id' '$stubDirectoryBash/chown' '$stubDirectoryBash/chmod' '$stubDirectoryBash/findmnt' '$stubDirectoryBash/stat' '$stubDirectoryBash/dd' '$stubDirectoryBash/readlink' '$stubDirectoryBash/mv' '$stubDirectoryBash/rsync'" | Out-Null
    Invoke-Bash -Command "tar -czf '$archiveBash' -C '$candidateSourceBash' ." | Out-Null
    Invoke-Bash -Command "tar -czf '$archiveTwoBash' -C '$candidateTwoSourceBash' ." | Out-Null
    Invoke-Bash -Command "tar -czf '$toctouArchiveBash' -C '$toctouSourceBash' ." | Out-Null
    Invoke-Bash -Command "tar -czf '$dirtyArchiveBash' -C '$dirtySourceBash' ." | Out-Null
    Invoke-Bash -Command "tar -czf '$diagnosticArchiveBash' -C '$diagnosticSourceBash' ." | Out-Null
    Invoke-Bash -Command "tar -czf '$untaggedArchiveBash' -C '$untaggedSourceBash' ." | Out-Null
    Invoke-Bash -Command "tar -czf '$tamperedArchiveBash' -C '$tamperedSourceBash' ." | Out-Null
    $archiveSha = (Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash.ToLowerInvariant()
    $archiveTwoSha = (Get-FileHash -LiteralPath $archiveTwo -Algorithm SHA256).Hash.ToLowerInvariant()
    $toctouArchiveSha = (Get-FileHash -LiteralPath $toctouArchive -Algorithm SHA256).Hash.ToLowerInvariant()
    $toctouEnvSha = (Get-FileHash -LiteralPath $toctouEnv -Algorithm SHA256).Hash.ToLowerInvariant()
    $baselineEnvSha = (Get-FileHash -LiteralPath (Join-Path $siteRoot '.env') -Algorithm SHA256).Hash.ToLowerInvariant()
    $candidateEnvSha = (Get-FileHash -LiteralPath $candidateEnv -Algorithm SHA256).Hash.ToLowerInvariant()
    $baselineDbNameSha = Get-TextSha256 -Value 'legacy_fixture'
    $candidateDbNameSha = Get-TextSha256 -Value 'migrated_fixture'
    $toctouDbNameSha = Get-TextSha256 -Value 'toctou_fixture'
    $dirtyArchiveSha = (Get-FileHash -LiteralPath $dirtyArchive -Algorithm SHA256).Hash.ToLowerInvariant()
    $diagnosticArchiveSha = (Get-FileHash -LiteralPath $diagnosticArchive -Algorithm SHA256).Hash.ToLowerInvariant()
    $untaggedArchiveSha = (Get-FileHash -LiteralPath $untaggedArchive -Algorithm SHA256).Hash.ToLowerInvariant()
    $tamperedArchiveSha = (Get-FileHash -LiteralPath $tamperedArchive -Algorithm SHA256).Hash.ToLowerInvariant()

    $common = @(
        '--root', $siteRootBash,
        '--php-bin', "$stubDirectoryBash/php83",
        '--php-fpm-control', "$stubDirectoryBash/php-fpm-83",
        '--nginx-bin', "$stubDirectoryBash/nginx",
        '--curl-bin', "$stubDirectoryBash/curl",
        '--vhost-file', $vhostFileBash,
        '--rewrite-file', $rewriteFileBash
    )
    $baselineBinding = @('--expected-env-sha256', $baselineEnvSha, '--expected-db-name-sha256', $baselineDbNameSha)
    $candidateBinding = @('--expected-env-sha256', $candidateEnvSha, '--expected-db-name-sha256', $candidateDbNameSha)
    $toctouBinding = @('--expected-env-sha256', $toctouEnvSha, '--expected-db-name-sha256', $toctouDbNameSha)

    Invoke-Atomic -Arguments (@('--release-id', 'owner-bypass', '--no-owner') + $common) -ExpectFailure | Out-Null
    Invoke-Atomic -Arguments (@('--release-id', 'owner-override', '--env-owner', 'nobody:nobody') + $common) -ExpectFailure | Out-Null
    Invoke-Atomic -Arguments (@('--release-id', 'runtime-owner-override', '--runtime-owner', 'nobody:nobody') + $common) -ExpectFailure | Out-Null

    Assert-Bash -Expression "'$stubDirectoryBash/stat' -c '%U:%G' -- '$siteRootBash/app' | grep -Fqx 'www:www'" -Message 'Baseline source fixture did not simulate www ownership.'
    Assert-Bash -Expression "'$stubDirectoryBash/stat' -c '%a' -- '$siteRootBash/app' | grep -Fqx '775'" -Message 'Baseline source fixture did not simulate a group-writable directory.'

    Invoke-Atomic -Arguments (@(
        '--initialize-baseline',
        '--release-id', 'baseline-offline',
        '--expected-current', 'absent',
        '--confirm-initialize-baseline',
        '--health-url', 'http://127.0.0.1/baseline'
    ) + $baselineBinding + $common) | Out-Null

    Assert-CurrentRelease -Root $siteRootBash -ReleaseId 'baseline-offline' -Message 'Baseline current link was not initialized.'
    Assert-Bash -Expression "grep -Fqx '    root $siteRootBash/current/snowy-admin-web/dist;' '$vhostFileBash'" -Message 'Frontend Nginx path was not changed exactly to current.'
    Assert-Bash -Expression "grep -Fq 'fastcgi_param SCRIPT_FILENAME $siteRootBash/current/public/index.php;' '$rewriteFileBash'" -Message 'Backend Nginx entry was not changed to current.'
    Assert-Bash -Expression "grep -Fq 'fastcgi_param DOCUMENT_ROOT $siteRootBash/current/public;' '$rewriteFileBash'" -Message 'Backend document root was not changed to current.'
    Assert-Bash -Expression "grep -Fq 'alias $siteRootBash/public/storage/;' '$rewriteFileBash'" -Message 'Stable storage alias was changed.'
    Assert-Bash -Expression "'$stubDirectoryBash/stat' -c '%U:%G' -- '$siteRootBash/releases/baseline-offline/app' | grep -Fqx 'root:root'" -Message 'Baseline code was not normalized to root:root.'
    Assert-Bash -Expression "'$stubDirectoryBash/stat' -c '%a' -- '$siteRootBash/releases/baseline-offline/app' | grep -Eq '^[0-7]*[0145][0145]$'" -Message 'Baseline code directory remains group/world writable.'
    Assert-Bash -Expression "'$stubDirectoryBash/stat' -c '%a' -- '$siteRootBash/releases/baseline-offline/composer.json' | grep -Eq '^[0-7]*[0145][0145]$'" -Message 'Baseline code file remains group/world writable.'
    Assert-Bash -Expression "'$stubDirectoryBash/stat' -c '%U:%G' -- '$siteRootBash/releases/baseline-offline/.baseline-content-manifest.json' | grep -Fqx 'root:root' && '$stubDirectoryBash/stat' -c '%a' -- '$siteRootBash/releases/baseline-offline/.baseline-content-manifest.json' | grep -Fqx '600'" -Message 'Baseline manifest protection is incorrect.'
    Assert-Bash -Expression "'$stubDirectoryBash/stat' -c '%U:%G' -- '$siteRootBash' | grep -Fqx 'root:www' && '$stubDirectoryBash/stat' -c '%a' -- '$siteRootBash' | grep -Fqx '750'" -Message 'Current parent is not protected from service-user writes.'
    Assert-Bash -Expression "grep -Eq '^chown:-R root:root .*/releases/\.prepare-baseline-offline-' '$stubStateBash/commands.log'" -Message 'Baseline recursive ownership normalization was not executed.'

    Write-Utf8NoBom (Join-Path $stubState 'mutate-archive-after-copy') ($toctouArchiveBash + "`n")
    Write-Utf8NoBom (Join-Path $stubState 'mutate-env-after-copy') ($toctouEnvBash + "`n")
    Invoke-Atomic -Arguments (@(
        '--release-id', 'toctou-safe',
        '--archive', $toctouArchiveBash,
        '--archive-sha256', $toctouArchiveSha,
        '--env-source', $toctouEnvBash
    ) + $toctouBinding + $common) | Out-Null
    if ((Get-FileHash -LiteralPath $toctouArchive -Algorithm SHA256).Hash.ToLowerInvariant() -eq $toctouArchiveSha) {
        throw 'TOCTOU archive source was not mutated by the smoke hook.'
    }
    if ((Get-FileHash -LiteralPath (Join-Path $siteRoot 'shared\env\toctou-safe.env') -Algorithm SHA256).Hash.ToLowerInvariant() -ne $toctouEnvSha) {
        throw 'Prepared env did not retain the protected staged snapshot.'
    }
    Assert-Bash -Expression "! grep -Fq 'attacker_fixture' '$siteRootBash/shared/env/toctou-safe.env'" -Message 'Untrusted env mutation reached the versioned env.'
    Assert-Bash -Expression "! find '$siteRootBash/.deploy/staging' -mindepth 1 -type f -print -quit | grep -q ." -Message 'Protected staging retained an input after prepare completed.'
    Assert-CurrentRelease -Root $siteRootBash -ReleaseId 'baseline-offline' -Message 'TOCTOU prepare unexpectedly changed current.'

    $missingBindingOutput = Invoke-Atomic -Arguments (@(
        '--release-id', 'candidate-one',
        '--archive', $archiveBash,
        '--archive-sha256', $archiveSha,
        '--env-source', $candidateEnvBash
    ) + $common) -ExpectFailure
    if (($missingBindingOutput -join "`n") -notmatch 'expected-env-sha256 is required') {
        throw 'Candidate prepare without external binding approval did not fail closed.'
    }
    Invoke-Atomic -Arguments (@(
        '--release-id', 'candidate-one',
        '--archive', $archiveBash,
        '--archive-sha256', $archiveSha,
        '--env-source', $candidateEnvBash,
        '--expected-env-sha256', ('0' * 64),
        '--expected-db-name-sha256', $candidateDbNameSha
    ) + $common) -ExpectFailure | Out-Null
    Invoke-Atomic -Arguments (@(
        '--release-id', 'candidate-one',
        '--archive', $archiveBash,
        '--archive-sha256', $archiveSha,
        '--env-source', $candidateEnvBash,
        '--expected-env-sha256', $candidateEnvSha,
        '--expected-db-name-sha256', ('0' * 64)
    ) + $common) -ExpectFailure | Out-Null
    Assert-Bash -Expression "test ! -e '$siteRootBash/releases/candidate-one' && test ! -e '$siteRootBash/shared/env/candidate-one.env'" -Message 'Rejected external binding approval left a candidate release or env.'

    Invoke-Atomic -Arguments (@(
        '--release-id', 'candidate-one',
        '--archive', $archiveBash,
        '--archive-sha256', $archiveSha,
        '--env-source', $candidateEnvBash
    ) + $candidateBinding + $common) | Out-Null

    Assert-CurrentRelease -Root $siteRootBash -ReleaseId 'baseline-offline' -Message 'Prepare-only unexpectedly changed current.'
    Assert-Bash -Expression "test '$siteRootBash/releases/candidate-one/.env' -ef '$siteRootBash/shared/env/candidate-one.env'" -Message 'Candidate environment is not version-pinned.'
    Assert-Bash -Expression "test '$siteRootBash/releases/candidate-one/public/upload' -ef '$siteRootBash/public/upload'" -Message 'Candidate upload path is not shared.'
    Assert-Bash -Expression "test `$(tr -d '\r\n' < '$siteRootBash/releases/candidate-one/.release-source') = '$archiveSha'" -Message 'Candidate package hash was not pinned.'
    Assert-Bash -Expression "test `$(tr -d '\r\n' < '$siteRootBash/releases/candidate-one/.release-env-sha256') = '$candidateEnvSha' && test `$(tr -d '\r\n' < '$siteRootBash/releases/candidate-one/.release-db-name-sha256') = '$candidateDbNameSha'" -Message 'Externally approved environment binding was not pinned in release markers.'
    Assert-Bash -Expression "grep -Fqx 'env_sha256=$candidateEnvSha' '$siteRootBash/.deploy/provenance/candidate-one.txt' && grep -Fqx 'expected_db_name_sha256=$candidateDbNameSha' '$siteRootBash/.deploy/provenance/candidate-one.txt'" -Message 'Externally approved environment binding was not pinned in provenance.'

    # Simulate a root-run candidate CLI probe that leaves an unwritable cache
    # shard behind. Activation must remove all release-local cache/temp entries
    # before current is changed.
    Invoke-Bash -Command "mkdir -p '$siteRootBash/releases/candidate-one/runtime/cache/f5' '$siteRootBash/releases/candidate-one/runtime/temp/root-probe' && printf 'root-owned-cache\n' > '$siteRootBash/releases/candidate-one/runtime/cache/f5/probe.php' && printf 'root-owned-temp\n' > '$siteRootBash/releases/candidate-one/runtime/temp/root-probe/probe.tmp'" | Out-Null
    Invoke-Bash -Command "printf 'current-release-sentinel\n' > '$siteRootBash/releases/baseline-offline/runtime/cache/current-sentinel'" | Out-Null

    Invoke-Atomic -Arguments (@(
        '--release-id', 'release-id-mismatch',
        '--archive', $archiveBash,
        '--archive-sha256', $archiveSha,
        '--env-source', $candidateEnvBash
    ) + $candidateBinding + $common) -ExpectFailure | Out-Null
    Invoke-Atomic -Arguments (@(
        '--release-id', 'package-hash-mismatch',
        '--archive', $archiveBash,
        '--archive-sha256', ('0' * 64),
        '--env-source', $candidateEnvBash
    ) + $candidateBinding + $common) -ExpectFailure | Out-Null
    Invoke-Atomic -Arguments (@(
        '--release-id', 'dirty-source',
        '--archive', $dirtyArchiveBash,
        '--archive-sha256', $dirtyArchiveSha,
        '--env-source', $candidateEnvBash
    ) + $candidateBinding + $common) -ExpectFailure | Out-Null
    Invoke-Atomic -Arguments (@(
        '--release-id', 'diagnostic-source',
        '--archive', $diagnosticArchiveBash,
        '--archive-sha256', $diagnosticArchiveSha,
        '--env-source', $candidateEnvBash
    ) + $candidateBinding + $common) -ExpectFailure | Out-Null
    Invoke-Atomic -Arguments (@(
        '--release-id', 'untagged-source',
        '--archive', $untaggedArchiveBash,
        '--archive-sha256', $untaggedArchiveSha,
        '--env-source', $candidateEnvBash
    ) + $candidateBinding + $common) -ExpectFailure | Out-Null
    Invoke-Atomic -Arguments (@(
        '--release-id', 'tampered-source',
        '--archive', $tamperedArchiveBash,
        '--archive-sha256', $tamperedArchiveSha,
        '--env-source', $candidateEnvBash
    ) + $candidateBinding + $common) -ExpectFailure | Out-Null
    foreach ($rejectedId in @('release-id-mismatch', 'package-hash-mismatch', 'dirty-source', 'diagnostic-source', 'untagged-source', 'tampered-source')) {
        Assert-Bash -Expression "test ! -e '$siteRootBash/releases/$rejectedId'" -Message "Rejected provenance created release $rejectedId."
    }

    $candidateSourceMarker = Join-Path $siteRoot 'releases\candidate-one\.release-source'
    Write-Utf8NoBom $candidateSourceMarker (('0' * 64) + "`n")
    Invoke-Atomic -Arguments (@(
        '--activate',
        '--release-id', 'candidate-one',
        '--expected-current', 'baseline-offline',
        '--confirm-activate',
        '--health-url', 'http://127.0.0.1/candidate-one'
    ) + $candidateBinding + $common) -ExpectFailure | Out-Null
    Assert-CurrentRelease -Root $siteRootBash -ReleaseId 'baseline-offline' -Message 'Tampered package hash marker changed current.'
    Write-Utf8NoBom $candidateSourceMarker ($archiveSha + "`n")

    $missingActivationBinding = Invoke-Atomic -Arguments (@(
        '--activate',
        '--release-id', 'candidate-one',
        '--expected-current', 'baseline-offline',
        '--confirm-activate',
        '--health-url', 'http://127.0.0.1/candidate-one'
    ) + $common) -ExpectFailure
    if (($missingActivationBinding -join "`n") -notmatch 'expected-env-sha256 is required') {
        throw 'Activation without external binding approval did not fail closed.'
    }
    Invoke-Atomic -Arguments (@(
        '--activate',
        '--release-id', 'candidate-one',
        '--expected-current', 'baseline-offline',
        '--expected-env-sha256', ('0' * 64),
        '--expected-db-name-sha256', $candidateDbNameSha,
        '--confirm-activate',
        '--health-url', 'http://127.0.0.1/candidate-one'
    ) + $common) -ExpectFailure | Out-Null
    Invoke-Atomic -Arguments (@(
        '--activate',
        '--release-id', 'candidate-one',
        '--expected-current', 'baseline-offline',
        '--expected-env-sha256', $candidateEnvSha,
        '--expected-db-name-sha256', ('0' * 64),
        '--confirm-activate',
        '--health-url', 'http://127.0.0.1/candidate-one'
    ) + $common) -ExpectFailure | Out-Null
    Assert-CurrentRelease -Root $siteRootBash -ReleaseId 'baseline-offline' -Message 'Rejected activation binding approval changed current.'

    Invoke-Atomic -Arguments (@(
        '--activate',
        '--release-id', 'candidate-one',
        '--expected-current', 'wrong-current',
        '--confirm-activate',
        '--health-url', 'http://127.0.0.1/candidate-one'
    ) + $candidateBinding + $common) -ExpectFailure | Out-Null
    Assert-CurrentRelease -Root $siteRootBash -ReleaseId 'baseline-offline' -Message 'CAS failure changed current.'

    Write-Utf8NoBom (Join-Path $stubState 'ancestor-mount-prefix') "$siteRootBash/releases/candidate-one/runtime`n"
    $ancestorMountOutput = Invoke-Atomic -Arguments (@(
        '--activate',
        '--release-id', 'candidate-one',
        '--expected-current', 'baseline-offline',
        '--confirm-activate',
        '--health-url', 'http://127.0.0.1/candidate-one'
    ) + $candidateBinding + $common) -ExpectFailure
    Remove-Item -LiteralPath (Join-Path $stubState 'ancestor-mount-prefix') -Force
    Assert-CurrentRelease -Root $siteRootBash -ReleaseId 'baseline-offline' -Message 'Ancestor mount rejection changed current.'
    if (($ancestorMountOutput -join "`n") -notmatch 'crosses a mount boundary') {
        throw 'Ancestor mount did not fail through the runtime mount-boundary gate.'
    }
    Assert-Bash -Expression "test -f '$siteRootBash/releases/candidate-one/runtime/cache/f5/probe.php' && test -f '$siteRootBash/releases/baseline-offline/runtime/cache/current-sentinel'" -Message 'Ancestor mount rejection cleared candidate or current runtime data.'

    Invoke-Atomic -Arguments (@(
        '--activate',
        '--release-id', 'candidate-one',
        '--expected-current', 'baseline-offline',
        '--confirm-activate',
        '--health-url', 'http://127.0.0.1/candidate-one'
    ) + $candidateBinding + $common) | Out-Null
    Assert-CurrentRelease -Root $siteRootBash -ReleaseId 'candidate-one' -Message 'Candidate activation did not switch current.'
    Assert-Bash -Expression "! find '$siteRootBash/releases/candidate-one/runtime/cache' '$siteRootBash/releases/candidate-one/runtime/temp' -mindepth 1 -print -quit | grep -q ." -Message 'Activation did not reset release-local cache/temp pollution.'
    Assert-Bash -Expression "'$stubDirectoryBash/stat' -c '%U:%G' -- '$siteRootBash/releases/candidate-one/runtime/cache' | grep -Fqx 'www:www' && '$stubDirectoryBash/stat' -c '%U:%G' -- '$siteRootBash/releases/candidate-one/runtime/temp' | grep -Fqx 'www:www'" -Message 'Activation did not restore service ownership on release-local runtime roots.'
    Assert-Bash -Expression "grep -Fqx 'current-release-sentinel' '$siteRootBash/releases/baseline-offline/runtime/cache/current-sentinel'" -Message 'Activation reset the previously current release runtime.'

    Invoke-Atomic -Arguments (@(
        '--release-id', 'candidate-two',
        '--archive', $archiveTwoBash,
        '--archive-sha256', $archiveTwoSha,
        '--env-source', $candidateEnvBash
    ) + $candidateBinding + $common) | Out-Null
    Write-Utf8NoBom (Join-Path $stubState 'fail-fpm-once') 'fail once'
    $failedActivationOutput = Invoke-Atomic -Arguments (@(
        '--activate',
        '--release-id', 'candidate-two',
        '--expected-current', 'candidate-one',
        '--confirm-activate',
        '--health-url', 'http://127.0.0.1/candidate-two'
    ) + $candidateBinding + $common) -ExpectFailure
    try {
        Assert-CurrentRelease -Root $siteRootBash -ReleaseId 'candidate-one' -Message 'Failed activation did not automatically restore previous current.'
    } catch {
        throw "$($_.Exception.Message)`nFailed activation output:`n$($failedActivationOutput -join "`n")"
    }
    if (($failedActivationOutput -join "`n") -notmatch 'previous release was restored and verified healthy') {
        throw 'Single FPM failure did not report a verified rollback outcome.'
    }

    Write-Utf8NoBom (Join-Path $stubState 'external-current-once') "$siteRootBash|baseline-offline`n"
    $externalCasOutput = Invoke-Atomic -Arguments (@(
        '--activate',
        '--release-id', 'candidate-two',
        '--expected-current', 'candidate-one',
        '--confirm-activate',
        '--health-url', 'http://127.0.0.1/candidate-two'
    ) + $candidateBinding + $common) -ExpectFailure
    Assert-CurrentRelease -Root $siteRootBash -ReleaseId 'baseline-offline' -Message 'Final CAS overwrote an externally changed current link.'
    if (($externalCasOutput -join "`n") -notmatch 'did not overwrite the externally changed current link') {
        throw 'External current mutation did not reach the final CAS rejection branch.'
    }
    Invoke-Bash -Command "rm -f '$siteRootBash/current' && ln -s 'releases/candidate-one' '$siteRootBash/current'" | Out-Null

    Write-Utf8NoBom (Join-Path $stubState 'fail-fpm-always') 'fail persistently'
    $persistentFpmOutput = Invoke-Atomic -Arguments (@(
        '--activate',
        '--release-id', 'candidate-two',
        '--expected-current', 'candidate-one',
        '--confirm-activate',
        '--health-url', 'http://127.0.0.1/candidate-two'
    ) + $candidateBinding + $common) -ExpectFailure
    Remove-Item -LiteralPath (Join-Path $stubState 'fail-fpm-always') -Force
    Assert-CurrentRelease -Root $siteRootBash -ReleaseId 'candidate-one' -Message 'Persistent FPM failure did not restore the previous current link.'
    $persistentFpmText = $persistentFpmOutput -join "`n"
    if ($persistentFpmText -notmatch 'ROLLBACK_FPM_RELOAD_FAILED' -or $persistentFpmText -match 'previous release was restored and verified healthy') {
        throw 'Persistent FPM failure incorrectly claimed a verified service rollback.'
    }

    Write-Utf8NoBom (Join-Path $stubState 'fail-audit-commit-once') 'fail audit commit once'
    $auditFailureOutput = Invoke-Atomic -Arguments (@(
        '--activate',
        '--release-id', 'candidate-two',
        '--expected-current', 'candidate-one',
        '--confirm-activate',
        '--health-url', 'http://127.0.0.1/candidate-two'
    ) + $candidateBinding + $common) -ExpectFailure
    Assert-CurrentRelease -Root $siteRootBash -ReleaseId 'candidate-one' -Message 'Audit commit failure did not automatically reverse current.'
    if (($auditFailureOutput -join "`n") -notmatch 'automatically rolled back because its audit record could not be committed') {
        throw 'Audit commit failure did not report its automatic rollback.'
    }
    Assert-Bash -Expression "grep -R -Fq 'status=rolled-back-after-audit-commit-failure' '$siteRootBash/.deploy/manifests'" -Message 'Audit commit failure did not leave a finalized rollback audit.'

    Invoke-Atomic -Arguments (@(
        '--activate',
        '--release-id', 'candidate-two',
        '--expected-current', 'candidate-one',
        '--confirm-activate',
        '--health-url', 'http://127.0.0.1/candidate-two'
    ) + $candidateBinding + $common) | Out-Null
    Assert-CurrentRelease -Root $siteRootBash -ReleaseId 'candidate-two' -Message 'Candidate two activation did not switch current.'

    Invoke-Atomic -Arguments (@(
        '--rollback',
        '--release-id', 'candidate-one',
        '--expected-current', 'candidate-two',
        '--expected-env-sha256', ('0' * 64),
        '--expected-db-name-sha256', $candidateDbNameSha,
        '--confirm-rollback',
        '--health-url', 'http://127.0.0.1/candidate-one'
    ) + $common) -ExpectFailure | Out-Null
    Assert-CurrentRelease -Root $siteRootBash -ReleaseId 'candidate-two' -Message 'Rejected rollback binding approval changed current.'

    Invoke-Atomic -Arguments (@(
        '--rollback',
        '--release-id', 'candidate-one',
        '--expected-current', 'candidate-two',
        '--confirm-rollback',
        '--health-url', 'http://127.0.0.1/candidate-one'
    ) + $candidateBinding + $common) | Out-Null
    Assert-CurrentRelease -Root $siteRootBash -ReleaseId 'candidate-one' -Message 'Explicit rollback did not restore candidate one.'

    Write-Utf8NoBom (Join-Path $siteRoot 'releases\baseline-offline\composer.json') '{"baseline":"tampered"}'
    $baselineTamperOutput = Invoke-Atomic -Arguments (@(
        '--rollback',
        '--release-id', 'baseline-offline',
        '--expected-current', 'candidate-one',
        '--confirm-rollback',
        '--health-url', 'http://127.0.0.1/baseline'
    ) + $baselineBinding + $common) -ExpectFailure
    Assert-CurrentRelease -Root $siteRootBash -ReleaseId 'candidate-one' -Message 'Tampered baseline was selected as current.'
    if (($baselineTamperOutput -join "`n") -notmatch 'baseline content validation failed') {
        throw 'Baseline tamper did not fail through content-manifest validation.'
    }

    Invoke-Bash -Command "tar --transform='s#^\\./#../#' -czf '$badArchiveBash' -C '$candidateSourceBash' ." | Out-Null
    $badSha = (Get-FileHash -LiteralPath $badArchive -Algorithm SHA256).Hash.ToLowerInvariant()
    Invoke-Atomic -Arguments (@(
        '--release-id', 'bad-traversal',
        '--archive', $badArchiveBash,
        '--archive-sha256', $badSha,
        '--env-source', $candidateEnvBash
    ) + $candidateBinding + $common) -ExpectFailure | Out-Null
    Assert-Bash -Expression "test ! -e '$siteRootBash/releases/bad-traversal'" -Message 'Traversal archive created a release.'

    $linkSource = Join-Path $smokeRoot 'link-source'
    New-Item -ItemType Directory -Force -Path $linkSource | Out-Null
    $linkSourceBash = Convert-ToBashPath $linkSource
    Invoke-Bash -Command "ln -s '$candidateSourceBash/public' '$linkSourceBash/unsafe-link' && tar -czf '$linkArchiveBash' -C '$linkSourceBash' ." | Out-Null
    $linkSha = (Get-FileHash -LiteralPath $linkArchive -Algorithm SHA256).Hash.ToLowerInvariant()
    Invoke-Atomic -Arguments (@(
        '--release-id', 'bad-link',
        '--archive', $linkArchiveBash,
        '--archive-sha256', $linkSha,
        '--env-source', $candidateEnvBash
    ) + $candidateBinding + $common) -ExpectFailure | Out-Null
    Assert-Bash -Expression "test ! -e '$siteRootBash/releases/bad-link'" -Message 'Symlink archive created a release.'

    $commandLog = Join-Path $stubState 'commands.log'
    if (Test-Path -LiteralPath $commandLog) {
        $logText = [System.IO.File]::ReadAllText($commandLog)
        if ($logText -match '(?i)mysql|mysqldump|migrate|install-sale') {
            throw 'Atomic deployment smoke observed a forbidden database command.'
        }
    }

    & powershell -NoProfile -ExecutionPolicy Bypass -File $buildScript -ContractSelfTest
    if ($LASTEXITCODE -ne 0) {
        throw 'Build/deploy release contract self-test failed.'
    }

    Write-Host '[atomic-deploy-smoke] passed: external env/DB binding approvals, protected input snapshots, baseline permission normalization/manifest, owner gates, release-local runtime reset, provenance, final CAS, audit rollback, strict FPM failure state, activation/rollback, traversal/link rejection, build contract, and zero DB commands'
} finally {
    if (Test-Path -LiteralPath $smokeRoot) {
        $resolvedSmoke = [System.IO.Path]::GetFullPath($smokeRoot)
        $resolvedTemp = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath()).TrimEnd('\') + '\'
        if ($resolvedSmoke.StartsWith($resolvedTemp, [System.StringComparison]::OrdinalIgnoreCase) -and
            (Split-Path -Leaf $resolvedSmoke).StartsWith('oa-atomic-smoke-', [System.StringComparison]::Ordinal)) {
            Remove-Item -LiteralPath $resolvedSmoke -Recurse -Force
        }
    }
}
