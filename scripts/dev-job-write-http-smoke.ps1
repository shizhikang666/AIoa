param(
    [string]$BackendBaseUrl = 'http://127.0.0.1:82',
    [string]$EnvPath = (Join-Path (Resolve-Path (Join-Path $PSScriptRoot '..')) '.env')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $ProjectRoot

function Get-EnvMap {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        throw "Missing local env file: $Path"
    }

    $map = @{}
    foreach ($line in Get-Content -LiteralPath $Path) {
        $trimmed = $line.Trim()
        if ($trimmed -eq '' -or $trimmed.StartsWith('#')) {
            continue
        }

        $parts = $trimmed -split '=', 2
        if ($parts.Count -ne 2) {
            continue
        }

        $key = $parts[0].Trim()
        $value = $parts[1].Trim()
        if (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'"))) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        $map[$key] = $value
    }

    return $map
}

function Get-EnvValue {
    param(
        [Parameter(Mandatory = $true)][hashtable]$EnvMap,
        [Parameter(Mandatory = $true)][string]$Key
    )

    if ($EnvMap.ContainsKey($Key) -and [string]$EnvMap[$Key] -ne '') {
        return [string]$EnvMap[$Key]
    }

    return ''
}

function Invoke-RawGet {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [string]$Token = ''
    )

    $headers = @()
    if ($Token -ne '') {
        $headers += @('-H', "Authorization: Bearer $Token")
    }

    $raw = & curl.exe -sS -X GET $Url @headers
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP GET failed: $Url"
    }

    return [string]::Join('', [string[]]$raw)
}

function Invoke-RawPostJson {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][hashtable]$Data,
        [string]$Token = ''
    )

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-dev-job-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    try {
        $Data | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $tmp -Encoding UTF8
        $headers = @('-H', 'Content-Type: application/json')
        if ($Token -ne '') {
            $headers += @('-H', "Authorization: Bearer $Token")
        }

        $raw = & curl.exe -sS -X POST $Url @headers --data-binary "@$tmp"
        if ($LASTEXITCODE -ne 0) {
            throw "HTTP POST failed: $Url"
        }

        return [string]::Join('', [string[]]$raw)
    } finally {
        Remove-Item -LiteralPath $tmp -ErrorAction SilentlyContinue
    }
}

function Read-JsonPath {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path,
        [switch]$Optional
    )

    $value = $Json | node (Join-Path $PSScriptRoot 'json-read.js') $Path
    $exitCode = $LASTEXITCODE
    if ($exitCode -eq 2 -and $Optional) {
        return $null
    }
    if ($exitCode -ne 0) {
        throw "JSON path missing or invalid: $Path"
    }

    return [string]$value
}

function Assert-Code {
    param([string]$Json, [int]$Expected, [string]$Name)

    $code = [int](Read-JsonPath -Json $Json -Path 'code')
    if ($code -ne $Expected) {
        throw "$Name returned code=$code expected=$Expected body=$Json"
    }
}

function Assert-PathEquals {
    param([string]$Json, [string]$Path, [string]$Expected, [string]$Name)

    $actual = [string](Read-JsonPath -Json $Json -Path $Path)
    if ($actual -ne $Expected) {
        throw "$Name expected $Path=$Expected actual=$actual"
    }
}

function Enc {
    param([Parameter(Mandatory = $true)][string]$Value)

    return [System.Uri]::EscapeDataString($Value)
}

function Invoke-Php {
    param([Parameter(Mandatory = $true)][string]$Code)

    $output = & php -r $Code
    if ($LASTEXITCODE -ne 0) {
        throw 'php inline command failed'
    }
    if ($null -eq $output) {
        return ''
    }

    return [string]::Join('', [string[]]$output)
}

$envMap = Get-EnvMap -Path $EnvPath
$account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
if ($account -eq '') {
    throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env'
}

