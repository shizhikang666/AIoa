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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-debit-note-batch-repayment-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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

$repaymentAccountId = 'SAC' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$debitSourceAccountId = 'SAC' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$sourceStatementId = 'STS' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$expenditureId = 'EXP' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$debitNoteId = 'BDN' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$missingAccountId = 'SAC' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$missingDebitNoteId = 'BDN' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$prefix = 'codex-bdnrep-' + ([Guid]::NewGuid().ToString('N').Substring(0, 8))
$payerTime = '2026-05-10 11:12:13'
$sourceTime = '2026-05-09 10:00:00'
$category = 'LoanRepayment'
$sourceCategory = 'SMOKE/DEBIT'

$safeAccount = $account.Replace("'", "\'")
$safeRepaymentAccountId = $repaymentAccountId.Replace("'", "\'")
$safeDebitSourceAccountId = $debitSourceAccountId.Replace("'", "\'")
$safeSourceStatementId = $sourceStatementId.Replace("'", "\'")
$safeExpenditureId = $expenditureId.Replace("'", "\'")
$safeDebitNoteId = $debitNoteId.Replace("'", "\'")
$safePrefix = $prefix.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$accountIds = ['$safeRepaymentAccountId', '$safeDebitSourceAccountId'];
think\facade\Db::name('biz_payment_record')->where('OBJECT_ID', '$safeDebitNoteId')->delete();
think\facade\Db::name('biz_debit_note')->where('ID', '$safeDebitNoteId')->delete();
think\facade\Db::name('biz_expenditure_record')->where('ID', '$safeExpenditureId')->delete();
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
        'ID' => '$safeRepaymentAccountId',
        'ACCOUNT_NAME' => '$safePrefix-repayment',
        'ACCOUNT_NUMBER' => '$safePrefix-repayment-no',
        'INITIAL_AMOUNT' => '1000.00',
        'CURRENT_AMOUNT' => '1000.00',
        'SORT_CODE' => 988,
    ],
    [
        'ID' => '$safeDebitSourceAccountId',
        'ACCOUNT_NAME' => '$safePrefix-debit-source',
        'ACCOUNT_NUMBER' => '$safePrefix-debit-source-no',
        'INITIAL_AMOUNT' => '1000.00',
        'CURRENT_AMOUNT' => '950.00',
        'SORT_CODE' => 987,
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
    'ID' => '$safeSourceStatementId',
    'ACCOUNT_ID' => '$safeDebitSourceAccountId',
    'PROCESS_ID' => 'Process_sys',
    'BEFORE_AMOUNT' => '1000.00',
    'AMOUNT' => '50.00',
    'AFTER_AMOUNT' => '950.00',
    'SETTLEMENT_TYPE' => 'EXPEND',
    'SETTLEMENT_CATEGORY' => '$sourceCategory',
    'PROCESS_CATEGORY' => 'Process_sys',
    'PAYER_TIME' => '$sourceTime',
    'DELETE_FLAG' => 'NOT_DELETE',
]));
think\facade\Db::name('biz_expenditure_record')->insert(array_merge(`$audit, [
    'ID' => '$safeExpenditureId',
    'OBJECT_ID' => '$safeDebitNoteId',
    'TARGET_ID' => '$safeDebitSourceAccountId',
    'SERIAL_ID' => '$safeSourceStatementId',
    'PROCESS_ID' => 'Process_sys',
    'SETTLEMENT_CATEGORY' => '$sourceCategory',
    'PAYER' => 'codex smoke lender',
    'BANK_NAME' => 'codex smoke source bank',
    'BANK_ACCOUNT' => 'codex smoke source account',
    'REMARK' => '$safePrefix-expenditure',
    'PAYER_TIME' => '$sourceTime',
    'AMOUNT' => '50.00',
    'DELETE_FLAG' => 'NOT_DELETE',
    'USER' => `$userId,
    'ORG' => `$orgId,
]));
think\facade\Db::name('biz_debit_note')->insert([
    'ID' => '$safeDebitNoteId',
    'EXPENDITURE_RECORD_ID' => '$safeExpenditureId',
    'REMARK' => '$safePrefix-debit-note',
    'PLAY_STATUS' => 'Unsettled',
    'AMOUNT' => '50.00',
    'SETTLEMENT_AMOUNT' => '20.00',
    'HISTORY_AMOUNT' => '20.00',
    'ORG' => `$orgId,
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => `$now,
    'UPDATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'VERSION' => 0,
]);
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_DEBIT_NOTE_BATCH_REPAYMENT_HTTP_SMOKE';
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
    throw 'failed to set up debit-note batch-repayment smoke data'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

try {
    $validPayload = @{
        accountId = $repaymentAccountId
        payer = 'codex smoke repayment payer'
        payerTime = $payerTime
        remark = "$prefix-valid"
        items = @(
            @{
                id = $debitNoteId
                amount = 30.00
            }
        )
    }

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/bizdebitnote/batchRepayment/edit" -Data $validPayload
    Assert-Code -Json $noToken -Expected 401 -Name 'debit note batch repayment without token'

    $missing = Invoke-RawPostJson -Url "$baseUrl/biz/bizdebitnote/batchRepayment/edit" -Token $token -Data @{
        payer = 'codex smoke repayment payer'
        payerTime = $payerTime
        items = @(@{ id = $debitNoteId; amount = 30.00 })
    }
    Assert-Code -Json $missing -Expected 400 -Name 'debit note batch repayment missing accountId'

    $emptyItems = Invoke-RawPostJson -Url "$baseUrl/biz/bizdebitnote/batchRepayment/edit" -Token $token -Data @{
        accountId = $repaymentAccountId
        payer = 'codex smoke repayment payer'
        payerTime = $payerTime
        items = @()
    }
    Assert-Code -Json $emptyItems -Expected 400 -Name 'debit note batch repayment empty items'

    $zero = Invoke-RawPostJson -Url "$baseUrl/biz/bizdebitnote/batchRepayment/edit" -Token $token -Data @{
        accountId = $repaymentAccountId
        payer = 'codex smoke repayment payer'
        payerTime = $payerTime
        items = @(@{ id = $debitNoteId; amount = 0 })
    }
    Assert-Code -Json $zero -Expected 400 -Name 'debit note batch repayment zero amount'

    $over = Invoke-RawPostJson -Url "$baseUrl/biz/bizdebitnote/batchRepayment/edit" -Token $token -Data @{
        accountId = $repaymentAccountId
        payer = 'codex smoke repayment payer'
        payerTime = $payerTime
        items = @(@{ id = $debitNoteId; amount = 31.00 })
    }
    Assert-Code -Json $over -Expected 400 -Name 'debit note batch repayment over amount'

    $missingNote = Invoke-RawPostJson -Url "$baseUrl/biz/bizdebitnote/batchRepayment/edit" -Token $token -Data @{
        accountId = $repaymentAccountId
        payer = 'codex smoke repayment payer'
        payerTime = $payerTime
        items = @(@{ id = $missingDebitNoteId; amount = 30.00 })
    }
    Assert-Code -Json $missingNote -Expected 404 -Name 'debit note batch repayment missing note rollback'

    $missingAccount = Invoke-RawPostJson -Url "$baseUrl/biz/bizdebitnote/batchRepayment/edit" -Token $token -Data @{
        accountId = $missingAccountId
        payer = 'codex smoke repayment payer'
        payerTime = $payerTime
        items = @(@{ id = $debitNoteId; amount = 30.00 })
    }
    Assert-Code -Json $missingAccount -Expected 404 -Name 'debit note batch repayment missing account rollback'

    $beforeCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$repaymentAccount = think\facade\Db::name('settlement_account')->where('ID', '$safeRepaymentAccountId')->find();
`$debitSourceAccount = think\facade\Db::name('settlement_account')->where('ID', '$safeDebitSourceAccountId')->find();
`$note = think\facade\Db::name('biz_debit_note')->where('ID', '$safeDebitNoteId')->find();
echo json_encode([
    'repaymentAmount' => (string)(`$repaymentAccount['CURRENT_AMOUNT'] ?? ''),
    'debitSourceAmount' => (string)(`$debitSourceAccount['CURRENT_AMOUNT'] ?? ''),
    'settlementAmount' => (string)(`$note['SETTLEMENT_AMOUNT'] ?? ''),
    'playStatus' => (string)(`$note['PLAY_STATUS'] ?? ''),
    'version' => (int)(`$note['VERSION'] ?? -1),
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
    Assert-DecimalEqual -Actual ([string]$before.repaymentAmount) -Expected ([decimal]'1000.00') -Name 'failed cases repayment account amount preserved'
    Assert-DecimalEqual -Actual ([string]$before.debitSourceAmount) -Expected ([decimal]'950.00') -Name 'failed cases source account amount preserved'
    Assert-DecimalEqual -Actual ([string]$before.settlementAmount) -Expected ([decimal]'20.00') -Name 'failed cases debit note settlement amount preserved'
    Assert-Equal -Actual ([string]$before.playStatus) -Expected 'Unsettled' -Name 'failed cases debit note status preserved'
    Assert-Equal -Actual ([string]$before.version) -Expected '0' -Name 'failed cases debit note version preserved'
    foreach ($name in @('payment', 'statement', 'account', 'expenditure', 'collection', 'debit')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$before.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed after failed cases: expected=$expected actual=$actual"
        }
    }

    $add = Invoke-RawPostJson -Url "$baseUrl/biz/bizdebitnote/batchRepayment/edit" -Token $token -Data $validPayload
    Assert-Code -Json $add -Expected 200 -Name 'debit note batch repayment'
    Assert-PathEquals -Json $add -Path 'data.accountId' -Expected $repaymentAccountId -Name 'debit note batch repayment account id'
    Assert-PathEquals -Json $add -Path 'data.settlementCategory' -Expected $category -Name 'debit note batch repayment category'
    Assert-PathEquals -Json $add -Path 'data.payerTime' -Expected $payerTime -Name 'debit note batch repayment payerTime'
    Assert-PathEquals -Json $add -Path 'data.count' -Expected '1' -Name 'debit note batch repayment count'
    Assert-PathEquals -Json $add -Path 'data.items.0.id' -Expected $debitNoteId -Name 'debit note batch repayment item id'
    Assert-PathEquals -Json $add -Path 'data.items.0.amount' -Expected '30.00' -Name 'debit note batch repayment item amount'
    Assert-PathEquals -Json $add -Path 'data.items.0.settlementAmountBefore' -Expected '20.00' -Name 'debit note batch repayment item before'
    Assert-PathEquals -Json $add -Path 'data.items.0.settlementAmountAfter' -Expected '50.00' -Name 'debit note batch repayment item after'
    Assert-PathEquals -Json $add -Path 'data.items.0.playStatus' -Expected 'AlreadySettled' -Name 'debit note batch repayment item status'
    Assert-PathEquals -Json $add -Path 'data.items.0.accountCount' -Expected '1' -Name 'debit note batch repayment account count'
    Assert-PathEquals -Json $add -Path 'data.items.0.debitNoteCount' -Expected '1' -Name 'debit note batch repayment note count'

    $paymentId = Read-JsonPath -Json $add -Path 'data.items.0.paymentId'
    $statementId = Read-JsonPath -Json $add -Path 'data.items.0.statementId'
    if ($paymentId.Trim() -eq '' -or $statementId.Trim() -eq '') {
        throw 'debit note batch repayment did not return generated ids'
    }

    $noteDetail = Invoke-RawGet -Url "$baseUrl/biz/bizdebitnote/detail?id=$(Enc $debitNoteId)" -Token $token
    Assert-Code -Json $noteDetail -Expected 200 -Name 'debit note detail after batch repayment'
    Assert-PathEquals -Json $noteDetail -Path 'data.id' -Expected $debitNoteId -Name 'debit note detail id'
    Assert-PathEquals -Json $noteDetail -Path 'data.expenditureRecordId' -Expected $expenditureId -Name 'debit note detail expenditure id'
    Assert-PathEquals -Json $noteDetail -Path 'data.playStatus' -Expected 'AlreadySettled' -Name 'debit note detail status'
    Assert-PathEquals -Json $noteDetail -Path 'data.accountId' -Expected $debitSourceAccountId -Name 'debit note detail source account'
    Assert-PathEquals -Json $noteDetail -Path 'data.accountName' -Expected "$prefix-debit-source" -Name 'debit note detail source account name'
    Assert-DecimalEqual -Actual (Read-JsonPath -Json $noteDetail -Path 'data.amount') -Expected ([decimal]'50.00') -Name 'debit note detail amount'
    Assert-DecimalEqual -Actual (Read-JsonPath -Json $noteDetail -Path 'data.settlementAmount') -Expected ([decimal]'50.00') -Name 'debit note detail settlement amount'
    Assert-DecimalEqual -Actual (Read-JsonPath -Json $noteDetail -Path 'data.historyAmount') -Expected ([decimal]'20.00') -Name 'debit note detail history amount'

    $paymentDetail = Invoke-RawGet -Url "$baseUrl/biz/bizpaymentrecord/detail?id=$(Enc $paymentId)" -Token $token
    Assert-Code -Json $paymentDetail -Expected 200 -Name 'payment record detail after debit note batch repayment'
    Assert-PathEquals -Json $paymentDetail -Path 'data.id' -Expected $paymentId -Name 'payment detail id'
    Assert-PathEquals -Json $paymentDetail -Path 'data.objectId' -Expected $debitNoteId -Name 'payment detail objectId'
    Assert-PathEquals -Json $paymentDetail -Path 'data.targetId' -Expected $repaymentAccountId -Name 'payment detail targetId'
    Assert-PathEquals -Json $paymentDetail -Path 'data.serialId' -Expected $statementId -Name 'payment detail serialId'
    Assert-PathEquals -Json $paymentDetail -Path 'data.processId' -Expected 'Process_sys' -Name 'payment detail processId'
    Assert-PathEquals -Json $paymentDetail -Path 'data.settlementCategory' -Expected $category -Name 'payment detail settlementCategory'
    Assert-PathEquals -Json $paymentDetail -Path 'data.payerTime' -Expected $payerTime -Name 'payment detail payerTime'
    Assert-DecimalEqual -Actual (Read-JsonPath -Json $paymentDetail -Path 'data.amount') -Expected ([decimal]'30.00') -Name 'payment detail amount'
    Assert-PathEquals -Json $paymentDetail -Path 'data.org' -Expected $orgId -Name 'payment detail org'

    $safePaymentId = $paymentId.Replace("'", "\'")
    $safeStatementId = $statementId.Replace("'", "\'")
    $stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$repaymentAccount = think\facade\Db::name('settlement_account')->where('ID', '$safeRepaymentAccountId')->find();
