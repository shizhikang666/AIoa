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

    return $raw
}

function Invoke-RawPost {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$Token,
        [string]$Body = '{}'
    )

    $raw = & curl.exe -sS -X POST $Url -H "Authorization: Bearer $Token" -H 'Content-Type: application/json' --data $Body
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP POST failed: $Url"
    }

    return $raw
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

function Assert-PagedShape {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data.records', 'data.total', 'data.current', 'data.size', 'data.pages')
}

function Assert-CustomerRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.name",
        "$Prefix.contacts",
        "$Prefix.phone",
        "$Prefix.detailsAddress",
        "$Prefix.address",
        "$Prefix.sourceType",
        "$Prefix.customType",
        "$Prefix.org",
        "$Prefix.user",
        "$Prefix.status",
        "$Prefix.headName",
        "$Prefix.orgName",
        "$Prefix.createUserName",
        "$Prefix.downloadPath",
        "$Prefix.firstContactTime"
    )
}

function Assert-SaleProjectRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.projectName",
        "$Prefix.projectState",
        "$Prefix.playState",
        "$Prefix.customerName",
        "$Prefix.customerAddress",
        "$Prefix.customerSourceType",
        "$Prefix.customType",
        "$Prefix.headName",
        "$Prefix.headPhone",
        "$Prefix.orgName",
        "$Prefix.accountName"
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
`$auth['device'] = 'CODEX_BUSINESS_READ_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$sampleCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$notDeleted = function (`$query) { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); };
`$customerId = think\facade\Db::name('customer')->where(`$notDeleted)->value('ID');
`$saleProjectId = think\facade\Db::name('biz_sale_project')->where(`$notDeleted)->value('ID');
if (!`$customerId) { throw new RuntimeException('sample customer not found'); }
if (!`$saleProjectId) { throw new RuntimeException('sample sale project not found'); }
echo json_encode(['customerId' => (string)`$customerId, 'saleProjectId' => (string)`$saleProjectId], JSON_UNESCAPED_UNICODE);
"@

$sampleJson = & php -r $sampleCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($sampleJson)) {
    throw 'failed to load sample business ids'
}

$customerId = Read-JsonPath -Json $sampleJson -Path 'customerId'
$saleProjectId = Read-JsonPath -Json $sampleJson -Path 'saleProjectId'
$baseUrl = $BackendBaseUrl.TrimEnd('/')
$encodedCustomerId = [System.Uri]::EscapeDataString($customerId.Trim())
$encodedSaleProjectId = [System.Uri]::EscapeDataString($saleProjectId.Trim())

$customerPage = Invoke-RawGet -Url "$baseUrl/biz/customer/page?id=$encodedCustomerId&current=1&size=1" -Token $token
Assert-PagedShape -Json $customerPage -Name 'biz customer page'
Assert-CustomerRow -Json $customerPage -Prefix 'data.records.0' -Name 'biz customer page first record'

$customerDetail = Invoke-RawGet -Url "$baseUrl/biz/customer/detail?id=$encodedCustomerId" -Token $token
Assert-Ok -Json $customerDetail -Name 'biz customer detail'
Assert-CustomerRow -Json $customerDetail -Prefix 'data' -Name 'biz customer detail'

$customerDetailList = Invoke-RawPost -Url "$baseUrl/biz/customer/detail/list?id=$encodedCustomerId" -Token $token
Assert-Ok -Json $customerDetailList -Name 'biz customer detail/list'
Assert-Paths -Json $customerDetailList -Name 'biz customer detail/list' -Paths @(
    'data.0.customer.id',
    'data.0.customerFollowUps'
)
Assert-CustomerRow -Json $customerDetailList -Prefix 'data.0.customer' -Name 'biz customer detail/list customer'

$saleProjectPage = Invoke-RawGet -Url "$baseUrl/biz/saleproject/page?id=$encodedSaleProjectId&current=1&size=1" -Token $token
Assert-PagedShape -Json $saleProjectPage -Name 'biz saleproject page'
Assert-SaleProjectRow -Json $saleProjectPage -Prefix 'data.records.0' -Name 'biz saleproject page first record'
Assert-Paths -Json $saleProjectPage -Name 'biz saleproject page return orders' -Paths @('data.records.0.returnOrders')

foreach ($path in @('case/page', 'operation/page', 'public/page')) {
    $page = Invoke-RawGet -Url "$baseUrl/biz/saleproject/${path}?current=1&size=1" -Token $token
    Assert-PagedShape -Json $page -Name "biz saleproject $path"
}

$saleProjectDetail = Invoke-RawGet -Url "$baseUrl/biz/saleproject/detail?id=$encodedSaleProjectId" -Token $token
Assert-Ok -Json $saleProjectDetail -Name 'biz saleproject detail'
Assert-SaleProjectRow -Json $saleProjectDetail -Prefix 'data.bizSaleProject' -Name 'biz saleproject detail project'
Assert-Paths -Json $saleProjectDetail -Name 'biz saleproject detail aggregates' -Paths @(
    'data.productItems',
    'data.invoicingList',
    'data.invoiceList',
    'data.paymentRecords',
    'data.saleProjectFollowUps',
    'data.changeLogs',
    'data.returnOrders'
)

$saleProjectListDetail = Invoke-RawGet -Url "$baseUrl/biz/saleproject/list/detail?id=$encodedSaleProjectId" -Token $token
Assert-Ok -Json $saleProjectListDetail -Name 'biz saleproject list/detail'
Assert-SaleProjectRow -Json $saleProjectListDetail -Prefix 'data.0.bizSaleProject' -Name 'biz saleproject list/detail project'
Assert-Paths -Json $saleProjectListDetail -Name 'biz saleproject list/detail aggregates' -Paths @('data.0.returnOrders')

$saleProjectProduct = Invoke-RawGet -Url "$baseUrl/biz/saleproject/product?id=$encodedSaleProjectId" -Token $token
Assert-Ok -Json $saleProjectProduct -Name 'biz saleproject product'
Assert-Paths -Json $saleProjectProduct -Name 'biz saleproject product' -Paths @('data')

$saleProjectCost = Invoke-RawPost -Url "$baseUrl/biz/saleproject/cost?id=$encodedSaleProjectId" -Token $token
Assert-Ok -Json $saleProjectCost -Name 'biz saleproject cost'
Assert-Paths -Json $saleProjectCost -Name 'biz saleproject cost' -Paths @('data')

$saleProjectCostDetails = Invoke-RawPost -Url "$baseUrl/biz/saleproject/cost/details?id=$encodedSaleProjectId" -Token $token
Assert-Ok -Json $saleProjectCostDetails -Name 'biz saleproject cost/details'
Assert-Paths -Json $saleProjectCostDetails -Name 'biz saleproject cost/details' -Paths @(
    'data.items',
    'data.productItems',
    'data.returnOrders'
)

Write-Host 'business read HTTP smoke passed'
