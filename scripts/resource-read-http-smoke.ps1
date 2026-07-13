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

function Enc {
    param([Parameter(Mandatory = $true)][string]$Value)

    return [System.Uri]::EscapeDataString($Value)
}

function Assert-PagedShape {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data.records', 'data.total', 'data.current', 'data.size', 'data.pages')
}

function Assert-ListShape {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data')
}

function Assert-ResourceRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.parentId",
        "$Prefix.title",
        "$Prefix.code",
        "$Prefix.category",
        "$Prefix.sortCode",
        "$Prefix.extJson",
        "$Prefix.createTime",
        "$Prefix.updateTime"
    )
}

function Assert-SystemResourceRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-ResourceRow -Json $Json -Prefix $Prefix -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.name",
        "$Prefix.module",
        "$Prefix.menuType",
        "$Prefix.path",
        "$Prefix.component",
        "$Prefix.icon",
        "$Prefix.color",
        "$Prefix.visible"
    )
}

function Assert-MobileResourceRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-ResourceRow -Json $Json -Prefix $Prefix -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.module",
        "$Prefix.menuType",
        "$Prefix.path",
        "$Prefix.icon",
        "$Prefix.color",
        "$Prefix.regType",
        "$Prefix.status",
        "$Prefix.tenantId"
    )
}

