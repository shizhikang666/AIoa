# Biz Return Order Compatibility

Date: 2026-06-18

Agent: merge-agent / main control agent

## Java And Frontend Inputs

- `ReturnOrderController.java`
- `ReturnOrderServiceImpl.java`
- `ReturnOrder.java`
- `ReturnOrderItem.java`
- `ReturnOrderAddParam.java`
- `ReturnOrderItemAddParam.java`
- `ReturnOrderPageParam.java`
- `ReturnOrderQueryParam.java`
- `BizReturnOrderEventHandler.java`
- `ExpenditureRecordAddEventHandler.java`
- copied frontend `snowy-admin-web/src/api/biz/returnOrderApi.js`
- copied frontend `snowy-admin-web/src/views/biz/returnorder/*`

## Routes

- `GET /biz/returnorder/page`
- `GET /biz/returnorder/query`
- `GET /biz/returnorder/detail`
- `POST /biz/returnorder/add`
- `POST /biz/returnorder/edit`
- `POST /biz/returnorder/delete`

All routes are protected with `AuthMiddleware`.

## Read Response Notes

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

`detail` returns one row with `productList`.

## Write Behavior

`add` performs direct return-order master/detail maintenance:

- requires `projectId`, `amount >= 0`, `warehousesId`, and nonempty `productList`;
- validates the project through tenant/data-scope guards and requires a returnable project state: `PARTIALLY_SHIPPED`, `SHIPPED`, or `COMPLETED`;
- validates the warehouse through tenant and write-scope guards;
- rejects submitted `processId` values that already exist in `act_hi_procinst`;
- validates each project product item belongs to the project, is active, is `SHIPPED`, and does not make cumulative returned quantity exceed the original project product item quantity;
- writes `return_order` and `return_order_item` rows in one transaction;
- writes Java-compatible return IN `delivery_record` rows and increments matching `inventory` rows;
- sets `STATE = AlreadySettled` when `amount <= 0`, otherwise `STATE = Unsettled`;
- recalculates the owning sale project's `TOTAL_REFUND_AMOUNT`, `TOTAL_RETURN_AMOUNT`, and `TOTAL_PRICE`.

`edit` performs bounded row maintenance plus reverse correction:

- requires `id`;
- rejects workflow-owned rows whose current or submitted process id exists in `act_hi_procinst`;
- supports updating the master fields and, when `productList` is submitted, replaces active child rows by logical deletion plus fresh child inserts;
- requires a submitted `productList` when changing `projectId`;
- reruns the same project, warehouse, product-item, cumulative quantity, tenant, and data-scope checks as `add`;
- reverses active `ReturnAndRefund` expenditure/statement/account effects before applying the edit;
- reverses active return IN delivery/inventory effects before applying the edit;
- rebuilds return IN delivery/inventory rows for the edited order contents;
- recalculates affected sale-project totals.

`delete` performs full-batch logical deletion plus reverse correction:

- accepts Java-style `[{ id }]`, `idList`, `ids`, or a single `id`;
- validates every id before writing;
- rejects workflow-owned rows whose process id exists in `act_hi_procinst`;
- reverses active `ReturnAndRefund` expenditure/statement/account effects;
- reverses active return IN delivery/inventory effects;
- sets `return_order.DELETE_FLAG = DELETED` and active child `return_order_item.DELETE_FLAG = DELETED`;
- recalculates affected sale-project totals.

## Deliberately Deferred

- workflow/process start and approval behavior;
- no-account automatic refund creation;
- Java event bus publishing and notification side effects;
- Java source changes;
- database schema or field changes.

## Data Scope

The Java service filters by login-user data scope when available and falls back to the current login user. The ThinkPHP reads follow the same shape:

- use request `orgId` and child organizations when explicitly provided;
- otherwise use `data_scope_org_ids` from token payload when present;
- otherwise fall back to current token user id against `return_order.USER`.

Write guards use tenant checks plus project/warehouse/order ownership or scoped organization checks. `bizAdmin`, `superAdmin`, and tenant admin style roles can operate across visible tenant rows.

## HTTP Smoke Coverage

`scripts/business-read-http-smoke.ps1` verifies authenticated return-order reads against the local backend:

- `GET /biz/returnorder/page`
- `GET /biz/returnorder/query?projectId=...`
- `GET /biz/returnorder/detail?id=...`

`scripts/return-order-write-http-smoke.ps1` verifies the write behavior:

- no-token rejection;
- validation failures for missing project, missing warehouse, empty product list, missing project row, and missing project product item;
- invalid-add rollback with no master/detail rows;
- valid add with detail/page/query readback;
- project total recalculation after add and settlement-account `ReturnAndRefund` expenses;
- invalid edit rollback;
- valid edit after delivery/refund side effects, including refund/account reverse correction, delivery/inventory reverse correction, child-row replacement, rebuilt return IN delivery rows, and project total recalculation;
- valid delete after edited refund side effects, including account restoration, active delivery/expenditure/statement cleanup, inventory restoration, master/detail logical delete, and project total restoration.
