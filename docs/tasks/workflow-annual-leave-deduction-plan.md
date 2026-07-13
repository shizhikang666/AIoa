# Workflow Annual Leave Deduction Plan

## Scope

Extend the approved `Process_ask_leave` side effect so annual leave approvals also update the current-year annual-leave balance:

- source workflow: `Process_ask_leave`
- source task: `Activity_approval`
- success path: `POST /biz/task/approve` with `form.approval = true`
- workflow category: `annualLeave`
- target table: `biz_user_vacation`

## Java Reference

Java `BizLeaveApplicationEventHandler.addHandle` calls `BizUserVacationService.reduce` only when the generated leave row has `category = annualLeave`.

Java `BizUserVacationServiceImpl.reduce`:

- finds the current-year vacation row for `userId` and `category`
- rejects missing annual-leave balance
- computes `AMOUNT - USED_AMOUNT`
- rejects when the remaining balance is smaller than the requested leave `amount`
- updates `USED_AMOUNT += amount`

## ThinkPHP Write Shape

The PHP approval transaction now:

- creates the approved `biz_leave_application` row first
- when `category = annualLeave`, locks the current-year `biz_user_vacation` annual-leave row
- validates remaining balance
- increments `USED_AMOUNT`
- updates `UPDATE_TIME`, `UPDATE_USER`, and `VERSION`
- returns `vacationDeduction` metadata in the approve response

If annual-leave deduction fails, the whole approval transaction rolls back:

- runtime workflow task remains active
- no leave-application row is inserted
- vacation balance remains unchanged

## Guardrails

- Deduction runs only for newly inserted approved leave rows.
- Idempotent updates of an existing leave row do not deduct again.
- Only `annualLeave` is deducted.
- Editable leave workflows deduct the edited `amount` at approval time.
- Vacation generation, automatic existing-payroll row recalculation, copy-user generation outside active leave start, notifications, SSE push, and other workflow delegates remain deferred. Payroll generation-time consumption of approved `leaveOfAbsence` rows is covered by `docs/tasks/workflow-payroll-generation-coverage-plan.md`; active leave-start copy-user CC rows are covered by `docs/tasks/workflow-copy-user-records-plan.md`; direct leave-row restoration is covered by `docs/tasks/biz-leave-application-vacation-adjustment-plan.md`.
- Java source, schema, `.env`, and production data are unchanged.

## Verification

`scripts/workflow-task-transition-http-smoke.ps1` now verifies:

- non-annual approved leave still creates the leave row
- rejected leave creates no leave row
- approved `annualLeave` creates the leave row and increments `biz_user_vacation.USED_AMOUNT`
- the annual-leave row `VERSION` increments
- insufficient annual-leave balance returns `400`
- insufficient balance leaves the workflow task active
- insufficient balance creates no leave row and leaves `USED_AMOUNT` unchanged
- temporary workflow, leave, and vacation rows are cleaned up

`scripts/workflow-process-cancel-edit-http-smoke.ps1` also verifies that cancelling an unapproved annual-leave process does not change `USED_AMOUNT`, and that approving an edited annual-leave process deducts the edited amount.

## Next Steps

1. Automatic updates to already-created payroll rows remain deferred because Java payroll deduction is calculated on explicit generation.
2. Expand workflow transitions to other process definitions only after their delegates are mapped and smoked.

Completed on 2026-06-22: active leave-start file relation binding is covered by `docs/tasks/workflow-file-relation-binding-plan.md`.

Completed on 2026-06-22: workflow-approved `leaveOfAbsence` rows are consumed by payroll generation. See `docs/tasks/workflow-payroll-generation-coverage-plan.md`.
