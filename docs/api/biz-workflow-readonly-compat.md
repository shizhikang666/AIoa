# Biz Workflow Read-Only Compatibility

Date: 2026-06-15

Agent: workflow-agent / api-agent

## Scope

This slice adds protected read-only ThinkPHP endpoints used by the copied Vue workflow pages. It maps Java workflow query routes to the existing ThinkPHP Camunda-table read layer.

The Java project remains read-only. This slice does not implement approval, rejection, process start, process cancel, task SSE, workflow writes, or Java delegate side effects.

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
| POST | `/biz/task/approve` | `biz.TaskController::approve`, controlled deferred |
| POST | `/biz/task/reject` | `biz.TaskController::reject`, controlled deferred |
| GET | `/biz/task/sse/stream` | `biz.TaskController::sseStream`, controlled deferred |
| POST | `/biz/process/cancel` | `biz.ProcessController::cancel`, controlled deferred |
| POST | `/biz/process/*/start` and `/leave/edit` | `biz.ProcessController` controlled-deferred wrappers |

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

- `POST /biz/task/approve`
- `POST /biz/task/reject`
- `GET /biz/task/sse/stream`
- `POST /biz/process/cancel`
- all copied process start/edit routes under `/biz/process`

They do not start or cancel workflow instances, complete tasks, reject tasks, mutate business tables, emit workflow-copy notifications, push long-lived SSE data, change schema, or modify Java source.

## Deferred

- Real workflow task approve/reject behavior
- Real process start/edit/cancel behavior
- Java delegate side effects
- Long-lived task SSE or Redis workflow push

Note: Java `BizTaskController` does not currently expose `/biz/task/sse/stream`; the copied frontend wrapper contains `sse()` but no active caller was found. Layout task refresh currently flows through `/dev/message/createSseConnect` and the `FlushProcessNotice` SSE payload.
