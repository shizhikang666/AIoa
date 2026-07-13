# Workflow Payment-Out Approval Plan

Date: 2026-06-22

## Scope

- Cover `Process_reimbursement` and `Process_make_payment` approval side effects.
- Preserve the existing first-step start runtime from `workflow-general-start-runtime-plan.md`.
- Model the BPMN finance-confirmation step by advancing approved `Activity_approval` tasks to `Activity_pay_approval`.
- Run the expense side effect only when `Activity_pay_approval` is approved.
- Keep manual `/biz/settlementaccount/expenses/add` behavior unchanged with `Process_sys`.

## Implemented Behavior

- First approval:
  - closes the first `Activity_approval` task/activity history rows;
  - leaves the process active;
  - creates one runtime/history `Activity_pay_approval` task assigned to `treasurer`;
  - does not create expenditure, statement, or account-balance side effects.
- Finance approval:
  - merges finance form variables such as `accountId`, `payerTime`, and `settlementCategory`;
  - creates one `settlement_account_statement` row with `SETTLEMENT_TYPE = EXPEND`, `PROCESS_ID = processInstanceId`, and `PROCESS_CATEGORY = Process_reimbursement` or `Process_make_payment`;
  - creates one linked `biz_expenditure_record` row with `PROCESS_ID = processInstanceId`;
  - decrements the selected settlement account.
- Reject at either approval task closes the workflow without finance side effects.

## Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- `php -l app\service\biz\SettlementAccountService.php`: passed.
- PowerShell parser checks for `scripts\workflow-payment-out-approve-http-smoke.ps1`, `scripts\workflow-general-start-http-smoke.ps1`, and `scripts\project-preflight.ps1`: passed.
- `.\scripts\workflow-payment-out-approve-http-smoke.ps1`: passed.
- `.\scripts\workflow-general-start-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-expenses-add-http-smoke.ps1`: passed.
- `.\scripts\workflow-payment-approve-http-smoke.ps1`: passed.
- `.\scripts\workflow-procure-warehouse-approve-http-smoke.ps1`: passed.
- `.\scripts\workflow-task-transition-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.

## Deferred

- Procurement-order creation is covered by `workflow-procure-approve-plan.md`; project workflow delegates remain deferred.
- Non-leave process edit behavior.
- Workflow notifications, task SSE, Java data-change events, Java source changes, schema changes, `.env` changes, production data operations, and commits.
