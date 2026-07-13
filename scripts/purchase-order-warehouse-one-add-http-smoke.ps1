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

function Get-EnvValue {
    param(
        [Parameter(Mandatory = $true)][hashtable]$EnvMap,
        [Parameter(Mandatory = $true)][string]$Key
    )

    if ($EnvMap.ContainsKey($Key) -and [string]$EnvMap[$Key] -ne '') {
        return [string]$EnvMap[$Key]
    }

    return ''
}

function Invoke-Php {
    param([Parameter(Mandatory = $true)][string]$Code)

    $output = & php -r $Code
    if ($LASTEXITCODE -ne 0) {
        throw 'php inline command failed'
    }
    if ($null -eq $output) {
        return ''
    }

    return [string]::Join('', [string[]]$output)
}

function Invoke-PhpJson {
    param([Parameter(Mandatory = $true)][string]$Code)

    $raw = Invoke-Php -Code $Code
    if ([string]::IsNullOrWhiteSpace($raw)) {
        throw 'php inline json command returned no output'
    }

    return $raw | ConvertFrom-Json
}

function Invoke-RawPostJson {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)]$Data,
        [string]$Token = ''
    )

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-purchase-order-warehouse-one-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    try {
        $json = ConvertTo-Json -InputObject $Data -Depth 10
        $utf8NoBom = New-Object System.Text.UTF8Encoding -ArgumentList $false
        [System.IO.File]::WriteAllText($tmp, $json, $utf8NoBom)

        $headers = @('-H', 'Content-Type: application/json')
        if ($Token -ne '') {
            $headers += @('-H', "Authorization: Bearer $Token")
        }

        $raw = & curl.exe -sS -X POST $Url @headers --data-binary "@$tmp"
        if ($LASTEXITCODE -ne 0) {
            throw "HTTP POST failed: $Url"
        }

        return [string]::Join('', [string[]]$raw)
    } finally {
        Remove-Item -LiteralPath $tmp -ErrorAction SilentlyContinue
    }
}

function Invoke-RawGet {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [string]$Token = ''
    )

    $headers = @()
    if ($Token -ne '') {
        $headers += @('-H', "Authorization: Bearer $Token")
    }

    $raw = & curl.exe -sS -X GET $Url @headers
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP GET failed: $Url"
    }

    return [string]::Join('', [string[]]$raw)
}

function Read-JsonPath {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path
    )

    $value = $Json | node (Join-Path $PSScriptRoot 'json-read.js') $Path
    if ($LASTEXITCODE -ne 0) {
        throw "JSON path missing or invalid: $Path"
    }

    return [string]$value
}

function Assert-Code {
    param([string]$Json, [int]$Expected, [string]$Name)

    $code = [int](Read-JsonPath -Json $Json -Path 'code')
    if ($code -ne $Expected) {
        throw "$Name returned code=$code expected=$Expected body=$Json"
    }
}

function Assert-Equal {
    param([string]$Actual, [string]$Expected, [string]$Name)

    if ($Actual -ne $Expected) {
        throw "$Name expected=$Expected actual=$Actual"
    }
}

function Assert-IntEqual {
    param([int]$Actual, [int]$Expected, [string]$Name)

    if ($Actual -ne $Expected) {
        throw "$Name expected=$Expected actual=$Actual"
    }
}

function Enc {
    param([Parameter(Mandatory = $true)][string]$Value)

    return [System.Uri]::EscapeDataString($Value)
}

$envMap = Get-EnvMap -Path $EnvPath
$account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
if ($account -eq '') {
    throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env'
}

