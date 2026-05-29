# Biz Supplier Read-Only Compatibility

Date: 2026-05-29

Agent: merge-agent

## Scope

This slice adds old-frontend-compatible read-only endpoints for supplier master data.

Java inputs:

- `snowy-admin-web/src/api/biz/supplierApi.js`
- `snowy-plugin-biz/.../supplier/controller/SupplierController.java`
- `snowy-plugin-biz/.../supplier/service/impl/SupplierServiceImpl.java`
- `snowy-plugin-biz/.../supplier/entity/Supplier.java`
- `F:\AI\projects\testJava\OA\oa2026.sql`

ThinkPHP outputs:

- `GET /biz/supplier/page`
- `GET /biz/supplier/list`
- `GET /biz/supplier/list/query/name`
- `GET /biz/supplier/detail`

## Behavior

- `page` returns a Java Page-style object with `records`, `total`, `current`, `size`, `page`, `limit`, and `pages`.
- `list` returns supplier rows for selector-style consumers.
- `list/query/name` searches enabled suppliers by `NAME` or `ALIAS_NAME`.
- `detail` returns one supplier row by id.
- Supplier rows are returned in lower-camel field names to match Java JSON serialization.
- Reads preserve the imported physical schema, including lower-case `org` in `supplier`.

## Deferred

- No `/biz/supplier/add` route.
- No `/biz/supplier/edit` route.
- No `/biz/supplier/delete` route.
- No supplier validation/write behavior.
- No Java source changes.

## Notes

- Java page reads apply login-user data scope. This slice applies tenant filtering from the bearer token and, when present, filters supplier `org` by token data-scope org ids.
- If no data-scope org ids are present in the token, the slice does not add the Java fallback `CREATE_USER = loginId` constraint yet, because the current auth token stores a simplified data-scope payload and imported superadmin data may otherwise become invisible.
