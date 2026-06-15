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
        [Parameter(Mandatory = $true)][string]$Token
    )

    $raw = & curl.exe -sS -X GET $Url -H "Authorization: Bearer $Token"
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP GET failed: $Url"
    }

    return [string]::Join('', [string[]]$raw)
}

function Invoke-RawPost {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$Token,
        [string]$Body = '{}'
    )

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ('business-read-smoke-' + [Guid]::NewGuid().ToString('N') + '.json')
    Set-Content -LiteralPath $bodyPath -Value $Body -Encoding ASCII
    try {
        $raw = & curl.exe -sS -X POST $Url -H "Authorization: Bearer $Token" -H 'Content-Type: application/json' --data-binary "@$bodyPath"
        if ($LASTEXITCODE -ne 0) {
            throw "HTTP POST failed: $Url"
        }

        return [string]::Join('', [string[]]$raw)
    } finally {
        if (Test-Path -LiteralPath $bodyPath) {
            Remove-Item -LiteralPath $bodyPath -Force
        }
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

function Assert-Ok {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $code = Read-JsonPath -Json $Json -Path 'code'
    if ([int]$code -ne 200) {
        throw "$Name returned code=$code"
    }
}

function Assert-Paths {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string[]]$Paths
    )

    foreach ($path in $Paths) {
        [void](Read-JsonPath -Json $Json -Path $path)
    }
}

function Has-Path {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path
    )

    return $null -ne (Read-JsonPath -Json $Json -Path $Path -Optional)
}

function Assert-PagedShape {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data.records', 'data.total', 'data.current', 'data.size', 'data.pages')
}

function Assert-FirstRecordIfPresent {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string[]]$Keys
    )

    if (-not (Has-Path -Json $Json -Path 'data.records.0')) {
        return
    }

    $paths = @()
    foreach ($key in $Keys) {
        $paths += "data.records.0.$key"
    }

    Assert-Paths -Json $Json -Name "$Name first record" -Paths $paths
}

function Assert-CustomerRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.name",
        "$Prefix.contacts",
        "$Prefix.phone",
        "$Prefix.detailsAddress",
        "$Prefix.address",
        "$Prefix.sourceType",
        "$Prefix.customType",
        "$Prefix.org",
        "$Prefix.user",
        "$Prefix.status",
        "$Prefix.headName",
        "$Prefix.orgName",
        "$Prefix.createUserName",
        "$Prefix.downloadPath",
        "$Prefix.firstContactTime"
    )
}

function Assert-SaleProjectRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.projectName",
        "$Prefix.projectState",
        "$Prefix.playState",
        "$Prefix.customerName",
        "$Prefix.customerAddress",
        "$Prefix.customerSourceType",
        "$Prefix.customType",
        "$Prefix.headName",
        "$Prefix.headPhone",
        "$Prefix.orgName",
        "$Prefix.accountName"
    )
}

function Assert-CustomerFollowUpRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.customerId",
        "$Prefix.customerName",
        "$Prefix.followUpTime",
        "$Prefix.content",
        "$Prefix.createUserName",
        "$Prefix.avatar",
        "$Prefix.createUserOrgId",
        "$Prefix.createUserOrgName",
        "$Prefix.extJson"
    )
}

function Assert-SaleProjectFollowUpRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.projectId",
        "$Prefix.projectName",
        "$Prefix.projectUser",
        "$Prefix.projectOrg",
        "$Prefix.followUpTime",
        "$Prefix.category",
        "$Prefix.content",
        "$Prefix.createUserName",
        "$Prefix.avatar",
        "$Prefix.createUserOrgId",
        "$Prefix.createUserOrgName",
        "$Prefix.extJson"
    )
}

function Assert-SaleProjectInvoicingRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.projectId",
        "$Prefix.amount",
        "$Prefix.invoicingState",
        "$Prefix.invoicingCategory",
        "$Prefix.companyName",
        "$Prefix.customerCompany",
        "$Prefix.unit",
        "$Prefix.phone",
        "$Prefix.projectName",
        "$Prefix.customerName"
    )
}

