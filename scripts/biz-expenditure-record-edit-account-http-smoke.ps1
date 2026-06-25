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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-expenditure-record-edit-account-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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

$currentAccountId = 'SAC' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$targetAccountId = 'SAC' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$recordId = 'BER' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$rollbackRecordId = 'BER' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$statementId = 'SAS' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$missingStatementId = 'SAS' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$objectId = 'OBJ' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$rollbackObjectId = 'OBJ' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$prefix = 'codex-expacct-' + ([Guid]::NewGuid().ToString('N').Substring(0, 8))
$payerTime = '2026-04-06 07:08:09'

$safeAccount = $account.Replace("'", "\'")
$safeCurrentAccountId = $currentAccountId.Replace("'", "\'")
$safeTargetAccountId = $targetAccountId.Replace("'", "\'")
$safeRecordId = $recordId.Replace("'", "\'")
$safeRollbackRecordId = $rollbackRecordId.Replace("'", "\'")
$safeStatementId = $statementId.Replace("'", "\'")
$safeMissingStatementId = $missingStatementId.Replace("'", "\'")
$safeObjectId = $objectId.Replace("'", "\'")
$safeRollbackObjectId = $rollbackObjectId.Replace("'", "\'")
$safePrefix = $prefix.Replace("'", "\'")
$safePayerTime = $payerTime.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_expenditure_record')->whereIn('ID', ['$safeRecordId', '$safeRollbackRecordId'])->delete();
think\facade\Db::name('settlement_account_statement')->whereIn('ID', ['$safeStatementId', '$safeMissingStatementId'])->delete();
think\facade\Db::name('settlement_account')->whereIn('ID', ['$safeCurrentAccountId', '$safeTargetAccountId'])->delete();
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
`$orgRows = think\facade\Db::name('sys_org')
    ->where(function (`$query) {
        `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
    })
    ->field('ID')
    ->order('ID', 'asc')
    ->limit(2)
    ->select()
    ->toArray();
if (`$orgId === '' && isset(`$orgRows[0])) {
    `$orgId = (string)(`$orgRows[0]['ID'] ?? '');
}
`$targetOrgId = isset(`$orgRows[1]) ? (string)(`$orgRows[1]['ID'] ?? `$orgId) : `$orgId;
`$now = date('Y-m-d H:i:s');
think\facade\Db::transaction(function () use (`$tenantId, `$userId, `$orgId, `$targetOrgId, `$now) {
    think\facade\Db::name('settlement_account')->insert([
        'ID' => '$safeCurrentAccountId',
        'ACCOUNT_NAME' => '$safePrefix-current',
        'ACCOUNT_NUMBER' => '$safePrefix-current-no',
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
    think\facade\Db::name('settlement_account')->insert([
        'ID' => '$safeTargetAccountId',
        'ACCOUNT_NAME' => '$safePrefix-target',
        'ACCOUNT_NUMBER' => '$safePrefix-target-no',
        'INITIAL_AMOUNT' => '500.00',
        'CURRENT_AMOUNT' => '500.00',
        'ACCOUNT_STATUS' => 'ENABLE',
        'SORT_CODE' => 994,
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => `$userId,
        'UPDATE_TIME' => `$now,
        'UPDATE_USER' => `$userId,
        'TENANT_ID' => `$tenantId,
        'VERSION' => 0,
        'org' => `$targetOrgId,
    ]);
    think\facade\Db::name('settlement_account_statement')->insert([
        'ID' => '$safeStatementId',
        'ACCOUNT_ID' => '$safeCurrentAccountId',
        'PROCESS_ID' => 'Process_sys',
        'AFTER_AMOUNT' => '987.66',
        'BEFORE_AMOUNT' => '1000.00',
        'AMOUNT' => '12.34',
        'SETTLEMENT_TYPE' => 'EXPEND',
        'SETTLEMENT_CATEGORY' => 'OTHER',
        'PROCESS_CATEGORY' => 'CODEX_SMOKE',
        'PAYER_TIME' => '$safePayerTime',
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
            'remark' => '$safePrefix-valid',
        ],
        [
            'id' => '$safeRollbackRecordId',
            'objectId' => '$safeRollbackObjectId',
            'statementId' => '$safeMissingStatementId',
            'remark' => '$safePrefix-missing-statement',
        ],
    ] as `$row) {
        think\facade\Db::name('biz_expenditure_record')->insert([
            'ID' => `$row['id'],
            'OBJECT_ID' => `$row['objectId'],
            'TARGET_ID' => '$safeCurrentAccountId',
            'SERIAL_ID' => `$row['statementId'],
            'PROCESS_ID' => 'Process_sys',
            'SETTLEMENT_CATEGORY' => 'OTHER',
            'PAYER' => 'codex smoke',
            'BANK_NAME' => '$safePrefix-current',
            'BANK_ACCOUNT' => '$safePrefix-current-no',
            'REMARK' => `$row['remark'],
            'PAYER_TIME' => '$safePayerTime',
            'AMOUNT' => '12.34',
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
`$auth['device'] = 'CODEX_EXPENDITURE_RECORD_EDIT_ACCOUNT_HTTP_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => `$userId,
    'tenantId' => `$tenantId,
    'orgId' => `$orgId,
    'targetOrgId' => `$targetOrgId,
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
    throw 'failed to set up expenditure record edit-account smoke data'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

try {
    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/bizexpenditurerecord/edit/account" -Data @{
        id = $recordId
        currentTargetId = $currentAccountId
        targetId = $targetAccountId
    }
    Assert-Code -Json $noToken -Expected 401 -Name 'expenditure record edit-account without token'

    $missing = Invoke-RawPostJson -Url "$baseUrl/biz/bizexpenditurerecord/edit/account" -Token $token -Data @{
        id = $recordId
        currentTargetId = $currentAccountId
    }
    Assert-Code -Json $missing -Expected 400 -Name 'expenditure record edit-account missing targetId'

    $same = Invoke-RawPostJson -Url "$baseUrl/biz/bizexpenditurerecord/edit/account" -Token $token -Data @{
        id = $recordId
        currentTargetId = $currentAccountId
        targetId = $currentAccountId
    }
    Assert-Code -Json $same -Expected 400 -Name 'expenditure record edit-account same account'

    $mismatch = Invoke-RawPostJson -Url "$baseUrl/biz/bizexpenditurerecord/edit/account" -Token $token -Data @{
        id = $recordId
        currentTargetId = $targetAccountId
        targetId = $currentAccountId
    }
    Assert-Code -Json $mismatch -Expected 400 -Name 'expenditure record edit-account current mismatch'

    $missingStatement = Invoke-RawPostJson -Url "$baseUrl/biz/bizexpenditurerecord/edit/account" -Token $token -Data @{
        id = $rollbackRecordId
        currentTargetId = $currentAccountId
        targetId = $targetAccountId
    }
    Assert-Code -Json $missingStatement -Expected 404 -Name 'expenditure record edit-account missing statement rollback'

    $beforeCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$current = think\facade\Db::name('settlement_account')->where('ID', '$safeCurrentAccountId')->find();
`$target = think\facade\Db::name('settlement_account')->where('ID', '$safeTargetAccountId')->find();
`$record = think\facade\Db::name('biz_expenditure_record')->where('ID', '$safeRecordId')->find();
`$rollback = think\facade\Db::name('biz_expenditure_record')->where('ID', '$safeRollbackRecordId')->find();
`$statement = think\facade\Db::name('settlement_account_statement')->where('ID', '$safeStatementId')->find();
echo json_encode([
    'currentAmount' => (string)(`$current['CURRENT_AMOUNT'] ?? ''),
    'targetAmount' => (string)(`$target['CURRENT_AMOUNT'] ?? ''),
    'recordTargetId' => (string)(`$record['TARGET_ID'] ?? ''),
    'rollbackTargetId' => (string)(`$rollback['TARGET_ID'] ?? ''),
    'statementAccountId' => (string)(`$statement['ACCOUNT_ID'] ?? ''),
], JSON_UNESCAPED_SLASHES);
"@
    $before = Invoke-PhpJson -Code $beforeCode
    Assert-DecimalEqual -Actual ([string]$before.currentAmount) -Expected ([decimal]'1000.00') -Name 'failed cases current account amount preserved'
    Assert-DecimalEqual -Actual ([string]$before.targetAmount) -Expected ([decimal]'500.00') -Name 'failed cases target account amount preserved'
    Assert-Equal -Actual ([string]$before.recordTargetId) -Expected $currentAccountId -Name 'failed cases expenditure target preserved'
    Assert-Equal -Actual ([string]$before.rollbackTargetId) -Expected $currentAccountId -Name 'missing statement expenditure target preserved'
    Assert-Equal -Actual ([string]$before.statementAccountId) -Expected $currentAccountId -Name 'failed cases statement account preserved'

    $edit = Invoke-RawPostJson -Url "$baseUrl/biz/bizexpenditurerecord/edit/account" -Token $token -Data @{
        id = $recordId
        currentTargetId = $currentAccountId
        targetId = $targetAccountId
        amount = 9999.99
        serialId = 'client-spoof-serial'
        objectId = 'client-spoof-object'
        org = 'client-spoof-org'
    }
    Assert-Code -Json $edit -Expected 200 -Name 'expenditure record edit-account'
    Assert-PathEquals -Json $edit -Path 'data.id' -Expected $recordId -Name 'expenditure record edit-account id'
    Assert-PathEquals -Json $edit -Path 'data.statementId' -Expected $statementId -Name 'expenditure record edit-account statement id'
    Assert-PathEquals -Json $edit -Path 'data.currentTargetId' -Expected $currentAccountId -Name 'expenditure record edit-account current target id'
    Assert-PathEquals -Json $edit -Path 'data.targetId' -Expected $targetAccountId -Name 'expenditure record edit-account target id'
    Assert-PathEquals -Json $edit -Path 'data.amount' -Expected '12.34' -Name 'expenditure record edit-account stored amount'

    $detail = Invoke-RawGet -Url "$baseUrl/biz/bizexpenditurerecord/detail?id=$(Enc $recordId)" -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'expenditure record detail after account switch'
    Assert-PathEquals -Json $detail -Path 'data.targetId' -Expected $targetAccountId -Name 'expenditure record detail targetId'
    Assert-PathEquals -Json $detail -Path 'data.org' -Expected $orgId -Name 'expenditure record detail org preserved'

    $stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$current = think\facade\Db::name('settlement_account')->where('ID', '$safeCurrentAccountId')->find();
`$target = think\facade\Db::name('settlement_account')->where('ID', '$safeTargetAccountId')->find();
`$record = think\facade\Db::name('biz_expenditure_record')->where('ID', '$safeRecordId')->find();
`$statement = think\facade\Db::name('settlement_account_statement')->where('ID', '$safeStatementId')->find();
`$rollback = think\facade\Db::name('biz_expenditure_record')->where('ID', '$safeRollbackRecordId')->find();
echo json_encode([
    'currentAccount' => [
        'amount' => (string)(`$current['CURRENT_AMOUNT'] ?? ''),
        'tenantId' => (string)(`$current['TENANT_ID'] ?? ''),
        'deleteFlag' => (string)(`$current['DELETE_FLAG'] ?? ''),
    ],
    'targetAccount' => [
        'amount' => (string)(`$target['CURRENT_AMOUNT'] ?? ''),
        'tenantId' => (string)(`$target['TENANT_ID'] ?? ''),
        'deleteFlag' => (string)(`$target['DELETE_FLAG'] ?? ''),
    ],
    'record' => [
        'objectId' => (string)(`$record['OBJECT_ID'] ?? ''),
        'targetId' => (string)(`$record['TARGET_ID'] ?? ''),
        'serialId' => (string)(`$record['SERIAL_ID'] ?? ''),
        'processId' => (string)(`$record['PROCESS_ID'] ?? ''),
        'settlementCategory' => (string)(`$record['SETTLEMENT_CATEGORY'] ?? ''),
        'payerTime' => (string)(`$record['PAYER_TIME'] ?? ''),
        'amount' => (string)(`$record['AMOUNT'] ?? ''),
        'tenantId' => (string)(`$record['TENANT_ID'] ?? ''),
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
        'targetId' => (string)(`$rollback['TARGET_ID'] ?? ''),
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

    Assert-DecimalEqual -Actual ([string]$state.currentAccount.amount) -Expected ([decimal]'1012.34') -Name 'current account amount increased by stored expenditure amount'
    Assert-DecimalEqual -Actual ([string]$state.targetAccount.amount) -Expected ([decimal]'487.66') -Name 'target account amount decreased by stored expenditure amount'
    Assert-Equal -Actual ([string]$state.currentAccount.tenantId) -Expected $tenantId -Name 'current account tenant preserved'
    Assert-Equal -Actual ([string]$state.targetAccount.tenantId) -Expected $tenantId -Name 'target account tenant preserved'
    Assert-Equal -Actual ([string]$state.currentAccount.deleteFlag) -Expected 'NOT_DELETE' -Name 'current account deleteFlag preserved'
    Assert-Equal -Actual ([string]$state.targetAccount.deleteFlag) -Expected 'NOT_DELETE' -Name 'target account deleteFlag preserved'

    Assert-Equal -Actual ([string]$state.record.objectId) -Expected $objectId -Name 'expenditure objectId preserved'
    Assert-Equal -Actual ([string]$state.record.targetId) -Expected $targetAccountId -Name 'expenditure targetId switched'
    Assert-Equal -Actual ([string]$state.record.serialId) -Expected $statementId -Name 'expenditure serialId preserved'
    Assert-Equal -Actual ([string]$state.record.processId) -Expected 'Process_sys' -Name 'expenditure processId preserved'
    Assert-Equal -Actual ([string]$state.record.settlementCategory) -Expected 'OTHER' -Name 'expenditure settlementCategory preserved'
    Assert-Equal -Actual ([string]$state.record.payerTime) -Expected $payerTime -Name 'expenditure payerTime preserved'
    Assert-DecimalEqual -Actual ([string]$state.record.amount) -Expected ([decimal]'12.34') -Name 'expenditure amount preserved'
    Assert-Equal -Actual ([string]$state.record.tenantId) -Expected $tenantId -Name 'expenditure tenantId preserved'
    Assert-Equal -Actual ([string]$state.record.org) -Expected $orgId -Name 'expenditure org preserved'
    Assert-Equal -Actual ([string]$state.record.deleteFlag) -Expected 'NOT_DELETE' -Name 'expenditure deleteFlag preserved'

    Assert-Equal -Actual ([string]$state.statement.accountId) -Expected $targetAccountId -Name 'statement accountId switched'
    Assert-Equal -Actual ([string]$state.statement.processId) -Expected 'Process_sys' -Name 'statement processId preserved'
    Assert-DecimalEqual -Actual ([string]$state.statement.amount) -Expected ([decimal]'12.34') -Name 'statement amount preserved'
    Assert-Equal -Actual ([string]$state.statement.settlementType) -Expected 'EXPEND' -Name 'statement settlementType preserved'
    Assert-Equal -Actual ([string]$state.statement.settlementCategory) -Expected 'OTHER' -Name 'statement settlementCategory preserved'
    Assert-Equal -Actual ([string]$state.statement.processCategory) -Expected 'CODEX_SMOKE' -Name 'statement processCategory preserved'
    Assert-Equal -Actual ([string]$state.statement.payerTime) -Expected $payerTime -Name 'statement payerTime preserved'
    Assert-Equal -Actual ([string]$state.statement.tenantId) -Expected $tenantId -Name 'statement tenantId preserved'
    Assert-Equal -Actual ([string]$state.statement.deleteFlag) -Expected 'NOT_DELETE' -Name 'statement deleteFlag preserved'

    Assert-Equal -Actual ([string]$state.rollback.targetId) -Expected $currentAccountId -Name 'rollback expenditure targetId preserved'
    Assert-Equal -Actual ([string]$state.rollback.serialId) -Expected $missingStatementId -Name 'rollback expenditure serialId preserved'
    Assert-DecimalEqual -Actual ([string]$state.rollback.amount) -Expected ([decimal]'12.34') -Name 'rollback expenditure amount preserved'

    foreach ($name in @('payment', 'statement', 'account', 'expenditure', 'collection', 'debit')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$state.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed unexpectedly: expected=$expected actual=$actual"
        }
    }

    Write-Host 'biz expenditure record edit-account HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
