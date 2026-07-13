# Sale Project Amount Edit Plan

Date: 2026-06-18

## Goal

Replace `/biz/saleproject/amount/edit` controlled-deferred behavior with Java-compatible sale-project amount maintenance.

## Java Reference

- `BizSaleProjectController.editProjectAmount`
- `BizSaleProjectServiceImpl.editProjectAmount`
- `projectStatusCorrector`
- `projectPaymentStatusCorrector`
- `projectTotalPriceCollectedCorrector`
- `SalesProjectFieldChangeLogService.add`

## Scope

- Accept Java/copied-frontend `id` and `initPrice`, with optional `remark`.
- Validate active sale-project access through the existing tenant/data-scope-aware project query.
- Update `INIT_PRICE`.
- Recalculate:
  - `AMOUNT_COLLECTED` from active `PROJECT_PLAY` payment records plus `HISTORY_AMOUNT`;
  - `PLAY_STATE` as `UNPAID`, `PARTIALLY_PAID`, or `PAID`;
  - `PROJECT_STATE` as `PARTIALLY_SHIPPED`, `SHIPPED`, or `COMPLETED`;
  - `TOTAL_PRICE`, `TOTAL_REFUND_AMOUNT`, and `TOTAL_RETURN_AMOUNT` from reissue orders, return orders, and return/refund expenditure rows.
- Add one `sales_project_field_change_log` row for `INIT_PRICE`.
- Preserve Java behavior where payment over-collection is checked against the pre-recalculation `TOTAL_PRICE`.

## Exclusions

- No sale-project add/edit/delete, deal edit, repeal, history add, or special add behavior. Cancel is covered separately by the sale-project cancel slice.
- No workflow process starts, approvals, notifications, attachment/file operations, inventory/delivery mutation, invoice mutation, customer deal count updates, Java data-change events, schema changes, frontend source changes, `.env` changes, production data operations, or commits.

## Verification Plan

- `php -l app\controller\biz\SaleProjectController.php`
- `php -l app\service\biz\SaleProjectService.php`
- `php -l route\app.php`
- PowerShell syntax check for `scripts\sale-project-amount-edit-http-smoke.ps1`
- `php think route:list | Select-String -Pattern 'biz/saleproject/(amount/edit|visibility/edit|add|edit|delete|deal/edit|cancel|history/add|repeal|special/add)'`
- `Select-String -Path scripts\frontend-deferred-write-wrapper-smoke.ps1 -Pattern '/biz/saleproject/amount/edit'`
- `.\scripts\sale-project-amount-edit-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
- `git diff --check`

DB-backed HTTP execution is pending while local MySQL `MySQL80` is stopped.
