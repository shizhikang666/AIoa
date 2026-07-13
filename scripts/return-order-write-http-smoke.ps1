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
        [Parameter(Mandatory = $true)][string]$Key,
        [string]$Default = ''
    )

    if ($EnvMap.ContainsKey($Key) -and [string]$EnvMap[$Key] -ne '') {
        return [string]$EnvMap[$Key]
    }

    return $Default
}

function Invoke-RawGet {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [string]$Token = ''
    )

    $args = @('-sS', '-X', 'GET', $Url)
    if ($Token.Trim() -ne '') {
        $args += @('-H', "Authorization: Bearer $Token")
    }

    $raw = & curl.exe @args
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP GET failed: $Url"
    }

    return [string]::Join('', [string[]]$raw)
}

function Invoke-RawPostJson {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)]$Data,
        [string]$Token = ''
    )

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-return-order-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    try {
        ConvertTo-Json -InputObject $Data -Depth 16 -Compress | Set-Content -LiteralPath $tmp -Encoding ASCII
        $args = @('-sS', '-X', 'POST', $Url, '-H', 'Content-Type: application/json', '--data-binary', "@$tmp")
        if ($Token.Trim() -ne '') {
            $args += @('-H', "Authorization: Bearer $Token")
        }

        $raw = & curl.exe @args
        if ($LASTEXITCODE -ne 0) {
            throw "HTTP POST failed: $Url"
        }

        return [string]::Join('', [string[]]$raw)
    } finally {
        Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue
    }
}

function Read-JsonPath {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path,
        [switch]$Optional
    )

    $value = $Json | node (Join-Path $PSScriptRoot 'json-read.js') $Path
    $exitCode = $LASTEXITCODE
    if ($exitCode -eq 2 -and $Optional) {
        return $null
    }
    if ($exitCode -ne 0) {
        throw "JSON path missing or invalid: $Path"
    }

    return [string]$value
}

function Assert-Code {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][int]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $code = [int](Read-JsonPath -Json $Json -Path 'code')
    if ($code -ne $Expected) {
        throw "$Name returned code=$code expected=$Expected body=$Json"
    }
}

function Assert-PathEquals {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $actual = [string](Read-JsonPath -Json $Json -Path $Path)
    if ($actual -ne $Expected) {
        throw "$Name expected $Path=$Expected actual=$actual body=$Json"
    }
}

