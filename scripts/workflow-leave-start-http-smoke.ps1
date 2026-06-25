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

function Invoke-JsonPost {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [string]$Token = '',
        [object]$Body = @{}
    )

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("workflow-leave-start-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    $Body | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $bodyPath -Encoding ASCII
    try {
        $args = @('-sS', '-X', 'POST', $Url, '-H', 'Content-Type: application/json', '--data-binary', "@$bodyPath")
        if ($Token -ne '') {
            $args = @('-sS', '-X', 'POST', $Url, '-H', "Authorization: Bearer $Token", '-H', 'Content-Type: application/json', '--data-binary', "@$bodyPath")
        }

        $raw = (& curl.exe @args)
        if ($LASTEXITCODE -ne 0) {
            throw "curl failed for $Url"
        }

        return (($raw -join "`n").TrimStart([char]0xFEFF) | ConvertFrom-Json)
    } finally {
        Remove-Item -LiteralPath $bodyPath -Force -ErrorAction SilentlyContinue
    }
}

function Invoke-JsonGet {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [string]$Token = ''
    )

    $args = @('-sS', $Url)
    if ($Token -ne '') {
        $args = @('-sS', $Url, '-H', "Authorization: Bearer $Token")
    }

    $raw = (& curl.exe @args)
    if ($LASTEXITCODE -ne 0) {
        throw "curl failed for $Url"
    }

    return (($raw -join "`n").TrimStart([char]0xFEFF) | ConvertFrom-Json)
}

function Invoke-PhpJson {
    param([Parameter(Mandatory = $true)][string]$Code)

    $raw = (& php -r $Code)
    if ($LASTEXITCODE -ne 0) {
        throw 'php code failed'
    }

    return (($raw -join "`n").TrimStart([char]0xFEFF) | ConvertFrom-Json)
}

function Test-RecordsContainProcess {
    param(
        [Parameter(Mandatory = $true)]$Records,
        [Parameter(Mandatory = $true)][string]$ProcessInstanceId
    )

    foreach ($record in @($Records)) {
        foreach ($property in @('processInstanceId', 'instanceId', 'processId', 'id')) {
            if ($record.PSObject.Properties.Name -contains $property -and [string]$record.$property -eq $ProcessInstanceId) {
                return $true
            }
        }
    }

    return $false
}

function Remove-SmokeProcess {
    param([string]$ProcessInstanceId)

    if ([string]::IsNullOrWhiteSpace($ProcessInstanceId)) {
        return
    }

    $safeProcessId = $ProcessInstanceId.Replace("'", "\'")
    $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safeProcessId';
think\facade\Db::transaction(function () use (`$pid): void {
    think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->delete();
    think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$pid)->delete();
    think\facade\Db::name('act_hi_varinst')->where('PROC_INST_ID_', `$pid)->delete();
    think\facade\Db::name('act_hi_taskinst')->where('PROC_INST_ID_', `$pid)->delete();
    think\facade\Db::name('act_hi_actinst')->where('PROC_INST_ID_', `$pid)->delete();
    think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->delete();
    think\facade\Db::name('act_ru_execution')->where('PROC_INST_ID_', `$pid)->where('ID_', '<>', `$pid)->delete();
    think\facade\Db::name('act_ru_execution')->where('ID_', `$pid)->delete();
    think\facade\Db::name('biz_cc_records')->where('INSTANCE_ID', `$pid)->delete();
    think\facade\Db::name('biz_file_relation')->where('OBJECT_ID', `$pid)->delete();
});
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    [void](Invoke-PhpJson -Code $cleanupCode)
}

function Remove-SmokeFile {
    param([string]$FileId)

    if ([string]::IsNullOrWhiteSpace($FileId)) {
        return
    }

    $safeFileId = $FileId.Replace("'", "\'")
    $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$fid = '$safeFileId';
think\facade\Db::transaction(function () use (`$fid): void {
    think\facade\Db::name('biz_file_relation')->where('TARGET_ID', `$fid)->delete();
    think\facade\Db::name('dev_file')->where('ID', `$fid)->delete();
});
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    [void](Invoke-PhpJson -Code $cleanupCode)
}

