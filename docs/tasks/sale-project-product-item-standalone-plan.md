# Sale Project Product Item Standalone Plan

Date: 2026-06-25

## Scope

Implement protected standalone product-item maintenance routes:

- `POST /biz/saleprojectproductitem/add`
- `POST /biz/saleprojectproductitem/edit`
- `POST /biz/saleprojectproductitem/delete`

Tables intentionally written:

- `biz_sale_project_product_item`
- `sale_project_product_item_relation`

## Mapped Behavior

- Java `BizSaleProjectProductItemController` currently leaves add/edit/delete commented, but `BizSaleProjectProductItemServiceImpl` still defines direct add, edit, and delete methods.
- Existing ThinkPHP sale-project form mutation already owns the compatible product item rules for normal project product rows: `CATEGORY = INIT`, `STATE = WAIT_DELIVER`, child relation expansion, kit child hydration, and reference protection.
- This slice reuses those rules for standalone route compatibility instead of adding workflow, delivery, invoice, stock, finance, or project-state side effects.

## Implementation Rules

- Routes are protected by the existing auth middleware.
- Add/edit/delete validate the owning sale project through tenant and data-scope rules.
- Mutations are limited to `FOLLOW` sale projects. Product-item changes after project init, delivery, invoice, return, or workflow activity remain deferred.
- Add creates one normal active product item and child relation rows inside one transaction.
- Edit supports full or partial item fields; omitted child rows preserve current relations when the product is unchanged.
- Delete logically deletes product-item rows and child relations.
- Active invoice-item or return-order-item references, and any delivered quantity, block delete and protected product/quantity/money/children edits with rollback.

## Deferred

- Product item mutation on non-`FOLLOW` projects.
- Delivery, invoice, return, inventory, payment, expenditure, settlement-account, workflow, notification, and Java event bus side effects.

## Smoke

Add `scripts/sale-project-product-item-standalone-http-smoke.ps1` to cover:

- no-token and validation guards;
- add with explicit kit children;
- edit preserving child relations when children are omitted;
- delete reference rollback;
- final logical delete of unreferenced rows;
- no delivery, inventory, invoice, finance, or workflow side effects.
