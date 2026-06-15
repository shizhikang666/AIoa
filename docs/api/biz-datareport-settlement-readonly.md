# Biz Datareport Settlement Read-Only API

Date: 2026-06-03

Agent: api-agent

## Scope

This slice adds Java-compatible read-only settlement report endpoints for the copied Vue data-report settlement page.

The Java source project remains read-only:

- `F:\AI\projects\testJava\OA`

## Added Routes

All routes are protected by the existing bearer-token middleware.

| Method | Path | Controller |
| --- | --- | --- |
| POST | `/biz/bizdatareport/settlement/income` | `BizDataReportController::settlementIncome` |
| POST | `/biz/bizdatareport/settlement/expenses` | `BizDataReportController::settlementExpenses` |

## Java Mapping

| Java Method | ThinkPHP Method | Notes |
| --- | --- | --- |
| `BizDataReportController.querySettlementAccountIncome` | `settlementIncome` | Returns `BizPaymentRecord`-compatible rows |
| `BizDataReportController.queryExpensesData` | `settlementExpenses` | Returns `BizExpenditureRecord`-compatible rows |
| `BizDataReportServiceImp.queryIncomeRecord` | `BizDataReportService::settlementIncome` | Preserves category, org subtree, data-scope, user fallback, and payer-time filters |
| `BizDataReportServiceImp.queryExpenditureRecord` | `BizDataReportService::settlementExpenses` | Preserves org subtree, data-scope, user fallback, and payer-time filters |

## Request Parameters

| Parameter | Behavior |
| --- | --- |
| `orgId` | Expands to the selected organization and children, matching Java `bizOrgService.getChildListById(..., true)` |
| `category` | Income category filter against `SETTLEMENT_CATEGORY` |
| `settlementCategory` | Compatibility alias for category filtering |
| `startCreateTime` / `endCreateTime` | Java-compatible filter applied to `PAYER_TIME`, not `CREATE_TIME` |
| `startPayerTime` / `endPayerTime` | Extra compatibility alias applied to `PAYER_TIME` |

## Response Shape

The endpoints return lists of settlement record rows with frontend-compatible fields:

- `id`
- `objectId`
- `targetId`
- `accountName`
- `accountNumber`
- `serialId`
- `processId`
- `settlementCategory`
- `payer`
- `bankName`
- `bankAccount`
- `remark`
- `payerTime`
- `amount`
- `createTime`
- `createUser`
- `updateTime`
- `updateUser`
- `tenantId`
- `user`
- `org`
- `orgName`

## Data Scope

The implementation follows the Java behavior:

1. If `orgId` is provided, restrict to that organization and its child organizations.
2. If token data-scope organization ids exist, restrict to those ids.
3. Otherwise, restrict to the current login user.
4. If no current user can be resolved, return no rows.

## Explicit Exclusions

- No settlement account income, expense, payment, transfer, or balance mutation route was added.
- No `saleProfit` route was added.
- No `summary/statistics` route was added.
- No Java source, database schema, frontend, Composer, `.env`, or write-side business logic was changed.

## Verification

Required checks:

```powershell
php -l app\controller\biz\BizDataReportController.php
php -l app\service\biz\BizDataReportService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

## 2026-06-15 HTTP Smoke Coverage

`scripts/datareport-read-http-smoke.ps1` now covers authenticated settlement report reads for:

- `POST /biz/bizdatareport/settlement/income`
- `POST /biz/bizdatareport/settlement/expenses`

The smoke asserts list wrappers and optional settlement record fields. It intentionally does not call settlement account income, expenses, payment, transfer, balance mutation, mark-success, workflow, provider, or data-change behavior.
