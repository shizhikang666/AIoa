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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-payroll-generate-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    try {
        $json = ConvertTo-Json -InputObject $Data -Depth 10
        $utf8NoBom = New-Object System.Text.UTF8Encoding -ArgumentList $false
        [System.IO.File]::WriteAllText($tmp, $json, $utf8NoBom)

        $headers = @('-H', 'Content-Type: application/json')
        if ($Token.Trim() -ne '') {
            $headers += @('-H', "Authorization: Bearer $Token")
        }

        $raw = & curl.exe -sS -X POST $Url @headers --data-binary "@$tmp"
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
        throw "$Name returned code=$code, expected $Expected"
    }
}

function To-Decimal {
    param([Parameter(Mandatory = $true)]$Value)

    return [decimal]::Parse(([string]$Value), [System.Globalization.CultureInfo]::InvariantCulture)
}

function Assert-Decimal {
    param(
        [Parameter(Mandatory = $true)]$Actual,
        [Parameter(Mandatory = $true)][string]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    if ((To-Decimal $Actual) -ne (To-Decimal $Expected)) {
        throw "$Name expected $Expected, got $Actual"
    }
}

function New-SmokeId {
    param([Parameter(Mandatory = $true)][string]$Prefix)

    return $Prefix + ([Guid]::NewGuid().ToString('N').Substring(0, 20 - $Prefix.Length))
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
`$auth['device'] = 'CODEX_BIZ_PAYROLL_GENERATE_ADD_HTTP_SMOKE';
`$tenantId = (string)(`$user['TENANT_ID'] ?? '1');
if (`$tenantId === '') { `$tenantId = '1'; }
`$orgId = (string)(`$user['ORG_ID'] ?? '');
if (`$orgId === '') {
    `$orgId = (string)(think\facade\Db::name('sys_org')->where(function (`$query) {
        `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
    })->value('ID') ?? '');
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
$adminUserId = [string]$session.userId
$tenantId = [string]$session.tenantId
$orgId = [string]$session.orgId
if ($token.Trim() -eq '' -or $adminUserId.Trim() -eq '' -or $tenantId.Trim() -eq '' -or $orgId.Trim() -eq '') {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'bpg' + ([Guid]::NewGuid().ToString('N').Substring(0, 9))
$salaryTime = '2026-06-15 00:00:00'

$userA = New-SmokeId -Prefix 'BPU'
$userB = New-SmokeId -Prefix 'BPU'
$projectCurrent = New-SmokeId -Prefix 'BPS'
$projectPrevious = New-SmokeId -Prefix 'BPS'
$paymentCurrent = New-SmokeId -Prefix 'BPP'
$paymentPrevious = New-SmokeId -Prefix 'BPP'
$leaveA = New-SmokeId -Prefix 'BPL'
$leaveB = New-SmokeId -Prefix 'BPL'
$customerId = New-SmokeId -Prefix 'BPC'
$paymentTargetId = New-SmokeId -Prefix 'BPT'
$paymentSerialCurrent = New-SmokeId -Prefix 'BSC'
$paymentSerialPrevious = New-SmokeId -Prefix 'BSP'
$paymentProcessCurrent = New-SmokeId -Prefix 'BRC'
$paymentProcessPrevious = New-SmokeId -Prefix 'BRP'
$leaveProcessA = New-SmokeId -Prefix 'BLA'
$leaveProcessB = New-SmokeId -Prefix 'BLB'

$safePrefix = $prefix.Replace("'", "\'")
$safeUserA = $userA.Replace("'", "\'")
$safeUserB = $userB.Replace("'", "\'")
$safeProjectCurrent = $projectCurrent.Replace("'", "\'")
$safeProjectPrevious = $projectPrevious.Replace("'", "\'")
$safePaymentCurrent = $paymentCurrent.Replace("'", "\'")
$safePaymentPrevious = $paymentPrevious.Replace("'", "\'")
$safeLeaveA = $leaveA.Replace("'", "\'")
$safeLeaveB = $leaveB.Replace("'", "\'")
$safeCustomerId = $customerId.Replace("'", "\'")
$safePaymentTargetId = $paymentTargetId.Replace("'", "\'")
$safePaymentSerialCurrent = $paymentSerialCurrent.Replace("'", "\'")
$safePaymentSerialPrevious = $paymentSerialPrevious.Replace("'", "\'")
$safePaymentProcessCurrent = $paymentProcessCurrent.Replace("'", "\'")
$safePaymentProcessPrevious = $paymentProcessPrevious.Replace("'", "\'")
$safeLeaveProcessA = $leaveProcessA.Replace("'", "\'")
$safeLeaveProcessB = $leaveProcessB.Replace("'", "\'")
$safeTenantId = $tenantId.Replace("'", "\'")
$safeOrgId = $orgId.Replace("'", "\'")
$safeAdminUserId = $adminUserId.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_payroll')->whereIn('USER', ['$safeUserA', '$safeUserB'])->delete();
think\facade\Db::name('biz_leave_application')->whereIn('ID', ['$safeLeaveA', '$safeLeaveB'])->delete();
think\facade\Db::name('biz_payment_record')->whereIn('ID', ['$safePaymentCurrent', '$safePaymentPrevious'])->delete();
think\facade\Db::name('biz_sale_project')->whereIn('ID', ['$safeProjectCurrent', '$safeProjectPrevious'])->delete();
think\facade\Db::name('sys_user')->whereIn('ID', ['$safeUserA', '$safeUserB'])->delete();
echo 'ok';
"@

Invoke-Php -Code $cleanupCode | Out-Null

$setupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('sys_user')->insertAll([
    [
        'ID' => '$safeUserA',
        'ACCOUNT' => '$safePrefix-a',
        'NAME' => '$safePrefix user a',
        'ORG_ID' => '$safeOrgId',
        'USER_STATUS' => 'ENABLE',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => '$safeAdminUserId',
        'TENANT_ID' => '$safeTenantId',
        'BANK_NAME' => '',
        'BANK_ACCOUNT' => '',
        'BASIC_SALARY' => '2400.00',
    ],
    [
        'ID' => '$safeUserB',
        'ACCOUNT' => '$safePrefix-b',
        'NAME' => '$safePrefix user b',
        'ORG_ID' => '$safeOrgId',
        'USER_STATUS' => 'ENABLE',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => '$safeAdminUserId',
        'TENANT_ID' => '$safeTenantId',
        'BANK_NAME' => '',
        'BANK_ACCOUNT' => '',
        'BASIC_SALARY' => '1200.00',
    ],
]);
think\facade\Db::name('biz_sale_project')->insertAll([
    [
        'ID' => '$safeProjectCurrent',
        'CUSTOMER' => '$safeCustomerId',
        'PROJECT_NAME' => '$safePrefix current project',
        'PROJECT_STATE' => 'COMPLETED',
        'PLAY_STATE' => 'PAID',
        'VISIBILITY' => 'PRIVATE',
        'INIT_PRICE' => '1000.00',
        'TOTAL_PRICE' => '1000.00',
        'AMOUNT_COLLECTED' => '1000.00',
        'PROJECT_CATEGORY' => 'DEFAULT',
        'USER' => '$safeUserA',
        'ORG' => '$safeOrgId',
        'REMARK' => '$safePrefix-current',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => '2026-06-07 09:00:00',
        'CREATE_USER' => '$safeAdminUserId',
        'TENANT_ID' => '$safeTenantId',
        'VERSION' => 0,
        'REBATE_AMOUNT' => '100.00',
        'DEAL_AMOUNT' => 0,
        'HISTORY_AMOUNT' => '0.00',
    ],
    [
        'ID' => '$safeProjectPrevious',
        'CUSTOMER' => '$safeCustomerId',
        'PROJECT_NAME' => '$safePrefix previous project',
        'PROJECT_STATE' => 'COMPLETED',
        'PLAY_STATE' => 'PAID',
        'VISIBILITY' => 'PRIVATE',
        'INIT_PRICE' => '700.00',
        'TOTAL_PRICE' => '700.00',
        'AMOUNT_COLLECTED' => '700.00',
        'PROJECT_CATEGORY' => 'DEFAULT',
        'USER' => '$safeUserA',
        'ORG' => '$safeOrgId',
        'REMARK' => '$safePrefix-previous',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => '2026-05-10 09:00:00',
        'CREATE_USER' => '$safeAdminUserId',
        'TENANT_ID' => '$safeTenantId',
        'VERSION' => 0,
        'REBATE_AMOUNT' => '50.00',
        'DEAL_AMOUNT' => 0,
        'HISTORY_AMOUNT' => '0.00',
    ],
]);
think\facade\Db::name('biz_payment_record')->insertAll([
    [
        'ID' => '$safePaymentCurrent',
        'OBJECT_ID' => '$safeProjectCurrent',
        'TARGET_ID' => '$safePaymentTargetId',
        'SERIAL_ID' => '$safePaymentSerialCurrent',
        'PROCESS_ID' => '$safePaymentProcessCurrent',
        'SETTLEMENT_CATEGORY' => 'PROJECT_PLAY',
        'PAYER' => '$safePrefix payer',
        'REMARK' => '$safePrefix current payment',
        'PAYER_TIME' => '2026-06-08 10:00:00',
        'AMOUNT' => '900.00',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => '2026-06-08 10:00:00',
        'CREATE_USER' => '$safeAdminUserId',
        'TENANT_ID' => '$safeTenantId',
        'USER' => '$safeUserA',
        'ORG' => '$safeOrgId',
    ],
    [
        'ID' => '$safePaymentPrevious',
        'OBJECT_ID' => '$safeProjectPrevious',
        'TARGET_ID' => '$safePaymentTargetId',
        'SERIAL_ID' => '$safePaymentSerialPrevious',
        'PROCESS_ID' => '$safePaymentProcessPrevious',
        'SETTLEMENT_CATEGORY' => 'PROJECT_PLAY',
        'PAYER' => '$safePrefix payer',
        'REMARK' => '$safePrefix previous payment',
        'PAYER_TIME' => '2026-06-09 10:00:00',
        'AMOUNT' => '650.00',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => '2026-06-09 10:00:00',
        'CREATE_USER' => '$safeAdminUserId',
        'TENANT_ID' => '$safeTenantId',
        'USER' => '$safeUserA',
        'ORG' => '$safeOrgId',
    ],
]);
think\facade\Db::name('biz_leave_application')->insertAll([
    [
        'ID' => '$safeLeaveA',
        'USER_ID' => '$safeUserA',
        'PROCESS_ID' => '$safeLeaveProcessA',
        'category' => 'leaveOfAbsence',
        'AMOUNT' => '1.50',
        'REMARK' => '$safePrefix leave a',
        'START_TIME' => '2026-06-10 08:00:00',
        'END_TIME' => '2026-06-11 12:00:00',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => '$safeAdminUserId',
        'TENANT_ID' => '$safeTenantId',
    ],
    [
        'ID' => '$safeLeaveB',
        'USER_ID' => '$safeUserB',
        'PROCESS_ID' => '$safeLeaveProcessB',
        'category' => 'leaveOfAbsence',
        'AMOUNT' => '9.00',
        'REMARK' => '$safePrefix leave b',
        'START_TIME' => '2026-05-31 12:00:00',
        'END_TIME' => '2026-06-02 12:00:00',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => '$safeAdminUserId',
        'TENANT_ID' => '$safeTenantId',
    ],
]);
echo json_encode([
    'counts' => [
        'payroll' => think\facade\Db::name('biz_payroll')->count(),
        'leave' => think\facade\Db::name('biz_leave_application')->count(),
        'payment' => think\facade\Db::name('biz_payment_record')->count(),
        'project' => think\facade\Db::name('biz_sale_project')->count(),
        'user' => think\facade\Db::name('sys_user')->count(),
    ],
], JSON_UNESCAPED_SLASHES);
"@

try {
    $setup = Invoke-PhpJson -Code $setupCode

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/bizpayroll/generate/add" -Data @{
        user = @($userA)
        salaryTime = $salaryTime
        socialSecurity = '123.45'
    }
    Assert-Code -Json $noToken -Expected 401 -Name 'payroll generate without token'

    $missingUser = Invoke-RawPostJson -Url "$baseUrl/biz/bizpayroll/generate/add" -Token $token -Data @{
        salaryTime = $salaryTime
        socialSecurity = '123.45'
    }
    Assert-Code -Json $missingUser -Expected 400 -Name 'payroll generate missing user'

    $duplicateUser = Invoke-RawPostJson -Url "$baseUrl/biz/bizpayroll/generate/add" -Token $token -Data @{
        user = @($userA, $userA)
        salaryTime = $salaryTime
        socialSecurity = '123.45'
    }
    Assert-Code -Json $duplicateUser -Expected 400 -Name 'payroll generate duplicate user'

    $negativeSocialSecurity = Invoke-RawPostJson -Url "$baseUrl/biz/bizpayroll/generate/add" -Token $token -Data @{
        user = @($userA)
        salaryTime = $salaryTime
        socialSecurity = '-1'
    }
    Assert-Code -Json $negativeSocialSecurity -Expected 400 -Name 'payroll generate negative social security'

    $generate = Invoke-RawPostJson -Url "$baseUrl/biz/bizpayroll/generate/add" -Token $token -Data @{
        user = @($userA, $userB)
        salaryTime = $salaryTime
        socialSecurity = '123.45'
    }
    Assert-Code -Json $generate -Expected 200 -Name 'payroll generate'
    $count = [int](Read-JsonPath -Json $generate -Path 'data.count')
    if ($count -ne 2) {
        throw "payroll generate expected count=2, got $count"
    }

    $afterCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$rowA = think\facade\Db::name('biz_payroll')->where('USER', '$safeUserA')->where('SALARY_TIME', '$salaryTime')->find();
`$rowB = think\facade\Db::name('biz_payroll')->where('USER', '$safeUserB')->where('SALARY_TIME', '$salaryTime')->find();
echo json_encode([
    'a' => `$rowA,
    'b' => `$rowB,
    'counts' => [
        'payroll' => think\facade\Db::name('biz_payroll')->count(),
        'leave' => think\facade\Db::name('biz_leave_application')->count(),
        'payment' => think\facade\Db::name('biz_payment_record')->count(),
        'project' => think\facade\Db::name('biz_sale_project')->count(),
        'user' => think\facade\Db::name('sys_user')->count(),
    ],
], JSON_UNESCAPED_SLASHES);
"@
    $after = Invoke-PhpJson -Code $afterCode
    if ($null -eq $after.a -or $null -eq $after.b) {
        throw 'generated payroll rows were not found'
    }

    Assert-Decimal -Actual $after.a.BASIC_SALARY -Expected '2400.00' -Name 'user a basic salary'
    Assert-Decimal -Actual $after.a.TRANSACTION_VOLUME -Expected '1000.00' -Name 'user a transaction volume'
    Assert-Decimal -Actual $after.a.RECEIVED_AMOUNT -Expected '900.00' -Name 'user a current received amount'
    Assert-Decimal -Actual $after.a.BEFORE_RECEIVED_AMOUNT -Expected '650.00' -Name 'user a previous received amount'
    Assert-Decimal -Actual $after.a.VACATION -Expected '1.50' -Name 'user a vacation'
    Assert-Decimal -Actual $after.a.VACATION_SUB_AMOUNT -Expected '150.00' -Name 'user a vacation sub amount'
    Assert-Decimal -Actual $after.a.PAYABLE_AMOUNT -Expected '2250.00' -Name 'user a payable amount'
    Assert-Decimal -Actual $after.a.ACTUAL_AMOUNT -Expected '2126.55' -Name 'user a actual amount'

    Assert-Decimal -Actual $after.b.BASIC_SALARY -Expected '1200.00' -Name 'user b basic salary'
    Assert-Decimal -Actual $after.b.TRANSACTION_VOLUME -Expected '0.00' -Name 'user b transaction volume'
    Assert-Decimal -Actual $after.b.VACATION -Expected '1.50' -Name 'user b cross-month vacation'
    Assert-Decimal -Actual $after.b.VACATION_SUB_AMOUNT -Expected '75.00' -Name 'user b vacation sub amount'
    Assert-Decimal -Actual $after.b.PAYABLE_AMOUNT -Expected '1125.00' -Name 'user b payable amount'
    Assert-Decimal -Actual $after.b.ACTUAL_AMOUNT -Expected '1001.55' -Name 'user b actual amount'

    $expectedPayroll = [int]$setup.counts.payroll + 2
    if ([int]$after.counts.payroll -ne $expectedPayroll) {
        throw "payroll count expected $expectedPayroll, got $($after.counts.payroll)"
    }
    foreach ($name in @('leave', 'payment', 'project', 'user')) {
        if ([int]$after.counts.$name -ne [int]$setup.counts.$name) {
            throw "$name count changed during payroll generation"
        }
    }

    Write-Host 'biz payroll generate/add HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
