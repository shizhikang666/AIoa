# Sys Config Read-Only Compatibility

## Scope

This slice adds authenticated, read-only compatibility for Java `SysConfigController.detail`.

## Java Reference

- Java project, read-only: `F:\AI\projects\testJava\OA`
- Controller: `SysConfigController`
- Service: `SysConfigServiceImpl`
- SQL table: `sys_config`
- Frontend API: `snowy-admin-web/src/api/sys/sysConfigApi.js`

## Added Route

Protected route:

- `GET /sys/sysConfig/detail`

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

## Deliberate Exclusions

- `POST /sys/sysConfig/edit` is not implemented in this slice.
- `GET /sys/sysConfig/generateConfig` is not implemented because the Java endpoint writes data despite using GET.
- No configuration cache is mutated.
- No `sys_config` row is inserted or updated.
- No database schema or Java source files are changed.

## Later Work

System configuration writes need permission checks, workflow process validation, audit logging, cache invalidation, and optimistic-lock handling before they are enabled.
