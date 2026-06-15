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

function Assert-Keys {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string[]]$Keys
    )

    foreach ($key in $Keys) {
        [void](Read-JsonPath -Json $Json -Path "$Prefix.$key")
    }
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

    Assert-Keys -Json $Json -Prefix 'data.records.0' -Name $Name -Keys $Keys
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

    Assert-Keys -Json $Json -Prefix 'data.0' -Name $Name -Keys $Keys
}

$orderKeys = @(
    'id',
    'title',
    'settlementStatus',
    'storageStatus',
    'supplierId',
    'supplierName',
    'supplier',
    'instanceId',
    'desirePurchaseDate',
    'amount',
    'remark',
    'extJson',
    'ext',
    'version',
    'org',
    'orgName'
)

$itemKeys = @(
    'id',
    'purchaseOrderId',
    'storageStatus',
    'productId',
    'productName',
    'amount',
    'number',
    'unitAmount',
    'discountRate',
    'freightShareAmount',
    'unitCostWithFreight',
    'productCategory',
    'category',
    'specs',
    'purchasePrice',
    'salePrice',
    'minPrice'
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
`$auth['device'] = 'CODEX_PURCHASE_ORDER_READ_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

$page = Invoke-RawGet -Url "$baseUrl/biz/bizpurchaseorder/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $page -Name 'biz purchaseorder page'
Assert-FirstRecordIfPresent -Json $page -Name 'biz purchaseorder page' -Keys $orderKeys

$list = Invoke-RawGet -Url "$baseUrl/biz/bizpurchaseorder/list" -Token $token
Assert-Ok -Json $list -Name 'biz purchaseorder list'
Assert-Paths -Json $list -Name 'biz purchaseorder list' -Paths @('data')
Assert-ListFirstIfPresent -Json $list -Name 'biz purchaseorder list' -Keys $orderKeys

$detailList = Invoke-RawGet -Url "$baseUrl/biz/bizpurchaseorder/detail/list" -Token $token
Assert-Ok -Json $detailList -Name 'biz purchaseorder detail/list'
Assert-Paths -Json $detailList -Name 'biz purchaseorder detail/list' -Paths @('data')
Assert-ListFirstIfPresent -Json $detailList -Name 'biz purchaseorder detail/list' -Keys $orderKeys
if (Has-Path -Json $detailList -Path 'data.0') {
    Assert-Paths -Json $detailList -Name 'biz purchaseorder detail/list order items' -Paths @('data.0.orderItems')
    if (Has-Path -Json $detailList -Path 'data.0.orderItems.0') {
        Assert-Keys -Json $detailList -Prefix 'data.0.orderItems.0' -Name 'biz purchaseorder detail/list first item' -Keys $itemKeys
    }
}

$pageFirstId = [string](Read-JsonPath -Json $page -Path 'data.records.0.id' -Optional)
if ($pageFirstId.Trim() -ne '') {
    $encodedId = [System.Uri]::EscapeDataString($pageFirstId.Trim())
    $detail = Invoke-RawGet -Url "$baseUrl/biz/bizpurchaseorder/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $detail -Name 'biz purchaseorder detail'
    Assert-Paths -Json $detail -Name 'biz purchaseorder detail wrapper' -Paths @(
        'data.bizPurchaseOrder',
        'data.bizPurchaseOrderItemList',
        'data.bizExpenditureRecordList'
    )
    Assert-Keys -Json $detail -Prefix 'data.bizPurchaseOrder' -Name 'biz purchaseorder detail order' -Keys $orderKeys
    if (Has-Path -Json $detail -Path 'data.bizPurchaseOrderItemList.0') {
        Assert-Keys -Json $detail -Prefix 'data.bizPurchaseOrderItemList.0' -Name 'biz purchaseorder detail first item' -Keys $itemKeys
    }
}

Write-Host 'purchase order read HTTP smoke passed'
