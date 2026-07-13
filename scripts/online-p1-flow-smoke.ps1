param(
    [string]$FrontendBaseUrl = 'https://oa.fucity.cn',
    [string]$ApiPrefix = '/backend',
    [string]$TenantId = '2018244380532912130',
    [string]$Password = '123456',
    [string]$AdminAccount = 'superAdminTwo',
    [string]$PayrollAccount = 'cszjb001',
    [string]$FileAccount = 'superAdmin',
    [string]$SshHost = '120.24.76.240',
    [int]$SshPort = 22,
    [string]$SshUser = 'root',
    [string]$SshKeyPath = 'C:\Users\Win10\.ssh\oa_fucity_deploy',
    [string]$RemoteRoot = '/www/wwwroot/oa.fucity.cn',
    [string]$RemotePhpBin = '/www/server/php/83/bin/php'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $ProjectRoot

$baseUrl = $FrontendBaseUrl.TrimEnd('/') + '/' + $ApiPrefix.Trim('/')
$marker = 'CODEX_P1_SMOKE_' + (Get-Date -Format 'yyyyMMddHHmmss')
$seed = 'P1' + (Get-Date -Format 'MMddHHmmss')

$ids = [ordered]@{
    invWarehouse = "WI$seed"
    invProductA = "IA$seed"
    invProductB = "IB$seed"
    poWarehouse = "WP$seed"
    poProductA = "PA$seed"
    poProductB = "PB$seed"
    purchaseOrder = "PO$seed"
    purchaseItemA = "PIA$seed"
    purchaseItemB = "PIB$seed"
    payrollExport = "PEX$seed"
    payrollImportUser = "PIU$seed"
}

$results = New-Object System.Collections.Generic.List[object]

function Add-Result {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string]$Status,
        [string]$Detail = ''
    )

    $script:results.Add([pscustomobject]@{
        name = $Name
        status = $Status
        detail = $Detail
    }) | Out-Null
}

function Invoke-Step {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][scriptblock]$Action
    )

    Write-Host "==> $Name"
    try {
        & $Action
        Add-Result -Name $Name -Status 'PASS'
        Write-Host "PASS $Name"
    } catch {
        Add-Result -Name $Name -Status 'FAIL' -Detail $_.Exception.Message
        Write-Host "FAIL $Name"
        Write-Host $_.Exception.Message
        throw
    }
}

function Invoke-OaApi {
    param(
        [Parameter(Mandatory = $true)][string]$Method,
        [Parameter(Mandatory = $true)][string]$Path,
        [hashtable]$Headers = @{},
        [object]$Body = $null,
        [switch]$AllowFailure
    )

    $uri = $script:baseUrl + $Path
    $allHeaders = @{}
    foreach ($key in $Headers.Keys) {
        $allHeaders[$key] = $Headers[$key]
    }
    if (-not $allHeaders.ContainsKey('tenantId')) {
        $allHeaders['tenantId'] = $script:TenantId
    }

    try {
        if ($Method.ToUpperInvariant() -eq 'GET') {
            $response = Invoke-RestMethod -Method Get -Uri $uri -Headers $allHeaders
        } else {
            $json = if ($null -eq $Body) { '{}' } else { ConvertTo-Json -InputObject $Body -Depth 40 -Compress }
            $response = Invoke-RestMethod -Method $Method -Uri $uri -Headers $allHeaders -ContentType 'application/json' -Body $json
        }
    } catch {
        if ($AllowFailure) {
            return [pscustomobject]@{
                code = 'http-error'
                msg = $_.Exception.Message
                data = $null
            }
        }
        throw
    }

    if (-not $AllowFailure -and [int]$response.code -ne 200) {
        throw "$Method $Path failed: code=$($response.code) msg=$($response.msg)"
    }

    return $response
}

function New-Session {
    param([Parameter(Mandatory = $true)][string]$Account)

    $login = Invoke-OaApi -Method POST -Path '/auth/b/doLogin' -Body @{
        account = $Account
        password = $script:Password
        tenantId = $script:TenantId
        device = 'CODEX_ONLINE_P1_FLOW_SMOKE'
    }
    if ([string]::IsNullOrWhiteSpace([string]$login.data)) {
        throw "login returned empty token for $Account"
    }

    $headers = @{
        Authorization = "Bearer $($login.data)"
        tenantId = $script:TenantId
    }
    $user = Invoke-OaApi -Method GET -Path '/auth/b/getLoginUser' -Headers $headers

    return [pscustomobject]@{
        Account = $Account
        Headers = $headers
        User = $user.data.user
    }
}

