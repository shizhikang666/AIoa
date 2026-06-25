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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-sale-project-cancel-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
`$auth['device'] = 'CODEX_SALE_PROJECT_CANCEL_HTTP_SMOKE';
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
$prefix = 'CODEX_CANCEL_' + (Get-Date -Format 'MMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
$projectId = [string]([Int64]604200000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$invalidProjectId = [string]([Int64]604201000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$otherProjectId = [string]([Int64]604202000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$missingId = [string]([Int64]604299000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$invoiceIdOne = [string]([Int64]704200000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$invoiceIdTwo = [string]([Int64]704201000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$invalidInvoiceId = [string]([Int64]704202000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$otherInvoiceId = [string]([Int64]704203000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))

$safePrefix = $prefix.Replace("'", "\'")
$safeProjectId = $projectId.Replace("'", "\'")
$safeInvalidProjectId = $invalidProjectId.Replace("'", "\'")
$safeOtherProjectId = $otherProjectId.Replace("'", "\'")
$safeInvoiceIdOne = $invoiceIdOne.Replace("'", "\'")
$safeInvoiceIdTwo = $invoiceIdTwo.Replace("'", "\'")
$safeInvalidInvoiceId = $invalidInvoiceId.Replace("'", "\'")
$safeOtherInvoiceId = $otherInvoiceId.Replace("'", "\'")
$safeUserId = $userId.Replace("'", "\'")
$safeTenantId = $tenantId.Replace("'", "\'")
$safeOrgId = $orgId.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_sale_project_invoicing')->whereIn('ID', ['$safeInvoiceIdOne', '$safeInvoiceIdTwo', '$safeInvalidInvoiceId', '$safeOtherInvoiceId'])->delete();
think\facade\Db::name('biz_sale_project')->whereIn('ID', ['$safeProjectId', '$safeInvalidProjectId', '$safeOtherProjectId'])->delete();
think\facade\Db::name('biz_sale_project')->whereLike('PROJECT_NAME', '$safePrefix%')->delete();
"@

Invoke-Php -Code $cleanupCode | Out-Null

try {
    $insertCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
`$projects = [
    ['$safeProjectId', 'WAIT_DELIVER'],
    ['$safeInvalidProjectId', 'FOLLOW'],
    ['$safeOtherProjectId', 'WAIT_DELIVER'],
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
`$invoices = [
    ['$safeInvoiceIdOne', '$safeProjectId', 'WAIT'],
    ['$safeInvoiceIdTwo', '$safeProjectId', 'WAIT'],
    ['$safeInvalidInvoiceId', '$safeInvalidProjectId', 'WAIT'],
    ['$safeOtherInvoiceId', '$safeOtherProjectId', 'WAIT'],
];
foreach (`$invoices as `$invoice) {
    think\facade\Db::name('biz_sale_project_invoicing')->insert([
        'ID' => `$invoice[0],
        'PROJECT_ID' => `$invoice[1],
        'PROCESS_ID' => `$invoice[0] . '-process',
        'INVOICING_CATEGORY' => 'SPECIAL',
        'INVOICING_STATE' => `$invoice[2],
        'AMOUNT' => '10.00',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => '$safeUserId',
        'TENANT_ID' => '$safeTenantId',
    ]);
}
"@
    Invoke-Php -Code $insertCode | Out-Null

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/cancel" -Data @{ id = $projectId }
    Assert-Code -Json $noToken -Expected 401 -Name 'sale project cancel without token'

    $missingProject = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/cancel" -Token $token -Data @{}
    Assert-Code -Json $missingProject -Expected 400 -Name 'sale project cancel missing id'

    $missingProjectRow = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/cancel" -Token $token -Data @{ id = $missingId }
    Assert-Code -Json $missingProjectRow -Expected 404 -Name 'sale project cancel missing row'

    $invalidState = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/cancel" -Token $token -Data @{ id = $invalidProjectId }
    Assert-Code -Json $invalidState -Expected 400 -Name 'sale project cancel invalid state'

    $invalidAfter = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$project = think\facade\Db::name('biz_sale_project')->where('ID', '$safeInvalidProjectId')->find();
`$invoice = think\facade\Db::name('biz_sale_project_invoicing')->where('ID', '$safeInvalidInvoiceId')->find();
echo json_encode(['project' => `$project, 'invoice' => `$invoice], JSON_UNESCAPED_SLASHES);
"@
    if ($invalidAfter.project.PROJECT_STATE -ne 'FOLLOW' -or [int]$invalidAfter.project.VERSION -ne 0) {
        throw 'sale project cancel invalid-state request changed the project'
    }
    if ($invalidAfter.invoice.DELETE_FLAG -ne 'NOT_DELETE') {
        throw 'sale project cancel invalid-state request changed invoicing rows'
    }

    $valid = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/cancel" -Token $token -Data @{ id = $projectId }
    Assert-Code -Json $valid -Expected 200 -Name 'sale project cancel valid'

    $after = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$project = think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->find();
`$targetInvoices = think\facade\Db::name('biz_sale_project_invoicing')->whereIn('ID', ['$safeInvoiceIdOne', '$safeInvoiceIdTwo'])->order('ID')->select()->toArray();
`$otherInvoice = think\facade\Db::name('biz_sale_project_invoicing')->where('ID', '$safeOtherInvoiceId')->find();
echo json_encode(['project' => `$project, 'targetInvoices' => `$targetInvoices, 'otherInvoice' => `$otherInvoice], JSON_UNESCAPED_SLASHES);
"@
    if ($after.project.PROJECT_STATE -ne 'FOLLOW') {
        throw "sale project cancel expected FOLLOW, got $($after.project.PROJECT_STATE)"
    }
    if ([int]$after.project.VERSION -ne 1 -or [string]$after.project.UPDATE_USER -ne $userId -or [string]$after.project.UPDATE_TIME -eq '') {
        throw 'sale project cancel did not refresh audit/version fields'
    }
    foreach ($invoice in $after.targetInvoices) {
        if ($invoice.DELETE_FLAG -ne 'DELETED' -or [string]$invoice.UPDATE_USER -ne $userId -or [string]$invoice.UPDATE_TIME -eq '') {
            throw 'sale project cancel did not logically delete target invoicing rows with audit fields'
        }
    }
    if ($after.otherInvoice.DELETE_FLAG -ne 'NOT_DELETE') {
        throw 'sale project cancel changed unrelated invoicing rows'
    }

    Write-Host 'sale project cancel HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
