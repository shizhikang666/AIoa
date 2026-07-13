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

function Enc {
    param([Parameter(Mandatory = $true)][string]$Value)

    return [System.Uri]::EscapeDataString($Value)
}

function Invoke-Download {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$Token
    )

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-payroll-export-{0}.csv" -f ([Guid]::NewGuid().ToString('N')))
    $headerPath = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-payroll-export-{0}.headers" -f ([Guid]::NewGuid().ToString('N')))

    $status = (& curl.exe -sS -D $headerPath -o $bodyPath -w '%{http_code}' $Url -H "Authorization: Bearer $Token")
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP download failed: $Url"
    }

    return @{
        Status = [int]([string]::Join('', [string[]]$status))
        BodyPath = $bodyPath
        HeaderPath = $headerPath
    }
}

function Invoke-RawGet {
    param([Parameter(Mandatory = $true)][string]$Url)

    $raw = & curl.exe -sS -X GET $Url
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP GET failed: $Url"
    }

    return [string]::Join('', [string[]]$raw)
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
`$auth['device'] = 'CODEX_BIZ_PAYROLL_EXPORT_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = (Invoke-Php -Code $tokenCode).Trim()
if ([string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$id = 'BPX' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$prefix = 'codex-payroll-export-' + ([Guid]::NewGuid().ToString('N').Substring(0, 12))
$safeId = $id.Replace("'", "\'")
$safePrefix = $prefix.Replace("'", "\'")
$download = $null

try {
    $setup = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$tenantId = (string)(`$user['TENANT_ID'] ?? '1');
