param(
    [string]$FrontendBaseUrl = 'https://oa.fucity.cn',
    [string]$ApiPrefix = '/backend',
    [string]$TenantId = '2018244380532912130',
    [string]$Password = '123456',
    [string]$SshHost = '120.24.76.240',
    [int]$SshPort = 22,
    [string]$SshUser = 'root',
    [string]$SshKeyPath = 'C:\Users\Win10\.ssh\oa_fucity_deploy',
    [string]$RemoteRoot = '/www/wwwroot/oa.fucity.cn'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$baseUrl = $FrontendBaseUrl.TrimEnd('/') + '/' + $ApiPrefix.Trim('/')
$marker = 'CODEX_PROJECT_INIT_SMOKE_' + (Get-Date -Format 'yyyyMMddHHmmss')
$seed = 'P' + (Get-Date -Format 'MMddHHmmss')

$ids = [ordered]@{
    customerId = "C$seed"
    productId = "D$seed"
    accountId = "A$seed"
    cancelProjectId = "X$seed"
    rejectProjectId = "R$seed"
    approveProjectId = "V$seed"
    cancelFileId = "FC$seed"
    rejectFileId = "FR$seed"
    approveFileId = "FA$seed"
}

function Invoke-OaApi {
    param(
        [Parameter(Mandatory = $true)][string]$Method,
        [Parameter(Mandatory = $true)][string]$Path,
        [hashtable]$Headers = @{},
        [object]$Body = $null,
        [switch]$AllowFailure
    )

    $uri = $script:baseUrl + $Path
    $allHeaders = @{}
    foreach ($key in $Headers.Keys) {
        $allHeaders[$key] = $Headers[$key]
    }
    if (-not $allHeaders.ContainsKey('tenantId')) {
        $allHeaders['tenantId'] = $script:TenantId
    }

    try {
        if ($Method.ToUpperInvariant() -eq 'GET') {
            $response = Invoke-RestMethod -Method Get -Uri $uri -Headers $allHeaders
        } else {
            $json = if ($null -eq $Body) { '{}' } else { ConvertTo-Json -InputObject $Body -Depth 40 -Compress }
            $response = Invoke-RestMethod -Method $Method -Uri $uri -Headers $allHeaders -ContentType 'application/json' -Body $json
        }
    } catch {
        if ($AllowFailure) {
            return [pscustomobject]@{
                code = 'http-error'
                msg = $_.Exception.Message
                data = $null
            }
        }
        throw
    }

    if (-not $AllowFailure -and [int]$response.code -ne 200) {
        throw "$Method $Path failed: code=$($response.code) msg=$($response.msg)"
    }

    return $response
}

function New-Session {
    param([Parameter(Mandatory = $true)][string]$Account)

    $login = Invoke-OaApi -Method POST -Path '/auth/b/doLogin' -Body @{
        account = $Account
        password = $script:Password
        tenantId = $script:TenantId
        device = 'CODEX_ONLINE_PROJECT_INIT_WORKFLOW_SMOKE'
    }
    if ([string]::IsNullOrWhiteSpace([string]$login.data)) {
        throw "login returned empty token for $Account"
    }

    $headers = @{
        Authorization = "Bearer $($login.data)"
        tenantId = $script:TenantId
    }
    $user = Invoke-OaApi -Method GET -Path '/auth/b/getLoginUser' -Headers $headers

    return [pscustomobject]@{
        Account = $Account
        Headers = $headers
        User = $user.data.user
    }
}

function Invoke-RemotePhpJson {
    param([Parameter(Mandatory = $true)][string]$Code)

    $ssh = 'C:\Windows\System32\OpenSSH\ssh.exe'
    if (-not (Test-Path -LiteralPath $ssh)) {
        $ssh = 'ssh'
    }
    if (-not (Test-Path -LiteralPath $SshKeyPath)) {
        throw "SSH key not found: $SshKeyPath"
    }

    $target = "$SshUser@$SshHost"
    $remoteCommand = "cd $RemoteRoot && php"
    $previousErrorActionPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $raw = $Code | & $ssh -i $SshKeyPath -p $SshPort -o StrictHostKeyChecking=accept-new $target $remoteCommand 2>&1
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }
    if ($LASTEXITCODE -ne 0) {
        throw "remote php failed: $($raw -join "`n")"
    }

    $text = ($raw | ForEach-Object { [string]$_ }) -join "`n"
    $text = $text.TrimStart([char]0xFEFF).Trim()
    if ($text -eq '') {
        throw 'remote php returned empty output'
    }

    $jsonStart = $text.IndexOf('{')
    if ($jsonStart -lt 0) {
        throw "remote php returned non-json output: $text"
    }

    return $text.Substring($jsonStart) | ConvertFrom-Json
}

function Assert-Equal {
    param(
        [object]$Actual,
        [object]$Expected,
        [string]$Name
    )
    if ([string]$Actual -ne [string]$Expected) {
        throw "$Name expected '$Expected', got '$Actual'"
    }
}

function Assert-IntEqual {
    param(
        [object]$Actual,
        [int]$Expected,
        [string]$Name
    )
    if ([int]$Actual -ne $Expected) {
        throw "$Name expected '$Expected', got '$Actual'"
    }
}

function Assert-DecimalEqual {
    param(
        [object]$Actual,
        [decimal]$Expected,
        [string]$Name
    )
    if ([decimal]$Actual -ne $Expected) {
        throw "$Name expected '$Expected', got '$Actual'"
    }
}

function ConvertTo-PhpStringArray {
    param([string[]]$Values)
    $items = @($Values | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object {
        "'" + $_.Replace("'", "\'") + "'"
    })
    return '[' + ($items -join ',') + ']'
}

function New-RemoteSetupCode {
    $safeMarker = $script:marker.Replace("'", "\'")
    $safeTenantId = $script:TenantId.Replace("'", "\'")
    $safeCustomerId = $script:ids.customerId.Replace("'", "\'")
    $safeProductId = $script:ids.productId.Replace("'", "\'")
    $safeAccountId = $script:ids.accountId.Replace("'", "\'")
    $safeCancelProjectId = $script:ids.cancelProjectId.Replace("'", "\'")
    $safeRejectProjectId = $script:ids.rejectProjectId.Replace("'", "\'")
    $safeApproveProjectId = $script:ids.approveProjectId.Replace("'", "\'")
    $safeCancelFileId = $script:ids.cancelFileId.Replace("'", "\'")
    $safeRejectFileId = $script:ids.rejectFileId.Replace("'", "\'")
    $safeApproveFileId = $script:ids.approveFileId.Replace("'", "\'")

@"
<?php
require 'vendor/autoload.php';
`$app = new think\App();
`$app->initialize();

`$tenantId = '$safeTenantId';
`$marker = '$safeMarker';
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', 'csyw001')->where('TENANT_ID', `$tenantId)->find();
if (!is_array(`$user) || `$user === []) {
    throw new RuntimeException('csyw001 user not found');
}
`$userId = (string)(`$user['ID'] ?? '');
`$orgId = (string)(`$user['ORG_ID'] ?? '');
`$now = date('Y-m-d H:i:s');
`$audit = [
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
];

think\facade\Db::transaction(function () use (`$marker, `$tenantId, `$userId, `$orgId, `$audit, `$now): void {
    think\facade\Db::name('customer')->insert(array_merge(`$audit, [
        'ID' => '$safeCustomerId',
        'NAME' => `$marker . ' customer',
        'CUSTOM_TYPE' => 'OLD',
        'ORG' => `$orgId !== '' ? `$orgId : null,
        'USER' => `$userId,
        'STATUS' => 'ENABLE',
        'DELETE_FLAG' => 'NOT_DELETE',
        'UPDATE_TIME' => null,
        'UPDATE_USER' => null,
        'VERSION' => 0,
        'DEAL_AMOUNT' => '0.00',
    ]));

    think\facade\Db::name('settlement_account')->insert(array_merge(`$audit, [
        'ID' => '$safeAccountId',
        'ACCOUNT_NAME' => `$marker . ' account',
        'ACCOUNT_NUMBER' => '$safeAccountId',
        'INITIAL_AMOUNT' => '0.00',
        'CURRENT_AMOUNT' => '0.00',
        'ACCOUNT_STATUS' => 'ENABLE',
        'SORT_CODE' => 990,
        'DELETE_FLAG' => 'NOT_DELETE',
        'UPDATE_TIME' => null,
        'UPDATE_USER' => null,
        'org' => `$orgId !== '' ? `$orgId : null,
        'VERSION' => 0,
    ]));

    think\facade\Db::name('biz_product')->insert(array_merge(`$audit, [
        'ID' => '$safeProductId',
        'PRODUCT_NAME' => `$marker . ' product',
        'PRODUCT_CATEGORY' => 'SMOKE',
        'SAFETY_STOCK' => 0,
        'PURCHASE_PRICE' => '10.00',
        'SALE_PRICE' => '20.00',
        'MIN_PRICE' => '8.00',
        'CATEGORY' => 'SINGLE_PRODUCT',
        'DELETE_FLAG' => 'NOT_DELETE',
        'UPDATE_TIME' => null,
        'UPDATE_USER' => null,
        'SPECS' => 'smoke',
        'ORG' => `$orgId !== '' ? `$orgId : null,
        'status' => 'ENABLE',
    ]));

    foreach ([['$safeCancelProjectId', 'cancel'], ['$safeRejectProjectId', 'reject'], ['$safeApproveProjectId', 'approve']] as `$project) {
        think\facade\Db::name('biz_sale_project')->insert(array_merge(`$audit, [
            'ID' => `$project[0],
            'CUSTOMER' => '$safeCustomerId',
            'PROJECT_NAME' => `$marker . ' ' . `$project[1],
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
            'UPDATE_TIME' => null,
            'UPDATE_USER' => null,
            'VERSION' => 0,
            'DEAL_AMOUNT' => '0.00',
            'HISTORY_AMOUNT' => '0.00',
            'TOTAL_RETURN_AMOUNT' => '0.00',
            'TOTAL_REFUND_AMOUNT' => '0.00',
        ]));
    }

    foreach ([['$safeCancelFileId', 'cancel'], ['$safeRejectFileId', 'reject'], ['$safeApproveFileId', 'approve']] as `$file) {
        `$name = `$marker . '-' . `$file[1] . '.txt';
        think\facade\Db::name('dev_file')->insert(array_merge(`$audit, [
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
            'UPDATE_TIME' => null,
            'UPDATE_USER' => null,
        ]));
    }
});

echo json_encode([
    'ok' => true,
    'userId' => `$userId,
    'orgId' => `$orgId,
    'tenantId' => `$tenantId,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
"@
}

function New-RemoteCleanupCode {
    param([string[]]$ProcessInstanceIds = @())

    $projectIds = ConvertTo-PhpStringArray @($script:ids.cancelProjectId, $script:ids.rejectProjectId, $script:ids.approveProjectId)
    $fileIds = ConvertTo-PhpStringArray @($script:ids.cancelFileId, $script:ids.rejectFileId, $script:ids.approveFileId)
    $processIds = ConvertTo-PhpStringArray $ProcessInstanceIds
    $safeCustomerId = $script:ids.customerId.Replace("'", "\'")
    $safeProductId = $script:ids.productId.Replace("'", "\'")
    $safeAccountId = $script:ids.accountId.Replace("'", "\'")

@"
<?php
require 'vendor/autoload.php';
`$app = new think\App();
`$app->initialize();
`$processIds = $processIds;
`$projectIds = $projectIds;
`$fileIds = $fileIds;

think\facade\Db::transaction(function () use (`$processIds, `$projectIds, `$fileIds): void {
    if (`$processIds !== []) {
        think\facade\Db::name('act_ru_task')->whereIn('PROC_INST_ID_', `$processIds)->delete();
        think\facade\Db::name('act_ru_variable')->whereIn('PROC_INST_ID_', `$processIds)->delete();
        `$executionIds = think\facade\Db::name('act_ru_execution')
            ->whereIn('PROC_INST_ID_', `$processIds)
            ->orderRaw('CASE WHEN PARENT_ID_ IS NULL THEN 1 ELSE 0 END ASC')
            ->column('ID_');
        foreach (`$executionIds as `$executionId) {
            think\facade\Db::name('act_ru_execution')->where('ID_', `$executionId)->delete();
        }
        think\facade\Db::name('act_hi_varinst')->whereIn('PROC_INST_ID_', `$processIds)->delete();
        think\facade\Db::name('act_hi_taskinst')->whereIn('PROC_INST_ID_', `$processIds)->delete();
        think\facade\Db::name('act_hi_actinst')->whereIn('PROC_INST_ID_', `$processIds)->delete();
        think\facade\Db::name('act_hi_procinst')->whereIn('PROC_INST_ID_', `$processIds)->delete();
        think\facade\Db::name('biz_cc_records')->whereIn('INSTANCE_ID', `$processIds)->delete();
        think\facade\Db::name('biz_file_relation')->whereIn('OBJECT_ID', `$processIds)->delete();
        think\facade\Db::name('biz_sale_project_invoicing')->whereIn('PROCESS_ID', `$processIds)->delete();
        think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROCESS_ID', `$processIds)->delete();
        think\facade\Db::name('biz_sale_project_reissue_order')->whereIn('PROCESS_ID', `$processIds)->delete();
        think\facade\Db::name('return_order')->whereIn('PROCESS_ID', `$processIds)->delete();
        think\facade\Db::name('delivery_record')->whereIn('PROCESS_ID', `$processIds)->delete();
        think\facade\Db::name('settlement_account_statement')->whereIn('PROCESS_ID', `$processIds)->delete();
        think\facade\Db::name('biz_payment_record')->whereIn('PROCESS_ID', `$processIds)->delete();
        think\facade\Db::name('biz_expenditure_record')->whereIn('PROCESS_ID', `$processIds)->delete();
    }
    if (`$projectIds !== []) {
        `$invoiceIds = think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROJECT_ID', `$projectIds)->column('ID');
        if (`$invoiceIds !== []) {
            think\facade\Db::name('biz_sale_project_invoice_item')->whereIn('INVOICE_ID', `$invoiceIds)->delete();
        }
        `$itemIds = think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->column('ID');
        if (`$itemIds !== []) {
            think\facade\Db::name('sale_project_product_item_relation')->whereIn('OBJECT_ID', `$itemIds)->delete();
            think\facade\Db::name('return_order_item')->whereIn('PROJECT_PRODUCT_ITEM_ID', `$itemIds)->delete();
        }
        think\facade\Db::name('biz_sale_project_invoice')->whereIn('PROJECT_ID', `$projectIds)->delete();
        think\facade\Db::name('biz_sale_project_invoicing')->whereIn('PROJECT_ID', `$projectIds)->delete();
        think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->delete();
        think\facade\Db::name('biz_file_relation')->whereIn('OBJECT_ID', `$projectIds)->delete();
        think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->delete();
    }
    if (`$fileIds !== []) {
        think\facade\Db::name('biz_file_relation')->whereIn('TARGET_ID', `$fileIds)->delete();
        think\facade\Db::name('dev_file')->whereIn('ID', `$fileIds)->delete();
    }
    think\facade\Db::name('biz_product')->where('ID', '$safeProductId')->delete();
    think\facade\Db::name('settlement_account')->where('ID', '$safeAccountId')->delete();
    think\facade\Db::name('customer')->where('ID', '$safeCustomerId')->delete();
});

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
"@
}

function New-RemoteStateCode {
    param([string[]]$ProcessInstanceIds = @())

    $projectIds = ConvertTo-PhpStringArray @($script:ids.cancelProjectId, $script:ids.rejectProjectId, $script:ids.approveProjectId)
    $fileIds = ConvertTo-PhpStringArray @($script:ids.cancelFileId, $script:ids.rejectFileId, $script:ids.approveFileId)
    $processIds = ConvertTo-PhpStringArray $ProcessInstanceIds
    $safeCustomerId = $script:ids.customerId.Replace("'", "\'")
    $safeProductId = $script:ids.productId.Replace("'", "\'")
    $safeAccountId = $script:ids.accountId.Replace("'", "\'")

@"
<?php
require 'vendor/autoload.php';
`$app = new think\App();
`$app->initialize();
`$processIds = $processIds;
`$projectIds = $projectIds;
`$fileIds = $fileIds;

`$projects = [];
if (`$projectIds !== []) {
    foreach (think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->select()->toArray() as `$row) {
        `$projects[(string)`$row['ID']] = `$row;
    }
}
`$processes = [];
foreach (`$processIds as `$pid) {
    `$processes[`$pid] = [
        'ruTask' => think\facade\Db::name('act_ru_task')->where('PROC_INST_ID_', `$pid)->count(),
        'ruVariable' => think\facade\Db::name('act_ru_variable')->where('PROC_INST_ID_', `$pid)->count(),
        'ruExecution' => think\facade\Db::name('act_ru_execution')->where('PROC_INST_ID_', `$pid)->count(),
        'hiProc' => think\facade\Db::name('act_hi_procinst')->where('PROC_INST_ID_', `$pid)->field('PROC_DEF_KEY_,STATE_,END_TIME_')->find(),
    ];
}
`$items = `$projectIds !== [] ? think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->select()->toArray() : [];
`$invoicing = `$projectIds !== [] ? think\facade\Db::name('biz_sale_project_invoicing')->whereIn('PROJECT_ID', `$projectIds)->select()->toArray() : [];
`$relations = [];
if (`$projectIds !== [] || `$processIds !== []) {
    `$relationQuery = think\facade\Db::name('biz_file_relation');
    `$relationQuery->where(function (`$query) use (`$projectIds, `$processIds) {
        if (`$projectIds !== []) {
            `$query->whereIn('OBJECT_ID', `$projectIds);
        }
        if (`$processIds !== []) {
            if (`$projectIds !== []) {
                `$query->whereOr('OBJECT_ID', 'in', `$processIds);
            } else {
                `$query->whereIn('OBJECT_ID', `$processIds);
            }
        }
    });
    `$relations = `$relationQuery->select()->toArray();
}

