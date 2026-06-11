# Sys Config Compatibility

## Scope

This note tracks authenticated compatibility for Java `SysConfigController.detail` and the copied frontend process-config save flow.

## Java Reference

- Java project, read-only: `F:\AI\projects\testJava\OA`
- Controller: `SysConfigController`
- Service: `SysConfigServiceImpl`
- SQL table: `sys_config`
- Frontend API: `snowy-admin-web/src/api/sys/sysConfigApi.js`

## Added Routes

Protected routes:

- `GET /sys/sysConfig/detail`
- `POST /sys/sysConfig/edit`

## Response Shape

The endpoint returns a Java-compatible base configuration object:

- `processConfigMap`

Each process config includes:

- `open`
- `approveUserIdList`
- `copyUserIdList`
- `treasurer`
- `procure`

## Endpoint Notes

- The route is used after login by the old frontend login flow and process configuration page.
- The query first tries the current token tenant row in `sys_config`.
- If no tenant row exists, it falls back to tenant `0`.
- If no valid JSON is available, it returns an in-memory default `processConfigMap` without writing to the database.

## 2026-06-11 Edit Compatibility

`POST /sys/sysConfig/edit` accepts the Java/frontend payload:

```json
{ "config": { "processConfigMap": {} } }
```

Behavior:

- Requires an admin-compatible token payload (`superadmin`, `bizAdmin`, or `tenantAdmin` account/role), matching the project's current protected write-route convention while Java route-permission middleware is still deferred.
- Requires `config.processConfigMap` to be an object/array, matching Java's validated edit parameter shape.
- Normalizes every known process key into `processConfigMap`.
- Preserves the Java fields `open`, `approveUserIdList`, `copyUserIdList`, `treasurer`, and `procure`.
- Writes normalized JSON into `sys_config.CONFIG_JSON`.
- Updates the current tenant row; if the tenant row is missing, creates it with `DELETE_FLAG = NOT_DELETE` and `VERSION = 0`.
- Does not validate user ids strictly, matching Java's permissive save behavior and avoiding historical config lockout.
- Does not add a ThinkPHP config cache path; `detail` reads the DB row directly.

Smoke evidence:

- `php -l app/service/sys/SysConfigService.php`
- `php -l app/controller/sys/SysConfigController.php`
- `php -l route/app.php`
- `php think route:list` includes `/sys/sysConfig/detail` and `/sys/sysConfig/edit`
- DB smoke edited `Process_reimbursement`, verified `detail` returned the saved values, checked malformed missing `config`, missing `processConfigMap`, and non-admin write rejection, then restored the original `CONFIG_JSON`

## Deliberate Exclusions

- `GET /sys/sysConfig/generateConfig` is not implemented because the Java endpoint writes data despite using GET.
- No configuration cache is mutated.
- No database schema or Java source files are changed.

## Later Work

System configuration still needs route-permission middleware, workflow process validation beyond the known key normalization, cache invalidation if a cache-backed read path is later added, and optimistic-lock handling if the table version column becomes active.