$safeAccount = $account.Replace("'", "\'")
$tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_PURCHASE_ORDER_WAREHOUSE_ONE_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
$token = Invoke-Php -Code $tokenCode
if ($token.Trim() -eq '') {
    throw 'failed to create local smoke token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'codex-po-wh-one-' + ([Guid]::NewGuid().ToString('N').Substring(0, 10))
$safePrefix = $prefix.Replace("'", "\'")
$warehouseId = ''
$orderId = ''
$inWarehouseOrderId = ''
$missingProductOrderId = ''

try {
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
`$missingProduct = `$newId();
`$inventoryExisting = `$newId();
`$orderId = `$newId();
`$inWarehouseOrderId = `$newId();
`$missingProductOrderId = `$newId();
`$itemExisting = `$newId();
`$itemNew = `$newId();
`$itemInWarehouse = `$newId();
`$itemMissingProduct = `$newId();
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
`$orderBase = [
    'TITLE' => '$safePrefix order',
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
];
think\facade\Db::name('biz_purchase_order')->insert(array_merge(`$orderBase, [
    'ID' => `$orderId,
    'SETTLEMENT_STATUS' => 'NOT_COMPLETED',
    'STORAGE_STATUS' => 'NOT_IN_WAREHOUSE',
]));
think\facade\Db::name('biz_purchase_order')->insert(array_merge(`$orderBase, [
    'ID' => `$inWarehouseOrderId,
    'SETTLEMENT_STATUS' => 'NOT_COMPLETED',
    'STORAGE_STATUS' => 'IN_WAREHOUSE',
]));
think\facade\Db::name('biz_purchase_order')->insert(array_merge(`$orderBase, [
    'ID' => `$missingProductOrderId,
    'SETTLEMENT_STATUS' => 'NOT_COMPLETED',
    'STORAGE_STATUS' => 'NOT_IN_WAREHOUSE',
]));
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
think\facade\Db::name('biz_purchase_order_item')->insert(array_merge(`$itemBase, [
    'ID' => `$itemInWarehouse,
    'PURCHASE_ORDER_ID' => `$inWarehouseOrderId,
    'STORAGE_STATUS' => 'IN_WAREHOUSE',
    'PRODUCT_ID' => `$productExisting,
    'NUMBER' => 1,
]));
think\facade\Db::name('biz_purchase_order_item')->insert(array_merge(`$itemBase, [
    'ID' => `$itemMissingProduct,
    'PURCHASE_ORDER_ID' => `$missingProductOrderId,
    'STORAGE_STATUS' => 'NOT_IN_WAREHOUSE',
    'PRODUCT_ID' => `$missingProduct,
    'NUMBER' => 1,
]));
echo json_encode([
    'warehouseId' => `$warehouseId,
    'productExisting' => `$productExisting,
    'productNew' => `$productNew,
    'orderId' => `$orderId,
    'inWarehouseOrderId' => `$inWarehouseOrderId,
    'missingProductOrderId' => `$missingProductOrderId,
    'itemExisting' => `$itemExisting,
    'itemNew' => `$itemNew,
    'tenantId' => `$tenantId,
    'counts' => [
        'inventory' => think\facade\Db::name('inventory')->count(),
        'delivery' => think\facade\Db::name('delivery_record')->count(),
        'items' => think\facade\Db::name('biz_purchase_order_item')->count(),
    ],
], JSON_UNESCAPED_UNICODE);
"@
    $setup = Invoke-PhpJson -Code $setupCode
    $warehouseId = [string]$setup.warehouseId
    $productExisting = [string]$setup.productExisting
    $productNew = [string]$setup.productNew
    $orderId = [string]$setup.orderId
    $inWarehouseOrderId = [string]$setup.inWarehouseOrderId
    $missingProductOrderId = [string]$setup.missingProductOrderId

    $validPayload = @{
        orderId = $orderId
        warehousesId = $warehouseId
        remark = "$prefix success"
    }

    $noToken = Invoke-RawPostJson -Url ($baseUrl + '/biz/bizpurchaseorder/warehouse/one/add') -Data $validPayload
    Assert-Code -Json $noToken -Expected 401 -Name 'purchase order warehouse one no token'

    $missingOrderId = Invoke-RawPostJson -Url ($baseUrl + '/biz/bizpurchaseorder/warehouse/one/add') -Token $token -Data @{
        warehousesId = $warehouseId
    }
    Assert-Code -Json $missingOrderId -Expected 400 -Name 'purchase order warehouse one missing orderId'

    $missingWarehouse = Invoke-RawPostJson -Url ($baseUrl + '/biz/bizpurchaseorder/warehouse/one/add') -Token $token -Data @{
        orderId = $orderId
        warehousesId = 'missing-warehouse'
    }
    Assert-Code -Json $missingWarehouse -Expected 404 -Name 'purchase order warehouse one missing warehouse'

    $alreadyInWarehouse = Invoke-RawPostJson -Url ($baseUrl + '/biz/bizpurchaseorder/warehouse/one/add') -Token $token -Data @{
        orderId = $inWarehouseOrderId
        warehousesId = $warehouseId
    }
    Assert-Code -Json $alreadyInWarehouse -Expected 400 -Name 'purchase order warehouse one already in warehouse'

    $missingProduct = Invoke-RawPostJson -Url ($baseUrl + '/biz/bizpurchaseorder/warehouse/one/add') -Token $token -Data @{
        orderId = $missingProductOrderId
        warehousesId = $warehouseId
    }
    Assert-Code -Json $missingProduct -Expected 400 -Name 'purchase order warehouse one missing product'

    $afterFailedCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$order = think\facade\Db::name('biz_purchase_order')->where('ID', '$orderId')->find();
echo json_encode([
    'inventory' => think\facade\Db::name('inventory')->count(),
    'delivery' => think\facade\Db::name('delivery_record')->count(),
    'items' => think\facade\Db::name('biz_purchase_order_item')->count(),
    'orderStorage' => (string)`$order['STORAGE_STATUS'],
], JSON_UNESCAPED_UNICODE);
"@
    $afterFailed = Invoke-PhpJson -Code $afterFailedCode
    Assert-IntEqual -Actual ([int]$afterFailed.inventory) -Expected ([int]$setup.counts.inventory) -Name 'failed warehouse one inventory count'
    Assert-IntEqual -Actual ([int]$afterFailed.delivery) -Expected ([int]$setup.counts.delivery) -Name 'failed warehouse one delivery count'
    Assert-IntEqual -Actual ([int]$afterFailed.items) -Expected ([int]$setup.counts.items) -Name 'failed warehouse one item count'
    Assert-Equal -Actual ([string]$afterFailed.orderStorage) -Expected 'NOT_IN_WAREHOUSE' -Name 'failed warehouse one order status'

    $success = Invoke-RawPostJson -Url ($baseUrl + '/biz/bizpurchaseorder/warehouse/one/add') -Token $token -Data $validPayload
    Assert-Code -Json $success -Expected 200 -Name 'purchase order warehouse one success'
    Assert-Equal -Actual (Read-JsonPath -Json $success -Path 'data.storageStatus') -Expected 'IN_WAREHOUSE' -Name 'warehouse one response storage'
    Assert-Equal -Actual (Read-JsonPath -Json $success -Path 'data.updatedItems') -Expected '2' -Name 'warehouse one response item count'

    $detail = Invoke-RawGet -Url ($baseUrl + '/biz/bizpurchaseorder/detail?id=' + (Enc $orderId)) -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'purchase order detail after warehouse one'
    Assert-Equal -Actual (Read-JsonPath -Json $detail -Path 'data.bizPurchaseOrder.storageStatus') -Expected 'IN_WAREHOUSE' -Name 'detail order storage'
    Assert-Equal -Actual (Read-JsonPath -Json $detail -Path 'data.bizPurchaseOrderItemList.0.storageStatus') -Expected 'IN_WAREHOUSE' -Name 'detail item 0 storage'
    Assert-Equal -Actual (Read-JsonPath -Json $detail -Path 'data.bizPurchaseOrderItemList.1.storageStatus') -Expected 'IN_WAREHOUSE' -Name 'detail item 1 storage'

    $verifyCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$order = think\facade\Db::name('biz_purchase_order')->where('ID', '$orderId')->find();
`$items = think\facade\Db::name('biz_purchase_order_item')->where('PURCHASE_ORDER_ID', '$orderId')->order('ID', 'asc')->select()->toArray();
`$deliveries = think\facade\Db::name('delivery_record')
    ->where('OBJECT_ID', '$orderId')
    ->where('WAREHOUSES_ID', '$warehouseId')
    ->order('PRODUCT_ID', 'asc')
    ->select()
    ->toArray();
`$inventoryExisting = think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$warehouseId')->where('PRODUCT_ID', '$productExisting')->find();
`$inventoryNew = think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$warehouseId')->where('PRODUCT_ID', '$productNew')->find();
echo json_encode([
    'inventoryCount' => think\facade\Db::name('inventory')->count(),
    'deliveryCount' => think\facade\Db::name('delivery_record')->count(),
    'order' => `$order,
    'items' => `$items,
    'deliveries' => `$deliveries,
    'inventoryExisting' => `$inventoryExisting,
    'inventoryNew' => `$inventoryNew,
], JSON_UNESCAPED_UNICODE);
"@
    $verify = Invoke-PhpJson -Code $verifyCode
    Assert-IntEqual -Actual ([int]$verify.inventoryCount) -Expected ([int]$setup.counts.inventory + 1) -Name 'warehouse one inventory count'
    Assert-IntEqual -Actual ([int]$verify.deliveryCount) -Expected ([int]$setup.counts.delivery + 2) -Name 'warehouse one delivery count'
    Assert-Equal -Actual ([string]$verify.order.STORAGE_STATUS) -Expected 'IN_WAREHOUSE' -Name 'warehouse one order storage'
    Assert-Equal -Actual ([string]$verify.order.VERSION) -Expected '1' -Name 'warehouse one order version'
    Assert-Equal -Actual ([string]$verify.items[0].STORAGE_STATUS) -Expected 'IN_WAREHOUSE' -Name 'warehouse one item 0 status'
    Assert-Equal -Actual ([string]$verify.items[1].STORAGE_STATUS) -Expected 'IN_WAREHOUSE' -Name 'warehouse one item 1 status'
    Assert-Equal -Actual ([string]$verify.inventoryExisting.CURRENT_COUNT) -Expected '5' -Name 'warehouse one existing inventory'
    Assert-Equal -Actual ([string]$verify.inventoryExisting.VERSION) -Expected '5' -Name 'warehouse one existing inventory version'
    Assert-Equal -Actual ([string]$verify.inventoryNew.CURRENT_COUNT) -Expected '5' -Name 'warehouse one new inventory'
    Assert-Equal -Actual ([string]$verify.deliveries[0].CATEGORY) -Expected 'IN' -Name 'warehouse one delivery 0 category'
    Assert-Equal -Actual ([string]$verify.deliveries[0].PROCESS_ID) -Expected 'Process_sys' -Name 'warehouse one delivery 0 process'
    Assert-Equal -Actual ([string]$verify.deliveries[0].PROCESS_CATEGORY) -Expected 'Process_procure_in_warehouse' -Name 'warehouse one delivery 0 process category'
    Assert-Equal -Actual ([string]$verify.deliveries[0].OBJECT_ID) -Expected $orderId -Name 'warehouse one delivery 0 object'
    Assert-Equal -Actual ([string]$verify.deliveries[1].CATEGORY) -Expected 'IN' -Name 'warehouse one delivery 1 category'

    $repeat = Invoke-RawPostJson -Url ($baseUrl + '/biz/bizpurchaseorder/warehouse/one/add') -Token $token -Data $validPayload
    Assert-Code -Json $repeat -Expected 400 -Name 'purchase order warehouse one repeat'

    $afterRepeat = Invoke-PhpJson -Code $verifyCode
    Assert-IntEqual -Actual ([int]$afterRepeat.deliveryCount) -Expected ([int]$setup.counts.delivery + 2) -Name 'warehouse one repeat delivery count'

    Write-Host 'purchase order warehouse one add HTTP smoke passed'
} finally {
    if ($warehouseId -ne '') {
        $safeWarehouseId = $warehouseId.Replace("'", "\'")
        $safeOrderId = $orderId.Replace("'", "\'")
        $safeInWarehouseOrderId = $inWarehouseOrderId.Replace("'", "\'")
        $safeMissingProductOrderId = $missingProductOrderId.Replace("'", "\'")
        $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$orderIds = array_filter(['$safeOrderId', '$safeInWarehouseOrderId', '$safeMissingProductOrderId']);
if (`$orderIds !== []) {
    think\facade\Db::name('delivery_record')->whereIn('OBJECT_ID', `$orderIds)->delete();
    think\facade\Db::name('biz_purchase_order_item')->whereIn('PURCHASE_ORDER_ID', `$orderIds)->delete();
    think\facade\Db::name('biz_purchase_order')->whereIn('ID', `$orderIds)->delete();
}
think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$safeWarehouseId')->delete();
think\facade\Db::name('biz_product')->whereLike('PRODUCT_NAME', '$safePrefix%')->delete();
think\facade\Db::name('warehouses')->where('ID', '$safeWarehouseId')->delete();
"@
        try {
            Invoke-Php -Code $cleanupCode | Out-Null
        } catch {
            Write-Warning "cleanup failed: $($_.Exception.Message)"
        }
    }
}
