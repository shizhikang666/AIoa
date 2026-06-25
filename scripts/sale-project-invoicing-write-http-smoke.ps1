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
        [Parameter(Mandatory = $true)][string]$Key,
        [string]$Default = ''
    )

    if ($EnvMap.ContainsKey($Key) -and [string]$EnvMap[$Key] -ne '') {
        return [string]$EnvMap[$Key]
    }

    return $Default
}

function Invoke-RawGet {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [string]$Token = ''
    )

    $args = @('-sS', '-X', 'GET', $Url)
    if ($Token.Trim() -ne '') {
        $args += @('-H', "Authorization: Bearer $Token")
    }

    $raw = & curl.exe @args
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP GET failed: $Url"
    }

    return [string]::Join('', [string[]]$raw)
}

function Invoke-RawPostJson {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)]$Data,
        [string]$Token = ''
    )

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-sale-project-invoicing-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    try {
        ConvertTo-Json -InputObject $Data -Depth 12 -Compress | Set-Content -LiteralPath $tmp -Encoding ASCII
        $args = @('-sS', '-X', 'POST', $Url, '-H', 'Content-Type: application/json', '--data-binary', "@$tmp")
        if ($Token.Trim() -ne '') {
            $args += @('-H', "Authorization: Bearer $Token")
        }

        $raw = & curl.exe @args
        if ($LASTEXITCODE -ne 0) {
            throw "HTTP POST failed: $Url"
        }

        return [string]::Join('', [string[]]$raw)
    } finally {
        Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue
    }
}

function Read-JsonPath {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path,
        [switch]$Optional
    )

    $value = $Json | node (Join-Path $PSScriptRoot 'json-read.js') $Path
    $exitCode = $LASTEXITCODE
    if ($exitCode -eq 2 -and $Optional) {
        return $null
    }
    if ($exitCode -ne 0) {
        throw "JSON path missing or invalid: $Path"
    }

    return [string]$value
}

function Assert-Code {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][int]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $code = [int](Read-JsonPath -Json $Json -Path 'code')
    if ($code -ne $Expected) {
        throw "$Name returned code=$code expected=$Expected body=$Json"
    }
}

function Assert-PathEquals {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $actual = [string](Read-JsonPath -Json $Json -Path $Path)
    if ($actual -ne $Expected) {
        throw "$Name expected $Path=$Expected actual=$actual body=$Json"
    }
}

