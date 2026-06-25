# Biz CC Records Write Plan

Date: 2026-06-16

## Scope

Replace `/biz/ccrecords/add` and `/biz/ccrecords/edit` controlled-deferred wrappers with narrow `biz_cc_records` row maintenance.

## Java Reference

- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\ccrecords\controller\BizCcRecordsController.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\ccrecords\service\impl\BizCcRecordsServiceImpl.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\ccrecords\param\BizCcRecordsAddParam.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\ccrecords\param\BizCcRecordsEditParam.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizprocess\delegate\base\CopyUserDelegate.java`

## Planned Behavior

`POST /biz/ccrecords/add`:

- require a current authenticated user;
- require `title`, `processId`, `instanceId`, and `category`;
- force `user` to the current authenticated user;
- default `promoterId` to the current authenticated user when omitted;
- write current tenant/audit fields from the bearer-token payload when available;
- insert one active `biz_cc_records` row and return `data = null`.

`POST /biz/ccrecords/edit`:

- require a current authenticated user and `id`;
- only allow editing the current user's active CC record in the current tenant;
- update `title`, `processId`, `promoterId`, `instanceId`, `category`, and `extJson`;
- preserve `USER`, `CREATE_TIME`, `CREATE_USER`, `TENANT_ID`, and `DELETE_FLAG`;
- return `data = null`.

## Deliberate Exclusions

- No workflow runtime start/approve/reject/cancel behavior.
- Manual add/edit remains current-user row maintenance. Active `Process_ask_leave` leave-start `copyUserIdList` generation is covered by the workflow runtime slice, not by these manual endpoints.
- No copy-user generation for other process keys.
- No file-relation binding, process-variable writes from manual CC maintenance, notification push, data-change events, or cache mutation.
- No Java source, database schema, Composer/npm, frontend source, `.env`, or production data changes.

## Verification

- `php -l app\controller\biz\CcRecordsController.php`
- `php -l app\service\biz\CcRecordsService.php`
- `php think route:list | Select-String -Pattern 'biz/ccrecords/(add|edit|delete|page|detail)'`
- `.\scripts\biz-cc-records-write-http-smoke.ps1`
- `.\scripts\workflow-read-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
- `git diff --check`