function New-RemoteTokenSession {
    param([Parameter(Mandatory = $true)][string]$Account)

    $safeAccount = $Account.Replace("'", "\'")
    $safeTenantId = $TenantId.Replace("'", "\'")
    $session = Invoke-RemotePhpJson -Code @"
<?php
require 'vendor/autoload.php';
`$app = new think\App();
`$app->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->where('TENANT_ID', '$safeTenantId')->find();
if (!is_array(`$user) || `$user === []) {
    `$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
}
if (!is_array(`$user) || `$user === []) { throw new RuntimeException('remote token account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_ONLINE_P1_FLOW_SMOKE_REMOTE_TOKEN';
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => (string)(`$user['ID'] ?? ''),
    'account' => (string)(`$user['ACCOUNT'] ?? ''),
], JSON_UNESCAPED_UNICODE), PHP_EOL;
"@
    if ([string]::IsNullOrWhiteSpace([string]$session.token)) {
        throw "remote token returned empty token for $Account"
    }

    $headers = @{
        Authorization = "Bearer $($session.token)"
        tenantId = $script:TenantId
    }
    $user = Invoke-OaApi -Method GET -Path '/auth/b/getLoginUser' -Headers $headers

    return [pscustomobject]@{
        Account = $Account
        Headers = $headers
        User = $user.data.user
    }
}

function Invoke-RemotePhpJson {
    param([Parameter(Mandatory = $true)][string]$Code)

    $ssh = 'C:\Windows\System32\OpenSSH\ssh.exe'
    if (-not (Test-Path -LiteralPath $ssh)) {
        $ssh = 'ssh'
    }
    if (-not (Test-Path -LiteralPath $SshKeyPath)) {
        throw "SSH key not found: $SshKeyPath"
    }

    $target = "$SshUser@$SshHost"
    $remoteCommand = "cd $RemoteRoot && $RemotePhpBin"
    $previousErrorActionPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $raw = $Code | & $ssh -i $SshKeyPath -p $SshPort -o StrictHostKeyChecking=accept-new $target $remoteCommand 2>&1
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }
    if ($LASTEXITCODE -ne 0) {
        throw "remote php failed: $($raw -join "`n")"
    }

    $text = ($raw | ForEach-Object { [string]$_ }) -join "`n"
    $text = $text.TrimStart([char]0xFEFF).Trim()
    if ($text -eq '') {
        throw 'remote php returned empty output'
    }

    $jsonStart = $text.IndexOf('{')
    if ($jsonStart -lt 0) {
        throw "remote php returned non-json output: $text"
    }

    return $text.Substring($jsonStart) | ConvertFrom-Json
}

function ConvertTo-PhpStringArray {
    param([string[]]$Values)

    $items = @()
    foreach ($value in $Values) {
        if (-not [string]::IsNullOrWhiteSpace($value)) {
            $items += "'" + ([string]$value).Replace("'", "\'") + "'"
        }
    }

    return '[' + ($items -join ', ') + ']'
}

function Assert-Equal {
    param(
        [object]$Actual,
        [object]$Expected,
        [string]$Name
    )
    if ([string]$Actual -ne [string]$Expected) {
        throw "$Name expected '$Expected', got '$Actual'"
    }
}

function Assert-IntEqual {
    param(
        [object]$Actual,
        [int]$Expected,
        [string]$Name
    )
    if ([int]$Actual -ne $Expected) {
        throw "$Name expected '$Expected', got '$Actual'"
    }
}

function Invoke-Download {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [hashtable]$Headers = @{}
    )

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-p1-download-{0}.body" -f ([Guid]::NewGuid().ToString('N')))
    $headerPath = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-p1-download-{0}.headers" -f ([Guid]::NewGuid().ToString('N')))
    $args = @('-sS', '-D', $headerPath, '-o', $bodyPath, '-w', '%{http_code}', $Url)
    foreach ($key in $Headers.Keys) {
        $args += @('-H', "$key`: $($Headers[$key])")
    }

    $status = & curl.exe @args
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP download failed: $Url"
    }

    return [pscustomobject]@{
        Status = [int]([string]::Join('', [string[]]$status))
        BodyPath = $bodyPath
        HeaderPath = $headerPath
    }
}

function Invoke-MultipartUpload {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$File,
        [hashtable]$Headers = @{},
        [hashtable]$Fields = @{}
    )

    $fileField = "file=@$File"
    if ([System.IO.Path]::GetExtension($File).Equals('.xlsx', [System.StringComparison]::OrdinalIgnoreCase)) {
        $fileField += ';type=application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    }
    $args = @('-sS', '-X', 'POST', $Url, '-F', $fileField)
    foreach ($key in $Fields.Keys) {
        $args += @('-F', "$key=$($Fields[$key])")
    }
    foreach ($key in $Headers.Keys) {
        $args += @('-H', "$key`: $($Headers[$key])")
    }

    $raw = & curl.exe @args
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP multipart POST failed: $Url"
    }

    $text = [string]::Join('', [string[]]$raw)
    return $text | ConvertFrom-Json
}

function Escape-Xml {
    param([Parameter(Mandatory = $true)][AllowEmptyString()][string]$Value)

    return [System.Security.SecurityElement]::Escape($Value)
}

function New-InlineCell {
    param(
        [Parameter(Mandatory = $true)][string]$Ref,
        [Parameter(Mandatory = $true)][AllowEmptyString()][string]$Value
    )

    return '<c r="{0}" t="inlineStr"><is><t>{1}</t></is></c>' -f $Ref, (Escape-Xml -Value $Value)
}

