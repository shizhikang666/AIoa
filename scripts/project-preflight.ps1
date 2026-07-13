param(
    [switch]$SkipRuntime,
    [switch]$SkipWeb,
    [switch]$SkipFrontendApiMethod,
    [switch]$SkipFrontendApiRouteGap,
    [switch]$SkipFrontendDeferredWrites,
    [switch]$SkipRoleSelector,
    [switch]$SkipUserDisplay,
    [switch]$SkipBizRead,
    [switch]$SkipInventoryDeliveryRead,
    [switch]$SkipFinanceRead,
    [switch]$SkipPurchaseOrderRead,
    [switch]$SkipSettlementAccountPaymentRead,
    [switch]$SkipSettlementAccountRead,
    [switch]$SkipSettlementAccountDelete,
    [switch]$SkipSettlementAccountExpensesAdd,
    [switch]$SkipSupplierWarehouseRead,
    [switch]$SkipProductRead,
    [switch]$SkipSaleProjectProductItemStandalone,
    [switch]$SkipSaleProjectInvoiceAdd,
    [switch]$SkipSaleProjectReissueOrderAdd,
    [switch]$SkipReturnOrderWrite,
    [switch]$SkipHrRead,
    [switch]$SkipTeamProjectRead,
    [switch]$SkipDatareportRead,
    [switch]$SkipResourceRead,
    [switch]$SkipDevRead,
    [switch]$SkipGenRead,
    [switch]$SkipAuthIndexRead,
    [switch]$SkipDirectoryAlias,
    [switch]$SkipTenantRead,
    [switch]$SkipMessageSse,
    [switch]$SkipWorkflowRead,
    [switch]$SkipWorkflowLeaveStart,
    [switch]$SkipWorkflowGeneralStart,
    [switch]$SkipWorkflowPaymentApprove,
    [switch]$SkipWorkflowPaymentOutApprove,
    [switch]$SkipWorkflowProcureApprove,
    [switch]$SkipWorkflowProcureWarehouseApprove,
    [switch]$SkipWorkflowProjectInitApprove,
    [switch]$SkipWorkflowProjectDeliveryApprove,
    [switch]$SkipWorkflowProjectReissueApprove,
    [switch]$SkipWorkflowProjectReturnApprove,
    [switch]$SkipWorkflowProjectPlayApprove,
    [switch]$SkipWorkflowTaskTransition,
    [switch]$SkipWorkflowProcessCancelEdit,
    [switch]$SkipBizLeaveApplicationVacationAdjustment,
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

if (-not $SkipFrontendApiRouteGap) {
    Invoke-Step 'Frontend API Route Gap Smoke' {
        & (Join-Path $PSScriptRoot 'frontend-api-route-gap-smoke.ps1') -FailOnReadMissing
    }
}

if (-not $SkipFrontendDeferredWrites) {
    Invoke-Step 'Frontend Deferred Write Wrapper Smoke' {
        & (Join-Path $PSScriptRoot 'frontend-deferred-write-wrapper-smoke.ps1')
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

if (-not $SkipPurchaseOrderRead) {
    Invoke-Step 'Purchase Order Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'purchase-order-read-http-smoke.ps1')
    }
}

if (-not $SkipSettlementAccountPaymentRead) {
    Invoke-Step 'Settlement Account Payment Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'settlement-account-payment-read-http-smoke.ps1')
    }
}

if (-not $SkipSettlementAccountRead) {
    Invoke-Step 'Settlement Account Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'settlement-account-read-http-smoke.ps1')
    }
}

if (-not $SkipSettlementAccountDelete) {
    Invoke-Step 'Settlement Account Delete HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'settlement-account-delete-http-smoke.ps1')
    }
}

if (-not $SkipSettlementAccountExpensesAdd) {
    Invoke-Step 'Settlement Account Expenses Add HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'settlement-account-expenses-add-http-smoke.ps1')
    }
}

if (-not $SkipSupplierWarehouseRead) {
    Invoke-Step 'Supplier/Warehouse Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'supplier-warehouse-read-http-smoke.ps1')
    }
}

if (-not $SkipProductRead) {
    Invoke-Step 'Product Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'product-read-http-smoke.ps1')
    }
}

if (-not $SkipSaleProjectProductItemStandalone) {
    Invoke-Step 'Sale Project Product Item Standalone HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'sale-project-product-item-standalone-http-smoke.ps1')
    }
}