$safeAccount = $account.Replace("'", "\'")
$tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_DEV_JOB_WRITE_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = Invoke-Php -Code $tokenCode
if ([string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'CODEX_JOB_' + (Get-Date -Format 'MMddHHmmss')
$safePrefix = $prefix.Replace("'", "\'")
$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('dev_job')->whereLike('NAME', '$safePrefix%')->delete();
"@

Invoke-Php -Code $cleanupCode | Out-Null

try {
    $classes = Invoke-RawGet -Url "$baseUrl/dev/job/getActionClass" -Token $token
    Assert-Code -Json $classes -Expected 200 -Name 'dev job action classes'
    $actionClass = [string](Read-JsonPath -Json $classes -Path 'data.0')
    if ($actionClass.Trim() -eq '') {
        throw 'dev job action class list is empty'
    }

    $now = Get-Date
    $cron1 = ('{0} {1} {2} 28 12 ?' -f ($now.Second % 60), ($now.Minute % 60), ($now.Hour % 24))
    $cron2 = ('{0} {1} {2} 27 12 ?' -f (($now.Second + 1) % 60), ($now.Minute % 60), ($now.Hour % 24))

    $noToken = Invoke-RawPostJson -Url "$baseUrl/dev/job/add" -Data @{
        name = "$prefix-no-token"
        category = 'BIZ'
        actionClass = $actionClass
        cronExpression = $cron1
        sortCode = 98
    }
    Assert-Code -Json $noToken -Expected 401 -Name 'dev job add without token'

    $add = Invoke-RawPostJson -Url "$baseUrl/dev/job/add" -Token $token -Data @{
        name = "$prefix-add"
        category = 'BIZ'
        actionClass = $actionClass
        cronExpression = $cron1
        sortCode = 98
        extJson = '{"codex":true}'
    }
    Assert-Code -Json $add -Expected 200 -Name 'dev job add'
    $id = [string](Read-JsonPath -Json $add -Path 'data.id')
    if ($id.Trim() -eq '') {
        throw 'dev job add did not return id'
    }

    $encodedId = Enc $id
    $detail = Invoke-RawGet -Url "$baseUrl/dev/job/detail?id=$encodedId" -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'dev job detail after add'
    Assert-PathEquals -Json $detail -Path 'data.name' -Expected "$prefix-add" -Name 'dev job detail add name'
    Assert-PathEquals -Json $detail -Path 'data.category' -Expected 'BIZ' -Name 'dev job detail add category'
    Assert-PathEquals -Json $detail -Path 'data.actionClass' -Expected $actionClass -Name 'dev job detail add actionClass'
    Assert-PathEquals -Json $detail -Path 'data.cronExpression' -Expected $cron1 -Name 'dev job detail add cron'
    Assert-PathEquals -Json $detail -Path 'data.jobStatus' -Expected 'STOPPED' -Name 'dev job detail add status'
    $code = [string](Read-JsonPath -Json $detail -Path 'data.code')
    if ($code.Length -ne 10) {
        throw "dev job generated code length expected 10 actual=$($code.Length)"
    }

    $duplicate = Invoke-RawPostJson -Url "$baseUrl/dev/job/add" -Token $token -Data @{
        name = "$prefix-dup"
        category = 'BIZ'
        actionClass = $actionClass
        cronExpression = $cron1
        sortCode = 97
    }
    Assert-Code -Json $duplicate -Expected 400 -Name 'dev job duplicate add'

    $invalidCategory = Invoke-RawPostJson -Url "$baseUrl/dev/job/add" -Token $token -Data @{
        name = "$prefix-invalid-category"
        category = 'LOCAL'
        actionClass = $actionClass
        cronExpression = $cron2
        sortCode = 97
    }
    Assert-Code -Json $invalidCategory -Expected 400 -Name 'dev job invalid category'

    $invalidCron = Invoke-RawPostJson -Url "$baseUrl/dev/job/add" -Token $token -Data @{
        name = "$prefix-invalid-cron"
        category = 'BIZ'
        actionClass = $actionClass
        cronExpression = 'bad'
        sortCode = 97
    }
    Assert-Code -Json $invalidCron -Expected 400 -Name 'dev job invalid cron'

    $invalidAction = Invoke-RawPostJson -Url "$baseUrl/dev/job/add" -Token $token -Data @{
        name = "$prefix-invalid-action"
        category = 'BIZ'
        actionClass = 'codex.MissingJob'
        cronExpression = $cron2
        sortCode = 97
    }
    Assert-Code -Json $invalidAction -Expected 400 -Name 'dev job invalid action class'

    $edit = Invoke-RawPostJson -Url "$baseUrl/dev/job/edit" -Token $token -Data @{
        id = $id
        name = "$prefix-edit"
        category = 'BIZ'
        actionClass = $actionClass
        cronExpression = $cron2
        sortCode = 97
        extJson = '{"codex":"edited"}'
    }
    Assert-Code -Json $edit -Expected 200 -Name 'dev job edit'

    $detailAfterEdit = Invoke-RawGet -Url "$baseUrl/dev/job/detail?id=$encodedId" -Token $token
    Assert-Code -Json $detailAfterEdit -Expected 200 -Name 'dev job detail after edit'
    Assert-PathEquals -Json $detailAfterEdit -Path 'data.name' -Expected "$prefix-edit" -Name 'dev job detail edit name'
    Assert-PathEquals -Json $detailAfterEdit -Path 'data.cronExpression' -Expected $cron2 -Name 'dev job detail edit cron'
    Assert-PathEquals -Json $detailAfterEdit -Path 'data.jobStatus' -Expected 'STOPPED' -Name 'dev job detail edit status'
    Assert-PathEquals -Json $detailAfterEdit -Path 'data.code' -Expected $code -Name 'dev job detail code preserved'

    $safeId = $id.Replace("'", "\'")
    Invoke-Php -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('dev_job')->where('ID', '$safeId')->update(['JOB_STATUS' => 'RUNNING']);
"@ | Out-Null

    $editRunning = Invoke-RawPostJson -Url "$baseUrl/dev/job/edit" -Token $token -Data @{
        id = $id
        name = "$prefix-running"
        category = 'BIZ'
        actionClass = $actionClass
        cronExpression = $cron1
        sortCode = 96
    }
    Assert-Code -Json $editRunning -Expected 400 -Name 'dev job running edit guard'

    $runNoToken = Invoke-RawPostJson -Url "$baseUrl/dev/job/runJob" -Data @{
        id = $id
    }
    Assert-Code -Json $runNoToken -Expected 401 -Name 'dev job run without token'

    $missingRun = Invoke-RawPostJson -Url "$baseUrl/dev/job/runJob" -Token $token -Data @{
        id = ''
    }
    Assert-Code -Json $missingRun -Expected 400 -Name 'dev job run missing id'

    $runAlready = Invoke-RawPostJson -Url "$baseUrl/dev/job/runJob" -Token $token -Data @{
        id = $id
    }
    Assert-Code -Json $runAlready -Expected 400 -Name 'dev job run already running'

    $stop = Invoke-RawPostJson -Url "$baseUrl/dev/job/stopJob" -Token $token -Data @{
        id = $id
    }
    Assert-Code -Json $stop -Expected 200 -Name 'dev job stop'

    $detailStopped = Invoke-RawGet -Url "$baseUrl/dev/job/detail?id=$encodedId" -Token $token
    Assert-Code -Json $detailStopped -Expected 200 -Name 'dev job detail after stop'
    Assert-PathEquals -Json $detailStopped -Path 'data.jobStatus' -Expected 'STOPPED' -Name 'dev job detail stop status'

    $stopAlready = Invoke-RawPostJson -Url "$baseUrl/dev/job/stopJob" -Token $token -Data @{
        id = $id
    }
    Assert-Code -Json $stopAlready -Expected 400 -Name 'dev job stop already stopped'

    $run = Invoke-RawPostJson -Url "$baseUrl/dev/job/runJob" -Token $token -Data @{
        id = $id
    }
    Assert-Code -Json $run -Expected 200 -Name 'dev job run'

    $detailRunning = Invoke-RawGet -Url "$baseUrl/dev/job/detail?id=$encodedId" -Token $token
    Assert-Code -Json $detailRunning -Expected 200 -Name 'dev job detail after run'
    Assert-PathEquals -Json $detailRunning -Path 'data.jobStatus' -Expected 'RUNNING' -Name 'dev job detail run status'

    $runNowRunning = Invoke-RawPostJson -Url "$baseUrl/dev/job/runJobNow" -Token $token -Data @{
        id = $id
    }
    Assert-Code -Json $runNowRunning -Expected 200 -Name 'dev job run now while running'

    Invoke-Php -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('dev_job')->where('ID', '$safeId')->update(['JOB_STATUS' => 'STOPPED']);
"@ | Out-Null

    $runNowStopped = Invoke-RawPostJson -Url "$baseUrl/dev/job/runJobNow" -Token $token -Data @{
        id = $id
    }
    Assert-Code -Json $runNowStopped -Expected 200 -Name 'dev job run now while stopped'

    $detailRunNow = Invoke-RawGet -Url "$baseUrl/dev/job/detail?id=$encodedId" -Token $token
    Assert-Code -Json $detailRunNow -Expected 200 -Name 'dev job detail after run now'
    Assert-PathEquals -Json $detailRunNow -Path 'data.jobStatus' -Expected 'RUNNING' -Name 'dev job detail run now status'

    Write-Host 'dev job write HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