echo json_encode([
    'projects' => `$projects,
    'processes' => `$processes,
    'items' => `$items,
    'invoicing' => `$invoicing,
    'relations' => `$relations,
    'customer' => think\facade\Db::name('customer')->where('ID', '$safeCustomerId')->find(),
    'activeResidual' => [
        'customer' => think\facade\Db::name('customer')->where('ID', '$safeCustomerId')->count(),
        'product' => think\facade\Db::name('biz_product')->where('ID', '$safeProductId')->count(),
        'account' => think\facade\Db::name('settlement_account')->where('ID', '$safeAccountId')->count(),
        'projects' => `$projectIds === [] ? 0 : think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->count(),
        'files' => `$fileIds === [] ? 0 : think\facade\Db::name('dev_file')->whereIn('ID', `$fileIds)->count(),
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
"@
}

function New-StartBody {
    param(
        [Parameter(Mandatory = $true)][string]$ProjectId,
        [Parameter(Mandatory = $true)][string]$FileId,
        [Parameter(Mandatory = $true)][string]$UserId,
        [switch]$WithInvoicing
    )

    $short = "SMK$script:seed"
    $body = @{
        bizSaleProjectId = $ProjectId
        approveUserIdList = @($UserId)
        copyUserIdList = @()
        fileIdList = @($FileId)
        productList = @(@{
            productId = $script:ids.productId
            number = 2
            unitPrice = '15.00'
            discountRate = '10.00'
            price = '27.00'
            remark = "$short product line"
        })
        consignee = "$short consignee"
        phone = '18800000000'
        unit = "$short unit"
        address = "$short address"
        logisticsCategory = 'EXPRESS'
        deliveryNote = "$short delivery"
        freight = '3.50'
        freightCategory = 'BUYER_PAY'
        accountId = $script:ids.accountId
        payerCategory = 'FULL_PAYMENT'
        initPrice = '27.00'
        rebateAmount = '1.25'
        completionDate = '2026-12-31 10:11:12'
        isInvoicing = [bool]$WithInvoicing
        tenantId = $script:TenantId
    }

    if ($WithInvoicing) {
        $body.invoicingInfo = @{
            invoicingCategory = 'SpecialTicket'
            amount = '12.34'
            companyName = "$short invoice company"
            customerCompany = "$short customer company"
            unit = "$short invoice unit"
            taxpayer = "$script:seed-tax"
            corporateAccount = "$script:seed-corp"
            bankName = "$short bank"
            unitAddress = "$short unit address"
            unitPhone = '010-00000000'
            phone = '18800000001'
            harvestAddress = "$short harvest"
            remark = "$short invoice remark"
        }
    }

    return $body
}