`$debitSourceAccount = think\facade\Db::name('settlement_account')->where('ID', '$safeDebitSourceAccountId')->find();
`$note = think\facade\Db::name('biz_debit_note')->where('ID', '$safeDebitNoteId')->find();
`$payment = think\facade\Db::name('biz_payment_record')->where('ID', '$safePaymentId')->find();
`$statement = think\facade\Db::name('settlement_account_statement')->where('ID', '$safeStatementId')->find();
echo json_encode([
    'repaymentAccount' => [
        'amount' => (string)(`$repaymentAccount['CURRENT_AMOUNT'] ?? ''),
        'tenantId' => (string)(`$repaymentAccount['TENANT_ID'] ?? ''),
        'deleteFlag' => (string)(`$repaymentAccount['DELETE_FLAG'] ?? ''),
    ],
    'debitSourceAccount' => [
        'amount' => (string)(`$debitSourceAccount['CURRENT_AMOUNT'] ?? ''),
        'tenantId' => (string)(`$debitSourceAccount['TENANT_ID'] ?? ''),
        'deleteFlag' => (string)(`$debitSourceAccount['DELETE_FLAG'] ?? ''),
    ],
    'note' => [
        'expenditureRecordId' => (string)(`$note['EXPENDITURE_RECORD_ID'] ?? ''),
        'amount' => (string)(`$note['AMOUNT'] ?? ''),
        'settlementAmount' => (string)(`$note['SETTLEMENT_AMOUNT'] ?? ''),
        'historyAmount' => (string)(`$note['HISTORY_AMOUNT'] ?? ''),
        'playStatus' => (string)(`$note['PLAY_STATUS'] ?? ''),
        'tenantId' => (string)(`$note['TENANT_ID'] ?? ''),
        'org' => (string)(`$note['ORG'] ?? ''),
        'version' => (int)(`$note['VERSION'] ?? -1),
        'deleteFlag' => (string)(`$note['DELETE_FLAG'] ?? ''),
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
    Assert-DecimalEqual -Actual ([string]$state.repaymentAccount.amount) -Expected ([decimal]'1030.00') -Name 'repayment account amount increased by batch repayment amount'
    Assert-DecimalEqual -Actual ([string]$state.debitSourceAccount.amount) -Expected ([decimal]'950.00') -Name 'source debit account amount preserved'
    Assert-Equal -Actual ([string]$state.repaymentAccount.tenantId) -Expected $tenantId -Name 'repayment account tenant preserved'
    Assert-Equal -Actual ([string]$state.debitSourceAccount.tenantId) -Expected $tenantId -Name 'source account tenant preserved'
    Assert-Equal -Actual ([string]$state.repaymentAccount.deleteFlag) -Expected 'NOT_DELETE' -Name 'repayment account deleteFlag preserved'
    Assert-Equal -Actual ([string]$state.debitSourceAccount.deleteFlag) -Expected 'NOT_DELETE' -Name 'source account deleteFlag preserved'

    Assert-Equal -Actual ([string]$state.note.expenditureRecordId) -Expected $expenditureId -Name 'debit note expenditureRecordId'
    Assert-DecimalEqual -Actual ([string]$state.note.amount) -Expected ([decimal]'50.00') -Name 'debit note amount'
    Assert-DecimalEqual -Actual ([string]$state.note.settlementAmount) -Expected ([decimal]'50.00') -Name 'debit note settlementAmount'
    Assert-DecimalEqual -Actual ([string]$state.note.historyAmount) -Expected ([decimal]'20.00') -Name 'debit note historyAmount'
    Assert-Equal -Actual ([string]$state.note.playStatus) -Expected 'AlreadySettled' -Name 'debit note playStatus'
    Assert-Equal -Actual ([string]$state.note.tenantId) -Expected $tenantId -Name 'debit note tenantId'
    Assert-Equal -Actual ([string]$state.note.org) -Expected $orgId -Name 'debit note org'
    Assert-Equal -Actual ([string]$state.note.version) -Expected '1' -Name 'debit note version'
    Assert-Equal -Actual ([string]$state.note.deleteFlag) -Expected 'NOT_DELETE' -Name 'debit note deleteFlag'

    Assert-Equal -Actual ([string]$state.payment.objectId) -Expected $debitNoteId -Name 'payment objectId'
    Assert-Equal -Actual ([string]$state.payment.targetId) -Expected $repaymentAccountId -Name 'payment targetId'
    Assert-Equal -Actual ([string]$state.payment.serialId) -Expected $statementId -Name 'payment serialId'
    Assert-Equal -Actual ([string]$state.payment.processId) -Expected 'Process_sys' -Name 'payment processId'
    Assert-Equal -Actual ([string]$state.payment.settlementCategory) -Expected $category -Name 'payment settlementCategory'
    Assert-Equal -Actual ([string]$state.payment.payer) -Expected 'codex smoke repayment payer' -Name 'payment payer'
    Assert-Equal -Actual ([string]$state.payment.bankName) -Expected '' -Name 'payment bankName'
    Assert-Equal -Actual ([string]$state.payment.bankAccount) -Expected '' -Name 'payment bankAccount'
    Assert-Equal -Actual ([string]$state.payment.remark) -Expected "$prefix-valid" -Name 'payment remark'
    Assert-Equal -Actual ([string]$state.payment.payerTime) -Expected $payerTime -Name 'payment payerTime'
    Assert-DecimalEqual -Actual ([string]$state.payment.amount) -Expected ([decimal]'30.00') -Name 'payment amount'
    Assert-Equal -Actual ([string]$state.payment.tenantId) -Expected $tenantId -Name 'payment tenantId'
    Assert-Equal -Actual ([string]$state.payment.org) -Expected $orgId -Name 'payment org'
    Assert-Equal -Actual ([string]$state.payment.deleteFlag) -Expected 'NOT_DELETE' -Name 'payment deleteFlag'

    Assert-Equal -Actual ([string]$state.statement.accountId) -Expected $repaymentAccountId -Name 'statement accountId'
    Assert-Equal -Actual ([string]$state.statement.processId) -Expected 'Process_sys' -Name 'statement processId'
    Assert-DecimalEqual -Actual ([string]$state.statement.beforeAmount) -Expected ([decimal]'1000.00') -Name 'statement beforeAmount'
    Assert-DecimalEqual -Actual ([string]$state.statement.amount) -Expected ([decimal]'30.00') -Name 'statement amount'
    Assert-DecimalEqual -Actual ([string]$state.statement.afterAmount) -Expected ([decimal]'1030.00') -Name 'statement afterAmount'
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

    Write-Host 'debit note batch-repayment HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
