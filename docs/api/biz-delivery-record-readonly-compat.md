# Biz Delivery Record Read-Only Compatibility

Date: 2026-05-29

Agent: merge-agent

## Scope

This slice adds old-frontend-compatible read-only endpoints for warehouse delivery records.

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

## Behavior

- `page` returns a Java Page-style object with `records`, `total`, `current`, `size`, `page`, `limit`, and `pages`.
- `exportOtherCompanyRecordsList` returns a list and requires `orgId` and `warehousesId`, matching Java validation intent.
- Rows are enriched with `warehousesName`, `productName`, and `operatorName` display fields.
- Export rows include product fields used by the frontend export screen: `productCategory`, `safetyStock`, `specs`, `minPrice`, `salePrice`, and `purchasePrice`.
- The frontend export form sends `completionTime` as a range; this slice also accepts Java-shaped `deliveryStartTime` and `deliveryEndTime`.
- `detail` is added as a read-only compatibility helper because the old frontend API wrapper includes `deliveryRecordDetail`, even though the analyzed Java controller does not expose a detail mapping.

## Controlled Deferred Writes

- `POST /biz/warehouses/delivery/add` returns a controlled `code = 400` deferred response.
- No delivery record edit/delete route.
- No stock in/out adjustment.
- No `inventory` update.
- No data-change event behavior.
- No Java source changes.

## Notes

- Java delivery record `add` compares requested stock against current inventory, creates an IN or OUT delivery record, mutates inventory, and publishes data-change events. That behavior is intentionally excluded from this read-only slice.
- This slice keeps tenant filtering from the bearer token when present and does not introduce extra data-scope constraints that are absent from the Java delivery record page query.

## 2026-06-15 HTTP Smoke Coverage

`scripts/inventory-delivery-read-http-smoke.ps1` now verifies authenticated delivery-record reads against the local backend:

- `GET /biz/warehouses/delivery/page` returns Java-style paging keys and frontend-visible delivery/product/operator fields when a visible row exists.
- `GET /biz/warehouses/delivery/detail?id=...` is checked only with an id returned by the authenticated page result.
- `GET /biz/warehouses/delivery/exportOtherCompanyRecordsList?warehousesId=...&orgId=...` returns an array and validates the export-list read contract when sample warehouse/org values are available.

The smoke is read-only. It does not call delivery add/edit/delete, stock movement, inventory mutation, finance, workflow, provider, or data-change behavior.
