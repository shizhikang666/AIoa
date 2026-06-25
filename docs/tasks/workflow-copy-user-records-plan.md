# Workflow Copy-User Records Plan

Date: 2026-06-22

## Scope

Add the CC-record part of Java `CopyUserDelegate` to the active ThinkPHP leave-start runtime:

- `POST /biz/process/leave/start`
- `Process_ask_leave`
- submitted `copyUserIdList`
- generated `biz_cc_records` rows readable by `/biz/ccrecords/page`

This is a bounded workflow-runtime side effect, not a full BPMN service-task engine.

## Java Reference

- `F:\AI\projects\testJava\OA\bpmn\personnel\Process_ask_leave.bpmn`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizprocess\delegate\base\CopyUserDelegate.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\ccrecords\param\BizCcRecordsAddParam.java`

## ThinkPHP Write Shape

For each valid copy user submitted to `leave/start`, insert one `biz_cc_records` row:

- `TITLE`: generated workflow title
- `PROCESS_ID`: process definition id
- `INSTANCE_ID`: process instance id
- `PROMOTER_ID`: starter user id
- `CATEGORY`: `Process_ask_leave`
- `USER`: copy user id
- `CREATE_USER`: copy user id, matching Java delegate behavior
- `TENANT_ID`: starter tenant id
- `DELETE_FLAG`: `NOT_DELETE`
- `EXT_JSON`: `null`

The leave-start response includes `ccRecordCount`.

## Guardrails

- Only active `Process_ask_leave` leave starts are covered.
- Manual `/biz/ccrecords/add|edit|delete` behavior stays current-user scoped row maintenance.
- Active leave-start file relation binding from `fileIdList` is covered by `docs/tasks/workflow-file-relation-binding-plan.md`.
- Notifications, data-change events, SSE push, payroll recalculation, other process keys, and non-leave delegates remain deferred.
- Java source, schema, `.env`, production data operations, and commits remain unchanged.

## Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`
- `php -l app\service\biz\CcRecordsService.php`
- PowerShell parser check for `scripts\workflow-leave-start-http-smoke.ps1`
- `.\scripts\workflow-leave-start-http-smoke.ps1`
- `.\scripts\biz-cc-records-write-http-smoke.ps1`
- `.\scripts\workflow-read-http-smoke.ps1`
- `git diff --check`
