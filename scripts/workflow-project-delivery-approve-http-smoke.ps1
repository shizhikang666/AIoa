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

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("workflow-project-delivery-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
        [string]$ProductId,
        [string]$WarehouseId
    )

    $safeProcessIds = @($ProcessInstanceIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
    $safeProjectIds = @($ProjectIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
    $safeCustomerId = $CustomerId.Replace("'", "\'")
    $safeProductId = $ProductId.Replace("'", "\'")
    $safeWarehouseId = $WarehouseId.Replace("'", "\'")
    $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pids = [$safeProcessIds];
`$projectIds = [$safeProjectIds];
if (`$pids !== []) {
    `$invoiceIds = think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROCESS_ID', `$pids)->column('ID');
    if (`$invoiceIds !== []) {
        think\facade\Db::name('biz_sale_project_invoice_item')->whereIn('INVOICE_ID', `$invoiceIds)->delete();
    }
    think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROCESS_ID', `$pids)->delete();
    think\facade\Db::name('delivery_record')->whereIn('PROCESS_ID', `$pids)->delete();
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
}
if (`$projectIds !== []) {
    `$itemIds = think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->column('ID');
    if (`$itemIds !== []) {
        think\facade\Db::name('sale_project_product_item_relation')->whereIn('OBJECT_ID', `$itemIds)->delete();
    }
    `$invoiceIds = think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROJECT_ID', `$projectIds)->column('ID');
    if (`$invoiceIds !== []) {
        think\facade\Db::name('biz_sale_project_invoice_item')->whereIn('INVOICE_ID', `$invoiceIds)->delete();
    }
    think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('delivery_record')->whereIn('OBJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->delete();
}
if ('$safeWarehouseId' !== '') {
    think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$safeWarehouseId')->delete();
    think\facade\Db::name('warehouses')->where('ID', '$safeWarehouseId')->delete();
}
if ('$safeProductId' !== '') {
    think\facade\Db::name('inventory')->where('PRODUCT_ID', '$safeProductId')->delete();
    think\facade\Db::name('biz_product')->where('ID', '$safeProductId')->delete();
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
        [string]$ProductId,
        [string]$WarehouseId
    )

    $safeProcessIds = @($ProcessInstanceIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
    $safeProjectIds = @($ProjectIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
    $safeProductId = $ProductId.Replace("'", "\'")
    $safeWarehouseId = $WarehouseId.Replace("'", "\'")
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
`$invoiceIds = `$pids !== [] ? think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROCESS_ID', `$pids)->column('ID') : [];
echo json_encode([
    'projects' => `$projects,
    'processes' => `$processes,
    'items' => `$projectIds !== [] ? think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->select()->toArray() : [],
    'invoices' => `$pids !== [] ? think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROCESS_ID', `$pids)->select()->toArray() : [],
    'invoiceItems' => `$invoiceIds !== [] ? think\facade\Db::name('biz_sale_project_invoice_item')->whereIn('INVOICE_ID', `$invoiceIds)->select()->toArray() : [],
    'deliveryRecords' => `$pids !== [] ? think\facade\Db::name('delivery_record')->whereIn('PROCESS_ID', `$pids)->select()->toArray() : [],
    'inventory' => think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$safeWarehouseId')->where('PRODUCT_ID', '$safeProductId')->find(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    return Invoke-PhpJson -Code $stateCode
}

function New-StartBody {
    param(
        [Parameter(Mandatory = $true)][string]$ProjectId,
        [Parameter(Mandatory = $true)][string]$ItemId,
        [string]$Amount = '3'
    )

    return @{
        projectId = $ProjectId
        approveUserIdList = @($script:UserId)
        copyUserIdList = @()
        fileIdList = @()
        projectProductItemList = @(@{
            projectProductItemId = $ItemId
            productId = $script:ProductId
            warehousesId = $script:WarehouseId
            amount = $Amount
            remark = "$script:Prefix item"
        })
        consignee = "$script:Prefix consignee"
        logisticsCategory = 'EXPRESS'
        phone = '18800000002'
        logisticsId = $script:LogisticsId
        freight = '0.00'
        freightCategory = 'BUYER_PAY'
        freightTime = $script:FreightTime
        unit = "$script:Prefix unit"
        address = "$script:Prefix address"
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
$Prefix = "codex-wf-delivery-$seed"
$CustomerId = "C$seed"
$ProjectCancelId = "PC$seed"
$ProjectRejectId = "PR$seed"
$ProjectApproveId = "PA$seed"
$ItemCancelId = "IC$seed"
$ItemRejectId = "IR$seed"
$ItemApproveId = "IA$seed"
$ProductId = "P$seed"
$WarehouseId = "W$seed"
$InventoryId = "V$seed"
$LogisticsId = "L$seed"
$FreightTime = '2026-06-22 12:13:14'
$processIds = @()
$UserId = ''

try {
    Remove-SmokeRows -ProcessInstanceIds @() -ProjectIds @($ProjectCancelId, $ProjectRejectId, $ProjectApproveId) -CustomerId $CustomerId -ProductId $ProductId -WarehouseId $WarehouseId

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
think\facade\Db::name('biz_product')->insert([
    'ID' => '$ProductId',
    'PRODUCT_NAME' => '$safePrefix product',
    'PRODUCT_CATEGORY' => 'SMOKE',
    'SAFETY_STOCK' => 0,
    'PURCHASE_PRICE' => '10.00',
    'SALE_PRICE' => '20.00',
    'MIN_PRICE' => '8.00',
    'CATEGORY' => 'SINGLE_PRODUCT',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'SPECS' => 'smoke',
    'ORG' => `$orgId !== '' ? `$orgId : null,
    'status' => 'ENABLE',
]);
think\facade\Db::name('warehouses')->insert([
    'ID' => '$WarehouseId',
    'NAME' => '$safePrefix warehouse',
    'CODE' => substr('$seed', 0, 20),
    'ADDRESS' => '$safePrefix address',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'ORG' => `$orgId !== '' ? `$orgId : null,
]);
think\facade\Db::name('inventory')->insert([
    'ID' => '$InventoryId',
    'WAREHOUSES_ID' => '$WarehouseId',
    'PRODUCT_ID' => '$ProductId',
    'CURRENT_COUNT' => '10',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 0,
]);
foreach ([['$ProjectCancelId', '$ItemCancelId', 'cancel'], ['$ProjectRejectId', '$ItemRejectId', 'reject'], ['$ProjectApproveId', '$ItemApproveId', 'approve']] as `$project) {
    think\facade\Db::name('biz_sale_project')->insert([
        'ID' => `$project[0],
        'CUSTOMER' => '$CustomerId',
        'PROJECT_NAME' => '$safePrefix ' . `$project[2],
        'PROJECT_STATE' => 'WAIT_DELIVER',
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
        'ID' => `$project[1],
        'PROJECT_ID' => `$project[0],
        'PRODUCT_ID' => '$ProductId',
        'CATEGORY' => 'INIT',
        'STATE' => 'WAIT_DELIVER',
        'NUMBER' => '5',
        'DELIVERY' => '0',
        'UNIT_PRICE' => '20.00',
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
`$auth['device'] = 'CODEX_WORKFLOW_PROJECT_DELIVERY_APPROVE_SMOKE';
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

    $noToken = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/delivery/start') -Body (New-StartBody -ProjectId $ProjectCancelId -ItemId $ItemCancelId)
    Assert-IntEqual -Actual ([int]$noToken.code) -Expected 401 -Name 'project delivery start no-token code'

    $missingAmount = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/delivery/start') -Token $token -Body @{
        projectId = $ProjectCancelId
        approveUserIdList = @($UserId)
        copyUserIdList = @()
        projectProductItemList = @(@{
            projectProductItemId = $ItemCancelId
            productId = $ProductId
            warehousesId = $WarehouseId
        })
        consignee = "$Prefix consignee"
        logisticsCategory = 'EXPRESS'
        phone = '18800000002'
        logisticsId = $LogisticsId
        freight = '0.00'
        freightCategory = 'BUYER_PAY'
        freightTime = $FreightTime
        unit = "$Prefix unit"
        address = "$Prefix address"
    }
    Assert-IntEqual -Actual ([int]$missingAmount.code) -Expected 400 -Name 'project delivery missing amount code'

    $cancelStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/delivery/start') -Token $token -Body (New-StartBody -ProjectId $ProjectCancelId -ItemId $ItemCancelId)
    Assert-IntEqual -Actual ([int]$cancelStart.code) -Expected 200 -Name 'project delivery cancel start code'
    $cancelProcessId = [string]$cancelStart.data.processInstanceId
    $processIds += $cancelProcessId
    Assert-Equal -Actual ([string]$cancelStart.data.processKey) -Expected 'Process_sale_project_delivery' -Name 'project delivery cancel process key'
    $cancel = Invoke-JsonPost -Url ($baseUrl + '/biz/process/cancel') -Token $token -Body @{ id = $cancelProcessId }
    Assert-IntEqual -Actual ([int]$cancel.code) -Expected 200 -Name 'project delivery cancel code'

    $rejectStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/delivery/start') -Token $token -Body (New-StartBody -ProjectId $ProjectRejectId -ItemId $ItemRejectId)
    Assert-IntEqual -Actual ([int]$rejectStart.code) -Expected 200 -Name 'project delivery reject start code'
    $rejectProcessId = [string]$rejectStart.data.processInstanceId
    $processIds += $rejectProcessId
    $reject = Invoke-JsonPost -Url ($baseUrl + '/biz/task/reject') -Token $token -Body @{
        id = [string]$rejectStart.data.taskId
        form = @{ comment = "$Prefix reject" }
    }
    Assert-IntEqual -Actual ([int]$reject.code) -Expected 200 -Name 'project delivery reject code'

    $approveStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/delivery/start') -Token $token -Body (New-StartBody -ProjectId $ProjectApproveId -ItemId $ItemApproveId)
    Assert-IntEqual -Actual ([int]$approveStart.code) -Expected 200 -Name 'project delivery approve start code'
    $approveProcessId = [string]$approveStart.data.processInstanceId
    $processIds += $approveProcessId
    $approve = Invoke-JsonPost -Url ($baseUrl + '/biz/task/approve') -Token $token -Body @{
        id = [string]$approveStart.data.taskId
        form = @{
            approval = $true
            comment = "$Prefix approve"
        }
    }
    Assert-IntEqual -Actual ([int]$approve.code) -Expected 200 -Name 'project delivery approve code'
    Assert-Equal -Actual ([string]$approve.data.processKey) -Expected 'Process_sale_project_delivery' -Name 'project delivery approve process key'
    Assert-Equal -Actual ([string]$approve.data.saleProjectDelivery.projectState) -Expected 'PARTIALLY_SHIPPED' -Name 'project delivery approve response state'

    $state = Get-State -ProcessInstanceIds $processIds -ProjectIds @($ProjectCancelId, $ProjectRejectId, $ProjectApproveId) -ProductId $ProductId -WarehouseId $WarehouseId
    foreach ($processId in $processIds) {
        $process = Get-MapValue -Map $state.processes -Key $processId
        Assert-IntEqual -Actual ([int]$process.ruTask) -Expected 0 -Name "runtime task count $processId"
        Assert-IntEqual -Actual ([int]$process.ruVariable) -Expected 0 -Name "runtime variable count $processId"
        Assert-IntEqual -Actual ([int]$process.ruExecution) -Expected 0 -Name "runtime execution count $processId"
        Assert-Equal -Actual ([string]$process.hiProc.PROC_DEF_KEY_) -Expected 'Process_sale_project_delivery' -Name "history process key $processId"
        Assert-Equal -Actual ([string]$process.hiProc.STATE_) -Expected 'COMPLETED' -Name "history process state $processId"
    }

    Assert-IntEqual -Actual ([int]$state.invoices.Count) -Expected 1 -Name 'delivery invoice row count'
    Assert-IntEqual -Actual ([int]$state.invoiceItems.Count) -Expected 1 -Name 'delivery invoice item row count'
    Assert-IntEqual -Actual ([int]$state.deliveryRecords.Count) -Expected 1 -Name 'delivery record row count'

    $invoice = $state.invoices[0]
    $invoiceItem = $state.invoiceItems[0]
    $deliveryRecord = $state.deliveryRecords[0]
    Assert-Equal -Actual ([string]$invoice.PROJECT_ID) -Expected $ProjectApproveId -Name 'invoice project id'
    Assert-Equal -Actual ([string]$invoice.PROCESS_ID) -Expected $approveProcessId -Name 'invoice process id'
    Assert-Equal -Actual ([string]$invoice.LOGISTICS_ID) -Expected $LogisticsId -Name 'invoice logistics id'
    Assert-DecimalEqual -Actual ([string]$invoice.FREIGHT) -Expected 0.00 -Name 'invoice freight'
    Assert-Equal -Actual ([string]$invoiceItem.PROJECT_PRODUCT_ITEM_ID) -Expected $ItemApproveId -Name 'invoice item project item id'
    Assert-Equal -Actual ([string]$invoiceItem.WAREHOUSES_ID) -Expected $WarehouseId -Name 'invoice item warehouse'
    Assert-DecimalEqual -Actual ([string]$invoiceItem.AMOUNT) -Expected 3 -Name 'invoice item amount'
    Assert-Equal -Actual ([string]$deliveryRecord.PROCESS_ID) -Expected $approveProcessId -Name 'delivery record process id'
    Assert-Equal -Actual ([string]$deliveryRecord.PROCESS_CATEGORY) -Expected 'Process_sale_project_delivery' -Name 'delivery record process category'
    Assert-Equal -Actual ([string]$deliveryRecord.CATEGORY) -Expected 'OUT' -Name 'delivery record category'
    Assert-Equal -Actual ([string]$deliveryRecord.OBJECT_ID) -Expected $ProjectApproveId -Name 'delivery record object id'
    Assert-Equal -Actual ([string]$deliveryRecord.PRODUCT_ID) -Expected $ProductId -Name 'delivery record product'
    Assert-Equal -Actual ([string]$deliveryRecord.WAREHOUSES_ID) -Expected $WarehouseId -Name 'delivery record warehouse'
    Assert-DecimalEqual -Actual ([string]$deliveryRecord.AMOUNT) -Expected 3 -Name 'delivery record amount'
    Assert-DecimalEqual -Actual ([string]$state.inventory.CURRENT_COUNT) -Expected 7 -Name 'inventory current count'

    foreach ($projectId in @($ProjectCancelId, $ProjectRejectId)) {
        $project = Get-MapValue -Map $state.projects -Key $projectId
        Assert-Equal -Actual ([string]$project.PROJECT_STATE) -Expected 'WAIT_DELIVER' -Name "$projectId project state"
    }
    $approvedProject = Get-MapValue -Map $state.projects -Key $ProjectApproveId
    Assert-Equal -Actual ([string]$approvedProject.PROJECT_STATE) -Expected 'PARTIALLY_SHIPPED' -Name 'approved project state'

    $cancelItem = @($state.items | Where-Object { [string]$_.ID -eq $ItemCancelId })[0]
    $rejectItem = @($state.items | Where-Object { [string]$_.ID -eq $ItemRejectId })[0]
    $approveItem = @($state.items | Where-Object { [string]$_.ID -eq $ItemApproveId })[0]
    Assert-DecimalEqual -Actual ([string]$cancelItem.DELIVERY) -Expected 0 -Name 'cancel item delivery'
    Assert-DecimalEqual -Actual ([string]$rejectItem.DELIVERY) -Expected 0 -Name 'reject item delivery'
    Assert-DecimalEqual -Actual ([string]$approveItem.DELIVERY) -Expected 3 -Name 'approve item delivery'
    Assert-Equal -Actual ([string]$approveItem.STATE) -Expected 'PART_WAIT_DELIVER' -Name 'approve item state'

    Write-Host 'workflow project delivery approve smoke passed'
} finally {
    Remove-SmokeRows -ProcessInstanceIds $processIds -ProjectIds @($ProjectCancelId, $ProjectRejectId, $ProjectApproveId) -CustomerId $CustomerId -ProductId $ProductId -WarehouseId $WarehouseId
}