function Assert-SaleProjectInvoiceRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.projectId",
        "$Prefix.logisticsCategory",
        "$Prefix.phone",
        "$Prefix.logisticsId",
        "$Prefix.freight",
        "$Prefix.freightTime",
        "$Prefix.freightCategory",
        "$Prefix.unit",
        "$Prefix.address",
        "$Prefix.projectName",
        "$Prefix.customerName"
    )
}

function Assert-SaleProjectInvoiceItemRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.invoiceId",
        "$Prefix.projectProductItemId",
        "$Prefix.warehousesId",
        "$Prefix.amount",
        "$Prefix.projectId",
        "$Prefix.productId",
        "$Prefix.productName",
        "$Prefix.warehousesName"
    )
}

function Assert-ReissueOrderRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.projectId",
        "$Prefix.projectName",
        "$Prefix.customerName"
    )
}

function Assert-SaleProjectProductInfoRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.productId",
        "$Prefix.targetId",
        "$Prefix.contentText",
        "$Prefix.alias",
        "$Prefix.versionType",
        "$Prefix.abbreviation",
        "$Prefix.deleteFlag",
        "$Prefix.extJson",
        "$Prefix.createTime",
        "$Prefix.createUserName",
        "$Prefix.productName",
        "$Prefix.targetProductName"
    )
}

function Assert-SaleProjectProductItemRelationRow {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Prefix,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.objectId",
        "$Prefix.targetId",
        "$Prefix.productId",
        "$Prefix.mark",
        "$Prefix.number",
        "$Prefix.deleteFlag",
        "$Prefix.extJson",
        "$Prefix.projectId",
        "$Prefix.projectName",
        "$Prefix.projectUser",
        "$Prefix.projectOrg",
        "$Prefix.productName",
        "$Prefix.productCategory",
        "$Prefix.productSysCategory",
        "$Prefix.specs"
    )
}

$envMap = Get-EnvMap -Path $EnvPath
$account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
if ($account -eq '') {
    throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env'
}

