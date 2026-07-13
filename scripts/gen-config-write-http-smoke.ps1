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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-gen-config-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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

function New-ConfigPayload {
    param(
        [string]$Id,
        [string]$BasicId,
        [string]$FieldName,
        [string]$Remark,
        [string]$JavaType = 'String',
        [string]$EffectType = 'input',
        [int]$SortCode = 31
    )

    return @{
        id = $Id
        basicId = $BasicId
        isTableKey = 'N'
        fieldName = $FieldName
        fieldRemark = $Remark
        fieldType = 'varchar(255)'
        fieldJavaType = $JavaType
        effectType = $EffectType
        dictTypeCode = ''
        whetherTable = 'Y'
        whetherRetract = 'N'
        whetherAddUpdate = 'Y'
        whetherRequired = 'N'
        queryWhether = 'Y'
        queryType = 'like'
        sortCode = $SortCode
        deleteFlag = 'DELETED'
        createTime = '2000-01-01 00:00:00'
        updateUser = 'client-spoof'
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
`$auth['device'] = 'CODEX_GEN_CONFIG_WRITE_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = Invoke-Php -Code $tokenCode
if ([string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'CODEX_GENCFG_' + (Get-Date -Format 'MMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
$baseId = [Int64]602000000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999)
$idA = [string]$baseId
$idB = [string]($baseId + 1)
$deletedId = [string]($baseId + 2)
$basicId = [string]([Int64]602100000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$safeFunctionPrefix = 'CODEX_GENCFG_%'

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$basicIds = think\facade\Db::name('gen_basic')->whereLike('FUNCTION_NAME', '$safeFunctionPrefix')->column('ID');
if (`$basicIds) {
    think\facade\Db::name('gen_config')->whereIn('BASIC_ID', `$basicIds)->delete();
    think\facade\Db::name('gen_basic')->whereIn('ID', `$basicIds)->delete();
}
think\facade\Db::name('gen_config')->whereIn('ID', ['$idA', '$idB', '$deletedId'])->delete();
think\facade\Db::name('gen_basic')->where('ID', '$basicId')->delete();
"@

Invoke-Php -Code $cleanupCode | Out-Null

try {
    $insertCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
think\facade\Db::transaction(function () use (`$now) {
    think\facade\Db::name('gen_basic')->insert([
        'ID' => '$basicId',
        'DB_TABLE' => 'dev_job',
        'DB_TABLE_KEY' => 'ID',
        'PLUGIN_NAME' => 'snowy-plugin-biz',
        'MODULE_NAME' => 'biz',
        'TABLE_PREFIX' => 'N',
        'GENERATE_TYPE' => 'ZIP',
        'MODULE' => '0',
        'MENU_PID' => '0',
        'FUNCTION_NAME' => '$prefix',
        'BUS_NAME' => 'codexgencfg',
        'CLASS_NAME' => 'CodexGenCfg',
        'FORM_LAYOUT' => 'vertical',
        'GRID_WHETHER' => 'N',
        'PACKAGE_NAME' => 'vip.xiaonuo',
        'AUTHOR_NAME' => 'codex',
        'SORT_CODE' => 99,
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => 'codex-smoke',
    ]);

    `$rows = [];
    foreach ([['$idA', '$($prefix)_FIELD_A', 'NOT_DELETE'], ['$idB', '$($prefix)_FIELD_B', 'NOT_DELETE'], ['$deletedId', '$($prefix)_FIELD_DELETED', 'DELETED']] as `$row) {
        `$rows[] = [
            'ID' => `$row[0],
            'BASIC_ID' => '$basicId',
            'IS_TABLE_KEY' => 'N',
            'FIELD_NAME' => `$row[1],
            'FIELD_REMARK' => `$row[1] . ' remark',
            'FIELD_TYPE' => 'varchar(255)',
            'FIELD_JAVA_TYPE' => 'String',
            'EFFECT_TYPE' => 'input',
            'DICT_TYPE_CODE' => null,
            'WHETHER_TABLE' => 'Y',
            'WHETHER_RETRACT' => 'N',
            'WHETHER_ADD_UPDATE' => 'Y',
            'WHETHER_REQUIRED' => 'N',
            'QUERY_WHETHER' => 'N',
            'QUERY_TYPE' => null,
            'SORT_CODE' => 10,
            'DELETE_FLAG' => `$row[2],
            'CREATE_TIME' => `$now,
            'CREATE_USER' => 'codex-smoke',
        ];
    }
    think\facade\Db::name('gen_config')->insertAll(`$rows);
});
"@
    Invoke-Php -Code $insertCode | Out-Null

    $noToken = Invoke-RawPostJson -Url "$baseUrl/gen/config/edit" -Data (New-ConfigPayload -Id $idA -BasicId $basicId -FieldName "$($prefix)_FIELD_A" -Remark "$($prefix)_NO_TOKEN")
    Assert-Code -Json $noToken -Expected 401 -Name 'gen config edit without token'

    $addDeferred = Invoke-RawPostJson -Url "$baseUrl/gen/config/add" -Token $token -Data (New-ConfigPayload -Id $idA -BasicId $basicId -FieldName "$($prefix)_FIELD_A" -Remark "$($prefix)_ADD_DEFERRED")
    Assert-Code -Json $addDeferred -Expected 400 -Name 'gen config add deferred'
    Assert-PathEquals -Json $addDeferred -Path 'data.operation' -Expected 'generator config add' -Name 'gen config add deferred operation'

    $missingField = New-ConfigPayload -Id $idA -BasicId $basicId -FieldName "$($prefix)_FIELD_A" -Remark "$($prefix)_MISSING"
    $missingField.Remove('effectType')
    $missing = Invoke-RawPostJson -Url "$baseUrl/gen/config/edit" -Token $token -Data $missingField
    Assert-Code -Json $missing -Expected 400 -Name 'gen config edit missing required field'

    $invalid = Invoke-RawPostJson -Url "$baseUrl/gen/config/edit" -Token $token -Data (New-ConfigPayload -Id '999999999999999999' -BasicId $basicId -FieldName "$($prefix)_MISSING_ID" -Remark "$($prefix)_MISSING_ID")
    Assert-Code -Json $invalid -Expected 404 -Name 'gen config edit missing id'

    $editPayload = New-ConfigPayload -Id $idA -BasicId $basicId -FieldName "$($prefix)_FIELD_A" -Remark "$($prefix)_A_EDITED" -JavaType 'Integer' -EffectType 'inputNumber' -SortCode 41
    $editPayload['fieldType'] = 'int'
    $editPayload['whetherRetract'] = $true
    $editPayload['whetherRequired'] = $false
    $editPayload['queryType'] = 'eq'
    $edit = Invoke-RawPostJson -Url "$baseUrl/gen/config/edit" -Token $token -Data $editPayload
    Assert-Code -Json $edit -Expected 200 -Name 'gen config edit'
    Assert-PathEquals -Json $edit -Path 'data' -Expected 'null' -Name 'gen config edit data'

    $encodedA = Enc $idA
    $detailA = Invoke-RawGet -Url "$baseUrl/gen/config/detail?id=$encodedA" -Token $token
    Assert-Code -Json $detailA -Expected 200 -Name 'gen config detail after edit'
    Assert-PathEquals -Json $detailA -Path 'data.id' -Expected $idA -Name 'gen config detail id after edit'
    Assert-PathEquals -Json $detailA -Path 'data.fieldRemark' -Expected "$($prefix)_A_EDITED" -Name 'gen config detail remark after edit'
    Assert-PathEquals -Json $detailA -Path 'data.fieldType' -Expected 'int' -Name 'gen config detail fieldType after edit'
    Assert-PathEquals -Json $detailA -Path 'data.fieldJavaType' -Expected 'Integer' -Name 'gen config detail java type after edit'
    Assert-PathEquals -Json $detailA -Path 'data.effectType' -Expected 'inputNumber' -Name 'gen config detail effect after edit'
    Assert-PathEquals -Json $detailA -Path 'data.whetherRetract' -Expected 'Y' -Name 'gen config detail bool true after edit'
    Assert-PathEquals -Json $detailA -Path 'data.whetherRequired' -Expected 'N' -Name 'gen config detail bool false after edit'
    Assert-PathEquals -Json $detailA -Path 'data.queryType' -Expected 'eq' -Name 'gen config detail query type after edit'
    Assert-PathEquals -Json $detailA -Path 'data.sortCode' -Expected '41' -Name 'gen config detail sort after edit'
    Assert-PathEquals -Json $detailA -Path 'data.deleteFlag' -Expected 'NOT_DELETE' -Name 'gen config detail delete flag after edit'
    Assert-PathEquals -Json $detailA -Path 'data.createUser' -Expected 'codex-smoke' -Name 'gen config detail create user preserved'

    $stateAfterEdit = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('gen_config')->where('ID', '$idA')->find();
echo json_encode([
    'deleteFlag' => `$row['DELETE_FLAG'] ?? null,
    'createUser' => `$row['CREATE_USER'] ?? null,
    'updateUserSpoofed' => (`$row['UPDATE_USER'] ?? null) === 'client-spoof',
], JSON_UNESCAPED_SLASHES);
"@
    if ($stateAfterEdit.deleteFlag -ne 'NOT_DELETE' -or $stateAfterEdit.createUser -ne 'codex-smoke' -or [bool]$stateAfterEdit.updateUserSpoofed) {
        throw 'gen config edit wrote client audit/delete fields'
    }

    $deleteMixed = Invoke-RawPostJson -Url "$baseUrl/gen/config/delete" -Token $token -Data @{
        idList = @($idB, '999999999999999998')
    }
    Assert-Code -Json $deleteMixed -Expected 404 -Name 'gen config mixed delete rollback'

    $encodedB = Enc $idB
    $detailBAfterFailedDelete = Invoke-RawGet -Url "$baseUrl/gen/config/detail?id=$encodedB" -Token $token
    Assert-Code -Json $detailBAfterFailedDelete -Expected 200 -Name 'gen config detail after failed delete'
    Assert-PathEquals -Json $detailBAfterFailedDelete -Path 'data.id' -Expected $idB -Name 'gen config still active after failed delete'
    Assert-PathEquals -Json $detailBAfterFailedDelete -Path 'data.deleteFlag' -Expected 'NOT_DELETE' -Name 'gen config delete flag after failed delete'

    $delete = Invoke-RawPostJson -Url "$baseUrl/gen/config/delete" -Token $token -Data @(@{ id = $idB })
    Assert-Code -Json $delete -Expected 200 -Name 'gen config delete'
    Assert-PathEquals -Json $delete -Path 'data' -Expected 'null' -Name 'gen config delete data'

    $detailAfterDelete = Invoke-RawGet -Url "$baseUrl/gen/config/detail?id=$encodedB" -Token $token
    Assert-Code -Json $detailAfterDelete -Expected 200 -Name 'gen config detail after delete'
    Assert-PathEquals -Json $detailAfterDelete -Path 'data' -Expected 'null' -Name 'gen config detail data after delete'

    $encodedBasic = Enc $basicId
    $listAfterDelete = Invoke-RawGet -Url "$baseUrl/gen/config/list?basicId=$encodedBasic" -Token $token
    Assert-Code -Json $listAfterDelete -Expected 200 -Name 'gen config list after delete'
    Assert-PathEquals -Json $listAfterDelete -Path 'data.length' -Expected '1' -Name 'gen config list length after delete'
    Assert-PathEquals -Json $listAfterDelete -Path 'data.0.id' -Expected $idA -Name 'gen config list remaining row after delete'

    $deleteState = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'configDeleteFlag' => think\facade\Db::name('gen_config')->where('ID', '$idB')->value('DELETE_FLAG'),
    'basicDeleteFlag' => think\facade\Db::name('gen_basic')->where('ID', '$basicId')->value('DELETE_FLAG'),
], JSON_UNESCAPED_SLASHES);
"@
    if ($deleteState.configDeleteFlag -ne 'DELETED' -or $deleteState.basicDeleteFlag -ne 'NOT_DELETE') {
        throw 'gen config delete did not preserve expected delete state'
    }

    Write-Host 'gen config write HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
