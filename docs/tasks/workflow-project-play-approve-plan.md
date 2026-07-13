# Workflow Project Play Approval Plan

Status: implemented and DB-backed HTTP-smoke verified on 2026-06-22.

## Scope

- Replace controlled-deferred behavior for `POST /biz/process/project/play/start`.
- Start `Process_sale_project_play` with the same minimal first-step runtime/history shape used by the leave, non-project, and project-init workflow slices.
- Preserve BPMN-compatible task progression: first approval advances from `Activity_approval` to `Activity_payment_approval`.
- Allow initial cancel through `POST /biz/process/cancel`; cancellation closes workflow rows without finance or sale-project payment side effects.
- Allow `POST /biz/task/reject` for both approval steps; rejection closes workflow runtime/history without finance or sale-project payment side effects.
- Allow finance approval to replace `BizSaleProjectPlayStateApproveDelegate` for the bounded project collection path.
- On finance approval, write one `settlement_account_statement` and one `biz_payment_record` with `PROCESS_ID = processInstanceId`, `PROCESS_CATEGORY = Process_sale_project_play`, `SETTLEMENT_CATEGORY = PROJECT_PLAY`, and `OBJECT_ID = projectId`.
- Recalculate sale-project `AMOUNT_COLLECTED`, `PLAY_STATE`, and `PROJECT_STATE` after the project collection payment is written.
- Keep project return workflow deferred until its Java state changes and delegate is replaced explicitly. Project delivery is covered separately by `workflow-project-delivery-approve-plan.md`, and project reissue is covered separately by `workflow-project-reissue-approve-plan.md`.

## Validation Boundary

- Requires a current token user, tenant context, one or more approvers, valid optional copy users, and a valid `treasurer`.
- Requires a visible sale project in the current tenant/data-scope rules.
- Requires a valid settlement account, `payerTime`, and positive `amount`.
- First approval creates no finance rows; only the finance confirmation task can write the collection side effect.
- The side effect is transaction-coupled with workflow completion; if settlement account or sale-project payment-status validation fails, the active finance task remains active.

## Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`
- `php -l app\service\biz\SaleProjectService.php`
- `php -l app\service\biz\SettlementAccountService.php`
- `php -l app\controller\biz\ProcessController.php`
- PowerShell parser checks for `scripts\workflow-project-play-approve-http-smoke.ps1`, `scripts\project-preflight.ps1`, `scripts\frontend-deferred-write-wrapper-smoke.ps1`, and `scripts\project-progress.ps1`
- `.\scripts\workflow-project-play-approve-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\workflow-project-init-approve-http-smoke.ps1`
- `.\scripts\workflow-payment-approve-http-smoke.ps1`
- `.\scripts\workflow-payment-out-approve-http-smoke.ps1`
- `git diff --check`

## Deferred

- Project delivery, reissue, and return starts and delegates.
- Non-leave process edit behavior outside the existing leave-edit path.
- Task SSE, notifications, Java data-change events, automatic existing-payroll recalculation, Java source changes, schema changes, `.env` changes, production data operations, and commits.
