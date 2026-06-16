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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-cc-records-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
`$auth['device'] = 'CODEX_CC_RECORDS_WRITE_HTTP_SMOKE';
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
if ($token.Trim() -eq '' -or $userId.Trim() -eq '' -or $tenantId.Trim() -eq '') {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'CODEX_CC_' + (Get-Date -Format 'MMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
$safePrefix = $prefix.Replace("'", "\'")
$missingId = '999999999999999999'

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_cc_records')->whereLike('TITLE', '$safePrefix%')->delete();
"@

Invoke-Php -Code $cleanupCode | Out-Null

$countCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'fileRelation' => think\facade\Db::name('biz_file_relation')->count(),
    'task' => think\facade\Db::name('biz_team_project_task')->count()
], JSON_UNESCAPED_SLASHES);
"@

try {
    $before = Invoke-PhpJson -Code $countCode

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/ccrecords/add" -Data @{
        title = "$prefix-no-token"
        processId = "$prefix-process"
        instanceId = "$prefix-instance"
        category = 'Process_sale_project_init'
        tenantId = $tenantId
    }
    Assert-Code -Json $noToken -Expected 401 -Name 'cc records add without token'

    $missing = Invoke-RawPostJson -Url "$baseUrl/biz/ccrecords/add" -Token $token -Data @{
        processId = "$prefix-process"
        instanceId = "$prefix-instance"
        category = 'Process_sale_project_init'
        tenantId = $tenantId
    }
    Assert-Code -Json $missing -Expected 400 -Name 'cc records add missing title'

    $add = Invoke-RawPostJson -Url "$baseUrl/biz/ccrecords/add" -Token $token -Data @{
        title = "$prefix-add"
        processId = "$prefix-process"
        instanceId = "$prefix-instance"
        category = 'Process_sale_project_init'
        extJson = @{ source = 'codex-smoke'; step = 'add' }
        tenantId = $tenantId
        user = 'client-spoof'
        deleteFlag = 'DELETED'
    }
    Assert-Code -Json $add -Expected 200 -Name 'cc records add'
    Assert-PathEquals -Json $add -Path 'data' -Expected 'null' -Name 'cc records add data'

    $row = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('biz_cc_records')->where('TITLE', '$safePrefix-add')->find();
echo json_encode(`$row, JSON_UNESCAPED_SLASHES);
"@
    $id = [string]$row.ID
    if ($id.Trim() -eq '') {
        throw 'cc records add did not create a row'
    }
    if ([string]$row.USER -ne $userId -or [string]$row.TENANT_ID -ne $tenantId -or [string]$row.DELETE_FLAG -ne 'NOT_DELETE') {
        throw 'cc records add did not preserve current user, tenant, and active delete flag'
    }

    $encodedId = Enc $id
    $detail = Invoke-RawGet -Url "$baseUrl/biz/ccrecords/detail?id=$encodedId" -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'cc records detail after add'
    Assert-PathEquals -Json $detail -Path 'data.title' -Expected "$prefix-add" -Name 'cc records detail title after add'
    Assert-PathEquals -Json $detail -Path 'data.user' -Expected $userId -Name 'cc records detail user after add'
    Assert-PathEquals -Json $detail -Path 'data.category' -Expected 'Process_sale_project_init' -Name 'cc records detail category after add'

    $missingEdit = Invoke-RawPostJson -Url "$baseUrl/biz/ccrecords/edit" -Token $token -Data @{
        title = "$prefix-edit-missing"
        processId = "$prefix-process-edit"
        instanceId = "$prefix-instance-edit"
        category = 'Process_sale_project_init'
        tenantId = $tenantId
    }
    Assert-Code -Json $missingEdit -Expected 400 -Name 'cc records edit missing id'

    $edit = Invoke-RawPostJson -Url "$baseUrl/biz/ccrecords/edit" -Token $token -Data @{
        id = $id
        title = "$prefix-edit"
        processId = "$prefix-process-edit"
        instanceId = "$prefix-instance-edit"
        category = 'Process_sale_project_init'
        extJson = @{ source = 'codex-smoke'; step = 'edit' }
        tenantId = $tenantId
        user = 'client-spoof'
        deleteFlag = 'DELETED'
    }
    Assert-Code -Json $edit -Expected 200 -Name 'cc records edit'
    Assert-PathEquals -Json $edit -Path 'data' -Expected 'null' -Name 'cc records edit data'

    $detailAfterEdit = Invoke-RawGet -Url "$baseUrl/biz/ccrecords/detail?id=$encodedId" -Token $token
    Assert-Code -Json $detailAfterEdit -Expected 200 -Name 'cc records detail after edit'
    Assert-PathEquals -Json $detailAfterEdit -Path 'data.title' -Expected "$prefix-edit" -Name 'cc records detail title after edit'
    Assert-PathEquals -Json $detailAfterEdit -Path 'data.processId' -Expected "$prefix-process-edit" -Name 'cc records detail process after edit'
    Assert-PathEquals -Json $detailAfterEdit -Path 'data.user' -Expected $userId -Name 'cc records detail user preserved after edit'
    Assert-PathEquals -Json $detailAfterEdit -Path 'data.deleteFlag' -Expected 'NOT_DELETE' -Name 'cc records detail delete flag preserved after edit'
    Assert-PathEquals -Json $detailAfterEdit -Path 'data.tenantId' -Expected $tenantId -Name 'cc records detail tenant preserved after edit'

    $deleteMixed = Invoke-RawPostJson -Url "$baseUrl/biz/ccrecords/delete" -Token $token -Data @(
        @{ id = $id },
        @{ id = $missingId }
    )
    Assert-Code -Json $deleteMixed -Expected 404 -Name 'cc records mixed delete rollback'

    $detailAfterFailedDelete = Invoke-RawGet -Url "$baseUrl/biz/ccrecords/detail?id=$encodedId" -Token $token
    Assert-Code -Json $detailAfterFailedDelete -Expected 200 -Name 'cc records detail after failed delete'
    Assert-PathEquals -Json $detailAfterFailedDelete -Path 'data.deleteFlag' -Expected 'NOT_DELETE' -Name 'cc records still active after failed delete'

    $delete = Invoke-RawPostJson -Url "$baseUrl/biz/ccrecords/delete" -Token $token -Data @(@{ id = $id })
    Assert-Code -Json $delete -Expected 200 -Name 'cc records delete'

    $detailAfterDelete = Invoke-RawGet -Url "$baseUrl/biz/ccrecords/detail?id=$encodedId" -Token $token
    Assert-Code -Json $detailAfterDelete -Expected 404 -Name 'cc records detail after delete'

    $deleteState = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('biz_cc_records')->where('ID', '$id')->find();
echo json_encode([
    'deleteFlag' => `$row['DELETE_FLAG'] ?? null,
    'user' => `$row['USER'] ?? null,
    'tenantId' => `$row['TENANT_ID'] ?? null,
    'fileRelation' => think\facade\Db::name('biz_file_relation')->count(),
    'task' => think\facade\Db::name('biz_team_project_task')->count()
], JSON_UNESCAPED_SLASHES);
"@
    if ($deleteState.deleteFlag -ne 'DELETED' -or $deleteState.user -ne $userId -or $deleteState.tenantId -ne $tenantId) {
        throw 'cc records delete did not preserve expected row state'
    }
    if ([int]$deleteState.fileRelation -ne [int]$before.fileRelation -or [int]$deleteState.task -ne [int]$before.task) {
        throw 'cc records write unexpectedly changed workflow/file side-effect tables'
    }

    Write-Host 'biz cc records write HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
