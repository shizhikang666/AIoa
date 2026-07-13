# Plan: api-agent/test-agent - Payroll Import

Status: implemented on 2026-06-18; DB-backed smoke verification is pending because local MySQL `MySQL80` is stopped.

## Scope

- Replace `/biz/bizpayroll/import` controlled-deferred behavior with a focused Java-template payroll import.
- Accept multipart `file` and optional `orgId`/`org` form fields.
- Parse the existing `userPayrollTemplate.xlsx` layout without adding Composer dependencies: row 1 column A is the salary month title, rows after the three header rows are data rows.
- Match row names against active `sys_user.NAME` values in the requested organization subtree and current tenant.
- Insert one `biz_payroll` row for each matched data row, using the matched user's `ID` and `ORG_ID`, parsed salary month, imported numeric fields, audit fields, `DELETE_FLAG = NOT_DELETE`, and current tenant.
- Return Java-style import counts: `totalCount`, `successCount`, `errorCount`, and `errorDetail`.
- Count missing users or invalid row data as row-level errors while committing successful rows, matching Java's per-row `doImport` behavior.
- Keep `/biz/bizpayroll/add`, EasyExcel rendering, workflow hooks, Java data-change events, frontend source changes, Java source changes, schema changes, Composer changes, `.env` changes, production data operations, and commits deferred.

## Verification Plan

- `php -l app\controller\biz\BizPayrollController.php`
- `php -l app\service\biz\BizPayrollService.php`
- `php -l route\app.php`
- PowerShell syntax check for `scripts\biz-payroll-import-http-smoke.ps1`
- `php think route:list | Select-String -Pattern 'biz/bizpayroll/(import|add|generate/add|edit|bath/edit|delete|page|detail|downloadImportTemplate|export)'`
- `Select-String -Path scripts\frontend-deferred-write-wrapper-smoke.ps1 -Pattern '/biz/bizpayroll/import|/biz/bizpayroll/add'`
- `.\scripts\biz-payroll-import-http-smoke.ps1` (pending local MySQL)
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
- `git diff --check`
