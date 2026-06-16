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
        [string]$Token = ''
    )

    $args = @('-sS', '-X', 'GET', $Url)
    if ($Token.Trim() -ne '') {
        $args += @('-H', "Authorization: Bearer $Token")
    }

    $raw = & curl.exe @args
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP GET failed: $Url"
    }

    return [string]::Join('', [string[]]$raw)
}

function Invoke-RawPostJson {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][hashtable]$Data,
        [string]$Token = ''
    )

    $jsonTmp = [System.IO.Path]::GetTempFileName()
    try {
        $Data | ConvertTo-Json -Depth 8 -Compress | Set-Content -LiteralPath $jsonTmp -Encoding UTF8
        $args = @('-sS', '-X', 'POST', $Url, '-H', 'Content-Type: application/json', '--data-binary', "@$jsonTmp")
        if ($Token.Trim() -ne '') {
            $args += @('-H', "Authorization: Bearer $Token")
        }

        $raw = & curl.exe @args
        if ($LASTEXITCODE -ne 0) {
            throw "HTTP POST failed: $Url"
        }
    } finally {
        Remove-Item -LiteralPath $jsonTmp -Force -ErrorAction SilentlyContinue
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

function Assert-Code {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][int]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $code = [int](Read-JsonPath -Json $Json -Path 'code')
    if ($code -ne $Expected) {
        throw "$Name returned code=$code, expected $Expected"
    }
}

function Assert-PathEquals {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $actual = Read-JsonPath -Json $Json -Path $Path
    if ($actual -ne $Expected) {
        throw "$Name expected $Path=$Expected, got $actual"
    }
}

