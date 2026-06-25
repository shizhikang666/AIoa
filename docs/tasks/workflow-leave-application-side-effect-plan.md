# Workflow Leave Application Side Effect Plan

## Scope

Extend the minimal `Process_ask_leave` approval path so the approved leave workflow also writes the Java delegate's business row:

- source workflow: `Process_ask_leave`
- source task: `Activity_approval`
- success path: `POST /biz/task/approve` with `form.approval = true`
- target table: `biz_leave_application`

This remains a transitional Camunda-table implementation. It does not emulate the full BPMN engine.

## Java Reference

Java `Process_ask_leave.bpmn` routes approved tasks through `LeaveApproveDelegate`.

Java `LeaveApproveDelegate`:

- reads process variables from the approved process
- copies them into `BizLeaveApplicationAddParam`
- sets `userId` from workflow `initiator`
- sets `processId` from the process instance id
- calls `BizLeaveApplicationService.add`

Java `BizLeaveApplicationService.add`:

- rejects overlapping leave rows for the same user and time range
- saves one `biz_leave_application` row
- publishes a data-change event

## ThinkPHP Write Shape

The PHP approval path now:

- decodes historic workflow variables for the process instance
- validates `initiator`, `category`, `amount`, `startTime`, `endTime`, and `tenantId`
- rejects overlapping active leave rows for the same user, tenant, and time range
- inserts one active `biz_leave_application` row on approval
- uses `PROCESS_ID = processInstanceId` as the idempotency key for update/retry behavior
- returns `leaveApplicationId` from the task approval response

Reject still closes the workflow history and does not create a leave-application row.

## Guardrails

- Only `Process_ask_leave` and `Activity_approval` are enabled.
- Only the approved path creates or updates `biz_leave_application`.
- `biz_user_vacation` annual-leave deduction is covered by `docs/tasks/workflow-annual-leave-deduction-plan.md`.
- Automatic existing-payroll row recalculation, copy-user generation outside active leave start, notifications, SSE push, and other workflow delegates remain deferred. Payroll generation-time consumption of approved `leaveOfAbsence` rows is covered by `docs/tasks/workflow-payroll-generation-coverage-plan.md`; active leave-start copy-user CC rows are covered by `docs/tasks/workflow-copy-user-records-plan.md`; direct leave-row restoration is covered by `docs/tasks/biz-leave-application-vacation-adjustment-plan.md`.
- Java source, database schema, `.env`, and production data are unchanged.

## Verification

`scripts/workflow-task-transition-http-smoke.ps1` now verifies:

- approved workflow runtime rows are cleared
- approved workflow history rows are completed
- approved history variables contain `AGREE`
- one `biz_leave_application` row is written with the process id, initiator user, category, amount, start/end time, and remark
- `/biz/bizleaveapplication/my/page` and `/detail` read back the approved row
- rejected workflow history contains `REJECT`
- rejected workflow creates zero leave-application rows
- workflow and leave rows created by the smoke are cleaned up by `processInstanceId`

`scripts/workflow-process-cancel-edit-http-smoke.ps1` verifies editable leave workflows use edited amount/end-time/remark values when approval later creates the leave row.

## Next Steps

1. Automatic updates to already-created payroll rows remain deferred because Java payroll deduction is calculated on explicit generation.
2. Direct leave edit/delete annual-leave restoration is covered by `docs/tasks/biz-leave-application-vacation-adjustment-plan.md`.
3. Expand workflow transitions to other process definitions only after their delegates are mapped and smoked.

Completed on 2026-06-22: approved `annualLeave` workflows now deduct current-year annual-leave balances. See `docs/tasks/workflow-annual-leave-deduction-plan.md`.

Completed on 2026-06-22: active leave-start file relation binding is covered by `docs/tasks/workflow-file-relation-binding-plan.md`.

Completed on 2026-06-22: active `Process_ask_leave` cancel/edit paths now cover unapproved process cancellation and one-time editable leave variable updates. See `docs/tasks/workflow-process-cancel-edit-plan.md`.

Completed on 2026-06-22: workflow-approved `leaveOfAbsence` rows are consumed by payroll generation. See `docs/tasks/workflow-payroll-generation-coverage-plan.md`.
