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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-settlement-transfer-add-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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

$expensesAccountId = 'SAC' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$revenueAccountId = 'SAC' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$missingAccountId = 'SAC' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$prefix = 'codex-transfer-' + ([Guid]::NewGuid().ToString('N').Substring(0, 8))
$payerTime = '2026-05-08 09:10:11'
$category = 'dealings'
$expensesName = "$prefix-expense"
$expensesNumber = "$prefix-expense-no"
$revenueName = "$prefix-income"
$revenueNumber = "$prefix-income-no"

$safeAccount = $account.Replace("'", "\'")
$safeExpensesAccountId = $expensesAccountId.Replace("'", "\'")
$safeRevenueAccountId = $revenueAccountId.Replace("'", "\'")
$safePrefix = $prefix.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$ids = ['$safeExpensesAccountId', '$safeRevenueAccountId'];
think\facade\Db::name('biz_payment_record')->whereIn('TARGET_ID', `$ids)->delete();
think\facade\Db::name('biz_expenditure_record')->whereIn('TARGET_ID', `$ids)->delete();
think\facade\Db::name('settlement_account_statement')->whereIn('ACCOUNT_ID', `$ids)->delete();
think\facade\Db::name('settlement_account')->whereIn('ID', `$ids)->delete();
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
foreach ([
    [
        'ID' => '$safeExpensesAccountId',
        'ACCOUNT_NAME' => '$safePrefix-expense',
        'ACCOUNT_NUMBER' => '$safePrefix-expense-no',
        'INITIAL_AMOUNT' => '1000.00',
        'CURRENT_AMOUNT' => '1000.00',
        'SORT_CODE' => 992,
    ],
    [
        'ID' => '$safeRevenueAccountId',
        'ACCOUNT_NAME' => '$safePrefix-income',
        'ACCOUNT_NUMBER' => '$safePrefix-income-no',
        'INITIAL_AMOUNT' => '500.00',
        'CURRENT_AMOUNT' => '500.00',
        'SORT_CODE' => 991,
    ],
] as `$row) {
    think\facade\Db::name('settlement_account')->insert(array_merge(`$row, [
        'ACCOUNT_STATUS' => 'ENABLE',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => `$userId,
        'UPDATE_TIME' => `$now,
        'UPDATE_USER' => `$userId,
        'TENANT_ID' => `$tenantId,
        'VERSION' => 0,
        'org' => `$orgId,
    ]));
}
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_SETTLEMENT_ACCOUNT_TRANSFER_ADD_HTTP_SMOKE';
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
    throw 'failed to set up settlement-account transfer-add smoke data'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

try {
    $validPayload = @{
        expensesAccountId = $expensesAccountId
        revenueAccountId = $revenueAccountId
        remark = "$prefix-valid"
        payerTime = $payerTime
        amount = 12.34
    }

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/transfer/add" -Data $validPayload
    Assert-Code -Json $noToken -Expected 401 -Name 'settlement account transfer add without token'

    $missingExpenses = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/transfer/add" -Token $token -Data @{
        revenueAccountId = $revenueAccountId
        payerTime = $payerTime
        amount = 12.34
    }
    Assert-Code -Json $missingExpenses -Expected 400 -Name 'settlement account transfer add missing expensesAccountId'

    $missingRevenue = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/transfer/add" -Token $token -Data @{
        expensesAccountId = $expensesAccountId
        payerTime = $payerTime
        amount = 12.34
    }
    Assert-Code -Json $missingRevenue -Expected 400 -Name 'settlement account transfer add missing revenueAccountId'

    $zero = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/transfer/add" -Token $token -Data @{
        expensesAccountId = $expensesAccountId
        revenueAccountId = $revenueAccountId
        payerTime = $payerTime
        amount = 0
    }
    Assert-Code -Json $zero -Expected 400 -Name 'settlement account transfer add zero amount'

    $same = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/transfer/add" -Token $token -Data @{
        expensesAccountId = $expensesAccountId
        revenueAccountId = $expensesAccountId
        payerTime = $payerTime
        amount = 12.34
    }
    Assert-Code -Json $same -Expected 400 -Name 'settlement account transfer add same account'

    $missingAccount = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/transfer/add" -Token $token -Data @{
        expensesAccountId = $expensesAccountId
        revenueAccountId = $missingAccountId
        payerTime = $payerTime
        amount = 12.34
    }
    Assert-Code -Json $missingAccount -Expected 404 -Name 'settlement account transfer add missing account rollback'

    $beforeCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$expenses = think\facade\Db::name('settlement_account')->where('ID', '$safeExpensesAccountId')->find();
`$revenue = think\facade\Db::name('settlement_account')->where('ID', '$safeRevenueAccountId')->find();
echo json_encode([
    'expensesAmount' => (string)(`$expenses['CURRENT_AMOUNT'] ?? ''),
    'revenueAmount' => (string)(`$revenue['CURRENT_AMOUNT'] ?? ''),
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
    Assert-DecimalEqual -Actual ([string]$before.expensesAmount) -Expected ([decimal]'1000.00') -Name 'failed cases expenses amount preserved'
    Assert-DecimalEqual -Actual ([string]$before.revenueAmount) -Expected ([decimal]'500.00') -Name 'failed cases revenue amount preserved'
    foreach ($name in @('payment', 'statement', 'account', 'expenditure', 'collection', 'debit')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$before.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed after failed cases: expected=$expected actual=$actual"
        }
    }

    $add = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/transfer/add" -Token $token -Data $validPayload
    Assert-Code -Json $add -Expected 200 -Name 'settlement account transfer add'
    Assert-PathEquals -Json $add -Path 'data.amount' -Expected '12.34' -Name 'settlement account transfer add amount'
    Assert-PathEquals -Json $add -Path 'data.settlementCategory' -Expected $category -Name 'settlement account transfer add category'
    Assert-PathEquals -Json $add -Path 'data.expenses.accountId' -Expected $expensesAccountId -Name 'settlement account transfer add expenses account id'
    Assert-PathEquals -Json $add -Path 'data.expenses.objectId' -Expected $revenueAccountId -Name 'settlement account transfer add expenses object id'
    Assert-PathEquals -Json $add -Path 'data.expenses.beforeAmount' -Expected '1000.00' -Name 'settlement account transfer add expenses before amount'
    Assert-PathEquals -Json $add -Path 'data.expenses.afterAmount' -Expected '987.66' -Name 'settlement account transfer add expenses after amount'
    Assert-PathEquals -Json $add -Path 'data.income.accountId' -Expected $revenueAccountId -Name 'settlement account transfer add income account id'
    Assert-PathEquals -Json $add -Path 'data.income.objectId' -Expected $expensesAccountId -Name 'settlement account transfer add income object id'
    Assert-PathEquals -Json $add -Path 'data.income.beforeAmount' -Expected '500.00' -Name 'settlement account transfer add income before amount'
    Assert-PathEquals -Json $add -Path 'data.income.afterAmount' -Expected '512.34' -Name 'settlement account transfer add income after amount'

    $expenditureId = Read-JsonPath -Json $add -Path 'data.expenses.id'
    $expensesStatementId = Read-JsonPath -Json $add -Path 'data.expenses.statementId'
    $paymentId = Read-JsonPath -Json $add -Path 'data.income.id'
    $revenueStatementId = Read-JsonPath -Json $add -Path 'data.income.statementId'
    foreach ($id in @($expenditureId, $expensesStatementId, $paymentId, $revenueStatementId)) {
        if ($id.Trim() -eq '') {
            throw 'settlement account transfer add did not return generated ids'
        }
    }

    $expenditureDetail = Invoke-RawGet -Url "$baseUrl/biz/bizexpenditurerecord/detail?id=$(Enc $expenditureId)" -Token $token
    Assert-Code -Json $expenditureDetail -Expected 200 -Name 'expenditure record detail after settlement transfer add'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.id' -Expected $expenditureId -Name 'expenditure detail id'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.objectId' -Expected $revenueAccountId -Name 'expenditure detail objectId'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.targetId' -Expected $expensesAccountId -Name 'expenditure detail targetId'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.serialId' -Expected $expensesStatementId -Name 'expenditure detail serialId'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.processId' -Expected 'Process_sys' -Name 'expenditure detail processId'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.settlementCategory' -Expected $category -Name 'expenditure detail settlementCategory'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.payerTime' -Expected $payerTime -Name 'expenditure detail payerTime'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.amount' -Expected '12.34' -Name 'expenditure detail amount'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.org' -Expected $orgId -Name 'expenditure detail org'

    $paymentDetail = Invoke-RawGet -Url "$baseUrl/biz/bizpaymentrecord/detail?id=$(Enc $paymentId)" -Token $token
    Assert-Code -Json $paymentDetail -Expected 200 -Name 'payment record detail after settlement transfer add'
    Assert-PathEquals -Json $paymentDetail -Path 'data.id' -Expected $paymentId -Name 'payment detail id'
    Assert-PathEquals -Json $paymentDetail -Path 'data.objectId' -Expected $expensesAccountId -Name 'payment detail objectId'
    Assert-PathEquals -Json $paymentDetail -Path 'data.targetId' -Expected $revenueAccountId -Name 'payment detail targetId'
    Assert-PathEquals -Json $paymentDetail -Path 'data.serialId' -Expected $revenueStatementId -Name 'payment detail serialId'
    Assert-PathEquals -Json $paymentDetail -Path 'data.processId' -Expected 'Process_sys' -Name 'payment detail processId'
    Assert-PathEquals -Json $paymentDetail -Path 'data.settlementCategory' -Expected $category -Name 'payment detail settlementCategory'
    Assert-PathEquals -Json $paymentDetail -Path 'data.payerTime' -Expected $payerTime -Name 'payment detail payerTime'
    Assert-PathEquals -Json $paymentDetail -Path 'data.amount' -Expected '12.34' -Name 'payment detail amount'
    Assert-PathEquals -Json $paymentDetail -Path 'data.org' -Expected $orgId -Name 'payment detail org'

    $safeExpenditureId = $expenditureId.Replace("'", "\'")
    $safePaymentId = $paymentId.Replace("'", "\'")
    $safeExpensesStatementId = $expensesStatementId.Replace("'", "\'")
    $safeRevenueStatementId = $revenueStatementId.Replace("'", "\'")
    $stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$expensesAccount = think\facade\Db::name('settlement_account')->where('ID', '$safeExpensesAccountId')->find();
`$revenueAccount = think\facade\Db::name('settlement_account')->where('ID', '$safeRevenueAccountId')->find();
`$expenditure = think\facade\Db::name('biz_expenditure_record')->where('ID', '$safeExpenditureId')->find();
`$payment = think\facade\Db::name('biz_payment_record')->where('ID', '$safePaymentId')->find();
`$expensesStatement = think\facade\Db::name('settlement_account_statement')->where('ID', '$safeExpensesStatementId')->find();
`$revenueStatement = think\facade\Db::name('settlement_account_statement')->where('ID', '$safeRevenueStatementId')->find();
echo json_encode([
    'expensesAccount' => [
        'amount' => (string)(`$expensesAccount['CURRENT_AMOUNT'] ?? ''),
        'tenantId' => (string)(`$expensesAccount['TENANT_ID'] ?? ''),
        'deleteFlag' => (string)(`$expensesAccount['DELETE_FLAG'] ?? ''),
    ],
    'revenueAccount' => [
        'amount' => (string)(`$revenueAccount['CURRENT_AMOUNT'] ?? ''),
        'tenantId' => (string)(`$revenueAccount['TENANT_ID'] ?? ''),
        'deleteFlag' => (string)(`$revenueAccount['DELETE_FLAG'] ?? ''),
    ],
    'expenditure' => [
        'objectId' => (string)(`$expenditure['OBJECT_ID'] ?? ''),
        'targetId' => (string)(`$expenditure['TARGET_ID'] ?? ''),
        'serialId' => (string)(`$expenditure['SERIAL_ID'] ?? ''),
        'processId' => (string)(`$expenditure['PROCESS_ID'] ?? ''),
        'settlementCategory' => (string)(`$expenditure['SETTLEMENT_CATEGORY'] ?? ''),
        'payer' => (string)(`$expenditure['PAYER'] ?? ''),
        'bankName' => (string)(`$expenditure['BANK_NAME'] ?? ''),
        'bankAccount' => (string)(`$expenditure['BANK_ACCOUNT'] ?? ''),
        'remark' => (string)(`$expenditure['REMARK'] ?? ''),
        'payerTime' => (string)(`$expenditure['PAYER_TIME'] ?? ''),
        'amount' => (string)(`$expenditure['AMOUNT'] ?? ''),
        'tenantId' => (string)(`$expenditure['TENANT_ID'] ?? ''),
        'org' => (string)(`$expenditure['ORG'] ?? ''),
        'deleteFlag' => (string)(`$expenditure['DELETE_FLAG'] ?? ''),
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
        'org' => (string)(`$payment['ORG'] ?? ''),
        'deleteFlag' => (string)(`$payment['DELETE_FLAG'] ?? ''),
    ],
    'expensesStatement' => [
        'accountId' => (string)(`$expensesStatement['ACCOUNT_ID'] ?? ''),
        'processId' => (string)(`$expensesStatement['PROCESS_ID'] ?? ''),
        'beforeAmount' => (string)(`$expensesStatement['BEFORE_AMOUNT'] ?? ''),
        'amount' => (string)(`$expensesStatement['AMOUNT'] ?? ''),
        'afterAmount' => (string)(`$expensesStatement['AFTER_AMOUNT'] ?? ''),
        'settlementType' => (string)(`$expensesStatement['SETTLEMENT_TYPE'] ?? ''),
        'settlementCategory' => (string)(`$expensesStatement['SETTLEMENT_CATEGORY'] ?? ''),
        'processCategory' => (string)(`$expensesStatement['PROCESS_CATEGORY'] ?? ''),
        'payerTime' => (string)(`$expensesStatement['PAYER_TIME'] ?? ''),
        'tenantId' => (string)(`$expensesStatement['TENANT_ID'] ?? ''),
        'deleteFlag' => (string)(`$expensesStatement['DELETE_FLAG'] ?? ''),
    ],
    'revenueStatement' => [
        'accountId' => (string)(`$revenueStatement['ACCOUNT_ID'] ?? ''),
        'processId' => (string)(`$revenueStatement['PROCESS_ID'] ?? ''),
        'beforeAmount' => (string)(`$revenueStatement['BEFORE_AMOUNT'] ?? ''),
        'amount' => (string)(`$revenueStatement['AMOUNT'] ?? ''),
        'afterAmount' => (string)(`$revenueStatement['AFTER_AMOUNT'] ?? ''),
        'settlementType' => (string)(`$revenueStatement['SETTLEMENT_TYPE'] ?? ''),
        'settlementCategory' => (string)(`$revenueStatement['SETTLEMENT_CATEGORY'] ?? ''),
        'processCategory' => (string)(`$revenueStatement['PROCESS_CATEGORY'] ?? ''),
        'payerTime' => (string)(`$revenueStatement['PAYER_TIME'] ?? ''),
        'tenantId' => (string)(`$revenueStatement['TENANT_ID'] ?? ''),
        'deleteFlag' => (string)(`$revenueStatement['DELETE_FLAG'] ?? ''),
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
    Assert-DecimalEqual -Actual ([string]$state.expensesAccount.amount) -Expected ([decimal]'987.66') -Name 'expenses account amount decreased by transfer amount'
    Assert-DecimalEqual -Actual ([string]$state.revenueAccount.amount) -Expected ([decimal]'512.34') -Name 'revenue account amount increased by transfer amount'
    Assert-Equal -Actual ([string]$state.expensesAccount.tenantId) -Expected $tenantId -Name 'expenses account tenant preserved'
    Assert-Equal -Actual ([string]$state.revenueAccount.tenantId) -Expected $tenantId -Name 'revenue account tenant preserved'
    Assert-Equal -Actual ([string]$state.expensesAccount.deleteFlag) -Expected 'NOT_DELETE' -Name 'expenses account deleteFlag preserved'
    Assert-Equal -Actual ([string]$state.revenueAccount.deleteFlag) -Expected 'NOT_DELETE' -Name 'revenue account deleteFlag preserved'

    Assert-Equal -Actual ([string]$state.expenditure.objectId) -Expected $revenueAccountId -Name 'expenditure objectId'
    Assert-Equal -Actual ([string]$state.expenditure.targetId) -Expected $expensesAccountId -Name 'expenditure targetId'
    Assert-Equal -Actual ([string]$state.expenditure.serialId) -Expected $expensesStatementId -Name 'expenditure serialId'
    Assert-Equal -Actual ([string]$state.expenditure.processId) -Expected 'Process_sys' -Name 'expenditure processId'
    Assert-Equal -Actual ([string]$state.expenditure.settlementCategory) -Expected $category -Name 'expenditure settlementCategory'
    Assert-Equal -Actual ([string]$state.expenditure.payer) -Expected $revenueName -Name 'expenditure payer'
    Assert-Equal -Actual ([string]$state.expenditure.bankName) -Expected '' -Name 'expenditure bankName'
    Assert-Equal -Actual ([string]$state.expenditure.bankAccount) -Expected $revenueNumber -Name 'expenditure bankAccount'
    Assert-Equal -Actual ([string]$state.expenditure.remark) -Expected "$prefix-valid" -Name 'expenditure remark'
    Assert-Equal -Actual ([string]$state.expenditure.payerTime) -Expected $payerTime -Name 'expenditure payerTime'
    Assert-DecimalEqual -Actual ([string]$state.expenditure.amount) -Expected ([decimal]'12.34') -Name 'expenditure amount'
    Assert-Equal -Actual ([string]$state.expenditure.tenantId) -Expected $tenantId -Name 'expenditure tenantId'
    Assert-Equal -Actual ([string]$state.expenditure.org) -Expected $orgId -Name 'expenditure org'
    Assert-Equal -Actual ([string]$state.expenditure.deleteFlag) -Expected 'NOT_DELETE' -Name 'expenditure deleteFlag'

    Assert-Equal -Actual ([string]$state.payment.objectId) -Expected $expensesAccountId -Name 'payment objectId'
    Assert-Equal -Actual ([string]$state.payment.targetId) -Expected $revenueAccountId -Name 'payment targetId'
    Assert-Equal -Actual ([string]$state.payment.serialId) -Expected $revenueStatementId -Name 'payment serialId'
    Assert-Equal -Actual ([string]$state.payment.processId) -Expected 'Process_sys' -Name 'payment processId'
    Assert-Equal -Actual ([string]$state.payment.settlementCategory) -Expected $category -Name 'payment settlementCategory'
    Assert-Equal -Actual ([string]$state.payment.payer) -Expected $expensesName -Name 'payment payer'
    Assert-Equal -Actual ([string]$state.payment.bankName) -Expected '' -Name 'payment bankName'
    Assert-Equal -Actual ([string]$state.payment.bankAccount) -Expected $expensesNumber -Name 'payment bankAccount'
    Assert-Equal -Actual ([string]$state.payment.remark) -Expected "$prefix-valid" -Name 'payment remark'
    Assert-Equal -Actual ([string]$state.payment.payerTime) -Expected $payerTime -Name 'payment payerTime'
    Assert-DecimalEqual -Actual ([string]$state.payment.amount) -Expected ([decimal]'12.34') -Name 'payment amount'
    Assert-Equal -Actual ([string]$state.payment.tenantId) -Expected $tenantId -Name 'payment tenantId'
    Assert-Equal -Actual ([string]$state.payment.org) -Expected $orgId -Name 'payment org'
    Assert-Equal -Actual ([string]$state.payment.deleteFlag) -Expected 'NOT_DELETE' -Name 'payment deleteFlag'

    Assert-Equal -Actual ([string]$state.expensesStatement.accountId) -Expected $expensesAccountId -Name 'expenses statement accountId'
    Assert-Equal -Actual ([string]$state.expensesStatement.processId) -Expected 'Process_sys' -Name 'expenses statement processId'
    Assert-DecimalEqual -Actual ([string]$state.expensesStatement.beforeAmount) -Expected ([decimal]'1000.00') -Name 'expenses statement beforeAmount'
    Assert-DecimalEqual -Actual ([string]$state.expensesStatement.amount) -Expected ([decimal]'12.34') -Name 'expenses statement amount'
    Assert-DecimalEqual -Actual ([string]$state.expensesStatement.afterAmount) -Expected ([decimal]'987.66') -Name 'expenses statement afterAmount'
    Assert-Equal -Actual ([string]$state.expensesStatement.settlementType) -Expected 'EXPEND' -Name 'expenses statement settlementType'
    Assert-Equal -Actual ([string]$state.expensesStatement.settlementCategory) -Expected $category -Name 'expenses statement settlementCategory'
    Assert-Equal -Actual ([string]$state.expensesStatement.processCategory) -Expected 'Process_sys' -Name 'expenses statement processCategory'
    Assert-Equal -Actual ([string]$state.expensesStatement.payerTime) -Expected $payerTime -Name 'expenses statement payerTime'
    Assert-Equal -Actual ([string]$state.expensesStatement.tenantId) -Expected $tenantId -Name 'expenses statement tenantId'
    Assert-Equal -Actual ([string]$state.expensesStatement.deleteFlag) -Expected 'NOT_DELETE' -Name 'expenses statement deleteFlag'

    Assert-Equal -Actual ([string]$state.revenueStatement.accountId) -Expected $revenueAccountId -Name 'revenue statement accountId'
    Assert-Equal -Actual ([string]$state.revenueStatement.processId) -Expected 'Process_sys' -Name 'revenue statement processId'
    Assert-DecimalEqual -Actual ([string]$state.revenueStatement.beforeAmount) -Expected ([decimal]'500.00') -Name 'revenue statement beforeAmount'
    Assert-DecimalEqual -Actual ([string]$state.revenueStatement.amount) -Expected ([decimal]'12.34') -Name 'revenue statement amount'
    Assert-DecimalEqual -Actual ([string]$state.revenueStatement.afterAmount) -Expected ([decimal]'512.34') -Name 'revenue statement afterAmount'
    Assert-Equal -Actual ([string]$state.revenueStatement.settlementType) -Expected 'INCOME' -Name 'revenue statement settlementType'
    Assert-Equal -Actual ([string]$state.revenueStatement.settlementCategory) -Expected $category -Name 'revenue statement settlementCategory'
    Assert-Equal -Actual ([string]$state.revenueStatement.processCategory) -Expected 'Process_sys' -Name 'revenue statement processCategory'
    Assert-Equal -Actual ([string]$state.revenueStatement.payerTime) -Expected $payerTime -Name 'revenue statement payerTime'
    Assert-Equal -Actual ([string]$state.revenueStatement.tenantId) -Expected $tenantId -Name 'revenue statement tenantId'
    Assert-Equal -Actual ([string]$state.revenueStatement.deleteFlag) -Expected 'NOT_DELETE' -Name 'revenue statement deleteFlag'

    foreach ($name in @('account', 'collection', 'debit')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$state.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed unexpectedly: expected=$expected actual=$actual"
        }
    }
    foreach ($name in @('payment', 'expenditure')) {
        $expected = [int]$setup.baseline.$name + 1
        $actual = [int]$state.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count did not increase by one: expected=$expected actual=$actual"
        }
    }
    $expectedStatements = [int]$setup.baseline.statement + 2
    $actualStatements = [int]$state.counts.statement
    if ($actualStatements -ne $expectedStatements) {
        throw "statement row count did not increase by two: expected=$expectedStatements actual=$actualStatements"
    }

    Write-Host 'settlement account transfer-add HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
