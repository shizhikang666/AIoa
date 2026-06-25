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

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("workflow-procure-approve-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    $json = $Body | ConvertTo-Json -Depth 14
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
        [string[]]$ProcessInstanceIds,
        [string]$Prefix
    )

    $safePrefix = $Prefix.Replace("'", "\'")
    $safeProcessIds = @($ProcessInstanceIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
    $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pids = [$safeProcessIds];
if (`$pids !== []) {
    `$orderIds = think\facade\Db::name('biz_purchase_order')->whereIn('INSTANCE_ID', `$pids)->column('ID');
    if (`$orderIds !== []) {
        think\facade\Db::name('biz_purchase_order_item')->whereIn('PURCHASE_ORDER_ID', `$orderIds)->delete();
        think\facade\Db::name('biz_purchase_order')->whereIn('ID', `$orderIds)->delete();
    }
    think\facade\Db::name('act_ru_task')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_ru_variable')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_varinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_taskinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_actinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_procinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_ru_execution')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('biz_cc_records')->whereIn('INSTANCE_ID', `$pids)->delete();
    think\facade\Db::name('biz_file_relation')->whereIn('OBJECT_ID', `$pids)->delete();
}
think\facade\Db::name('biz_product')->whereLike('PRODUCT_NAME', '$safePrefix%')->delete();
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    [void](Invoke-PhpJson -Code $cleanupCode)
}

