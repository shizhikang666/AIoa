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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-debit-note-history-add-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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
$missingAccountId = 'SAC' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$prefix = 'codex-bdnhist-' + ([Guid]::NewGuid().ToString('N').Substring(0, 8))
$createTime = '2026-05-11 12:13:14'

$safeAccount = $account.Replace("'", "\'")
$safeAccountId = $accountId.Replace("'", "\'")
$safePrefix = $prefix.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_payment_record')->where('TARGET_ID', '$safeAccountId')->delete();
think\facade\Db::name('biz_expenditure_record')->where('TARGET_ID', '$safeAccountId')->delete();
think\facade\Db::name('settlement_account_statement')->where('ACCOUNT_ID', '$safeAccountId')->delete();
think\facade\Db::name('biz_debit_note')->whereLike('REMARK', '$safePrefix%')->delete();
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
    'ACCOUNT_NAME' => '$safePrefix-account',
    'ACCOUNT_NUMBER' => '$safePrefix-account-no',
    'INITIAL_AMOUNT' => '1000.00',
    'CURRENT_AMOUNT' => '1000.00',
    'ACCOUNT_STATUS' => 'ENABLE',
    'SORT_CODE' => 986,
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
`$auth['device'] = 'CODEX_DEBIT_NOTE_HISTORY_ADD_HTTP_SMOKE';
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
    throw 'failed to set up debit-note history-add smoke data'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

try {
    $validPayload = @{
        accountId = $accountId
        amount = 50.00
        historyAmount = 20.00
        createTime = $createTime
        remark = "$prefix-valid"
    }

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/bizdebitnote/history/add" -Data $validPayload
    Assert-Code -Json $noToken -Expected 401 -Name 'debit note history add without token'

    $missing = Invoke-RawPostJson -Url "$baseUrl/biz/bizdebitnote/history/add" -Token $token -Data @{
        amount = 50.00
        historyAmount = 20.00
        createTime = $createTime
        remark = "$prefix-missing-account"
    }
    Assert-Code -Json $missing -Expected 400 -Name 'debit note history add missing accountId'

    $zero = Invoke-RawPostJson -Url "$baseUrl/biz/bizdebitnote/history/add" -Token $token -Data @{
        accountId = $accountId
        amount = 0
        historyAmount = 0
        createTime = $createTime
        remark = "$prefix-zero"
    }
    Assert-Code -Json $zero -Expected 400 -Name 'debit note history add zero amount'

    $negative = Invoke-RawPostJson -Url "$baseUrl/biz/bizdebitnote/history/add" -Token $token -Data @{
        accountId = $accountId
        amount = 50.00
        historyAmount = -1.00
        createTime = $createTime
        remark = "$prefix-negative"
    }
    Assert-Code -Json $negative -Expected 400 -Name 'debit note history add negative history amount'

    $over = Invoke-RawPostJson -Url "$baseUrl/biz/bizdebitnote/history/add" -Token $token -Data @{
        accountId = $accountId
        amount = 50.00
        historyAmount = 50.01
        createTime = $createTime
        remark = "$prefix-over"
    }
    Assert-Code -Json $over -Expected 400 -Name 'debit note history add over amount'

    $badTime = Invoke-RawPostJson -Url "$baseUrl/biz/bizdebitnote/history/add" -Token $token -Data @{
        accountId = $accountId
        amount = 50.00
        historyAmount = 20.00
        createTime = 'not-a-time'
        remark = "$prefix-bad-time"
    }
    Assert-Code -Json $badTime -Expected 400 -Name 'debit note history add invalid createTime'

    $missingAccount = Invoke-RawPostJson -Url "$baseUrl/biz/bizdebitnote/history/add" -Token $token -Data @{
        accountId = $missingAccountId
        amount = 50.00
        historyAmount = 20.00
        createTime = $createTime
        remark = "$prefix-missing-target"
    }
    Assert-Code -Json $missingAccount -Expected 404 -Name 'debit note history add missing settlement account'

    $beforeCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$account = think\facade\Db::name('settlement_account')->where('ID', '$safeAccountId')->find();
echo json_encode([
    'accountAmount' => (string)(`$account['CURRENT_AMOUNT'] ?? ''),
    'accountVersion' => (int)(`$account['VERSION'] ?? -1),
    'noteCount' => think\facade\Db::name('biz_debit_note')->whereLike('REMARK', '$safePrefix%')->count(),
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
    Assert-DecimalEqual -Actual ([string]$before.accountAmount) -Expected ([decimal]'1000.00') -Name 'failed cases account amount preserved'
    Assert-Equal -Actual ([string]$before.accountVersion) -Expected '0' -Name 'failed cases account version preserved'
    Assert-Equal -Actual ([string]$before.noteCount) -Expected '0' -Name 'failed cases no history note inserted'
    foreach ($name in @('payment', 'statement', 'account', 'expenditure', 'collection', 'debit')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$before.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed after failed cases: expected=$expected actual=$actual"
        }
    }

    $add = Invoke-RawPostJson -Url "$baseUrl/biz/bizdebitnote/history/add" -Token $token -Data $validPayload
    Assert-Code -Json $add -Expected 200 -Name 'debit note history add'
    Assert-PathEquals -Json $add -Path 'data.accountId' -Expected $accountId -Name 'debit note history add account id'
    Assert-PathEquals -Json $add -Path 'data.amount' -Expected '50.00' -Name 'debit note history add amount'
    Assert-PathEquals -Json $add -Path 'data.historyAmount' -Expected '20.00' -Name 'debit note history add history amount'
    Assert-PathEquals -Json $add -Path 'data.settlementAmount' -Expected '20.00' -Name 'debit note history add settlement amount'
    Assert-PathEquals -Json $add -Path 'data.playStatus' -Expected 'Unsettled' -Name 'debit note history add play status'
    Assert-PathEquals -Json $add -Path 'data.org' -Expected $orgId -Name 'debit note history add org'
    Assert-PathEquals -Json $add -Path 'data.tenantId' -Expected $tenantId -Name 'debit note history add tenant'
    Assert-PathEquals -Json $add -Path 'data.count' -Expected '1' -Name 'debit note history add count'

    $debitNoteId = Read-JsonPath -Json $add -Path 'data.id'
    if ($debitNoteId.Trim() -eq '') {
        throw 'debit note history add did not return generated id'
    }

    $noteDetail = Invoke-RawGet -Url "$baseUrl/biz/bizdebitnote/detail?id=$(Enc $debitNoteId)" -Token $token
    Assert-Code -Json $noteDetail -Expected 200 -Name 'debit note detail after history add'
    Assert-PathEquals -Json $noteDetail -Path 'data.id' -Expected $debitNoteId -Name 'debit note detail id'
    Assert-PathEquals -Json $noteDetail -Path 'data.playStatus' -Expected 'Unsettled' -Name 'debit note detail status'
    Assert-PathEquals -Json $noteDetail -Path 'data.createTime' -Expected $createTime -Name 'debit note detail createTime'
    Assert-PathEquals -Json $noteDetail -Path 'data.org' -Expected $orgId -Name 'debit note detail org'
    Assert-DecimalEqual -Actual (Read-JsonPath -Json $noteDetail -Path 'data.amount') -Expected ([decimal]'50.00') -Name 'debit note detail amount'
    Assert-DecimalEqual -Actual (Read-JsonPath -Json $noteDetail -Path 'data.settlementAmount') -Expected ([decimal]'20.00') -Name 'debit note detail settlement amount'
    Assert-DecimalEqual -Actual (Read-JsonPath -Json $noteDetail -Path 'data.historyAmount') -Expected ([decimal]'20.00') -Name 'debit note detail history amount'

    $safeDebitNoteId = $debitNoteId.Replace("'", "\'")
    $stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$account = think\facade\Db::name('settlement_account')->where('ID', '$safeAccountId')->find();
`$note = think\facade\Db::name('biz_debit_note')->where('ID', '$safeDebitNoteId')->find();
echo json_encode([
    'account' => [
        'amount' => (string)(`$account['CURRENT_AMOUNT'] ?? ''),
        'version' => (int)(`$account['VERSION'] ?? -1),
        'tenantId' => (string)(`$account['TENANT_ID'] ?? ''),
        'deleteFlag' => (string)(`$account['DELETE_FLAG'] ?? ''),
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
        'createTime' => (string)(`$note['CREATE_TIME'] ?? ''),
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
    Assert-DecimalEqual -Actual ([string]$state.account.amount) -Expected ([decimal]'1000.00') -Name 'account amount preserved after history add'
    Assert-Equal -Actual ([string]$state.account.version) -Expected '0' -Name 'account version preserved after history add'
    Assert-Equal -Actual ([string]$state.account.tenantId) -Expected $tenantId -Name 'account tenant preserved'
    Assert-Equal -Actual ([string]$state.account.deleteFlag) -Expected 'NOT_DELETE' -Name 'account deleteFlag preserved'

    Assert-Equal -Actual ([string]$state.note.expenditureRecordId) -Expected '' -Name 'history note has no expenditure record'
    Assert-DecimalEqual -Actual ([string]$state.note.amount) -Expected ([decimal]'50.00') -Name 'history note amount'
    Assert-DecimalEqual -Actual ([string]$state.note.settlementAmount) -Expected ([decimal]'20.00') -Name 'history note settlementAmount'
    Assert-DecimalEqual -Actual ([string]$state.note.historyAmount) -Expected ([decimal]'20.00') -Name 'history note historyAmount'
    Assert-Equal -Actual ([string]$state.note.playStatus) -Expected 'Unsettled' -Name 'history note playStatus'
    Assert-Equal -Actual ([string]$state.note.tenantId) -Expected $tenantId -Name 'history note tenantId'
    Assert-Equal -Actual ([string]$state.note.org) -Expected $orgId -Name 'history note org'
    Assert-Equal -Actual ([string]$state.note.version) -Expected '0' -Name 'history note version'
    Assert-Equal -Actual ([string]$state.note.deleteFlag) -Expected 'NOT_DELETE' -Name 'history note deleteFlag'
    Assert-Equal -Actual ([string]$state.note.createTime) -Expected $createTime -Name 'history note createTime'

    foreach ($name in @('payment', 'statement', 'account', 'expenditure', 'collection')) {
        $expected = [int]$setup.baseline.$name
        $actual = [int]$state.counts.$name
        if ($actual -ne $expected) {
            throw "$name row count changed unexpectedly: expected=$expected actual=$actual"
        }
    }
    $expectedDebit = [int]$setup.baseline.debit + 1
    $actualDebit = [int]$state.counts.debit
    if ($actualDebit -ne $expectedDebit) {
        throw "debit row count did not increase by one: expected=$expectedDebit actual=$actualDebit"
    }

    Write-Host 'debit note history-add HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
