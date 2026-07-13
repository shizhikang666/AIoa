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

function Invoke-RawPostJson {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$Token,
        [Parameter(Mandatory = $true)][string]$Body
    )

    $raw = & curl.exe -sS -X POST $Url -H "Authorization: Bearer $Token" -H 'Content-Type: application/json' --data-binary $Body
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP POST failed: $Url"
    }

    return [string]::Join('', [string[]]$raw)
}

function Invoke-RawPostForm {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$Token,
        [Parameter(Mandatory = $true)][string]$Body
    )

    $raw = & curl.exe -sS -X POST $Url -H "Authorization: Bearer $Token" -H 'Content-Type: application/x-www-form-urlencoded' --data-binary $Body
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP POST failed: $Url"
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

function Assert-ProjectRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.customer",
        "$Prefix.customerName",
        "$Prefix.projectName",
        "$Prefix.projectState",
        "$Prefix.playState",
        "$Prefix.totalPrice",
        "$Prefix.amountCollected",
        "$Prefix.org",
        "$Prefix.orgName",
        "$Prefix.completionDate"
    )
}

function Assert-SettlementRecordRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.objectId",
        "$Prefix.targetId",
        "$Prefix.accountName",
        "$Prefix.serialId",
        "$Prefix.settlementCategory",
        "$Prefix.payerTime",
        "$Prefix.amount",
        "$Prefix.user",
        "$Prefix.org",
        "$Prefix.orgName"
    )
}

function Assert-ProductRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.productName",
        "$Prefix.productCategory",
        "$Prefix.purchasePrice",
        "$Prefix.salePrice",
        "$Prefix.minPrice",
        "$Prefix.category"
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
`$auth['device'] = 'CODEX_DATAREPORT_READ_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$emptyBody = '{}'
$yearBody = 'year=2026'

$amount = Invoke-RawPostJson -Url "$baseUrl/biz/bizdatareport/saleproject" -Token $token -Body $emptyBody
Assert-Ok -Json $amount -Name 'biz datareport saleproject amount'
Assert-Paths -Json $amount -Name 'biz datareport saleproject amount' -Paths @('data.amount')

$list = Invoke-RawPostJson -Url "$baseUrl/biz/bizdatareport/saleproject/list" -Token $token -Body $emptyBody
Assert-Ok -Json $list -Name 'biz datareport saleproject list'
Assert-Paths -Json $list -Name 'biz datareport saleproject list' -Paths @('data')
if (Has-Path -Json $list -Path 'data.0') {
    Assert-ProjectRow -Json $list -Prefix 'data.0' -Name 'biz datareport saleproject list first row'
}

$details = Invoke-RawPostJson -Url "$baseUrl/biz/bizdatareport/saleProjectList/details" -Token $token -Body $emptyBody
Assert-Ok -Json $details -Name 'biz datareport saleproject details'
Assert-Paths -Json $details -Name 'biz datareport saleproject details' -Paths @('data')
if (Has-Path -Json $details -Path 'data.0') {
    Assert-ProjectRow -Json $details -Prefix 'data.0' -Name 'biz datareport saleproject details first row'
    Assert-Paths -Json $details -Name 'biz datareport saleproject details nested collections' -Paths @('data.0.productList', 'data.0.returnOrders')
}

$report = Invoke-RawPostJson -Url "$baseUrl/biz/bizdatareport/saleproject/report" -Token $token -Body $emptyBody
Assert-Ok -Json $report -Name 'biz datareport saleproject report'
Assert-Paths -Json $report -Name 'biz datareport saleproject report' -Paths @('data.list')
if (Has-Path -Json $report -Path 'data.list.0') {
    Assert-Paths -Json $report -Name 'biz datareport saleproject report first row' -Paths @(
        'data.list.0.playState',
        'data.list.0.projectState',
        'data.list.0.createTime',
        'data.list.0.completionDate'
    )
}

$unpaid = Invoke-RawPostJson -Url "$baseUrl/biz/bizdatareport/saleproject/UnpaidPayment" -Token $token -Body $emptyBody
Assert-Ok -Json $unpaid -Name 'biz datareport unpaid payment'
Assert-Paths -Json $unpaid -Name 'biz datareport unpaid payment' -Paths @('data.amount')

$income = Invoke-RawPostJson -Url "$baseUrl/biz/bizdatareport/settlement/income" -Token $token -Body $emptyBody
Assert-Ok -Json $income -Name 'biz datareport settlement income'
Assert-Paths -Json $income -Name 'biz datareport settlement income' -Paths @('data')
if (Has-Path -Json $income -Path 'data.0') {
    Assert-SettlementRecordRow -Json $income -Prefix 'data.0' -Name 'biz datareport settlement income first row'
}

$expenses = Invoke-RawPostJson -Url "$baseUrl/biz/bizdatareport/settlement/expenses" -Token $token -Body $emptyBody
Assert-Ok -Json $expenses -Name 'biz datareport settlement expenses'
Assert-Paths -Json $expenses -Name 'biz datareport settlement expenses' -Paths @('data')
if (Has-Path -Json $expenses -Path 'data.0') {
    Assert-SettlementRecordRow -Json $expenses -Prefix 'data.0' -Name 'biz datareport settlement expenses first row'
}

$saleProfit = Invoke-RawPostJson -Url "$baseUrl/biz/bizdatareport/saleProfit" -Token $token -Body $emptyBody
Assert-Ok -Json $saleProfit -Name 'biz datareport sale profit'
Assert-Paths -Json $saleProfit -Name 'biz datareport sale profit' -Paths @('data.projectlist', 'data.orderList', 'data.bizProducts')
if (Has-Path -Json $saleProfit -Path 'data.projectlist.0') {
    Assert-ProjectRow -Json $saleProfit -Prefix 'data.projectlist.0' -Name 'biz datareport sale profit project first row'
    Assert-Paths -Json $saleProfit -Name 'biz datareport sale profit project nested collections' -Paths @('data.projectlist.0.productList', 'data.projectlist.0.returnOrders')
}
if (Has-Path -Json $saleProfit -Path 'data.bizProducts.0') {
    Assert-ProductRow -Json $saleProfit -Prefix 'data.bizProducts.0' -Name 'biz datareport sale profit product first row'
}

$summary = Invoke-RawPostForm -Url "$baseUrl/biz/bizdatareport/summary/statistics" -Token $token -Body $yearBody
Assert-Ok -Json $summary -Name 'biz datareport summary statistics'
Assert-Paths -Json $summary -Name 'biz datareport summary statistics' -Paths @('data')
if (Has-Path -Json $summary -Path 'data.0') {
    Assert-Paths -Json $summary -Name 'biz datareport summary statistics first row' -Paths @(
        'data.0.org',
        'data.0.settlementAccounts',
        'data.0.paymentRecords',
        'data.0.bizExpenditureRecords',
        'data.0.bizSaleProjects',
        'data.0.bizDebitNotes'
    )
}

Write-Host 'datareport read HTTP smoke passed'
