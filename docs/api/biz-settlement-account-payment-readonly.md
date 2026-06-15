# Settlement Account Payment Read-Only Compatibility

Date: 2026-06-03

Agent: api-agent

## Scope

This slice maps the Java `SettlementAccountStatementController` endpoints exposed under the old frontend route path:

- `GET /biz/settlementaccountpayment/page`
- `GET /biz/settlementaccountpayment/list`

Java source remains read-only at `F:\AI\projects\testJava\OA`.

## Java Behavior

Java `SettlementAccountStatementServiceImpl`:

- reads `settlement_account_statement`
- filters by `accountId`
- filters `PAYER_TIME` by `startPlayTime` and `endPlayTime`
- filters `CREATE_TIME` by `startCreateTime` and `endCreateTime`
- sorts by requested field and order, otherwise by `id` ascending

## Added Routes

| Method | Path | Controller | Notes |
| --- | --- | --- | --- |
| GET | `/biz/settlementaccountpayment/page` | `SettlementAccountPaymentController::page` | Paged statement rows. |
| GET | `/biz/settlementaccountpayment/list` | `SettlementAccountPaymentController::list` | Statement rows for account detail tab. |

Both routes are protected by `AuthMiddleware`.

## Response Shape

Rows include:

- `id`
- `accountId`
- `accountName`
- `accountNumber`
- `processId`
- `beforeAmount`
- `amount`
- `afterAmount`
- `settlementType`
- `settlementCategory`
- `processCategory`
- `payerTime`
- `createTime`
- `extJson`
- `tenantId`

The service also accepts frontend aliases `startPayerTime` and `endPayerTime` in addition to the Java names `startPlayTime` and `endPlayTime`.

## Deferred

The following remain intentionally deferred:

- settlement account payment creation
- settlement account expenses/income actions
- settlement account transfer actions
- account balance mutation
- workflow side effects
- frontend component changes
- database schema changes

## Test Commands

```powershell
php -l app\controller\biz\SettlementAccountPaymentController.php
php -l app\service\biz\SettlementAccountPaymentService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

## 2026-06-15 HTTP Smoke Coverage

`scripts/settlement-account-payment-read-http-smoke.ps1` now verifies authenticated settlement-account statement reads against the local backend:

- `GET /biz/settlementaccountpayment/page`
- `GET /biz/settlementaccountpayment/list`

The smoke checks Java-style paging keys and stable frontend-visible fields such as `accountId`, `accountName`, `accountNumber`, `beforeAmount`, `amount`, `afterAmount`, `settlementType`, `settlementCategory`, `processCategory`, `payerTime`, `orgName`, and decoded `ext`. It does not call settlement account payment creation, expenses/income actions, transfer actions, account balance mutation, workflow, provider, or data-change behavior.
