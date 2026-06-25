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

function Invoke-RawPostJson {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)]$Data,
        [string]$Token = ''
    )

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-settlement-payment-add-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    try {
        $json = ConvertTo-Json -InputObject $Data -Depth 10
        $utf8NoBom = New-Object System.Text.UTF8Encoding -ArgumentList $false
        [System.IO.File]::WriteAllText($tmp, $json, $utf8NoBom)

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

function Assert-Equal {
    param([string]$Actual, [string]$Expected, [string]$Name)

    if ($Actual -ne $Expected) {
        throw "$Name expected=$Expected actual=$Actual"
    }
}

function Assert-DecimalEqual {
    param([string]$Actual, [decimal]$Expected, [string]$Name)

    if ([decimal]$Actual -ne $Expected) {
        throw "$Name expected=$Expected actual=$Actual"
    }
}

$envMap = Get-EnvMap -Path $EnvPath
$account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
if ($account -eq '') {
    throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env'
}

$accountId = 'SAC' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$objectId = 'OBJ' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$missingAccountId = 'SAC' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$prefix = 'codex-payadd-' + ([Guid]::NewGuid().ToString('N').Substring(0, 8))
$payerTime = '2026-05-06 07:08:09'
$category = 'SMOKE/INCOME'

$safeAccount = $account.Replace("'", "\'")
$safeAccountId = $accountId.Replace("'", "\'")
$safePrefix = $prefix.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_payment_record')->where('TARGET_ID', '$safeAccountId')->delete();
think\facade\Db::name('settlement_account_statement')->where('ACCOUNT_ID', '$safeAccountId')->delete();
think\facade\Db::name('settlement_account')->where('ID', '$safeAccountId')->delete();
"@

Invoke-Php -Code $cleanupCode | Out-Null

$setupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$tenantId = (string)(`$user['TENANT_ID'] ?? '1');
if (`$tenantId === '') { `$tenantId = '1'; }
`$userId = (string)`$user['ID'];
`$orgId = (string)(`$user['ORG_ID'] ?? '');
if (`$orgId === '') {
    `$org = think\facade\Db::name('sys_org')
        ->where(function (`$query) {
            `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
        })
        ->field('ID')
        ->order('ID', 'asc')
        ->find();
    `$orgId = (string)(`$org['ID'] ?? '');
}
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('settlement_account')->insert([
    'ID' => '$safeAccountId',
    'ACCOUNT_NAME' => '$safePrefix-income',
    'ACCOUNT_NUMBER' => '$safePrefix-income-no',
    'INITIAL_AMOUNT' => '1000.00',
    'CURRENT_AMOUNT' => '1000.00',
    'ACCOUNT_STATUS' => 'ENABLE',
    'SORT_CODE' => 993,
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => `$now,
    'UPDATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 0,
    'org' => `$orgId,
]);
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_SETTLEMENT_ACCOUNT_PAYMENT_ADD_HTTP_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => `$userId,
    'tenantId' => `$tenantId,
    'orgId' => `$orgId,
    'baseline' => [
        'payment' => think\facade\Db::name('biz_payment_record')->count(),
        'statement' => think\facade\Db::name('settlement_account_statement')->count(),
        'account' => think\facade\Db::name('settlement_account')->count(),
        'expenditure' => think\facade\Db::name('biz_expenditure_record')->count(),
        'collection' => think\facade\Db::name('biz_collection_receipt')->count(),
        'debit' => think\facade\Db::name('biz_debit_note')->count(),
    ],
], JSON_UNESCAPED_SLASHES);
"@

$setup = Invoke-PhpJson -Code $setupCode
$token = [string]$setup.token
$tenantId = [string]$setup.tenantId
$orgId = [string]$setup.orgId
if ($token.Trim() -eq '' -or $tenantId.Trim() -eq '') {
    throw 'failed to set up settlement-account payment-add smoke data'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

try {
    $validPayload = @{
        objectId = $objectId
        targetId = $accountId
        settlementCategory = @('SMOKE', 'INCOME')
        payer = 'codex smoke payer'
        bankName = 'codex smoke bank'
        bankAccount = 'codex smoke account'
        remark = "$prefix-valid"
        payerTime = $payerTime
        amount = 12.34
    }

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/payment/add" -Data $validPayload
    Assert-Code -Json $noToken -Expected 401 -Name 'settlement account payment add without token'

    $missing = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/payment/add" -Token $token -Data @{
        settlementCategory = $category
        payer = 'codex smoke payer'
        payerTime = $payerTime
        amount = 12.34
    }
    Assert-Code -Json $missing -Expected 400 -Name 'settlement account payment add missing targetId'

    $zero = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/payment/add" -Token $token -Data @{
        targetId = $accountId
        settlementCategory = $category
        payer = 'codex smoke payer'
        payerTime = $payerTime
        amount = 0
    }
    Assert-Code -Json $zero -Expected 400 -Name 'settlement account payment add zero amount'

    $missingAccount = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/payment/add" -Token $token -Data @{
        targetId = $missingAccountId
        settlementCategory = $category
        payer = 'codex smoke payer'
        payerTime = $payerTime
        amount = 12.34
    }
    Assert-Code -Json $missingAccount -Expected 404 -Name 'settlement account payment add missing account rollback'

    $beforeCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$account = think\facade\Db::name('settlement_account')->where('ID', '$safeAccountId')->find();
echo json_encode([
    'currentAmount' => (string)(`$account['CURRENT_AMOUNT'] ?? ''),
    'counts' => [
        'payment' => think\facade\Db::name('biz_payment_record')->count(),
        'statement' => think\facade\Db::name('settlement_account_statement')->count(),
        'account' => think\facade\Db::name('settlement_account')->count(),
        'expenditure' => think\facade\Db::name('biz_expenditure_record')->count(),
        'collection' => think\facade\Db::name('biz_collection_receipt')->count(),
        'debit' => think\facade\Db::name('biz_debit_note')->count(),
    ],
], JSON_UNESCAPED_SLASHES);
"@
    $before = Invoke-PhpJson -Code $beforeCode
    Assert-DecimalEqual -Actual ([string]$before.currentAmount) -Expected ([decimal]'1000.00') -Name 'failed cases account amount preserved'
    foreach ($name in @('payment', 'statement', 'account', 'expenditure', 'collection', 'debit')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$before.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed after failed cases: expected=$expected actual=$actual"
        }
    }

    $add = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/payment/add" -Token $token -Data $validPayload
    Assert-Code -Json $add -Expected 200 -Name 'settlement account payment add'
    Assert-PathEquals -Json $add -Path 'data.accountId' -Expected $accountId -Name 'settlement account payment add account id'
    Assert-PathEquals -Json $add -Path 'data.amount' -Expected '12.34' -Name 'settlement account payment add amount'
    Assert-PathEquals -Json $add -Path 'data.beforeAmount' -Expected '1000.00' -Name 'settlement account payment add before amount'
    Assert-PathEquals -Json $add -Path 'data.afterAmount' -Expected '1012.34' -Name 'settlement account payment add after amount'

    $paymentId = Read-JsonPath -Json $add -Path 'data.id'
    $statementId = Read-JsonPath -Json $add -Path 'data.statementId'
    if ($paymentId.Trim() -eq '' -or $statementId.Trim() -eq '') {
        throw 'settlement account payment add did not return generated ids'
    }

    $detail = Invoke-RawGet -Url "$baseUrl/biz/bizpaymentrecord/detail?id=$(Enc $paymentId)" -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'payment record detail after settlement payment add'
    Assert-PathEquals -Json $detail -Path 'data.id' -Expected $paymentId -Name 'payment record detail id'
    Assert-PathEquals -Json $detail -Path 'data.targetId' -Expected $accountId -Name 'payment record detail targetId'
    Assert-PathEquals -Json $detail -Path 'data.serialId' -Expected $statementId -Name 'payment record detail serialId'
    Assert-PathEquals -Json $detail -Path 'data.processId' -Expected 'Process_sys' -Name 'payment record detail processId'
    Assert-PathEquals -Json $detail -Path 'data.settlementCategory' -Expected $category -Name 'payment record detail settlementCategory'
    Assert-PathEquals -Json $detail -Path 'data.payerTime' -Expected $payerTime -Name 'payment record detail payerTime'
    Assert-PathEquals -Json $detail -Path 'data.amount' -Expected '12.34' -Name 'payment record detail amount'
    Assert-PathEquals -Json $detail -Path 'data.org' -Expected $orgId -Name 'payment record detail org'

    $safePaymentId = $paymentId.Replace("'", "\'")
    $safeStatementId = $statementId.Replace("'", "\'")
    $safeObjectId = $objectId.Replace("'", "\'")
    $safePayerTime = $payerTime.Replace("'", "\'")
    $stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$account = think\facade\Db::name('settlement_account')->where('ID', '$safeAccountId')->find();
`$payment = think\facade\Db::name('biz_payment_record')->where('ID', '$safePaymentId')->find();
`$statement = think\facade\Db::name('settlement_account_statement')->where('ID', '$safeStatementId')->find();
echo json_encode([
    'account' => [
        'amount' => (string)(`$account['CURRENT_AMOUNT'] ?? ''),
        'tenantId' => (string)(`$account['TENANT_ID'] ?? ''),
        'deleteFlag' => (string)(`$account['DELETE_FLAG'] ?? ''),
    ],
    'payment' => [
        'objectId' => (string)(`$payment['OBJECT_ID'] ?? ''),
        'targetId' => (string)(`$payment['TARGET_ID'] ?? ''),
        'serialId' => (string)(`$payment['SERIAL_ID'] ?? ''),
        'processId' => (string)(`$payment['PROCESS_ID'] ?? ''),
        'settlementCategory' => (string)(`$payment['SETTLEMENT_CATEGORY'] ?? ''),
        'payer' => (string)(`$payment['PAYER'] ?? ''),
        'bankName' => (string)(`$payment['BANK_NAME'] ?? ''),
        'bankAccount' => (string)(`$payment['BANK_ACCOUNT'] ?? ''),
        'remark' => (string)(`$payment['REMARK'] ?? ''),
        'payerTime' => (string)(`$payment['PAYER_TIME'] ?? ''),
        'amount' => (string)(`$payment['AMOUNT'] ?? ''),
        'tenantId' => (string)(`$payment['TENANT_ID'] ?? ''),
        'user' => (string)(`$payment['USER'] ?? ''),
        'org' => (string)(`$payment['ORG'] ?? ''),
        'deleteFlag' => (string)(`$payment['DELETE_FLAG'] ?? ''),
    ],
    'statement' => [
        'accountId' => (string)(`$statement['ACCOUNT_ID'] ?? ''),
        'processId' => (string)(`$statement['PROCESS_ID'] ?? ''),
        'beforeAmount' => (string)(`$statement['BEFORE_AMOUNT'] ?? ''),
        'amount' => (string)(`$statement['AMOUNT'] ?? ''),
        'afterAmount' => (string)(`$statement['AFTER_AMOUNT'] ?? ''),
        'settlementType' => (string)(`$statement['SETTLEMENT_TYPE'] ?? ''),
        'settlementCategory' => (string)(`$statement['SETTLEMENT_CATEGORY'] ?? ''),
        'processCategory' => (string)(`$statement['PROCESS_CATEGORY'] ?? ''),
        'payerTime' => (string)(`$statement['PAYER_TIME'] ?? ''),
        'tenantId' => (string)(`$statement['TENANT_ID'] ?? ''),
        'deleteFlag' => (string)(`$statement['DELETE_FLAG'] ?? ''),
    ],
    'counts' => [
        'payment' => think\facade\Db::name('biz_payment_record')->count(),
        'statement' => think\facade\Db::name('settlement_account_statement')->count(),
        'account' => think\facade\Db::name('settlement_account')->count(),
        'expenditure' => think\facade\Db::name('biz_expenditure_record')->count(),
        'collection' => think\facade\Db::name('biz_collection_receipt')->count(),
        'debit' => think\facade\Db::name('biz_debit_note')->count(),
    ],
], JSON_UNESCAPED_SLASHES);
"@

    $state = Invoke-PhpJson -Code $stateCode
    Assert-DecimalEqual -Actual ([string]$state.account.amount) -Expected ([decimal]'1012.34') -Name 'account amount increased by payment amount'
    Assert-Equal -Actual ([string]$state.account.tenantId) -Expected $tenantId -Name 'account tenant preserved'
    Assert-Equal -Actual ([string]$state.account.deleteFlag) -Expected 'NOT_DELETE' -Name 'account deleteFlag preserved'

    Assert-Equal -Actual ([string]$state.payment.objectId) -Expected $objectId -Name 'payment objectId'
    Assert-Equal -Actual ([string]$state.payment.targetId) -Expected $accountId -Name 'payment targetId'
    Assert-Equal -Actual ([string]$state.payment.serialId) -Expected $statementId -Name 'payment serialId'
    Assert-Equal -Actual ([string]$state.payment.processId) -Expected 'Process_sys' -Name 'payment processId'
    Assert-Equal -Actual ([string]$state.payment.settlementCategory) -Expected $category -Name 'payment settlementCategory'
    Assert-Equal -Actual ([string]$state.payment.payer) -Expected 'codex smoke payer' -Name 'payment payer'
    Assert-Equal -Actual ([string]$state.payment.bankName) -Expected 'codex smoke bank' -Name 'payment bankName'
    Assert-Equal -Actual ([string]$state.payment.bankAccount) -Expected 'codex smoke account' -Name 'payment bankAccount'
    Assert-Equal -Actual ([string]$state.payment.remark) -Expected "$prefix-valid" -Name 'payment remark'
    Assert-Equal -Actual ([string]$state.payment.payerTime) -Expected $payerTime -Name 'payment payerTime'
    Assert-DecimalEqual -Actual ([string]$state.payment.amount) -Expected ([decimal]'12.34') -Name 'payment amount'
    Assert-Equal -Actual ([string]$state.payment.tenantId) -Expected $tenantId -Name 'payment tenantId'
    Assert-Equal -Actual ([string]$state.payment.org) -Expected $orgId -Name 'payment org'
    Assert-Equal -Actual ([string]$state.payment.deleteFlag) -Expected 'NOT_DELETE' -Name 'payment deleteFlag'

    Assert-Equal -Actual ([string]$state.statement.accountId) -Expected $accountId -Name 'statement accountId'
    Assert-Equal -Actual ([string]$state.statement.processId) -Expected 'Process_sys' -Name 'statement processId'
    Assert-DecimalEqual -Actual ([string]$state.statement.beforeAmount) -Expected ([decimal]'1000.00') -Name 'statement beforeAmount'
    Assert-DecimalEqual -Actual ([string]$state.statement.amount) -Expected ([decimal]'12.34') -Name 'statement amount'
    Assert-DecimalEqual -Actual ([string]$state.statement.afterAmount) -Expected ([decimal]'1012.34') -Name 'statement afterAmount'
    Assert-Equal -Actual ([string]$state.statement.settlementType) -Expected 'INCOME' -Name 'statement settlementType'
    Assert-Equal -Actual ([string]$state.statement.settlementCategory) -Expected $category -Name 'statement settlementCategory'
    Assert-Equal -Actual ([string]$state.statement.processCategory) -Expected 'Process_sys' -Name 'statement processCategory'
    Assert-Equal -Actual ([string]$state.statement.payerTime) -Expected $payerTime -Name 'statement payerTime'
    Assert-Equal -Actual ([string]$state.statement.tenantId) -Expected $tenantId -Name 'statement tenantId'
    Assert-Equal -Actual ([string]$state.statement.deleteFlag) -Expected 'NOT_DELETE' -Name 'statement deleteFlag'

    foreach ($name in @('account', 'expenditure', 'collection', 'debit')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$state.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed unexpectedly: expected=$expected actual=$actual"
        }
    }
    foreach ($name in @('payment', 'statement')) {
        $expected = [int]$setup.baseline.$name + 1
        $actual = [int]$state.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count did not increase by one: expected=$expected actual=$actual"
        }
    }

    Write-Host 'settlement account payment-add HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