function Get-WorkflowState {
    param([Parameter(Mandatory = $true)][string]$ProcessInstanceId)

    $safePid = $ProcessInstanceId.Replace("'", "\'")
    $stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safePid';
`$task = think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->find();
`$order = think\facade\Db::name('biz_purchase_order')->where('INSTANCE_ID', `$pid)->find();
`$orderId = (string)(`$order['ID'] ?? '');
`$items = `$orderId !== ''
    ? think\facade\Db::name('biz_purchase_order_item')->where('PURCHASE_ORDER_ID', `$orderId)->order('PRODUCT_ID', 'asc')->select()->toArray()
    : [];
`$vars = [];
foreach (think\facade\Db::name('act_hi_varinst')->where('PROC_INST_ID_', `$pid)->whereIn('NAME_', ['approval', 'status', 'state', 'productList', 'amount', 'user'])->field('NAME_,VAR_TYPE_,LONG_,TEXT_')->select()->toArray() as `$row) {
    `$name = (string)(`$row['NAME_'] ?? '');
    `$type = (string)(`$row['VAR_TYPE_'] ?? '');
    if (`$type === 'boolean') {
        `$vars[`$name] = ((int)(`$row['LONG_'] ?? 0)) === 1 ? 'true' : 'false';
    } else {
        `$vars[`$name] = (string)(`$row['TEXT_'] ?? '');
    }
}
echo json_encode([
    'runtime' => [
        'taskCount' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->count(),
        'variableCount' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$pid)->count(),
        'executionCount' => think\facade\Db::name('act_ru_execution')->where('PROC_INST_ID_', `$pid)->whereOr('ID_', `$pid)->count(),
        'taskId' => (string)(`$task['ID_'] ?? ''),
        'taskDefinitionKey' => (string)(`$task['TASK_DEF_KEY_'] ?? ''),
        'assignee' => (string)(`$task['ASSIGNEE_'] ?? ''),
    ],
    'history' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->field('PROC_DEF_KEY_,STATE_,END_ACT_ID_,END_TIME_')->find(),
    'variables' => `$vars,
    'orderCount' => think\facade\Db::name('biz_purchase_order')->where('INSTANCE_ID', `$pid)->count(),
    'deliveryCount' => think\facade\Db::name('delivery_record')->where('OBJECT_ID', `$orderId)->count(),
    'order' => `$order,
    'orderExt' => json_decode((string)(`$order['EXT_JSON'] ?? ''), true),
    'items' => `$items,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    return Invoke-PhpJson -Code $stateCode
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
`$auth['device'] = 'CODEX_WORKFLOW_PROCURE_APPROVE_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => (string)`$user['ID'],
    'tenantId' => trim((string)(`$user['TENANT_ID'] ?? '')),
    'orgId' => trim((string)(`$user['ORG_ID'] ?? '')),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

$context = Invoke-PhpJson -Code $contextCode
$token = [string]$context.token
$userId = [string]$context.userId
$tenantId = [string]$context.tenantId
$orgId = [string]$context.orgId
if ([string]::IsNullOrWhiteSpace($token) -or [string]::IsNullOrWhiteSpace($userId) -or [string]::IsNullOrWhiteSpace($tenantId)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'codex-wf-procure-' + ([Guid]::NewGuid().ToString('N').Substring(0, 10))
$processIds = @()
$processInstanceId = ''

try {
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
`$newId = function (): string { return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999); };
`$now = date('Y-m-d H:i:s');
`$productA = `$newId();
`$productB = `$newId();
foreach ([`$productA => 'A', `$productB => 'B'] as `$productId => `$label) {
    think\facade\Db::name('biz_product')->insert([
        'ID' => `$productId,
        'PRODUCT_NAME' => '$safePrefix product ' . `$label,
        'PRODUCT_CATEGORY' => 'SMOKE',
        'SAFETY_STOCK' => 0,
        'PURCHASE_PRICE' => '0.00',
        'SALE_PRICE' => '0.00',
        'MIN_PRICE' => '0.00',
        'CATEGORY' => 'SINGLE_PRODUCT',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => `$userId,
        'UPDATE_TIME' => null,
        'UPDATE_USER' => null,
        'TENANT_ID' => `$tenantId,
        'SPECS' => 'smoke',
        'ORG' => `$orgId !== '' ? `$orgId : null,
        'status' => 'ENABLE',
    ]);
}
echo json_encode([
    'productA' => `$productA,
    'productB' => `$productB,
    'tenantId' => `$tenantId,
    'orgId' => `$orgId,
    'deliveryCount' => think\facade\Db::name('delivery_record')->count(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    $setup = Invoke-PhpJson -Code $setupCode
    $productA = [string]$setup.productA
    $productB = [string]$setup.productB
    $tenantId = [string]$setup.tenantId
    $orgId = [string]$setup.orgId

    $start = Invoke-JsonPost -Url ($baseUrl + '/biz/process/procure/start') -Token $token -Body @{
        processSmokeMarker = "$prefix-start"
        approveUserIdList = @($userId)
        copyUserIdList = @()
        supplier = @{
            name = "$prefix supplier"
            contacts = 'codex contact'
            phone = '18800000000'
            bankName = 'codex bank'
            bankAccount = 'codex bank account'
        }
        desirePurchaseDate = '2099-04-01 09:00:00'
        procure = $userId
        approvesGeneralOffice = @($userId)
        productInfoList = @(
            @{
                productName = "$prefix requested"
                number = 1
                remark = 'request line'
            }
        )
        amount = '0.00'
        remark = "$prefix procurement request"
        tenantId = $tenantId
    }
    if ([int]$start.code -ne 200) {
        throw "workflow procure start failed: $($start | ConvertTo-Json -Compress)"
    }

    $processInstanceId = [string]$start.data.processInstanceId
    $processIds += $processInstanceId
    $leaderTaskId = [string]$start.data.taskId
    Assert-Equal -Actual ([string]$start.data.processKey) -Expected 'Process_procure' -Name 'start process key'
    if ([string]::IsNullOrWhiteSpace($processInstanceId) -or [string]::IsNullOrWhiteSpace($leaderTaskId)) {
        throw 'start response missing processInstanceId or taskId'
    }
    Write-Host "started Process_procure processInstanceId=$processInstanceId taskId=$leaderTaskId"

    $leaderApprove = Invoke-JsonPost -Url ($baseUrl + '/biz/task/approve') -Token $token -Body @{
        id = $leaderTaskId
        form = @{
            approval = $true
            comment = 'codex leader approve'
        }
    }
    if ([int]$leaderApprove.code -ne 200) {
        throw "workflow procure leader approve failed: $($leaderApprove | ConvertTo-Json -Compress)"
    }
    $procureTaskId = [string]$leaderApprove.data.nextTaskId
    Assert-Equal -Actual ([string]$leaderApprove.data.taskDefinitionKey) -Expected 'Activity_procure_approval' -Name 'leader next task definition'
    Assert-Equal -Actual ([string]$leaderApprove.data.assignee) -Expected $userId -Name 'leader next assignee'

    $midLeader = Get-WorkflowState -ProcessInstanceId $processInstanceId
    Assert-IntEqual -Actual ([int]$midLeader.runtime.taskCount) -Expected 1 -Name 'runtime task count after leader'
    Assert-Equal -Actual ([string]$midLeader.runtime.taskId) -Expected $procureTaskId -Name 'procure task id'
    Assert-Equal -Actual ([string]$midLeader.runtime.taskDefinitionKey) -Expected 'Activity_procure_approval' -Name 'runtime procure task definition'
    Assert-IntEqual -Actual ([int]$midLeader.orderCount) -Expected 0 -Name 'order count after leader'

    $productList = @(
        @{
            productId = $productA
            number = 2
            unitAmount = '12.50'
            discountRate = '0.00'
            amount = '25.00'
            remark = 'codex procure line A'
        },
        @{
            productId = $productB
            number = 3
            unitAmount = '3.50'
            discountRate = '0.00'
            amount = '10.50'
            remark = 'codex procure line B'
        }
    )
    $productListJson = ConvertTo-Json -InputObject $productList -Depth 8 -Compress

    $procureApprove = Invoke-JsonPost -Url ($baseUrl + '/biz/task/approve') -Token $token -Body @{
        id = $procureTaskId
        form = @{
            approval = $true
            comment = 'codex procure confirmation approve'
            productList = $productListJson
            amount = '35.50'
        }
    }
    if ([int]$procureApprove.code -ne 200) {
        throw "workflow procure confirmation failed: $($procureApprove | ConvertTo-Json -Compress)"
    }
    $generalTaskId = [string]$procureApprove.data.nextTaskId
    Assert-Equal -Actual ([string]$procureApprove.data.taskDefinitionKey) -Expected 'Activity_approval_procure' -Name 'procure next task definition'
    Assert-Equal -Actual ([string]$procureApprove.data.assignee) -Expected $userId -Name 'procure next assignee'

    $midProcure = Get-WorkflowState -ProcessInstanceId $processInstanceId
    Assert-IntEqual -Actual ([int]$midProcure.runtime.taskCount) -Expected 1 -Name 'runtime task count after procurement'
    Assert-Equal -Actual ([string]$midProcure.runtime.taskId) -Expected $generalTaskId -Name 'general task id'
    Assert-Equal -Actual ([string]$midProcure.runtime.taskDefinitionKey) -Expected 'Activity_approval_procure' -Name 'runtime general task definition'
    Assert-IntEqual -Actual ([int]$midProcure.orderCount) -Expected 0 -Name 'order count before final approval'
    if (-not ([string]$midProcure.variables.productList).Contains($productA) -or -not ([string]$midProcure.variables.productList).Contains($productB)) {
        throw "productList variable missing product ids: $($midProcure.variables | ConvertTo-Json -Compress)"
    }

    $generalApprove = Invoke-JsonPost -Url ($baseUrl + '/biz/task/approve') -Token $token -Body @{
        id = $generalTaskId
        form = @{
            approval = $true
            comment = 'codex general office approve'
        }
    }
    if ([int]$generalApprove.code -ne 200) {
        throw "workflow procure general approve failed: $($generalApprove | ConvertTo-Json -Compress)"
    }
    Assert-Equal -Actual ([string]$generalApprove.data.processKey) -Expected 'Process_procure' -Name 'final process key'
    Assert-Equal -Actual ([string]$generalApprove.data.status) -Expected 'AGREE' -Name 'final status'
    Assert-Equal -Actual ([string]$generalApprove.data.purchaseOrder.settlementStatus) -Expected 'NOT_COMPLETED' -Name 'response settlement status'
    Assert-Equal -Actual ([string]$generalApprove.data.purchaseOrder.storageStatus) -Expected 'NOT_IN_WAREHOUSE' -Name 'response storage status'
    Assert-IntEqual -Actual ([int]$generalApprove.data.purchaseOrder.itemCount) -Expected 2 -Name 'response item count'
    Assert-DecimalEqual -Actual ([string]$generalApprove.data.purchaseOrder.amount) -Expected 35.50 -Name 'response order amount'

    $final = Get-WorkflowState -ProcessInstanceId $processInstanceId
    Assert-IntEqual -Actual ([int]$final.runtime.taskCount) -Expected 0 -Name 'final runtime task count'
    Assert-IntEqual -Actual ([int]$final.runtime.variableCount) -Expected 0 -Name 'final runtime variable count'
    Assert-IntEqual -Actual ([int]$final.runtime.executionCount) -Expected 0 -Name 'final runtime execution count'
    Assert-Equal -Actual ([string]$final.history.PROC_DEF_KEY_) -Expected 'Process_procure' -Name 'history process key'
    Assert-Equal -Actual ([string]$final.history.STATE_) -Expected 'COMPLETED' -Name 'history state'
    Assert-Equal -Actual ([string]$final.history.END_ACT_ID_) -Expected 'Event_0kb2f2q' -Name 'history end activity'
    Assert-Equal -Actual ([string]$final.variables.approval) -Expected 'true' -Name 'history approval variable'
    Assert-Equal -Actual ([string]$final.variables.status) -Expected 'AGREE' -Name 'history status variable'
    Assert-Equal -Actual ([string]$final.variables.state) -Expected 'AGREE' -Name 'history state variable'
    Assert-Equal -Actual ([string]$final.variables.amount) -Expected '35.50' -Name 'history amount variable'
    Assert-IntEqual -Actual ([int]$final.orderCount) -Expected 1 -Name 'final order count'
    Assert-Equal -Actual ([string]$final.order.SETTLEMENT_STATUS) -Expected 'NOT_COMPLETED' -Name 'order settlement status'
    Assert-Equal -Actual ([string]$final.order.STORAGE_STATUS) -Expected 'NOT_IN_WAREHOUSE' -Name 'order storage status'
    Assert-Equal -Actual ([string]$final.order.INSTANCE_ID) -Expected $processInstanceId -Name 'order instance id'
    Assert-Equal -Actual ([string]$final.order.CREATE_USER) -Expected $userId -Name 'order create user'
    Assert-Equal -Actual ([string]$final.order.TENANT_ID) -Expected $tenantId -Name 'order tenant id'
    if (-not [string]::IsNullOrWhiteSpace($orgId)) {
        Assert-Equal -Actual ([string]$final.order.ORG) -Expected $orgId -Name 'order org'
    }
    Assert-DecimalEqual -Actual ([string]$final.order.AMOUNT) -Expected 35.50 -Name 'order amount'
    Assert-Equal -Actual ([string]$final.orderExt.supplier.name) -Expected "$prefix supplier" -Name 'order supplier name'
    Assert-IntEqual -Actual ([int]$final.items.Count) -Expected 2 -Name 'order item count'
    $itemsByProduct = @{}
    foreach ($item in @($final.items)) {
        $itemsByProduct[[string]$item.PRODUCT_ID] = $item
    }
    if (-not $itemsByProduct.ContainsKey($productA) -or -not $itemsByProduct.ContainsKey($productB)) {
        throw "order item product ids mismatch: $($final.items | ConvertTo-Json -Compress)"
    }
    $itemA = $itemsByProduct[$productA]
    $itemB = $itemsByProduct[$productB]
    Assert-Equal -Actual ([string]$itemA.NUMBER) -Expected '2' -Name 'item A number'
    Assert-DecimalEqual -Actual ([string]$itemA.AMOUNT) -Expected 25.00 -Name 'item A amount'
    Assert-DecimalEqual -Actual ([string]$itemA.UNIT_AMOUNT) -Expected 12.50 -Name 'item A unit amount'
    Assert-Equal -Actual ([string]$itemA.STORAGE_STATUS) -Expected 'NOT_IN_WAREHOUSE' -Name 'item A storage'
    Assert-Equal -Actual ([string]$itemB.NUMBER) -Expected '3' -Name 'item B number'
    Assert-DecimalEqual -Actual ([string]$itemB.AMOUNT) -Expected 10.50 -Name 'item B amount'
    Assert-DecimalEqual -Actual ([string]$itemB.UNIT_AMOUNT) -Expected 3.50 -Name 'item B unit amount'
    Assert-Equal -Actual ([string]$itemB.STORAGE_STATUS) -Expected 'NOT_IN_WAREHOUSE' -Name 'item B storage'
    Assert-IntEqual -Actual ([int]$final.deliveryCount) -Expected 0 -Name 'purchase approval delivery count'

    Write-Host 'workflow procure approve HTTP smoke passed'
} finally {
    Remove-SmokeRows -ProcessInstanceIds $processIds -Prefix $prefix
}
