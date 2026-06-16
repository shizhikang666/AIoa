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
        [Parameter(Mandatory = $true)][string]$Key
    )

    if ($EnvMap.ContainsKey($Key) -and [string]$EnvMap[$Key] -ne '') {
        return [string]$EnvMap[$Key]
    }

    return ''
}

function Invoke-RawGet {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [string]$Token = ''
    )

    $headers = @()
    if ($Token -ne '') {
        $headers += @('-H', "Authorization: Bearer $Token")
    }

    $raw = & curl.exe -sS -X GET $Url @headers
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP GET failed: $Url"
    }

    return [string]::Join('', [string[]]$raw)
}

function Invoke-RawPostJson {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)]$Data,
        [string]$Token = ''
    )

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-tenant-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    try {
        ConvertTo-Json -InputObject $Data -Depth 8 | Set-Content -LiteralPath $tmp -Encoding UTF8
        $headers = @('-H', 'Content-Type: application/json')
        if ($Token -ne '') {
            $headers += @('-H', "Authorization: Bearer $Token")
        }

        $raw = & curl.exe -sS -X POST $Url @headers --data-binary "@$tmp"
        if ($LASTEXITCODE -ne 0) {
            throw "HTTP POST failed: $Url"
        }

        return [string]::Join('', [string[]]$raw)
    } finally {
        Remove-Item -LiteralPath $tmp -ErrorAction SilentlyContinue
    }
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

function Assert-Code {
    param([string]$Json, [int]$Expected, [string]$Name)

    $code = [int](Read-JsonPath -Json $Json -Path 'code')
    if ($code -ne $Expected) {
        throw "$Name returned code=$code expected=$Expected body=$Json"
    }
}

function Assert-PathEquals {
    param([string]$Json, [string]$Path, [string]$Expected, [string]$Name)

    $actual = [string](Read-JsonPath -Json $Json -Path $Path)
    if ($actual -ne $Expected) {
        throw "$Name expected $Path=$Expected actual=$actual"
    }
}

function Enc {
    param([Parameter(Mandatory = $true)][string]$Value)

    return [System.Uri]::EscapeDataString($Value)
}

function Invoke-Php {
    param([Parameter(Mandatory = $true)][string]$Code)

    $output = & php -r $Code
    if ($LASTEXITCODE -ne 0) {
        throw 'php inline command failed'
    }
    if ($null -eq $output) {
        return ''
    }

    return [string]::Join('', [string[]]$output)
}

function Invoke-PhpJson {
    param([Parameter(Mandatory = $true)][string]$Code)

    $raw = Invoke-Php -Code $Code
    if ([string]::IsNullOrWhiteSpace($raw)) {
        throw 'php inline json command returned no output'
    }

    return $raw | ConvertFrom-Json
}

$envMap = Get-EnvMap -Path $EnvPath
$account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
if ($account -eq '') {
    throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env'
}