function Enc {
    param([Parameter(Mandatory = $true)][string]$Value)

    return [System.Uri]::EscapeDataString($Value)
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

function New-SmokeId {
    param([Parameter(Mandatory = $true)][string]$Prefix)

    return $Prefix + ([Guid]::NewGuid().ToString('N').Substring(0, 20 - $Prefix.Length))
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
`$auth['device'] = 'CODEX_SALE_PROJECT_INVOICING_WRITE_HTTP_SMOKE';
`$tenantId = (string)(`$user['TENANT_ID'] ?? '1');
if (`$tenantId === '') { `$tenantId = '1'; }
`$orgId = (string)(`$user['ORG_ID'] ?? '');
if (`$orgId === '') {
    `$orgId = (string)(think\facade\Db::name('sys_org')->where(function (`$query) {
        `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
    })->value('ID') ?? '0');
}
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => (string)`$user['ID'],
    'tenantId' => `$tenantId,
    'orgId' => `$orgId,
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
$prefix = 'spi' + ([Guid]::NewGuid().ToString('N').Substring(0, 9))
$projectId = New-SmokeId -Prefix 'SPP'
$customerId = New-SmokeId -Prefix 'SPC'
$missingProjectId = New-SmokeId -Prefix 'SPM'
$missingInvoiceId = New-SmokeId -Prefix 'SPI'

$safePrefix = $prefix.Replace("'", "\'")
$safeProjectId = $projectId.Replace("'", "\'")
$safeCustomerId = $customerId.Replace("'", "\'")
$safeUserId = $userId.Replace("'", "\'")
$safeTenantId = $tenantId.Replace("'", "\'")
$safeOrgId = $orgId.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_sale_project_invoicing')->where('PROJECT_ID', '$safeProjectId')->delete();
think\facade\Db::name('biz_sale_project_invoicing')->whereLike('PROCESS_ID', '$safePrefix%')->delete();
think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->delete();
think\facade\Db::name('biz_sale_project')->whereLike('PROJECT_NAME', '$safePrefix%')->delete();
"@

Invoke-Php -Code $cleanupCode | Out-Null

$sideEffectCountCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
echo json_encode([
    'invoice' => think\facade\Db::name('biz_sale_project_invoice')->count(),
    'invoiceItem' => think\facade\Db::name('biz_sale_project_invoice_item')->count(),
    'delivery' => think\facade\Db::name('delivery_record')->count(),
    'expenditure' => think\facade\Db::name('biz_expenditure_record')->count(),
    'payment' => think\facade\Db::name('biz_payment_record')->count(),
    'statement' => think\facade\Db::name('settlement_account_statement')->count(),
    'returnOrder' => think\facade\Db::name('return_order')->count(),
    'rate' => think\facade\Db::name('sale_project_rate')->count()
], JSON_UNESCAPED_SLASHES);
"@

try {
    $before = Invoke-PhpJson -Code $sideEffectCountCode

    $setupCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('biz_sale_project')->insert([
    'ID' => '$safeProjectId',
    'CUSTOMER' => '$safeCustomerId',
    'PROJECT_NAME' => '$safePrefix project',
    'PROJECT_STATE' => 'SHIPPED',
    'PLAY_STATE' => 'UNPAID',
    'VISIBILITY' => 'PRIVATE',
    'INIT_PRICE' => '500.00',
    'TOTAL_PRICE' => '500.00',
    'AMOUNT_COLLECTED' => '0.00',
    'PROJECT_CATEGORY' => 'DEFAULT',
    'USER' => '$safeUserId',
    'ORG' => '$safeOrgId',
    'REMARK' => '$safePrefix',
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
"@
    Invoke-Php -Code $setupCode | Out-Null

    $validAddPayload = @{
        projectId = $projectId
        amount = '123.45'
        invoicingCategory = 'SpecialTicket'
        processId = "$prefix-proc-add"
        remark = "$prefix add"
        companyName = "$prefix company A"
        customerCompany = "$prefix customer A"
        unit = "$prefix unit A"
        phone = '13800000001'
        taxpayer = "$prefix-tax-a"
        corporateAccount = "$prefix-account-a"
        bankName = "$prefix-bank-a"
        unitAddress = "$prefix-unit-address-a"
        unitPhone = '021-00000001'
        harvestAddress = "$prefix-harvest-a"
        extJson = @{
            source = 'codex'
            step = 'add'
        }
    }

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectinvoicing/add" -Data $validAddPayload
    Assert-Code -Json $noToken -Expected 401 -Name 'sale project invoicing add without token'

    $missingProject = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectinvoicing/add" -Token $token -Data @{
        amount = '123.45'
        invoicingCategory = 'SpecialTicket'
        processId = "$prefix-proc-missing-project"
        companyName = "$prefix company"
        customerCompany = "$prefix customer"
        unit = "$prefix unit"
        taxpayer = "$prefix-tax"
        corporateAccount = "$prefix-account"
        bankName = "$prefix-bank"
        unitAddress = "$prefix-address"
    }
    Assert-Code -Json $missingProject -Expected 400 -Name 'sale project invoicing add missing projectId'

    $missingProcess = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectinvoicing/add" -Token $token -Data @{
        projectId = $projectId
        amount = '123.45'
        invoicingCategory = 'SpecialTicket'
        companyName = "$prefix company"
        customerCompany = "$prefix customer"
        unit = "$prefix unit"
        taxpayer = "$prefix-tax"
        corporateAccount = "$prefix-account"
        bankName = "$prefix-bank"
        unitAddress = "$prefix-address"
    }
    Assert-Code -Json $missingProcess -Expected 400 -Name 'sale project invoicing add missing processId'

    $invalidCategory = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectinvoicing/add" -Token $token -Data @{
        projectId = $projectId
        amount = '123.45'
        invoicingCategory = 'InvalidTicket'
        processId = "$prefix-proc-invalid-category"
        companyName = "$prefix company"
        customerCompany = "$prefix customer"
        unit = "$prefix unit"
        taxpayer = "$prefix-tax"
        corporateAccount = "$prefix-account"
        bankName = "$prefix-bank"
        unitAddress = "$prefix-address"
    }
    Assert-Code -Json $invalidCategory -Expected 400 -Name 'sale project invoicing add invalid category'

    $missingProjectRow = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectinvoicing/add" -Token $token -Data @{
        projectId = $missingProjectId
        amount = '123.45'
        invoicingCategory = 'SpecialTicket'
        processId = "$prefix-proc-missing-project-row"
        companyName = "$prefix company"
        customerCompany = "$prefix customer"
        unit = "$prefix unit"
        taxpayer = "$prefix-tax"
        corporateAccount = "$prefix-account"
        bankName = "$prefix-bank"
        unitAddress = "$prefix-address"
    }
    Assert-Code -Json $missingProjectRow -Expected 404 -Name 'sale project invoicing add missing project row'

    $add = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectinvoicing/add" -Token $token -Data $validAddPayload
    Assert-Code -Json $add -Expected 200 -Name 'sale project invoicing add'
    $invoiceId = [string](Read-JsonPath -Json $add -Path 'data.id')
    if ($invoiceId.Trim() -eq '') {
        throw 'sale project invoicing add did not return data.id'
    }
    Assert-PathEquals -Json $add -Path 'data.projectId' -Expected $projectId -Name 'sale project invoicing add'
    Assert-PathEquals -Json $add -Path 'data.invoicingState' -Expected 'INVOICING_STATE_WAIT' -Name 'sale project invoicing add'
    Assert-PathEquals -Json $add -Path 'data.invoicingCategory' -Expected 'SpecialTicket' -Name 'sale project invoicing add'
    Assert-PathEquals -Json $add -Path 'data.companyName' -Expected "$prefix company A" -Name 'sale project invoicing add'

    $page = Invoke-RawGet -Url "$baseUrl/biz/saleprojectinvoicing/page?projectId=$(Enc $projectId)&companyName=$(Enc $prefix)&size=10" -Token $token
    Assert-Code -Json $page -Expected 200 -Name 'sale project invoicing page'
    Assert-PathEquals -Json $page -Path 'data.records.0.id' -Expected $invoiceId -Name 'sale project invoicing page'
    Assert-PathEquals -Json $page -Path 'data.records.0.projectState' -Expected 'SHIPPED' -Name 'sale project invoicing page'

    $customer = Invoke-RawGet -Url "$baseUrl/biz/saleprojectinvoicing/customer?id=$(Enc $customerId)" -Token $token
    Assert-Code -Json $customer -Expected 200 -Name 'sale project invoicing customer'
    Assert-PathEquals -Json $customer -Path 'data.id' -Expected $invoiceId -Name 'sale project invoicing customer'

    $safeInvoiceId = $invoiceId.Replace("'", "\'")
    $afterAddDb = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$row = think\facade\Db::name('biz_sale_project_invoicing')->where('ID', '$safeInvoiceId')->find();
echo json_encode(`$row, JSON_UNESCAPED_SLASHES);
"@
    if ([decimal]$afterAddDb.AMOUNT -ne [decimal]'123.45' -or [string]$afterAddDb.INVOICING_STATE -ne 'INVOICING_STATE_WAIT' -or [string]$afterAddDb.DELETE_FLAG -ne 'NOT_DELETE') {
        throw "sale project invoicing add database verification failed: $($afterAddDb | ConvertTo-Json -Compress)"
    }

    $invalidEditPayload = @{
        id = $invoiceId
        projectId = $projectId
        amount = '222.22'
        invoicingState = 'INVALID_STATE'
        invoicingCategory = 'GeneralTicket'
        processId = "$prefix-proc-invalid-edit"
        remark = "$prefix invalid edit"
        companyName = "$prefix company invalid"
        customerCompany = "$prefix customer invalid"
        unit = "$prefix unit invalid"
        phone = '13800000002'
        taxpayer = "$prefix-tax-invalid"
        corporateAccount = "$prefix-account-invalid"
        bankName = "$prefix-bank-invalid"
        unitAddress = "$prefix-unit-address-invalid"
        harvestAddress = "$prefix-harvest-invalid"
    }
    $invalidEdit = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectinvoicing/edit" -Token $token -Data $invalidEditPayload
    Assert-Code -Json $invalidEdit -Expected 400 -Name 'sale project invoicing edit invalid state'

    $afterInvalidEdit = Invoke-RawGet -Url "$baseUrl/biz/saleprojectinvoicing/detail?id=$(Enc $invoiceId)" -Token $token
    Assert-Code -Json $afterInvalidEdit -Expected 200 -Name 'sale project invoicing detail after invalid edit'
    Assert-PathEquals -Json $afterInvalidEdit -Path 'data.companyName' -Expected "$prefix company A" -Name 'sale project invoicing detail after invalid edit'
    Assert-PathEquals -Json $afterInvalidEdit -Path 'data.invoicingCategory' -Expected 'SpecialTicket' -Name 'sale project invoicing detail after invalid edit'

    $editPayload = @{
        id = $invoiceId
        projectId = $projectId
        amount = '234.56'
        invoicingState = 'INVOICING_STATE_WAIT'
        invoicingCategory = 'GeneralTicket'
        processId = "$prefix-proc-edit"
        remark = "$prefix edit"
        companyName = "$prefix company B"
        customerCompany = "$prefix customer B"
        unit = "$prefix unit B"
        phone = '13800000003'
        taxpayer = "$prefix-tax-b"
        corporateAccount = "$prefix-account-b"
        bankName = "$prefix-bank-b"
        unitAddress = "$prefix-unit-address-b"
        unitPhone = '021-00000002'
        harvestAddress = "$prefix-harvest-b"
        extJson = @{
            source = 'codex'
            step = 'edit'
        }
    }
    $edit = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectinvoicing/edit" -Token $token -Data $editPayload
    Assert-Code -Json $edit -Expected 200 -Name 'sale project invoicing edit'
    Assert-PathEquals -Json $edit -Path 'data.id' -Expected $invoiceId -Name 'sale project invoicing edit'

    $detail = Invoke-RawGet -Url "$baseUrl/biz/saleprojectinvoicing/detail?id=$(Enc $invoiceId)" -Token $token
    Assert-Code -Json $detail -Expected 200 -Name 'sale project invoicing detail after edit'
    Assert-PathEquals -Json $detail -Path 'data.invoicingCategory' -Expected 'GeneralTicket' -Name 'sale project invoicing detail after edit'
    Assert-PathEquals -Json $detail -Path 'data.companyName' -Expected "$prefix company B" -Name 'sale project invoicing detail after edit'
    Assert-PathEquals -Json $detail -Path 'data.phone' -Expected '13800000003' -Name 'sale project invoicing detail after edit'

    $complete = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectinvoicing/complete" -Token $token -Data @{
        id = $invoiceId
    }
    Assert-Code -Json $complete -Expected 200 -Name 'sale project invoicing complete'

    $afterComplete = Invoke-RawGet -Url "$baseUrl/biz/saleprojectinvoicing/detail?id=$(Enc $invoiceId)" -Token $token
    Assert-Code -Json $afterComplete -Expected 200 -Name 'sale project invoicing detail after complete'
    Assert-PathEquals -Json $afterComplete -Path 'data.invoicingState' -Expected 'INVOICING_STATE_COMPLETE' -Name 'sale project invoicing detail after complete'

    $deleteRollback = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectinvoicing/delete" -Token $token -Data @{
        idList = @($invoiceId, $missingInvoiceId)
    }
    Assert-Code -Json $deleteRollback -Expected 404 -Name 'sale project invoicing delete mixed rollback'

    $afterDeleteRollback = Invoke-RawGet -Url "$baseUrl/biz/saleprojectinvoicing/detail?id=$(Enc $invoiceId)" -Token $token
    Assert-Code -Json $afterDeleteRollback -Expected 200 -Name 'sale project invoicing detail after delete rollback'
    Assert-PathEquals -Json $afterDeleteRollback -Path 'data.invoicingState' -Expected 'INVOICING_STATE_COMPLETE' -Name 'sale project invoicing detail after delete rollback'

    $delete = Invoke-RawPostJson -Url "$baseUrl/biz/saleprojectinvoicing/delete" -Token $token -Data @{
        idList = @($invoiceId)
    }
    Assert-Code -Json $delete -Expected 200 -Name 'sale project invoicing delete'
    Assert-PathEquals -Json $delete -Path 'data.count' -Expected '1' -Name 'sale project invoicing delete'

    $afterDeleteDetail = Invoke-RawGet -Url "$baseUrl/biz/saleprojectinvoicing/detail?id=$(Enc $invoiceId)" -Token $token
    Assert-Code -Json $afterDeleteDetail -Expected 404 -Name 'sale project invoicing detail after delete'

    $verify = Invoke-PhpJson -Code @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$invoice = think\facade\Db::name('biz_sale_project_invoicing')->where('ID', '$safeInvoiceId')->find();
`$project = think\facade\Db::name('biz_sale_project')->where('ID', '$safeProjectId')->find();
echo json_encode([
    'invoice' => `$invoice,
    'project' => `$project,
], JSON_UNESCAPED_SLASHES);
"@
    $invoice = $verify.invoice
    $project = $verify.project
    if ([string]$invoice.DELETE_FLAG -ne 'DELETED' -or [decimal]$invoice.AMOUNT -ne [decimal]'234.56' -or [string]$invoice.INVOICING_CATEGORY -ne 'GeneralTicket' -or [string]$invoice.INVOICING_STATE -ne 'INVOICING_STATE_COMPLETE' -or [string]$invoice.COMPANY_NAME -ne "$prefix company B") {
        throw "sale project invoicing final database verification failed: $($verify | ConvertTo-Json -Compress)"
    }
    if ([string]$project.DELETE_FLAG -ne 'NOT_DELETE' -or [string]$project.PROJECT_STATE -ne 'SHIPPED' -or [int]$project.VERSION -ne 0) {
        throw "sale project invoicing unexpectedly changed project row: $($verify | ConvertTo-Json -Compress)"
    }

    $after = Invoke-PhpJson -Code $sideEffectCountCode
    foreach ($key in @('invoice', 'invoiceItem', 'delivery', 'expenditure', 'payment', 'statement', 'returnOrder', 'rate')) {
        if ([int]$after.$key -ne [int]$before.$key) {
            throw "sale project invoicing unexpectedly changed side-effect table count: $key"
        }
    }

    Write-Host 'sale project invoicing write HTTP smoke passed'
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}
