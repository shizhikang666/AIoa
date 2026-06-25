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
    '/biz/bizpaymentrecord/delete',
    '/biz/bizexpenditurerecord/add',
    '/biz/bizexpenditurerecord/delete',
    '/biz/bizcollectionreceipt/add',
    '/biz/bizcollectionreceipt/edit',
    '/biz/bizcollectionreceipt/delete',
    '/biz/bizdebitnote/add',
    '/biz/bizdebitnote/edit',
    '/biz/bizdebitnote/delete',
    '/biz/bizpurchaseorder/add',
    '/biz/bizpurchaseorder/delete',
    '/biz/inventory/delete',
    '/biz/bizleaveapplication/add',
    '/biz/bizpayroll/add',
    '/dev/email/sendLocalTxt',
    '/dev/email/sendLocalHtml',
    '/dev/email/sendAliyunTxt',
    '/dev/email/sendAliyunHtml',
    '/dev/email/sendAliyunTmp',
    '/dev/email/sendTencentTxt',
    '/dev/email/sendTencentHtml',
    '/dev/email/sendTencentTmp',
    '/gen/basic/execGenPro',
    '/gen/config/add',
    '/biz/process/project/reissue/start',
    '/biz/process/project/return/start'
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

foreach ($path in @('/biz/bizpaymentrecord/add', '/biz/bizexpenditurerecord/delete', '/biz/bizcollectionreceipt/delete', '/biz/inventory/delete', '/biz/bizpayroll/add', '/dev/email/sendLocalTxt', '/gen/basic/execGenPro', '/biz/process/cancel')) {
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
