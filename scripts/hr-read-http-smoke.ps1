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
        [Parameter(Mandatory = $true)][string]$Key,
        [string]$Default = ''
    )

    if ($EnvMap.ContainsKey($Key) -and [string]$EnvMap[$Key] -ne '') {
        return [string]$EnvMap[$Key]
    }

    return $Default
}

function Invoke-RawGet {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$Token
    )

    $raw = & curl.exe -sS -X GET $Url -H "Authorization: Bearer $Token"
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP GET failed: $Url"
    }

    return [string]::Join('', [string[]]$raw)
}

function Read-JsonPath {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path,
        [switch]$Optional
    )

    $value = $Json | node (Join-Path $PSScriptRoot 'json-read.js') $Path
    $exitCode = $LASTEXITCODE
    if ($exitCode -eq 2 -and $Optional) {
        return $null
    }
    if ($exitCode -ne 0) {
        throw "JSON path missing or invalid: $Path"
    }

    return [string]$value
}

function Assert-Ok {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $code = Read-JsonPath -Json $Json -Path 'code'
    if ([int]$code -ne 200) {
        throw "$Name returned code=$code"
    }
}

function Assert-Paths {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string[]]$Paths
    )

    foreach ($path in $Paths) {
        [void](Read-JsonPath -Json $Json -Path $path)
    }
}

function Has-Path {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path
    )

    return $null -ne (Read-JsonPath -Json $Json -Path $Path -Optional)
}

function Assert-PagedShape {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data.records', 'data.total', 'data.current', 'data.size', 'data.pages')
}

function Assert-PayrollRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.user",
        "$Prefix.userId",
        "$Prefix.headName",
        "$Prefix.name",
        "$Prefix.userAccount",
        "$Prefix.org",
        "$Prefix.orgId",
        "$Prefix.orgName",
        "$Prefix.salaryTime",
        "$Prefix.basicSalary",
        "$Prefix.actualAmount",
        "$Prefix.payableAmount",
        "$Prefix.tenantId"
    )
}

function Assert-LeaveRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.userId",
        "$Prefix.name",
        "$Prefix.orgId",
        "$Prefix.orgName",
        "$Prefix.processId",
        "$Prefix.category",
        "$Prefix.amount",
        "$Prefix.startTime",
        "$Prefix.endTime",
        "$Prefix.objectId",
        "$Prefix.tenantId"
    )
}

function Assert-VacationRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.userId",
        "$Prefix.userName",
        "$Prefix.amount",
        "$Prefix.usedAmount",
        "$Prefix.category",
        "$Prefix.deleteFlag",
        "$Prefix.tenantId",
        "$Prefix.version"
    )
}

function Assert-HistoryExcelRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.ID",
        "$Prefix.name",
        "$Prefix.NAME",
        "$Prefix.deleteFlag",
        "$Prefix.DELETE_FLAG",
        "$Prefix.extJson",
        "$Prefix.EXT_JSON",
        "$Prefix.tenantId",
        "$Prefix.TENANT_ID"
    )
}

$envMap = Get-EnvMap -Path $EnvPath
$account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
if ($account -eq '') {
    throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env'
}

$safeAccount = $account.Replace("'", "\'")
$tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_HR_READ_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

$payrollPage = Invoke-RawGet -Url "$baseUrl/biz/bizpayroll/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $payrollPage -Name 'biz payroll page'
if (Has-Path -Json $payrollPage -Path 'data.records.0') {
    Assert-PayrollRow -Json $payrollPage -Prefix 'data.records.0' -Name 'biz payroll page first row'
}

$payrollMyPage = Invoke-RawGet -Url "$baseUrl/biz/bizpayroll/mypage?current=1&size=1" -Token $token
Assert-PagedShape -Json $payrollMyPage -Name 'biz payroll mypage'
if (Has-Path -Json $payrollMyPage -Path 'data.records.0') {
    Assert-PayrollRow -Json $payrollMyPage -Prefix 'data.records.0' -Name 'biz payroll mypage first row'
}

$payrollFirstId = [string](Read-JsonPath -Json $payrollPage -Path 'data.records.0.id' -Optional)
if ($payrollFirstId.Trim() -ne '') {
    $encodedId = [System.Uri]::EscapeDataString($payrollFirstId.Trim())
    $payrollDetail = Invoke-RawGet -Url "$baseUrl/biz/bizpayroll/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $payrollDetail -Name 'biz payroll detail'
    Assert-PayrollRow -Json $payrollDetail -Prefix 'data' -Name 'biz payroll detail'
}

$leavePage = Invoke-RawGet -Url "$baseUrl/biz/bizleaveapplication/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $leavePage -Name 'biz leave application page'
if (Has-Path -Json $leavePage -Path 'data.records.0') {
    Assert-LeaveRow -Json $leavePage -Prefix 'data.records.0' -Name 'biz leave application page first row'
}

$leaveMyPage = Invoke-RawGet -Url "$baseUrl/biz/bizleaveapplication/my/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $leaveMyPage -Name 'biz leave application my page'
if (Has-Path -Json $leaveMyPage -Path 'data.records.0') {
    Assert-LeaveRow -Json $leaveMyPage -Prefix 'data.records.0' -Name 'biz leave application my page first row'
}

$leaveFirstId = [string](Read-JsonPath -Json $leavePage -Path 'data.records.0.id' -Optional)
if ($leaveFirstId.Trim() -ne '') {
    $encodedId = [System.Uri]::EscapeDataString($leaveFirstId.Trim())
    $leaveDetail = Invoke-RawGet -Url "$baseUrl/biz/bizleaveapplication/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $leaveDetail -Name 'biz leave application detail'
    Assert-LeaveRow -Json $leaveDetail -Prefix 'data' -Name 'biz leave application detail'
}

$vacationPage = Invoke-RawGet -Url "$baseUrl/biz/bizuservacation/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $vacationPage -Name 'biz user vacation page'
if (Has-Path -Json $vacationPage -Path 'data.records.0') {
    Assert-VacationRow -Json $vacationPage -Prefix 'data.records.0' -Name 'biz user vacation page first row'
}

$vacationDetail = Invoke-RawGet -Url "$baseUrl/biz/bizuservacation/detail" -Token $token
Assert-Ok -Json $vacationDetail -Name 'biz user vacation detail'
Assert-VacationRow -Json $vacationDetail -Prefix 'data' -Name 'biz user vacation detail'

$historyPage = Invoke-RawGet -Url "$baseUrl/biz/bizhistoryexcel/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $historyPage -Name 'biz history excel page'
if (Has-Path -Json $historyPage -Path 'data.records.0') {
    Assert-HistoryExcelRow -Json $historyPage -Prefix 'data.records.0' -Name 'biz history excel page first row'
}

$historyFirstId = [string](Read-JsonPath -Json $historyPage -Path 'data.records.0.id' -Optional)
if ($historyFirstId.Trim() -ne '') {
    $encodedId = [System.Uri]::EscapeDataString($historyFirstId.Trim())
    $historyDetail = Invoke-RawGet -Url "$baseUrl/biz/bizhistoryexcel/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $historyDetail -Name 'biz history excel detail'
    Assert-HistoryExcelRow -Json $historyDetail -Prefix 'data' -Name 'biz history excel detail'
}

Write-Host 'HR read HTTP smoke passed'