function New-NumberCell {
    param(
        [Parameter(Mandatory = $true)][string]$Ref,
        [Parameter(Mandatory = $true)][string]$Value
    )

    return '<c r="{0}"><v>{1}</v></c>' -f $Ref, $Value
}

function New-PayrollMonthTitle {
    param(
        [Parameter(Mandatory = $true)][int]$Year,
        [Parameter(Mandatory = $true)][int]$Month
    )

    $yearChar = [char]0x5E74
    $monthChar = [char]0x6708
    $workChar = [char]0x5DE5
    $salaryChar = [char]0x8D44
    $tableChar = [char]0x8868

    return ('{0}{1}{2}{3}{4}{5}{6}' -f $Year, $yearChar, $Month, $monthChar, $workChar, $salaryChar, $tableChar)
}

function New-PayrollImportXlsx {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$UserName,
        [Parameter(Mandatory = $true)][string]$Remark
    )

    $templatePath = Join-Path $script:ProjectRoot 'app\resources\biz\payroll\userPayrollTemplate.xlsx'
    if (-not (Test-Path -LiteralPath $templatePath)) {
        throw "payroll import template not found: $templatePath"
    }
    if (Test-Path -LiteralPath $Path) {
        Remove-Item -LiteralPath $Path -Force
    }
    Copy-Item -LiteralPath $templatePath -Destination $Path -Force

    $title = New-PayrollMonthTitle -Year 2026 -Month 6
    $rows = New-Object System.Collections.Generic.List[string]
    $rows.Add('<row r="1">' + (New-InlineCell -Ref 'A1' -Value $title) + '</row>')
    $rows.Add('<row r="2">' + (New-InlineCell -Ref 'A2' -Value 'org') + (New-InlineCell -Ref 'B2' -Value 'no') + (New-InlineCell -Ref 'C2' -Value 'name') + '</row>')
    $rows.Add('<row r="3">' + (New-InlineCell -Ref 'D3' -Value 'base') + '</row>')

    $cells = New-Object System.Collections.Generic.List[string]
    $cells.Add((New-InlineCell -Ref 'A4' -Value 'Smoke Org'))
    $cells.Add((New-InlineCell -Ref 'B4' -Value '1'))
    $cells.Add((New-InlineCell -Ref 'C4' -Value $UserName))
    $values = @{
        D = '1000.25'; E = '200.00'; F = '30.00'; G = '10.00'; H = '40.00'; I = '50.00'
        J = '60.00'; K = '70.00'; L = '1320.25'; M = '1100.00'; N = '900.00'; O = '20.00'
        P = '30.00'; Q = '400.00'; R = '15.00'; S = '45.00'; T = '80.00'; U = '25.00'
        V = '300.00'; W = '1700.00'; X = '12.00'; Y = '123.45'; Z = '1564.55'
        AA = '1000.00'; AB = '564.55'
    }
    foreach ($column in @('D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB')) {
        $cells.Add((New-NumberCell -Ref ("$column`4") -Value ([string]$values[$column])))
    }
    $cells.Add((New-InlineCell -Ref 'AC4' -Value $Remark))
    $rows.Add('<row r="4">{0}</row>' -f ([string]::Join('', $cells)))

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' +
        ([string]::Join('', $rows)) +
        '</sheetData></worksheet>'

    $zip = [System.IO.Compression.ZipFile]::Open($Path, [System.IO.Compression.ZipArchiveMode]::Update)
    try {
        $entry = $zip.GetEntry('xl/worksheets/sheet1.xml')
        if ($null -ne $entry) {
            $entry.Delete()
        }
        $entry = $zip.CreateEntry('xl/worksheets/sheet1.xml')
        $stream = $entry.Open()
        try {
            $writer = [System.IO.StreamWriter]::new($stream, [System.Text.UTF8Encoding]::new($false))
            try {
                $writer.Write($sheetXml)
            } finally {
                $writer.Dispose()
            }
        } finally {
            $stream.Dispose()
        }
    } finally {
        $zip.Dispose()
    }
}

