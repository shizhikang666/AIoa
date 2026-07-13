# Sale Project Repeal Plan

Date: 2026-06-18

## Goal

Replace `/biz/saleproject/repeal` controlled-deferred behavior with Java-compatible sale-project discard state maintenance.

## Java Reference

- `BizSaleProjectController.repeal`
- `BizSaleProjectServiceImpl.repeal`
- `BizSaleProjectRepealParam`

## Scope

- Accept Java/copied-frontend array payloads such as `[{ id, repealContent }]`, plus compatible `ids`, `idList`, `projectIds`, or single `id` forms.
- Validate active sale-project access through the existing tenant/data-scope-aware project query.
- Require every selected project to exist and have `PROJECT_STATE = FOLLOW`.
- Update selected rows to `PROJECT_STATE = DISCARD`, set `REPEAL_CONTENT` from the first submitted row/top-level field, and refresh audit fields plus `VERSION`.

## Exclusions

- No sale-project add/edit/delete, deal edit, history add, special add, workflow process starts/cancels, task actions, payment/settlement correction, inventory/delivery mutation, invoice mutation, notification, file cleanup, Java data-change events, frontend source changes, Java source changes, schema changes, Composer changes, `.env` changes, production data operations, or commits.

## Verification Plan

- `php -l app\controller\biz\SaleProjectController.php`
- `php -l app\service\biz\SaleProjectService.php`
- `php -l route\app.php`
- PowerShell syntax check for `scripts\sale-project-repeal-http-smoke.ps1`
- `php think route:list | Select-String -Pattern 'biz/saleproject/(repeal|cancel|amount/edit|visibility/edit|add|edit|delete|deal/edit|history/add|special/add)'`
- `Select-String -Path scripts\frontend-deferred-write-wrapper-smoke.ps1 -Pattern '/biz/saleproject/repeal|/biz/saleproject/delete'`
- `.\scripts\sale-project-repeal-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
- `git diff --check`

DB-backed HTTP execution is pending while local MySQL `MySQL80` is stopped.
