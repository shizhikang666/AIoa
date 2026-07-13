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

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-sale-project-foundation-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    try {
        ConvertTo-Json -InputObject $Data -Depth 12 | Set-Content -LiteralPath $tmp -Encoding UTF8
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
`$auth['device'] = 'CODEX_SALE_PROJECT_FOUNDATION_HTTP_SMOKE';
`$tenantId = (string)(`$user['TENANT_ID'] ?? '');
`$orgId = (string)(`$user['ORG_ID'] ?? '');
if (`$orgId === '') {
    `$orgId = (string)think\facade\Db::name('sys_org')
        ->where('TENANT_ID', `$tenantId)
        ->where(function (`$query): void { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })
        ->value('ID');
}
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => (string)`$user['ID'],
    'tenantId' => `$tenantId,
    'orgId' => `$orgId
], JSON_UNESCAPED_SLASHES);
"@

$session = Invoke-PhpJson -Code $sessionCode
$token = [string]$session.token
$userId = [string]$session.userId
$tenantId = [string]$session.tenantId
$orgId = [string]$session.orgId
if ($token.Trim() -eq '' -or $userId.Trim() -eq '' -or $tenantId.Trim() -eq '' -or $orgId.Trim() -eq '') {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'CODEX_SALE_FOUNDATION_' + (Get-Date -Format 'MMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
$customerId = [string]([Int64]604600000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$editProjectId = [string]([Int64]604601000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$blockedEditProjectId = [string]([Int64]604602000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$missingProjectId = [string]([Int64]604699000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
$missingCustomerId = [string]([Int64]604698000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))

$safePrefix = $prefix.Replace("'", "\'")
$safeCustomerId = $customerId.Replace("'", "\'")
$safeEditProjectId = $editProjectId.Replace("'", "\'")
$safeBlockedEditProjectId = $blockedEditProjectId.Replace("'", "\'")
$safeUserId = $userId.Replace("'", "\'")
$safeTenantId = $tenantId.Replace("'", "\'")
$safeOrgId = $orgId.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$projectIds = think\facade\Db::name('biz_sale_project')->whereLike('PROJECT_NAME', '$safePrefix%')->column('ID');
`$projectIds = array_values(array_filter(array_map('strval', `$projectIds)));
if (`$projectIds !== []) {
    think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_sale_project_invoicing')->whereIn('PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_payment_record')->whereIn('OBJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('sales_project_field_change_log')->whereIn('OBJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('sale_project_rate')->whereIn('PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->delete();
}
think\facade\Db::name('biz_sale_project')->whereIn('ID', ['$safeEditProjectId', '$safeBlockedEditProjectId'])->delete();
think\facade\Db::name('customer')->whereLike('NAME', '$safePrefix%')->delete();
think\facade\Db::name('customer')->where('ID', '$safeCustomerId')->delete();
"@

Invoke-Php -Code $cleanupCode | Out-Null

try {
    $insertCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('customer')->insert([
    'ID' => '$safeCustomerId',
    'NAME' => '$safePrefix customer',
    'CUSTOM_TYPE' => 'OLD',
    'ORG' => '$safeOrgId',
    'USER' => '$safeUserId',
    'STATUS' => 'ENABLE',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => '$safeUserId',
    'TENANT_ID' => '$safeTenantId',
    'VERSION' => 0,
    'DEAL_AMOUNT' => 0,
]);
foreach ([['$safeEditProjectId', 'FOLLOW'], ['$safeBlockedEditProjectId', 'WAIT_DELIVER']] as `$project) {
    think\facade\Db::name('biz_sale_project')->insert([
        'ID' => `$project[0],
        'CUSTOMER' => '$safeCustomerId',
        'PROJECT_NAME' => '$safePrefix edit ' . `$project[0],
        'PROJECT_STATE' => `$project[1],
        'PLAY_STATE' => 'UNPAID',
        'VISIBILITY' => 'PRIVATE',
        'INIT_PRICE' => '10.00',
        'TOTAL_PRICE' => '10.00',
        'AMOUNT_COLLECTED' => '0.00',
        'PROJECT_CATEGORY' => 'DEFAULT',
        'USER' => '$safeUserId',
        'ORG' => '$safeOrgId',
        'REMARK' => 'seed',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => '$safeUserId',
        'TENANT_ID' => '$safeTenantId',
        'VERSION' => 0,
        'DEAL_AMOUNT' => 0,
        'HISTORY_AMOUNT' => '0.00',
        'TOTAL_RETURN_AMOUNT' => '0.00',
        'TOTAL_REFUND_AMOUNT' => '0.00',
    ]);
}
echo json_encode(['ok' => 1], JSON_UNESCAPED_SLASHES);
"@
    Invoke-PhpJson -Code $insertCode | Out-Null

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/add" -Data @{ customer = $customerId; projectName = "$prefix no token"; projectCategory = 'DEFAULT' }
    Assert-Code -Json $noToken -Expected 401 -Name 'sale project add without token'

    $missingAddField = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/add" -Token $token -Data @{ customer = $customerId; projectCategory = 'DEFAULT' }
    Assert-Code -Json $missingAddField -Expected 400 -Name 'sale project add missing projectName'

    $missingCustomer = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/add" -Token $token -Data @{ customer = $missingCustomerId; projectName = "$prefix missing customer"; projectCategory = 'DEFAULT' }
    Assert-Code -Json $missingCustomer -Expected 404 -Name 'sale project add missing customer'

    $addResponse = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/add" -Token $token -Data @{
        customer = $customerId
        projectName = "$prefix add project"
        projectCategory = 'DEFAULT'
        remark = 'add remark'
        area = 'add area'
        detailsAddress = 'add address'
        projectCode = "$prefix-code"
        specimenCategory = 'SAMPLE'
        specimenName = 'sample-name'
        initPrice = '999.00'
        totalPrice = '999.00'
        projectState = 'COMPLETED'
        playState = 'PAID'
    }
    Assert-Code -Json $addResponse -Expected 200 -Name 'sale project add success'
    $addProjectId = Read-JsonPath -Json $addResponse -Path 'data.id'

    $editMissingId = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/edit" -Token $token -Data @{ projectName = 'missing id' }
    Assert-Code -Json $editMissingId -Expected 400 -Name 'sale project edit missing id'

    $editMissingRow = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/edit" -Token $token -Data @{ id = $missingProjectId; projectName = 'missing row' }
    Assert-Code -Json $editMissingRow -Expected 404 -Name 'sale project edit missing row'

    $editBlocked = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/edit" -Token $token -Data @{ id = $blockedEditProjectId; projectName = "$prefix blocked edit" }
    Assert-Code -Json $editBlocked -Expected 400 -Name 'sale project edit non-FOLLOW'

    $editSuccess = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/edit" -Token $token -Data @{
        id = $editProjectId
        projectName = "$prefix edited project"
        projectCategory = 'DIRECT'
        remark = 'edited remark'
        area = 'edited area'
        detailsAddress = 'edited address'
        projectCode = "$prefix-edited-code"
        customer = $missingCustomerId
        initPrice = '888.00'
        projectState = 'COMPLETED'
        playState = 'PAID'
    }
    Assert-Code -Json $editSuccess -Expected 200 -Name 'sale project edit success'

    $historyTooMuch = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/history/add" -Token $token -Data @{
        projectName = "$prefix history too much"
        customerName = "$prefix history customer too much"
        user = $userId
        initPrice = '100.00'
        historyAmount = '101.00'
        completionDate = '2026-06-18 09:00:00'
    }
    Assert-Code -Json $historyTooMuch -Expected 400 -Name 'sale project history add over collected'

    $historySuccess = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/history/add" -Token $token -Data @{
        projectName = "$prefix history project"
        customerName = "$prefix history customer"
        user = $userId
        initPrice = '300.00'
        historyAmount = '300.00'
        completionDate = '2026-06-18 10:00:00'
        remark = 'ignored history remark'
    }
    Assert-Code -Json $historySuccess -Expected 200 -Name 'sale project history add success'
    $historyProjectId = Read-JsonPath -Json $historySuccess -Path 'data.id'
    $historyCustomerId = Read-JsonPath -Json $historySuccess -Path 'data.customerId'

    $specialNegative = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/special/add" -Token $token -Data @{
        projectName = "$prefix special negative"
        customerName = "$prefix special customer negative"
        orgId = $orgId
        initPrice = '-1.00'
        completionDate = '2026-06-18 11:00:00'
    }
    Assert-Code -Json $specialNegative -Expected 400 -Name 'sale project special add negative initPrice'

    $specialSuccess = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/special/add" -Token $token -Data @{
        projectName = "$prefix special project"
        customerName = "$prefix special customer"
        orgId = $orgId
        initPrice = '200.00'
        historyAmount = '199.00'
        completionDate = '2026-06-18 12:00:00'
        remark = 'ignored special remark'
    }
    Assert-Code -Json $specialSuccess -Expected 200 -Name 'sale project special add success'
    $specialProjectId = Read-JsonPath -Json $specialSuccess -Path 'data.id'
    $specialCustomerId = Read-JsonPath -Json $specialSuccess -Path 'data.customerId'

    foreach ($projectId in @($addProjectId, $editProjectId, $historyProjectId, $specialProjectId)) {
        foreach ($path in @('detail', 'product')) {
            $readback = Invoke-RawGet -Url "$baseUrl/biz/saleproject/${path}?id=$projectId" -Token $token
            Assert-Code -Json $readback -Expected 200 -Name "sale project $path readback $projectId"
        }
        foreach ($path in @('cost', 'cost/details')) {
            $readback = Invoke-RawPostJson -Url "$baseUrl/biz/saleproject/${path}?id=$projectId" -Token $token -Data @{}
            Assert-Code -Json $readback -Expected 200 -Name "sale project $path readback $projectId"
        }
    }

    $safeAddProjectId = $addProjectId.Replace("'", "\'")
    $safeHistoryProjectId = $historyProjectId.Replace("'", "\'")
    $safeHistoryCustomerId = $historyCustomerId.Replace("'", "\'")
    $safeSpecialProjectId = $specialProjectId.Replace("'", "\'")
    $safeSpecialCustomerId = $specialCustomerId.Replace("'", "\'")
    $verifyCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$projectIds = ['$safeAddProjectId', '$safeEditProjectId', '$safeBlockedEditProjectId', '$safeHistoryProjectId', '$safeSpecialProjectId'];
`$rows = think\facade\Db::name('biz_sale_project')->whereIn('ID', `$projectIds)->select()->toArray();
`$projects = [];
foreach (`$rows as `$row) { `$projects[(string)`$row['ID']] = `$row; }
`$customers = [];
foreach (think\facade\Db::name('customer')->whereIn('ID', ['$safeCustomerId', '$safeHistoryCustomerId', '$safeSpecialCustomerId'])->select()->toArray() as `$row) {
    `$customers[(string)`$row['ID']] = `$row;
}
`$createdProjectIds = ['$safeAddProjectId', '$safeHistoryProjectId', '$safeSpecialProjectId'];
`$related = [
    'productItems' => think\facade\Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', `$createdProjectIds)->count(),
    'invoicing' => think\facade\Db::name('biz_sale_project_invoicing')->whereIn('PROJECT_ID', `$createdProjectIds)->count(),
    'paymentRecords' => think\facade\Db::name('biz_payment_record')->whereIn('OBJECT_ID', `$createdProjectIds)->count(),
    'changeLogs' => think\facade\Db::name('sales_project_field_change_log')->whereIn('OBJECT_ID', `$createdProjectIds)->count(),
    'rates' => think\facade\Db::name('sale_project_rate')->whereIn('PROJECT_ID', `$createdProjectIds)->count(),
];
echo json_encode([
    'add' => `$projects['$safeAddProjectId'] ?? null,
    'edit' => `$projects['$safeEditProjectId'] ?? null,
    'blockedEdit' => `$projects['$safeBlockedEditProjectId'] ?? null,
    'history' => `$projects['$safeHistoryProjectId'] ?? null,
    'special' => `$projects['$safeSpecialProjectId'] ?? null,
    'seedCustomer' => `$customers['$safeCustomerId'] ?? null,
    'historyCustomer' => `$customers['$safeHistoryCustomerId'] ?? null,
    'specialCustomer' => `$customers['$safeSpecialCustomerId'] ?? null,
    'related' => `$related,
], JSON_UNESCAPED_SLASHES);
"@

    $state = Invoke-PhpJson -Code $verifyCode
    if ($null -eq $state.add -or $null -eq $state.edit -or $null -eq $state.history -or $null -eq $state.special) {
        throw 'sale project foundation smoke missing persisted project rows'
    }
    if ([string]$state.add.PROJECT_STATE -ne 'FOLLOW' -or [string]$state.add.PLAY_STATE -ne 'UNPAID' -or [string]$state.add.INIT_PRICE -ne '0.00' -or [string]$state.add.TOTAL_PRICE -ne '0.00') {
        throw 'sale project add did not preserve Java-compatible default project state and amounts'
    }
    if ([string]$state.add.PROJECT_CATEGORY -ne 'DEFAULT' -or [string]$state.add.CUSTOMER -ne $customerId -or [string]$state.add.PROJECT_CODE -ne "$prefix-code") {
        throw 'sale project add did not persist expected base fields'
    }
    if ([string]$state.edit.PROJECT_NAME -ne "$prefix edited project" -or [string]$state.edit.PROJECT_CATEGORY -ne 'DIRECT' -or [string]$state.edit.PROJECT_STATE -ne 'FOLLOW' -or [string]$state.edit.INIT_PRICE -ne '10.00') {
        throw 'sale project edit did not update only allowed base fields'
    }
    if ([int]$state.edit.VERSION -ne 1 -or [int]$state.blockedEdit.VERSION -ne 0 -or [string]$state.blockedEdit.PROJECT_NAME -like "$prefix blocked edit*") {
        throw 'sale project edit state guard or version increment failed'
    }
    if ([string]$state.history.PROJECT_CATEGORY -ne 'DIRECT' -or [string]$state.history.PLAY_STATE -ne 'PAID' -or [string]$state.history.PROJECT_STATE -ne 'COMPLETED' -or [string]$state.history.AMOUNT_COLLECTED -ne '300.00') {
        throw 'sale project history add did not apply historical payment correction'
    }
    if ([string]$state.history.HISTORY_AMOUNT -ne '300.00' -or [string]$state.history.TOTAL_PRICE -ne '300.00' -or [string]$state.history.USER -ne $userId) {
        throw 'sale project history add persisted unexpected project values'
    }
    if ([string]$state.historyCustomer.CUSTOM_TYPE -ne 'OLD' -or [string]$state.historyCustomer.STATUS -ne 'ENABLE' -or [string]$state.historyCustomer.USER -ne $userId) {
        throw 'sale project history add did not create expected history customer'
    }
    if ([string]$state.special.special_type -ne 'PUBLIC_FOR_REIMBURSEMENT' -or [string]$state.special.PLAY_STATE -ne 'UNPAID' -or [string]$state.special.PROJECT_STATE -ne 'SHIPPED') {
        throw 'sale project special add did not apply reimbursement state'
    }
    if ([string]$state.special.HISTORY_AMOUNT -ne '0.00' -or [string]$state.special.AMOUNT_COLLECTED -ne '0.00' -or [string]$state.special.ORG -ne $orgId) {
        throw 'sale project special add persisted ignored or wrong fields'
    }
    if ([string]$state.specialCustomer.CUSTOM_TYPE -ne 'OLD' -or [string]$state.specialCustomer.STATUS -ne 'ENABLE' -or [string]$state.specialCustomer.ORG -ne $orgId) {
        throw 'sale project special add did not create expected customer'
    }
    foreach ($property in @('productItems', 'invoicing', 'paymentRecords', 'changeLogs', 'rates')) {
        if ([int]$state.related.$property -ne 0) {
            throw "sale project foundation smoke found unexpected $property side effects"
        }
    }

    Write-Host 'sale project foundation closure HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
