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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-purchase-order-edit-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
$orderId = 'POE' + $suffix.Substring(0, 17)
$completedOrderId = 'POE' + $suffix.Substring(1, 17)
$expenditureOrderId = 'POE' + $suffix.Substring(2, 17)
$otherOrderId = 'POE' + $suffix.Substring(3, 17)
$missingOrderId = 'POE' + $suffix.Substring(4, 17)
$itemId = 'PEI' + $suffix.Substring(5, 17)
$completedItemId = 'PEI' + $suffix.Substring(6, 17)
$expenditureItemId = 'PEI' + $suffix.Substring(7, 17)
$otherItemId = 'PEI' + $suffix.Substring(8, 17)
$expenditureId = 'PEX' + $suffix.Substring(9, 17)
$prefix = 'codex-poedit-' + $suffix.Substring(0, 8)

$safeAccount = $account.Replace("'", "\'")
$safeOrderId = $orderId.Replace("'", "\'")
$safeCompletedOrderId = $completedOrderId.Replace("'", "\'")
$safeExpenditureOrderId = $expenditureOrderId.Replace("'", "\'")
$safeOtherOrderId = $otherOrderId.Replace("'", "\'")
$safeItemId = $itemId.Replace("'", "\'")
$safeCompletedItemId = $completedItemId.Replace("'", "\'")
$safeExpenditureItemId = $expenditureItemId.Replace("'", "\'")
$safeOtherItemId = $otherItemId.Replace("'", "\'")
$safeExpenditureId = $expenditureId.Replace("'", "\'")
$safePrefix = $prefix.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$ids = ['$safeOrderId', '$safeCompletedOrderId', '$safeExpenditureOrderId', '$safeOtherOrderId'];
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
        ->order('SORT_CODE', 'asc')
        ->find();
    `$orgId = (string)(`$org['ID'] ?? '');
}
`$now = date('Y-m-d H:i:s');
`$base = [
    'SUPPLIER_ID' => '',
    'INSTANCE_ID' => '',
    'DESIRE_PURCHASE_DATE' => `$now,
    'AMOUNT' => '100.00',
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
foreach ([
    ['$safeOrderId', '$safePrefix-normal', 'NOT_COMPLETED'],
    ['$safeCompletedOrderId', '$safePrefix-completed', 'COMPLETED'],
    ['$safeExpenditureOrderId', '$safePrefix-expenditure', 'NOT_COMPLETED'],
    ['$safeOtherOrderId', '$safePrefix-other', 'NOT_COMPLETED'],
] as `$row) {
    think\facade\Db::name('biz_purchase_order')->insert(array_merge(`$base, [
        'ID' => `$row[0],
        'TITLE' => `$row[1],
        'SETTLEMENT_STATUS' => `$row[2],
        'STORAGE_STATUS' => 'NOT_IN_WAREHOUSE',
    ]));
}
foreach ([
    ['$safeItemId', '$safeOrderId', 'PDEDIT' . substr('$suffix', 0, 14)],
    ['$safeCompletedItemId', '$safeCompletedOrderId', 'PDEDIT' . substr('$suffix', 1, 14)],
    ['$safeExpenditureItemId', '$safeExpenditureOrderId', 'PDEDIT' . substr('$suffix', 2, 14)],
    ['$safeOtherItemId', '$safeOtherOrderId', 'PDEDIT' . substr('$suffix', 3, 14)],
] as `$row) {
    think\facade\Db::name('biz_purchase_order_item')->insert([
        'ID' => `$row[0],
        'PURCHASE_ORDER_ID' => `$row[1],
        'STORAGE_STATUS' => 'NOT_IN_WAREHOUSE',
        'PRODUCT_ID' => `$row[2],
        'AMOUNT' => '100.00',
        'NUMBER' => 2,
        'UNIT_AMOUNT' => '50.00',
        'DISCOUNT_RATE' => '0.00',
        'REMARK' => '$safePrefix-item',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => `$userId,
        'UPDATE_TIME' => `$now,
        'UPDATE_USER' => `$userId,
        'TENANT_ID' => `$tenantId,
        'VERSION' => 0,
        'FREIGHT_SHARE_AMOUNT' => '0.00',
        'UNIT_COST_WITH_FREIGHT' => '50.00',
    ]);
}
think\facade\Db::name('biz_expenditure_record')->insert([
    'ID' => '$safeExpenditureId',
    'OBJECT_ID' => '$safeExpenditureOrderId',
    'TARGET_ID' => 'SA' . substr('$suffix', 0, 18),
    'SERIAL_ID' => 'ST' . substr('$suffix', 0, 18),
    'PROCESS_ID' => '',
    'SETTLEMENT_CATEGORY' => 'GOODS_EXPENDITURE',
    'PAYER' => '$safePrefix-payer',
    'REMARK' => '$safePrefix-expense',
    'PAYER_TIME' => `$now,
    'AMOUNT' => '1.00',
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
`$auth['device'] = 'CODEX_PURCHASE_ORDER_EDIT_HTTP_SMOKE';
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
    throw 'failed to set up purchase-order edit smoke data'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

try {
    $validPayload = @{
        id = $orderId
        amount = 123.45
        productList = @(
            @{
                id = $itemId
                amount = 123.45
                unitAmount = 61.73
                discountRate = 1.50
                freightShareAmount = 3.25
                unitCostWithFreight = 64.98
            }
        )
    }

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/edit" -Data $validPayload
    Assert-Code -Json $noToken -Expected 401 -Name 'purchase order edit without token'

    $missing = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/edit" -Token $token -Data @{}
    Assert-Code -Json $missing -Expected 400 -Name 'purchase order edit missing id'

    $missingList = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/edit" -Token $token -Data @{ id = $orderId }
    Assert-Code -Json $missingList -Expected 400 -Name 'purchase order edit missing productList'

    $notFound = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/edit" -Token $token -Data @{
        id = $missingOrderId
        amount = 111
        productList = @(@{ id = $itemId; amount = 111 })
    }
    Assert-Code -Json $notFound -Expected 404 -Name 'purchase order edit missing order'

    $completed = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/edit" -Token $token -Data @{
        id = $completedOrderId
        amount = 111
        productList = @(@{ id = $completedItemId; amount = 111 })
    }
    Assert-Code -Json $completed -Expected 400 -Name 'purchase order edit completed order'

    $hasExpenditure = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/edit" -Token $token -Data @{
        id = $expenditureOrderId
        amount = 111
        productList = @(@{ id = $expenditureItemId; amount = 111 })
    }
    Assert-Code -Json $hasExpenditure -Expected 400 -Name 'purchase order edit order with expenditure'

    $wrongItem = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/edit" -Token $token -Data @{
        id = $orderId
        amount = 111
        productList = @(@{ id = $otherItemId; amount = 111 })
    }
    Assert-Code -Json $wrongItem -Expected 404 -Name 'purchase order edit wrong item order'

    $safeMissingOrderId = $missingOrderId.Replace("'", "\'")
    $beforeCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'normal' => think\facade\Db::name('biz_purchase_order')->where('ID', '$safeOrderId')->field('AMOUNT,SETTLEMENT_STATUS,VERSION')->find(),
    'normalItem' => think\facade\Db::name('biz_purchase_order_item')->where('ID', '$safeItemId')->field('AMOUNT,UNIT_AMOUNT,DISCOUNT_RATE,FREIGHT_SHARE_AMOUNT,UNIT_COST_WITH_FREIGHT,VERSION')->find(),
    'completed' => think\facade\Db::name('biz_purchase_order')->where('ID', '$safeCompletedOrderId')->field('AMOUNT,SETTLEMENT_STATUS,VERSION')->find(),
    'expenditureOrder' => think\facade\Db::name('biz_purchase_order')->where('ID', '$safeExpenditureOrderId')->field('AMOUNT,VERSION')->find(),
    'otherItem' => think\facade\Db::name('biz_purchase_order_item')->where('ID', '$safeOtherItemId')->field('AMOUNT,VERSION,PURCHASE_ORDER_ID')->find(),
    'missingCount' => think\facade\Db::name('biz_purchase_order')->where('ID', '$safeMissingOrderId')->count(),
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
    Assert-Equal -Actual ([string]$before.normal.AMOUNT) -Expected '100.00' -Name 'normal order amount before edit'
    Assert-Equal -Actual ([string]$before.normal.VERSION) -Expected '0' -Name 'normal order version before edit'
    Assert-Equal -Actual ([string]$before.normalItem.AMOUNT) -Expected '100.00' -Name 'normal item amount before edit'
    Assert-Equal -Actual ([string]$before.normalItem.VERSION) -Expected '0' -Name 'normal item version before edit'
    Assert-Equal -Actual ([string]$before.completed.SETTLEMENT_STATUS) -Expected 'COMPLETED' -Name 'completed order status preserved'
    Assert-Equal -Actual ([string]$before.completed.VERSION) -Expected '0' -Name 'completed order version preserved'
    Assert-Equal -Actual ([string]$before.expenditureOrder.AMOUNT) -Expected '100.00' -Name 'expenditure order amount preserved'
    Assert-Equal -Actual ([string]$before.expenditureOrder.VERSION) -Expected '0' -Name 'expenditure order version preserved'
    Assert-Equal -Actual ([string]$before.otherItem.AMOUNT) -Expected '100.00' -Name 'wrong item amount preserved'
    Assert-Equal -Actual ([string]$before.otherItem.VERSION) -Expected '0' -Name 'wrong item version preserved'
    Assert-Equal -Actual ([string]$before.missingCount) -Expected '0' -Name 'missing order was not created'
    foreach ($name in @('orders', 'items', 'expenditure', 'inventory', 'delivery')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$before.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed after failed edit cases: expected=$expected actual=$actual"
        }
    }

    $edit = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/edit" -Token $token -Data $validPayload
    Assert-Code -Json $edit -Expected 200 -Name 'purchase order edit'
    Assert-PathEquals -Json $edit -Path 'data.id' -Expected $orderId -Name 'purchase order edit id'
    Assert-PathEquals -Json $edit -Path 'data.updatedItems' -Expected '1' -Name 'purchase order edit item count'
    Assert-PathEquals -Json $edit -Path 'data.amount' -Expected '123.45' -Name 'purchase order edit amount'

    $detail = Invoke-RawGet -Url "$baseUrl/biz/bizpurchaseorder/detail?id=$(Enc $orderId)" -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'purchase order detail after edit'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrder.id' -Expected $orderId -Name 'purchase order detail id'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrder.amount' -Expected '123.45' -Name 'purchase order detail edited amount'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrder.settlementStatus' -Expected 'NOT_COMPLETED' -Name 'purchase order edit status preserved'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrder.storageStatus' -Expected 'NOT_IN_WAREHOUSE' -Name 'purchase order edit storage preserved'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrderItemList.0.id' -Expected $itemId -Name 'purchase order detail item id'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrderItemList.0.amount' -Expected '123.45' -Name 'purchase order detail item amount'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrderItemList.0.unitAmount' -Expected '61.73' -Name 'purchase order detail item unit amount'

    $afterCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$order = think\facade\Db::name('biz_purchase_order')->where('ID', '$safeOrderId')->find();