if (-not $SkipSaleProjectInvoiceAdd) {
    Invoke-Step 'Sale Project Invoice Add/Edit/Delete HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'sale-project-invoice-add-http-smoke.ps1')
    }
}

if (-not $SkipSaleProjectReissueOrderAdd) {
    Invoke-Step 'Sale Project Reissue Order Add/Edit/Delete HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'sale-project-reissue-order-add-http-smoke.ps1')
    }
}

if (-not $SkipReturnOrderWrite) {
    Invoke-Step 'Return Order Write HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'return-order-write-http-smoke.ps1')
    }
}

if (-not $SkipHrRead) {
    Invoke-Step 'HR Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'hr-read-http-smoke.ps1')
    }
}

if (-not $SkipTeamProjectRead) {
    Invoke-Step 'Team Project Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'team-project-read-http-smoke.ps1')
    }
}

if (-not $SkipDatareportRead) {
    Invoke-Step 'Datareport Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'datareport-read-http-smoke.ps1')
    }
}

if (-not $SkipResourceRead) {
    Invoke-Step 'Resource Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'resource-read-http-smoke.ps1')
    }
}

if (-not $SkipDevRead) {
    Invoke-Step 'Dev Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'dev-read-http-smoke.ps1')
    }
}

if (-not $SkipGenRead) {
    Invoke-Step 'Gen Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'gen-read-http-smoke.ps1')
    }
}

if (-not $SkipAuthIndexRead) {
    Invoke-Step 'Auth/Index Read HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'auth-index-read-http-smoke.ps1')
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

if (-not $SkipWorkflowLeaveStart) {
    Invoke-Step 'Workflow Leave Start HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'workflow-leave-start-http-smoke.ps1')
    }
}

if (-not $SkipWorkflowGeneralStart) {
    Invoke-Step 'Workflow General Start HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'workflow-general-start-http-smoke.ps1')
    }
}

if (-not $SkipWorkflowPaymentApprove) {
    Invoke-Step 'Workflow Payment Approve HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'workflow-payment-approve-http-smoke.ps1')
    }
}

if (-not $SkipWorkflowPaymentOutApprove) {
    Invoke-Step 'Workflow Payment-Out Approve HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'workflow-payment-out-approve-http-smoke.ps1')
    }
}

if (-not $SkipWorkflowProcureApprove) {
    Invoke-Step 'Workflow Procure Approve HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'workflow-procure-approve-http-smoke.ps1')
    }
}

if (-not $SkipWorkflowProcureWarehouseApprove) {
    Invoke-Step 'Workflow Procure Warehouse Approve HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'workflow-procure-warehouse-approve-http-smoke.ps1')
    }
}

if (-not $SkipWorkflowProjectInitApprove) {
    Invoke-Step 'Workflow Project Init Approve HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'workflow-project-init-approve-http-smoke.ps1')
    }
}

if (-not $SkipWorkflowProjectDeliveryApprove) {
    Invoke-Step 'Workflow Project Delivery Approve HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'workflow-project-delivery-approve-http-smoke.ps1')
    }
}

if (-not $SkipWorkflowProjectReissueApprove) {
    Invoke-Step 'Workflow Project Reissue Approve HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'workflow-project-reissue-approve-http-smoke.ps1')
    }
}

if (-not $SkipWorkflowProjectReturnApprove) {
    Invoke-Step 'Workflow Project Return Approve HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'workflow-project-return-approve-http-smoke.ps1')
    }
}

if (-not $SkipWorkflowProjectPlayApprove) {
    Invoke-Step 'Workflow Project Play Approve HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'workflow-project-play-approve-http-smoke.ps1')
    }
}

if (-not $SkipWorkflowTaskTransition) {
    Invoke-Step 'Workflow Task Transition HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'workflow-task-transition-http-smoke.ps1')
    }
}

if (-not $SkipWorkflowProcessCancelEdit) {
    Invoke-Step 'Workflow Process Cancel/Edit HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'workflow-process-cancel-edit-http-smoke.ps1')
    }
}

if (-not $SkipBizLeaveApplicationVacationAdjustment) {
    Invoke-Step 'Biz Leave Application Vacation Adjustment HTTP Smoke' {
        & (Join-Path $PSScriptRoot 'biz-leave-application-vacation-adjustment-http-smoke.ps1')
    }
}

if (-not $SkipDiffCheck) {
    Invoke-Step 'Git Diff Whitespace Check' {
        git diff --check
    }
}

Write-Host ""
Write-Host 'project preflight passed'
