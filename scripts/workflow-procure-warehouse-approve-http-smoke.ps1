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

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("workflow-procure-warehouse-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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

function Remove-SmokeRows {
    param(
        [string]$ProcessInstanceId,
        [string]$WarehouseId,
        [string]$OrderId,
        [string]$Prefix
    )

    if ([string]::IsNullOrWhiteSpace($WarehouseId) -and [string]::IsNullOrWhiteSpace($OrderId) -and [string]::IsNullOrWhiteSpace($ProcessInstanceId)) {
        return
    }

    $safeProcessId = $ProcessInstanceId.Replace("'", "\'")
    $safeWarehouseId = $WarehouseId.Replace("'", "\'")
    $safeOrderId = $OrderId.Replace("'", "\'")
    $safePrefix = $Prefix.Replace("'", "\'")
    $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$safeProcessId';
`$warehouseId = '$safeWarehouseId';
`$orderId = '$safeOrderId';
if (`$pid !== '') {
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
}
if (`$orderId !== '') {
    think\facade\Db::name('delivery_record')->where('OBJECT_ID', `$orderId)->delete();
    think\facade\Db::name('biz_purchase_order_item')->where('PURCHASE_ORDER_ID', `$orderId)->delete();
    think\facade\Db::name('biz_purchase_order')->where('ID', `$orderId)->delete();
}
if (`$warehouseId !== '') {
    think\facade\Db::name('inventory')->where('WAREHOUSES_ID', `$warehouseId)->delete();
    think\facade\Db::name('warehouses')->where('ID', `$warehouseId)->delete();
}
think\facade\Db::name('biz_product')->whereLike('PRODUCT_NAME', '$safePrefix%')->delete();
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
`$auth['device'] = 'CODEX_WORKFLOW_PROCURE_WAREHOUSE_APPROVE_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => (string)`$user['ID'],
    'tenantId' => trim((string)(`$user['TENANT_ID'] ?? '')),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

$context = Invoke-PhpJson -Code $contextCode
$token = [string]$context.token
$userId = [string]$context.userId
$tenantId = [string]$context.tenantId
if ([string]::IsNullOrWhiteSpace($token) -or [string]::IsNullOrWhiteSpace($userId)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'codex-wf-po-wh-' + ([Guid]::NewGuid().ToString('N').Substring(0, 10))
$warehouseId = ''
$orderId = ''
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
if (`$orgId === '') {
    `$org = think\facade\Db::name('sys_org')
        ->where(function (`$query) { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })
        ->field('ID')
        ->order('ID', 'asc')
        ->find();
    `$orgId = (string)(`$org['ID'] ?? '');
}
`$newId = function (): string { return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999); };
`$now = date('Y-m-d H:i:s');
`$warehouseId = `$newId();
`$productExisting = `$newId();
`$productNew = `$newId();
`$inventoryExisting = `$newId();
`$orderId = `$newId();
`$itemExisting = `$newId();
`$itemNew = `$newId();
`$code = substr(preg_replace('/[^A-Za-z0-9]/', '', '$safePrefix'), 0, 20);
think\facade\Db::name('warehouses')->insert([
    'ID' => `$warehouseId,
    'NAME' => '$safePrefix warehouse',
    'CODE' => `$code,
    'ADDRESS' => 'codex smoke',
    'SORT_CODE' => 998,
    'USER' => `$userId,
    'ORG' => `$orgId !== '' ? `$orgId : null,
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => null,
    'UPDATE_USER' => null,
    'TENANT_ID' => `$tenantId,
]);
foreach ([`$productExisting => 'Existing', `$productNew => 'New'] as `$productId => `$label) {
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
think\facade\Db::name('inventory')->insert([
    'ID' => `$inventoryExisting,
    'WAREHOUSES_ID' => `$warehouseId,
    'PRODUCT_ID' => `$productExisting,
    'CURRENT_COUNT' => 2,
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => null,
    'UPDATE_USER' => null,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 4,
]);
think\facade\Db::name('biz_purchase_order')->insert([
    'ID' => `$orderId,
    'TITLE' => '$safePrefix order',
    'SETTLEMENT_STATUS' => 'NOT_COMPLETED',
    'STORAGE_STATUS' => 'NOT_IN_WAREHOUSE',
    'SUPPLIER_ID' => '',
    'INSTANCE_ID' => '',
    'DESIRE_PURCHASE_DATE' => `$now,
    'AMOUNT' => '80.00',
    'REMARK' => '$safePrefix',
    'EXT_JSON' => json_encode(['supplier' => ['name' => '$safePrefix supplier']], JSON_UNESCAPED_SLASHES),
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => null,
    'UPDATE_USER' => null,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 0,
    'ORG' => `$orgId,
]);
`$itemBase = [
    'AMOUNT' => '10.00',
    'UNIT_AMOUNT' => '10.00',
    'DISCOUNT_RATE' => '0.00',
    'REMARK' => '$safePrefix item',
    'EXT_JSON' => null,
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => null,
    'UPDATE_USER' => null,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 0,
    'FREIGHT_SHARE_AMOUNT' => '0.00',
    'UNIT_COST_WITH_FREIGHT' => '10.00',
];
think\facade\Db::name('biz_purchase_order_item')->insert(array_merge(`$itemBase, [
    'ID' => `$itemExisting,
    'PURCHASE_ORDER_ID' => `$orderId,
    'STORAGE_STATUS' => 'NOT_IN_WAREHOUSE',
    'PRODUCT_ID' => `$productExisting,
    'NUMBER' => 3,
]));
think\facade\Db::name('biz_purchase_order_item')->insert(array_merge(`$itemBase, [
    'ID' => `$itemNew,
    'PURCHASE_ORDER_ID' => `$orderId,
    'STORAGE_STATUS' => 'NOT_IN_WAREHOUSE',
    'PRODUCT_ID' => `$productNew,
    'NUMBER' => 5,
]));
echo json_encode([
    'warehouseId' => `$warehouseId,
    'productExisting' => `$productExisting,
    'productNew' => `$productNew,
    'orderId' => `$orderId,
    'tenantId' => `$tenantId,
    'counts' => [
        'inventory' => think\facade\Db::name('inventory')->count(),
        'delivery' => think\facade\Db::name('delivery_record')->count(),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    $setup = Invoke-PhpJson -Code $setupCode
    $warehouseId = [string]$setup.warehouseId
    $orderId = [string]$setup.orderId
    $productExisting = [string]$setup.productExisting
    $productNew = [string]$setup.productNew
    $tenantId = [string]$setup.tenantId

    $start = Invoke-JsonPost -Url ($baseUrl + '/biz/process/procure/warehouse/start') -Token $token -Body @{
        processSmokeMarker = "$prefix-start"
        approveUserIdList = @($userId)
        copyUserIdList = @()
        orderId = $orderId
        warehousesId = $warehouseId
        remark = "$prefix workflow approval"
        tenantId = $tenantId
    }
    if ([int]$start.code -ne 200) {
        throw "workflow procure warehouse start failed: $($start | ConvertTo-Json -Compress)"
    }

    $processInstanceId = [string]$start.data.processInstanceId
    $taskId = [string]$start.data.taskId
    Assert-Equal -Actual ([string]$start.data.processKey) -Expected 'Process_procure_in_warehouse' -Name 'start process key'
    if ([string]::IsNullOrWhiteSpace($processInstanceId) -or [string]::IsNullOrWhiteSpace($taskId)) {
        throw 'start response missing processInstanceId or taskId'
    }
    Write-Host "started Process_procure_in_warehouse processInstanceId=$processInstanceId taskId=$taskId"

    $approve = Invoke-JsonPost -Url ($baseUrl + '/biz/task/approve') -Token $token -Body @{
        id = $taskId
        form = @{
            approval = $true
            comment = 'codex workflow procure warehouse approve smoke'
        }
    }
    if ([int]$approve.code -ne 200) {
        throw "workflow procure warehouse approve failed: $($approve | ConvertTo-Json -Compress)"
    }

    Assert-Equal -Actual ([string]$approve.data.processKey) -Expected 'Process_procure_in_warehouse' -Name 'approve process key'
    Assert-Equal -Actual ([string]$approve.data.purchaseWarehouse.id) -Expected $orderId -Name 'approve warehouse order id'
    Assert-Equal -Actual ([string]$approve.data.purchaseWarehouse.storageStatus) -Expected 'IN_WAREHOUSE' -Name 'approve warehouse status'
    Assert-Equal -Actual ([string]$approve.data.purchaseWarehouse.updatedItems) -Expected '2' -Name 'approve warehouse updated items'

    $verifyCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pid = '$processInstanceId';
`$orderId = '$orderId';
`$warehouseId = '$warehouseId';
`$productExisting = '$productExisting';
`$productNew = '$productNew';
`$order = think\facade\Db::name('biz_purchase_order')->where('ID', `$orderId)->find();
`$items = think\facade\Db::name('biz_purchase_order_item')->where('PURCHASE_ORDER_ID', `$orderId)->order('ID', 'asc')->select()->toArray();
`$deliveries = think\facade\Db::name('delivery_record')->where('OBJECT_ID', `$orderId)->where('WAREHOUSES_ID', `$warehouseId)->order('PRODUCT_ID', 'asc')->select()->toArray();
`$inventoryExisting = think\facade\Db::name('inventory')->where('WAREHOUSES_ID', `$warehouseId)->where('PRODUCT_ID', `$productExisting)->find();
`$inventoryNew = think\facade\Db::name('inventory')->where('WAREHOUSES_ID', `$warehouseId)->where('PRODUCT_ID', `$productNew)->find();
`$hiProc = think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->find();
`$vars = think\facade\Db::name('act_hi_varinst')->where('PROC_INST_ID_', `$pid)->column('TEXT_', 'NAME_');
echo json_encode([
    'runtimeTasks' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->count(),
    'runtimeVars' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$pid)->count(),
    'runtimeExecs' => think\facade\Db::name('act_ru_execution')->where('PROC_INST_ID_', `$pid)->whereOr('ID_', `$pid)->count(),
    'inventoryCount' => think\facade\Db::name('inventory')->count(),
    'deliveryCount' => think\facade\Db::name('delivery_record')->count(),
    'order' => `$order,
    'items' => `$items,
    'deliveries' => `$deliveries,
    'inventoryExisting' => `$inventoryExisting,
    'inventoryNew' => `$inventoryNew,
    'hiProc' => `$hiProc,
    'vars' => `$vars,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    $verify = Invoke-PhpJson -Code $verifyCode
    Assert-IntEqual -Actual ([int]$verify.runtimeTasks) -Expected 0 -Name 'runtime task cleanup'
    Assert-IntEqual -Actual ([int]$verify.runtimeVars) -Expected 0 -Name 'runtime var cleanup'
    Assert-IntEqual -Actual ([int]$verify.runtimeExecs) -Expected 0 -Name 'runtime execution cleanup'
    Assert-IntEqual -Actual ([int]$verify.inventoryCount) -Expected ([int]$setup.counts.inventory + 1) -Name 'inventory count'
    Assert-IntEqual -Actual ([int]$verify.deliveryCount) -Expected ([int]$setup.counts.delivery + 2) -Name 'delivery count'
    Assert-Equal -Actual ([string]$verify.order.STORAGE_STATUS) -Expected 'IN_WAREHOUSE' -Name 'order storage'
    Assert-Equal -Actual ([string]$verify.items[0].STORAGE_STATUS) -Expected 'IN_WAREHOUSE' -Name 'item 0 storage'
    Assert-Equal -Actual ([string]$verify.items[1].STORAGE_STATUS) -Expected 'IN_WAREHOUSE' -Name 'item 1 storage'
    Assert-Equal -Actual ([string]$verify.inventoryExisting.CURRENT_COUNT) -Expected '5' -Name 'existing inventory'
    Assert-Equal -Actual ([string]$verify.inventoryNew.CURRENT_COUNT) -Expected '5' -Name 'new inventory'
    Assert-Equal -Actual ([string]$verify.deliveries[0].PROCESS_ID) -Expected $processInstanceId -Name 'delivery 0 process id'
    Assert-Equal -Actual ([string]$verify.deliveries[0].PROCESS_CATEGORY) -Expected 'Process_procure_in_warehouse' -Name 'delivery 0 process category'
    Assert-Equal -Actual ([string]$verify.deliveries[0].OBJECT_ID) -Expected $orderId -Name 'delivery 0 object id'
    Assert-Equal -Actual ([string]$verify.deliveries[1].PROCESS_ID) -Expected $processInstanceId -Name 'delivery 1 process id'
    Assert-Equal -Actual ([string]$verify.hiProc.PROC_DEF_KEY_) -Expected 'Process_procure_in_warehouse' -Name 'history process key'
    Assert-Equal -Actual ([string]$verify.hiProc.STATE_) -Expected 'COMPLETED' -Name 'history state'
    Assert-Equal -Actual ([string]$verify.vars.status) -Expected 'AGREE' -Name 'history status variable'
    Assert-Equal -Actual ([string]$verify.vars.state) -Expected 'AGREE' -Name 'history state variable'

    Write-Host 'workflow procure warehouse approve HTTP smoke passed'
} finally {
    Remove-SmokeRows -ProcessInstanceId $processInstanceId -WarehouseId $warehouseId -OrderId $orderId -Prefix $prefix
}
