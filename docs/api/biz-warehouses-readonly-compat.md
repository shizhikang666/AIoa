# Biz Warehouses Read-Only Compatibility

Date: 2026-05-29

Agent: merge-agent

## Scope

This slice adds old-frontend-compatible read-only endpoints for warehouse master data.

Java inputs:

- `snowy-admin-web/src/api/biz/warehousesApi.js`
- `snowy-plugin-biz/.../warehouses/controller/WarehousesController.java`
- `snowy-plugin-biz/.../warehouses/service/impl/WarehousesServiceImpl.java`
- `snowy-plugin-biz/.../warehouses/entity/Warehouses.java`
- `F:\AI\projects\testJava\OA\oa2026.sql`

ThinkPHP outputs:

- `GET /biz/warehouses/page`
- `GET /biz/warehouses/list`
- `GET /biz/warehouses/detail`

## Behavior

- `page` returns a Java Page-style object with `records`, `total`, `current`, `size`, `page`, `limit`, and `pages`.
- `list` returns warehouse rows for selector-style consumers.
- `detail` returns one warehouse row by id.
- Warehouse rows are returned in lower-camel field names to match Java JSON serialization.
- `headName` is resolved from `sys_user.NAME` through the warehouse `USER` field.
- `orgName` is resolved from `sys_org.NAME` through the warehouse `ORG` field.

## Deferred

- No `/biz/warehouses/add` route.
- No `/biz/warehouses/edit` route.
- No `/biz/warehouses/delete` route.
- No warehouse validation/write behavior.
- No Java source changes.

## Notes

- Java page reads apply login-user data scope through the warehouse owner user. This slice applies tenant filtering from the bearer token and, when token data-scope org ids are present, filters by warehouse `ORG` or owner users in those orgs.
- If no data-scope org ids are present in the token, the slice does not add the Java fallback `USER = loginId` constraint yet, because the current auth token stores a simplified data-scope payload and imported superadmin data may otherwise become invisible.