if (`$tenantId === '') { `$tenantId = '1'; }
`$orgId = (string)(`$user['ORG_ID'] ?? '');
if (`$orgId === '') {
    `$orgId = (string)(think\facade\Db::name('sys_org')->where(function (`$query) {
        `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
    })->value('ID') ?? '');
}
think\facade\Db::name('biz_payroll')->whereLike('REMARK', '$safePrefix%')->delete();
`$before = [
    'payroll' => think\facade\Db::name('biz_payroll')->count(),
    'leave' => think\facade\Db::name('biz_leave_application')->count(),
    'vacation' => think\facade\Db::name('biz_user_vacation')->count(),
    'file' => think\facade\Db::name('dev_file')->count(),
];
think\facade\Db::name('biz_payroll')->insert([
    'ID' => '$safeId',
    'SENIORITY_SALARY' => '10.00',
    'PERFORMANCE_SALARY' => '20.00',
    'WORK_SALARY' => '30.00',
    'BASIC_SALARY' => '1000.00',
    'POST_WAGE' => '200.00',
    'RENT_SUBSIDIES' => '50.00',
    'MEAL_ALLOWANCE' => '60.00',
    'DORMITORY_RENT' => '70.00',
    'BASE_AMOUNT' => '1300.00',
    'TRANSACTION_VOLUME' => '5000.00',
    'RECEIVED_AMOUNT' => '4500.00',
    'TAX_FREIGHT' => '100.00',
    'MONTHLY_COMMISSION' => '300.00',
    'BEFORE_RECEIVED_AMOUNT' => '1200.00',
    'BEFORE_COMMISSION' => '80.00',
    'RATE_COMMISSION' => '0.00',
    'TOTAL_COMMISSION' => '380.00',
    'MERIT_BONUSES' => '90.00',
    'VACATION' => '0.00',
    'VACATION_SUB_AMOUNT' => '0.00',
    'YEAR_END_BONUS' => '0.00',
    'PAYABLE_AMOUNT' => '1770.00',
    'PERSONAL_INCOME_TAX' => '30.00',
    'SOCIAL_SECURITY' => '40.00',
    'ACTUAL_AMOUNT' => '1700.00',
    'PUBLIC_ACCOUNT' => '1000.00',
    'PRIVATE_ACCOUNT' => '700.00',
    'SALARY_TIME' => '2026-05-01 00:00:00',
    'USER' => (string)`$user['ID'],
    'ORG' => `$orgId,
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => date('Y-m-d H:i:s'),
    'CREATE_USER' => (string)`$user['ID'],
    'TENANT_ID' => `$tenantId,
    'REMARK' => '$safePrefix-row',
]);
echo json_encode(['id' => '$safeId', 'prefix' => '$safePrefix', 'before' => `$before], JSON_UNESCAPED_UNICODE);
"@

    $baseUrl = $BackendBaseUrl.TrimEnd('/')
    $url = $baseUrl + '/biz/bizpayroll/export?searchKey=' + (Enc $prefix) + '&startSalaryTime=2026-05-01%2000%3A00%3A00&endSalaryTime=2026-05-31%2023%3A59%3A59'
    $download = Invoke-Download -Url $url -Token $token
    if ([int]$download.Status -ne 200) {
        throw "export expected HTTP 200, got $($download.Status)"
    }

    $headers = [System.IO.File]::ReadAllText([string]$download.HeaderPath)
    if ($headers -notmatch 'Content-Type:\s*text/csv') {
        throw "export expected text/csv content type, got headers=$headers"
    }
    if ($headers -notmatch 'Content-Disposition:.*\.csv') {
        throw "export expected csv content disposition, got headers=$headers"
    }

    $utf8 = [System.Text.Encoding]::UTF8
    $orgHeader = $utf8.GetString([byte[]](0xE6, 0x9C, 0xBA, 0xE6, 0x9E, 0x84))
    $cashHeader = $utf8.GetString([byte[]](0xE7, 0x8E, 0xB0, 0xE9, 0x87, 0x91, 0x2F, 0xE7, 0xA7, 0x81, 0xE8, 0xB4, 0xA6))
    $content = [System.IO.File]::ReadAllText([string]$download.BodyPath, $utf8)
    foreach ($needle in @($orgHeader, $cashHeader, "$prefix-row", '1700')) {
        if ($content -notlike "*$needle*") {
            throw "export csv missing marker: $needle"
        }
    }

    $noToken = Invoke-RawGet -Url ($baseUrl + '/biz/bizpayroll/export')
    $code = [int](Read-JsonPath -Json $noToken -Path 'code')
    if ($code -ne 401) {
        throw "no-token export expected code=401, got code=$code"
    }

    $after = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('biz_payroll')->where('ID', '$safeId')->find();
echo json_encode([
    'row' => `$row,
    'counts' => [
        'payroll' => think\facade\Db::name('biz_payroll')->count(),
        'leave' => think\facade\Db::name('biz_leave_application')->count(),
        'vacation' => think\facade\Db::name('biz_user_vacation')->count(),
        'file' => think\facade\Db::name('dev_file')->count(),
    ],
], JSON_UNESCAPED_UNICODE);
"@

    foreach ($name in @('payroll', 'leave', 'vacation', 'file')) {
        $expected = [int]$setup.before.$name
        if ($name -eq 'payroll') {
            $expected += 1
        }
        $actual = [int]$after.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed unexpectedly: expected=$expected actual=$actual"
        }
    }
    if ([string]$after.row.DELETE_FLAG -ne 'NOT_DELETE') {
        throw 'temporary payroll row delete flag changed during export'
    }

    Write-Host 'biz payroll export http smoke passed'
} finally {
    if ($null -ne $download) {
        Remove-Item -LiteralPath ([string]$download.BodyPath) -Force -ErrorAction SilentlyContinue
        Remove-Item -LiteralPath ([string]$download.HeaderPath) -Force -ErrorAction SilentlyContinue
    }

    Invoke-Php -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_payroll')->where('ID', '$safeId')->delete();
think\facade\Db::name('biz_payroll')->whereLike('REMARK', '$safePrefix%')->delete();
"@ | Out-Null
}
