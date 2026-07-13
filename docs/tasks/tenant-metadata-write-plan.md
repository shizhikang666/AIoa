# Tenant Metadata Write Plan

Date: 2026-06-16

## Scope

Replace `/tenants/tenant/add`, `/tenants/tenant/edit`, and `/tenants/tenant/delete` controlled-deferred wrappers with narrow tenant-row metadata maintenance.

## Java Reference

- `F:\AI\projects\testJava\OA\snowy-plugin\fudi-plugin-tenants\src\main\java\vip\xiaonuo\tenant\modular\tenant\controller\TenantsController.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\fudi-plugin-tenants\src\main\java\vip\xiaonuo\tenant\modular\tenant\service\impl\TenantsServiceImpl.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\fudi-plugin-tenants\src\main\java\vip\xiaonuo\tenant\modular\tenant\param\TenantsAddParam.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\fudi-plugin-tenants\src\main\java\vip\xiaonuo\tenant\modular\tenant\param\TenantsEditParam.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\fudi-plugin-tenants\src\main\java\vip\xiaonuo\tenant\modular\tenant\param\TenantsIdParam.java`

## Planned Behavior

`POST /tenants/tenant/add`:

- require `tenantName`;
- reject duplicate active tenant names;
- insert one active `tenants` row with a generated id and 10-digit code;
- write create audit fields from the bearer-token payload when available;
- return `data = null`.

`POST /tenants/tenant/edit`:

- require `tenantId` and `tenantName`;
- reject missing or deleted tenants;
- reject editing the built-in system tenant with `CODE = tenant`;
- reject duplicate active tenant names outside the edited row;
- update only `Tenant_Name`, `UPDATE_TIME`, and `UPDATE_USER`;
- return `data = null`.

`POST /tenants/tenant/delete`:

- require the existing `/auth/b/safe/password` marker for `mark = tenants`;
- accept Java-style `[{ id }]`, `idList`, `ids`, `tenantId`, or single `id`;
- validate the full id batch before any write;
- reject the built-in system tenant with `CODE = tenant`;
- reject tenants that are still referenced by any other table's active `TENANT_ID` rows;
- logically delete selected tenant rows;
- return `data = null`.

## Deliberate Exclusions

- No `SysGenerateApi.generateDefaultSysData` equivalent.
- No default admin user, role, resource, relation, or permission generation.
- No cache invalidation, Redis tenant cache mutation, or data-change event publishing.
- No physical tenant deletion.
- No Java source, database schema, Composer/npm, frontend source, `.env`, or production data changes.

## Verification

- `php -l app\controller\tenant\TenantsController.php`
- `php -l app\service\tenant\TenantsService.php`
- `php think route:list | Select-String -Pattern 'tenants/tenant/(add|edit|delete|page|detail)'`
- `.\scripts\tenant-write-http-smoke.ps1`
- `.\scripts\tenant-read-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
- `git diff --check`