function New-RemoteCleanupCode {
    $warehouseIds = ConvertTo-PhpStringArray -Values @($script:ids.invWarehouse, $script:ids.poWarehouse)
    $productIds = ConvertTo-PhpStringArray -Values @($script:ids.invProductA, $script:ids.invProductB, $script:ids.poProductA, $script:ids.poProductB)
    $orderIds = ConvertTo-PhpStringArray -Values @($script:ids.purchaseOrder)
    $payrollIds = ConvertTo-PhpStringArray -Values @($script:ids.payrollExport)
    $userIds = ConvertTo-PhpStringArray -Values @($script:ids.payrollImportUser)
    $safeMarker = $script:marker.Replace("'", "\'")

@"
<?php
require 'vendor/autoload.php';
`$app = new think\App();
`$app->initialize();

`$warehouseIds = $warehouseIds;
`$productIds = $productIds;
`$orderIds = $orderIds;
`$payrollIds = $payrollIds;
`$userIds = $userIds;

think\facade\Db::transaction(function () use (`$warehouseIds, `$productIds, `$orderIds, `$payrollIds, `$userIds): void {
    if (`$orderIds !== []) {
        think\facade\Db::name('delivery_record')->whereIn('OBJECT_ID', `$orderIds)->delete();
        think\facade\Db::name('biz_purchase_order_item')->whereIn('PURCHASE_ORDER_ID', `$orderIds)->delete();
        think\facade\Db::name('biz_purchase_order')->whereIn('ID', `$orderIds)->delete();
    }
    if (`$warehouseIds !== []) {
        think\facade\Db::name('inventory')->whereIn('WAREHOUSES_ID', `$warehouseIds)->delete();
        think\facade\Db::name('warehouses')->whereIn('ID', `$warehouseIds)->delete();
    }
    if (`$productIds !== []) {
        think\facade\Db::name('biz_product')->whereIn('ID', `$productIds)->delete();
    }
    if (`$payrollIds !== []) {
        think\facade\Db::name('biz_payroll')->whereIn('ID', `$payrollIds)->delete();
    }
    if (`$userIds !== []) {
        think\facade\Db::name('biz_payroll')->whereIn('USER', `$userIds)->delete();
        think\facade\Db::name('sys_user')->whereIn('ID', `$userIds)->delete();
    }
    think\facade\Db::name('biz_payroll')->whereLike('REMARK', '$safeMarker%')->delete();
});

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE), PHP_EOL;
"@
}

