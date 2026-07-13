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
        $value = $parts[1].Trim()
        if (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'"))) {
            $value = $value.Substring(1, $value.Length - 2)
        }
        $map[$parts[0].Trim()] = $value
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

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("workflow-project-init-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
        [Parameter(Mandatory = $true)][AllowEmptyString()][string]$Actual,
        [Parameter(Mandatory = $true)][AllowEmptyString()][string]$Expected,
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

function Remove-SmokeRows {
    param(
        [string[]]$ProcessInstanceIds,
        [string[]]$ProjectIds,
        [string[]]$FileIds,
        [string]$CustomerId,
        [string]$ProductId,
        [string]$AccountId
    )

    $safeProcessIds = @($ProcessInstanceIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
    $safeProjectIds = @($ProjectIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
    $safeFileIds = @($FileIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
    $safeCustomerId = $CustomerId.Replace("'", "\'")
    $safeProductId = $ProductId.Replace("'", "\'")
    $safeAccountId = $AccountId.Replace("'", "\'")
    $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$pids = [$safeProcessIds];
`$projectIds = [$safeProjectIds];
`$fileIds = [$safeFileIds];
if (`$pids !== []) {
    think\facade\Db::name('act_ru_task')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_ru_variable')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_varinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_taskinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_actinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_hi_procinst')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('act_ru_execution')->whereIn('PROC_INST_ID_', `$pids)->delete();
    think\facade\Db::name('biz_cc_records')->whereIn('INSTANCE_ID', `$pids)->delete();
    think\facade\Db::name('biz_file_relation')->whereIn('OBJECT_ID', `$pids)->delete();
    think\facade\Db::name('biz_sale_project_invoicing')->whereIn('PROCESS_ID', `$pids)->delete();
}
if (`$projectIds !== []) {
    `$itemIds = think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->column('ID');
    if (`$itemIds !== []) {
        think\facade\Db::name('sale_project_product_item_relation')->whereIn('OBJECT_ID', `$itemIds)->delete();
    }
    think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_file_relation')->whereIn('OBJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_sale_project_invoicing')->whereIn('PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->delete();
}
if (`$fileIds !== []) {
    think\facade\Db::name('biz_file_relation')->whereIn('TARGET_ID', `$fileIds)->delete();
    think\facade\Db::name('dev_file')->whereIn('ID', `$fileIds)->delete();
}
if ('$safeProductId' !== '') {
    think\facade\Db::name('biz_product')->where('ID', '$safeProductId')->delete();
}
if ('$safeAccountId' !== '') {
    think\facade\Db::name('settlement_account')->where('ID', '$safeAccountId')->delete();
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
        [string[]]$ProjectIds,
        [string]$CustomerId
    )

    $safeProcessIds = @($ProcessInstanceIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
    $safeProjectIds = @($ProjectIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ','
    $safeCustomerId = $CustomerId.Replace("'", "\'")
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
    foreach (`$pids as `$pid) {
        `$processes[`$pid] = [
            'ruTask' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->count(),
            'ruVariable' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$pid)->count(),
            'ruExecution' => think\facade\Db::name('act_ru_execution')->where('PROC_INST_ID_', `$pid)->count(),
            'hiProc' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->field('PROC_DEF_KEY_,STATE_,END_TIME_')->find(),
        ];
    }
}
`$items = `$projectIds !== [] ? think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->select()->toArray() : [];
`$invoicing = `$projectIds !== [] ? think\facade\Db::name('biz_sale_project_invoicing')->whereIn('PROJECT_ID', `$projectIds)->select()->toArray() : [];
`$relations = [];
foreach (think\facade\Db::name('biz_file_relation')->where(function (`$query) use (`$pids, `$projectIds) {
    if (`$pids !== []) { `$query->whereIn('OBJECT_ID', `$pids); }
    if (`$projectIds !== []) { `$query->whereOr('OBJECT_ID', 'in', `$projectIds); }
})->select()->toArray() as `$row) {
    `$relations[] = `$row;
}
echo json_encode([
    'projects' => `$projects,
    'processes' => `$processes,
    'items' => `$items,
    'invoicing' => `$invoicing,
    'relations' => `$relations,
    'customer' => think\facade\Db::name('customer')->where('ID', '$safeCustomerId')->find(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    return Invoke-PhpJson -Code $stateCode
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
$prefix = "codex-wf-project-$seed"
$customerId = "C$seed"
$projectCancelId = "PC$seed"
$projectRejectId = "PR$seed"
$projectApproveId = "PA$seed"
$productId = "PD$seed"
$fileCancelId = "FC$seed"
$fileRejectId = "FR$seed"
$fileApproveId = "FA$seed"
$accountId = "SA$seed"
$processIds = @()

try {
    Remove-SmokeRows -ProcessInstanceIds @() -ProjectIds @($projectCancelId, $projectRejectId, $projectApproveId) -FileIds @($fileCancelId, $fileRejectId, $fileApproveId) -CustomerId $customerId -ProductId $productId -AccountId $accountId

    $safeAccount = $account.Replace("'", "\'")
    $safePrefix = $prefix.Replace("'", "\'")
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
    'ID' => '$customerId',
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
think\facade\Db::name('settlement_account')->insert([
    'ID' => '$accountId',
    'ACCOUNT_NAME' => '$safePrefix account',
    'ACCOUNT_NUMBER' => '$seed',
    'INITIAL_AMOUNT' => '0.00',
    'CURRENT_AMOUNT' => '0.00',
    'ACCOUNT_STATUS' => 'ENABLE',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'org' => `$orgId !== '' ? `$orgId : null,
    'VERSION' => 0,
]);
think\facade\Db::name('biz_product')->insert([
    'ID' => '$productId',
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
    'SPECS' => 'smoke',
    'ORG' => `$orgId !== '' ? `$orgId : null,
    'status' => 'ENABLE',
]);
foreach ([['$projectCancelId', 'cancel'], ['$projectRejectId', 'reject'], ['$projectApproveId', 'approve']] as `$project) {
    think\facade\Db::name('biz_sale_project')->insert([
        'ID' => `$project[0],
        'CUSTOMER' => '$customerId',
        'PROJECT_NAME' => '$safePrefix ' . `$project[1],
        'PROJECT_STATE' => 'FOLLOW',
        'PLAY_STATE' => 'UNPAID',
        'VISIBILITY' => 'PRIVATE',
        'INIT_PRICE' => '0.00',
        'TOTAL_PRICE' => '0.00',
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
foreach ([['$fileCancelId', 'cancel'], ['$fileRejectId', 'reject'], ['$fileApproveId', 'approve']] as `$file) {
    `$name = '$safePrefix-' . `$file[1] . '.txt';
    think\facade\Db::name('dev_file')->insert([
        'ID' => `$file[0],
        'ENGINE' => 'LOCAL',
        'BUCKET' => 'defaultBucketName',
        'NAME' => `$name,
        'SUFFIX' => 'txt',
        'SIZE_KB' => 1,
        'SIZE_INFO' => '1KB',
        'OBJ_NAME' => `$name,
        'STORAGE_PATH' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . `$name,
        'DOWNLOAD_PATH' => '/api/dev/file/download?id=' . `$file[0],
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => `$userId,
        'TENANT_ID' => `$tenantId,
    ]);
}
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_WORKFLOW_PROJECT_INIT_APPROVE_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => `$userId,
    'tenantId' => `$tenantId,
    'orgId' => `$orgId,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    $context = Invoke-PhpJson -Code $setupCode
    $token = [string]$context.token
    $userId = [string]$context.userId
    $tenantId = [string]$context.tenantId
    if ([string]::IsNullOrWhiteSpace($token) -or [string]::IsNullOrWhiteSpace($userId) -or [string]::IsNullOrWhiteSpace($tenantId)) {
        throw 'failed to create local smoke auth token'
    }

    function New-StartBody {
        param(
            [Parameter(Mandatory = $true)][string]$ProjectId,
            [Parameter(Mandatory = $true)][string]$FileId,
            [bool]$IsInvoicing = $false
        )

        $body = @{
            bizSaleProjectId = $ProjectId
            approveUserIdList = @($userId)
            copyUserIdList = @()
            fileIdList = @($FileId)
            productList = @(@{
                productId = $productId
                number = 2
                unitPrice = '15.00'
                discountRate = '10.00'
                price = '27.00'
                remark = "$prefix product line"
            })
            consignee = "$prefix consignee"
            phone = '18800000000'
            unit = "$prefix unit"
            address = "$prefix address"
            logisticsCategory = 'EXPRESS'
            deliveryNote = "$prefix delivery"
            freight = '3.50'
            freightCategory = 'BUYER_PAY'
            accountId = $accountId
            payerCategory = 'FULL_PAYMENT'
            initPrice = '27.00'
            rebateAmount = '1.25'
            completionDate = '2026-06-22 10:11:12'
            isInvoicing = $IsInvoicing
            tenantId = $tenantId
        }
        if ($IsInvoicing) {
            $body.invoicingInfo = @{
                invoicingCategory = 'SpecialTicket'
                amount = '12.34'
                companyName = "$prefix invoice company"
                customerCompany = "$prefix customer company"
                unit = "$prefix invoice unit"
                taxpayer = "$seed-tax"
                corporateAccount = "$seed-corp"
                bankName = "$prefix bank"
                unitAddress = "$prefix unit address"
                unitPhone = '010-00000000'
                phone = '18800000001'
                harvestAddress = "$prefix harvest"
                remark = "$prefix invoice remark"
            }
        }

        return $body
    }

    $noToken = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/init/start') -Body (New-StartBody -ProjectId $projectCancelId -FileId $fileCancelId)
    Assert-Code -Json $noToken -Expected 401 -Name 'project init start no token'

    $missingProducts = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/init/start') -Token $token -Body @{
        bizSaleProjectId = $projectCancelId
        approveUserIdList = @($userId)
        copyUserIdList = @()
        fileIdList = @($fileCancelId)
        accountId = $accountId
        payerCategory = 'FULL_PAYMENT'
        initPrice = '27.00'
        rebateAmount = '0.00'
        isInvoicing = $false
        tenantId = $tenantId
    }
    Assert-Code -Json $missingProducts -Expected 400 -Name 'project init start missing productList'

    $cancelStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/init/start') -Token $token -Body (New-StartBody -ProjectId $projectCancelId -FileId $fileCancelId)
    Assert-Code -Json $cancelStart -Expected 200 -Name 'project init cancel start'
    $cancelPid = [string]$cancelStart.data.processInstanceId
    $processIds += $cancelPid
    Assert-Equal -Actual ([string]$cancelStart.data.processKey) -Expected 'Process_sale_project_init' -Name 'cancel start process key'
    Assert-Equal -Actual ([string]$cancelStart.data.projectState) -Expected 'PENDING_APPROVAL' -Name 'cancel start project state'

    $cancel = Invoke-JsonPost -Url ($baseUrl + '/biz/process/cancel') -Token $token -Body @{ id = $cancelPid }
    Assert-Code -Json $cancel -Expected 200 -Name 'project init cancel'

    $rejectStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/init/start') -Token $token -Body (New-StartBody -ProjectId $projectRejectId -FileId $fileRejectId)
    Assert-Code -Json $rejectStart -Expected 200 -Name 'project init reject start'
    $rejectPid = [string]$rejectStart.data.processInstanceId
    $rejectTaskId = [string]$rejectStart.data.taskId
    $processIds += $rejectPid

    $reject = Invoke-JsonPost -Url ($baseUrl + '/biz/task/reject') -Token $token -Body @{
        id = $rejectTaskId
        form = @{ comment = "$prefix reject" }
    }
    Assert-Code -Json $reject -Expected 200 -Name 'project init reject'

    $approveStart = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/init/start') -Token $token -Body (New-StartBody -ProjectId $projectApproveId -FileId $fileApproveId -IsInvoicing $true)
    Assert-Code -Json $approveStart -Expected 200 -Name 'project init approve start'
    $approvePid = [string]$approveStart.data.processInstanceId
    $approveTaskId = [string]$approveStart.data.taskId
    $processIds += $approvePid

    $approve = Invoke-JsonPost -Url ($baseUrl + '/biz/task/approve') -Token $token -Body @{
        id = $approveTaskId
        form = @{
            approval = $true
            comment = "$prefix approve"
        }
    }
    Assert-Code -Json $approve -Expected 200 -Name 'project init approve'
    Assert-Equal -Actual ([string]$approve.data.saleProject.projectState) -Expected 'WAIT_DELIVER' -Name 'approve response project state'

    $state = Get-State -ProcessInstanceIds $processIds -ProjectIds @($projectCancelId, $projectRejectId, $projectApproveId) -CustomerId $customerId
    Assert-Equal -Actual ([string]$state.projects.$projectCancelId.PROJECT_STATE) -Expected 'FOLLOW' -Name 'cancel project rollback'
    Assert-Equal -Actual ([string]$state.projects.$projectRejectId.PROJECT_STATE) -Expected 'FOLLOW' -Name 'reject project rollback'
    Assert-Equal -Actual ([string]$state.projects.$projectCancelId.PROCESS_ID) -Expected '' -Name 'cancel project process id remains empty'
    Assert-Equal -Actual ([string]$state.projects.$projectRejectId.PROCESS_ID) -Expected '' -Name 'reject project process id remains empty'
    Assert-Equal -Actual ([string]$state.projects.$projectApproveId.PROJECT_STATE) -Expected 'WAIT_DELIVER' -Name 'approve project state'
    Assert-Equal -Actual ([string]$state.projects.$projectApproveId.PROCESS_ID) -Expected $approvePid -Name 'approve project process id'
    Assert-DecimalEqual -Actual ([string]$state.projects.$projectApproveId.INIT_PRICE) -Expected 27.00 -Name 'approve project init price'
    Assert-DecimalEqual -Actual ([string]$state.projects.$projectApproveId.TOTAL_PRICE) -Expected 27.00 -Name 'approve project total price'
    Assert-DecimalEqual -Actual ([string]$state.projects.$projectApproveId.REBATE_AMOUNT) -Expected 1.25 -Name 'approve project rebate'
    Assert-Equal -Actual ([string]$state.projects.$projectApproveId.CONSIGNEE) -Expected "$prefix consignee" -Name 'approve consignee'

    foreach ($processId in $processIds) {
        Assert-IntEqual -Actual ([int]$state.processes.$processId.ruTask) -Expected 0 -Name "runtime task cleanup $processId"
        Assert-IntEqual -Actual ([int]$state.processes.$processId.ruVariable) -Expected 0 -Name "runtime variable cleanup $processId"
        Assert-IntEqual -Actual ([int]$state.processes.$processId.ruExecution) -Expected 0 -Name "runtime execution cleanup $processId"
        Assert-Equal -Actual ([string]$state.processes.$processId.hiProc.PROC_DEF_KEY_) -Expected 'Process_sale_project_init' -Name "history key $processId"
        Assert-Equal -Actual ([string]$state.processes.$processId.hiProc.STATE_) -Expected 'COMPLETED' -Name "history state $processId"
    }

    $approveItems = @($state.items | Where-Object { [string]$_.PROJECT_ID -eq $projectApproveId })
    Assert-IntEqual -Actual $approveItems.Count -Expected 1 -Name 'approved product item count'
    Assert-Equal -Actual ([string]$approveItems[0].PRODUCT_ID) -Expected $productId -Name 'approved item product'
    Assert-Equal -Actual ([string]$approveItems[0].STATE) -Expected 'WAIT_DELIVER' -Name 'approved item state'
    Assert-DecimalEqual -Actual ([string]$approveItems[0].PRICE) -Expected 27.00 -Name 'approved item price'

    $rejectedItems = @($state.items | Where-Object { [string]$_.PROJECT_ID -eq $projectRejectId -or [string]$_.PROJECT_ID -eq $projectCancelId })
    Assert-IntEqual -Actual $rejectedItems.Count -Expected 0 -Name 'cancel reject no product items'

    $processRelations = @($state.relations | Where-Object { [string]$_.OBJECT_ID -eq $approvePid -and [string]$_.CATEGORY -eq 'Process_sale_project_init' })
    $projectRelations = @($state.relations | Where-Object { [string]$_.CATEGORY -eq 'SALE_PROJECT' })
    $cancelRejectProjectRelations = @($projectRelations | Where-Object { [string]$_.OBJECT_ID -eq $projectCancelId -or [string]$_.OBJECT_ID -eq $projectRejectId })
    Assert-IntEqual -Actual $processRelations.Count -Expected 1 -Name 'process file relation count'
    Assert-IntEqual -Actual $projectRelations.Count -Expected 1 -Name 'sale project file relation count'
    Assert-IntEqual -Actual $cancelRejectProjectRelations.Count -Expected 0 -Name 'cancel reject no sale project file relations'

    $invoiceRows = @($state.invoicing | Where-Object { [string]$_.PROJECT_ID -eq $projectApproveId })
    $cancelRejectInvoiceRows = @($state.invoicing | Where-Object { [string]$_.PROJECT_ID -eq $projectCancelId -or [string]$_.PROJECT_ID -eq $projectRejectId })
    Assert-IntEqual -Actual $state.invoicing.Count -Expected 1 -Name 'workflow invoicing total count'
    Assert-IntEqual -Actual $cancelRejectInvoiceRows.Count -Expected 0 -Name 'cancel reject no workflow invoicing'
    Assert-IntEqual -Actual $invoiceRows.Count -Expected 1 -Name 'workflow invoicing count'
    Assert-Equal -Actual ([string]$invoiceRows[0].PROCESS_ID) -Expected $approvePid -Name 'workflow invoicing process'
    Assert-Equal -Actual ([string]$invoiceRows[0].INVOICING_STATE) -Expected 'INVOICING_STATE_WAIT' -Name 'workflow invoicing state'
    Assert-DecimalEqual -Actual ([string]$invoiceRows[0].AMOUNT) -Expected 12.34 -Name 'workflow invoicing amount'
    Assert-DecimalEqual -Actual ([string]$state.customer.DEAL_AMOUNT) -Expected 1.00 -Name 'customer deal amount increment'

    $returnToFollow = Invoke-JsonPost -Url ($baseUrl + '/biz/saleproject/cancel') -Token $token -Body @{ id = $projectApproveId }
    Assert-Code -Json $returnToFollow -Expected 200 -Name 'approved project return to follow'

    $reapplyWithoutInventory = Invoke-JsonPost -Url ($baseUrl + '/biz/process/project/init/start') -Token $token -Body (New-StartBody -ProjectId $projectApproveId -FileId $fileApproveId)
    Assert-Code -Json $reapplyWithoutInventory -Expected 400 -Name 'restored project reapply without inventory'

    $reapplyState = Get-State -ProcessInstanceIds $processIds -ProjectIds @($projectApproveId) -CustomerId $customerId
    Assert-Equal -Actual ([string]$reapplyState.projects.$projectApproveId.PROJECT_STATE) -Expected 'FOLLOW' -Name 'failed reapply keeps project in follow state'
    Assert-DecimalEqual -Actual ([string]$reapplyState.customer.DEAL_AMOUNT) -Expected 0.00 -Name 'failed reapply keeps customer deal amount rolled back'

    Write-Host 'workflow project init approve smoke passed'
} finally {
    Remove-SmokeRows -ProcessInstanceIds $processIds -ProjectIds @($projectCancelId, $projectRejectId, $projectApproveId) -FileIds @($fileCancelId, $fileRejectId, $fileApproveId) -CustomerId $customerId -ProductId $productId -AccountId $accountId
}
