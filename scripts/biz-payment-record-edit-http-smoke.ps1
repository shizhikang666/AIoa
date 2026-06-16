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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-payment-record-edit-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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

$recordId = 'BPR' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$rollbackRecordId = 'BPR' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$statementId = 'SAS' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$missingStatementId = 'SAS' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$objectId = 'OBJ' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$rollbackObjectId = 'OBJ' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$prefix = 'codex-payment-record-edit-' + ([Guid]::NewGuid().ToString('N').Substring(0, 12))
$oldTime = '2026-01-02 03:04:05'
$newTime = '2026-02-03 04:05:06'
$rollbackNewTime = '2026-03-04 05:06:07'

$safeAccount = $account.Replace("'", "\'")
$safeRecordId = $recordId.Replace("'", "\'")
$safeRollbackRecordId = $rollbackRecordId.Replace("'", "\'")
$safeStatementId = $statementId.Replace("'", "\'")
$safeMissingStatementId = $missingStatementId.Replace("'", "\'")
$safeObjectId = $objectId.Replace("'", "\'")
$safeRollbackObjectId = $rollbackObjectId.Replace("'", "\'")
$safePrefix = $prefix.Replace("'", "\'")
$safeOldTime = $oldTime.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_payment_record')->whereIn('ID', ['$safeRecordId', '$safeRollbackRecordId'])->delete();
think\facade\Db::name('biz_payment_record')->whereLike('REMARK', '$safePrefix%')->delete();
think\facade\Db::name('settlement_account_statement')->whereIn('ID', ['$safeStatementId', '$safeMissingStatementId'])->delete();
think\facade\Db::name('settlement_account_statement')->whereLike('EXT_JSON', '%$safePrefix%')->delete();
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
`$accountQuery = think\facade\Db::name('settlement_account')
    ->where(function (`$query) {
        `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
    });
if (`$tenantId !== '') {
    `$accountQuery->where('TENANT_ID', `$tenantId);
}
`$account = `$accountQuery->field('ID,ACCOUNT_NAME,ACCOUNT_NUMBER,TENANT_ID,org')->order('ID', 'asc')->find();
if (!`$account) { throw new RuntimeException('active settlement account not found'); }
`$accountId = (string)`$account['ID'];
if (`$orgId === '') {
    `$orgId = (string)(`$account['org'] ?? '');
}
`$now = date('Y-m-d H:i:s');
think\facade\Db::transaction(function () use (`$tenantId, `$userId, `$orgId, `$account, `$accountId, `$now) {
    think\facade\Db::name('settlement_account_statement')->insert([
        'ID' => '$safeStatementId',
        'ACCOUNT_ID' => `$accountId,
        'PROCESS_ID' => 'Process_sys',
        'AFTER_AMOUNT' => '1012.34',
        'BEFORE_AMOUNT' => '1000.00',
        'AMOUNT' => '12.34',
        'SETTLEMENT_TYPE' => 'INCOME',
        'SETTLEMENT_CATEGORY' => 'OTHER',
        'PROCESS_CATEGORY' => 'CODEX_SMOKE',
        'PAYER_TIME' => '$safeOldTime',
        'CREATE_TIME' => `$now,
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_USER' => `$userId,
        'UPDATE_TIME' => `$now,
        'EXT_JSON' => json_encode(['source' => 'codex-smoke', 'prefix' => '$safePrefix'], JSON_UNESCAPED_SLASHES),
        'UPDATE_USER' => `$userId,
        'TENANT_ID' => `$tenantId,
    ]);
    foreach ([
        [
            'id' => '$safeRecordId',
            'objectId' => '$safeObjectId',
            'statementId' => '$safeStatementId',
            'amount' => '12.34',
            'remark' => '$safePrefix-valid',
        ],
        [
            'id' => '$safeRollbackRecordId',
            'objectId' => '$safeRollbackObjectId',
            'statementId' => '$safeMissingStatementId',
            'amount' => '23.45',
            'remark' => '$safePrefix-rollback',
        ],
    ] as `$row) {
        think\facade\Db::name('biz_payment_record')->insert([
            'ID' => `$row['id'],
            'OBJECT_ID' => `$row['objectId'],
            'TARGET_ID' => `$accountId,
            'SERIAL_ID' => `$row['statementId'],
            'PROCESS_ID' => 'Process_sys',
            'SETTLEMENT_CATEGORY' => 'OTHER',
            'PAYER' => 'codex smoke',
            'BANK_NAME' => (string)(`$account['ACCOUNT_NAME'] ?? ''),
            'BANK_ACCOUNT' => (string)(`$account['ACCOUNT_NUMBER'] ?? ''),
            'REMARK' => `$row['remark'],
            'PAYER_TIME' => '$safeOldTime',
            'AMOUNT' => `$row['amount'],
            'DELETE_FLAG' => 'NOT_DELETE',
            'CREATE_TIME' => `$now,
            'CREATE_USER' => `$userId,
            'UPDATE_TIME' => `$now,
            'UPDATE_USER' => `$userId,
            'TENANT_ID' => `$tenantId,
            'USER' => `$userId,
            'ORG' => `$orgId,
        ]);
    }
});
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_PAYMENT_RECORD_EDIT_HTTP_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => `$userId,
    'tenantId' => `$tenantId,
    'orgId' => `$orgId,
    'accountId' => `$accountId,
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
$accountId = [string]$setup.accountId
$tenantId = [string]$setup.tenantId
$orgId = [string]$setup.orgId
if ($token.Trim() -eq '' -or $accountId.Trim() -eq '' -or $tenantId.Trim() -eq '') {
    throw 'failed to set up payment record edit smoke data'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

try {
    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/bizpaymentrecord/edit" -Data @{
        id = $recordId
        payerTime = $newTime
    }
    Assert-Code -Json $noToken -Expected 401 -Name 'payment record edit without token'

    $missing = Invoke-RawPostJson -Url "$baseUrl/biz/bizpaymentrecord/edit" -Token $token -Data @{
        id = $recordId
    }
    Assert-Code -Json $missing -Expected 400 -Name 'payment record edit missing payerTime'

    $edit = Invoke-RawPostJson -Url "$baseUrl/biz/bizpaymentrecord/edit" -Token $token -Data @{
        id = $recordId
        payerTime = $newTime
        targetId = 'client-spoof-target'
        serialId = 'client-spoof-serial'
        objectId = 'client-spoof-object'
        processId = 'client-spoof-process'
        settlementCategory = 'client-spoof-category'
        amount = 9999.99
        user = 'client-spoof-user'
        org = 'client-spoof-org'
    }
    Assert-Code -Json $edit -Expected 200 -Name 'payment record edit'
    Assert-PathEquals -Json $edit -Path 'data.id' -Expected $recordId -Name 'payment record edit id'
    Assert-PathEquals -Json $edit -Path 'data.statementId' -Expected $statementId -Name 'payment record edit statement id'

    $detail = Invoke-RawGet -Url "$baseUrl/biz/bizpaymentrecord/detail?id=$(Enc $recordId)" -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'payment record detail after edit'
    Assert-PathEquals -Json $detail -Path 'data.payerTime' -Expected $newTime -Name 'payment record detail payerTime'

    $rollback = Invoke-RawPostJson -Url "$baseUrl/biz/bizpaymentrecord/edit" -Token $token -Data @{
        id = $rollbackRecordId
        payerTime = $rollbackNewTime
    }
    Assert-Code -Json $rollback -Expected 404 -Name 'payment record edit missing statement rollback'

    $stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$payment = think\facade\Db::name('biz_payment_record')->where('ID', '$safeRecordId')->find();
`$statement = think\facade\Db::name('settlement_account_statement')->where('ID', '$safeStatementId')->find();
`$rollback = think\facade\Db::name('biz_payment_record')->where('ID', '$safeRollbackRecordId')->find();
echo json_encode([
    'payment' => [
        'objectId' => (string)(`$payment['OBJECT_ID'] ?? ''),
        'targetId' => (string)(`$payment['TARGET_ID'] ?? ''),
        'serialId' => (string)(`$payment['SERIAL_ID'] ?? ''),
        'processId' => (string)(`$payment['PROCESS_ID'] ?? ''),
        'settlementCategory' => (string)(`$payment['SETTLEMENT_CATEGORY'] ?? ''),
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
        'amount' => (string)(`$statement['AMOUNT'] ?? ''),
        'settlementType' => (string)(`$statement['SETTLEMENT_TYPE'] ?? ''),
        'settlementCategory' => (string)(`$statement['SETTLEMENT_CATEGORY'] ?? ''),
        'processCategory' => (string)(`$statement['PROCESS_CATEGORY'] ?? ''),
        'payerTime' => (string)(`$statement['PAYER_TIME'] ?? ''),
        'tenantId' => (string)(`$statement['TENANT_ID'] ?? ''),
        'deleteFlag' => (string)(`$statement['DELETE_FLAG'] ?? ''),
    ],
    'rollback' => [
        'payerTime' => (string)(`$rollback['PAYER_TIME'] ?? ''),
        'serialId' => (string)(`$rollback['SERIAL_ID'] ?? ''),
        'amount' => (string)(`$rollback['AMOUNT'] ?? ''),
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

    Assert-Equal -Actual ([string]$state.payment.objectId) -Expected $objectId -Name 'payment objectId preserved'
    Assert-Equal -Actual ([string]$state.payment.targetId) -Expected $accountId -Name 'payment targetId preserved'
    Assert-Equal -Actual ([string]$state.payment.serialId) -Expected $statementId -Name 'payment serialId preserved'
    Assert-Equal -Actual ([string]$state.payment.processId) -Expected 'Process_sys' -Name 'payment processId preserved'
    Assert-Equal -Actual ([string]$state.payment.settlementCategory) -Expected 'OTHER' -Name 'payment settlementCategory preserved'
    Assert-Equal -Actual ([string]$state.payment.payerTime) -Expected $newTime -Name 'payment payerTime updated'
    Assert-DecimalEqual -Actual ([string]$state.payment.amount) -Expected ([decimal]'12.34') -Name 'payment amount preserved'
    Assert-Equal -Actual ([string]$state.payment.tenantId) -Expected $tenantId -Name 'payment tenantId preserved'
    Assert-Equal -Actual ([string]$state.payment.deleteFlag) -Expected 'NOT_DELETE' -Name 'payment deleteFlag preserved'

    Assert-Equal -Actual ([string]$state.statement.accountId) -Expected $accountId -Name 'statement accountId preserved'
    Assert-Equal -Actual ([string]$state.statement.processId) -Expected 'Process_sys' -Name 'statement processId preserved'
    Assert-DecimalEqual -Actual ([string]$state.statement.amount) -Expected ([decimal]'12.34') -Name 'statement amount preserved'
    Assert-Equal -Actual ([string]$state.statement.settlementType) -Expected 'INCOME' -Name 'statement settlementType preserved'
    Assert-Equal -Actual ([string]$state.statement.settlementCategory) -Expected 'OTHER' -Name 'statement settlementCategory preserved'
    Assert-Equal -Actual ([string]$state.statement.processCategory) -Expected 'CODEX_SMOKE' -Name 'statement processCategory preserved'
    Assert-Equal -Actual ([string]$state.statement.payerTime) -Expected $newTime -Name 'statement payerTime synced'
    Assert-Equal -Actual ([string]$state.statement.tenantId) -Expected $tenantId -Name 'statement tenantId preserved'
    Assert-Equal -Actual ([string]$state.statement.deleteFlag) -Expected 'NOT_DELETE' -Name 'statement deleteFlag preserved'

    Assert-Equal -Actual ([string]$state.rollback.payerTime) -Expected $oldTime -Name 'rollback payment payerTime preserved'
    Assert-Equal -Actual ([string]$state.rollback.serialId) -Expected $missingStatementId -Name 'rollback payment serialId preserved'
    Assert-DecimalEqual -Actual ([string]$state.rollback.amount) -Expected ([decimal]'23.45') -Name 'rollback payment amount preserved'

    foreach ($name in @('payment', 'statement', 'account', 'expenditure', 'collection', 'debit')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$state.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed unexpectedly: expected=$expected actual=$actual"
        }
    }

    Write-Host 'biz payment record edit HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
