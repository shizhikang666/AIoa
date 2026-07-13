# Biz Workflow Read-Only Compatibility

Date: 2026-06-15
Updated: 2026-06-22

Agent: workflow-agent / api-agent

## Scope

This slice adds protected ThinkPHP workflow compatibility endpoints used by the copied Vue workflow pages. It maps Java workflow query routes to the existing ThinkPHP Camunda-table read layer and includes narrow `Process_ask_leave` start, approval, cancel, and editable-leave writes, first-step non-project process starts, `Process_sale_project_init` start/approval/cancel/reject, `Process_sale_project_play` start/approval/cancel/reject, plus the `Process_payment`, `Process_reimbursement`/`Process_make_payment`, `Process_procure`, and `Process_procure_in_warehouse` approval side effects.

The Java project remains read-only. This slice does not implement broad workflow engine parity, task SSE, non-leave approval/reject delegate side effects outside `Process_payment`, `Process_reimbursement`, `Process_make_payment`, `Process_procure`, `Process_procure_in_warehouse`, `Process_sale_project_init`, and `Process_sale_project_play`, project workflow start side effects outside project init/play, or workflow writes outside the explicitly listed bounded paths.

## Java Reference

- `BizProcessController.java`
- `BizProcessProjectController.java`
- `BizTaskController.java`
- `BizProcessServiceImpl.java`
- `BizProjectProcessServiceImpl.java`
- `BizTaskServiceImpl.java`

## Routes

| Method | Route | ThinkPHP handler |
| --- | --- | --- |
| GET | `/biz/process/all/page` | `biz.ProcessController/allPage` |
| GET | `/biz/process/query` | `biz.ProcessController/query` |
| POST | `/biz/process/query/list` | `biz.ProcessController/queryList` |
| GET | `/biz/process/project/runtime/query/list` | `biz.ProcessController/projectRuntimeQueryList` |
| POST | `/biz/process/fileList` | `biz.ProcessController/fileList` |
| GET | `/biz/task/runtime/activity/detail` | `biz.TaskController/runtimeActivityDetail` |
| POST | `/biz/task/approve` | `biz.TaskController::approve`, minimal `Process_ask_leave` transition plus `Process_payment` income, `Process_reimbursement`/`Process_make_payment` payment-out, `Process_procure` purchase-order, `Process_procure_in_warehouse` warehouse, `Process_sale_project_init` initial project, and `Process_sale_project_play` collection side effects |
| POST | `/biz/task/reject` | `biz.TaskController::reject`, minimal `Process_ask_leave`, `Process_payment`, `Process_reimbursement`, `Process_make_payment`, `Process_procure`, `Process_procure_in_warehouse`, `Process_sale_project_init`, and `Process_sale_project_play` transition |
| GET | `/biz/task/sse/stream` | `biz.TaskController::sseStream`, controlled deferred |
| POST | `/biz/process/cancel` | `biz.ProcessController::cancel`, minimal active leave, non-project first-step, project-init, and project-play cancellation |
| POST | `/biz/process/leave/edit` | `biz.ProcessController::leaveEdit`, minimal editable `Process_ask_leave` variable update |
| POST | `/biz/process/payment/start` | `biz.ProcessController::paymentStart`, minimal `Process_payment` first-step start |
| POST | `/biz/process/reimbursement/start` | `biz.ProcessController::reimbursementStart`, minimal `Process_reimbursement` first-step start |
| POST | `/biz/process/makePayment/start` | `biz.ProcessController::makePaymentStart`, minimal `Process_make_payment` first-step start |
| POST | `/biz/process/procure/start` | `biz.ProcessController::procureStart`, minimal `Process_procure` first-step start |
| POST | `/biz/process/procure/warehouse/start` | `biz.ProcessController::procureWarehouseStart`, minimal `Process_procure_in_warehouse` first-step start |
| POST | `/biz/process/project/init/start` | `biz.ProcessController::projectInitStart`, minimal `Process_sale_project_init` first-step start with sale-project pending state |
| POST | `/biz/process/project/play/start` | `biz.ProcessController::projectPlayStart`, minimal `Process_sale_project_play` first-step start with collection approval path |
| POST | `/biz/process/project/delivery|reissue|return/start` | `biz.ProcessController` controlled-deferred project-start wrappers |

All routes are protected by `AuthMiddleware`.

## Response Notes