$envMap = Get-EnvMap -Path $EnvPath
$account = ''
if ($envMap.ContainsKey('LOCAL_SUPER_ADMIN_ACCOUNT')) {
    $account = [string]$envMap['LOCAL_SUPER_ADMIN_ACCOUNT']
}
if ($account -eq '') {
    throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env'
}

$safeAccount = $account.Replace("'", "\'")
$contextCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_WORKFLOW_LEAVE_START_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => (string)`$user['ID'],
    'tenantId' => (string)(`$user['TENANT_ID'] ?? ''),
    'orgId' => (string)(`$user['ORG_ID'] ?? ''),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

$context = Invoke-PhpJson -Code $contextCode
$token = [string]$context.token
$userId = [string]$context.userId
if ([string]::IsNullOrWhiteSpace($token) -or [string]::IsNullOrWhiteSpace($userId)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$path = '/biz/process/leave/start'
$processInstanceId = ''
$fileId = ''

try {
    $noToken = Invoke-JsonPost -Url ($baseUrl + $path) -Body @{}
    if ([int]$noToken.code -ne 401) {
        throw "leave start no-token expected code=401, got code=$($noToken.code)"
    }
    Write-Host "$path no-token code=401"

    $missingApprove = Invoke-JsonPost -Url ($baseUrl + $path) -Token $token -Body @{
        category = 'leaveOfAbsence'
        startTime = '2026-06-23 09:00:00'
        endTime = '2026-06-23 18:00:00'
        amount = '1'
        copyUserIdList = @()
        remark = 'codex missing approve smoke'
    }
    if ([int]$missingApprove.code -ne 400) {
        throw "leave start missing approver expected code=400, got code=$($missingApprove.code)"
    }
    Write-Host "$path missing-approve code=400"

    $safeUserId = $userId.Replace("'", "\'")
    $safeTenantId = ([string]$context.tenantId).Replace("'", "\'")
    $fileContextCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$id = (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
`$name = 'codex-workflow-leave-start-' . substr(`$id, -8) . '.txt';
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('dev_file')->insert([
    'ID' => `$id,
    'ENGINE' => 'LOCAL',
    'BUCKET' => 'defaultBucketName',
    'NAME' => `$name,
    'SUFFIX' => 'txt',
    'SIZE_KB' => 1,
    'SIZE_INFO' => '1KB',
    'OBJ_NAME' => `$name,
    'STORAGE_PATH' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . `$name,
    'DOWNLOAD_PATH' => '/api/dev/file/download?id=' . `$id,
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => '$safeUserId',
    'TENANT_ID' => '$safeTenantId',
]);
echo json_encode(['id' => `$id, 'name' => `$name], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $fileContext = Invoke-PhpJson -Code $fileContextCode
    $fileId = [string]$fileContext.id
    $fileName = [string]$fileContext.name
    if ([string]::IsNullOrWhiteSpace($fileId) -or [string]::IsNullOrWhiteSpace($fileName)) {
        throw 'failed to create smoke dev_file row'
    }

    $remark = 'codex workflow leave start smoke ' + ([Guid]::NewGuid().ToString('N').Substring(0, 12))
    $success = Invoke-JsonPost -Url ($baseUrl + $path) -Token $token -Body @{
        category = 'leaveOfAbsence'
        startTime = '2026-06-23 09:00:00'
        endTime = '2026-06-23 18:00:00'
        amount = '1'
        approveUserIdList = @($userId)
        copyUserIdList = @($userId)
        fileIdList = @($fileId)
        remark = $remark
    }
    if ([int]$success.code -ne 200) {
        throw "leave start success expected code=200, got code=$($success.code), message=$($success.message)"
    }
    $processInstanceId = [string]$success.data.processInstanceId
    $taskId = [string]$success.data.taskId
    if ([string]::IsNullOrWhiteSpace($processInstanceId) -or [string]::IsNullOrWhiteSpace($taskId)) {
        throw 'leave start response missing processInstanceId or taskId'
    }
    if ([int]$success.data.ccRecordCount -ne 1) {
        throw "leave start ccRecordCount expected 1, got $($success.data.ccRecordCount)"
    }
    if ([int]$success.data.fileRelationCount -ne 1) {
        throw "leave start fileRelationCount expected 1, got $($success.data.fileRelationCount)"
    }
    Write-Host "$path success processInstanceId=$processInstanceId taskId=$taskId"

    $safeProcessId = $processInstanceId.Replace("'", "\'")
    $safeFileId = $fileId.Replace("'", "\'")
    $verifyCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safeProcessId';
`$fid = '$safeFileId';
echo json_encode([
    'ruExecution' => think\facade\Db::name('act_ru_execution')->where('PROC_INST_ID_', `$pid)->count(),
    'ruTask' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->count(),
    'ruVariable' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$pid)->count(),
    'hiProcinst' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->count(),
    'hiTaskinst' => think\facade\Db::name('act_hi_taskinst')->where('PROC_INST_ID_', `$pid)->count(),
    'hiVarinst' => think\facade\Db::name('act_hi_varinst')->where('PROC_INST_ID_', `$pid)->count(),
    'hiActinst' => think\facade\Db::name('act_hi_actinst')->where('PROC_INST_ID_', `$pid)->count(),
    'status' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$pid)->where('NAME_', 'status')->value('TEXT_'),
    'ccRecord' => think\facade\Db::name('biz_cc_records')->where('INSTANCE_ID', `$pid)->field('TITLE,PROCESS_ID,PROMOTER_ID,INSTANCE_ID,CATEGORY,USER,DELETE_FLAG,TENANT_ID')->find(),
    'fileRelation' => think\facade\Db::name('biz_file_relation')->where('OBJECT_ID', `$pid)->where('TARGET_ID', `$fid)->field('OBJECT_ID,TARGET_ID,CATEGORY,FILE_NAME,DELETE_FLAG,CREATE_USER,TENANT_ID')->find(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $counts = Invoke-PhpJson -Code $verifyCode
    if ([int]$counts.ruExecution -lt 2 -or [int]$counts.ruTask -ne 1 -or [int]$counts.ruVariable -lt 10) {
        throw "runtime rows incomplete: $($counts | ConvertTo-Json -Compress)"
    }
    if ([int]$counts.hiProcinst -ne 1 -or [int]$counts.hiTaskinst -ne 1 -or [int]$counts.hiVarinst -lt 10 -or [int]$counts.hiActinst -lt 1) {
        throw "history rows incomplete: $($counts | ConvertTo-Json -Compress)"
    }
    if ([string]$counts.status -ne 'progress') {
        throw "status variable expected progress, got $($counts.status)"
    }
    if ($null -eq $counts.ccRecord) {
        throw 'cc record was not created for copyUserIdList'
    }
    if ([string]$counts.ccRecord.PROCESS_ID -ne ([string]$success.data.processDefinitionId) -or [string]$counts.ccRecord.INSTANCE_ID -ne $processInstanceId) {
        throw "cc record process ids mismatch: $($counts.ccRecord | ConvertTo-Json -Compress)"
    }
    if ([string]$counts.ccRecord.PROMOTER_ID -ne $userId -or [string]$counts.ccRecord.USER -ne $userId) {
        throw "cc record user fields mismatch: $($counts.ccRecord | ConvertTo-Json -Compress)"
    }
    if ([string]$counts.ccRecord.TITLE -ne ([string]$success.data.title) -or [string]$counts.ccRecord.CATEGORY -ne 'Process_ask_leave' -or [string]$counts.ccRecord.DELETE_FLAG -ne 'NOT_DELETE') {
        throw "cc record payload mismatch: $($counts.ccRecord | ConvertTo-Json -Compress)"
    }
    if ($null -eq $counts.fileRelation) {
        throw 'file relation was not created for fileIdList'
    }
    if ([string]$counts.fileRelation.OBJECT_ID -ne $processInstanceId -or [string]$counts.fileRelation.TARGET_ID -ne $fileId) {
        throw "file relation object/target mismatch: $($counts.fileRelation | ConvertTo-Json -Compress)"
    }
    if ([string]$counts.fileRelation.CATEGORY -ne 'Process_ask_leave' -or [string]$counts.fileRelation.FILE_NAME -ne $fileName -or [string]$counts.fileRelation.DELETE_FLAG -ne 'NOT_DELETE') {
        throw "file relation payload mismatch: $($counts.fileRelation | ConvertTo-Json -Compress)"
    }
    if ([string]$counts.fileRelation.CREATE_USER -ne $userId) {
        throw "file relation create user mismatch: $($counts.fileRelation | ConvertTo-Json -Compress)"
    }
    Write-Host 'act_* row verification passed'

    $taskPage = Invoke-JsonGet -Url ($baseUrl + "/biz/task/page?current=1&size=5&processInstanceId=$processInstanceId") -Token $token
    if ([int]$taskPage.code -ne 200 -or -not (Test-RecordsContainProcess -Records $taskPage.data.records -ProcessInstanceId $processInstanceId)) {
        throw 'pending task page did not include started leave process'
    }
    Write-Host '/biz/task/page includes started leave task'

    $processPage = Invoke-JsonGet -Url ($baseUrl + "/biz/process/page?current=1&size=5&processKey=Process_ask_leave") -Token $token
    if ([int]$processPage.code -ne 200 -or -not (Test-RecordsContainProcess -Records $processPage.data.records -ProcessInstanceId $processInstanceId)) {
        throw 'started process page did not include started leave process'
    }
    Write-Host '/biz/process/page includes started leave process'

    $encodedProcessId = [System.Uri]::EscapeDataString($processInstanceId)
    $ccPage = Invoke-JsonGet -Url ($baseUrl + "/biz/ccrecords/page?current=1&size=5&instanceId=$encodedProcessId") -Token $token
    if ([int]$ccPage.code -ne 200 -or [int]$ccPage.data.total -lt 1) {
        throw 'cc records page did not include generated copy-user row'
    }
    $ccRecord = $ccPage.data.records[0]
    if ([string]$ccRecord.instanceId -ne $processInstanceId -or [string]$ccRecord.user -ne $userId -or [string]$ccRecord.promoterId -ne $userId) {
        throw "cc records page row mismatch: $($ccRecord | ConvertTo-Json -Compress)"
    }
    Write-Host '/biz/ccrecords/page includes generated copy-user record'

    $fileList = Invoke-JsonPost -Url ($baseUrl + '/biz/process/fileList') -Token $token -Body @{
        processInstanceId = $processInstanceId
        category = 'Process_ask_leave'
    }
    if ([int]$fileList.code -ne 200 -or @($fileList.data).Count -lt 1) {
        throw 'process fileList did not include generated workflow file relation'
    }
    $fileRow = @($fileList.data)[0]
    if ([string]$fileRow.objectId -ne $processInstanceId -or [string]$fileRow.targetId -ne $fileId -or [string]$fileRow.category -ne 'Process_ask_leave') {
        throw "process fileList row mismatch: $($fileRow | ConvertTo-Json -Compress)"
    }
    Write-Host '/biz/process/fileList includes generated workflow file relation'

    $detail = Invoke-JsonGet -Url ($baseUrl + "/biz/task/runtime/activity/detail?id=$taskId") -Token $token
    if ([int]$detail.code -ne 200) {
        throw "runtime activity detail expected code=200, got code=$($detail.code)"
    }
    if ([string]$detail.data.processInstanceId -ne $processInstanceId) {
        throw 'runtime activity detail processInstanceId mismatch'
    }
    if ([string]$detail.data.variables.category -ne 'leaveOfAbsence') {
        throw 'runtime activity detail category mismatch'
    }
    Write-Host '/biz/task/runtime/activity/detail reads started leave variables'
} finally {
    Remove-SmokeProcess -ProcessInstanceId $processInstanceId
    Remove-SmokeFile -FileId $fileId
}

Write-Host 'workflow leave start HTTP smoke passed'
