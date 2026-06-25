# Sale Project Visibility Edit Plan

Date: 2026-06-18

## Scope

- Replace `/biz/saleproject/visibility/edit` controlled-deferred behavior with narrow Java-compatible sale-project visibility maintenance.
- Accept copied frontend and Java-style payloads:
  - `projectId`
  - `visibilityState`
  - `specimenCategory`
  - `specimenName`
- Validate `visibilityState` as `PUBLIC` or `PRIVATE`.
- Require `specimenCategory` when switching to `PUBLIC`.
- Allow copied frontend private toggles to omit specimen fields and preserve the existing values.
- Validate the active sale project through the existing tenant/data-scope-aware project query.
- Update only:
  - `VISIBILITY`
  - `SPECIMEN_CATEGORY` when supplied or when switching to `PUBLIC`
  - `SPECIMEN_NAME` when supplied or when switching to `PUBLIC`
  - `UPDATE_TIME`
  - `UPDATE_USER`
  - `VERSION`

## Non-Goals

- No sale-project add/edit/delete implementation.
- No amount edit, deal edit, repeal, history add, or special add behavior. Cancel is covered separately by the sale-project cancel slice.
- No project-state, play-state, finance, invoice, inventory, delivery, workflow, attachment, notification, change-log, customer, or data-change side effects.
- No frontend source changes, Java source changes, schema changes, Composer changes, `.env` changes, production data operations, or commits.

## Java Reference

- Java controller: `BizSaleProjectController#editProjectVisibility`
- Java service: `BizSaleProjectServiceImpl#editProjectVisibility`
- Java parameter: `BizProjectEditVisibilityParam`
- Java enum: `BizSaleProjectVisibilityEnum`

Java marks `specimenCategory` as required. The copied frontend also has private toggle callers that submit only `projectId` and `visibilityState`, so this ThinkPHP slice preserves existing specimen fields when switching to `PRIVATE` without specimen input.

## Verification Plan

- `php -l app\controller\biz\SaleProjectController.php`
- `php -l app\service\biz\SaleProjectService.php`
- `php -l route\app.php`
- PowerShell syntax check for `scripts\sale-project-visibility-edit-http-smoke.ps1`
- `php think route:list | Select-String -Pattern 'biz/saleproject/(visibility/edit|add|edit|delete|amount/edit|deal/edit|cancel|history/add|repeal|special/add)'`
- `Select-String -Path scripts\frontend-deferred-write-wrapper-smoke.ps1 -Pattern '/biz/saleproject/visibility/edit'` should return no rows.
- `.\scripts\sale-project-visibility-edit-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