- Task and process page responses include Java/S-Table pagination aliases: `page`, `current`, `limit`, `size`, `pages`, `total`, and `records`.
- Task rows from `/biz/task/page` and `/biz/task/history/page` intentionally keep `id` as the task id for copied Vue task-detail callers. The workflow process instance id is exposed as `instanceId`, `processInstanceId`, and `processId`.
- Process rows from `/biz/process/page` and `/biz/process/all/page` keep `id`, `instanceId`, and `processInstanceId` as the process instance id.
- Task/process rows always include `variable` as an object so copied Vue templates can safely read fields such as `record.variable.amount` even when no workflow variables exist.
- Process rows include raw Camunda fields plus frontend-friendly aliases:
  - `id`
  - `instanceId`
  - `processInstanceId`
  - `category`
  - `processKey`
  - `title`
  - `status`
  - `remark`
  - `amount`
  - `createTime`
  - `startTime`
  - `endTime`
  - `variable`
- `query` returns Java-compatible entries with `variable`, `processIdList`, and `variableMap`.
- `query/list` requires a non-empty `processKeyList` or compatible `processKeys`/`category` filter plus a non-empty `attribute` map, matching Java `BizBaseProcessQueryParam` `@NotEmpty` behavior. Empty filters return `400` instead of scanning all historic process rows.
- `project/runtime/query/list` returns runtime process rows matching `projectId`.
- `fileList` reads attachment rows through existing `biz_file_relation` and `dev_file` read logic.
- `variable`, `fileList`, and `query/list` accept JSON request bodies from copied frontend callers, with query/form parameters retained as compatibility fallbacks.
- `variable` returns Java-compatible variable entries with `name`, `value`, `label`, `type`, and `properties`; the internal workflow detail service still uses the normalized variable map.
- `runtime/activity/detail` returns `category`, `variables`, `taskId`, `processKey`, `processInstanceId`, and `processDefinitionId`.
- Existing `detail` and `variable` reads now accept either `processInstanceId` or the Java/frontend `id` parameter.
- `detail` also returns the old frontend detail shape: `userProcess`, `startUser`, `startOrgTree`, `userActivityList`, and `ccUser`.
- Leave, non-project, project-init, and project-play process-start responses return `id`, `processInstanceId`, `processDefinitionId`, `processKey`, `taskId`, `assignee`, `status`, `ccRecordCount`, and `fileRelationCount`.

## Browser And API Smoke, 2026-06-15

- Runtime used local MySQL/Redis plus ThinkPHP `127.0.0.1:82` and Vue `127.0.0.1:83`.
- Authenticated API shape check covered `/biz/task/page`, `/biz/task/history/page`, `/biz/process/page`, `/biz/process/all/page`, and `/biz/ccrecords/page`; all returned HTTP 200 with `code=200`.
- Authenticated workflow read HTTP smoke is now available at `scripts/workflow-read-http-smoke.ps1` and is included in `scripts/project-preflight.ps1` by default.
- The smoke covers `/biz/task/count`, `/biz/task/list`, `/biz/task/page`, `/biz/task/history/page`, `/biz/process/page`, `/biz/process/all/page`, `/biz/process/query`, `/biz/process/query/list`, `/biz/process/project/runtime/query/list` when a local `projectId` variable exists, `/biz/process/detail`, `/biz/process/variable`, `/biz/process/fileList`, `/biz/ccrecords/page`, and `/biz/ccrecords/detail` when a current-user CC record exists.
- `/biz/process/query/list` is called with Java-style `processKeyList` and `attribute.objectId` filters, and the smoke also asserts that an empty `{}` filter returns `400`.
- Browser smoke used a temporary local menu cache to load copied workflow routes directly through `createWebHistory` paths:
  - `/biz/biztask`
  - `/biz/biztask/historyTask`
  - `/biz/biztask/mystarttask`
  - `/biz/biztask/allprocess`
  - `/biz/biztask/copytask`
- Each page rendered an Ant table or empty state and hit its corresponding read endpoint.
- Browser console had no blocking errors, and the smoke observed no workflow write requests such as approve, reject, cancel, start, edit, CC delete, or task SSE.

Additional workflow detail browser smoke on 2026-06-15:

- Used system Chrome through Playwright with a temporary local token and temporary workflow route cache.
- `/biz/biztask/allprocess` rendered without 404 but had no current local rows.
- `/biz/biztask/mystarttask` rendered 3 rows; clicking the first process opened the detail drawer.
- The detail flow called `GET /api/biz/process/detail`, `POST /api/biz/process/fileList`, `POST /api/biz/process/variable`, `GET /api/biz/saleproject/detail`, and `GET /api/biz/warehouses/list`.
- Browser console had no errors, no API requests failed, and no forbidden workflow write, CC delete, upload, delete, sale-project write, or business write request was observed.

## Controlled Deferred Writes

The following routes now return controlled `code = 400` deferred responses:

- `GET /biz/task/sse/stream`
- copied project process start routes under `/biz/process/project` except `/biz/process/project/init/start` and `/biz/process/project/play/start`

