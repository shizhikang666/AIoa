# Workflow General Start Runtime Plan

Status: implemented and DB-backed HTTP-smoke verified on 2026-06-22.

## Scope

- Replace controlled-deferred behavior for five non-project process starts:
  - `POST /biz/process/payment/start`
  - `POST /biz/process/reimbursement/start`
  - `POST /biz/process/makePayment/start`
  - `POST /biz/process/procure/start`
  - `POST /biz/process/procure/warehouse/start`
- Create the same minimal Camunda-compatible first-step runtime/history shape used by the leave-start slice: root execution, active approval execution, one `Activity_approval` task, matching historic process/task/activity rows, runtime variables, historic variables, optional CC rows, and optional workflow file relations.
- Store CC/file relation `CATEGORY` and historic variable `PROC_DEF_KEY_` with the actual process key.
- Support initial cancel for these active unapproved processes through `POST /biz/process/cancel`.
- Keep non-leave task approval/reject transitions blocked until each Java delegate side effect is replaced explicitly, except for the later covered payment-in, payment-out, procurement, and procurement-warehouse paths listed below.

Subsequent state on 2026-06-22: `Process_payment` approval is now covered by `workflow-payment-approve-plan.md`, `Process_reimbursement` and `Process_make_payment` payment-out approval are covered by `workflow-payment-out-approve-plan.md`, `Process_procure` approval is now covered by `workflow-procure-approve-plan.md`, `Process_procure_in_warehouse` approval is now covered by `workflow-procure-warehouse-approve-plan.md`, `Process_sale_project_init` start/approval/cancel/reject is covered by `workflow-project-init-approve-plan.md`, `Process_sale_project_delivery` start/approval/cancel/reject is covered by `workflow-project-delivery-approve-plan.md`, and `Process_sale_project_play` start/approval/cancel/reject is covered by `workflow-project-play-approve-plan.md`; project reissue/return approval delegates remain deferred.

## Validation Boundary

- All starts require a current token user and at least one valid approver in the current tenant.
- `Process_payment` requires `settlementCategory`, `accountId`, `payerTime`, positive `amount`, and valid `treasurer`.
- `Process_reimbursement` and `Process_make_payment` require positive `amount`, `bankAccount`, `bankName`, `payer`, `useAdvancePayment`, and valid `treasurer`; `accountId` and `payerTime` are required when `useAdvancePayment = true`.
- `Process_procure` requires supplier data, `desirePurchaseDate`, valid `procure`, and valid optional general-office approvers.
- `Process_procure_in_warehouse` requires `orderId` and `warehousesId`.

## Deferred

- Project init, delivery, and play starts are covered separately; project reissue/return starts remain deferred because Java changes project state around start.
- Remaining project reissue/return approval/reject transitions remain deferred because Java delegates create project side effects.
- Task SSE, notifications, Java data-change events, automatic payroll recalculation, schema changes, `.env` changes, Java source changes, production data operations, and commits remain deferred.

## Verification Plan

- `php -l app\service\workflow\WorkflowRuntimeService.php`
- `php -l app\controller\biz\ProcessController.php`
- PowerShell parser checks for `scripts\workflow-general-start-http-smoke.ps1`, `scripts\frontend-deferred-write-wrapper-smoke.ps1`, and `scripts\project-preflight.ps1`
- `php think route:list | Select-String -Pattern "biz/process/(payment|reimbursement|makePayment|procure|procure/warehouse)/start|biz/process/project/init/start"`
- `.\scripts\workflow-general-start-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `git diff --check`
