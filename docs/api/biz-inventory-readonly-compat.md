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

## Behavior

- `page` and `list` require `warehousesId`, matching the Java `InventoryPageParam`.
- `page` returns a Java Page-style object with `records`, `total`, `current`, `size`, `page`, `limit`, and `pages`.
- `page` and `list` read `inventory` joined to enabled `biz_product` records.
- Rows include top-level inventory fields plus product display fields used by the old frontend: `productName`, `productCategory`, `safetyStock`, `purchasePrice`, `salePrice`, `minPrice`, `category`, and `specs`.
- Rows also include an `inventory` object containing the inventory-only fields for compatibility with Java `ProductInventory`.

## Deferred

- No `/biz/inventory/add` route.
- No `/biz/inventory/delete` route.
- No stock in/out, batch stock movement, or inventory adjustment route.
- No data-change event behavior.
- No Java source changes.

## Notes

- Java inventory add and stock movement methods mutate `inventory` and publish warehouse inventory data-change events. Those operations are intentionally excluded from this read-only slice.
- Java page/list validation checks the warehouse exists before querying inventory. This ThinkPHP slice does the same using the imported `warehouses` table and tenant filter when present.

## 2026-06-15 HTTP Smoke Coverage

`scripts/inventory-delivery-read-http-smoke.ps1` now verifies authenticated inventory reads against the local backend:

- `GET /biz/inventory/page?warehousesId=...` returns Java-style paging keys and frontend-visible inventory/product fields when a visible row exists.
- `GET /biz/inventory/list?warehousesId=...` returns an array with the same row contract.
- `GET /biz/inventory/detail?id=...` is checked only with an id returned by the authenticated page result.

The smoke is read-only. It does not call inventory add/delete, stock movement, batch adjustment, delivery writes, finance, workflow, provider, or data-change behavior.
