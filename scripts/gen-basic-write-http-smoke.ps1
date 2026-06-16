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
        [Parameter(Mandatory = $true)][hashtable]$Data,
        [string]$Token = ''
    )

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-gen-basic-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    try {
        $Data | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $tmp -Encoding UTF8
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
$tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_GEN_BASIC_WRITE_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = Invoke-Php -Code $tokenCode
if ([string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'CODEX_GEN_' + (Get-Date -Format 'MMddHHmmss')
$safeFunctionPrefix = 'CODEX_GEN_%'
$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$ids = think\facade\Db::name('gen_basic')->whereLike('FUNCTION_NAME', '$safeFunctionPrefix')->column('ID');
if (`$ids) {
    think\facade\Db::name('gen_config')->whereIn('BASIC_ID', `$ids)->delete();
    think\facade\Db::name('gen_basic')->whereIn('ID', `$ids)->delete();
}
"@

Invoke-Php -Code $cleanupCode | Out-Null

function New-BasicPayload {
    param(
        [string]$FunctionName,
        [string]$DbTable = 'dev_job',
        [string]$DbTableKey = 'ID',
        [string]$ClassName = 'CodexGenJob'
    )

    return @{
        dbTable = $DbTable
        dbTableKey = $DbTableKey
        pluginName = 'snowy-plugin-biz'
        moduleName = 'biz'
        tablePrefix = 'N'
        generateType = 'ZIP'
        module = '0'
        menuPid = '0'
        mobileModule = ''
        functionName = $FunctionName
        busName = 'codexgen'
        className = $ClassName
        formLayout = 'vertical'
        gridWhether = 'N'
        packageName = 'vip.xiaonuo'
        authorName = 'codex'
        sortCode = 99
    }
}

try {
    $noToken = Invoke-RawPostJson -Url "$baseUrl/gen/basic/add" -Data (New-BasicPayload -FunctionName "$prefix-no-token")
    Assert-Code -Json $noToken -Expected 401 -Name 'gen basic add without token'

    $add = Invoke-RawPostJson -Url "$baseUrl/gen/basic/add" -Token $token -Data (New-BasicPayload -FunctionName "$prefix-add")
    Assert-Code -Json $add -Expected 200 -Name 'gen basic add'
    $id = [string](Read-JsonPath -Json $add -Path 'data.id')
    if ($id.Trim() -eq '') {
        throw 'gen basic add did not return id'
    }

    $encodedId = Enc $id
    $detail = Invoke-RawGet -Url "$baseUrl/gen/basic/detail?id=$encodedId" -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'gen basic detail after add'
    Assert-PathEquals -Json $detail -Path 'data.id' -Expected $id -Name 'gen basic detail id after add'
    Assert-PathEquals -Json $detail -Path 'data.dbTable' -Expected 'dev_job' -Name 'gen basic detail table after add'
    Assert-PathEquals -Json $detail -Path 'data.dbTableKey' -Expected 'ID' -Name 'gen basic detail key after add'
    Assert-PathEquals -Json $detail -Path 'data.functionName' -Expected "$prefix-add" -Name 'gen basic detail function after add'

    $configList = Invoke-RawGet -Url "$baseUrl/gen/config/list?basicId=$encodedId" -Token $token
    Assert-Code -Json $configList -Expected 200 -Name 'gen config list after add'
    $configLength = [int](Read-JsonPath -Json $configList -Path 'data.length')
    if ($configLength -le 0) {
        throw 'gen basic add did not create gen_config rows'
    }
    Assert-PathEquals -Json $configList -Path 'data.0.basicId' -Expected $id -Name 'gen config first row basic id after add'
    Assert-PathEquals -Json $configList -Path 'data.0.fieldName' -Expected 'ID' -Name 'gen config first row field after add'
    Assert-PathEquals -Json $configList -Path 'data.0.isTableKey' -Expected 'Y' -Name 'gen config first row key after add'

    $invalidTable = Invoke-RawPostJson -Url "$baseUrl/gen/basic/add" -Token $token -Data (New-BasicPayload -FunctionName "$prefix-invalid-table" -DbTable 'codex_missing_table')
    Assert-Code -Json $invalidTable -Expected 404 -Name 'gen basic invalid table'

    $invalidKey = Invoke-RawPostJson -Url "$baseUrl/gen/basic/add" -Token $token -Data (New-BasicPayload -FunctionName "$prefix-invalid-key" -DbTableKey 'MISSING_KEY')
    Assert-Code -Json $invalidKey -Expected 400 -Name 'gen basic invalid key'

    $editSameTable = New-BasicPayload -FunctionName "$prefix-edit-key" -DbTable 'dev_job' -DbTableKey 'NAME'
    $editSameTable['id'] = $id
    $editSameTable['sortCode'] = 98
    $edit = Invoke-RawPostJson -Url "$baseUrl/gen/basic/edit" -Token $token -Data $editSameTable
    Assert-Code -Json $edit -Expected 200 -Name 'gen basic edit same table'
    Assert-PathEquals -Json $edit -Path 'data.dbTableKey' -Expected 'NAME' -Name 'gen basic edit same table key'
    Assert-PathEquals -Json $edit -Path 'data.functionName' -Expected "$prefix-edit-key" -Name 'gen basic edit same table function'

    $safeId = $id.Replace("'", "\'")
    $keyState = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'nameKey' => think\facade\Db::name('gen_config')->where('BASIC_ID', '$safeId')->where('FIELD_NAME', 'NAME')->where('IS_TABLE_KEY', 'Y')->where('DELETE_FLAG', 'NOT_DELETE')->count(),
    'idKey' => think\facade\Db::name('gen_config')->where('BASIC_ID', '$safeId')->where('FIELD_NAME', 'ID')->where('IS_TABLE_KEY', 'Y')->where('DELETE_FLAG', 'NOT_DELETE')->count()
], JSON_UNESCAPED_SLASHES);
"@
    if ([int]$keyState.nameKey -ne 1 -or [int]$keyState.idKey -ne 0) {
        throw 'gen basic edit did not refresh gen_config primary-key flags'
    }

    $editNewTable = New-BasicPayload -FunctionName "$prefix-edit-table" -DbTable 'gen_basic' -DbTableKey 'ID' -ClassName 'CodexGenBasic'
    $editNewTable['id'] = $id
    $editNewTable['sortCode'] = 97
    $editTable = Invoke-RawPostJson -Url "$baseUrl/gen/basic/edit" -Token $token -Data $editNewTable
    Assert-Code -Json $editTable -Expected 200 -Name 'gen basic edit new table'
    Assert-PathEquals -Json $editTable -Path 'data.dbTable' -Expected 'gen_basic' -Name 'gen basic edit new table name'
    Assert-PathEquals -Json $editTable -Path 'data.dbTableKey' -Expected 'ID' -Name 'gen basic edit new table key'

    $rebuildState = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'active' => think\facade\Db::name('gen_config')->where('BASIC_ID', '$safeId')->where('DELETE_FLAG', 'NOT_DELETE')->count(),
    'dbTableField' => think\facade\Db::name('gen_config')->where('BASIC_ID', '$safeId')->where('FIELD_NAME', 'DB_TABLE')->where('DELETE_FLAG', 'NOT_DELETE')->count(),
    'oldNameField' => think\facade\Db::name('gen_config')->where('BASIC_ID', '$safeId')->where('FIELD_NAME', 'NAME')->where('DELETE_FLAG', 'NOT_DELETE')->count()
], JSON_UNESCAPED_SLASHES);
"@
    if ([int]$rebuildState.active -le 0 -or [int]$rebuildState.dbTableField -ne 1 -or [int]$rebuildState.oldNameField -ne 0) {
        throw 'gen basic table-change edit did not rebuild active configs'
    }

    $deleteMixed = Invoke-RawPostJson -Url "$baseUrl/gen/basic/delete" -Token $token -Data @{
        idList = @($id, '9999999999999999999')
    }
    Assert-Code -Json $deleteMixed -Expected 404 -Name 'gen basic mixed delete rollback'

    $stillActive = Invoke-RawGet -Url "$baseUrl/gen/basic/detail?id=$encodedId" -Token $token
    Assert-Code -Json $stillActive -Expected 200 -Name 'gen basic detail after failed delete'
    Assert-PathEquals -Json $stillActive -Path 'data.id' -Expected $id -Name 'gen basic still active after failed delete'

    $delete = Invoke-RawPostJson -Url "$baseUrl/gen/basic/delete" -Token $token -Data @{
        idList = @($id)
    }
    Assert-Code -Json $delete -Expected 200 -Name 'gen basic delete'

    $pageAfterDelete = Invoke-RawGet -Url "$baseUrl/gen/basic/page?id=$encodedId&current=1&size=1" -Token $token
    Assert-Code -Json $pageAfterDelete -Expected 200 -Name 'gen basic page after delete'
    Assert-PathEquals -Json $pageAfterDelete -Path 'data.total' -Expected '0' -Name 'gen basic page total after delete'

    $afterDeleteState = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'activeConfig' => think\facade\Db::name('gen_config')->where('BASIC_ID', '$safeId')->where('DELETE_FLAG', 'NOT_DELETE')->count()
], JSON_UNESCAPED_SLASHES);
"@
    if ([int]$afterDeleteState.activeConfig -ne 0) {
        throw 'gen basic delete did not logically delete active configs'
    }

    Write-Host 'gen basic write HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
