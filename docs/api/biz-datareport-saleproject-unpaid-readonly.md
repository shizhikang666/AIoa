# Biz Datareport Sale Project Unpaid Payment Read-Only API Compatibility

Date: 2026-06-03

Agent: api-agent

## Scope

This slice adds read-only ThinkPHP compatibility for the Java sale-project unpaid-payment report endpoint used by the copied data-report dashboard.

Java reference inputs:

- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizdatareport\controller\BizDataReportController.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizdatareport\service\impl\BizDataReportServiceImp.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizdatareport\enmus\BizDataReportEnum.java`

## Route

| Method | Path | ThinkPHP Handler | Java Equivalent |
| --- | --- | --- | --- |
| POST | `/biz/bizdatareport/saleproject/UnpaidPayment` | `biz.BizDataReportController/saleProjectUnpaidPayment` | `querySaleProjectUnpaidPaymentAmount` |

The route is protected by `AuthMiddleware`.

## Request Filters

Supported request body fields:

- `startCreateTime`
- `endCreateTime`
- `orgId`
- `headName`
- `tenantId`

The date fields map to Java's completion-date range filter for this endpoint.

## Java Compatibility Notes

- Uses成交 project states: `WAIT_DELIVER`, `SHIPPED`, `PARTIALLY_SHIPPED`, `COMPLETED`.
- Uses unpaid play states: `UNPAID`, `PARTIALLY_PAID`.
- Keeps Java's calculation: `totalPrice - amountCollected + totalReturnAmount`.
- Data scope uses `data_scope_org_ids` from the token payload when present and falls back to current user ownership otherwise.
- `orgId` expands to child organizations.

## Response Shape

```json
{
  "amount": 0
}
```

## Explicit Exclusions

The following endpoints remain deferred:

- `/biz/bizdatareport/saleProfit`
- `/biz/bizdatareport/settlement/income`
- `/biz/bizdatareport/settlement/expenses`
- `/biz/bizdatareport/summary/statistics`

No Java source, database schema, frontend files, Composer files, `.env`, write behavior, finance mutation, workflow mutation, or production data sync behavior was changed.

## Verification

Run:

```powershell
php -l app/controller/biz/BizDataReportController.php
php -l app/service/biz/BizDataReportService.php
php -l route/app.php
composer dump-autoload
php think
php think route:list
```

Smoke the service against representative sale-project rows and token payload data scope.
