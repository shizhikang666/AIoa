param(
    [string]$BackendBaseUrl = 'http://127.0.0.1:82',
    [string]$EnvPath = (Join-Path (Resolve-Path (Join-Path $PSScriptRoot '..')) '.env')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $ProjectRoot

Add-Type -AssemblyName System.IO.Compression.FileSystem

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

function Read-JsonPath {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path
    )

    $value = $Json | node (Join-Path $PSScriptRoot 'json-read.js') $Path
    if ($LASTEXITCODE -ne 0) {
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

function Assert-Decimal {
    param(
        [Parameter(Mandatory = $true)]$Actual,
        [Parameter(Mandatory = $true)][string]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $actualDecimal = [decimal]::Parse(([string]$Actual), [System.Globalization.CultureInfo]::InvariantCulture)
    $expectedDecimal = [decimal]::Parse($Expected, [System.Globalization.CultureInfo]::InvariantCulture)
    if ($actualDecimal -ne $expectedDecimal) {
        throw "$Name expected $Expected, got $Actual"
    }
}

function Escape-Xml {
    param([Parameter(Mandatory = $true)][AllowEmptyString()][string]$Value)

    return [System.Security.SecurityElement]::Escape($Value)
}

function New-InlineCell {
    param(
        [Parameter(Mandatory = $true)][string]$Ref,
        [Parameter(Mandatory = $true)][AllowEmptyString()][string]$Value
    )

    return '<c r="{0}" t="inlineStr"><is><t>{1}</t></is></c>' -f $Ref, (Escape-Xml -Value $Value)
}

function New-NumberCell {
    param(
        [Parameter(Mandatory = $true)][string]$Ref,
        [Parameter(Mandatory = $true)][string]$Value
    )

    return '<c r="{0}"><v>{1}</v></c>' -f $Ref, $Value
}

function New-PayrollMonthTitle {
    param(
        [Parameter(Mandatory = $true)][int]$Year,
        [Parameter(Mandatory = $true)][int]$Month
    )

    $yearChar = [char]0x5E74
    $monthChar = [char]0x6708
    $workChar = [char]0x5DE5
    $salaryChar = [char]0x8D44
    $tableChar = [char]0x8868

    return ('{0}{1}{2}{3}{4}{5}{6}' -f $Year, $yearChar, $Month, $monthChar, $workChar, $salaryChar, $tableChar)
}

function New-PayrollImportXlsx {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Title,
        [Parameter(Mandatory = $true)][array]$Rows
    )

    if ($Title -like '2026*') {
        $Title = New-PayrollMonthTitle -Year 2026 -Month 6
    }

    $workDir = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-payroll-import-xlsx-{0}" -f ([Guid]::NewGuid().ToString('N')))
    try {
        $sheetDir = Join-Path $workDir 'xl\worksheets'
        New-Item -ItemType Directory -Path $sheetDir -Force | Out-Null

        $xmlRows = New-Object System.Collections.Generic.List[string]
        $xmlRows.Add('<row r="1">' + (New-InlineCell -Ref 'A1' -Value $Title) + '</row>')
        $xmlRows.Add('<row r="2">' + (New-InlineCell -Ref 'A2' -Value '部门') + (New-InlineCell -Ref 'B2' -Value '序号') + (New-InlineCell -Ref 'C2' -Value '姓名') + '</row>')
        $xmlRows.Add('<row r="3">' + (New-InlineCell -Ref 'D3' -Value '底薪工资') + '</row>')

        $rowNumber = 4
        foreach ($row in $Rows) {
            $cells = New-Object System.Collections.Generic.List[string]
            $cells.Add((New-InlineCell -Ref ("A$rowNumber") -Value ([string]$row['orgName'])))
            $cells.Add((New-InlineCell -Ref ("B$rowNumber") -Value ([string]$row['order'])))
            $cells.Add((New-InlineCell -Ref ("C$rowNumber") -Value ([string]$row['name'])))
            foreach ($column in @('D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB')) {
                $cells.Add((New-NumberCell -Ref ("$column$rowNumber") -Value ([string]$row[$column])))
            }
            $cells.Add((New-InlineCell -Ref ("AC$rowNumber") -Value ([string]$row['remark'])))
            $xmlRows.Add('<row r="{0}">{1}</row>' -f $rowNumber, ([string]::Join('', $cells)))
            $rowNumber++
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' +
            ([string]::Join('', $xmlRows)) +
            '</sheetData></worksheet>'
        [System.IO.File]::WriteAllText((Join-Path $sheetDir 'sheet1.xml'), $sheetXml, [System.Text.Encoding]::UTF8)

        if (Test-Path -LiteralPath $Path) {
            Remove-Item -LiteralPath $Path -Force
        }
        [System.IO.Compression.ZipFile]::CreateFromDirectory($workDir, $Path)
    } finally {
        Remove-Item -LiteralPath $workDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}

function Invoke-MultipartImport {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$File,
        [string]$OrgId = '',
        [string]$Token = ''
    )

    $args = @('-sS', '-X', 'POST', $Url, '-F', "file=@$File;type=application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")
    if ($OrgId.Trim() -ne '') {
        $args += @('-F', "orgId=$OrgId")
    }
    if ($Token.Trim() -ne '') {
        $args += @('-H', "Authorization: Bearer $Token")
    }

    $raw = & curl.exe @args
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP multipart POST failed: $Url"
    }

    return [string]::Join('', [string[]]$raw)
}

function Invoke-MultipartWithoutFile {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$OrgId,
        [Parameter(Mandatory = $true)][string]$Token
    )

    $raw = & curl.exe -sS -X POST $Url -H "Authorization: Bearer $Token" -F "orgId=$OrgId"
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP multipart POST failed: $Url"
    }

    return [string]::Join('', [string[]]$raw)
}

