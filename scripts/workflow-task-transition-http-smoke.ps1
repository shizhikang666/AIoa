param(
    [string]$BackendBaseUrl = 'http://127.0.0.1:82',
    [string]$EnvPath = (Join-Path (Resolve-Path (Join-Path $PSScriptRoot '..')) '.env')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $ProjectRoot
$script:SmokeLeaveDayOffset = 0

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

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("workflow-task-transition-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
echo json_encode(['id' => `$id, 'amount' => '$safeAmount', 'usedAmount' => '$safeUsedAmount'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
        [string]$Amount = '1.00'
    )

    $script:SmokeLeaveDayOffset += 1
    $date = ([datetime]'2099-01-01 09:00:00').AddDays($script:SmokeLeaveDayOffset)
    $startTime = $date.ToString('yyyy-MM-dd 09:00:00')
    $endTime = $date.ToString('yyyy-MM-dd 18:00:00')
    $response = Invoke-JsonPost -Url ($baseUrl + '/biz/process/leave/start') -Token $Token -Body @{
        category = $Category
        startTime = $startTime
        endTime = $endTime
        amount = $Amount
        approveUserIdList = @($UserId)
        copyUserIdList = @()
        fileIdList = @()
        remark = $Remark
    }
    if ([int]$response.code -ne 200) {
        throw "leave start expected code=200, got code=$($response.code), message=$($response.message)"
    }

    return @{
        processInstanceId = [string]$response.data.processInstanceId
        taskId = [string]$response.data.taskId
        category = $Category
        amount = $Amount
        startTime = $startTime
        endTime = $endTime
        remark = $Remark
    }
}

function Assert-TransitionState {
    param(
        [Parameter(Mandatory = $true)][string]$ProcessInstanceId,
        [Parameter(Mandatory = $true)][string]$TaskId,
        [Parameter(Mandatory = $true)][string]$ExpectedState,
        [Parameter(Mandatory = $true)][string]$ExpectedDeleteReason,
        [Parameter(Mandatory = $true)][string]$ExpectedComment
    )

    $safeProcessId = $ProcessInstanceId.Replace("'", "\'")
    $safeTaskId = $TaskId.Replace("'", "\'")
    $verifyCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safeProcessId';
`$tid = '$safeTaskId';
`$varRows = think\facade\Db::name('act_hi_varinst')
    ->where('PROC_INST_ID_', `$pid)
    ->whereIn('NAME_', ['status', 'state', 'approval', 'comment'])
    ->field('NAME_, VAR_TYPE_, LONG_, TEXT_, TEXT2_')
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
    'hiProc' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->field('STATE_, END_TIME_, DURATION_')->find(),
    'hiTask' => think\facade\Db::name('act_hi_taskinst')->where('ID_', `$tid)->field('END_TIME_, DURATION_, DELETE_REASON_')->find(),
    'hiAct' => think\facade\Db::name('act_hi_actinst')->where('TASK_ID_', `$tid)->field('END_TIME_, DURATION_, ACT_INST_STATE_')->find(),
    'vars' => `$vars,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $state = Invoke-PhpJson -Code $verifyCode
    if ([int]$state.ruTask -ne 0 -or [int]$state.ruVariable -ne 0 -or [int]$state.ruExecution -ne 0) {
        throw "runtime rows not cleared: $($state | ConvertTo-Json -Compress)"
    }
    if ([string]$state.hiProc.STATE_ -ne 'COMPLETED' -or [string]::IsNullOrWhiteSpace([string]$state.hiProc.END_TIME_)) {
        throw "historic process not completed: $($state.hiProc | ConvertTo-Json -Compress)"
    }
    if ([string]$state.hiTask.DELETE_REASON_ -ne $ExpectedDeleteReason -or [string]::IsNullOrWhiteSpace([string]$state.hiTask.END_TIME_)) {
        throw "historic task not completed as expected: $($state.hiTask | ConvertTo-Json -Compress)"
    }
    if ([int]$state.hiAct.ACT_INST_STATE_ -ne 4 -or [string]::IsNullOrWhiteSpace([string]$state.hiAct.END_TIME_)) {
        throw "historic activity not completed: $($state.hiAct | ConvertTo-Json -Compress)"
    }
    if ([string]$state.vars.status.TEXT_ -ne $ExpectedState -or [string]$state.vars.state.TEXT_ -ne $ExpectedState) {
        throw "history status/state mismatch: $($state.vars | ConvertTo-Json -Compress)"
    }
    $expectedApprovalLong = if ($ExpectedState -eq 'AGREE') { 1 } else { 0 }
    if ([int]$state.vars.approval.LONG_ -ne $expectedApprovalLong) {
        throw "history approval mismatch: $($state.vars.approval | ConvertTo-Json -Compress)"
    }
    if ([string]$state.vars.comment.TEXT_ -ne $ExpectedComment) {
        throw "history comment mismatch: $($state.vars.comment | ConvertTo-Json -Compress)"
    }
}

function Assert-LeaveApplicationState {
    param(
        [Parameter(Mandatory = $true)][string]$Token,
        [Parameter(Mandatory = $true)][string]$ProcessInstanceId,
        [Parameter(Mandatory = $true)][int]$ExpectedCount,
        [string]$ExpectedId = '',
        [string]$ExpectedUserId = '',
        [string]$ExpectedCategory = '',
        [string]$ExpectedAmount = '',
        [string]$ExpectedStartTime = '',
        [string]$ExpectedEndTime = '',
        [string]$ExpectedRemark = ''
    )

    $safeProcessId = $ProcessInstanceId.Replace("'", "\'")
    $queryCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safeProcessId';
`$rows = think\facade\Db::name('biz_leave_application')
    ->where('PROCESS_ID', `$pid)
    ->field('ID,USER_ID,PROCESS_ID,category,AMOUNT,REMARK,START_TIME,END_TIME,DELETE_FLAG,CREATE_USER,TENANT_ID,OBJECT_ID')
    ->select()
    ->toArray();
echo json_encode(['count' => count(`$rows), 'rows' => `$rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $state = Invoke-PhpJson -Code $queryCode
    if ([int]$state.count -ne $ExpectedCount) {
        throw "leave application count expected $ExpectedCount, got $($state.count): $($state | ConvertTo-Json -Compress)"
    }
    if ($ExpectedCount -eq 0) {
        return
    }

    $row = $state.rows[0]
    if ($ExpectedId.Trim() -ne '' -and [string]$row.ID -ne $ExpectedId) {
        throw "leave application id mismatch: $($row | ConvertTo-Json -Compress)"
    }
    if ([string]$row.USER_ID -ne $ExpectedUserId -or [string]$row.category -ne $ExpectedCategory) {
        throw "leave application user/category mismatch: $($row | ConvertTo-Json -Compress)"
    }
    if ([decimal]$row.AMOUNT -ne [decimal]$ExpectedAmount) {
        throw "leave application amount mismatch: $($row | ConvertTo-Json -Compress)"
    }
    if ([string]$row.START_TIME -ne $ExpectedStartTime -or [string]$row.END_TIME -ne $ExpectedEndTime) {
        throw "leave application time mismatch: $($row | ConvertTo-Json -Compress)"
    }
    if ([string]$row.REMARK -ne $ExpectedRemark -or [string]$row.DELETE_FLAG -ne 'NOT_DELETE') {
        throw "leave application remark/deleteFlag mismatch: $($row | ConvertTo-Json -Compress)"
    }

    $encodedProcessId = [System.Uri]::EscapeDataString($ProcessInstanceId)
    $page = Invoke-JsonGet -Url ($baseUrl + "/biz/bizleaveapplication/my/page?current=1&size=5&processId=$encodedProcessId") -Token $Token
    if ([int]$page.code -ne 200 -or -not (Test-RecordsContainProcess -Records $page.data.records -ProcessInstanceId $ProcessInstanceId)) {
        throw 'leave application my/page missing approved workflow row'
    }

    $encodedLeaveId = [System.Uri]::EscapeDataString([string]$row.ID)
    $detail = Invoke-JsonGet -Url ($baseUrl + "/biz/bizleaveapplication/detail?id=$encodedLeaveId") -Token $Token
    if ([int]$detail.code -ne 200 -or [string]$detail.data.processId -ne $ProcessInstanceId) {
        throw 'leave application detail missing approved workflow row'
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
    ->field('ID,AMOUNT,USED_AMOUNT,CATEGORY,DELETE_FLAG,VERSION')
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

function Assert-ActiveRuntimeTask {
    param([Parameter(Mandatory = $true)][string]$ProcessInstanceId)

    $safeProcessId = $ProcessInstanceId.Replace("'", "\'")
    $queryCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safeProcessId';
echo json_encode([
    'ruTask' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->count(),
    'hiProc' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->field('STATE_,END_TIME_')->find(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $state = Invoke-PhpJson -Code $queryCode
    if ([int]$state.ruTask -ne 1 -or [string]$state.hiProc.STATE_ -ne 'ACTIVE' -or -not [string]::IsNullOrWhiteSpace([string]$state.hiProc.END_TIME_)) {
        throw "runtime task was not preserved after failed transition: $($state | ConvertTo-Json -Compress)"
    }
}

function Remove-SmokePayroll {
    param(
        [Parameter(Mandatory = $true)][string]$UserId,
        [Parameter(Mandatory = $true)][string]$SalaryTime
    )

    if ([string]::IsNullOrWhiteSpace($UserId) -or [string]::IsNullOrWhiteSpace($SalaryTime)) {
        return
    }

    $safeUserId = $UserId.Replace("'", "\'")
    $safeSalaryTime = $SalaryTime.Replace("'", "\'")
    $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_payroll')->where('USER', '$safeUserId')->where('SALARY_TIME', '$safeSalaryTime')->delete();
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    [void](Invoke-PhpJson -Code $cleanupCode)
}

function Assert-WorkflowLeavePayrollGeneration {
    param(
        [Parameter(Mandatory = $true)][string]$Token,
        [Parameter(Mandatory = $true)][string]$UserId,
        [Parameter(Mandatory = $true)][string]$SalaryTime,
        [Parameter(Mandatory = $true)][string]$ExpectedVacation
    )

    Remove-SmokePayroll -UserId $UserId -SalaryTime $SalaryTime

    $generate = Invoke-JsonPost -Url ($baseUrl + '/biz/bizpayroll/generate/add') -Token $Token -Body @{
        user = @($UserId)
        salaryTime = $SalaryTime
        socialSecurity = '0.00'
    }
    if ([int]$generate.code -ne 200) {
        throw "payroll generate expected code=200, got code=$($generate.code), message=$($generate.message)"
    }

    $safeUserId = $UserId.Replace("'", "\'")
    $safeSalaryTime = $SalaryTime.Replace("'", "\'")
    $queryCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('biz_payroll')
    ->where('USER', '$safeUserId')
    ->where('SALARY_TIME', '$safeSalaryTime')
    ->field('ID,USER,SALARY_TIME,VACATION,VACATION_SUB_AMOUNT,PAYABLE_AMOUNT,ACTUAL_AMOUNT,DELETE_FLAG')
    ->find();
echo json_encode(['row' => `$row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $state = Invoke-PhpJson -Code $queryCode
    if ($null -eq $state.row) {
        throw 'payroll generate did not create row for workflow-approved leave user'
    }
    if ([string]$state.row.USER -ne $UserId -or [string]$state.row.SALARY_TIME -ne $SalaryTime -or [string]$state.row.DELETE_FLAG -ne 'NOT_DELETE') {
        throw "payroll generated row identity mismatch: $($state.row | ConvertTo-Json -Compress)"
    }
    if ([decimal]$state.row.VACATION -ne [decimal]$ExpectedVacation) {
        throw "payroll generated vacation expected $ExpectedVacation, got $($state.row.VACATION): $($state.row | ConvertTo-Json -Compress)"
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
`$auth['device'] = 'CODEX_WORKFLOW_TASK_TRANSITION_SMOKE';
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
$approvePath = '/biz/task/approve'
$rejectPath = '/biz/task/reject'
$approveProcessId = ''
$rejectProcessId = ''
$annualProcessId = ''
$annualVacationId = ''
$insufficientProcessId = ''
$insufficientVacationId = ''
$payrollSalaryTime = '2099-01-15 00:00:00'

try {
    foreach ($path in @($approvePath, $rejectPath)) {
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

    $approveStarted = Start-SmokeLeaveProcess -Token $token -UserId $userId -Remark 'codex workflow task approve smoke'
    $approveProcessId = [string]$approveStarted.processInstanceId
    $approveTaskId = [string]$approveStarted.taskId
    $approveComment = 'codex approve smoke'
    $approveResponse = Invoke-JsonPost -Url ($baseUrl + $approvePath) -Token $token -Body @{
        id = $approveTaskId
        form = @{
            approval = $true
            comment = $approveComment
        }
    }
    if ([int]$approveResponse.code -ne 200) {
        throw "$approvePath expected code=200, got code=$($approveResponse.code), message=$($approveResponse.message)"
    }
    Assert-TransitionState -ProcessInstanceId $approveProcessId -TaskId $approveTaskId -ExpectedState 'AGREE' -ExpectedDeleteReason 'completed' -ExpectedComment $approveComment
    Assert-LeaveApplicationState `
        -Token $token `
        -ProcessInstanceId $approveProcessId `
        -ExpectedCount 1 `
        -ExpectedId ([string]$approveResponse.data.leaveApplicationId) `
        -ExpectedUserId $userId `
        -ExpectedCategory ([string]$approveStarted.category) `
        -ExpectedAmount ([string]$approveStarted.amount) `
        -ExpectedStartTime ([string]$approveStarted.startTime) `
        -ExpectedEndTime ([string]$approveStarted.endTime) `
        -ExpectedRemark ([string]$approveStarted.remark)
    Write-Host '/biz/bizleaveapplication/detail reads approved leave application'
    Assert-WorkflowLeavePayrollGeneration -Token $token -UserId $userId -SalaryTime $payrollSalaryTime -ExpectedVacation ([string]$approveStarted.amount)
    Write-Host '/biz/bizpayroll/generate/add includes workflow-approved leave vacation'
    Write-Host "$approvePath completed processInstanceId=$approveProcessId taskId=$approveTaskId"

    $pendingAfterApprove = Invoke-JsonGet -Url ($baseUrl + "/biz/task/page?current=1&size=5&processInstanceId=$approveProcessId") -Token $token
    if ([int]$pendingAfterApprove.code -ne 200 -or (Test-RecordsContainProcess -Records $pendingAfterApprove.data.records -ProcessInstanceId $approveProcessId)) {
        throw 'pending task page still includes approved task'
    }
    $historyAfterApprove = Invoke-JsonGet -Url ($baseUrl + "/biz/task/history/page?current=1&size=5&processInstanceId=$approveProcessId") -Token $token
    if ([int]$historyAfterApprove.code -ne 200 -or -not (Test-RecordsContainProcess -Records $historyAfterApprove.data.records -ProcessInstanceId $approveProcessId)) {
        throw 'history task page missing approved task'
    }
    Write-Host '/biz/task/history/page includes approved task'

    $rejectStarted = Start-SmokeLeaveProcess -Token $token -UserId $userId -Remark 'codex workflow task reject smoke'
    $rejectProcessId = [string]$rejectStarted.processInstanceId
    $rejectTaskId = [string]$rejectStarted.taskId
    $rejectComment = 'codex reject smoke'
    $rejectResponse = Invoke-JsonPost -Url ($baseUrl + $rejectPath) -Token $token -Body @{
        id = $rejectTaskId
        comment = $rejectComment
    }
    if ([int]$rejectResponse.code -ne 200) {
        throw "$rejectPath expected code=200, got code=$($rejectResponse.code), message=$($rejectResponse.message)"
    }
    Assert-TransitionState -ProcessInstanceId $rejectProcessId -TaskId $rejectTaskId -ExpectedState 'REJECT' -ExpectedDeleteReason 'deleted' -ExpectedComment $rejectComment
    Assert-LeaveApplicationState -Token $token -ProcessInstanceId $rejectProcessId -ExpectedCount 0
    Write-Host "$rejectPath completed processInstanceId=$rejectProcessId taskId=$rejectTaskId"

    $historyAfterReject = Invoke-JsonGet -Url ($baseUrl + "/biz/task/history/page?current=1&size=5&processInstanceId=$rejectProcessId") -Token $token
    if ([int]$historyAfterReject.code -ne 200 -or -not (Test-RecordsContainProcess -Records $historyAfterReject.data.records -ProcessInstanceId $rejectProcessId)) {
        throw 'history task page missing rejected task'
    }
    Write-Host '/biz/task/history/page includes rejected task'

    $annualVacation = New-SmokeAnnualVacation -UserId $userId -TenantId $tenantId -Amount '8.00' -UsedAmount '1.25'
    $annualVacationId = [string]$annualVacation.id
    $annualStarted = Start-SmokeLeaveProcess -Token $token -UserId $userId -Remark 'codex workflow annual leave approve smoke' -Category 'annualLeave' -Amount '1.50'
    $annualProcessId = [string]$annualStarted.processInstanceId
    $annualTaskId = [string]$annualStarted.taskId
    $annualComment = 'codex annual approve smoke'
    $annualResponse = Invoke-JsonPost -Url ($baseUrl + $approvePath) -Token $token -Body @{
        id = $annualTaskId
        form = @{
            approval = $true
            comment = $annualComment
        }
    }
    if ([int]$annualResponse.code -ne 200) {
        throw "annual $approvePath expected code=200, got code=$($annualResponse.code), message=$($annualResponse.message)"
    }
    Assert-TransitionState -ProcessInstanceId $annualProcessId -TaskId $annualTaskId -ExpectedState 'AGREE' -ExpectedDeleteReason 'completed' -ExpectedComment $annualComment
    Assert-LeaveApplicationState `
        -Token $token `
        -ProcessInstanceId $annualProcessId `
        -ExpectedCount 1 `
        -ExpectedId ([string]$annualResponse.data.leaveApplicationId) `
        -ExpectedUserId $userId `
        -ExpectedCategory ([string]$annualStarted.category) `
        -ExpectedAmount ([string]$annualStarted.amount) `
        -ExpectedStartTime ([string]$annualStarted.startTime) `
        -ExpectedEndTime ([string]$annualStarted.endTime) `
        -ExpectedRemark ([string]$annualStarted.remark)
    if ([string]$annualResponse.data.vacationDeduction.id -ne $annualVacationId) {
        throw "annual vacation deduction id mismatch: $($annualResponse.data.vacationDeduction | ConvertTo-Json -Compress)"
    }
    Assert-VacationState -VacationId $annualVacationId -ExpectedUsedAmount '2.75' -ExpectedVersion 1
    Write-Host "$approvePath annual leave deducted vacationId=$annualVacationId"

    $insufficientVacation = New-SmokeAnnualVacation -UserId $userId -TenantId $tenantId -Amount '1.00' -UsedAmount '0.75'
    $insufficientVacationId = [string]$insufficientVacation.id
    $insufficientStarted = Start-SmokeLeaveProcess -Token $token -UserId $userId -Remark 'codex workflow annual leave insufficient smoke' -Category 'annualLeave' -Amount '1.00'
    $insufficientProcessId = [string]$insufficientStarted.processInstanceId
    $insufficientTaskId = [string]$insufficientStarted.taskId
    $insufficientResponse = Invoke-JsonPost -Url ($baseUrl + $approvePath) -Token $token -Body @{
        id = $insufficientTaskId
        form = @{
            approval = $true
            comment = 'codex insufficient annual approve smoke'
        }
    }
    if ([int]$insufficientResponse.code -ne 400) {
        throw "annual insufficient $approvePath expected code=400, got code=$($insufficientResponse.code), message=$($insufficientResponse.message)"
    }
    Assert-ActiveRuntimeTask -ProcessInstanceId $insufficientProcessId
    Assert-LeaveApplicationState -Token $token -ProcessInstanceId $insufficientProcessId -ExpectedCount 0
    Assert-VacationState -VacationId $insufficientVacationId -ExpectedUsedAmount '0.75' -ExpectedVersion 0
    Write-Host "$approvePath annual leave insufficient balance rolled back"
} finally {
    Remove-SmokePayroll -UserId $userId -SalaryTime $payrollSalaryTime
    Remove-SmokeProcess -ProcessInstanceId $approveProcessId
    Remove-SmokeProcess -ProcessInstanceId $rejectProcessId
    Remove-SmokeProcess -ProcessInstanceId $annualProcessId
    Remove-SmokeProcess -ProcessInstanceId $insufficientProcessId
    Remove-SmokeVacation -VacationId $annualVacationId
    Remove-SmokeVacation -VacationId $insufficientVacationId
}

Write-Host 'workflow task transition HTTP smoke passed'
