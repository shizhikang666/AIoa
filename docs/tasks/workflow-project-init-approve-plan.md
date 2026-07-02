# Workflow Project Init Approval Plan

Status: implemented and DB-backed HTTP-smoke verified on 2026-06-22.

## Scope

- Replace controlled-deferred behavior for `POST /biz/process/project/init/start`.
- Start `Process_sale_project_init` with the same minimal first-step runtime/history shape used by the leave and non-project workflow slices.
- Move the referenced sale project from `FOLLOW` to `PENDING_APPROVAL` at start time.
- Allow initial cancel through `POST /biz/process/cancel`; cancellation rolls the sale project back to `FOLLOW`.
- Allow `POST /biz/task/reject` for `Process_sale_project_init`; rejection closes workflow runtime/history and rolls the sale project back to `FOLLOW`.
- Allow `POST /biz/task/approve` for `Process_sale_project_init`; approval writes bounded initial project side effects and closes workflow runtime/history.
- On approval, write sale-project delivery/account/amount fields, product items, `SALE_PROJECT` file relations, optional invoicing rows, customer deal amount, and `PROCESS_ID = processInstanceId`.
- Keep project return workflow deferred until its Java state changes and delegate is replaced explicitly. Project delivery is covered separately by `workflow-project-delivery-approve-plan.md`, project reissue is covered separately by `workflow-project-reissue-approve-plan.md`, and project play is covered separately by `workflow-project-play-approve-plan.md`.

## Validation Boundary

- Requires a current token user, tenant context, one or more approvers, and valid optional copy users.
- Requires an active `FOLLOW` sale project visible to the current tenant/data-scope rules.
- Requires non-empty `fileIdList` and non-empty `productList`.
- Requires project-init fields such as `accountId`, `payerCategory`, non-negative `initPrice` and `rebateAmount`, and valid invoicing information when `isInvoicing = true`.
- Approval is idempotent only when the project already carries the same workflow `PROCESS_ID` and is already in an approved delivery state.

## Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`
- `php -l app\service\biz\SaleProjectService.php`
- `php -l app\controller\biz\ProcessController.php`
- PowerShell parser checks for `scripts\workflow-project-init-approve-http-smoke.ps1`, `scripts\project-preflight.ps1`, `scripts\frontend-deferred-write-wrapper-smoke.ps1`, and `scripts\project-progress.ps1`
- `.\scripts\workflow-project-init-approve-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\workflow-general-start-http-smoke.ps1`
- `.\scripts\workflow-task-transition-http-smoke.ps1`
- `.\scripts\workflow-process-cancel-edit-http-smoke.ps1`
- `.\scripts\workflow-payment-approve-http-smoke.ps1`
- `.\scripts\workflow-payment-out-approve-http-smoke.ps1`
- `.\scripts\workflow-procure-approve-http-smoke.ps1`
- `.\scripts\workflow-procure-warehouse-approve-http-smoke.ps1`
- `git diff --check`

## Deferred

- Project delivery, reissue, and return starts and delegates.
- Non-leave process edit behavior outside the existing leave-edit path.
- Task SSE, notifications, Java data-change events, automatic existing-payroll recalculation, Java source changes, schema changes, `.env` changes, production data operations, and commits.
