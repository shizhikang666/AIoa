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

function Invoke-PhpJson {
    param([Parameter(Mandatory = $true)][string]$Code)

    $raw = (& php -r $Code)
    if ($LASTEXITCODE -ne 0) {
        throw 'php code failed'
    }

    return (($raw -join "`n").TrimStart([char]0xFEFF) | ConvertFrom-Json)
}

function Invoke-JsonPost {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [string]$Token = '',
        [object]$Body = @{}
    )

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("workflow-project-reissue-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    $json = $Body | ConvertTo-Json -Depth 16
    $utf8NoBom = New-Object System.Text.UTF8Encoding -ArgumentList $false
    [System.IO.File]::WriteAllText($bodyPath, $json, $utf8NoBom)
    try {
        $args = @('-sS', '-X', 'POST', $Url, '-H', 'Content-Type: application/json', '--data-binary', "@$bodyPath")
        if ($Token -ne '') {
            $args = @('-sS', '-X', 'POST', $Url, '-H', "Authorization: Bearer $Token", '-H', 'Content-Type: application/json', '--data-binary', "@$bodyPath")
        }

        $raw = (& curl.exe @args)
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

    $raw = (& curl.exe -sS -X GET $Url -H "Authorization: Bearer $Token")
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

function Get-MapValue {
    param(
        [Parameter(Mandatory = $true)][object]$Map,
        [Parameter(Mandatory = $true)][string]$Key
    )

    $property = $Map.PSObject.Properties[$Key]
    if ($null -eq $property) {
        return $null
    }

    return $property.Value
}

function New-SqlLiteralList {
    param([string[]]$Values)

    return @($Values | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
}

function Remove-SmokeRows {
    param(
        [string[]]$ProcessInstanceIds,
        [string[]]$ProjectIds,
        [string]$CustomerId,
        [string[]]$ProductIds
    )

    $safeProcessIds = New-SqlLiteralList -Values $ProcessInstanceIds
    $safeProjectIds = New-SqlLiteralList -Values $ProjectIds
    $safeProductIds = New-SqlLiteralList -Values $ProductIds
    $safeCustomerId = $CustomerId.Replace("'", "\'")
    $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pids = [$safeProcessIds];
`$projectIds = [$safeProjectIds];
`$productIds = [$safeProductIds];
if (`$pids !== []) {
    `$orderIds = think\facade\Db::name('biz_sale_project_reissue_order')->whereIn('PROCESS_ID', `$pids)->column('ID');
    if (`$orderIds !== []) {
        `$itemIds = think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_REISSUE_ORDER_ID', `$orderIds)->column('ID');
        if (`$itemIds !== []) {
            think\facade\Db::name('sale_project_product_item_relation')->whereIn('OBJECT_ID', `$itemIds)->delete();
        }
        think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_REISSUE_ORDER_ID', `$orderIds)->delete();
        think\facade\Db::name('biz_sale_project_reissue_order')->whereIn('ID', `$orderIds)->delete();
    }
    `$invoiceIds = think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROCESS_ID', `$pids)->column('ID');
    if (`$invoiceIds !== []) {
        think\facade\Db::name('biz_sale_project_invoice_item')->whereIn('INVOICE_ID', `$invoiceIds)->delete();
    }
    think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROCESS_ID', `$pids)->delete();
    think\facade\Db::name('biz_sale_project_invoicing')->whereIn('PROCESS_ID', `$pids)->delete();
    think\facade\Db::name('delivery_record')->whereIn('PROCESS_ID', `$pids)->delete();
    think\facade\Db::name('biz_payment_record')->whereIn('PROCESS_ID', `$pids)->delete();
    think\facade\Db::name('biz_expenditure_record')->whereIn('PROCESS_ID', `$pids)->delete();
    think\facade\Db::name('settlement_account_statement')->whereIn('PROCESS_ID', `$pids)->delete();
    think\facade\Db::name('act_ru_task')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_ru_variable')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_varinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_taskinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_actinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_procinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_ru_execution')->whereIn('PROC_INST_ID_', `$pids)->whereNotIn('ID_', `$pids)->delete();
    think\facade\Db::name('act_ru_execution')->whereIn('ID_', `$pids)->delete();
    think\facade\Db::name('biz_cc_records')->whereIn('INSTANCE_ID', `$pids)->delete();
    think\facade\Db::name('biz_file_relation')->whereIn('OBJECT_ID', `$pids)->delete();
}
if (`$projectIds !== []) {
    `$orderIds = think\facade\Db::name('biz_sale_project_reissue_order')->whereIn('PROJECT_ID', `$projectIds)->column('ID');
    if (`$orderIds !== []) {
        `$itemIds = think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_REISSUE_ORDER_ID', `$orderIds)->column('ID');
        if (`$itemIds !== []) {
            think\facade\Db::name('sale_project_product_item_relation')->whereIn('OBJECT_ID', `$itemIds)->delete();
        }
        think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_REISSUE_ORDER_ID', `$orderIds)->delete();
        think\facade\Db::name('biz_sale_project_reissue_order')->whereIn('ID', `$orderIds)->delete();
    }
    `$itemIds = think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->column('ID');
    if (`$itemIds !== []) {
        think\facade\Db::name('sale_project_product_item_relation')->whereIn('OBJECT_ID', `$itemIds)->delete();
    }
    `$invoiceIds = think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROJECT_ID', `$projectIds)->column('ID');
    if (`$invoiceIds !== []) {
        think\facade\Db::name('biz_sale_project_invoice_item')->whereIn('INVOICE_ID', `$invoiceIds)->delete();
    }
    think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_sale_project_invoicing')->whereIn('PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('delivery_record')->whereIn('OBJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->delete();
}
if (`$productIds !== []) {
    think\facade\Db::name('biz_product')->whereIn('ID', `$productIds)->delete();
}
if ('$safeCustomerId' !== '') {
    think\facade\Db::name('customer')->where('ID', '$safeCustomerId')->delete();
}
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    [void](Invoke-PhpJson -Code $cleanupCode)
}

