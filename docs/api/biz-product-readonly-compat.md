# Biz Product Read-Only Compatibility

Date: 2026-05-29

Agent: merge-agent

## Scope

This slice adds old-frontend-compatible read-only endpoints for product master data.

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

## Behavior

- `page` returns a Java Page-style object with `records`, `total`, `current`, `size`, `page`, `limit`, and `pages`.
- `list` returns product rows for selector-style consumers.
- `detail` returns the Java result shape: `bizProduct` plus `productList`.
- `children` reads `product_relation` rows for kit product children and returns `number`, `product`, and `objectId`.
- Product rows are returned in lower-camel field names to match Java JSON serialization.
- `page` hides disabled products by default unless `showDisabledProducts=true`, matching the Java service.
- Reads preserve the imported physical schema, including lower-case `status` in `biz_product`.

## Deferred

- No `/biz/bizproduct/add` route.
- No `/biz/bizproduct/edit` route.
- No `/biz/bizproduct/delete` route.
- No `/biz/bizproduct/reconciliation/edit` route.
- No `/biz/bizproduct/edit/status` route.
- No product relation writes, cache events, inventory changes, or Java source changes.

## Notes

- The Java service applies login-user data scope. This slice applies tenant filtering from the bearer token and, when present, filters product `ORG` by token data-scope org ids.
- If no data-scope org ids are present in the token, the slice does not add the Java fallback `CREATE_USER = loginId` constraint yet, because the current auth token stores a simplified data-scope payload and old imported superadmin data may otherwise become invisible.
