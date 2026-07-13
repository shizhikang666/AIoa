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

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("workflow-payment-approve-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
    think\facade\Db::name('biz_payment_record')->whereIn('PROCESS_ID', `$pids)->delete();
    think\facade\Db::name('settlement_account_statement')->whereIn('PROCESS_ID', `$pids)->delete();
}
if (`$accountId !== '') {
    think\facade\Db::name('biz_payment_record')->where('TARGET_ID', `$accountId)->delete();
    think\facade\Db::name('settlement_account_statement')->where('ACCOUNT_ID', `$accountId)->delete();
    think\facade\Db::name('settlement_account')->where('ID', `$accountId)->delete();
}
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

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$safeAccount = $account.Replace("'", "\'")
$prefix = 'codex-wf-pay-' + ([Guid]::NewGuid().ToString('N').Substring(0, 10))
$accountId = 'SAC' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$objectId = 'OBJ' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$payerTime = '2026-05-07 08:09:10'
$category = 'SMOKE/WORKFLOW_PAYMENT'
$processInstanceId = ''
$rejectProcessInstanceId = ''

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
    'SORT_CODE' => 992,
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
`$auth['device'] = 'CODEX_WORKFLOW_PAYMENT_APPROVE_SMOKE';
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

    $start = Invoke-JsonPost -Url ($baseUrl + '/biz/process/payment/start') -Token $token -Body @{
        processSmokeMarker = "$prefix-approve"
        approveUserIdList = @($userId)
        copyUserIdList = @()
        accountId = $accountId
        settlementCategory = @('SMOKE', 'WORKFLOW_PAYMENT')
        payerTime = $payerTime
        amount = '12.34'
        payer = 'codex workflow payer'
        treasurer = $userId
        objectId = $objectId
        bankName = 'codex workflow bank'
        bankAccount = 'codex workflow account'
        remark = "$prefix-approve"
    }
    if ([int]$start.code -ne 200) {
        throw "payment workflow start expected code=200, got code=$($start.code), message=$($start.message)"
    }

    $processInstanceId = [string]$start.data.processInstanceId
    $taskId = [string]$start.data.taskId
    Assert-Equal -Actual ([string]$start.data.processKey) -Expected 'Process_payment' -Name 'payment workflow start processKey'

    $approve = Invoke-JsonPost -Url ($baseUrl + '/biz/task/approve') -Token $token -Body @{
        id = $taskId
        form = @{
            approval = $true
            comment = 'codex workflow payment approve'
        }
    }
    if ([int]$approve.code -ne 200) {
        throw "payment workflow approve expected code=200, got code=$($approve.code), message=$($approve.message)"
    }
    Assert-Equal -Actual ([string]$approve.data.processInstanceId) -Expected $processInstanceId -Name 'approve processInstanceId'
    Assert-Equal -Actual ([string]$approve.data.processKey) -Expected 'Process_payment' -Name 'approve processKey'
    Assert-Equal -Actual ([string]$approve.data.status) -Expected 'AGREE' -Name 'approve status'
    Assert-Equal -Actual ([string]$approve.data.payment.accountId) -Expected $accountId -Name 'approve payment accountId'
    Assert-Equal -Actual ([string]$approve.data.payment.amount) -Expected '12.34' -Name 'approve payment amount'
    Assert-Equal -Actual ([string]$approve.data.payment.beforeAmount) -Expected '1000.00' -Name 'approve payment beforeAmount'
    Assert-Equal -Actual ([string]$approve.data.payment.afterAmount) -Expected '1012.34' -Name 'approve payment afterAmount'

    $safePid = $processInstanceId.Replace("'", "\'")
    $safeObjectId = $objectId.Replace("'", "\'")
    $stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safePid';
`$accountId = '$safeAccountId';
`$payment = think\facade\Db::name('biz_payment_record')->where('PROCESS_ID', `$pid)->find();
`$statement = think\facade\Db::name('settlement_account_statement')->where('PROCESS_ID', `$pid)->find();
`$account = think\facade\Db::name('settlement_account')->where('ID', `$accountId)->find();
`$vars = [];
foreach (think\facade\Db::name('act_hi_varinst')->where('PROC_INST_ID_', `$pid)->whereIn('NAME_', ['approval', 'status', 'state'])->order('CREATE_TIME_', 'asc')->order('REV_', 'asc')->order('ID_', 'asc')->select()->toArray() as `$row) {
    `$name = (string)(`$row['NAME_'] ?? '');
    if ((string)(`$row['VAR_TYPE_'] ?? '') === 'boolean') {
        `$vars[`$name] = ((int)(`$row['LONG_'] ?? 0)) === 1 ? 'true' : 'false';
    } else {
        `$vars[`$name] = (string)(`$row['TEXT_'] ?? '');
    }
}
echo json_encode([
    'runtime' => [
        'task' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->count(),
        'variable' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$pid)->count(),
        'execution' => think\facade\Db::name('act_ru_execution')->where('PROC_INST_ID_', `$pid)->count(),
    ],
    'history' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->field('STATE_,END_ACT_ID_,END_TIME_')->find(),
    'variables' => `$vars,
    'account' => [
        'amount' => (string)(`$account['CURRENT_AMOUNT'] ?? ''),
        'tenantId' => (string)(`$account['TENANT_ID'] ?? ''),
    ],
    'payment' => [
        'id' => (string)(`$payment['ID'] ?? ''),
        'objectId' => (string)(`$payment['OBJECT_ID'] ?? ''),
        'targetId' => (string)(`$payment['TARGET_ID'] ?? ''),
        'serialId' => (string)(`$payment['SERIAL_ID'] ?? ''),
        'processId' => (string)(`$payment['PROCESS_ID'] ?? ''),
        'settlementCategory' => (string)(`$payment['SETTLEMENT_CATEGORY'] ?? ''),
        'payer' => (string)(`$payment['PAYER'] ?? ''),
        'bankName' => (string)(`$payment['BANK_NAME'] ?? ''),
        'bankAccount' => (string)(`$payment['BANK_ACCOUNT'] ?? ''),
        'remark' => (string)(`$payment['REMARK'] ?? ''),
        'payerTime' => (string)(`$payment['PAYER_TIME'] ?? ''),
        'amount' => (string)(`$payment['AMOUNT'] ?? ''),
        'tenantId' => (string)(`$payment['TENANT_ID'] ?? ''),
        'user' => (string)(`$payment['USER'] ?? ''),
        'org' => (string)(`$payment['ORG'] ?? ''),
        'deleteFlag' => (string)(`$payment['DELETE_FLAG'] ?? ''),
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

    $state = Invoke-PhpJson -Code $stateCode
    Assert-IntEqual -Actual ([int]$state.runtime.task) -Expected 0 -Name 'approve runtime task count'
    Assert-IntEqual -Actual ([int]$state.runtime.variable) -Expected 0 -Name 'approve runtime variable count'
    Assert-IntEqual -Actual ([int]$state.runtime.execution) -Expected 0 -Name 'approve runtime execution count'
    Assert-Equal -Actual ([string]$state.history.STATE_) -Expected 'COMPLETED' -Name 'approve history state'
    Assert-Equal -Actual ([string]$state.history.END_ACT_ID_) -Expected 'Event_148gpcc' -Name 'approve end activity'
    Assert-Equal -Actual ([string]$state.variables.approval) -Expected 'true' -Name 'approve history approval variable'
    Assert-Equal -Actual ([string]$state.variables.status) -Expected 'AGREE' -Name 'approve history status variable'
    Assert-Equal -Actual ([string]$state.variables.state) -Expected 'AGREE' -Name 'approve history state variable'

    Assert-DecimalEqual -Actual ([string]$state.account.amount) -Expected ([decimal]'1012.34') -Name 'approve account amount'
    Assert-Equal -Actual ([string]$state.account.tenantId) -Expected $tenantId -Name 'approve account tenantId'
    Assert-Equal -Actual ([string]$state.payment.objectId) -Expected $objectId -Name 'payment objectId'
    Assert-Equal -Actual ([string]$state.payment.targetId) -Expected $accountId -Name 'payment targetId'
    Assert-Equal -Actual ([string]$state.payment.serialId) -Expected ([string]$state.statement.id) -Name 'payment serialId'
    Assert-Equal -Actual ([string]$state.payment.processId) -Expected $processInstanceId -Name 'payment processId'
    Assert-Equal -Actual ([string]$state.payment.settlementCategory) -Expected $category -Name 'payment settlementCategory'
    Assert-Equal -Actual ([string]$state.payment.payer) -Expected 'codex workflow payer' -Name 'payment payer'
    Assert-Equal -Actual ([string]$state.payment.bankName) -Expected 'codex workflow bank' -Name 'payment bankName'
    Assert-Equal -Actual ([string]$state.payment.bankAccount) -Expected 'codex workflow account' -Name 'payment bankAccount'
    Assert-Equal -Actual ([string]$state.payment.remark) -Expected "$prefix-approve" -Name 'payment remark'
    Assert-Equal -Actual ([string]$state.payment.payerTime) -Expected $payerTime -Name 'payment payerTime'
    Assert-DecimalEqual -Actual ([string]$state.payment.amount) -Expected ([decimal]'12.34') -Name 'payment amount'
    Assert-Equal -Actual ([string]$state.payment.tenantId) -Expected $tenantId -Name 'payment tenantId'
    Assert-Equal -Actual ([string]$state.payment.user) -Expected $userId -Name 'payment user'
    Assert-Equal -Actual ([string]$state.payment.org) -Expected $orgId -Name 'payment org'
    Assert-Equal -Actual ([string]$state.payment.deleteFlag) -Expected 'NOT_DELETE' -Name 'payment deleteFlag'
    Assert-Equal -Actual ([string]$state.statement.accountId) -Expected $accountId -Name 'statement accountId'
    Assert-Equal -Actual ([string]$state.statement.processId) -Expected $processInstanceId -Name 'statement processId'
    Assert-DecimalEqual -Actual ([string]$state.statement.beforeAmount) -Expected ([decimal]'1000.00') -Name 'statement beforeAmount'
    Assert-DecimalEqual -Actual ([string]$state.statement.amount) -Expected ([decimal]'12.34') -Name 'statement amount'
    Assert-DecimalEqual -Actual ([string]$state.statement.afterAmount) -Expected ([decimal]'1012.34') -Name 'statement afterAmount'
    Assert-Equal -Actual ([string]$state.statement.settlementType) -Expected 'INCOME' -Name 'statement settlementType'
    Assert-Equal -Actual ([string]$state.statement.settlementCategory) -Expected $category -Name 'statement settlementCategory'
    Assert-Equal -Actual ([string]$state.statement.processCategory) -Expected 'Process_payment' -Name 'statement processCategory'
    Assert-Equal -Actual ([string]$state.statement.payerTime) -Expected $payerTime -Name 'statement payerTime'
    Assert-Equal -Actual ([string]$state.statement.tenantId) -Expected $tenantId -Name 'statement tenantId'
    Assert-Equal -Actual ([string]$state.statement.deleteFlag) -Expected 'NOT_DELETE' -Name 'statement deleteFlag'
    Write-Host "Process_payment approve created payment record processInstanceId=$processInstanceId"

    $rejectStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/payment/start') -Token $token -Body @{
        processSmokeMarker = "$prefix-reject"
        approveUserIdList = @($userId)
        copyUserIdList = @()
        accountId = $accountId
        settlementCategory = @('SMOKE', 'WORKFLOW_PAYMENT')
        payerTime = $payerTime
        amount = '4.56'
        treasurer = $userId
        objectId = $objectId
        remark = "$prefix-reject"
    }
    if ([int]$rejectStart.code -ne 200) {
        throw "payment workflow reject-start expected code=200, got code=$($rejectStart.code), message=$($rejectStart.message)"
    }

    $rejectProcessInstanceId = [string]$rejectStart.data.processInstanceId
    $rejectTaskId = [string]$rejectStart.data.taskId
    $reject = Invoke-JsonPost -Url ($baseUrl + '/biz/task/reject') -Token $token -Body @{
        id = $rejectTaskId
        form = @{
            comment = 'codex workflow payment reject'
        }
    }
    if ([int]$reject.code -ne 200) {
        throw "payment workflow reject expected code=200, got code=$($reject.code), message=$($reject.message)"
    }
    Assert-Equal -Actual ([string]$reject.data.processInstanceId) -Expected $rejectProcessInstanceId -Name 'reject processInstanceId'
    Assert-Equal -Actual ([string]$reject.data.processKey) -Expected 'Process_payment' -Name 'reject processKey'
    Assert-Equal -Actual ([string]$reject.data.status) -Expected 'REJECT' -Name 'reject status'

    $safeRejectPid = $rejectProcessInstanceId.Replace("'", "\'")
    $rejectStateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safeRejectPid';
`$accountId = '$safeAccountId';
`$vars = [];
foreach (think\facade\Db::name('act_hi_varinst')->where('PROC_INST_ID_', `$pid)->whereIn('NAME_', ['approval', 'status', 'state'])->order('CREATE_TIME_', 'asc')->order('REV_', 'asc')->order('ID_', 'asc')->select()->toArray() as `$row) {
    `$name = (string)(`$row['NAME_'] ?? '');
    if ((string)(`$row['VAR_TYPE_'] ?? '') === 'boolean') {
        `$vars[`$name] = ((int)(`$row['LONG_'] ?? 0)) === 1 ? 'true' : 'false';
    } else {
        `$vars[`$name] = (string)(`$row['TEXT_'] ?? '');
    }
}
`$account = think\facade\Db::name('settlement_account')->where('ID', `$accountId)->find();
echo json_encode([
    'runtime' => [
        'task' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->count(),
        'variable' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$pid)->count(),
        'execution' => think\facade\Db::name('act_ru_execution')->where('PROC_INST_ID_', `$pid)->count(),
    ],
    'history' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->field('STATE_,END_ACT_ID_,END_TIME_')->find(),
    'variables' => `$vars,
    'paymentCount' => think\facade\Db::name('biz_payment_record')->where('PROCESS_ID', `$pid)->count(),
    'statementCount' => think\facade\Db::name('settlement_account_statement')->where('PROCESS_ID', `$pid)->count(),
    'accountAmount' => (string)(`$account['CURRENT_AMOUNT'] ?? ''),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    $rejectState = Invoke-PhpJson -Code $rejectStateCode
    Assert-IntEqual -Actual ([int]$rejectState.runtime.task) -Expected 0 -Name 'reject runtime task count'
    Assert-IntEqual -Actual ([int]$rejectState.runtime.variable) -Expected 0 -Name 'reject runtime variable count'
    Assert-IntEqual -Actual ([int]$rejectState.runtime.execution) -Expected 0 -Name 'reject runtime execution count'
    Assert-Equal -Actual ([string]$rejectState.history.STATE_) -Expected 'COMPLETED' -Name 'reject history state'
    Assert-Equal -Actual ([string]$rejectState.history.END_ACT_ID_) -Expected 'Event_148gpcc' -Name 'reject end activity'
    Assert-Equal -Actual ([string]$rejectState.variables.approval) -Expected 'false' -Name 'reject history approval variable'
    Assert-Equal -Actual ([string]$rejectState.variables.status) -Expected 'REJECT' -Name 'reject history status variable'
    Assert-Equal -Actual ([string]$rejectState.variables.state) -Expected 'REJECT' -Name 'reject history state variable'
    Assert-IntEqual -Actual ([int]$rejectState.paymentCount) -Expected 0 -Name 'reject payment count'
    Assert-IntEqual -Actual ([int]$rejectState.statementCount) -Expected 0 -Name 'reject statement count'
    Assert-DecimalEqual -Actual ([string]$rejectState.accountAmount) -Expected ([decimal]'1012.34') -Name 'reject account amount unchanged'
    Write-Host "Process_payment reject closed workflow without payment record processInstanceId=$rejectProcessInstanceId"

    Write-Host 'workflow payment approve HTTP smoke passed'
} finally {
    Remove-SmokeRows -AccountId $accountId -ProcessInstanceIds @($processInstanceId, $rejectProcessInstanceId)
}
