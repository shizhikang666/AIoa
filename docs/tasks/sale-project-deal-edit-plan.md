# Sale Project Deal Edit Plan

Date: 2026-06-18

## Goal

Replace `/biz/saleproject/deal/edit` controlled-deferred behavior with Java-compatible sale-project delivery/freight field maintenance.

## Java Reference

- `BizSaleProjectController.editDealSale`
- `BizSaleProjectServiceImpl.editDealBizSaleProject`
- `BizDealProjectEditParam`
- `BizSaleProjectServiceImpl.checkParams(String id)`

## Scope

- Accept copied frontend/Java JSON body with `id`.
- Validate active sale-project access through the existing tenant/data-scope-aware project query.
- Update only the fields exposed by `BizDealProjectEditParam`: `UNIT`, `ADDRESS`, `LOGISTICS_CATEGORY`, `CONSIGNEE`, `PHONE`, `REMARK`, `FREIGHT`, `FREIGHT_CATEGORY`, and `DELIVERY_NOTE`.
- Refresh audit fields and increment `VERSION`.
- Preserve protected project state, payment state, amounts, logical-delete state, invoicing rows, product rows, finance rows, and workflow state.

## Exclusions

- No sale-project add/edit, history add, special add, workflow process starts/cancels, task actions, payment/settlement correction, inventory/delivery mutation, invoice mutation, product-item mutation, file cleanup, Java data-change events, frontend source changes, Java source changes, schema changes, Composer changes, `.env` changes, production data operations, or commits.

## Verification Plan

- `php -l app\controller\biz\SaleProjectController.php`
- `php -l app\service\biz\SaleProjectService.php`
- `php -l route\app.php`
- PowerShell syntax check for `scripts\sale-project-deal-edit-http-smoke.ps1`
- `php think route:list | Select-String -Pattern 'biz/saleproject/(deal/edit|delete|repeal|cancel|amount/edit|visibility/edit|add|edit|history/add|special/add)'`
- `Select-String -Path scripts\frontend-deferred-write-wrapper-smoke.ps1 -Pattern '/biz/saleproject/deal/edit|/biz/saleproject/add|/biz/saleproject/edit'`
- `.\scripts\sale-project-deal-edit-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
- `git diff --check`

DB-backed HTTP execution is pending while local MySQL `MySQL80` is stopped.
