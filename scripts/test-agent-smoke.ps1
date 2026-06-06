param(
    [switch]$SkipComposer,
    [string]$BackendBaseUrl = '',
    [switch]$NoTokenSmoke
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $ProjectRoot

function Invoke-TestStep {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][scriptblock]$Action
    )

    Write-Host "[test-agent] running: $Name"
    & $Action
    Write-Host "[test-agent] passed: $Name"
}

function Invoke-External {
    param(
        [Parameter(Mandatory = $true)][string]$FilePath,
        [Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments
    )

    & $FilePath @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Command failed: $FilePath $($Arguments -join ' ')"
    }
}

Invoke-TestStep 'composer dump-autoload' {
    if (-not $SkipComposer) {
        Invoke-External composer dump-autoload
    }
}

Invoke-TestStep 'ThinkPHP console bootstrap' {
    Invoke-External php think
}

$routeListText = ''
Invoke-TestStep 'ThinkPHP route list and required route coverage' {
    $script:routeListText = (& php think route:list 2>&1 | Out-String)
    if ($LASTEXITCODE -ne 0) {
        Write-Host $script:routeListText
        throw 'php think route:list failed'
    }

    $requiredRoutes = @(
        'sys/user/downloadImportUserTemplate',
        'sys/user/export',
        'sys/user/exportUserInfo',
        'biz/user/export',
        'biz/user/exportUserInfo',
        'dev/message/createSseConnect',
        'biz/dict/page',
        'biz/org/page',
        'biz/user/page',
        'biz/position/page'
    )

    foreach ($route in $requiredRoutes) {
        if (-not $script:routeListText.Contains($route)) {
            throw "Required route missing: $route"
        }
    }
}

Invoke-TestStep 'PHP syntax lint for app, config, and route' {
    $files = Get-ChildItem -Recurse app,config,route -Include *.php
    foreach ($file in $files) {
        & php -l $file.FullName | Out-Null
        if ($LASTEXITCODE -ne 0) {
            throw "PHP lint failed: $($file.FullName)"
        }
    }

    Write-Host "[test-agent] linted PHP files: $($files.Count)"
}

Invoke-TestStep 'git diff whitespace check' {
    Invoke-External git diff --check
}

if ($NoTokenSmoke) {
    Invoke-TestStep 'no-token backend smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -NoTokenSmoke is used'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $routes = @(
            'sys/user/downloadImportUserTemplate',
            'sys/user/export',
            'sys/user/exportUserInfo',
            'biz/user/export',
            'biz/user/exportUserInfo'
        )

        foreach ($route in $routes) {
            $response = Invoke-WebRequest -Uri "$base/$route" -Method GET -UseBasicParsing -TimeoutSec 10
            $content = [string]$response.Content
            if ($response.StatusCode -ne 401 -and -not $content.Contains('"code":401') -and -not $content.Contains('"code": 401')) {
                throw "Expected unauthenticated response for $route, got HTTP $($response.StatusCode): $content"
            }
        }
    }
}

Write-Host '[test-agent] smoke run completed'