function Get-State {
    param(
        [string[]]$ProcessInstanceIds,
        [string[]]$ProjectIds
    )

    $safeProcessIds = New-SqlLiteralList -Values $ProcessInstanceIds
    $safeProjectIds = New-SqlLiteralList -Values $ProjectIds
    $stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pids = [$safeProcessIds];
`$projectIds = [$safeProjectIds];
`$projects = [];
if (`$projectIds !== []) {
    foreach (think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->select()->toArray() as `$row) {
        `$projects[(string)`$row['ID']] = `$row;
    }
}
`$processes = [];
if (`$pids !== []) {
    foreach (`$pids as `$processId) {
        `$processes[`$processId] = [
            'ruTask' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$processId)->count(),
            'ruVariable' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$processId)->count(),
            'ruExecution' => think\facade\Db::name('act_ru_execution')->where('PROC_INST_ID_', `$processId)->count(),
            'hiProc' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$processId)->field('PROC_DEF_KEY_,STATE_,END_TIME_')->find(),
        ];
    }
}
`$reissueOrders = `$pids !== [] ? think\facade\Db::name('biz_sale_project_reissue_order')->whereIn('PROCESS_ID', `$pids)->select()->toArray() : [];
`$orderIds = array_values(array_map(static fn (`$row) => (string)`$row['ID'], `$reissueOrders));
`$reissueItems = `$orderIds !== [] ? think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_REISSUE_ORDER_ID', `$orderIds)->select()->toArray() : [];
`$itemIds = array_values(array_map(static fn (`$row) => (string)`$row['ID'], `$reissueItems));
`$relations = `$itemIds !== [] ? think\facade\Db::name('sale_project_product_item_relation')->whereIn('OBJECT_ID', `$itemIds)->select()->toArray() : [];
`$invoiceIds = `$pids !== [] ? think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROCESS_ID', `$pids)->column('ID') : [];
echo json_encode([
    'projects' => `$projects,
    'processes' => `$processes,
    'reissueOrders' => `$reissueOrders,
    'reissueItems' => `$reissueItems,
    'relations' => `$relations,
    'deliveryRecordCount' => `$pids !== [] ? think\facade\Db::name('delivery_record')->whereIn('PROCESS_ID', `$pids)->count() : 0,
    'invoiceCount' => `$pids !== [] ? think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROCESS_ID', `$pids)->count() : 0,
    'invoiceItemCount' => `$invoiceIds !== [] ? think\facade\Db::name('biz_sale_project_invoice_item')->whereIn('INVOICE_ID', `$invoiceIds)->count() : 0,
    'invoicingCount' => `$pids !== [] ? think\facade\Db::name('biz_sale_project_invoicing')->whereIn('PROCESS_ID', `$pids)->count() : 0,
    'paymentCount' => `$pids !== [] ? think\facade\Db::name('biz_payment_record')->whereIn('PROCESS_ID', `$pids)->count() : 0,
    'expenditureCount' => `$pids !== [] ? think\facade\Db::name('biz_expenditure_record')->whereIn('PROCESS_ID', `$pids)->count() : 0,
    'statementCount' => `$pids !== [] ? think\facade\Db::name('settlement_account_statement')->whereIn('PROCESS_ID', `$pids)->count() : 0,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    return Invoke-PhpJson -Code $stateCode
}

