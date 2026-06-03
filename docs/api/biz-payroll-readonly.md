# Biz Payroll Read-Only API Compatibility

Date: 2026-06-03

Agent: api-agent

## Scope

This slice adds read-only ThinkPHP compatibility for the Java payroll module.

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

## Explicit Exclusions

The following Java/frontend routes remain deferred:

- `/biz/bizpayroll/downloadImportTemplate`
- `/biz/bizpayroll/import`
- `/biz/bizpayroll/export`
- `/biz/bizpayroll/generate/add`
- `/biz/bizpayroll/add`
- `/biz/bizpayroll/edit`
- `/biz/bizpayroll/bath/edit`
- `/biz/bizpayroll/delete`

No Java source, database schema, frontend files, Composer files, `.env`, salary import/export logic, salary generation logic, edit logic, delete logic, or business side effects were changed.

## Verification

Run:

```powershell
php -l app/controller/biz/BizPayrollController.php
php -l app/service/biz/BizPayrollService.php
php -l route/app.php
composer dump-autoload
php think
php think route:list
```

Smoke the service with representative token payloads and imported `biz_payroll` data when available.
