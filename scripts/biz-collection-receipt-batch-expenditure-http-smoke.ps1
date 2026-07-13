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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-collection-batch-expenditure-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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

$expenseAccountId = 'SAC' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$receiptAccountId = 'SAC' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$paymentStatementId = 'STS' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$paymentId = 'PAY' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$receiptId = 'BCR' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$missingAccountId = 'SAC' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$missingReceiptId = 'BCR' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$prefix = 'codex-bcrexp-' + ([Guid]::NewGuid().ToString('N').Substring(0, 8))
$payerTime = '2026-05-09 10:11:12'
$paymentTime = '2026-05-08 10:00:00'
$category = 'repayment'
$sourceCategory = 'SMOKE/COLLECTION'

$safeAccount = $account.Replace("'", "\'")
$safeExpenseAccountId = $expenseAccountId.Replace("'", "\'")
$safeReceiptAccountId = $receiptAccountId.Replace("'", "\'")
$safePaymentStatementId = $paymentStatementId.Replace("'", "\'")
$safePaymentId = $paymentId.Replace("'", "\'")
$safeReceiptId = $receiptId.Replace("'", "\'")
$safePrefix = $prefix.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$accountIds = ['$safeExpenseAccountId', '$safeReceiptAccountId'];
think\facade\Db::name('biz_expenditure_record')->where('OBJECT_ID', '$safeReceiptId')->delete();
think\facade\Db::name('biz_collection_receipt')->where('ID', '$safeReceiptId')->delete();
think\facade\Db::name('biz_payment_record')->where('ID', '$safePaymentId')->delete();
think\facade\Db::name('settlement_account_statement')->whereIn('ACCOUNT_ID', `$accountIds)->delete();
think\facade\Db::name('settlement_account')->whereIn('ID', `$accountIds)->delete();
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
        'ID' => '$safeExpenseAccountId',
        'ACCOUNT_NAME' => '$safePrefix-expense',
        'ACCOUNT_NUMBER' => '$safePrefix-expense-no',
        'INITIAL_AMOUNT' => '1000.00',
        'CURRENT_AMOUNT' => '1000.00',
        'SORT_CODE' => 990,
    ],
    [
        'ID' => '$safeReceiptAccountId',
        'ACCOUNT_NAME' => '$safePrefix-receipt',
        'ACCOUNT_NUMBER' => '$safePrefix-receipt-no',
        'INITIAL_AMOUNT' => '550.00',
        'CURRENT_AMOUNT' => '550.00',
        'SORT_CODE' => 989,
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
`$audit = [
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => `$now,
    'UPDATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
];
think\facade\Db::name('settlement_account_statement')->insert(array_merge(`$audit, [
    'ID' => '$safePaymentStatementId',
    'ACCOUNT_ID' => '$safeReceiptAccountId',
    'PROCESS_ID' => 'Process_sys',
    'BEFORE_AMOUNT' => '500.00',
    'AMOUNT' => '50.00',
    'AFTER_AMOUNT' => '550.00',
    'SETTLEMENT_TYPE' => 'INCOME',
    'SETTLEMENT_CATEGORY' => '$sourceCategory',
    'PROCESS_CATEGORY' => 'Process_sys',
    'PAYER_TIME' => '$paymentTime',
    'DELETE_FLAG' => 'NOT_DELETE',
]));
think\facade\Db::name('biz_payment_record')->insert(array_merge(`$audit, [
    'ID' => '$safePaymentId',
    'OBJECT_ID' => '$safeReceiptId',
    'TARGET_ID' => '$safeReceiptAccountId',
    'SERIAL_ID' => '$safePaymentStatementId',
    'PROCESS_ID' => 'Process_sys',
    'SETTLEMENT_CATEGORY' => '$sourceCategory',
    'PAYER' => 'codex smoke customer',
    'BANK_NAME' => 'codex smoke source bank',
    'BANK_ACCOUNT' => 'codex smoke source account',
    'REMARK' => '$safePrefix-payment',
    'PAYER_TIME' => '$paymentTime',
    'AMOUNT' => '50.00',
    'DELETE_FLAG' => 'NOT_DELETE',
    'USER' => `$userId,
    'ORG' => `$orgId,
]));
think\facade\Db::name('biz_collection_receipt')->insert([
    'ID' => '$safeReceiptId',
    'PAYMENT_RECORD_ID' => '$safePaymentId',
    'REMARK' => '$safePrefix-receipt',
    'PLAY_STATUS' => 'Unsettled',
    'AMOUNT' => '50.00',
    'SETTLEMENT_AMOUNT' => '20.00',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => `$now,
    'UPDATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 0,
]);
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_COLLECTION_RECEIPT_BATCH_EXPENDITURE_HTTP_SMOKE';
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
    throw 'failed to set up collection-receipt batch-expenditure smoke data'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

try {
    $validPayload = @{
        accountId = $expenseAccountId
        payer = 'codex smoke receiver'
        payerTime = $payerTime
        remark = "$prefix-valid"
        items = @(
            @{
                id = $receiptId
                amount = 30.00
            }
        )
    }

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/bizcollectionreceipt/batchExpenditure/edit" -Data $validPayload
    Assert-Code -Json $noToken -Expected 401 -Name 'collection receipt batch expenditure without token'

    $missing = Invoke-RawPostJson -Url "$baseUrl/biz/bizcollectionreceipt/batchExpenditure/edit" -Token $token -Data @{
        payer = 'codex smoke receiver'
        payerTime = $payerTime
        items = @(@{ id = $receiptId; amount = 30.00 })
    }
    Assert-Code -Json $missing -Expected 400 -Name 'collection receipt batch expenditure missing accountId'

    $emptyItems = Invoke-RawPostJson -Url "$baseUrl/biz/bizcollectionreceipt/batchExpenditure/edit" -Token $token -Data @{
        accountId = $expenseAccountId
        payer = 'codex smoke receiver'
        payerTime = $payerTime
        items = @()
    }
    Assert-Code -Json $emptyItems -Expected 400 -Name 'collection receipt batch expenditure empty items'

    $zero = Invoke-RawPostJson -Url "$baseUrl/biz/bizcollectionreceipt/batchExpenditure/edit" -Token $token -Data @{
        accountId = $expenseAccountId
        payer = 'codex smoke receiver'
        payerTime = $payerTime
        items = @(@{ id = $receiptId; amount = 0 })
    }
    Assert-Code -Json $zero -Expected 400 -Name 'collection receipt batch expenditure zero amount'

    $over = Invoke-RawPostJson -Url "$baseUrl/biz/bizcollectionreceipt/batchExpenditure/edit" -Token $token -Data @{
        accountId = $expenseAccountId
        payer = 'codex smoke receiver'
        payerTime = $payerTime
        items = @(@{ id = $receiptId; amount = 31.00 })
    }
    Assert-Code -Json $over -Expected 400 -Name 'collection receipt batch expenditure over amount'

    $missingReceipt = Invoke-RawPostJson -Url "$baseUrl/biz/bizcollectionreceipt/batchExpenditure/edit" -Token $token -Data @{
        accountId = $expenseAccountId
        payer = 'codex smoke receiver'
        payerTime = $payerTime
        items = @(@{ id = $missingReceiptId; amount = 30.00 })
    }
    Assert-Code -Json $missingReceipt -Expected 404 -Name 'collection receipt batch expenditure missing receipt rollback'

    $missingAccount = Invoke-RawPostJson -Url "$baseUrl/biz/bizcollectionreceipt/batchExpenditure/edit" -Token $token -Data @{
        accountId = $missingAccountId
        payer = 'codex smoke receiver'
        payerTime = $payerTime
        items = @(@{ id = $receiptId; amount = 30.00 })
    }
    Assert-Code -Json $missingAccount -Expected 404 -Name 'collection receipt batch expenditure missing account rollback'

    $beforeCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$expenseAccount = think\facade\Db::name('settlement_account')->where('ID', '$safeExpenseAccountId')->find();
`$receipt = think\facade\Db::name('biz_collection_receipt')->where('ID', '$safeReceiptId')->find();
echo json_encode([
    'expenseAmount' => (string)(`$expenseAccount['CURRENT_AMOUNT'] ?? ''),
    'settlementAmount' => (string)(`$receipt['SETTLEMENT_AMOUNT'] ?? ''),
    'playStatus' => (string)(`$receipt['PLAY_STATUS'] ?? ''),
    'version' => (int)(`$receipt['VERSION'] ?? -1),
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
    Assert-DecimalEqual -Actual ([string]$before.expenseAmount) -Expected ([decimal]'1000.00') -Name 'failed cases expense account amount preserved'
    Assert-DecimalEqual -Actual ([string]$before.settlementAmount) -Expected ([decimal]'20.00') -Name 'failed cases receipt settlement amount preserved'
    Assert-Equal -Actual ([string]$before.playStatus) -Expected 'Unsettled' -Name 'failed cases receipt status preserved'
    Assert-Equal -Actual ([string]$before.version) -Expected '0' -Name 'failed cases receipt version preserved'
    foreach ($name in @('payment', 'statement', 'account', 'expenditure', 'collection', 'debit')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$before.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed after failed cases: expected=$expected actual=$actual"
        }
    }

    $add = Invoke-RawPostJson -Url "$baseUrl/biz/bizcollectionreceipt/batchExpenditure/edit" -Token $token -Data $validPayload
    Assert-Code -Json $add -Expected 200 -Name 'collection receipt batch expenditure'
    Assert-PathEquals -Json $add -Path 'data.accountId' -Expected $expenseAccountId -Name 'collection receipt batch expenditure account id'
    Assert-PathEquals -Json $add -Path 'data.settlementCategory' -Expected $category -Name 'collection receipt batch expenditure category'
    Assert-PathEquals -Json $add -Path 'data.payerTime' -Expected $payerTime -Name 'collection receipt batch expenditure payerTime'
    Assert-PathEquals -Json $add -Path 'data.count' -Expected '1' -Name 'collection receipt batch expenditure count'
    Assert-PathEquals -Json $add -Path 'data.items.0.id' -Expected $receiptId -Name 'collection receipt batch expenditure item id'
    Assert-PathEquals -Json $add -Path 'data.items.0.amount' -Expected '30.00' -Name 'collection receipt batch expenditure item amount'
    Assert-PathEquals -Json $add -Path 'data.items.0.settlementAmountBefore' -Expected '20.00' -Name 'collection receipt batch expenditure item before'
    Assert-PathEquals -Json $add -Path 'data.items.0.settlementAmountAfter' -Expected '50.00' -Name 'collection receipt batch expenditure item after'
    Assert-PathEquals -Json $add -Path 'data.items.0.playStatus' -Expected 'AlreadySettled' -Name 'collection receipt batch expenditure item status'
    Assert-PathEquals -Json $add -Path 'data.items.0.accountCount' -Expected '1' -Name 'collection receipt batch expenditure account count'
    Assert-PathEquals -Json $add -Path 'data.items.0.receiptCount' -Expected '1' -Name 'collection receipt batch expenditure receipt count'

    $expenditureId = Read-JsonPath -Json $add -Path 'data.items.0.expenditureId'
    $statementId = Read-JsonPath -Json $add -Path 'data.items.0.statementId'
    if ($expenditureId.Trim() -eq '' -or $statementId.Trim() -eq '') {
        throw 'collection receipt batch expenditure did not return generated ids'
    }

    $receiptDetail = Invoke-RawGet -Url "$baseUrl/biz/bizcollectionreceipt/detail?id=$(Enc $receiptId)" -Token $token
    Assert-Code -Json $receiptDetail -Expected 200 -Name 'collection receipt detail after batch expenditure'
    Assert-PathEquals -Json $receiptDetail -Path 'data.id' -Expected $receiptId -Name 'collection receipt detail id'
    Assert-PathEquals -Json $receiptDetail -Path 'data.paymentRecordId' -Expected $paymentId -Name 'collection receipt detail payment id'
    Assert-PathEquals -Json $receiptDetail -Path 'data.playStatus' -Expected 'AlreadySettled' -Name 'collection receipt detail status'
    Assert-PathEquals -Json $receiptDetail -Path 'data.accountId' -Expected $receiptAccountId -Name 'collection receipt detail source account'
    Assert-PathEquals -Json $receiptDetail -Path 'data.accountName' -Expected "$prefix-receipt" -Name 'collection receipt detail source account name'
    Assert-DecimalEqual -Actual (Read-JsonPath -Json $receiptDetail -Path 'data.amount') -Expected ([decimal]'50.00') -Name 'collection receipt detail amount'
    Assert-DecimalEqual -Actual (Read-JsonPath -Json $receiptDetail -Path 'data.settlementAmount') -Expected ([decimal]'50.00') -Name 'collection receipt detail settlement amount'

    $expenditureDetail = Invoke-RawGet -Url "$baseUrl/biz/bizexpenditurerecord/detail?id=$(Enc $expenditureId)" -Token $token
    Assert-Code -Json $expenditureDetail -Expected 200 -Name 'expenditure record detail after collection batch expenditure'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.id' -Expected $expenditureId -Name 'expenditure detail id'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.objectId' -Expected $receiptId -Name 'expenditure detail objectId'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.targetId' -Expected $expenseAccountId -Name 'expenditure detail targetId'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.serialId' -Expected $statementId -Name 'expenditure detail serialId'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.processId' -Expected 'Process_sys' -Name 'expenditure detail processId'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.settlementCategory' -Expected $category -Name 'expenditure detail settlementCategory'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.payerTime' -Expected $payerTime -Name 'expenditure detail payerTime'
    Assert-DecimalEqual -Actual (Read-JsonPath -Json $expenditureDetail -Path 'data.amount') -Expected ([decimal]'30.00') -Name 'expenditure detail amount'
    Assert-PathEquals -Json $expenditureDetail -Path 'data.org' -Expected $orgId -Name 'expenditure detail org'

    $safeExpenditureId = $expenditureId.Replace("'", "\'")
    $safeStatementId = $statementId.Replace("'", "\'")
    $stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$expenseAccount = think\facade\Db::name('settlement_account')->where('ID', '$safeExpenseAccountId')->find();
`$receiptAccount = think\facade\Db::name('settlement_account')->where('ID', '$safeReceiptAccountId')->find();
`$receipt = think\facade\Db::name('biz_collection_receipt')->where('ID', '$safeReceiptId')->find();
`$expenditure = think\facade\Db::name('biz_expenditure_record')->where('ID', '$safeExpenditureId')->find();
`$statement = think\facade\Db::name('settlement_account_statement')->where('ID', '$safeStatementId')->find();
echo json_encode([
    'expenseAccount' => [
        'amount' => (string)(`$expenseAccount['CURRENT_AMOUNT'] ?? ''),
        'tenantId' => (string)(`$expenseAccount['TENANT_ID'] ?? ''),
        'deleteFlag' => (string)(`$expenseAccount['DELETE_FLAG'] ?? ''),
    ],
    'receiptAccount' => [
        'amount' => (string)(`$receiptAccount['CURRENT_AMOUNT'] ?? ''),
        'tenantId' => (string)(`$receiptAccount['TENANT_ID'] ?? ''),
        'deleteFlag' => (string)(`$receiptAccount['DELETE_FLAG'] ?? ''),
    ],
    'receipt' => [
        'paymentRecordId' => (string)(`$receipt['PAYMENT_RECORD_ID'] ?? ''),
        'amount' => (string)(`$receipt['AMOUNT'] ?? ''),
        'settlementAmount' => (string)(`$receipt['SETTLEMENT_AMOUNT'] ?? ''),
        'playStatus' => (string)(`$receipt['PLAY_STATUS'] ?? ''),
        'tenantId' => (string)(`$receipt['TENANT_ID'] ?? ''),
        'version' => (int)(`$receipt['VERSION'] ?? -1),
        'deleteFlag' => (string)(`$receipt['DELETE_FLAG'] ?? ''),
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
    Assert-DecimalEqual -Actual ([string]$state.expenseAccount.amount) -Expected ([decimal]'970.00') -Name 'expense account amount decreased by batch expenditure amount'
    Assert-DecimalEqual -Actual ([string]$state.receiptAccount.amount) -Expected ([decimal]'550.00') -Name 'source receipt account amount preserved'
    Assert-Equal -Actual ([string]$state.expenseAccount.tenantId) -Expected $tenantId -Name 'expense account tenant preserved'
    Assert-Equal -Actual ([string]$state.receiptAccount.tenantId) -Expected $tenantId -Name 'source account tenant preserved'
    Assert-Equal -Actual ([string]$state.expenseAccount.deleteFlag) -Expected 'NOT_DELETE' -Name 'expense account deleteFlag preserved'
    Assert-Equal -Actual ([string]$state.receiptAccount.deleteFlag) -Expected 'NOT_DELETE' -Name 'source account deleteFlag preserved'

    Assert-Equal -Actual ([string]$state.receipt.paymentRecordId) -Expected $paymentId -Name 'receipt paymentRecordId'
    Assert-DecimalEqual -Actual ([string]$state.receipt.amount) -Expected ([decimal]'50.00') -Name 'receipt amount'
    Assert-DecimalEqual -Actual ([string]$state.receipt.settlementAmount) -Expected ([decimal]'50.00') -Name 'receipt settlementAmount'
    Assert-Equal -Actual ([string]$state.receipt.playStatus) -Expected 'AlreadySettled' -Name 'receipt playStatus'
    Assert-Equal -Actual ([string]$state.receipt.tenantId) -Expected $tenantId -Name 'receipt tenantId'
    Assert-Equal -Actual ([string]$state.receipt.version) -Expected '1' -Name 'receipt version'
    Assert-Equal -Actual ([string]$state.receipt.deleteFlag) -Expected 'NOT_DELETE' -Name 'receipt deleteFlag'

    Assert-Equal -Actual ([string]$state.expenditure.objectId) -Expected $receiptId -Name 'expenditure objectId'
    Assert-Equal -Actual ([string]$state.expenditure.targetId) -Expected $expenseAccountId -Name 'expenditure targetId'
    Assert-Equal -Actual ([string]$state.expenditure.serialId) -Expected $statementId -Name 'expenditure serialId'
    Assert-Equal -Actual ([string]$state.expenditure.processId) -Expected 'Process_sys' -Name 'expenditure processId'
    Assert-Equal -Actual ([string]$state.expenditure.settlementCategory) -Expected $category -Name 'expenditure settlementCategory'
    Assert-Equal -Actual ([string]$state.expenditure.payer) -Expected 'codex smoke receiver' -Name 'expenditure payer'
    Assert-Equal -Actual ([string]$state.expenditure.bankName) -Expected '' -Name 'expenditure bankName'
    Assert-Equal -Actual ([string]$state.expenditure.bankAccount) -Expected '' -Name 'expenditure bankAccount'
    Assert-Equal -Actual ([string]$state.expenditure.remark) -Expected "$prefix-valid" -Name 'expenditure remark'
    Assert-Equal -Actual ([string]$state.expenditure.payerTime) -Expected $payerTime -Name 'expenditure payerTime'
    Assert-DecimalEqual -Actual ([string]$state.expenditure.amount) -Expected ([decimal]'30.00') -Name 'expenditure amount'
    Assert-Equal -Actual ([string]$state.expenditure.tenantId) -Expected $tenantId -Name 'expenditure tenantId'
    Assert-Equal -Actual ([string]$state.expenditure.org) -Expected $orgId -Name 'expenditure org'
    Assert-Equal -Actual ([string]$state.expenditure.deleteFlag) -Expected 'NOT_DELETE' -Name 'expenditure deleteFlag'

    Assert-Equal -Actual ([string]$state.statement.accountId) -Expected $expenseAccountId -Name 'statement accountId'
    Assert-Equal -Actual ([string]$state.statement.processId) -Expected 'Process_sys' -Name 'statement processId'
    Assert-DecimalEqual -Actual ([string]$state.statement.beforeAmount) -Expected ([decimal]'1000.00') -Name 'statement beforeAmount'
    Assert-DecimalEqual -Actual ([string]$state.statement.amount) -Expected ([decimal]'30.00') -Name 'statement amount'
    Assert-DecimalEqual -Actual ([string]$state.statement.afterAmount) -Expected ([decimal]'970.00') -Name 'statement afterAmount'
    Assert-Equal -Actual ([string]$state.statement.settlementType) -Expected 'EXPEND' -Name 'statement settlementType'
    Assert-Equal -Actual ([string]$state.statement.settlementCategory) -Expected $category -Name 'statement settlementCategory'
    Assert-Equal -Actual ([string]$state.statement.processCategory) -Expected 'Process_sys' -Name 'statement processCategory'
    Assert-Equal -Actual ([string]$state.statement.payerTime) -Expected $payerTime -Name 'statement payerTime'
    Assert-Equal -Actual ([string]$state.statement.tenantId) -Expected $tenantId -Name 'statement tenantId'
    Assert-Equal -Actual ([string]$state.statement.deleteFlag) -Expected 'NOT_DELETE' -Name 'statement deleteFlag'

    foreach ($name in @('payment', 'account', 'collection', 'debit')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$state.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed unexpectedly: expected=$expected actual=$actual"
        }
    }
    foreach ($name in @('expenditure', 'statement')) {
        $expected = [int]$setup.baseline.$name + 1
        $actual = [int]$state.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count did not increase by one: expected=$expected actual=$actual"
        }
    }

    Write-Host 'collection receipt batch-expenditure HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
