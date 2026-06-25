# Biz Inventory Read-Only Compatibility

Date: 2026-05-29

Agent: merge-agent

## Scope

This slice adds old-frontend-compatible read-only endpoints for warehouse inventory data.

Java inputs:

- `snowy-admin-web/src/api/biz/inventoryApi.js`
- `snowy-admin-web/src/views/biz/inventory/index.vue`
- `snowy-plugin-biz/.../inventory/controller/InventoryController.java`
- `snowy-plugin-biz/.../inventory/service/impl/InventoryServiceImpl.java`
- `snowy-plugin-biz/.../inventory/result/ProductInventory.java`
- `F:\AI\projects\testJava\OA\oa2026.sql`

ThinkPHP outputs:

- `GET /biz/inventory/page`
- `GET /biz/inventory/list`
- `GET /biz/inventory/detail`
- `POST /biz/inventory/add`
- `POST /biz/warehouses/delivery/add`

## Behavior

- `page` and `list` require `warehousesId`, matching the Java `InventoryPageParam`.
- `page` returns a Java Page-style object with `records`, `total`, `current`, `size`, `page`, `limit`, and `pages`.
- `page` and `list` read `inventory` joined to enabled `biz_product` records.
- Rows include top-level inventory fields plus product display fields used by the old frontend: `productName`, `productCategory`, `safetyStock`, `purchasePrice`, `salePrice`, `minPrice`, `category`, and `specs`.
- Rows also include an `inventory` object containing the inventory-only fields for compatibility with Java `ProductInventory`.

## Inventory Add

`POST /biz/inventory/add` now implements the copied inventory page's "录入库存信息" action.

The route accepts the Java/frontend shape:

- `warehousesId`
- `productIds`

It validates the active warehouse in the current tenant, validates active enabled products, rejects duplicate product ids, keeps the inventory tenant aligned to the warehouse, and then inserts missing `inventory` rows with `CURRENT_COUNT = 0`.

For existing active warehouse/product rows, it preserves `CURRENT_COUNT`, sets `CURRENT_COUNT` to zero only when it was null, refreshes update audit fields, and increments `VERSION`. Deleted unique-key conflicts are rejected instead of silently creating duplicate rows.

The inventory add slice deliberately does not implement stock-in/stock-out movement, batch movement helpers, delivery records, purchase-order warehouse entry, Java data-change event publishing, schema changes, or Java source changes.

## Delivery Add Stocktake

`POST /biz/warehouses/delivery/add` implements Java-compatible system stocktake behavior for an existing warehouse/product inventory row.

The route accepts:

- `warehousesId`
- `productId`
- `amount`
- `deliveryTime`
- optional `remark`

Submitted `amount` is treated as the desired final inventory count. The route locks the active inventory row, computes movement from the current count, writes one system `IN` or `OUT` `delivery_record` row when the movement is non-zero, updates `inventory.CURRENT_COUNT` to the target amount, and increments `VERSION`.

This slice deliberately does not implement purchase-order warehouse entry, broader stock workflows, finance, workflow, Java event bus/data-change publishing, schema changes, or Java source changes.

## Controlled Deferred Writes

- `POST /biz/inventory/delete` returns a controlled `code = 400` deferred response.
- No inventory deletion behavior is executed by the wrapper.

## Notes

- Java inventory add and stock movement methods publish warehouse inventory data-change events. The ThinkPHP inventory add slice persists the inventory rows but intentionally excludes event publishing and stock movement side effects. The ThinkPHP delivery add slice performs the direct inventory mutation in one transaction because the Java event-bus handler is not present.
- Java page/list validation checks the warehouse exists before querying inventory. This ThinkPHP slice does the same using the imported `warehouses` table and tenant filter when present.

## 2026-06-15 HTTP Smoke Coverage

`scripts/inventory-delivery-read-http-smoke.ps1` now verifies authenticated inventory reads against the local backend:

- `GET /biz/inventory/page?warehousesId=...` returns Java-style paging keys and frontend-visible inventory/product fields when a visible row exists.
- `GET /biz/inventory/list?warehousesId=...` returns an array with the same row contract.
- `GET /biz/inventory/detail?id=...` is checked only with an id returned by the authenticated page result.

The read smoke is read-only. It does not call inventory add/delete, delivery add, batch adjustment, finance, workflow, provider, or data-change behavior.

## 2026-06-17 Inventory Add HTTP Smoke Coverage

`scripts/inventory-add-http-smoke.ps1` verifies authenticated inventory registration against the local backend:

- no-token rejection;
- missing `productIds`, duplicate product ids, and missing-product validation;
- successful insertion of two missing warehouse/product rows;
- existing warehouse/product inventory row preserves `CURRENT_COUNT` and increments `VERSION`;
- inserted row detail readback through `GET /biz/inventory/detail`;
- failed cases do not change `inventory` or `delivery_record` counts;
- successful add does not create `delivery_record` rows;
- temporary warehouse, product, and inventory rows are physically cleaned after the smoke.

## 2026-06-18 Delivery Add HTTP Smoke Coverage

`scripts/delivery-record-add-http-smoke.ps1` verifies authenticated system stocktake behavior against the local backend:

- no-token rejection;
- missing field and missing-inventory validation;
- failed cases do not change inventory or delivery counts;
- target count above current creates one `IN` delivery row with `Process_sys`;
- target count below current creates one `OUT` delivery row with `Process_sys`;
- repeating the same target creates no movement row;
- inventory `CURRENT_COUNT` and `VERSION` reflect the successful stocktake updates;
- temporary warehouse, product, inventory, and delivery rows are physically cleaned after the smoke.

Execution is pending because local MySQL `MySQL80` was stopped and failed to start on 2026-06-18.
