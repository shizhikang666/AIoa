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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-sale-project-amount-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
`$auth['device'] = 'CODEX_SALE_PROJECT_AMOUNT_HTTP_SMOKE';
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
$prefix = 'CODEX_AMT_' + (Get-Date -Format 'MMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
$projectId = [string]([Int64]604200000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$missingId = [string]([Int64]604299000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$paymentId = [string]([Int64]604210000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$paymentTargetId = [string]([Int64]604211000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$customerId = [string]([Int64]604212000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$safePrefix = $prefix.Replace("'", "\'")
$safeProjectId = $projectId.Replace("'", "\'")
$safePaymentId = $paymentId.Replace("'", "\'")
$safePaymentTargetId = $paymentTargetId.Replace("'", "\'")
$safeCustomerId = $customerId.Replace("'", "\'")
$safeUserId = $userId.Replace("'", "\'")
$safeTenantId = $tenantId.Replace("'", "\'")
$safeOrgId = $orgId.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_payment_record')->where('OBJECT_ID', '$safeProjectId')->delete();
think\facade\Db::name('sales_project_field_change_log')->where('OBJECT_ID', '$safeProjectId')->delete();
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
    'reissueOrder' => think\facade\Db::name('biz_sale_project_reissue_order')->count(),
    'returnOrder' => think\facade\Db::name('return_order')->count(),
    'expenditure' => think\facade\Db::name('biz_expenditure_record')->count(),
    'payment' => think\facade\Db::name('biz_payment_record')->count(),
    'changeLog' => think\facade\Db::name('sales_project_field_change_log')->count()
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
    'CUSTOMER' => '$safeCustomerId',
    'PROJECT_NAME' => '$safePrefix project',
    'PROJECT_STATE' => 'FOLLOW',
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
"@
    Invoke-Php -Code $insertCode | Out-Null

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/amount/edit" -Data @{
        id = $projectId
        initPrice = '150.50'
        remark = "$prefix-no-token"
    }
    Assert-Code -Json $noToken -Expected 401 -Name 'sale project amount without token'

    $missingProject = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/amount/edit" -Token $token -Data @{
        initPrice = '150.50'
        remark = "$prefix-missing-id"
    }
    Assert-Code -Json $missingProject -Expected 400 -Name 'sale project amount missing id'

    $negativeAmount = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/amount/edit" -Token $token -Data @{
        id = $projectId
        initPrice = '-1'
        remark = "$prefix-negative"
    }
    Assert-Code -Json $negativeAmount -Expected 400 -Name 'sale project amount negative initPrice'

    $missingProjectRow = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/amount/edit" -Token $token -Data @{
        id = $missingId
        initPrice = '150.50'
        remark = "$prefix-missing-row"
    }
    Assert-Code -Json $missingProjectRow -Expected 404 -Name 'sale project amount missing row'

    $valid = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/amount/edit" -Token $token -Data @{
        id = $projectId
        initPrice = '150.50'
        remark = "$prefix-reason"
    }
    Assert-Code -Json $valid -Expected 200 -Name 'sale project amount valid edit'

    $afterValid = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->find();
`$log = think\facade\Db::name('sales_project_field_change_log')
    ->where('OBJECT_ID', '$safeProjectId')
    ->where('FIELD_NAME', 'INIT_PRICE')
    ->where(function (`$query) { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->order('CREATE_TIME', 'desc')
    ->find();
echo json_encode(['row' => `$row, 'log' => `$log], JSON_UNESCAPED_SLASHES);
"@
    $projectRow = $afterValid.row
    if ([decimal]$projectRow.INIT_PRICE -ne [decimal]'150.50' -or [decimal]$projectRow.TOTAL_PRICE -ne [decimal]'150.50') {
        throw 'sale project amount edit did not update INIT_PRICE and TOTAL_PRICE'
    }
    if ([decimal]$projectRow.AMOUNT_COLLECTED -ne 0 -or $projectRow.PLAY_STATE -ne 'UNPAID' -or $projectRow.PROJECT_STATE -ne 'SHIPPED') {
        throw 'sale project amount edit did not correct payment/project status as expected'
    }
    if ([decimal]$projectRow.TOTAL_RETURN_AMOUNT -ne 0 -or [decimal]$projectRow.TOTAL_REFUND_AMOUNT -ne 0) {
        throw 'sale project amount edit unexpectedly changed return/refund totals'
    }
    if ([int]$projectRow.VERSION -ne 1 -or [string]$projectRow.UPDATE_USER -ne $userId -or [string]$projectRow.UPDATE_TIME -eq '') {
        throw 'sale project amount edit did not refresh audit/version fields'
    }

    $changeLog = $afterValid.log
    if ($null -eq $changeLog -or $changeLog.OBJECT_ID -ne $projectId -or $changeLog.FIELD_NAME -ne 'INIT_PRICE') {
        throw 'sale project amount edit did not write INIT_PRICE change log'
    }
    if ($changeLog.BEFORE_VALUE -ne '100.00' -or $changeLog.AFTER_VALUE -ne '150.50' -or $changeLog.CHANGE_REASON -ne "$prefix-reason") {
        throw 'sale project amount edit change log values are incorrect'
    }

    $afterSuccessCounts = Invoke-PhpJson -Code $sideEffectCountCode
    foreach ($key in @('productItem', 'invoicing', 'reissueOrder', 'returnOrder', 'expenditure', 'payment')) {
        if ([int]$afterSuccessCounts.$key -ne [int]$before.$key) {
            throw "sale project amount edit unexpectedly changed side-effect table count: $key"
        }
    }
    if ([int]$afterSuccessCounts.changeLog -ne ([int]$before.changeLog + 1)) {
        throw 'sale project amount edit did not add exactly one change log'
    }

    $insertPaymentCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('biz_payment_record')->insert([
    'ID' => '$safePaymentId',
    'OBJECT_ID' => '$safeProjectId',
    'TARGET_ID' => '$safePaymentTargetId',
    'SERIAL_ID' => '$safePaymentTargetId',
    'PROCESS_ID' => '$safePrefix-payment-process',
    'SETTLEMENT_CATEGORY' => 'PROJECT_PLAY',
    'PAYER' => '$safePrefix payer',
    'AMOUNT' => '200.00',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => '$safeUserId',
    'TENANT_ID' => '$safeTenantId',
    'USER' => '$safeUserId',
    'ORG' => '$safeOrgId',
]);
"@
    Invoke-Php -Code $insertPaymentCode | Out-Null

    $overCollected = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/amount/edit" -Token $token -Data @{
        id = $projectId
        initPrice = '50.00'
        remark = "$prefix-over-collected"
    }
    Assert-Code -Json $overCollected -Expected 400 -Name 'sale project amount over-collected rollback'

    $afterRollback = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->find();
`$logCount = think\facade\Db::name('sales_project_field_change_log')
    ->where('OBJECT_ID', '$safeProjectId')
    ->where('FIELD_NAME', 'INIT_PRICE')
    ->where(function (`$query) { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->count();
echo json_encode(['row' => `$row, 'logCount' => `$logCount], JSON_UNESCAPED_SLASHES);
"@
    if ([decimal]$afterRollback.row.INIT_PRICE -ne [decimal]'150.50' -or [decimal]$afterRollback.row.TOTAL_PRICE -ne [decimal]'150.50') {
        throw 'sale project amount over-collected failure did not roll back project amount'
    }
    if ([int]$afterRollback.row.VERSION -ne 1 -or [int]$afterRollback.logCount -ne 1) {
        throw 'sale project amount over-collected failure changed version or change-log count'
    }

    $after = Invoke-PhpJson -Code $sideEffectCountCode
    foreach ($key in @('productItem', 'invoicing', 'reissueOrder', 'returnOrder', 'expenditure')) {
        if ([int]$after.$key -ne [int]$before.$key) {
            throw "sale project amount smoke unexpectedly changed side-effect table count: $key"
        }
    }
    if ([int]$after.payment -ne ([int]$before.payment + 1) -or [int]$after.changeLog -ne ([int]$before.changeLog + 1)) {
        throw 'sale project amount smoke final payment/change-log counts are incorrect'
    }

    Write-Host 'sale project amount edit HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
