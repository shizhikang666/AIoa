# Sale Project Invoice Add Plan

Date: 2026-06-25

Status: implemented and DB-backed HTTP-smoke verified.

## Scope

- Add bounded direct `POST /biz/saleprojectinvoice/add` support.
- Match the useful Java `BizSaleProjectInvoiceService.add` path: create one `biz_sale_project_invoice` row and linked `biz_sale_project_invoice_item` rows from submitted `projectProductItemList`.
- Mirror Java's invoice-item add event by incrementing the linked sale-project product items' `DELIVERY` and setting their delivery `STATE`.
- Recalculate the owning sale project's shipment state after delivery item updates.

## Transaction Strategy

- Lock the owning sale project before writing.
- Reject `FOLLOW` projects.
- Require a non-empty `processId`; reject duplicate active invoice rows for the same tenant and process id.
- Lock submitted sale-project product items, validate warehouse ids, validate positive delivery amounts, and reject over-delivery.
- Insert the invoice, insert invoice items, update product-item delivery state, and update sale-project state in one transaction.

## Explicit Non-Scope

- No direct invoice edit/delete route in this block.
- No `delivery_record` rows, inventory decrements, settlement-account statements, payment records, expenditure records, workflow runtime rows, notifications, file cleanup, Java data-change event bus, Java source changes, schema changes, `.env` changes, production data operations, or commits.
- Reverse delivery/product-item correction for invoice edit/delete remains a separate feature-closure candidate.

## Verification

- `php -l app\controller\biz\SaleProjectInvoiceController.php`
- `php -l app\service\biz\SaleProjectService.php`
- `php -l route\app.php`
- PowerShell parser check for `scripts\sale-project-invoice-add-http-smoke.ps1`
- `php think route:list | Select-String "saleprojectinvoice/add"`
- `php think route:list` concrete route count: 583
- `.\scripts\sale-project-invoice-add-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`
- Existing workflow project-delivery approval smoke regression.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\project-progress.ps1 -Lean`
- `git diff --check` with LF/CRLF warnings only
