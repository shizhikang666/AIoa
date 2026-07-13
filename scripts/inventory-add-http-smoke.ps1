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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-inventory-add-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
`$auth['device'] = 'CODEX_INVENTORY_ADD_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
$token = Invoke-Php -Code $tokenCode
if ($token.Trim() -eq '') {
    throw 'failed to create local smoke token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'codex-inventory-add-' + ([Guid]::NewGuid().ToString('N').Substring(0, 12))
$safePrefix = $prefix.Replace("'", "\'")
$warehouseId = ''
$productA = ''
$productB = ''
$productExisting = ''
$existingInventoryId = ''

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
`$productA = `$newId();
`$productB = `$newId();
`$productExisting = `$newId();
`$existingInventoryId = `$newId();
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
foreach ([`$productA => 'A', `$productB => 'B', `$productExisting => 'Existing'] as `$productId => `$label) {
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
    'ID' => `$existingInventoryId,
    'WAREHOUSES_ID' => `$warehouseId,
    'PRODUCT_ID' => `$productExisting,
    'CURRENT_COUNT' => 7,
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => null,
    'UPDATE_USER' => null,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 3,
]);
echo json_encode([
    'warehouseId' => `$warehouseId,
    'productA' => `$productA,
    'productB' => `$productB,
    'productExisting' => `$productExisting,
    'existingInventoryId' => `$existingInventoryId,
    'tenantId' => `$tenantId,
    'counts' => [
        'inventory' => think\facade\Db::name('inventory')->count(),
        'delivery' => think\facade\Db::name('delivery_record')->count(),
    ],
], JSON_UNESCAPED_UNICODE);
"@
    $setup = Invoke-PhpJson -Code $setupCode
    $warehouseId = [string]$setup.warehouseId
    $productA = [string]$setup.productA
    $productB = [string]$setup.productB
    $productExisting = [string]$setup.productExisting
    $existingInventoryId = [string]$setup.existingInventoryId

    $noToken = Invoke-RawPostJson -Url ($baseUrl + '/biz/inventory/add') -Data @{
        productIds = @($productA)
        warehousesId = $warehouseId
    }
    Assert-Code -Json $noToken -Expected 401 -Name 'inventory add no-token'

    $missingProducts = Invoke-RawPostJson -Url ($baseUrl + '/biz/inventory/add') -Token $token -Data @{
        warehousesId = $warehouseId
    }
    Assert-Code -Json $missingProducts -Expected 400 -Name 'inventory add missing productIds'

    $duplicateProducts = Invoke-RawPostJson -Url ($baseUrl + '/biz/inventory/add') -Token $token -Data @{
        productIds = @($productA, $productA)
        warehousesId = $warehouseId
    }
    Assert-Code -Json $duplicateProducts -Expected 400 -Name 'inventory add duplicate productIds'

    $missingProduct = Invoke-RawPostJson -Url ($baseUrl + '/biz/inventory/add') -Token $token -Data @{
        productIds = @($productA, 'missing-product-id')
        warehousesId = $warehouseId
    }
    Assert-Code -Json $missingProduct -Expected 400 -Name 'inventory add missing product'

    $afterFailedCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'inventory' => think\facade\Db::name('inventory')->count(),
    'delivery' => think\facade\Db::name('delivery_record')->count(),
], JSON_UNESCAPED_UNICODE);
"@
    $afterFailed = Invoke-PhpJson -Code $afterFailedCode
    Assert-IntEqual -Actual ([int]$afterFailed.inventory) -Expected ([int]$setup.counts.inventory) -Name 'failed inventory add count'
    Assert-IntEqual -Actual ([int]$afterFailed.delivery) -Expected ([int]$setup.counts.delivery) -Name 'failed inventory add delivery count'

    $success = Invoke-RawPostJson -Url ($baseUrl + '/biz/inventory/add') -Token $token -Data @{
        productIds = @($productA, $productB, $productExisting)
        warehousesId = $warehouseId
    }
    Assert-Code -Json $success -Expected 200 -Name 'inventory add success'
    Assert-Equal -Actual (Read-JsonPath -Json $success -Path 'data.count') -Expected '3' -Name 'inventory add count'
    Assert-Equal -Actual (Read-JsonPath -Json $success -Path 'data.inserted') -Expected '2' -Name 'inventory add inserted'
    Assert-Equal -Actual (Read-JsonPath -Json $success -Path 'data.updated') -Expected '1' -Name 'inventory add updated'

    $insertedId = Read-JsonPath -Json $success -Path 'data.ids.0'
    $detail = Invoke-RawGet -Url ($baseUrl + '/biz/inventory/detail?id=' + (Enc $insertedId)) -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'inventory add detail'
    Assert-Equal -Actual (Read-JsonPath -Json $detail -Path 'data.warehousesId') -Expected $warehouseId -Name 'inventory detail warehouse'
    Assert-Equal -Actual (Read-JsonPath -Json $detail -Path 'data.currentCount') -Expected '0' -Name 'inventory detail currentCount'

    $verifyCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$rows = think\facade\Db::name('inventory')
    ->where('WAREHOUSES_ID', '$warehouseId')
    ->whereIn('PRODUCT_ID', ['$productA', '$productB', '$productExisting'])
    ->order('PRODUCT_ID', 'asc')
    ->select()
    ->toArray();
echo json_encode([
    'inventory' => think\facade\Db::name('inventory')->count(),
    'delivery' => think\facade\Db::name('delivery_record')->count(),
    'rows' => `$rows,
], JSON_UNESCAPED_UNICODE);
"@
    $verify = Invoke-PhpJson -Code $verifyCode
    Assert-IntEqual -Actual ([int]$verify.inventory) -Expected ([int]$setup.counts.inventory + 2) -Name 'successful inventory add count'
    Assert-IntEqual -Actual ([int]$verify.delivery) -Expected ([int]$setup.counts.delivery) -Name 'successful inventory add delivery count'
    Assert-IntEqual -Actual ([int]$verify.rows.Count) -Expected 3 -Name 'inventory rows for warehouse'

    foreach ($row in $verify.rows) {
        Assert-Equal -Actual ([string]$row.DELETE_FLAG) -Expected 'NOT_DELETE' -Name 'inventory delete flag'
        Assert-Equal -Actual ([string]$row.TENANT_ID) -Expected ([string]$setup.tenantId) -Name 'inventory tenant'
        if ([string]$row.PRODUCT_ID -eq $productExisting) {
            Assert-Equal -Actual ([string]$row.ID) -Expected $existingInventoryId -Name 'existing inventory id'
            Assert-Equal -Actual ([string]$row.CURRENT_COUNT) -Expected '7' -Name 'existing inventory count unchanged'
            Assert-Equal -Actual ([string]$row.VERSION) -Expected '4' -Name 'existing inventory version increment'
        } else {
            Assert-Equal -Actual ([string]$row.CURRENT_COUNT) -Expected '0' -Name 'new inventory count'
            Assert-Equal -Actual ([string]$row.VERSION) -Expected '0' -Name 'new inventory version'
        }
    }

    Write-Host 'inventory add http smoke passed'
} finally {
    if ($warehouseId -ne '') {
        $safeWarehouseId = $warehouseId.Replace("'", "\'")
        $safeProductA = $productA.Replace("'", "\'")
        $safeProductB = $productB.Replace("'", "\'")
        $safeProductExisting = $productExisting.Replace("'", "\'")
        $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$safeWarehouseId')->delete();
think\facade\Db::name('biz_product')->whereIn('ID', ['$safeProductA', '$safeProductB', '$safeProductExisting'])->delete();
think\facade\Db::name('warehouses')->where('ID', '$safeWarehouseId')->delete();
"@
        Invoke-Php -Code $cleanupCode | Out-Null
    }
}