function New-RemoteSetupInventoryCode {
    $safeTenantId = $TenantId.Replace("'", "\'")
    $safeAdmin = $AdminAccount.Replace("'", "\'")
    $safeMarker = $marker.Replace("'", "\'")
    $safeWarehouse = $ids.invWarehouse.Replace("'", "\'")
    $safeProductA = $ids.invProductA.Replace("'", "\'")
    $safeProductB = $ids.invProductB.Replace("'", "\'")

@"
<?php
require 'vendor/autoload.php';
`$app = new think\App();
`$app->initialize();

`$tenantId = '$safeTenantId';
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAdmin')->where('TENANT_ID', `$tenantId)->find();
if (!is_array(`$user) || `$user === []) { throw new RuntimeException('admin user not found'); }
`$userId = (string)(`$user['ID'] ?? '');
`$orgId = (string)(`$user['ORG_ID'] ?? '');
`$now = date('Y-m-d H:i:s');

think\facade\Db::transaction(function () use (`$tenantId, `$userId, `$orgId, `$now): void {
    think\facade\Db::name('warehouses')->insert([
        'ID' => '$safeWarehouse',
        'NAME' => '$safeMarker inventory warehouse',
        'CODE' => '$safeWarehouse',
        'ADDRESS' => 'codex p1 smoke',
        'SORT_CODE' => 999,
        'USER' => `$userId,
        'ORG' => `$orgId !== '' ? `$orgId : null,
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => `$userId,
        'UPDATE_TIME' => null,
        'UPDATE_USER' => null,
        'TENANT_ID' => `$tenantId,
    ]);
    foreach (['$safeProductA' => 'A', '$safeProductB' => 'B'] as `$productId => `$label) {
        think\facade\Db::name('biz_product')->insert([
            'ID' => `$productId,
            'PRODUCT_NAME' => '$safeMarker inventory product ' . `$label,
            'PRODUCT_CATEGORY' => 'SMOKE',
            'SAFETY_STOCK' => 0,
            'PURCHASE_PRICE' => '0.00',
            'SALE_PRICE' => '0.00',
            'MIN_PRICE' => '0.00',
            'CATEGORY' => 'SINGLE_PRODUCT',
            'DELETE_FLAG' => 'NOT_DELETE',
            'CREATE_TIME' => `$now,
            'CREATE_USER' => `$userId,
            'UPDATE_TIME' => null,
            'UPDATE_USER' => null,
            'TENANT_ID' => `$tenantId,
            'SPECS' => 'smoke',
            'ORG' => `$orgId !== '' ? `$orgId : null,
            'status' => 'ENABLE',
        ]);
    }
});

echo json_encode(['ok' => true, 'warehouseId' => '$safeWarehouse'], JSON_UNESCAPED_UNICODE), PHP_EOL;
"@
}

function New-RemoteStateInventoryCode {
    $safeWarehouse = $ids.invWarehouse.Replace("'", "\'")

@"
<?php
require 'vendor/autoload.php';
`$app = new think\App();
`$app->initialize();
`$rows = think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$safeWarehouse')->select()->toArray();
echo json_encode(['count' => count(`$rows), 'rows' => `$rows], JSON_UNESCAPED_UNICODE), PHP_EOL;
"@
}

function New-RemoteSetupPurchaseCode {
    $safeTenantId = $TenantId.Replace("'", "\'")
    $safeAdmin = $AdminAccount.Replace("'", "\'")
    $safeMarker = $marker.Replace("'", "\'")
    $safeWarehouse = $ids.poWarehouse.Replace("'", "\'")
    $safeProductA = $ids.poProductA.Replace("'", "\'")
    $safeProductB = $ids.poProductB.Replace("'", "\'")
    $safeOrder = $ids.purchaseOrder.Replace("'", "\'")
    $safeItemA = $ids.purchaseItemA.Replace("'", "\'")
    $safeItemB = $ids.purchaseItemB.Replace("'", "\'")

@"
<?php
require 'vendor/autoload.php';
`$app = new think\App();
`$app->initialize();

`$tenantId = '$safeTenantId';
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAdmin')->where('TENANT_ID', `$tenantId)->find();
if (!is_array(`$user) || `$user === []) { throw new RuntimeException('admin user not found'); }
`$userId = (string)(`$user['ID'] ?? '');
`$orgId = (string)(`$user['ORG_ID'] ?? '');
`$now = date('Y-m-d H:i:s');

think\facade\Db::transaction(function () use (`$tenantId, `$userId, `$orgId, `$now): void {
    think\facade\Db::name('warehouses')->insert([
        'ID' => '$safeWarehouse',
        'NAME' => '$safeMarker purchase warehouse',
        'CODE' => '$safeWarehouse',
        'ADDRESS' => 'codex p1 smoke',
        'SORT_CODE' => 999,
        'USER' => `$userId,
        'ORG' => `$orgId !== '' ? `$orgId : null,
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => `$userId,
        'UPDATE_TIME' => null,
        'UPDATE_USER' => null,
        'TENANT_ID' => `$tenantId,
    ]);
    foreach (['$safeProductA' => 'A', '$safeProductB' => 'B'] as `$productId => `$label) {
        think\facade\Db::name('biz_product')->insert([
            'ID' => `$productId,
            'PRODUCT_NAME' => '$safeMarker purchase product ' . `$label,
            'PRODUCT_CATEGORY' => 'SMOKE',
            'SAFETY_STOCK' => 0,
            'PURCHASE_PRICE' => '0.00',
            'SALE_PRICE' => '0.00',
            'MIN_PRICE' => '0.00',
            'CATEGORY' => 'SINGLE_PRODUCT',
            'DELETE_FLAG' => 'NOT_DELETE',
            'CREATE_TIME' => `$now,
            'CREATE_USER' => `$userId,
            'UPDATE_TIME' => null,
            'UPDATE_USER' => null,
            'TENANT_ID' => `$tenantId,
            'SPECS' => 'smoke',
            'ORG' => `$orgId !== '' ? `$orgId : null,
            'status' => 'ENABLE',
        ]);
    }
    think\facade\Db::name('biz_purchase_order')->insert([
        'ID' => '$safeOrder',
        'TITLE' => '$safeOrder',
        'SUPPLIER_ID' => '',
        'INSTANCE_ID' => '',
        'DESIRE_PURCHASE_DATE' => `$now,
        'AMOUNT' => '80.00',
        'REMARK' => '$safeOrder',
        'EXT_JSON' => json_encode(['supplier' => ['name' => '$safeOrder']], JSON_UNESCAPED_SLASHES),
        'SETTLEMENT_STATUS' => 'NOT_COMPLETED',
        'STORAGE_STATUS' => 'NOT_IN_WAREHOUSE',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => `$userId,
        'UPDATE_TIME' => null,
        'UPDATE_USER' => null,
        'TENANT_ID' => `$tenantId,
        'VERSION' => 0,
        'ORG' => `$orgId,
    ]);
    `$itemBase = [
        'AMOUNT' => '10.00',
        'UNIT_AMOUNT' => '10.00',
        'DISCOUNT_RATE' => '0.00',
        'REMARK' => '$safeOrder',
        'EXT_JSON' => null,
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => `$userId,
        'UPDATE_TIME' => null,
        'UPDATE_USER' => null,
        'TENANT_ID' => `$tenantId,
        'VERSION' => 0,
        'FREIGHT_SHARE_AMOUNT' => '0.00',
        'UNIT_COST_WITH_FREIGHT' => '10.00',
    ];
    think\facade\Db::name('biz_purchase_order_item')->insert(array_merge(`$itemBase, [
        'ID' => '$safeItemA',
        'PURCHASE_ORDER_ID' => '$safeOrder',
        'STORAGE_STATUS' => 'NOT_IN_WAREHOUSE',
        'PRODUCT_ID' => '$safeProductA',
        'NUMBER' => 3,
    ]));
    think\facade\Db::name('biz_purchase_order_item')->insert(array_merge(`$itemBase, [
        'ID' => '$safeItemB',
        'PURCHASE_ORDER_ID' => '$safeOrder',
        'STORAGE_STATUS' => 'NOT_IN_WAREHOUSE',
        'PRODUCT_ID' => '$safeProductB',
        'NUMBER' => 5,
    ]));
});

echo json_encode(['ok' => true, 'orderId' => '$safeOrder'], JSON_UNESCAPED_UNICODE), PHP_EOL;
"@
}

