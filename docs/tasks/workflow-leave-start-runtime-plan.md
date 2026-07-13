# Workflow Leave Start Runtime Plan

## Scope

Implement the first ThinkPHP workflow write path for `Process_ask_leave`:

- `POST /biz/process/leave/start`
- Camunda-compatible `act_ru_*` runtime rows
- Camunda-compatible `act_hi_*` history rows
- generated copy-user `biz_cc_records` rows for `copyUserIdList`
- generated workflow `biz_file_relation` rows for `fileIdList`
- read-back through existing task/process query APIs

This is a transitional runtime write, not a full BPMN engine replacement.

## Java Reference

Java `BizProcessServiceImpl.startProcess(BizLeaveApplicationParam)`:

- validates the base process params
- builds a title from the current user's name plus the leave category display name
- sets `isEdit = true` when `endTime` is empty
- calls `baseProcessService.start("Process_ask_leave", dict, true)`

Java `BizBaseProcessServiceImpl.start` adds:

- `tenantId`
- `org`
- `approval = true`
- authenticated user id as initiator

## ThinkPHP Write Shape

The PHP runtime writes:

- `act_ru_execution`: root process execution plus active approval execution
- `act_ru_task`: one active `Activity_approval` task assigned to the first approver
- `act_ru_variable`: process variables with `VAR_SCOPE_ = processInstanceId`
- `act_hi_procinst`: active historic process row with `STATE_ = ACTIVE`
- `act_hi_taskinst`: active historic task row
- `act_hi_actinst`: active user task activity
- `act_hi_varinst`: historic variables matching runtime variables
- `biz_cc_records`: one active CC row per submitted copy user
- `biz_file_relation`: one active workflow file relation per submitted file id

Variables include:

- `initiator`
- `approveUserIdList`
- `copyUserIdList`
- `fileIdList`
- `org`
- `tenantId`
- `approval`
- `category`
- `amount`
- `startTime`
- `endTime`
- `objectId`
- `isEdit`
- `title`
- `remark`
- `status = progress`

Approver/copy lists are stored as JSON text, so the existing PHP variable normalizer returns arrays without Java serialized bytearrays.

For `copyUserIdList`, the runtime also inserts `biz_cc_records` rows with the workflow title, process definition id, instance id, promoter id, `CATEGORY = Process_ask_leave`, copied user id, tenant id, and `DELETE_FLAG = NOT_DELETE`. The leave-start response includes `ccRecordCount`.

For `fileIdList`, the runtime inserts `biz_file_relation` rows with `OBJECT_ID = processInstanceId`, `TARGET_ID = dev_file.ID`, `CATEGORY = Process_ask_leave`, `FILE_NAME = dev_file.NAME`, starter audit fields, tenant id, and `DELETE_FLAG = NOT_DELETE`. The leave-start response includes `fileRelationCount`.

## Guardrails

- Only `Process_ask_leave` is enabled.
- Other process start endpoints remain deferred.
- Approval/reject transition, active leave cancel, and editable leave variable updates are covered by later workflow slices.
- Active leave-start copy-user CC record generation is covered in this step.
- Active leave-start file relation binding is covered in this step.
- No leave application business row is generated in this step.
- No payroll/vacation balance side effect is executed in this step.
- Notifications, data-change events, and other Java delegate/BPMN service-task behavior are not emulated in this step.

## Verification

`scripts/workflow-leave-start-http-smoke.ps1` verifies:

- no-token request returns `401`
- missing approver request returns `400`
- successful start returns `processInstanceId` and `taskId`
- required `act_ru_*` and `act_hi_*` rows exist
- `/biz/task/page` includes the new task
- `/biz/process/page` includes the started process
- `/biz/ccrecords/page` includes the generated copy-user row
- `/biz/process/fileList` includes the generated workflow file relation
- `/biz/task/runtime/activity/detail` returns started variables
- inserted test rows are cleaned up by `processInstanceId`

`scripts/project-preflight.ps1` includes this smoke by default and can skip it with `-SkipWorkflowLeaveStart`.

## Next Steps

1. Automatic updates to already-created payroll rows remain deferred because Java payroll deduction is calculated on explicit generation. Workflow-approved `leaveOfAbsence` rows are consumed by explicit payroll generation; see `docs/tasks/workflow-payroll-generation-coverage-plan.md`.
2. Direct leave edit/delete annual-leave restoration is covered by `docs/tasks/biz-leave-application-vacation-adjustment-plan.md`.
3. Expand the same runtime pattern to higher-risk finance/procurement workflows only after delegate side effects are mapped and tested.

Completed on 2026-06-22: minimal `Activity_approval` approve/reject transitions are now covered by `docs/tasks/workflow-task-transition-runtime-plan.md` and `scripts/workflow-task-transition-http-smoke.ps1`.

Completed on 2026-06-22: approved leave transitions now generate/read back `biz_leave_application` rows; see `docs/tasks/workflow-leave-application-side-effect-plan.md`.

Completed on 2026-06-22: approved `annualLeave` transitions now deduct current-year annual-leave balances; see `docs/tasks/workflow-annual-leave-deduction-plan.md`.

Completed on 2026-06-22: active `Process_ask_leave` cancel/edit paths now cover unapproved process cancellation and one-time editable leave variable updates; see `docs/tasks/workflow-process-cancel-edit-plan.md`.

Completed on 2026-06-22: active `Process_ask_leave` leave starts now generate copy-user CC records for `copyUserIdList`; see `docs/tasks/workflow-copy-user-records-plan.md`.

Completed on 2026-06-22: active `Process_ask_leave` leave starts now bind workflow files for `fileIdList`; see `docs/tasks/workflow-file-relation-binding-plan.md`.
