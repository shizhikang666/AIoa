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

function Assert-PathMissing {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $value = Read-JsonPath -Json $Json -Path $Path -Optional
    if ($null -ne $value) {
        throw "$Name unexpectedly exposed $Path"
    }
}

function Assert-DisplayPaths {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.account",
        "$Prefix.name",
        "$Prefix.avatar",
        "$Prefix.gender",
        "$Prefix.genderName",
        "$Prefix.phone",
        "$Prefix.orgId",
        "$Prefix.orgName",
        "$Prefix.positionId",
        "$Prefix.positionName",
        "$Prefix.userStatus",
        "$Prefix.sortCode"
    )
    Assert-PathMissing -Json $Json -Path "$Prefix.PASSWORD" -Name $Name

    $orgId = Read-JsonPath -Json $Json -Path "$Prefix.orgId"
    $orgName = Read-JsonPath -Json $Json -Path "$Prefix.orgName"
    if ($orgId -ne '' -and $orgId -ne 'null' -and ($orgName -eq '' -or $orgName -eq 'null')) {
        throw "$Name has orgId but blank orgName"
    }

    $positionId = Read-JsonPath -Json $Json -Path "$Prefix.positionId"
    $positionName = Read-JsonPath -Json $Json -Path "$Prefix.positionName"
    if ($positionId -ne '' -and $positionId -ne 'null' -and ($positionName -eq '' -or $positionName -eq 'null')) {
        throw "$Name has positionId but blank positionName"
    }
}

function Assert-PagedDisplayResponse {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data.records', 'data.total', 'data.current', 'data.size', 'data.pages')
    Assert-DisplayPaths -Json $Json -Prefix 'data.records.0' -Name "$Name first record"
}

function Assert-SelectorResponse {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-PagedDisplayResponse -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @(
        'data.records.0.value',
        'data.records.0.label',
        'data.records.0.title'
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
`$auth['device'] = 'CODEX_USER_DISPLAY_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$sampleUserCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$base = function () {
    return think\facade\Db::name('sys_user')
        ->where(function (`$query) { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); });
};
`$id = `$base()
    ->whereNotNull('ORG_ID')
    ->where('ORG_ID', '<>', '')
    ->whereNotNull('POSITION_ID')
    ->where('POSITION_ID', '<>', '')
    ->value('ID');
if (!`$id) { `$id = `$base()->value('ID'); }
if (!`$id) { throw new RuntimeException('sample user not found'); }
echo (string)`$id;
"@

$sampleUserId = & php -r $sampleUserCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($sampleUserId)) {
    throw 'failed to load a sample user id'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$encodedUserId = [System.Uri]::EscapeDataString($sampleUserId.Trim())

$sysPage = Invoke-RawGet -Url "$baseUrl/sys/user/page?id=$encodedUserId&current=1&size=1" -Token $token
Assert-PagedDisplayResponse -Json $sysPage -Name 'sys user page'

$bizPage = Invoke-RawGet -Url "$baseUrl/biz/user/page?id=$encodedUserId&current=1&size=1" -Token $token
Assert-PagedDisplayResponse -Json $bizPage -Name 'biz user page'

$detail = Invoke-RawGet -Url "$baseUrl/sys/user/detail?id=$encodedUserId" -Token $token
Assert-Ok -Json $detail -Name 'sys user detail'
Assert-DisplayPaths -Json $detail -Prefix 'data' -Name 'sys user detail'

$listDetail = Invoke-RawGet -Url "$baseUrl/sys/user/list/detail?id=$encodedUserId" -Token $token
Assert-Ok -Json $listDetail -Name 'sys user list/detail'
Assert-DisplayPaths -Json $listDetail -Prefix 'data.0' -Name 'sys user list/detail first record'

$sysSelector = Invoke-RawGet -Url "$baseUrl/sys/user/userSelector?id=$encodedUserId&current=1&size=1" -Token $token
Assert-SelectorResponse -Json $sysSelector -Name 'sys user userSelector'

$bizSelector = Invoke-RawGet -Url "$baseUrl/biz/user/userSelector?id=$encodedUserId&current=1&size=1" -Token $token
Assert-SelectorResponse -Json $bizSelector -Name 'biz user userSelector'

Write-Host 'user display HTTP smoke passed'
