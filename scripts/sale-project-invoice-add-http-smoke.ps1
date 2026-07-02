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

    $raw = & php -r $Code
    if ($LASTEXITCODE -ne 0) {
        throw 'php code failed'
    }

    return ($raw -join "`n").TrimStart([char]0xFEFF)
}

function Invoke-PhpJson {
    param([Parameter(Mandatory = $true)][string]$Code)

    return (Invoke-Php -Code $Code | ConvertFrom-Json)
}

function Invoke-JsonPost {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [string]$Token = '',
        [object]$Body = @{}
    )

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("sale-project-invoice-add-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    $json = $Body | ConvertTo-Json -Depth 12
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

    foreach ($key in @('delivery', 'inventory', 'payment', 'expenditure', 'statement', 'ruTask', 'hiProc')) {
        Assert-IntEqual -Actual ([int]$After.$key) -Expected ([int]$Before.$key) -Name "$key side-effect count"
    }
}

function New-InvoiceBody {
    param(
        [Parameter(Mandatory = $true)][string]$ProjectId,
        [Parameter(Mandatory = $true)][string]$ProcessId,
        [Parameter(Mandatory = $true)][string]$ItemId,
        [Parameter(Mandatory = $true)][string]$WarehouseId,
        [Parameter(Mandatory = $true)][string]$Operator,
        [decimal]$Amount = 3
    )

    $logisticsId = 'LG' + ($ProcessId -replace '[^A-Za-z0-9]', '')
    if ($logisticsId.Length -gt 20) {
        $logisticsId = $logisticsId.Substring(0, 20)
    }

    return @{
        projectId = $ProjectId
        processId = $ProcessId
        consignee = 'Codex consignee'
        logisticsCategory = 'EXPRESS'
        phone = '18800000003'
        logisticsId = $logisticsId
        freight = '0.00'
        freightTime = '2026-06-25 12:13:14'
        freightCategory = 'BUYER_PAY'
        unit = 'Codex unit'
        address = 'Codex address'
        remark = 'direct invoice add smoke'
        operator = $Operator
        projectProductItemList = @(@{
            projectProductItemId = $ItemId
            warehousesId = $WarehouseId
            amount = "$Amount"
            remark = 'item remark'
        })
    }
}

function New-InvoiceEditBody {
    param(
        [Parameter(Mandatory = $true)][string]$InvoiceId,
        [Parameter(Mandatory = $true)][string]$ProjectId,
        [Parameter(Mandatory = $true)][string]$ProcessId
    )

    $logisticsId = 'LE' + ($ProcessId -replace '[^A-Za-z0-9]', '')
    if ($logisticsId.Length -gt 20) {
        $logisticsId = $logisticsId.Substring(0, 20)
    }

    return @{
        id = $InvoiceId
        projectId = $ProjectId
        processId = $ProcessId
        consignee = 'Codex edited consignee'
        logisticsCategory = 'TRUCK'
        phone = '18800000004'
        logisticsId = $logisticsId
        freight = '12.34'
        freightTime = '2026-06-25 13:14:15'
        freightCategory = 'SELLER_PAY'
        unit = 'Codex edited unit'
        address = 'Codex edited address'
        remark = 'direct invoice edit smoke'
    }
}