function Invoke-PhpJson {
    param([Parameter(Mandatory = $true)][string]$Code)

    $raw = (& php -r $Code).Trim()
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($raw)) {
        throw 'PHP bootstrap command failed'
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
`$auth['device'] = 'CODEX_BIZ_USER_VACATION_WRITE_HTTP_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => (string)`$user['ID'],
    'tenantId' => (string)(`$user['TENANT_ID'] ?? '')
], JSON_UNESCAPED_SLASHES);
"@

$session = Invoke-PhpJson -Code $sessionCode
$token = [string]$session.token
$userId = [string]$session.userId
$tenantId = [string]$session.tenantId
if ($token.Trim() -eq '' -or $userId.Trim() -eq '') {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'cv' + (Get-Date -Format 'MMddHHmmss')
$category = $prefix + 'A'
$missingId = $prefix + 'Missing'
$safePrefix = $prefix.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_user_vacation')->whereLike('CATEGORY', '$safePrefix%')->delete();
echo 'ok';
"@

& php -r $cleanupCode | Out-Null

$countCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
echo json_encode([
    'leave' => think\facade\Db::name('biz_leave_application')->count(),
    'payroll' => think\facade\Db::name('biz_payroll')->count()
], JSON_UNESCAPED_SLASHES);
"@

try {
    $before = Invoke-PhpJson -Code $countCode

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/bizuservacation/add" -Data @{
        userId = $userId
        amount = '8'
        usedAmount = '1.5'
        category = $category
        tenantId = $tenantId
    }
    Assert-Code -Json $noToken -Expected 401 -Name 'biz user vacation add without token'

    $add = Invoke-RawPostJson -Url "$baseUrl/biz/bizuservacation/add" -Token $token -Data @{
        userId = $userId
        amount = '8'
        usedAmount = '1.5'
        category = $category
        tenantId = $tenantId
    }
    Assert-Code -Json $add -Expected 200 -Name 'biz user vacation add'
    $id = Read-JsonPath -Json $add -Path 'data.id'
    if ($id.Trim() -eq '') {
        throw 'biz user vacation add did not return id'
    }

    $encodedUserId = [System.Uri]::EscapeDataString($userId)
    $encodedCategory = [System.Uri]::EscapeDataString($category)
    $detail = Invoke-RawGet -Url "$baseUrl/biz/bizuservacation/detail?userId=$encodedUserId&category=$encodedCategory" -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'biz user vacation detail after add'
    Assert-PathEquals -Json $detail -Path 'data.id' -Expected $id -Name 'biz user vacation detail after add'
    Assert-PathEquals -Json $detail -Path 'data.amount' -Expected '8' -Name 'biz user vacation detail amount after add'
    Assert-PathEquals -Json $detail -Path 'data.usedAmount' -Expected '1.5' -Name 'biz user vacation detail used amount after add'

    $duplicate = Invoke-RawPostJson -Url "$baseUrl/biz/bizuservacation/add" -Token $token -Data @{
        userId = $userId
        amount = '8'
        usedAmount = '1.5'
        category = $category
        tenantId = $tenantId
    }
    Assert-Code -Json $duplicate -Expected 400 -Name 'biz user vacation duplicate add'

    $edit = Invoke-RawPostJson -Url "$baseUrl/biz/bizuservacation/edit" -Token $token -Data @{
        id = $id
        userId = $userId
        amount = '9'
        usedAmount = '2'
        category = $category
    }
    Assert-Code -Json $edit -Expected 200 -Name 'biz user vacation edit'

    $detailAfterEdit = Invoke-RawGet -Url "$baseUrl/biz/bizuservacation/detail?userId=$encodedUserId&category=$encodedCategory" -Token $token
    Assert-Code -Json $detailAfterEdit -Expected 200 -Name 'biz user vacation detail after edit'
    Assert-PathEquals -Json $detailAfterEdit -Path 'data.amount' -Expected '9' -Name 'biz user vacation detail amount after edit'
    Assert-PathEquals -Json $detailAfterEdit -Path 'data.usedAmount' -Expected '2' -Name 'biz user vacation detail used amount after edit'
    Assert-PathEquals -Json $detailAfterEdit -Path 'data.version' -Expected '1' -Name 'biz user vacation detail version after edit'

    $invalidEdit = Invoke-RawPostJson -Url "$baseUrl/biz/bizuservacation/edit" -Token $token -Data @{
        id = $id
        userId = $userId
        amount = '1'
        usedAmount = '2'
        category = $category
    }
    Assert-Code -Json $invalidEdit -Expected 400 -Name 'biz user vacation invalid edit'

    $deleteMixed = Invoke-RawPostJson -Url "$baseUrl/biz/bizuservacation/delete" -Token $token -Data @{
        idList = @($id, $missingId)
    }
    Assert-Code -Json $deleteMixed -Expected 400 -Name 'biz user vacation mixed delete rollback'

    $stillPresent = Invoke-RawGet -Url "$baseUrl/biz/bizuservacation/detail?userId=$encodedUserId&category=$encodedCategory" -Token $token
    Assert-Code -Json $stillPresent -Expected 200 -Name 'biz user vacation detail after failed delete'
    Assert-PathEquals -Json $stillPresent -Path 'data.id' -Expected $id -Name 'biz user vacation row after failed delete'

    $delete = Invoke-RawPostJson -Url "$baseUrl/biz/bizuservacation/delete" -Token $token -Data @{
        idList = @($id)
    }
    Assert-Code -Json $delete -Expected 200 -Name 'biz user vacation delete'

    $encodedId = [System.Uri]::EscapeDataString($id)
    $pageAfterDelete = Invoke-RawGet -Url "$baseUrl/biz/bizuservacation/page?id=$encodedId&current=1&size=1" -Token $token
    Assert-Code -Json $pageAfterDelete -Expected 200 -Name 'biz user vacation page after delete'
    Assert-PathEquals -Json $pageAfterDelete -Path 'data.total' -Expected '0' -Name 'biz user vacation page total after delete'

    $after = Invoke-PhpJson -Code $countCode
    if ([string]$before.leave -ne [string]$after.leave) {
        throw 'leave application row count changed during vacation smoke'
    }
    if ([string]$before.payroll -ne [string]$after.payroll) {
        throw 'payroll row count changed during vacation smoke'
    }

    Write-Host 'biz user vacation write HTTP smoke passed'
} finally {
    & php -r $cleanupCode | Out-Null
}
