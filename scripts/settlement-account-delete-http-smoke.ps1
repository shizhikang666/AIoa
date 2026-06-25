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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-settlement-delete-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
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

function Assert-Equal {
    param([string]$Actual, [string]$Expected, [string]$Name)

    if ($Actual -ne $Expected) {
        throw "$Name expected=$Expected actual=$Actual"
    }
}

function Enc {
    param([Parameter(Mandatory = $true)][string]$Value)

    return [System.Uri]::EscapeDataString($Value)
}

function Get-JsonProperty {
    param(
        [Parameter(Mandatory = $true)]$Object,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $property = $Object.PSObject.Properties[$Name]
    if ($null -eq $property) {
        throw "missing json property: $Name"
    }

    return [string]$property.Value
}

$envMap = Get-EnvMap -Path $EnvPath
$account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
if ($account -eq '') {
    throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env'
}

$deleteAccountId = 'SAD' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$rollbackAccountId = 'SAR' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$statementReferencedAccountId = 'SAS' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$objectReferencedAccountId = 'SAO' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$paymentTargetAccountId = 'SAT' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$missingAccountId = 'SAM' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$statementId = 'STS' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$paymentId = 'PAY' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$serialId = 'SER' + ([Guid]::NewGuid().ToString('N').Substring(0, 17))
$prefix = 'codex-del-' + ([Guid]::NewGuid().ToString('N').Substring(0, 8))

$safeAccount = $account.Replace("'", "\'")
$allAccountIds = @($deleteAccountId, $rollbackAccountId, $statementReferencedAccountId, $objectReferencedAccountId, $paymentTargetAccountId)
$safeIds = ($allAccountIds | ForEach-Object { "'" + $_.Replace("'", "\'") + "'" }) -join ', '
$safeDeleteAccountId = $deleteAccountId.Replace("'", "\'")
$safeRollbackAccountId = $rollbackAccountId.Replace("'", "\'")
$safeStatementReferencedAccountId = $statementReferencedAccountId.Replace("'", "\'")
$safeObjectReferencedAccountId = $objectReferencedAccountId.Replace("'", "\'")
$safePaymentTargetAccountId = $paymentTargetAccountId.Replace("'", "\'")
$safeStatementId = $statementId.Replace("'", "\'")
$safePaymentId = $paymentId.Replace("'", "\'")
$safeSerialId = $serialId.Replace("'", "\'")
$safePrefix = $prefix.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$ids = [$safeIds];
think\facade\Db::name('biz_payment_record')
    ->whereIn('TARGET_ID', `$ids)
    ->whereOr('OBJECT_ID', 'in', `$ids)
    ->delete();
think\facade\Db::name('biz_expenditure_record')
    ->whereIn('TARGET_ID', `$ids)
    ->whereOr('OBJECT_ID', 'in', `$ids)
    ->delete();
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
`$accounts = [
    ['$safeDeleteAccountId', '$safePrefix-delete', '100.00', 987],
    ['$safeRollbackAccountId', '$safePrefix-rollback', '200.00', 986],
    ['$safeStatementReferencedAccountId', '$safePrefix-statement-ref', '300.00', 985],
    ['$safeObjectReferencedAccountId', '$safePrefix-object-ref', '400.00', 984],
    ['$safePaymentTargetAccountId', '$safePrefix-payment-target', '500.00', 983],
];
foreach (`$accounts as `$item) {
    think\facade\Db::name('settlement_account')->insert([
        'ID' => `$item[0],
        'ACCOUNT_NAME' => `$item[1],
        'ACCOUNT_NUMBER' => `$item[1] . '-no',
        'INITIAL_AMOUNT' => `$item[2],
        'CURRENT_AMOUNT' => `$item[2],
        'ACCOUNT_STATUS' => 'ENABLE',
        'SORT_CODE' => `$item[3],
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => `$userId,
        'UPDATE_TIME' => `$now,
        'UPDATE_USER' => `$userId,
        'TENANT_ID' => `$tenantId,
        'VERSION' => 0,
        'org' => `$orgId,
    ]);
}
think\facade\Db::name('settlement_account_statement')->insert([
    'ID' => '$safeStatementId',
    'ACCOUNT_ID' => '$safeStatementReferencedAccountId',
    'PROCESS_ID' => 'Process_sys',
    'AFTER_AMOUNT' => '301.00',
    'BEFORE_AMOUNT' => '300.00',
    'AMOUNT' => '1.00',
    'SETTLEMENT_TYPE' => 'INCOME',
    'SETTLEMENT_CATEGORY' => 'SMOKE/DELETE-REF',
    'PROCESS_CATEGORY' => 'Process_sys',
    'PAYER_TIME' => `$now,
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => `$now,
    'UPDATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
]);
think\facade\Db::name('biz_payment_record')->insert([
    'ID' => '$safePaymentId',
    'OBJECT_ID' => '$safeObjectReferencedAccountId',
    'TARGET_ID' => '$safePaymentTargetAccountId',
    'SERIAL_ID' => '$safeSerialId',
    'PROCESS_ID' => 'Process_sys',
    'SETTLEMENT_CATEGORY' => 'SMOKE/DELETE-OBJECT-REF',
    'PAYER' => 'codex settlement delete smoke',
    'BANK_NAME' => null,
    'BANK_ACCOUNT' => null,
    'REMARK' => '$safePrefix-object-ref',
    'PAYER_TIME' => `$now,
    'AMOUNT' => '1.00',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => `$userId,
    'UPDATE_TIME' => `$now,
    'UPDATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'USER' => `$userId,
    'ORG' => `$orgId,
]);
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_SETTLEMENT_ACCOUNT_DELETE_HTTP_SMOKE';
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
if ($token.Trim() -eq '') {
    throw 'failed to set up settlement-account delete smoke data'
}

$stateCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$ids = [$safeIds];
`$flags = [];
foreach (`$ids as `$id) {
    `$row = think\facade\Db::name('settlement_account')->where('ID', `$id)->field('DELETE_FLAG')->find();
    `$flags[`$id] = (string)(`$row['DELETE_FLAG'] ?? '');
}
echo json_encode([
    'flags' => `$flags,
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

$baseUrl = $BackendBaseUrl.TrimEnd('/')

try {
    $validDelete = @(@{ id = $deleteAccountId })

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/delete" -Data $validDelete
    Assert-Code -Json $noToken -Expected 401 -Name 'settlement account delete without token'

    $empty = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/delete" -Token $token -Data @{}
    Assert-Code -Json $empty -Expected 400 -Name 'settlement account delete missing id'

    $missing = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/delete" -Token $token -Data @(@{ id = $missingAccountId })
    Assert-Code -Json $missing -Expected 404 -Name 'settlement account delete missing account'

    $statementReferenced = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/delete" -Token $token -Data @(@{ id = $statementReferencedAccountId })
    Assert-Code -Json $statementReferenced -Expected 400 -Name 'settlement account delete statement referenced account'

    $objectReferenced = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/delete" -Token $token -Data @(@{ id = $objectReferencedAccountId })
    Assert-Code -Json $objectReferenced -Expected 400 -Name 'settlement account delete object referenced account'

    $mixed = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/delete" -Token $token -Data @(
        @{ id = $rollbackAccountId },
        @{ id = $missingAccountId }
    )
    Assert-Code -Json $mixed -Expected 404 -Name 'settlement account delete mixed rollback'

    $afterFailed = Invoke-PhpJson -Code $stateCode
    foreach ($id in $allAccountIds) {
        Assert-Equal -Actual (Get-JsonProperty -Object $afterFailed.flags -Name $id) -Expected 'NOT_DELETE' -Name "failed delete preserves $id"
    }
    foreach ($name in @('payment', 'statement', 'account', 'expenditure', 'collection', 'debit')) {
        $expected = [int](Get-JsonProperty -Object $setup.baseline -Name $name)
        $actual = [int](Get-JsonProperty -Object $afterFailed.counts -Name $name)
        if ($actual -ne $expected) {
            throw "$name row count changed after failed delete cases: expected=$expected actual=$actual"
        }
    }

    $delete = Invoke-RawPostJson -Url "$baseUrl/biz/settlementaccount/delete" -Token $token -Data $validDelete
    Assert-Code -Json $delete -Expected 200 -Name 'settlement account delete valid account'
    Assert-PathEquals -Json $delete -Path 'data.count' -Expected '1' -Name 'settlement account delete count'
    Assert-PathEquals -Json $delete -Path 'data.ids.0' -Expected $deleteAccountId -Name 'settlement account delete id'

    $deletedDetail = Invoke-RawGet -Url "$baseUrl/biz/settlementaccount/detail?id=$(Enc $deleteAccountId)" -Token $token
    Assert-Code -Json $deletedDetail -Expected 404 -Name 'settlement account detail after delete'

    $afterSuccess = Invoke-PhpJson -Code $stateCode
    Assert-Equal -Actual (Get-JsonProperty -Object $afterSuccess.flags -Name $deleteAccountId) -Expected 'DELETED' -Name 'valid delete flag'
    Assert-Equal -Actual (Get-JsonProperty -Object $afterSuccess.flags -Name $rollbackAccountId) -Expected 'NOT_DELETE' -Name 'rollback account remains active'
    Assert-Equal -Actual (Get-JsonProperty -Object $afterSuccess.flags -Name $statementReferencedAccountId) -Expected 'NOT_DELETE' -Name 'statement referenced account remains active'
    Assert-Equal -Actual (Get-JsonProperty -Object $afterSuccess.flags -Name $objectReferencedAccountId) -Expected 'NOT_DELETE' -Name 'object referenced account remains active'

    foreach ($name in @('payment', 'statement', 'account', 'expenditure', 'collection', 'debit')) {
        $expected = [int](Get-JsonProperty -Object $setup.baseline -Name $name)
        $actual = [int](Get-JsonProperty -Object $afterSuccess.counts -Name $name)
        if ($actual -ne $expected) {
            throw "$name row count changed after valid logical delete: expected=$expected actual=$actual"
        }
    }

    Write-Host 'settlement account delete HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
