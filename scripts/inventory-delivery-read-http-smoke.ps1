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
        [Parameter(Mandatory = $true)][string]$Token
    )

    $raw = & curl.exe -sS -X GET $Url -H "Authorization: Bearer $Token"
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP GET failed: $Url"
    }

    return [string]::Join('', [string[]]$raw)
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

function Assert-Ok {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $code = Read-JsonPath -Json $Json -Path 'code'
    if ([int]$code -ne 200) {
        throw "$Name returned code=$code"
    }
}

function Assert-Paths {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string[]]$Paths
    )

    foreach ($path in $Paths) {
        [void](Read-JsonPath -Json $Json -Path $path)
    }
}

function Has-Path {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path
    )

    return $null -ne (Read-JsonPath -Json $Json -Path $Path -Optional)
}

function Assert-PagedShape {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data.records', 'data.total', 'data.current', 'data.size', 'data.pages')
}

function Assert-FirstRecordIfPresent {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string[]]$Keys
    )

    if (-not (Has-Path -Json $Json -Path 'data.records.0')) {
        return
    }

    foreach ($key in $Keys) {
        [void](Read-JsonPath -Json $Json -Path "data.records.0.$key")
    }
}

function Assert-InventoryRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.warehousesId",
        "$Prefix.productId",
        "$Prefix.currentCount",
        "$Prefix.productName",
        "$Prefix.productCategory",
        "$Prefix.safetyStock",
        "$Prefix.purchasePrice",
        "$Prefix.salePrice",
        "$Prefix.minPrice",
        "$Prefix.category",
        "$Prefix.specs",
        "$Prefix.inventory.id",
        "$Prefix.inventory.warehousesId",
        "$Prefix.inventory.productId",
        "$Prefix.inventory.currentCount"
    )
}

function Assert-DeliveryRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.warehousesId",
        "$Prefix.warehousesName",
        "$Prefix.processId",
        "$Prefix.productId",
        "$Prefix.productName",
        "$Prefix.amount",
        "$Prefix.category",
        "$Prefix.processCategory",
        "$Prefix.operator",
        "$Prefix.operatorName",
        "$Prefix.deliveryTime",
        "$Prefix.productCategory",
        "$Prefix.safetyStock",
        "$Prefix.specs",
        "$Prefix.minPrice",
        "$Prefix.salePrice",
        "$Prefix.purchasePrice"
    )
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
`$auth['device'] = 'CODEX_INVENTORY_DELIVERY_READ_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$sampleCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$tenantId = trim((string)(`$auth['tenant_id'] ?? ''));
`$notDeleted = function (`$query, string `$alias = '') {
    `$prefix = `$alias === '' ? '' : `$alias . '.';
    `$query->whereNull(`$prefix . 'DELETE_FLAG')->whereOr(`$prefix . 'DELETE_FLAG', '=', 'NOT_DELETE');
};
`$warehouseQuery = think\facade\Db::name('warehouses')
    ->where(function (`$query) use (`$notDeleted) { `$notDeleted(`$query); })
    ->field('ID, ORG');
if (`$tenantId !== '') { `$warehouseQuery->where('TENANT_ID', `$tenantId); }
`$warehouse = `$warehouseQuery->find();
`$inventoryQuery = think\facade\Db::name('inventory')
    ->alias('i')
    ->join('biz_product p', 'p.ID = i.PRODUCT_ID', 'INNER')
    ->where(function (`$query) use (`$notDeleted) { `$notDeleted(`$query, 'i'); })
    ->where(function (`$query) use (`$notDeleted) { `$notDeleted(`$query, 'p'); })
    ->where('p.status', 'ENABLE')
    ->field('i.ID AS ID, i.WAREHOUSES_ID AS WAREHOUSES_ID');
if (`$tenantId !== '') { `$inventoryQuery->where('i.TENANT_ID', `$tenantId)->where('p.TENANT_ID', `$tenantId); }
`$inventory = `$inventoryQuery->find();
`$deliveryQuery = think\facade\Db::name('delivery_record')
    ->alias('d')
    ->leftJoin('biz_product p', 'p.ID = d.PRODUCT_ID')
    ->where(function (`$query) use (`$notDeleted) { `$notDeleted(`$query, 'd'); })
    ->field('d.ID AS ID, d.WAREHOUSES_ID AS WAREHOUSES_ID, p.ORG AS PRODUCT_ORG');
if (`$tenantId !== '') { `$deliveryQuery->where('d.TENANT_ID', `$tenantId); }
`$delivery = `$deliveryQuery->find();
echo json_encode([
    'warehouseId' => `$warehouse ? (string)`$warehouse['ID'] : '',
    'warehouseOrg' => `$warehouse ? (string)(`$warehouse['ORG'] ?? '') : '',
    'inventoryId' => `$inventory ? (string)`$inventory['ID'] : '',
    'inventoryWarehouseId' => `$inventory ? (string)`$inventory['WAREHOUSES_ID'] : '',
    'deliveryId' => `$delivery ? (string)`$delivery['ID'] : '',
    'deliveryWarehouseId' => `$delivery ? (string)`$delivery['WAREHOUSES_ID'] : '',
    'deliveryProductOrg' => `$delivery ? (string)(`$delivery['PRODUCT_ORG'] ?? '') : '',
], JSON_UNESCAPED_UNICODE);
"@

$sampleJson = & php -r $sampleCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($sampleJson)) {
    throw 'failed to load inventory/delivery sample ids'
}
$sampleJson = ([string]$sampleJson) -replace ([string][char]0xFEFF), ''