$processIds = New-Object System.Collections.Generic.List[string]
$results = New-Object System.Collections.Generic.List[object]

try {
    Invoke-RemotePhpJson -Code (New-RemoteCleanupCode) | Out-Null
    $setup = Invoke-RemotePhpJson -Code (New-RemoteSetupCode)
    Assert-Equal -Actual $setup.ok -Expected $true -Name 'remote setup'
    $userId = [string]$setup.userId
    if ([string]::IsNullOrWhiteSpace($userId)) {
        throw 'remote setup returned empty userId'
    }

    $sales = New-Session -Account 'csyw001'
    $headers = $sales.Headers

    $cancelStart = Invoke-OaApi -Method POST -Path '/biz/process/project/init/start' -Headers $headers -Body (New-StartBody -ProjectId $ids.cancelProjectId -FileId $ids.cancelFileId -UserId $userId)
    $cancelPid = [string]$cancelStart.data.processInstanceId
    $processIds.Add($cancelPid) | Out-Null
    Assert-Equal -Actual $cancelStart.data.processKey -Expected 'Process_sale_project_init' -Name 'cancel start process key'
    Assert-Equal -Actual $cancelStart.data.projectState -Expected 'PENDING_APPROVAL' -Name 'cancel start project state'
    $cancel = Invoke-OaApi -Method POST -Path '/biz/process/cancel' -Headers $headers -Body @{ id = $cancelPid }
    Assert-Equal -Actual $cancel.code -Expected 200 -Name 'cancel process code'
    $results.Add([pscustomobject]@{ scope = 'sales'; account = 'csyw001'; endpoint = '/biz/process/project/init/start -> /biz/process/cancel'; projectId = $ids.cancelProjectId; processInstanceId = $cancelPid; ok = $true }) | Out-Null

    $rejectStart = Invoke-OaApi -Method POST -Path '/biz/process/project/init/start' -Headers $headers -Body (New-StartBody -ProjectId $ids.rejectProjectId -FileId $ids.rejectFileId -UserId $userId)
    $rejectPid = [string]$rejectStart.data.processInstanceId
    $rejectTaskId = [string]$rejectStart.data.taskId
    $processIds.Add($rejectPid) | Out-Null
    Assert-Equal -Actual $rejectStart.data.processKey -Expected 'Process_sale_project_init' -Name 'reject start process key'
    $reject = Invoke-OaApi -Method POST -Path '/biz/task/reject' -Headers $headers -Body @{
        id = $rejectTaskId
        form = @{ comment = "$marker reject" }
    }
    Assert-Equal -Actual $reject.code -Expected 200 -Name 'reject process code'
    $results.Add([pscustomobject]@{ scope = 'sales'; account = 'csyw001'; endpoint = '/biz/process/project/init/start -> /biz/task/reject'; projectId = $ids.rejectProjectId; processInstanceId = $rejectPid; taskId = $rejectTaskId; ok = $true }) | Out-Null

    $approveStart = Invoke-OaApi -Method POST -Path '/biz/process/project/init/start' -Headers $headers -Body (New-StartBody -ProjectId $ids.approveProjectId -FileId $ids.approveFileId -UserId $userId -WithInvoicing)
    $approvePid = [string]$approveStart.data.processInstanceId
    $approveTaskId = [string]$approveStart.data.taskId
    $processIds.Add($approvePid) | Out-Null
    Assert-Equal -Actual $approveStart.data.processKey -Expected 'Process_sale_project_init' -Name 'approve start process key'
    $approve = Invoke-OaApi -Method POST -Path '/biz/task/approve' -Headers $headers -Body @{
        id = $approveTaskId
        form = @{
            approval = $true
            comment = "$marker approve"
        }
    }
    Assert-Equal -Actual $approve.data.saleProject.projectState -Expected 'WAIT_DELIVER' -Name 'approved project state'
    Assert-Equal -Actual $approve.data.saleProject.productItemCount -Expected 1 -Name 'approved product item count'
    Assert-Equal -Actual $approve.data.saleProject.fileRelationCount -Expected 1 -Name 'approved file relation count'
    Assert-Equal -Actual $approve.data.saleProject.invoicingCount -Expected 1 -Name 'approved invoicing count'
    $results.Add([pscustomobject]@{ scope = 'sales'; account = 'csyw001'; endpoint = '/biz/process/project/init/start -> /biz/task/approve'; projectId = $ids.approveProjectId; processInstanceId = $approvePid; taskId = $approveTaskId; ok = $true }) | Out-Null

    $state = Invoke-RemotePhpJson -Code (New-RemoteStateCode -ProcessInstanceIds @($processIds))
    $cancelProject = $state.projects.($ids.cancelProjectId)
    $rejectProject = $state.projects.($ids.rejectProjectId)
    $approveProject = $state.projects.($ids.approveProjectId)
    Assert-Equal -Actual $cancelProject.PROJECT_STATE -Expected 'FOLLOW' -Name 'cancel project rollback'
    Assert-Equal -Actual $rejectProject.PROJECT_STATE -Expected 'FOLLOW' -Name 'reject project rollback'
    Assert-Equal -Actual $approveProject.PROJECT_STATE -Expected 'WAIT_DELIVER' -Name 'approve project persisted state'
    Assert-Equal -Actual $approveProject.PROCESS_ID -Expected $approvePid -Name 'approve project process id'
    Assert-DecimalEqual -Actual $approveProject.INIT_PRICE -Expected 27.00 -Name 'approve project init price'
    Assert-DecimalEqual -Actual $approveProject.TOTAL_PRICE -Expected 27.00 -Name 'approve project total price'
    Assert-DecimalEqual -Actual $approveProject.REBATE_AMOUNT -Expected 1.25 -Name 'approve project rebate'
    Assert-DecimalEqual -Actual $state.customer.DEAL_AMOUNT -Expected 1.00 -Name 'customer deal amount increment'

    foreach ($processInstanceId in @($processIds)) {
        Assert-IntEqual -Actual $state.processes.$processInstanceId.ruTask -Expected 0 -Name "runtime task cleanup $processInstanceId"
        Assert-IntEqual -Actual $state.processes.$processInstanceId.ruVariable -Expected 0 -Name "runtime variable cleanup $processInstanceId"
        Assert-IntEqual -Actual $state.processes.$processInstanceId.ruExecution -Expected 0 -Name "runtime execution cleanup $processInstanceId"
        Assert-Equal -Actual $state.processes.$processInstanceId.hiProc.PROC_DEF_KEY_ -Expected 'Process_sale_project_init' -Name "history process key $processInstanceId"
        Assert-Equal -Actual $state.processes.$processInstanceId.hiProc.STATE_ -Expected 'COMPLETED' -Name "history process state $processInstanceId"
    }

    $approvedItems = @($state.items | Where-Object { [string]$_.PROJECT_ID -eq $ids.approveProjectId })
    Assert-IntEqual -Actual $approvedItems.Count -Expected 1 -Name 'approved project item count'
    Assert-Equal -Actual $approvedItems[0].PRODUCT_ID -Expected $ids.productId -Name 'approved item product id'
    Assert-Equal -Actual $approvedItems[0].STATE -Expected 'WAIT_DELIVER' -Name 'approved item state'
    Assert-DecimalEqual -Actual $approvedItems[0].PRICE -Expected 27.00 -Name 'approved item price'

    $approvedProjectRelations = @($state.relations | Where-Object { [string]$_.OBJECT_ID -eq $ids.approveProjectId -and [string]$_.CATEGORY -eq 'SALE_PROJECT' })
    Assert-IntEqual -Actual $approvedProjectRelations.Count -Expected 1 -Name 'approved sale project file relation'
    $approvedInvoicing = @($state.invoicing | Where-Object { [string]$_.PROJECT_ID -eq $ids.approveProjectId })
    Assert-IntEqual -Actual $approvedInvoicing.Count -Expected 1 -Name 'approved project invoicing rows'
    Assert-Equal -Actual $approvedInvoicing[0].PROCESS_ID -Expected $approvePid -Name 'approved invoicing process id'
    Assert-DecimalEqual -Actual $approvedInvoicing[0].AMOUNT -Expected 12.34 -Name 'approved invoicing amount'

    $cleanup = Invoke-RemotePhpJson -Code (New-RemoteCleanupCode -ProcessInstanceIds @($processIds))
    Assert-Equal -Actual $cleanup.ok -Expected $true -Name 'remote cleanup'
    $afterCleanup = Invoke-RemotePhpJson -Code (New-RemoteStateCode -ProcessInstanceIds @($processIds))
    Assert-IntEqual -Actual $afterCleanup.activeResidual.customer -Expected 0 -Name 'customer residual'
    Assert-IntEqual -Actual $afterCleanup.activeResidual.product -Expected 0 -Name 'product residual'
    Assert-IntEqual -Actual $afterCleanup.activeResidual.account -Expected 0 -Name 'account residual'
    Assert-IntEqual -Actual $afterCleanup.activeResidual.projects -Expected 0 -Name 'project residual'
    Assert-IntEqual -Actual $afterCleanup.activeResidual.files -Expected 0 -Name 'file residual'

    [pscustomobject]@{
        ok = $true
        marker = $marker
        ids = $ids
        results = $results
        verification = [pscustomobject]@{
            cancelProjectState = 'FOLLOW'
            rejectProjectState = 'FOLLOW'
            approveProjectState = 'WAIT_DELIVER'
            approvedProductItems = 1
            approvedSaleProjectFileRelations = 1
            approvedInvoicingRows = 1
            customerDealAmountAfterApprove = '1.00'
            residualRowsAfterCleanup = $afterCleanup.activeResidual
        }
    } | ConvertTo-Json -Depth 12
} finally {
    try {
        Invoke-RemotePhpJson -Code (New-RemoteCleanupCode -ProcessInstanceIds @($processIds)) | Out-Null
    } catch {
        Write-Warning "final cleanup failed: $($_.Exception.Message)"
    }
}
