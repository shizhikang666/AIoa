# Biz Payroll API Compatibility

Date: 2026-06-12

Agent: api-agent / merge-agent

## Scope

This document tracks ThinkPHP compatibility for the Java payroll module. The original slice added read-only page/detail APIs. The 2026-06-12 slice adds the low-risk Java-compatible base writes for edit, batch edit, and logical delete.

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

## Explicit Exclusions

The following Java/frontend routes remain deferred:

- `/biz/bizpayroll/downloadImportTemplate`
- `/biz/bizpayroll/import`
- `/biz/bizpayroll/export`
- `/biz/bizpayroll/generate/add`
- `/biz/bizpayroll/add`

No Java source, database schema, frontend files, Composer files, `.env`, salary import/export logic, salary generation logic, payroll add logic, or workflow/business side effects were changed.

## Verification

Run:

```powershell
php -l app/controller/biz/BizPayrollController.php
php -l app/service/biz/BizPayrollService.php
php -l route/app.php
php think
php think route:list
```

Focused DB smoke on 2026-06-12 inserted temporary `biz_payroll` rows, then verified:

- `edit` updates editable salary fields and preserves non-edit fields.
- `bath/edit` updates two rows.
- `bath/edit` with a missing id fails before updating existing rows.
- non-admin out-of-scope edit returns `403`.
- `delete` sets `DELETE_FLAG = DELETED` and hides the row from `detail`.
- temporary smoke rows are physically cleaned up.
