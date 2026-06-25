# Workflow Task Transition Runtime Plan

## Scope

Implement the first ThinkPHP workflow task transition path:

- `POST /biz/task/approve`
- `POST /biz/task/reject`
- only for `Process_ask_leave`
- only for active `Activity_approval`

This is a transitional Camunda-table update, not a full BPMN engine replacement.

## Java Reference

Java `BizTaskServiceImpl.completeTask(BizTaskCompleteParam)`:

- requires an active task assigned to the current login user
- treats `form.approval = false` as reject
- sets `state = AGREE` for approve
- submits task form variables through Camunda

Java `BizTaskServiceImpl.rejectTask(BizTaskRejectParam)`:

- requires an active task assigned to the current login user
- sets `approval = false`
- sets `state = REJECT`
- submits task form variables through Camunda

## ThinkPHP Write Shape

The PHP runtime transition:

- validates the task exists and is assigned to the current token user
- allows only `Process_ask_leave` + `Activity_approval`
- writes final history variables:
  - `approval`
  - `status`
  - `state`
  - `comment`
  - `nrOfCompletedInstances`
  - `nrOfActiveInstances`
- closes `act_hi_taskinst`
- closes `act_hi_actinst`
- closes `act_hi_procinst` with `STATE_ = COMPLETED`
- creates or updates one `biz_leave_application` row on approve
- deletes matching `act_ru_task`, `act_ru_variable`, and `act_ru_execution` rows

Approve uses `state/status = AGREE` and task `DELETE_REASON_ = completed`.
Reject uses `state/status = REJECT` and task `DELETE_REASON_ = deleted`.
Approve creates the leave row from historic workflow variables. Reject does not create a leave row.

## Guardrails

- Other process keys remain deferred.
- Other task definition keys remain deferred.
- No BPMN service task or delegate is emulated.
- The approved leave-row side effect and approved `annualLeave` balance deduction are emulated for leave workflows.
- No copy-user record, notification, SSE push, or automatic existing-payroll row recalculation is executed. Payroll generation-time consumption of approved `leaveOfAbsence` rows is covered by `docs/tasks/workflow-payroll-generation-coverage-plan.md`; direct leave-row restoration is covered by `docs/tasks/biz-leave-application-vacation-adjustment-plan.md`.
- This step completes the minimal leave process immediately after the single active approval task.

## Verification

`scripts/workflow-task-transition-http-smoke.ps1` verifies:

- approve/reject no-token requests return `401`
- missing id requests return `400`
- a temporary leave process can be approved
- approved task runtime rows are cleared
- approved task/process history rows are completed
- approved history variables contain `AGREE`
- approved workflow creates one `biz_leave_application` row
- leave application page/detail read back the approved row
- `/biz/bizpayroll/generate/add` includes the workflow-approved `leaveOfAbsence` amount in generated payroll `VACATION`
- approved `annualLeave` increments current-year `biz_user_vacation.USED_AMOUNT`
- insufficient annual-leave balance rolls back the transition and keeps the runtime task active
- history task page includes the approved task
- a temporary leave process can be rejected
- rejected task runtime rows are cleared
- rejected task/process history rows are completed
- rejected history variables contain `REJECT`
- rejected workflow creates zero `biz_leave_application` rows
- inserted temporary rows are cleaned up by `processInstanceId`

`scripts/project-preflight.ps1` includes this smoke by default and can skip it with `-SkipWorkflowTaskTransition`.

## Next Steps

1. Add copy/file generation for process keys outside active leave start only after their delegates are mapped and smoked.
2. Automatic updates to already-created payroll rows remain deferred because Java payroll deduction is calculated on explicit generation.
3. Direct leave edit/delete annual-leave restoration is covered by `docs/tasks/biz-leave-application-vacation-adjustment-plan.md`.
4. Expand task transitions to finance/procurement/sale-project flows only after their delegate side effects are mapped and smoked.

Completed on 2026-06-22: approved `Process_ask_leave` transitions now generate/read back the leave-application business row. See `docs/tasks/workflow-leave-application-side-effect-plan.md`.

Completed on 2026-06-22: approved `annualLeave` transitions now deduct current-year vacation balances. See `docs/tasks/workflow-annual-leave-deduction-plan.md`.

Completed on 2026-06-22: active `Process_ask_leave` cancel/edit paths now cover unapproved process cancellation and one-time editable leave variable updates. See `docs/tasks/workflow-process-cancel-edit-plan.md`.

Completed on 2026-06-22: workflow-approved `leaveOfAbsence` rows are consumed by payroll generation. See `docs/tasks/workflow-payroll-generation-coverage-plan.md`.
