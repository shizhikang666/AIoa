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

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("workflow-general-start-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    $Body | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $bodyPath -Encoding ASCII
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

function Get-BusinessCounts {
    $code = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'paymentRecord' => think\facade\Db::name('biz_payment_record')->count(),
    'expenditureRecord' => think\facade\Db::name('biz_expenditure_record')->count(),
    'purchaseOrder' => think\facade\Db::name('biz_purchase_order')->count(),
    'deliveryRecord' => think\facade\Db::name('delivery_record')->count(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    return Invoke-PhpJson -Code $code
}

function Assert-BusinessCountsEqual {
    param(
        [Parameter(Mandatory = $true)]$Expected,
        [Parameter(Mandatory = $true)]$Actual
    )

    foreach ($name in @('paymentRecord', 'expenditureRecord', 'purchaseOrder', 'deliveryRecord')) {
        if ([int]$Expected.$name -ne [int]$Actual.$name) {
            throw "business table count changed for $name, expected=$($Expected.$name), actual=$($Actual.$name)"
        }
    }
}

function New-SmokeFile {
    param(
        [Parameter(Mandatory = $true)][string]$UserId,
        [Parameter(Mandatory = $true)][string]$TenantId
    )

    $safeUserId = $UserId.Replace("'", "\'")
    $safeTenantId = $TenantId.Replace("'", "\'")
    $code = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$id = (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
`$name = 'codex-workflow-general-start-' . substr(`$id, -8) . '.txt';
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

    return Invoke-PhpJson -Code $code
}

function Assert-StartedProcess {
    param(
        [Parameter(Mandatory = $true)][string]$ProcessInstanceId,
        [Parameter(Mandatory = $true)][string]$ProcessKey,
        [Parameter(Mandatory = $true)][string]$TaskId,
        [Parameter(Mandatory = $true)][string]$Marker,
        [int]$ExpectedCcCount = 0,
        [int]$ExpectedFileCount = 0
    )

    $safeProcessId = $ProcessInstanceId.Replace("'", "\'")
    $safeProcessKey = $ProcessKey.Replace("'", "\'")
    $safeTaskId = $TaskId.Replace("'", "\'")
    $safeMarker = $Marker.Replace("'", "\'")
    $code = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safeProcessId';
`$tid = '$safeTaskId';
`$key = '$safeProcessKey';
`$marker = '$safeMarker';
echo json_encode([
    'ruExecution' => think\facade\Db::name('act_ru_execution')->where('PROC_INST_ID_', `$pid)->count(),
    'ruTask' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->count(),
    'ruVariable' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$pid)->count(),
    'hiProc' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->field('PROC_DEF_KEY_,STATE_,END_TIME_')->find(),
    'hiTask' => think\facade\Db::name('act_hi_taskinst')->where('ID_', `$tid)->field('TASK_DEF_KEY_,PROC_DEF_KEY_,END_TIME_')->find(),
    'marker' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$pid)->where('NAME_', 'processSmokeMarker')->value('TEXT_'),
    'status' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$pid)->where('NAME_', 'status')->value('TEXT_'),
    'ccCount' => think\facade\Db::name('biz_cc_records')->where('INSTANCE_ID', `$pid)->where('CATEGORY', `$key)->count(),
    'fileCount' => think\facade\Db::name('biz_file_relation')->where('OBJECT_ID', `$pid)->where('CATEGORY', `$key)->count(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $state = Invoke-PhpJson -Code $code
    if ([int]$state.ruExecution -lt 2 -or [int]$state.ruTask -ne 1 -or [int]$state.ruVariable -lt 8) {
        throw "runtime rows incomplete for $ProcessKey`: $($state | ConvertTo-Json -Compress)"
    }
    if ([string]$state.hiProc.PROC_DEF_KEY_ -ne $ProcessKey -or [string]$state.hiProc.STATE_ -ne 'ACTIVE' -or -not [string]::IsNullOrWhiteSpace([string]$state.hiProc.END_TIME_)) {
        throw "historic process mismatch for $ProcessKey`: $($state.hiProc | ConvertTo-Json -Compress)"
    }
    if ([string]$state.hiTask.TASK_DEF_KEY_ -ne 'Activity_approval' -or [string]$state.hiTask.PROC_DEF_KEY_ -ne $ProcessKey -or -not [string]::IsNullOrWhiteSpace([string]$state.hiTask.END_TIME_)) {
        throw "historic task mismatch for $ProcessKey`: $($state.hiTask | ConvertTo-Json -Compress)"
    }
    if ([string]$state.marker -ne $Marker -or [string]$state.status -ne 'progress') {
        throw "runtime variables mismatch for $ProcessKey`: $($state | ConvertTo-Json -Compress)"
    }
    if ([int]$state.ccCount -ne $ExpectedCcCount -or [int]$state.fileCount -ne $ExpectedFileCount) {
        throw "cc/file counts mismatch for $ProcessKey`: $($state | ConvertTo-Json -Compress)"
    }
}

