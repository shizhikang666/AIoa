# Tenants Read-Only Compatibility

## Scope

This slice adds authenticated, read-only compatibility for the tenant management page.

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

## Deliberate Exclusions

- No `/tenants/tenant/add` route is implemented.
- No `/tenants/tenant/edit` route is implemented.
- No `/tenants/tenant/delete` route is implemented.
- No default tenant system data generation is performed.
- No tenant cache/event mutation is performed.
- No Java source files, database schema, `.env`, Composer files, or public config files are changed.

## Later Work

Tenant add/edit/delete require validation, safe-password verification, system-tenant protection, default user/role/resource generation, cache/event handling, and a confirmed permission model before they can be enabled.
