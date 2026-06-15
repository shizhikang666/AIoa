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

function Invoke-RawPost {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$Token,
        [string]$Body = '{}'
    )

    $raw = & curl.exe -sS -X POST $Url -H "Authorization: Bearer $Token" -H 'Content-Type: application/json' --data $Body
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP POST failed: $Url"
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

function Has-Path {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path
    )

    return $null -ne (Read-JsonPath -Json $Json -Path $Path -Optional)
}

function Assert-Ok {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $code = Read-JsonPath -Json $Json -Path 'code'
    if ([int]$code -ne 200) {
        throw "$Name returned code=$code"
    }
}

function Assert-Paths {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string[]]$Paths
    )

    foreach ($path in $Paths) {
        [void](Read-JsonPath -Json $Json -Path $path)
    }
}

function Assert-PagedShape {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name,
        [int]$ExpectedCurrent = 1,
        [int]$ExpectedSize = 1
    )

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data.records', 'data.total', 'data.current', 'data.size', 'data.pages')

    $current = [int](Read-JsonPath -Json $Json -Path 'data.current')
    $size = [int](Read-JsonPath -Json $Json -Path 'data.size')
    if ($current -ne $ExpectedCurrent) {
        throw "$Name expected current=$ExpectedCurrent but got $current"
    }
    if ($size -ne $ExpectedSize) {
        throw "$Name expected size=$ExpectedSize but got $size"
    }
}

function Assert-FirstRecordIfPresent {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string[]]$Keys
    )

    if (-not (Has-Path -Json $Json -Path 'data.records.0')) {
        return
    }

    $paths = @()
    foreach ($key in $Keys) {
        $paths += "data.records.0.$key"
    }
    Assert-Paths -Json $Json -Name "$Name first record" -Paths $paths
}

function Assert-FirstListItemIfPresent {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string[]]$Keys
    )

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data')
    if (-not (Has-Path -Json $Json -Path 'data.0')) {
        return
    }

    $paths = @()
    foreach ($key in $Keys) {
        $paths += "data.0.$key"
    }
    Assert-Paths -Json $Json -Name "$Name first item" -Paths $paths
}

function Assert-WorkflowProcessRowIfPresent {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-FirstRecordIfPresent -Json $Json -Name $Name -Keys @(
        'id',
        'instanceId',
        'processInstanceId',
        'category',
        'processKey',
        'createTime',
        'startTime',
        'variable'
    )
}

