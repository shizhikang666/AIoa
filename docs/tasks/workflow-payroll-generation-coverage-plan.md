# Workflow Payroll Generation Coverage Plan

Date: 2026-06-22

Agent: api-agent/test-agent

## Scope

Verify the payroll-facing side of approved leave workflows without adding a non-Java automatic recalculation step.

Covered path:

- `POST /biz/process/leave/start`
- `POST /biz/task/approve`
- generated `biz_leave_application` row with `CATEGORY = leaveOfAbsence`
- later `POST /biz/bizpayroll/generate/add`
- generated `biz_payroll.VACATION`

## Java Reference

Java `LeaveApproveDelegate` only creates the leave application row through `BizLeaveApplicationService.add`.

Java `BizPayrollServiceImpl.generate` later reads `biz_leave_application` rows with category `leaveOfAbsence` when payroll generation is explicitly requested.

There is no Java workflow-approval path that automatically rewrites already-created payroll rows.

## ThinkPHP Coverage

The ThinkPHP approval path creates the workflow-owned `biz_leave_application` row on approved `Process_ask_leave` transitions.

`BizPayrollService::generate` already reads same-tenant active leave rows for the selected users and salary month through `applyGeneratedLeaveAmounts`, then calculates `VACATION`, `VACATION_SUB_AMOUNT`, `PAYABLE_AMOUNT`, and `ACTUAL_AMOUNT`.

`scripts/workflow-task-transition-http-smoke.ps1` now cross-checks the two paths together by approving a temporary `leaveOfAbsence` workflow, calling `/biz/bizpayroll/generate/add` for the same user/month, and asserting the generated payroll row's `VACATION` equals the approved workflow amount.

## Guardrails

- Do not auto-update existing `biz_payroll` rows when a workflow is approved.
- Do not add new workflow delegate behavior beyond the bounded leave transition.
- Do not change payroll add, payroll import/export rendering, Java data-change events, notifications, task SSE, schema, Java source, `.env`, or production data.
- Existing payroll generation remains an explicit user/API action.

## Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`
- `php -l app\service\biz\BizPayrollService.php`
- PowerShell parser check for `scripts\workflow-task-transition-http-smoke.ps1`
- `.\scripts\workflow-task-transition-http-smoke.ps1`
- `.\scripts\biz-payroll-generate-add-http-smoke.ps1`
- `git diff --check`
