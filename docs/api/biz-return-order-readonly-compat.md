# Biz Return Order Read-Only Compatibility

Date: 2026-06-01

Agent: merge-agent

## Java Inputs

- `ReturnOrderController.java`
- `ReturnOrderServiceImpl.java`
- `ReturnOrder.java`
- `ReturnOrderItem.java`
- `ReturnOrderPageParam.java`
- `ReturnOrderQueryParam.java`
- `F:\AI\projects\testJava\OA\oa2026.sql`
- old frontend `snowy-admin-web/src/api/biz/returnOrderApi.js`

## Added Routes

- `GET /biz/returnorder/page`
- `GET /biz/returnorder/query`
- `GET /biz/returnorder/detail`

All routes are protected with `AuthMiddleware`.

## Response Notes

`page` returns Java-style pagination fields:

- `records`
- `total`
- `current`
- `page`
- `size`
- `limit`
- `pages`

Return-order rows include:

- `id`
- `projectId`
- `projectName`
- `amount`
- `state`
- `processId`
- `remark`
- `warehousesId`
- `warehouseName`
- `logisticsCategory`
- `logisticsId`
- `user`
- `headName`
- `org`
- `orgName`
- audit and tenant fields

`query` requires `projectId` and returns each row with `productList`, matching the Java service behavior used by sale-project detail pages.

`detail` is read-only and also returns `productList` to make old frontend detail views safer, without adding any mutation behavior.

## Data Scope

The Java service filters by login-user data scope when available and falls back to the current login user. The ThinkPHP service follows the same shape:

- use request `orgId` and child organizations when explicitly provided;
- otherwise use `data_scope_org_ids` from token payload when present;
- otherwise fall back to current token user id against `return_order.USER`.

## Explicitly Deferred

- return-order add/edit/delete
- settlement-status update
- refund/expenditure mutation
- delivery-record creation
- inventory stock mutation
- workflow/process mutation
- Java source changes
- database schema or field changes
