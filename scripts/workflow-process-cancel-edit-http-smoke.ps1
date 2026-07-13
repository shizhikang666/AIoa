param(
    [string]$BackendBaseUrl = 'http://127.0.0.1:82',
    [string]$EnvPath = (Join-Path (Resolve-Path (Join-Path $PSScriptRoot '..')) '.env')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $ProjectRoot
$script:SmokeLeaveDayOffset = 20

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

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("workflow-process-cancel-edit-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
    think\facade\Db::name('biz_leave_application')->where('PROCESS_ID', `$pid)->delete();
});
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    [void](Invoke-PhpJson -Code $cleanupCode)
}

function New-SmokeAnnualVacation {
    param(
        [Parameter(Mandatory = $true)][string]$UserId,
        [Parameter(Mandatory = $true)][string]$TenantId,
        [Parameter(Mandatory = $true)][string]$Amount,
        [Parameter(Mandatory = $true)][string]$UsedAmount
    )

    $safeUserId = $UserId.Replace("'", "\'")
    $safeTenantId = $TenantId.Replace("'", "\'")
    $safeAmount = $Amount.Replace("'", "\'")
    $safeUsedAmount = $UsedAmount.Replace("'", "\'")
    $insertCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$id = (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('biz_user_vacation')->insert([
    'ID' => `$id,
    'USER_ID' => '$safeUserId',
    'AMOUNT' => '$safeAmount',
    'USED_AMOUNT' => '$safeUsedAmount',
    'CATEGORY' => 'annualLeave',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => '$safeUserId',
    'UPDATE_TIME' => null,
    'UPDATE_USER' => null,
    'TENANT_ID' => '$safeTenantId',
    'VERSION' => 0,
]);
echo json_encode(['id' => `$id], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    return Invoke-PhpJson -Code $insertCode
}

function Remove-SmokeVacation {
    param([string]$VacationId)

    if ([string]::IsNullOrWhiteSpace($VacationId)) {
        return
    }

    $safeVacationId = $VacationId.Replace("'", "\'")
    $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_user_vacation')->where('ID', '$safeVacationId')->delete();
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    [void](Invoke-PhpJson -Code $cleanupCode)
}

function Start-SmokeLeaveProcess {
    param(
        [Parameter(Mandatory = $true)][string]$Token,
        [Parameter(Mandatory = $true)][string]$UserId,
        [Parameter(Mandatory = $true)][string]$Remark,
        [string]$Category = 'leaveOfAbsence',
        [string]$Amount = '1.00',
        [switch]$WithoutEndTime
    )

    $script:SmokeLeaveDayOffset += 1
    $date = ([datetime]'2099-02-01 09:00:00').AddDays($script:SmokeLeaveDayOffset)
    $startTime = $date.ToString('yyyy-MM-dd 09:00:00')
    $endTime = $date.ToString('yyyy-MM-dd 18:00:00')
    $body = @{
        category = $Category
        startTime = $startTime
        amount = $Amount
        approveUserIdList = @($UserId)
        copyUserIdList = @()
        fileIdList = @()
        remark = $Remark
    }
    if (-not $WithoutEndTime) {
        $body.endTime = $endTime
    }

    $response = Invoke-JsonPost -Url ($baseUrl + '/biz/process/leave/start') -Token $Token -Body $body
    if ([int]$response.code -ne 200) {
        throw "leave start expected code=200, got code=$($response.code), message=$($response.message)"
    }

    return @{
        processInstanceId = [string]$response.data.processInstanceId
        taskId = [string]$response.data.taskId
        category = $Category
        amount = $Amount
        startTime = $startTime
        endTime = $(if ($WithoutEndTime) { '' } else { $endTime })
        remark = $Remark
    }
}

function Assert-EditedVariables {
    param(
        [Parameter(Mandatory = $true)][string]$ProcessInstanceId,
        [Parameter(Mandatory = $true)][string]$ExpectedEndTime,
        [Parameter(Mandatory = $true)][string]$ExpectedAmount,
        [Parameter(Mandatory = $true)][string]$ExpectedRemark
    )

    $safeProcessId = $ProcessInstanceId.Replace("'", "\'")
    $queryCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safeProcessId';
`$names = ['endTime', 'amount', 'remark', 'isEdit'];
`$runtimeRows = think\facade\Db::name('act_ru_variable')
    ->where('PROC_INST_ID_', `$pid)
    ->whereIn('NAME_', `$names)
    ->field('NAME_,TYPE_,LONG_,TEXT_,TEXT2_')
    ->select()
    ->toArray();
`$historyRows = think\facade\Db::name('act_hi_varinst')
    ->where('PROC_INST_ID_', `$pid)
    ->whereIn('NAME_', `$names)
    ->field('NAME_,VAR_TYPE_,LONG_,TEXT_,TEXT2_')
    ->select()
    ->toArray();
`$normalize = function (array `$rows): array {
    `$values = [];
    foreach (`$rows as `$row) {
        `$name = `$row['NAME_'];
        if (`$name === 'endTime') {
            `$values[`$name] = date('Y-m-d H:i:s', intdiv((int)`$row['LONG_'], 1000));
        } elseif (`$name === 'isEdit') {
            `$values[`$name] = (int)`$row['LONG_'];
        } else {
            `$values[`$name] = `$row['TEXT_'] ?? null;
        }
    }
    return `$values;
};
echo json_encode([
    'runtime' => `$normalize(`$runtimeRows),
    'history' => `$normalize(`$historyRows),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $state = Invoke-PhpJson -Code $queryCode
    foreach ($scope in @('runtime', 'history')) {
        $vars = $state.$scope
        if ([string]$vars.endTime -ne $ExpectedEndTime -or [decimal]$vars.amount -ne [decimal]$ExpectedAmount -or [string]$vars.remark -ne $ExpectedRemark -or [int]$vars.isEdit -ne 0) {
            throw "$scope edited variables mismatch: $($vars | ConvertTo-Json -Compress)"
        }
    }
}

function Assert-ProcessCancelled {
    param(
        [Parameter(Mandatory = $true)][string]$ProcessInstanceId,
        [Parameter(Mandatory = $true)][string]$TaskId
    )

    $safeProcessId = $ProcessInstanceId.Replace("'", "\'")
    $safeTaskId = $TaskId.Replace("'", "\'")
    $queryCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safeProcessId';
`$tid = '$safeTaskId';
`$varRows = think\facade\Db::name('act_hi_varinst')
    ->where('PROC_INST_ID_', `$pid)
    ->whereIn('NAME_', ['status', 'state', 'approval', 'cancel'])
    ->field('NAME_,VAR_TYPE_,LONG_,TEXT_')
    ->select()
    ->toArray();
`$vars = [];
foreach (`$varRows as `$row) {
    `$vars[`$row['NAME_']] = `$row;
}
echo json_encode([
    'ruTask' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->count(),
    'ruVariable' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$pid)->count(),
    'ruExecution' => think\facade\Db::name('act_ru_execution')->where('PROC_INST_ID_', `$pid)->count(),
    'leaveCount' => think\facade\Db::name('biz_leave_application')->where('PROCESS_ID', `$pid)->count(),
    'hiProc' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->field('STATE_,END_TIME_,END_ACT_ID_')->find(),
    'hiTask' => think\facade\Db::name('act_hi_taskinst')->where('ID_', `$tid)->field('END_TIME_,DELETE_REASON_')->find(),
    'vars' => `$vars,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $state = Invoke-PhpJson -Code $queryCode
    if ([int]$state.ruTask -ne 0 -or [int]$state.ruVariable -ne 0 -or [int]$state.ruExecution -ne 0) {
        throw "cancel did not clear runtime rows: $($state | ConvertTo-Json -Compress)"
    }
    if ([int]$state.leaveCount -ne 0) {
        throw "cancel should not create leave application: $($state | ConvertTo-Json -Compress)"
    }
    if ([string]$state.hiProc.STATE_ -ne 'COMPLETED' -or [string]::IsNullOrWhiteSpace([string]$state.hiProc.END_TIME_)) {
        throw "cancel did not complete historic process: $($state.hiProc | ConvertTo-Json -Compress)"
    }
    if ([string]$state.hiTask.DELETE_REASON_ -ne 'deleted' -or [string]::IsNullOrWhiteSpace([string]$state.hiTask.END_TIME_)) {
        throw "cancel did not close historic task: $($state.hiTask | ConvertTo-Json -Compress)"
    }
    if ([string]$state.vars.status.TEXT_ -ne 'cancel' -or [string]$state.vars.state.TEXT_ -ne 'cancel') {
        throw "cancel status mismatch: $($state.vars | ConvertTo-Json -Compress)"
    }
    if ([int]$state.vars.approval.LONG_ -ne 0 -or [int]$state.vars.cancel.LONG_ -ne 1) {
        throw "cancel flags mismatch: $($state.vars | ConvertTo-Json -Compress)"
    }
}

function Assert-LeaveApplicationState {
    param(
        [Parameter(Mandatory = $true)][string]$ProcessInstanceId,
        [Parameter(Mandatory = $true)][string]$ExpectedAmount,
        [Parameter(Mandatory = $true)][string]$ExpectedEndTime,
        [Parameter(Mandatory = $true)][string]$ExpectedRemark
    )

    $safeProcessId = $ProcessInstanceId.Replace("'", "\'")
    $queryCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('biz_leave_application')
    ->where('PROCESS_ID', '$safeProcessId')
    ->field('ID,PROCESS_ID,category,AMOUNT,REMARK,END_TIME,DELETE_FLAG')
    ->find();
echo json_encode(['row' => `$row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $state = Invoke-PhpJson -Code $queryCode
    if ($null -eq $state.row) {
        throw "leave application missing for $ProcessInstanceId"
    }
    if ([string]$state.row.category -ne 'annualLeave' -or [decimal]$state.row.AMOUNT -ne [decimal]$ExpectedAmount -or [string]$state.row.END_TIME -ne $ExpectedEndTime -or [string]$state.row.REMARK -ne $ExpectedRemark -or [string]$state.row.DELETE_FLAG -ne 'NOT_DELETE') {
        throw "leave application mismatch: $($state.row | ConvertTo-Json -Compress)"
    }
}

function Assert-VacationState {
    param(
        [Parameter(Mandatory = $true)][string]$VacationId,
        [Parameter(Mandatory = $true)][string]$ExpectedUsedAmount,
        [Parameter(Mandatory = $true)][int]$ExpectedVersion
    )

    $safeVacationId = $VacationId.Replace("'", "\'")
    $queryCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('biz_user_vacation')
    ->where('ID', '$safeVacationId')
    ->field('ID,USED_AMOUNT,VERSION')
    ->find();
echo json_encode(['row' => `$row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $state = Invoke-PhpJson -Code $queryCode
    if ($null -eq $state.row) {
        throw "vacation row missing: $VacationId"
    }
    if ([decimal]$state.row.USED_AMOUNT -ne [decimal]$ExpectedUsedAmount -or [int]$state.row.VERSION -ne $ExpectedVersion) {
        throw "vacation row mismatch: $($state.row | ConvertTo-Json -Compress)"
    }
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
`$auth['device'] = 'CODEX_WORKFLOW_PROCESS_CANCEL_EDIT_SMOKE';
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
$cancelPath = '/biz/process/cancel'
$editPath = '/biz/process/leave/edit'
$approvePath = '/biz/task/approve'
$cancelProcessId = ''
$cancelVacationId = ''
$editProcessId = ''
$editVacationId = ''
$notEditableProcessId = ''

try {
    foreach ($path in @($cancelPath, $editPath)) {
        $noToken = Invoke-JsonPost -Url ($baseUrl + $path) -Body @{}
        if ([int]$noToken.code -ne 401) {
            throw "$path no-token expected code=401, got code=$($noToken.code)"
        }
        Write-Host "$path no-token code=401"

        $missingId = Invoke-JsonPost -Url ($baseUrl + $path) -Token $token -Body @{}
        if ([int]$missingId.code -ne 400) {
            throw "$path missing-id expected code=400, got code=$($missingId.code)"
        }
        Write-Host "$path missing-id code=400"
    }

    $cancelVacation = New-SmokeAnnualVacation -UserId $userId -TenantId $tenantId -Amount '8.00' -UsedAmount '0.50'
    $cancelVacationId = [string]$cancelVacation.id
    $cancelStarted = Start-SmokeLeaveProcess -Token $token -UserId $userId -Remark 'codex workflow process cancel smoke' -Category 'annualLeave' -Amount '1.25'
    $cancelProcessId = [string]$cancelStarted.processInstanceId
    $cancelTaskId = [string]$cancelStarted.taskId
    $cancelResponse = Invoke-JsonPost -Url ($baseUrl + $cancelPath) -Token $token -Body @{ id = $cancelProcessId }
    if ([int]$cancelResponse.code -ne 200 -or [string]$cancelResponse.data.status -ne 'cancel') {
        throw "$cancelPath expected code=200/status=cancel, got $($cancelResponse | ConvertTo-Json -Compress)"
    }
    Assert-ProcessCancelled -ProcessInstanceId $cancelProcessId -TaskId $cancelTaskId
    Assert-VacationState -VacationId $cancelVacationId -ExpectedUsedAmount '0.50' -ExpectedVersion 0
    Write-Host "$cancelPath completed processInstanceId=$cancelProcessId"

    $editVacation = New-SmokeAnnualVacation -UserId $userId -TenantId $tenantId -Amount '8.00' -UsedAmount '1.00'
    $editVacationId = [string]$editVacation.id
    $editStarted = Start-SmokeLeaveProcess -Token $token -UserId $userId -Remark 'codex workflow leave edit smoke initial' -Category 'annualLeave' -Amount '1.00' -WithoutEndTime
    $editProcessId = [string]$editStarted.processInstanceId
    $editTaskId = [string]$editStarted.taskId
    $editedEndTime = ([datetime]$editStarted.startTime).ToString('yyyy-MM-dd 18:00:00')
    $editedRemark = 'codex workflow leave edit smoke edited'
    $editResponse = Invoke-JsonPost -Url ($baseUrl + $editPath) -Token $token -Body @{
        id = $editProcessId
        endTime = $editedEndTime
        amount = '2.25'
        remark = $editedRemark
    }
    if ([int]$editResponse.code -ne 200 -or [bool]$editResponse.data.isEdit -ne $false) {
        throw "$editPath expected code=200/isEdit=false, got $($editResponse | ConvertTo-Json -Compress)"
    }
    Assert-EditedVariables -ProcessInstanceId $editProcessId -ExpectedEndTime $editedEndTime -ExpectedAmount '2.25' -ExpectedRemark $editedRemark
    Write-Host "$editPath updated runtime variables processInstanceId=$editProcessId"

    $secondEdit = Invoke-JsonPost -Url ($baseUrl + $editPath) -Token $token -Body @{
        id = $editProcessId
        endTime = $editedEndTime
        amount = '2.50'
        remark = 'codex second edit should fail'
    }
    if ([int]$secondEdit.code -ne 400) {
        throw "$editPath second edit expected code=400, got code=$($secondEdit.code), message=$($secondEdit.message)"
    }
    Write-Host "$editPath second edit rejected"

    $approveResponse = Invoke-JsonPost -Url ($baseUrl + $approvePath) -Token $token -Body @{
        id = $editTaskId
        form = @{
            approval = $true
            comment = 'codex approve after leave edit smoke'
        }
    }
    if ([int]$approveResponse.code -ne 200) {
        throw "$approvePath after edit expected code=200, got code=$($approveResponse.code), message=$($approveResponse.message)"
    }
    Assert-LeaveApplicationState -ProcessInstanceId $editProcessId -ExpectedAmount '2.25' -ExpectedEndTime $editedEndTime -ExpectedRemark $editedRemark
    Assert-VacationState -VacationId $editVacationId -ExpectedUsedAmount '3.25' -ExpectedVersion 1
    Write-Host "$approvePath used edited leave amount"

    $notEditableStarted = Start-SmokeLeaveProcess -Token $token -UserId $userId -Remark 'codex workflow leave noneditable smoke' -Category 'leaveOfAbsence' -Amount '1.00'
    $notEditableProcessId = [string]$notEditableStarted.processInstanceId
    $notEditableResponse = Invoke-JsonPost -Url ($baseUrl + $editPath) -Token $token -Body @{
        id = $notEditableProcessId
        endTime = $notEditableStarted.endTime
        amount = '1.50'
        remark = 'codex noneditable edit should fail'
    }
    if ([int]$notEditableResponse.code -ne 400) {
        throw "$editPath noneditable expected code=400, got code=$($notEditableResponse.code), message=$($notEditableResponse.message)"
    }
    Write-Host "$editPath noneditable process rejected"
} finally {
    Remove-SmokeProcess -ProcessInstanceId $cancelProcessId
    Remove-SmokeProcess -ProcessInstanceId $editProcessId
    Remove-SmokeProcess -ProcessInstanceId $notEditableProcessId
    Remove-SmokeVacation -VacationId $cancelVacationId
    Remove-SmokeVacation -VacationId $editVacationId
}

Write-Host 'workflow process cancel/edit HTTP smoke passed'
