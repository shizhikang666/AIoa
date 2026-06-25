# Sale Project Product Item Mutation Plan

Date: 2026-06-18

## Scope

Implement copied-form `productList` mutation for normal sale-project `add` and `edit`.

Covered routes:

- `POST /biz/saleproject/add`
- `POST /biz/saleproject/edit`

Tables intentionally written:

- `biz_sale_project`
- `biz_sale_project_product_item`
- `sale_project_product_item_relation`

## Mapped Behavior

- Java normal `BizSaleProjectAddParam` and `BizSaleProjectEditParam` expose only base project fields.
- Java project apply flow owns product-list binding through `BizSaleProjectProductItemService.unbindAndAddBatch`, which sets product rows to `CATEGORY = INIT` and `STATE = WAIT_DELIVER`.
- Java product-item data-change events expand submitted `children` into `sale_project_product_item_relation`.
- The copied Vue sale-project form submits `productList` through `/biz/saleproject/add|edit`; `null` means unchanged.
- Copied process forms can submit kit-product `children`; normal sale-project form may omit children, so ThinkPHP hydrates kit children from `product_relation.CATEGORY = KIT_PRODUCT_DATA`.

## Implemented Rules

- `productList` omitted or `null`: preserve existing product rows.
- `productList` array: synchronize active rows in the same project transaction.
- Existing submitted rows are updated, omitted rows are logically deleted, and new rows are inserted.
- Product rows validate enabled active `biz_product` records in the current tenant/data scope.
- Quantities must be positive integers; money fields must be non-negative.
- Removed product-item rows and child relations are logically deleted.
- Active invoice-item or return-order-item references block deletion and product/quantity/money/child changes with rollback.

## Deferred

- Direct standalone product-item add/edit/delete routes.
- Delivery, invoice, inventory, payment, expenditure, settlement-account, reissue-order, workflow, file cleanup, notification, and Java event bus side effects.

## Smoke

`scripts/sale-project-product-item-mutation-http-smoke.ps1` covers:

- no-token and invalid-product rollback;
- add with direct product and kit-product child hydration;
- detail/product readback;
- edit update/insert/logical-delete behavior;
- `productList = null` preservation;
- referenced item deletion blocker;
- empty-array clear for unreferenced rows.
