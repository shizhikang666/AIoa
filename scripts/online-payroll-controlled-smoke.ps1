param(
    [string]$FrontendBaseUrl = 'https://oa.fucity.cn',
    [string]$ApiPrefix = '/backend',
    [string]$TenantId = '2018244380532912130',
    [string]$Password = '123456',
    [string]$HrAccount = 'cszjb001',
    [string]$SshHost = '120.24.76.240',
    [int]$SshPort = 22,
    [string]$SshUser = 'root',
    [string]$SshKeyPath = 'C:\Users\Win10\.ssh\oa_fucity_deploy',
    [string]$RemoteRoot = '/www/wwwroot/oa.fucity.cn'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $ProjectRoot

$baseUrl = $FrontendBaseUrl.TrimEnd('/') + '/' + $ApiPrefix.Trim('/')
$marker = 'CODEX_PAYROLL_SMOKE_' + (Get-Date -Format 'yyyyMMddHHmmss')
$seed = 'P' + (Get-Date -Format 'MMddHHmmss')
$directSalaryTime = '2026-04-01 00:00:00'
$generateSalaryTime = '2026-04-15 00:00:00'

$ids = [pscustomobject]@{
    directUserId = "UD$seed"
    genUserA = "UA$seed"
    genUserB = "UB$seed"
    customerId = "UC$seed"
    projectCurrent = "PC$seed"
    projectPrevious = "PP$seed"
    paymentCurrent = "RC$seed"
    paymentPrevious = "RP$seed"
    paymentTarget = "PT$seed"
    paymentSerialCurrent = "SC$seed"
    paymentSerialPrevious = "SP$seed"
    paymentProcessCurrent = "GC$seed"
    paymentProcessPrevious = "GP$seed"
    leaveA = "LA$seed"
    leaveB = "LB$seed"
    leaveProcessA = "VA$seed"
    leaveProcessB = "VB$seed"
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
            $json = if ($null -eq $Body) { '{}' } else { ConvertTo-Json -InputObject $Body -Depth 30 -Compress }
            $response = Invoke-RestMethod -Method $Method -Uri $uri -Headers $allHeaders -ContentType 'application/json' -Body $json
        }
    } catch {
        if ($AllowFailure) {
            return [pscustomobject]@{
                code = 'http-error'
                msg = $_.Exception.Message
                message = $_.Exception.Message
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
        device = 'CODEX_ONLINE_PAYROLL_CONTROLLED_SMOKE'
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

function ConvertTo-PhpStringArray {
    param([string[]]$Values)

    $items = @()
    foreach ($value in $Values) {
        $items += "'" + ([string]$value).Replace("'", "\'") + "'"
    }

    return '[' + ($items -join ', ') + ']'
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
        [object]$Expected,
        [string]$Name
    )
    if ([decimal]$Actual -ne [decimal]$Expected) {
        throw "$Name expected '$Expected', got '$Actual'"
    }
}

function New-RemoteCleanupCode {
    $userIds = ConvertTo-PhpStringArray -Values @($script:ids.directUserId, $script:ids.genUserA, $script:ids.genUserB)
    $projectIds = ConvertTo-PhpStringArray -Values @($script:ids.projectCurrent, $script:ids.projectPrevious)
    $paymentIds = ConvertTo-PhpStringArray -Values @($script:ids.paymentCurrent, $script:ids.paymentPrevious)
    $leaveIds = ConvertTo-PhpStringArray -Values @($script:ids.leaveA, $script:ids.leaveB)
    $safeCustomerId = $script:ids.customerId.Replace("'", "\'")

@"
<?php
require 'vendor/autoload.php';
`$app = new think\App();
`$app->initialize();
`$userIds = $userIds;
`$projectIds = $projectIds;
`$paymentIds = $paymentIds;
`$leaveIds = $leaveIds;

think\facade\Db::transaction(function () use (`$userIds, `$projectIds, `$paymentIds, `$leaveIds): void {
    think\facade\Db::name('biz_payroll')->whereIn('USER', `$userIds)->delete();
    think\facade\Db::name('biz_leave_application')->whereIn('ID', `$leaveIds)->delete();
    think\facade\Db::name('biz_payment_record')->whereIn('ID', `$paymentIds)->delete();
    think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->delete();
    think\facade\Db::name('customer')->where('ID', '$safeCustomerId')->delete();
    think\facade\Db::name('sys_user')->whereIn('ID', `$userIds)->delete();
});

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
"@
}

function New-RemoteSetupCode {
    $safeTenantId = $TenantId.Replace("'", "\'")
    $safeMarker = $marker.Replace("'", "\'")
    $safeDirectUser = $ids.directUserId.Replace("'", "\'")
    $safeGenUserA = $ids.genUserA.Replace("'", "\'")
    $safeGenUserB = $ids.genUserB.Replace("'", "\'")
    $safeCustomerId = $ids.customerId.Replace("'", "\'")
    $safeProjectCurrent = $ids.projectCurrent.Replace("'", "\'")
    $safeProjectPrevious = $ids.projectPrevious.Replace("'", "\'")
    $safePaymentCurrent = $ids.paymentCurrent.Replace("'", "\'")
    $safePaymentPrevious = $ids.paymentPrevious.Replace("'", "\'")
    $safePaymentTarget = $ids.paymentTarget.Replace("'", "\'")
    $safePaymentSerialCurrent = $ids.paymentSerialCurrent.Replace("'", "\'")
    $safePaymentSerialPrevious = $ids.paymentSerialPrevious.Replace("'", "\'")
    $safePaymentProcessCurrent = $ids.paymentProcessCurrent.Replace("'", "\'")
    $safePaymentProcessPrevious = $ids.paymentProcessPrevious.Replace("'", "\'")
    $safeLeaveA = $ids.leaveA.Replace("'", "\'")
    $safeLeaveB = $ids.leaveB.Replace("'", "\'")
    $safeLeaveProcessA = $ids.leaveProcessA.Replace("'", "\'")
    $safeLeaveProcessB = $ids.leaveProcessB.Replace("'", "\'")

@"
<?php
require 'vendor/autoload.php';
`$app = new think\App();
`$app->initialize();

`$tenantId = '$safeTenantId';
`$marker = '$safeMarker';
`$operator = think\facade\Db::name('sys_user')->where('ACCOUNT', '$HrAccount')->where('TENANT_ID', `$tenantId)->find();
if (!is_array(`$operator) || `$operator === []) {
    throw new RuntimeException('$HrAccount user not found');
}
`$operatorId = (string)(`$operator['ID'] ?? '');
`$orgId = (string)(`$operator['ORG_ID'] ?? '');
if (`$operatorId === '' || `$orgId === '') {
    throw new RuntimeException('$HrAccount user or org is empty');
}
`$now = date('Y-m-d H:i:s');
`$audit = [
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$operatorId,
    'TENANT_ID' => `$tenantId,
];

think\facade\Db::transaction(function () use (`$marker, `$tenantId, `$operatorId, `$orgId, `$audit, `$now): void {
    think\facade\Db::name('customer')->insert(array_merge(`$audit, [
        'ID' => '$safeCustomerId',
        'NAME' => `$marker . ' payroll customer',
        'CUSTOM_TYPE' => 'OLD',
        'ORG' => `$orgId,
        'USER' => `$operatorId,
        'STATUS' => 'ENABLE',
        'DELETE_FLAG' => 'NOT_DELETE',
        'UPDATE_TIME' => null,
        'UPDATE_USER' => null,
        'VERSION' => 0,
        'DEAL_AMOUNT' => '0.00',
    ]));

    think\facade\Db::name('sys_user')->insertAll([
        array_merge(`$audit, [
            'ID' => '$safeDirectUser',
            'ACCOUNT' => '$seed-direct',
            'NAME' => `$marker . ' direct user',
            'ORG_ID' => `$orgId,
            'USER_STATUS' => 'ENABLE',
            'DELETE_FLAG' => 'NOT_DELETE',
            'BANK_NAME' => '',
            'BANK_ACCOUNT' => '',
            'BASIC_SALARY' => '3600.00',
        ]),
        array_merge(`$audit, [
            'ID' => '$safeGenUserA',
            'ACCOUNT' => '$seed-gen-a',
            'NAME' => `$marker . ' gen user a',
            'ORG_ID' => `$orgId,
            'USER_STATUS' => 'ENABLE',
            'DELETE_FLAG' => 'NOT_DELETE',
            'BANK_NAME' => '',
            'BANK_ACCOUNT' => '',
            'BASIC_SALARY' => '2400.00',
        ]),
        array_merge(`$audit, [
            'ID' => '$safeGenUserB',
            'ACCOUNT' => '$seed-gen-b',
            'NAME' => `$marker . ' gen user b',
            'ORG_ID' => `$orgId,
            'USER_STATUS' => 'ENABLE',
            'DELETE_FLAG' => 'NOT_DELETE',
            'BANK_NAME' => '',
            'BANK_ACCOUNT' => '',
            'BASIC_SALARY' => '1200.00',
        ]),
    ]);

    think\facade\Db::name('biz_sale_project')->insertAll([
        array_merge(`$audit, [
            'ID' => '$safeProjectCurrent',
            'CUSTOMER' => '$safeCustomerId',
            'PROJECT_NAME' => `$marker . ' current project',
            'PROJECT_STATE' => 'COMPLETED',
            'PLAY_STATE' => 'PAID',
            'VISIBILITY' => 'PRIVATE',
            'INIT_PRICE' => '1000.00',
            'TOTAL_PRICE' => '1000.00',
            'AMOUNT_COLLECTED' => '1000.00',
            'PROJECT_CATEGORY' => 'DEFAULT',
            'USER' => '$safeGenUserA',
            'ORG' => `$orgId,
            'REMARK' => `$marker . ' current project',
            'DELETE_FLAG' => 'NOT_DELETE',
            'CREATE_TIME' => '2026-04-07 09:00:00',
            'VERSION' => 0,
            'REBATE_AMOUNT' => '100.00',
            'DEAL_AMOUNT' => '0',
            'HISTORY_AMOUNT' => '0.00',
            'TOTAL_RETURN_AMOUNT' => '0.00',
            'TOTAL_REFUND_AMOUNT' => '0.00',
        ]),
        array_merge(`$audit, [
            'ID' => '$safeProjectPrevious',
            'CUSTOMER' => '$safeCustomerId',
            'PROJECT_NAME' => `$marker . ' previous project',
            'PROJECT_STATE' => 'COMPLETED',
            'PLAY_STATE' => 'PAID',
            'VISIBILITY' => 'PRIVATE',
            'INIT_PRICE' => '700.00',
            'TOTAL_PRICE' => '700.00',
            'AMOUNT_COLLECTED' => '700.00',
            'PROJECT_CATEGORY' => 'DEFAULT',
            'USER' => '$safeGenUserA',
            'ORG' => `$orgId,
            'REMARK' => `$marker . ' previous project',
            'DELETE_FLAG' => 'NOT_DELETE',
            'CREATE_TIME' => '2026-03-10 09:00:00',
            'VERSION' => 0,
            'REBATE_AMOUNT' => '50.00',
            'DEAL_AMOUNT' => '0',
            'HISTORY_AMOUNT' => '0.00',
            'TOTAL_RETURN_AMOUNT' => '0.00',
            'TOTAL_REFUND_AMOUNT' => '0.00',
        ]),
    ]);

    think\facade\Db::name('biz_payment_record')->insertAll([
        array_merge(`$audit, [
            'ID' => '$safePaymentCurrent',
            'OBJECT_ID' => '$safeProjectCurrent',
            'TARGET_ID' => '$safePaymentTarget',
            'SERIAL_ID' => '$safePaymentSerialCurrent',
            'PROCESS_ID' => '$safePaymentProcessCurrent',
            'SETTLEMENT_CATEGORY' => 'PROJECT_PLAY',
            'PAYER' => `$marker . ' payer',
            'REMARK' => `$marker . ' current payment',
            'PAYER_TIME' => '2026-04-08 10:00:00',
            'AMOUNT' => '900.00',
            'DELETE_FLAG' => 'NOT_DELETE',
            'CREATE_TIME' => '2026-04-08 10:00:00',
            'USER' => '$safeGenUserA',
            'ORG' => `$orgId,
        ]),
        array_merge(`$audit, [
            'ID' => '$safePaymentPrevious',
            'OBJECT_ID' => '$safeProjectPrevious',
            'TARGET_ID' => '$safePaymentTarget',
            'SERIAL_ID' => '$safePaymentSerialPrevious',
            'PROCESS_ID' => '$safePaymentProcessPrevious',
            'SETTLEMENT_CATEGORY' => 'PROJECT_PLAY',
            'PAYER' => `$marker . ' payer',
            'REMARK' => `$marker . ' previous payment',
            'PAYER_TIME' => '2026-04-09 10:00:00',
            'AMOUNT' => '650.00',
            'DELETE_FLAG' => 'NOT_DELETE',
            'CREATE_TIME' => '2026-04-09 10:00:00',
            'USER' => '$safeGenUserA',
            'ORG' => `$orgId,
        ]),
    ]);

    think\facade\Db::name('biz_leave_application')->insertAll([
        array_merge(`$audit, [
            'ID' => '$safeLeaveA',
            'USER_ID' => '$safeGenUserA',
            'PROCESS_ID' => '$safeLeaveProcessA',
            'category' => 'leaveOfAbsence',
            'AMOUNT' => '1.50',
            'REMARK' => `$marker . ' leave a',
            'START_TIME' => '2026-04-10 08:00:00',
            'END_TIME' => '2026-04-11 12:00:00',
            'DELETE_FLAG' => 'NOT_DELETE',
        ]),
        array_merge(`$audit, [
            'ID' => '$safeLeaveB',
            'USER_ID' => '$safeGenUserB',
            'PROCESS_ID' => '$safeLeaveProcessB',
            'category' => 'leaveOfAbsence',
            'AMOUNT' => '9.00',
            'REMARK' => `$marker . ' leave b',
            'START_TIME' => '2026-03-31 12:00:00',
            'END_TIME' => '2026-04-02 12:00:00',
            'DELETE_FLAG' => 'NOT_DELETE',
        ]),
    ]);
});

