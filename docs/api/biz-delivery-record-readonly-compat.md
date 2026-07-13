# Biz Delivery Record Compatibility

Date: 2026-06-18

Agent: merge-agent

## Scope

This slice adds old-frontend-compatible endpoints for warehouse delivery records and the Java-compatible system stocktake action.

Java inputs:

- `snowy-admin-web/src/api/biz/deliveryRecordApi.js`
- `snowy-admin-web/src/views/biz/bizproduct/details/inventoryInfo/inventoryInfo.vue`
- `snowy-admin-web/src/views/biz/inventory/exportExcel/index.vue`
- `snowy-plugin-biz/.../warehouses/controller/DeliveryRecordController.java`
- `snowy-plugin-biz/.../warehouses/service/impl/DeliveryRecordServiceImpl.java`
- `snowy-plugin-biz/.../warehouses/entity/DeliveryRecord.java`
- `F:\AI\projects\testJava\OA\oa2026.sql`

ThinkPHP outputs:

- `GET /biz/warehouses/delivery/page`
- `GET /biz/warehouses/delivery/exportOtherCompanyRecordsList`
- `GET /biz/warehouses/delivery/detail`
- `POST /biz/warehouses/delivery/add`

## Behavior

- `page` returns a Java Page-style object with `records`, `total`, `current`, `size`, `page`, `limit`, and `pages`.
- `exportOtherCompanyRecordsList` returns a list and requires `orgId` and `warehousesId`, matching Java validation intent.
- Rows are enriched with `warehousesName`, `productName`, and `operatorName` display fields.
- Export rows include product fields used by the frontend export screen: `productCategory`, `safetyStock`, `specs`, `minPrice`, `salePrice`, and `purchasePrice`.
- The frontend export form sends `completionTime` as a range; this slice also accepts Java-shaped `deliveryStartTime` and `deliveryEndTime`.
- `detail` is added as a read compatibility helper because the old frontend API wrapper includes `deliveryRecordDetail`, even though the analyzed Java controller does not expose a detail mapping.

## Delivery Add Stocktake

- `POST /biz/warehouses/delivery/add` now implements the copied inventory page's system stocktake behavior.
- The submitted `amount` is treated as the target stock count, not a delta.
- The route locks the active `inventory` row for `warehousesId` and `productId`.
- If target stock is greater than current stock, it writes one `delivery_record` row with `CATEGORY = IN` for the difference.
- If target stock is lower than current stock, it writes one `delivery_record` row with `CATEGORY = OUT` for the absolute difference.
- If target stock is unchanged, it does not create a delivery row.
- Movement rows use `PROCESS_ID = Process_sys` and `PROCESS_CATEGORY = Process_sys`.
- The same transaction updates `inventory.CURRENT_COUNT` to the submitted target amount and increments `VERSION`.

## Notes

- Java delivery record `add` publishes a delivery-record data-change event, and Java's event handler mutates inventory. ThinkPHP performs the durable delivery row and inventory mutation directly in the same transaction and still excludes Java data-change event publishing.
- This slice keeps tenant filtering from the bearer token when present and does not introduce extra data-scope constraints that are absent from the Java delivery record page query.
- No delivery edit/delete route, purchase-order warehouse stock-in, sale-project delivery, return stock-in, workflow, provider, or frontend source behavior was added.

## 2026-06-15 HTTP Smoke Coverage

`scripts/inventory-delivery-read-http-smoke.ps1` now verifies authenticated delivery-record reads against the local backend:

- `GET /biz/warehouses/delivery/page` returns Java-style paging keys and frontend-visible delivery/product/operator fields when a visible row exists.
- `GET /biz/warehouses/delivery/detail?id=...` is checked only with an id returned by the authenticated page result.
- `GET /biz/warehouses/delivery/exportOtherCompanyRecordsList?warehousesId=...&orgId=...` returns an array and validates the export-list read contract when sample warehouse/org values are available.

The smoke is read-only. It does not call delivery add/edit/delete, stock movement, inventory mutation, finance, workflow, provider, or data-change behavior.

## 2026-06-18 Delivery Add Smoke

`scripts/delivery-record-add-http-smoke.ps1` verifies authenticated delivery stocktake writes against the local backend:

- no-token rejection;
- missing-field validation;
- missing inventory rollback;
- target increase creates an `IN` delivery row and updates inventory;
- target decrease creates an `OUT` delivery row and updates inventory;
- unchanged target creates no delivery row;
- delivery detail readback exposes `Process_sys`, category, amount, product, warehouse, and operator fields;
- temporary warehouse, product, inventory, and delivery rows are physically cleaned after the smoke.

This DB-backed smoke was added on 2026-06-18 but could not be executed in that run because local MySQL `MySQL80` was stopped and failed to start.