`$item = think\facade\Db::name('biz_purchase_order_item')->where('ID', '$safeItemId')->find();
`$expenditure = think\facade\Db::name('biz_expenditure_record')->where('ID', '$safeExpenditureId')->find();
echo json_encode([
    'order' => [
        'amount' => (string)(`$order['AMOUNT'] ?? ''),
        'settlementStatus' => (string)(`$order['SETTLEMENT_STATUS'] ?? ''),
        'storageStatus' => (string)(`$order['STORAGE_STATUS'] ?? ''),
        'tenantId' => (string)(`$order['TENANT_ID'] ?? ''),
        'org' => (string)(`$order['ORG'] ?? ''),
        'version' => (int)(`$order['VERSION'] ?? -1),
        'updateUser' => (string)(`$order['UPDATE_USER'] ?? ''),
        'deleteFlag' => (string)(`$order['DELETE_FLAG'] ?? ''),
    ],
    'item' => [
        'purchaseOrderId' => (string)(`$item['PURCHASE_ORDER_ID'] ?? ''),
        'storageStatus' => (string)(`$item['STORAGE_STATUS'] ?? ''),
        'productId' => (string)(`$item['PRODUCT_ID'] ?? ''),
        'number' => (string)(`$item['NUMBER'] ?? ''),
        'amount' => (string)(`$item['AMOUNT'] ?? ''),
        'unitAmount' => (string)(`$item['UNIT_AMOUNT'] ?? ''),
        'discountRate' => (string)(`$item['DISCOUNT_RATE'] ?? ''),
        'freightShareAmount' => (string)(`$item['FREIGHT_SHARE_AMOUNT'] ?? ''),
        'unitCostWithFreight' => (string)(`$item['UNIT_COST_WITH_FREIGHT'] ?? ''),
        'version' => (int)(`$item['VERSION'] ?? -1),
        'updateUser' => (string)(`$item['UPDATE_USER'] ?? ''),
        'deleteFlag' => (string)(`$item['DELETE_FLAG'] ?? ''),
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
    Assert-Equal -Actual ([string]$after.order.amount) -Expected '123.45' -Name 'order amount after edit'
    Assert-Equal -Actual ([string]$after.order.settlementStatus) -Expected 'NOT_COMPLETED' -Name 'order settlement preserved'
    Assert-Equal -Actual ([string]$after.order.storageStatus) -Expected 'NOT_IN_WAREHOUSE' -Name 'order storage preserved'
    Assert-Equal -Actual ([string]$after.order.version) -Expected '1' -Name 'order version incremented'
    Assert-Equal -Actual ([string]$after.order.updateUser) -Expected $userId -Name 'order update user'
    Assert-Equal -Actual ([string]$after.order.deleteFlag) -Expected 'NOT_DELETE' -Name 'order delete flag preserved'
    Assert-Equal -Actual ([string]$after.item.purchaseOrderId) -Expected $orderId -Name 'item purchase order id preserved'
    Assert-Equal -Actual ([string]$after.item.storageStatus) -Expected 'NOT_IN_WAREHOUSE' -Name 'item storage status preserved'
    Assert-Equal -Actual ([string]$after.item.number) -Expected '2' -Name 'item number preserved'
    Assert-Equal -Actual ([string]$after.item.amount) -Expected '123.45' -Name 'item amount after edit'
    Assert-Equal -Actual ([string]$after.item.unitAmount) -Expected '61.73' -Name 'item unit amount after edit'
    Assert-Equal -Actual ([string]$after.item.discountRate) -Expected '1.50' -Name 'item discount after edit'
    Assert-Equal -Actual ([string]$after.item.freightShareAmount) -Expected '3.25' -Name 'item freight after edit'
    Assert-Equal -Actual ([string]$after.item.unitCostWithFreight) -Expected '64.98' -Name 'item cost with freight after edit'
    Assert-Equal -Actual ([string]$after.item.version) -Expected '1' -Name 'item version incremented'
    Assert-Equal -Actual ([string]$after.item.updateUser) -Expected $userId -Name 'item update user'
    Assert-Equal -Actual ([string]$after.item.deleteFlag) -Expected 'NOT_DELETE' -Name 'item delete flag preserved'
    Assert-Equal -Actual ([string]$after.expenditure.objectId) -Expected $expenditureOrderId -Name 'unrelated expenditure object preserved'
    Assert-Equal -Actual ([string]$after.expenditure.settlementCategory) -Expected 'GOODS_EXPENDITURE' -Name 'unrelated expenditure category preserved'
    Assert-Equal -Actual ([string]$after.expenditure.amount) -Expected '1.00' -Name 'unrelated expenditure amount preserved'
    foreach ($name in @('orders', 'items', 'expenditure', 'inventory', 'delivery')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$after.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed unexpectedly: expected=$expected actual=$actual"
        }
    }

    Write-Host 'purchase order edit HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
