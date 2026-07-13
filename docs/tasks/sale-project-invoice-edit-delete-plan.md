# Sale Project Invoice Edit Delete Plan

Date: 2026-06-25

Status: implemented and DB-backed HTTP-smoke verified.

## Scope

- Add protected `POST /biz/saleprojectinvoice/edit` and `POST /biz/saleprojectinvoice/delete`.
- Keep edit narrow: update delivery-invoice master/logistics fields only, without replacing invoice items.
- Keep delete transactional: validate all selected invoices first, logically delete invoice and invoice-item rows, reverse linked sale-project product-item `DELIVERY`, recalculate product-item `STATE`, and recalculate sale-project shipment state.
- Protect workflow-owned delivery invoices: if an invoice `PROCESS_ID` has active `delivery_record` rows, direct delete is rejected and `PROCESS_ID` changes are rejected.

## Transaction Strategy

- Lock selected active invoice rows through the existing sale-project tenant/data-scope guard.
- Reject missing rows, tenant mismatch, invalid project ownership, duplicate active `PROCESS_ID`, and delivery-record-backed invoices before writing.
- Lock active invoice items and linked project-product items before reverse correction.
- Reject reverse underflow, then update product-item delivery/state, invoice item `DELETE_FLAG`, invoice `DELETE_FLAG`, and project shipment state in the same transaction.

## Explicit Non-Scope

- No standalone invoice-item add/edit/delete or item replacement through invoice edit.
- No `delivery_record` deletion, inventory restoration, finance/settlement/payment/expenditure mutation, workflow runtime mutation, notifications, file cleanup, Java data-change event bus, Java source changes, schema changes, `.env` changes, production data operations, or commits.

## Verification

- `php -l app\controller\biz\SaleProjectInvoiceController.php`
- `php -l app\service\biz\SaleProjectService.php`
- `php -l route\app.php`
- PowerShell parser checks for `scripts\sale-project-invoice-add-http-smoke.ps1`, `scripts\project-preflight.ps1`, and `scripts\project-progress.ps1`
- `php think route:list | Select-String -Pattern 'saleprojectinvoice/(add|edit|delete)'`
- `php think route:list` concrete route count: 585
- `.\scripts\sale-project-invoice-add-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`
- Workflow project-delivery approval regression smoke
- Frontend route/method/deferred-wrapper smokes
- `.\scripts\project-progress.ps1 -Lean`
- `git diff --check` with LF/CRLF warnings only
