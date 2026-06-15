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

function Invoke-RawPostJson {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$Token,
        [Parameter(Mandatory = $true)][string]$Json
    )

    $tmp = New-TemporaryFile
    try {
        Set-Content -LiteralPath $tmp -Value $Json -NoNewline -Encoding UTF8
        $raw = & curl.exe -sS -X POST $Url -H "Authorization: Bearer $Token" -H 'Content-Type: application/json' --data-binary "@$tmp"
        if ($LASTEXITCODE -ne 0) {
            throw "HTTP POST failed: $Url"
        }

        return [string]::Join('', [string[]]$raw)
    } finally {
        Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue
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

function Assert-Ok {
    param([string]$Json, [string]$Name)

    $code = Read-JsonPath -Json $Json -Path 'code'
    if ([int]$code -ne 200) {
        throw "$Name returned code=$code"
    }
}

function Assert-Paths {
    param([string]$Json, [string]$Name, [string[]]$Paths)

    foreach ($path in $Paths) {
        [void](Read-JsonPath -Json $Json -Path $path)
    }
}

function Has-Path {
    param([string]$Json, [string]$Path)

    return $null -ne (Read-JsonPath -Json $Json -Path $Path -Optional)
}

function Enc {
    param([Parameter(Mandatory = $true)][string]$Value)

    return [System.Uri]::EscapeDataString($Value)
}

function Assert-PagedShape {
    param([string]$Json, [string]$Name)

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data.records', 'data.total')
}

function Assert-ListShape {
    param([string]$Json, [string]$Name)

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data')
}

function Assert-SessionRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.account",
        "$Prefix.name",
        "$Prefix.sessionId",
        "$Prefix.sessionCreateTime",
        "$Prefix.sessionTimeout",
        "$Prefix.tokenCount",
        "$Prefix.tokenSignList"
    )
}

function Assert-MessageRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.subject",
        "$Prefix.category",
        "$Prefix.createTime"
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
`$auth['device'] = 'CODEX_AUTH_INDEX_READ_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

$loginUser = Invoke-RawGet -Url "$baseUrl/auth/b/getLoginUser" -Token $token
Assert-Ok -Json $loginUser -Name 'auth login user'
Assert-Paths -Json $loginUser -Name 'auth login user' -Paths @('data.user.ID', 'data.user.ACCOUNT', 'data.user.NAME', 'data.roleCodeList', 'data.menuIdList')
$userId = [string](Read-JsonPath -Json $loginUser -Path 'data.user.ID')
$encodedUserId = Enc $userId

$sessionAnalysis = Invoke-RawGet -Url "$baseUrl/auth/session/analysis" -Token $token
Assert-Ok -Json $sessionAnalysis -Name 'auth session analysis'
Assert-Paths -Json $sessionAnalysis -Name 'auth session analysis' -Paths @(
    'data.currentSessionTotalCount',
    'data.maxTokenCount',
    'data.oneHourNewlyAdded',
    'data.proportionOfBAndC'
)

$sessionBPage = Invoke-RawGet -Url "$baseUrl/auth/session/b/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $sessionBPage -Name 'auth session b page'
if (Has-Path -Json $sessionBPage -Path 'data.records.0') {
    Assert-SessionRow -Json $sessionBPage -Prefix 'data.records.0' -Name 'auth session b page first row'
}

$sessionCPage = Invoke-RawGet -Url "$baseUrl/auth/session/c/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $sessionCPage -Name 'auth session c page'

$thirdPage = Invoke-RawGet -Url "$baseUrl/auth/third/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $thirdPage -Name 'auth third page'

$loginMenu = Invoke-RawGet -Url "$baseUrl/sys/userCenter/loginMenu" -Token $token
Assert-ListShape -Json $loginMenu -Name 'user center login menu'
if (Has-Path -Json $loginMenu -Path 'data.0') {
    Assert-Paths -Json $loginMenu -Name 'user center login menu first module' -Paths @('data.0.id', 'data.0.title', 'data.0.path')
}

$loginOrgTree = Invoke-RawGet -Url "$baseUrl/sys/userCenter/loginOrgTree" -Token $token
Assert-ListShape -Json $loginOrgTree -Name 'user center login org tree'

$loginPositionInfo = Invoke-RawGet -Url "$baseUrl/sys/userCenter/loginPositionInfo" -Token $token
Assert-ListShape -Json $loginPositionInfo -Name 'user center login position info'

$loginWorkbench = Invoke-RawGet -Url "$baseUrl/sys/userCenter/loginWorkbench" -Token $token
Assert-Ok -Json $loginWorkbench -Name 'user center login workbench'
Assert-Paths -Json $loginWorkbench -Name 'user center login workbench' -Paths @('data')

$processConfig = Invoke-RawPostJson -Url "$baseUrl/sys/userCenter/process/config" -Token $token -Json '{}'
Assert-Ok -Json $processConfig -Name 'user center process config'
Assert-Paths -Json $processConfig -Name 'user center process config' -Paths @('data.configJson', 'data.config')

$unreadMessagePage = Invoke-RawGet -Url "$baseUrl/sys/userCenter/loginUnreadMessagePage?current=1&size=1" -Token $token
Assert-PagedShape -Json $unreadMessagePage -Name 'user center unread message page'
if (Has-Path -Json $unreadMessagePage -Path 'data.records.0') {
    Assert-MessageRow -Json $unreadMessagePage -Prefix 'data.records.0' -Name 'user center unread message first row'
}

$userListByIds = Invoke-RawPostJson -Url "$baseUrl/sys/userCenter/getUserListByIdList" -Token $token -Json "[`"$userId`"]"
Assert-ListShape -Json $userListByIds -Name 'user center user list by ids'
if (Has-Path -Json $userListByIds -Path 'data.0') {
    Assert-Paths -Json $userListByIds -Name 'user center user list by ids first row' -Paths @('data.0.id', 'data.0.name')
}

$avatarById = Invoke-RawGet -Url "$baseUrl/sys/userCenter/getAvatarById?id=$encodedUserId" -Token $token
Assert-Ok -Json $avatarById -Name 'user center avatar by id'
Assert-Paths -Json $avatarById -Name 'user center avatar by id' -Paths @('data.id', 'data.avatar')

$today = Get-Date -Format 'yyyy-MM-dd'
$scheduleList = Invoke-RawGet -Url "$baseUrl/sys/index/schedule/list?scheduleDate=$today" -Token $token
Assert-ListShape -Json $scheduleList -Name 'index schedule list'

$messageList = Invoke-RawGet -Url "$baseUrl/sys/index/message/list?limit=5" -Token $token
Assert-ListShape -Json $messageList -Name 'index message list'
if (Has-Path -Json $messageList -Path 'data.0') {
    Assert-MessageRow -Json $messageList -Prefix 'data.0' -Name 'index message list first row'
}

$messagePage = Invoke-RawGet -Url "$baseUrl/sys/index/message/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $messagePage -Name 'index message page'
if (Has-Path -Json $messagePage -Path 'data.records.0') {
    Assert-MessageRow -Json $messagePage -Prefix 'data.records.0' -Name 'index message page first row'
}

$visLogList = Invoke-RawGet -Url "$baseUrl/sys/index/visLog/list" -Token $token
Assert-ListShape -Json $visLogList -Name 'index visit log list'

$opLogList = Invoke-RawGet -Url "$baseUrl/sys/index/opLog/list" -Token $token
Assert-ListShape -Json $opLogList -Name 'index operation log list'

Write-Host 'auth/index read HTTP smoke passed'