$safeAccount = $account.Replace("'", "\'")
$sessionCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_TENANT_WRITE_HTTP_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => (string)`$user['ID']
], JSON_UNESCAPED_SLASHES);
"@

$session = Invoke-PhpJson -Code $sessionCode
$token = [string]$session.token
$userId = [string]$session.userId
if ($token.Trim() -eq '' -or $userId.Trim() -eq '') {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'CODEX_TENANT_' + (Get-Date -Format 'MMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
$safePrefix = $prefix.Replace("'", "\'")
$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('tenants')->whereLike('Tenant_Name', '$safePrefix%')->delete();
think\facade\Cache::delete('oa:auth:safe:tenants:' . hash('sha256', '$token'));
"@

Invoke-Php -Code $cleanupCode | Out-Null

$countCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'sysUser' => think\facade\Db::name('sys_user')->count(),
    'sysRole' => think\facade\Db::name('sys_role')->count(),
    'sysResource' => think\facade\Db::name('sys_resource')->count(),
    'sysRelation' => think\facade\Db::name('sys_relation')->count()
], JSON_UNESCAPED_SLASHES);
"@

try {
    $before = Invoke-PhpJson -Code $countCode

    $noToken = Invoke-RawPostJson -Url "$baseUrl/tenants/tenant/add" -Data @{
        tenantName = "$prefix-no-token"
    }
    Assert-Code -Json $noToken -Expected 401 -Name 'tenant add without token'

    $missing = Invoke-RawPostJson -Url "$baseUrl/tenants/tenant/add" -Token $token -Data @{}
    Assert-Code -Json $missing -Expected 400 -Name 'tenant add missing name'

    $add = Invoke-RawPostJson -Url "$baseUrl/tenants/tenant/add" -Token $token -Data @{
        tenantName = "$prefix-add"
    }
    Assert-Code -Json $add -Expected 200 -Name 'tenant add'
    Assert-PathEquals -Json $add -Path 'data' -Expected 'null' -Name 'tenant add data'

    $tenantState = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('tenants')->where('Tenant_Name', '$safePrefix-add')->find();
echo json_encode(`$row, JSON_UNESCAPED_SLASHES);
"@
    $tenantId = [string]$tenantState.Tenant_ID
    $tenantCode = [string]$tenantState.CODE
    if ($tenantId.Trim() -eq '' -or $tenantCode.Length -ne 10) {
        throw 'tenant add did not create expected id/code'
    }

    $encodedTenantId = Enc $tenantId
    $detail = Invoke-RawGet -Url "$baseUrl/tenants/tenant/detail?tenantId=$encodedTenantId" -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'tenant detail after add'
    Assert-PathEquals -Json $detail -Path 'data.tenantName' -Expected "$prefix-add" -Name 'tenant detail name after add'
    Assert-PathEquals -Json $detail -Path 'data.code' -Expected $tenantCode -Name 'tenant detail code after add'
    Assert-PathEquals -Json $detail -Path 'data.deleteFlag' -Expected 'NOT_DELETE' -Name 'tenant detail delete flag after add'

    $duplicate = Invoke-RawPostJson -Url "$baseUrl/tenants/tenant/add" -Token $token -Data @{
        tenantName = "$prefix-add"
    }
    Assert-Code -Json $duplicate -Expected 400 -Name 'tenant duplicate add'

    $systemEdit = Invoke-RawPostJson -Url "$baseUrl/tenants/tenant/edit" -Token $token -Data @{
        tenantId = '0'
        tenantName = "$prefix-system-edit"
    }
    Assert-Code -Json $systemEdit -Expected 400 -Name 'tenant system edit guard'

    $edit = Invoke-RawPostJson -Url "$baseUrl/tenants/tenant/edit" -Token $token -Data @{
        tenantId = $tenantId
        tenantName = "$prefix-edit"
        code = 'client-spoof'
        deleteFlag = 'DELETED'
        createUser = 'client-spoof'
    }
    Assert-Code -Json $edit -Expected 200 -Name 'tenant edit'
    Assert-PathEquals -Json $edit -Path 'data' -Expected 'null' -Name 'tenant edit data'

    $detailAfterEdit = Invoke-RawGet -Url "$baseUrl/tenants/tenant/detail?tenantId=$encodedTenantId" -Token $token
    Assert-Code -Json $detailAfterEdit -Expected 200 -Name 'tenant detail after edit'
    Assert-PathEquals -Json $detailAfterEdit -Path 'data.tenantName' -Expected "$prefix-edit" -Name 'tenant detail name after edit'
    Assert-PathEquals -Json $detailAfterEdit -Path 'data.code' -Expected $tenantCode -Name 'tenant code preserved after edit'
    Assert-PathEquals -Json $detailAfterEdit -Path 'data.deleteFlag' -Expected 'NOT_DELETE' -Name 'tenant delete flag preserved after edit'
    Assert-PathEquals -Json $detailAfterEdit -Path 'data.createUser' -Expected $userId -Name 'tenant create user preserved after edit'

    $noSafeDelete = Invoke-RawPostJson -Url "$baseUrl/tenants/tenant/delete" -Token $token -Data @(@{ id = $tenantId })
    Assert-Code -Json $noSafeDelete -Expected 408 -Name 'tenant delete without safe marker'
    Assert-PathEquals -Json $noSafeDelete -Path 'data' -Expected 'tenants' -Name 'tenant delete safe marker'

    Invoke-Php -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Cache::set('oa:auth:safe:tenants:' . hash('sha256', '$token'), true, 120);
"@ | Out-Null

    $mixedDelete = Invoke-RawPostJson -Url "$baseUrl/tenants/tenant/delete" -Token $token -Data @(
        @{ id = $tenantId },
        @{ id = '999999999999999999' }
    )
    Assert-Code -Json $mixedDelete -Expected 404 -Name 'tenant mixed delete rollback'
    $detailAfterFailedDelete = Invoke-RawGet -Url "$baseUrl/tenants/tenant/detail?tenantId=$encodedTenantId" -Token $token
    Assert-Code -Json $detailAfterFailedDelete -Expected 200 -Name 'tenant detail after failed delete'
    Assert-PathEquals -Json $detailAfterFailedDelete -Path 'data.deleteFlag' -Expected 'NOT_DELETE' -Name 'tenant still active after failed delete'

    $systemDelete = Invoke-RawPostJson -Url "$baseUrl/tenants/tenant/delete" -Token $token -Data @(@{ id = '0' })
    Assert-Code -Json $systemDelete -Expected 400 -Name 'tenant system delete guard'

    $delete = Invoke-RawPostJson -Url "$baseUrl/tenants/tenant/delete" -Token $token -Data @(@{ id = $tenantId })
    Assert-Code -Json $delete -Expected 200 -Name 'tenant delete'
    Assert-PathEquals -Json $delete -Path 'data' -Expected 'null' -Name 'tenant delete data'

    $detailAfterDelete = Invoke-RawGet -Url "$baseUrl/tenants/tenant/detail?tenantId=$encodedTenantId" -Token $token
    Assert-Code -Json $detailAfterDelete -Expected 200 -Name 'tenant detail after delete'
    Assert-PathEquals -Json $detailAfterDelete -Path 'data' -Expected 'null' -Name 'tenant detail data after delete'

    $deleteState = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('tenants')->where('Tenant_ID', '$tenantId')->find();
echo json_encode([
    'deleteFlag' => `$row['DELETE_FLAG'] ?? null,
    'code' => `$row['CODE'] ?? null,
    'defaultCounts' => [
        'sysUser' => think\facade\Db::name('sys_user')->count(),
        'sysRole' => think\facade\Db::name('sys_role')->count(),
        'sysResource' => think\facade\Db::name('sys_resource')->count(),
        'sysRelation' => think\facade\Db::name('sys_relation')->count()
    ]
], JSON_UNESCAPED_SLASHES);
"@
    if ($deleteState.deleteFlag -ne 'DELETED' -or $deleteState.code -ne $tenantCode) {
        throw 'tenant delete did not preserve expected row state'
    }
    foreach ($key in @('sysUser', 'sysRole', 'sysResource', 'sysRelation')) {
        if ([int]$deleteState.defaultCounts.$key -ne [int]$before.$key) {
            throw "tenant write unexpectedly changed $key count"
        }
    }

    Write-Host 'tenant write HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
