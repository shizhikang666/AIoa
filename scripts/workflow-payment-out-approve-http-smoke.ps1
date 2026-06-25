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

function Invoke-PhpJson {
    param([Parameter(Mandatory = $true)][string]$Code)

    $raw = (& php -r $Code)
    if ($LASTEXITCODE -ne 0) {
        throw 'php code failed'
    }

    return (($raw -join "`n").TrimStart([char]0xFEFF) | ConvertFrom-Json)
}

function Invoke-JsonPost {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [string]$Token = '',
        [object]$Body = @{}
    )

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("workflow-payment-out-approve-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    $json = $Body | ConvertTo-Json -Depth 12
    $utf8NoBom = New-Object System.Text.UTF8Encoding -ArgumentList $false
    [System.IO.File]::WriteAllText($bodyPath, $json, $utf8NoBom)
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

function Assert-Equal {
    param(
        [Parameter(Mandatory = $true)][string]$Actual,
        [Parameter(Mandatory = $true)][string]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    if ($Actual -ne $Expected) {
        throw "$Name expected=$Expected actual=$Actual"
    }
}

function Assert-IntEqual {
    param(
        [Parameter(Mandatory = $true)][int]$Actual,
        [Parameter(Mandatory = $true)][int]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    if ($Actual -ne $Expected) {
        throw "$Name expected=$Expected actual=$Actual"
    }
}

function Assert-DecimalEqual {
    param(
        [Parameter(Mandatory = $true)][string]$Actual,
        [Parameter(Mandatory = $true)][decimal]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    if ([decimal]$Actual -ne $Expected) {
        throw "$Name expected=$Expected actual=$Actual"
    }
}

function Remove-SmokeRows {
    param(
        [string]$AccountId,
        [string[]]$ProcessInstanceIds
    )

    if ([string]::IsNullOrWhiteSpace($AccountId) -and $ProcessInstanceIds.Count -eq 0) {
        return
    }

    $safeAccountId = $AccountId.Replace("'", "\'")
    $safeProcessIds = @($ProcessInstanceIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
    $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$accountId = '$safeAccountId';
`$pids = [$safeProcessIds];
if (`$pids !== []) {
    think\facade\Db::name('act_ru_task')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_ru_variable')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_varinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_taskinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_actinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_procinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_ru_execution')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('biz_cc_records')->whereIn('INSTANCE_ID', `$pids)->delete();
    think\facade\Db::name('biz_file_relation')->whereIn('OBJECT_ID', `$pids)->delete();
    think\facade\Db::name('biz_expenditure_record')->whereIn('PROCESS_ID', `$pids)->delete();
    think\facade\Db::name('settlement_account_statement')->whereIn('PROCESS_ID', `$pids)->delete();
}
if (`$accountId !== '') {
    think\facade\Db::name('biz_expenditure_record')->where('TARGET_ID', `$accountId)->delete();
    think\facade\Db::name('settlement_account_statement')->where('ACCOUNT_ID', `$accountId)->delete();
    think\facade\Db::name('settlement_account')->where('ID', `$accountId)->delete();
}
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    [void](Invoke-PhpJson -Code $cleanupCode)
}

function Get-WorkflowState {
    param(
        [Parameter(Mandatory = $true)][string]$ProcessInstanceId,
        [Parameter(Mandatory = $true)][string]$AccountId
    )

    $safePid = $ProcessInstanceId.Replace("'", "\'")
    $safeAccountId = $AccountId.Replace("'", "\'")
    $stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safePid';
`$accountId = '$safeAccountId';
`$task = think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->find();
`$expenditure = think\facade\Db::name('biz_expenditure_record')->where('PROCESS_ID', `$pid)->find();
`$statement = think\facade\Db::name('settlement_account_statement')->where('PROCESS_ID', `$pid)->find();
`$account = think\facade\Db::name('settlement_account')->where('ID', `$accountId)->find();
`$vars = [];
foreach (think\facade\Db::name('act_hi_varinst')->where('PROC_INST_ID_', `$pid)->whereIn('NAME_', ['approval', 'status', 'state', 'accountId', 'payerTime', 'settlementCategory'])->select()->toArray() as `$row) {
    `$name = (string)(`$row['NAME_'] ?? '');
    `$type = (string)(`$row['VAR_TYPE_'] ?? '');
    if (`$type === 'boolean') {
        `$vars[`$name] = ((int)(`$row['LONG_'] ?? 0)) === 1 ? 'true' : 'false';
    } elseif (`$type === 'date') {
        `$millis = (int)(`$row['LONG_'] ?? 0);
        `$vars[`$name] = `$millis > 0 ? date('Y-m-d H:i:s', intdiv(`$millis, 1000)) : '';
    } else {
        `$vars[`$name] = (string)(`$row['TEXT_'] ?? '');
    }
}
echo json_encode([
    'runtime' => [
        'taskCount' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->count(),
        'variableCount' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$pid)->count(),
        'executionCount' => think\facade\Db::name('act_ru_execution')->where('PROC_INST_ID_', `$pid)->count(),
        'taskId' => (string)(`$task['ID_'] ?? ''),
        'taskDefinitionKey' => (string)(`$task['TASK_DEF_KEY_'] ?? ''),
        'assignee' => (string)(`$task['ASSIGNEE_'] ?? ''),
    ],
    'history' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->field('STATE_,END_ACT_ID_,END_TIME_')->find(),
    'variables' => `$vars,
    'account' => [
        'amount' => (string)(`$account['CURRENT_AMOUNT'] ?? ''),
        'tenantId' => (string)(`$account['TENANT_ID'] ?? ''),
    ],
    'expenditureCount' => think\facade\Db::name('biz_expenditure_record')->where('PROCESS_ID', `$pid)->count(),
    'statementCount' => think\facade\Db::name('settlement_account_statement')->where('PROCESS_ID', `$pid)->count(),
    'expenditure' => [
        'id' => (string)(`$expenditure['ID'] ?? ''),
        'objectId' => (string)(`$expenditure['OBJECT_ID'] ?? ''),
        'targetId' => (string)(`$expenditure['TARGET_ID'] ?? ''),
        'serialId' => (string)(`$expenditure['SERIAL_ID'] ?? ''),
        'processId' => (string)(`$expenditure['PROCESS_ID'] ?? ''),
        'settlementCategory' => (string)(`$expenditure['SETTLEMENT_CATEGORY'] ?? ''),
        'payer' => (string)(`$expenditure['PAYER'] ?? ''),
        'bankName' => (string)(`$expenditure['BANK_NAME'] ?? ''),
        'bankAccount' => (string)(`$expenditure['BANK_ACCOUNT'] ?? ''),
        'remark' => (string)(`$expenditure['REMARK'] ?? ''),
        'payerTime' => (string)(`$expenditure['PAYER_TIME'] ?? ''),
        'amount' => (string)(`$expenditure['AMOUNT'] ?? ''),
        'tenantId' => (string)(`$expenditure['TENANT_ID'] ?? ''),
        'user' => (string)(`$expenditure['USER'] ?? ''),
        'org' => (string)(`$expenditure['ORG'] ?? ''),
        'deleteFlag' => (string)(`$expenditure['DELETE_FLAG'] ?? ''),
    ],
    'statement' => [
        'id' => (string)(`$statement['ID'] ?? ''),
        'accountId' => (string)(`$statement['ACCOUNT_ID'] ?? ''),
        'processId' => (string)(`$statement['PROCESS_ID'] ?? ''),
        'beforeAmount' => (string)(`$statement['BEFORE_AMOUNT'] ?? ''),
        'amount' => (string)(`$statement['AMOUNT'] ?? ''),
        'afterAmount' => (string)(`$statement['AFTER_AMOUNT'] ?? ''),
        'settlementType' => (string)(`$statement['SETTLEMENT_TYPE'] ?? ''),
        'settlementCategory' => (string)(`$statement['SETTLEMENT_CATEGORY'] ?? ''),
        'processCategory' => (string)(`$statement['PROCESS_CATEGORY'] ?? ''),
        'payerTime' => (string)(`$statement['PAYER_TIME'] ?? ''),
        'tenantId' => (string)(`$statement['TENANT_ID'] ?? ''),
        'deleteFlag' => (string)(`$statement['DELETE_FLAG'] ?? ''),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    return Invoke-PhpJson -Code $stateCode
}

function Invoke-ApproveCase {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$ProcessKey,
        [Parameter(Mandatory = $true)][string]$Amount,
        [Parameter(Mandatory = $true)][decimal]$ExpectedBefore,
        [Parameter(Mandatory = $true)][decimal]$ExpectedAfter,
        [Parameter(Mandatory = $true)][string]$ObjectId
    )

    $start = Invoke-JsonPost -Url ($baseUrl + $Path) -Token $token -Body @{
        processSmokeMarker = "$prefix-$ProcessKey"
        approveUserIdList = @($userId)
        copyUserIdList = @()
        amount = $Amount
        bankAccount = "codex-$ProcessKey-bank-account"
        bankName = "codex-$ProcessKey-bank"
        payer = "codex-$ProcessKey-payee"
        useAdvancePayment = $false
        treasurer = $userId
        objectId = $ObjectId
        remark = "$prefix-$ProcessKey"
    }
    if ([int]$start.code -ne 200) {
        throw "$ProcessKey start expected code=200, got code=$($start.code), message=$($start.message)"
    }

    $processInstanceId = [string]$start.data.processInstanceId
    $firstTaskId = [string]$start.data.taskId
    if ([string]::IsNullOrWhiteSpace($processInstanceId) -or [string]::IsNullOrWhiteSpace($firstTaskId)) {
        throw "$ProcessKey start response missing processInstanceId or taskId"
    }
    $script:processInstanceIds += $processInstanceId
    Assert-Equal -Actual ([string]$start.data.processKey) -Expected $ProcessKey -Name "$ProcessKey start processKey"

    $firstApprove = Invoke-JsonPost -Url ($baseUrl + '/biz/task/approve') -Token $token -Body @{
        id = $firstTaskId
        form = @{
            approval = $true
            comment = "$ProcessKey first approval"
        }
    }
    if ([int]$firstApprove.code -ne 200) {
        throw "$ProcessKey first approve expected code=200, got code=$($firstApprove.code), message=$($firstApprove.message)"
    }
    $financeTaskId = [string]$firstApprove.data.nextTaskId
    if ([string]::IsNullOrWhiteSpace($financeTaskId)) {
        throw "$ProcessKey first approve missing nextTaskId"
    }
    Assert-Equal -Actual ([string]$firstApprove.data.taskDefinitionKey) -Expected 'Activity_pay_approval' -Name "$ProcessKey next task definition"
    Assert-Equal -Actual ([string]$firstApprove.data.status) -Expected 'progress' -Name "$ProcessKey first approve status"

    $mid = Get-WorkflowState -ProcessInstanceId $processInstanceId -AccountId $accountId
    Assert-IntEqual -Actual ([int]$mid.runtime.taskCount) -Expected 1 -Name "$ProcessKey mid runtime task count"
    Assert-Equal -Actual ([string]$mid.runtime.taskId) -Expected $financeTaskId -Name "$ProcessKey mid task id"
    Assert-Equal -Actual ([string]$mid.runtime.taskDefinitionKey) -Expected 'Activity_pay_approval' -Name "$ProcessKey mid task definition"
    Assert-Equal -Actual ([string]$mid.runtime.assignee) -Expected $userId -Name "$ProcessKey mid assignee"
    Assert-Equal -Actual ([string]$mid.history.STATE_) -Expected 'ACTIVE' -Name "$ProcessKey mid process state"
    Assert-IntEqual -Actual ([int]$mid.expenditureCount) -Expected 0 -Name "$ProcessKey mid expenditure count"
    Assert-IntEqual -Actual ([int]$mid.statementCount) -Expected 0 -Name "$ProcessKey mid statement count"
    Assert-DecimalEqual -Actual ([string]$mid.account.amount) -Expected $ExpectedBefore -Name "$ProcessKey mid account amount"

    $category = "SMOKE/$ProcessKey"
    $financeApprove = Invoke-JsonPost -Url ($baseUrl + '/biz/task/approve') -Token $token -Body @{
        id = $financeTaskId
        form = @{
            approval = $true
            comment = "$ProcessKey finance approval"
            accountId = $accountId
            payerTime = $payerTime
            settlementCategory = @('SMOKE', $ProcessKey)
        }
    }
    if ([int]$financeApprove.code -ne 200) {
        throw "$ProcessKey finance approve expected code=200, got code=$($financeApprove.code), message=$($financeApprove.message)"
    }
    Assert-Equal -Actual ([string]$financeApprove.data.processInstanceId) -Expected $processInstanceId -Name "$ProcessKey finance processInstanceId"
    Assert-Equal -Actual ([string]$financeApprove.data.processKey) -Expected $ProcessKey -Name "$ProcessKey finance processKey"
    Assert-Equal -Actual ([string]$financeApprove.data.status) -Expected 'AGREE' -Name "$ProcessKey finance status"
    Assert-Equal -Actual ([string]$financeApprove.data.expenditure.accountId) -Expected $accountId -Name "$ProcessKey expenditure accountId"
    Assert-Equal -Actual ([string]$financeApprove.data.expenditure.amount) -Expected $Amount -Name "$ProcessKey expenditure amount"
    Assert-DecimalEqual -Actual ([string]$financeApprove.data.expenditure.beforeAmount) -Expected $ExpectedBefore -Name "$ProcessKey expenditure beforeAmount"
    Assert-DecimalEqual -Actual ([string]$financeApprove.data.expenditure.afterAmount) -Expected $ExpectedAfter -Name "$ProcessKey expenditure afterAmount"

    $state = Get-WorkflowState -ProcessInstanceId $processInstanceId -AccountId $accountId
    Assert-IntEqual -Actual ([int]$state.runtime.taskCount) -Expected 0 -Name "$ProcessKey final runtime task count"
    Assert-IntEqual -Actual ([int]$state.runtime.variableCount) -Expected 0 -Name "$ProcessKey final runtime variable count"
    Assert-IntEqual -Actual ([int]$state.runtime.executionCount) -Expected 0 -Name "$ProcessKey final runtime execution count"
    Assert-Equal -Actual ([string]$state.history.STATE_) -Expected 'COMPLETED' -Name "$ProcessKey final history state"
    Assert-Equal -Actual ([string]$state.history.END_ACT_ID_) -Expected 'Event_1q6ckfm' -Name "$ProcessKey final end activity"
    Assert-Equal -Actual ([string]$state.variables.approval) -Expected 'true' -Name "$ProcessKey final approval variable"
    Assert-Equal -Actual ([string]$state.variables.status) -Expected 'AGREE' -Name "$ProcessKey final status variable"
    Assert-Equal -Actual ([string]$state.variables.state) -Expected 'AGREE' -Name "$ProcessKey final state variable"
    Assert-Equal -Actual ([string]$state.variables.accountId) -Expected $accountId -Name "$ProcessKey finance accountId variable"
    Assert-Equal -Actual ([string]$state.variables.payerTime) -Expected $payerTime -Name "$ProcessKey finance payerTime variable"
    Assert-Equal -Actual ([string]$state.variables.settlementCategory) -Expected $category -Name "$ProcessKey finance settlementCategory variable"

    Assert-DecimalEqual -Actual ([string]$state.account.amount) -Expected $ExpectedAfter -Name "$ProcessKey final account amount"
    Assert-Equal -Actual ([string]$state.account.tenantId) -Expected $tenantId -Name "$ProcessKey account tenantId"
    Assert-IntEqual -Actual ([int]$state.expenditureCount) -Expected 1 -Name "$ProcessKey final expenditure count"
    Assert-IntEqual -Actual ([int]$state.statementCount) -Expected 1 -Name "$ProcessKey final statement count"
    Assert-Equal -Actual ([string]$state.expenditure.objectId) -Expected $ObjectId -Name "$ProcessKey expenditure objectId"
    Assert-Equal -Actual ([string]$state.expenditure.targetId) -Expected $accountId -Name "$ProcessKey expenditure targetId"
    Assert-Equal -Actual ([string]$state.expenditure.serialId) -Expected ([string]$state.statement.id) -Name "$ProcessKey expenditure serialId"
    Assert-Equal -Actual ([string]$state.expenditure.processId) -Expected $processInstanceId -Name "$ProcessKey expenditure processId"
    Assert-Equal -Actual ([string]$state.expenditure.settlementCategory) -Expected $category -Name "$ProcessKey expenditure category"
    Assert-Equal -Actual ([string]$state.expenditure.payer) -Expected "codex-$ProcessKey-payee" -Name "$ProcessKey expenditure payer"
    Assert-Equal -Actual ([string]$state.expenditure.bankName) -Expected "codex-$ProcessKey-bank" -Name "$ProcessKey expenditure bankName"
    Assert-Equal -Actual ([string]$state.expenditure.bankAccount) -Expected "codex-$ProcessKey-bank-account" -Name "$ProcessKey expenditure bankAccount"
    Assert-Equal -Actual ([string]$state.expenditure.remark) -Expected "$prefix-$ProcessKey" -Name "$ProcessKey expenditure remark"
    Assert-Equal -Actual ([string]$state.expenditure.payerTime) -Expected $payerTime -Name "$ProcessKey expenditure payerTime"
    Assert-DecimalEqual -Actual ([string]$state.expenditure.amount) -Expected ([decimal]$Amount) -Name "$ProcessKey expenditure amount"
    Assert-Equal -Actual ([string]$state.expenditure.tenantId) -Expected $tenantId -Name "$ProcessKey expenditure tenantId"
    Assert-Equal -Actual ([string]$state.expenditure.user) -Expected $userId -Name "$ProcessKey expenditure user"
    Assert-Equal -Actual ([string]$state.expenditure.org) -Expected $orgId -Name "$ProcessKey expenditure org"
    Assert-Equal -Actual ([string]$state.expenditure.deleteFlag) -Expected 'NOT_DELETE' -Name "$ProcessKey expenditure deleteFlag"

    Assert-Equal -Actual ([string]$state.statement.accountId) -Expected $accountId -Name "$ProcessKey statement accountId"
    Assert-Equal -Actual ([string]$state.statement.processId) -Expected $processInstanceId -Name "$ProcessKey statement processId"
    Assert-DecimalEqual -Actual ([string]$state.statement.beforeAmount) -Expected $ExpectedBefore -Name "$ProcessKey statement beforeAmount"
    Assert-DecimalEqual -Actual ([string]$state.statement.amount) -Expected ([decimal]$Amount) -Name "$ProcessKey statement amount"
    Assert-DecimalEqual -Actual ([string]$state.statement.afterAmount) -Expected $ExpectedAfter -Name "$ProcessKey statement afterAmount"
    Assert-Equal -Actual ([string]$state.statement.settlementType) -Expected 'EXPEND' -Name "$ProcessKey statement settlementType"
    Assert-Equal -Actual ([string]$state.statement.settlementCategory) -Expected $category -Name "$ProcessKey statement settlementCategory"
    Assert-Equal -Actual ([string]$state.statement.processCategory) -Expected $ProcessKey -Name "$ProcessKey statement processCategory"
    Assert-Equal -Actual ([string]$state.statement.payerTime) -Expected $payerTime -Name "$ProcessKey statement payerTime"
    Assert-Equal -Actual ([string]$state.statement.tenantId) -Expected $tenantId -Name "$ProcessKey statement tenantId"
    Assert-Equal -Actual ([string]$state.statement.deleteFlag) -Expected 'NOT_DELETE' -Name "$ProcessKey statement deleteFlag"

    Write-Host "$ProcessKey finance approval created expenditure processInstanceId=$processInstanceId"
}

