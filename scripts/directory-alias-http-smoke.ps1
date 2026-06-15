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
        [Parameter(Mandatory = $true)][string]$Name,
        [int]$ExpectedCurrent = 1,
        [int]$ExpectedSize = 1
    )

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data.records', 'data.total', 'data.current', 'data.size', 'data.pages')

    $current = [int](Read-JsonPath -Json $Json -Path 'data.current')
    $size = [int](Read-JsonPath -Json $Json -Path 'data.size')
    if ($current -ne $ExpectedCurrent) {
        throw "$Name expected current=$ExpectedCurrent but got $current"
    }
    if ($size -ne $ExpectedSize) {
        throw "$Name expected size=$ExpectedSize but got $size"
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

    $paths = @()
    foreach ($key in $Keys) {
        $paths += "data.records.0.$key"
    }
    Assert-Paths -Json $Json -Name "$Name first record" -Paths $paths
}

function Assert-FirstNodeIfPresent {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string[]]$Keys
    )

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data')
    if (-not (Has-Path -Json $Json -Path 'data.0')) {
        return
    }

    $paths = @()
    foreach ($key in $Keys) {
        $paths += "data.0.$key"
    }
    Assert-Paths -Json $Json -Name "$Name first node" -Paths $paths
}

function Assert-PathMissing {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Name
    )

    if (Has-Path -Json $Json -Path $Path) {
        throw "$Name unexpectedly exposed $Path"
    }
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
`$auth['device'] = 'CODEX_DIRECTORY_ALIAS_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

$bizOrgPage = Invoke-RawGet -Url "$baseUrl/biz/org/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $bizOrgPage -Name 'biz org page'
Assert-FirstRecordIfPresent -Json $bizOrgPage -Name 'biz org page' -Keys @('id', 'name', 'parentId', 'category', 'sortCode')

$bizOrgTree = Invoke-RawGet -Url "$baseUrl/biz/org/tree" -Token $token
Assert-FirstNodeIfPresent -Json $bizOrgTree -Name 'biz org tree' -Keys @('id', 'name', 'parentId', 'children')

$bizOrgSelector = Invoke-RawGet -Url "$baseUrl/biz/org/orgTreeSelector" -Token $token
Assert-FirstNodeIfPresent -Json $bizOrgSelector -Name 'biz org orgTreeSelector' -Keys @('id', 'value', 'label', 'title', 'parentId', 'children')

$bizOrgUserSelector = Invoke-RawGet -Url "$baseUrl/biz/org/userSelector?current=1&size=1" -Token $token
Assert-PagedShape -Json $bizOrgUserSelector -Name 'biz org userSelector'
Assert-FirstRecordIfPresent -Json $bizOrgUserSelector -Name 'biz org userSelector' -Keys @('id', 'value', 'label', 'title', 'name', 'account', 'orgName', 'positionName')
Assert-PathMissing -Json $bizOrgUserSelector -Path 'data.records.0.PASSWORD' -Name 'biz org userSelector'

$bizPositionPage = Invoke-RawGet -Url "$baseUrl/biz/position/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $bizPositionPage -Name 'biz position page'
Assert-FirstRecordIfPresent -Json $bizPositionPage -Name 'biz position page' -Keys @('id', 'name', 'orgId', 'category', 'sortCode')

$bizPositionSelector = Invoke-RawGet -Url "$baseUrl/biz/position/positionSelector?current=1&size=1" -Token $token
Assert-PagedShape -Json $bizPositionSelector -Name 'biz position positionSelector'
Assert-FirstRecordIfPresent -Json $bizPositionSelector -Name 'biz position positionSelector' -Keys @('id', 'value', 'label', 'title', 'name', 'orgId', 'category', 'sortCode')

$bizDictPage = Invoke-RawGet -Url "$baseUrl/biz/dict/page?current=1&size=1&category=BIZ" -Token $token
Assert-PagedShape -Json $bizDictPage -Name 'biz dict page'
Assert-FirstRecordIfPresent -Json $bizDictPage -Name 'biz dict page' -Keys @('id', 'parentId', 'dictLabel', 'dictValue', 'category', 'sortCode')

$bizDictTree = Invoke-RawGet -Url "$baseUrl/biz/dict/tree?category=BIZ" -Token $token
Assert-FirstNodeIfPresent -Json $bizDictTree -Name 'biz dict tree' -Keys @('id', 'parentId', 'dictLabel', 'dictValue', 'name', 'label', 'value', 'weight', 'children')

$bizDictTreeAll = Invoke-RawGet -Url "$baseUrl/biz/dict/treeAll" -Token $token
Assert-FirstNodeIfPresent -Json $bizDictTreeAll -Name 'biz dict treeAll' -Keys @('id', 'parentId', 'dictLabel', 'dictValue', 'name', 'label', 'value', 'weight', 'children')

Write-Host 'directory alias HTTP smoke passed'
