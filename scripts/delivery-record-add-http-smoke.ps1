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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-delivery-add-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
`$auth['device'] = 'CODEX_DELIVERY_RECORD_ADD_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
$token = Invoke-Php -Code $tokenCode
if ($token.Trim() -eq '') {
    throw 'failed to create local smoke token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'codex-delivery-add-' + ([Guid]::NewGuid().ToString('N').Substring(0, 12))
$safePrefix = $prefix.Replace("'", "\'")
$warehouseId = ''
$productStock = ''
$productNoInventory = ''

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
`$now = date('Y-m-d H:i:s');
`$newId = function (): string { return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999); };
`$warehouseId = `$newId();
`$productStock = `$newId();
`$productNoInventory = `$newId();
`$inventoryId = `$newId();
`$code = substr(preg_replace('/[^A-Za-z0-9]/', '', '$safePrefix'), 0, 20);
think\facade\Db::name('warehouses')->insert([
    'ID' => `$warehouseId,
    'NAME' => '$safePrefix warehouse',
    'CODE' => `$code,
    'ADDRESS' => 'codex smoke',
    'SORT_CODE' => 999,
    'USER' => `$userId,
    'ORG' => `$orgId !== '' ? `$orgId : null,
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => null,
    'UPDATE_USER' => null,
    'TENANT_ID' => `$tenantId,
]);
foreach ([`$productStock => 'Stock', `$productNoInventory => 'NoInventory'] as `$productId => `$label) {
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
    'ID' => `$inventoryId,
    'WAREHOUSES_ID' => `$warehouseId,
    'PRODUCT_ID' => `$productStock,
    'CURRENT_COUNT' => 7,
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => null,
    'UPDATE_USER' => null,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 5,
]);
echo json_encode([
    'warehouseId' => `$warehouseId,
    'productStock' => `$productStock,
    'productNoInventory' => `$productNoInventory,
    'inventoryId' => `$inventoryId,
    'tenantId' => `$tenantId,
    'counts' => [
        'inventory' => think\facade\Db::name('inventory')->count(),
        'delivery' => think\facade\Db::name('delivery_record')->count(),
    ],
], JSON_UNESCAPED_UNICODE);
"@
    $setup = Invoke-PhpJson -Code $setupCode
    $warehouseId = [string]$setup.warehouseId
    $productStock = [string]$setup.productStock
    $productNoInventory = [string]$setup.productNoInventory
    $inventoryId = [string]$setup.inventoryId

    $noToken = Invoke-RawPostJson -Url ($baseUrl + '/biz/warehouses/delivery/add') -Data @{
        warehousesId = $warehouseId
        productId = $productStock
        amount = 9
        deliveryTime = '2026-06-18 10:00:00'
    }
    Assert-Code -Json $noToken -Expected 401 -Name 'delivery add no-token'

    $missingProduct = Invoke-RawPostJson -Url ($baseUrl + '/biz/warehouses/delivery/add') -Token $token -Data @{
        warehousesId = $warehouseId
        amount = 9
        deliveryTime = '2026-06-18 10:00:00'
    }
    Assert-Code -Json $missingProduct -Expected 400 -Name 'delivery add missing productId'

    $missingInventory = Invoke-RawPostJson -Url ($baseUrl + '/biz/warehouses/delivery/add') -Token $token -Data @{
        warehousesId = $warehouseId
        productId = $productNoInventory
        amount = 9
        deliveryTime = '2026-06-18 10:00:00'
    }
    Assert-Code -Json $missingInventory -Expected 404 -Name 'delivery add missing inventory'

    $afterFailedCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$inventory = think\facade\Db::name('inventory')->where('ID', '$inventoryId')->find();
