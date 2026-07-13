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

function Invoke-RawPostJson {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)]$Data,
        [string]$Token = ''
    )

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-sale-project-visibility-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    try {
        ConvertTo-Json -InputObject $Data -Depth 10 | Set-Content -LiteralPath $tmp -Encoding UTF8
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
`$auth['device'] = 'CODEX_SALE_PROJECT_VISIBILITY_HTTP_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => (string)`$user['ID'],
    'tenantId' => (string)(`$user['TENANT_ID'] ?? ''),
    'orgId' => (string)(`$user['ORG_ID'] ?? '')
], JSON_UNESCAPED_SLASHES);
"@

$session = Invoke-PhpJson -Code $sessionCode
$token = [string]$session.token
$userId = [string]$session.userId
$tenantId = [string]$session.tenantId
$orgId = [string]$session.orgId
if ($token.Trim() -eq '' -or $userId.Trim() -eq '' -or $tenantId.Trim() -eq '') {
    throw 'failed to create local smoke auth token'
}
if ($orgId.Trim() -eq '') {
    $orgId = '0'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'CODEX_VIS_' + (Get-Date -Format 'MMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
$projectId = [string]([Int64]604100000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$missingId = [string]([Int64]604199000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$safePrefix = $prefix.Replace("'", "\'")
$safeProjectId = $projectId.Replace("'", "\'")
$safeUserId = $userId.Replace("'", "\'")
$safeTenantId = $tenantId.Replace("'", "\'")
$safeOrgId = $orgId.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->delete();
think\facade\Db::name('biz_sale_project')->whereLike('PROJECT_NAME', '$safePrefix%')->delete();
"@

Invoke-Php -Code $cleanupCode | Out-Null

$sideEffectCountCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'productItem' => think\facade\Db::name('biz_sale_project_product_item')->count(),
    'invoicing' => think\facade\Db::name('biz_sale_project_invoicing')->count(),
    'changeLog' => think\facade\Db::name('sales_project_field_change_log')->count(),
    'payment' => think\facade\Db::name('biz_payment_record')->count(),
    'delivery' => think\facade\Db::name('delivery_record')->count()
], JSON_UNESCAPED_SLASHES);
"@

try {
    $before = Invoke-PhpJson -Code $sideEffectCountCode

    $insertCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('biz_sale_project')->insert([
    'ID' => '$safeProjectId',
    'CUSTOMER' => '$safePrefix-customer',
    'PROJECT_NAME' => '$safePrefix project',
    'PROJECT_STATE' => 'FOLLOW',
    'PLAY_STATE' => 'UNPAID',
    'VISIBILITY' => 'PRIVATE',
    'INIT_PRICE' => '0.00',
    'TOTAL_PRICE' => '0.00',
    'AMOUNT_COLLECTED' => '0.00',
    'PROJECT_CATEGORY' => 'DEFAULT',
    'USER' => '$safeUserId',
    'ORG' => '$safeOrgId',
    'REMARK' => '$safePrefix',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => '$safeUserId',
    'TENANT_ID' => '$safeTenantId',
    'VERSION' => 0,
    'SPECIMEN_CATEGORY' => 'OriginalCategory',
    'SPECIMEN_NAME' => 'OriginalName',
    'DEAL_AMOUNT' => 0,
    'HISTORY_AMOUNT' => '0.00',
]);
"@
    Invoke-Php -Code $insertCode | Out-Null

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/visibility/edit" -Data @{
        projectId = $projectId
        visibilityState = 'PUBLIC'
        specimenCategory = 'GrabTheBid'
        specimenName = "$prefix-no-token"
    }
    Assert-Code -Json $noToken -Expected 401 -Name 'sale project visibility without token'

    $missingProject = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/visibility/edit" -Token $token -Data @{
        visibilityState = 'PUBLIC'
        specimenCategory = 'GrabTheBid'
    }
    Assert-Code -Json $missingProject -Expected 400 -Name 'sale project visibility missing projectId'

    $invalidVisibility = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/visibility/edit" -Token $token -Data @{
        projectId = $projectId
        visibilityState = 'TEAM_ONLY'
        specimenCategory = 'GrabTheBid'
    }
    Assert-Code -Json $invalidVisibility -Expected 400 -Name 'sale project visibility invalid visibility'

    $publicMissingSpecimen = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/visibility/edit" -Token $token -Data @{
        projectId = $projectId
        visibilityState = 'PUBLIC'
    }
    Assert-Code -Json $publicMissingSpecimen -Expected 400 -Name 'sale project visibility public missing specimen'

    $missingProjectRow = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/visibility/edit" -Token $token -Data @{
        projectId = $missingId
        visibilityState = 'PUBLIC'
        specimenCategory = 'GrabTheBid'
        specimenName = "$prefix-missing"
    }
    Assert-Code -Json $missingProjectRow -Expected 404 -Name 'sale project visibility missing row'

    $public = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/visibility/edit" -Token $token -Data @{
        projectId = $projectId
        visibilityState = 'PUBLIC'
        specimenCategory = 'GrabTheBid'
        specimenName = "$prefix-brand"
    }
    Assert-Code -Json $public -Expected 200 -Name 'sale project visibility public'

    $publicRow = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->find();
echo json_encode(`$row, JSON_UNESCAPED_SLASHES);
"@
    if ($publicRow.VISIBILITY -ne 'PUBLIC' -or $publicRow.SPECIMEN_CATEGORY -ne 'GrabTheBid' -or $publicRow.SPECIMEN_NAME -ne "$prefix-brand") {
        throw 'sale project visibility public update did not persist expected fields'
    }
    if ([int]$publicRow.VERSION -ne 1 -or [string]$publicRow.UPDATE_USER -ne $userId -or [string]$publicRow.UPDATE_TIME -eq '') {
        throw 'sale project visibility public update did not refresh audit/version fields'
    }
    if ($publicRow.PROJECT_STATE -ne 'FOLLOW' -or $publicRow.PLAY_STATE -ne 'UNPAID' -or [decimal]$publicRow.TOTAL_PRICE -ne 0) {
        throw 'sale project visibility public update changed unrelated project fields'
    }

    $private = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/visibility/edit" -Token $token -Data @{
        projectId = $projectId
        visibilityState = 'PRIVATE'
    }
    Assert-Code -Json $private -Expected 200 -Name 'sale project visibility private'

    $privateRow = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->find();
echo json_encode(`$row, JSON_UNESCAPED_SLASHES);
"@
    if ($privateRow.VISIBILITY -ne 'PRIVATE' -or $privateRow.SPECIMEN_CATEGORY -ne 'GrabTheBid' -or $privateRow.SPECIMEN_NAME -ne "$prefix-brand") {
        throw 'sale project visibility private update did not preserve specimen fields'
    }
    if ([int]$privateRow.VERSION -ne 2) {
        throw "sale project visibility private update expected version=2 actual=$($privateRow.VERSION)"
    }

    $after = Invoke-PhpJson -Code $sideEffectCountCode
    foreach ($key in @('productItem', 'invoicing', 'changeLog', 'payment', 'delivery')) {
        if ([int]$after.$key -ne [int]$before.$key) {
            throw "sale project visibility unexpectedly changed side-effect table count: $key"
        }
    }

    Write-Host 'sale project visibility edit HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
