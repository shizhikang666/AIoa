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

function Assert-FirstRecordIfPresent {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string[]]$Keys
    )

    if (-not (Has-Path -Json $Json -Path 'data.records.0')) {
        return
    }

    foreach ($key in $Keys) {
        [void](Read-JsonPath -Json $Json -Path "data.records.0.$key")
    }
}

function Assert-ListFirstIfPresent {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string[]]$Keys
    )

    if (-not (Has-Path -Json $Json -Path 'data.0')) {
        return
    }

    foreach ($key in $Keys) {
        [void](Read-JsonPath -Json $Json -Path "data.0.$key")
    }
}

$paymentKeys = @(
    'id',
    'objectId',
    'targetId',
    'accountName',
    'accountNumber',
    'serialId',
    'processId',
    'settlementCategory',
    'payer',
    'payerTime',
    'amount',
    'org',
    'orgName'
)

$receiptKeys = @(
    'id',
    'paymentRecordId',
    'remark',
    'playStatus',
    'amount',
    'settlementAmount',
    'version',
    'accountName',
    'accountNumber',
    'settlementCategory',
    'payer',
    'org',
    'orgName'
)

$debitKeys = @(
    'id',
    'expenditureRecordId',
    'remark',
    'playStatus',
    'amount',
    'settlementAmount',
    'historyAmount',
    'version',
    'org',
    'orgName',
    'accountName',
    'accountNumber',
    'settlementCategory',
    'category',
    'payer'
)

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
`$auth['device'] = 'CODEX_FINANCE_READ_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

$paymentPage = Invoke-RawGet -Url "$baseUrl/biz/bizpaymentrecord/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $paymentPage -Name 'biz paymentrecord page'
Assert-FirstRecordIfPresent -Json $paymentPage -Name 'biz paymentrecord page' -Keys $paymentKeys

$paymentListDetails = Invoke-RawGet -Url "$baseUrl/biz/bizpaymentrecord/listdetails" -Token $token
Assert-Ok -Json $paymentListDetails -Name 'biz paymentrecord listdetails'
Assert-Paths -Json $paymentListDetails -Name 'biz paymentrecord listdetails' -Paths @('data')
Assert-ListFirstIfPresent -Json $paymentListDetails -Name 'biz paymentrecord listdetails' -Keys $paymentKeys

$paymentList = Invoke-RawGet -Url "$baseUrl/biz/bizpaymentrecord/list" -Token $token
Assert-Ok -Json $paymentList -Name 'biz paymentrecord list'
Assert-Paths -Json $paymentList -Name 'biz paymentrecord list' -Paths @('data')
Assert-ListFirstIfPresent -Json $paymentList -Name 'biz paymentrecord list' -Keys $paymentKeys

$paymentPageFirstId = [string](Read-JsonPath -Json $paymentPage -Path 'data.records.0.id' -Optional)
if ($paymentPageFirstId.Trim() -ne '') {
    $encodedPaymentId = [System.Uri]::EscapeDataString($paymentPageFirstId.Trim())
    $paymentDetail = Invoke-RawGet -Url "$baseUrl/biz/bizpaymentrecord/detail?id=$encodedPaymentId" -Token $token
    Assert-Ok -Json $paymentDetail -Name 'biz paymentrecord detail'
    foreach ($key in $paymentKeys) {
        [void](Read-JsonPath -Json $paymentDetail -Path "data.$key")
    }
}

$expenditurePage = Invoke-RawGet -Url "$baseUrl/biz/bizexpenditurerecord/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $expenditurePage -Name 'biz expenditurerecord page'
Assert-FirstRecordIfPresent -Json $expenditurePage -Name 'biz expenditurerecord page' -Keys $paymentKeys

$expenditureListDetails = Invoke-RawGet -Url "$baseUrl/biz/bizexpenditurerecord/listDetails" -Token $token
Assert-Ok -Json $expenditureListDetails -Name 'biz expenditurerecord listDetails'
Assert-Paths -Json $expenditureListDetails -Name 'biz expenditurerecord listDetails' -Paths @('data')
Assert-ListFirstIfPresent -Json $expenditureListDetails -Name 'biz expenditurerecord listDetails' -Keys $paymentKeys

$expenditureList = Invoke-RawGet -Url "$baseUrl/biz/bizexpenditurerecord/list" -Token $token
Assert-Ok -Json $expenditureList -Name 'biz expenditurerecord list'
Assert-Paths -Json $expenditureList -Name 'biz expenditurerecord list' -Paths @('data')
Assert-ListFirstIfPresent -Json $expenditureList -Name 'biz expenditurerecord list' -Keys $paymentKeys

$expenditurePageFirstId = [string](Read-JsonPath -Json $expenditurePage -Path 'data.records.0.id' -Optional)
if ($expenditurePageFirstId.Trim() -ne '') {
    $encodedExpenditureId = [System.Uri]::EscapeDataString($expenditurePageFirstId.Trim())
    $expenditureDetail = Invoke-RawGet -Url "$baseUrl/biz/bizexpenditurerecord/detail?id=$encodedExpenditureId" -Token $token
    Assert-Ok -Json $expenditureDetail -Name 'biz expenditurerecord detail'
    foreach ($key in $paymentKeys) {
        [void](Read-JsonPath -Json $expenditureDetail -Path "data.$key")
    }
}

$collectionPage = Invoke-RawGet -Url "$baseUrl/biz/bizcollectionreceipt/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $collectionPage -Name 'biz collectionreceipt page'
Assert-FirstRecordIfPresent -Json $collectionPage -Name 'biz collectionreceipt page' -Keys $receiptKeys

$collectionList = Invoke-RawGet -Url "$baseUrl/biz/bizcollectionreceipt/list" -Token $token
Assert-Ok -Json $collectionList -Name 'biz collectionreceipt list'
Assert-Paths -Json $collectionList -Name 'biz collectionreceipt list' -Paths @('data')
Assert-ListFirstIfPresent -Json $collectionList -Name 'biz collectionreceipt list' -Keys $receiptKeys

$collectionPageFirstId = [string](Read-JsonPath -Json $collectionPage -Path 'data.records.0.id' -Optional)
if ($collectionPageFirstId.Trim() -ne '') {
    $encodedCollectionId = [System.Uri]::EscapeDataString($collectionPageFirstId.Trim())
    $collectionDetail = Invoke-RawGet -Url "$baseUrl/biz/bizcollectionreceipt/detail?id=$encodedCollectionId" -Token $token
    Assert-Ok -Json $collectionDetail -Name 'biz collectionreceipt detail'
    foreach ($key in $receiptKeys) {
        [void](Read-JsonPath -Json $collectionDetail -Path "data.$key")
    }
}

$debitPage = Invoke-RawGet -Url "$baseUrl/biz/bizdebitnote/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $debitPage -Name 'biz debitnote page'
Assert-FirstRecordIfPresent -Json $debitPage -Name 'biz debitnote page' -Keys $debitKeys

$debitList = Invoke-RawGet -Url "$baseUrl/biz/bizdebitnote/list" -Token $token
Assert-Ok -Json $debitList -Name 'biz debitnote list'
Assert-Paths -Json $debitList -Name 'biz debitnote list' -Paths @('data')
Assert-ListFirstIfPresent -Json $debitList -Name 'biz debitnote list' -Keys $debitKeys

$debitPageFirstId = [string](Read-JsonPath -Json $debitPage -Path 'data.records.0.id' -Optional)
if ($debitPageFirstId.Trim() -ne '') {
    $encodedDebitId = [System.Uri]::EscapeDataString($debitPageFirstId.Trim())
    $debitDetail = Invoke-RawGet -Url "$baseUrl/biz/bizdebitnote/detail?id=$encodedDebitId" -Token $token
    Assert-Ok -Json $debitDetail -Name 'biz debitnote detail'
    foreach ($key in $debitKeys) {
        [void](Read-JsonPath -Json $debitDetail -Path "data.$key")
    }
}

Write-Host 'finance read HTTP smoke passed'