function Invoke-Skip {
    param([Parameter(Mandatory = $true)][string]$Message)

    Write-Host "skip: $Message"
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
`$auth['device'] = 'CODEX_WORKFLOW_READ_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$sampleCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$account = '$safeAccount';
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', `$account)->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$userId = (string)`$user['ID'];
`$pendingTaskId = (string)(think\facade\Db::name('act_ru_task')->where('ASSIGNEE_', `$userId)->order('CREATE_TIME_', 'desc')->value('ID_') ?? '');
`$processInstanceId = (string)(think\facade\Db::name('act_hi_procinst')->order('START_TIME_', 'desc')->value('PROC_INST_ID_') ?? '');
`$projectId = (string)(think\facade\Db::name('act_ru_variable')
    ->where('NAME_', 'projectId')
    ->whereNotNull('TEXT_')
    ->where('TEXT_', '<>', '')
    ->value('TEXT_') ?? '');
`$ccId = (string)(think\facade\Db::name('biz_cc_records')
    ->where('USER', `$userId)
    ->where('DELETE_FLAG', 'NOT_DELETE')
    ->order('ID', 'asc')
    ->value('ID') ?? '');
echo json_encode([
    'userId' => `$userId,
    'pendingTaskId' => `$pendingTaskId,
    'processInstanceId' => `$processInstanceId,
    'projectId' => `$projectId,
    'ccId' => `$ccId,
], JSON_UNESCAPED_UNICODE);
"@

$sampleJson = & php -r $sampleCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($sampleJson)) {
    throw 'failed to load sample workflow ids'
}
$sampleJson = ([string]$sampleJson) -replace ([string][char]0xFEFF), ''

$pendingTaskId = [string](Read-JsonPath -Json $sampleJson -Path 'pendingTaskId')
$processInstanceId = [string](Read-JsonPath -Json $sampleJson -Path 'processInstanceId')
$projectId = [string](Read-JsonPath -Json $sampleJson -Path 'projectId')
$ccId = [string](Read-JsonPath -Json $sampleJson -Path 'ccId')
$baseUrl = $BackendBaseUrl.TrimEnd('/')

$taskCount = Invoke-RawGet -Url "$baseUrl/biz/task/count" -Token $token
Assert-Ok -Json $taskCount -Name 'biz task count'
Assert-Paths -Json $taskCount -Name 'biz task count' -Paths @('data')

$taskList = Invoke-RawGet -Url "$baseUrl/biz/task/list?current=1&size=1" -Token $token
Assert-FirstListItemIfPresent -Json $taskList -Name 'biz task list' -Keys @('id', 'taskId', 'instanceId', 'processInstanceId', 'processId', 'variable')

$taskPage = Invoke-RawGet -Url "$baseUrl/biz/task/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $taskPage -Name 'biz task page'
Assert-FirstRecordIfPresent -Json $taskPage -Name 'biz task page' -Keys @('id', 'taskId', 'instanceId', 'processInstanceId', 'processId', 'variable')

$taskHistoryPage = Invoke-RawGet -Url "$baseUrl/biz/task/history/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $taskHistoryPage -Name 'biz task history page'
Assert-FirstRecordIfPresent -Json $taskHistoryPage -Name 'biz task history page' -Keys @('id', 'taskId', 'instanceId', 'processInstanceId', 'processId', 'variable')

$processPage = Invoke-RawGet -Url "$baseUrl/biz/process/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $processPage -Name 'biz process page'
Assert-WorkflowProcessRowIfPresent -Json $processPage -Name 'biz process page'

$processAllPage = Invoke-RawGet -Url "$baseUrl/biz/process/all/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $processAllPage -Name 'biz process all page'
Assert-WorkflowProcessRowIfPresent -Json $processAllPage -Name 'biz process all page'

$queryList = Invoke-RawPost -Url "$baseUrl/biz/process/query/list?processKeys=__codex_missing_process_key__" -Token $token
Assert-Ok -Json $queryList -Name 'biz process query/list'
Assert-Paths -Json $queryList -Name 'biz process query/list' -Paths @('data')

$query = Invoke-RawGet -Url "$baseUrl/biz/process/query?variableName=__codex_missing_variable__&variable=__codex_missing_value__" -Token $token
Assert-Ok -Json $query -Name 'biz process query'
Assert-Paths -Json $query -Name 'biz process query' -Paths @('data')
if (Has-Path -Json $query -Path 'data.0') {
    Assert-Paths -Json $query -Name 'biz process query first item' -Paths @('data.0.variable', 'data.0.processIdList', 'data.0.variableMap')
}

if ($projectId.Trim() -ne '') {
    $encodedProjectId = [System.Uri]::EscapeDataString($projectId.Trim())
    $projectRuntimeQuery = Invoke-RawGet -Url "$baseUrl/biz/process/project/runtime/query/list?projectId=$encodedProjectId" -Token $token
    Assert-Ok -Json $projectRuntimeQuery -Name 'biz process project runtime query list'
    Assert-Paths -Json $projectRuntimeQuery -Name 'biz process project runtime query list' -Paths @('data')
} else {
    Invoke-Skip 'biz process project runtime query sample projectId not found'
}

if ($pendingTaskId.Trim() -ne '') {
    $encodedTaskId = [System.Uri]::EscapeDataString($pendingTaskId.Trim())
    $runtimeActivity = Invoke-RawGet -Url "$baseUrl/biz/task/runtime/activity/detail?id=$encodedTaskId" -Token $token
    Assert-Ok -Json $runtimeActivity -Name 'biz task runtime activity detail'
    Assert-Paths -Json $runtimeActivity -Name 'biz task runtime activity detail' -Paths @(
        'data.category',
        'data.variables',
        'data.taskId',
        'data.processKey',
        'data.processInstanceId',
        'data.processDefinitionId'
    )
} else {
    Invoke-Skip 'biz task runtime activity detail sample pending task not found for smoke account'
}

if ($processInstanceId.Trim() -ne '') {
    $encodedProcessInstanceId = [System.Uri]::EscapeDataString($processInstanceId.Trim())
    $processDetail = Invoke-RawGet -Url "$baseUrl/biz/process/detail?id=$encodedProcessInstanceId" -Token $token
    Assert-Ok -Json $processDetail -Name 'biz process detail'
    Assert-Paths -Json $processDetail -Name 'biz process detail' -Paths @(
        'data.variables',
        'data.activities',
        'data.comments',
        'data.userProcess',
        'data.startUser',
        'data.startOrgTree',
        'data.userActivityList',
        'data.ccUser'
    )

    $processVariable = Invoke-RawPost -Url "$baseUrl/biz/process/variable?id=$encodedProcessInstanceId" -Token $token
    Assert-Ok -Json $processVariable -Name 'biz process variable'
    Assert-Paths -Json $processVariable -Name 'biz process variable' -Paths @('data')

    $processFileList = Invoke-RawPost -Url "$baseUrl/biz/process/fileList?id=$encodedProcessInstanceId" -Token $token
    Assert-Ok -Json $processFileList -Name 'biz process fileList'
    Assert-Paths -Json $processFileList -Name 'biz process fileList' -Paths @('data')
} else {
    Invoke-Skip 'biz process detail/variable/fileList sample process instance not found'
}

$ccPage = Invoke-RawGet -Url "$baseUrl/biz/ccrecords/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $ccPage -Name 'biz ccrecords page'
Assert-FirstRecordIfPresent -Json $ccPage -Name 'biz ccrecords page' -Keys @(
    'id',
    'title',
    'processId',
    'promoterId',
    'instanceId',
    'category',
    'user',
    'deleteFlag',
    'createTime'
)

if ($ccId.Trim() -ne '') {
    $encodedCcId = [System.Uri]::EscapeDataString($ccId.Trim())
    $ccDetail = Invoke-RawGet -Url "$baseUrl/biz/ccrecords/detail?id=$encodedCcId" -Token $token
    Assert-Ok -Json $ccDetail -Name 'biz ccrecords detail'
    Assert-Paths -Json $ccDetail -Name 'biz ccrecords detail' -Paths @(
        'data.id',
        'data.title',
        'data.processId',
        'data.promoterId',
        'data.instanceId',
        'data.category',
        'data.user',
        'data.deleteFlag',
        'data.createTime'
    )
} else {
    Invoke-Skip 'biz ccrecords detail sample current-user record not found'
}

Write-Host 'workflow read HTTP smoke passed'