function Enc {
    param([Parameter(Mandatory = $true)][string]$Value)

    return [System.Uri]::EscapeDataString($Value)
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

function New-SmokeId {
    param([Parameter(Mandatory = $true)][string]$Prefix)

    return $Prefix + ([Guid]::NewGuid().ToString('N').Substring(0, 20 - $Prefix.Length))
}

$envMap = Get-EnvMap -Path $EnvPath
$account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
if ($account -eq '') {
    throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env'
}

$safeAccount = $account.Replace("'", "\'")
$sessionCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_RETURN_ORDER_WRITE_HTTP_SMOKE';
`$tenantId = (string)(`$user['TENANT_ID'] ?? '1');
if (`$tenantId === '') { `$tenantId = '1'; }
`$orgId = (string)(`$user['ORG_ID'] ?? '');
if (`$orgId === '') {
    `$orgId = (string)(think\facade\Db::name('sys_org')->where(function (`$query) {
        `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
    })->value('ID') ?? '0');
}
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => (string)`$user['ID'],
    'tenantId' => `$tenantId,
    'orgId' => `$orgId,
], JSON_UNESCAPED_SLASHES);
"@

$session = Invoke-PhpJson -Code $sessionCode
$token = [string]$session.token
$userId = [string]$session.userId
$tenantId = [string]$session.tenantId
$orgId = [string]$session.orgId
if ($token.Trim() -eq '' -or $userId.Trim() -eq '' -or $tenantId.Trim() -eq '' -or $orgId.Trim() -eq '') {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'ro' + ([Guid]::NewGuid().ToString('N').Substring(0, 10))
$projectId = New-SmokeId -Prefix 'RSP'
$customerId = New-SmokeId -Prefix 'RSC'
$warehouseId = New-SmokeId -Prefix 'RWH'
$productIdA = New-SmokeId -Prefix 'RPA'
$productIdB = New-SmokeId -Prefix 'RPB'
$itemIdA = New-SmokeId -Prefix 'RIA'
$itemIdB = New-SmokeId -Prefix 'RIB'
$missingProjectId = New-SmokeId -Prefix 'RSM'
$missingItemId = New-SmokeId -Prefix 'RIM'
$missingOrderId = New-SmokeId -Prefix 'ROM'
$expenditureId = New-SmokeId -Prefix 'RER'
$settlementAccountId = New-SmokeId -Prefix 'RSA'

$safePrefix = $prefix.Replace("'", "\'")
$safeProjectId = $projectId.Replace("'", "\'")
$safeCustomerId = $customerId.Replace("'", "\'")
$safeWarehouseId = $warehouseId.Replace("'", "\'")
$safeProductIdA = $productIdA.Replace("'", "\'")
$safeProductIdB = $productIdB.Replace("'", "\'")
$safeItemIdA = $itemIdA.Replace("'", "\'")
$safeItemIdB = $itemIdB.Replace("'", "\'")
$safeUserId = $userId.Replace("'", "\'")
$safeTenantId = $tenantId.Replace("'", "\'")
$safeOrgId = $orgId.Replace("'", "\'")
$safeExpenditureId = $expenditureId.Replace("'", "\'")
$safeSettlementAccountId = $settlementAccountId.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$orderIds = think\facade\Db::name('return_order')->where('PROJECT_ID', '$safeProjectId')->column('ID');
if (`$orderIds) {
    think\facade\Db::name('delivery_record')->whereIn('OBJECT_ID', `$orderIds)->delete();
    think\facade\Db::name('return_order_item')->whereIn('RETURN_ORDER_ID', `$orderIds)->delete();
    think\facade\Db::name('biz_expenditure_record')->whereIn('OBJECT_ID', `$orderIds)->delete();
}
think\facade\Db::name('return_order_item')->whereIn('PROJECT_PRODUCT_ITEM_ID', ['$safeItemIdA', '$safeItemIdB'])->delete();
think\facade\Db::name('return_order')->where('PROJECT_ID', '$safeProjectId')->delete();
think\facade\Db::name('biz_expenditure_record')->where('PROCESS_ID', 'like', '$safePrefix%')->delete();
think\facade\Db::name('settlement_account_statement')->where('PROCESS_ID', 'like', '$safePrefix%')->delete();
think\facade\Db::name('settlement_account_statement')->where('ACCOUNT_ID', '$safeSettlementAccountId')->delete();
think\facade\Db::name('delivery_record')->where('PROCESS_ID', 'like', '$safePrefix%')->delete();
think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$safeWarehouseId')->delete();
think\facade\Db::name('settlement_account')->where('ID', '$safeSettlementAccountId')->delete();
think\facade\Db::name('biz_sale_project_product_item')->whereIn('ID', ['$safeItemIdA', '$safeItemIdB'])->delete();
think\facade\Db::name('biz_product')->whereIn('ID', ['$safeProductIdA', '$safeProductIdB'])->delete();
think\facade\Db::name('warehouses')->where('ID', '$safeWarehouseId')->delete();
think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->delete();
"@

Invoke-Php -Code $cleanupCode | Out-Null

$sideEffectCountCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'delivery' => think\facade\Db::name('delivery_record')->count(),
    'inventory' => think\facade\Db::name('inventory')->count(),
    'expenditure' => think\facade\Db::name('biz_expenditure_record')->count(),
    'payment' => think\facade\Db::name('biz_payment_record')->count(),
    'statement' => think\facade\Db::name('settlement_account_statement')->count()
], JSON_UNESCAPED_SLASHES);
"@

try {
    $before = Invoke-PhpJson -Code $sideEffectCountCode

    $setupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('biz_sale_project')->insert([
    'ID' => '$safeProjectId',
    'CUSTOMER' => '$safeCustomerId',
    'PROJECT_NAME' => '$safePrefix project',
    'PROJECT_STATE' => 'SHIPPED',
    'PLAY_STATE' => 'UNPAID',
    'VISIBILITY' => 'PRIVATE',
    'INIT_PRICE' => '1000.00',
    'TOTAL_PRICE' => '1000.00',
    'AMOUNT_COLLECTED' => '0.00',
    'PROJECT_CATEGORY' => 'DEFAULT',
    'USER' => '$safeUserId',
    'ORG' => '$safeOrgId',
    'REMARK' => '$safePrefix',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => '$safeUserId',
    'TENANT_ID' => '$safeTenantId',
    'VERSION' => 0,
    'DEAL_AMOUNT' => 0,
    'HISTORY_AMOUNT' => '0.00',
    'TOTAL_RETURN_AMOUNT' => '0.00',
    'TOTAL_REFUND_AMOUNT' => '0.00',
]);
think\facade\Db::name('warehouses')->insert([
    'ID' => '$safeWarehouseId',
    'NAME' => '$safePrefix warehouse',
    'CODE' => '$safePrefix',
    'ADDRESS' => '$safePrefix address',
    'SORT_CODE' => 1,
    'USER' => '$safeUserId',
    'ORG' => '$safeOrgId',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => '$safeUserId',
    'TENANT_ID' => '$safeTenantId',
]);
think\facade\Db::name('settlement_account')->insert([
    'ID' => '$safeSettlementAccountId',
    'ACCOUNT_NAME' => '$safePrefix refund account',
    'ACCOUNT_NUMBER' => '$safePrefix refund no',
    'INITIAL_AMOUNT' => '1000.00',
    'CURRENT_AMOUNT' => '1000.00',
    'ACCOUNT_STATUS' => 'ENABLE',
    'SORT_CODE' => 1,
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => '$safeUserId',
    'UPDATE_TIME' => `$now,
    'UPDATE_USER' => '$safeUserId',
    'TENANT_ID' => '$safeTenantId',
    'VERSION' => 0,
    'org' => '$safeOrgId',
]);
think\facade\Db::name('biz_product')->insertAll([
    [
        'ID' => '$safeProductIdA',
        'PRODUCT_NAME' => '$safePrefix product A',
        'PRODUCT_CATEGORY' => 'DEFAULT',
        'SAFETY_STOCK' => 0,
        'PURCHASE_PRICE' => '10.00',
        'SALE_PRICE' => '100.00',
        'MIN_PRICE' => '80.00',
        'CATEGORY' => 'DEFAULT',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => '$safeUserId',
        'TENANT_ID' => '$safeTenantId',
        'ORG' => '$safeOrgId',
        'status' => 'ENABLE',
    ],
    [
        'ID' => '$safeProductIdB',
        'PRODUCT_NAME' => '$safePrefix product B',
        'PRODUCT_CATEGORY' => 'DEFAULT',
        'SAFETY_STOCK' => 0,
        'PURCHASE_PRICE' => '20.00',
        'SALE_PRICE' => '120.00',
        'MIN_PRICE' => '90.00',
        'CATEGORY' => 'DEFAULT',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => '$safeUserId',
        'TENANT_ID' => '$safeTenantId',
        'ORG' => '$safeOrgId',
        'status' => 'ENABLE',
    ],
]);
think\facade\Db::name('biz_sale_project_product_item')->insertAll([
    [
        'ID' => '$safeItemIdA',
        'PROJECT_ID' => '$safeProjectId',
        'PRODUCT_ID' => '$safeProductIdA',
        'CATEGORY' => 'DEFAULT',
        'STATE' => 'SHIPPED',
        'NUMBER' => 5,
        'DELIVERY' => 5,
        'UNIT_PRICE' => '100.00',
        'DISCOUNT_RATE' => '1.00',
        'PRICE' => '500.00',
        'REMARK' => '$safePrefix item A',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => '$safeUserId',
        'TENANT_ID' => '$safeTenantId',
        'VERSION' => 0,
        'PROJECT_REISSUE_ORDER_ID' => '',
    ],
    [
        'ID' => '$safeItemIdB',
        'PROJECT_ID' => '$safeProjectId',
        'PRODUCT_ID' => '$safeProductIdB',
        'CATEGORY' => 'DEFAULT',
        'STATE' => 'SHIPPED',
        'NUMBER' => 7,
        'DELIVERY' => 7,
        'UNIT_PRICE' => '120.00',
        'DISCOUNT_RATE' => '1.00',
        'PRICE' => '840.00',
        'REMARK' => '$safePrefix item B',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => '$safeUserId',
        'TENANT_ID' => '$safeTenantId',
        'VERSION' => 0,
        'PROJECT_REISSUE_ORDER_ID' => '',
    ],
]);
"@
    Invoke-Php -Code $setupCode | Out-Null

    $validAddPayload = @{
        projectId = $projectId
        amount = '120.00'
        warehousesId = $warehouseId
        processId = "$prefix-add"
        logisticsCategory = 'DEFAULT'
        logisticsId = "$prefix-logistics-a"
        remark = "$prefix add"
        productList = @(
            @{
                projectProductItemId = $itemIdA
                productId = $productIdA
                amount = '2'
            }
        )
        extJson = @{
            source = 'codex'
            step = 'add'
        }
    }

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/returnorder/add" -Data $validAddPayload
    Assert-Code -Json $noToken -Expected 401 -Name 'return order add without token'

    $missingProject = Invoke-RawPostJson -Url "$baseUrl/biz/returnorder/add" -Token $token -Data @{
        amount = '120.00'
        warehousesId = $warehouseId
        productList = @(@{ projectProductItemId = $itemIdA; amount = '1' })
    }
    Assert-Code -Json $missingProject -Expected 400 -Name 'return order add missing projectId'

    $missingWarehouse = Invoke-RawPostJson -Url "$baseUrl/biz/returnorder/add" -Token $token -Data @{
        projectId = $projectId
        amount = '120.00'
        productList = @(@{ projectProductItemId = $itemIdA; amount = '1' })
    }
    Assert-Code -Json $missingWarehouse -Expected 400 -Name 'return order add missing warehousesId'

    $emptyProducts = Invoke-RawPostJson -Url "$baseUrl/biz/returnorder/add" -Token $token -Data @{
        projectId = $projectId
        amount = '120.00'
        warehousesId = $warehouseId
        productList = @()
    }
    Assert-Code -Json $emptyProducts -Expected 400 -Name 'return order add empty productList'

    $missingProjectRow = Invoke-RawPostJson -Url "$baseUrl/biz/returnorder/add" -Token $token -Data @{
        projectId = $missingProjectId
        amount = '120.00'
        warehousesId = $warehouseId
        productList = @(@{ projectProductItemId = $itemIdA; amount = '1' })
    }
    Assert-Code -Json $missingProjectRow -Expected 404 -Name 'return order add missing project row'

    $missingProductItem = Invoke-RawPostJson -Url "$baseUrl/biz/returnorder/add" -Token $token -Data @{
        projectId = $projectId
        amount = '120.00'
        warehousesId = $warehouseId
        productList = @(@{ projectProductItemId = $missingItemId; amount = '1' })
    }
    Assert-Code -Json $missingProductItem -Expected 404 -Name 'return order add missing product item'

    $afterInvalidAdds = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'orders' => think\facade\Db::name('return_order')->where('PROJECT_ID', '$safeProjectId')->count(),
    'items' => think\facade\Db::name('return_order_item')->whereIn('PROJECT_PRODUCT_ITEM_ID', ['$safeItemIdA', '$safeItemIdB'])->count()
], JSON_UNESCAPED_SLASHES);
"@
    if ([int]$afterInvalidAdds.orders -ne 0 -or [int]$afterInvalidAdds.items -ne 0) {
        throw "return order invalid add rollback failed: $($afterInvalidAdds | ConvertTo-Json -Compress)"
    }

    $add = Invoke-RawPostJson -Url "$baseUrl/biz/returnorder/add" -Token $token -Data $validAddPayload
    Assert-Code -Json $add -Expected 200 -Name 'return order add'
    $orderId = [string](Read-JsonPath -Json $add -Path 'data.id')
    if ($orderId.Trim() -eq '') {
        throw 'return order add did not return data.id'
    }
    Assert-PathEquals -Json $add -Path 'data.projectId' -Expected $projectId -Name 'return order add'
    Assert-PathEquals -Json $add -Path 'data.warehousesId' -Expected $warehouseId -Name 'return order add'
    Assert-PathEquals -Json $add -Path 'data.state' -Expected 'Unsettled' -Name 'return order add'
    Assert-PathEquals -Json $add -Path 'data.productList.0.projectProductItemId' -Expected $itemIdA -Name 'return order add'

    $page = Invoke-RawGet -Url "$baseUrl/biz/returnorder/page?projectId=$(Enc $projectId)&size=10" -Token $token
    Assert-Code -Json $page -Expected 200 -Name 'return order page'
    Assert-PathEquals -Json $page -Path 'data.records.0.id' -Expected $orderId -Name 'return order page'

    $query = Invoke-RawGet -Url "$baseUrl/biz/returnorder/query?projectId=$(Enc $projectId)" -Token $token
    Assert-Code -Json $query -Expected 200 -Name 'return order query'
    Assert-PathEquals -Json $query -Path 'data.0.id' -Expected $orderId -Name 'return order query'
    Assert-PathEquals -Json $query -Path 'data.0.productList.0.projectProductItemId' -Expected $itemIdA -Name 'return order query'

    $safeOrderId = $orderId.Replace("'", "\'")
    $afterAddDb = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$order = think\facade\Db::name('return_order')->where('ID', '$safeOrderId')->find();
