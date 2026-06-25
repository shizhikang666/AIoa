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

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("workflow-project-play-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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

function Get-MapValue {
    param(
        [Parameter(Mandatory = $true)][object]$Map,
        [Parameter(Mandatory = $true)][string]$Key
    )

    $property = $Map.PSObject.Properties[$Key]
    if ($null -eq $property) {
        return $null
    }

    return $property.Value
}

function Remove-SmokeRows {
    param(
        [string[]]$ProcessInstanceIds,
        [string[]]$ProjectIds,
        [string]$CustomerId,
        [string]$AccountId
    )

    $safeProcessIds = @($ProcessInstanceIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
    $safeProjectIds = @($ProjectIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
    $safeCustomerId = $CustomerId.Replace("'", "\'")
    $safeAccountId = $AccountId.Replace("'", "\'")
    $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pids = [$safeProcessIds];
`$projectIds = [$safeProjectIds];
if (`$pids !== []) {
    think\facade\Db::name('act_ru_task')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_ru_variable')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_varinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_taskinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_actinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_procinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_ru_execution')->whereIn('PROC_INST_ID_', `$pids)->whereNotIn('ID_', `$pids)->delete();
    think\facade\Db::name('act_ru_execution')->whereIn('ID_', `$pids)->delete();
    think\facade\Db::name('biz_cc_records')->whereIn('INSTANCE_ID', `$pids)->delete();
    think\facade\Db::name('biz_file_relation')->whereIn('OBJECT_ID', `$pids)->delete();
    think\facade\Db::name('biz_payment_record')->whereIn('PROCESS_ID', `$pids)->delete();
    think\facade\Db::name('settlement_account_statement')->whereIn('PROCESS_ID', `$pids)->delete();
}
if (`$projectIds !== []) {
    think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_payment_record')->whereIn('OBJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->delete();
}
if ('$safeAccountId' !== '') {
    think\facade\Db::name('settlement_account_statement')->where('ACCOUNT_ID', '$safeAccountId')->delete();
    think\facade\Db::name('settlement_account')->where('ID', '$safeAccountId')->delete();
}
if ('$safeCustomerId' !== '') {
    think\facade\Db::name('customer')->where('ID', '$safeCustomerId')->delete();
}
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    [void](Invoke-PhpJson -Code $cleanupCode)
}

function Get-State {
    param(
        [string[]]$ProcessInstanceIds,
        [string[]]$ProjectIds,
        [string]$AccountId
    )

    $safeProcessIds = @($ProcessInstanceIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
    $safeProjectIds = @($ProjectIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
    $safeAccountId = $AccountId.Replace("'", "\'")
    $stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pids = [$safeProcessIds];
`$projectIds = [$safeProjectIds];
`$projects = [];
if (`$projectIds !== []) {
    foreach (think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->select()->toArray() as `$row) {
        `$projects[(string)`$row['ID']] = `$row;
    }
}
`$processes = [];
if (`$pids !== []) {
    foreach (`$pids as `$processId) {
        `$processes[`$processId] = [
            'ruTask' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$processId)->count(),
            'ruVariable' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$processId)->count(),
            'ruExecution' => think\facade\Db::name('act_ru_execution')->where('PROC_INST_ID_', `$processId)->count(),
            'hiProc' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$processId)->field('PROC_DEF_KEY_,STATE_,END_TIME_')->find(),
        ];
    }
}
echo json_encode([
    'projects' => `$projects,
    'processes' => `$processes,
    'payments' => `$pids !== [] ? think\facade\Db::name('biz_payment_record')->whereIn('PROCESS_ID', `$pids)->select()->toArray() : [],
    'statements' => `$pids !== [] ? think\facade\Db::name('settlement_account_statement')->whereIn('PROCESS_ID', `$pids)->select()->toArray() : [],
    'account' => think\facade\Db::name('settlement_account')->where('ID', '$safeAccountId')->find(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    return Invoke-PhpJson -Code $stateCode
}

function New-StartBody {
    param(
        [Parameter(Mandatory = $true)][string]$ProjectId,
        [string]$Amount = '40.00'
    )

    return @{
        projectId = $ProjectId
        approveUserIdList = @($script:UserId)
        copyUserIdList = @()
        fileIdList = @()
        treasurer = $script:UserId
        accountId = $script:AccountId
        payerTime = $script:PayerTime
        amount = $Amount
        payer = 'codex project play payer'
        remark = $script:Prefix
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

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$seed = [Guid]::NewGuid().ToString('N').Substring(0, 10)
$Prefix = "codex-wf-play-$seed"
$CustomerId = "C$seed"
$ProjectCancelId = "PC$seed"
$ProjectRejectId = "PR$seed"
$ProjectFinanceRejectId = "PF$seed"
$ProjectApproveId = "PA$seed"
$AccountId = "SA$seed"
$PayerTime = '2026-05-08 09:10:11'
$processIds = @()
$UserId = ''

try {
    Remove-SmokeRows -ProcessInstanceIds @() -ProjectIds @($ProjectCancelId, $ProjectRejectId, $ProjectFinanceRejectId, $ProjectApproveId) -CustomerId $CustomerId -AccountId $AccountId

    $safeAccount = $account.Replace("'", "\'")
    $safePrefix = $Prefix.Replace("'", "\'")
    $setupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$userId = (string)`$user['ID'];
`$tenantId = trim((string)(`$user['TENANT_ID'] ?? ''));
if (`$tenantId === '') { `$tenantId = '1'; }
`$orgId = trim((string)(`$user['ORG_ID'] ?? ''));
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('customer')->insert([
    'ID' => '$CustomerId',
    'NAME' => '$safePrefix customer',
    'CUSTOM_TYPE' => 'OLD',
    'ORG' => `$orgId !== '' ? `$orgId : null,
    'USER' => `$userId,
    'STATUS' => 'ENABLE',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 0,
    'DEAL_AMOUNT' => '0.00',
]);
think\facade\Db::name('settlement_account')->insert([
    'ID' => '$AccountId',
    'ACCOUNT_NAME' => '$safePrefix account',
    'ACCOUNT_NUMBER' => '$seed',
    'INITIAL_AMOUNT' => '0.00',
    'CURRENT_AMOUNT' => '0.00',
    'ACCOUNT_STATUS' => 'ENABLE',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'org' => `$orgId !== '' ? `$orgId : null,
    'VERSION' => 0,
]);
foreach ([['$ProjectCancelId', 'cancel'], ['$ProjectRejectId', 'reject'], ['$ProjectFinanceRejectId', 'finance-reject'], ['$ProjectApproveId', 'approve']] as `$project) {
    think\facade\Db::name('biz_sale_project')->insert([
        'ID' => `$project[0],
        'CUSTOMER' => '$CustomerId',
        'PROJECT_NAME' => '$safePrefix ' . `$project[1],
        'PROJECT_STATE' => 'SHIPPED',
        'PLAY_STATE' => 'UNPAID',
        'VISIBILITY' => 'PRIVATE',
        'INIT_PRICE' => '100.00',
        'TOTAL_PRICE' => '100.00',
        'AMOUNT_COLLECTED' => '0.00',
        'PROJECT_CATEGORY' => 'DEFAULT',
        'USER' => `$userId,
        'ORG' => `$orgId !== '' ? `$orgId : null,
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => `$userId,
        'TENANT_ID' => `$tenantId,
        'VERSION' => 0,
        'DEAL_AMOUNT' => '0.00',
        'HISTORY_AMOUNT' => '0.00',
        'TOTAL_RETURN_AMOUNT' => '0.00',
        'TOTAL_REFUND_AMOUNT' => '0.00',
    ]);
    think\facade\Db::name('biz_sale_project_product_item')->insert([
        'ID' => 'I' . `$project[0],
        'PROJECT_ID' => `$project[0],
        'PRODUCT_ID' => 'SMOKE',
        'CATEGORY' => 'INIT',
        'STATE' => 'SHIPPED',
        'NUMBER' => '1.00',
        'DELIVERY' => '1.00',
        'UNIT_PRICE' => '100.00',
        'DISCOUNT_RATE' => '100',
        'PRICE' => '100.00',
        'REMARK' => '$safePrefix',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => `$userId,
        'TENANT_ID' => `$tenantId,
        'VERSION' => 0,
        'PROJECT_REISSUE_ORDER_ID' => '',
        'MARK' => '',
    ]);
}
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_WORKFLOW_PROJECT_PLAY_APPROVE_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => `$userId,
    'tenantId' => `$tenantId,
    'orgId' => `$orgId,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    $context = Invoke-PhpJson -Code $setupCode
    $token = [string]$context.token
    $UserId = [string]$context.userId
    if ([string]::IsNullOrWhiteSpace($token) -or [string]::IsNullOrWhiteSpace($UserId)) {
        throw 'failed to create local smoke auth token'
    }

    $noToken = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/play/start') -Body (New-StartBody -ProjectId $ProjectCancelId)
    Assert-IntEqual -Actual ([int]$noToken.code) -Expected 401 -Name 'project play start no-token code'

    $missingAmount = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/play/start') -Token $token -Body @{
        projectId = $ProjectCancelId
        approveUserIdList = @($UserId)
        copyUserIdList = @()
        treasurer = $UserId
        accountId = $AccountId
        payerTime = $PayerTime
    }
    Assert-IntEqual -Actual ([int]$missingAmount.code) -Expected 400 -Name 'project play missing amount code'

    $cancelStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/play/start') -Token $token -Body (New-StartBody -ProjectId $ProjectCancelId)
    Assert-IntEqual -Actual ([int]$cancelStart.code) -Expected 200 -Name 'project play cancel start code'
    $cancelProcessId = [string]$cancelStart.data.processInstanceId
    $processIds += $cancelProcessId
    Assert-Equal -Actual ([string]$cancelStart.data.processKey) -Expected 'Process_sale_project_play' -Name 'project play cancel process key'
    $cancel = Invoke-JsonPost -Url ($baseUrl + '/biz/process/cancel') -Token $token -Body @{ id = $cancelProcessId }
    Assert-IntEqual -Actual ([int]$cancel.code) -Expected 200 -Name 'project play cancel code'

    $rejectStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/play/start') -Token $token -Body (New-StartBody -ProjectId $ProjectRejectId)
    Assert-IntEqual -Actual ([int]$rejectStart.code) -Expected 200 -Name 'project play reject start code'
    $rejectProcessId = [string]$rejectStart.data.processInstanceId
    $processIds += $rejectProcessId
    $reject = Invoke-JsonPost -Url ($baseUrl + '/biz/task/reject') -Token $token -Body @{
        id = [string]$rejectStart.data.taskId
        form = @{ comment = "$Prefix first reject" }
    }
    Assert-IntEqual -Actual ([int]$reject.code) -Expected 200 -Name 'project play first reject code'

    $financeRejectStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/play/start') -Token $token -Body (New-StartBody -ProjectId $ProjectFinanceRejectId)
    Assert-IntEqual -Actual ([int]$financeRejectStart.code) -Expected 200 -Name 'project play finance reject start code'
    $financeRejectProcessId = [string]$financeRejectStart.data.processInstanceId
    $processIds += $financeRejectProcessId
    $firstApprove = Invoke-JsonPost -Url ($baseUrl + '/biz/task/approve') -Token $token -Body @{
        id = [string]$financeRejectStart.data.taskId
        form = @{ approval = $true; comment = "$Prefix first approve" }
    }
    Assert-IntEqual -Actual ([int]$firstApprove.code) -Expected 200 -Name 'project play first approve code'
    Assert-Equal -Actual ([string]$firstApprove.data.taskDefinitionKey) -Expected 'Activity_payment_approval' -Name 'project play next task key'
    $financeReject = Invoke-JsonPost -Url ($baseUrl + '/biz/task/reject') -Token $token -Body @{
        id = [string]$firstApprove.data.taskId
        form = @{ comment = "$Prefix finance reject" }
    }
    Assert-IntEqual -Actual ([int]$financeReject.code) -Expected 200 -Name 'project play finance reject code'

    $approveStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/play/start') -Token $token -Body (New-StartBody -ProjectId $ProjectApproveId)
    Assert-IntEqual -Actual ([int]$approveStart.code) -Expected 200 -Name 'project play approve start code'
    $approveProcessId = [string]$approveStart.data.processInstanceId
    $processIds += $approveProcessId
    $leaderApprove = Invoke-JsonPost -Url ($baseUrl + '/biz/task/approve') -Token $token -Body @{
        id = [string]$approveStart.data.taskId
        form = @{ approval = $true; comment = "$Prefix leader approve" }
    }
    Assert-IntEqual -Actual ([int]$leaderApprove.code) -Expected 200 -Name 'project play leader approve code'
    $financeApprove = Invoke-JsonPost -Url ($baseUrl + '/biz/task/approve') -Token $token -Body @{
        id = [string]$leaderApprove.data.taskId
        form = @{
            approval = $true
            comment = "$Prefix finance approve"
            accountId = $AccountId
            payerTime = $PayerTime
            amount = '40.00'
            payer = 'codex project play payer final'
        }
    }
    Assert-IntEqual -Actual ([int]$financeApprove.code) -Expected 200 -Name 'project play finance approve code'
    Assert-Equal -Actual ([string]$financeApprove.data.processKey) -Expected 'Process_sale_project_play' -Name 'project play finance approve process key'

    $state = Get-State -ProcessInstanceIds $processIds -ProjectIds @($ProjectCancelId, $ProjectRejectId, $ProjectFinanceRejectId, $ProjectApproveId) -AccountId $AccountId
    foreach ($processId in $processIds) {
        $process = Get-MapValue -Map $state.processes -Key $processId
        Assert-IntEqual -Actual ([int]$process.ruTask) -Expected 0 -Name "runtime task count $processId"
        Assert-IntEqual -Actual ([int]$process.ruVariable) -Expected 0 -Name "runtime variable count $processId"
        Assert-IntEqual -Actual ([int]$process.ruExecution) -Expected 0 -Name "runtime execution count $processId"
        Assert-Equal -Actual ([string]$process.hiProc.STATE_) -Expected 'COMPLETED' -Name "history process state $processId"
    }

    Assert-IntEqual -Actual ([int]$state.payments.Count) -Expected 1 -Name 'project play payment row count'
    Assert-IntEqual -Actual ([int]$state.statements.Count) -Expected 1 -Name 'project play statement row count'
    $payment = $state.payments[0]
    $statement = $state.statements[0]
    Assert-Equal -Actual ([string]$payment.OBJECT_ID) -Expected $ProjectApproveId -Name 'payment object id'
    Assert-Equal -Actual ([string]$payment.TARGET_ID) -Expected $AccountId -Name 'payment target account'
    Assert-Equal -Actual ([string]$payment.PROCESS_ID) -Expected $approveProcessId -Name 'payment process id'
    Assert-Equal -Actual ([string]$payment.SETTLEMENT_CATEGORY) -Expected 'PROJECT_PLAY' -Name 'payment settlement category'
    Assert-DecimalEqual -Actual ([string]$payment.AMOUNT) -Expected 40.00 -Name 'payment amount'
    Assert-Equal -Actual ([string]$statement.PROCESS_CATEGORY) -Expected 'Process_sale_project_play' -Name 'statement process category'
    Assert-Equal -Actual ([string]$statement.SETTLEMENT_CATEGORY) -Expected 'PROJECT_PLAY' -Name 'statement settlement category'
    Assert-DecimalEqual -Actual ([string]$statement.AMOUNT) -Expected 40.00 -Name 'statement amount'
    Assert-DecimalEqual -Actual ([string]$state.account.CURRENT_AMOUNT) -Expected 40.00 -Name 'account current amount'

    foreach ($projectId in @($ProjectCancelId, $ProjectRejectId, $ProjectFinanceRejectId)) {
        $project = Get-MapValue -Map $state.projects -Key $projectId
        Assert-Equal -Actual ([string]$project.PLAY_STATE) -Expected 'UNPAID' -Name "$projectId play state"
        Assert-Equal -Actual ([string]$project.PROJECT_STATE) -Expected 'SHIPPED' -Name "$projectId project state"
        Assert-DecimalEqual -Actual ([string]$project.AMOUNT_COLLECTED) -Expected 0.00 -Name "$projectId amount collected"
    }

    $approvedProject = Get-MapValue -Map $state.projects -Key $ProjectApproveId
    Assert-Equal -Actual ([string]$approvedProject.PLAY_STATE) -Expected 'PARTIALLY_PAID' -Name 'approved project play state'
    Assert-Equal -Actual ([string]$approvedProject.PROJECT_STATE) -Expected 'SHIPPED' -Name 'approved project state'
    Assert-DecimalEqual -Actual ([string]$approvedProject.AMOUNT_COLLECTED) -Expected 40.00 -Name 'approved project amount collected'

    Write-Host 'workflow project play approve smoke passed'
} finally {
    Remove-SmokeRows -ProcessInstanceIds $processIds -ProjectIds @($ProjectCancelId, $ProjectRejectId, $ProjectFinanceRejectId, $ProjectApproveId) -CustomerId $CustomerId -AccountId $AccountId
}
