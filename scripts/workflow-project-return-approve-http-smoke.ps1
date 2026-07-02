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

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("workflow-project-return-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    $json = $Body | ConvertTo-Json -Depth 16
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

function Invoke-JsonGet {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$Token
    )

    $raw = (& curl.exe -sS -X GET $Url -H "Authorization: Bearer $Token")
    if ($LASTEXITCODE -ne 0) {
        throw "curl failed for $Url"
    }

    return (($raw -join "`n").TrimStart([char]0xFEFF) | ConvertFrom-Json)
}

function Enc {
    param([Parameter(Mandatory = $true)][string]$Value)

    return [System.Uri]::EscapeDataString($Value)
}

function Assert-Code {
    param(
        [Parameter(Mandatory = $true)]$Json,
        [Parameter(Mandatory = $true)][int]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    if ([int]$Json.code -ne $Expected) {
        throw "$Name expected code=$Expected actual=$($Json.code) response=$($Json | ConvertTo-Json -Compress -Depth 8)"
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

function New-SqlLiteralList {
    param([string[]]$Values)

    return @($Values | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
}

function Remove-SmokeRows {
    param(
        [string[]]$ProcessInstanceIds,
        [string[]]$ProjectIds,
        [string]$CustomerId,
        [string[]]$ProductIds,
        [string]$WarehouseId,
        [string]$AccountId = ''
    )

    $safeProcessIds = New-SqlLiteralList -Values $ProcessInstanceIds
    $safeProjectIds = New-SqlLiteralList -Values $ProjectIds
    $safeProductIds = New-SqlLiteralList -Values $ProductIds
    $safeCustomerId = $CustomerId.Replace("'", "\'")
    $safeWarehouseId = $WarehouseId.Replace("'", "\'")
    $safeAccountId = $AccountId.Replace("'", "\'")
    $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pids = [$safeProcessIds];
`$projectIds = [$safeProjectIds];
`$productIds = [$safeProductIds];
if (`$pids !== []) {
    `$orderIds = think\facade\Db::name('return_order')->whereIn('PROCESS_ID', `$pids)->column('ID');
    if (`$orderIds !== []) {
        think\facade\Db::name('return_order_item')->whereIn('RETURN_ORDER_ID', `$orderIds)->delete();
        think\facade\Db::name('delivery_record')->whereIn('OBJECT_ID', `$orderIds)->delete();
    }
    think\facade\Db::name('return_order')->whereIn('PROCESS_ID', `$pids)->delete();
    `$invoiceIds = think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROCESS_ID', `$pids)->column('ID');
    if (`$invoiceIds !== []) {
        think\facade\Db::name('biz_sale_project_invoice_item')->whereIn('INVOICE_ID', `$invoiceIds)->delete();
    }
    think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROCESS_ID', `$pids)->delete();
    think\facade\Db::name('biz_sale_project_invoicing')->whereIn('PROCESS_ID', `$pids)->delete();
    think\facade\Db::name('delivery_record')->whereIn('PROCESS_ID', `$pids)->delete();
    think\facade\Db::name('biz_payment_record')->whereIn('PROCESS_ID', `$pids)->delete();
    think\facade\Db::name('biz_expenditure_record')->whereIn('PROCESS_ID', `$pids)->delete();
    think\facade\Db::name('settlement_account_statement')->whereIn('PROCESS_ID', `$pids)->delete();
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
    `$orderIds = think\facade\Db::name('return_order')->whereIn('PROJECT_ID', `$projectIds)->column('ID');
    if (`$orderIds !== []) {
        think\facade\Db::name('return_order_item')->whereIn('RETURN_ORDER_ID', `$orderIds)->delete();
        think\facade\Db::name('delivery_record')->whereIn('OBJECT_ID', `$orderIds)->delete();
        think\facade\Db::name('return_order')->whereIn('ID', `$orderIds)->delete();
    }
    `$itemIds = think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->column('ID');
    if (`$itemIds !== []) {
        think\facade\Db::name('sale_project_product_item_relation')->whereIn('OBJECT_ID', `$itemIds)->delete();
    }
    think\facade\Db::name('delivery_record')->whereIn('OBJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->delete();
}
if ('$safeWarehouseId' !== '') {
    think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$safeWarehouseId')->delete();
    think\facade\Db::name('warehouses')->where('ID', '$safeWarehouseId')->delete();
}
if ('$safeAccountId' !== '') {
    think\facade\Db::name('settlement_account_statement')->where('ACCOUNT_ID', '$safeAccountId')->delete();
    think\facade\Db::name('biz_expenditure_record')->where('TARGET_ID', '$safeAccountId')->delete();
    think\facade\Db::name('settlement_account')->where('ID', '$safeAccountId')->delete();
}
if (`$productIds !== []) {
    think\facade\Db::name('inventory')->whereIn('PRODUCT_ID', `$productIds)->delete();
    think\facade\Db::name('biz_product')->whereIn('ID', `$productIds)->delete();
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
        [string]$WarehouseId,
        [string]$ChildProductId,
        [string]$AccountId
    )

    $safeProcessIds = New-SqlLiteralList -Values $ProcessInstanceIds
    $safeProjectIds = New-SqlLiteralList -Values $ProjectIds
    $safeWarehouseId = $WarehouseId.Replace("'", "\'")
    $safeChildProductId = $ChildProductId.Replace("'", "\'")
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
            'hiProc' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$processId)->field('PROC_DEF_KEY_,STATE_,END_ACT_ID_,END_TIME_')->find(),
        ];
    }
}
`$returnOrders = `$pids !== [] ? think\facade\Db::name('return_order')->whereIn('PROCESS_ID', `$pids)->select()->toArray() : [];
`$orderIds = array_values(array_map(static fn (`$row) => (string)`$row['ID'], `$returnOrders));
`$expenditures = `$pids !== [] ? think\facade\Db::name('biz_expenditure_record')->whereIn('PROCESS_ID', `$pids)->select()->toArray() : [];
`$statements = `$pids !== [] ? think\facade\Db::name('settlement_account_statement')->whereIn('PROCESS_ID', `$pids)->select()->toArray() : [];
`$returnItems = `$orderIds !== [] ? think\facade\Db::name('return_order_item')->whereIn('RETURN_ORDER_ID', `$orderIds)->select()->toArray() : [];
`$projectItems = `$projectIds !== [] ? think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->select()->toArray() : [];
`$projectItemIds = array_values(array_map(static fn (`$row) => (string)`$row['ID'], `$projectItems));
`$relations = `$projectItemIds !== [] ? think\facade\Db::name('sale_project_product_item_relation')->whereIn('OBJECT_ID', `$projectItemIds)->select()->toArray() : [];
`$invoiceIds = `$pids !== [] ? think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROCESS_ID', `$pids)->column('ID') : [];
echo json_encode([
    'projects' => `$projects,
    'processes' => `$processes,
    'returnOrders' => `$returnOrders,
    'returnItems' => `$returnItems,
    'projectItems' => `$projectItems,
    'relations' => `$relations,
    'deliveryRecords' => `$pids !== [] ? think\facade\Db::name('delivery_record')->whereIn('PROCESS_ID', `$pids)->select()->toArray() : [],
    'invoiceCount' => `$pids !== [] ? think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROCESS_ID', `$pids)->count() : 0,
    'invoiceItemCount' => `$invoiceIds !== [] ? think\facade\Db::name('biz_sale_project_invoice_item')->whereIn('INVOICE_ID', `$invoiceIds)->count() : 0,
    'invoicingCount' => `$pids !== [] ? think\facade\Db::name('biz_sale_project_invoicing')->whereIn('PROCESS_ID', `$pids)->count() : 0,
    'paymentCount' => `$pids !== [] ? think\facade\Db::name('biz_payment_record')->whereIn('PROCESS_ID', `$pids)->count() : 0,
    'expenditureCount' => count(`$expenditures),
    'statementCount' => count(`$statements),
    'expenditures' => `$expenditures,
    'statements' => `$statements,
    'account' => '$safeAccountId' !== '' ? think\facade\Db::name('settlement_account')->where('ID', '$safeAccountId')->find() : null,
    'inventory' => think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$safeWarehouseId')->where('PRODUCT_ID', '$safeChildProductId')->find(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    return Invoke-PhpJson -Code $stateCode
}

function New-StartBody {
    param(
        [Parameter(Mandatory = $true)][string]$ProjectId,
        [Parameter(Mandatory = $true)][string]$ItemId,
        [string]$Amount = '2'
    )

    return @{
        projectId = $ProjectId
        approveUserIdList = @($script:UserId)
        copyUserIdList = @()
        fileIdList = @()
        warehousesId = $script:WarehouseId
        logisticsCategory = 'EXPRESS'
        logisticsId = $script:LogisticsId
        amount = '20.00'
        remark = "$script:Prefix return"
        productList = @(@{
            projectProductItemId = $ItemId
            productId = $script:ProductId
            productName = "$script:Prefix product"
            amount = $Amount
            remark = "$script:Prefix item"
        })
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
$Prefix = "codex-wf-return-$seed"
$CustomerId = "C$seed"
$ProjectCancelId = "PC$seed"
$ProjectRejectId = "PR$seed"
$ProjectApproveId = "PA$seed"
$ItemCancelId = "IC$seed"
$ItemRejectId = "IR$seed"
$ItemApproveId = "IA$seed"
$RelationCancelId = "RC$seed"
$RelationRejectId = "RR$seed"
$RelationApproveId = "RA$seed"
$ProductId = "P$seed"
$ChildProductId = "H$seed"
$WarehouseId = "W$seed"
$InventoryId = "V$seed"
$SettlementAccountId = "S$seed"
$LogisticsId = "L$seed"
$processIds = @()
$UserId = ''

try {
    Remove-SmokeRows -ProcessInstanceIds @() -ProjectIds @($ProjectCancelId, $ProjectRejectId, $ProjectApproveId) -CustomerId $CustomerId -ProductIds @($ProductId, $ChildProductId) -WarehouseId $WarehouseId -AccountId $SettlementAccountId

    $safeAccount = $account.Replace("'", "\'")
    $safePrefix = $Prefix.Replace("'", "\'")
    $safeSettlementAccountId = $SettlementAccountId.Replace("'", "\'")
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
foreach ([['$ProductId', '$safePrefix product'], ['$ChildProductId', '$safePrefix child']] as `$product) {
    think\facade\Db::name('biz_product')->insert([
        'ID' => `$product[0],
        'PRODUCT_NAME' => `$product[1],
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
}
think\facade\Db::name('warehouses')->insert([
    'ID' => '$WarehouseId',
    'NAME' => '$safePrefix warehouse',
    'CODE' => substr('$seed', 0, 20),
    'ADDRESS' => '$safePrefix address',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'USER' => `$userId,
    'ORG' => `$orgId !== '' ? `$orgId : null,
]);
think\facade\Db::name('inventory')->insert([
    'ID' => '$InventoryId',
    'WAREHOUSES_ID' => '$WarehouseId',
    'PRODUCT_ID' => '$ChildProductId',
    'CURRENT_COUNT' => '10',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 0,
]);
think\facade\Db::name('settlement_account')->insert([
    'ID' => '$safeSettlementAccountId',
    'ACCOUNT_NAME' => '$safePrefix account',
    'ACCOUNT_NUMBER' => '$safeSettlementAccountId',
    'INITIAL_AMOUNT' => '1000.00',
    'CURRENT_AMOUNT' => '1000.00',
    'ACCOUNT_STATUS' => 'ENABLE',
    'SORT_CODE' => 0,
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 0,
    'org' => `$orgId !== '' ? `$orgId : null,
]);
foreach ([['$ProjectCancelId', '$ItemCancelId', '$RelationCancelId', 'cancel'], ['$ProjectRejectId', '$ItemRejectId', '$RelationRejectId', 'reject'], ['$ProjectApproveId', '$ItemApproveId', '$RelationApproveId', 'approve']] as `$project) {
    think\facade\Db::name('biz_sale_project')->insert([
        'ID' => `$project[0],
        'CUSTOMER' => '$CustomerId',
        'PROJECT_NAME' => '$safePrefix ' . `$project[3],
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
        'ACCOUNT_ID' => `$project[3] === 'approve' ? '$safeSettlementAccountId' : null,
    ]);
    think\facade\Db::name('biz_sale_project_product_item')->insert([
        'ID' => `$project[1],
        'PROJECT_ID' => `$project[0],
        'PRODUCT_ID' => '$ProductId',
        'CATEGORY' => 'INIT',
        'STATE' => 'SHIPPED',
        'NUMBER' => '5',
        'DELIVERY' => '5',
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
    think\facade\Db::name('sale_project_product_item_relation')->insert([
        'ID' => `$project[2],
        'OBJECT_ID' => `$project[1],
        'TARGET_ID' => '$ChildProductId',
        'MARK' => '',
        'NUMBER' => '2',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => `$userId,
        'TENANT_ID' => `$tenantId,
        'REMARK' => '$safePrefix relation',
    ]);
}
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_WORKFLOW_PROJECT_RETURN_APPROVE_SMOKE';
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

    $noToken = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/return/start') -Body (New-StartBody -ProjectId $ProjectCancelId -ItemId $ItemCancelId)
    Assert-Code -Json $noToken -Expected 401 -Name 'project return start no-token'

    $missingProductList = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/return/start') -Token $token -Body @{
        projectId = $ProjectCancelId
        approveUserIdList = @($UserId)
        copyUserIdList = @()
        warehousesId = $WarehouseId
        amount = '20.00'
    }
    Assert-Code -Json $missingProductList -Expected 400 -Name 'project return missing productList'

    $missingWarehouse = New-StartBody -ProjectId $ProjectCancelId -ItemId $ItemCancelId
    $missingWarehouse.Remove('warehousesId')
    $missingWarehouseResponse = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/return/start') -Token $token -Body $missingWarehouse
    Assert-Code -Json $missingWarehouseResponse -Expected 400 -Name 'project return missing warehouse'

    $negativeAmount = New-StartBody -ProjectId $ProjectCancelId -ItemId $ItemCancelId
    $negativeAmount.amount = '-1.00'
    $negative = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/return/start') -Token $token -Body $negativeAmount
    Assert-Code -Json $negative -Expected 400 -Name 'project return negative amount'

    $cancelStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/return/start') -Token $token -Body (New-StartBody -ProjectId $ProjectCancelId -ItemId $ItemCancelId)
    Assert-Code -Json $cancelStart -Expected 200 -Name 'project return cancel start'
    $cancelProcessId = [string]$cancelStart.data.processInstanceId
    $processIds += $cancelProcessId
    Assert-Equal -Actual ([string]$cancelStart.data.processKey) -Expected 'Process_sale_project_product_return' -Name 'project return cancel process key'
    $cancel = Invoke-JsonPost -Url ($baseUrl + '/biz/process/cancel') -Token $token -Body @{ id = $cancelProcessId }
    Assert-Code -Json $cancel -Expected 200 -Name 'project return cancel'

    $rejectStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/return/start') -Token $token -Body (New-StartBody -ProjectId $ProjectRejectId -ItemId $ItemRejectId)
    Assert-Code -Json $rejectStart -Expected 200 -Name 'project return reject start'
    $rejectProcessId = [string]$rejectStart.data.processInstanceId
    $processIds += $rejectProcessId
    $reject = Invoke-JsonPost -Url ($baseUrl + '/biz/task/reject') -Token $token -Body @{
        id = [string]$rejectStart.data.taskId
        form = @{ comment = "$Prefix reject" }
    }
    Assert-Code -Json $reject -Expected 200 -Name 'project return reject'

    $approveStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/return/start') -Token $token -Body (New-StartBody -ProjectId $ProjectApproveId -ItemId $ItemApproveId)
    Assert-Code -Json $approveStart -Expected 200 -Name 'project return approve start'
    $approveProcessId = [string]$approveStart.data.processInstanceId
    $processIds += $approveProcessId
    $approve = Invoke-JsonPost -Url ($baseUrl + '/biz/task/approve') -Token $token -Body @{
        id = [string]$approveStart.data.taskId
        form = @{
            approval = $true
            comment = "$Prefix approve"
        }
    }
    Assert-Code -Json $approve -Expected 200 -Name 'project return approve'
    Assert-Equal -Actual ([string]$approve.data.processKey) -Expected 'Process_sale_project_product_return' -Name 'project return approve process key'
    Assert-IntEqual -Actual ([int]$approve.data.saleProjectReturn.productItemCount) -Expected 1 -Name 'project return response product item count'
    Assert-IntEqual -Actual ([int]$approve.data.saleProjectReturn.deliveryRecordCount) -Expected 1 -Name 'project return response delivery record count'
    Assert-Equal -Actual ([string]$approve.data.saleProjectReturn.autoRefund.accountId) -Expected $SettlementAccountId -Name 'project return response auto-refund account'
    Assert-DecimalEqual -Actual ([string]$approve.data.saleProjectReturn.autoRefund.amount) -Expected 20.00 -Name 'project return response auto-refund amount'
    Assert-Equal -Actual ([string]$approve.data.saleProjectReturn.autoRefund.returnRefund.state) -Expected 'AlreadySettled' -Name 'project return response auto-refund state'

    $state = Get-State -ProcessInstanceIds $processIds -ProjectIds @($ProjectCancelId, $ProjectRejectId, $ProjectApproveId) -WarehouseId $WarehouseId -ChildProductId $ChildProductId -AccountId $SettlementAccountId
    foreach ($processId in $processIds) {
        $process = Get-MapValue -Map $state.processes -Key $processId
        Assert-IntEqual -Actual ([int]$process.ruTask) -Expected 0 -Name "runtime task count $processId"
        Assert-IntEqual -Actual ([int]$process.ruVariable) -Expected 0 -Name "runtime variable count $processId"
        Assert-IntEqual -Actual ([int]$process.ruExecution) -Expected 0 -Name "runtime execution count $processId"
        Assert-Equal -Actual ([string]$process.hiProc.PROC_DEF_KEY_) -Expected 'Process_sale_project_product_return' -Name "history process key $processId"
        Assert-Equal -Actual ([string]$process.hiProc.STATE_) -Expected 'COMPLETED' -Name "history process state $processId"
        Assert-Equal -Actual ([string]$process.hiProc.END_ACT_ID_) -Expected 'Event_1q6ckfm' -Name "history process end event $processId"
    }

    Assert-IntEqual -Actual (@($state.returnOrders).Count) -Expected 1 -Name 'return order row count'
    Assert-IntEqual -Actual (@($state.returnItems).Count) -Expected 1 -Name 'return item row count'
    Assert-IntEqual -Actual (@($state.deliveryRecords).Count) -Expected 1 -Name 'return delivery record row count'
    Assert-IntEqual -Actual ([int]$state.invoiceCount) -Expected 0 -Name 'invoice side-effect count'
    Assert-IntEqual -Actual ([int]$state.invoiceItemCount) -Expected 0 -Name 'invoice item side-effect count'
    Assert-IntEqual -Actual ([int]$state.invoicingCount) -Expected 0 -Name 'invoicing side-effect count'
    Assert-IntEqual -Actual ([int]$state.paymentCount) -Expected 0 -Name 'payment side-effect count'
    Assert-IntEqual -Actual ([int]$state.expenditureCount) -Expected 1 -Name 'auto-refund expenditure count'
    Assert-IntEqual -Actual ([int]$state.statementCount) -Expected 1 -Name 'auto-refund statement count'

    $order = @($state.returnOrders)[0]
    $item = @($state.returnItems)[0]
    $deliveryRecord = @($state.deliveryRecords)[0]
    $responseOrderId = [string]$approve.data.saleProjectReturn.returnOrderId
    Assert-Equal -Actual ([string]$order.ID) -Expected $responseOrderId -Name 'return order id'
    Assert-Equal -Actual ([string]$order.PROJECT_ID) -Expected $ProjectApproveId -Name 'return order project id'
    Assert-Equal -Actual ([string]$order.PROCESS_ID) -Expected $approveProcessId -Name 'return order process id'
    Assert-DecimalEqual -Actual ([string]$order.AMOUNT) -Expected 20.00 -Name 'return order amount'
    Assert-Equal -Actual ([string]$order.STATE) -Expected 'AlreadySettled' -Name 'return order state'
    Assert-Equal -Actual ([string]$order.WAREHOUSES_ID) -Expected $WarehouseId -Name 'return order warehouse'
    Assert-Equal -Actual ([string]$order.LOGISTICS_ID) -Expected $LogisticsId -Name 'return order logistics id'
    Assert-Equal -Actual ([string]$item.RETURN_ORDER_ID) -Expected $responseOrderId -Name 'return item order id'
    Assert-Equal -Actual ([string]$item.PROJECT_PRODUCT_ITEM_ID) -Expected $ItemApproveId -Name 'return item project product item id'
    Assert-DecimalEqual -Actual ([string]$item.AMOUNT) -Expected 2 -Name 'return item amount'
    Assert-Equal -Actual ([string]$deliveryRecord.PROCESS_ID) -Expected $approveProcessId -Name 'return delivery process id'
    Assert-Equal -Actual ([string]$deliveryRecord.PROCESS_CATEGORY) -Expected 'Process_sale_project_product_return' -Name 'return delivery process category'
    Assert-Equal -Actual ([string]$deliveryRecord.CATEGORY) -Expected 'IN' -Name 'return delivery category'
    Assert-Equal -Actual ([string]$deliveryRecord.OBJECT_ID) -Expected $responseOrderId -Name 'return delivery object id'
    Assert-Equal -Actual ([string]$deliveryRecord.PRODUCT_ID) -Expected $ChildProductId -Name 'return delivery product id'
    Assert-Equal -Actual ([string]$deliveryRecord.WAREHOUSES_ID) -Expected $WarehouseId -Name 'return delivery warehouse'
    Assert-Equal -Actual ([string]$deliveryRecord.OPERATOR) -Expected $UserId -Name 'return delivery operator'
    Assert-DecimalEqual -Actual ([string]$deliveryRecord.AMOUNT) -Expected 4 -Name 'return delivery amount'
    Assert-DecimalEqual -Actual ([string]$state.inventory.CURRENT_COUNT) -Expected 14 -Name 'inventory incremented current count'

    $expenditure = @($state.expenditures)[0]
    $statement = @($state.statements)[0]
    Assert-Equal -Actual ([string]$expenditure.OBJECT_ID) -Expected $responseOrderId -Name 'auto-refund expenditure object id'
    Assert-Equal -Actual ([string]$expenditure.TARGET_ID) -Expected $SettlementAccountId -Name 'auto-refund expenditure account'
    Assert-Equal -Actual ([string]$expenditure.SETTLEMENT_CATEGORY) -Expected 'ReturnAndRefund' -Name 'auto-refund expenditure category'
    Assert-Equal -Actual ([string]$expenditure.PROCESS_ID) -Expected $approveProcessId -Name 'auto-refund expenditure process id'
    Assert-Equal -Actual ([string]$expenditure.SERIAL_ID) -Expected ([string]$statement.ID) -Name 'auto-refund expenditure statement link'
    Assert-DecimalEqual -Actual ([string]$expenditure.AMOUNT) -Expected 20.00 -Name 'auto-refund expenditure amount'
    Assert-Equal -Actual ([string]$statement.ACCOUNT_ID) -Expected $SettlementAccountId -Name 'auto-refund statement account'
    Assert-Equal -Actual ([string]$statement.SETTLEMENT_TYPE) -Expected 'EXPEND' -Name 'auto-refund statement type'
    Assert-Equal -Actual ([string]$statement.SETTLEMENT_CATEGORY) -Expected 'ReturnAndRefund' -Name 'auto-refund statement category'
    Assert-Equal -Actual ([string]$statement.PROCESS_CATEGORY) -Expected 'Process_sale_project_product_return' -Name 'auto-refund statement process category'
    Assert-DecimalEqual -Actual ([string]$statement.BEFORE_AMOUNT) -Expected 1000.00 -Name 'auto-refund statement before amount'
    Assert-DecimalEqual -Actual ([string]$statement.AFTER_AMOUNT) -Expected 980.00 -Name 'auto-refund statement after amount'
    Assert-DecimalEqual -Actual ([string]$state.account.CURRENT_AMOUNT) -Expected 980.00 -Name 'auto-refund account current amount'

    foreach ($projectId in @($ProjectCancelId, $ProjectRejectId)) {
        $project = Get-MapValue -Map $state.projects -Key $projectId
        Assert-Equal -Actual ([string]$project.PROJECT_STATE) -Expected 'SHIPPED' -Name "$projectId project state"
        Assert-DecimalEqual -Actual ([string]$project.TOTAL_REFUND_AMOUNT) -Expected 0.00 -Name "$projectId total refund amount"
        Assert-DecimalEqual -Actual ([string]$project.TOTAL_RETURN_AMOUNT) -Expected 0.00 -Name "$projectId total return amount"
        Assert-DecimalEqual -Actual ([string]$project.TOTAL_PRICE) -Expected 100.00 -Name "$projectId total price"
    }
    $approvedProject = Get-MapValue -Map $state.projects -Key $ProjectApproveId
    Assert-Equal -Actual ([string]$approvedProject.PROJECT_STATE) -Expected 'SHIPPED' -Name 'approved project state'
    Assert-DecimalEqual -Actual ([string]$approvedProject.TOTAL_REFUND_AMOUNT) -Expected 20.00 -Name 'approved project total refund amount'
    Assert-DecimalEqual -Actual ([string]$approvedProject.TOTAL_RETURN_AMOUNT) -Expected 20.00 -Name 'approved project total return amount'
    Assert-DecimalEqual -Actual ([string]$approvedProject.TOTAL_PRICE) -Expected 80.00 -Name 'approved project total price'

    foreach ($itemId in @($ItemCancelId, $ItemRejectId, $ItemApproveId)) {
        $projectItem = @($state.projectItems | Where-Object { [string]$_.ID -eq $itemId })[0]
        Assert-Equal -Actual ([string]$projectItem.STATE) -Expected 'SHIPPED' -Name "$itemId project item state"
        Assert-DecimalEqual -Actual ([string]$projectItem.DELIVERY) -Expected 5 -Name "$itemId project item delivery"
    }

    $readback = Invoke-JsonGet -Url ($baseUrl + '/biz/returnorder/query?projectId=' + (Enc $ProjectApproveId)) -Token $token
    Assert-Code -Json $readback -Expected 200 -Name 'project return readback query'
    Assert-IntEqual -Actual (@($readback.data).Count) -Expected 1 -Name 'project return readback order count'
    Assert-Equal -Actual ([string]$readback.data[0].id) -Expected $responseOrderId -Name 'project return readback order id'
    Assert-IntEqual -Actual (@($readback.data[0].productList).Count) -Expected 1 -Name 'project return readback product item count'
    Assert-Equal -Actual ([string]$readback.data[0].productList[0].projectProductItemId) -Expected $ItemApproveId -Name 'project return readback product item id'

    $detail = Invoke-JsonGet -Url ($baseUrl + '/biz/returnorder/detail?id=' + (Enc $responseOrderId)) -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'project return readback detail'
    Assert-Equal -Actual ([string]$detail.data.id) -Expected $responseOrderId -Name 'project return detail id'
    Assert-IntEqual -Actual (@($detail.data.productList).Count) -Expected 1 -Name 'project return detail product item count'

    Write-Host 'workflow project return approve smoke passed'
} finally {
    Remove-SmokeRows -ProcessInstanceIds $processIds -ProjectIds @($ProjectCancelId, $ProjectRejectId, $ProjectApproveId) -CustomerId $CustomerId -ProductIds @($ProductId, $ChildProductId) -WarehouseId $WarehouseId -AccountId $SettlementAccountId
}
