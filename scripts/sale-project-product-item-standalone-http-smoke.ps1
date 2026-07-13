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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-sale-project-product-item-standalone-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
        [Parameter(Mandatory = $true)][string]$Path
    )

    $value = $Json | node (Join-Path $PSScriptRoot 'json-read.js') $Path
    if ($LASTEXITCODE -ne 0) {
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

function Assert-SideEffectsUnchanged {
    param(
        [Parameter(Mandatory = $true)]$Before,
        [Parameter(Mandatory = $true)]$After
    )

    foreach ($key in @('delivery', 'inventory', 'invoiceItem', 'payment', 'expenditure', 'statement', 'ruTask', 'hiProc')) {
        if ([string]$Before.$key -ne [string]$After.$key) {
            throw "side effect count changed for $key before=$($Before.$key) after=$($After.$key)"
        }
    }
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
`$auth['device'] = 'CODEX_SALE_PROJECT_PRODUCT_ITEM_STANDALONE_HTTP_SMOKE';
`$tenantId = (string)(`$user['TENANT_ID'] ?? '1');
if (`$tenantId === '') { `$tenantId = '1'; }
`$orgId = (string)(`$user['ORG_ID'] ?? '');
if (`$orgId === '') {
    `$orgId = (string)(think\facade\Db::name('sys_org')->where(function (`$query): void {
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
$prefix = 'CODEX_SPITEM_DIRECT_' + (Get-Date -Format 'MMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
$customerId = New-SmokeId -Prefix 'SPIC'
$productAId = New-SmokeId -Prefix 'SPIA'
$productBId = New-SmokeId -Prefix 'SPIB'
$kitProductId = New-SmokeId -Prefix 'SPIK'
$childProductId = New-SmokeId -Prefix 'SPIH'
$licenseFileId = New-SmokeId -Prefix 'SPIF'
$missingProductId = New-SmokeId -Prefix 'SPIM'
$relationId = New-SmokeId -Prefix 'SPIR'
$returnOrderItemId = New-SmokeId -Prefix 'SPIO'
$returnOrderId = New-SmokeId -Prefix 'SPIR'

$safePrefix = $prefix.Replace("'", "\'")
$safeCustomerId = $customerId.Replace("'", "\'")
$safeProductAId = $productAId.Replace("'", "\'")
$safeProductBId = $productBId.Replace("'", "\'")
$safeKitProductId = $kitProductId.Replace("'", "\'")
$safeChildProductId = $childProductId.Replace("'", "\'")
$safeLicenseFileId = $licenseFileId.Replace("'", "\'")
$safeMissingProductId = $missingProductId.Replace("'", "\'")
$safeRelationId = $relationId.Replace("'", "\'")
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
        think\facade\Db::name('return_order_item')->whereIn('PROJECT_PRODUCT_ITEM_ID', `$itemIds)->delete();
    }
    think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->delete();
}
think\facade\Db::name('return_order_item')->where('ID', '$safeReturnOrderItemId')->delete();
think\facade\Db::name('product_relation')->where('ID', '$safeRelationId')->delete();
think\facade\Db::name('biz_product')->whereIn('ID', ['$safeProductAId', '$safeProductBId', '$safeKitProductId', '$safeChildProductId'])->delete();
think\facade\Db::name('customer')->where('ID', '$safeCustomerId')->delete();
think\facade\Db::name('dev_file')->where('ID', '$safeLicenseFileId')->delete();
"@

$snapshotCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'delivery' => think\facade\Db::name('delivery_record')->count(),
    'inventory' => think\facade\Db::name('inventory')->count(),
    'invoiceItem' => think\facade\Db::name('biz_sale_project_invoice_item')->count(),
    'payment' => think\facade\Db::name('biz_payment_record')->count(),
    'expenditure' => think\facade\Db::name('biz_expenditure_record')->count(),
    'statement' => think\facade\Db::name('settlement_account_statement')->count(),
    'ruTask' => think\facade\Db::name('act_ru_task')->count(),
    'hiProc' => think\facade\Db::name('act_hi_procinst')->count(),
], JSON_UNESCAPED_SLASHES);
"@

Invoke-Php -Code $cleanupCode | Out-Null

try {
    $setupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('dev_file')->insert([
    'ID' => '$safeLicenseFileId',
    'ENGINE' => 'LOCAL',
    'BUCKET' => 'defaultBucketName',
    'NAME' => '$safePrefix-license.txt',
    'SUFFIX' => 'txt',
    'SIZE_KB' => 1,
    'SIZE_INFO' => '1KB',
    'OBJ_NAME' => '$safePrefix-license.txt',
    'STORAGE_PATH' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . '$safePrefix-license.txt',
    'DOWNLOAD_PATH' => '/backend/dev/file/download?id=$safeLicenseFileId',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => '$safeUserId',
    'TENANT_ID' => '$safeTenantId',
]);
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
    ['$safeProductAId', '$safePrefix product A', 'SINGLE_PRODUCT', '120.00', '100.00', '50.00'],
    ['$safeProductBId', '$safePrefix product B', 'SINGLE_PRODUCT', '80.00', '70.00', '30.00'],
    ['$safeChildProductId', '$safePrefix child product', 'SINGLE_PRODUCT', '20.00', '15.00', '5.00'],
    ['$safeKitProductId', '$safePrefix kit product', 'KIT_PRODUCT', '300.00', '260.00', '90.00'],
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
        'SPECS' => 'standalone-smoke',
        'ORG' => '$safeOrgId',
        'status' => 'ENABLE',
    ]);
}
`$child = think\facade\Db::name('biz_product')->where('ID', '$safeChildProductId')->find();
think\facade\Db::name('product_relation')->insert([
    'ID' => '$safeRelationId',
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
    Invoke-PhpJson -Code $setupCode | Out-Null
    $beforeSideEffects = Invoke-PhpJson -Code $snapshotCode

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectproductitem/add" -Data @{
        projectId = 'missing-project'
        productId = $productAId
        number = 1
        unitPrice = '1.00'
        discountRate = '0'
        price = '1.00'
    }
    Assert-Code -Json $noToken -Expected 401 -Name 'standalone product item add without token'

    $projectAdd = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/add" -Token $token -Data @{
        customer = $customerId
        projectName = "$prefix project"
        projectCategory = 'DEFAULT'
        businessLicenseFileId = $licenseFileId
    }
    Assert-Code -Json $projectAdd -Expected 200 -Name 'sale project add for standalone product item smoke'
    $projectId = Read-JsonPath -Json $projectAdd -Path 'data.id'
    $safeProjectId = $projectId.Replace("'", "\'")

    $missingProduct = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectproductitem/add" -Token $token -Data @{
        projectId = $projectId
        productId = $missingProductId
        number = 1
        unitPrice = '1.00'
        discountRate = '0'
        price = '1.00'
    }
    Assert-Code -Json $missingProduct -Expected 404 -Name 'standalone product item add missing product'

    $addKit = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectproductitem/add" -Token $token -Data @{
        projectId = $projectId
        productId = $kitProductId
        number = 2
        unitPrice = '300.00'
        discountRate = '0'
        price = '600.00'
        remark = 'direct kit'
        children = @(
            @{ productId = $childProductId; number = 3; remark = 'direct child'; mark = 'child mark' }
        )
    }
    Assert-Code -Json $addKit -Expected 200 -Name 'standalone product item add kit'
    $kitItemId = Read-JsonPath -Json $addKit -Path 'data.id'
    Assert-Equal -Actual (Read-JsonPath -Json $addKit -Path 'data.relationCount') -Expected '1' -Name 'standalone product item add relation count'
    $safeKitItemId = $kitItemId.Replace("'", "\'")

    $stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$items = think\facade\Db::name('biz_sale_project_product_item')
    ->where('PROJECT_ID', '$safeProjectId')
    ->where(function (`$query): void { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->order('ID', 'asc')
    ->select()->toArray();
`$itemIds = array_column(`$items, 'ID');
`$relations = `$itemIds === [] ? [] : think\facade\Db::name('sale_project_product_item_relation')
    ->whereIn('OBJECT_ID', `$itemIds)
    ->where(function (`$query): void { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->order('ID', 'asc')
    ->select()->toArray();
`$byProduct = [];
foreach (`$items as `$item) { `$byProduct[(string)`$item['PRODUCT_ID']] = `$item; }
echo json_encode([
    'itemCount' => count(`$items),
    'relationCount' => count(`$relations),
    'kitItem' => `$byProduct['$safeKitProductId'] ?? null,
    'productBItem' => `$byProduct['$safeProductBId'] ?? null,
    'firstRelation' => `$relations[0] ?? null,
], JSON_UNESCAPED_SLASHES);
"@
    $stateAfterAdd = Invoke-PhpJson -Code $stateCode
    Assert-IntEqual -Actual ([int]$stateAfterAdd.itemCount) -Expected 1 -Name 'standalone add active item count'
    Assert-IntEqual -Actual ([int]$stateAfterAdd.relationCount) -Expected 1 -Name 'standalone add active relation count'
    Assert-Equal -Actual ([string]$stateAfterAdd.kitItem.STATE) -Expected 'WAIT_DELIVER' -Name 'standalone add item state'
    Assert-Equal -Actual ([string]$stateAfterAdd.kitItem.CATEGORY) -Expected 'INIT' -Name 'standalone add item category'
    Assert-Equal -Actual ([string]$stateAfterAdd.kitItem.DELIVERY) -Expected '0' -Name 'standalone add item delivery'
    Assert-Equal -Actual ([string]$stateAfterAdd.firstRelation.TARGET_ID) -Expected $childProductId -Name 'standalone add child target'
    Assert-Equal -Actual ([string]$stateAfterAdd.firstRelation.MARK) -Expected 'child mark' -Name 'standalone add child mark'

    $editKit = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectproductitem/edit" -Token $token -Data @{
        id = $kitItemId
        projectId = $projectId
        productId = $kitProductId
        number = 3
        price = '900.00'
        remark = 'direct kit edited without children'
    }
    Assert-Code -Json $editKit -Expected 200 -Name 'standalone product item edit preserves children'
    $stateAfterEdit = Invoke-PhpJson -Code $stateCode
    Assert-IntEqual -Actual ([int]$stateAfterEdit.itemCount) -Expected 1 -Name 'standalone edit active item count'
    Assert-IntEqual -Actual ([int]$stateAfterEdit.relationCount) -Expected 1 -Name 'standalone edit preserves relation count'
    Assert-Equal -Actual ([string]$stateAfterEdit.kitItem.NUMBER) -Expected '3' -Name 'standalone edit number'
    Assert-Equal -Actual ([string]$stateAfterEdit.kitItem.PRICE) -Expected '900.00' -Name 'standalone edit price'
    Assert-Equal -Actual ([string]$stateAfterEdit.firstRelation.TARGET_ID) -Expected $childProductId -Name 'standalone edit preserved child target'

    $addSingle = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectproductitem/add" -Token $token -Data @{
        projectId = $projectId
        productId = $productBId
        number = 1
        unitPrice = '80.00'
        discountRate = '0'
        price = '80.00'
        remark = 'direct single'
    }
    Assert-Code -Json $addSingle -Expected 200 -Name 'standalone product item add single'
    $productBItemId = Read-JsonPath -Json $addSingle -Path 'data.id'

    $insertReferenceCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('return_order_item')->insert([
    'ID' => '$safeReturnOrderItemId',
    'RETURN_ORDER_ID' => '$safeReturnOrderId',
    'PROJECT_PRODUCT_ITEM_ID' => '$safeKitItemId',
    'AMOUNT' => '1.00',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => date('Y-m-d H:i:s'),
    'CREATE_USER' => '$safeUserId',
    'TENANT_ID' => '$safeTenantId',
]);
echo json_encode(['ok' => 1], JSON_UNESCAPED_SLASHES);
"@
    Invoke-PhpJson -Code $insertReferenceCode | Out-Null

    $blockedEdit = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectproductitem/edit" -Token $token -Data @{
        id = $kitItemId
        projectId = $projectId
        productId = $productAId
        number = 3
        unitPrice = '120.00'
        discountRate = '0'
        price = '360.00'
    }
    Assert-Code -Json $blockedEdit -Expected 400 -Name 'standalone product item edit blocks referenced key change'

    $blockedDelete = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectproductitem/delete" -Token $token -Data @(
        @{ id = $kitItemId }
    )
    Assert-Code -Json $blockedDelete -Expected 400 -Name 'standalone product item delete blocks referenced row'

    Invoke-Php -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('return_order_item')->where('ID', '$safeReturnOrderItemId')->delete();
"@ | Out-Null

    $deleteSingle = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectproductitem/delete" -Token $token -Data @{
        ids = @($productBItemId)
    }
    Assert-Code -Json $deleteSingle -Expected 200 -Name 'standalone product item delete single'
    $stateAfterDeleteSingle = Invoke-PhpJson -Code $stateCode
    Assert-IntEqual -Actual ([int]$stateAfterDeleteSingle.itemCount) -Expected 1 -Name 'standalone delete single active item count'

    $deleteKit = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectproductitem/delete" -Token $token -Data @(
        @{ id = $kitItemId }
    )
    Assert-Code -Json $deleteKit -Expected 200 -Name 'standalone product item delete kit'
    $stateAfterDeleteKit = Invoke-PhpJson -Code $stateCode
    Assert-IntEqual -Actual ([int]$stateAfterDeleteKit.itemCount) -Expected 0 -Name 'standalone delete kit active item count'
    Assert-IntEqual -Actual ([int]$stateAfterDeleteKit.relationCount) -Expected 0 -Name 'standalone delete kit active relation count'

    Invoke-Php -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->update([
    'PROJECT_STATE' => 'WAIT_DELIVER',
    'UPDATE_TIME' => date('Y-m-d H:i:s'),
    'UPDATE_USER' => '$safeUserId',
]);
"@ | Out-Null

    $blockedStateAdd = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectproductitem/add" -Token $token -Data @{
        projectId = $projectId
        productId = $productAId
        number = 1
        unitPrice = '120.00'
        discountRate = '0'
        price = '120.00'
    }
    Assert-Code -Json $blockedStateAdd -Expected 400 -Name 'standalone product item add blocks non-follow project'

    $afterSideEffects = Invoke-PhpJson -Code $snapshotCode
    Assert-SideEffectsUnchanged -Before $beforeSideEffects -After $afterSideEffects

    Write-Host 'sale-project-product-item-standalone-http-smoke OK'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
