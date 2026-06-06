# Biz Product API Compatibility

Date: 2026-06-06

Agent: merge-agent / api-agent / frontend-agent

## Scope

This document tracks old-frontend-compatible endpoints for product master data.

Java inputs:

- `snowy-admin-web/src/api/biz/bizProductApi.js`
- `snowy-plugin-biz/.../bizproduct/controller/BizProductController.java`
- `snowy-plugin-biz/.../bizproduct/service/impl/BizProductServiceImpl.java`
- `snowy-plugin-biz/.../bizproduct/entity/BizProduct.java`
- `F:\AI\projects\testJava\OA\oa2026.sql`

ThinkPHP outputs:

- `GET /biz/bizproduct/page`
- `GET /biz/bizproduct/list`
- `GET /biz/bizproduct/detail`
- `POST /biz/bizproduct/children`
- `POST /biz/bizproduct/edit/status`
- `POST /biz/bizproduct/reconciliation/edit`

## Behavior

- `page` returns a Java Page-style object with `records`, `total`, `current`, `size`, `page`, `limit`, and `pages`.
- `list` returns product rows for selector-style consumers.
- `detail` returns the Java result shape: `bizProduct` plus `productList`.
- `children` reads `product_relation` rows for kit product children and returns `number`, `product`, and `objectId`.
- Product rows are returned in lower-camel field names to match Java JSON serialization.
- `page` hides disabled products by default unless `showDisabledProducts=true`, matching the Java service.
- Reads preserve the imported physical schema, including lower-case `status` in `biz_product`.

## Lightweight Write Behavior

### Product status edit

`POST /biz/bizproduct/edit/status`

Required fields:

- `id`
- `status`

Supported `status` values:

- `ENABLE`
- `DISABLE`

The endpoint validates the active product row and write scope, then updates only lower-case physical column `status` plus update audit fields.

### Product reconciliation edit

`POST /biz/bizproduct/reconciliation/edit`

Required fields:

- `ids`
- `reconciliationType`

Optional fields:

- `reconciliationAmount`

Supported `reconciliationType` values:

- `ENABLE`
- `DISABLE`

The endpoint validates every active product id and write scope, then updates only `RECONCILIATION_TYPE`, `RECONCILIATION_AMOUNT`, and update audit fields. `reconciliationAmount` must be empty/null or a non-negative number.

## Deferred

- No `/biz/bizproduct/add` route.
- No `/biz/bizproduct/edit` route.
- No `/biz/bizproduct/delete` route.
- No product relation writes, cache events, inventory changes, purchase writes, sale-project writes, workflow actions, or Java source changes.

## Notes

- The Java service applies login-user data scope. This slice applies tenant filtering from the bearer token and, when present, filters product `ORG` by token data-scope org ids.
- If no data-scope org ids are present in the token, the slice does not add the Java fallback `CREATE_USER = loginId` constraint yet, because the current auth token stores a simplified data-scope payload and old imported superadmin data may otherwise become invisible.
- Lightweight write routes use a stricter scope: admin-compatible roles, scoped organization ids, or matching product `CREATE_USER`.