function New-SmokeId {
    param([Parameter(Mandatory = $true)][string]$Prefix)

    return $Prefix + ([Guid]::NewGuid().ToString('N').Substring(0, 20 - $Prefix.Length))
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
`$auth['device'] = 'CODEX_BIZ_PAYROLL_IMPORT_HTTP_SMOKE';
`$tenantId = (string)(`$user['TENANT_ID'] ?? '1');
if (`$tenantId === '') { `$tenantId = '1'; }
`$orgId = (string)(`$user['ORG_ID'] ?? '');
if (`$orgId === '') {
    `$orgId = (string)(think\facade\Db::name('sys_org')->where(function (`$query) {
        `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
    })->value('ID') ?? '');
}
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => (string)`$user['ID'],
    'tenantId' => `$tenantId,
    'orgId' => `$orgId,
], JSON_UNESCAPED_SLASHES);
"@

$session = Invoke-PhpJson -Code $sessionCode
$token = [string]$session.token
$adminUserId = [string]$session.userId
$tenantId = [string]$session.tenantId
$orgId = [string]$session.orgId
if ($token.Trim() -eq '' -or $adminUserId.Trim() -eq '' -or $tenantId.Trim() -eq '' -or $orgId.Trim() -eq '') {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'bpi' + ([Guid]::NewGuid().ToString('N').Substring(0, 9))
$userId = New-SmokeId -Prefix 'BPI'
$userName = "$prefix user"

$safeUserId = $userId.Replace("'", "\'")
$safeUserName = $userName.Replace("'", "\'")
$safePrefix = $prefix.Replace("'", "\'")
$safeTenantId = $tenantId.Replace("'", "\'")
$safeOrgId = $orgId.Replace("'", "\'")
$safeAdminUserId = $adminUserId.Replace("'", "\'")

$validXlsx = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-payroll-import-valid-{0}.xlsx" -f ([Guid]::NewGuid().ToString('N')))
$badMonthXlsx = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-payroll-import-bad-{0}.xlsx" -f ([Guid]::NewGuid().ToString('N')))

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_payroll')->where('USER', '$safeUserId')->delete();
think\facade\Db::name('sys_user')->where('ID', '$safeUserId')->delete();
echo 'ok';
"@

Invoke-Php -Code $cleanupCode | Out-Null

$setupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('sys_user')->insert([
    'ID' => '$safeUserId',
    'ACCOUNT' => '$safePrefix',
    'NAME' => '$safeUserName',
    'ORG_ID' => '$safeOrgId',
    'USER_STATUS' => 'ENABLE',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => '$safeAdminUserId',
    'TENANT_ID' => '$safeTenantId',
    'BANK_NAME' => '',
    'BANK_ACCOUNT' => '',
    'BASIC_SALARY' => '0.00',
]);
echo 'ok';
"@

try {
    Invoke-Php -Code $setupCode | Out-Null

    New-PayrollImportXlsx -Path $validXlsx -Title '2026年06月工资表' -Rows @(
        @{
            orgName = 'Smoke Org'
            order = '1'
            name = $userName
            D = '1000.25'
            E = '200.00'
            F = '30.00'
            G = '10.00'
            H = '40.00'
            I = '50.00'
            J = '60.00'
            K = '70.00'
            L = '1320.25'
            M = '1100.00'
            N = '900.00'
            O = '20.00'
            P = '30.00'
            Q = '400.00'
            R = '15.00'
            S = '45.00'
            T = '80.00'
            U = '25.00'
            V = '300.00'
            W = '1700.00'
            X = '12.00'
            Y = '123.45'
            Z = '1564.55'
            AA = '1000.00'
            AB = '564.55'
            remark = "$prefix remark"
        },
        @{
            orgName = 'Smoke Org'
            order = '2'
            name = "$prefix missing"
            D = '1.00'
            E = '0.00'
            F = '0.00'
            G = '0.00'
            H = '0.00'
            I = '0.00'
            J = '0.00'
            K = '0.00'
            L = '1.00'
            M = '0.00'
            N = '0.00'
            O = '0.00'
            P = '0.00'
            Q = '0.00'
            R = '0.00'
            S = '0.00'
            T = '0.00'
            U = '0.00'
            V = '0.00'
            W = '1.00'
            X = '0.00'
            Y = '0.00'
            Z = '1.00'
            AA = '0.00'
            AB = '1.00'
            remark = 'missing user'
        }
    )
    New-PayrollImportXlsx -Path $badMonthXlsx -Title 'bad month' -Rows @()

    $noToken = Invoke-MultipartImport -Url "$baseUrl/biz/bizpayroll/import" -File $validXlsx -OrgId $orgId
    Assert-Code -Json $noToken -Expected 401 -Name 'payroll import no-token'

    $missingFile = Invoke-MultipartWithoutFile -Url "$baseUrl/biz/bizpayroll/import" -OrgId $orgId -Token $token
    Assert-Code -Json $missingFile -Expected 400 -Name 'payroll import missing file'

    $badMonth = Invoke-MultipartImport -Url "$baseUrl/biz/bizpayroll/import" -File $badMonthXlsx -OrgId $orgId -Token $token
    Assert-Code -Json $badMonth -Expected 400 -Name 'payroll import bad month'

    $valid = Invoke-MultipartImport -Url "$baseUrl/biz/bizpayroll/import" -File $validXlsx -OrgId $orgId -Token $token
    Assert-Code -Json $valid -Expected 200 -Name 'payroll import valid'
    if ([int](Read-JsonPath -Json $valid -Path 'data.totalCount') -ne 2) {
        throw 'payroll import totalCount mismatch'
    }
    if ([int](Read-JsonPath -Json $valid -Path 'data.successCount') -ne 1) {
        throw 'payroll import successCount mismatch'
    }
    if ([int](Read-JsonPath -Json $valid -Path 'data.errorCount') -ne 1) {
        throw 'payroll import errorCount mismatch'
    }
    if ([int](Read-JsonPath -Json $valid -Path 'data.errorDetail.0.index') -ne 2) {
        throw 'payroll import error index mismatch'
    }

    $readbackCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('biz_payroll')->where('USER', '$safeUserId')->where(function (`$query) {
    `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
})->find();
echo json_encode(`$row ?: [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"@
    $row = Invoke-PhpJson -Code $readbackCode
    if ([string]$row.ID -eq '') {
        throw 'payroll import row not inserted'
    }
    if ([string]$row.SALARY_TIME -ne '2026-06-01 00:00:00') {
        throw "payroll import salary time mismatch: $($row.SALARY_TIME)"
    }
    if ([string]$row.ORG -ne $orgId) {
        throw "payroll import org mismatch: $($row.ORG)"
    }
    Assert-Decimal -Actual $row.BASIC_SALARY -Expected '1000.25' -Name 'basic salary'
    Assert-Decimal -Actual $row.POST_WAGE -Expected '200.00' -Name 'post wage'
    Assert-Decimal -Actual $row.WORK_SALARY -Expected '30.00' -Name 'work salary'
    Assert-Decimal -Actual $row.SENIORITY_SALARY -Expected '10.00' -Name 'seniority salary'
    Assert-Decimal -Actual $row.PERFORMANCE_SALARY -Expected '40.00' -Name 'performance salary'
    Assert-Decimal -Actual $row.BASE_AMOUNT -Expected '1320.25' -Name 'base amount'
    Assert-Decimal -Actual $row.TOTAL_COMMISSION -Expected '45.00' -Name 'total commission'
    Assert-Decimal -Actual $row.YEAR_END_BONUS -Expected '300.00' -Name 'year end bonus'
    Assert-Decimal -Actual $row.PAYABLE_AMOUNT -Expected '1700.00' -Name 'payable amount'
    Assert-Decimal -Actual $row.PERSONAL_INCOME_TAX -Expected '12.00' -Name 'personal income tax'
    Assert-Decimal -Actual $row.SOCIAL_SECURITY -Expected '123.45' -Name 'social security'
    Assert-Decimal -Actual $row.ACTUAL_AMOUNT -Expected '1564.55' -Name 'actual amount'
    Assert-Decimal -Actual $row.PUBLIC_ACCOUNT -Expected '1000.00' -Name 'public account'
    Assert-Decimal -Actual $row.PRIVATE_ACCOUNT -Expected '564.55' -Name 'private account'
    if ([string]$row.REMARK -ne "$prefix remark") {
        throw "payroll import remark mismatch: $($row.REMARK)"
    }
    if ([string]$row.DELETE_FLAG -ne 'NOT_DELETE') {
        throw "payroll import delete flag mismatch: $($row.DELETE_FLAG)"
    }
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
    Remove-Item -LiteralPath $validXlsx -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $badMonthXlsx -Force -ErrorAction SilentlyContinue
}

Write-Host 'payroll import HTTP smoke passed'