function Assert-SelectorRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.title",
        "$Prefix.label",
        "$Prefix.value",
        "$Prefix.weight"
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
`$auth['device'] = 'CODEX_RESOURCE_READ_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

$sysModulePage = Invoke-RawGet -Url "$baseUrl/sys/module/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $sysModulePage -Name 'sys module page'
if (Has-Path -Json $sysModulePage -Path 'data.records.0') {
    Assert-SystemResourceRow -Json $sysModulePage -Prefix 'data.records.0' -Name 'sys module page first row'
}

$sysModuleFirstId = [string](Read-JsonPath -Json $sysModulePage -Path 'data.records.0.id' -Optional)
if ($sysModuleFirstId.Trim() -ne '') {
    $encodedId = Enc $sysModuleFirstId.Trim()
    $sysModuleDetail = Invoke-RawGet -Url "$baseUrl/sys/module/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $sysModuleDetail -Name 'sys module detail'
    Assert-SystemResourceRow -Json $sysModuleDetail -Prefix 'data' -Name 'sys module detail'
}

$sysMenuPage = Invoke-RawGet -Url "$baseUrl/sys/menu/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $sysMenuPage -Name 'sys menu page'
if (Has-Path -Json $sysMenuPage -Path 'data.records.0') {
    Assert-SystemResourceRow -Json $sysMenuPage -Prefix 'data.records.0' -Name 'sys menu page first row'
}

$sysMenuTree = Invoke-RawGet -Url "$baseUrl/sys/menu/tree" -Token $token
Assert-ListShape -Json $sysMenuTree -Name 'sys menu tree'
if (Has-Path -Json $sysMenuTree -Path 'data.0') {
    Assert-SystemResourceRow -Json $sysMenuTree -Prefix 'data.0' -Name 'sys menu tree first row'
    Assert-SelectorRow -Json $sysMenuTree -Prefix 'data.0' -Name 'sys menu tree first selector row'
}

$sysModuleSelector = Invoke-RawGet -Url "$baseUrl/sys/menu/moduleSelector" -Token $token
Assert-ListShape -Json $sysModuleSelector -Name 'sys menu module selector'
if (Has-Path -Json $sysModuleSelector -Path 'data.0') {
    Assert-SelectorRow -Json $sysModuleSelector -Prefix 'data.0' -Name 'sys menu module selector first row'
}

$sysMenuTreeSelector = Invoke-RawGet -Url "$baseUrl/sys/menu/menuTreeSelector" -Token $token
Assert-ListShape -Json $sysMenuTreeSelector -Name 'sys menu tree selector'
if (Has-Path -Json $sysMenuTreeSelector -Path 'data.0') {
    Assert-SelectorRow -Json $sysMenuTreeSelector -Prefix 'data.0' -Name 'sys menu tree selector first row'
}

$sysMenuFirstId = [string](Read-JsonPath -Json $sysMenuPage -Path 'data.records.0.id' -Optional)
if ($sysMenuFirstId.Trim() -ne '') {
    $encodedId = Enc $sysMenuFirstId.Trim()
    $sysMenuDetail = Invoke-RawGet -Url "$baseUrl/sys/menu/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $sysMenuDetail -Name 'sys menu detail'
    Assert-SystemResourceRow -Json $sysMenuDetail -Prefix 'data' -Name 'sys menu detail'
}

$sysButtonPage = Invoke-RawGet -Url "$baseUrl/sys/button/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $sysButtonPage -Name 'sys button page'
if (Has-Path -Json $sysButtonPage -Path 'data.records.0') {
    Assert-SystemResourceRow -Json $sysButtonPage -Prefix 'data.records.0' -Name 'sys button page first row'
}

$sysButtonFirstId = [string](Read-JsonPath -Json $sysButtonPage -Path 'data.records.0.id' -Optional)
if ($sysButtonFirstId.Trim() -ne '') {
    $encodedId = Enc $sysButtonFirstId.Trim()
    $sysButtonDetail = Invoke-RawGet -Url "$baseUrl/sys/button/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $sysButtonDetail -Name 'sys button detail'
    Assert-SystemResourceRow -Json $sysButtonDetail -Prefix 'data' -Name 'sys button detail'
}

$sysFieldPage = Invoke-RawGet -Url "$baseUrl/sys/field/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $sysFieldPage -Name 'sys field page'
if (Has-Path -Json $sysFieldPage -Path 'data.records.0') {
    Assert-SystemResourceRow -Json $sysFieldPage -Prefix 'data.records.0' -Name 'sys field page first row'
}

$sysFieldTree = Invoke-RawGet -Url "$baseUrl/sys/field/tree" -Token $token
Assert-ListShape -Json $sysFieldTree -Name 'sys field tree'
if (Has-Path -Json $sysFieldTree -Path 'data.0') {
    Assert-SystemResourceRow -Json $sysFieldTree -Prefix 'data.0' -Name 'sys field tree first row'
    Assert-SelectorRow -Json $sysFieldTree -Prefix 'data.0' -Name 'sys field tree first selector row'
}

$sysFieldMenuTreeSelector = Invoke-RawGet -Url "$baseUrl/sys/field/MenuTreeSelector" -Token $token
Assert-ListShape -Json $sysFieldMenuTreeSelector -Name 'sys field menu tree selector'
if (Has-Path -Json $sysFieldMenuTreeSelector -Path 'data.0') {
    Assert-SelectorRow -Json $sysFieldMenuTreeSelector -Prefix 'data.0' -Name 'sys field menu tree selector first row'
}

$sysFieldFirstId = [string](Read-JsonPath -Json $sysFieldPage -Path 'data.records.0.id' -Optional)
if ($sysFieldFirstId.Trim() -ne '') {
    $encodedId = Enc $sysFieldFirstId.Trim()
    $sysFieldDetail = Invoke-RawGet -Url "$baseUrl/sys/field/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $sysFieldDetail -Name 'sys field detail'
    Assert-SystemResourceRow -Json $sysFieldDetail -Prefix 'data' -Name 'sys field detail'
}

$mobileModulePage = Invoke-RawGet -Url "$baseUrl/mobile/module/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $mobileModulePage -Name 'mobile module page'
if (Has-Path -Json $mobileModulePage -Path 'data.records.0') {
    Assert-MobileResourceRow -Json $mobileModulePage -Prefix 'data.records.0' -Name 'mobile module page first row'
}

$mobileModuleFirstId = [string](Read-JsonPath -Json $mobileModulePage -Path 'data.records.0.id' -Optional)
if ($mobileModuleFirstId.Trim() -ne '') {
    $encodedId = Enc $mobileModuleFirstId.Trim()
    $mobileModuleDetail = Invoke-RawGet -Url "$baseUrl/mobile/module/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $mobileModuleDetail -Name 'mobile module detail'
    Assert-MobileResourceRow -Json $mobileModuleDetail -Prefix 'data' -Name 'mobile module detail'
}

$mobileMenuTree = Invoke-RawGet -Url "$baseUrl/mobile/menu/tree" -Token $token
Assert-ListShape -Json $mobileMenuTree -Name 'mobile menu tree'
if (Has-Path -Json $mobileMenuTree -Path 'data.0') {
    Assert-MobileResourceRow -Json $mobileMenuTree -Prefix 'data.0' -Name 'mobile menu tree first row'
    Assert-SelectorRow -Json $mobileMenuTree -Prefix 'data.0' -Name 'mobile menu tree first selector row'
}

$mobileModuleSelector = Invoke-RawGet -Url "$baseUrl/mobile/menu/moduleSelector" -Token $token
Assert-ListShape -Json $mobileModuleSelector -Name 'mobile menu module selector'
if (Has-Path -Json $mobileModuleSelector -Path 'data.0') {
    Assert-SelectorRow -Json $mobileModuleSelector -Prefix 'data.0' -Name 'mobile menu module selector first row'
    Assert-Paths -Json $mobileModuleSelector -Name 'mobile menu module selector first row name alias' -Paths @('data.0.name')
}

$mobileMenuTreeSelector = Invoke-RawGet -Url "$baseUrl/mobile/menu/menuTreeSelector" -Token $token
Assert-ListShape -Json $mobileMenuTreeSelector -Name 'mobile menu tree selector'
if (Has-Path -Json $mobileMenuTreeSelector -Path 'data.0') {
    Assert-SelectorRow -Json $mobileMenuTreeSelector -Prefix 'data.0' -Name 'mobile menu tree selector first row'
    Assert-Paths -Json $mobileMenuTreeSelector -Name 'mobile menu tree selector first row name alias' -Paths @('data.0.name')
}

$mobileMenuFirstId = [string](Read-JsonPath -Json $mobileMenuTree -Path 'data.0.id' -Optional)
if ($mobileMenuFirstId.Trim() -ne '') {
    $encodedId = Enc $mobileMenuFirstId.Trim()
    $mobileMenuDetail = Invoke-RawGet -Url "$baseUrl/mobile/menu/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $mobileMenuDetail -Name 'mobile menu detail'
    Assert-MobileResourceRow -Json $mobileMenuDetail -Prefix 'data' -Name 'mobile menu detail'
}

$mobileButtonPage = Invoke-RawGet -Url "$baseUrl/mobile/button/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $mobileButtonPage -Name 'mobile button page'
if (Has-Path -Json $mobileButtonPage -Path 'data.records.0') {
    Assert-MobileResourceRow -Json $mobileButtonPage -Prefix 'data.records.0' -Name 'mobile button page first row'
}

$mobileButtonFirstId = [string](Read-JsonPath -Json $mobileButtonPage -Path 'data.records.0.id' -Optional)
if ($mobileButtonFirstId.Trim() -ne '') {
    $encodedId = Enc $mobileButtonFirstId.Trim()
    $mobileButtonDetail = Invoke-RawGet -Url "$baseUrl/mobile/button/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $mobileButtonDetail -Name 'mobile button detail'
    Assert-MobileResourceRow -Json $mobileButtonDetail -Prefix 'data' -Name 'mobile button detail'
}

Write-Host 'resource read HTTP smoke passed'