$warehouseId = [string](Read-JsonPath -Json $sampleJson -Path 'warehouseId')
$warehouseOrg = [string](Read-JsonPath -Json $sampleJson -Path 'warehouseOrg')
$inventoryWarehouseId = [string](Read-JsonPath -Json $sampleJson -Path 'inventoryWarehouseId')
$deliveryWarehouseId = [string](Read-JsonPath -Json $sampleJson -Path 'deliveryWarehouseId')
$deliveryProductOrg = [string](Read-JsonPath -Json $sampleJson -Path 'deliveryProductOrg')

$inventoryQueryWarehouseId = if ($inventoryWarehouseId.Trim() -ne '') { $inventoryWarehouseId } else { $warehouseId }
if ($inventoryQueryWarehouseId.Trim() -eq '') {
    throw 'inventory/delivery smoke requires at least one active warehouse'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$encodedInventoryWarehouseId = [System.Uri]::EscapeDataString($inventoryQueryWarehouseId.Trim())

$inventoryPage = Invoke-RawGet -Url "$baseUrl/biz/inventory/page?warehousesId=$encodedInventoryWarehouseId&current=1&size=1" -Token $token
Assert-PagedShape -Json $inventoryPage -Name 'biz inventory page'
Assert-FirstRecordIfPresent -Json $inventoryPage -Name 'biz inventory page' -Keys @(
    'id',
    'warehousesId',
    'productId',
    'currentCount',
    'productName',
    'productCategory',
    'safetyStock',
    'purchasePrice',
    'salePrice',
    'minPrice',
    'category',
    'specs',
    'inventory'
)

$inventoryList = Invoke-RawGet -Url "$baseUrl/biz/inventory/list?warehousesId=$encodedInventoryWarehouseId" -Token $token
Assert-Ok -Json $inventoryList -Name 'biz inventory list'
Assert-Paths -Json $inventoryList -Name 'biz inventory list' -Paths @('data')
if (Has-Path -Json $inventoryList -Path 'data.0') {
    Assert-InventoryRow -Json $inventoryList -Prefix 'data.0' -Name 'biz inventory list first row'
}

$inventoryPageFirstId = [string](Read-JsonPath -Json $inventoryPage -Path 'data.records.0.id' -Optional)
if ($inventoryPageFirstId.Trim() -ne '') {
    $encodedInventoryId = [System.Uri]::EscapeDataString($inventoryPageFirstId.Trim())
    $inventoryDetail = Invoke-RawGet -Url "$baseUrl/biz/inventory/detail?id=$encodedInventoryId" -Token $token
    Assert-Ok -Json $inventoryDetail -Name 'biz inventory detail'
    Assert-InventoryRow -Json $inventoryDetail -Prefix 'data' -Name 'biz inventory detail'
}

$deliveryPage = Invoke-RawGet -Url "$baseUrl/biz/warehouses/delivery/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $deliveryPage -Name 'biz warehouses delivery page'
Assert-FirstRecordIfPresent -Json $deliveryPage -Name 'biz warehouses delivery page' -Keys @(
    'id',
    'warehousesId',
    'warehousesName',
    'processId',
    'productId',
    'productName',
    'amount',
    'category',
    'processCategory',
    'operator',
    'operatorName',
    'deliveryTime'
)

$deliveryPageFirstId = [string](Read-JsonPath -Json $deliveryPage -Path 'data.records.0.id' -Optional)
if ($deliveryPageFirstId.Trim() -ne '') {
    $encodedDeliveryId = [System.Uri]::EscapeDataString($deliveryPageFirstId.Trim())
    $deliveryDetail = Invoke-RawGet -Url "$baseUrl/biz/warehouses/delivery/detail?id=$encodedDeliveryId" -Token $token
    Assert-Ok -Json $deliveryDetail -Name 'biz warehouses delivery detail'
    Assert-DeliveryRow -Json $deliveryDetail -Prefix 'data' -Name 'biz warehouses delivery detail'
}

$deliveryExportWarehouseId = if ($deliveryWarehouseId.Trim() -ne '') { $deliveryWarehouseId } else { $warehouseId }
$deliveryExportOrgId = if ($deliveryProductOrg.Trim() -ne '') { $deliveryProductOrg } else { $warehouseOrg }
if ($deliveryExportWarehouseId.Trim() -ne '' -and $deliveryExportOrgId.Trim() -ne '') {
    $encodedDeliveryExportWarehouseId = [System.Uri]::EscapeDataString($deliveryExportWarehouseId.Trim())
    $encodedDeliveryExportOrgId = [System.Uri]::EscapeDataString($deliveryExportOrgId.Trim())
    $deliveryExport = Invoke-RawGet -Url "$baseUrl/biz/warehouses/delivery/exportOtherCompanyRecordsList?warehousesId=$encodedDeliveryExportWarehouseId&orgId=$encodedDeliveryExportOrgId" -Token $token
    Assert-Ok -Json $deliveryExport -Name 'biz warehouses delivery exportOtherCompanyRecordsList'
    Assert-Paths -Json $deliveryExport -Name 'biz warehouses delivery exportOtherCompanyRecordsList' -Paths @('data')
    if (Has-Path -Json $deliveryExport -Path 'data.0') {
        Assert-DeliveryRow -Json $deliveryExport -Prefix 'data.0' -Name 'biz warehouses delivery export first row'
    }
}

Write-Host 'inventory/delivery read HTTP smoke passed'
