# Biz Warehouses API Compatibility

Date: 2026-06-06

Agent: api-agent / frontend-agent

## Scope

This document maps the Java warehouse master-data endpoints currently supported by the ThinkPHP compatibility layer.

Java inputs:

- `snowy-admin-web/src/api/biz/warehousesApi.js`
- `snowy-plugin-biz/.../warehouses/controller/WarehousesController.java`
- `snowy-plugin-biz/.../warehouses/service/impl/WarehousesServiceImpl.java`
- `snowy-plugin-biz/.../warehouses/entity/Warehouses.java`
- `F:\AI\projects\testJava\OA\oa2026.sql`

ThinkPHP outputs:

- `GET /biz/warehouses/page`
- `GET /biz/warehouses/list`
- `POST /biz/warehouses/add`
- `POST /biz/warehouses/edit`
- `POST /biz/warehouses/delete`
- `GET /biz/warehouses/detail`

## Behavior

- `page` returns a Java Page-style object with `records`, `total`, `current`, `size`, `page`, `limit`, and `pages`.
- `list` returns warehouse rows for selector-style consumers.
- `detail` returns one warehouse row by id.
- Warehouse rows are returned in lower-camel field names to match Java JSON serialization.
- `headName` is resolved from `sys_user.NAME` through the warehouse `USER` field.
- `orgName` is resolved from `sys_org.NAME` through the warehouse `ORG` field.

## Write Behavior

### Warehouse add

`POST /biz/warehouses/add`

Required fields:

- `name`
- `code`

Supported optional fields:

- `address`
- `sortCode`
- `extJson`

The endpoint inserts a new active warehouse row with `DELETE_FLAG = NOT_DELETE`, create audit fields, tenant id, `USER` from the current token user, and `ORG` from the current token user's organization.

### Warehouse edit

`POST /biz/warehouses/edit`

Required fields:

- `id`

Mutable fields when present:

- `name` (must not be blank when submitted)
- `code` (must not be blank when submitted)
- `address`
- `sortCode`
- `org`
- `extJson`

The endpoint validates that the current token user can write the existing warehouse through admin-compatible roles, scoped organization ids, or direct warehouse ownership. Submitted `org` values are also validated against token write scope.

### Warehouse delete

`POST /biz/warehouses/delete`

Accepted input shapes:

- `[{"id": "..."}]`
- `{"idList": ["..."]}`
- `{"ids": ["..."]}`
- `{"id": "..."}`

The endpoint validates every target warehouse through the current user's write scope, then performs a logical delete by setting `DELETE_FLAG = DELETED`. It does not physically remove imported warehouse data.

## Deferred

- No inventory stock update route.
- No delivery record write route.
- No purchase-order write route.
- No sale-project invoice write route.
- No workflow side effects.
- No Java source changes.

## Notes

- Java page reads apply login-user data scope through the warehouse owner user. This slice applies tenant filtering from the bearer token and, when token data-scope org ids are present, filters by warehouse `ORG` or owner users in those orgs.
- If no data-scope org ids are present in the token, the slice does not add the Java fallback `USER = loginId` constraint yet, because the current auth token stores a simplified data-scope payload and imported superadmin data may otherwise become invisible.
- Write routes use a stricter scope: admin-compatible roles, scoped organization ids, or matching warehouse `USER`.
