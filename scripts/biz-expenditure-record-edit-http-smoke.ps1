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
    param([hashtable]$EnvMap, [string]$Key)

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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-expenditure-record-edit-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
    param([string]$Json, [string]$Path)

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

$recordId = 'BER' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$rollbackRecordId = 'BER' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$objectRecordId = 'BER' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$statementId = 'SAS' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$missingStatementId = 'SAS' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$objectId = 'OBJ' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$prefix = 'codex-expenditure-record-edit-' + ([Guid]::NewGuid().ToString('N').Substring(0, 12))
$oldTime = '2026-01-02 03:04:05'
$newTime = '2026-02-03 04:05:06'
$rollbackNewTime = '2026-03-04 05:06:07'

$safeAccount = $account.Replace("'", "\'")
$safeRecordId = $recordId.Replace("'", "\'")
$safeRollbackRecordId = $rollbackRecordId.Replace("'", "\'")
$safeObjectRecordId = $objectRecordId.Replace("'", "\'")
$safeStatementId = $statementId.Replace("'", "\'")
$safeMissingStatementId = $missingStatementId.Replace("'", "\'")
$safeObjectId = $objectId.Replace("'", "\'")
$safePrefix = $prefix.Replace("'", "\'")
$safeOldTime = $oldTime.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_expenditure_record')->whereIn('ID', ['$safeRecordId', '$safeRollbackRecordId', '$safeObjectRecordId'])->delete();
think\facade\Db::name('biz_expenditure_record')->whereLike('REMARK', '$safePrefix%')->delete();
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
        'AFTER_AMOUNT' => '954.33',
        'BEFORE_AMOUNT' => '1000.00',
        'AMOUNT' => '45.67',
        'SETTLEMENT_TYPE' => 'EXPEND',
        'SETTLEMENT_CATEGORY' => 'WELFARE_EXPENSES',
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
            'objectId' => '',
            'statementId' => '$safeStatementId',
            'amount' => '45.67',
            'remark' => '$safePrefix-valid',
        ],
        [
            'id' => '$safeRollbackRecordId',
            'objectId' => '',
            'statementId' => '$safeMissingStatementId',
            'amount' => '56.78',
            'remark' => '$safePrefix-rollback',
        ],
        [
            'id' => '$safeObjectRecordId',
            'objectId' => '$safeObjectId',
            'statementId' => '$safeMissingStatementId',
            'amount' => '67.89',
            'remark' => '$safePrefix-object',
        ],
    ] as `$row) {
        think\facade\Db::name('biz_expenditure_record')->insert([
            'ID' => `$row['id'],
            'OBJECT_ID' => `$row['objectId'],
            'TARGET_ID' => `$accountId,
            'SERIAL_ID' => `$row['statementId'],
            'PROCESS_ID' => 'Process_sys',
            'SETTLEMENT_CATEGORY' => 'WELFARE_EXPENSES',
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
`$auth['device'] = 'CODEX_EXPENDITURE_RECORD_EDIT_HTTP_SMOKE';
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
if ($token.Trim() -eq '' -or $accountId.Trim() -eq '' -or $tenantId.Trim() -eq '') {
    throw 'failed to set up expenditure record edit smoke data'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

try {
    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/bizexpenditurerecord/edit" -Data @{
        id = $recordId
        payerTime = $newTime
    }
    Assert-Code -Json $noToken -Expected 401 -Name 'expenditure record edit without token'

    $missing = Invoke-RawPostJson -Url "$baseUrl/biz/bizexpenditurerecord/edit" -Token $token -Data @{
        payerTime = $newTime
    }
    Assert-Code -Json $missing -Expected 400 -Name 'expenditure record edit missing id'

    $edit = Invoke-RawPostJson -Url "$baseUrl/biz/bizexpenditurerecord/edit" -Token $token -Data @{
        id = $recordId
        payerTime = $newTime
        settlementCategory = 'EntertainmentExpenses'
        targetId = 'client-spoof-target'
        serialId = 'client-spoof-serial'
        objectId = 'client-spoof-object'
        processId = 'client-spoof-process'
        amount = 9999.99
        user = 'client-spoof-user'
        org = 'client-spoof-org'
    }
    Assert-Code -Json $edit -Expected 200 -Name 'expenditure record edit'
    Assert-PathEquals -Json $edit -Path 'data.id' -Expected $recordId -Name 'expenditure record edit id'
    Assert-PathEquals -Json $edit -Path 'data.statementId' -Expected $statementId -Name 'expenditure record edit statement id'

    $detail = Invoke-RawGet -Url "$baseUrl/biz/bizexpenditurerecord/detail?id=$(Enc $recordId)" -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'expenditure record detail after edit'
    Assert-PathEquals -Json $detail -Path 'data.payerTime' -Expected $newTime -Name 'expenditure record detail payerTime'
    Assert-PathEquals -Json $detail -Path 'data.settlementCategory' -Expected 'EntertainmentExpenses' -Name 'expenditure record detail settlementCategory'

    $protectedCategory = Invoke-RawPostJson -Url "$baseUrl/biz/bizexpenditurerecord/edit" -Token $token -Data @{
        id = $recordId
        settlementCategory = 'TravelExpenses'
    }
    Assert-Code -Json $protectedCategory -Expected 400 -Name 'expenditure record protected target category'

    $objectGuard = Invoke-RawPostJson -Url "$baseUrl/biz/bizexpenditurerecord/edit" -Token $token -Data @{
        id = $objectRecordId
        payerTime = $newTime
        settlementCategory = 'EntertainmentExpenses'
    }
    Assert-Code -Json $objectGuard -Expected 400 -Name 'expenditure record linked object guard'

    $rollback = Invoke-RawPostJson -Url "$baseUrl/biz/bizexpenditurerecord/edit" -Token $token -Data @{
        id = $rollbackRecordId
        payerTime = $rollbackNewTime
        settlementCategory = 'EntertainmentExpenses'
    }
    Assert-Code -Json $rollback -Expected 404 -Name 'expenditure record edit missing statement rollback'

    $stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$record = think\facade\Db::name('biz_expenditure_record')->where('ID', '$safeRecordId')->find();
`$statement = think\facade\Db::name('settlement_account_statement')->where('ID', '$safeStatementId')->find();
`$rollback = think\facade\Db::name('biz_expenditure_record')->where('ID', '$safeRollbackRecordId')->find();
`$object = think\facade\Db::name('biz_expenditure_record')->where('ID', '$safeObjectRecordId')->find();
echo json_encode([
    'record' => [
        'objectId' => (string)(`$record['OBJECT_ID'] ?? ''),
        'targetId' => (string)(`$record['TARGET_ID'] ?? ''),
        'serialId' => (string)(`$record['SERIAL_ID'] ?? ''),
        'processId' => (string)(`$record['PROCESS_ID'] ?? ''),
        'settlementCategory' => (string)(`$record['SETTLEMENT_CATEGORY'] ?? ''),
        'payerTime' => (string)(`$record['PAYER_TIME'] ?? ''),
        'amount' => (string)(`$record['AMOUNT'] ?? ''),
        'tenantId' => (string)(`$record['TENANT_ID'] ?? ''),
        'user' => (string)(`$record['USER'] ?? ''),
        'org' => (string)(`$record['ORG'] ?? ''),
        'deleteFlag' => (string)(`$record['DELETE_FLAG'] ?? ''),
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
        'settlementCategory' => (string)(`$rollback['SETTLEMENT_CATEGORY'] ?? ''),
        'serialId' => (string)(`$rollback['SERIAL_ID'] ?? ''),
        'amount' => (string)(`$rollback['AMOUNT'] ?? ''),
    ],
    'object' => [
        'payerTime' => (string)(`$object['PAYER_TIME'] ?? ''),
        'settlementCategory' => (string)(`$object['SETTLEMENT_CATEGORY'] ?? ''),
        'objectId' => (string)(`$object['OBJECT_ID'] ?? ''),
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

    Assert-Equal -Actual ([string]$state.record.objectId) -Expected '' -Name 'expenditure objectId preserved'
    Assert-Equal -Actual ([string]$state.record.targetId) -Expected $accountId -Name 'expenditure targetId preserved'
    Assert-Equal -Actual ([string]$state.record.serialId) -Expected $statementId -Name 'expenditure serialId preserved'
    Assert-Equal -Actual ([string]$state.record.processId) -Expected 'Process_sys' -Name 'expenditure processId preserved'
    Assert-Equal -Actual ([string]$state.record.settlementCategory) -Expected 'EntertainmentExpenses' -Name 'expenditure category updated'
    Assert-Equal -Actual ([string]$state.record.payerTime) -Expected $newTime -Name 'expenditure payerTime updated'
    Assert-DecimalEqual -Actual ([string]$state.record.amount) -Expected ([decimal]'45.67') -Name 'expenditure amount preserved'
    Assert-Equal -Actual ([string]$state.record.tenantId) -Expected $tenantId -Name 'expenditure tenantId preserved'
    Assert-Equal -Actual ([string]$state.record.deleteFlag) -Expected 'NOT_DELETE' -Name 'expenditure deleteFlag preserved'

    Assert-Equal -Actual ([string]$state.statement.accountId) -Expected $accountId -Name 'statement accountId preserved'
    Assert-Equal -Actual ([string]$state.statement.processId) -Expected 'Process_sys' -Name 'statement processId preserved'
    Assert-DecimalEqual -Actual ([string]$state.statement.amount) -Expected ([decimal]'45.67') -Name 'statement amount preserved'
    Assert-Equal -Actual ([string]$state.statement.settlementType) -Expected 'EXPEND' -Name 'statement settlementType preserved'
    Assert-Equal -Actual ([string]$state.statement.settlementCategory) -Expected 'WELFARE_EXPENSES' -Name 'statement settlementCategory preserved'
    Assert-Equal -Actual ([string]$state.statement.processCategory) -Expected 'CODEX_SMOKE' -Name 'statement processCategory preserved'
    Assert-Equal -Actual ([string]$state.statement.payerTime) -Expected $newTime -Name 'statement payerTime synced'
    Assert-Equal -Actual ([string]$state.statement.tenantId) -Expected $tenantId -Name 'statement tenantId preserved'
    Assert-Equal -Actual ([string]$state.statement.deleteFlag) -Expected 'NOT_DELETE' -Name 'statement deleteFlag preserved'

    Assert-Equal -Actual ([string]$state.rollback.payerTime) -Expected $oldTime -Name 'rollback expenditure payerTime preserved'
    Assert-Equal -Actual ([string]$state.rollback.settlementCategory) -Expected 'WELFARE_EXPENSES' -Name 'rollback expenditure category preserved'
    Assert-Equal -Actual ([string]$state.rollback.serialId) -Expected $missingStatementId -Name 'rollback expenditure serialId preserved'
    Assert-DecimalEqual -Actual ([string]$state.rollback.amount) -Expected ([decimal]'56.78') -Name 'rollback expenditure amount preserved'

    Assert-Equal -Actual ([string]$state.object.payerTime) -Expected $oldTime -Name 'object-guard expenditure payerTime preserved'
    Assert-Equal -Actual ([string]$state.object.settlementCategory) -Expected 'WELFARE_EXPENSES' -Name 'object-guard expenditure category preserved'
    Assert-Equal -Actual ([string]$state.object.objectId) -Expected $objectId -Name 'object-guard expenditure objectId preserved'

    foreach ($name in @('payment', 'statement', 'account', 'expenditure', 'collection', 'debit')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$state.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed unexpectedly: expected=$expected actual=$actual"
        }
    }

    Write-Host 'biz expenditure record edit HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
