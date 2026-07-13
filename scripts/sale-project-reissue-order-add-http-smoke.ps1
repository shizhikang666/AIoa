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

    return ($raw.TrimStart([char]0xFEFF) | ConvertFrom-Json)
}

function Invoke-JsonPost {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [string]$Token = '',
        [object]$Body = @{}
    )

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("sale-project-reissue-order-add-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    $json = $Body | ConvertTo-Json -Depth 16
    $utf8NoBom = New-Object System.Text.UTF8Encoding -ArgumentList $false
    [System.IO.File]::WriteAllText($bodyPath, $json, $utf8NoBom)
    try {
        $args = @('-sS', '-X', 'POST', $Url, '-H', 'Content-Type: application/json', '--data-binary', "@$bodyPath")
        if ($Token -ne '') {
            $args = @('-sS', '-X', 'POST', $Url, '-H', "Authorization: Bearer $Token", '-H', 'Content-Type: application/json', '--data-binary', "@$bodyPath")
        }

        $raw = & curl.exe @args
        if ($LASTEXITCODE -ne 0) {
            throw "curl failed for $Url"
        }

        return (($raw -join "`n").TrimStart([char]0xFEFF) | ConvertFrom-Json)
    } finally {
        Remove-Item -LiteralPath $bodyPath -Force -ErrorAction SilentlyContinue
    }
}

function Invoke-JsonGet {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$Token
    )

    $raw = & curl.exe -sS -X GET $Url -H "Authorization: Bearer $Token"
    if ($LASTEXITCODE -ne 0) {
        throw "curl failed for $Url"
    }

    return (($raw -join "`n").TrimStart([char]0xFEFF) | ConvertFrom-Json)
}

function Enc {
    param([Parameter(Mandatory = $true)][string]$Value)

    return [System.Uri]::EscapeDataString($Value)
}

