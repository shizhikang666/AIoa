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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-settlement-expenses-add-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
$prefix = 'codex-expadd-' + ([Guid]::NewGuid().ToString('N').Substring(0, 8))
$payerTime = '2026-05-07 08:09:10'
$category = 'SMOKE/EXPENSE'

$safeAccount = $account.Replace("'", "\'")
$safeAccountId = $accountId.Replace("'", "\'")
$safePrefix = $prefix.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_expenditure_record')->where('TARGET_ID', '$safeAccountId')->delete();
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
    'ACCOUNT_NAME' => '$safePrefix-expense',
    'ACCOUNT_NUMBER' => '$safePrefix-expense-no',
    'INITIAL_AMOUNT' => '1000.00',
    'CURRENT_AMOUNT' => '1000.00',
    'ACCOUNT_STATUS' => 'ENABLE',
    'SORT_CODE' => 994,
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
`$auth['device'] = 'CODEX_SETTLEMENT_ACCOUNT_EXPENSES_ADD_HTTP_SMOKE';
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
    throw 'failed to set up settlement-account expenses-add smoke data'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

try {
    $validPayload = @{
        objectId = $objectId
        targetId = $accountId
        settlementCategory = $category
        payer = 'codex smoke receiver'
        bankName = 'codex smoke expense bank'
        bankAccount = 'codex smoke expense account'
        remark = "$prefix-valid"
        payerTime = $payerTime
        amount = 12.34
    }

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/expenses/add" -Data $validPayload
    Assert-Code -Json $noToken -Expected 401 -Name 'settlement account expenses add without token'

    $missing = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/expenses/add" -Token $token -Data @{
        settlementCategory = $category
        payer = 'codex smoke receiver'
        payerTime = $payerTime
        amount = 12.34
    }
    Assert-Code -Json $missing -Expected 400 -Name 'settlement account expenses add missing targetId'

    $zero = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/expenses/add" -Token $token -Data @{
        targetId = $accountId
        settlementCategory = $category
        payer = 'codex smoke receiver'
        payerTime = $payerTime
        amount = 0
    }
    Assert-Code -Json $zero -Expected 400 -Name 'settlement account expenses add zero amount'

    $missingAccount = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/expenses/add" -Token $token -Data @{
        targetId = $missingAccountId
        settlementCategory = $category
        payer = 'codex smoke receiver'
        payerTime = $payerTime
        amount = 12.34
    }
    Assert-Code -Json $missingAccount -Expected 404 -Name 'settlement account expenses add missing account rollback'

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

    $add = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/expenses/add" -Token $token -Data $validPayload
    Assert-Code -Json $add -Expected 200 -Name 'settlement account expenses add'
    Assert-PathEquals -Json $add -Path 'data.accountId' -Expected $accountId -Name 'settlement account expenses add account id'
    Assert-PathEquals -Json $add -Path 'data.amount' -Expected '12.34' -Name 'settlement account expenses add amount'
    Assert-PathEquals -Json $add -Path 'data.beforeAmount' -Expected '1000.00' -Name 'settlement account expenses add before amount'
    Assert-PathEquals -Json $add -Path 'data.afterAmount' -Expected '987.66' -Name 'settlement account expenses add after amount'

    $expenditureId = Read-JsonPath -Json $add -Path 'data.id'
    $statementId = Read-JsonPath -Json $add -Path 'data.statementId'
    if ($expenditureId.Trim() -eq '' -or $statementId.Trim() -eq '') {
        throw 'settlement account expenses add did not return generated ids'
    }

    $detail = Invoke-RawGet -Url "$baseUrl/biz/bizexpenditurerecord/detail?id=$(Enc $expenditureId)" -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'expenditure record detail after settlement expenses add'
    Assert-PathEquals -Json $detail -Path 'data.id' -Expected $expenditureId -Name 'expenditure record detail id'
    Assert-PathEquals -Json $detail -Path 'data.targetId' -Expected $accountId -Name 'expenditure record detail targetId'
    Assert-PathEquals -Json $detail -Path 'data.serialId' -Expected $statementId -Name 'expenditure record detail serialId'
    Assert-PathEquals -Json $detail -Path 'data.processId' -Expected 'Process_sys' -Name 'expenditure record detail processId'
    Assert-PathEquals -Json $detail -Path 'data.settlementCategory' -Expected $category -Name 'expenditure record detail settlementCategory'
    Assert-PathEquals -Json $detail -Path 'data.payerTime' -Expected $payerTime -Name 'expenditure record detail payerTime'
    Assert-PathEquals -Json $detail -Path 'data.amount' -Expected '12.34' -Name 'expenditure record detail amount'
    Assert-PathEquals -Json $detail -Path 'data.org' -Expected $orgId -Name 'expenditure record detail org'

    $safeExpenditureId = $expenditureId.Replace("'", "\'")
    $safeStatementId = $statementId.Replace("'", "\'")
    $stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$account = think\facade\Db::name('settlement_account')->where('ID', '$safeAccountId')->find();
`$expenditure = think\facade\Db::name('biz_expenditure_record')->where('ID', '$safeExpenditureId')->find();
`$statement = think\facade\Db::name('settlement_account_statement')->where('ID', '$safeStatementId')->find();
echo json_encode([
    'account' => [
        'amount' => (string)(`$account['CURRENT_AMOUNT'] ?? ''),
        'tenantId' => (string)(`$account['TENANT_ID'] ?? ''),
        'deleteFlag' => (string)(`$account['DELETE_FLAG'] ?? ''),
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
        'user' => (string)(`$expenditure['USER'] ?? ''),
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
    Assert-DecimalEqual -Actual ([string]$state.account.amount) -Expected ([decimal]'987.66') -Name 'account amount decreased by expenditure amount'
    Assert-Equal -Actual ([string]$state.account.tenantId) -Expected $tenantId -Name 'account tenant preserved'
    Assert-Equal -Actual ([string]$state.account.deleteFlag) -Expected 'NOT_DELETE' -Name 'account deleteFlag preserved'

    Assert-Equal -Actual ([string]$state.expenditure.objectId) -Expected $objectId -Name 'expenditure objectId'
    Assert-Equal -Actual ([string]$state.expenditure.targetId) -Expected $accountId -Name 'expenditure targetId'
    Assert-Equal -Actual ([string]$state.expenditure.serialId) -Expected $statementId -Name 'expenditure serialId'
    Assert-Equal -Actual ([string]$state.expenditure.processId) -Expected 'Process_sys' -Name 'expenditure processId'
    Assert-Equal -Actual ([string]$state.expenditure.settlementCategory) -Expected $category -Name 'expenditure settlementCategory'
    Assert-Equal -Actual ([string]$state.expenditure.payer) -Expected 'codex smoke receiver' -Name 'expenditure payer'
    Assert-Equal -Actual ([string]$state.expenditure.bankName) -Expected 'codex smoke expense bank' -Name 'expenditure bankName'
    Assert-Equal -Actual ([string]$state.expenditure.bankAccount) -Expected 'codex smoke expense account' -Name 'expenditure bankAccount'
    Assert-Equal -Actual ([string]$state.expenditure.remark) -Expected "$prefix-valid" -Name 'expenditure remark'
    Assert-Equal -Actual ([string]$state.expenditure.payerTime) -Expected $payerTime -Name 'expenditure payerTime'
    Assert-DecimalEqual -Actual ([string]$state.expenditure.amount) -Expected ([decimal]'12.34') -Name 'expenditure amount'
    Assert-Equal -Actual ([string]$state.expenditure.tenantId) -Expected $tenantId -Name 'expenditure tenantId'
    Assert-Equal -Actual ([string]$state.expenditure.org) -Expected $orgId -Name 'expenditure org'
    Assert-Equal -Actual ([string]$state.expenditure.deleteFlag) -Expected 'NOT_DELETE' -Name 'expenditure deleteFlag'

    Assert-Equal -Actual ([string]$state.statement.accountId) -Expected $accountId -Name 'statement accountId'
    Assert-Equal -Actual ([string]$state.statement.processId) -Expected 'Process_sys' -Name 'statement processId'
    Assert-DecimalEqual -Actual ([string]$state.statement.beforeAmount) -Expected ([decimal]'1000.00') -Name 'statement beforeAmount'
    Assert-DecimalEqual -Actual ([string]$state.statement.amount) -Expected ([decimal]'12.34') -Name 'statement amount'
    Assert-DecimalEqual -Actual ([string]$state.statement.afterAmount) -Expected ([decimal]'987.66') -Name 'statement afterAmount'
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

    Write-Host 'settlement account expenses-add HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