$envMap = Get-EnvMap -Path $EnvPath
$account = ''
if ($envMap.ContainsKey('LOCAL_SUPER_ADMIN_ACCOUNT')) {
    $account = [string]$envMap['LOCAL_SUPER_ADMIN_ACCOUNT']
}
if ([string]::IsNullOrWhiteSpace($account)) {
    throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in local .env'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$seed = ([Guid]::NewGuid().ToString('N')).Substring(0, 10)
$Prefix = "codex_invoice_add_$seed"
$CustomerId = "C$seed"
$ProjectId = "PJ$seed"
$FollowProjectId = "PF$seed"
$ItemId = "I$seed"
$FollowItemId = "F$seed"
$ProductId = "P$seed"
$WarehouseId = "W$seed"
$InventoryId = "V$seed"
$ProcessId = "proc-invoice-$seed"
$UserId = ''

$safeAccount = $account.Replace("'", "\'")
$safePrefix = $Prefix.Replace("'", "\'")
$safeCustomerId = $CustomerId.Replace("'", "\'")
$safeProjectId = $ProjectId.Replace("'", "\'")
$safeFollowProjectId = $FollowProjectId.Replace("'", "\'")
$safeItemId = $ItemId.Replace("'", "\'")
$safeFollowItemId = $FollowItemId.Replace("'", "\'")
$safeProductId = $ProductId.Replace("'", "\'")
$safeWarehouseId = $WarehouseId.Replace("'", "\'")
$safeInventoryId = $InventoryId.Replace("'", "\'")
$safeProcessId = $ProcessId.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$projectIds = ['$safeProjectId', '$safeFollowProjectId'];
`$invoiceIds = think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROJECT_ID', `$projectIds)->column('ID');
`$processInvoiceIds = think\facade\Db::name('biz_sale_project_invoice')->where('PROCESS_ID', '$safeProcessId')->column('ID');
`$invoiceIds = array_values(array_unique(array_merge(`$invoiceIds, `$processInvoiceIds)));
if (`$invoiceIds !== []) {
    think\facade\Db::name('biz_sale_project_invoice_item')->whereIn('INVOICE_ID', `$invoiceIds)->delete();
    think\facade\Db::name('biz_sale_project_invoice')->whereIn('ID', `$invoiceIds)->delete();
}
think\facade\Db::name('biz_sale_project_product_item')->whereIn('ID', ['$safeItemId', '$safeFollowItemId'])->delete();
think\facade\Db::name('inventory')->where('ID', '$safeInventoryId')->delete();
think\facade\Db::name('warehouses')->where('ID', '$safeWarehouseId')->delete();
think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->delete();
think\facade\Db::name('biz_product')->where('ID', '$safeProductId')->delete();
think\facade\Db::name('customer')->where('ID', '$safeCustomerId')->delete();
"@

$snapshotCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'delivery' => think\facade\Db::name('delivery_record')->count(),
    'inventory' => think\facade\Db::name('inventory')->count(),
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
think\facade\Db::name('biz_product')->insert([
    'ID' => '$safeProductId',
    'PRODUCT_NAME' => '$safePrefix product',
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
    'SPECS' => 'direct-invoice-smoke',
    'ORG' => `$orgId !== '' ? `$orgId : null,
    'status' => 'ENABLE',
]);
think\facade\Db::name('warehouses')->insert([
    'ID' => '$safeWarehouseId',
    'NAME' => '$safePrefix warehouse',
    'CODE' => substr('$seed', 0, 20),
    'ADDRESS' => '$safePrefix address',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'ORG' => `$orgId !== '' ? `$orgId : null,
]);
think\facade\Db::name('inventory')->insert([
    'ID' => '$safeInventoryId',
    'WAREHOUSES_ID' => '$safeWarehouseId',
    'PRODUCT_ID' => '$safeProductId',
    'CURRENT_COUNT' => '10',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 0,
]);
foreach ([['$safeProjectId', '$safeItemId', 'WAIT_DELIVER'], ['$safeFollowProjectId', '$safeFollowItemId', 'FOLLOW']] as `$project) {
    think\facade\Db::name('biz_sale_project')->insert([
        'ID' => `$project[0],
        'CUSTOMER' => '$safeCustomerId',
        'PROJECT_NAME' => '$safePrefix ' . `$project[2],
        'PROJECT_STATE' => `$project[2],
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
    think\facade\Db::name('biz_sale_project_product_item')->insert([
        'ID' => `$project[1],
        'PROJECT_ID' => `$project[0],
        'PRODUCT_ID' => '$safeProductId',
        'CATEGORY' => 'INIT',
        'STATE' => 'WAIT_DELIVER',
        'NUMBER' => '5',
        'DELIVERY' => '0',
        'UNIT_PRICE' => '20.00',
        'DISCOUNT_RATE' => '100',
        'PRICE' => '100.00',
        'REMARK' => '$safePrefix',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => `$userId,
        'TENANT_ID' => `$tenantId,
        'VERSION' => 0,
        'PROJECT_REISSUE_ORDER_ID' => '',
        'MARK' => '',
    ]);
}
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_SALE_PROJECT_INVOICE_ADD_SMOKE';
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
    if ([string]::IsNullOrWhiteSpace($token) -or [string]::IsNullOrWhiteSpace($UserId)) {
        throw 'failed to create local smoke auth token'
    }

    $beforeSideEffects = Invoke-PhpJson -Code $snapshotCode

    $validBody = New-InvoiceBody -ProjectId $ProjectId -ProcessId $ProcessId -ItemId $ItemId -WarehouseId $WarehouseId -Operator $UserId
    $noToken = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectinvoice/add') -Body $validBody
    Assert-Code -Json $noToken -Expected 401 -Name 'invoice add no-token'

    $missingItems = @{
        projectId = $ProjectId
        processId = "proc-missing-$seed"
        operator = $UserId
    }
    $missingResponse = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectinvoice/add') -Token $token -Body $missingItems
    Assert-Code -Json $missingResponse -Expected 400 -Name 'invoice add missing projectProductItemList'

    $followBody = New-InvoiceBody -ProjectId $FollowProjectId -ProcessId "proc-follow-$seed" -ItemId $FollowItemId -WarehouseId $WarehouseId -Operator $UserId
    $followResponse = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectinvoice/add') -Token $token -Body $followBody
    Assert-Code -Json $followResponse -Expected 400 -Name 'invoice add follow project guard'

    $overBody = New-InvoiceBody -ProjectId $ProjectId -ProcessId "proc-over-$seed" -ItemId $ItemId -WarehouseId $WarehouseId -Operator $UserId -Amount 6
    $overResponse = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectinvoice/add') -Token $token -Body $overBody
    Assert-Code -Json $overResponse -Expected 400 -Name 'invoice add over delivery guard'

    $add = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectinvoice/add') -Token $token -Body $validBody
    Assert-Code -Json $add -Expected 200 -Name 'invoice add'
    Assert-IntEqual -Actual ([int]$add.data.invoiceItemCount) -Expected 1 -Name 'invoice add item count'
    Assert-Equal -Actual ([string]$add.data.projectState) -Expected 'PARTIALLY_SHIPPED' -Name 'invoice add project state response'

    $duplicate = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectinvoice/add') -Token $token -Body $validBody
    Assert-Code -Json $duplicate -Expected 400 -Name 'invoice add duplicate process id'

    $invoiceId = [string]$add.data.invoiceId
    $editProcessId = "proc-edit-$seed"
    $editBody = New-InvoiceEditBody -InvoiceId $invoiceId -ProjectId $ProjectId -ProcessId $editProcessId
    $editNoToken = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectinvoice/edit') -Body $editBody
    Assert-Code -Json $editNoToken -Expected 401 -Name 'invoice edit no-token'

    $editMissingId = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectinvoice/edit') -Token $token -Body @{
        projectId = $ProjectId
        processId = "proc-edit-missing-$seed"
    }
    Assert-Code -Json $editMissingId -Expected 400 -Name 'invoice edit missing id'

    $wrongProjectBody = New-InvoiceEditBody -InvoiceId $invoiceId -ProjectId $ProjectId -ProcessId "proc-edit-wrong-project-$seed"
    $wrongProjectBody.projectId = $FollowProjectId
    $editWrongProject = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectinvoice/edit') -Token $token -Body $wrongProjectBody
    Assert-Code -Json $editWrongProject -Expected 400 -Name 'invoice edit project guard'

    $edit = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectinvoice/edit') -Token $token -Body $editBody
    Assert-Code -Json $edit -Expected 200 -Name 'invoice edit'
    Assert-Equal -Actual ([string]$edit.data.id) -Expected $invoiceId -Name 'invoice edit response id'
    Assert-Equal -Actual ([string]$edit.data.processId) -Expected $editProcessId -Name 'invoice edit response process id'

    $safeInvoiceId = $invoiceId.Replace("'", "\'")
    $safeEditProcessId = $editProcessId.Replace("'", "\'")
    $stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$invoice = think\facade\Db::name('biz_sale_project_invoice')
    ->where('ID', '$safeInvoiceId')
    ->find();
`$invoiceItems = `$invoice ? think\facade\Db::name('biz_sale_project_invoice_item')->where('INVOICE_ID', (string)`$invoice['ID'])->select()->toArray() : [];
echo json_encode([
    'project' => think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->find(),
    'followProject' => think\facade\Db::name('biz_sale_project')->where('ID', '$safeFollowProjectId')->find(),
    'item' => think\facade\Db::name('biz_sale_project_product_item')->where('ID', '$safeItemId')->find(),
    'invoice' => `$invoice,
    'invoiceItems' => `$invoiceItems,
    'deliveryRecords' => think\facade\Db::name('delivery_record')->whereIn('PROCESS_ID', ['$safeProcessId', '$safeEditProcessId'])->select()->toArray(),
    'inventory' => think\facade\Db::name('inventory')->where('ID', '$safeInventoryId')->find(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $state = Invoke-PhpJson -Code $stateCode
    Assert-Equal -Actual ([string]$state.invoice.ID) -Expected $invoiceId -Name 'invoice DB id'
    Assert-Equal -Actual ([string]$state.invoice.PROJECT_ID) -Expected $ProjectId -Name 'invoice DB project id'
    Assert-Equal -Actual ([string]$state.invoice.PROCESS_ID) -Expected $editProcessId -Name 'invoice DB process id'
    Assert-Equal -Actual ([string]$state.invoice.CONSIGNEE) -Expected 'Codex edited consignee' -Name 'invoice DB consignee'
    Assert-Equal -Actual ([string]$state.invoice.LOGISTICS_CATEGORY) -Expected 'TRUCK' -Name 'invoice DB logistics category'
    Assert-Equal -Actual ([string]$state.invoice.PHONE) -Expected '18800000004' -Name 'invoice DB phone'
    Assert-Equal -Actual ([string]$state.invoice.FREIGHT_CATEGORY) -Expected 'SELLER_PAY' -Name 'invoice DB freight category'
    Assert-Equal -Actual ([string]$state.invoice.UNIT) -Expected 'Codex edited unit' -Name 'invoice DB unit'
    Assert-Equal -Actual ([string]$state.invoice.ADDRESS) -Expected 'Codex edited address' -Name 'invoice DB address'
    Assert-Equal -Actual ([string]$state.invoice.REMARK) -Expected 'direct invoice edit smoke' -Name 'invoice DB remark'
    Assert-Equal -Actual ([string]$state.invoice.OPERATOR) -Expected $UserId -Name 'invoice DB operator'
    Assert-DecimalEqual -Actual ([string]$state.invoice.FREIGHT) -Expected 12.34 -Name 'invoice DB freight'
    Assert-IntEqual -Actual (@($state.invoiceItems).Count) -Expected 1 -Name 'invoice DB item count'
    $invoiceItem = @($state.invoiceItems)[0]
    Assert-Equal -Actual ([string]$invoiceItem.PROJECT_PRODUCT_ITEM_ID) -Expected $ItemId -Name 'invoice item DB product item id'
    Assert-Equal -Actual ([string]$invoiceItem.WAREHOUSES_ID) -Expected $WarehouseId -Name 'invoice item DB warehouse'
    Assert-DecimalEqual -Actual ([string]$invoiceItem.AMOUNT) -Expected 3 -Name 'invoice item DB amount'
    Assert-DecimalEqual -Actual ([string]$state.item.DELIVERY) -Expected 3 -Name 'product item delivery'
    Assert-Equal -Actual ([string]$state.item.STATE) -Expected 'PART_WAIT_DELIVER' -Name 'product item state'
    Assert-Equal -Actual ([string]$state.project.PROJECT_STATE) -Expected 'PARTIALLY_SHIPPED' -Name 'project state'
    Assert-Equal -Actual ([string]$state.followProject.PROJECT_STATE) -Expected 'FOLLOW' -Name 'follow project unchanged'
    Assert-IntEqual -Actual (@($state.deliveryRecords).Count) -Expected 0 -Name 'direct invoice delivery records'
    Assert-DecimalEqual -Actual ([string]$state.inventory.CURRENT_COUNT) -Expected 10 -Name 'inventory unchanged'

    $readback = Invoke-JsonGet -Url ($baseUrl + '/biz/saleprojectinvoice/list?projectId=' + (Enc $ProjectId)) -Token $token
    Assert-Code -Json $readback -Expected 200 -Name 'invoice list readback'
    Assert-IntEqual -Actual (@($readback.data).Count) -Expected 1 -Name 'invoice list count'
    Assert-Equal -Actual ([string]$readback.data[0].bizSaleProjectInvoice.id) -Expected ([string]$state.invoice.ID) -Name 'invoice list id'
    Assert-Equal -Actual ([string]$readback.data[0].bizSaleProjectInvoice.processId) -Expected $editProcessId -Name 'invoice list process id'
    Assert-IntEqual -Actual (@($readback.data[0].invoiceItems).Count) -Expected 1 -Name 'invoice list nested item count'

    $itemPage = Invoke-JsonGet -Url ($baseUrl + '/biz/saleprojectinvoiceItem/page?invoiceId=' + (Enc ([string]$state.invoice.ID)) + '&current=1&size=10') -Token $token
    Assert-Code -Json $itemPage -Expected 200 -Name 'invoice item page readback'
    Assert-IntEqual -Actual ([int]$itemPage.data.total) -Expected 1 -Name 'invoice item page total'

    $afterEditSideEffects = Invoke-PhpJson -Code $snapshotCode
    Assert-SideEffectsUnchanged -Before $beforeSideEffects -After $afterEditSideEffects

    $deleteNoToken = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectinvoice/delete') -Body @(@{ id = $invoiceId })
    Assert-Code -Json $deleteNoToken -Expected 401 -Name 'invoice delete no-token'

    $deleteMissing = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectinvoice/delete') -Token $token -Body @{}
    Assert-Code -Json $deleteMissing -Expected 400 -Name 'invoice delete missing idList'

    $mixedDelete = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectinvoice/delete') -Token $token -Body @{
        idList = @($invoiceId, "missing-$seed")
    }
    Assert-Code -Json $mixedDelete -Expected 404 -Name 'invoice delete mixed missing rollback'

    $rollbackState = Invoke-PhpJson -Code $stateCode
    Assert-Equal -Actual ([string]$rollbackState.invoice.DELETE_FLAG) -Expected 'NOT_DELETE' -Name 'invoice mixed delete rollback flag'
    Assert-IntEqual -Actual (@($rollbackState.invoiceItems).Count) -Expected 1 -Name 'invoice mixed delete rollback item count'
    Assert-Equal -Actual ([string](@($rollbackState.invoiceItems)[0]).DELETE_FLAG) -Expected 'NOT_DELETE' -Name 'invoice item mixed delete rollback flag'
    Assert-DecimalEqual -Actual ([string]$rollbackState.item.DELIVERY) -Expected 3 -Name 'product item delivery mixed delete rollback'
    Assert-Equal -Actual ([string]$rollbackState.project.PROJECT_STATE) -Expected 'PARTIALLY_SHIPPED' -Name 'project state mixed delete rollback'

    $delete = Invoke-JsonPost -Url ($baseUrl + '/biz/saleprojectinvoice/delete') -Token $token -Body @(@{ id = $invoiceId })
    Assert-Code -Json $delete -Expected 200 -Name 'invoice delete'
    Assert-IntEqual -Actual ([int]$delete.data.count) -Expected 1 -Name 'invoice delete count'
    Assert-IntEqual -Actual ([int]$delete.data.invoiceItemCount) -Expected 1 -Name 'invoice delete item count'
    Assert-Equal -Actual ([string]$delete.data.projectStates.$ProjectId) -Expected 'WAIT_DELIVER' -Name 'invoice delete project state response'

    $deletedState = Invoke-PhpJson -Code $stateCode
    Assert-Equal -Actual ([string]$deletedState.invoice.DELETE_FLAG) -Expected 'DELETED' -Name 'invoice deleted flag'
    Assert-IntEqual -Actual (@($deletedState.invoiceItems).Count) -Expected 1 -Name 'deleted invoice item count'
    Assert-Equal -Actual ([string](@($deletedState.invoiceItems)[0]).DELETE_FLAG) -Expected 'DELETED' -Name 'invoice item deleted flag'
    Assert-DecimalEqual -Actual ([string]$deletedState.item.DELIVERY) -Expected 0 -Name 'product item delivery reversed'
    Assert-Equal -Actual ([string]$deletedState.item.STATE) -Expected 'WAIT_DELIVER' -Name 'product item state reversed'
    Assert-Equal -Actual ([string]$deletedState.project.PROJECT_STATE) -Expected 'WAIT_DELIVER' -Name 'project state reversed'
    Assert-Equal -Actual ([string]$deletedState.followProject.PROJECT_STATE) -Expected 'FOLLOW' -Name 'follow project unchanged after delete'
    Assert-IntEqual -Actual (@($deletedState.deliveryRecords).Count) -Expected 0 -Name 'direct invoice delete delivery records'
    Assert-DecimalEqual -Actual ([string]$deletedState.inventory.CURRENT_COUNT) -Expected 10 -Name 'inventory unchanged after delete'

    $deleteReadback = Invoke-JsonGet -Url ($baseUrl + '/biz/saleprojectinvoice/list?projectId=' + (Enc $ProjectId)) -Token $token
    Assert-Code -Json $deleteReadback -Expected 200 -Name 'invoice list after delete'
    $deleteReadbackCount = if ($null -eq $deleteReadback.data) { 0 } else { @($deleteReadback.data).Count }
    Assert-IntEqual -Actual ([int]$deleteReadbackCount) -Expected 0 -Name 'invoice list after delete count'

    $deletedItemPage = Invoke-JsonGet -Url ($baseUrl + '/biz/saleprojectinvoiceItem/page?invoiceId=' + (Enc $invoiceId) + '&current=1&size=10') -Token $token
    Assert-Code -Json $deletedItemPage -Expected 200 -Name 'invoice item page after delete'
    Assert-IntEqual -Actual ([int]$deletedItemPage.data.total) -Expected 0 -Name 'invoice item page after delete total'

    $afterDeleteSideEffects = Invoke-PhpJson -Code $snapshotCode
    Assert-SideEffectsUnchanged -Before $beforeSideEffects -After $afterDeleteSideEffects

    Write-Host 'sale-project invoice add/edit/delete HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