`$itemCount = think\facade\Db::name('return_order_item')->where('RETURN_ORDER_ID', '$safeOrderId')->where('DELETE_FLAG', 'NOT_DELETE')->count();
`$project = think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->find();
`$deliveryRows = think\facade\Db::name('delivery_record')->where('OBJECT_ID', '$safeOrderId')->select()->toArray();
`$inventory = think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$safeWarehouseId')->where('PRODUCT_ID', '$safeProductIdA')->find();
echo json_encode([
    'order' => `$order,
    'itemCount' => `$itemCount,
    'project' => `$project,
    'deliveryRows' => `$deliveryRows,
    'inventory' => `$inventory,
], JSON_UNESCAPED_SLASHES);
"@
    if ([string]$afterAddDb.order.DELETE_FLAG -ne 'NOT_DELETE' -or [decimal]$afterAddDb.order.AMOUNT -ne [decimal]'120.00' -or [int]$afterAddDb.itemCount -ne 1) {
        throw "return order add database verification failed: $($afterAddDb | ConvertTo-Json -Compress)"
    }
    if ([decimal]$afterAddDb.project.TOTAL_REFUND_AMOUNT -ne [decimal]'120.00' -or [decimal]$afterAddDb.project.TOTAL_RETURN_AMOUNT -ne [decimal]'0.00' -or [decimal]$afterAddDb.project.TOTAL_PRICE -ne [decimal]'880.00') {
        throw "return order add project totals failed: $($afterAddDb | ConvertTo-Json -Compress)"
    }
    $deliveryRows = @($afterAddDb.deliveryRows)
    if ($deliveryRows.Count -ne 1) {
        throw "return order add expected one delivery record: $($afterAddDb | ConvertTo-Json -Compress)"
    }
    $delivery = $deliveryRows[0]
    if ([string]$delivery.CATEGORY -ne 'IN' -or [string]$delivery.PROCESS_CATEGORY -ne 'Process_sale_project_product_return' -or [string]$delivery.PROCESS_ID -ne "$prefix-add" -or [string]$delivery.PRODUCT_ID -ne $productIdA) {
        throw "return order delivery verification failed: $($delivery | ConvertTo-Json -Compress)"
    }
    if ([decimal]$delivery.AMOUNT -ne [decimal]'2' -or [string]$delivery.OBJECT_ID -ne $orderId -or [string]$delivery.WAREHOUSES_ID -ne $warehouseId) {
        throw "return order delivery amount/object verification failed: $($delivery | ConvertTo-Json -Compress)"
    }
    if ([string]$afterAddDb.inventory.DELETE_FLAG -ne 'NOT_DELETE' -or [decimal]$afterAddDb.inventory.CURRENT_COUNT -ne [decimal]'2') {
        throw "return order add inventory verification failed: $($afterAddDb.inventory | ConvertTo-Json -Compress)"
    }

    $invalidEdit = Invoke-RawPostJson -Url "$baseUrl/biz/returnorder/edit" -Token $token -Data @{
        id = $orderId
        projectId = $projectId
        amount = '130.00'
        warehousesId = $warehouseId
        productList = @(@{ projectProductItemId = $itemIdA; amount = '999' })
    }
    Assert-Code -Json $invalidEdit -Expected 400 -Name 'return order edit invalid product amount rollback'

    $afterInvalidEdit = Invoke-RawGet -Url "$baseUrl/biz/returnorder/detail?id=$(Enc $orderId)" -Token $token
    Assert-Code -Json $afterInvalidEdit -Expected 200 -Name 'return order detail after invalid edit'
    Assert-PathEquals -Json $afterInvalidEdit -Path 'data.productList.0.projectProductItemId' -Expected $itemIdA -Name 'return order detail after invalid edit'

    $refundBasePayload = @{
        objectId = $orderId
        targetId = $settlementAccountId
        settlementCategory = 'ReturnAndRefund'
        payer = "$prefix payer"
        bankName = "$prefix bank"
        bankAccount = "$prefix account"
        payerTime = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
    }

    $firstRefund = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/expenses/add" -Token $token -Data ($refundBasePayload + @{
        amount = '60.00'
        remark = "$prefix first refund"
    })
    Assert-Code -Json $firstRefund -Expected 200 -Name 'return refund first expense'
    Assert-PathEquals -Json $firstRefund -Path 'data.returnRefund.state' -Expected 'Unsettled' -Name 'return refund first state'
    Assert-PathEquals -Json $firstRefund -Path 'data.returnRefund.refundAmount' -Expected '60.00' -Name 'return refund first amount'

    $overRefund = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/expenses/add" -Token $token -Data ($refundBasePayload + @{
        amount = '70.00'
        remark = "$prefix over refund"
    })
    Assert-Code -Json $overRefund -Expected 400 -Name 'return refund over amount rollback'

    $secondRefund = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/expenses/add" -Token $token -Data ($refundBasePayload + @{
        amount = '60.00'
        remark = "$prefix second refund"
    })
    Assert-Code -Json $secondRefund -Expected 200 -Name 'return refund second expense'
    Assert-PathEquals -Json $secondRefund -Path 'data.returnRefund.state' -Expected 'AlreadySettled' -Name 'return refund settled state'
    Assert-PathEquals -Json $secondRefund -Path 'data.returnRefund.refundAmount' -Expected '120.00' -Name 'return refund settled amount'

    $finalDb = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$order = think\facade\Db::name('return_order')->where('ID', '$safeOrderId')->find();
`$project = think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->find();
`$account = think\facade\Db::name('settlement_account')->where('ID', '$safeSettlementAccountId')->find();
`$deliveryCount = think\facade\Db::name('delivery_record')->where('OBJECT_ID', '$safeOrderId')->count();
`$inventory = think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$safeWarehouseId')->where('PRODUCT_ID', '$safeProductIdA')->find();
`$expenditureCount = think\facade\Db::name('biz_expenditure_record')->where('OBJECT_ID', '$safeOrderId')->where('SETTLEMENT_CATEGORY', 'ReturnAndRefund')->count();
`$statementCount = think\facade\Db::name('settlement_account_statement')->where('ACCOUNT_ID', '$safeSettlementAccountId')->count();
echo json_encode([
    'order' => `$order,
    'project' => `$project,
    'account' => `$account,
    'deliveryCount' => `$deliveryCount,
    'inventory' => `$inventory,
    'expenditureCount' => `$expenditureCount,
    'statementCount' => `$statementCount,
], JSON_UNESCAPED_SLASHES);
"@
    if ([string]$finalDb.order.STATE -ne 'AlreadySettled' -or [string]$finalDb.order.DELETE_FLAG -ne 'NOT_DELETE') {
        throw "return order refund state verification failed: $($finalDb.order | ConvertTo-Json -Compress)"
    }
    if ([decimal]$finalDb.project.TOTAL_REFUND_AMOUNT -ne [decimal]'120.00' -or [decimal]$finalDb.project.TOTAL_RETURN_AMOUNT -ne [decimal]'120.00' -or [decimal]$finalDb.project.TOTAL_PRICE -ne [decimal]'880.00') {
        throw "return refund project totals failed: $($finalDb.project | ConvertTo-Json -Compress)"
    }
    if ([decimal]$finalDb.account.CURRENT_AMOUNT -ne [decimal]'880.00') {
        throw "return refund account balance failed: $($finalDb.account | ConvertTo-Json -Compress)"
    }
    if ([int]$finalDb.deliveryCount -ne 1 -or [decimal]$finalDb.inventory.CURRENT_COUNT -ne [decimal]'2') {
        throw "return delivery/inventory final verification failed: $($finalDb | ConvertTo-Json -Compress)"
    }
    if ([int]$finalDb.expenditureCount -ne 2 -or [int]$finalDb.statementCount -ne 2) {
        throw "return refund rollback count verification failed: $($finalDb | ConvertTo-Json -Compress)"
    }

    $edit = Invoke-RawPostJson -Url "$baseUrl/biz/returnorder/edit" -Token $token -Data @{
        id = $orderId
        projectId = $projectId
        amount = '80.00'
        warehousesId = $warehouseId
        processId = "$prefix-edit"
        logisticsCategory = 'EDITED'
        logisticsId = "$prefix-logistics-b"
        remark = "$prefix edited"
        productList = @(
            @{
                projectProductItemId = $itemIdB
                productId = $productIdB
                amount = '3'
            }
        )
        extJson = @{
            source = 'codex'
            step = 'edit'
        }
    }
    Assert-Code -Json $edit -Expected 200 -Name 'return order edit with reverse stock and refund'
    Assert-PathEquals -Json $edit -Path 'data.id' -Expected $orderId -Name 'return order edit id'
    Assert-PathEquals -Json $edit -Path 'data.amount' -Expected '80' -Name 'return order edit amount'
    Assert-PathEquals -Json $edit -Path 'data.state' -Expected 'Unsettled' -Name 'return order edit state after refund reverse'
    Assert-PathEquals -Json $edit -Path 'data.productList.0.projectProductItemId' -Expected $itemIdB -Name 'return order edit product item'

    $afterEditDb = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$order = think\facade\Db::name('return_order')->where('ID', '$safeOrderId')->find();
