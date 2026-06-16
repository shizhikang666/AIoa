# Biz Purchase Order Read-Only Compatibility

## Scope

This slice adds protected read-only ThinkPHP routes compatible with the Java purchase-order controller and the existing Vue API module.

## Routes

- `GET /biz/bizpurchaseorder/page`
- `GET /biz/bizpurchaseorder/detail/list`
- `GET /biz/bizpurchaseorder/list`
- `GET /biz/bizpurchaseorder/detail`

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

## Controlled Deferred Writes

These protected copied-frontend write paths now return Java-style `code = 400` deferred responses:

- `POST /biz/bizpurchaseorder/add`
- `POST /biz/bizpurchaseorder/edit`
- `POST /biz/bizpurchaseorder/audit/edit`
- `POST /biz/bizpurchaseorder/delete`
- `POST /biz/bizpurchaseorder/cancel`
- `POST /biz/bizpurchaseorder/warehouse/add`
- `POST /biz/bizpurchaseorder/warehouse/one/add`

They do not create purchase orders, edit purchase order rows, audit records, cancel records, generate warehouse stock-in records, move inventory, create expenditure records, start workflow, mutate data-change events, change database schema, modify Java source, edit `.env`, or change Composer/public config files.

## Verification

- `composer dump-autoload`
- `php think`
- `php think route:list`
- PHP syntax lint
- Token smoke tests for page, list, detail/list, detail, and no-token 401.

## 2026-06-15 HTTP Smoke Coverage

`scripts/purchase-order-read-http-smoke.ps1` now verifies authenticated purchase-order read payloads against the local backend:

- `GET /biz/bizpurchaseorder/page`
- `GET /biz/bizpurchaseorder/list`
- `GET /biz/bizpurchaseorder/detail/list`
- `GET /biz/bizpurchaseorder/detail` when a visible page row exists

The smoke checks Java-style paging keys, stable frontend-visible order fields, `detail/list` `orderItems`, and the detail wrapper buckets `bizPurchaseOrder`, `bizPurchaseOrderItemList`, and `bizExpenditureRecordList`. It does not call add, edit, audit, delete, cancel, warehouse add, one-warehouse add, inventory stock movement, expenditure mutation, workflow, provider, or data-change behavior.
