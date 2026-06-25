# Workflow Payment Approve Plan

Status: implemented and DB-backed HTTP-smoke verified on 2026-06-22.

## Scope

- Replace the `Process_payment` approval delegate path for `Activity_approval`.
- Keep the existing first-step start behavior from `workflow-general-start-runtime-plan.md`.
- On approve, close workflow runtime/history rows and create the Java-compatible payment-in side effect:
  - `settlement_account_statement.PROCESS_ID = processInstanceId`
  - `settlement_account_statement.PROCESS_CATEGORY = Process_payment`
  - `biz_payment_record.PROCESS_ID = processInstanceId`
  - settlement account balance increments by the approved amount
- On reject, close workflow runtime/history rows without payment/statement/account side effects.
- Preserve existing `/biz/settlementaccount/payment/add` behavior, where quick manual income still writes `PROCESS_ID = Process_sys` and `PROCESS_CATEGORY = Process_sys`.

## Guardrails

- Only `Process_payment` moves out of non-leave approve-through deferred behavior in this slice.
- Reimbursement and make-payment approval are covered by `workflow-payment-out-approve-plan.md`; procurement-order creation is covered by `workflow-procure-approve-plan.md`; project init is covered by `workflow-project-init-approve-plan.md`; project delivery is covered by `workflow-project-delivery-approve-plan.md`; project play is covered by `workflow-project-play-approve-plan.md`; project reissue/return workflows and delegates remain deferred.
- The side effect is transaction-coupled with workflow completion; if account or payment validation fails, the active task remains active.
- Existing start payloads that provide `treasurer` but not `payer` remain compatible; workflow payment approval falls back to `treasurer`/initiator for `PAYER`.
- No Java source, schema, `.env`, production data, notification, task SSE, or Java data-change event changes are included.

## Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`
- `php -l app\service\biz\SettlementAccountService.php`
- PowerShell parser checks for:
  - `scripts\workflow-payment-approve-http-smoke.ps1`
  - `scripts\workflow-general-start-http-smoke.ps1`
  - `scripts\project-preflight.ps1`
  - `scripts\project-progress.ps1`
- `.\scripts\workflow-payment-approve-http-smoke.ps1`
- `.\scripts\workflow-general-start-http-smoke.ps1`
- `.\scripts\settlement-account-payment-add-http-smoke.ps1`
- `.\scripts\workflow-task-transition-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