function Assert-ProcessStillActive {
    param([Parameter(Mandatory = $true)][string]$ProcessInstanceId)

    $safeProcessId = $ProcessInstanceId.Replace("'", "\'")
    $code = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safeProcessId';
echo json_encode([
    'ruTask' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->count(),
    'hiProc' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->field('STATE_,END_TIME_')->find(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $state = Invoke-PhpJson -Code $code
    if ([int]$state.ruTask -ne 1 -or [string]$state.hiProc.STATE_ -ne 'ACTIVE' -or -not [string]::IsNullOrWhiteSpace([string]$state.hiProc.END_TIME_)) {
        throw "process should still be active after deferred approval: $($state | ConvertTo-Json -Compress)"
    }
}

function Assert-PaymentOutFinanceTask {
    param(
        [Parameter(Mandatory = $true)][string]$ProcessInstanceId,
        [Parameter(Mandatory = $true)][string]$TaskId,
        [Parameter(Mandatory = $true)][string]$Assignee
    )

    $safeProcessId = $ProcessInstanceId.Replace("'", "\'")
    $safeTaskId = $TaskId.Replace("'", "\'")
    $safeAssignee = $Assignee.Replace("'", "\'")
    $code = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safeProcessId';
`$tid = '$safeTaskId';
echo json_encode([
    'ruTaskCount' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->count(),
    'task' => think\facade\Db::name('act_ru_task')->where('ID_', `$tid)->field('TASK_DEF_KEY_,ASSIGNEE_')->find(),
    'hiProc' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->field('STATE_,END_TIME_')->find(),
    'paymentRecordCount' => think\facade\Db::name('biz_payment_record')->where('PROCESS_ID', `$pid)->count(),
    'expenditureRecordCount' => think\facade\Db::name('biz_expenditure_record')->where('PROCESS_ID', `$pid)->count(),
    'statementCount' => think\facade\Db::name('settlement_account_statement')->where('PROCESS_ID', `$pid)->count(),
    'expectedAssignee' => '$safeAssignee',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $state = Invoke-PhpJson -Code $code
    if ([int]$state.ruTaskCount -ne 1 -or [string]$state.task.TASK_DEF_KEY_ -ne 'Activity_pay_approval' -or [string]$state.task.ASSIGNEE_ -ne $Assignee) {
        throw "payment-out finance task mismatch: $($state | ConvertTo-Json -Compress)"
    }
    if ([string]$state.hiProc.STATE_ -ne 'ACTIVE' -or -not [string]::IsNullOrWhiteSpace([string]$state.hiProc.END_TIME_)) {
        throw "payment-out process should stay active before finance decision: $($state | ConvertTo-Json -Compress)"
    }
    if ([int]$state.paymentRecordCount -ne 0 -or [int]$state.expenditureRecordCount -ne 0 -or [int]$state.statementCount -ne 0) {
        throw "payment-out first approval should not create business rows: $($state | ConvertTo-Json -Compress)"
    }
}

function Assert-ProcureConfirmationTask {
    param(
        [Parameter(Mandatory = $true)][string]$ProcessInstanceId,
        [Parameter(Mandatory = $true)][string]$TaskId,
        [Parameter(Mandatory = $true)][string]$Assignee
    )

    $safeProcessId = $ProcessInstanceId.Replace("'", "\'")
    $safeTaskId = $TaskId.Replace("'", "\'")
    $safeAssignee = $Assignee.Replace("'", "\'")
    $code = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safeProcessId';
`$tid = '$safeTaskId';
echo json_encode([
    'ruTaskCount' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->count(),
    'task' => think\facade\Db::name('act_ru_task')->where('ID_', `$tid)->field('TASK_DEF_KEY_,ASSIGNEE_')->find(),
    'hiProc' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->field('STATE_,END_TIME_')->find(),
    'purchaseOrderCount' => think\facade\Db::name('biz_purchase_order')->where('INSTANCE_ID', `$pid)->count(),
    'expectedAssignee' => '$safeAssignee',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $state = Invoke-PhpJson -Code $code
    if ([int]$state.ruTaskCount -ne 1 -or [string]$state.task.TASK_DEF_KEY_ -ne 'Activity_procure_approval' -or [string]$state.task.ASSIGNEE_ -ne $Assignee) {
        throw "procure confirmation task mismatch: $($state | ConvertTo-Json -Compress)"
    }
    if ([string]$state.hiProc.STATE_ -ne 'ACTIVE' -or -not [string]::IsNullOrWhiteSpace([string]$state.hiProc.END_TIME_)) {
        throw "procure process should stay active before procurement decision: $($state | ConvertTo-Json -Compress)"
    }
    if ([int]$state.purchaseOrderCount -ne 0) {
        throw "procure first approval should not create purchase order: $($state | ConvertTo-Json -Compress)"
    }
}

function Assert-ProcessRejected {
    param(
        [Parameter(Mandatory = $true)][string]$ProcessInstanceId,
        [Parameter(Mandatory = $true)][string]$ProcessKey
    )

    $safeProcessId = $ProcessInstanceId.Replace("'", "\'")
    $safeProcessKey = $ProcessKey.Replace("'", "\'")
    $code = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safeProcessId';
`$key = '$safeProcessKey';
`$vars = [];
foreach (think\facade\Db::name('act_hi_varinst')->where('PROC_INST_ID_', `$pid)->whereIn('NAME_', ['status', 'state', 'approval'])->field('NAME_,VAR_TYPE_,LONG_,TEXT_')->select()->toArray() as `$row) {
    `$vars[`$row['NAME_']] = `$row;
}
echo json_encode([
    'ruTask' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->count(),
    'ruVariable' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$pid)->count(),
    'ruExecution' => think\facade\Db::name('act_ru_execution')->where('PROC_INST_ID_', `$pid)->count(),
    'hiProc' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->field('PROC_DEF_KEY_,STATE_,END_TIME_,END_ACT_ID_')->find(),
    'vars' => `$vars,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $state = Invoke-PhpJson -Code $code
    if ([int]$state.ruTask -ne 0 -or [int]$state.ruVariable -ne 0 -or [int]$state.ruExecution -ne 0) {
        throw "runtime rows should be gone after reject: $($state | ConvertTo-Json -Compress)"
    }
    if ([string]$state.hiProc.PROC_DEF_KEY_ -ne $ProcessKey -or [string]$state.hiProc.STATE_ -ne 'COMPLETED' -or [string]::IsNullOrWhiteSpace([string]$state.hiProc.END_TIME_)) {
        throw "historic process mismatch after reject: $($state | ConvertTo-Json -Compress)"
    }
    if ([string]$state.vars.status.TEXT_ -ne 'REJECT' -or [string]$state.vars.state.TEXT_ -ne 'REJECT' -or [int]$state.vars.approval.LONG_ -ne 0) {
        throw "historic reject variables mismatch: $($state | ConvertTo-Json -Compress)"
    }
}

function Assert-ProcessCancelled {
    param(
        [Parameter(Mandatory = $true)][string]$ProcessInstanceId,
        [Parameter(Mandatory = $true)][string]$ProcessKey
    )

    $safeProcessId = $ProcessInstanceId.Replace("'", "\'")
    $safeProcessKey = $ProcessKey.Replace("'", "\'")
    $code = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safeProcessId';
`$key = '$safeProcessKey';
`$vars = [];
foreach (think\facade\Db::name('act_hi_varinst')->where('PROC_INST_ID_', `$pid)->whereIn('NAME_', ['status', 'state', 'approval', 'cancel'])->field('NAME_,VAR_TYPE_,LONG_,TEXT_')->select()->toArray() as `$row) {
    `$vars[`$row['NAME_']] = `$row;
}
echo json_encode([
    'ruTask' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->count(),
    'ruVariable' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$pid)->count(),
    'ruExecution' => think\facade\Db::name('act_ru_execution')->where('PROC_INST_ID_', `$pid)->count(),
    'hiProc' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->field('PROC_DEF_KEY_,STATE_,END_TIME_,END_ACT_ID_')->find(),
    'vars' => `$vars,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $state = Invoke-PhpJson -Code $code
    if ([int]$state.ruTask -ne 0 -or [int]$state.ruVariable -ne 0 -or [int]$state.ruExecution -ne 0) {
        throw "cancel did not clear runtime rows for $ProcessKey`: $($state | ConvertTo-Json -Compress)"
    }
    if ([string]$state.hiProc.PROC_DEF_KEY_ -ne $ProcessKey -or [string]$state.hiProc.STATE_ -ne 'COMPLETED' -or [string]::IsNullOrWhiteSpace([string]$state.hiProc.END_TIME_)) {
        throw "cancel did not complete historic process for $ProcessKey`: $($state.hiProc | ConvertTo-Json -Compress)"
    }
    if ([string]$state.vars.status.TEXT_ -ne 'cancel' -or [string]$state.vars.state.TEXT_ -ne 'cancel' -or [int]$state.vars.approval.LONG_ -ne 0 -or [int]$state.vars.cancel.LONG_ -ne 1) {
        throw "cancel variables mismatch for $ProcessKey`: $($state.vars | ConvertTo-Json -Compress)"
    }
}

function Test-QueryContainsProcess {
    param(
        [Parameter(Mandatory = $true)]$Response,
        [Parameter(Mandatory = $true)][string]$ProcessInstanceId
    )

    foreach ($item in @($Response.data)) {
        foreach ($id in @($item.processIdList)) {
            if ([string]$id -eq $ProcessInstanceId) {
                return $true
            }
        }
    }

    return $false
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
`$auth['device'] = 'CODEX_WORKFLOW_GENERAL_START_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => (string)`$user['ID'],
    'tenantId' => (string)(`$user['TENANT_ID'] ?? ''),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

$context = Invoke-PhpJson -Code $contextCode
$token = [string]$context.token
$userId = [string]$context.userId
$tenantId = [string]$context.tenantId
if ([string]::IsNullOrWhiteSpace($token) -or [string]::IsNullOrWhiteSpace($userId) -or [string]::IsNullOrWhiteSpace($tenantId)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$processIds = @()
$fileId = ''
$beforeCounts = Get-BusinessCounts

try {
    $noToken = Invoke-JsonPost -Url ($baseUrl + '/biz/process/payment/start') -Body @{}
    if ([int]$noToken.code -ne 401) {
        throw "/biz/process/payment/start no-token expected code=401, got code=$($noToken.code)"
    }
    Write-Host '/biz/process/payment/start no-token code=401'

    $missingApprove = Invoke-JsonPost -Url ($baseUrl + '/biz/process/payment/start') -Token $token -Body @{
        accountId = 'codex-account'
        settlementCategory = 'Collection'
        payerTime = '2099-03-01 09:00:00'
        amount = '1.00'
        treasurer = $userId
    }
    if ([int]$missingApprove.code -ne 400) {
        throw "payment start missing approver expected code=400, got code=$($missingApprove.code)"
    }
    Write-Host '/biz/process/payment/start missing-approve code=400'

    $missingAmount = Invoke-JsonPost -Url ($baseUrl + '/biz/process/payment/start') -Token $token -Body @{
        approveUserIdList = @($userId)
        copyUserIdList = @()
        accountId = 'codex-account'
        settlementCategory = 'Collection'
        payerTime = '2099-03-01 09:00:00'
        treasurer = $userId
    }
    if ([int]$missingAmount.code -ne 400) {
        $dataProperty = $missingAmount.PSObject.Properties['data']
        if ($null -ne $dataProperty -and $null -ne $dataProperty.Value) {
            $processIdProperty = $dataProperty.Value.PSObject.Properties['processInstanceId']
            if ($null -ne $processIdProperty -and -not [string]::IsNullOrWhiteSpace([string]$processIdProperty.Value)) {
                $processIds += [string]$processIdProperty.Value
            }
        }
        throw "payment start missing amount expected code=400, got code=$($missingAmount.code)"
    }
    Write-Host '/biz/process/payment/start missing-amount code=400'

    $file = New-SmokeFile -UserId $userId -TenantId $tenantId
    $fileId = [string]$file.id

    $markerPrefix = 'codex-general-start-' + ([Guid]::NewGuid().ToString('N').Substring(0, 10))
    $cases = @(
        [pscustomobject]@{
            Path = '/biz/process/payment/start'
            Key = 'Process_payment'
            ExpectedCc = 1
            ExpectedFile = 1
            Body = @{
                processSmokeMarker = "$markerPrefix-payment"
                approveUserIdList = @($userId)
                copyUserIdList = @($userId)
                fileIdList = @($fileId)
                accountId = 'codex-payment-account'
                settlementCategory = @('SETTLEMENT_ACCOUNT', 'INCOME_CATEGORY', 'Collection')
                payerTime = '2099-03-01 09:00:00'
                amount = '12.34'
                treasurer = $userId
                remark = 'codex payment start smoke'
            }
        },
        [pscustomobject]@{
            Path = '/biz/process/reimbursement/start'
            Key = 'Process_reimbursement'
            ExpectedCc = 0
            ExpectedFile = 0
            Body = @{
                processSmokeMarker = "$markerPrefix-reimbursement"
                approveUserIdList = @($userId)
                copyUserIdList = @()
                settlementCategory = 'TravelExpenses'
                amount = '23.45'
                bankAccount = '6222020202020202'
                bankName = 'codex bank'
                payer = 'codex payer'
                useAdvancePayment = $false
                treasurer = $userId
                remark = 'codex reimbursement start smoke'
            }
        },
        [pscustomobject]@{
            Path = '/biz/process/makePayment/start'
            Key = 'Process_make_payment'
            ExpectedCc = 0
            ExpectedFile = 0
            Body = @{
                processSmokeMarker = "$markerPrefix-make-payment"
                approveUserIdList = @($userId)
                copyUserIdList = @()
                settlementCategory = 'GOODS_EXPENDITURE'
                amount = '34.56'
                bankAccount = '6222030303030303'
                bankName = 'codex bank'
                payer = 'codex payee'
                useAdvancePayment = $false
                treasurer = $userId
                remark = 'codex make payment start smoke'
            }
        },
        [pscustomobject]@{
            Path = '/biz/process/procure/start'
            Key = 'Process_procure'
            ExpectedCc = 0
            ExpectedFile = 0
            Body = @{
                processSmokeMarker = "$markerPrefix-procure"
                approveUserIdList = @($userId)
                copyUserIdList = @()
                supplier = @{
                    name = 'codex supplier'
                    contacts = 'codex contact'
                }
                desirePurchaseDate = '2099-03-02 09:00:00'
                procure = $userId
                approvesGeneralOffice = @($userId)
                productInfoList = @(
                    @{
                        productName = 'codex product'
                        number = 1
                        amount = '1.00'
                    }
                )
                amount = '45.67'
                remark = 'codex procure start smoke'
            }
        },
        [pscustomobject]@{
            Path = '/biz/process/procure/warehouse/start'
            Key = 'Process_procure_in_warehouse'
            ExpectedCc = 0
            ExpectedFile = 0
            Body = @{
                processSmokeMarker = "$markerPrefix-procure-warehouse"
                approveUserIdList = @($userId)
                copyUserIdList = @()
                orderId = 'codex-order-id'
                warehousesId = 'codex-warehouse-id'
                logisticsId = 'codex-logistics-id'
                remark = 'codex procure warehouse start smoke'
            }
        }
    )

    foreach ($case in $cases) {
        $response = Invoke-JsonPost -Url ($baseUrl + $case.Path) -Token $token -Body $case.Body
        if ([int]$response.code -ne 200) {
            throw "$($case.Path) expected code=200, got code=$($response.code), message=$($response.message)"
        }

        $processInstanceId = [string]$response.data.processInstanceId
        $taskId = [string]$response.data.taskId
        if ([string]::IsNullOrWhiteSpace($processInstanceId) -or [string]::IsNullOrWhiteSpace($taskId)) {
            throw "$($case.Path) response missing processInstanceId or taskId"
        }
        if ([string]$response.data.processKey -ne [string]$case.Key) {
            throw "$($case.Path) processKey mismatch: $($response.data | ConvertTo-Json -Compress)"
        }
        $processIds += $processInstanceId

        Assert-StartedProcess -ProcessInstanceId $processInstanceId -ProcessKey ([string]$case.Key) -TaskId $taskId -Marker ([string]$case.Body.processSmokeMarker) -ExpectedCcCount ([int]$case.ExpectedCc) -ExpectedFileCount ([int]$case.ExpectedFile)
        Write-Host "$($case.Path) started processInstanceId=$processInstanceId taskId=$taskId"

        $activityDetail = Invoke-JsonGet -Url ($baseUrl + "/biz/task/runtime/activity/detail?id=$taskId") -Token $token
        if ([int]$activityDetail.code -ne 200 -or [string]$activityDetail.data.processKey -ne [string]$case.Key) {
            throw "runtime activity detail mismatch for $($case.Path): $($activityDetail | ConvertTo-Json -Compress)"
        }

        $encodedMarker = [System.Uri]::EscapeDataString([string]$case.Body.processSmokeMarker)
        $encodedKey = [System.Uri]::EscapeDataString([string]$case.Key)
        $query = Invoke-JsonGet -Url ($baseUrl + "/biz/process/query?variableName=processSmokeMarker&variable=$encodedMarker&processCategory=$encodedKey") -Token $token
        if ([int]$query.code -ne 200 -or -not (Test-QueryContainsProcess -Response $query -ProcessInstanceId $processInstanceId)) {
            throw "process query did not include $processInstanceId for $($case.Path)"
        }

        $approve = Invoke-JsonPost -Url ($baseUrl + '/biz/task/approve') -Token $token -Body @{
            id = $taskId
            form = @{
                approval = $true
                comment = 'codex non-leave approve smoke'
            }
        }
        if ([string]$case.Key -in @('Process_reimbursement', 'Process_make_payment')) {
            if ([int]$approve.code -ne 200) {
                throw "/biz/task/approve payment-out first step expected code=200, got code=$($approve.code), message=$($approve.message)"
            }
            $financeTaskId = [string]$approve.data.nextTaskId
            if ([string]::IsNullOrWhiteSpace($financeTaskId)) {
                throw "/biz/task/approve payment-out first step missing nextTaskId"
            }
            Assert-PaymentOutFinanceTask -ProcessInstanceId $processInstanceId -TaskId $financeTaskId -Assignee $userId

            $financeReject = Invoke-JsonPost -Url ($baseUrl + '/biz/task/reject') -Token $token -Body @{
                id = $financeTaskId
                form = @{
                    comment = 'codex payment-out finance reject should not write business rows'
                }
            }
            if ([int]$financeReject.code -ne 200 -or [string]$financeReject.data.status -ne 'REJECT' -or [string]$financeReject.data.processKey -ne [string]$case.Key) {
                throw "/biz/task/reject payment-out finance mismatch for $($case.Key): $($financeReject | ConvertTo-Json -Compress)"
            }
            Assert-ProcessRejected -ProcessInstanceId $processInstanceId -ProcessKey ([string]$case.Key)
            Write-Host "$($case.Key) first approval advanced to finance task; finance reject closed without business rows"
            continue
        }

        if ([string]$case.Key -eq 'Process_procure') {
            if ([int]$approve.code -ne 200) {
                throw "/biz/task/approve procure first step expected code=200, got code=$($approve.code), message=$($approve.message)"
            }
            $procureTaskId = [string]$approve.data.nextTaskId
            if ([string]::IsNullOrWhiteSpace($procureTaskId)) {
                throw "/biz/task/approve procure first step missing nextTaskId"
            }
            Assert-ProcureConfirmationTask -ProcessInstanceId $processInstanceId -TaskId $procureTaskId -Assignee $userId

            $procureReject = Invoke-JsonPost -Url ($baseUrl + '/biz/task/reject') -Token $token -Body @{
                id = $procureTaskId
                form = @{
                    comment = 'codex procure confirmation reject should not write business rows'
                }
            }
            if ([int]$procureReject.code -ne 200 -or [string]$procureReject.data.status -ne 'REJECT' -or [string]$procureReject.data.processKey -ne [string]$case.Key) {
                throw "/biz/task/reject procure confirmation mismatch: $($procureReject | ConvertTo-Json -Compress)"
            }
            Assert-ProcessRejected -ProcessInstanceId $processInstanceId -ProcessKey ([string]$case.Key)
            Write-Host "$($case.Key) first approval advanced to procurement task; procurement reject closed without business rows"
            continue
        }

        $expectedApproveCodes = @(400)
        if ([string]$case.Key -in @('Process_payment', 'Process_procure_in_warehouse')) {
            $expectedApproveCodes = @(400, 404)
        }
        if ($expectedApproveCodes -notcontains [int]$approve.code) {
            throw "/biz/task/approve non-leave expected code in [$($expectedApproveCodes -join ',')], got code=$($approve.code)"
        }
        Assert-ProcessStillActive -ProcessInstanceId $processInstanceId
        if ([string]$case.Key -in @('Process_payment', 'Process_procure_in_warehouse')) {
            Write-Host "$($case.Key) approve with smoke-only business refs rolled back"
        } else {
            Write-Host "$($case.Key) non-leave approval remains deferred"
        }

        $cancel = Invoke-JsonPost -Url ($baseUrl + '/biz/process/cancel') -Token $token -Body @{ id = $processInstanceId }
        if ([int]$cancel.code -ne 200 -or [string]$cancel.data.status -ne 'cancel' -or [string]$cancel.data.processKey -ne [string]$case.Key) {
            throw "/biz/process/cancel mismatch for $($case.Key): $($cancel | ConvertTo-Json -Compress)"
        }
        Assert-ProcessCancelled -ProcessInstanceId $processInstanceId -ProcessKey ([string]$case.Key)
        Write-Host "$($case.Key) cancel closed runtime"
    }

    $afterCounts = Get-BusinessCounts
    Assert-BusinessCountsEqual -Expected $beforeCounts -Actual $afterCounts
    Write-Host 'business side-effect table counts unchanged'
} finally {
    foreach ($processId in $processIds) {
        Remove-SmokeProcess -ProcessInstanceId $processId
    }
    Remove-SmokeFile -FileId $fileId
}

Write-Host 'workflow general start HTTP smoke passed'
