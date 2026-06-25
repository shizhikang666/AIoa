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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-sale-project-repeal-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
        [Parameter(Mandatory = $true)][string]$Path
    )

    $value = $Json | node (Join-Path $PSScriptRoot 'json-read.js') $Path
    if ($LASTEXITCODE -ne 0) {
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
`$auth['device'] = 'CODEX_SALE_PROJECT_REPEAL_HTTP_SMOKE';
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
$prefix = 'CODEX_REPEAL_' + (Get-Date -Format 'MMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
$projectIdOne = [string]([Int64]604300000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$projectIdTwo = [string]([Int64]604301000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$invalidProjectId = [string]([Int64]604302000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$missingId = [string]([Int64]604399000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$invoiceId = [string]([Int64]704300000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))

$safePrefix = $prefix.Replace("'", "\'")
$safeProjectIdOne = $projectIdOne.Replace("'", "\'")
$safeProjectIdTwo = $projectIdTwo.Replace("'", "\'")
$safeInvalidProjectId = $invalidProjectId.Replace("'", "\'")
$safeInvoiceId = $invoiceId.Replace("'", "\'")
$safeUserId = $userId.Replace("'", "\'")
$safeTenantId = $tenantId.Replace("'", "\'")
$safeOrgId = $orgId.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_sale_project_invoicing')->where('ID', '$safeInvoiceId')->delete();
think\facade\Db::name('biz_sale_project')->whereIn('ID', ['$safeProjectIdOne', '$safeProjectIdTwo', '$safeInvalidProjectId'])->delete();
think\facade\Db::name('biz_sale_project')->whereLike('PROJECT_NAME', '$safePrefix%')->delete();
"@

Invoke-Php -Code $cleanupCode | Out-Null

try {
    $insertCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
`$projects = [
    ['$safeProjectIdOne', 'FOLLOW'],
    ['$safeProjectIdTwo', 'FOLLOW'],
    ['$safeInvalidProjectId', 'WAIT_DELIVER'],
];
foreach (`$projects as `$project) {
    think\facade\Db::name('biz_sale_project')->insert([
        'ID' => `$project[0],
        'CUSTOMER' => '$safePrefix-customer',
        'PROJECT_NAME' => '$safePrefix project ' . `$project[0],
        'PROJECT_STATE' => `$project[1],
        'PLAY_STATE' => 'UNPAID',
        'VISIBILITY' => 'PRIVATE',
        'INIT_PRICE' => '100.00',
        'TOTAL_PRICE' => '100.00',
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
        'DEAL_AMOUNT' => 0,
        'HISTORY_AMOUNT' => '0.00',
        'TOTAL_RETURN_AMOUNT' => '0.00',
        'TOTAL_REFUND_AMOUNT' => '0.00',
    ]);
}
think\facade\Db::name('biz_sale_project_invoicing')->insert([
    'ID' => '$safeInvoiceId',
    'PROJECT_ID' => '$safeProjectIdOne',
    'PROCESS_ID' => '$safeInvoiceId-process',
    'INVOICING_CATEGORY' => 'SPECIAL',
    'INVOICING_STATE' => 'WAIT',
    'AMOUNT' => '10.00',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => '$safeUserId',
    'TENANT_ID' => '$safeTenantId',
]);
"@
    Invoke-Php -Code $insertCode | Out-Null

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/repeal" -Data @(@{ id = $projectIdOne; repealContent = "$prefix no-token" })
    Assert-Code -Json $noToken -Expected 401 -Name 'sale project repeal without token'

    $missingList = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/repeal" -Token $token -Data @{ items = @() }
    Assert-Code -Json $missingList -Expected 400 -Name 'sale project repeal missing list'

    $missingProjectRow = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/repeal" -Token $token -Data @(@{ id = $missingId; repealContent = "$prefix missing" })
    Assert-Code -Json $missingProjectRow -Expected 404 -Name 'sale project repeal missing row'

    $invalidState = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/repeal" -Token $token -Data @(@{ id = $invalidProjectId; repealContent = "$prefix invalid" })
    Assert-Code -Json $invalidState -Expected 400 -Name 'sale project repeal invalid state'

    $invalidAfter = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$project = think\facade\Db::name('biz_sale_project')->where('ID', '$safeInvalidProjectId')->find();
echo json_encode(`$project, JSON_UNESCAPED_SLASHES);
"@
    if ($invalidAfter.PROJECT_STATE -ne 'WAIT_DELIVER' -or [int]$invalidAfter.VERSION -ne 0 -or [string]$invalidAfter.REPEAL_CONTENT -ne '') {
        throw 'sale project repeal invalid-state request changed the project'
    }

    $valid = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/repeal" -Token $token -Data @(
        @{ id = $projectIdOne; repealContent = "$prefix reason" },
        @{ id = $projectIdTwo; repealContent = 'ignored second reason' }
    )
    Assert-Code -Json $valid -Expected 200 -Name 'sale project repeal valid'

    $after = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$projects = think\facade\Db::name('biz_sale_project')->whereIn('ID', ['$safeProjectIdOne', '$safeProjectIdTwo'])->order('ID')->select()->toArray();
`$invoice = think\facade\Db::name('biz_sale_project_invoicing')->where('ID', '$safeInvoiceId')->find();
echo json_encode(['projects' => `$projects, 'invoice' => `$invoice], JSON_UNESCAPED_SLASHES);
"@
    foreach ($project in $after.projects) {
        if ($project.PROJECT_STATE -ne 'DISCARD' -or $project.REPEAL_CONTENT -ne "$prefix reason") {
            throw 'sale project repeal did not update state/content as expected'
        }
        if ([int]$project.VERSION -ne 1 -or [string]$project.UPDATE_USER -ne $userId -or [string]$project.UPDATE_TIME -eq '') {
            throw 'sale project repeal did not refresh audit/version fields'
        }
        if ($project.DELETE_FLAG -ne 'NOT_DELETE') {
            throw 'sale project repeal unexpectedly deleted the project row'
        }
    }
    if ($after.invoice.DELETE_FLAG -ne 'NOT_DELETE') {
        throw 'sale project repeal unexpectedly changed invoicing rows'
    }

    Write-Host 'sale project repeal HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