function New-StartBody {
    param([Parameter(Mandatory = $true)][string]$ProjectId)

    return @{
        projectId = $ProjectId
        approveUserIdList = @($script:UserId)
        copyUserIdList = @()
        fileIdList = @()
        productList = @(@{
            productId = $script:ProductId
            productName = "$($script:Prefix) product"
            number = 2
            unitPrice = '10.00'
            discountRate = '0.00'
            price = '20.00'
            remark = "$($script:Prefix) item"
            children = @(@{
                productId = $script:ChildProductId
                productName = "$($script:Prefix) child"
                number = 1
                remark = "$($script:Prefix) child"
            })
        })
        amount = '20.00'
        remark = "$($script:Prefix) reissue"
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
$Prefix = "codex-wf-reissue-$seed"
$CustomerId = "C$seed"
$ProjectCancelId = "PC$seed"
$ProjectRejectId = "PR$seed"
$ProjectApproveId = "PA$seed"
$ProductId = "P$seed"
$ChildProductId = "H$seed"
$processIds = @()
$UserId = ''

try {
    Remove-SmokeRows -ProcessInstanceIds @() -ProjectIds @($ProjectCancelId, $ProjectRejectId, $ProjectApproveId) -CustomerId $CustomerId -ProductIds @($ProductId, $ChildProductId)

    $safeAccount = $account.Replace("'", "\'")
    $safePrefix = $Prefix.Replace("'", "\'")
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
    'ID' => '$CustomerId',
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
foreach ([['$ProductId', '$safePrefix product'], ['$ChildProductId', '$safePrefix child']] as `$product) {
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
        'SPECS' => 'smoke',
        'ORG' => `$orgId !== '' ? `$orgId : null,
        'status' => 'ENABLE',
    ]);
}
foreach ([['$ProjectCancelId', 'cancel'], ['$ProjectRejectId', 'reject'], ['$ProjectApproveId', 'approve']] as `$project) {
    think\facade\Db::name('biz_sale_project')->insert([
        'ID' => `$project[0],
        'CUSTOMER' => '$CustomerId',
        'PROJECT_NAME' => '$safePrefix ' . `$project[1],
        'PROJECT_STATE' => 'WAIT_DELIVER',
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
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_WORKFLOW_PROJECT_REISSUE_APPROVE_SMOKE';
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

    $noToken = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/reissue/start') -Body (New-StartBody -ProjectId $ProjectCancelId)
    Assert-Code -Json $noToken -Expected 401 -Name 'project reissue start no-token'

    $missingProductList = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/reissue/start') -Token $token -Body @{
        projectId = $ProjectCancelId
        approveUserIdList = @($UserId)
        copyUserIdList = @()
        amount = '20.00'
        remark = "$Prefix missing productList"
    }
    Assert-Code -Json $missingProductList -Expected 400 -Name 'project reissue missing productList'

    $negativeAmount = New-StartBody -ProjectId $ProjectCancelId
    $negativeAmount.amount = '-1.00'
    $negative = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/reissue/start') -Token $token -Body $negativeAmount
    Assert-Code -Json $negative -Expected 400 -Name 'project reissue negative amount'

    $cancelStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/reissue/start') -Token $token -Body (New-StartBody -ProjectId $ProjectCancelId)
    Assert-Code -Json $cancelStart -Expected 200 -Name 'project reissue cancel start'
    $cancelProcessId = [string]$cancelStart.data.processInstanceId
    $processIds += $cancelProcessId
    Assert-Equal -Actual ([string]$cancelStart.data.processKey) -Expected 'Process_project_reissue_product' -Name 'project reissue cancel process key'
    $cancel = Invoke-JsonPost -Url ($baseUrl + '/biz/process/cancel') -Token $token -Body @{ id = $cancelProcessId }
    Assert-Code -Json $cancel -Expected 200 -Name 'project reissue cancel'

    $rejectStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/reissue/start') -Token $token -Body (New-StartBody -ProjectId $ProjectRejectId)
    Assert-Code -Json $rejectStart -Expected 200 -Name 'project reissue reject start'
    $rejectProcessId = [string]$rejectStart.data.processInstanceId
    $processIds += $rejectProcessId
    $reject = Invoke-JsonPost -Url ($baseUrl + '/biz/task/reject') -Token $token -Body @{
        id = [string]$rejectStart.data.taskId
        form = @{ comment = "$Prefix reject" }
    }
    Assert-Code -Json $reject -Expected 200 -Name 'project reissue reject'

    $approveStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/reissue/start') -Token $token -Body (New-StartBody -ProjectId $ProjectApproveId)
    Assert-Code -Json $approveStart -Expected 200 -Name 'project reissue approve start'
    $approveProcessId = [string]$approveStart.data.processInstanceId
    $processIds += $approveProcessId
    $approve = Invoke-JsonPost -Url ($baseUrl + '/biz/task/approve') -Token $token -Body @{
        id = [string]$approveStart.data.taskId
        form = @{
            approval = $true
            comment = "$Prefix approve"
        }
    }
    Assert-Code -Json $approve -Expected 200 -Name 'project reissue approve'
    Assert-Equal -Actual ([string]$approve.data.processKey) -Expected 'Process_project_reissue_product' -Name 'project reissue approve process key'
    Assert-IntEqual -Actual ([int]$approve.data.saleProjectReissue.productItemCount) -Expected 1 -Name 'project reissue response product item count'
    Assert-IntEqual -Actual ([int]$approve.data.saleProjectReissue.relationCount) -Expected 1 -Name 'project reissue response relation count'

    $state = Get-State -ProcessInstanceIds $processIds -ProjectIds @($ProjectCancelId, $ProjectRejectId, $ProjectApproveId)
    foreach ($processId in $processIds) {
        $process = Get-MapValue -Map $state.processes -Key $processId
        Assert-IntEqual -Actual ([int]$process.ruTask) -Expected 0 -Name "runtime task count $processId"
        Assert-IntEqual -Actual ([int]$process.ruVariable) -Expected 0 -Name "runtime variable count $processId"
        Assert-IntEqual -Actual ([int]$process.ruExecution) -Expected 0 -Name "runtime execution count $processId"
        Assert-Equal -Actual ([string]$process.hiProc.PROC_DEF_KEY_) -Expected 'Process_project_reissue_product' -Name "history process key $processId"
        Assert-Equal -Actual ([string]$process.hiProc.STATE_) -Expected 'COMPLETED' -Name "history process state $processId"
    }

    Assert-IntEqual -Actual (@($state.reissueOrders).Count) -Expected 1 -Name 'reissue order row count'
    Assert-IntEqual -Actual (@($state.reissueItems).Count) -Expected 1 -Name 'reissue item row count'
    Assert-IntEqual -Actual (@($state.relations).Count) -Expected 1 -Name 'reissue relation row count'
    Assert-IntEqual -Actual ([int]$state.deliveryRecordCount) -Expected 0 -Name 'delivery record side-effect count'
    Assert-IntEqual -Actual ([int]$state.invoiceCount) -Expected 0 -Name 'invoice side-effect count'
    Assert-IntEqual -Actual ([int]$state.invoiceItemCount) -Expected 0 -Name 'invoice item side-effect count'
    Assert-IntEqual -Actual ([int]$state.invoicingCount) -Expected 0 -Name 'invoicing side-effect count'
    Assert-IntEqual -Actual ([int]$state.paymentCount) -Expected 0 -Name 'payment side-effect count'
    Assert-IntEqual -Actual ([int]$state.expenditureCount) -Expected 0 -Name 'expenditure side-effect count'
    Assert-IntEqual -Actual ([int]$state.statementCount) -Expected 0 -Name 'statement side-effect count'

    $order = @($state.reissueOrders)[0]
    $item = @($state.reissueItems)[0]
    $relation = @($state.relations)[0]
    $responseOrderId = [string]$approve.data.saleProjectReissue.reissueOrderId
    Assert-Equal -Actual ([string]$order.ID) -Expected $responseOrderId -Name 'reissue order id'
    Assert-Equal -Actual ([string]$order.PROJECT_ID) -Expected $ProjectApproveId -Name 'reissue order project id'
    Assert-Equal -Actual ([string]$order.PROCESS_ID) -Expected $approveProcessId -Name 'reissue order process id'
    Assert-DecimalEqual -Actual ([string]$order.AMOUNT) -Expected 20.00 -Name 'reissue order amount'
    Assert-Equal -Actual ([string]$item.PROJECT_ID) -Expected $ProjectApproveId -Name 'reissue item project id'
    Assert-Equal -Actual ([string]$item.PRODUCT_ID) -Expected $ProductId -Name 'reissue item product id'
    Assert-Equal -Actual ([string]$item.CATEGORY) -Expected 'REISSUE_ORDER' -Name 'reissue item category'
    Assert-Equal -Actual ([string]$item.STATE) -Expected 'WAIT_DELIVER' -Name 'reissue item state'
    Assert-Equal -Actual ([string]$item.PROJECT_REISSUE_ORDER_ID) -Expected $responseOrderId -Name 'reissue item order id'
    Assert-DecimalEqual -Actual ([string]$item.NUMBER) -Expected 2 -Name 'reissue item number'
    Assert-DecimalEqual -Actual ([string]$item.DELIVERY) -Expected 0 -Name 'reissue item delivery'
    Assert-DecimalEqual -Actual ([string]$item.UNIT_PRICE) -Expected 10.00 -Name 'reissue item unit price'
    Assert-DecimalEqual -Actual ([string]$item.PRICE) -Expected 20.00 -Name 'reissue item price'
    Assert-Equal -Actual ([string]$relation.OBJECT_ID) -Expected ([string]$item.ID) -Name 'reissue relation object id'
    Assert-Equal -Actual ([string]$relation.TARGET_ID) -Expected $ChildProductId -Name 'reissue relation target id'
    Assert-DecimalEqual -Actual ([string]$relation.NUMBER) -Expected 1 -Name 'reissue relation number'

    foreach ($projectId in @($ProjectCancelId, $ProjectRejectId, $ProjectApproveId)) {
        $project = Get-MapValue -Map $state.projects -Key $projectId
        Assert-Equal -Actual ([string]$project.PROJECT_STATE) -Expected 'WAIT_DELIVER' -Name "$projectId project state"
        Assert-Equal -Actual ([string]$project.PLAY_STATE) -Expected 'UNPAID' -Name "$projectId play state"
        Assert-DecimalEqual -Actual ([string]$project.TOTAL_PRICE) -Expected 100.00 -Name "$projectId total price"
    }

    $readback = Invoke-JsonGet -Url ($baseUrl + '/biz/saleprojectreissueorder/list/query?projectId=' + (Enc $ProjectApproveId)) -Token $token
    Assert-Code -Json $readback -Expected 200 -Name 'project reissue readback list/query'
    Assert-IntEqual -Actual (@($readback.data).Count) -Expected 1 -Name 'project reissue readback order count'
    $readbackRow = @($readback.data)[0]
    Assert-Equal -Actual ([string]$readbackRow.order.id) -Expected $responseOrderId -Name 'project reissue readback order id'
    Assert-IntEqual -Actual (@($readbackRow.productItemList).Count) -Expected 1 -Name 'project reissue readback item count'
    Assert-IntEqual -Actual (@($readbackRow.productItemList[0].children).Count) -Expected 1 -Name 'project reissue readback child count'

    Write-Host 'workflow project reissue approve smoke passed'
} finally {
    Remove-SmokeRows -ProcessInstanceIds $processIds -ProjectIds @($ProjectCancelId, $ProjectRejectId, $ProjectApproveId) -CustomerId $CustomerId -ProductIds @($ProductId, $ChildProductId)
}
