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

function Invoke-JsonPost {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [string]$Token = ''
    )

    $bodyFile = [System.IO.Path]::GetTempFileName()
    try {
        Set-Content -LiteralPath $bodyFile -Value '{}' -Encoding ASCII
        $args = @('-sS', '-X', 'POST', $Url, '-H', 'Content-Type: application/json', '--data-binary', "@$bodyFile")
        if ($Token -ne '') {
            $args = @('-sS', '-X', 'POST', $Url, '-H', "Authorization: Bearer $Token", '-H', 'Content-Type: application/json', '--data-binary', "@$bodyFile")
        }

        $raw = (& curl.exe @args)
        if ($LASTEXITCODE -ne 0) {
            throw "curl failed for $Url"
        }

        return ($raw -join "`n").TrimStart([char]0xFEFF) | ConvertFrom-Json
    } finally {
        Remove-Item -LiteralPath $bodyFile -Force -ErrorAction SilentlyContinue
    }
}

function Invoke-JsonGet {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [string]$Token = ''
    )

    $args = @('-sS', $Url)
    if ($Token -ne '') {
        $args = @('-sS', $Url, '-H', "Authorization: Bearer $Token")
    }

    $raw = (& curl.exe @args)
    if ($LASTEXITCODE -ne 0) {
        throw "curl failed for $Url"
    }

    return ($raw -join "`n").TrimStart([char]0xFEFF) | ConvertFrom-Json
}

