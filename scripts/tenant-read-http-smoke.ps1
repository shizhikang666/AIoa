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

function Assert-PathNotBlank {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $value = Read-JsonPath -Json $Json -Path $Path
    if ([string]::IsNullOrWhiteSpace($value) -or $value -eq 'null') {
        throw "$Name expected non-blank $Path"
    }
}

function Has-Path {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path
    )

    return $null -ne (Read-JsonPath -Json $Json -Path $Path -Optional)
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
`$auth['device'] = 'CODEX_TENANT_READ_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$sampleTenantCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$id = think\facade\Db::name('tenants')
    ->where(function (`$query) { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->order('Tenant_ID', 'asc')
    ->value('Tenant_ID');
if (`$id) { echo (string)`$id; }
"@

$sampleTenantId = & php -r $sampleTenantCode
if ($LASTEXITCODE -ne 0) {
    throw 'failed to load a sample tenant id'
}
$sampleTenantId = if ($null -eq $sampleTenantId) { '' } else { [string]$sampleTenantId }

$baseUrl = $BackendBaseUrl.TrimEnd('/')

$page = Invoke-RawGet -Url "$baseUrl/tenants/tenant/page?current=1&size=1" -Token $token
Assert-Ok -Json $page -Name 'tenants tenant page'
Assert-Paths -Json $page -Name 'tenants tenant page' -Paths @('data.records', 'data.total', 'data.current', 'data.size', 'data.pages')
if (Has-Path -Json $page -Path 'data.records.0') {
    Assert-Paths -Json $page -Name 'tenants tenant page first record' -Paths @('data.records.0.tenantId', 'data.records.0.tenantName')
    Assert-PathNotBlank -Json $page -Path 'data.records.0.tenantId' -Name 'tenants tenant page first record'
    Assert-PathNotBlank -Json $page -Path 'data.records.0.tenantName' -Name 'tenants tenant page first record'
}

if (-not [string]::IsNullOrWhiteSpace($sampleTenantId)) {
    $encodedTenantId = [System.Uri]::EscapeDataString($sampleTenantId.Trim())
    $detail = Invoke-RawGet -Url "$baseUrl/tenants/tenant/detail?tenantId=$encodedTenantId" -Token $token
    Assert-Ok -Json $detail -Name 'tenants tenant detail'
    Assert-Paths -Json $detail -Name 'tenants tenant detail' -Paths @('data.tenantId', 'data.tenantName')
    Assert-PathNotBlank -Json $detail -Path 'data.tenantId' -Name 'tenants tenant detail'
    Assert-PathNotBlank -Json $detail -Path 'data.tenantName' -Name 'tenants tenant detail'
} else {
    Write-Host 'skip: tenants tenant detail sample not found'
}

Write-Host 'tenant read HTTP smoke passed'
