# Workflow File Relation Binding Plan

Date: 2026-06-22

## Scope

Add the file-relation part of Java `CopyUserDelegate` to the active ThinkPHP leave-start runtime:

- `POST /biz/process/leave/start`
- `Process_ask_leave`
- submitted `fileIdList`
- generated `biz_file_relation` rows readable by `/biz/process/fileList`

This is a bounded workflow-runtime side effect, not a full BPMN service-task engine.

## Java Reference

- `F:\AI\projects\testJava\OA\bpmn\personnel\Process_ask_leave.bpmn`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizprocess\delegate\base\CopyUserDelegate.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizfilerelation\service\impl\BizFileRelationServiceImpl.java`

## ThinkPHP Write Shape

For each valid file id submitted to `leave/start`, insert one `biz_file_relation` row:

- `OBJECT_ID`: process instance id
- `TARGET_ID`: submitted file id
- `CATEGORY`: `Process_ask_leave`
- `FILE_NAME`: linked `dev_file.NAME`
- `DELETE_FLAG`: `NOT_DELETE`
- `CREATE_TIME`: leave-start timestamp
- `CREATE_USER`: starter user id
- `TENANT_ID`: starter tenant id
- `EXT_JSON`: `null`

The linked `dev_file` row must be active and belong to the current tenant. The leave-start response includes `fileRelationCount`.

## Guardrails

- Only active `Process_ask_leave` leave starts are covered.
- Manual `/biz/bizfilerelation/add|edit|delete` behavior and category validation stay unchanged.
- The workflow write uses `CATEGORY = Process_ask_leave`, matching Java `CopyUserDelegate.addBath`, while the manual file-relation API remains limited to its Java enum categories.
- Copy generation for other process keys, notifications, data-change events, SSE push, payroll recalculation, non-leave delegates, cloud storage, physical file cleanup, Java source, schema, `.env`, production data operations, and commits remain deferred.

## Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`
- PowerShell parser check for `scripts\workflow-leave-start-http-smoke.ps1`
- `.\scripts\workflow-leave-start-http-smoke.ps1`
- `.\scripts\workflow-read-http-smoke.ps1`
- `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -FileRelationHttpSmoke`
- `.\scripts\workflow-task-transition-http-smoke.ps1`
- `git diff --check`
