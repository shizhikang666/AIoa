# Biz Purchase Order Read-Only Compatibility

## Scope

This slice adds protected ThinkPHP purchase-order read routes plus narrow cancel, edit, audit-edit, single-order warehouse stock-in, and batch warehouse stock-in routes compatible with the Java purchase-order controller and the existing Vue API module.

## Routes

- `GET /biz/bizpurchaseorder/page`
- `GET /biz/bizpurchaseorder/detail/list`
- `GET /biz/bizpurchaseorder/list`
- `GET /biz/bizpurchaseorder/detail`
- `POST /biz/bizpurchaseorder/cancel`
- `POST /biz/bizpurchaseorder/edit`
- `POST /biz/bizpurchaseorder/audit/edit`
- `POST /biz/bizpurchaseorder/warehouse/one/add`
- `POST /biz/bizpurchaseorder/warehouse/add`

## Java References

- `vip.xiaonuo.biz.modular.bizpurchaseorder.controller.BizPurchaseOrderController`
- `vip.xiaonuo.biz.modular.bizpurchaseorder.service.impl.BizPurchaseOrderServiceImpl`
- `vip.xiaonuo.biz.modular.bizpurchaseorder.entity.BizPurchaseOrder`
- `vip.xiaonuo.biz.modular.bizpurchaseorder.entity.BizPurchaseOrderItem`
- `vip.xiaonuo.biz.modular.bizpurchaseorder.result.BizPurchaseOrderDetail`

## Tables

- `biz_purchase_order`
- `biz_purchase_order_item`
- `biz_product`
- `biz_expenditure_record`
- `sys_org`

## Supported Filters

- `current`, `size`, `page`, `limit`, `pageNo`, `pageSize`
- `sortField`, `sortOrder`
- `id`
- `settlementStatus`
- `storageStatus`
- `supplierId`
- `supplierName`
- `instanceId`
- `productName`
- `minAmount`
- `maxAmount`
- `startCreateTime`
- `endCreateTime`
- `orgId`
- `tenantId`

## Response Notes

- `page` returns `records`, `total`, `current`, `size`, `pages`.
- `list` returns plain order rows.
- `detail/list` returns order rows with `orderItems`.
- `detail` returns the Java-compatible wrapper:
  - `bizPurchaseOrder`
  - `bizPurchaseOrderItemList`
  - `bizExpenditureRecordList`
- Supplier display data is decoded from `EXT_JSON.supplier`, matching the imported SQL data shape.
- Product display fields are joined from `biz_product`.

## Cancel Status Route

`POST /biz/bizpurchaseorder/cancel` accepts the copied frontend payload:

- `id`

The route is intentionally narrow:

- locks the active purchase-order row in the current tenant;
- requires admin-compatible role, matching data-scope organization, or matching `CREATE_USER`;
- rejects completed settlement status with `采购单已结算不能修改状态`;
- rejects in-warehouse status with `采购单已入库不能修改状态`;
- updates only `biz_purchase_order.SETTLEMENT_STATUS = Canceled`, `UPDATE_TIME`, `UPDATE_USER`, and `VERSION`;
- does not edit purchase-order items, warehouse records, inventory, expenditure records, settlement-account statements, workflow state, data-change events, Java source, `.env`, Composer config, or frontend source.

## Purchase Order Edit Route

`POST /biz/bizpurchaseorder/edit` accepts the copied frontend edit payload:

- `id`
- `amount`
- `productList`

Each `productList` entry updates only existing purchase-order item fields exposed by Java `BizPurchaseOrderItemEditParam`:

- `id`
- `amount`
- `unitAmount`
- `discountRate`
- `freightShareAmount`
- `unitCostWithFreight`

The route is intentionally narrow:

- locks the active purchase-order row in the current tenant;
- requires admin-compatible role, matching data-scope organization, or matching `CREATE_USER`;
- rejects completed settlement status with `已结算订单不支持修改！`;
- rejects orders that already have goods expenditure records with `该订单已有支出记录不支持修改！`;
- requires each submitted item id to be an active row belonging to the same order;
- updates only `biz_purchase_order.AMOUNT`, purchase-order item amount/cost fields, `UPDATE_TIME`, `UPDATE_USER`, and `VERSION`;
- does not create or delete purchase orders or items, audit records, warehouse records, inventory, expenditure records, settlement-account statements, workflow state, data-change events, Java source, `.env`, Composer config, or frontend source.