$envMap = Get-EnvMap -Path $EnvPath
$account = ''
if ($envMap.ContainsKey('LOCAL_SUPER_ADMIN_ACCOUNT')) {
    $account = [string]$envMap['LOCAL_SUPER_ADMIN_ACCOUNT']
}
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
`$auth['device'] = 'CODEX_DEFERRED_WRITE_WRAPPER_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = (& php -r $tokenCode).Trim()
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

function Assert-DeferredResponse {
    param(
        [Parameter(Mandatory = $true)]$Json,
        [Parameter(Mandatory = $true)][string]$Path
    )

    if ([int]$Json.code -ne 400) {
        throw "$Path expected code=400, got code=$($Json.code)"
    }

    $message = ''
    if ($Json.PSObject.Properties.Name -contains 'message') {
        $message = [string]$Json.message
    }
    $operation = ''
    if ($null -ne $Json.data -and $Json.data.PSObject.Properties.Name -contains 'operation') {
        $operation = [string]$Json.data.operation
    }

    if ($message -notmatch 'deferred' -and $operation.Trim() -eq '') {
        throw "$Path expected deferred marker, got message=$($Json.message)"
    }
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$paths = @(
    '/biz/bizpaymentrecord/add',
    '/biz/bizpaymentrecord/edit/account',
    '/biz/bizpaymentrecord/delete',
    '/biz/bizexpenditurerecord/add',
    '/biz/bizexpenditurerecord/edit/account',
    '/biz/bizexpenditurerecord/delete',
    '/biz/bizcollectionreceipt/add',
    '/biz/bizcollectionreceipt/edit',
    '/biz/bizcollectionreceipt/batchExpenditure/edit',
    '/biz/bizcollectionreceipt/delete',
    '/biz/bizdebitnote/add',
    '/biz/bizdebitnote/edit',
    '/biz/bizdebitnote/batchRepayment/edit',
    '/biz/bizdebitnote/history/add',
    '/biz/bizdebitnote/delete',
    '/biz/bizpurchaseorder/add',
    '/biz/bizpurchaseorder/edit',
    '/biz/bizpurchaseorder/audit/edit',
    '/biz/bizpurchaseorder/warehouse/add',
    '/biz/bizpurchaseorder/warehouse/one/add',
    '/biz/bizpurchaseorder/cancel',
    '/biz/bizpurchaseorder/delete',
    '/biz/inventory/add',
    '/biz/inventory/delete',
    '/biz/warehouses/delivery/add',
    '/biz/settlementaccount/delete',
    '/biz/settlementaccount/expenses/add',
    '/biz/settlementaccount/payment/add',
    '/biz/settlementaccount/transfer/add',
    '/biz/bizleaveapplication/add',
    '/biz/bizpayroll/add',
    '/biz/bizpayroll/import',
    '/biz/bizpayroll/generate/add',
    '/dev/email/sendLocalTxt',
    '/dev/email/sendLocalHtml',
    '/dev/email/sendAliyunTxt',
    '/dev/email/sendAliyunHtml',
    '/dev/email/sendAliyunTmp',
    '/dev/email/sendTencentTxt',
    '/dev/email/sendTencentHtml',
    '/dev/email/sendTencentTmp',
    '/dev/job/stopJob',
    '/dev/job/runJob',
    '/dev/job/runJobNow',
    '/gen/basic/execGenPro',
    '/gen/config/add',
    '/biz/process/cancel',
    '/biz/process/leave/edit',
    '/biz/process/leave/start',
    '/biz/process/makePayment/start',
    '/biz/process/payment/start',
    '/biz/process/procure/start',
    '/biz/process/procure/warehouse/start',
    '/biz/process/project/delivery/start',
    '/biz/process/project/init/start',
    '/biz/process/project/play/start',
    '/biz/process/project/reissue/start',
    '/biz/process/project/return/start',
    '/biz/process/reimbursement/start',
    '/biz/saleproject/add',
    '/biz/saleproject/amount/edit',
    '/biz/saleproject/cancel',
    '/biz/saleproject/deal/edit',
    '/biz/saleproject/delete',
    '/biz/saleproject/edit',
    '/biz/saleproject/history/add',
    '/biz/saleproject/repeal',
    '/biz/saleproject/special/add',
    '/biz/saleproject/visibility/edit',
    '/biz/task/approve',
    '/biz/task/reject',
    '/biz/returnorder/add',
    '/biz/returnorder/edit',
    '/biz/returnorder/delete',
    '/biz/saleprojectinvoicing/add',
    '/biz/saleprojectinvoicing/edit',
    '/biz/saleprojectinvoicing/delete'
)

foreach ($path in $paths) {
    $json = Invoke-JsonPost -Url ($baseUrl + $path) -Token $token
    Assert-DeferredResponse -Json $json -Path $path
    Write-Host "$path code=400"
}

$getPaths = @(
    '/biz/task/sse/stream'
)

foreach ($path in $getPaths) {
    $json = Invoke-JsonGet -Url ($baseUrl + $path) -Token $token
    Assert-DeferredResponse -Json $json -Path $path
    Write-Host "$path code=400"
}

foreach ($path in @('/biz/bizpaymentrecord/add', '/biz/bizexpenditurerecord/delete', '/biz/bizcollectionreceipt/delete', '/biz/bizdebitnote/history/add', '/biz/bizpurchaseorder/cancel', '/biz/inventory/delete', '/biz/settlementaccount/payment/add', '/biz/bizpayroll/import', '/dev/email/sendLocalTxt', '/dev/job/runJob', '/gen/basic/execGenPro', '/biz/process/cancel', '/biz/saleproject/delete', '/biz/task/approve', '/biz/returnorder/delete', '/biz/saleprojectinvoicing/delete')) {
    $json = Invoke-JsonPost -Url ($baseUrl + $path)
    if ([int]$json.code -ne 401) {
        throw "$path no-token expected code=401, got code=$($json.code)"
    }
    Write-Host "$path no-token code=401"
}

foreach ($path in @('/biz/task/sse/stream')) {
    $json = Invoke-JsonGet -Url ($baseUrl + $path)
    if ([int]$json.code -ne 401) {
        throw "$path no-token expected code=401, got code=$($json.code)"
    }
    Write-Host "$path no-token code=401"
}

Write-Host 'frontend deferred write wrapper smoke passed'
