# Workflow Procure Warehouse Approve Plan

Status: implemented and DB-backed HTTP-smoke verified on 2026-06-22.

## Scope

- Replace the `Process_procure_in_warehouse` approval delegate path for `Activity_approval`.
- Keep the existing first-step start behavior from `workflow-general-start-runtime-plan.md`.
- On approve, close the workflow runtime/history rows and call the purchase-order warehouse-in service using:
  - `PROCESS_ID = processInstanceId`
  - `PROCESS_CATEGORY = Process_procure_in_warehouse`
  - `OBJECT_ID = orderId`
  - `OPERATOR = initiator`
- On reject, close workflow runtime/history rows without warehouse side effects.
- Preserve existing `/biz/bizpurchaseorder/warehouse/one/add` behavior, where quick manual stock-in still writes `PROCESS_ID = Process_sys`.

## Guardrails

- This slice moves `Process_procure_in_warehouse` out of non-leave approve-through deferred behavior.
- Reimbursement and make-payment approval are covered by `workflow-payment-out-approve-plan.md`; procurement-order creation is covered by `workflow-procure-approve-plan.md`; project init is covered by `workflow-project-init-approve-plan.md`; project delivery is covered by `workflow-project-delivery-approve-plan.md`; project play is covered by `workflow-project-play-approve-plan.md`; project reissue/return workflows and delegates remain deferred. Payment approval is covered by `workflow-payment-approve-plan.md`.
- The side effect is transaction-coupled with workflow completion; if order, warehouse, item, product, inventory, or stock-in validation fails, the active task remains active.
- No Java source, schema, `.env`, production data, notification, task SSE, or Java data-change event changes are included.

## Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`
- `php -l app\service\biz\PurchaseOrderService.php`
- PowerShell parser checks for:
  - `scripts\workflow-procure-warehouse-approve-http-smoke.ps1`
  - `scripts\workflow-general-start-http-smoke.ps1`
  - `scripts\project-preflight.ps1`
  - `scripts\project-progress.ps1`
- `.\scripts\workflow-procure-warehouse-approve-http-smoke.ps1`
- `.\scripts\workflow-general-start-http-smoke.ps1`
- `.\scripts\workflow-task-transition-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\project-progress.ps1 -Lean`
- `git diff --check`
