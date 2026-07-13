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

function Invoke-JsonGet {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$Token
    )

    $raw = & curl.exe -sS -X GET $Url -H "Authorization: Bearer $Token"
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP GET failed: $Url"
    }

    return $raw | ConvertFrom-Json
}

function Assert-Ok {
    param(
        [Parameter(Mandatory = $true)]$Response,
        [Parameter(Mandatory = $true)][string]$Name
    )

    if ([int]$Response.code -ne 200) {
        throw "$Name returned code=$($Response.code)"
    }
}

function Assert-PagedSelector {
    param(
        [Parameter(Mandatory = $true)]$Data,
        [Parameter(Mandatory = $true)][string]$Name
    )

    foreach ($key in @('records', 'total', 'current', 'size', 'pages')) {
        if (-not ($Data.PSObject.Properties.Name -contains $key)) {
            throw "$Name missing paged key: $key"
        }
    }

    $records = @($Data.records)
    if ([int]$Data.current -ne 1) {
        throw "$Name expected current=1"
    }
    if ([int]$Data.size -ne 2) {
        throw "$Name expected size=2"
    }
    if ([int]$Data.total -lt 0 -or [int]$Data.pages -lt 0) {
        throw "$Name has invalid totals"
    }

    foreach ($record in $records) {
        foreach ($key in @('id', 'value', 'label', 'title', 'name', 'code', 'category')) {
            if (-not ($record.PSObject.Properties.Name -contains $key)) {
                throw "$Name record missing selector key: $key"
            }
        }
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
`$auth['device'] = 'CODEX_ROLE_SELECTOR_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$base = $BackendBaseUrl.TrimEnd('/')

$sampleUserCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$id = think\facade\Db::name('sys_user')->where(function (`$query) { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })->value('ID');
if (!`$id) { throw new RuntimeException('sample user not found'); }
echo (string)`$id;
"@
$sampleUserId = & php -r $sampleUserCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($sampleUserId)) {
    throw 'failed to load a sample user id'
}

$ownRole = Invoke-JsonGet -Url "$base/sys/user/ownRole?id=$sampleUserId" -Token $token
Assert-Ok -Response $ownRole -Name 'sys user ownRole'
if ($null -eq $ownRole.data) {
    throw 'sys user ownRole returned null data'
}

$sysUserRoles = Invoke-JsonGet -Url "$base/sys/user/roleSelector?current=1&size=2" -Token $token
Assert-Ok -Response $sysUserRoles -Name 'sys user roleSelector'
Assert-PagedSelector -Data $sysUserRoles.data -Name 'sys user roleSelector'

$bizUserRoles = Invoke-JsonGet -Url "$base/biz/user/roleSelector?current=1&size=2&category=ORG" -Token $token
Assert-Ok -Response $bizUserRoles -Name 'biz user roleSelector'
Assert-PagedSelector -Data $bizUserRoles.data -Name 'biz user roleSelector'

$sysRoleRoles = Invoke-JsonGet -Url "$base/sys/role/roleSelector?current=1&size=2" -Token $token
Assert-Ok -Response $sysRoleRoles -Name 'sys role roleSelector'
Assert-PagedSelector -Data $sysRoleRoles.data -Name 'sys role roleSelector'

Write-Host 'role selector HTTP smoke passed'