`$activeItems = think\facade\Db::name('return_order_item')->where('RETURN_ORDER_ID', '$safeOrderId')->where('DELETE_FLAG', 'NOT_DELETE')->select()->toArray();
`$project = think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->find();
`$account = think\facade\Db::name('settlement_account')->where('ID', '$safeSettlementAccountId')->find();
`$activeDeliveryRows = think\facade\Db::name('delivery_record')->where('OBJECT_ID', '$safeOrderId')->where('DELETE_FLAG', 'NOT_DELETE')->select()->toArray();
`$oldInventory = think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$safeWarehouseId')->where('PRODUCT_ID', '$safeProductIdA')->find();
`$newInventory = think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$safeWarehouseId')->where('PRODUCT_ID', '$safeProductIdB')->find();
`$activeExpenditureCount = think\facade\Db::name('biz_expenditure_record')->where('OBJECT_ID', '$safeOrderId')->where('SETTLEMENT_CATEGORY', 'ReturnAndRefund')->where('DELETE_FLAG', 'NOT_DELETE')->count();
`$activeStatementCount = think\facade\Db::name('settlement_account_statement')->where('ACCOUNT_ID', '$safeSettlementAccountId')->where('SETTLEMENT_CATEGORY', 'ReturnAndRefund')->where('DELETE_FLAG', 'NOT_DELETE')->count();
`$deletedExpenditureCount = think\facade\Db::name('biz_expenditure_record')->where('OBJECT_ID', '$safeOrderId')->where('SETTLEMENT_CATEGORY', 'ReturnAndRefund')->where('DELETE_FLAG', 'DELETED')->count();
`$deletedDeliveryCount = think\facade\Db::name('delivery_record')->where('OBJECT_ID', '$safeOrderId')->where('DELETE_FLAG', 'DELETED')->count();
echo json_encode([
    'order' => `$order,
    'activeItems' => `$activeItems,
    'project' => `$project,
    'account' => `$account,
    'activeDeliveryRows' => `$activeDeliveryRows,
    'oldInventory' => `$oldInventory,
    'newInventory' => `$newInventory,
    'activeExpenditureCount' => `$activeExpenditureCount,
    'activeStatementCount' => `$activeStatementCount,
    'deletedExpenditureCount' => `$deletedExpenditureCount,
    'deletedDeliveryCount' => `$deletedDeliveryCount,
], JSON_UNESCAPED_SLASHES);
"@
    $activeItems = @($afterEditDb.activeItems)
    $activeDeliveryRows = @($afterEditDb.activeDeliveryRows)
    if ([string]$afterEditDb.order.STATE -ne 'Unsettled' -or [decimal]$afterEditDb.order.AMOUNT -ne [decimal]'80.00' -or [string]$afterEditDb.order.PROCESS_ID -ne "$prefix-edit") {
        throw "return order edit master verification failed: $($afterEditDb.order | ConvertTo-Json -Compress)"
    }
    if ($activeItems.Count -ne 1 -or [string]$activeItems[0].PROJECT_PRODUCT_ITEM_ID -ne $itemIdB -or [decimal]$activeItems[0].AMOUNT -ne [decimal]'3') {
        throw "return order edit item replacement failed: $($activeItems | ConvertTo-Json -Compress)"
    }
    if ([decimal]$afterEditDb.project.TOTAL_REFUND_AMOUNT -ne [decimal]'80.00' -or [decimal]$afterEditDb.project.TOTAL_RETURN_AMOUNT -ne [decimal]'0.00' -or [decimal]$afterEditDb.project.TOTAL_PRICE -ne [decimal]'920.00') {
        throw "return order edit project totals failed: $($afterEditDb.project | ConvertTo-Json -Compress)"
    }
    if ([decimal]$afterEditDb.account.CURRENT_AMOUNT -ne [decimal]'1000.00') {
        throw "return order edit account refund reverse failed: $($afterEditDb.account | ConvertTo-Json -Compress)"
    }
    if ($activeDeliveryRows.Count -ne 1 -or [string]$activeDeliveryRows[0].PRODUCT_ID -ne $productIdB -or [decimal]$activeDeliveryRows[0].AMOUNT -ne [decimal]'3' -or [string]$activeDeliveryRows[0].PROCESS_ID -ne "$prefix-edit") {
        throw "return order edit delivery replacement failed: $($activeDeliveryRows | ConvertTo-Json -Compress)"
    }
    if ([decimal]$afterEditDb.oldInventory.CURRENT_COUNT -ne [decimal]'0' -or [decimal]$afterEditDb.newInventory.CURRENT_COUNT -ne [decimal]'3') {
        throw "return order edit inventory reverse/rebuild failed: $($afterEditDb | ConvertTo-Json -Compress)"
    }
    if ([int]$afterEditDb.activeExpenditureCount -ne 0 -or [int]$afterEditDb.activeStatementCount -ne 0 -or [int]$afterEditDb.deletedExpenditureCount -ne 2 -or [int]$afterEditDb.deletedDeliveryCount -ne 1) {
        throw "return order edit side-effect reverse counts failed: $($afterEditDb | ConvertTo-Json -Compress)"
    }

    $editedRefund = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/expenses/add" -Token $token -Data ($refundBasePayload + @{
        amount = '80.00'
        remark = "$prefix edited refund"
    })
    Assert-Code -Json $editedRefund -Expected 200 -Name 'return refund edited expense'
    Assert-PathEquals -Json $editedRefund -Path 'data.returnRefund.state' -Expected 'AlreadySettled' -Name 'return refund edited settled state'
    Assert-PathEquals -Json $editedRefund -Path 'data.returnRefund.refundAmount' -Expected '80.00' -Name 'return refund edited amount'

    $delete = Invoke-RawPostJson -Url "$baseUrl/biz/returnorder/delete" -Token $token -Data @{
        idList = @($orderId)
    }
    Assert-Code -Json $delete -Expected 200 -Name 'return order delete with reverse stock and refund'
    Assert-PathEquals -Json $delete -Path 'data.count' -Expected '1' -Name 'return order delete count'

    $afterDeleteDb = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$order = think\facade\Db::name('return_order')->where('ID', '$safeOrderId')->find();
`$activeItemCount = think\facade\Db::name('return_order_item')->where('RETURN_ORDER_ID', '$safeOrderId')->where('DELETE_FLAG', 'NOT_DELETE')->count();
`$project = think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->find();
`$account = think\facade\Db::name('settlement_account')->where('ID', '$safeSettlementAccountId')->find();
`$activeDeliveryCount = think\facade\Db::name('delivery_record')->where('OBJECT_ID', '$safeOrderId')->where('DELETE_FLAG', 'NOT_DELETE')->count();
`$newInventory = think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$safeWarehouseId')->where('PRODUCT_ID', '$safeProductIdB')->find();
`$activeExpenditureCount = think\facade\Db::name('biz_expenditure_record')->where('OBJECT_ID', '$safeOrderId')->where('SETTLEMENT_CATEGORY', 'ReturnAndRefund')->where('DELETE_FLAG', 'NOT_DELETE')->count();
`$activeStatementCount = think\facade\Db::name('settlement_account_statement')->where('ACCOUNT_ID', '$safeSettlementAccountId')->where('SETTLEMENT_CATEGORY', 'ReturnAndRefund')->where('DELETE_FLAG', 'NOT_DELETE')->count();
echo json_encode([
    'order' => `$order,
    'activeItemCount' => `$activeItemCount,
    'project' => `$project,
    'account' => `$account,
    'activeDeliveryCount' => `$activeDeliveryCount,
    'newInventory' => `$newInventory,
    'activeExpenditureCount' => `$activeExpenditureCount,
    'activeStatementCount' => `$activeStatementCount,
], JSON_UNESCAPED_SLASHES);
"@
    if ([string]$afterDeleteDb.order.DELETE_FLAG -ne 'DELETED' -or [int]$afterDeleteDb.activeItemCount -ne 0) {
        throw "return order delete logical delete failed: $($afterDeleteDb | ConvertTo-Json -Compress)"
    }
    if ([decimal]$afterDeleteDb.project.TOTAL_REFUND_AMOUNT -ne [decimal]'0.00' -or [decimal]$afterDeleteDb.project.TOTAL_RETURN_AMOUNT -ne [decimal]'0.00' -or [decimal]$afterDeleteDb.project.TOTAL_PRICE -ne [decimal]'1000.00') {
        throw "return order delete project totals restore failed: $($afterDeleteDb.project | ConvertTo-Json -Compress)"
    }
    if ([decimal]$afterDeleteDb.account.CURRENT_AMOUNT -ne [decimal]'1000.00' -or [int]$afterDeleteDb.activeExpenditureCount -ne 0 -or [int]$afterDeleteDb.activeStatementCount -ne 0) {
        throw "return order delete finance reverse failed: $($afterDeleteDb | ConvertTo-Json -Compress)"
    }
    if ([int]$afterDeleteDb.activeDeliveryCount -ne 0 -or [decimal]$afterDeleteDb.newInventory.CURRENT_COUNT -ne [decimal]'0') {
        throw "return order delete inventory reverse failed: $($afterDeleteDb | ConvertTo-Json -Compress)"
    }

    Write-Host 'return order write HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
