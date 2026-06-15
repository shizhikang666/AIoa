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
        [Parameter(Mandatory = $true)][string]$Key,
        [string]$Default = ''
    )

    if ($EnvMap.ContainsKey($Key) -and [string]$EnvMap[$Key] -ne '') {
        return [string]$EnvMap[$Key]
    }

    return $Default
}

function Invoke-RawGet {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$Token
    )

    $raw = & curl.exe -sS -X GET $Url -H "Authorization: Bearer $Token"
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP GET failed: $Url"
    }

    return [string]::Join('', [string[]]$raw)
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

function Assert-Ok {
    param([string]$Json, [string]$Name)

    $code = Read-JsonPath -Json $Json -Path 'code'
    if ([int]$code -ne 200) {
        throw "$Name returned code=$code"
    }
}

function Assert-Paths {
    param([string]$Json, [string]$Name, [string[]]$Paths)

    foreach ($path in $Paths) {
        [void](Read-JsonPath -Json $Json -Path $path)
    }
}

function Has-Path {
    param([string]$Json, [string]$Path)

    return $null -ne (Read-JsonPath -Json $Json -Path $Path -Optional)
}

function Enc {
    param([Parameter(Mandatory = $true)][string]$Value)

    return [System.Uri]::EscapeDataString($Value)
}

function Assert-PagedShape {
    param([string]$Json, [string]$Name)

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data.records', 'data.total', 'data.current', 'data.size', 'data.pages')
}

function Assert-ListShape {
    param([string]$Json, [string]$Name)

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data')
}

function Assert-ConfigRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.configKey",
        "$Prefix.configValue",
        "$Prefix.category",
        "$Prefix.remark",
        "$Prefix.sortCode",
        "$Prefix.extJson",
        "$Prefix.sensitive"
    )
}

function Assert-DictRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.parentId",
        "$Prefix.dictLabel",
        "$Prefix.dictValue",
        "$Prefix.category",
        "$Prefix.sortCode",
        "$Prefix.tenantId"
    )
}

function Assert-FileRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.engine",
        "$Prefix.bucket",
        "$Prefix.name",
        "$Prefix.suffix",
        "$Prefix.sizeKb",
        "$Prefix.sizeInfo",
        "$Prefix.objName",
        "$Prefix.storagePath",
        "$Prefix.downloadPath",
        "$Prefix.tenantId"
    )
}

function Assert-JobRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.name",
        "$Prefix.code",
        "$Prefix.category",
        "$Prefix.actionClass",
        "$Prefix.cronExpression",
        "$Prefix.jobStatus",
        "$Prefix.sortCode"
    )
}

function Assert-LogRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.category",
        "$Prefix.name",
        "$Prefix.exeStatus",
        "$Prefix.opIp",
        "$Prefix.opBrowser",
        "$Prefix.reqMethod",
        "$Prefix.reqUrl",
        "$Prefix.opTime",
        "$Prefix.tenantId"
    )
}

