# Biz Payroll Generate Add Plan

Date: 2026-06-18

Agent: api-agent/test-agent

## Scope

Replace the protected `/biz/bizpayroll/generate/add` controlled-deferred wrapper with Java-compatible payroll generation.

Java reference:

- `BizPayrollController.add(BizPayrollGenerateParam)`
- `BizPayrollServiceImpl.generate(BizPayrollGenerateParam)`
- `BizPayrollGenerateParam`

## Behavior

- Accept copied frontend payload:
  - `user`: nonempty list of user ids
  - `salaryTime`: required month/date
  - `socialSecurity`: nonnegative amount, optional null treated as `0`
- Load active users by submitted ids, tenant, and current data-scope.
- For each user, create one `biz_payroll` row with:
  - salary month copied from `salaryTime`
  - user/org from `sys_user`
  - `BASIC_SALARY` from `sys_user.BASIC_SALARY`
  - Java default zero values for seniority/performance/work salary, allowances, commissions, taxes, and account split fields
  - submitted `SOCIAL_SECURITY`
- Calculate Java-compatible monthly aggregates:
  - `TRANSACTION_VOLUME`: sum `biz_sale_project.TOTAL_PRICE` for project states in `WAIT_DELIVER, SHIPPED, PARTIALLY_SHIPPED, COMPLETED`, user in submitted users, and project create time inside the salary month.
  - `RECEIVED_AMOUNT`: for paid sale projects referenced by `PROJECT_PLAY` payment records created in the salary month, add `TOTAL_PRICE - REBATE_AMOUNT` when the sale project was also created in the salary month.
  - `BEFORE_RECEIVED_AMOUNT`: same referenced projects when the sale project was created before/outside the salary month.
  - `VACATION`: sum leave amount for `biz_leave_application.category = leaveOfAbsence` rows overlapping the salary month; same-month leave uses stored `AMOUNT`, cross-month leave clips to the salary month, counts inclusive natural days, and subtracts half a day when the effective start or end time is 12:00.
  - `BASE_AMOUNT`: seniority + performance + work + basic + rent + meal - dormitory rent.
  - `VACATION_SUB_AMOUNT`: `BASIC_SALARY / 24`, rounded down to 2 decimals, multiplied by vacation days.
  - `PAYABLE_AMOUNT`: `BASE_AMOUNT + TOTAL_COMMISSION - VACATION_SUB_AMOUNT`.
  - `ACTUAL_AMOUNT`: `PAYABLE_AMOUNT - PERFORMANCE_SALARY - SOCIAL_SECURITY`.
- Return generated count and ids.

## Guardrails

- Requests stay behind `AuthMiddleware`.
- Validate the full user list before inserting rows.
- Reject duplicate user ids, missing users, out-of-scope users, invalid salary dates, and negative social-security values.
- Run generation in one transaction.
- Do not update `sys_user`, sale projects, payment records, leave applications, vacation balances, workflow/process rows, files, imports, exports, Java source, Composer/npm files, `.env`, or database schema.
- Keep `/biz/bizpayroll/add` and `/biz/bizpayroll/import` controlled-deferred.

## Verification

- `php -l app\controller\biz\BizPayrollController.php`
- `php -l app\service\biz\BizPayrollService.php`
- `php -l route\app.php`
- PowerShell parser check for `scripts\biz-payroll-generate-add-http-smoke.ps1`
- `php think route:list | Select-String -Pattern 'biz/bizpayroll/(generate/add|add|import|edit|bath/edit|delete|page|detail)'`
- `Select-String -Path scripts\frontend-deferred-write-wrapper-smoke.ps1 -Pattern '/biz/bizpayroll/generate/add'` should return no rows.
- `.\scripts\biz-payroll-generate-add-http-smoke.ps1`
- `.\scripts\biz-payroll-export-http-smoke.ps1`
- `.\scripts\hr-read-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