function New-RemoteStatePurchaseCode {
    $safeWarehouse = $ids.poWarehouse.Replace("'", "\'")
    $safeOrder = $ids.purchaseOrder.Replace("'", "\'")

@"
<?php
require 'vendor/autoload.php';
`$app = new think\App();
`$app->initialize();
`$order = think\facade\Db::name('biz_purchase_order')->where('ID', '$safeOrder')->find();
`$items = think\facade\Db::name('biz_purchase_order_item')->where('PURCHASE_ORDER_ID', '$safeOrder')->select()->toArray();
`$inventory = think\facade\Db::name('inventory')->where('WAREHOUSES_ID', '$safeWarehouse')->select()->toArray();
`$delivery = think\facade\Db::name('delivery_record')->where('OBJECT_ID', '$safeOrder')->select()->toArray();
echo json_encode(['order' => `$order, 'items' => `$items, 'inventory' => `$inventory, 'delivery' => `$delivery], JSON_UNESCAPED_UNICODE), PHP_EOL;
"@
}

function New-RemoteSetupPayrollExportCode {
    $safeTenantId = $TenantId.Replace("'", "\'")
    $safePayrollAccount = $PayrollAccount.Replace("'", "\'")
    $safeMarker = $marker.Replace("'", "\'")
    $safePayrollId = $ids.payrollExport.Replace("'", "\'")

@"
<?php
require 'vendor/autoload.php';
`$app = new think\App();
`$app->initialize();

`$tenantId = '$safeTenantId';
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safePayrollAccount')->where('TENANT_ID', `$tenantId)->find();
if (!is_array(`$user) || `$user === []) { throw new RuntimeException('payroll user not found'); }
`$userId = (string)(`$user['ID'] ?? '');
`$orgId = (string)(`$user['ORG_ID'] ?? '');
if (`$orgId === '') {
    `$orgId = (string)(think\facade\Db::name('sys_org')->where(function (`$query) {
        `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
    })->value('ID') ?? '');
}

think\facade\Db::name('biz_payroll')->insert([
    'ID' => '$safePayrollId',
    'SENIORITY_SALARY' => '10.00',
    'PERFORMANCE_SALARY' => '20.00',
    'WORK_SALARY' => '30.00',
    'BASIC_SALARY' => '1000.00',
    'POST_WAGE' => '200.00',
    'RENT_SUBSIDIES' => '50.00',
    'MEAL_ALLOWANCE' => '60.00',
    'DORMITORY_RENT' => '70.00',
    'BASE_AMOUNT' => '1300.00',
    'TRANSACTION_VOLUME' => '5000.00',
    'RECEIVED_AMOUNT' => '4500.00',
    'TAX_FREIGHT' => '100.00',
    'MONTHLY_COMMISSION' => '300.00',
    'BEFORE_RECEIVED_AMOUNT' => '1200.00',
    'BEFORE_COMMISSION' => '80.00',
    'RATE_COMMISSION' => '0.00',
    'TOTAL_COMMISSION' => '380.00',
    'MERIT_BONUSES' => '90.00',
    'VACATION' => '0.00',
    'VACATION_SUB_AMOUNT' => '0.00',
    'YEAR_END_BONUS' => '0.00',
    'PAYABLE_AMOUNT' => '1770.00',
    'PERSONAL_INCOME_TAX' => '30.00',
    'SOCIAL_SECURITY' => '40.00',
    'ACTUAL_AMOUNT' => '1700.00',
    'PUBLIC_ACCOUNT' => '1000.00',
    'PRIVATE_ACCOUNT' => '700.00',
    'SALARY_TIME' => '2026-05-01 00:00:00',
    'USER' => `$userId,
    'ORG' => `$orgId,
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => date('Y-m-d H:i:s'),
    'CREATE_USER' => `$userId,
    'TENANT_ID' => `$tenantId,
    'REMARK' => '$safeMarker payroll export row',
]);

echo json_encode(['ok' => true, 'payrollId' => '$safePayrollId'], JSON_UNESCAPED_UNICODE), PHP_EOL;
"@
}

function New-RemoteSetupPayrollImportUserCode {
    $safeTenantId = $TenantId.Replace("'", "\'")
    $safePayrollAccount = $PayrollAccount.Replace("'", "\'")
    $safeMarker = $marker.Replace("'", "\'")
    $safeUserId = $ids.payrollImportUser.Replace("'", "\'")

@"
<?php
require 'vendor/autoload.php';
`$app = new think\App();
`$app->initialize();

`$tenantId = '$safeTenantId';
`$admin = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safePayrollAccount')->where('TENANT_ID', `$tenantId)->find();
if (!is_array(`$admin) || `$admin === []) { throw new RuntimeException('payroll user not found'); }
`$adminUserId = (string)(`$admin['ID'] ?? '');
`$orgId = (string)(`$admin['ORG_ID'] ?? '');
if (`$orgId === '') {
    `$orgId = (string)(think\facade\Db::name('sys_org')->where(function (`$query) {
        `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
    })->value('ID') ?? '');
}
if (`$orgId === '') { throw new RuntimeException('org not found for payroll import smoke'); }

`$userName = '$safeUserId';
think\facade\Db::name('sys_user')->insert([
    'ID' => '$safeUserId',
    'ACCOUNT' => '$safeUserId',
    'NAME' => `$userName,
    'ORG_ID' => `$orgId,
    'USER_STATUS' => 'ENABLE',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => date('Y-m-d H:i:s'),
    'CREATE_USER' => `$adminUserId,
    'TENANT_ID' => `$tenantId,
    'BANK_NAME' => '',
    'BANK_ACCOUNT' => '',
    'BASIC_SALARY' => '0.00',
]);

echo json_encode(['ok' => true, 'userId' => '$safeUserId', 'userName' => `$userName, 'orgId' => `$orgId], JSON_UNESCAPED_UNICODE), PHP_EOL;
"@
}

