# Biz Data Report Sale Project Details Read-Only Compatibility

Date: 2026-06-03

Agent: api-agent

## Scope

This slice maps only the Java reporting endpoint required by the copied Vue `saleprojectproductinfo` page:

- `POST /biz/bizdatareport/saleProjectList/details`

Java source remains read-only at `F:\AI\projects\testJava\OA`.

## Java Behavior

Java `BizDataReportServiceImp::getSaleProjectList` delegates to `querySaleProjects`:

- filters sale projects by `orgId` plus child organizations when provided
- applies login-user data scope; otherwise falls back to current responsible user
- filters `COMPLETION_DATE` by `startCreateTime` and `endCreateTime`
- includes project states `WAIT_DELIVER`, `SHIPPED`, `PARTIALLY_SHIPPED`, and `COMPLETED`
- attaches `productList` from `biz_sale_project_product_item`
- attaches `returnOrders` from `return_order`

## Added Route

| Method | Path | Controller | Notes |
| --- | --- | --- | --- |
| POST | `/biz/bizdatareport/saleProjectList/details` | `BizDataReportController::saleProjectListDetails` | Returns sale projects with `productList` and `returnOrders`. |

The route is protected by `AuthMiddleware`.

## Response Shape

Each project row includes the normal sale-project display fields plus:

- `productList`
- `returnOrders`

Each product item includes:

- `id`
- `projectId`
- `productId`
- `productName`
- `number`
- `unitPrice`
- `price`
- `children`

Child rows preserve `extJson` when present and synthesize a compatible product JSON object when it is missing.

## Deferred

The rest of the Java `bizdatareport` module remains deferred:

- `POST /biz/bizdatareport/saleproject`
- `POST /biz/bizdatareport/saleproject/list`
- `POST /biz/bizdatareport/saleproject/report`
- `POST /biz/bizdatareport/saleproject/UnpaidPayment`
- `POST /biz/bizdatareport/settlement/income`
- `POST /biz/bizdatareport/settlement/expenses`
- `POST /biz/bizdatareport/saleProfit`
- `POST /biz/bizdatareport/summary/statistics`

Those endpoints have separate finance/report semantics and need dedicated validation and smoke tests.

## Test Commands

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
