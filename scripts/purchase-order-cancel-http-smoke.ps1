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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-purchase-order-cancel-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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

function Assert-PathEquals {
    param([string]$Json, [string]$Path, [string]$Expected, [string]$Name)

    $actual = [string](Read-JsonPath -Json $Json -Path $Path)
    if ($actual -ne $Expected) {
        throw "$Name expected $Path=$Expected actual=$actual"
    }
}

function Assert-Equal {
    param([string]$Actual, [string]$Expected, [string]$Name)

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

$suffix = [Guid]::NewGuid().ToString('N')
$orderId = 'POC' + $suffix.Substring(0, 17)
$completedOrderId = 'POC' + $suffix.Substring(1, 17)
$warehouseOrderId = 'POC' + $suffix.Substring(2, 17)
$missingOrderId = 'POC' + $suffix.Substring(3, 17)
$itemId = 'POI' + $suffix.Substring(4, 17)
$expenditureId = 'EXP' + $suffix.Substring(5, 17)
$prefix = 'codex-pocan-' + $suffix.Substring(0, 8)

$safeAccount = $account.Replace("'", "\'")
$safeOrderId = $orderId.Replace("'", "\'")
$safeCompletedOrderId = $completedOrderId.Replace("'", "\'")
$safeWarehouseOrderId = $warehouseOrderId.Replace("'", "\'")
$safeItemId = $itemId.Replace("'", "\'")
$safeExpenditureId = $expenditureId.Replace("'", "\'")
$safePrefix = $prefix.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$ids = ['$safeOrderId', '$safeCompletedOrderId', '$safeWarehouseOrderId'];
think\facade\Db::name('biz_expenditure_record')->whereIn('OBJECT_ID', `$ids)->delete();
think\facade\Db::name('biz_purchase_order_item')->whereIn('PURCHASE_ORDER_ID', `$ids)->delete();
think\facade\Db::name('biz_purchase_order')->whereIn('ID', `$ids)->delete();
"@

Invoke-Php -Code $cleanupCode | Out-Null

$setupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$tenantId = (string)(`$user['TENANT_ID'] ?? '1');
if (`$tenantId === '') { `$tenantId = '1'; }
`$userId = (string)`$user['ID'];
`$orgId = (string)(`$user['ORG_ID'] ?? '');
if (`$orgId === '') {
    `$org = think\facade\Db::name('sys_org')
        ->where(function (`$query) {
            `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
        })
        ->field('ID')
        ->order('ID', 'asc')
        ->find();
    `$orgId = (string)(`$org['ID'] ?? '');
}
`$now = date('Y-m-d H:i:s');
`$base = [
    'SUPPLIER_ID' => '',
    'INSTANCE_ID' => '',
    'DESIRE_PURCHASE_DATE' => `$now,
    'AMOUNT' => '123.45',
    'REMARK' => '$safePrefix',
    'EXT_JSON' => json_encode(['supplier' => ['name' => '$safePrefix-supplier']], JSON_UNESCAPED_SLASHES),
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => `$now,
    'UPDATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 0,
    'ORG' => `$orgId,
];
think\facade\Db::name('biz_purchase_order')->insert(array_merge(`$base, [
    'ID' => '$safeOrderId',
    'TITLE' => '$safePrefix-normal',
    'SETTLEMENT_STATUS' => 'NOT_COMPLETED',
    'STORAGE_STATUS' => 'NOT_IN_WAREHOUSE',
]));
think\facade\Db::name('biz_purchase_order')->insert(array_merge(`$base, [
    'ID' => '$safeCompletedOrderId',
    'TITLE' => '$safePrefix-completed',
    'SETTLEMENT_STATUS' => 'COMPLETED',
    'STORAGE_STATUS' => 'NOT_IN_WAREHOUSE',
]));
think\facade\Db::name('biz_purchase_order')->insert(array_merge(`$base, [
    'ID' => '$safeWarehouseOrderId',
    'TITLE' => '$safePrefix-warehouse',
    'SETTLEMENT_STATUS' => 'NOT_COMPLETED',
    'STORAGE_STATUS' => 'IN_WAREHOUSE',
]));
think\facade\Db::name('biz_purchase_order_item')->insert([
    'ID' => '$safeItemId',
    'PURCHASE_ORDER_ID' => '$safeOrderId',
    'STORAGE_STATUS' => 'NOT_IN_WAREHOUSE',
    'PRODUCT_ID' => 'PDCODEXPOCANCEL001',
    'AMOUNT' => '123.45',
    'NUMBER' => 1,
    'UNIT_AMOUNT' => '123.45',
    'DISCOUNT_RATE' => '100.00',
    'REMARK' => '$safePrefix-item',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => `$now,
    'UPDATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 0,
    'FREIGHT_SHARE_AMOUNT' => '0.00',
    'UNIT_COST_WITH_FREIGHT' => '123.45',
]);
think\facade\Db::name('biz_expenditure_record')->insert([
    'ID' => '$safeExpenditureId',
    'OBJECT_ID' => '$safeOrderId',
    'TARGET_ID' => 'SACODEXPOCANCEL001',
    'SERIAL_ID' => 'STCODEXPOCANCEL001',
    'PROCESS_ID' => '',
    'SETTLEMENT_CATEGORY' => 'GOODS_EXPENDITURE',
    'PAYER' => '$safePrefix-payer',
    'REMARK' => '$safePrefix-expense',
    'PAYER_TIME' => `$now,
    'AMOUNT' => '12.34',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => `$now,
    'UPDATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'USER' => `$userId,
    'ORG' => `$orgId,
]);
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_PURCHASE_ORDER_CANCEL_HTTP_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => `$userId,
    'tenantId' => `$tenantId,
    'orgId' => `$orgId,
    'baseline' => [
        'orders' => think\facade\Db::name('biz_purchase_order')->count(),
        'items' => think\facade\Db::name('biz_purchase_order_item')->count(),
        'expenditure' => think\facade\Db::name('biz_expenditure_record')->count(),
        'inventory' => think\facade\Db::name('inventory')->count(),
        'delivery' => think\facade\Db::name('delivery_record')->count(),
    ],
], JSON_UNESCAPED_SLASHES);
"@