They do not start or cancel workflow instances, mutate business tables, emit workflow-copy notifications, push long-lived SSE data, change schema, or modify Java source.

`POST /biz/process/leave/start` now creates a minimal `Process_ask_leave` runtime/history row set, generates `biz_cc_records` rows for submitted `copyUserIdList` users, and binds active submitted `fileIdList` files through `biz_file_relation` with `CATEGORY = Process_ask_leave`. The non-project `payment`, `reimbursement`, `makePayment`, `procure`, and `procure/warehouse` start routes create the same first-step runtime/history shape with the actual process key in variables, CC rows, and file relations. `POST /biz/process/project/init/start` creates the same first-step shape for `Process_sale_project_init`, marks the sale project `PENDING_APPROVAL`, and binds project-init workflow files; cancel and reject roll that pending project back to `FOLLOW`. `POST /biz/process/project/play/start` creates the same first-step shape for `Process_sale_project_play`; first approval advances to BPMN-compatible `Activity_payment_approval`, finance reject closes without payment rows, and finance approval writes an income settlement statement plus linked `biz_payment_record` with `PROCESS_ID = processInstanceId`, `PROCESS_CATEGORY = Process_sale_project_play`, and `SETTLEMENT_CATEGORY = PROJECT_PLAY`, then recalculates sale-project payment state. `POST /biz/process/leave/edit` updates editable leave runtime/history variables once, then sets `isEdit = false`. `POST /biz/process/cancel` cancels an active unapproved leave, non-project first-step process, project-init process, or project-play process and writes final `cancel` history variables. `POST /biz/task/approve` and `/reject` complete/reject `Process_ask_leave` `Activity_approval` tasks by closing history rows and deleting runtime rows. Approved leave transitions also emulate the `LeaveApproveDelegate` business-row side effect by creating/updating one `biz_leave_application` row from historic process variables. Approved `annualLeave` transitions deduct the current-year `biz_user_vacation.USED_AMOUNT`; if the process was edited before approval, the edited amount is deducted. Approved `leaveOfAbsence` rows are consumed by explicit `/biz/bizpayroll/generate/add` payroll generation. `Process_payment` approve now closes the workflow and performs the payment-in side effect, writing an income settlement statement and linked `biz_payment_record` with `PROCESS_ID = processInstanceId` while incrementing the settlement account; reject closes the workflow without finance side effects. `Process_reimbursement` and `Process_make_payment` first approval now creates an active `Activity_pay_approval` finance task without finance side effects; finance approval writes an expense settlement statement and linked `biz_expenditure_record` with `PROCESS_ID = processInstanceId` while decrementing the settlement account, and reject closes without finance side effects. `Process_procure` approval advances through procurement confirmation and optional general-office approval before creating purchase-order rows with `INSTANCE_ID = processInstanceId`; reject closes without purchase-order side effects. `Process_procure_in_warehouse` approve now closes the workflow and performs the purchase-order warehouse-in side effect, writing delivery rows with `PROCESS_ID = processInstanceId`; reject closes the workflow without warehouse side effects. `Process_sale_project_init` approve applies the bounded project-init side effects by writing sale-project delivery/account/amount fields, product items, `SALE_PROJECT` file relations, optional invoicing rows, customer deal amount, and `PROCESS_ID = processInstanceId`. Other non-leave approve/reject remains blocked and does not create remaining project business rows. These paths do not auto-update existing payroll rows or push SSE notifications.

## Deferred

- Workflow task approve/reject for process keys or task definitions other than `Process_ask_leave`, `Process_payment`, `Process_reimbursement`, `Process_make_payment`, `Process_procure`, `Process_procure_in_warehouse`, `Process_sale_project_init`, and `Process_sale_project_play` bounded approval tasks
- Process edit behavior outside active `Process_ask_leave`
- Process cancel behavior outside active unapproved leave, non-project first-step, project-init, and project-play processes
- Project process start behavior under `/biz/process/project` except `/biz/process/project/init/start` and `/biz/process/project/play/start`
- Java delegate side effects outside approved leave-row creation, `Process_payment` income creation, `Process_reimbursement`/`Process_make_payment` expense creation, `Process_procure` purchase-order creation, `Process_procure_in_warehouse` stock-in, `Process_sale_project_init` initial project side effects, and `Process_sale_project_play` project collection side effects
- Long-lived task SSE or Redis workflow push

Note: Java `BizTaskController` does not currently expose `/biz/task/sse/stream`; the copied frontend wrapper contains `sse()` but no active caller was found. Layout task refresh currently flows through `/dev/message/createSseConnect` and the `FlushProcessNotice` SSE payload.