$safeAccount = $account.Replace("'", "\'")
$tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_BUSINESS_READ_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$sampleCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$notDeleted = function (`$query) { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); };
`$customerId = think\facade\Db::name('customer')->where(`$notDeleted)->value('ID');
`$saleProjectId = think\facade\Db::name('biz_sale_project')->where(`$notDeleted)->value('ID');
if (!`$customerId) { throw new RuntimeException('sample customer not found'); }
if (!`$saleProjectId) { throw new RuntimeException('sample sale project not found'); }
`$customerFollowUp = think\facade\Db::name('customer_follow_up')
    ->alias('f')
    ->join('customer c', 'c.ID = f.CUSTOMER_ID', 'INNER')
    ->where(function (`$query) { `$query->whereNull('f.DELETE_FLAG')->whereOr('f.DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->where(function (`$query) { `$query->whereNull('c.DELETE_FLAG')->whereOr('c.DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->field('f.ID AS ID, f.CUSTOMER_ID AS CUSTOMER_ID')
    ->find();
`$saleProjectFollowUp = think\facade\Db::name('sale_project_follow_up')
    ->alias('f')
    ->join('biz_sale_project p', 'p.ID = f.PROJECT_ID', 'INNER')
    ->where(function (`$query) { `$query->whereNull('f.DELETE_FLAG')->whereOr('f.DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->where(function (`$query) { `$query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->field('f.ID AS ID, f.PROJECT_ID AS PROJECT_ID')
    ->find();
`$invoicing = think\facade\Db::name('biz_sale_project_invoicing')
    ->alias('i')
    ->join('biz_sale_project p', 'p.ID = i.PROJECT_ID', 'INNER')
    ->where(function (`$query) { `$query->whereNull('i.DELETE_FLAG')->whereOr('i.DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->where(function (`$query) { `$query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->field('i.ID AS ID, i.PROJECT_ID AS PROJECT_ID, p.CUSTOMER AS CUSTOMER_ID')
    ->find();
`$invoice = think\facade\Db::name('biz_sale_project_invoice')
    ->alias('v')
    ->join('biz_sale_project p', 'p.ID = v.PROJECT_ID', 'INNER')
    ->where(function (`$query) { `$query->whereNull('v.DELETE_FLAG')->whereOr('v.DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->where(function (`$query) { `$query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->field('v.ID AS ID, v.PROJECT_ID AS PROJECT_ID')
    ->find();
`$invoiceItem = think\facade\Db::name('biz_sale_project_invoice_item')
    ->alias('item')
    ->join('biz_sale_project_invoice v', 'v.ID = item.INVOICE_ID', 'INNER')
    ->join('biz_sale_project p', 'p.ID = v.PROJECT_ID', 'INNER')
    ->where(function (`$query) { `$query->whereNull('item.DELETE_FLAG')->whereOr('item.DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->where(function (`$query) { `$query->whereNull('v.DELETE_FLAG')->whereOr('v.DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->where(function (`$query) { `$query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->field('item.ID AS ID, item.INVOICE_ID AS INVOICE_ID')
    ->find();
`$reissueOrder = think\facade\Db::name('biz_sale_project_reissue_order')
    ->alias('o')
    ->join('biz_sale_project p', 'p.ID = o.PROJECT_ID', 'INNER')
    ->where(function (`$query) { `$query->whereNull('o.DELETE_FLAG')->whereOr('o.DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->where(function (`$query) { `$query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->field('o.ID AS ID, o.PROJECT_ID AS PROJECT_ID')
    ->find();
`$productInfo = think\facade\Db::name('biz_sale_project_product_info')
    ->where(function (`$query) { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->field('ID, PRODUCT_ID, TARGET_ID')
    ->find();
`$productItemRelation = think\facade\Db::name('sale_project_product_item_relation')
    ->alias('r')
    ->join('biz_sale_project_product_item i', 'i.ID = r.OBJECT_ID', 'INNER')
    ->join('biz_sale_project p', 'p.ID = i.PROJECT_ID', 'INNER')
    ->where(function (`$query) { `$query->whereNull('r.DELETE_FLAG')->whereOr('r.DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->where(function (`$query) { `$query->whereNull('i.DELETE_FLAG')->whereOr('i.DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->where(function (`$query) { `$query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', 'NOT_DELETE'); })
    ->field('r.ID AS ID, r.OBJECT_ID AS OBJECT_ID')
    ->find();
echo json_encode([
    'customerId' => (string)`$customerId,
    'saleProjectId' => (string)`$saleProjectId,
    'customerFollowUpId' => `$customerFollowUp ? (string)`$customerFollowUp['ID'] : '',
    'customerFollowUpCustomerId' => `$customerFollowUp ? (string)`$customerFollowUp['CUSTOMER_ID'] : '',
    'saleProjectFollowUpId' => `$saleProjectFollowUp ? (string)`$saleProjectFollowUp['ID'] : '',
    'saleProjectFollowUpProjectId' => `$saleProjectFollowUp ? (string)`$saleProjectFollowUp['PROJECT_ID'] : '',
    'saleProjectInvoicingId' => `$invoicing ? (string)`$invoicing['ID'] : '',
    'saleProjectInvoicingProjectId' => `$invoicing ? (string)`$invoicing['PROJECT_ID'] : '',
    'saleProjectInvoicingCustomerId' => `$invoicing ? (string)`$invoicing['CUSTOMER_ID'] : '',
    'saleProjectInvoiceId' => `$invoice ? (string)`$invoice['ID'] : '',
    'saleProjectInvoiceProjectId' => `$invoice ? (string)`$invoice['PROJECT_ID'] : '',
    'saleProjectInvoiceItemId' => `$invoiceItem ? (string)`$invoiceItem['ID'] : '',
    'saleProjectInvoiceItemInvoiceId' => `$invoiceItem ? (string)`$invoiceItem['INVOICE_ID'] : '',
    'saleProjectReissueOrderId' => `$reissueOrder ? (string)`$reissueOrder['ID'] : '',
    'saleProjectReissueOrderProjectId' => `$reissueOrder ? (string)`$reissueOrder['PROJECT_ID'] : '',
    'saleProjectProductInfoId' => `$productInfo ? (string)`$productInfo['ID'] : '',
    'saleProjectProductInfoTargetId' => `$productInfo ? (string)`$productInfo['TARGET_ID'] : '',
    'saleProjectProductItemRelationId' => `$productItemRelation ? (string)`$productItemRelation['ID'] : '',
    'saleProjectProductItemRelationObjectId' => `$productItemRelation ? (string)`$productItemRelation['OBJECT_ID'] : '',
], JSON_UNESCAPED_UNICODE);
"@

$sampleJson = & php -r $sampleCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($sampleJson)) {
    throw 'failed to load sample business ids'
}
$sampleJson = ([string]$sampleJson) -replace ([string][char]0xFEFF), ''

$customerId = Read-JsonPath -Json $sampleJson -Path 'customerId'
$saleProjectId = Read-JsonPath -Json $sampleJson -Path 'saleProjectId'
$customerFollowUpId = Read-JsonPath -Json $sampleJson -Path 'customerFollowUpId'
$customerFollowUpCustomerId = Read-JsonPath -Json $sampleJson -Path 'customerFollowUpCustomerId'
$saleProjectFollowUpId = Read-JsonPath -Json $sampleJson -Path 'saleProjectFollowUpId'
$saleProjectFollowUpProjectId = Read-JsonPath -Json $sampleJson -Path 'saleProjectFollowUpProjectId'
$saleProjectInvoicingId = [string](Read-JsonPath -Json $sampleJson -Path 'saleProjectInvoicingId')
$saleProjectInvoicingProjectId = [string](Read-JsonPath -Json $sampleJson -Path 'saleProjectInvoicingProjectId')
$saleProjectInvoicingCustomerId = [string](Read-JsonPath -Json $sampleJson -Path 'saleProjectInvoicingCustomerId')
$saleProjectInvoiceId = [string](Read-JsonPath -Json $sampleJson -Path 'saleProjectInvoiceId')
$saleProjectInvoiceProjectId = [string](Read-JsonPath -Json $sampleJson -Path 'saleProjectInvoiceProjectId')
$saleProjectInvoiceItemId = [string](Read-JsonPath -Json $sampleJson -Path 'saleProjectInvoiceItemId')
$saleProjectInvoiceItemInvoiceId = [string](Read-JsonPath -Json $sampleJson -Path 'saleProjectInvoiceItemInvoiceId')
$saleProjectReissueOrderId = [string](Read-JsonPath -Json $sampleJson -Path 'saleProjectReissueOrderId')
$saleProjectReissueOrderProjectId = [string](Read-JsonPath -Json $sampleJson -Path 'saleProjectReissueOrderProjectId')
$saleProjectProductInfoId = [string](Read-JsonPath -Json $sampleJson -Path 'saleProjectProductInfoId')
$saleProjectProductInfoTargetId = [string](Read-JsonPath -Json $sampleJson -Path 'saleProjectProductInfoTargetId')
$saleProjectProductItemRelationId = [string](Read-JsonPath -Json $sampleJson -Path 'saleProjectProductItemRelationId')
$saleProjectProductItemRelationObjectId = [string](Read-JsonPath -Json $sampleJson -Path 'saleProjectProductItemRelationObjectId')
$baseUrl = $BackendBaseUrl.TrimEnd('/')
$encodedCustomerId = [System.Uri]::EscapeDataString($customerId.Trim())
$encodedSaleProjectId = [System.Uri]::EscapeDataString($saleProjectId.Trim())

$customerPage = Invoke-RawGet -Url "$baseUrl/biz/customer/page?id=$encodedCustomerId&current=1&size=1" -Token $token
Assert-PagedShape -Json $customerPage -Name 'biz customer page'
Assert-CustomerRow -Json $customerPage -Prefix 'data.records.0' -Name 'biz customer page first record'

$customerDetail = Invoke-RawGet -Url "$baseUrl/biz/customer/detail?id=$encodedCustomerId" -Token $token
Assert-Ok -Json $customerDetail -Name 'biz customer detail'
Assert-CustomerRow -Json $customerDetail -Prefix 'data' -Name 'biz customer detail'

$customerDetailList = Invoke-RawPost -Url "$baseUrl/biz/customer/detail/list?id=$encodedCustomerId" -Token $token
Assert-Ok -Json $customerDetailList -Name 'biz customer detail/list'
Assert-Paths -Json $customerDetailList -Name 'biz customer detail/list' -Paths @(
    'data.0.customer.id',
    'data.0.customerFollowUps'
)
Assert-CustomerRow -Json $customerDetailList -Prefix 'data.0.customer' -Name 'biz customer detail/list customer'

$customerFollowUpPageCustomerId = if ($customerFollowUpCustomerId.Trim() -ne '') { $customerFollowUpCustomerId } else { $customerId }
$encodedCustomerFollowUpCustomerId = [System.Uri]::EscapeDataString($customerFollowUpPageCustomerId.Trim())
$customerFollowUpPage = Invoke-RawGet -Url "$baseUrl/biz/customerfollowup/page?customerId=$encodedCustomerFollowUpCustomerId&current=1&size=1" -Token $token
Assert-PagedShape -Json $customerFollowUpPage -Name 'biz customerfollowup page'
Assert-FirstRecordIfPresent -Json $customerFollowUpPage -Name 'biz customerfollowup page' -Keys @(
    'id',
    'customerId',
    'customerName',
    'followUpTime',
    'content',
    'createUserName',
    'avatar',
    'createUserOrgId',
    'createUserOrgName',
    'extJson'
)
if ($customerFollowUpId.Trim() -ne '') {
    $encodedCustomerFollowUpId = [System.Uri]::EscapeDataString($customerFollowUpId.Trim())
    $customerFollowUpDetail = Invoke-RawGet -Url "$baseUrl/biz/customerfollowup/detail?id=$encodedCustomerFollowUpId" -Token $token
    Assert-Ok -Json $customerFollowUpDetail -Name 'biz customerfollowup detail'
    Assert-CustomerFollowUpRow -Json $customerFollowUpDetail -Prefix 'data' -Name 'biz customerfollowup detail'
}

$saleProjectPage = Invoke-RawGet -Url "$baseUrl/biz/saleproject/page?id=$encodedSaleProjectId&current=1&size=1" -Token $token
Assert-PagedShape -Json $saleProjectPage -Name 'biz saleproject page'
Assert-SaleProjectRow -Json $saleProjectPage -Prefix 'data.records.0' -Name 'biz saleproject page first record'
Assert-Paths -Json $saleProjectPage -Name 'biz saleproject page return orders' -Paths @('data.records.0.returnOrders')

foreach ($path in @('case/page', 'operation/page', 'public/page')) {
    $page = Invoke-RawGet -Url "$baseUrl/biz/saleproject/${path}?current=1&size=1" -Token $token
    Assert-PagedShape -Json $page -Name "biz saleproject $path"
}

$saleProjectDetail = Invoke-RawGet -Url "$baseUrl/biz/saleproject/detail?id=$encodedSaleProjectId" -Token $token
Assert-Ok -Json $saleProjectDetail -Name 'biz saleproject detail'
Assert-SaleProjectRow -Json $saleProjectDetail -Prefix 'data.bizSaleProject' -Name 'biz saleproject detail project'
Assert-Paths -Json $saleProjectDetail -Name 'biz saleproject detail aggregates' -Paths @(
    'data.productItems',
    'data.invoicingList',
    'data.invoiceList',
    'data.paymentRecords',
    'data.saleProjectFollowUps',
    'data.changeLogs',
    'data.returnOrders'
)

$saleProjectFollowUpPageProjectId = if ($saleProjectFollowUpProjectId.Trim() -ne '') { $saleProjectFollowUpProjectId } else { $saleProjectId }
$encodedSaleProjectFollowUpProjectId = [System.Uri]::EscapeDataString($saleProjectFollowUpPageProjectId.Trim())
$saleProjectFollowUpPage = Invoke-RawGet -Url "$baseUrl/biz/saleprojectfollowup/page?projectId=$encodedSaleProjectFollowUpProjectId&current=1&size=1" -Token $token
Assert-PagedShape -Json $saleProjectFollowUpPage -Name 'biz saleprojectfollowup page'
Assert-FirstRecordIfPresent -Json $saleProjectFollowUpPage -Name 'biz saleprojectfollowup page' -Keys @(
    'id',
    'projectId',
    'projectName',
    'projectUser',
    'projectOrg',
    'followUpTime',
    'category',
    'content',
    'createUserName',
    'avatar',
    'createUserOrgId',
    'createUserOrgName',
    'extJson'
)
if ($saleProjectFollowUpId.Trim() -ne '') {
    $encodedSaleProjectFollowUpId = [System.Uri]::EscapeDataString($saleProjectFollowUpId.Trim())
    $saleProjectFollowUpDetail = Invoke-RawGet -Url "$baseUrl/biz/saleprojectfollowup/detail?id=$encodedSaleProjectFollowUpId" -Token $token
    Assert-Ok -Json $saleProjectFollowUpDetail -Name 'biz saleprojectfollowup detail'
    Assert-SaleProjectFollowUpRow -Json $saleProjectFollowUpDetail -Prefix 'data' -Name 'biz saleprojectfollowup detail'
}

$saleProjectListDetail = Invoke-RawGet -Url "$baseUrl/biz/saleproject/list/detail?id=$encodedSaleProjectId" -Token $token
Assert-Ok -Json $saleProjectListDetail -Name 'biz saleproject list/detail'
Assert-SaleProjectRow -Json $saleProjectListDetail -Prefix 'data.0.bizSaleProject' -Name 'biz saleproject list/detail project'
Assert-Paths -Json $saleProjectListDetail -Name 'biz saleproject list/detail aggregates' -Paths @('data.0.returnOrders')

$saleProjectProduct = Invoke-RawGet -Url "$baseUrl/biz/saleproject/product?id=$encodedSaleProjectId" -Token $token
Assert-Ok -Json $saleProjectProduct -Name 'biz saleproject product'
Assert-Paths -Json $saleProjectProduct -Name 'biz saleproject product' -Paths @('data')

$saleProjectCost = Invoke-RawPost -Url "$baseUrl/biz/saleproject/cost?id=$encodedSaleProjectId" -Token $token
Assert-Ok -Json $saleProjectCost -Name 'biz saleproject cost'
Assert-Paths -Json $saleProjectCost -Name 'biz saleproject cost' -Paths @('data')

$saleProjectCostDetails = Invoke-RawPost -Url "$baseUrl/biz/saleproject/cost/details?id=$encodedSaleProjectId" -Token $token
Assert-Ok -Json $saleProjectCostDetails -Name 'biz saleproject cost/details'
Assert-Paths -Json $saleProjectCostDetails -Name 'biz saleproject cost/details' -Paths @(
    'data.items',
    'data.productItems',
    'data.returnOrders'
)

$saleProjectInvoicingPage = Invoke-RawGet -Url "$baseUrl/biz/saleprojectinvoicing/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $saleProjectInvoicingPage -Name 'biz saleprojectinvoicing page'
Assert-FirstRecordIfPresent -Json $saleProjectInvoicingPage -Name 'biz saleprojectinvoicing page' -Keys @(
    'id',
    'projectId',
    'amount',
    'invoicingState',
    'invoicingCategory',
    'companyName',
    'customerCompany',
    'unit',
    'phone',
    'projectName',
    'customerName'
)
if ($saleProjectInvoicingId.Trim() -ne '') {
    $encodedSaleProjectInvoicingId = [System.Uri]::EscapeDataString($saleProjectInvoicingId.Trim())
    $saleProjectInvoicingDetail = Invoke-RawGet -Url "$baseUrl/biz/saleprojectinvoicing/detail?id=$encodedSaleProjectInvoicingId" -Token $token
    Assert-Ok -Json $saleProjectInvoicingDetail -Name 'biz saleprojectinvoicing detail'
    Assert-SaleProjectInvoicingRow -Json $saleProjectInvoicingDetail -Prefix 'data' -Name 'biz saleprojectinvoicing detail'
}
if ($saleProjectInvoicingCustomerId.Trim() -ne '') {
    $encodedSaleProjectInvoicingCustomerId = [System.Uri]::EscapeDataString($saleProjectInvoicingCustomerId.Trim())
    $saleProjectInvoicingCustomer = Invoke-RawGet -Url "$baseUrl/biz/saleprojectinvoicing/customer?id=$encodedSaleProjectInvoicingCustomerId" -Token $token
    Assert-Ok -Json $saleProjectInvoicingCustomer -Name 'biz saleprojectinvoicing customer'
    Assert-SaleProjectInvoicingRow -Json $saleProjectInvoicingCustomer -Prefix 'data' -Name 'biz saleprojectinvoicing customer'
}

$saleProjectInvoicePage = Invoke-RawGet -Url "$baseUrl/biz/saleprojectinvoice/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $saleProjectInvoicePage -Name 'biz saleprojectinvoice page'
Assert-FirstRecordIfPresent -Json $saleProjectInvoicePage -Name 'biz saleprojectinvoice page' -Keys @(
    'id',
    'projectId',
    'logisticsCategory',
    'phone',
    'logisticsId',
    'freight',
    'freightTime',
    'freightCategory',
    'unit',
    'address',
    'projectName',
    'customerName'
)

$saleProjectInvoiceListProjectId = if ($saleProjectInvoiceProjectId.Trim() -ne '') { $saleProjectInvoiceProjectId } else { $saleProjectId }
$encodedSaleProjectInvoiceListProjectId = [System.Uri]::EscapeDataString($saleProjectInvoiceListProjectId.Trim())
$saleProjectInvoiceList = Invoke-RawGet -Url "$baseUrl/biz/saleprojectinvoice/list?projectId=$encodedSaleProjectInvoiceListProjectId" -Token $token
Assert-Ok -Json $saleProjectInvoiceList -Name 'biz saleprojectinvoice list'
Assert-Paths -Json $saleProjectInvoiceList -Name 'biz saleprojectinvoice list' -Paths @('data')
if (Has-Path -Json $saleProjectInvoiceList -Path 'data.0') {
    Assert-SaleProjectInvoiceRow -Json $saleProjectInvoiceList -Prefix 'data.0.bizSaleProjectInvoice' -Name 'biz saleprojectinvoice list invoice'
    Assert-Paths -Json $saleProjectInvoiceList -Name 'biz saleprojectinvoice list nested items' -Paths @('data.0.invoiceItems')
}

$saleProjectInvoiceItemPage = Invoke-RawGet -Url "$baseUrl/biz/saleprojectinvoiceItem/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $saleProjectInvoiceItemPage -Name 'biz saleprojectinvoiceItem page'
Assert-FirstRecordIfPresent -Json $saleProjectInvoiceItemPage -Name 'biz saleprojectinvoiceItem page' -Keys @(
    'id',
    'invoiceId',
    'projectProductItemId',
    'warehousesId',
    'amount',
    'projectId',
    'productId',
    'productName',
    'warehousesName'
)
if ($saleProjectInvoiceItemInvoiceId.Trim() -ne '') {
    $encodedSaleProjectInvoiceItemInvoiceId = [System.Uri]::EscapeDataString($saleProjectInvoiceItemInvoiceId.Trim())
    $saleProjectInvoiceItemByInvoice = Invoke-RawGet -Url "$baseUrl/biz/saleprojectinvoiceItem/page?invoiceId=$encodedSaleProjectInvoiceItemInvoiceId&current=1&size=1" -Token $token
    Assert-PagedShape -Json $saleProjectInvoiceItemByInvoice -Name 'biz saleprojectinvoiceItem page by invoice'
    Assert-SaleProjectInvoiceItemRow -Json $saleProjectInvoiceItemByInvoice -Prefix 'data.records.0' -Name 'biz saleprojectinvoiceItem page by invoice first record'
}

$saleProjectReissueProjectId = if ($saleProjectReissueOrderProjectId.Trim() -ne '') { $saleProjectReissueOrderProjectId } else { $saleProjectId }
$encodedSaleProjectReissueProjectId = [System.Uri]::EscapeDataString($saleProjectReissueProjectId.Trim())
$saleProjectReissueList = Invoke-RawGet -Url "$baseUrl/biz/saleprojectreissueorder/list/query?projectId=$encodedSaleProjectReissueProjectId" -Token $token
Assert-Ok -Json $saleProjectReissueList -Name 'biz saleprojectreissueorder list/query'
Assert-Paths -Json $saleProjectReissueList -Name 'biz saleprojectreissueorder list/query' -Paths @('data')
if (Has-Path -Json $saleProjectReissueList -Path 'data.0') {
    Assert-ReissueOrderRow -Json $saleProjectReissueList -Prefix 'data.0.order' -Name 'biz saleprojectreissueorder list/query order'
    Assert-Paths -Json $saleProjectReissueList -Name 'biz saleprojectreissueorder list/query nested items' -Paths @('data.0.productItemList')
}

$saleProjectProductInfoPage = Invoke-RawGet -Url "$baseUrl/biz/saleprojectproductinfo/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $saleProjectProductInfoPage -Name 'biz saleprojectproductinfo page'
Assert-FirstRecordIfPresent -Json $saleProjectProductInfoPage -Name 'biz saleprojectproductinfo page' -Keys @(
    'id',
    'productId',
    'targetId',
    'contentText',
    'alias',
    'versionType',
    'abbreviation',
    'deleteFlag',
    'extJson',
    'createTime',
    'createUserName',
    'productName',
    'targetProductName'
)
if ($saleProjectProductInfoId.Trim() -ne '') {
    $encodedSaleProjectProductInfoId = [System.Uri]::EscapeDataString($saleProjectProductInfoId.Trim())
    $saleProjectProductInfoDetail = Invoke-RawGet -Url "$baseUrl/biz/saleprojectproductinfo/detail?id=$encodedSaleProjectProductInfoId" -Token $token
    Assert-Ok -Json $saleProjectProductInfoDetail -Name 'biz saleprojectproductinfo detail'
    Assert-SaleProjectProductInfoRow -Json $saleProjectProductInfoDetail -Prefix 'data' -Name 'biz saleprojectproductinfo detail'
}

$saleProjectProductInfoTargetId = if ($saleProjectProductInfoTargetId.Trim() -ne '') { $saleProjectProductInfoTargetId } else { '__codex_missing_target_id__' }
$encodedSaleProjectProductInfoTargetId = [System.Uri]::EscapeDataString($saleProjectProductInfoTargetId.Trim())
$saleProjectProductInfoList = Invoke-RawGet -Url "$baseUrl/biz/saleprojectproductinfo/list?targetIds=$encodedSaleProjectProductInfoTargetId" -Token $token
Assert-Ok -Json $saleProjectProductInfoList -Name 'biz saleprojectproductinfo list'
Assert-Paths -Json $saleProjectProductInfoList -Name 'biz saleprojectproductinfo list' -Paths @('data')
if (Has-Path -Json $saleProjectProductInfoList -Path 'data.0') {
    Assert-SaleProjectProductInfoRow -Json $saleProjectProductInfoList -Prefix 'data.0' -Name 'biz saleprojectproductinfo list first row'
}

$saleProjectProductItemRelationObjectId = if ($saleProjectProductItemRelationObjectId.Trim() -ne '') { $saleProjectProductItemRelationObjectId } else { '__codex_missing_product_item_id__' }
$relationBody = '[{"id":"' + $saleProjectProductItemRelationObjectId.Replace('\', '\\').Replace('"', '\"') + '"}]'
$saleProjectProductItemRelationList = Invoke-RawPost -Url "$baseUrl/biz/saleprojectproductitemrelation/list" -Token $token -Body $relationBody
Assert-Ok -Json $saleProjectProductItemRelationList -Name 'biz saleprojectproductitemrelation list'
Assert-Paths -Json $saleProjectProductItemRelationList -Name 'biz saleprojectproductitemrelation list' -Paths @('data')
if (Has-Path -Json $saleProjectProductItemRelationList -Path 'data.0') {
    Assert-SaleProjectProductItemRelationRow -Json $saleProjectProductItemRelationList -Prefix 'data.0' -Name 'biz saleprojectproductitemrelation list first row'
}

Write-Host 'business read HTTP smoke passed'