$setup = Invoke-PhpJson -Code $setupCode
$token = [string]$setup.token
$userId = [string]$setup.userId
if ($token.Trim() -eq '' -or $userId.Trim() -eq '') {
    throw 'failed to set up purchase-order cancel smoke data'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

try {
    $validPayload = @{ id = $orderId }

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/cancel" -Data $validPayload
    Assert-Code -Json $noToken -Expected 401 -Name 'purchase order cancel without token'

    $missing = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/cancel" -Token $token -Data @{}
    Assert-Code -Json $missing -Expected 400 -Name 'purchase order cancel missing id'

    $notFound = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/cancel" -Token $token -Data @{ id = $missingOrderId }
    Assert-Code -Json $notFound -Expected 404 -Name 'purchase order cancel missing order'

    $completed = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/cancel" -Token $token -Data @{ id = $completedOrderId }
    Assert-Code -Json $completed -Expected 400 -Name 'purchase order cancel completed order'

    $warehouse = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/cancel" -Token $token -Data @{ id = $warehouseOrderId }
    Assert-Code -Json $warehouse -Expected 400 -Name 'purchase order cancel in-warehouse order'

    $safeMissingOrderId = $missingOrderId.Replace("'", "\'")
    $beforeCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'normal' => think\facade\Db::name('biz_purchase_order')->where('ID', '$safeOrderId')->field('SETTLEMENT_STATUS,STORAGE_STATUS,VERSION,UPDATE_USER')->find(),
    'completed' => think\facade\Db::name('biz_purchase_order')->where('ID', '$safeCompletedOrderId')->field('SETTLEMENT_STATUS,STORAGE_STATUS,VERSION')->find(),
    'warehouse' => think\facade\Db::name('biz_purchase_order')->where('ID', '$safeWarehouseOrderId')->field('SETTLEMENT_STATUS,STORAGE_STATUS,VERSION')->find(),
    'missingCount' => think\facade\Db::name('biz_purchase_order')->where('ID', '$safeMissingOrderId')->count(),
    'itemCount' => think\facade\Db::name('biz_purchase_order_item')->where('PURCHASE_ORDER_ID', '$safeOrderId')->count(),
    'expenditureCount' => think\facade\Db::name('biz_expenditure_record')->where('OBJECT_ID', '$safeOrderId')->count(),
    'counts' => [
        'orders' => think\facade\Db::name('biz_purchase_order')->count(),
        'items' => think\facade\Db::name('biz_purchase_order_item')->count(),
        'expenditure' => think\facade\Db::name('biz_expenditure_record')->count(),
        'inventory' => think\facade\Db::name('inventory')->count(),
        'delivery' => think\facade\Db::name('delivery_record')->count(),
    ],
], JSON_UNESCAPED_SLASHES);
"@
    $before = Invoke-PhpJson -Code $beforeCode
    Assert-Equal -Actual ([string]$before.normal.SETTLEMENT_STATUS) -Expected 'NOT_COMPLETED' -Name 'normal order status before cancel'
    Assert-Equal -Actual ([string]$before.normal.VERSION) -Expected '0' -Name 'normal order version before cancel'
    Assert-Equal -Actual ([string]$before.completed.SETTLEMENT_STATUS) -Expected 'COMPLETED' -Name 'completed order status preserved'
    Assert-Equal -Actual ([string]$before.completed.VERSION) -Expected '0' -Name 'completed order version preserved'
    Assert-Equal -Actual ([string]$before.warehouse.STORAGE_STATUS) -Expected 'IN_WAREHOUSE' -Name 'warehouse order storage preserved'
    Assert-Equal -Actual ([string]$before.warehouse.VERSION) -Expected '0' -Name 'warehouse order version preserved'
    Assert-Equal -Actual ([string]$before.missingCount) -Expected '0' -Name 'missing order was not created'
    Assert-Equal -Actual ([string]$before.itemCount) -Expected '1' -Name 'item count before cancel'
    Assert-Equal -Actual ([string]$before.expenditureCount) -Expected '1' -Name 'expenditure count before cancel'
    foreach ($name in @('orders', 'items', 'expenditure', 'inventory', 'delivery')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$before.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed after failed cases: expected=$expected actual=$actual"
        }
    }

    $cancel = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/cancel" -Token $token -Data $validPayload
    Assert-Code -Json $cancel -Expected 200 -Name 'purchase order cancel'
    Assert-PathEquals -Json $cancel -Path 'data.id' -Expected $orderId -Name 'purchase order cancel id'
    Assert-PathEquals -Json $cancel -Path 'data.previousSettlementStatus' -Expected 'NOT_COMPLETED' -Name 'purchase order cancel previous status'
    Assert-PathEquals -Json $cancel -Path 'data.settlementStatus' -Expected 'Canceled' -Name 'purchase order cancel status'
    Assert-PathEquals -Json $cancel -Path 'data.storageStatus' -Expected 'NOT_IN_WAREHOUSE' -Name 'purchase order cancel storage'
    Assert-PathEquals -Json $cancel -Path 'data.count' -Expected '1' -Name 'purchase order cancel count'

    $detail = Invoke-RawGet -Url "$baseUrl/biz/bizpurchaseorder/detail?id=$(Enc $orderId)" -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'purchase order detail after cancel'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrder.id' -Expected $orderId -Name 'purchase order detail id'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrder.settlementStatus' -Expected 'Canceled' -Name 'purchase order detail canceled status'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrder.storageStatus' -Expected 'NOT_IN_WAREHOUSE' -Name 'purchase order detail storage preserved'

    $afterCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$order = think\facade\Db::name('biz_purchase_order')->where('ID', '$safeOrderId')->find();
