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

function Invoke-RawPostJson {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)]$Data,
        [string]$Token = ''
    )

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-sale-project-product-item-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    try {
        ConvertTo-Json -InputObject $Data -Depth 16 | Set-Content -LiteralPath $tmp -Encoding UTF8
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
    param([Parameter(Mandatory = $true)][Int64]$Base)

    return [string]($Base + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
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
`$auth['device'] = 'CODEX_SALE_PROJECT_PRODUCT_ITEM_HTTP_SMOKE';
`$tenantId = (string)(`$user['TENANT_ID'] ?? '');
`$orgId = (string)(`$user['ORG_ID'] ?? '');
if (`$orgId === '') {
    `$orgId = (string)think\facade\Db::name('sys_org')
        ->where('TENANT_ID', `$tenantId)
        ->where(function (`$query): void { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })
        ->value('ID');
}
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => (string)`$user['ID'],
    'tenantId' => `$tenantId,
    'orgId' => `$orgId
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
$prefix = 'CODEX_SP_ITEM_' + (Get-Date -Format 'MMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
$customerId = New-SmokeId -Base 605100000000000000
$productAId = New-SmokeId -Base 605101000000000000
$productBId = New-SmokeId -Base 605102000000000000
$kitProductId = New-SmokeId -Base 605103000000000000
$childProductId = New-SmokeId -Base 605104000000000000
$missingProductId = New-SmokeId -Base 605198000000000000
$returnOrderItemId = New-SmokeId -Base 605199000000000000
$returnOrderId = New-SmokeId -Base 605197000000000000

$safePrefix = $prefix.Replace("'", "\'")
$safeCustomerId = $customerId.Replace("'", "\'")
$safeProductAId = $productAId.Replace("'", "\'")
$safeProductBId = $productBId.Replace("'", "\'")
$safeKitProductId = $kitProductId.Replace("'", "\'")
$safeChildProductId = $childProductId.Replace("'", "\'")
$safeReturnOrderItemId = $returnOrderItemId.Replace("'", "\'")
$safeReturnOrderId = $returnOrderId.Replace("'", "\'")
$safeUserId = $userId.Replace("'", "\'")
$safeTenantId = $tenantId.Replace("'", "\'")
$safeOrgId = $orgId.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$projectIds = think\facade\Db::name('biz_sale_project')->whereLike('PROJECT_NAME', '$safePrefix%')->column('ID');
`$projectIds = array_values(array_filter(array_map('strval', `$projectIds)));
if (`$projectIds !== []) {
    `$itemIds = think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->column('ID');
    `$itemIds = array_values(array_filter(array_map('strval', `$itemIds)));
    if (`$itemIds !== []) {
        think\facade\Db::name('sale_project_product_item_relation')->whereIn('OBJECT_ID', `$itemIds)->delete();
    }
    think\facade\Db::name('return_order_item')->whereIn('PROJECT_PRODUCT_ITEM_ID', `$itemIds)->delete();
    think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->delete();
}
think\facade\Db::name('return_order_item')->where('ID', '$safeReturnOrderItemId')->delete();
think\facade\Db::name('product_relation')->where('OBJECT_ID', '$safeKitProductId')->delete();
think\facade\Db::name('biz_product')->whereIn('ID', ['$safeProductAId', '$safeProductBId', '$safeKitProductId', '$safeChildProductId'])->delete();
think\facade\Db::name('customer')->where('ID', '$safeCustomerId')->delete();
"@

Invoke-Php -Code $cleanupCode | Out-Null

try {
    $insertCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('customer')->insert([
    'ID' => '$safeCustomerId',
    'NAME' => '$safePrefix customer',
    'CUSTOM_TYPE' => 'OLD',
    'ORG' => '$safeOrgId',
    'USER' => '$safeUserId',
    'STATUS' => 'ENABLE',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => '$safeUserId',
    'TENANT_ID' => '$safeTenantId',
    'VERSION' => 0,
    'DEAL_AMOUNT' => 0,
]);
`$products = [
    ['$safeProductAId', '$safePrefix product A', 'SINGLE_PRODUCT', '100.00', '80.00', '10.00'],
    ['$safeProductBId', '$safePrefix product B', 'SINGLE_PRODUCT', '50.00', '40.00', '12.00'],
    ['$safeChildProductId', '$safePrefix child product', 'SINGLE_PRODUCT', '20.00', '15.00', '4.00'],
    ['$safeKitProductId', '$safePrefix kit product', 'KIT_PRODUCT', '300.00', '240.00', '30.00'],
];
foreach (`$products as `$product) {
    think\facade\Db::name('biz_product')->insert([
        'ID' => `$product[0],
        'PRODUCT_NAME' => `$product[1],
        'PRODUCT_CATEGORY' => 'DEFAULT',
        'SAFETY_STOCK' => 0,
        'PURCHASE_PRICE' => `$product[5],
        'SALE_PRICE' => `$product[3],
        'MIN_PRICE' => `$product[4],
        'CATEGORY' => `$product[2],
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => '$safeUserId',
        'TENANT_ID' => '$safeTenantId',
        'SPECS' => 'smoke',
        'ORG' => '$safeOrgId',
        'status' => 'ENABLE',
    ]);
}
`$child = think\facade\Db::name('biz_product')->where('ID', '$safeChildProductId')->find();
think\facade\Db::name('product_relation')->insert([
    'ID' => (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999),
    'OBJECT_ID' => '$safeKitProductId',
    'TARGET_ID' => '$safeChildProductId',
    'CATEGORY' => 'KIT_PRODUCT_DATA',
    'EXT_JSON' => json_encode([
        'number' => 3,
        'product' => [
            'id' => `$child['ID'],
            'productName' => `$child['PRODUCT_NAME'],
            'productCategory' => `$child['PRODUCT_CATEGORY'],
            'category' => `$child['CATEGORY'],
            'specs' => `$child['SPECS'],
            'purchasePrice' => `$child['PURCHASE_PRICE'],
            'salePrice' => `$child['SALE_PRICE'],
            'minPrice' => `$child['MIN_PRICE'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    'TENANT_ID' => '$safeTenantId',
]);
echo json_encode(['ok' => 1], JSON_UNESCAPED_SLASHES);
"@
    Invoke-PhpJson -Code $insertCode | Out-Null

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/add" -Data @{
        customer = $customerId
        projectName = "$prefix no token"
        projectCategory = 'DEFAULT'
    }
    Assert-Code -Json $noToken -Expected 401 -Name 'sale project product item add without token'

    $missingProduct = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/add" -Token $token -Data @{
        customer = $customerId
        projectName = "$prefix missing product rollback"
        projectCategory = 'DEFAULT'
        productList = @(
            @{ productId = $missingProductId; number = 1; unitPrice = '1.00'; discountRate = '0'; price = '1.00' }
        )
    }
    Assert-Code -Json $missingProduct -Expected 404 -Name 'sale project add missing product rollback'

    $add = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/add" -Token $token -Data @{
        customer = $customerId
        projectName = "$prefix add"
        projectCategory = 'DEFAULT'
        remark = 'with products'
        productList = @(
            @{ productId = $productAId; number = 2; unitPrice = '100.00'; discountRate = '10'; price = '180.00'; remark = 'line A' },
            @{ productId = $kitProductId; number = 1; unitPrice = '300.00'; discountRate = '0'; price = '300.00'; remark = 'kit without explicit children' }
        )
    }
    Assert-Code -Json $add -Expected 200 -Name 'sale project add with productList'
    $projectId = Read-JsonPath -Json $add -Path 'data.id'
    $safeProjectId = $projectId.Replace("'", "\'")

    $detail = Invoke-RawGet -Url "$baseUrl/biz/saleproject/detail?id=$projectId" -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'sale project detail after add productList'
    $productEndpoint = Invoke-RawGet -Url "$baseUrl/biz/saleproject/product?id=$projectId" -Token $token
    Assert-Code -Json $productEndpoint -Expected 200 -Name 'sale project product endpoint after add productList'

    $stateAfterAddCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$project = think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->find();
`$items = think\facade\Db::name('biz_sale_project_product_item')
    ->where('PROJECT_ID', '$safeProjectId')
    ->where(function (`$query): void { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->order('ID', 'asc')
    ->select()->toArray();
`$itemIds = array_column(`$items, 'ID');
`$relations = `$itemIds === [] ? [] : think\facade\Db::name('sale_project_product_item_relation')
    ->whereIn('OBJECT_ID', `$itemIds)
    ->where(function (`$query): void { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->select()->toArray();
`$byProduct = [];
foreach (`$items as `$item) { `$byProduct[(string)`$item['PRODUCT_ID']] = `$item; }
echo json_encode([
    'project' => `$project,
    'itemCount' => count(`$items),
    'relationCount' => count(`$relations),
    'productAItemId' => `$byProduct['$safeProductAId']['ID'] ?? null,
    'kitItemId' => `$byProduct['$safeKitProductId']['ID'] ?? null,
    'productAItem' => `$byProduct['$safeProductAId'] ?? null,
    'kitRelationTarget' => `$relations[0]['TARGET_ID'] ?? null,
    'kitRelationNumber' => `$relations[0]['NUMBER'] ?? null,
], JSON_UNESCAPED_SLASHES);
"@
    $stateAfterAdd = Invoke-PhpJson -Code $stateAfterAddCode
    if ([int]$stateAfterAdd.itemCount -ne 2 -or [int]$stateAfterAdd.relationCount -ne 1) {
        throw 'sale project add did not create expected product items and kit relation'
    }
    if ([string]$stateAfterAdd.project.PROJECT_STATE -ne 'FOLLOW' -or [string]$stateAfterAdd.project.INIT_PRICE -ne '0.00') {
        throw 'sale project add productList changed protected project state or amount'
    }
    if ([string]$stateAfterAdd.productAItem.STATE -ne 'WAIT_DELIVER' -or [string]$stateAfterAdd.productAItem.CATEGORY -ne 'INIT' -or [string]$stateAfterAdd.productAItem.DELIVERY -ne '0') {
        throw 'sale project add did not initialize product item state fields'
    }
    if ([string]$stateAfterAdd.kitRelationTarget -ne $childProductId -or [string]$stateAfterAdd.kitRelationNumber -ne '3') {
        throw 'sale project add did not hydrate kit product child relation'
    }

    $productAItemId = [string]$stateAfterAdd.productAItemId

    $edit = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/edit" -Token $token -Data @{
        id = $projectId
        projectName = "$prefix edited"
        productList = @(
            @{ id = $productAItemId; productId = $productAId; number = 3; unitPrice = '100.00'; discountRate = '0'; price = '300.00'; remark = 'line A updated' },
            @{ productId = $productBId; number = 4; unitPrice = '50.00'; discountRate = '0'; price = '200.00'; remark = 'line B new' }
        )
    }
    Assert-Code -Json $edit -Expected 200 -Name 'sale project edit replaces productList'

    $stateAfterEditCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$items = think\facade\Db::name('biz_sale_project_product_item')
    ->where('PROJECT_ID', '$safeProjectId')
    ->where(function (`$query): void { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->order('ID', 'asc')
    ->select()->toArray();
`$itemIds = array_column(`$items, 'ID');
`$activeRelations = `$itemIds === [] ? [] : think\facade\Db::name('sale_project_product_item_relation')
    ->whereIn('OBJECT_ID', `$itemIds)
    ->where(function (`$query): void { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->select()->toArray();
`$byProduct = [];
foreach (`$items as `$item) { `$byProduct[(string)`$item['PRODUCT_ID']] = `$item; }
echo json_encode([
    'itemCount' => count(`$items),
    'relationCount' => count(`$activeRelations),
    'productAItemId' => `$byProduct['$safeProductAId']['ID'] ?? null,
    'productANumber' => `$byProduct['$safeProductAId']['NUMBER'] ?? null,
    'productBItemId' => `$byProduct['$safeProductBId']['ID'] ?? null,
    'kitActive' => isset(`$byProduct['$safeKitProductId']),
], JSON_UNESCAPED_SLASHES);
"@
    $stateAfterEdit = Invoke-PhpJson -Code $stateAfterEditCode
    if ([int]$stateAfterEdit.itemCount -ne 2 -or [int]$stateAfterEdit.relationCount -ne 0) {
        throw 'sale project edit did not replace active product item set'
    }
    if ([string]$stateAfterEdit.productAItemId -ne $productAItemId -or [string]$stateAfterEdit.productANumber -ne '3') {
        throw 'sale project edit did not update the existing product item'
    }
    if ([string]$stateAfterEdit.productBItemId -eq '' -or [bool]$stateAfterEdit.kitActive) {
        throw 'sale project edit did not add/remove expected product rows'
    }

    $nullEdit = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/edit" -Token $token -Data @{
        id = $projectId
        projectCode = "$prefix-null-preserve"
        productList = $null
    }
    Assert-Code -Json $nullEdit -Expected 200 -Name 'sale project edit productList null preserves items'
    $nullState = Invoke-PhpJson -Code $stateAfterEditCode
    if ([int]$nullState.itemCount -ne 2) {
        throw 'sale project edit productList null did not preserve active product items'
    }

    $safeProductAItemId = $productAItemId.Replace("'", "\'")
    $insertReferenceCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('return_order_item')->insert([
    'ID' => '$safeReturnOrderItemId',
    'RETURN_ORDER_ID' => '$safeReturnOrderId',
    'PROJECT_PRODUCT_ITEM_ID' => '$safeProductAItemId',
    'AMOUNT' => '1.00',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => date('Y-m-d H:i:s'),
    'CREATE_USER' => '$safeUserId',
    'TENANT_ID' => '$safeTenantId',
]);
echo json_encode(['ok' => 1], JSON_UNESCAPED_SLASHES);
"@
    Invoke-PhpJson -Code $insertReferenceCode | Out-Null

    $blockedClear = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/edit" -Token $token -Data @{
        id = $projectId
        productList = @()
    }
    Assert-Code -Json $blockedClear -Expected 400 -Name 'sale project edit blocks referenced product item deletion'
    $blockedState = Invoke-PhpJson -Code $stateAfterEditCode
    if ([int]$blockedState.itemCount -ne 2) {
        throw 'sale project referenced product item deletion did not rollback'
    }

    Invoke-Php -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('return_order_item')->where('ID', '$safeReturnOrderItemId')->delete();
"@ | Out-Null

    $clear = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/edit" -Token $token -Data @{
        id = $projectId
        productList = @()
    }
    Assert-Code -Json $clear -Expected 200 -Name 'sale project edit clears productList'
    $clearState = Invoke-PhpJson -Code $stateAfterEditCode
    if ([int]$clearState.itemCount -ne 0) {
        throw 'sale project edit did not clear active product items'
    }

    Write-Host 'sale-project-product-item-mutation-http-smoke OK'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
