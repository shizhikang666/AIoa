# Biz Supplier API Compatibility

Date: 2026-06-05

Agent: api-agent / frontend-agent

## Scope

This document maps the Java supplier master-data endpoints currently supported by the ThinkPHP compatibility layer.

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
- `POST /biz/supplier/add`
- `POST /biz/supplier/edit`
- `POST /biz/supplier/delete`
- `GET /biz/supplier/detail`

## Behavior

- `page` returns a Java Page-style object with `records`, `total`, `current`, `size`, `page`, `limit`, and `pages`.
- `list` returns supplier rows for selector-style consumers.
- `list/query/name` searches enabled suppliers by `NAME` or `ALIAS_NAME`.
- `detail` returns one supplier row by id.
- Supplier rows are returned in lower-camel field names to match Java JSON serialization.
- Reads preserve the imported physical schema, including lower-case `org` in `supplier`.

## Write Behavior

### Supplier add

`POST /biz/supplier/add`

Required fields:

- `name`
- `contacts`
- `phone`

Supported mutable fields:

- `aliasName`
- `bankName`
- `bankAccount`
- `status`
- `enterpriseNature`
- `taxRegistrationNumber`
- `paymentMethod`
- `sortCode`
- `extJson`

If `status` is empty, the endpoint defaults it to `ENABLE`, matching Java `SupplierServiceImpl.add`. The endpoint writes `DELETE_FLAG = NOT_DELETE`, create audit fields, tenant id, and the current token user's organization into the lower-case physical `org` column.

### Supplier edit

`POST /biz/supplier/edit`

Required fields:

- `id`
- `name`
- `contacts`
- `phone`
- `status`

The endpoint validates that the target supplier is active and writable by the current token user. Admin-compatible users can write all tenant rows; scoped users can write rows in their scoped organizations; otherwise the creator can write their own supplier rows.

### Supplier delete

`POST /biz/supplier/delete`

Accepted input shapes:

- `[{"id": "..."}]`
- `{"idList": ["..."]}`
- `{"ids": ["..."]}`
- `{"id": "..."}`

The endpoint validates every target supplier through the current user's write scope, then performs a logical delete by setting `DELETE_FLAG = DELETED`. It does not physically remove imported supplier data.

## Deferred

- No supplier import/export route.
- No purchase, payment, procurement, inventory, or workflow side effects.
- No Java source changes.

## Notes

- Java page reads apply login-user data scope. This slice applies tenant filtering from the bearer token and, when present, filters supplier `org` by token data-scope org ids.
- If no data-scope org ids are present in the token, the slice does not add the Java fallback `CREATE_USER = loginId` constraint yet, because the current auth token stores a simplified data-scope payload and imported superadmin data may otherwise become invisible.
- Write routes use a stricter scope: admin-compatible roles, scoped organization, or matching `CREATE_USER`.

## 2026-06-15 HTTP Smoke Coverage

`scripts/supplier-warehouse-read-http-smoke.ps1` now verifies authenticated supplier reads against the local backend:

- `GET /biz/supplier/page`
- `GET /biz/supplier/list`
- `GET /biz/supplier/list/query/name` when a visible supplier name exists
- `GET /biz/supplier/detail` when a visible page row exists

The smoke checks Java-style paging keys and stable frontend-visible fields such as `name`, `contacts`, `phone`, `bankName`, `bankAccount`, `status`, `aliasName`, `org`, and `orgName`. It does not call supplier add, edit, delete, import/export, purchase, payment, procurement, inventory, workflow, provider, or data-change behavior.
