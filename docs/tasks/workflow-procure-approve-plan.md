# Workflow Procure Approve Plan

Date: 2026-06-22

## Scope

- Replace the `Process_procure` approval delegate path for purchase-order creation.
- Preserve the BPMN staged flow:
  - `Activity_approval` advances to `Activity_procure_approval`.
  - `Activity_procure_approval` stores `productList` and `amount`, then advances to `Activity_approval_procure` when `approvesGeneralOffice` is present.
  - Empty `approvesGeneralOffice` skips the final approval task and creates the purchase order immediately after procurement confirmation.
  - Final `Activity_approval_procure` approval creates the purchase order.
- Reject at any stage closes workflow runtime/history without purchase-order, delivery, inventory, or finance side effects.

## Business Writes

- Insert one `biz_purchase_order` row with:
  - `INSTANCE_ID = processInstanceId`
  - `SETTLEMENT_STATUS = NOT_COMPLETED`
  - `STORAGE_STATUS = NOT_IN_WAREHOUSE`
  - supplier snapshot in `EXT_JSON.supplier`
- Insert one `biz_purchase_order_item` row per procurement confirmation `productList` item.
- Do not create supplier, delivery, inventory, finance, notification, or Java event rows in this slice.

## Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`
- `php -l app\service\biz\PurchaseOrderService.php`
- PowerShell parser checks:
  - `scripts\workflow-procure-approve-http-smoke.ps1`
  - `scripts\workflow-general-start-http-smoke.ps1`
  - `scripts\project-preflight.ps1`
  - `scripts\project-progress.ps1`
- `.\scripts\workflow-procure-approve-http-smoke.ps1`
- `.\scripts\workflow-general-start-http-smoke.ps1`