echo json_encode([
    'ok' => true,
    'operatorId' => `$operatorId,
    'orgId' => `$orgId,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
"@
}

function New-RemoteStateCode {
    $userIds = ConvertTo-PhpStringArray -Values @($script:ids.directUserId, $script:ids.genUserA, $script:ids.genUserB)
    $projectIds = ConvertTo-PhpStringArray -Values @($script:ids.projectCurrent, $script:ids.projectPrevious)
    $paymentIds = ConvertTo-PhpStringArray -Values @($script:ids.paymentCurrent, $script:ids.paymentPrevious)
    $leaveIds = ConvertTo-PhpStringArray -Values @($script:ids.leaveA, $script:ids.leaveB)
    $safeDirectUser = $script:ids.directUserId.Replace("'", "\'")
    $safeGenUserA = $script:ids.genUserA.Replace("'", "\'")
    $safeGenUserB = $script:ids.genUserB.Replace("'", "\'")
    $safeGenerateSalaryTime = $script:generateSalaryTime.Replace("'", "\'")
    $safeCustomerId = $script:ids.customerId.Replace("'", "\'")

@"
<?php
require 'vendor/autoload.php';
`$app = new think\App();
`$app->initialize();
`$userIds = $userIds;
`$projectIds = $projectIds;
`$paymentIds = $paymentIds;
`$leaveIds = $leaveIds;

`$generated = [];
foreach (think\facade\Db::name('biz_payroll')
    ->whereIn('USER', ['$safeGenUserA', '$safeGenUserB'])
    ->where('SALARY_TIME', '$safeGenerateSalaryTime')
    ->where(function (`$query): void {
        `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
    })
    ->select()
    ->toArray() as `$row) {
    `$generated[(string)`$row['USER']] = `$row;
}

echo json_encode([
    'generated' => `$generated,
    'activeResidual' => [
        'payroll' => think\facade\Db::name('biz_payroll')->whereIn('USER', `$userIds)->where(function (`$query): void {
            `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
        })->count(),
        'users' => think\facade\Db::name('sys_user')->whereIn('ID', `$userIds)->count(),
        'projects' => think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->count(),
        'payments' => think\facade\Db::name('biz_payment_record')->whereIn('ID', `$paymentIds)->count(),
        'leaves' => think\facade\Db::name('biz_leave_application')->whereIn('ID', `$leaveIds)->count(),
        'customer' => think\facade\Db::name('customer')->where('ID', '$safeCustomerId')->count(),
    ],
    'allResidual' => [
        'payroll' => think\facade\Db::name('biz_payroll')->whereIn('USER', `$userIds)->count(),
        'users' => think\facade\Db::name('sys_user')->whereIn('ID', `$userIds)->count(),
        'projects' => think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->count(),
        'payments' => think\facade\Db::name('biz_payment_record')->whereIn('ID', `$paymentIds)->count(),
        'leaves' => think\facade\Db::name('biz_leave_application')->whereIn('ID', `$leaveIds)->count(),
        'customer' => think\facade\Db::name('customer')->where('ID', '$safeCustomerId')->count(),
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
"@
}

$results = New-Object System.Collections.Generic.List[object]

try {
    Invoke-RemotePhpJson -Code (New-RemoteCleanupCode) | Out-Null
    $setup = Invoke-RemotePhpJson -Code (New-RemoteSetupCode)
    Assert-Equal -Actual $setup.ok -Expected $true -Name 'remote setup'
    $orgId = [string]$setup.orgId
    if ([string]::IsNullOrWhiteSpace($orgId)) {
        throw 'remote setup returned empty orgId'
    }

    $hr = New-Session -Account $HrAccount
    $headers = $hr.Headers

    $missingGenerate = Invoke-OaApi -Method POST -Path '/biz/bizpayroll/generate/add' -Headers $headers -Body @{
        salaryTime = $generateSalaryTime
        socialSecurity = '123.45'
    } -AllowFailure
    Assert-Equal -Actual $missingGenerate.code -Expected 400 -Name 'payroll generate missing user'

    $directAdd = Invoke-OaApi -Method POST -Path '/biz/bizpayroll/add' -Headers $headers -Body @{
        user = $ids.directUserId
        org = $orgId
        salaryTime = $directSalaryTime
        basicSalary = '3600.00'
        postWage = '400.00'
        baseAmount = '4000.00'
        payableAmount = '3900.00'
        socialSecurity = '200.00'
        actualAmount = '3700.00'
        yearEndBonus = '100.00'
        publicAccount = '3500.00'
        privateAccount = '200.00'
        remark = "$marker direct add"
    }
    $directPayrollId = [string]$directAdd.data.id
    if ([string]::IsNullOrWhiteSpace($directPayrollId)) {
        throw 'direct payroll add returned empty id'
    }
    $directDetail = Invoke-OaApi -Method GET -Path ("/biz/bizpayroll/detail?id={0}" -f [uri]::EscapeDataString($directPayrollId)) -Headers $headers
    Assert-Equal -Actual $directDetail.data.user -Expected $ids.directUserId -Name 'direct payroll user'
    Assert-DecimalEqual -Actual $directDetail.data.actualAmount -Expected '3700.00' -Name 'direct payroll actual amount'
    Assert-DecimalEqual -Actual $directDetail.data.publicAccount -Expected '3500.00' -Name 'direct payroll public account'

    $directEdit = Invoke-OaApi -Method POST -Path '/biz/bizpayroll/edit' -Headers $headers -Body @{
        id = $directPayrollId
        actualAmount = '3710.00'
        socialSecurity = '210.00'
    }
    Assert-Equal -Actual $directEdit.data.count -Expected 1 -Name 'direct payroll edit count'
    $directAfterEdit = Invoke-OaApi -Method GET -Path ("/biz/bizpayroll/detail?id={0}" -f [uri]::EscapeDataString($directPayrollId)) -Headers $headers
    Assert-DecimalEqual -Actual $directAfterEdit.data.actualAmount -Expected '3710.00' -Name 'direct payroll edited actual amount'
    Assert-DecimalEqual -Actual $directAfterEdit.data.socialSecurity -Expected '210.00' -Name 'direct payroll edited social security'

    $directBatchEdit = Invoke-OaApi -Method POST -Path '/biz/bizpayroll/bath/edit' -Headers $headers -Body @{
        list = @(@{
            id = $directPayrollId
            actualAmount = '3720.00'
            rateCommission = '5.00'
        })
    }
    Assert-Equal -Actual $directBatchEdit.data.count -Expected 1 -Name 'direct payroll batch edit count'
    $directAfterBatch = Invoke-OaApi -Method GET -Path ("/biz/bizpayroll/detail?id={0}" -f [uri]::EscapeDataString($directPayrollId)) -Headers $headers
    Assert-DecimalEqual -Actual $directAfterBatch.data.actualAmount -Expected '3720.00' -Name 'direct payroll batch actual amount'
    Assert-DecimalEqual -Actual $directAfterBatch.data.rateCommission -Expected '5.00' -Name 'direct payroll batch rate commission'

    $directDelete = Invoke-OaApi -Method POST -Path '/biz/bizpayroll/delete' -Headers $headers -Body @{ id = $directPayrollId }
    Assert-Equal -Actual $directDelete.data.count -Expected 1 -Name 'direct payroll delete count'
    $directGone = Invoke-OaApi -Method GET -Path ("/biz/bizpayroll/detail?id={0}" -f [uri]::EscapeDataString($directPayrollId)) -Headers $headers -AllowFailure
    if ([string]$directGone.code -eq '200') {
        throw 'direct payroll detail should not be active after delete'
    }
    $results.Add([pscustomobject]@{ scope = 'payroll'; account = $HrAccount; endpoint = '/biz/bizpayroll/add -> detail -> edit -> bath/edit -> delete'; id = $directPayrollId; ok = $true }) | Out-Null

    $generate = Invoke-OaApi -Method POST -Path '/biz/bizpayroll/generate/add' -Headers $headers -Body @{
        user = @($ids.genUserA, $ids.genUserB)
        salaryTime = $generateSalaryTime
        socialSecurity = '123.45'
    }
    Assert-Equal -Actual $generate.data.count -Expected 2 -Name 'payroll generate count'
    $generatedIds = @($generate.data.ids | ForEach-Object { [string]$_ })
    Assert-IntEqual -Actual $generatedIds.Count -Expected 2 -Name 'payroll generated id count'

    $state = Invoke-RemotePhpJson -Code (New-RemoteStateCode)
    $rowA = $state.generated.($ids.genUserA)
    $rowB = $state.generated.($ids.genUserB)
    if ($null -eq $rowA -or $null -eq $rowB) {
        throw 'generated payroll rows were not found'
    }
    Assert-DecimalEqual -Actual $rowA.BASIC_SALARY -Expected '2400.00' -Name 'generated user a basic salary'
    Assert-DecimalEqual -Actual $rowA.TRANSACTION_VOLUME -Expected '1000.00' -Name 'generated user a transaction volume'
    Assert-DecimalEqual -Actual $rowA.RECEIVED_AMOUNT -Expected '900.00' -Name 'generated user a received amount'
    Assert-DecimalEqual -Actual $rowA.BEFORE_RECEIVED_AMOUNT -Expected '650.00' -Name 'generated user a before received amount'
    Assert-DecimalEqual -Actual $rowA.VACATION -Expected '1.50' -Name 'generated user a vacation'
    Assert-DecimalEqual -Actual $rowA.VACATION_SUB_AMOUNT -Expected '150.00' -Name 'generated user a vacation sub amount'
    Assert-DecimalEqual -Actual $rowA.PAYABLE_AMOUNT -Expected '2250.00' -Name 'generated user a payable amount'
    Assert-DecimalEqual -Actual $rowA.ACTUAL_AMOUNT -Expected '2126.55' -Name 'generated user a actual amount'
    Assert-DecimalEqual -Actual $rowB.BASIC_SALARY -Expected '1200.00' -Name 'generated user b basic salary'
    Assert-DecimalEqual -Actual $rowB.TRANSACTION_VOLUME -Expected '0.00' -Name 'generated user b transaction volume'
    Assert-DecimalEqual -Actual $rowB.VACATION -Expected '1.50' -Name 'generated user b cross-month vacation'
    Assert-DecimalEqual -Actual $rowB.VACATION_SUB_AMOUNT -Expected '75.00' -Name 'generated user b vacation sub amount'
    Assert-DecimalEqual -Actual $rowB.PAYABLE_AMOUNT -Expected '1125.00' -Name 'generated user b payable amount'
    Assert-DecimalEqual -Actual $rowB.ACTUAL_AMOUNT -Expected '1001.55' -Name 'generated user b actual amount'
    $results.Add([pscustomobject]@{ scope = 'payroll'; account = $HrAccount; endpoint = '/biz/bizpayroll/generate/add'; ids = $generatedIds; ok = $true }) | Out-Null

    $generatedDelete = Invoke-OaApi -Method POST -Path '/biz/bizpayroll/delete' -Headers $headers -Body @{ ids = $generatedIds }
    Assert-Equal -Actual $generatedDelete.data.count -Expected 2 -Name 'generated payroll delete count'
    $afterLogicalDelete = Invoke-RemotePhpJson -Code (New-RemoteStateCode)
    Assert-IntEqual -Actual $afterLogicalDelete.activeResidual.payroll -Expected 0 -Name 'active payroll residual after logical delete'

    $cleanup = Invoke-RemotePhpJson -Code (New-RemoteCleanupCode)
    Assert-Equal -Actual $cleanup.ok -Expected $true -Name 'remote cleanup'
    $afterCleanup = Invoke-RemotePhpJson -Code (New-RemoteStateCode)
    Assert-IntEqual -Actual $afterCleanup.allResidual.payroll -Expected 0 -Name 'payroll residual'
    Assert-IntEqual -Actual $afterCleanup.allResidual.users -Expected 0 -Name 'user residual'
    Assert-IntEqual -Actual $afterCleanup.allResidual.projects -Expected 0 -Name 'project residual'
    Assert-IntEqual -Actual $afterCleanup.allResidual.payments -Expected 0 -Name 'payment residual'
    Assert-IntEqual -Actual $afterCleanup.allResidual.leaves -Expected 0 -Name 'leave residual'
    Assert-IntEqual -Actual $afterCleanup.allResidual.customer -Expected 0 -Name 'customer residual'

    [pscustomobject]@{
        ok = $true
        marker = $marker
        account = $HrAccount
        ids = $ids
        directPayrollId = $directPayrollId
        generatedPayrollIds = $generatedIds
        results = $results
        verification = [pscustomobject]@{
            directActualAfterBatchEdit = '3720.00'
            directDeletedFromDetail = $true
            generatedCount = 2
            generatedUserA = [pscustomobject]@{
                transactionVolume = '1000.00'
                receivedAmount = '900.00'
                beforeReceivedAmount = '650.00'
                vacation = '1.50'
                actualAmount = '2126.55'
            }
            generatedUserB = [pscustomobject]@{
                vacation = '1.50'
                actualAmount = '1001.55'
            }
            activePayrollAfterLogicalDelete = 0
            residualRowsAfterCleanup = $afterCleanup.allResidual
        }
    } | ConvertTo-Json -Depth 12
} finally {
    try {
        Invoke-RemotePhpJson -Code (New-RemoteCleanupCode) | Out-Null
    } catch {
        Write-Warning "final cleanup failed: $($_.Exception.Message)"
    }
}
