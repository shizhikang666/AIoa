param(
    [switch]$SkipRuntime,
    [switch]$SkipWeb,
    [switch]$SkipFrontendApiMethod,
    [switch]$SkipRoleSelector,
    [switch]$SkipUserDisplay,
    [switch]$SkipBizRead,
    [switch]$SkipInventoryDeliveryRead,
    [switch]$SkipFinanceRead,
    [switch]$SkipDirectoryAlias,
    [switch]$SkipTenantRead,
    [switch]$SkipMessageSse,
    [switch]$SkipWorkflowRead,
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

if (-not $SkipFrontendApiMethod) {
    Invoke-Step 'Frontend API Method Smoke' {
        & (Join-Path $PSScriptRoot 'frontend-api-method-smoke.ps1')
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

if (-not $SkipBizRead) {
    Invoke-Step 'Business Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'business-read-http-smoke.ps1')
    }
}

if (-not $SkipInventoryDeliveryRead) {
    Invoke-Step 'Inventory/Delivery Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'inventory-delivery-read-http-smoke.ps1')
    }
}

if (-not $SkipFinanceRead) {
    Invoke-Step 'Finance Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'finance-read-http-smoke.ps1')
    }
}

if (-not $SkipDirectoryAlias) {
    Invoke-Step 'Directory Alias HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'directory-alias-http-smoke.ps1')
    }
}

if (-not $SkipTenantRead) {
    Invoke-Step 'Tenant Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'tenant-read-http-smoke.ps1')
    }
}

if (-not $SkipMessageSse) {
    Invoke-Step 'Message SSE HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'message-sse-http-smoke.ps1')
    }
}

if (-not $SkipWorkflowRead) {
    Invoke-Step 'Workflow Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'workflow-read-http-smoke.ps1')
    }
}

if (-not $SkipDiffCheck) {
    Invoke-Step 'Git Diff Whitespace Check' {
        git diff --check
    }
}

Write-Host ""
Write-Host 'project preflight passed'
