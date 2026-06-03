# Biz Sale Project Product Info Read-Only Compatibility

Date: 2026-06-03

Agent: api-agent

## Scope

This slice maps the Java `BizSaleProjectProductInfoController` read APIs used by the copied Vue page `biz/saleprojectproductinfo`.

Java source remains read-only at `F:\AI\projects\testJava\OA`.

## Added Routes

| Method | Path | Controller | Notes |
| --- | --- | --- | --- |
| GET | `/biz/saleprojectproductinfo/page` | `SaleProjectProductInfoController::page` | Paged read. |
| GET | `/biz/saleprojectproductinfo/list` | `SaleProjectProductInfoController::list` | Supports `targetIds` as comma-separated string or array. |
| GET | `/biz/saleprojectproductinfo/detail` | `SaleProjectProductInfoController::detail` | Reads one row by `id`. |

All routes are protected by `AuthMiddleware`.

## Response Fields

Rows include these Java/frontend-compatible fields:

- `id`
- `productId`
- `targetId`
- `contentText`
- `remark`
- `alias`
- `versionType`
- `versionRemark`
- `abbreviation`
- `hardware`
- `oldCode`
- `deleteFlag`
- `extJson`
- `createTime`
- `createUser`
- `createUserName`
- `updateTime`
- `updateUser`
- `updateUserName`
- `tenantId`
- `productName`
- `targetProductName`

## Deferred

The following Java endpoints remain deferred:

- `POST /biz/saleprojectproductinfo/add`
- `POST /biz/saleprojectproductinfo/edit`
- `POST /biz/saleprojectproductinfo/delete`

They write business data and should wait for a dedicated validation, audit, transaction, and permission plan.

## Test Commands

```powershell
php -l app\controller\biz\SaleProjectProductInfoController.php
php -l app\service\biz\SaleProjectProductInfoService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```
