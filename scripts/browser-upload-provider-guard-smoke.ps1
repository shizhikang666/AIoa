param(
    [string[]]$TargetPath = @(),
    [string]$FrontendBaseUrl = 'http://127.0.0.1:83',
    [string]$ForbiddenPathPattern = '(/|^)(add|edit|delete|del|complete|upload|import|export|doLogin|doLogout|start|approve|reject|cancel|send|grant|reset|enable|disable|revoke|save|runJob|stopJob|runJobNow)(\b|\?|/)',
    [switch]$SkipDefaultTargets
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $ProjectRoot

$pageSmoke = Join-Path $PSScriptRoot 'browser-page-smoke.ps1'
if (-not (Test-Path -LiteralPath $pageSmoke)) {
    throw "Missing browser page smoke helper: $pageSmoke"
}

$targets = @()

if (-not $SkipDefaultTargets) {
    $targets += [pscustomobject]@{
        Path = '/dev/file/index'
        MinRows = 0
        Click = $false
        AllowMissing = $false
    }
    $targets += [pscustomobject]@{
        Path = '/biz/bizpayroll'
        MinRows = 0
        Click = $false
        AllowMissing = $false
    }
    $targets += [pscustomobject]@{
        Path = '/biz/bizproduct'
        MinRows = 0
        Click = $true
        AllowMissing = $false
    }
    $targets += [pscustomobject]@{
        Path = '/biz/customer'
        MinRows = 1
        Click = $true
        AllowMissing = $false
    }
    $targets += [pscustomobject]@{
        Path = '/biz/saleproject/dealProjectList'
        MinRows = 0
        Click = $true
        AllowMissing = $false
    }
}

foreach ($path in $TargetPath) {
    if ([string]::IsNullOrWhiteSpace($path)) {
        continue
    }

    $targets += [pscustomobject]@{
        Path = $path
        MinRows = 0
        Click = $false
        AllowMissing = $false
    }
}

if ($targets.Count -eq 0) {
    throw 'No browser smoke targets were provided.'
}

foreach ($target in $targets) {
    Write-Host "browser upload/provider guard smoke: $($target.Path)"

    $params = @{
        TargetPath = $target.Path
        FrontendBaseUrl = $FrontendBaseUrl
        MinRows = [int]$target.MinRows
        ForbiddenPathPattern = $ForbiddenPathPattern
    }

    if ([bool]$target.Click) {
        $params['ClickFirstTableLink'] = $true
    }
    if ([bool]$target.AllowMissing) {
        $params['AllowMissingTableLink'] = $true
    }

    & $pageSmoke @params
    if ($LASTEXITCODE -ne 0) {
        throw "browser upload/provider guard smoke failed for $($target.Path)"
    }
}

Write-Host 'browser upload/provider guard smoke passed'
