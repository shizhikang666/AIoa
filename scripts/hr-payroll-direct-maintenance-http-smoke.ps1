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

    return $raw | ConvertFrom-Json
}

function Invoke-JsonPost {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [object]$Body = @{},
        [string]$Token = ''
    )

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-hr-direct-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    try {
        $json = ConvertTo-Json -InputObject $Body -Depth 10
        $utf8NoBom = New-Object System.Text.UTF8Encoding -ArgumentList $false
        [System.IO.File]::WriteAllText($bodyPath, $json, $utf8NoBom)

        $args = @('-sS', '-X', 'POST', $Url, '-H', 'Content-Type: application/json', '--data-binary', "@$bodyPath")
        if ($Token.Trim() -ne '') {
            $args = @('-sS', '-X', 'POST', $Url, '-H', "Authorization: Bearer $Token", '-H', 'Content-Type: application/json', '--data-binary', "@$bodyPath")
        }

        $raw = & curl.exe @args
        if ($LASTEXITCODE -ne 0) {
            throw "HTTP POST failed: $Url"
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
        throw "$Name expected code=$Expected, got code=$($Json.code), message=$($Json.message)"
    }
}

function Assert-Decimal {
    param(
        [Parameter(Mandatory = $true)]$Actual,
        [Parameter(Mandatory = $true)][string]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    if ([decimal]$Actual -ne [decimal]$Expected) {
        throw "$Name expected $Expected, got $Actual"
    }
}

function New-SmokeId {
    param([Parameter(Mandatory = $true)][string]$Prefix)

    return $Prefix + ([Guid]::NewGuid().ToString('N').Substring(0, 20 - $Prefix.Length))
}

$envMap = Get-EnvMap -Path $EnvPath
$account = ''
if ($envMap.ContainsKey('LOCAL_SUPER_ADMIN_ACCOUNT')) {
    $account = [string]$envMap['LOCAL_SUPER_ADMIN_ACCOUNT']
}
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
`$auth['device'] = 'CODEX_HR_PAYROLL_DIRECT_MAINTENANCE_SMOKE';
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
    'adminUserId' => (string)`$user['ID'],
    'tenantId' => `$tenantId,
    'orgId' => `$orgId,
], JSON_UNESCAPED_SLASHES);
"@

