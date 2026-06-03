# Biz Datareport Sale Profit Read-Only API

Date: 2026-06-03

Agent: api-agent

## Scope

This slice adds the Java-compatible sale-profit report endpoint used by the copied Vue sale-profit dashboard page.

The Java source project remains read-only:

- `F:\AI\projects\testJava\OA`

## Added Route

The route is protected by the existing bearer-token middleware.

| Method | Path | Controller |
| --- | --- | --- |
| POST | `/biz/bizdatareport/saleProfit` | `BizDataReportController::saleProfit` |

## Java Mapping

| Java Method | ThinkPHP Method | Notes |
| --- | --- | --- |
| `BizDataReportController.querySaleProfitReport` | `saleProfit` | Returns Java-compatible `SaleProfitResult` data |
| `BizDataReportServiceImp.getSaleProfitResult` | `BizDataReportService::saleProfit` | Assembles sale projects, completed purchase orders, and product rows |
| `BizDataReportServiceImp.querySaleProjects` | existing sale-project report query plus nested product/return rows | Preserves organization subtree, data-scope, completion-time, and deal-state filters |
| `BizDataReportServiceImp.queryPurchaseOrders` | completed purchase order query | Preserves data-scope or current-user fallback and settlement completed filter |
| `BizDataReportServiceImp.queryProducts` | product lookup query | Returns product rows needed by the frontend worker |

## Request Parameters

| Parameter | Behavior |
| --- | --- |
| `orgId` | Applies to the sale-project list only, matching Java behavior |
| `startCreateTime` / `endCreateTime` | Applied to sale-project `COMPLETION_DATE`, matching Java behavior |

## Response Shape

The endpoint returns:

```json
{
  "projectlist": [],
  "orderList": [],
  "bizProducts": []
}
```

`projectlist` rows include `productList` and `returnOrders`.

`returnOrders` rows include nested `productList` with `projectProductItemId`.

`orderList` rows include nested `orderItems` with `productId`, `amount`, and `number`.

`bizProducts` rows include `id`, `productName`, `purchasePrice`, `salePrice`, `minPrice`, `category`, and related display fields.

## Compatibility Notes

- The copied frontend calculates `salesRevenue`, `cost`, `grossProfit`, `grossProfitLv`, and `productList` in `saleProfit/webWork/calcProfit.js`.
- Therefore this endpoint returns the raw Java-compatible collections instead of precomputing summary numbers in PHP.
- Empty `children` arrays are omitted from sale-profit product rows so the frontend does not treat single products as kit products.

## Explicit Exclusions

- No `summary/statistics` route was added.
- No purchase, sale, inventory, settlement, payment, return, or workflow mutation route was added.
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
