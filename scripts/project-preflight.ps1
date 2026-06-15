param(
    [switch]$SkipRuntime,
    [switch]$SkipWeb,
    [switch]$SkipRoleSelector,
    [switch]$SkipUserDisplay,
    [switch]$SkipDiffCheck
)

$ErrorActionPreference = 'Stop'

function Invoke-Step {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][scriptblock]$Action
    )

    Write-Host ""
    Write-Host "== $Name =="
    & $Action
    Write-Host "OK: $Name"
}

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $repoRoot

Invoke-Step 'Git Status' {
    git status --short --branch
}

if (-not $SkipRuntime) {
    Invoke-Step 'Runtime Readiness' {
        & (Join-Path $PSScriptRoot 'runtime-ready.ps1')
    }
}

if (-not $SkipWeb) {
    Invoke-Step 'Web Readiness' {
        & (Join-Path $PSScriptRoot 'web-ready.ps1')
    }
}

if (-not $SkipRoleSelector) {
    Invoke-Step 'Role Selector HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'role-selector-http-smoke.ps1')
    }
}

if (-not $SkipUserDisplay) {
    Invoke-Step 'User Display HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'user-display-http-smoke.ps1')
    }
}

if (-not $SkipDiffCheck) {
    Invoke-Step 'Git Diff Whitespace Check' {
        git diff --check
    }
}

Write-Host ""
Write-Host 'project preflight passed'