`$item = think\facade\Db::name('biz_purchase_order_item')->where('ID', '$safeItemId')->find();
`$expenditure = think\facade\Db::name('biz_expenditure_record')->where('ID', '$safeExpenditureId')->find();
echo json_encode([
    'order' => [
        'settlementStatus' => (string)(`$order['SETTLEMENT_STATUS'] ?? ''),
        'storageStatus' => (string)(`$order['STORAGE_STATUS'] ?? ''),
        'amount' => (string)(`$order['AMOUNT'] ?? ''),
        'tenantId' => (string)(`$order['TENANT_ID'] ?? ''),
        'org' => (string)(`$order['ORG'] ?? ''),
        'version' => (int)(`$order['VERSION'] ?? -1),
        'updateUser' => (string)(`$order['UPDATE_USER'] ?? ''),
        'deleteFlag' => (string)(`$order['DELETE_FLAG'] ?? ''),
    ],
    'item' => [
        'purchaseOrderId' => (string)(`$item['PURCHASE_ORDER_ID'] ?? ''),
        'storageStatus' => (string)(`$item['STORAGE_STATUS'] ?? ''),
        'amount' => (string)(`$item['AMOUNT'] ?? ''),
        'version' => (int)(`$item['VERSION'] ?? -1),
    ],
    'expenditure' => [
        'objectId' => (string)(`$expenditure['OBJECT_ID'] ?? ''),
        'settlementCategory' => (string)(`$expenditure['SETTLEMENT_CATEGORY'] ?? ''),
        'amount' => (string)(`$expenditure['AMOUNT'] ?? ''),
    ],
    'counts' => [
        'orders' => think\facade\Db::name('biz_purchase_order')->count(),
        'items' => think\facade\Db::name('biz_purchase_order_item')->count(),
        'expenditure' => think\facade\Db::name('biz_expenditure_record')->count(),
        'inventory' => think\facade\Db::name('inventory')->count(),
        'delivery' => think\facade\Db::name('delivery_record')->count(),
    ],
], JSON_UNESCAPED_SLASHES);
"@
    $after = Invoke-PhpJson -Code $afterCode
    Assert-Equal -Actual ([string]$after.order.settlementStatus) -Expected 'Canceled' -Name 'order settlement status after cancel'
    Assert-Equal -Actual ([string]$after.order.storageStatus) -Expected 'NOT_IN_WAREHOUSE' -Name 'order storage status preserved'
    Assert-Equal -Actual ([string]$after.order.amount) -Expected '123.45' -Name 'order amount preserved'
    Assert-Equal -Actual ([string]$after.order.version) -Expected '1' -Name 'order version incremented'
    Assert-Equal -Actual ([string]$after.order.updateUser) -Expected $userId -Name 'order update user'
    Assert-Equal -Actual ([string]$after.order.deleteFlag) -Expected 'NOT_DELETE' -Name 'order delete flag preserved'
    Assert-Equal -Actual ([string]$after.item.purchaseOrderId) -Expected $orderId -Name 'item purchase order id preserved'
    Assert-Equal -Actual ([string]$after.item.storageStatus) -Expected 'NOT_IN_WAREHOUSE' -Name 'item storage status preserved'
    Assert-Equal -Actual ([string]$after.item.amount) -Expected '123.45' -Name 'item amount preserved'
    Assert-Equal -Actual ([string]$after.item.version) -Expected '0' -Name 'item version preserved'
    Assert-Equal -Actual ([string]$after.expenditure.objectId) -Expected $orderId -Name 'expenditure object id preserved'
    Assert-Equal -Actual ([string]$after.expenditure.settlementCategory) -Expected 'GOODS_EXPENDITURE' -Name 'expenditure category preserved'
    Assert-Equal -Actual ([string]$after.expenditure.amount) -Expected '12.34' -Name 'expenditure amount preserved'
    foreach ($name in @('orders', 'items', 'expenditure', 'inventory', 'delivery')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$after.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed unexpectedly: expected=$expected actual=$actual"
        }
    }

    Write-Host 'purchase order cancel HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
