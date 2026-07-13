param(
    [string]$PhpBinary = 'php',
    [string]$ComposerBinary = 'composer',
    [string]$NginxBinary = 'nginx',
    [string]$PhpFpmBinary = 'php-fpm',
    [switch]$Production,
    [switch]$Strict,
    [switch]$SkipThinkBoot,
    [switch]$SkipWritableProbe,
    [switch]$CreateMissingWritableDirs,
    [switch]$CheckErrorLogPolicy,
    [switch]$CheckOpcachePolicy,
    [switch]$CheckBackupTools,
    [switch]$CheckSchedulerPolicy,
    [switch]$CheckCachePolicy,
    [switch]$CheckCookiePolicy,
    [switch]$CheckUrlPolicy,
    [switch]$CheckStoragePolicy,
    [switch]$CheckProviderPolicy,
    [switch]$CheckEnvTemplatePolicy,
    [switch]$CheckRuntimePermissionPolicy,
    [switch]$CheckWebServerPolicy,
    [switch]$CheckSecurityHeadersPolicy,
    [switch]$CheckCorsPolicy,
    [switch]$CheckNginxSyntax,
    [switch]$CheckPhpFpmSyntax,
    [switch]$CheckDatabaseSchema,
    [switch]$CheckArtifactPolicy,
    [switch]$CheckFrontendBuildPolicy,
    [switch]$CheckComposerPolicy,
    [switch]$CheckReleasePackagePolicy,
    [string]$MysqlDumpBinary = 'mysqldump',
    [string]$MysqlClientBinary = 'mysql',
    [string]$BackupDirectory = 'runtime/backup',
    [string]$ReleaseRoot = '.',
    [string]$SchedulerPolicyDocument = 'docs/tasks/scheduler-queue-policy.md',
    [int]$CacheTcpTimeoutSeconds = 2,
    [string]$ExpectedPublicRoot = '',
    [string]$PublicBaseUrl = '',
    [string]$CorsProbeOrigin = '',
    [int]$HttpProbeTimeoutSeconds = 5,
    [string]$MinUploadMaxFilesize = '8M',
    [string]$MinPostMaxSize = '8M'
)

$ErrorActionPreference = 'Stop'

$script:FailureCount = 0
$script:WarningCount = 0

function Write-Check {
    param(
        [string]$Status,
        [string]$Name,
        [string]$Detail = ''
    )

    $line = "[$Status] $Name"
    if (-not [string]::IsNullOrWhiteSpace($Detail)) {
        $line = "$line - $Detail"
    }

    Write-Host $line
}

function Add-Ok {
    param([string]$Name, [string]$Detail = '')
    Write-Check -Status 'OK' -Name $Name -Detail $Detail
}

function Add-Warn {
    param([string]$Name, [string]$Detail = '')
    $script:WarningCount++
    Write-Check -Status 'WARN' -Name $Name -Detail $Detail
}

function Add-Fail {
    param([string]$Name, [string]$Detail = '')
    $script:FailureCount++
    Write-Check -Status 'FAIL' -Name $Name -Detail $Detail
}

function Add-ConditionalBackupIssue {
    param([string]$Name, [string]$Detail = '')

    if ($Production) {
        Add-Fail -Name $Name -Detail $Detail
    } else {
        Add-Warn -Name $Name -Detail $Detail
    }
}

function Add-ConditionalProductionIssue {
    param([string]$Name, [string]$Detail = '')

    if ($Production) {
        Add-Fail -Name $Name -Detail $Detail
    } else {
        Add-Warn -Name $Name -Detail $Detail
    }
}

function Test-Command {
    param([string]$Command)

    $found = Get-Command $Command -ErrorAction SilentlyContinue
    return $null -ne $found
}

function Read-DotEnv {
    param([string]$Path)

    $values = @{}
    if (-not (Test-Path -LiteralPath $Path)) {
        return $values
    }

    Get-Content -LiteralPath $Path | ForEach-Object {
        $line = $_.Trim()
        if ([string]::IsNullOrWhiteSpace($line) -or $line.StartsWith('#')) {
            return
        }

        if ($line -match '^([^=\s]+)\s*=\s*(.*)$') {
            $key = $Matches[1].Trim()
            $value = $Matches[2].Trim()
            if (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'"))) {
                $value = $value.Substring(1, $value.Length - 2)
            }

            $values[$key] = $value
        }
    }

    return $values
}

function Test-DirectoryWritable {
    param([string]$Path)

    $probePath = Join-Path $Path ".deployment-readiness-$([System.Guid]::NewGuid().ToString('N')).tmp"
    try {
        [System.IO.File]::WriteAllText($probePath, 'ok')
        Remove-Item -LiteralPath $probePath -Force
        return $true
    } catch {
        if (Test-Path -LiteralPath $probePath) {
            Remove-Item -LiteralPath $probePath -Force -ErrorAction SilentlyContinue
        }

        return $false
    }
}

function Test-TcpConnection {
    param(
        [string]$HostName,
        [int]$Port,
        [int]$TimeoutSeconds
    )

    $client = [System.Net.Sockets.TcpClient]::new()
    try {
        $timeoutMs = [Math]::Max(1, $TimeoutSeconds) * 1000
        $connect = $client.BeginConnect($HostName, $Port, $null, $null)
        $completed = $connect.AsyncWaitHandle.WaitOne($timeoutMs, $false)
        if (-not $completed) {
            return $false
        }

        $client.EndConnect($connect)
        return $client.Connected
    } catch {
        return $false
    } finally {
        $client.Dispose()
    }
}

