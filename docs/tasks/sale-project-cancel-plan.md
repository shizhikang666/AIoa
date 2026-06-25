# Sale Project Cancel Plan

Date: 2026-06-18

## Goal

Replace `/biz/saleproject/cancel` controlled-deferred behavior with Java-compatible sale-project status rollback.

## Java Reference

- `BizSaleProjectController.cancel`
- `BizSaleProjectServiceImpl.cancel`
- `BizSaleProjectInvoicingServiceImpl.deleteByProjectId`

## Scope

- Accept Java/copied-frontend `id` or `projectId` payload fields.
- Validate the active sale project through the existing tenant/data-scope-aware project query.
- Require `PROJECT_STATE = WAIT_DELIVER`.
- Update `biz_sale_project.PROJECT_STATE` to `FOLLOW`, plus audit fields and `VERSION`.
- Logically delete active `biz_sale_project_invoicing` rows for the same project and tenant, matching Java's MyBatis-plus logical delete behavior.

## Exclusions

- No sale-project add/edit/delete, deal edit, repeal, history add, special add, workflow process starts/cancels, task actions, payment/settlement correction, inventory/delivery mutation, notification, file cleanup, Java data-change events, frontend source changes, Java source changes, schema changes, Composer changes, `.env` changes, production data operations, or commits.

## Verification Plan

- `php -l app\controller\biz\SaleProjectController.php`
- `php -l app\service\biz\SaleProjectService.php`
- `php -l route\app.php`
- PowerShell syntax check for `scripts\sale-project-cancel-http-smoke.ps1`
- `php think route:list | Select-String -Pattern 'biz/saleproject/(cancel|amount/edit|visibility/edit|add|edit|delete|deal/edit|history/add|repeal|special/add)'`
- `Select-String -Path scripts\frontend-deferred-write-wrapper-smoke.ps1 -Pattern '/biz/saleproject/cancel'`
- `.\scripts\sale-project-cancel-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
- `git diff --check`

DB-backed HTTP execution is pending while local MySQL `MySQL80` is stopped.
