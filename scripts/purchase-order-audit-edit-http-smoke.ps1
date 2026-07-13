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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-purchase-order-audit-edit-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
$orderId = 'POA' + $suffix.Substring(0, 17)
$otherOrderId = 'POA' + $suffix.Substring(1, 17)
$missingOrderId = 'POA' + $suffix.Substring(2, 17)
$itemId = 'PAI' + $suffix.Substring(3, 17)
$otherItemId = 'PAI' + $suffix.Substring(4, 17)
$expenditureId = 'PAX' + $suffix.Substring(5, 17)
$prefix = 'codex-poaudit-' + $suffix.Substring(0, 8)

$safeAccount = $account.Replace("'", "\'")
$safeOrderId = $orderId.Replace("'", "\'")
$safeOtherOrderId = $otherOrderId.Replace("'", "\'")
$safeMissingOrderId = $missingOrderId.Replace("'", "\'")
$safeItemId = $itemId.Replace("'", "\'")
$safeOtherItemId = $otherItemId.Replace("'", "\'")
$safeExpenditureId = $expenditureId.Replace("'", "\'")
$safePrefix = $prefix.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$ids = ['$safeOrderId', '$safeOtherOrderId'];
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
    ['$safeOrderId', '$safePrefix-audit', 'COMPLETED'],
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
    ['$safeItemId', '$safeOrderId', 'PDAUDIT' . substr('$suffix', 0, 13)],
    ['$safeOtherItemId', '$safeOtherOrderId', 'PDAUDIT' . substr('$suffix', 1, 13)],
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
    'OBJECT_ID' => '$safeOrderId',
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
`$auth['device'] = 'CODEX_PURCHASE_ORDER_AUDIT_EDIT_HTTP_SMOKE';
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
    throw 'failed to set up purchase-order audit edit smoke data'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

try {
    $auditPayload = @{
        id = $orderId
        amount = 222.22
        productList = @(
            @{
                id = $itemId
                amount = 222.22
                unitAmount = 111.11
                discountRate = 2.50
                freightShareAmount = 4.75
                unitCostWithFreight = 115.86
            }
        )
    }

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/audit/edit" -Data $auditPayload
    Assert-Code -Json $noToken -Expected 401 -Name 'purchase order audit edit without token'

    $missing = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/audit/edit" -Token $token -Data @{}
    Assert-Code -Json $missing -Expected 400 -Name 'purchase order audit edit missing id'

    $missingList = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/audit/edit" -Token $token -Data @{ id = $orderId }
    Assert-Code -Json $missingList -Expected 400 -Name 'purchase order audit edit missing productList'

    $duplicateItem = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/audit/edit" -Token $token -Data @{
        id = $orderId
        amount = 111
        productList = @(@{ id = $itemId; amount = 111 }, @{ id = $itemId; amount = 112 })
    }
    Assert-Code -Json $duplicateItem -Expected 400 -Name 'purchase order audit edit duplicate item'

    $notFound = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/audit/edit" -Token $token -Data @{
        id = $missingOrderId
        amount = 111
        productList = @(@{ id = $itemId; amount = 111 })
    }
    Assert-Code -Json $notFound -Expected 404 -Name 'purchase order audit edit missing order'

    $wrongItem = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/audit/edit" -Token $token -Data @{
        id = $orderId
        amount = 111
        productList = @(@{ id = $otherItemId; amount = 111 })
    }
    Assert-Code -Json $wrongItem -Expected 404 -Name 'purchase order audit edit wrong item order'

    $normalEditRejected = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/edit" -Token $token -Data $auditPayload
    Assert-Code -Json $normalEditRejected -Expected 400 -Name 'normal purchase order edit still rejects completed order'

    $beforeCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'order' => think\facade\Db::name('biz_purchase_order')->where('ID', '$safeOrderId')->field('AMOUNT,SETTLEMENT_STATUS,STORAGE_STATUS,VERSION')->find(),
    'item' => think\facade\Db::name('biz_purchase_order_item')->where('ID', '$safeItemId')->field('AMOUNT,UNIT_AMOUNT,DISCOUNT_RATE,FREIGHT_SHARE_AMOUNT,UNIT_COST_WITH_FREIGHT,VERSION')->find(),
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
    Assert-Equal -Actual ([string]$before.order.AMOUNT) -Expected '100.00' -Name 'audit order amount before success'
    Assert-Equal -Actual ([string]$before.order.SETTLEMENT_STATUS) -Expected 'COMPLETED' -Name 'audit order completed status before success'
    Assert-Equal -Actual ([string]$before.order.VERSION) -Expected '0' -Name 'audit order version before success'
    Assert-Equal -Actual ([string]$before.item.AMOUNT) -Expected '100.00' -Name 'audit item amount before success'
    Assert-Equal -Actual ([string]$before.item.VERSION) -Expected '0' -Name 'audit item version before success'
    Assert-Equal -Actual ([string]$before.otherItem.AMOUNT) -Expected '100.00' -Name 'wrong item amount preserved'
    Assert-Equal -Actual ([string]$before.otherItem.VERSION) -Expected '0' -Name 'wrong item version preserved'
    Assert-Equal -Actual ([string]$before.missingCount) -Expected '0' -Name 'missing order was not created'
    foreach ($name in @('orders', 'items', 'expenditure', 'inventory', 'delivery')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$before.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed after failed audit edit cases: expected=$expected actual=$actual"
        }
    }

    $auditEdit = Invoke-RawPostJson -Url "$baseUrl/biz/bizpurchaseorder/audit/edit" -Token $token -Data $auditPayload
    Assert-Code -Json $auditEdit -Expected 200 -Name 'purchase order audit edit'
    Assert-PathEquals -Json $auditEdit -Path 'data.id' -Expected $orderId -Name 'purchase order audit edit id'
    Assert-PathEquals -Json $auditEdit -Path 'data.updatedItems' -Expected '1' -Name 'purchase order audit edit item count'
    Assert-PathEquals -Json $auditEdit -Path 'data.amount' -Expected '222.22' -Name 'purchase order audit edit amount'

    $detail = Invoke-RawGet -Url "$baseUrl/biz/bizpurchaseorder/detail?id=$(Enc $orderId)" -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'purchase order detail after audit edit'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrder.id' -Expected $orderId -Name 'purchase order detail id'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrder.amount' -Expected '222.22' -Name 'purchase order detail audit edited amount'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrder.settlementStatus' -Expected 'COMPLETED' -Name 'purchase order audit edit status preserved'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrder.storageStatus' -Expected 'NOT_IN_WAREHOUSE' -Name 'purchase order audit edit storage preserved'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrderItemList.0.id' -Expected $itemId -Name 'purchase order detail audit item id'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrderItemList.0.amount' -Expected '222.22' -Name 'purchase order detail audit item amount'
    Assert-PathEquals -Json $detail -Path 'data.bizPurchaseOrderItemList.0.unitAmount' -Expected '111.11' -Name 'purchase order detail audit item unit amount'

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
    Assert-Equal -Actual ([string]$after.order.amount) -Expected '222.22' -Name 'audit order amount after edit'
    Assert-Equal -Actual ([string]$after.order.settlementStatus) -Expected 'COMPLETED' -Name 'audit order settlement preserved'
    Assert-Equal -Actual ([string]$after.order.storageStatus) -Expected 'NOT_IN_WAREHOUSE' -Name 'audit order storage preserved'
    Assert-Equal -Actual ([string]$after.order.version) -Expected '1' -Name 'audit order version incremented'
    Assert-Equal -Actual ([string]$after.order.updateUser) -Expected $userId -Name 'audit order update user'
    Assert-Equal -Actual ([string]$after.order.deleteFlag) -Expected 'NOT_DELETE' -Name 'audit order delete flag preserved'
    Assert-Equal -Actual ([string]$after.item.purchaseOrderId) -Expected $orderId -Name 'audit item order id preserved'
    Assert-Equal -Actual ([string]$after.item.storageStatus) -Expected 'NOT_IN_WAREHOUSE' -Name 'audit item storage preserved'
    Assert-Equal -Actual ([string]$after.item.number) -Expected '2' -Name 'audit item number preserved'
    Assert-Equal -Actual ([string]$after.item.amount) -Expected '222.22' -Name 'audit item amount after edit'
    Assert-Equal -Actual ([string]$after.item.unitAmount) -Expected '111.11' -Name 'audit item unit amount after edit'
    Assert-Equal -Actual ([string]$after.item.discountRate) -Expected '2.50' -Name 'audit item discount after edit'
    Assert-Equal -Actual ([string]$after.item.freightShareAmount) -Expected '4.75' -Name 'audit item freight after edit'
    Assert-Equal -Actual ([string]$after.item.unitCostWithFreight) -Expected '115.86' -Name 'audit item cost with freight after edit'
    Assert-Equal -Actual ([string]$after.item.version) -Expected '1' -Name 'audit item version incremented'
    Assert-Equal -Actual ([string]$after.item.updateUser) -Expected $userId -Name 'audit item update user'
    Assert-Equal -Actual ([string]$after.item.deleteFlag) -Expected 'NOT_DELETE' -Name 'audit item delete flag preserved'
    Assert-Equal -Actual ([string]$after.expenditure.objectId) -Expected $orderId -Name 'audit expenditure object preserved'
    Assert-Equal -Actual ([string]$after.expenditure.settlementCategory) -Expected 'GOODS_EXPENDITURE' -Name 'audit expenditure category preserved'
    Assert-Equal -Actual ([string]$after.expenditure.amount) -Expected '1.00' -Name 'audit expenditure amount preserved'
    foreach ($name in @('orders', 'items', 'expenditure', 'inventory', 'delivery')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$after.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed unexpectedly: expected=$expected actual=$actual"
        }
    }

    Write-Host 'purchase order audit edit HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