function New-RemoteStatePayrollImportCode {
    $safeUserId = $ids.payrollImportUser.Replace("'", "\'")
    $safeMarker = $marker.Replace("'", "\'")

@"
<?php
require 'vendor/autoload.php';
`$app = new think\App();
`$app->initialize();
`$rows = think\facade\Db::name('biz_payroll')->where('USER', '$safeUserId')->whereLike('REMARK', '$safeMarker%')->select()->toArray();
echo json_encode(['count' => count(`$rows), 'rows' => `$rows], JSON_UNESCAPED_UNICODE), PHP_EOL;
"@
}

$adminSession = New-Session -Account $AdminAccount
$headers = $adminSession.Headers
$payrollSession = New-Session -Account $PayrollAccount
$payrollHeaders = $payrollSession.Headers
$fileSession = New-RemoteTokenSession -Account $FileAccount
$fileHeaders = $fileSession.Headers

try {
    Invoke-RemotePhpJson -Code (New-RemoteCleanupCode) | Out-Null

    Invoke-Step -Name 'P1 inventory add target smoke' -Action {
        Invoke-RemotePhpJson -Code (New-RemoteSetupInventoryCode) | Out-Null
        $response = Invoke-OaApi -Method POST -Path '/biz/inventory/add' -Headers $headers -Body @{
            warehousesId = $ids.invWarehouse
            productIds = @($ids.invProductA, $ids.invProductB)
        }
        Assert-Equal -Actual $response.data.count -Expected 2 -Name 'inventory add count'
        Assert-Equal -Actual $response.data.inserted -Expected 2 -Name 'inventory add inserted'
        $detail = Invoke-OaApi -Method GET -Path ("/biz/inventory/detail?id={0}" -f [uri]::EscapeDataString([string]$response.data.ids[0])) -Headers $headers
        Assert-Equal -Actual $detail.data.warehousesId -Expected $ids.invWarehouse -Name 'inventory detail warehouse'
        $state = Invoke-RemotePhpJson -Code (New-RemoteStateInventoryCode)
        Assert-IntEqual -Actual $state.count -Expected 2 -Name 'remote inventory row count'
    }

    Invoke-Step -Name 'P1 purchase order warehouse target smoke' -Action {
        Invoke-RemotePhpJson -Code (New-RemoteSetupPurchaseCode) | Out-Null
        $response = Invoke-OaApi -Method POST -Path '/biz/bizpurchaseorder/warehouse/one/add' -Headers $headers -Body @{
            orderId = $ids.purchaseOrder
            warehousesId = $ids.poWarehouse
            remark = "$marker purchase warehouse"
        }
        Assert-Equal -Actual $response.data.storageStatus -Expected 'IN_WAREHOUSE' -Name 'purchase warehouse response status'
        Assert-Equal -Actual $response.data.updatedItems -Expected 2 -Name 'purchase warehouse updated items'
        $detail = Invoke-OaApi -Method GET -Path ("/biz/bizpurchaseorder/detail?id={0}" -f [uri]::EscapeDataString($ids.purchaseOrder)) -Headers $headers
        Assert-Equal -Actual $detail.data.bizPurchaseOrder.storageStatus -Expected 'IN_WAREHOUSE' -Name 'purchase order detail status'
        $state = Invoke-RemotePhpJson -Code (New-RemoteStatePurchaseCode)
        Assert-Equal -Actual $state.order.STORAGE_STATUS -Expected 'IN_WAREHOUSE' -Name 'remote purchase order status'
        Assert-IntEqual -Actual $state.inventory.Count -Expected 2 -Name 'remote purchase inventory rows'
        Assert-IntEqual -Actual $state.delivery.Count -Expected 2 -Name 'remote purchase delivery rows'
    }

    Invoke-Step -Name 'P1 file upload download delete target smoke' -Action {
        $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-p1-file-{0}.txt" -f ([Guid]::NewGuid().ToString('N')))
        $download = $null
        $fileId = ''
        try {
            [System.IO.File]::WriteAllText($tmp, "$marker file upload download", [System.Text.Encoding]::UTF8)
            $upload = Invoke-MultipartUpload -Url ($baseUrl + '/dev/file/uploadLocalReturnFile') -File $tmp -Headers $fileHeaders
            Assert-Equal -Actual $upload.code -Expected 200 -Name 'file upload response'
            $fileId = [string]$upload.data.id
            if ([string]::IsNullOrWhiteSpace($fileId)) {
                throw 'file upload returned empty id'
            }
            $download = Invoke-Download -Url ($baseUrl + '/dev/file/download?id=' + [uri]::EscapeDataString($fileId)) -Headers $fileHeaders
            Assert-Equal -Actual $download.Status -Expected 200 -Name 'file download status'
            $content = [System.IO.File]::ReadAllText([string]$download.BodyPath, [System.Text.Encoding]::UTF8)
            if ($content -notlike "*$marker file upload download*") {
                throw 'download body missing upload marker'
            }
            $delete = Invoke-OaApi -Method POST -Path '/dev/file/delete' -Headers $fileHeaders -Body @(@{ id = $fileId })
            Assert-Equal -Actual $delete.code -Expected 200 -Name 'file delete response'
        } finally {
            Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue
            if ($null -ne $download) {
                Remove-Item -LiteralPath ([string]$download.BodyPath) -Force -ErrorAction SilentlyContinue
                Remove-Item -LiteralPath ([string]$download.HeaderPath) -Force -ErrorAction SilentlyContinue
            }
            if ($fileId -ne '') {
                $safeFileId = $fileId.Replace("'", "\'")
                Invoke-RemotePhpJson -Code @"
<?php
require 'vendor/autoload.php';
`$app = new think\App();
`$app->initialize();
`$row = think\facade\Db::name('dev_file')->where('ID', '$safeFileId')->find();
if (is_array(`$row) && `$row !== []) {
    `$path = (string)(`$row['STORAGE_PATH'] ?? '');
    think\facade\Db::name('dev_file')->where('ID', '$safeFileId')->delete();
    if (`$path !== '' && is_file(`$path)) { @unlink(`$path); }
}
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE), PHP_EOL;
"@ | Out-Null
            }
        }
    }

    Invoke-Step -Name 'P1 payroll export target smoke' -Action {
        Invoke-RemotePhpJson -Code (New-RemoteSetupPayrollExportCode) | Out-Null
        $download = $null
        try {
            $url = $baseUrl + '/biz/bizpayroll/export?searchKey=' + [uri]::EscapeDataString($marker) + '&startSalaryTime=2026-05-01%2000%3A00%3A00&endSalaryTime=2026-05-31%2023%3A59%3A59'
            $download = Invoke-Download -Url $url -Headers $payrollHeaders
            Assert-Equal -Actual $download.Status -Expected 200 -Name 'payroll export status'
            $body = [System.IO.File]::ReadAllText([string]$download.BodyPath, [System.Text.Encoding]::UTF8)
            if ($body -notlike "*$marker payroll export row*" -or $body -notlike '*1700*') {
                throw 'payroll export csv missing smoke row'
            }
        } finally {
            if ($null -ne $download) {
                Remove-Item -LiteralPath ([string]$download.BodyPath) -Force -ErrorAction SilentlyContinue
                Remove-Item -LiteralPath ([string]$download.HeaderPath) -Force -ErrorAction SilentlyContinue
            }
        }
    }

    Invoke-Step -Name 'P1 payroll import target smoke' -Action {
        $setup = Invoke-RemotePhpJson -Code (New-RemoteSetupPayrollImportUserCode)
        $xlsx = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-p1-payroll-import-{0}.xlsx" -f ([Guid]::NewGuid().ToString('N')))
        try {
            New-PayrollImportXlsx -Path $xlsx -UserName ([string]$setup.userName) -Remark "$marker payroll import row"
            $import = Invoke-MultipartUpload -Url ($baseUrl + '/biz/bizpayroll/import') -File $xlsx -Headers $payrollHeaders -Fields @{ orgId = [string]$setup.orgId }
            if ([int]$import.code -ne 200) {
                throw "payroll import response expected '200', got '$($import.code)' msg='$($import.msg)'"
            }
            Assert-Equal -Actual $import.code -Expected 200 -Name 'payroll import response'
            Assert-Equal -Actual $import.data.totalCount -Expected 1 -Name 'payroll import total'
            if ([int]$import.data.successCount -ne 1) {
                $detailJson = ConvertTo-Json -InputObject $import.data -Depth 8 -Compress
                throw "payroll import success expected '1', got '$($import.data.successCount)' detail=$detailJson"
            }
            Assert-Equal -Actual $import.data.successCount -Expected 1 -Name 'payroll import success'
            $state = Invoke-RemotePhpJson -Code (New-RemoteStatePayrollImportCode)
            Assert-IntEqual -Actual $state.count -Expected 1 -Name 'remote payroll import row count'
        } finally {
            Remove-Item -LiteralPath $xlsx -Force -ErrorAction SilentlyContinue
        }
    }

    Invoke-RemotePhpJson -Code (New-RemoteCleanupCode) | Out-Null
    $cleanup = Invoke-RemotePhpJson -Code (New-RemoteCleanupCode)
    Assert-Equal -Actual $cleanup.ok -Expected $true -Name 'remote cleanup'
} finally {
    try {
        Invoke-RemotePhpJson -Code (New-RemoteCleanupCode) | Out-Null
    } catch {
        Write-Warning "final cleanup failed: $($_.Exception.Message)"
    }
}

[pscustomobject]@{
    ok = -not ($results | Where-Object { $_.status -eq 'FAIL' })
    marker = $marker
    frontendBaseUrl = $FrontendBaseUrl
    apiPrefix = $ApiPrefix
    tenantId = $TenantId
    mobileExcluded = $true
    results = $results
} | ConvertTo-Json -Depth 8
