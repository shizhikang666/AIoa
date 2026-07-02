# Return Order Inventory And Refund Settlement Plan

## Scope

- Extend existing PHP `POST /biz/returnorder/add` so successful direct return-order creation writes Java-compatible IN `delivery_record` rows and increments `inventory` in the same transaction.
- Extend existing `Process_sale_project_product_return` approval side effects so workflow-created IN delivery rows also increment `inventory` in the same transaction.
- Extend existing settlement-account expense creation so `ReturnAndRefund` expenditure rows update the linked return-order settlement state and recalculate sale-project return totals.
- This slice originally preserved edit/delete protection after delivery/refund side effects. That boundary was superseded by `docs/tasks/return-order-reverse-stock-finance-plan.md`, which implements direct edit/delete reverse correction.

## Java Reference

- `ReturnOrderServiceImpl.add()` saves `return_order`, `return_order_item`, creates IN delivery rows through `DeliveryRecordService.addBathInRecord()`, and publishes a return-order data-change event.
- `DeliveryRecordEventHandler` receives delivery-record adds and calls `InventoryService.inInventory()` for IN rows.
- `ExpenditureRecordAddEventHandler` receives `ReturnAndRefund` expenditure adds, calls `ReturnOrderService.updateStatus()`, and then recalculates the sale-project return/payment totals.

## PHP Boundary

- PHP has no Java event bus, so inventory and refund settlement correction are explicit service calls inside the existing DB transactions.
- Kit/project-product-item relations keep the current decomposition behavior before delivery rows are merged by child product.
- Inventory rows are locked by warehouse/product/tenant. Missing inventory rows are created with the returned quantity, matching Java `inInventory()` save-or-update behavior.
- Over-refund rolls back the expenditure, statement, and account balance update.

## Non-Goals

- No Java source edits.
- No frontend source edits.
- No notification, data-change-event bus, file cleanup, provider calls, schema changes, `.env` changes, production data operations, commits, or pushes.

## Verification

- PHP syntax checks for touched service/controller/route files.
- PowerShell parser checks for touched smoke/progress scripts.
- DB-backed HTTP smoke covering direct return-order add inventory increment, edit/delete reverse correction after delivery/refund side effects, workflow return approval inventory increment, and `ReturnAndRefund` settlement correction/over-refund rollback.
- Existing return-order write smoke updated to expect the new side effects.
- Existing workflow project-return smoke updated to expect inventory increment.
- Frontend route/method/deferred-wrapper smokes and project-progress lean check.
