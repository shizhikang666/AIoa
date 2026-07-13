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
    Assert-Paths -Json $Json -Name $Name -Paths @('data.records', 'data.total', 'data.current', 'data.size', 'data.pages')
}

function Assert-ListShape {
    param([string]$Json, [string]$Name)

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data')
}

function Assert-GenBasicRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.dbTable",
        "$Prefix.dbTableKey",
        "$Prefix.pluginName",
        "$Prefix.moduleName",
        "$Prefix.functionName",
        "$Prefix.busName",
        "$Prefix.className",
        "$Prefix.formLayout",
        "$Prefix.packageName",
        "$Prefix.sortCode"
    )
}

function Assert-GenConfigRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.basicId",
        "$Prefix.isTableKey",
        "$Prefix.fieldName",
        "$Prefix.fieldRemark",
        "$Prefix.fieldType",
        "$Prefix.fieldJavaType",
        "$Prefix.effectType",
        "$Prefix.whetherTable",
        "$Prefix.whetherAddUpdate",
        "$Prefix.whetherRequired",
        "$Prefix.queryWhether",
        "$Prefix.sortCode"
    )
}

function Assert-TableRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @("$Prefix.tableName", "$Prefix.tableRemark")
}

function Assert-ColumnRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @("$Prefix.columnName", "$Prefix.typeName", "$Prefix.columnRemark")
}

function Assert-SelectorRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @("$Prefix.id", "$Prefix.label", "$Prefix.value")
}

function Assert-CodeFileRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @("$Prefix.codeFileName", "$Prefix.codeFileWithPathName", "$Prefix.codeFileContent")
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
`$auth['device'] = 'CODEX_GEN_READ_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

$basicPage = Invoke-RawGet -Url "$baseUrl/gen/basic/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $basicPage -Name 'gen basic page'
if (Has-Path -Json $basicPage -Path 'data.records.0') {
    Assert-GenBasicRow -Json $basicPage -Prefix 'data.records.0' -Name 'gen basic page first row'
}

$tables = Invoke-RawGet -Url "$baseUrl/gen/basic/tables" -Token $token
Assert-ListShape -Json $tables -Name 'gen basic tables'
if (Has-Path -Json $tables -Path 'data.0') {
    Assert-TableRow -Json $tables -Prefix 'data.0' -Name 'gen basic tables first row'
}

$firstTable = [string](Read-JsonPath -Json $tables -Path 'data.0.tableName' -Optional)
if ($firstTable.Trim() -ne '') {
    $encodedTable = Enc $firstTable.Trim()
    $columns = Invoke-RawGet -Url "$baseUrl/gen/basic/tableColumns?tableName=$encodedTable" -Token $token
    Assert-ListShape -Json $columns -Name 'gen basic table columns'
    if (Has-Path -Json $columns -Path 'data.0') {
        Assert-ColumnRow -Json $columns -Prefix 'data.0' -Name 'gen basic table columns first row'
    }
} else {
    Write-Host 'skip: gen basic tableColumns sample table not found'
}

$mobileModuleSelector = Invoke-RawGet -Url "$baseUrl/gen/basic/mobileModuleSelector" -Token $token
Assert-ListShape -Json $mobileModuleSelector -Name 'gen basic mobile module selector'
if (Has-Path -Json $mobileModuleSelector -Path 'data.0') {
    Assert-SelectorRow -Json $mobileModuleSelector -Prefix 'data.0' -Name 'gen basic mobile module selector first row'
    Assert-Paths -Json $mobileModuleSelector -Name 'gen basic mobile module selector first row name alias' -Paths @('data.0.name')
}

$basicFirstId = [string](Read-JsonPath -Json $basicPage -Path 'data.records.0.id' -Optional)
if ($basicFirstId.Trim() -ne '') {
    $encodedBasicId = Enc $basicFirstId.Trim()

    $basicDetail = Invoke-RawGet -Url "$baseUrl/gen/basic/detail?id=$encodedBasicId" -Token $token
    Assert-Ok -Json $basicDetail -Name 'gen basic detail'
    Assert-GenBasicRow -Json $basicDetail -Prefix 'data' -Name 'gen basic detail'

    $preview = Invoke-RawGet -Url "$baseUrl/gen/basic/previewGen?id=$encodedBasicId" -Token $token
    Assert-Ok -Json $preview -Name 'gen basic preview'
    Assert-Paths -Json $preview -Name 'gen basic preview buckets' -Paths @(
        'data.genBasicCodeSqlResultList',
        'data.genBasicCodeFrontendResultList',
        'data.genBasicCodeBackendResultList',
        'data.genBasicCodeMobileResultList'
    )
    if (Has-Path -Json $preview -Path 'data.genBasicCodeSqlResultList.0') {
        Assert-CodeFileRow -Json $preview -Prefix 'data.genBasicCodeSqlResultList.0' -Name 'gen basic preview sql first row'
    }
    if (Has-Path -Json $preview -Path 'data.genBasicCodeFrontendResultList.0') {
        Assert-CodeFileRow -Json $preview -Prefix 'data.genBasicCodeFrontendResultList.0' -Name 'gen basic preview frontend first row'
    }
    if (Has-Path -Json $preview -Path 'data.genBasicCodeBackendResultList.0') {
        Assert-CodeFileRow -Json $preview -Prefix 'data.genBasicCodeBackendResultList.0' -Name 'gen basic preview backend first row'
    }
    if (Has-Path -Json $preview -Path 'data.genBasicCodeMobileResultList.0') {
        Assert-CodeFileRow -Json $preview -Prefix 'data.genBasicCodeMobileResultList.0' -Name 'gen basic preview mobile first row'
    }

    $configList = Invoke-RawGet -Url "$baseUrl/gen/config/list?basicId=$encodedBasicId" -Token $token
    Assert-ListShape -Json $configList -Name 'gen config list'
    if (Has-Path -Json $configList -Path 'data.0') {
        Assert-GenConfigRow -Json $configList -Prefix 'data.0' -Name 'gen config list first row'

        $configFirstId = [string](Read-JsonPath -Json $configList -Path 'data.0.id')
        $encodedConfigId = Enc $configFirstId.Trim()
        $configDetail = Invoke-RawGet -Url "$baseUrl/gen/config/detail?id=$encodedConfigId" -Token $token
        Assert-Ok -Json $configDetail -Name 'gen config detail'
        Assert-GenConfigRow -Json $configDetail -Prefix 'data' -Name 'gen config detail'
    } else {
        Write-Host 'skip: gen config detail sample config not found'
    }
} else {
    Write-Host 'skip: gen basic detail/config/preview sample basic not found'
}

Write-Host 'gen read HTTP smoke passed'