function Resolve-CanonicalPath {
    param([string]$Path)

    try {
        $resolved = Resolve-Path -LiteralPath $Path -ErrorAction Stop
        return $resolved.ProviderPath.TrimEnd('\', '/')
    } catch {
        return $null
    }
}

function Resolve-ConfiguredProjectPath {
    param([string]$Path)

    if ([string]::IsNullOrWhiteSpace($Path)) {
        return ''
    }

    $normalized = $Path -replace '[\\/]', [System.IO.Path]::DirectorySeparatorChar
    if ([System.IO.Path]::IsPathRooted($normalized)) {
        return $normalized.TrimEnd('\', '/')
    }

    $projectRoot = (Get-Location).Path
    return (Join-Path $projectRoot $normalized.TrimStart('\', '/')).TrimEnd('\', '/')
}

function Test-ConfiguredPathUnderRoot {
    param(
        [string]$Path,
        [string]$Root
    )

    if ([string]::IsNullOrWhiteSpace($Path) -or [string]::IsNullOrWhiteSpace($Root)) {
        return $false
    }

    try {
        $absolutePath = [System.IO.Path]::GetFullPath((Resolve-ConfiguredProjectPath -Path $Path)).TrimEnd('\', '/')
        $absoluteRoot = [System.IO.Path]::GetFullPath((Resolve-ConfiguredProjectPath -Path $Root)).TrimEnd('\', '/')
        $rootPrefix = $absoluteRoot + [System.IO.Path]::DirectorySeparatorChar
        return $absolutePath.Equals($absoluteRoot, [System.StringComparison]::OrdinalIgnoreCase) -or
            $absolutePath.StartsWith($rootPrefix, [System.StringComparison]::OrdinalIgnoreCase)
    } catch {
        return $false
    }
}

function Get-UnixMode {
    param([string]$Path)

    if ([System.Environment]::OSVersion.Platform -eq [System.PlatformID]::Win32NT) {
        return $null
    }

    if (-not (Test-Command 'stat')) {
        return $null
    }

    $gnuStat = Invoke-External -FilePath 'stat' -Arguments @('-c', '%a', $Path)
    if ($gnuStat.ExitCode -eq 0) {
        $mode = (@($gnuStat.Output) | Select-Object -First 1).ToString().Trim()
        if ($mode -match '^[0-7]+$') {
            return $mode
        }
    }

    $bsdStat = Invoke-External -FilePath 'stat' -Arguments @('-f', '%Lp', $Path)
    if ($bsdStat.ExitCode -eq 0) {
        $mode = (@($bsdStat.Output) | Select-Object -First 1).ToString().Trim()
        if ($mode -match '^[0-7]+$') {
            return $mode
        }
    }

    return $null
}

function Test-UnixModeGroupOrOtherWritable {
    param([string]$Mode)

    if ([string]::IsNullOrWhiteSpace($Mode) -or $Mode -notmatch '^[0-7]{3,4}$') {
        return $false
    }

    $digits = $Mode.Substring($Mode.Length - 3)
    $group = [int]::Parse($digits.Substring(1, 1), [System.Globalization.CultureInfo]::InvariantCulture)
    $other = [int]::Parse($digits.Substring(2, 1), [System.Globalization.CultureInfo]::InvariantCulture)
    return (($group -band 2) -ne 0) -or (($other -band 2) -ne 0)
}

function Test-UnixModeOtherReadable {
    param([string]$Mode)

    if ([string]::IsNullOrWhiteSpace($Mode) -or $Mode -notmatch '^[0-7]{3,4}$') {
        return $false
    }

    $digits = $Mode.Substring($Mode.Length - 3)
    $other = [int]::Parse($digits.Substring(2, 1), [System.Globalization.CultureInfo]::InvariantCulture)
    return (($other -band 4) -ne 0)
}

function Invoke-External {
    param(
        [string]$FilePath,
        [string[]]$Arguments
    )

    $previousErrorActionPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $output = & $FilePath @Arguments 2>&1
        $exitCode = $LASTEXITCODE
        if ($null -eq $exitCode) {
            $exitCode = 0
        }

        return @{
            ExitCode = $exitCode
            Output = @($output)
        }
    } catch {
        return @{
            ExitCode = 1
            Output = @($_.Exception.Message)
        }
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }
}

function Convert-PhpIniSizeToBytes {
    param([string]$Value)

    if ([string]::IsNullOrWhiteSpace($Value)) {
        return $null
    }

    $normalized = $Value.Trim()
    if ($normalized -eq '-1') {
        return [int64]-1
    }

    if ($normalized -match '^(?<number>[+-]?\d+(?:\.\d+)?)\s*(?<unit>[KMGTP]?)(?:B)?$') {
        $number = [double]::Parse($Matches['number'], [System.Globalization.CultureInfo]::InvariantCulture)
        $unit = $Matches['unit'].ToUpperInvariant()
        $multiplier = switch ($unit) {
            'K' { 1KB }
            'M' { 1MB }
            'G' { 1GB }
            'T' { [math]::Pow(1024, 4) }
            'P' { [math]::Pow(1024, 5) }
            default { 1 }
        }

        return [int64]($number * $multiplier)
    }

    return $null
}

function Join-UrlPath {
    param(
        [string]$BaseUrl,
        [string]$Path
    )

    $base = $BaseUrl.TrimEnd('/')
    $suffix = $Path
    if (-not $suffix.StartsWith('/')) {
        $suffix = "/$suffix"
    }

    return "$base$suffix"
}

function Test-LocalUrlHost {
    param([string]$HostName)

    $normalized = $HostName.Trim().ToLowerInvariant()
    return @('localhost', '127.0.0.1', '::1') -contains $normalized
}

function Get-HttpStatusCode {
    param(
        [string]$Url,
        [int]$TimeoutSeconds
    )

    try {
        $request = [System.Net.HttpWebRequest][System.Net.WebRequest]::Create($Url)
        $request.Method = 'GET'
        $request.AllowAutoRedirect = $false
        $request.Timeout = [Math]::Max(1, $TimeoutSeconds) * 1000
        $request.UserAgent = 'OA-ThinkPHP deployment-readiness'

        $response = $request.GetResponse()
        try {
            return @{
                StatusCode = [int]([System.Net.HttpWebResponse]$response).StatusCode
                Error = ''
            }
        } finally {
            $response.Close()
        }
    } catch [System.Net.WebException] {
        if ($null -ne $_.Exception.Response) {
            $response = [System.Net.HttpWebResponse]$_.Exception.Response
            try {
                return @{
                    StatusCode = [int]$response.StatusCode
                    Error = ''
                }
            } finally {
                $response.Close()
            }
        }

        return @{
            StatusCode = $null
            Error = $_.Exception.Status.ToString()
        }
    } catch {
        return @{
            StatusCode = $null
            Error = $_.Exception.Message
        }
    }
}

function Get-HttpResponseMetadata {
    param(
        [string]$Url,
        [int]$TimeoutSeconds,
        [string]$Method = 'GET',
        [hashtable]$RequestHeaders = @{}
    )

    try {
        $request = [System.Net.HttpWebRequest][System.Net.WebRequest]::Create($Url)
        $request.Method = $Method.ToUpperInvariant()
        $request.AllowAutoRedirect = $false
        $request.Timeout = [Math]::Max(1, $TimeoutSeconds) * 1000
        $request.UserAgent = 'OA-ThinkPHP deployment-readiness'
        foreach ($key in $RequestHeaders.Keys) {
            $request.Headers[$key] = [string]$RequestHeaders[$key]
        }

        $response = $request.GetResponse()
        try {
            $headers = @{}
            foreach ($key in $response.Headers.AllKeys) {
                $headers[$key.ToLowerInvariant()] = $response.Headers[$key]
            }

            return @{
                StatusCode = [int]([System.Net.HttpWebResponse]$response).StatusCode
                Headers = $headers
                Error = ''
            }
        } finally {
            $response.Close()
        }
    } catch [System.Net.WebException] {
        if ($null -ne $_.Exception.Response) {
            $response = [System.Net.HttpWebResponse]$_.Exception.Response
            try {
                $headers = @{}
                foreach ($key in $response.Headers.AllKeys) {
                    $headers[$key.ToLowerInvariant()] = $response.Headers[$key]
                }

                return @{
                    StatusCode = [int]$response.StatusCode
                    Headers = $headers
                    Error = ''
                }
            } finally {
                $response.Close()
            }
        }

        return @{
            StatusCode = $null
            Headers = @{}
            Error = $_.Exception.Status.ToString()
        }
    } catch {
        return @{
            StatusCode = $null
            Headers = @{}
            Error = $_.Exception.Message
        }
    }
}

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $repoRoot

Write-Host "Deployment readiness root: $repoRoot"
Write-Host 'Secrets are not printed. This script does not edit .env, config, database rows, or production data.'
Write-Host 'Missing writable directories are created only when -CreateMissingWritableDirs is supplied.'
Write-Host ''

$requiredFiles = @(
    'composer.json',
    'composer.lock',
    'think',
    'public/index.php',
    'public/router.php',
    'public/.htaccess',
    'config/app.php',
    'config/database.php',
    'config/cache.php',
    'config/log.php',
    'config/filesystem.php'
)

foreach ($path in $requiredFiles) {
    if (Test-Path -LiteralPath $path) {
        Add-Ok "Required file $path"
    } else {
        Add-Fail "Required file $path" 'missing'
    }
}

$forbiddenPublicEntries = @(
    '.env',
    '.example.env',
    'composer.json',
    'composer.lock',
    'vendor',
    'runtime',
    'app',
    'config',
    'route',
    'extend',
    'docs',
    'scripts',
    'tests',
    'think',
    'PLANS.md',
    'IMPLEMENT.md',
    'STATUS.md'
)

$publicExposureCount = 0
foreach ($entry in $forbiddenPublicEntries) {
    $publicEntryPath = Join-Path 'public' $entry
    if (Test-Path -LiteralPath $publicEntryPath) {
        $publicExposureCount++
        Add-Fail 'Public web exposure guard' "$publicEntryPath must not be web-accessible"
    }
}

if ($publicExposureCount -eq 0) {
    Add-Ok 'Public web exposure guard' 'no sensitive project entries under public'
}

if ((Test-Path -LiteralPath '.git') -and (Test-Command 'git')) {
    $trackedEnv = Invoke-External -FilePath 'git' -Arguments @('ls-files', '--error-unmatch', '.env')
    if ($trackedEnv.ExitCode -eq 0) {
        Add-Fail 'Git secret guard' '.env is tracked; remove it from source control history/index before deployment'
    } else {
        Add-Ok 'Git secret guard' '.env is not tracked'
    }

    $ignoredPaths = @(
        '.env',
        'vendor/autoload.php',
        'runtime/test.tmp',
        'public/storage/test.tmp'
    )

    foreach ($path in $ignoredPaths) {
        $ignoreCheck = Invoke-External -FilePath 'git' -Arguments @('check-ignore', '--quiet', '--', $path)
        if ($ignoreCheck.ExitCode -eq 0) {
            Add-Ok "Git ignore guard $path"
        } else {
            Add-Warn "Git ignore guard $path" 'not ignored; verify source-control hygiene before release'
        }
    }
} else {
    Add-Warn 'Git secret guard' 'git metadata or command unavailable; verify .env/runtime/vendor ignore rules before release'
}

if (Test-Path -LiteralPath '.env') {
    Add-Ok '.env present' 'values hidden'
} else {
    Add-Fail '.env present' 'missing local/deployment environment file'
}

if (Test-Path -LiteralPath '.example.env') {
    Add-Ok '.example.env present'
} else {
    Add-Warn '.example.env present' 'missing sample environment file'
}

if (Test-Path -LiteralPath 'vendor/autoload.php') {
    Add-Ok 'Composer vendor autoload present'
} else {
    Add-Fail 'Composer vendor autoload present' 'run composer install before deployment'
}

if (Test-Command $PhpBinary) {
    $phpVersion = Invoke-External -FilePath $PhpBinary -Arguments @('-r', 'echo PHP_VERSION;')
    if ($phpVersion.ExitCode -eq 0) {
        $versionText = ($phpVersion.Output -join '').Trim()
        try {
            $version = [version]$versionText
            if ($version -ge [version]'8.0.0') {
                Add-Ok 'PHP version' $versionText
            } else {
                Add-Fail 'PHP version' "found $versionText, require >= 8.0.0"
            }
        } catch {
            Add-Warn 'PHP version' "unable to parse: $versionText"
        }
    } else {
        Add-Fail 'PHP version' 'php -r failed'
    }
} else {
    Add-Fail 'PHP command' "$PhpBinary not found"
}

if (Test-Command $ComposerBinary) {
    $composerVersion = Invoke-External -FilePath $ComposerBinary -Arguments @('--version')
    if ($composerVersion.ExitCode -eq 0) {
        $firstLine = (@($composerVersion.Output) | Select-Object -First 1)
        Add-Ok 'Composer command' $firstLine
    } else {
        Add-Warn 'Composer command' "$ComposerBinary --version failed"
    }
} else {
    Add-Warn 'Composer command' "$ComposerBinary not found; acceptable only when vendor is already deployed"
}

if (Test-Command $PhpBinary) {
    $requiredExtensions = @('pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json', 'tokenizer', 'fileinfo', 'curl', 'zip', 'dom', 'xml')
    foreach ($extension in $requiredExtensions) {
        $phpCode = "echo extension_loaded('$extension') ? 'yes' : 'no';"
        $extensionCheck = Invoke-External -FilePath $PhpBinary -Arguments @('-r', $phpCode)
        $loaded = (($extensionCheck.Output -join '').Trim() -eq 'yes')
        if ($extensionCheck.ExitCode -eq 0 -and $loaded) {
            Add-Ok "PHP extension $extension"
        } else {
            Add-Warn "PHP extension $extension" 'not loaded; verify before staging/production'
        }
    }
}

if (Test-Command $PhpBinary) {
    $phpIniCode = @'
$keys = array('file_uploads', 'upload_max_filesize', 'post_max_size', 'max_file_uploads', 'memory_limit');
foreach ($keys as $key) {
    echo $key . '=' . ini_get($key) . PHP_EOL;
}
'@
    $phpIniCheck = Invoke-External -FilePath $PhpBinary -Arguments @('-r', $phpIniCode)
    $phpIniValues = @{}
    if ($phpIniCheck.ExitCode -eq 0) {
        foreach ($line in @($phpIniCheck.Output)) {
            $text = ([string]$line).Trim()
            if ($text -match '^([^=]+)=(.*)$') {
                $phpIniValues[$Matches[1]] = $Matches[2]
            }
        }
    }

    if ($phpIniCheck.ExitCode -ne 0 -or $phpIniValues.Count -eq 0) {
        Add-Warn 'PHP upload/body limits' 'unable to read file_uploads, upload_max_filesize, post_max_size, max_file_uploads, and memory_limit'
    } else {
        $fileUploads = if ($phpIniValues.ContainsKey('file_uploads')) { ([string]$phpIniValues['file_uploads']).Trim().ToLowerInvariant() } else { '' }
        if (@('1', 'on', 'true', 'yes') -contains $fileUploads) {
            Add-Ok 'PHP file_uploads' 'enabled'
        } else {
            Add-Fail 'PHP file_uploads' 'disabled; upload/import endpoints will not accept files'
        }

        $minUploadBytes = Convert-PhpIniSizeToBytes -Value $MinUploadMaxFilesize
        if ($null -eq $minUploadBytes) {
            Add-Warn 'PHP upload_max_filesize threshold' "$MinUploadMaxFilesize cannot be parsed"
        }

        $uploadLimit = if ($phpIniValues.ContainsKey('upload_max_filesize')) { ([string]$phpIniValues['upload_max_filesize']).Trim() } else { '' }
        $uploadBytes = Convert-PhpIniSizeToBytes -Value $uploadLimit
        if ($null -eq $uploadBytes) {
            Add-Warn 'PHP upload_max_filesize' "$uploadLimit cannot be parsed"
        } elseif ($null -ne $minUploadBytes -and $uploadBytes -ge 0 -and $uploadBytes -lt $minUploadBytes) {
            Add-Warn 'PHP upload_max_filesize' "$uploadLimit below recommended minimum $MinUploadMaxFilesize"
        } else {
            Add-Ok 'PHP upload_max_filesize' $uploadLimit
        }

        $minPostBytes = Convert-PhpIniSizeToBytes -Value $MinPostMaxSize
        if ($null -eq $minPostBytes) {
            Add-Warn 'PHP post_max_size threshold' "$MinPostMaxSize cannot be parsed"
        }

        $postLimit = if ($phpIniValues.ContainsKey('post_max_size')) { ([string]$phpIniValues['post_max_size']).Trim() } else { '' }
        $postBytes = Convert-PhpIniSizeToBytes -Value $postLimit
        if ($null -eq $postBytes) {
            Add-Warn 'PHP post_max_size' "$postLimit cannot be parsed"
        } elseif ($postBytes -eq 0) {
            Add-Warn 'PHP post_max_size' '0/unlimited; verify this is intentional and bounded by the web server'
        } elseif ($null -ne $minPostBytes -and $postBytes -lt $minPostBytes) {
            Add-Warn 'PHP post_max_size' "$postLimit below recommended minimum $MinPostMaxSize"
        } elseif ($null -ne $uploadBytes -and $uploadBytes -gt 0 -and $postBytes -le $uploadBytes) {
            Add-Warn 'PHP post_max_size' "$postLimit should be larger than upload_max_filesize $uploadLimit"
        } else {
            Add-Ok 'PHP post_max_size' $postLimit
        }

        $maxFileUploadsText = if ($phpIniValues.ContainsKey('max_file_uploads')) { ([string]$phpIniValues['max_file_uploads']).Trim() } else { '' }
        $maxFileUploads = 0
        if ([int]::TryParse($maxFileUploadsText, [ref]$maxFileUploads) -and $maxFileUploads -gt 0) {
            Add-Ok 'PHP max_file_uploads' $maxFileUploadsText
        } else {
            Add-Fail 'PHP max_file_uploads' "$maxFileUploadsText is not a positive integer"
        }

        $memoryLimit = if ($phpIniValues.ContainsKey('memory_limit')) { ([string]$phpIniValues['memory_limit']).Trim() } else { '' }
        $memoryBytes = Convert-PhpIniSizeToBytes -Value $memoryLimit
        if ($memoryBytes -eq -1) {
            Add-Ok 'PHP memory_limit' '-1 (unlimited)'
        } elseif ($null -eq $memoryBytes) {
            Add-Warn 'PHP memory_limit' "$memoryLimit cannot be parsed"
        } elseif ($null -ne $postBytes -and $postBytes -gt 0 -and $memoryBytes -lt $postBytes) {
            Add-Warn 'PHP memory_limit' "$memoryLimit is lower than post_max_size $postLimit"
        } else {
            Add-Ok 'PHP memory_limit' $memoryLimit
        }
    }
}

if (($CheckErrorLogPolicy -or $Production) -and (Test-Command $PhpBinary)) {
    $phpErrorIniCode = @'
$keys = array('display_errors', 'display_startup_errors', 'log_errors', 'error_log', 'expose_php', 'html_errors');
foreach ($keys as $key) {
    echo $key . '=' . ini_get($key) . PHP_EOL;
}
'@
    $phpErrorIniCheck = Invoke-External -FilePath $PhpBinary -Arguments @('-r', $phpErrorIniCode)
    $phpErrorIniValues = @{}
    if ($phpErrorIniCheck.ExitCode -eq 0) {
        foreach ($line in @($phpErrorIniCheck.Output)) {
            $text = ([string]$line).Trim()
            if ($text -match '^([^=]+)=(.*)$') {
                $phpErrorIniValues[$Matches[1]] = $Matches[2]
            }
        }
    }

    if ($phpErrorIniCheck.ExitCode -ne 0 -or $phpErrorIniValues.Count -eq 0) {
        Add-Warn 'PHP error/log policy' 'unable to read display_errors, display_startup_errors, log_errors, error_log, expose_php, and html_errors'
    } else {
        $enabledValues = @('1', 'on', 'true', 'yes', 'stdout', 'stderr')

        $displayErrors = if ($phpErrorIniValues.ContainsKey('display_errors')) { ([string]$phpErrorIniValues['display_errors']).Trim().ToLowerInvariant() } else { '' }
        if ($enabledValues -contains $displayErrors) {
            Add-ConditionalProductionIssue -Name 'PHP display_errors' -Detail "$displayErrors; disable before production"
        } else {
            Add-Ok 'PHP display_errors' $displayErrors
        }

        $displayStartupErrors = if ($phpErrorIniValues.ContainsKey('display_startup_errors')) { ([string]$phpErrorIniValues['display_startup_errors']).Trim().ToLowerInvariant() } else { '' }
        if ($enabledValues -contains $displayStartupErrors) {
            Add-ConditionalProductionIssue -Name 'PHP display_startup_errors' -Detail "$displayStartupErrors; disable before production"
        } else {
            Add-Ok 'PHP display_startup_errors' $displayStartupErrors
        }

        $logErrors = if ($phpErrorIniValues.ContainsKey('log_errors')) { ([string]$phpErrorIniValues['log_errors']).Trim().ToLowerInvariant() } else { '' }
        if ($enabledValues -contains $logErrors) {
            Add-Ok 'PHP log_errors' $logErrors
        } else {
            Add-ConditionalProductionIssue -Name 'PHP log_errors' -Detail "$logErrors; enable error logging before production"
        }

        $errorLog = if ($phpErrorIniValues.ContainsKey('error_log')) { ([string]$phpErrorIniValues['error_log']).Trim() } else { '' }
        if ([string]::IsNullOrWhiteSpace($errorLog)) {
            Add-Warn 'PHP error_log' 'empty; verify PHP-FPM/web-server error log destination before production'
        } else {
            Add-Ok 'PHP error_log' $errorLog
        }

        $exposePhp = if ($phpErrorIniValues.ContainsKey('expose_php')) { ([string]$phpErrorIniValues['expose_php']).Trim().ToLowerInvariant() } else { '' }
        if ($enabledValues -contains $exposePhp) {
            Add-ConditionalProductionIssue -Name 'PHP expose_php' -Detail "$exposePhp; disable before production"
        } else {
            Add-Ok 'PHP expose_php' $exposePhp
        }

        $htmlErrors = if ($phpErrorIniValues.ContainsKey('html_errors')) { ([string]$phpErrorIniValues['html_errors']).Trim().ToLowerInvariant() } else { '' }
        if ($enabledValues -contains $htmlErrors) {
            Add-ConditionalProductionIssue -Name 'PHP html_errors' -Detail "$htmlErrors; disable for API responses before production"
        } else {
            Add-Ok 'PHP html_errors' $htmlErrors
        }
    }
}

if (($CheckOpcachePolicy -or $Production) -and (Test-Command $PhpBinary)) {
    $phpOpcacheCode = @'
$keys = array('opcache.enable', 'opcache.enable_cli', 'opcache.validate_timestamps', 'opcache.revalidate_freq', 'opcache.memory_consumption', 'opcache.max_accelerated_files');
echo 'opcache.loaded=' . (extension_loaded('Zend OPcache') ? 'yes' : 'no') . PHP_EOL;
foreach ($keys as $key) {
    echo $key . '=' . ini_get($key) . PHP_EOL;
}
'@
    $phpOpcacheCheck = Invoke-External -FilePath $PhpBinary -Arguments @('-r', $phpOpcacheCode)
    $phpOpcacheValues = @{}
    if ($phpOpcacheCheck.ExitCode -eq 0) {
        foreach ($line in @($phpOpcacheCheck.Output)) {
            $text = ([string]$line).Trim()
            if ($text -match '^([^=]+)=(.*)$') {
                $phpOpcacheValues[$Matches[1]] = $Matches[2]
            }
        }
    }

    if ($phpOpcacheCheck.ExitCode -ne 0 -or $phpOpcacheValues.Count -eq 0) {
        Add-Warn 'PHP OPcache policy' 'unable to read OPcache settings'
    } else {
        $enabledValues = @('1', 'on', 'true', 'yes')
        $loaded = if ($phpOpcacheValues.ContainsKey('opcache.loaded')) { ([string]$phpOpcacheValues['opcache.loaded']).Trim().ToLowerInvariant() } else { '' }

        if ($loaded -eq 'yes') {
            Add-Ok 'PHP OPcache extension' 'loaded'
        } else {
            Add-ConditionalProductionIssue -Name 'PHP OPcache extension' -Detail 'not loaded; enable OPcache for production PHP-FPM'
        }

        $opcacheEnabled = if ($phpOpcacheValues.ContainsKey('opcache.enable')) { ([string]$phpOpcacheValues['opcache.enable']).Trim().ToLowerInvariant() } else { '' }
        if ($enabledValues -contains $opcacheEnabled) {
            Add-Ok 'PHP opcache.enable' $opcacheEnabled
        } else {
            Add-ConditionalProductionIssue -Name 'PHP opcache.enable' -Detail "$opcacheEnabled; enable OPcache for production PHP-FPM"
        }

        $opcacheCli = if ($phpOpcacheValues.ContainsKey('opcache.enable_cli')) { ([string]$phpOpcacheValues['opcache.enable_cli']).Trim().ToLowerInvariant() } else { '' }
        if ($enabledValues -contains $opcacheCli) {
            Add-Ok 'PHP opcache.enable_cli' $opcacheCli
        } else {
            Add-Warn 'PHP opcache.enable_cli' "$opcacheCli; acceptable for web runtime, but CLI warmup/checks will not use OPcache"
        }

        $validateTimestamps = if ($phpOpcacheValues.ContainsKey('opcache.validate_timestamps')) { ([string]$phpOpcacheValues['opcache.validate_timestamps']).Trim().ToLowerInvariant() } else { '' }
        if ($enabledValues -contains $validateTimestamps) {
            Add-Warn 'PHP opcache.validate_timestamps' "$validateTimestamps; confirm deploy/reload strategy and revalidate frequency"
        } elseif ([string]::IsNullOrWhiteSpace($validateTimestamps)) {
            Add-Warn 'PHP opcache.validate_timestamps' 'empty; verify OPcache web runtime settings'
        } else {
            Add-Ok 'PHP opcache.validate_timestamps' $validateTimestamps
        }

        $revalidateFreqText = if ($phpOpcacheValues.ContainsKey('opcache.revalidate_freq')) { ([string]$phpOpcacheValues['opcache.revalidate_freq']).Trim() } else { '' }
        $revalidateFreq = 0
        if ([int]::TryParse($revalidateFreqText, [ref]$revalidateFreq) -and $revalidateFreq -ge 0) {
            Add-Ok 'PHP opcache.revalidate_freq' $revalidateFreqText
        } else {
            Add-Warn 'PHP opcache.revalidate_freq' "$revalidateFreqText is not a non-negative integer"
        }

        $memoryText = if ($phpOpcacheValues.ContainsKey('opcache.memory_consumption')) { ([string]$phpOpcacheValues['opcache.memory_consumption']).Trim() } else { '' }
        $memoryMb = 0
        if ([int]::TryParse($memoryText, [ref]$memoryMb) -and $memoryMb -gt 0) {
            if ($memoryMb -lt 64) {
                Add-Warn 'PHP opcache.memory_consumption' "$memoryText MB below common production baseline 64"
            } else {
                Add-Ok 'PHP opcache.memory_consumption' "$memoryText MB"
            }
        } else {
            Add-Warn 'PHP opcache.memory_consumption' "$memoryText is not a positive integer"
        }

        $maxFilesText = if ($phpOpcacheValues.ContainsKey('opcache.max_accelerated_files')) { ([string]$phpOpcacheValues['opcache.max_accelerated_files']).Trim() } else { '' }
        $maxFiles = 0
        if ([int]::TryParse($maxFilesText, [ref]$maxFiles) -and $maxFiles -gt 0) {
            if ($maxFiles -lt 4000) {
                Add-Warn 'PHP opcache.max_accelerated_files' "$maxFilesText below common production baseline 4000"
            } else {
                Add-Ok 'PHP opcache.max_accelerated_files' $maxFilesText
            }
        } else {
            Add-Warn 'PHP opcache.max_accelerated_files' "$maxFilesText is not a positive integer"
        }
    }
}

if ($CheckSchedulerPolicy -or $Production) {
    $schedulerPolicyDocumentPresent = $false
    if ([string]::IsNullOrWhiteSpace($SchedulerPolicyDocument)) {
        Add-ConditionalProductionIssue -Name 'Scheduler/queue policy document' -Detail 'empty SchedulerPolicyDocument path'
    } elseif (Test-Path -LiteralPath $SchedulerPolicyDocument -PathType Leaf) {
        $schedulerPolicyDocumentPresent = $true
        Add-Ok 'Scheduler/queue policy document' "$SchedulerPolicyDocument present"
    } else {
        Add-ConditionalProductionIssue -Name 'Scheduler/queue policy document' -Detail "$SchedulerPolicyDocument missing; document whether workers/jobs are disabled or supervised before production"
    }

    if (Test-Path -LiteralPath 'config/console.php' -PathType Leaf) {
        if (Test-Command $PhpBinary) {
            $consoleCommandCode = @'
$config = require 'config/console.php';
$commands = $config['commands'] ?? array();
echo is_array($commands) ? count($commands) : 'invalid';
'@
            $consoleCommandCheck = Invoke-External -FilePath $PhpBinary -Arguments @('-r', $consoleCommandCode)
            $consoleCommandText = ($consoleCommandCheck.Output -join '').Trim()
            $consoleCommandCount = 0
            if ($consoleCommandCheck.ExitCode -eq 0 -and [int]::TryParse($consoleCommandText, [ref]$consoleCommandCount) -and $consoleCommandCount -ge 0) {
                if ($consoleCommandCount -eq 0) {
                    Add-Ok 'ThinkPHP console commands' 'none registered in config/console.php'
                } elseif ($schedulerPolicyDocumentPresent) {
                    Add-Ok 'ThinkPHP console commands' "$consoleCommandCount registered; scheduler/queue policy documented"
                } else {
                    Add-ConditionalProductionIssue -Name 'ThinkPHP console commands' -Detail "$consoleCommandCount registered; document execution, restart, and log policy"
                }
            } else {
                Add-ConditionalProductionIssue -Name 'ThinkPHP console commands' -Detail 'unable to count config/console.php commands'
            }
        } else {
            Add-Warn 'ThinkPHP console commands' "$PhpBinary unavailable; cannot count config/console.php commands"
        }
    } else {
        Add-ConditionalProductionIssue -Name 'ThinkPHP console config' -Detail 'config/console.php missing'
    }

    $commandFiles = @()
    if (Test-Path -LiteralPath 'app' -PathType Container) {
        $commandFiles = @(Get-ChildItem -Path 'app' -Recurse -File -Filter '*Command.php' -ErrorAction SilentlyContinue)
    }

    if ($commandFiles.Count -eq 0) {
        Add-Ok 'App command classes' 'no *Command.php files found under app'
    } elseif ($schedulerPolicyDocumentPresent) {
        Add-Ok 'App command classes' "$($commandFiles.Count) found; scheduler/queue policy documented"
    } else {
        Add-ConditionalProductionIssue -Name 'App command classes' -Detail "$($commandFiles.Count) found; document whether and how they run"
    }

    $queuePackageSignals = @()
    if (Test-Path -LiteralPath 'composer.json' -PathType Leaf) {
        $composerJsonText = Get-Content -LiteralPath 'composer.json' -Raw
        foreach ($package in @('topthink/think-queue', 'workerman/', 'php-amqplib/', 'predis/predis')) {
            if ($composerJsonText.Contains($package)) {
                $queuePackageSignals += $package
            }
        }
    }

    if ($queuePackageSignals.Count -eq 0) {
        Add-Ok 'Queue worker dependencies' 'no known queue/worker package signals in composer.json'
    } elseif ($schedulerPolicyDocumentPresent) {
        Add-Ok 'Queue worker dependencies' "$($queuePackageSignals -join ', ') present; scheduler/queue policy documented"
    } else {
        Add-ConditionalProductionIssue -Name 'Queue worker dependencies' -Detail "$($queuePackageSignals -join ', ') present; document worker process policy"
    }

    $devJobSignals = @()
    if (Test-Path -LiteralPath 'app/controller/dev/JobController.php' -PathType Leaf) {
        $devJobSignals += 'app/controller/dev/JobController.php'
    }
    if (Test-Path -LiteralPath 'app/service/dev/JobService.php' -PathType Leaf) {
        $devJobSignals += 'app/service/dev/JobService.php'
    }
    if ((Test-Path -LiteralPath 'route/app.php' -PathType Leaf) -and (Select-String -Path 'route/app.php' -Pattern 'dev/job' -SimpleMatch -Quiet)) {
        $devJobSignals += 'route/app.php dev/job'
    }

    if ($devJobSignals.Count -eq 0) {
        Add-Ok 'Dev job runtime controls' 'no dev/job control signals found'
    } elseif ($schedulerPolicyDocumentPresent) {
        Add-Ok 'Dev job runtime controls' "present; documented as non-executed by readiness"
    } else {
        Add-ConditionalProductionIssue -Name 'Dev job runtime controls' -Detail 'dev/job controls present; document auth, execution, and disabled/enabled policy before production'
    }
}

$envValues = Read-DotEnv -Path '.env'
if ($envValues.Count -gt 0) {
    $requiredEnvKeys = @('APP_DEBUG', 'DB_TYPE', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PORT', 'DB_CHARSET')
    foreach ($key in $requiredEnvKeys) {
        if ($envValues.ContainsKey($key) -and -not [string]::IsNullOrWhiteSpace([string]$envValues[$key])) {
            Add-Ok ".env key $key" 'set'
        } else {
            Add-Warn ".env key $key" 'missing or empty'
        }
    }

    if ($envValues.ContainsKey('DB_PORT')) {
        $port = 0
        if ([int]::TryParse([string]$envValues['DB_PORT'], [ref]$port) -and $port -gt 0 -and $port -le 65535) {
            Add-Ok '.env DB_PORT' 'valid TCP port'
        } else {
            Add-Warn '.env DB_PORT' 'not a valid TCP port'
        }
    }

    if ($envValues.ContainsKey('APP_DEBUG')) {
        $debugValue = ([string]$envValues['APP_DEBUG']).Trim().ToLowerInvariant()
        if ($Production -and $debugValue -ne 'false') {
            Add-Fail 'Production APP_DEBUG' 'set APP_DEBUG=false before production'
        } elseif ($debugValue -eq 'true') {
            Add-Warn 'APP_DEBUG' 'true; acceptable for local smoke, not production'
        } else {
            Add-Ok 'APP_DEBUG' $debugValue
        }
    }

    $cacheDriver = ''
    if ($envValues.ContainsKey('CACHE_DRIVER')) {
        $cacheDriver = ([string]$envValues['CACHE_DRIVER']).Trim().ToLowerInvariant()
    }

    if ($cacheDriver -eq 'redis') {
        foreach ($key in @('REDIS_HOST', 'REDIS_PORT')) {
            if ($envValues.ContainsKey($key) -and -not [string]::IsNullOrWhiteSpace([string]$envValues[$key])) {
                Add-Ok ".env key $key" 'set'
            } else {
                Add-Warn ".env key $key" 'missing while CACHE_DRIVER=redis'
            }
        }
    }
}

if ($CheckEnvTemplatePolicy -or $Production) {
    $exampleEnvValues = Read-DotEnv -Path '.example.env'
    if ($exampleEnvValues.Count -eq 0) {
        Add-ConditionalProductionIssue -Name 'Example env template' -Detail '.example.env missing or empty'
    } else {
        Add-Ok 'Example env template' '.example.env parseable'

        $requiredTemplateKeys = @(
            'APP_DEBUG',
            'DB_DRIVER',
            'DB_TYPE',
            'DB_HOST',
            'DB_NAME',
            'DB_USER',
            'DB_PASS',
            'DB_PORT',
            'DB_CHARSET',
            'DEFAULT_LANG',
            'CACHE_DRIVER',
            'REDIS_HOST',
            'REDIS_PORT',
            'REDIS_PASSWD',
            'REDIS_DB',
            'REDIS_TIMEOUT',
            'REDIS_EXPIRE',
            'CACHE_PREFIX',
            'APP_HOST'
        )

        $missingTemplateKeys = @()
        foreach ($key in $requiredTemplateKeys) {
            if ($exampleEnvValues.ContainsKey($key)) {
                Add-Ok "Example env key $key" 'documented'
            } else {
                $missingTemplateKeys += $key
            }
        }

        if ($missingTemplateKeys.Count -eq 0) {
            Add-Ok 'Example env required key coverage' 'complete'
        } else {
            Add-ConditionalProductionIssue -Name 'Example env required key coverage' -Detail "missing key(s): $($missingTemplateKeys -join ', ')"
        }

        if ($envValues.Count -gt 0) {
            $localOnlyEnvKeys = @('LOCAL_SUPER_ADMIN_ACCOUNT', 'LOCAL_SUPER_ADMIN_PASSWORD')
            $missingFromTemplate = @()
            foreach ($key in ($envValues.Keys | Sort-Object)) {
                if ($localOnlyEnvKeys -contains $key) {
                    continue
                }

                if (-not $exampleEnvValues.ContainsKey($key)) {
                    $missingFromTemplate += $key
                }
            }

            if ($missingFromTemplate.Count -eq 0) {
                Add-Ok 'Example env local key coverage' 'all non-local .env keys documented'
            } else {
                Add-ConditionalProductionIssue -Name 'Example env local key coverage' -Detail "missing non-local key(s): $($missingFromTemplate -join ', ')"
            }
        }

        if ($exampleEnvValues.ContainsKey('APP_DEBUG')) {
            $exampleDebug = ([string]$exampleEnvValues['APP_DEBUG']).Trim().ToLowerInvariant()
            if ($exampleDebug -eq 'false') {
                Add-Ok 'Example env APP_DEBUG default' 'false'
            } else {
                Add-ConditionalProductionIssue -Name 'Example env APP_DEBUG default' -Detail 'template should default to false for release guidance'
            }
        }

        if ($exampleEnvValues.ContainsKey('DB_PORT') -and -not [string]::IsNullOrWhiteSpace([string]$exampleEnvValues['DB_PORT'])) {
            $exampleDbPort = 0
            if ([int]::TryParse([string]$exampleEnvValues['DB_PORT'], [ref]$exampleDbPort) -and $exampleDbPort -gt 0 -and $exampleDbPort -le 65535) {
                Add-Ok 'Example env DB_PORT' 'valid TCP port'
            } else {
                Add-ConditionalProductionIssue -Name 'Example env DB_PORT' -Detail 'not a valid TCP port'
            }
        }

        foreach ($key in @('DB_PASS', 'REDIS_PASSWD', 'REDIS_PASSWORD', 'LOCAL_SUPER_ADMIN_PASSWORD')) {
            if (-not $exampleEnvValues.ContainsKey($key)) {
                continue
            }

            $templateSecret = ([string]$exampleEnvValues[$key]).Trim()
            if ([string]::IsNullOrWhiteSpace($templateSecret) -or $templateSecret -match '^(?i:<.*>|change.*|.*example.*|.*placeholder.*|.*local.*|.*password.*)$') {
                Add-Ok "Example env secret placeholder $key" 'placeholder or empty'
            } else {
                Add-ConditionalProductionIssue -Name "Example env secret placeholder $key" -Detail 'non-empty value present; verify it is a placeholder, not a real secret'
            }
        }

        foreach ($key in @('LOCAL_SUPER_ADMIN_ACCOUNT', 'LOCAL_SUPER_ADMIN_PASSWORD')) {
            if ($exampleEnvValues.ContainsKey($key) -and -not [string]::IsNullOrWhiteSpace([string]$exampleEnvValues[$key])) {
                Add-Warn "Example env local smoke key $key" 'local smoke credentials should stay out of release templates'
            }
        }
    }
}

if ($CheckUrlPolicy -or $Production) {
    $urlPolicyItems = @()
    $appHost = if ($envValues.ContainsKey('APP_HOST')) { ([string]$envValues['APP_HOST']).Trim() } else { '' }
    $urlPolicyItems += @{
        Name = 'APP_HOST URL policy'
        Url = $appHost
        Optional = $true
    }
    $urlPolicyItems += @{
        Name = 'PublicBaseUrl URL policy'
        Url = $PublicBaseUrl
        Optional = $true
    }

    foreach ($item in $urlPolicyItems) {
        $name = [string]$item.Name
        $url = [string]$item.Url
        if ([string]::IsNullOrWhiteSpace($url)) {
            Add-Warn -Name $name -Detail 'empty; set an HTTPS URL before final staging/production gate if URL generation or HTTP exposure probes depend on it'
            continue
        }

        $uri = $null
        if (-not [Uri]::TryCreate($url, [UriKind]::Absolute, [ref]$uri) -or [string]::IsNullOrWhiteSpace($uri.Scheme) -or [string]::IsNullOrWhiteSpace($uri.Host)) {
            Add-ConditionalProductionIssue -Name $name -Detail 'not an absolute URL with scheme and host'
            continue
        }

        $scheme = $uri.Scheme.ToLowerInvariant()
        $hostName = $uri.Host
        if ($scheme -eq 'https') {
            Add-Ok -Name $name -Detail "https://${hostName}"
        } elseif (($scheme -eq 'http') -and (Test-LocalUrlHost -HostName $hostName)) {
            Add-Ok -Name $name -Detail "local ${scheme}://${hostName}"
        } elseif ($scheme -eq 'http') {
            Add-ConditionalProductionIssue -Name $name -Detail "http://${hostName}; use HTTPS before production"
        } else {
            Add-ConditionalProductionIssue -Name $name -Detail "${scheme}://${hostName} is not an approved HTTP(S) URL"
        }
    }
}

if (($CheckStoragePolicy -or $Production) -and (Test-Command $PhpBinary)) {
    $storagePolicyCode = @'
if (!function_exists('app')) {
    function app() {
        static $app;
        if ($app === null) {
            $app = new class {
                public function getRootPath() {
                    return getcwd() . DIRECTORY_SEPARATOR;
                }

                public function getRuntimePath() {
                    return getcwd() . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR;
                }
            };
        }

        return $app;
    }
}

$config = is_file('config/filesystem.php') ? require 'config/filesystem.php' : array();
$disks = is_array($config['disks'] ?? null) ? $config['disks'] : array();
$default = (string)($config['default'] ?? '');
$values = array(
    'filesystem.default' => $default,
    'disk.default.exists' => ($default !== '' && array_key_exists($default, $disks)) ? 'yes' : 'no',
    'disk.local.exists' => array_key_exists('local', $disks) ? 'yes' : 'no',
    'disk.local.type' => $disks['local']['type'] ?? '',
    'disk.local.root' => $disks['local']['root'] ?? '',
    'disk.public.exists' => array_key_exists('public', $disks) ? 'yes' : 'no',
    'disk.public.type' => $disks['public']['type'] ?? '',
    'disk.public.root' => $disks['public']['root'] ?? '',
    'disk.public.url' => $disks['public']['url'] ?? '',
    'disk.public.visibility' => $disks['public']['visibility'] ?? '',
    'expected.local.root' => app()->getRuntimePath() . 'storage',
    'expected.public.root' => app()->getRootPath() . 'public/storage',
    'expected.dev_file.root' => app()->getRuntimePath() . 'upload' . DIRECTORY_SEPARATOR . 'dev_file',
    'expected.public.webroot' => app()->getRootPath() . 'public',
);
foreach ($values as $key => $value) {
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    } elseif (is_array($value) || is_object($value)) {
        $value = 'non-scalar';
    }
    echo $key . '=' . (string)$value . PHP_EOL;
}
'@
    $storagePolicyCheck = Invoke-External -FilePath $PhpBinary -Arguments @('-r', $storagePolicyCode)
    $storagePolicyValues = @{}
    if ($storagePolicyCheck.ExitCode -eq 0) {
        foreach ($line in @($storagePolicyCheck.Output)) {
            $text = ([string]$line).Trim()
            if ($text -match '^([^=]+)=(.*)$') {
                $storagePolicyValues[$Matches[1]] = $Matches[2]
            }
        }
    }

    if ($storagePolicyCheck.ExitCode -ne 0 -or $storagePolicyValues.Count -eq 0) {
        Add-ConditionalProductionIssue -Name 'File storage policy' -Detail 'unable to read config/filesystem.php'
    } else {
        $getStorageValue = {
            param([string]$Key)
            if ($storagePolicyValues.ContainsKey($Key)) {
                return ([string]$storagePolicyValues[$Key]).Trim()
            }

            return ''
        }

        $defaultDisk = & $getStorageValue 'filesystem.default'
        if ([string]::IsNullOrWhiteSpace($defaultDisk)) {
            Add-ConditionalProductionIssue -Name 'Filesystem default disk' -Detail 'empty default disk'
        } else {
            Add-Ok 'Filesystem default disk' $defaultDisk
            if ((& $getStorageValue 'disk.default.exists') -eq 'yes') {
                Add-Ok 'Filesystem default disk config' "$defaultDisk disk configured"
            } else {
                Add-ConditionalProductionIssue -Name 'Filesystem default disk config' -Detail "$defaultDisk disk missing from config/filesystem.php"
            }
        }

        foreach ($diskName in @('local', 'public')) {
            if ((& $getStorageValue "disk.$diskName.exists") -eq 'yes') {
                Add-Ok "Filesystem $diskName disk" 'configured'
            } else {
                Add-ConditionalProductionIssue -Name "Filesystem $diskName disk" -Detail 'missing from config/filesystem.php'
                continue
            }

            $diskType = (& $getStorageValue "disk.$diskName.type").ToLowerInvariant()
            if ($diskType -eq 'local') {
                Add-Ok "Filesystem $diskName disk type" $diskType
            } else {
                Add-ConditionalProductionIssue -Name "Filesystem $diskName disk type" -Detail "$diskType; expected local for current deployment"
            }
        }

        $pathChecks = @(
            @{
                Name = 'Filesystem local disk root'
                Actual = & $getStorageValue 'disk.local.root'
                Expected = & $getStorageValue 'expected.local.root'
                Label = 'runtime/storage'
            },
            @{
                Name = 'Filesystem public disk root'
                Actual = & $getStorageValue 'disk.public.root'
                Expected = & $getStorageValue 'expected.public.root'
                Label = 'public/storage'
            }
        )

        foreach ($pathCheck in $pathChecks) {
            $actualPath = Resolve-ConfiguredProjectPath -Path ([string]$pathCheck.Actual)
            $expectedPath = Resolve-ConfiguredProjectPath -Path ([string]$pathCheck.Expected)
            $actualCanonical = Resolve-CanonicalPath -Path $actualPath
            $expectedCanonical = Resolve-CanonicalPath -Path $expectedPath
            if ([string]::IsNullOrWhiteSpace($actualPath)) {
                Add-ConditionalProductionIssue -Name ([string]$pathCheck.Name) -Detail 'empty configured path'
            } elseif ($null -eq $actualCanonical) {
                Add-ConditionalProductionIssue -Name ([string]$pathCheck.Name) -Detail "$actualPath missing; create and verify PHP-FPM permissions before upload writes"
            } elseif ($null -ne $expectedCanonical -and [string]::Equals($actualCanonical, $expectedCanonical, [System.StringComparison]::OrdinalIgnoreCase)) {
                Add-Ok ([string]$pathCheck.Name) ([string]$pathCheck.Label)
            } else {
                Add-ConditionalProductionIssue -Name ([string]$pathCheck.Name) -Detail "$actualCanonical does not resolve to $([string]$pathCheck.Label)"
            }
        }

        $publicUrl = & $getStorageValue 'disk.public.url'
        if ([string]::IsNullOrWhiteSpace($publicUrl)) {
            Add-ConditionalProductionIssue -Name 'Filesystem public disk URL' -Detail 'empty public disk url'
        } elseif ($publicUrl.StartsWith('/')) {
            Add-Ok 'Filesystem public disk URL' $publicUrl
        } else {
            $publicUri = $null
            if (-not [Uri]::TryCreate($publicUrl, [UriKind]::Absolute, [ref]$publicUri) -or [string]::IsNullOrWhiteSpace($publicUri.Scheme) -or [string]::IsNullOrWhiteSpace($publicUri.Host)) {
                Add-ConditionalProductionIssue -Name 'Filesystem public disk URL' -Detail 'not a root-relative path or absolute HTTP(S) URL'
            } else {
                $publicScheme = $publicUri.Scheme.ToLowerInvariant()
                $publicHost = $publicUri.Host
                if ($publicScheme -eq 'https') {
                    Add-Ok 'Filesystem public disk URL' "https://${publicHost}"
                } elseif (($publicScheme -eq 'http') -and (Test-LocalUrlHost -HostName $publicHost)) {
                    Add-Ok 'Filesystem public disk URL' "local http://${publicHost}"
                } elseif ($publicScheme -eq 'http') {
                    Add-ConditionalProductionIssue -Name 'Filesystem public disk URL' -Detail "http://${publicHost}; use HTTPS or a root-relative path before production"
                } else {
                    Add-ConditionalProductionIssue -Name 'Filesystem public disk URL' -Detail "$publicScheme is not approved for public disk URLs"
                }
            }
        }

        $publicVisibility = (& $getStorageValue 'disk.public.visibility').ToLowerInvariant()
        if ($publicVisibility -eq 'public') {
            Add-Ok 'Filesystem public disk visibility' $publicVisibility
        } else {
            Add-ConditionalProductionIssue -Name 'Filesystem public disk visibility' -Detail 'missing or not public; public disk downloads may not be served consistently'
        }

        $devFileRootConfigured = if ($envValues.ContainsKey('DEV_FILE_LOCAL_ROOT')) { ([string]$envValues['DEV_FILE_LOCAL_ROOT']).Trim() } else { '' }
        $devFileRootSource = 'default runtime/upload/dev_file'
        $devFileRoot = & $getStorageValue 'expected.dev_file.root'
        if (-not [string]::IsNullOrWhiteSpace($devFileRootConfigured)) {
            $devFileRootSource = 'DEV_FILE_LOCAL_ROOT'
            $devFileRoot = $devFileRootConfigured
        }

        $devFilePath = Resolve-ConfiguredProjectPath -Path $devFileRoot
        $devFileCanonical = Resolve-CanonicalPath -Path $devFilePath
        if ($null -eq $devFileCanonical) {
            Add-ConditionalProductionIssue -Name 'Dev file local root' -Detail "$devFileRootSource path missing; create and verify PHP-FPM permissions before upload writes"
        } else {
            Add-Ok 'Dev file local root' "$devFileRootSource exists"
        }

        $publicWebRoot = Resolve-ConfiguredProjectPath -Path (& $getStorageValue 'expected.public.webroot')
        $publicWebCanonical = Resolve-CanonicalPath -Path $publicWebRoot
        if ($null -ne $devFileCanonical -and $null -ne $publicWebCanonical) {
            $publicPrefix = $publicWebCanonical.TrimEnd('\', '/') + [System.IO.Path]::DirectorySeparatorChar
            if ([string]::Equals($devFileCanonical, $publicWebCanonical, [System.StringComparison]::OrdinalIgnoreCase) -or $devFileCanonical.StartsWith($publicPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
                Add-ConditionalProductionIssue -Name 'Dev file local root exposure' -Detail "$devFileRootSource resolves under public web root"
            } else {
                Add-Ok 'Dev file local root exposure' 'not under public web root'
            }
        }
    }
} elseif ($CheckStoragePolicy -or $Production) {
    Add-ConditionalProductionIssue -Name 'File storage policy' -Detail "$PhpBinary not found; cannot read config/filesystem.php"
}

if ($CheckProviderPolicy -or $Production) {
    $providerDocuments = @(
        'docs/tasks/upload-provider-deferred-plan.md',
        'docs/api/dev-email-sms-readonly-compat.md',
        'docs/api/dev-file-readonly-compat.md',
        'docs/api/user-center-readonly-compat.md'
    )
    foreach ($path in $providerDocuments) {
        if (Test-Path -LiteralPath $path -PathType Leaf) {
            Add-Ok "Provider deferred document $path" 'present'
        } else {
            Add-ConditionalProductionIssue -Name "Provider deferred document $path" -Detail 'missing provider/deferred boundary documentation'
        }
    }

    $providerSourceSignals = @(
        @{
            Name = 'Email send deferred wrappers'
            Path = 'app/controller/dev/EmailController.php'
            Patterns = @(
                'sendLocalTxt',
                'sendLocalHtml',
                'sendAliyunTxt',
                'sendAliyunHtml',
                'sendAliyunTmp',
                'sendTencentTxt',
                'sendTencentHtml',
                'sendTencentTmp',
                'deferredWrite'
            )
        },
        @{
            Name = 'SMS send deferred wrappers'
            Path = 'app/controller/dev/SmsController.php'
            Patterns = @('sendAliyun', 'sendTencent', 'sendXiaonuo', 'deferredSend')
        },
        @{
            Name = 'Auth phone/WebPush deferred wrappers'
            Path = 'app/controller/auth/AuthController.php'
            Patterns = @('getPhoneValidCode', 'subscription', 'ApiResponse::fail')
        },
        @{
            Name = 'Password recovery provider deferred wrappers'
            Path = 'app/controller/sys/UserCenterController.php'
            Patterns = @('findPasswordGetPhoneValidCode', 'findPasswordGetEmailValidCode', 'findPasswordByPhone', 'findPasswordByEmail', 'ApiResponse::fail')
        },
        @{
            Name = 'Third-party OAuth deferred wrappers'
            Path = 'app/controller/auth/ThirdController.php'
            Patterns = @('render', 'callback', 'ApiResponse::fail')
        },
        @{
            Name = 'Cloud file upload unsupported guard'
            Path = 'app/service/dev/FileService.php'
            Patterns = @('SNOWY_SYS_DEFAULT_FILE_ENGINE', 'unsupported file engine', 'ENGINE_LOCAL')
        }
    )

    foreach ($signal in $providerSourceSignals) {
        $path = [string]$signal.Path
        if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
            Add-ConditionalProductionIssue -Name ([string]$signal.Name) -Detail "$path missing"
            continue
        }

        $text = Get-Content -LiteralPath $path -Raw
        $missingPatterns = @()
        foreach ($pattern in @($signal.Patterns)) {
            if (-not $text.Contains([string]$pattern)) {
                $missingPatterns += [string]$pattern
            }
        }

        if ($missingPatterns.Count -eq 0) {
            Add-Ok ([string]$signal.Name) "$path contains deferred/unsupported signals"
        } else {
            Add-ConditionalProductionIssue -Name ([string]$signal.Name) -Detail "$path missing signal(s): $($missingPatterns -join ', ')"
        }
    }

    if (Test-Path -LiteralPath 'route/app.php' -PathType Leaf) {
        $routeText = Get-Content -LiteralPath 'route/app.php' -Raw
        $routeSignals = @(
            'getPhoneValidCode',
            'subscription',
            'findPasswordGetPhoneValidCode',
            'findPasswordGetEmailValidCode',
            'findPasswordByPhone',
            'findPasswordByEmail',
            'auth/third',
            'uploadAliyunReturnId',
            'uploadTencentReturnId',
            'uploadMinioReturnId',
            'sendLocalTxt',
            'sendAliyunTxt',
            'sendTencentTmp',
            'sendAliyun',
            'sendTencent',
            'sendXiaonuo'
        )
        $missingRouteSignals = @()
        foreach ($signal in $routeSignals) {
            if (-not $routeText.Contains($signal)) {
                $missingRouteSignals += $signal
            }
        }

        if ($missingRouteSignals.Count -eq 0) {
            Add-Ok 'Provider deferred routes' 'auth, cloud upload, email, and SMS deferred routes are registered'
        } else {
            Add-ConditionalProductionIssue -Name 'Provider deferred routes' -Detail "missing route signal(s): $($missingRouteSignals -join ', ')"
        }
    } else {
        Add-ConditionalProductionIssue -Name 'Provider deferred routes' -Detail 'route/app.php missing'
    }

    if (Test-Path -LiteralPath 'composer.json' -PathType Leaf) {
        $composerText = (Get-Content -LiteralPath 'composer.json' -Raw).ToLowerInvariant()
        $providerPackageSignals = @()
        foreach ($package in @('aliyun', 'alibabacloud', 'tencentcloud', 'qcloud', 'aws/aws-sdk-php', 'minio', 'phpmailer', 'swiftmailer', 'symfony/mailer', 'minishlink/web-push')) {
            if ($composerText.Contains($package)) {
                $providerPackageSignals += $package
            }
        }

        if ($providerPackageSignals.Count -eq 0) {
            Add-Ok 'Provider SDK dependencies' 'no known mail/SMS/cloud/WebPush SDK package signals in composer.json'
        } else {
            Add-ConditionalProductionIssue -Name 'Provider SDK dependencies' -Detail "$($providerPackageSignals -join ', ') present; require explicit provider enablement plan before production"
        }
    } else {
        Add-ConditionalProductionIssue -Name 'Provider SDK dependencies' -Detail 'composer.json missing'
    }

    if ((Test-Command $PhpBinary) -and (Test-Path -LiteralPath 'vendor/autoload.php' -PathType Leaf)) {
        $providerConfigCode = @'
require getcwd() . '/vendor/autoload.php';
$app = new think\App(getcwd());
$app->initialize();
$value = think\facade\Db::name('dev_config')
    ->where('CONFIG_KEY', 'SNOWY_SYS_DEFAULT_FILE_ENGINE')
    ->where(function ($query): void {
        $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
    })
    ->value('CONFIG_VALUE');
echo 'default_file_engine=' . strtoupper(trim((string)$value)) . PHP_EOL;
'@
        $providerConfigCheck = Invoke-External -FilePath $PhpBinary -Arguments @('-r', $providerConfigCode)
        $defaultFileEngine = ''
        if ($providerConfigCheck.ExitCode -eq 0) {
            foreach ($line in @($providerConfigCheck.Output)) {
                $text = ([string]$line).Trim()
                if ($text -match '^default_file_engine=(.*)$') {
                    $defaultFileEngine = $Matches[1].Trim()
                }
            }
        }

        if ($providerConfigCheck.ExitCode -ne 0) {
            Add-ConditionalProductionIssue -Name 'Default file engine provider policy' -Detail 'unable to read dev_config SNOWY_SYS_DEFAULT_FILE_ENGINE'
        } elseif ([string]::IsNullOrWhiteSpace($defaultFileEngine) -or $defaultFileEngine -eq 'LOCAL') {
            Add-Ok 'Default file engine provider policy' 'LOCAL'
        } else {
            Add-ConditionalProductionIssue -Name 'Default file engine provider policy' -Detail "$defaultFileEngine configured; cloud provider storage remains deferred"
        }
    } else {
        Add-ConditionalProductionIssue -Name 'Default file engine provider policy' -Detail "$PhpBinary or vendor/autoload.php unavailable; cannot confirm dynamic upload default engine"
    }
}

if ($CheckDatabaseSchema -or $Production) {
    $databaseColumnTables = @(
        'sys_user',
        'sys_role',
        'sys_resource',
        'sys_relation',
        'sys_org',
        'sys_position',
        'tenants',
        'dev_config',
        'dev_file',
        'dev_email',
        'dev_sms',
        'act_ru_task',
        'act_hi_procinst',
        'biz_sale_project',
        'biz_sale_project_product_item',
        'biz_sale_project_invoice',
        'biz_sale_project_invoice_item',
        'biz_sale_project_invoicing',
        'biz_sale_project_reissue_order',
        'return_order',
        'return_order_item',
        'delivery_record',
        'inventory',
        'settlement_account',
        'settlement_account_statement',
        'biz_payment_record',
        'biz_expenditure_record',
        'biz_purchase_order',
        'biz_purchase_order_item',
        'biz_collection_receipt',
        'biz_debit_note',
        'biz_payroll',
        'biz_leave_application',
        'biz_file_relation',
        'customer',
        'supplier',
        'warehouses',
        'biz_product'
    )

    if ((Test-Command $PhpBinary) -and (Test-Path -LiteralPath 'vendor/autoload.php' -PathType Leaf)) {
        $databaseSchemaCode = @'
require getcwd() . '/vendor/autoload.php';
$app = new think\App(getcwd());
$app->initialize();
think\facade\Db::query('SELECT 1');

$requiredTables = [
    'sys_user', 'sys_role', 'sys_resource', 'sys_relation', 'sys_org', 'sys_position', 'sys_user_process_config',
    'tenants', 'auth_third_user', 'client_user', 'client_relation', 'mobile_resource',
    'dev_config', 'dev_dict', 'dev_email', 'dev_file', 'dev_job', 'dev_log', 'dev_message', 'dev_relation', 'dev_sms',
    'gen_basic', 'gen_config',
    'act_ru_execution', 'act_ru_task', 'act_ru_variable', 'act_hi_procinst', 'act_hi_taskinst', 'act_hi_actinst',
    'act_hi_varinst', 'act_re_procdef', 'act_re_deployment',
    'biz_sale_project', 'biz_sale_project_product_item', 'biz_sale_project_invoice', 'biz_sale_project_invoice_item',
    'biz_sale_project_invoicing', 'biz_sale_project_reissue_order', 'return_order', 'return_order_item',
    'delivery_record', 'inventory', 'settlement_account', 'settlement_account_statement', 'biz_payment_record',
    'biz_expenditure_record', 'biz_purchase_order', 'biz_purchase_order_item', 'biz_collection_receipt',
    'biz_debit_note', 'biz_payroll', 'biz_leave_application', 'biz_file_relation', 'customer', 'supplier',
    'warehouses', 'biz_product',
];

$columnRequirements = [
    'sys_user' => ['ID', 'ACCOUNT', 'PASSWORD', 'DELETE_FLAG', 'TENANT_ID'],
    'sys_role' => ['ID', 'CODE', 'DELETE_FLAG', 'TENANT_ID'],
    'sys_resource' => ['ID', 'CATEGORY', 'MODULE', 'MENU_TYPE', 'PATH'],
    'sys_relation' => ['ID', 'OBJECT_ID', 'TARGET_ID', 'CATEGORY'],
    'sys_org' => ['ID', 'PARENT_ID', 'NAME', 'DELETE_FLAG', 'TENANT_ID'],
    'sys_position' => ['ID', 'ORG_ID', 'NAME', 'DELETE_FLAG', 'TENANT_ID'],
    'tenants' => ['Tenant_ID', 'Tenant_Name', 'DELETE_FLAG'],
    'dev_config' => ['ID', 'CONFIG_KEY', 'CONFIG_VALUE', 'DELETE_FLAG'],
    'dev_file' => ['ID', 'ENGINE', 'STORAGE_PATH', 'DOWNLOAD_PATH', 'DELETE_FLAG', 'TENANT_ID'],
    'dev_email' => ['ID', 'ENGINE', 'SUBJECT', 'DELETE_FLAG', 'TENANT_ID'],
    'dev_sms' => ['ID', 'ENGINE', 'PHONE_NUMBERS', 'DELETE_FLAG', 'TENANT_ID'],
    'act_ru_task' => ['ID_', 'PROC_INST_ID_', 'TASK_DEF_KEY_', 'ASSIGNEE_'],
    'act_hi_procinst' => ['ID_', 'PROC_INST_ID_', 'PROC_DEF_KEY_', 'END_TIME_'],
    'biz_sale_project' => ['ID', 'DELETE_FLAG', 'TENANT_ID', 'VERSION', 'PROCESS_ID', 'ACCOUNT_ID'],
    'biz_sale_project_product_item' => ['ID', 'PROJECT_ID', 'PRODUCT_ID', 'CATEGORY', 'STATE', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_sale_project_invoice' => ['ID', 'PROJECT_ID', 'PROCESS_ID', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_sale_project_invoice_item' => ['ID', 'INVOICE_ID', 'PROJECT_PRODUCT_ITEM_ID', 'WAREHOUSES_ID', 'AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_sale_project_invoicing' => ['ID', 'PROJECT_ID', 'PROCESS_ID', 'AMOUNT', 'INVOICING_STATE', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_sale_project_reissue_order' => ['ID', 'PROJECT_ID', 'PROCESS_ID', 'DELETE_FLAG', 'TENANT_ID'],
    'return_order' => ['ID', 'PROJECT_ID', 'PROCESS_ID', 'DELETE_FLAG', 'TENANT_ID'],
    'return_order_item' => ['ID', 'RETURN_ORDER_ID', 'PROJECT_PRODUCT_ITEM_ID', 'AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'delivery_record' => ['ID', 'OBJECT_ID', 'PROCESS_ID', 'PRODUCT_ID', 'WAREHOUSES_ID', 'CATEGORY', 'DELETE_FLAG', 'TENANT_ID'],
    'inventory' => ['ID', 'PRODUCT_ID', 'WAREHOUSES_ID', 'CURRENT_COUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'settlement_account' => ['ID', 'ACCOUNT_NAME', 'ACCOUNT_NUMBER', 'CURRENT_AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'settlement_account_statement' => ['ID', 'ACCOUNT_ID', 'PROCESS_ID', 'SETTLEMENT_CATEGORY', 'AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_payment_record' => ['ID', 'OBJECT_ID', 'PROCESS_ID', 'SETTLEMENT_CATEGORY', 'AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_expenditure_record' => ['ID', 'OBJECT_ID', 'PROCESS_ID', 'SETTLEMENT_CATEGORY', 'AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_purchase_order' => ['ID', 'SUPPLIER_ID', 'INSTANCE_ID', 'AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_purchase_order_item' => ['ID', 'PURCHASE_ORDER_ID', 'PRODUCT_ID', 'AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_collection_receipt' => ['ID', 'PAYMENT_RECORD_ID', 'AMOUNT', 'SETTLEMENT_AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_debit_note' => ['ID', 'EXPENDITURE_RECORD_ID', 'AMOUNT', 'SETTLEMENT_AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_payroll' => ['ID', 'USER', 'ORG', 'PAYABLE_AMOUNT', 'ACTUAL_AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_leave_application' => ['ID', 'USER_ID', 'PROCESS_ID', 'AMOUNT', 'START_TIME', 'END_TIME', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_file_relation' => ['ID', 'OBJECT_ID', 'TARGET_ID', 'CATEGORY', 'FILE_NAME', 'DELETE_FLAG', 'TENANT_ID'],
    'customer' => ['ID', 'NAME', 'PHONE', 'DELETE_FLAG', 'TENANT_ID'],
    'supplier' => ['ID', 'NAME', 'PHONE', 'DELETE_FLAG', 'TENANT_ID'],
    'warehouses' => ['ID', 'NAME', 'CODE', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_product' => ['ID', 'PRODUCT_NAME', 'PRODUCT_CATEGORY', 'DELETE_FLAG', 'TENANT_ID'],
];

$tableRows = think\facade\Db::query('SHOW TABLES');
$tables = [];
foreach ($tableRows as $row) {
    $values = array_values($row);
    if (isset($values[0])) {
        $tables[] = (string)$values[0];
    }
}

$tableLookup = [];
foreach ($tables as $table) {
    $tableLookup[strtolower($table)] = true;
}

$missingTables = [];
foreach ($requiredTables as $table) {
    if (!isset($tableLookup[strtolower($table)])) {
        $missingTables[] = $table;
    }
}

$missingColumns = [];
foreach ($columnRequirements as $table => $requiredColumns) {
    if (!isset($tableLookup[strtolower($table)])) {
        $missingColumns[$table] = ['table_missing'];
        continue;
    }

    $escapedTable = str_replace('`', '``', $table);
    $columnRows = think\facade\Db::query('SHOW COLUMNS FROM `' . $escapedTable . '`');
    $columnLookup = [];
    foreach ($columnRows as $row) {
        if (isset($row['Field'])) {
            $columnLookup[strtoupper((string)$row['Field'])] = true;
        }
    }

    foreach ($requiredColumns as $column) {
        if (!isset($columnLookup[strtoupper($column)])) {
            $missingColumns[$table][] = $column;
        }
    }
}

echo 'db.connected=yes' . PHP_EOL;
echo 'db.table_count=' . count($tables) . PHP_EOL;
echo 'db.required_table_count=' . count($requiredTables) . PHP_EOL;
echo 'db.missing_tables=' . (count($missingTables) === 0 ? 'none' : implode(',', $missingTables)) . PHP_EOL;
foreach (array_keys($columnRequirements) as $table) {
    echo 'db.column.' . $table . '.missing=' . (isset($missingColumns[$table]) ? implode(',', $missingColumns[$table]) : 'none') . PHP_EOL;
}
'@
        $databaseSchemaCheck = Invoke-External -FilePath $PhpBinary -Arguments @('-r', $databaseSchemaCode)
        $databaseSchemaValues = @{}
        if ($databaseSchemaCheck.ExitCode -eq 0) {
            foreach ($line in @($databaseSchemaCheck.Output)) {
                $text = ([string]$line).Trim()
                if ($text -match '^([^=]+)=(.*)$') {
                    $databaseSchemaValues[$Matches[1].Trim()] = $Matches[2].Trim()
                }
            }
        }

        if ($databaseSchemaCheck.ExitCode -ne 0) {
            Add-ConditionalProductionIssue -Name 'Database schema probe' -Detail 'unable to boot ThinkPHP and inspect schema with read-only queries'
        } else {
            if ($databaseSchemaValues.ContainsKey('db.connected') -and $databaseSchemaValues['db.connected'] -eq 'yes') {
                Add-Ok 'Database connection' 'SELECT 1 succeeded'
            } else {
                Add-ConditionalProductionIssue -Name 'Database connection' -Detail 'schema probe did not confirm SELECT 1'
            }

            $tableCountText = if ($databaseSchemaValues.ContainsKey('db.table_count')) { [string]$databaseSchemaValues['db.table_count'] } else { '' }
            $tableCount = 0
            $tableCountValid = [int]::TryParse($tableCountText, [ref]$tableCount)
            if ($tableCountValid -and $tableCount -ge 100) {
                Add-Ok 'Database table count' "$tableCount tables"
            } else {
                Add-ConditionalProductionIssue -Name 'Database table count' -Detail "$tableCountText tables reported; expected at least 100"
            }

            $missingTables = if ($databaseSchemaValues.ContainsKey('db.missing_tables')) { [string]$databaseSchemaValues['db.missing_tables'] } else { '' }
            if ($missingTables -eq 'none') {
                $requiredTableCount = if ($databaseSchemaValues.ContainsKey('db.required_table_count')) { [string]$databaseSchemaValues['db.required_table_count'] } else { 'curated' }
                Add-Ok 'Database required tables' "$requiredTableCount curated tables present"
            } else {
                Add-ConditionalProductionIssue -Name 'Database required tables' -Detail "missing table(s): $missingTables"
            }

            $missingColumnGroups = 0
            foreach ($table in $databaseColumnTables) {
                $key = "db.column.$table.missing"
                $missingColumns = if ($databaseSchemaValues.ContainsKey($key)) { [string]$databaseSchemaValues[$key] } else { '' }
                if ([string]::IsNullOrWhiteSpace($missingColumns)) {
                    $missingColumnGroups++
                    Add-ConditionalProductionIssue -Name "Database required columns $table" -Detail 'schema probe did not report column result'
                } elseif ($missingColumns -ne 'none') {
                    $missingColumnGroups++
                    Add-ConditionalProductionIssue -Name "Database required columns $table" -Detail "missing column(s): $missingColumns"
                }
            }

            if ($missingColumnGroups -eq 0) {
                Add-Ok 'Database required columns' "$($databaseColumnTables.Count) table column groups checked"
            }
        }
    } else {
        Add-ConditionalProductionIssue -Name 'Database schema probe' -Detail "$PhpBinary or vendor/autoload.php unavailable; cannot inspect schema"
    }
}

if ($CheckArtifactPolicy -or $Production) {
    function Format-ArtifactPolicyMatches {
        param([string[]]$Matches)

        $sample = @($Matches | Select-Object -First 5)
        $suffix = if ($Matches.Count -gt $sample.Count) { ', ...' } else { '' }
        return "$($Matches.Count) match(es): $($sample -join ', ')$suffix"
    }

    $sourceMetadataMatches = @()
    foreach ($path in @('.git', '.codex')) {
        if (Test-Path -LiteralPath $path) {
            $sourceMetadataMatches += $path
        }
    }

    if ($sourceMetadataMatches.Count -eq 0) {
        Add-Ok 'Deployment source metadata artifacts' 'no .git/.codex directories in release root'
    } else {
        Add-ConditionalProductionIssue -Name 'Deployment source metadata artifacts' -Detail (Format-ArtifactPolicyMatches -Matches $sourceMetadataMatches)
    }

    $dependencyArtifactMatches = @()
    foreach ($path in @('node_modules', 'snowy-admin-web/node_modules')) {
        if (Test-Path -LiteralPath $path) {
            $dependencyArtifactMatches += $path
        }
    }

    if ($dependencyArtifactMatches.Count -eq 0) {
        Add-Ok 'Deployment dependency build artifacts' 'no frontend node_modules directories in release root'
    } else {
        Add-ConditionalProductionIssue -Name 'Deployment dependency build artifacts' -Detail (Format-ArtifactPolicyMatches -Matches $dependencyArtifactMatches)
    }

    $runtimeArtifactMatches = @()
    if (Test-Path -LiteralPath 'runtime' -PathType Container) {
        foreach ($pattern in @(
            'codex-*',
            '*.png',
            '*import*.sql',
            'probe-*.php',
            'route_list.php',
            '*-82*.log',
            'vite-*.log',
            'frontend-*.log',
            'think-run*.log',
            'mysql-import*.log'
        )) {
            $matches = Get-ChildItem -LiteralPath 'runtime' -Filter $pattern -Force -ErrorAction SilentlyContinue
            foreach ($match in @($matches)) {
                $runtimeArtifactMatches += ('runtime/' + $match.Name)
            }
        }
    }

    $runtimeArtifactMatches = @($runtimeArtifactMatches | Sort-Object -Unique)
    if ($runtimeArtifactMatches.Count -eq 0) {
        Add-Ok 'Deployment runtime local artifacts' 'no known local smoke/import/build artifacts in runtime root'
    } else {
        Add-ConditionalProductionIssue -Name 'Deployment runtime local artifacts' -Detail (Format-ArtifactPolicyMatches -Matches $runtimeArtifactMatches)
    }
}

if ($CheckFrontendBuildPolicy -or $Production) {
    function Format-FrontendBuildPolicyMatches {
        param([string[]]$Matches)

        $sample = @($Matches | Select-Object -First 5)
        $suffix = if ($Matches.Count -gt $sample.Count) { ', ...' } else { '' }
        return "$($Matches.Count) match(es): $($sample -join ', ')$suffix"
    }

    $frontendRoot = 'snowy-admin-web'
    if (-not (Test-Path -LiteralPath $frontendRoot -PathType Container)) {
        Add-ConditionalProductionIssue -Name 'Frontend build root' -Detail "$frontendRoot missing"
    } else {
        Add-Ok 'Frontend build root' $frontendRoot

        $frontendPackagePath = Join-Path $frontendRoot 'package.json'
        if (Test-Path -LiteralPath $frontendPackagePath -PathType Leaf) {
            try {
                $frontendPackage = Get-Content -LiteralPath $frontendPackagePath -Raw | ConvertFrom-Json
                $buildScript = [string]$frontendPackage.scripts.build
                if ($buildScript.Contains('vite build') -and $buildScript.Contains('--mode production')) {
                    Add-Ok 'Frontend production build script' $buildScript
                } else {
                    Add-ConditionalProductionIssue -Name 'Frontend production build script' -Detail 'package.json scripts.build should run vite build --mode production'
                }
            } catch {
                Add-ConditionalProductionIssue -Name 'Frontend package.json' -Detail 'unable to parse package.json'
            }
        } else {
            Add-ConditionalProductionIssue -Name 'Frontend package.json' -Detail "$frontendPackagePath missing"
        }

        $lockfiles = @()
        foreach ($path in @('package-lock.json', 'pnpm-lock.yaml', 'yarn.lock')) {
            $fullPath = Join-Path $frontendRoot $path
            if (Test-Path -LiteralPath $fullPath -PathType Leaf) {
                $lockfiles += $path
            }
        }

        if ($lockfiles.Count -eq 1 -and $lockfiles[0] -eq 'package-lock.json') {
            Add-Ok 'Frontend package lock policy' 'package-lock.json'
        } elseif ($lockfiles.Count -eq 0) {
            Add-ConditionalProductionIssue -Name 'Frontend package lock policy' -Detail 'no npm/pnpm/yarn lockfile found'
        } else {
            Add-ConditionalProductionIssue -Name 'Frontend package lock policy' -Detail "unexpected or mixed lockfiles: $($lockfiles -join ', ')"
        }

        $frontendProductionEnvPath = Join-Path $frontendRoot '.env.production'
        if (Test-Path -LiteralPath $frontendProductionEnvPath -PathType Leaf) {
            $frontendProductionEnv = Read-DotEnv -Path $frontendProductionEnvPath
            $requiredFrontendEnvKeys = @('NODE_ENV', 'VITE_API_BASEURL', 'VITE_API_PREFIX', 'VITE_SET_DRAWER')
            foreach ($key in $requiredFrontendEnvKeys) {
                if ($frontendProductionEnv.ContainsKey($key) -and -not [string]::IsNullOrWhiteSpace([string]$frontendProductionEnv[$key])) {
                    Add-Ok "Frontend .env.production key $key" 'set'
                } else {
                    Add-ConditionalProductionIssue -Name "Frontend .env.production key $key" -Detail 'missing or empty'
                }
            }

            $frontendPublicKey = if ($frontendProductionEnv.ContainsKey('VITE_PUBLIC_KEY')) { [string]$frontendProductionEnv['VITE_PUBLIC_KEY'] } else { '' }
            if (-not [string]::IsNullOrWhiteSpace($frontendPublicKey)) {
                Add-Ok 'Frontend .env.production key VITE_PUBLIC_KEY' 'set'
            } else {
                Add-Warn 'Frontend .env.production key VITE_PUBLIC_KEY' 'empty; password transport falls back to HTTPS plaintext unless AUTH_SM2_PRIVATE_KEY is configured'
            }

            $nodeEnv = if ($frontendProductionEnv.ContainsKey('NODE_ENV')) { ([string]$frontendProductionEnv['NODE_ENV']).Trim().ToLowerInvariant() } else { '' }
            if ($nodeEnv -eq 'production') {
                Add-Ok 'Frontend NODE_ENV' 'production'
            } else {
                Add-ConditionalProductionIssue -Name 'Frontend NODE_ENV' -Detail "$nodeEnv; expected production"
            }

            $apiValues = @{}
            foreach ($key in @('VITE_API_BASEURL', 'VITE_API_PREFIX')) {
                $apiValues[$key] = if ($frontendProductionEnv.ContainsKey($key)) { ([string]$frontendProductionEnv[$key]).Trim() } else { '' }
            }

            foreach ($entry in $apiValues.GetEnumerator()) {
                $value = [string]$entry.Value
                if ([string]::IsNullOrWhiteSpace($value)) {
                    Add-ConditionalProductionIssue -Name "Frontend $($entry.Key)" -Detail 'empty production API setting'
                } elseif ($value -match '^(?i:http://(?:localhost|127\.0\.0\.1|0\.0\.0\.0)(?::\d+)?(?:/|$))') {
                    Add-ConditionalProductionIssue -Name "Frontend $($entry.Key)" -Detail 'points to local HTTP host'
                } elseif ($value -match '^(?i:http://)') {
                    Add-ConditionalProductionIssue -Name "Frontend $($entry.Key)" -Detail 'uses non-HTTPS absolute URL'
                } elseif ($value.StartsWith('/') -or $value -match '^(?i:https://)') {
                    Add-Ok "Frontend $($entry.Key)" 'production-safe URL shape'
                } else {
                    Add-ConditionalProductionIssue -Name "Frontend $($entry.Key)" -Detail 'not a root-relative path or HTTPS URL'
                }
            }

            $setDrawer = if ($frontendProductionEnv.ContainsKey('VITE_SET_DRAWER')) { ([string]$frontendProductionEnv['VITE_SET_DRAWER']).Trim().ToLowerInvariant() } else { '' }
            if ($setDrawer -eq 'false') {
                Add-Ok 'Frontend production drawer switch' 'false'
            } else {
                Add-ConditionalProductionIssue -Name 'Frontend production drawer switch' -Detail "$setDrawer; expected false"
            }
        } else {
            Add-ConditionalProductionIssue -Name 'Frontend .env.production' -Detail "$frontendProductionEnvPath missing"
        }

        $viteConfigPath = Join-Path $frontendRoot 'vite.config.mjs'
        if (Test-Path -LiteralPath $viteConfigPath -PathType Leaf) {
            $viteConfigText = Get-Content -LiteralPath $viteConfigPath -Raw
            if ($viteConfigText.Contains('build:')) {
                Add-Ok 'Frontend Vite build config' 'build block present'
            } else {
                Add-ConditionalProductionIssue -Name 'Frontend Vite build config' -Detail 'build block missing'
            }

            if ($viteConfigText.Contains('manifest: true')) {
                Add-Ok 'Frontend Vite manifest' 'enabled'
            } else {
                Add-ConditionalProductionIssue -Name 'Frontend Vite manifest' -Detail 'manifest output is not enabled'
            }

            if ($viteConfigText.Contains('sourcemap: false')) {
                Add-Ok 'Frontend Vite sourcemap policy' 'disabled'
            } else {
                Add-ConditionalProductionIssue -Name 'Frontend Vite sourcemap policy' -Detail 'production sourcemaps should be disabled unless explicitly approved'
            }

            if ($viteConfigText.Contains('vite-plugin-compression') -or $viteConfigText.Contains('viteCompression')) {
                Add-Ok 'Frontend Vite compression plugin' 'configured'
            } else {
                Add-Warn 'Frontend Vite compression plugin' 'not configured'
            }
        } else {
            Add-ConditionalProductionIssue -Name 'Frontend Vite config' -Detail "$viteConfigPath missing"
        }

        $frontendDistPath = Join-Path $frontendRoot 'dist'
        if (Test-Path -LiteralPath $frontendDistPath -PathType Container) {
            Add-Ok 'Frontend dist directory' 'present'

            foreach ($path in @('index.html', 'assets')) {
                $fullPath = Join-Path $frontendDistPath $path
                if (Test-Path -LiteralPath $fullPath) {
                    Add-Ok "Frontend dist $path" 'present'
                } else {
                    Add-ConditionalProductionIssue -Name "Frontend dist $path" -Detail 'missing from production build output'
                }
            }

            if ((Test-Path -LiteralPath (Join-Path $frontendDistPath 'manifest.json') -PathType Leaf) -or (Test-Path -LiteralPath (Join-Path $frontendDistPath '.vite/manifest.json') -PathType Leaf)) {
                Add-Ok 'Frontend dist manifest' 'present'
            } else {
                Add-ConditionalProductionIssue -Name 'Frontend dist manifest' -Detail 'missing from production build output'
            }

            $distSensitiveMatches = @()
            foreach ($path in @('.env', '.env.production', 'package.json', 'package-lock.json', 'src', 'node_modules', 'vite.config.mjs', '.git')) {
                if (Test-Path -LiteralPath (Join-Path $frontendDistPath $path)) {
                    $distSensitiveMatches += "dist/$path"
                }
            }

            if ($distSensitiveMatches.Count -eq 0) {
                Add-Ok 'Frontend dist sensitive source exposure' 'no source/config/dependency entries in dist root'
            } else {
                Add-ConditionalProductionIssue -Name 'Frontend dist sensitive source exposure' -Detail (Format-FrontendBuildPolicyMatches -Matches $distSensitiveMatches)
            }
        } else {
            Add-ConditionalProductionIssue -Name 'Frontend dist directory' -Detail 'missing; run approved frontend production build before release packaging'
        }

        $frontendTemporaryBuildMatches = @()
        foreach ($pattern in @('vite.config.mjs.timestamp-*', 'stats.html')) {
            $matches = Get-ChildItem -LiteralPath $frontendRoot -Filter $pattern -Force -ErrorAction SilentlyContinue
            foreach ($match in @($matches)) {
                $frontendTemporaryBuildMatches += ("$frontendRoot/" + $match.Name)
            }
        }

        $frontendTemporaryBuildMatches = @($frontendTemporaryBuildMatches | Sort-Object -Unique)
        if ($frontendTemporaryBuildMatches.Count -eq 0) {
            Add-Ok 'Frontend temporary build artifacts' 'no Vite timestamp or visualizer files in frontend root'
        } else {
            Add-ConditionalProductionIssue -Name 'Frontend temporary build artifacts' -Detail (Format-FrontendBuildPolicyMatches -Matches $frontendTemporaryBuildMatches)
        }
    }
}

if ($CheckComposerPolicy -or $Production) {
    function Read-ComposerJsonFile {
        param([string]$Path)

        Add-Type -AssemblyName System.Web.Extensions
        $serializer = New-Object System.Web.Script.Serialization.JavaScriptSerializer
        $serializer.MaxJsonLength = [int]::MaxValue
        $serializer.RecursionLimit = 100
        return $serializer.DeserializeObject((Get-Content -LiteralPath $Path -Raw))
    }

    function Test-ComposerJsonProperty {
        param(
            [object]$Object,
            [string]$Name
        )

        if ($Object -is [System.Collections.IDictionary]) {
            return $Object.ContainsKey($Name)
        }

        return $null -ne ($Object.PSObject.Properties | Where-Object { $_.Name -eq $Name } | Select-Object -First 1)
    }

    function Get-ComposerJsonPropertyValue {
        param(
            [object]$Object,
            [string]$Name
        )

        if ($Object -is [System.Collections.IDictionary]) {
            if ($Object.ContainsKey($Name)) {
                return $Object[$Name]
            }

            return $null
        }

        $property = $Object.PSObject.Properties | Where-Object { $_.Name -eq $Name } | Select-Object -First 1
        if ($null -eq $property) {
            return $null
        }

        return $property.Value
    }

    $composerJson = $null
    if (Test-Path -LiteralPath 'composer.json' -PathType Leaf) {
        try {
            $composerJson = Read-ComposerJsonFile -Path 'composer.json'
            Add-Ok 'Composer manifest' 'composer.json parseable'
        } catch {
            Add-ConditionalProductionIssue -Name 'Composer manifest' -Detail 'composer.json is not parseable JSON'
        }
    } else {
        Add-ConditionalProductionIssue -Name 'Composer manifest' -Detail 'composer.json missing'
    }

    if (Test-Path -LiteralPath 'composer.lock' -PathType Leaf) {
        try {
            $composerLock = Read-ComposerJsonFile -Path 'composer.lock'
            if (-not [string]::IsNullOrWhiteSpace([string](Get-ComposerJsonPropertyValue -Object $composerLock -Name 'content-hash'))) {
                Add-Ok 'Composer lock' 'composer.lock parseable with content-hash'
            } else {
                Add-ConditionalProductionIssue -Name 'Composer lock' -Detail 'content-hash missing'
            }
        } catch {
            Add-ConditionalProductionIssue -Name 'Composer lock' -Detail 'composer.lock is not parseable JSON'
        }
    } else {
        Add-ConditionalProductionIssue -Name 'Composer lock' -Detail 'composer.lock missing'
    }

    if ($null -ne $composerJson) {
        $require = Get-ComposerJsonPropertyValue -Object $composerJson -Name 'require'
        foreach ($package in @('php', 'topthink/framework', 'topthink/think-orm', 'topthink/think-filesystem')) {
            if ($null -ne $require -and (Test-ComposerJsonProperty -Object $require -Name $package)) {
                Add-Ok "Composer require $package" ([string](Get-ComposerJsonPropertyValue -Object $require -Name $package))
            } else {
                Add-ConditionalProductionIssue -Name "Composer require $package" -Detail 'missing from composer.json require'
            }
        }

        $autoload = Get-ComposerJsonPropertyValue -Object $composerJson -Name 'autoload'
        $psr4 = if ($null -ne $autoload) { Get-ComposerJsonPropertyValue -Object $autoload -Name 'psr-4' } else { $null }
        $appAutoload = if ($null -ne $psr4) { Get-ComposerJsonPropertyValue -Object $psr4 -Name 'app\' } else { $null }
        if ([string]$appAutoload -eq 'app') {
            Add-Ok 'Composer app autoload' 'app\ => app'
        } else {
            Add-ConditionalProductionIssue -Name 'Composer app autoload' -Detail 'missing app\ => app PSR-4 mapping'
        }

        $psr0 = if ($null -ne $autoload) { Get-ComposerJsonPropertyValue -Object $autoload -Name 'psr-0' } else { $null }
        $extendAutoload = if ($null -ne $psr0) { Get-ComposerJsonPropertyValue -Object $psr0 -Name '' } else { $null }
        if ([string]$extendAutoload -eq 'extend/') {
            Add-Ok 'Composer extend autoload' 'extend/ mapping present'
        } else {
            Add-Warn 'Composer extend autoload' 'extend/ PSR-0 mapping missing or changed'
        }

        $postAutoload = @()
        $scripts = Get-ComposerJsonPropertyValue -Object $composerJson -Name 'scripts'
        if ($null -ne $scripts -and (Test-ComposerJsonProperty -Object $scripts -Name 'post-autoload-dump')) {
            $postAutoload = @((Get-ComposerJsonPropertyValue -Object $scripts -Name 'post-autoload-dump'))
        }

        foreach ($scriptSignal in @('think service:discover', 'think vendor:publish')) {
            $found = $false
            foreach ($scriptLine in $postAutoload) {
                if ([string]$scriptLine -match [regex]::Escape($scriptSignal)) {
                    $found = $true
                    break
                }
            }

            if ($found) {
                Add-Ok "Composer post-autoload $scriptSignal" 'registered'
            } else {
                Add-ConditionalProductionIssue -Name "Composer post-autoload $scriptSignal" -Detail 'missing from scripts.post-autoload-dump'
            }
        }
    }

    if (Test-Path -LiteralPath 'vendor/autoload.php' -PathType Leaf) {
        Add-Ok 'Composer vendor autoload policy' 'vendor/autoload.php present'
    } else {
        Add-ConditionalProductionIssue -Name 'Composer vendor autoload policy' -Detail 'vendor/autoload.php missing; run approved composer install before release'
    }

    foreach ($path in @('vendor/composer/installed.php', 'vendor/composer/installed.json', 'vendor/composer/platform_check.php')) {
        if (Test-Path -LiteralPath $path -PathType Leaf) {
            Add-Ok "Composer vendor metadata $path" 'present'
        } else {
            Add-ConditionalProductionIssue -Name "Composer vendor metadata $path" -Detail 'missing from vendor/composer'
        }
    }

    $devDependencyMatches = @()
    foreach ($path in @('vendor/symfony/var-dumper', 'vendor/topthink/think-trace')) {
        if (Test-Path -LiteralPath $path) {
            $devDependencyMatches += $path
        }
    }

    if ($devDependencyMatches.Count -eq 0) {
        Add-Ok 'Composer dev dependency exposure' 'no known require-dev packages installed in vendor'
    } else {
        Add-ConditionalProductionIssue -Name 'Composer dev dependency exposure' -Detail "$($devDependencyMatches -join ', ') installed; production release should use composer install --no-dev --optimize-autoloader"
    }

    if (Test-Command $ComposerBinary) {
        $composerValidate = Invoke-External -FilePath $ComposerBinary -Arguments @('validate', '--no-check-publish', '--no-interaction')
        if ($composerValidate.ExitCode -eq 0) {
            Add-Ok 'Composer validate' 'composer.json and composer.lock accepted'
        } else {
            Add-ConditionalProductionIssue -Name 'Composer validate' -Detail 'composer validate failed'
        }
    } else {
        Add-Warn 'Composer validate' "$ComposerBinary not found; cannot run read-only composer validate"
    }
}

if ($CheckReleasePackagePolicy -or $Production) {
    function Format-ReleasePackagePolicyMatches {
        param([string[]]$Matches)

        if ($Matches.Count -le 8) {
            return ($Matches -join ', ')
        }

        $sample = $Matches | Select-Object -First 8
        return "$($sample -join ', ') (+$($Matches.Count - 8) more)"
    }

    function Join-ReleasePackagePath {
        param(
            [string]$Root,
            [string]$RelativePath
        )

        return Join-Path $Root ($RelativePath -replace '/', [System.IO.Path]::DirectorySeparatorChar)
    }

    function Test-ReleasePackageEntry {
        param(
            [string]$Name,
            [string]$Root,
            [string]$RelativePath,
            [string]$PathType
        )

        $fullPath = Join-ReleasePackagePath -Root $Root -RelativePath $RelativePath
        if (Test-Path -LiteralPath $fullPath -PathType $PathType) {
            Add-Ok $Name $RelativePath
        } else {
            Add-ConditionalProductionIssue -Name $Name -Detail "$RelativePath missing from release root"
        }
    }

    if ([string]::IsNullOrWhiteSpace($ReleaseRoot)) {
        Add-ConditionalProductionIssue -Name 'Release package root' -Detail 'empty ReleaseRoot'
    } else {
        $releaseRootPath = Resolve-ConfiguredProjectPath -Path $ReleaseRoot
        $releaseRootCanonical = Resolve-CanonicalPath -Path $releaseRootPath
        if ($null -eq $releaseRootCanonical) {
            Add-ConditionalProductionIssue -Name 'Release package root' -Detail "$ReleaseRoot missing"
        } else {
            Add-Ok 'Release package root' $releaseRootCanonical

            $releaseRequiredFiles = @(
                @{ Name = 'Release backend entry think'; Path = 'think' },
                @{ Name = 'Release Composer manifest'; Path = 'composer.json' },
                @{ Name = 'Release Composer lock'; Path = 'composer.lock' },
                @{ Name = 'Release Composer autoload'; Path = 'vendor/autoload.php' },
                @{ Name = 'Release Composer installed metadata'; Path = 'vendor/composer/installed.php' },
                @{ Name = 'Release public index'; Path = 'public/index.php' },
                @{ Name = 'Release public router'; Path = 'public/router.php' },
                @{ Name = 'Release public htaccess'; Path = 'public/.htaccess' },
                @{ Name = 'Release frontend index'; Path = 'snowy-admin-web/dist/index.html' }
            )

            foreach ($entry in $releaseRequiredFiles) {
                Test-ReleasePackageEntry -Name ([string]$entry.Name) -Root $releaseRootCanonical -RelativePath ([string]$entry.Path) -PathType Leaf
            }

            $releaseRequiredDirectories = @(
                @{ Name = 'Release app source'; Path = 'app' },
                @{ Name = 'Release config source'; Path = 'config' },
                @{ Name = 'Release route source'; Path = 'route' },
                @{ Name = 'Release extend source'; Path = 'extend' },
                @{ Name = 'Release vendor directory'; Path = 'vendor' },
                @{ Name = 'Release public directory'; Path = 'public' },
                @{ Name = 'Release frontend assets'; Path = 'snowy-admin-web/dist/assets' }
            )

            foreach ($entry in $releaseRequiredDirectories) {
                Test-ReleasePackageEntry -Name ([string]$entry.Name) -Root $releaseRootCanonical -RelativePath ([string]$entry.Path) -PathType Container
            }

            $manifestCandidates = @(
                'snowy-admin-web/dist/.vite/manifest.json',
                'snowy-admin-web/dist/manifest.json'
            )
            $manifestFound = $false
            foreach ($manifestPath in $manifestCandidates) {
                if (Test-Path -LiteralPath (Join-ReleasePackagePath -Root $releaseRootCanonical -RelativePath $manifestPath) -PathType Leaf) {
                    $manifestFound = $true
                    break
                }
            }

            if ($manifestFound) {
                Add-Ok 'Release frontend manifest' 'present in dist'
            } else {
                Add-ConditionalProductionIssue -Name 'Release frontend manifest' -Detail 'dist manifest missing from release root'
            }

            $excludedReleaseEntries = @()
            foreach ($path in @(
                '.env',
                '.example.env',
                '.git',
                '.codex',
                '.agents',
                '.idea',
                '.vscode',
                'snowy-admin-web/node_modules',
                'snowy-admin-web/src',
                'snowy-admin-web/.env',
                'snowy-admin-web/.env.development',
                'snowy-admin-web/.env.production',
                'snowy-admin-web/package.json',
                'snowy-admin-web/package-lock.json',
                'snowy-admin-web/pnpm-lock.yaml',
                'snowy-admin-web/yarn.lock',
                'snowy-admin-web/vite.config.mjs',
                'snowy-admin-web/stats.html'
            )) {
                if (Test-Path -LiteralPath (Join-ReleasePackagePath -Root $releaseRootCanonical -RelativePath $path)) {
                    $excludedReleaseEntries += $path
                }
            }

            if ($excludedReleaseEntries.Count -eq 0) {
                Add-Ok 'Release excluded entries' 'no source-control, secret, frontend source, or dependency build entries found'
            } else {
                Add-ConditionalProductionIssue -Name 'Release excluded entries' -Detail (Format-ReleasePackagePolicyMatches -Matches $excludedReleaseEntries)
            }

            $runtimeRoot = Join-ReleasePackagePath -Root $releaseRootCanonical -RelativePath 'runtime'
            $runtimeReleaseArtifacts = @()
            if (Test-Path -LiteralPath $runtimeRoot -PathType Container) {
                $runtimeReleaseArtifacts = Get-ChildItem -LiteralPath $runtimeRoot -Recurse -File -Force -ErrorAction SilentlyContinue |
                    ForEach-Object {
                        $relative = $_.FullName.Substring($releaseRootCanonical.Length).TrimStart('\', '/') -replace '\\', '/'
                        if ($relative -ne 'runtime/.gitignore') {
                            $relative
                        }
                    }
            }

            if ($runtimeReleaseArtifacts.Count -eq 0) {
                Add-Ok 'Release runtime artifacts' 'no runtime files found except optional placeholders'
            } else {
                Add-ConditionalProductionIssue -Name 'Release runtime artifacts' -Detail (Format-ReleasePackagePolicyMatches -Matches $runtimeReleaseArtifacts)
            }

            $publicForbiddenEntries = @()
            foreach ($path in @(
                '.env',
                '.git',
                'composer.json',
                'composer.lock',
                'vendor',
                'app',
                'config',
                'route',
                'extend',
                'docs',
                'scripts',
                'snowy-admin-web'
            )) {
                $publicPath = Join-ReleasePackagePath -Root $releaseRootCanonical -RelativePath ("public/$path")
                if (Test-Path -LiteralPath $publicPath) {
                    $publicForbiddenEntries += "public/$path"
                }
            }

            if ($publicForbiddenEntries.Count -eq 0) {
                Add-Ok 'Release public root exposure' 'no project source/config/dependency entries under public'
            } else {
                Add-ConditionalProductionIssue -Name 'Release public root exposure' -Detail (Format-ReleasePackagePolicyMatches -Matches $publicForbiddenEntries)
            }
        }
    }
}

if ($CheckCachePolicy -or $Production) {
    if ($envValues.Count -eq 0) {
        Add-ConditionalProductionIssue -Name 'Cache policy' -Detail '.env unavailable; cannot validate CACHE_DRIVER or Redis settings'
    } else {
        $cacheDriver = if ($envValues.ContainsKey('CACHE_DRIVER')) { ([string]$envValues['CACHE_DRIVER']).Trim().ToLowerInvariant() } else { 'file' }
        if ([string]::IsNullOrWhiteSpace($cacheDriver)) {
            $cacheDriver = 'file'
        }

        if (@('file', 'local') -contains $cacheDriver) {
            Add-Ok 'Cache driver' "$cacheDriver; no external cache probe needed"
        } elseif ($cacheDriver -eq 'redis') {
            Add-Ok 'Cache driver' 'redis'

            $redisHost = if ($envValues.ContainsKey('REDIS_HOST')) { ([string]$envValues['REDIS_HOST']).Trim() } else { '' }
            if ([string]::IsNullOrWhiteSpace($redisHost)) {
                Add-ConditionalProductionIssue -Name 'Redis host' -Detail 'REDIS_HOST missing or empty while CACHE_DRIVER=redis'
            } else {
                Add-Ok 'Redis host' 'set'
            }

            $redisPortText = if ($envValues.ContainsKey('REDIS_PORT')) { ([string]$envValues['REDIS_PORT']).Trim() } else { '' }
            $redisPort = 0
            $redisPortValid = [int]::TryParse($redisPortText, [ref]$redisPort) -and $redisPort -gt 0 -and $redisPort -le 65535
            if ($redisPortValid) {
                Add-Ok 'Redis port' 'valid TCP port'
            } else {
                Add-ConditionalProductionIssue -Name 'Redis port' -Detail 'REDIS_PORT missing, empty, or invalid while CACHE_DRIVER=redis'
            }

            $redisDbText = if ($envValues.ContainsKey('REDIS_DB')) { ([string]$envValues['REDIS_DB']).Trim() } else { '' }
            if ([string]::IsNullOrWhiteSpace($redisDbText)) {
                Add-Ok 'Redis database' 'default 0'
            } else {
                $redisDb = 0
                if ([int]::TryParse($redisDbText, [ref]$redisDb) -and $redisDb -ge 0) {
                    Add-Ok 'Redis database' $redisDbText
                } else {
                    Add-Warn 'Redis database' 'REDIS_DB is not a non-negative integer'
                }
            }

            $redisTimeoutText = if ($envValues.ContainsKey('REDIS_TIMEOUT')) { ([string]$envValues['REDIS_TIMEOUT']).Trim() } else { '' }
            if ([string]::IsNullOrWhiteSpace($redisTimeoutText)) {
                Add-Ok 'Redis timeout' 'default 0'
            } else {
                $redisTimeout = 0.0
                if ([double]::TryParse($redisTimeoutText, [System.Globalization.NumberStyles]::Float, [System.Globalization.CultureInfo]::InvariantCulture, [ref]$redisTimeout) -and $redisTimeout -ge 0) {
                    Add-Ok 'Redis timeout' $redisTimeoutText
                } else {
                    Add-Warn 'Redis timeout' 'REDIS_TIMEOUT is not a non-negative number'
                }
            }

            $redisPasswordSet = (
                ($envValues.ContainsKey('REDIS_PASSWD') -and -not [string]::IsNullOrWhiteSpace([string]$envValues['REDIS_PASSWD'])) -or
                ($envValues.ContainsKey('REDIS_PASSWORD') -and -not [string]::IsNullOrWhiteSpace([string]$envValues['REDIS_PASSWORD']))
            )
            if ($redisPasswordSet) {
                Add-Ok 'Redis password policy' 'password value present'
            } else {
                Add-Warn 'Redis password policy' 'password empty; verify Redis is protected by local binding, firewall, VPC, or equivalent controls'
            }

            if (-not [string]::IsNullOrWhiteSpace($redisHost) -and $redisPortValid) {
                if (Test-TcpConnection -HostName $redisHost -Port $redisPort -TimeoutSeconds $CacheTcpTimeoutSeconds) {
                    Add-Ok 'Redis TCP reachability' "${redisHost}:$redisPort reachable"
                } else {
                    Add-ConditionalProductionIssue -Name 'Redis TCP reachability' -Detail "${redisHost}:$redisPort not reachable within $CacheTcpTimeoutSeconds seconds"
                }
            }
        } else {
            Add-ConditionalProductionIssue -Name 'Cache driver' -Detail "$cacheDriver is not one of file, local, or redis; verify ThinkPHP cache store support"
        }
    }
}

if (($CheckCookiePolicy -or $Production) -and (Test-Command $PhpBinary)) {
    $cookiePolicyCode = @'
$cookie = is_file('config/cookie.php') ? require 'config/cookie.php' : array();
$session = is_file('config/session.php') ? require 'config/session.php' : array();
$keys = array(
    'cookie.secure' => $cookie['secure'] ?? '',
    'cookie.httponly' => $cookie['httponly'] ?? '',
    'cookie.samesite' => $cookie['samesite'] ?? '',
    'cookie.domain' => $cookie['domain'] ?? '',
    'cookie.path' => $cookie['path'] ?? '',
    'session.name' => $session['name'] ?? '',
    'session.type' => $session['type'] ?? '',
    'session.expire' => $session['expire'] ?? '',
);
foreach ($keys as $key => $value) {
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    }
    echo $key . '=' . $value . PHP_EOL;
}
'@
    $cookiePolicyCheck = Invoke-External -FilePath $PhpBinary -Arguments @('-r', $cookiePolicyCode)
    $cookiePolicyValues = @{}
    if ($cookiePolicyCheck.ExitCode -eq 0) {
        foreach ($line in @($cookiePolicyCheck.Output)) {
            $text = ([string]$line).Trim()
            if ($text -match '^([^=]+)=(.*)$') {
                $cookiePolicyValues[$Matches[1]] = $Matches[2]
            }
        }
    }

    if ($cookiePolicyCheck.ExitCode -ne 0 -or $cookiePolicyValues.Count -eq 0) {
        Add-ConditionalProductionIssue -Name 'Cookie/session policy' -Detail 'unable to read config/cookie.php and config/session.php'
    } else {
        $enabledValues = @('1', 'true', 'on', 'yes')

        $cookieSecure = if ($cookiePolicyValues.ContainsKey('cookie.secure')) { ([string]$cookiePolicyValues['cookie.secure']).Trim().ToLowerInvariant() } else { '' }
        if ($enabledValues -contains $cookieSecure) {
            Add-Ok 'Cookie secure flag' $cookieSecure
        } else {
            Add-ConditionalProductionIssue -Name 'Cookie secure flag' -Detail "$cookieSecure; enable secure cookies for HTTPS production"
        }

        $cookieHttpOnly = if ($cookiePolicyValues.ContainsKey('cookie.httponly')) { ([string]$cookiePolicyValues['cookie.httponly']).Trim().ToLowerInvariant() } else { '' }
        if ($enabledValues -contains $cookieHttpOnly) {
            Add-Ok 'Cookie httponly flag' $cookieHttpOnly
        } else {
            Add-ConditionalProductionIssue -Name 'Cookie httponly flag' -Detail "$cookieHttpOnly; enable HttpOnly cookies before production"
        }

        $cookieSameSite = if ($cookiePolicyValues.ContainsKey('cookie.samesite')) { ([string]$cookiePolicyValues['cookie.samesite']).Trim().ToLowerInvariant() } else { '' }
        if (@('lax', 'strict', 'none') -contains $cookieSameSite) {
            Add-Ok 'Cookie SameSite policy' $cookieSameSite
        } else {
            Add-ConditionalProductionIssue -Name 'Cookie SameSite policy' -Detail 'empty or unsupported; set lax, strict, or none before production'
        }

        $cookiePath = if ($cookiePolicyValues.ContainsKey('cookie.path')) { ([string]$cookiePolicyValues['cookie.path']).Trim() } else { '' }
        if ($cookiePath -eq '/') {
            Add-Ok 'Cookie path' $cookiePath
        } elseif ([string]::IsNullOrWhiteSpace($cookiePath)) {
            Add-Warn 'Cookie path' 'empty; verify default path before production'
        } else {
            Add-Ok 'Cookie path' $cookiePath
        }

        $sessionName = if ($cookiePolicyValues.ContainsKey('session.name')) { ([string]$cookiePolicyValues['session.name']).Trim() } else { '' }
        if ([string]::IsNullOrWhiteSpace($sessionName)) {
            Add-ConditionalProductionIssue -Name 'Session name' -Detail 'empty session cookie name'
        } elseif ($sessionName -eq 'PHPSESSID') {
            Add-Warn 'Session name' 'PHPSESSID default; consider app-specific session name before production'
        } else {
            Add-Ok 'Session name' $sessionName
        }

        $sessionType = if ($cookiePolicyValues.ContainsKey('session.type')) { ([string]$cookiePolicyValues['session.type']).Trim() } else { '' }
        if ([string]::IsNullOrWhiteSpace($sessionType)) {
            Add-Warn 'Session type' 'empty; verify framework default before production'
        } else {
            Add-Ok 'Session type' $sessionType
        }

        $sessionExpireText = if ($cookiePolicyValues.ContainsKey('session.expire')) { ([string]$cookiePolicyValues['session.expire']).Trim() } else { '' }
        $sessionExpire = 0
        if ([int]::TryParse($sessionExpireText, [ref]$sessionExpire) -and $sessionExpire -gt 0) {
            Add-Ok 'Session expire' "$sessionExpire seconds"
        } else {
            Add-Warn 'Session expire' "$sessionExpireText is not a positive integer"
        }
    }
}

if ($CheckBackupTools -or $Production) {
    if (Test-Command $MysqlDumpBinary) {
        Add-Ok 'Backup dump command' "$MysqlDumpBinary found"
    } else {
        Add-ConditionalBackupIssue -Name 'Backup dump command' -Detail "$MysqlDumpBinary not found; database backup command must be available before production writes"
    }

    if (Test-Command $MysqlClientBinary) {
        Add-Ok 'Backup restore client command' "$MysqlClientBinary found"
    } else {
        Add-ConditionalBackupIssue -Name 'Backup restore client command' -Detail "$MysqlClientBinary not found; restore verification command must be available before production writes"
    }

    if ($envValues.Count -gt 0) {
        $backupDbKeys = @('DB_TYPE', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PORT')
        foreach ($key in $backupDbKeys) {
            if ($envValues.ContainsKey($key) -and -not [string]::IsNullOrWhiteSpace([string]$envValues[$key])) {
                Add-Ok "Backup DB env key $key" 'set'
            } else {
                Add-ConditionalBackupIssue -Name "Backup DB env key $key" -Detail 'missing or empty'
            }
        }

        if ($envValues.ContainsKey('DB_TYPE')) {
            $dbType = ([string]$envValues['DB_TYPE']).Trim().ToLowerInvariant()
            if (@('mysql', 'mysqli', 'mariadb') -contains $dbType) {
                Add-Ok 'Backup DB type' $dbType
            } else {
                Add-ConditionalBackupIssue -Name 'Backup DB type' -Detail "$dbType is not a MySQL/MariaDB type; verify backup command strategy"
            }
        }
    } else {
        Add-ConditionalBackupIssue -Name 'Backup DB env keys' -Detail '.env unavailable; cannot validate backup connection inputs'
    }

    if ([string]::IsNullOrWhiteSpace($BackupDirectory)) {
        Add-ConditionalBackupIssue -Name 'Backup directory' 'empty BackupDirectory'
    } elseif (Test-Path -LiteralPath $BackupDirectory -PathType Container) {
        if ($SkipWritableProbe) {
            Add-Ok 'Backup directory' "$BackupDirectory exists; probe skipped"
        } elseif (Test-DirectoryWritable -Path $BackupDirectory) {
            Add-Ok 'Backup directory' "$BackupDirectory writable by current user"
        } else {
            Add-ConditionalBackupIssue -Name 'Backup directory' -Detail "$BackupDirectory is not writable by current user; verify backup user permissions"
        }
    } else {
        Add-ConditionalBackupIssue -Name 'Backup directory' -Detail "$BackupDirectory missing; create and protect it before production writes"
    }
}

$runtimeDirectories = @(
    'runtime',
    'runtime/log',
    'runtime/cache',
    'runtime/temp',
    'runtime/storage',
    'runtime/upload',
    'public/storage'
)

if ($CheckRuntimePermissionPolicy -or $Production) {
    $publicRootPath = Join-Path ([string]$repoRoot) 'public'

    if (Test-Path -LiteralPath $publicRootPath -PathType Container) {
        Add-Ok 'Runtime permission public root' (Resolve-ConfiguredProjectPath -Path $publicRootPath)
    } else {
        Add-ConditionalProductionIssue -Name 'Runtime permission public root' -Detail "$publicRootPath missing"
    }

    $sensitivePermissionPaths = @(
        '.env',
        'config/database.php',
        'config/cache.php',
        'config/log.php',
        'config/filesystem.php',
        'composer.json',
        'composer.lock'
    )

    foreach ($path in $sensitivePermissionPaths) {
        if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
            continue
        }

        if (Test-ConfiguredPathUnderRoot -Path $path -Root $publicRootPath) {
            Add-ConditionalProductionIssue -Name "Runtime sensitive path scope $path" -Detail 'resolves under public root'
        } else {
            Add-Ok "Runtime sensitive path scope $path" 'outside public root'
        }
    }

    foreach ($path in $runtimeDirectories) {
        if ($path -eq 'public/storage') {
            if (Test-ConfiguredPathUnderRoot -Path $path -Root $publicRootPath) {
                Add-Ok "Runtime writable path scope $path" 'public upload/download path'
            } else {
                Add-ConditionalProductionIssue -Name "Runtime writable path scope $path" -Detail 'does not resolve under public root; verify upload URL mapping'
            }
            continue
        }

        if (Test-ConfiguredPathUnderRoot -Path $path -Root $publicRootPath) {
            Add-ConditionalProductionIssue -Name "Runtime writable path scope $path" -Detail 'non-public runtime path resolves under public root'
        } else {
            Add-Ok "Runtime writable path scope $path" 'outside public root'
        }
    }

    if ([string]::IsNullOrWhiteSpace($BackupDirectory)) {
        Add-ConditionalProductionIssue -Name 'Runtime backup path scope' -Detail 'empty BackupDirectory'
    } elseif (Test-ConfiguredPathUnderRoot -Path $BackupDirectory -Root $publicRootPath) {
        Add-ConditionalProductionIssue -Name 'Runtime backup path scope' -Detail "$BackupDirectory resolves under public root"
    } else {
        Add-Ok 'Runtime backup path scope' "$BackupDirectory outside public root"
    }

    if (-not [string]::IsNullOrWhiteSpace($BackupDirectory)) {
        if (Test-Path -LiteralPath $BackupDirectory -PathType Container) {
            Add-Ok 'Runtime backup directory existence' "$BackupDirectory exists"
        } else {
            Add-ConditionalProductionIssue -Name 'Runtime backup directory existence' -Detail "$BackupDirectory missing; create and protect it before production backups"
        }
    }

    if ([System.Environment]::OSVersion.Platform -eq [System.PlatformID]::Win32NT) {
        Add-Ok 'Runtime Unix permission mode check' 'skipped on Windows host'
    } elseif (-not (Test-Command 'stat')) {
        Add-ConditionalProductionIssue -Name 'Runtime Unix permission mode check' -Detail 'stat command unavailable'
    } else {
        $modePaths = @(
            '.env',
            'config/database.php',
            'config/cache.php',
            'config/log.php',
            'config/filesystem.php'
        )

        foreach ($path in $modePaths) {
            if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
                continue
            }

            $mode = Get-UnixMode -Path $path
            if ([string]::IsNullOrWhiteSpace($mode)) {
                Add-ConditionalProductionIssue -Name "Runtime Unix mode $path" -Detail 'unable to read file mode'
                continue
            }

            if (Test-UnixModeGroupOrOtherWritable -Mode $mode) {
                Add-ConditionalProductionIssue -Name "Runtime Unix mode $path" -Detail "mode $mode allows group/other write"
            } else {
                Add-Ok "Runtime Unix mode $path" "mode $mode not group/other writable"
            }

            if ($path -eq '.env') {
                if (Test-UnixModeOtherReadable -Mode $mode) {
                    Add-ConditionalProductionIssue -Name 'Runtime Unix mode .env secrecy' -Detail "mode $mode allows other read"
                } else {
                    Add-Ok 'Runtime Unix mode .env secrecy' "mode $mode not other-readable"
                }
            }
        }
    }
}

foreach ($path in $runtimeDirectories) {
    if (-not (Test-Path -LiteralPath $path -PathType Container)) {
        if ($CreateMissingWritableDirs) {
            try {
                New-Item -ItemType Directory -Path $path -Force | Out-Null
                Add-Ok "Writable path $path" 'created'
            } catch {
                Add-Fail "Writable path $path" "missing and could not be created: $($_.Exception.Message)"
                continue
            }
        } else {
            Add-Warn "Writable path $path" 'missing; ThinkPHP or deployment script must create it with web-user permissions'
            continue
        }
    }

    if ($SkipWritableProbe) {
        Add-Ok "Writable path $path" 'exists; probe skipped'
    } elseif (Test-DirectoryWritable -Path $path) {
        Add-Ok "Writable path $path"
    } else {
        Add-Warn "Writable path $path" 'current user cannot write; verify PHP-FPM/web user permissions'
    }
}

if (-not [string]::IsNullOrWhiteSpace($ExpectedPublicRoot)) {
    $expectedResolved = Resolve-CanonicalPath -Path $ExpectedPublicRoot
    $projectPublicResolved = Resolve-CanonicalPath -Path (Join-Path ([string]$repoRoot) 'public')

    if (
        -not [string]::IsNullOrWhiteSpace($expectedResolved) -and
        -not [string]::IsNullOrWhiteSpace($projectPublicResolved) -and
        [StringComparer]::OrdinalIgnoreCase.Equals($expectedResolved, $projectPublicResolved)
    ) {
        Add-Ok 'Expected public root' $expectedResolved
    } else {
        $actual = if ([string]::IsNullOrWhiteSpace($expectedResolved)) { 'unresolved' } else { $expectedResolved }
        Add-Fail 'Expected public root' "expected $projectPublicResolved, got $actual"
    }
}

if (-not [string]::IsNullOrWhiteSpace($PublicBaseUrl)) {
    $sensitiveHttpPaths = @(
        '/.env',
        '/.example.env',
        '/composer.json',
        '/composer.lock',
        '/vendor/autoload.php',
        '/runtime',
        '/app',
        '/config',
        '/route',
        '/extend',
        '/docs',
        '/scripts',
        '/tests',
        '/PLANS.md',
        '/IMPLEMENT.md',
        '/STATUS.md'
    )

    foreach ($path in $sensitiveHttpPaths) {
        $url = Join-UrlPath -BaseUrl $PublicBaseUrl -Path $path
        $result = Get-HttpStatusCode -Url $url -TimeoutSeconds $HttpProbeTimeoutSeconds
        $statusCode = $result.StatusCode

        if ($null -eq $statusCode) {
            Add-Warn "HTTP public exposure guard $path" "probe failed: $($result.Error)"
        } elseif ($statusCode -ge 200 -and $statusCode -lt 300) {
            Add-Fail "HTTP public exposure guard $path" "returned HTTP $statusCode; sensitive project paths must not be web-readable"
        } elseif ($statusCode -ge 300 -and $statusCode -lt 400) {
            Add-Warn "HTTP public exposure guard $path" "returned HTTP $statusCode redirect; verify the redirect target is not web-readable"
        } else {
            Add-Ok "HTTP public exposure guard $path" "HTTP $statusCode"
        }
    }
}

if ($CheckSecurityHeadersPolicy -or $Production) {
    if ([string]::IsNullOrWhiteSpace($PublicBaseUrl)) {
        Add-ConditionalProductionIssue -Name 'HTTP security headers probe' -Detail 'empty PublicBaseUrl; cannot inspect response headers'
    } else {
        $securityHeaderUri = $null
        if (-not [Uri]::TryCreate($PublicBaseUrl, [UriKind]::Absolute, [ref]$securityHeaderUri) -or [string]::IsNullOrWhiteSpace($securityHeaderUri.Scheme) -or [string]::IsNullOrWhiteSpace($securityHeaderUri.Host)) {
            Add-ConditionalProductionIssue -Name 'HTTP security headers probe' -Detail 'PublicBaseUrl is not an absolute URL with scheme and host'
        } elseif (@('http', 'https') -notcontains $securityHeaderUri.Scheme.ToLowerInvariant()) {
            Add-ConditionalProductionIssue -Name 'HTTP security headers probe' -Detail "unsupported URL scheme $($securityHeaderUri.Scheme)"
        } else {
            $metadata = Get-HttpResponseMetadata -Url $PublicBaseUrl -TimeoutSeconds $HttpProbeTimeoutSeconds
            if ($null -eq $metadata.StatusCode) {
                Add-ConditionalProductionIssue -Name 'HTTP security headers probe' -Detail "probe failed: $($metadata.Error)"
            } else {
                $statusCode = [int]$metadata.StatusCode
                if ($statusCode -ge 200 -and $statusCode -lt 400) {
                    Add-Ok 'HTTP security headers probe' "HTTP $statusCode"
                } else {
                    Add-Warn 'HTTP security headers probe' "HTTP $statusCode; header policy should be verified on a normal frontend/backend entry response"
                }

                $headers = $metadata.Headers
                $getHeader = {
                    param([string]$Name)
                    $key = $Name.ToLowerInvariant()
                    if ($headers.ContainsKey($key)) {
                        return ([string]$headers[$key]).Trim()
                    }

                    return ''
                }

                $isLocalHttp = ($securityHeaderUri.Scheme.ToLowerInvariant() -eq 'http') -and (Test-LocalUrlHost -HostName $securityHeaderUri.Host)
                $isHttps = $securityHeaderUri.Scheme.ToLowerInvariant() -eq 'https'

                $hsts = & $getHeader 'Strict-Transport-Security'
                if ($isHttps -and -not (Test-LocalUrlHost -HostName $securityHeaderUri.Host)) {
                    if ([string]::IsNullOrWhiteSpace($hsts)) {
                        Add-ConditionalProductionIssue -Name 'HTTP security header Strict-Transport-Security' -Detail 'missing on HTTPS public URL'
                    } elseif ($hsts -match '(?i)\bmax-age\s*=\s*\d+') {
                        Add-Ok 'HTTP security header Strict-Transport-Security' 'max-age present'
                    } else {
                        Add-ConditionalProductionIssue -Name 'HTTP security header Strict-Transport-Security' -Detail 'missing max-age directive'
                    }
                } elseif ($isLocalHttp) {
                    Add-Ok 'HTTP security header Strict-Transport-Security' 'skipped for local HTTP smoke URL'
                } else {
                    Add-ConditionalProductionIssue -Name 'HTTP security header Strict-Transport-Security' -Detail 'requires HTTPS public URL before production'
                }

                $contentTypeOptions = (& $getHeader 'X-Content-Type-Options').ToLowerInvariant()
                if ($contentTypeOptions -eq 'nosniff') {
                    Add-Ok 'HTTP security header X-Content-Type-Options' 'nosniff'
                } else {
                    Add-ConditionalProductionIssue -Name 'HTTP security header X-Content-Type-Options' -Detail 'missing nosniff'
                }

                $xFrameOptions = (& $getHeader 'X-Frame-Options').ToLowerInvariant()
                $contentSecurityPolicy = & $getHeader 'Content-Security-Policy'
                if (@('deny', 'sameorigin') -contains $xFrameOptions) {
                    Add-Ok 'HTTP frame protection' "X-Frame-Options $xFrameOptions"
                } elseif ($contentSecurityPolicy -match '(?i)\bframe-ancestors\b') {
                    Add-Ok 'HTTP frame protection' 'CSP frame-ancestors present'
                } else {
                    Add-ConditionalProductionIssue -Name 'HTTP frame protection' -Detail 'missing X-Frame-Options deny/sameorigin or CSP frame-ancestors'
                }

                if ([string]::IsNullOrWhiteSpace($contentSecurityPolicy)) {
                    Add-ConditionalProductionIssue -Name 'HTTP security header Content-Security-Policy' -Detail 'missing'
                } else {
                    Add-Ok 'HTTP security header Content-Security-Policy' 'present'
                }

                $referrerPolicy = (& $getHeader 'Referrer-Policy').ToLowerInvariant()
                if ([string]::IsNullOrWhiteSpace($referrerPolicy)) {
                    Add-ConditionalProductionIssue -Name 'HTTP security header Referrer-Policy' -Detail 'missing'
                } elseif ($referrerPolicy -eq 'unsafe-url') {
                    Add-ConditionalProductionIssue -Name 'HTTP security header Referrer-Policy' -Detail 'unsafe-url is not release-safe'
                } else {
                    Add-Ok 'HTTP security header Referrer-Policy' $referrerPolicy
                }

                $permissionsPolicy = & $getHeader 'Permissions-Policy'
                if ([string]::IsNullOrWhiteSpace($permissionsPolicy)) {
                    Add-ConditionalProductionIssue -Name 'HTTP security header Permissions-Policy' -Detail 'missing'
                } else {
                    Add-Ok 'HTTP security header Permissions-Policy' 'present'
                }
            }
        }
    }
}

if ($CheckCorsPolicy -or $Production) {
    $corsProbeConfirmed = $false
    $frontendApiRequiresCors = $false
    $corsSourceFiles = @()
    foreach ($path in @('app', 'config', 'route', 'public')) {
        if (Test-Path -LiteralPath $path) {
            $corsSourceFiles += Get-ChildItem -LiteralPath $path -Recurse -File -ErrorAction SilentlyContinue |
                Where-Object { @('.php', '.conf') -contains $_.Extension -or $_.Name -eq '.htaccess' }
        }
    }

    $corsSourceMatches = @()
    if ($corsSourceFiles.Count -gt 0) {
        $corsSourceMatches = @(Select-String -LiteralPath $corsSourceFiles.FullName -Pattern 'Access-Control-Allow-Origin|AllowCrossDomain|crossdomain|CORS' -ErrorAction SilentlyContinue)
    }

    if ($corsSourceMatches.Count -gt 0) {
        Add-Ok 'CORS source scan' "$($corsSourceMatches.Count) signal(s)"
    } else {
        Add-Warn 'CORS source scan' 'no app/source CORS signal found; verify server/proxy policy for cross-origin deployments'
    }

    $middlewarePath = 'app/middleware.php'
    $hasGlobalCorsSignal = $false
    if (Test-Path -LiteralPath $middlewarePath) {
        $middlewareText = Get-Content -LiteralPath $middlewarePath -Raw
        $hasGlobalCorsSignal = $middlewareText -match 'Access-Control-Allow-Origin|AllowCrossDomain|crossdomain|CORS'
    }

    if ($hasGlobalCorsSignal) {
        Add-Ok 'CORS global middleware signal' 'present in app/middleware.php'
    } else {
        Add-Warn 'CORS global middleware signal' 'not found in app/middleware.php; cross-origin production should be handled by app middleware or server config'
    }

    $wildcardOriginMatches = @($corsSourceMatches | Where-Object { $_.Line -match 'Access-Control-Allow-Origin' -and $_.Line -match '\*' })
    if ($wildcardOriginMatches.Count -gt 0) {
        Add-ConditionalProductionIssue -Name 'CORS wildcard origin source' -Detail "$($wildcardOriginMatches.Count) match(es); avoid '*' for credentialed/admin APIs"
    } else {
        Add-Ok 'CORS wildcard origin source' 'no wildcard Access-Control-Allow-Origin source matches'
    }

    $credentialMatches = @($corsSourceMatches | Where-Object { $_.Line -match 'Access-Control-Allow-Credentials' -and $_.Line -match '(?i)\btrue\b' })
    if ($wildcardOriginMatches.Count -gt 0 -and $credentialMatches.Count -gt 0) {
        Add-ConditionalProductionIssue -Name 'CORS wildcard credentials source' -Detail 'wildcard origin and credential headers both appear in source; verify they cannot be emitted together'
    } else {
        Add-Ok 'CORS wildcard credentials source' 'no wildcard-origin plus credentials source combination detected'
    }

    $frontendEnvPath = 'snowy-admin-web/.env.production'
    if (Test-Path -LiteralPath $frontendEnvPath) {
        $frontendEnv = Read-DotEnv -Path $frontendEnvPath
        $apiPrefix = ''
        if ($frontendEnv.ContainsKey('VITE_API_PREFIX')) {
            $apiPrefix = [string]$frontendEnv['VITE_API_PREFIX']
        }

        if ([string]::IsNullOrWhiteSpace($apiPrefix)) {
            Add-Warn 'Frontend CORS API prefix' 'VITE_API_PREFIX empty in production env; verify same-origin /api or proxy policy'
        } elseif ($apiPrefix.Trim().StartsWith('/')) {
            Add-Ok 'Frontend CORS API prefix' 'relative API prefix; same-origin deployment can avoid browser CORS'
        } else {
            $apiPrefixUri = $null
            if ([Uri]::TryCreate($apiPrefix, [UriKind]::Absolute, [ref]$apiPrefixUri) -and @('http', 'https') -contains $apiPrefixUri.Scheme.ToLowerInvariant()) {
                $frontendApiRequiresCors = $true
                if ($apiPrefixUri.Scheme.ToLowerInvariant() -eq 'https' -or (Test-LocalUrlHost -HostName $apiPrefixUri.Host)) {
                    Add-Warn 'Frontend CORS API prefix' 'absolute API URL; cross-origin CORS must be verified'
                } else {
                    Add-ConditionalProductionIssue -Name 'Frontend CORS API prefix' -Detail 'absolute non-HTTPS API URL is not release-safe'
                }
            } else {
                Add-ConditionalProductionIssue -Name 'Frontend CORS API prefix' -Detail 'VITE_API_PREFIX is neither relative nor an absolute HTTP(S) URL'
            }
        }
    } else {
        Add-Warn 'Frontend CORS API prefix' 'snowy-admin-web/.env.production missing; cannot infer same-origin vs cross-origin API policy'
    }

    if ([string]::IsNullOrWhiteSpace($PublicBaseUrl)) {
        Add-Warn 'CORS preflight probe' 'empty PublicBaseUrl; skip live CORS preflight'
    } elseif ([string]::IsNullOrWhiteSpace($CorsProbeOrigin)) {
        Add-Warn 'CORS preflight probe' 'empty CorsProbeOrigin; pass the frontend origin to inspect Access-Control-* headers'
    } else {
        $corsOriginUri = $null
        if (-not [Uri]::TryCreate($CorsProbeOrigin, [UriKind]::Absolute, [ref]$corsOriginUri) -or @('http', 'https') -notcontains $corsOriginUri.Scheme.ToLowerInvariant() -or [string]::IsNullOrWhiteSpace($corsOriginUri.Host)) {
            Add-ConditionalProductionIssue -Name 'CORS preflight origin' -Detail 'CorsProbeOrigin must be an absolute HTTP(S) origin'
        } else {
            $corsMetadata = Get-HttpResponseMetadata -Url $PublicBaseUrl -TimeoutSeconds $HttpProbeTimeoutSeconds -Method 'OPTIONS' -RequestHeaders @{
                Origin = $CorsProbeOrigin
                'Access-Control-Request-Method' = 'GET'
                'Access-Control-Request-Headers' = 'Authorization, Content-Type'
            }

            if ($null -eq $corsMetadata.StatusCode) {
                Add-ConditionalProductionIssue -Name 'CORS preflight probe' -Detail "probe failed: $($corsMetadata.Error)"
            } else {
                $corsStatusCode = [int]$corsMetadata.StatusCode
                if ($corsStatusCode -ge 200 -and $corsStatusCode -lt 400) {
                    Add-Ok 'CORS preflight probe' "HTTP $corsStatusCode"
                } else {
                    Add-ConditionalProductionIssue -Name 'CORS preflight probe' -Detail "HTTP $corsStatusCode"
                }

                $corsHeaders = $corsMetadata.Headers
                $getCorsHeader = {
                    param([string]$Name)
                    $key = $Name.ToLowerInvariant()
                    if ($corsHeaders.ContainsKey($key)) {
                        return ([string]$corsHeaders[$key]).Trim()
                    }

                    return ''
                }

                $allowOrigin = & $getCorsHeader 'Access-Control-Allow-Origin'
                $originAllowed = $false
                if ([string]::IsNullOrWhiteSpace($allowOrigin)) {
                    Add-ConditionalProductionIssue -Name 'CORS Access-Control-Allow-Origin' -Detail 'missing'
                } elseif ($allowOrigin -eq '*') {
                    Add-ConditionalProductionIssue -Name 'CORS Access-Control-Allow-Origin' -Detail "wildcard for origin $CorsProbeOrigin"
                } elseif ($allowOrigin.TrimEnd('/') -ieq $CorsProbeOrigin.TrimEnd('/')) {
                    $originAllowed = $true
                    Add-Ok 'CORS Access-Control-Allow-Origin' 'matches probe origin'
                } else {
                    Add-ConditionalProductionIssue -Name 'CORS Access-Control-Allow-Origin' -Detail 'does not match probe origin'
                }

                $allowCredentials = (& $getCorsHeader 'Access-Control-Allow-Credentials').ToLowerInvariant()
                if ($allowOrigin -eq '*' -and $allowCredentials -eq 'true') {
                    Add-ConditionalProductionIssue -Name 'CORS credentials policy' -Detail 'wildcard origin cannot be combined with credentials'
                } else {
                    Add-Ok 'CORS credentials policy' 'no wildcard plus credentials combination in probe'
                }

                $varyHeader = & $getCorsHeader 'Vary'
                if ($originAllowed -and $varyHeader -notmatch '(?i)(^|,\s*)Origin(\s*,|$)') {
                    Add-ConditionalProductionIssue -Name 'CORS Vary header' -Detail 'reflected origin should include Vary: Origin'
                } elseif ($originAllowed) {
                    Add-Ok 'CORS Vary header' 'Origin present'
                }

                $allowMethods = (& $getCorsHeader 'Access-Control-Allow-Methods').ToLowerInvariant()
                if ($allowMethods -match '(^|[,\s])(\*|get)([,\s]|$)') {
                    Add-Ok 'CORS Access-Control-Allow-Methods' 'GET allowed'
                } else {
                    Add-ConditionalProductionIssue -Name 'CORS Access-Control-Allow-Methods' -Detail 'GET missing'
                }

                $allowHeaders = (& $getCorsHeader 'Access-Control-Allow-Headers').ToLowerInvariant()
                $authorizationAllowed = $allowHeaders -match '(^|[,\s])(\*|authorization)([,\s]|$)'
                $contentTypeAllowed = $allowHeaders -match '(^|[,\s])(\*|content-type)([,\s]|$)'
                if ($authorizationAllowed -and $contentTypeAllowed) {
                    Add-Ok 'CORS Access-Control-Allow-Headers' 'Authorization and Content-Type allowed'
                } else {
                    Add-ConditionalProductionIssue -Name 'CORS Access-Control-Allow-Headers' -Detail 'Authorization or Content-Type missing'
                }

                $corsProbeConfirmed = $originAllowed -and ($allowMethods -match '(^|[,\s])(\*|get)([,\s]|$)') -and $authorizationAllowed -and $contentTypeAllowed
            }
        }
    }

    if ($Production -and $frontendApiRequiresCors -and -not $corsProbeConfirmed -and -not $hasGlobalCorsSignal) {
        Add-Fail 'CORS production evidence' 'absolute frontend API URL needs app/server CORS evidence or a successful preflight probe'
    }
}

if ($CheckWebServerPolicy -or $CheckNginxSyntax -or $CheckPhpFpmSyntax -or $Production) {
    if (Test-Command $NginxBinary) {
        Add-Ok 'Nginx command' "$NginxBinary found"
        if ($CheckNginxSyntax) {
            $nginxSyntax = Invoke-External -FilePath $NginxBinary -Arguments @('-t')
            if ($nginxSyntax.ExitCode -eq 0) {
                Add-Ok 'Nginx syntax' 'nginx -t passed'
            } else {
                $tail = (@($nginxSyntax.Output) | Select-Object -Last 1)
                Add-Fail 'Nginx syntax' ([string]$tail)
            }
        }
    } else {
        Add-ConditionalProductionIssue -Name 'Nginx command' -Detail "$NginxBinary not found; skip only if managed outside this host"
    }

    if (Test-Command $PhpFpmBinary) {
        Add-Ok 'PHP-FPM command' "$PhpFpmBinary found"
        if ($CheckPhpFpmSyntax) {
            $phpFpmSyntax = Invoke-External -FilePath $PhpFpmBinary -Arguments @('-tt')
            if ($phpFpmSyntax.ExitCode -eq 0) {
                Add-Ok 'PHP-FPM syntax' 'php-fpm -tt passed'
            } else {
                $tail = (@($phpFpmSyntax.Output) | Select-Object -Last 1)
                Add-Fail 'PHP-FPM syntax' ([string]$tail)
            }
        }
    } else {
        Add-ConditionalProductionIssue -Name 'PHP-FPM command' -Detail "$PhpFpmBinary not found; skip only if managed outside this host"
    }
}

if (-not $SkipThinkBoot -and (Test-Command $PhpBinary) -and (Test-Path -LiteralPath 'vendor/autoload.php') -and (Test-Path -LiteralPath 'think')) {
    $routeList = Invoke-External -FilePath $PhpBinary -Arguments @('think', 'route:list')
    if ($routeList.ExitCode -eq 0) {
        $routeRows = @($routeList.Output | Where-Object { $_ -match '^\s*\|' })
        Add-Ok 'ThinkPHP route:list boot' "$($routeRows.Count) table rows"
    } else {
        $tail = (@($routeList.Output) | Select-Object -Last 3) -join ' '
        Add-Fail 'ThinkPHP route:list boot' $tail
    }
} elseif ($SkipThinkBoot) {
    Add-Ok 'ThinkPHP route:list boot' 'skipped'
}

Write-Host ''
Write-Host "Deployment readiness summary: $script:FailureCount failures, $script:WarningCount warnings"

if ($script:FailureCount -gt 0) {
    exit 1
}

if ($Strict -and $script:WarningCount -gt 0) {
    Write-Host 'Strict mode treats warnings as failures.'
    exit 1
}

exit 0