function Assert-MessageRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.category",
        "$Prefix.subject",
        "$Prefix.content",
        "$Prefix.extJson",
        "$Prefix.tenantId",
        "$Prefix.receiveCount",
        "$Prefix.readCount"
    )
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
`$auth['device'] = 'CODEX_DEV_READ_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

$configPage = Invoke-RawGet -Url "$baseUrl/dev/config/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $configPage -Name 'dev config page'
if (Has-Path -Json $configPage -Path 'data.records.0') {
    Assert-ConfigRow -Json $configPage -Prefix 'data.records.0' -Name 'dev config page first row'
}

$configList = Invoke-RawGet -Url "$baseUrl/dev/config/list?category=BIZ_DEFINE" -Token $token
Assert-ListShape -Json $configList -Name 'dev config list'
if (Has-Path -Json $configList -Path 'data.0') {
    Assert-ConfigRow -Json $configList -Prefix 'data.0' -Name 'dev config list first row'
}

$configFirstId = [string](Read-JsonPath -Json $configPage -Path 'data.records.0.id' -Optional)
if ($configFirstId.Trim() -ne '') {
    $encodedId = Enc $configFirstId.Trim()
    $configDetail = Invoke-RawGet -Url "$baseUrl/dev/config/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $configDetail -Name 'dev config detail'
    Assert-ConfigRow -Json $configDetail -Prefix 'data' -Name 'dev config detail'
}

$dictPage = Invoke-RawGet -Url "$baseUrl/dev/dict/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $dictPage -Name 'dev dict page'
if (Has-Path -Json $dictPage -Path 'data.records.0') {
    Assert-DictRow -Json $dictPage -Prefix 'data.records.0' -Name 'dev dict page first row'
}

$dictList = Invoke-RawGet -Url "$baseUrl/dev/dict/list?size=1" -Token $token
Assert-ListShape -Json $dictList -Name 'dev dict list'
if (Has-Path -Json $dictList -Path 'data.0') {
    Assert-DictRow -Json $dictList -Prefix 'data.0' -Name 'dev dict list first row'
}

$dictTree = Invoke-RawGet -Url "$baseUrl/dev/dict/tree" -Token $token
Assert-ListShape -Json $dictTree -Name 'dev dict tree'
if (Has-Path -Json $dictTree -Path 'data.0') {
    Assert-DictRow -Json $dictTree -Prefix 'data.0' -Name 'dev dict tree first row'
    Assert-Paths -Json $dictTree -Name 'dev dict tree aliases' -Paths @('data.0.name', 'data.0.label', 'data.0.value', 'data.0.weight')
}

$dictFirstId = [string](Read-JsonPath -Json $dictPage -Path 'data.records.0.id' -Optional)
if ($dictFirstId.Trim() -ne '') {
    $encodedId = Enc $dictFirstId.Trim()
    $dictDetail = Invoke-RawGet -Url "$baseUrl/dev/dict/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $dictDetail -Name 'dev dict detail'
    Assert-DictRow -Json $dictDetail -Prefix 'data' -Name 'dev dict detail'
}

$filePage = Invoke-RawGet -Url "$baseUrl/dev/file/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $filePage -Name 'dev file page'
if (Has-Path -Json $filePage -Path 'data.records.0') {
    Assert-FileRow -Json $filePage -Prefix 'data.records.0' -Name 'dev file page first row'
}

$fileList = Invoke-RawGet -Url "$baseUrl/dev/file/list?size=1" -Token $token
Assert-ListShape -Json $fileList -Name 'dev file list'
if (Has-Path -Json $fileList -Path 'data.0') {
    Assert-FileRow -Json $fileList -Prefix 'data.0' -Name 'dev file list first row'
}

$fileFirstId = [string](Read-JsonPath -Json $filePage -Path 'data.records.0.id' -Optional)
if ($fileFirstId.Trim() -ne '') {
    $encodedId = Enc $fileFirstId.Trim()
    $fileDetail = Invoke-RawGet -Url "$baseUrl/dev/file/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $fileDetail -Name 'dev file detail'
    Assert-FileRow -Json $fileDetail -Prefix 'data' -Name 'dev file detail'
}

$jobPage = Invoke-RawGet -Url "$baseUrl/dev/job/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $jobPage -Name 'dev job page'
if (Has-Path -Json $jobPage -Path 'data.records.0') {
    Assert-JobRow -Json $jobPage -Prefix 'data.records.0' -Name 'dev job page first row'
}

$jobList = Invoke-RawGet -Url "$baseUrl/dev/job/list?size=1" -Token $token
Assert-ListShape -Json $jobList -Name 'dev job list'
if (Has-Path -Json $jobList -Path 'data.0') {
    Assert-JobRow -Json $jobList -Prefix 'data.0' -Name 'dev job list first row'
}

$jobActionClasses = Invoke-RawGet -Url "$baseUrl/dev/job/getActionClass" -Token $token
Assert-ListShape -Json $jobActionClasses -Name 'dev job action class list'

$jobFirstId = [string](Read-JsonPath -Json $jobPage -Path 'data.records.0.id' -Optional)
if ($jobFirstId.Trim() -ne '') {
    $encodedId = Enc $jobFirstId.Trim()
    $jobDetail = Invoke-RawGet -Url "$baseUrl/dev/job/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $jobDetail -Name 'dev job detail'
    Assert-JobRow -Json $jobDetail -Prefix 'data' -Name 'dev job detail'
}

$logPage = Invoke-RawGet -Url "$baseUrl/dev/log/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $logPage -Name 'dev log page'
if (Has-Path -Json $logPage -Path 'data.records.0') {
    Assert-LogRow -Json $logPage -Prefix 'data.records.0' -Name 'dev log page first row'
}

$logFirstId = [string](Read-JsonPath -Json $logPage -Path 'data.records.0.id' -Optional)
if ($logFirstId.Trim() -ne '') {
    $encodedId = Enc $logFirstId.Trim()
    $logDetail = Invoke-RawGet -Url "$baseUrl/dev/log/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $logDetail -Name 'dev log detail'
    Assert-LogRow -Json $logDetail -Prefix 'data' -Name 'dev log detail'
    Assert-Paths -Json $logDetail -Name 'dev log detail large fields' -Paths @('data.exeMessage', 'data.paramJson', 'data.resultJson', 'data.signData')
}

$visLine = Invoke-RawGet -Url "$baseUrl/dev/log/vis/lineChartData" -Token $token
Assert-Ok -Json $visLine -Name 'dev log vis line chart'
Assert-Paths -Json $visLine -Name 'dev log vis line chart' -Paths @('data')

$visPie = Invoke-RawGet -Url "$baseUrl/dev/log/vis/pieChartData" -Token $token
Assert-Ok -Json $visPie -Name 'dev log vis pie chart'
Assert-Paths -Json $visPie -Name 'dev log vis pie chart' -Paths @('data')

$opBar = Invoke-RawGet -Url "$baseUrl/dev/log/op/barChartData" -Token $token
Assert-Ok -Json $opBar -Name 'dev log op bar chart'
Assert-Paths -Json $opBar -Name 'dev log op bar chart' -Paths @('data')

$opPie = Invoke-RawGet -Url "$baseUrl/dev/log/op/pieChartData" -Token $token
Assert-Ok -Json $opPie -Name 'dev log op pie chart'
Assert-Paths -Json $opPie -Name 'dev log op pie chart' -Paths @('data')

$serverInfo = Invoke-RawGet -Url "$baseUrl/dev/monitor/serverInfo" -Token $token
Assert-Ok -Json $serverInfo -Name 'dev monitor server info'
Assert-Paths -Json $serverInfo -Name 'dev monitor server info' -Paths @(
    'data.devMonitorCpuInfo',
    'data.devMonitorMemoryInfo',
    'data.devMonitorStorageInfo',
    'data.devMonitorServerInfo',
    'data.devMonitorJvmInfo'
)

$networkInfo = Invoke-RawGet -Url "$baseUrl/dev/monitor/networkInfo" -Token $token
Assert-Ok -Json $networkInfo -Name 'dev monitor network info'
Assert-Paths -Json $networkInfo -Name 'dev monitor network info' -Paths @(
    'data.devMonitorNetworkInfo.upLinkRate',
    'data.devMonitorNetworkInfo.downLinkRate'
)

$messagePage = Invoke-RawGet -Url "$baseUrl/dev/message/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $messagePage -Name 'dev message page'
if (Has-Path -Json $messagePage -Path 'data.records.0') {
    Assert-MessageRow -Json $messagePage -Prefix 'data.records.0' -Name 'dev message page first row'
}

Write-Host 'dev read HTTP smoke passed'
