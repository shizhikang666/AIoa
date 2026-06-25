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

function Invoke-RawPostJson {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)]$Data,
        [string]$Token = ''
    )

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-sale-project-deal-edit-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    try {
        ConvertTo-Json -InputObject $Data -Depth 10 | Set-Content -LiteralPath $tmp -Encoding UTF8
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

$envMap = Get-EnvMap -Path $EnvPath
$account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
if ($account -eq '') {
    throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env'
}

$safeAccount = $account.Replace("'", "\'")
$sessionCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_SALE_PROJECT_DEAL_EDIT_HTTP_SMOKE';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => (string)`$user['ID'],
    'tenantId' => (string)(`$user['TENANT_ID'] ?? ''),
    'orgId' => (string)(`$user['ORG_ID'] ?? '')
], JSON_UNESCAPED_SLASHES);
"@

$session = Invoke-PhpJson -Code $sessionCode
$token = [string]$session.token
$userId = [string]$session.userId
$tenantId = [string]$session.tenantId
$orgId = [string]$session.orgId
if ($token.Trim() -eq '' -or $userId.Trim() -eq '' -or $tenantId.Trim() -eq '') {
    throw 'failed to create local smoke auth token'
}
if ($orgId.Trim() -eq '') {
    $orgId = '0'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'CODEX_DEAL_EDIT_' + (Get-Date -Format 'MMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
$projectId = [string]([Int64]604500000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$missingId = [string]([Int64]604599000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$invoiceId = [string]([Int64]704500000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))

$safePrefix = $prefix.Replace("'", "\'")
$safeProjectId = $projectId.Replace("'", "\'")
$safeInvoiceId = $invoiceId.Replace("'", "\'")
$safeUserId = $userId.Replace("'", "\'")
$safeTenantId = $tenantId.Replace("'", "\'")
$safeOrgId = $orgId.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_sale_project_invoicing')->where('ID', '$safeInvoiceId')->delete();
think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->delete();
think\facade\Db::name('biz_sale_project')->whereLike('PROJECT_NAME', '$safePrefix%')->delete();
"@

Invoke-Php -Code $cleanupCode | Out-Null

try {
    $insertCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('biz_sale_project')->insert([
    'ID' => '$safeProjectId',
    'CUSTOMER' => '$safeProjectId',
    'PROJECT_NAME' => '$safePrefix project',
    'PROJECT_STATE' => 'SHIPPED',
    'PLAY_STATE' => 'PARTIALLY_PAID',
    'VISIBILITY' => 'PRIVATE',
    'INIT_PRICE' => '100.00',
    'TOTAL_PRICE' => '100.00',
    'AMOUNT_COLLECTED' => '20.00',
    'PROJECT_CATEGORY' => 'DEFAULT',
    'USER' => '$safeUserId',
    'ORG' => '$safeOrgId',
    'REMARK' => 'initial remark',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => '$safeUserId',
    'TENANT_ID' => '$safeTenantId',
    'VERSION' => 0,
    'CONSIGNEE' => 'initial consignee',
    'PHONE' => '13000000000',
    'UNIT' => 'initial unit',
    'ADDRESS' => 'initial address',
    'FREIGHT_CATEGORY' => 'initial-category',
    'FREIGHT' => '1.00',
    'DEAL_AMOUNT' => 0,
    'LOGISTICS_CATEGORY' => 'initial-logistics',
    'DELIVERY_NOTE' => 'initial delivery note',
    'HISTORY_AMOUNT' => '0.00',
    'TOTAL_RETURN_AMOUNT' => '0.00',
    'TOTAL_REFUND_AMOUNT' => '0.00',
]);
think\facade\Db::name('biz_sale_project_invoicing')->insert([
    'ID' => '$safeInvoiceId',
    'PROJECT_ID' => '$safeProjectId',
    'PROCESS_ID' => '$safeInvoiceId-process',
    'INVOICING_CATEGORY' => 'SPECIAL',
    'INVOICING_STATE' => 'WAIT',
    'AMOUNT' => '10.00',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => '$safeUserId',
    'TENANT_ID' => '$safeTenantId',
]);
"@
    Invoke-Php -Code $insertCode | Out-Null

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/deal/edit" -Data @{ id = $projectId; unit = 'no token' }
    Assert-Code -Json $noToken -Expected 401 -Name 'sale project deal edit without token'

    $missingIdResponse = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/deal/edit" -Token $token -Data @{ unit = 'missing id' }
    Assert-Code -Json $missingIdResponse -Expected 400 -Name 'sale project deal edit missing id'

    $missingRow = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/deal/edit" -Token $token -Data @{ id = $missingId; unit = 'missing row' }
    Assert-Code -Json $missingRow -Expected 404 -Name 'sale project deal edit missing row'

    $negativeFreight = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/deal/edit" -Token $token -Data @{ id = $projectId; freight = '-1.00' }
    Assert-Code -Json $negativeFreight -Expected 400 -Name 'sale project deal edit negative freight'

    $afterNegative = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$project = think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->find();
`$invoice = think\facade\Db::name('biz_sale_project_invoicing')->where('ID', '$safeInvoiceId')->find();
echo json_encode(['project' => `$project, 'invoice' => `$invoice], JSON_UNESCAPED_SLASHES);
"@
    if ([int]$afterNegative.project.VERSION -ne 0 -or $afterNegative.project.UNIT -ne 'initial unit' -or $afterNegative.invoice.DELETE_FLAG -ne 'NOT_DELETE') {
        throw 'sale project deal edit invalid request changed persisted data'
    }

    $valid = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/deal/edit" -Token $token -Data @{
        id = $projectId
        unit = 'CODEX Deal Unit'
        address = 'CODEX Deal Address'
        logisticsCategory = 'yunda'
        consignee = 'CODEX Recipient'
        phone = '13800000000'
        remark = 'CODEX deal remark'
        freight = '12.34'
        freightCategory = 'Consignment_payment'
        deliveryNote = 'CODEX delivery note'
        projectState = 'FOLLOW'
        initPrice = '999.99'
        totalPrice = '999.99'
        amountCollected = '999.99'
        deleteFlag = 'DELETED'
    }
    Assert-Code -Json $valid -Expected 200 -Name 'sale project deal edit valid'

    $after = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$project = think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->find();
`$invoice = think\facade\Db::name('biz_sale_project_invoicing')->where('ID', '$safeInvoiceId')->find();
echo json_encode(['project' => `$project, 'invoice' => `$invoice], JSON_UNESCAPED_SLASHES);
"@
    if ($after.project.UNIT -ne 'CODEX Deal Unit' -or $after.project.ADDRESS -ne 'CODEX Deal Address') {
        throw 'sale project deal edit did not update delivery unit/address'
    }
    if ($after.project.LOGISTICS_CATEGORY -ne 'yunda' -or $after.project.CONSIGNEE -ne 'CODEX Recipient' -or $after.project.PHONE -ne '13800000000') {
        throw 'sale project deal edit did not update logistics/recipient fields'
    }
    if ($after.project.REMARK -ne 'CODEX deal remark' -or $after.project.FREIGHT_CATEGORY -ne 'Consignment_payment' -or $after.project.DELIVERY_NOTE -ne 'CODEX delivery note') {
        throw 'sale project deal edit did not update remark/freight category/delivery note'
    }
    if ([decimal]$after.project.FREIGHT -ne [decimal]'12.34') {
        throw 'sale project deal edit did not update freight'
    }
    if ($after.project.PROJECT_STATE -ne 'SHIPPED' -or $after.project.DELETE_FLAG -ne 'NOT_DELETE') {
        throw 'sale project deal edit changed protected project state/delete fields'
    }
    if ([decimal]$after.project.INIT_PRICE -ne [decimal]'100.00' -or [decimal]$after.project.TOTAL_PRICE -ne [decimal]'100.00' -or [decimal]$after.project.AMOUNT_COLLECTED -ne [decimal]'20.00') {
        throw 'sale project deal edit changed protected amount fields'
    }
    if ([int]$after.project.VERSION -ne 1 -or [string]$after.project.UPDATE_USER -ne $userId -or [string]$after.project.UPDATE_TIME -eq '') {
        throw 'sale project deal edit did not refresh audit/version fields'
    }
    if ($after.invoice.DELETE_FLAG -ne 'NOT_DELETE' -or [decimal]$after.invoice.AMOUNT -ne [decimal]'10.00') {
        throw 'sale project deal edit unexpectedly changed invoicing rows'
    }

    Write-Host 'sale project deal edit HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
