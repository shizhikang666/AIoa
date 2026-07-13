# Biz Datareport Sale Project Summary Read-Only API Compatibility

Date: 2026-06-03

Agent: api-agent

## Scope

This slice adds read-only ThinkPHP compatibility for the Java sale-project summary report endpoints used by the copied data-report dashboard.

Java reference inputs:

- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizdatareport\controller\BizDataReportController.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizdatareport\service\impl\BizDataReportServiceImp.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizdatareport\result\BizQueryBigDataAmountResult.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizdatareport\result\BizSaleProjectResult.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizdatareport\result\BizSaleProjectDataResult.java`

## Routes

| Method | Path | ThinkPHP Handler | Java Equivalent |
| --- | --- | --- | --- |
| POST | `/biz/bizdatareport/saleproject` | `biz.BizDataReportController/saleProjectAmount` | `querySaleProjectAmount` |
| POST | `/biz/bizdatareport/saleproject/list` | `biz.BizDataReportController/saleProjectList` | `querySaleProjectList` |
| POST | `/biz/bizdatareport/saleproject/report` | `biz.BizDataReportController/saleProjectReport` | `querySaleProjectReport` |

All routes are protected by `AuthMiddleware`.

## Request Filters

Supported request body fields:

- `startCreateTime`
- `endCreateTime`
- `orgId`
- `headName`
- `tenantId`

## Java Compatibility Notes

- `/saleproject` and `/saleproject/list` follow Java by filtering sale projects by `COMPLETION_DATE` and成交 project states.
- `/saleproject/report` follows Java by matching `CREATE_TIME` or `COMPLETION_DATE` within the requested range and returning only status/time fields.
- Data scope uses `data_scope_org_ids` from the token payload when present and falls back to current user ownership otherwise.
- `orgId` expands to child organizations, matching the Java org-tree behavior.

## Response Shape

`/saleproject` returns:

```json
{
  "amount": 0
}
```

`/saleproject/list` returns an array of project rows with amount fields such as:

- `totalPrice`
- `amountCollected`
- `totalReturnAmount`
- `totalRefundAmount`
- `rebateAmount`
- `completionDate`
- `createTime`

`/saleproject/report` returns:

```json
{
  "list": [
    {
      "playState": "UNPAID",
      "projectState": "FOLLOW",
      "createTime": "2026-01-01 00:00:00",
      "completionDate": null
    }
  ]
}
```

## Explicit Exclusions

The following endpoints remain deferred:

- `/biz/bizdatareport/saleProfit`
- `/biz/bizdatareport/saleproject/UnpaidPayment`
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

## 2026-06-15 HTTP Smoke Coverage

`scripts/datareport-read-http-smoke.ps1` now covers authenticated sale-project report reads for:

- `POST /biz/bizdatareport/saleproject`
- `POST /biz/bizdatareport/saleproject/list`
- `POST /biz/bizdatareport/saleproject/report`

The smoke asserts amount/list/report wrappers and optional project row fields. This slice also keeps the more specific `/saleproject/list` and `/saleproject/report` routes before `/saleproject` so they are not shadowed. It intentionally does not call sale-project writes, finance mutation, workflow, provider, or data-change behavior.