function Assert-Code {
    param(
        [Parameter(Mandatory = $true)]$Json,
        [Parameter(Mandatory = $true)][int]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    if ([int]$Json.code -ne $Expected) {
        throw "$Name expected code=$Expected actual=$($Json.code) response=$($Json | ConvertTo-Json -Compress -Depth 8)"
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

function Assert-DecimalEqual {
    param(
        [Parameter(Mandatory = $true)][string]$Actual,
        [Parameter(Mandatory = $true)][decimal]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    if ([decimal]$Actual -ne $Expected) {
        throw "$Name expected=$Expected actual=$Actual"
    }
}

function Assert-SideEffectsUnchanged {
    param(
        [Parameter(Mandatory = $true)]$Before,
        [Parameter(Mandatory = $true)]$After
    )

    foreach ($key in @('delivery', 'inventory', 'invoice', 'invoiceItem', 'invoicing', 'payment', 'expenditure', 'statement', 'ruTask', 'hiProc')) {
        if ([string]$Before.$key -ne [string]$After.$key) {
            throw "side effect count changed for $key before=$($Before.$key) after=$($After.$key)"
        }
    }
}

function New-ReissueBody {
    param(
        [Parameter(Mandatory = $true)][string]$ProjectId,
        [Parameter(Mandatory = $true)][string]$ProcessId,
        [Parameter(Mandatory = $true)][string]$ProductId,
        [Parameter(Mandatory = $true)][string]$ChildProductId,
        [string]$Amount = '20.00',
        [int]$Number = 2,
        [string]$UnitPrice = '10.00',
        [string]$Price = '20.00',
        [int]$ChildNumber = 1,
        [string]$RemarkSuffix = 'direct reissue',
        [string]$ItemRemarkSuffix = 'item',
        [string]$ChildRemarkSuffix = 'child',
        [string]$ChildMark = 'child mark'
    )

    return @{
        projectId = $ProjectId
        processId = $ProcessId
        amount = $Amount
        remark = "$script:Prefix $RemarkSuffix"
        productList = @(@{
            productId = $ProductId
            number = $Number
            unitPrice = $UnitPrice
            discountRate = '0.00'
            price = $Price
            remark = "$script:Prefix $ItemRemarkSuffix"
            children = @(@{
                productId = $ChildProductId
                number = $ChildNumber
                remark = "$script:Prefix $ChildRemarkSuffix"
                mark = $ChildMark
            })
        })
    }
}

$envMap = Get-EnvMap -Path $EnvPath
$account = ''
if ($envMap.ContainsKey('LOCAL_SUPER_ADMIN_ACCOUNT')) {
    $account = [string]$envMap['LOCAL_SUPER_ADMIN_ACCOUNT']
}
if ($account -eq '') {
    throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$seed = [Guid]::NewGuid().ToString('N').Substring(0, 10)
$Prefix = "codex-reissue-add-$seed"
$CustomerId = "C$seed"
$ProjectId = "PJ$seed"
$FollowProjectId = "PF$seed"
$ProductId = "P$seed"
$ChildProductId = "H$seed"
$MissingProductId = "M$seed"
$ProcessId = "proc-$seed"
$EditProcessId = "proc-edit-$seed"
$WorkflowProcessId = "wf-$seed"
$DuplicateProcessId = $ProcessId
$UserId = ''
$TenantId = ''
$OrgId = ''

$safeAccount = $account.Replace("'", "\'")
$safePrefix = $Prefix.Replace("'", "\'")
$safeCustomerId = $CustomerId.Replace("'", "\'")
$safeProjectId = $ProjectId.Replace("'", "\'")
$safeFollowProjectId = $FollowProjectId.Replace("'", "\'")
$safeProductId = $ProductId.Replace("'", "\'")
$safeChildProductId = $ChildProductId.Replace("'", "\'")
$safeProcessId = $ProcessId.Replace("'", "\'")
$safeEditProcessId = $EditProcessId.Replace("'", "\'")
$safeWorkflowProcessId = $WorkflowProcessId.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$projectIds = ['$safeProjectId', '$safeFollowProjectId'];
`$orderIds = think\facade\Db::name('biz_sale_project_reissue_order')->whereIn('PROJECT_ID', `$projectIds)->column('ID');
if (`$orderIds !== []) {
    `$itemIds = think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_REISSUE_ORDER_ID', `$orderIds)->column('ID');
    if (`$itemIds !== []) {
        think\facade\Db::name('sale_project_product_item_relation')->whereIn('OBJECT_ID', `$itemIds)->delete();
    }
    think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_REISSUE_ORDER_ID', `$orderIds)->delete();
    think\facade\Db::name('biz_sale_project_reissue_order')->whereIn('ID', `$orderIds)->delete();
}
think\facade\Db::name('biz_sale_project_reissue_order')->where('PROCESS_ID', '$safeProcessId')->delete();
think\facade\Db::name('biz_sale_project_reissue_order')->where('PROCESS_ID', '$safeEditProcessId')->delete();
think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', '$safeWorkflowProcessId')->delete();
think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->delete();
think\facade\Db::name('biz_product')->whereIn('ID', ['$safeProductId', '$safeChildProductId'])->delete();
think\facade\Db::name('customer')->where('ID', '$safeCustomerId')->delete();
"@

$snapshotCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'delivery' => think\facade\Db::name('delivery_record')->count(),
    'inventory' => think\facade\Db::name('inventory')->count(),
    'invoice' => think\facade\Db::name('biz_sale_project_invoice')->count(),
    'invoiceItem' => think\facade\Db::name('biz_sale_project_invoice_item')->count(),
    'invoicing' => think\facade\Db::name('biz_sale_project_invoicing')->count(),
    'payment' => think\facade\Db::name('biz_payment_record')->count(),
    'expenditure' => think\facade\Db::name('biz_expenditure_record')->count(),
    'statement' => think\facade\Db::name('settlement_account_statement')->count(),
    'ruTask' => think\facade\Db::name('act_ru_task')->count(),
    'hiProc' => think\facade\Db::name('act_hi_procinst')->count(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

Invoke-Php -Code $cleanupCode | Out-Null

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
think\facade\Db::name('customer')->insert([
    'ID' => '$safeCustomerId',
    'NAME' => '$safePrefix customer',
    'CUSTOM_TYPE' => 'OLD',
    'ORG' => `$orgId !== '' ? `$orgId : null,
    'USER' => `$userId,
    'STATUS' => 'ENABLE',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 0,
    'DEAL_AMOUNT' => '0.00',
]);
foreach ([['$safeProductId', '$safePrefix product'], ['$safeChildProductId', '$safePrefix child']] as `$product) {
    think\facade\Db::name('biz_product')->insert([
        'ID' => `$product[0],
        'PRODUCT_NAME' => `$product[1],
        'PRODUCT_CATEGORY' => 'SMOKE',
        'SAFETY_STOCK' => 0,
        'PURCHASE_PRICE' => '10.00',
        'SALE_PRICE' => '20.00',
        'MIN_PRICE' => '8.00',
        'CATEGORY' => 'SINGLE_PRODUCT',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => `$userId,
        'TENANT_ID' => `$tenantId,
        'SPECS' => 'direct-reissue-smoke',
        'ORG' => `$orgId !== '' ? `$orgId : null,
        'status' => 'ENABLE',
    ]);
}
foreach ([['$safeProjectId', 'WAIT_DELIVER'], ['$safeFollowProjectId', 'FOLLOW']] as `$project) {
    think\facade\Db::name('biz_sale_project')->insert([
        'ID' => `$project[0],
        'CUSTOMER' => '$safeCustomerId',
        'PROJECT_NAME' => '$safePrefix ' . `$project[1],
        'PROJECT_STATE' => `$project[1],
        'PLAY_STATE' => 'UNPAID',
        'VISIBILITY' => 'PRIVATE',
        'INIT_PRICE' => '100.00',
        'TOTAL_PRICE' => '100.00',
        'AMOUNT_COLLECTED' => '0.00',
        'PROJECT_CATEGORY' => 'DEFAULT',
        'USER' => `$userId,
        'ORG' => `$orgId !== '' ? `$orgId : null,
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => `$userId,
        'TENANT_ID' => `$tenantId,
        'VERSION' => 0,
        'DEAL_AMOUNT' => '0.00',
        'HISTORY_AMOUNT' => '0.00',
        'TOTAL_RETURN_AMOUNT' => '0.00',
        'TOTAL_REFUND_AMOUNT' => '0.00',
    ]);
}
think\facade\Db::name('act_hi_procinst')->insert([
    'ID_' => '$safeWorkflowProcessId',
    'PROC_INST_ID_' => '$safeWorkflowProcessId',
    'BUSINESS_KEY_' => '$safeProjectId',
    'PROC_DEF_KEY_' => 'Process_project_reissue_product',
    'PROC_DEF_ID_' => 'Process_project_reissue_product:smoke',
    'START_TIME_' => `$now,
    'START_USER_ID_' => `$userId,
    'START_ACT_ID_' => 'Activity_approval',
    'TENANT_ID_' => `$tenantId,
    'STATE_' => 'ACTIVE',
]);
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_SALE_PROJECT_REISSUE_ORDER_ADD_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => `$userId,
    'tenantId' => `$tenantId,
    'orgId' => `$orgId,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    $context = Invoke-PhpJson -Code $setupCode
    $token = [string]$context.token
    $UserId = [string]$context.userId
    $TenantId = [string]$context.tenantId
    $OrgId = [string]$context.orgId
    if ([string]::IsNullOrWhiteSpace($token) -or [string]::IsNullOrWhiteSpace($UserId) -or [string]::IsNullOrWhiteSpace($TenantId)) {
        throw 'failed to create local smoke auth token'
    }

    $beforeSideEffects = Invoke-PhpJson -Code $snapshotCode

    $validBody = New-ReissueBody -ProjectId $ProjectId -ProcessId $ProcessId -ProductId $ProductId -ChildProductId $ChildProductId
    $noToken = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectreissueorder/add') -Body $validBody
    Assert-Code -Json $noToken -Expected 401 -Name 'reissue order add no-token'

    $missingProductList = @{
        projectId = $ProjectId
        processId = "proc-missing-$seed"
        amount = '20.00'
    }
    $missing = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectreissueorder/add') -Token $token -Body $missingProductList
    Assert-Code -Json $missing -Expected 400 -Name 'reissue order add missing productList'

    $missingProduct = New-ReissueBody -ProjectId $ProjectId -ProcessId "proc-bad-product-$seed" -ProductId $MissingProductId -ChildProductId $ChildProductId
    $missingProductResponse = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectreissueorder/add') -Token $token -Body $missingProduct
    Assert-Code -Json $missingProductResponse -Expected 404 -Name 'reissue order add missing product'

    $followBody = New-ReissueBody -ProjectId $FollowProjectId -ProcessId "proc-follow-$seed" -ProductId $ProductId -ChildProductId $ChildProductId
    $followResponse = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectreissueorder/add') -Token $token -Body $followBody
    Assert-Code -Json $followResponse -Expected 400 -Name 'reissue order add follow project guard'

    $workflowProcessBody = New-ReissueBody -ProjectId $ProjectId -ProcessId $WorkflowProcessId -ProductId $ProductId -ChildProductId $ChildProductId
    $workflowProcessResponse = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectreissueorder/add') -Token $token -Body $workflowProcessBody
    Assert-Code -Json $workflowProcessResponse -Expected 400 -Name 'reissue order add workflow process guard'

    $add = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectreissueorder/add') -Token $token -Body $validBody
    Assert-Code -Json $add -Expected 200 -Name 'reissue order add'
    Assert-IntEqual -Actual ([int]$add.data.productItemCount) -Expected 1 -Name 'reissue order add product item count'
    Assert-IntEqual -Actual ([int]$add.data.relationCount) -Expected 1 -Name 'reissue order add relation count'
    Assert-Equal -Actual ([string]$add.data.projectState) -Expected 'PARTIALLY_SHIPPED' -Name 'reissue order add project state response'
    Assert-Equal -Actual ([string]$add.data.playState) -Expected 'UNPAID' -Name 'reissue order add play state response'
    Assert-DecimalEqual -Actual ([string]$add.data.totalPrice) -Expected 120.00 -Name 'reissue order add total price response'

    $duplicate = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectreissueorder/add') -Token $token -Body $validBody
    Assert-Code -Json $duplicate -Expected 400 -Name 'reissue order duplicate process id'

$stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$notDeleted = static fn (`$row): bool => !isset(`$row['DELETE_FLAG']) || `$row['DELETE_FLAG'] === null || (string)`$row['DELETE_FLAG'] === 'NOT_DELETE';
`$allOrders = think\facade\Db::name('biz_sale_project_reissue_order')
    ->where('PROJECT_ID', '$safeProjectId')
    ->order('ID', 'asc')
    ->select()->toArray();
`$allOrderIds = array_values(array_map(static fn (`$row) => (string)`$row['ID'], `$allOrders));
`$allItems = `$allOrderIds === [] ? [] : think\facade\Db::name('biz_sale_project_product_item')
    ->whereIn('PROJECT_REISSUE_ORDER_ID', `$allOrderIds)
    ->order('ID', 'asc')
    ->select()->toArray();
`$allItemIds = array_values(array_map(static fn (`$row) => (string)`$row['ID'], `$allItems));
`$allRelations = `$allItemIds === [] ? [] : think\facade\Db::name('sale_project_product_item_relation')
    ->whereIn('OBJECT_ID', `$allItemIds)
    ->order('ID', 'asc')
    ->select()->toArray();
`$orders = array_values(array_filter(`$allOrders, `$notDeleted));
`$orderIds = array_values(array_map(static fn (`$row) => (string)`$row['ID'], `$orders));
`$items = `$orderIds === [] ? [] : array_values(array_filter(`$allItems, static fn (`$row): bool => in_array((string)`$row['PROJECT_REISSUE_ORDER_ID'], `$orderIds, true) && (!isset(`$row['DELETE_FLAG']) || `$row['DELETE_FLAG'] === null || (string)`$row['DELETE_FLAG'] === 'NOT_DELETE')));
`$itemIds = array_values(array_map(static fn (`$row) => (string)`$row['ID'], `$items));
`$relations = `$itemIds === [] ? [] : array_values(array_filter(`$allRelations, static fn (`$row): bool => in_array((string)`$row['OBJECT_ID'], `$itemIds, true) && (!isset(`$row['DELETE_FLAG']) || `$row['DELETE_FLAG'] === null || (string)`$row['DELETE_FLAG'] === 'NOT_DELETE')));
echo json_encode([
    'project' => think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->find(),
    'followProject' => think\facade\Db::name('biz_sale_project')->where('ID', '$safeFollowProjectId')->find(),
    'orders' => `$orders,
    'items' => `$items,
    'relations' => `$relations,
    'allOrders' => `$allOrders,
    'allItems' => `$allItems,
    'allRelations' => `$allRelations,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $state = Invoke-PhpJson -Code $stateCode
    Assert-IntEqual -Actual (@($state.orders).Count) -Expected 1 -Name 'reissue order DB order count'
    Assert-IntEqual -Actual (@($state.items).Count) -Expected 1 -Name 'reissue order DB item count'
    Assert-IntEqual -Actual (@($state.relations).Count) -Expected 1 -Name 'reissue order DB relation count'

    $order = @($state.orders)[0]
    $item = @($state.items)[0]
    $relation = @($state.relations)[0]
    Assert-Equal -Actual ([string]$order.ID) -Expected ([string]$add.data.reissueOrderId) -Name 'reissue order DB id'
    Assert-Equal -Actual ([string]$order.PROJECT_ID) -Expected $ProjectId -Name 'reissue order DB project id'
    Assert-Equal -Actual ([string]$order.PROCESS_ID) -Expected $ProcessId -Name 'reissue order DB process id'
    Assert-DecimalEqual -Actual ([string]$order.AMOUNT) -Expected 20.00 -Name 'reissue order DB amount'
    Assert-Equal -Actual ([string]$item.PROJECT_ID) -Expected $ProjectId -Name 'reissue item DB project id'
    Assert-Equal -Actual ([string]$item.PRODUCT_ID) -Expected $ProductId -Name 'reissue item DB product id'
    Assert-Equal -Actual ([string]$item.CATEGORY) -Expected 'REISSUE_ORDER' -Name 'reissue item DB category'
    Assert-Equal -Actual ([string]$item.STATE) -Expected 'WAIT_DELIVER' -Name 'reissue item DB state'
    Assert-Equal -Actual ([string]$item.PROJECT_REISSUE_ORDER_ID) -Expected ([string]$order.ID) -Name 'reissue item DB order id'
    Assert-DecimalEqual -Actual ([string]$item.NUMBER) -Expected 2 -Name 'reissue item DB number'
    Assert-DecimalEqual -Actual ([string]$item.DELIVERY) -Expected 0 -Name 'reissue item DB delivery'
    Assert-Equal -Actual ([string]$relation.OBJECT_ID) -Expected ([string]$item.ID) -Name 'reissue relation DB object id'
    Assert-Equal -Actual ([string]$relation.TARGET_ID) -Expected $ChildProductId -Name 'reissue relation DB target id'
    Assert-DecimalEqual -Actual ([string]$relation.NUMBER) -Expected 1 -Name 'reissue relation DB number'
    Assert-Equal -Actual ([string]$relation.MARK) -Expected 'child mark' -Name 'reissue relation DB mark'
    Assert-Equal -Actual ([string]$state.project.PROJECT_STATE) -Expected 'PARTIALLY_SHIPPED' -Name 'reissue order project state'
    Assert-Equal -Actual ([string]$state.project.PLAY_STATE) -Expected 'UNPAID' -Name 'reissue order play state'
    Assert-DecimalEqual -Actual ([string]$state.project.TOTAL_PRICE) -Expected 120.00 -Name 'reissue order project total price'
    Assert-DecimalEqual -Actual ([string]$state.project.TOTAL_REFUND_AMOUNT) -Expected 0.00 -Name 'reissue order project total refund'
    Assert-DecimalEqual -Actual ([string]$state.project.TOTAL_RETURN_AMOUNT) -Expected 0.00 -Name 'reissue order project total return'
    Assert-Equal -Actual ([string]$state.followProject.PROJECT_STATE) -Expected 'FOLLOW' -Name 'follow project unchanged'

    $readback = Invoke-JsonGet -Url ($baseUrl + '/biz/saleprojectreissueorder/list/query?projectId=' + (Enc $ProjectId)) -Token $token
    Assert-Code -Json $readback -Expected 200 -Name 'reissue order list/query readback'
    Assert-IntEqual -Actual (@($readback.data).Count) -Expected 1 -Name 'reissue order list/query order count'
    $readbackRow = @($readback.data)[0]
    Assert-Equal -Actual ([string]$readbackRow.order.id) -Expected ([string]$order.ID) -Name 'reissue order list/query order id'
    Assert-IntEqual -Actual (@($readbackRow.productItemList).Count) -Expected 1 -Name 'reissue order list/query item count'
    Assert-IntEqual -Actual (@($readbackRow.productItemList[0].children).Count) -Expected 1 -Name 'reissue order list/query child count'

    $orderId = [string]$add.data.reissueOrderId
    $editBody = New-ReissueBody -ProjectId $ProjectId -ProcessId $EditProcessId -ProductId $ProductId -ChildProductId $ChildProductId -Amount '30.00' -Number 3 -UnitPrice '10.00' -Price '30.00' -ChildNumber 2 -RemarkSuffix 'direct reissue edit' -ItemRemarkSuffix 'item edit' -ChildRemarkSuffix 'child edit' -ChildMark 'child edit mark'
    $editBody.id = $orderId

    $editNoToken = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectreissueorder/edit') -Body $editBody
    Assert-Code -Json $editNoToken -Expected 401 -Name 'reissue order edit no-token'

    $missingEdit = New-ReissueBody -ProjectId $ProjectId -ProcessId $EditProcessId -ProductId $ProductId -ChildProductId $ChildProductId
    $missingEditResponse = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectreissueorder/edit') -Token $token -Body $missingEdit
    Assert-Code -Json $missingEditResponse -Expected 400 -Name 'reissue order edit missing id'

    $wrongProjectEdit = New-ReissueBody -ProjectId $FollowProjectId -ProcessId $EditProcessId -ProductId $ProductId -ChildProductId $ChildProductId
    $wrongProjectEdit.id = $orderId
    $wrongProjectResponse = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectreissueorder/edit') -Token $token -Body $wrongProjectEdit
    Assert-Code -Json $wrongProjectResponse -Expected 400 -Name 'reissue order edit wrong project'

    $edit = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectreissueorder/edit') -Token $token -Body $editBody
    Assert-Code -Json $edit -Expected 200 -Name 'reissue order edit'
    Assert-IntEqual -Actual ([int]$edit.data.productItemCount) -Expected 1 -Name 'reissue order edit product item count'
    Assert-IntEqual -Actual ([int]$edit.data.relationCount) -Expected 1 -Name 'reissue order edit relation count'
    Assert-Equal -Actual ([string]$edit.data.projectState) -Expected 'PARTIALLY_SHIPPED' -Name 'reissue order edit project state response'
    Assert-Equal -Actual ([string]$edit.data.playState) -Expected 'UNPAID' -Name 'reissue order edit play state response'
    Assert-DecimalEqual -Actual ([string]$edit.data.totalPrice) -Expected 130.00 -Name 'reissue order edit total price response'

    $state = Invoke-PhpJson -Code $stateCode
    Assert-IntEqual -Actual (@($state.orders).Count) -Expected 1 -Name 'reissue order edit active order count'
    Assert-IntEqual -Actual (@($state.items).Count) -Expected 1 -Name 'reissue order edit active item count'
    Assert-IntEqual -Actual (@($state.relations).Count) -Expected 1 -Name 'reissue order edit active relation count'
    Assert-IntEqual -Actual (@($state.allItems).Count) -Expected 2 -Name 'reissue order edit all item count'
    Assert-IntEqual -Actual (@($state.allRelations).Count) -Expected 2 -Name 'reissue order edit all relation count'

    $order = @($state.orders)[0]
    $item = @($state.items)[0]
    $relation = @($state.relations)[0]
    Assert-Equal -Actual ([string]$order.PROCESS_ID) -Expected $EditProcessId -Name 'reissue order edit DB process id'
    Assert-DecimalEqual -Actual ([string]$order.AMOUNT) -Expected 30.00 -Name 'reissue order edit DB amount'
    Assert-DecimalEqual -Actual ([string]$item.NUMBER) -Expected 3 -Name 'reissue item edit DB number'
    Assert-DecimalEqual -Actual ([string]$item.PRICE) -Expected 30.00 -Name 'reissue item edit DB price'
    Assert-DecimalEqual -Actual ([string]$relation.NUMBER) -Expected 2 -Name 'reissue relation edit DB number'
    Assert-Equal -Actual ([string]$relation.MARK) -Expected 'child edit mark' -Name 'reissue relation edit DB mark'
    Assert-Equal -Actual ([string]$state.project.PROJECT_STATE) -Expected 'PARTIALLY_SHIPPED' -Name 'reissue order edit project state'
    Assert-Equal -Actual ([string]$state.project.PLAY_STATE) -Expected 'UNPAID' -Name 'reissue order edit play state'
    Assert-DecimalEqual -Actual ([string]$state.project.TOTAL_PRICE) -Expected 130.00 -Name 'reissue order edit project total price'

    $readback = Invoke-JsonGet -Url ($baseUrl + '/biz/saleprojectreissueorder/list/query?projectId=' + (Enc $ProjectId)) -Token $token
    Assert-Code -Json $readback -Expected 200 -Name 'reissue order list/query edit readback'
    Assert-IntEqual -Actual (@($readback.data).Count) -Expected 1 -Name 'reissue order list/query edit order count'
    $readbackRow = @($readback.data)[0]
    Assert-Equal -Actual ([string]$readbackRow.order.id) -Expected $orderId -Name 'reissue order list/query edit order id'
    Assert-IntEqual -Actual (@($readbackRow.productItemList).Count) -Expected 1 -Name 'reissue order list/query edit item count'

    $deleteBody = @{ idList = @($orderId) }
    $deleteNoToken = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectreissueorder/delete') -Body $deleteBody
    Assert-Code -Json $deleteNoToken -Expected 401 -Name 'reissue order delete no-token'

    $deleteMissing = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectreissueorder/delete') -Token $token -Body @{}
    Assert-Code -Json $deleteMissing -Expected 400 -Name 'reissue order delete missing idList'

    $mixedDelete = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectreissueorder/delete') -Token $token -Body @{ idList = @($orderId, "missing-$seed") }
    Assert-Code -Json $mixedDelete -Expected 404 -Name 'reissue order delete missing id rollback'
    $stateAfterFailedDelete = Invoke-PhpJson -Code $stateCode
    Assert-IntEqual -Actual (@($stateAfterFailedDelete.orders).Count) -Expected 1 -Name 'reissue order delete rollback active order count'
    Assert-DecimalEqual -Actual ([string]$stateAfterFailedDelete.project.TOTAL_PRICE) -Expected 130.00 -Name 'reissue order delete rollback total price'

    $delete = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectreissueorder/delete') -Token $token -Body $deleteBody
    Assert-Code -Json $delete -Expected 200 -Name 'reissue order delete'
    Assert-IntEqual -Actual ([int]$delete.data.count) -Expected 1 -Name 'reissue order delete count'
    Assert-IntEqual -Actual ([int]$delete.data.productItemCount) -Expected 1 -Name 'reissue order delete product item count'
    Assert-IntEqual -Actual ([int]$delete.data.relationCount) -Expected 1 -Name 'reissue order delete relation count'
    $deleteProjectState = [string]$delete.data.projectStates.PSObject.Properties[$ProjectId].Value
    $deleteProject = $delete.data.projects.PSObject.Properties[$ProjectId].Value
    Assert-Equal -Actual $deleteProjectState -Expected 'WAIT_DELIVER' -Name 'reissue order delete project state response'
    Assert-Equal -Actual ([string]$deleteProject.playState) -Expected 'UNPAID' -Name 'reissue order delete play state response'
    Assert-DecimalEqual -Actual ([string]$deleteProject.totalPrice) -Expected 100.00 -Name 'reissue order delete total price response'

    $state = Invoke-PhpJson -Code $stateCode
    Assert-IntEqual -Actual (@($state.orders).Count) -Expected 0 -Name 'reissue order delete active order count'
    Assert-IntEqual -Actual (@($state.items).Count) -Expected 0 -Name 'reissue order delete active item count'
    Assert-IntEqual -Actual (@($state.relations).Count) -Expected 0 -Name 'reissue order delete active relation count'
    Assert-IntEqual -Actual (@($state.allOrders).Count) -Expected 1 -Name 'reissue order delete all order count'
    Assert-IntEqual -Actual (@($state.allItems).Count) -Expected 2 -Name 'reissue order delete all item count'
    Assert-IntEqual -Actual (@($state.allRelations).Count) -Expected 2 -Name 'reissue order delete all relation count'
    foreach ($row in @($state.allOrders)) {
        Assert-Equal -Actual ([string]$row.DELETE_FLAG) -Expected 'DELETED' -Name 'reissue order delete all orders flag'
    }
    foreach ($row in @($state.allItems)) {
        Assert-Equal -Actual ([string]$row.DELETE_FLAG) -Expected 'DELETED' -Name 'reissue order delete all items flag'
    }
    foreach ($row in @($state.allRelations)) {
        Assert-Equal -Actual ([string]$row.DELETE_FLAG) -Expected 'DELETED' -Name 'reissue order delete all relations flag'
    }
    Assert-Equal -Actual ([string]$state.project.PROJECT_STATE) -Expected 'WAIT_DELIVER' -Name 'reissue order delete project state'
    Assert-Equal -Actual ([string]$state.project.PLAY_STATE) -Expected 'UNPAID' -Name 'reissue order delete play state'
    Assert-DecimalEqual -Actual ([string]$state.project.TOTAL_PRICE) -Expected 100.00 -Name 'reissue order delete project total price'
    Assert-DecimalEqual -Actual ([string]$state.project.TOTAL_REFUND_AMOUNT) -Expected 0.00 -Name 'reissue order delete project total refund'
    Assert-DecimalEqual -Actual ([string]$state.project.TOTAL_RETURN_AMOUNT) -Expected 0.00 -Name 'reissue order delete project total return'

    $readback = Invoke-JsonGet -Url ($baseUrl + '/biz/saleprojectreissueorder/list/query?projectId=' + (Enc $ProjectId)) -Token $token
    Assert-Code -Json $readback -Expected 200 -Name 'reissue order list/query delete readback'
    Assert-IntEqual -Actual (@($readback.data).Count) -Expected 0 -Name 'reissue order list/query delete order count'

    $afterSideEffects = Invoke-PhpJson -Code $snapshotCode
    Assert-SideEffectsUnchanged -Before $beforeSideEffects -After $afterSideEffects

    Write-Host 'sale-project reissue-order add/edit/delete HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
