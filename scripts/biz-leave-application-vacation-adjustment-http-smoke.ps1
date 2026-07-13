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

function Invoke-JsonPost {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [string]$Token = '',
        [object]$Body = @{}
    )

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("biz-leave-vacation-adjustment-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    $Body | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $bodyPath -Encoding ASCII
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

function Invoke-PhpJson {
    param([Parameter(Mandatory = $true)][string]$Code)

    $raw = (& php -r $Code)
    if ($LASTEXITCODE -ne 0) {
        throw 'php code failed'
    }

    return (($raw -join "`n").TrimStart([char]0xFEFF) | ConvertFrom-Json)
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

function Assert-VacationState {
    param(
        [Parameter(Mandatory = $true)][string]$VacationId,
        [Parameter(Mandatory = $true)][string]$ExpectedUsedAmount,
        [Parameter(Mandatory = $true)][int]$ExpectedVersion
    )

    $safeVacationId = $VacationId.Replace("'", "\'")
    $queryCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('biz_user_vacation')
    ->where('ID', '$safeVacationId')
    ->field('ID,AMOUNT,USED_AMOUNT,CATEGORY,DELETE_FLAG,VERSION')
    ->find();
echo json_encode(['row' => `$row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    $state = Invoke-PhpJson -Code $queryCode
    if ($null -eq $state.row) {
        throw "vacation row missing: $VacationId"
    }
    if ([decimal]$state.row.USED_AMOUNT -ne [decimal]$ExpectedUsedAmount -or [int]$state.row.VERSION -ne $ExpectedVersion) {
        throw "vacation row mismatch: $($state.row | ConvertTo-Json -Compress)"
    }
}

function Assert-LeaveRow {
    param(
        [Parameter(Mandatory = $true)][string]$LeaveId,
        [Parameter(Mandatory = $true)][string]$ExpectedCategory,
        [Parameter(Mandatory = $true)][string]$ExpectedAmount,
        [Parameter(Mandatory = $true)][string]$ExpectedDeleteFlag
    )

    $safeLeaveId = $LeaveId.Replace("'", "\'")
    $queryCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('biz_leave_application')
    ->where('ID', '$safeLeaveId')
    ->field('ID,`category`,AMOUNT,DELETE_FLAG')
    ->find();
echo json_encode(['row' => `$row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    $state = Invoke-PhpJson -Code $queryCode
    if ($null -eq $state.row) {
        throw "leave row missing: $LeaveId"
    }
    if ([string]$state.row.category -ne $ExpectedCategory -or [decimal]$state.row.AMOUNT -ne [decimal]$ExpectedAmount -or [string]$state.row.DELETE_FLAG -ne $ExpectedDeleteFlag) {
        throw "leave row mismatch: $($state.row | ConvertTo-Json -Compress)"
    }
}

function Remove-SmokeRows {
    param(
        [string]$VacationId = '',
        [string[]]$LeaveIds = @()
    )

    $safeVacationId = $VacationId.Replace("'", "\'")
    $safeLeaveIds = @($LeaveIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" })
    if ($safeVacationId.Trim() -eq '' -and $safeLeaveIds.Count -eq 0) {
        return
    }

    $leaveList = if ($safeLeaveIds.Count -gt 0) { $safeLeaveIds -join ',' } else { "''" }
    $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::transaction(function (): void {
    if ('$safeVacationId' !== '') {
        think\facade\Db::name('biz_user_vacation')->where('ID', '$safeVacationId')->delete();
    }
    think\facade\Db::name('biz_leave_application')->whereIn('ID', [$leaveList])->delete();
});
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

    [void](Invoke-PhpJson -Code $cleanupCode)
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
$contextCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_BIZ_LEAVE_VACATION_ADJUSTMENT_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => (string)`$user['ID'],
    'tenantId' => (string)(`$user['TENANT_ID'] ?? ''),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

$context = Invoke-PhpJson -Code $contextCode
$token = [string]$context.token
$userId = [string]$context.userId
$tenantId = [string]$context.tenantId
if ([string]::IsNullOrWhiteSpace($token) -or [string]::IsNullOrWhiteSpace($userId) -or [string]::IsNullOrWhiteSpace($tenantId)) {
    throw 'failed to create local smoke auth token'
}

$safeUserId = $userId.Replace("'", "\'")
$safeTenantId = $tenantId.Replace("'", "\'")
$setupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$newId = function (): string {
    return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
};
`$vacationId = `$newId();
`$leaveAId = `$newId();
`$leaveBId = `$newId();
`$now = date('Y-m-d H:i:s');
`$startA = date('Y-m-d 09:00:00', strtotime('+10 days'));
`$endA = date('Y-m-d 18:00:00', strtotime('+10 days'));
`$startB = date('Y-m-d 09:00:00', strtotime('+11 days'));
`$endB = date('Y-m-d 18:00:00', strtotime('+11 days'));
`$processA = 'codex-direct-leave-a-' . substr(`$leaveAId, -8);
`$processB = 'codex-direct-leave-b-' . substr(`$leaveBId, -8);
think\facade\Db::transaction(function () use (`$vacationId, `$leaveAId, `$leaveBId, `$now, `$startA, `$endA, `$startB, `$endB, `$processA, `$processB): void {
    think\facade\Db::name('biz_user_vacation')->insert([
        'ID' => `$vacationId,
        'USER_ID' => '$safeUserId',
        'AMOUNT' => '12.00',
        'USED_AMOUNT' => '8.25',
        'CATEGORY' => 'annualLeave',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => '$safeUserId',
        'UPDATE_TIME' => null,
        'UPDATE_USER' => null,
        'TENANT_ID' => '$safeTenantId',
        'VERSION' => 0,
    ]);
    foreach ([
        [`$leaveAId, `$processA, '2.00', `$startA, `$endA, 'codex direct leave annual edit row'],
        [`$leaveBId, `$processB, '1.25', `$startB, `$endB, 'codex direct leave annual delete row'],
    ] as `$row) {
        think\facade\Db::name('biz_leave_application')->insert([
            'ID' => `$row[0],
            'USER_ID' => '$safeUserId',
            'PROCESS_ID' => `$row[1],
            'category' => 'annualLeave',
            'AMOUNT' => `$row[2],
            'REMARK' => `$row[5],
            'START_TIME' => `$row[3],
            'END_TIME' => `$row[4],
            'DELETE_FLAG' => 'NOT_DELETE',
            'CREATE_TIME' => `$now,
            'CREATE_USER' => '$safeUserId',
            'UPDATE_TIME' => null,
            'UPDATE_USER' => null,
            'TENANT_ID' => '$safeTenantId',
            'OBJECT_ID' => null,
        ]);
    }
});
echo json_encode([
    'vacationId' => `$vacationId,
    'leaveAId' => `$leaveAId,
    'leaveBId' => `$leaveBId,
    'processA' => `$processA,
    'processB' => `$processB,
    'startA' => `$startA,
    'endA' => `$endA,
    'startB' => `$startB,
    'endB' => `$endB,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@

$setup = Invoke-PhpJson -Code $setupCode
$vacationId = [string]$setup.vacationId
$leaveAId = [string]$setup.leaveAId
$leaveBId = [string]$setup.leaveBId
$processA = [string]$setup.processA
$processB = [string]$setup.processB
$startA = [string]$setup.startA
$endA = [string]$setup.endA
$startB = [string]$setup.startB
$endB = [string]$setup.endB
$baseUrl = $BackendBaseUrl.TrimEnd('/')

try {
    $noTokenEdit = Invoke-JsonPost -Url ($baseUrl + '/biz/bizleaveapplication/edit') -Body @{}
    Assert-Code -Json $noTokenEdit -Expected 401 -Name 'leave application edit without token'
    Write-Host '/biz/bizleaveapplication/edit no-token code=401'

    $missingEdit = Invoke-JsonPost -Url ($baseUrl + '/biz/bizleaveapplication/edit') -Token $token -Body @{}
    Assert-Code -Json $missingEdit -Expected 400 -Name 'leave application edit missing id'
    Write-Host '/biz/bizleaveapplication/edit missing-id code=400'

    $noTokenDelete = Invoke-JsonPost -Url ($baseUrl + '/biz/bizleaveapplication/delete') -Body @{}
    Assert-Code -Json $noTokenDelete -Expected 401 -Name 'leave application delete without token'
    Write-Host '/biz/bizleaveapplication/delete no-token code=401'

    $missingDelete = Invoke-JsonPost -Url ($baseUrl + '/biz/bizleaveapplication/delete') -Token $token -Body @{}
    Assert-Code -Json $missingDelete -Expected 400 -Name 'leave application delete missing id'
    Write-Host '/biz/bizleaveapplication/delete missing-id code=400'

    $increase = Invoke-JsonPost -Url ($baseUrl + '/biz/bizleaveapplication/edit') -Token $token -Body @{
        id = $leaveAId
        userId = $userId
        processId = $processA
        category = 'annualLeave'
        amount = '3.25'
        remark = 'codex direct leave annual amount increase'
        startTime = $startA
        endTime = $endA
    }
    Assert-Code -Json $increase -Expected 200 -Name 'leave application annual edit increase'
    Assert-VacationState -VacationId $vacationId -ExpectedUsedAmount '9.50' -ExpectedVersion 1
    Assert-LeaveRow -LeaveId $leaveAId -ExpectedCategory 'annualLeave' -ExpectedAmount '3.25' -ExpectedDeleteFlag 'NOT_DELETE'
    Write-Host '/biz/bizleaveapplication/edit annual amount delta adjusted vacation'

    $toNonAnnual = Invoke-JsonPost -Url ($baseUrl + '/biz/bizleaveapplication/edit') -Token $token -Body @{
        id = $leaveAId
        userId = $userId
        processId = $processA
        category = 'leaveOfAbsence'
        amount = '3.25'
        remark = 'codex direct leave annual converted to nonannual'
        startTime = $startA
        endTime = $endA
    }
    Assert-Code -Json $toNonAnnual -Expected 200 -Name 'leave application annual to nonannual edit'
    Assert-VacationState -VacationId $vacationId -ExpectedUsedAmount '6.25' -ExpectedVersion 2
    Assert-LeaveRow -LeaveId $leaveAId -ExpectedCategory 'leaveOfAbsence' -ExpectedAmount '3.25' -ExpectedDeleteFlag 'NOT_DELETE'
    Write-Host '/biz/bizleaveapplication/edit annual category restore adjusted vacation'

    $insufficient = Invoke-JsonPost -Url ($baseUrl + '/biz/bizleaveapplication/edit') -Token $token -Body @{
        id = $leaveAId
        userId = $userId
        processId = $processA
        category = 'annualLeave'
        amount = '8.00'
        remark = 'codex direct leave insufficient annual edit'
        startTime = $startA
        endTime = $endA
    }
    Assert-Code -Json $insufficient -Expected 400 -Name 'leave application annual insufficient edit'
    Assert-VacationState -VacationId $vacationId -ExpectedUsedAmount '6.25' -ExpectedVersion 2
    Assert-LeaveRow -LeaveId $leaveAId -ExpectedCategory 'leaveOfAbsence' -ExpectedAmount '3.25' -ExpectedDeleteFlag 'NOT_DELETE'
    Write-Host '/biz/bizleaveapplication/edit insufficient annual balance rolled back'

    $delete = Invoke-JsonPost -Url ($baseUrl + '/biz/bizleaveapplication/delete') -Token $token -Body @{
        idList = @($leaveBId)
    }
    Assert-Code -Json $delete -Expected 200 -Name 'leave application annual delete'
    Assert-VacationState -VacationId $vacationId -ExpectedUsedAmount '5.00' -ExpectedVersion 3
    Assert-LeaveRow -LeaveId $leaveBId -ExpectedCategory 'annualLeave' -ExpectedAmount '1.25' -ExpectedDeleteFlag 'DELETED'
    Write-Host '/biz/bizleaveapplication/delete annual row restored vacation'

    Write-Host 'biz leave application vacation adjustment HTTP smoke passed'
} finally {
    Remove-SmokeRows -VacationId $vacationId -LeaveIds @($leaveAId, $leaveBId)
}