$session = Invoke-PhpJson -Code $sessionCode
$token = [string]$session.token
$adminUserId = [string]$session.adminUserId
$tenantId = [string]$session.tenantId
$orgId = [string]$session.orgId
if ($token.Trim() -eq '' -or $adminUserId.Trim() -eq '' -or $tenantId.Trim() -eq '' -or $orgId.Trim() -eq '') {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'hrd' + ([Guid]::NewGuid().ToString('N').Substring(0, 9))
$targetUserId = New-SmokeId -Prefix 'HRU'
$vacationId = New-SmokeId -Prefix 'HRV'
$leaveProcessId = New-SmokeId -Prefix 'HRP'
$overlapProcessId = New-SmokeId -Prefix 'HRO'
$payrollBadMarker = New-SmokeId -Prefix 'HPB'

$safePrefix = $prefix.Replace("'", "\'")
$safeTargetUserId = $targetUserId.Replace("'", "\'")
$safeVacationId = $vacationId.Replace("'", "\'")
$safeTenantId = $tenantId.Replace("'", "\'")
$safeOrgId = $orgId.Replace("'", "\'")
$safeAdminUserId = $adminUserId.Replace("'", "\'")
$safeLeaveProcessId = $leaveProcessId.Replace("'", "\'")
$safeOverlapProcessId = $overlapProcessId.Replace("'", "\'")
$safePayrollBadMarker = $payrollBadMarker.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
think\facade\Db::transaction(function (): void {
    think\facade\Db::name('biz_payroll')->where('USER', '$safeTargetUserId')->delete();
    think\facade\Db::name('biz_leave_application')->where('USER_ID', '$safeTargetUserId')->delete();
    think\facade\Db::name('biz_user_vacation')->where('USER_ID', '$safeTargetUserId')->delete();
    think\facade\Db::name('sys_user')->where('ID', '$safeTargetUserId')->delete();
});
echo 'ok';
"@

Invoke-Php -Code $cleanupCode | Out-Null

$setupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
think\facade\Db::transaction(function () use (`$now): void {
    think\facade\Db::name('sys_user')->insert([
        'ID' => '$safeTargetUserId',
        'ACCOUNT' => '$safePrefix-target',
        'NAME' => '$safePrefix target user',
        'ORG_ID' => '$safeOrgId',
        'USER_STATUS' => 'ENABLE',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => '$safeAdminUserId',
        'TENANT_ID' => '$safeTenantId',
        'BANK_NAME' => '',
        'BANK_ACCOUNT' => '',
        'BASIC_SALARY' => '3600.00',
    ]);
    think\facade\Db::name('biz_user_vacation')->insert([
        'ID' => '$safeVacationId',
        'USER_ID' => '$safeTargetUserId',
        'AMOUNT' => '10.00',
        'USED_AMOUNT' => '1.00',
        'CATEGORY' => 'annualLeave',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => '$safeAdminUserId',
        'UPDATE_TIME' => null,
        'UPDATE_USER' => null,
        'TENANT_ID' => '$safeTenantId',
        'VERSION' => 0,
    ]);
});
echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
"@

Invoke-PhpJson -Code $setupCode | Out-Null

$leaveStart = (Get-Date -Year ([int](Get-Date).Year) -Month 12 -Day 20 -Hour 9 -Minute 0 -Second 0).ToString('yyyy-MM-dd HH:mm:ss')
$leaveEnd = (Get-Date -Year ([int](Get-Date).Year) -Month 12 -Day 20 -Hour 18 -Minute 0 -Second 0).ToString('yyyy-MM-dd HH:mm:ss')
$overlapStart = (Get-Date -Year ([int](Get-Date).Year) -Month 12 -Day 20 -Hour 12 -Minute 0 -Second 0).ToString('yyyy-MM-dd HH:mm:ss')
$overlapEnd = (Get-Date -Year ([int](Get-Date).Year) -Month 12 -Day 21 -Hour 9 -Minute 0 -Second 0).ToString('yyyy-MM-dd HH:mm:ss')
$salaryTime = (Get-Date -Year ([int](Get-Date).Year) -Month 11 -Day 1 -Hour 0 -Minute 0 -Second 0).ToString('yyyy-MM-dd HH:mm:ss')

try {
    $noTokenLeave = Invoke-JsonPost -Url ($baseUrl + '/biz/bizleaveapplication/add') -Body @{}
    Assert-Code -Json $noTokenLeave -Expected 401 -Name 'leave add without token'
    Write-Host '/biz/bizleaveapplication/add no-token code=401'

    $noTokenPayroll = Invoke-JsonPost -Url ($baseUrl + '/biz/bizpayroll/add') -Body @{}
    Assert-Code -Json $noTokenPayroll -Expected 401 -Name 'payroll add without token'
    Write-Host '/biz/bizpayroll/add no-token code=401'

    $missingLeave = Invoke-JsonPost -Url ($baseUrl + '/biz/bizleaveapplication/add') -Token $token -Body @{}
    Assert-Code -Json $missingLeave -Expected 400 -Name 'leave add missing body'
    Write-Host '/biz/bizleaveapplication/add missing-body code=400'

    $missingPayroll = Invoke-JsonPost -Url ($baseUrl + '/biz/bizpayroll/add') -Token $token -Body @{}
    Assert-Code -Json $missingPayroll -Expected 400 -Name 'payroll add missing body'
    Write-Host '/biz/bizpayroll/add missing-body code=400'

    $leave = Invoke-JsonPost -Url ($baseUrl + '/biz/bizleaveapplication/add') -Token $token -Body @{
        userId = $targetUserId
        processId = $leaveProcessId
        category = 'annualLeave'
        amount = '1.50'
        remark = 'codex hr direct leave add'
        startTime = $leaveStart
        endTime = $leaveEnd
        objectId = $leaveProcessId
    }
    Assert-Code -Json $leave -Expected 200 -Name 'leave add annual'
    $leaveId = [string]$leave.data.id
    if ($leaveId.Trim() -eq '') {
        throw 'leave add did not return data.id'
    }
    Write-Host '/biz/bizleaveapplication/add annual code=200'

    $overlap = Invoke-JsonPost -Url ($baseUrl + '/biz/bizleaveapplication/add') -Token $token -Body @{
        userId = $targetUserId
        processId = $overlapProcessId
        category = 'leaveOfAbsence'
        amount = '0.50'
        remark = 'codex hr direct overlap'
        startTime = $overlapStart
        endTime = $overlapEnd
    }
    Assert-Code -Json $overlap -Expected 400 -Name 'leave add overlapping range'
    Write-Host '/biz/bizleaveapplication/add overlap code=400'

    $badPayroll = Invoke-JsonPost -Url ($baseUrl + '/biz/bizpayroll/add') -Token $token -Body @{
        user = $targetUserId
        org = $safePayrollBadMarker
        salaryTime = $salaryTime
        actualAmount = '1.00'
    }
    Assert-Code -Json $badPayroll -Expected 400 -Name 'payroll add org mismatch'
    Write-Host '/biz/bizpayroll/add org-mismatch code=400'

    $payroll = Invoke-JsonPost -Url ($baseUrl + '/biz/bizpayroll/add') -Token $token -Body @{
        user = $targetUserId
        org = $orgId
        salaryTime = $salaryTime
        basicSalary = '3600.00'
        postWage = '400.00'
        baseAmount = '4000.00'
        payableAmount = '3900.00'
        socialSecurity = '200.00'
        actualAmount = '3700.00'
        yearEndBonus = '100.00'
        publicAccount = '3500.00'
        privateAccount = '200.00'
        remark = 'codex hr direct payroll add'
    }
    Assert-Code -Json $payroll -Expected 200 -Name 'payroll add'
    $payrollId = [string]$payroll.data.id
    if ($payrollId.Trim() -eq '') {
        throw 'payroll add did not return data.id'
    }
    Write-Host '/biz/bizpayroll/add code=200'

    $safeLeaveId = $leaveId.Replace("'", "\'")
    $safePayrollId = $payrollId.Replace("'", "\'")
    $verifyCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$leave = think\facade\Db::name('biz_leave_application')->where('ID', '$safeLeaveId')->find();
`$payroll = think\facade\Db::name('biz_payroll')->where('ID', '$safePayrollId')->find();
`$vacation = think\facade\Db::name('biz_user_vacation')->where('ID', '$safeVacationId')->find();
echo json_encode([
    'leave' => `$leave,
    'payroll' => `$payroll,
    'vacation' => `$vacation,
    'counts' => [
        'leave' => think\facade\Db::name('biz_leave_application')->where('USER_ID', '$safeTargetUserId')->count(),
        'payroll' => think\facade\Db::name('biz_payroll')->where('USER', '$safeTargetUserId')->count(),
    ],
], JSON_UNESCAPED_SLASHES);
"@

    $state = Invoke-PhpJson -Code $verifyCode
    if ($null -eq $state.leave -or $null -eq $state.payroll -or $null -eq $state.vacation) {
        throw 'direct HR/payroll rows were not found'
    }
    if ([int]$state.counts.leave -ne 1 -or [int]$state.counts.payroll -ne 1) {
        throw "unexpected row counts: $($state.counts | ConvertTo-Json -Compress)"
    }
    Assert-Decimal -Actual $state.leave.AMOUNT -Expected '1.50' -Name 'leave amount'
    Assert-Decimal -Actual $state.vacation.USED_AMOUNT -Expected '2.50' -Name 'vacation used amount'
    Assert-Decimal -Actual $state.payroll.ACTUAL_AMOUNT -Expected '3700.00' -Name 'payroll actual amount'
    Assert-Decimal -Actual $state.payroll.PUBLIC_ACCOUNT -Expected '3500.00' -Name 'payroll public account'
    if ([string]$state.leave.DELETE_FLAG -ne 'NOT_DELETE' -or [string]$state.payroll.DELETE_FLAG -ne 'NOT_DELETE') {
        throw 'created rows were not active rows'
    }

    Write-Host 'hr payroll direct maintenance HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