echo json_encode([
    'inventory' => think\facade\Db::name('inventory')->count(),
    'delivery' => think\facade\Db::name('delivery_record')->count(),
    'currentCount' => (string)`$inventory['CURRENT_COUNT'],
    'version' => (string)`$inventory['VERSION'],
], JSON_UNESCAPED_UNICODE);
"@
    $afterFailed = Invoke-PhpJson -Code $afterFailedCode
    Assert-IntEqual -Actual ([int]$afterFailed.inventory) -Expected ([int]$setup.counts.inventory) -Name 'failed delivery add inventory count'
    Assert-IntEqual -Actual ([int]$afterFailed.delivery) -Expected ([int]$setup.counts.delivery) -Name 'failed delivery add delivery count'
    Assert-Equal -Actual ([string]$afterFailed.currentCount) -Expected '7' -Name 'failed delivery add inventory amount'
    Assert-Equal -Actual ([string]$afterFailed.version) -Expected '5' -Name 'failed delivery add inventory version'

    $inAdd = Invoke-RawPostJson -Url ($baseUrl + '/biz/warehouses/delivery/add') -Token $token -Data @{
        warehousesId = $warehouseId
        productId = $productStock
        amount = 10
        deliveryTime = '2026-06-18 10:00:00'
        remark = 'codex in stocktake'
    }
    Assert-Code -Json $inAdd -Expected 200 -Name 'delivery add IN'
    Assert-Equal -Actual (Read-JsonPath -Json $inAdd -Path 'data.category') -Expected 'IN' -Name 'delivery add IN category'
    Assert-Equal -Actual (Read-JsonPath -Json $inAdd -Path 'data.amount') -Expected '3' -Name 'delivery add IN amount'
    Assert-Equal -Actual (Read-JsonPath -Json $inAdd -Path 'data.currentCount') -Expected '10' -Name 'delivery add IN target'
    $inDeliveryId = Read-JsonPath -Json $inAdd -Path 'data.id'

    $inDetail = Invoke-RawGet -Url ($baseUrl + '/biz/warehouses/delivery/detail?id=' + (Enc $inDeliveryId)) -Token $token
    Assert-Code -Json $inDetail -Expected 200 -Name 'delivery IN detail'
    Assert-Equal -Actual (Read-JsonPath -Json $inDetail -Path 'data.category') -Expected 'IN' -Name 'delivery IN detail category'
    Assert-Equal -Actual (Read-JsonPath -Json $inDetail -Path 'data.processId') -Expected 'Process_sys' -Name 'delivery IN processId'
    Assert-Equal -Actual (Read-JsonPath -Json $inDetail -Path 'data.processCategory') -Expected 'Process_sys' -Name 'delivery IN processCategory'
    Assert-Equal -Actual (Read-JsonPath -Json $inDetail -Path 'data.amount') -Expected '3' -Name 'delivery IN detail amount'

    $outAdd = Invoke-RawPostJson -Url ($baseUrl + '/biz/warehouses/delivery/add') -Token $token -Data @{
        warehousesId = $warehouseId
        productId = $productStock
        amount = 4
        deliveryTime = '2026-06-18 11:00:00'
        remark = 'codex out stocktake'
    }
    Assert-Code -Json $outAdd -Expected 200 -Name 'delivery add OUT'
    Assert-Equal -Actual (Read-JsonPath -Json $outAdd -Path 'data.category') -Expected 'OUT' -Name 'delivery add OUT category'
    Assert-Equal -Actual (Read-JsonPath -Json $outAdd -Path 'data.amount') -Expected '6' -Name 'delivery add OUT amount'
    Assert-Equal -Actual (Read-JsonPath -Json $outAdd -Path 'data.currentCount') -Expected '4' -Name 'delivery add OUT target'

    $noMovement = Invoke-RawPostJson -Url ($baseUrl + '/biz/warehouses/delivery/add') -Token $token -Data @{
        warehousesId = $warehouseId
        productId = $productStock
        amount = 4
        deliveryTime = '2026-06-18 12:00:00'
    }
    Assert-Code -Json $noMovement -Expected 200 -Name 'delivery add no movement'
    Assert-Equal -Actual (Read-JsonPath -Json $noMovement -Path 'data.count') -Expected '0' -Name 'delivery no movement count'
    Assert-Equal -Actual (Read-JsonPath -Json $noMovement -Path 'data.currentCount') -Expected '4' -Name 'delivery no movement target'

    $verifyCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$inventory = think\facade\Db::name('inventory')->where('ID', '$inventoryId')->find();
`$deliveries = think\facade\Db::name('delivery_record')
    ->where('WAREHOUSES_ID', '$warehouseId')
    ->where('PRODUCT_ID', '$productStock')
    ->order('DELIVERY_TIME', 'asc')
    ->select()
    ->toArray();
echo json_encode([
    'inventoryCount' => think\facade\Db::name('inventory')->count(),
    'deliveryCount' => think\facade\Db::name('delivery_record')->count(),
    'inventory' => `$inventory,
    'deliveries' => `$deliveries,
], JSON_UNESCAPED_UNICODE);
"@
    $verify = Invoke-PhpJson -Code $verifyCode
    Assert-IntEqual -Actual ([int]$verify.inventoryCount) -Expected ([int]$setup.counts.inventory) -Name 'delivery add final inventory count'
    Assert-IntEqual -Actual ([int]$verify.deliveryCount) -Expected ([int]$setup.counts.delivery + 2) -Name 'delivery add final delivery count'
    Assert-Equal -Actual ([string]$verify.inventory.CURRENT_COUNT) -Expected '4' -Name 'delivery add final inventory amount'
    Assert-Equal -Actual ([string]$verify.inventory.VERSION) -Expected '8' -Name 'delivery add final inventory version'
    Assert-IntEqual -Actual ([int]$verify.deliveries.Count) -Expected 2 -Name 'delivery add movement row count'
    Assert-Equal -Actual ([string]$verify.deliveries[0].CATEGORY) -Expected 'IN' -Name 'first movement category'
    Assert-Equal -Actual ([string]$verify.deliveries[0].AMOUNT) -Expected '3' -Name 'first movement amount'
    Assert-Equal -Actual ([string]$verify.deliveries[1].CATEGORY) -Expected 'OUT' -Name 'second movement category'
    Assert-Equal -Actual ([string]$verify.deliveries[1].AMOUNT) -Expected '6' -Name 'second movement amount'

    Write-Host 'delivery record add http smoke passed'
} finally {
    if ($warehouseId -ne '') {
        $safeWarehouseId = $warehouseId.Replace("'", "\'")
        $safeProductStock = $productStock.Replace("'", "\'")
        $safeProductNoInventory = $productNoInventory.Replace("'", "\'")
        $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('delivery_record')->where('WAREHOUSES_ID', '$safeWarehouseId')->delete();
think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$safeWarehouseId')->delete();
think\facade\Db::name('biz_product')->whereIn('ID', ['$safeProductStock', '$safeProductNoInventory'])->delete();
think\facade\Db::name('warehouses')->where('ID', '$safeWarehouseId')->delete();
"@
        Invoke-Php -Code $cleanupCode | Out-Null
    }
}
