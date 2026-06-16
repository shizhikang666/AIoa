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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-dev-config-edit-batch-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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

function Get-ConfigRow {
    param([Parameter(Mandatory = $true)][string]$Key)

    $safeKey = $Key.Replace("'", "\'")

    return Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('dev_config')->where('CONFIG_KEY', '$safeKey')->find();
echo json_encode(`$row, JSON_UNESCAPED_UNICODE);
"@
}

function Assert-RowField {
    param(
        [Parameter(Mandatory = $true)]$Row,
        [Parameter(Mandatory = $true)][string]$Field,
        [Parameter(Mandatory = $true)][string]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    if ($null -eq $Row) {
        throw "$Name row is null"
    }

    $prop = $Row.PSObject.Properties[$Field]
    if ($null -eq $prop) {
        throw "$Name missing field $Field"
    }

    $actual = [string]$prop.Value
    if ($actual -ne $Expected) {
        throw "$Name expected $Field=$Expected actual=$actual"
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
`$auth['device'] = 'CODEX_DEV_CONFIG_EDIT_BATCH_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = Invoke-Php -Code $tokenCode
if ([string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'CODEX_DEV_CONFIG_BATCH_' + (Get-Date -Format 'MMddHHmmss')
$plainKey = "${prefix}_PLAIN"
$secretKey = "${prefix}_SECRET_TOKEN"
$rollbackKey = "${prefix}_ROLLBACK"
$missingKey = "${prefix}_MISSING"
$safePrefix = $prefix.Replace("'", "\'")
$safePlainKey = $plainKey.Replace("'", "\'")
$safeSecretKey = $secretKey.Replace("'", "\'")
$safeRollbackKey = $rollbackKey.Replace("'", "\'")
$safeMissingKey = $missingKey.Replace("'", "\'")
$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('dev_config')->whereLike('CONFIG_KEY', '$safePrefix%')->delete();
"@

Invoke-Php -Code $cleanupCode | Out-Null

try {
    Invoke-Php -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
`$rows = [
    [
        'ID' => (string)((int)floor(microtime(true) * 1000)) . random_int(100000, 999999),
        'CONFIG_KEY' => '$safePlainKey',
        'CONFIG_VALUE' => 'plain-before',
        'CATEGORY' => 'SYS_BASE',
        'REMARK' => 'codex temporary plain config',
        'SORT_CODE' => 9901,
        'EXT_JSON' => null,
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => 'codex-smoke',
        'UPDATE_TIME' => `$now,
        'UPDATE_USER' => 'codex-smoke',
    ],
    [
        'ID' => (string)((int)floor(microtime(true) * 1000)) . random_int(100000, 999999),
        'CONFIG_KEY' => '$safeSecretKey',
        'CONFIG_VALUE' => 'secret-before',
        'CATEGORY' => 'EMAIL_LOCAL',
        'REMARK' => 'codex temporary secret config',
        'SORT_CODE' => 9902,
        'EXT_JSON' => null,
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => 'codex-smoke',
        'UPDATE_TIME' => `$now,
        'UPDATE_USER' => 'codex-smoke',
    ],
    [
        'ID' => (string)((int)floor(microtime(true) * 1000)) . random_int(100000, 999999),
        'CONFIG_KEY' => '$safeRollbackKey',
        'CONFIG_VALUE' => 'rollback-before',
        'CATEGORY' => 'BIZ_DEFINE',
        'REMARK' => 'codex temporary rollback config',
        'SORT_CODE' => 9903,
        'EXT_JSON' => null,
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => 'codex-smoke',
        'UPDATE_TIME' => `$now,
        'UPDATE_USER' => 'codex-smoke',
    ],
];
foreach (`$rows as `$row) {
    think\facade\Db::name('dev_config')->insert(`$row);
}
"@ | Out-Null

    $noToken = Invoke-RawPostJson -Url "$baseUrl/dev/config/editBatch" -Data @(@{
        configKey = $plainKey
        configValue = 'no-token'
    })
    Assert-Code -Json $noToken -Expected 401 -Name 'dev config editBatch without token'

    $empty = Invoke-RawPostJson -Url "$baseUrl/dev/config/editBatch" -Token $token -Data @()
    Assert-Code -Json $empty -Expected 400 -Name 'dev config editBatch empty list'

    $missingValue = Invoke-RawPostJson -Url "$baseUrl/dev/config/editBatch" -Token $token -Data @(@{
        configKey = $plainKey
    })
    Assert-Code -Json $missingValue -Expected 400 -Name 'dev config editBatch missing value'

    $good = Invoke-RawPostJson -Url "$baseUrl/dev/config/editBatch" -Token $token -Data @(
        @{
            configKey = $plainKey
            configValue = 'plain-after'
        },
        @{
            configKey = $secretKey
            configValue = 'secret-after'
        }
    )
    Assert-Code -Json $good -Expected 200 -Name 'dev config editBatch'
    Assert-PathEquals -Json $good -Path 'data' -Expected 'null' -Name 'dev config editBatch data'

    $plainRow = Get-ConfigRow -Key $plainKey
    Assert-RowField -Row $plainRow -Field 'CONFIG_VALUE' -Expected 'plain-after' -Name 'plain row after batch'
    Assert-RowField -Row $plainRow -Field 'CATEGORY' -Expected 'SYS_BASE' -Name 'plain category preserved'
    Assert-RowField -Row $plainRow -Field 'DELETE_FLAG' -Expected 'NOT_DELETE' -Name 'plain delete flag preserved'

    $secretRow = Get-ConfigRow -Key $secretKey
    Assert-RowField -Row $secretRow -Field 'CONFIG_VALUE' -Expected 'secret-after' -Name 'secret row after batch'
    Assert-RowField -Row $secretRow -Field 'CATEGORY' -Expected 'EMAIL_LOCAL' -Name 'secret category preserved'

    $secretDetail = Invoke-RawGet -Url "$baseUrl/dev/config/detail?id=$(Enc $secretRow.ID)" -Token $token
    Assert-Code -Json $secretDetail -Expected 200 -Name 'dev config secret detail'
    Assert-PathEquals -Json $secretDetail -Path 'data.configValue' -Expected '******' -Name 'dev config secret masked detail'
    Assert-PathEquals -Json $secretDetail -Path 'data.sensitive' -Expected 'true' -Name 'dev config secret sensitive flag'

    $maskPreserve = Invoke-RawPostJson -Url "$baseUrl/dev/config/editBatch" -Token $token -Data @(
        @{
            configKey = $plainKey
            configValue = 'plain-after-mask-pass'
        },
        @{
            configKey = $secretKey
            configValue = '******'
        }
    )
    Assert-Code -Json $maskPreserve -Expected 200 -Name 'dev config editBatch mask preserve'

    $secretAfterMask = Get-ConfigRow -Key $secretKey
    Assert-RowField -Row $secretAfterMask -Field 'CONFIG_VALUE' -Expected 'secret-after' -Name 'secret raw value preserved by mask'

    $duplicate = Invoke-RawPostJson -Url "$baseUrl/dev/config/editBatch" -Token $token -Data @(
        @{
            configKey = $plainKey
            configValue = 'dup-one'
        },
        @{
            configKey = $plainKey
            configValue = 'dup-two'
        }
    )
    Assert-Code -Json $duplicate -Expected 400 -Name 'dev config editBatch duplicate key'

    $plainAfterDuplicate = Get-ConfigRow -Key $plainKey
    Assert-RowField -Row $plainAfterDuplicate -Field 'CONFIG_VALUE' -Expected 'plain-after-mask-pass' -Name 'plain unchanged after duplicate key'

    $mixedMissing = Invoke-RawPostJson -Url "$baseUrl/dev/config/editBatch" -Token $token -Data @(
        @{
            configKey = $rollbackKey
            configValue = 'rollback-should-not-write'
        },
        @{
            configKey = $missingKey
            configValue = 'missing-value'
        }
    )
    Assert-Code -Json $mixedMissing -Expected 404 -Name 'dev config editBatch mixed missing key'

    $rollbackAfterMissing = Get-ConfigRow -Key $rollbackKey
    Assert-RowField -Row $rollbackAfterMissing -Field 'CONFIG_VALUE' -Expected 'rollback-before' -Name 'rollback row unchanged after mixed missing key'

    Write-Host 'dev config editBatch HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
