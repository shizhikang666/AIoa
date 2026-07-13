# Tenants Compatibility

## Scope

This slice adds authenticated read compatibility and narrow tenant-row metadata writes for the tenant management page.

## Java Reference

- Java project, read-only: `F:\AI\projects\testJava\OA`
- Controller: `TenantsController`
- Service: `TenantsServiceImpl`
- SQL table: `tenants`
- Frontend API: `snowy-admin-web/src/api/tenant/tenantsApi.js`

## Added Routes

Protected routes:

- `GET /tenants/tenant/page`
- `GET /tenants/tenant/detail`
- `POST /tenants/tenant/add`
- `POST /tenants/tenant/edit`
- `POST /tenants/tenant/delete`

## Response Shape

Page responses return:

- `records`
- `total`
- `page`
- `current`
- `limit`
- `size`
- `pages`

Tenant rows return:

- `tenantId`
- `tenantName`
- `code`
- `createTime`
- `deleteFlag`
- `createUser`
- `updateTime`
- `updateUser`

## Supported Filters

- `id`
- `tenantId`
- `tenantName`
- `code`
- `searchKey`
- `current`, `page`, or `pageNo`
- `size`, `limit`, or `pageSize`
- `sortField`
- `sortOrder`

Supported sort fields are:

- `tenantId`
- `tenantName`
- `code`
- `createTime`
- `updateTime`

## Compatibility Notes

The `tenants` table uses mixed-case physical columns:

- `Tenant_ID`
- `Tenant_Name`

The ThinkPHP queries preserve those physical names and return Java-style camelCase fields to the frontend.

## Write Behavior

`POST /tenants/tenant/add` now validates `tenantName`, rejects duplicate active tenant names, inserts one active `tenants` row, generates a 10-digit numeric tenant code, fills create audit fields from the bearer token when available, and returns `data = null`.

`POST /tenants/tenant/edit` now validates `tenantId` and `tenantName`, rejects missing or system tenant rows, rejects duplicate active names, updates only `Tenant_Name` plus update audit fields, and returns `data = null`.

`POST /tenants/tenant/delete` requires the same safe-password marker used by the copied frontend. Missing safe state returns `code = 408` with `data = tenants`. With a valid marker, delete validates the full id batch, rejects missing/system/referenced tenant ids before writing, sets `DELETE_FLAG = DELETED`, updates audit fields, and returns `data = null`.

## Deliberate Exclusions

- No default tenant system data generation is performed.
- No tenant cache/event mutation is performed.
- No Java source files, database schema, `.env`, Composer files, or public config files are changed.

## Later Work

Tenant default user/role/resource/permission bootstrap, tenant cache/event handling, and a confirmed permission model remain deferred.
