# Biz Payroll API Compatibility

Date: 2026-06-18

Agent: api-agent / merge-agent

## Scope

This document tracks ThinkPHP compatibility for the Java payroll module. The original slice added read-only page/detail APIs. The 2026-06-12 slices add the low-risk Java-compatible base writes for edit, batch edit, logical delete, and import-template download. The 2026-06-16 slice opens payroll export as an authenticated CSV download without EasyExcel rendering. The 2026-06-18 slices open Java-compatible payroll generation and focused payroll import.

Java reference inputs:

- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizpayroll\controller\BizPayrollController.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizpayroll\service\impl\BizPayrollServiceImpl.java`
- `F:\AI\projects\testJava\OA\oa2026.sql`

## Routes

| Method | Path | ThinkPHP Handler | Java Equivalent |
| --- | --- | --- | --- |
| GET | `/biz/bizpayroll/page` | `biz.BizPayrollController/page` | `BizPayrollController.page` |
| GET | `/biz/bizpayroll/mypage` | `biz.BizPayrollController/myPage` | `BizPayrollController.mypage` |
| GET | `/biz/bizpayroll/detail` | `biz.BizPayrollController/detail` | `BizPayrollController.detail` |
| GET | `/biz/bizpayroll/downloadImportTemplate` | `biz.BizPayrollController/downloadImportTemplate` | `BizPayrollController.download` |
| GET | `/biz/bizpayroll/export` | `biz.BizPayrollController/export` | `BizPayrollController.export` |
| POST | `/biz/bizpayroll/add` | `biz.BizPayrollController/add` | Controlled deferred wrapper |
| POST | `/biz/bizpayroll/import` | `biz.BizPayrollController/importExcel` | `BizPayrollController.importUser` |
| POST | `/biz/bizpayroll/generate/add` | `biz.BizPayrollController/generateAdd` | `BizPayrollController.generateAdd` |
| POST | `/biz/bizpayroll/edit` | `biz.BizPayrollController/edit` | `BizPayrollController.edit` |
| POST | `/biz/bizpayroll/bath/edit` | `biz.BizPayrollController/bathEdit` | `BizPayrollController.bathEdit` |
| POST | `/biz/bizpayroll/delete` | `biz.BizPayrollController/delete` | `BizPayrollController.delete` |

All routes are protected by `AuthMiddleware`.

## Query Compatibility

Supported page filters:

- `current`, `page`, `pageNo`
- `size`, `limit`, `pageSize`
- `sortField`, `sortOrder`
- `startSalaryTime`, `endSalaryTime`
- `salaryTime`
- `orgId`
- `user` or `userId`
- `tenantId`
- `searchKey`

The frontend usually sends a month picker value and converts it into `startSalaryTime` and `endSalaryTime`. The service also accepts `salaryTime` and expands it to a month range for compatibility.

## Response Fields

Rows return Java/frontend-compatible camelCase fields including:

- identity and display fields: `id`, `user`, `userId`, `headName`, `name`, `userAccount`, `org`, `orgId`, `orgName`
- salary fields: `basicSalary`, `postWage`, `senioritySalary`, `performanceSalary`, `workSalary`, `rentSubsidies`, `mealAllowance`, `dormitoryRent`, `baseAmount`, `transactionVolume`, `receivedAmount`, `taxFreight`, `monthlyCommission`, `beforeReceivedAmount`, `beforeCommission`, `rateCommission`, `totalCommission`, `meritBonuses`, `vacation`, `vacationSubAmount`, `payableAmount`, `personalIncomeTax`, `socialSecurity`, `actualAmount`, `yearEndBonus`, `privateAccount`, `publicAccount`
- metadata: `salaryTime`, `remark`, `deleteFlag`, `createTime`, `createUser`, `createUserName`, `updateTime`, `updateUser`, `updateUserName`, `tenantId`

## Data Scope

- `/page` follows Java behavior by applying login data-scope organization ids when present.
- If no data-scope organization ids are available, `/page` falls back to the current login user.
- `/mypage` is always limited to the current login user, then applies date/org filters.
- `/detail` is protected and uses the same data-scope guard as `/page`.

## Write Compatibility

`POST /biz/bizpayroll/edit` accepts Java `BizPayrollEditParam` style payloads and updates only the Java edit fields:

- `senioritySalary`, `performanceSalary`, `workSalary`, `basicSalary`
- `rentSubsidies`, `mealAllowance`, `dormitoryRent`
- `baseAmount`, `transactionVolume`, `receivedAmount`, `taxFreight`
- `monthlyCommission`, `beforeReceivedAmount`, `beforeCommission`, `rateCommission`, `totalCommission`
- `meritBonuses`, `vacation`, `vacationSubAmount`
- `payableAmount`, `personalIncomeTax`, `socialSecurity`, `actualAmount`

`POST /biz/bizpayroll/bath/edit` accepts `{ list: [...] }`, validates every id before writing, rejects missing or duplicate ids, and updates the same editable field set.

`POST /biz/bizpayroll/delete` accepts Java-style delete payloads such as `[{ "id": "..." }]`, `{ "id": "..." }`, `{ "ids": [...] }`, or comma-separated ids. It sets `DELETE_FLAG = DELETED` and updates audit fields.

The write guards are tenant-scoped. Admin-compatible users may write all current-tenant payroll rows. Non-admin users may write only rows within their data-scope organization ids, rows assigned to themselves, or rows they created.

The write slice intentionally preserves fields that Java `BizPayrollEditParam` does not expose, including `POST_WAGE`, `YEAR_END_BONUS`, `PUBLIC_ACCOUNT`, `PRIVATE_ACCOUNT`, `REMARK`, `USER`, `ORG`, and `SALARY_TIME`.

`POST /biz/bizpayroll/generate/add` accepts Java-style `{ user: string[], salaryTime, socialSecurity }` payloads. It validates selected active users under tenant/data-scope rules, then writes one `biz_payroll` row per user in a transaction. The generated values follow the Java service:

- `BASIC_SALARY` comes from `sys_user.BASIC_SALARY`; salary allowances, commissions, tax, year-end, public/private account, and manual salary fields start at `0.00`.
- `TRANSACTION_VOLUME` sums current-month deal-state sale projects for selected users.
- `RECEIVED_AMOUNT` and `BEFORE_RECEIVED_AMOUNT` are derived from current-month `PROJECT_PLAY` payment records and paid sale projects, split by sale-project create month.
- `VACATION` sums leave-of-absence records overlapping the salary month, including rows created by approved `Process_ask_leave` workflows with `CATEGORY = leaveOfAbsence`. Cross-month rows use the Java overlap-day formula, including half-day adjustment for 12:00 start/end times.
- `BASE_AMOUNT`, `VACATION_SUB_AMOUNT`, `PAYABLE_AMOUNT`, and `ACTUAL_AMOUNT` use the Java formulas. The route intentionally preserves Java behavior by not rejecting an existing payroll row for the same user/month.

Workflow approval does not automatically rewrite already-created payroll rows. This matches the Java flow: `LeaveApproveDelegate` creates the leave application row, and payroll deduction is applied when `/biz/bizpayroll/generate/add` is explicitly called.

`POST /biz/bizpayroll/import` accepts multipart `file` and optional `orgId`/`org` fields. It parses the tracked Java payroll template layout with PHP built-in ZIP/XML support instead of adding Composer dependencies:

- row 1 column A must be a title like `2026年06月工资表`;
- the first three rows are treated as template headers;
- data rows map columns C through AC to employee name, payroll numeric fields, public/private account values, and remark;
- imported names are whitespace-normalized and matched against active `sys_user.NAME` rows in the requested organization subtree and current tenant;
- matched rows insert one `biz_payroll` row with the matched user id, user organization, imported salary month, imported numeric values, audit fields, and `DELETE_FLAG = NOT_DELETE`;
- missing users or invalid row values are returned in `errorDetail` while successful rows are committed, matching Java's partial-success import shape;
- Java data-change events are not emitted.

## Import Template Download

`GET /biz/bizpayroll/downloadImportTemplate` returns the original Java resource `userPayrollTemplate.xlsx` as an authenticated blob response. The tracked ThinkPHP copy is stored at:

`app/resources/biz/payroll/userPayrollTemplate.xlsx`

The template bytes match the Java source resource:

- size: `13427`
- SHA256: `4A98E66E74E8D310D6226A5F6DD60602652FC25FD6D0FB272281BBF19CD861B8`

The route is intentionally a download response, not a JSON `ApiResponse::ok` wrapper.

## Export Download

`GET /biz/bizpayroll/export` returns an authenticated CSV blob response for the copied payroll page's `responseType: 'blob'` export call.

The Java route uses EasyExcel to write multi-level `.xlsx` headers and merged organization groups. The current ThinkPHP project does not include a spreadsheet writer dependency, and this slice intentionally does not add one. Instead, the route emits UTF-8 BOM CSV with Excel-readable columns matching the Java export-visible fields:

- organization group and employee name;
- salary cost fields;
- commission fields;
- leave/year-end/payable/deduction/actual amount fields;
- public/private account fields and remark.

The export reuses the existing payroll query filters and data-scope behavior. When no sort field is supplied, it sorts by organization to match the Java export's organization grouping intent. Empty result sets return a JSON failure envelope with `code = 400` and message `无数据可导出`.

## Controlled Deferred Writes

The following frontend route now returns a controlled `code = 400` deferred response:

- `/biz/bizpayroll/add`

The add wrapper does not manually create payroll rows, start workflow, write provider output, change database schema, modify Java source, edit `.env`, or touch Composer files.

## Explicit Exclusions

Payroll add logic, EasyExcel-style xlsx export rendering, merged-cell styling, automatic rewrites of existing payroll rows on workflow approval, Java data-change events, and broader business side effects remain deferred.

## Verification

Run:

```powershell
php -l app/controller/biz/BizPayrollController.php
php -l app/service/biz/BizPayrollService.php
php -l route/app.php
php think
php think route:list
.\scripts\biz-payroll-import-http-smoke.ps1
.\scripts\biz-payroll-generate-add-http-smoke.ps1
```

Focused DB smoke on 2026-06-12 inserted temporary `biz_payroll` rows, then verified:

- `edit` updates editable salary fields and preserves non-edit fields.
- `bath/edit` updates two rows.
- `bath/edit` with a missing id fails before updating existing rows.
- non-admin out-of-scope edit returns `403`.
- `delete` sets `DELETE_FLAG = DELETED` and hides the row from `detail`.
- temporary smoke rows are physically cleaned up.

Focused template download smoke on 2026-06-12 verified:

- service returns filename `工资条导入模板.xlsx`, xlsx content type, 13427 bytes, SHA256 `4A98E66E74E8D310D6226A5F6DD60602652FC25FD6D0FB272281BBF19CD861B8`, and `PK` file header.
- authenticated HTTP GET returns `200`, xlsx content type, `.xlsx` content disposition, 13427 bytes, matching SHA256, and `PK` file header.
- `biz_payroll` row count remains unchanged by template download.

## 2026-06-15 HTTP Smoke Coverage

`scripts/hr-read-http-smoke.ps1` now covers authenticated payroll reads for:

- `GET /biz/bizpayroll/page`
- `GET /biz/bizpayroll/mypage`
- `GET /biz/bizpayroll/detail` when the visible page has a sample row

The smoke asserts Java-style paging keys and frontend-visible identity/display/salary fields. It intentionally does not call payroll edit, batch edit, delete, import, generate, add, export, or template download routes.

## 2026-06-16 Export HTTP Smoke Coverage

`scripts/biz-payroll-export-http-smoke.ps1` covers authenticated payroll export by inserting one temporary `biz_payroll` row, downloading `/biz/bizpayroll/export` with salary-month and `searchKey` filters, asserting CSV headers and row markers, verifying representative related table counts remain unchanged, checking no-token `code = 401`, and cleaning the temporary row.

## 2026-06-18 Generate HTTP Smoke Coverage

`scripts/biz-payroll-generate-add-http-smoke.ps1` covers authenticated payroll generation by inserting temporary users, sale projects, payment records, and leave applications; calling `/biz/bizpayroll/generate/add`; checking validation failures, no-token rejection, generated payroll formulas, related table count stability, and cleanup.

## 2026-06-22 Workflow Payroll Generation Coverage

`scripts/workflow-task-transition-http-smoke.ps1` now approves a temporary `Process_ask_leave` `leaveOfAbsence` workflow, calls `/biz/bizpayroll/generate/add` for the same user and salary month, and verifies the generated payroll row includes the approved leave amount in `VACATION`.

## 2026-06-18 Import HTTP Smoke Coverage

`scripts/biz-payroll-import-http-smoke.ps1` is ready to cover authenticated payroll import by inserting a temporary user, generating a minimal `.xlsx` payroll file, checking no-token, missing-file, bad-month, and partial-success import behavior, verifying imported payroll field values, and cleaning temporary rows/files. DB-backed execution is pending until local MySQL is available.
