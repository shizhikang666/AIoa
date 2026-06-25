# Workflow Process Cancel/Edit Plan

## Scope

Extend the transitional `Process_ask_leave` runtime write coverage with:

- `POST /biz/process/cancel`
- `POST /biz/process/leave/edit`

This is still a narrow Camunda-table compatibility layer, not a full BPMN engine.

## Java Reference

Java `BizProcessServiceImpl.cancelProcess` delegates to the cancellation strategy. The default strategy:

- requires an active runtime process
- rejects cancellation after any task has already completed for non-super-admin users
- cancels the active user task path and routes to `Activity_REJECT`
- sets `approval = false` and `cancel = true`, which leaves final status as `cancel`

Java `BizProcessServiceImpl.editProcess`:

- requires an active process started by the current login user
- requires historic variable `isEdit = true`
- updates `endTime`, `amount`, `remark`
- sets `isEdit = false`

## ThinkPHP Write Shape

`POST /biz/process/cancel` now:

- accepts `{ id }` as the process instance id
- allows only active `Process_ask_leave` processes started by the current token user
- rejects processes with already completed historic tasks
- closes the active approval task/activity/process history rows
- writes final history variables `approval = false`, `cancel = true`, `status = cancel`, and `state = cancel`
- deletes matching runtime task, variable, and execution rows

`POST /biz/process/leave/edit` now:

- accepts `{ id, endTime, amount, remark }`
- allows only active `Process_ask_leave` processes started by the current token user
- requires `isEdit = true`
- validates the edited end time is after the stored start time
- updates runtime and historic variables for `endTime`, `amount`, `remark`, and `isEdit = false`

## Annual-Leave Boundary

Cancellation is covered only before approval, so no leave-application row or vacation deduction exists to restore.

For editable annual-leave processes, the edited `amount` becomes the value used later by `POST /biz/task/approve`. Approval still performs the annual-leave balance deduction in the same transaction as leave-row creation.

## Guardrails

- Other process keys remain deferred.
- Other task activities remain deferred.
- Super-admin cancellation override is not implemented.
- Copy-user record generation, SSE push, notifications, automatic existing-payroll row recalculation, and process edit after approval remain deferred. Payroll generation-time consumption of approved `leaveOfAbsence` rows is covered by `docs/tasks/workflow-payroll-generation-coverage-plan.md`; direct leave-row annual-leave restoration is covered by `docs/tasks/biz-leave-application-vacation-adjustment-plan.md`.
- Java source, schema, `.env`, and production data are unchanged.

## Verification

`scripts/workflow-process-cancel-edit-http-smoke.ps1` verifies:

- no-token requests return `401`
- missing ids return `400`
- an unapproved annual-leave process can be cancelled
- cancel clears runtime rows and writes final `cancel` history variables
- cancel does not create a leave row or change the vacation balance
- an editable annual-leave process can update `endTime`, `amount`, and `remark`
- a second edit is rejected after `isEdit` is set to false
- approval after edit creates the leave row with edited values
- approval after edit deducts the edited annual-leave amount
- a non-editable leave process rejects edit
- temporary workflow and vacation rows are cleaned up

## Next Steps

1. Automatic updates to already-created payroll rows remain deferred because Java payroll deduction is calculated on explicit generation.
2. Expand workflow starts/transitions to other process definitions only after delegate side effects are mapped and smoked.

Completed on 2026-06-22: active leave-start copy-user CC rows are covered by `docs/tasks/workflow-copy-user-records-plan.md`.

Completed on 2026-06-22: active leave-start file relation binding is covered by `docs/tasks/workflow-file-relation-binding-plan.md`.

Completed on 2026-06-22: workflow-approved `leaveOfAbsence` rows are consumed by payroll generation. See `docs/tasks/workflow-payroll-generation-coverage-plan.md`.
