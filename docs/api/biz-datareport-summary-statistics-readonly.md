# Biz Datareport Summary Statistics Read-Only API

Date: 2026-06-03

Agent: api-agent

## Scope

This slice adds the Java-compatible annual summary statistics endpoint used by the copied Vue summary statistics page.

The Java source project remains read-only:

- `F:\AI\projects\testJava\OA`

## Added Route

The route is protected by the existing bearer-token middleware.

| Method | Path | Controller |
| --- | --- | --- |
| POST | `/biz/bizdatareport/summary/statistics` | `BizDataReportController::summaryStatistics` |

## Java Mapping

| Java Method | ThinkPHP Method | Notes |
| --- | --- | --- |
| `BizDataReportController.querySummaryStatistics` | `summaryStatistics` | Returns one company summary object per accessible company |
| `BizDataReportServiceImp.querySummaryStatistics` | `BizDataReportService::summaryStatistics` | Assembles raw collections for frontend annual/monthly calculation |
| `bizOrgService.getChildListById` | company-scope org expansion | Uses token data-scope/current org to determine company groups |

## Request Parameters

| Parameter | Behavior |
| --- | --- |
| `year` | Required. Parsed to the selected year and queried through that year's end timestamp |

## Response Shape

The endpoint returns an array. Each item includes:

```json
{
  "org": {},
  "settlementAccounts": [],
  "paymentRecords": [],
  "bizExpenditureRecords": [],
  "bizSaleProjects": [],
  "bizDebitNotes": []
}
```

## Compatibility Notes

- The copied frontend calculates monthly totals in `summaryStatistics/components/webWork/calcStatisics.js`.
- Therefore this endpoint returns raw Java-compatible collections instead of precomputing finance summary numbers in PHP.
- `paymentRecords` and `bizExpenditureRecords` are bounded by settlement accounts in the company scope and `payerTime <= endOfYear`.
- `bizSaleProjects` are bounded by deal-state projects in the company scope and `completionDate <= endOfYear`.
- `bizDebitNotes` are bounded by company scope and `createTime <= endOfYear`.

## Explicit Exclusions

- No settlement account income, expenses, payment, transfer, correction, or balance mutation route was added.
- No workflow start/approve/reject/cancel route was added.
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

`scripts/datareport-read-http-smoke.ps1` now covers authenticated summary-statistics reads for:

- `POST /biz/bizdatareport/summary/statistics`

The smoke submits the current report year and asserts the raw company summary collections expected by the copied frontend worker. `BizDataReportController` now parses JSON bodies through the shared compatibility body-reader pattern so copied POST report filters are not lost. It intentionally does not call settlement account income, expenses, payment, transfer, workflow, provider, or data-change writes.