$envMap = Get-EnvMap -Path $EnvPath
$account = ''
if ($envMap.ContainsKey('LOCAL_SUPER_ADMIN_ACCOUNT')) {
    $account = [string]$envMap['LOCAL_SUPER_ADMIN_ACCOUNT']
}
if ($account -eq '') {
    throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$safeAccount = $account.Replace("'", "\'")
$prefix = 'codex-wf-exp-' + ([Guid]::NewGuid().ToString('N').Substring(0, 10))
$accountId = 'SAC' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$reimbursementObjectId = 'OBJ' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$makePaymentObjectId = 'OBJ' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$payerTime = '2026-05-08 09:10:11'
$script:processInstanceIds = @()

try {
    Remove-SmokeRows -AccountId $accountId -ProcessInstanceIds @()

    $safeAccountId = $accountId.Replace("'", "\'")
    $safePrefix = $prefix.Replace("'", "\'")
    $setupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$userId = (string)`$user['ID'];
`$tenantId = trim((string)(`$user['TENANT_ID'] ?? ''));
if (`$tenantId === '') { `$tenantId = '1'; }
`$orgId = trim((string)(`$user['ORG_ID'] ?? ''));
if (`$orgId === '') {
    `$org = think\facade\Db::name('sys_org')
        ->where(function (`$query) { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })
        ->field('ID')
        ->order('ID', 'asc')
        ->find();
    `$orgId = (string)(`$org['ID'] ?? '');
}
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('settlement_account')->insert([
    'ID' => '$safeAccountId',
    'ACCOUNT_NAME' => '$safePrefix-account',
    'ACCOUNT_NUMBER' => '$safePrefix-no',
    'INITIAL_AMOUNT' => '1000.00',
    'CURRENT_AMOUNT' => '1000.00',
    'ACCOUNT_STATUS' => 'ENABLE',
    'SORT_CODE' => 991,
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => `$now,
    'UPDATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 0,
    'org' => `$orgId !== '' ? `$orgId : null,
]);
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_WORKFLOW_PAYMENT_OUT_APPROVE_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => `$userId,
    'tenantId' => `$tenantId,
    'orgId' => `$orgId,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    $context = Invoke-PhpJson -Code $setupCode
    $token = [string]$context.token
    $userId = [string]$context.userId
    $tenantId = [string]$context.tenantId
    $orgId = [string]$context.orgId
    if ([string]::IsNullOrWhiteSpace($token) -or [string]::IsNullOrWhiteSpace($userId)) {
        throw 'failed to create local smoke auth token'
    }

    Invoke-ApproveCase `
        -Path '/biz/process/reimbursement/start' `
        -ProcessKey 'Process_reimbursement' `
        -Amount '12.34' `
        -ExpectedBefore ([decimal]'1000.00') `
        -ExpectedAfter ([decimal]'987.66') `
        -ObjectId $reimbursementObjectId

    Invoke-ApproveCase `
        -Path '/biz/process/makePayment/start' `
        -ProcessKey 'Process_make_payment' `
        -Amount '23.45' `
        -ExpectedBefore ([decimal]'987.66') `
        -ExpectedAfter ([decimal]'964.21') `
        -ObjectId $makePaymentObjectId

    Write-Host 'workflow payment-out approve HTTP smoke passed'
} finally {
    Remove-SmokeRows -AccountId $accountId -ProcessInstanceIds $script:processInstanceIds
}
