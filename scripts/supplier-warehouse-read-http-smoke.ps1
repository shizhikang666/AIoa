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

function Assert-RowKeys {
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

$supplierKeys = @(
    'id',
    'name',
    'contacts',
    'phone',
    'bankName',
    'bankAccount',
    'status',
    'enterpriseNature',
    'taxRegistrationNumber',
    'paymentMethod',
    'sortCode',
    'aliasName',
    'org',
    'orgName'
)

$warehouseKeys = @(
    'id',
    'name',
    'code',
    'address',
    'sortCode',
    'user',
    'headName',
    'org',
    'orgName',
    'extJson'
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
`$auth['device'] = 'CODEX_SUPPLIER_WAREHOUSE_READ_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

$supplierPage = Invoke-RawGet -Url "$baseUrl/biz/supplier/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $supplierPage -Name 'biz supplier page'
if (Has-Path -Json $supplierPage -Path 'data.records.0') {
    Assert-RowKeys -Json $supplierPage -Prefix 'data.records.0' -Name 'biz supplier page first row' -Keys $supplierKeys
}

$supplierList = Invoke-RawGet -Url "$baseUrl/biz/supplier/list" -Token $token
Assert-Ok -Json $supplierList -Name 'biz supplier list'
Assert-Paths -Json $supplierList -Name 'biz supplier list' -Paths @('data')
if (Has-Path -Json $supplierList -Path 'data.0') {
    Assert-RowKeys -Json $supplierList -Prefix 'data.0' -Name 'biz supplier list first row' -Keys $supplierKeys
}

$supplierPageFirstId = [string](Read-JsonPath -Json $supplierPage -Path 'data.records.0.id' -Optional)
$supplierPageFirstName = [string](Read-JsonPath -Json $supplierPage -Path 'data.records.0.name' -Optional)
if ($supplierPageFirstId.Trim() -ne '') {
    $encodedSupplierId = [System.Uri]::EscapeDataString($supplierPageFirstId.Trim())
    $supplierDetail = Invoke-RawGet -Url "$baseUrl/biz/supplier/detail?id=$encodedSupplierId" -Token $token
    Assert-Ok -Json $supplierDetail -Name 'biz supplier detail'
    Assert-RowKeys -Json $supplierDetail -Prefix 'data' -Name 'biz supplier detail' -Keys $supplierKeys
}
if ($supplierPageFirstName.Trim() -ne '') {
    $encodedSupplierName = [System.Uri]::EscapeDataString($supplierPageFirstName.Trim())
    $supplierQueryByName = Invoke-RawGet -Url "$baseUrl/biz/supplier/list/query/name?name=$encodedSupplierName" -Token $token
    Assert-Ok -Json $supplierQueryByName -Name 'biz supplier list/query/name'
    Assert-Paths -Json $supplierQueryByName -Name 'biz supplier list/query/name' -Paths @('data')
    if (Has-Path -Json $supplierQueryByName -Path 'data.0') {
        Assert-RowKeys -Json $supplierQueryByName -Prefix 'data.0' -Name 'biz supplier list/query/name first row' -Keys $supplierKeys
    }
}

$warehousePage = Invoke-RawGet -Url "$baseUrl/biz/warehouses/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $warehousePage -Name 'biz warehouses page'
if (Has-Path -Json $warehousePage -Path 'data.records.0') {
    Assert-RowKeys -Json $warehousePage -Prefix 'data.records.0' -Name 'biz warehouses page first row' -Keys $warehouseKeys
}

$warehouseList = Invoke-RawGet -Url "$baseUrl/biz/warehouses/list" -Token $token
Assert-Ok -Json $warehouseList -Name 'biz warehouses list'
Assert-Paths -Json $warehouseList -Name 'biz warehouses list' -Paths @('data')
if (Has-Path -Json $warehouseList -Path 'data.0') {
    Assert-RowKeys -Json $warehouseList -Prefix 'data.0' -Name 'biz warehouses list first row' -Keys $warehouseKeys
}

$warehousePageFirstId = [string](Read-JsonPath -Json $warehousePage -Path 'data.records.0.id' -Optional)
if ($warehousePageFirstId.Trim() -ne '') {
    $encodedWarehouseId = [System.Uri]::EscapeDataString($warehousePageFirstId.Trim())
    $warehouseDetail = Invoke-RawGet -Url "$baseUrl/biz/warehouses/detail?id=$encodedWarehouseId" -Token $token
    Assert-Ok -Json $warehouseDetail -Name 'biz warehouses detail'
    Assert-RowKeys -Json $warehouseDetail -Prefix 'data' -Name 'biz warehouses detail' -Keys $warehouseKeys
}

Write-Host 'supplier/warehouse read HTTP smoke passed'