## Purchase Order Audit Edit Route

`POST /biz/bizpurchaseorder/audit/edit` accepts the same copied frontend audit remediation payload as normal edit:

- `id`
- `amount`
- `productList`

Each `productList` entry updates only existing purchase-order item fields exposed by Java `BizPurchaseOrderItemEditParam`.

The route is intentionally narrow:

- locks the active purchase-order row in the current tenant;
- requires admin-compatible role, matching data-scope organization, or matching `CREATE_USER`;
- intentionally skips the normal edit guards for completed settlement and existing goods expenditure records, matching Java `editAudit`;
- requires each submitted item id to be an active row belonging to the same order;
- updates only `biz_purchase_order.AMOUNT`, purchase-order item amount/cost fields, `UPDATE_TIME`, `UPDATE_USER`, and `VERSION`;
- does not create or delete purchase orders or items, audit records, warehouse records, inventory, expenditure records, settlement-account statements, workflow state, data-change events, Java source, `.env`, Composer config, or frontend source.

## Purchase Order Warehouse One Add Route

`POST /biz/bizpurchaseorder/warehouse/one/add` accepts the copied one-click warehouse form payload:

- `orderId`
- `warehousesId`
- optional `remark`

The route implements the Java single purchase-order stock-in path:

- locks the active purchase order in the current tenant;
- requires admin-compatible role, matching data-scope organization, or matching `CREATE_USER`;
- requires the order `STORAGE_STATUS` to be `NOT_IN_WAREHOUSE`;
- validates the active warehouse and referenced product ids;
- writes one `delivery_record` row per active purchase-order item with `CATEGORY = IN`, `PROCESS_ID = Process_sys`, `PROCESS_CATEGORY = Process_procure_in_warehouse`, `OBJECT_ID = orderId`, and item `NUMBER` as `AMOUNT`;
- creates missing inventory rows or increments active inventory rows for the selected warehouse/product pairs;
- updates the purchase order and its items to `STORAGE_STATUS = IN_WAREHOUSE`;
- refreshes audit fields and increments `VERSION` on changed order, item, and inventory rows.

This route intentionally does not implement batch stock-in selection, purchase-order creation/deletion, expenditure creation, settlement-account statements, workflow start/approval, Java data-change event publishing, Java source, `.env`, Composer config, or frontend source changes.

## Purchase Order Warehouse Add Route

`POST /biz/bizpurchaseorder/warehouse/add` accepts the copied batch warehouse form payload:

- `warehousesId`

The route implements the Java batch stock-in path:

- selects active purchase orders visible to the current user with `SETTLEMENT_STATUS = COMPLETED` and `STORAGE_STATUS = NOT_IN_WAREHOUSE`;
- locks selected orders and their active item rows in one transaction;
- validates the active warehouse and referenced product ids per selected order tenant;
- writes one `delivery_record` row per active purchase-order item with `CATEGORY = IN`, `PROCESS_ID = Process_sys`, `PROCESS_CATEGORY = Process_procure_in_warehouse`, `OBJECT_ID = orderId`, and item `NUMBER` as `AMOUNT`;
- creates missing inventory rows or increments active inventory rows for the selected warehouse/product pairs;
- updates selected purchase orders and their items to `STORAGE_STATUS = IN_WAREHOUSE`;
- refreshes audit fields and increments `VERSION` on changed order, item, and inventory rows.

If no completed not-in-warehouse purchase orders are visible, the route returns success with `count = 0` and performs no stock movement.

This route intentionally does not implement purchase-order creation/deletion, expenditure creation, settlement-account statements, workflow start/approval, Java data-change event publishing, Java source, `.env`, Composer config, or frontend source changes.

## Controlled Deferred Writes

These protected copied-frontend write paths now return Java-style `code = 400` deferred responses:

- `POST /biz/bizpurchaseorder/add`
- `POST /biz/bizpurchaseorder/delete`

They do not create purchase orders, audit records, create expenditure records, start workflow, mutate data-change events, change database schema, modify Java source, edit `.env`, or change Composer/public config files.

## Verification

- `composer dump-autoload`
- `php think`
- `php think route:list`
- PHP syntax lint
- Token smoke tests for page, list, detail/list, detail, cancel status, edit, audit edit, warehouse one add, warehouse batch add, and no-token 401.

## 2026-06-15 HTTP Smoke Coverage

`scripts/purchase-order-read-http-smoke.ps1` now verifies authenticated purchase-order read payloads against the local backend:

- `GET /biz/bizpurchaseorder/page`
- `GET /biz/bizpurchaseorder/list`
- `GET /biz/bizpurchaseorder/detail/list`
- `GET /biz/bizpurchaseorder/detail` when a visible page row exists

The smoke checks Java-style paging keys, stable frontend-visible order fields, `detail/list` `orderItems`, and the detail wrapper buckets `bizPurchaseOrder`, `bizPurchaseOrderItemList`, and `bizExpenditureRecordList`. It does not call add, audit, delete, warehouse add, one-warehouse add, inventory stock movement, expenditure mutation, workflow, provider, or data-change behavior.

## 2026-06-17 Cancel HTTP Smoke Coverage

`scripts/purchase-order-cancel-http-smoke.ps1` verifies authenticated purchase-order cancel behavior against the local backend:

- no-token rejection;
- missing id and missing order failures;
- completed-settlement and in-warehouse guards;
- successful `SETTLEMENT_STATUS = Canceled`;
- detail readback after cancel;
- `VERSION` increment and update audit;
- unchanged purchase-order item, expenditure, inventory, and delivery row counts.

## 2026-06-17 Edit HTTP Smoke Coverage

`scripts/purchase-order-edit-http-smoke.ps1` verifies authenticated purchase-order edit behavior against the local backend:

- no-token rejection;
- missing id and missing productList failures;
- missing-order, completed-settlement, goods-expenditure, and wrong-item-order guards;
- successful order amount and purchase-order item amount/cost field update;
- detail readback after edit;
- `VERSION` increment and update audit on the edited order and item;
- preservation of storage status, settlement status, order number, delete flags, unrelated expenditure rows, and related table counts.

## 2026-06-17 Audit Edit HTTP Smoke Coverage

`scripts/purchase-order-audit-edit-http-smoke.ps1` verifies authenticated purchase-order audit edit behavior against the local backend:

- no-token rejection;
- missing id, missing productList, duplicate item, missing-order, and wrong-item-order guards;
- normal `/edit` still rejects the same completed order before audit edit succeeds;
- successful audit edit against a completed order with an existing goods-expenditure row;
- detail readback after audit edit;
- `VERSION` increment and update audit on the edited order and item;
- preservation of settlement/storage status, item row identity, goods-expenditure row, inventory/delivery counts, and related table counts.

## 2026-06-18 Warehouse One Add HTTP Smoke Coverage

`scripts/purchase-order-warehouse-one-add-http-smoke.ps1` verifies authenticated single purchase-order warehouse stock-in behavior against the local backend:

- no-token rejection;
- missing `orderId`, missing warehouse, already-warehoused order, and invalid product-id rollback;
- successful `warehouse/one/add` inserts one `IN` delivery row per item with `Process_sys` and `Process_procure_in_warehouse`;
- successful stock-in increments existing inventory and creates missing inventory rows;
- purchase order and item `STORAGE_STATUS` values move to `IN_WAREHOUSE`;
- `VERSION` and audit fields are updated on changed order, item, and inventory rows;
- repeating the same stock-in is rejected without extra delivery rows;
- temporary warehouse, product, purchase-order, item, inventory, and delivery rows are physically cleaned after the smoke.

Execution is pending because local MySQL `MySQL80` was stopped and failed to start on 2026-06-18.

## 2026-06-18 Warehouse Add HTTP Smoke Coverage

`scripts/purchase-order-warehouse-add-http-smoke.ps1` verifies authenticated batch purchase-order warehouse stock-in behavior against the local backend:

- no-token rejection;
- missing `warehousesId` validation;
- invalid product-id rollback across the whole eligible batch;
- missing warehouse rollback while eligible orders still exist;
- successful batch stock-in only processes completed not-in-warehouse orders;
- non-completed and already-in-warehouse orders stay unchanged;
- successful batch stock-in inserts one `IN` delivery row per processed item with `Process_sys` and `Process_procure_in_warehouse`;
- successful stock-in increments existing inventory and creates missing inventory rows;
- processed purchase orders and item rows move to `IN_WAREHOUSE`;
- repeating the batch after all eligible orders are in warehouse returns `count = 0` without extra delivery rows;
- temporary warehouse, product, purchase-order, item, inventory, and delivery rows are physically cleaned after the smoke.

Execution is pending because local MySQL `MySQL80` is stopped.
