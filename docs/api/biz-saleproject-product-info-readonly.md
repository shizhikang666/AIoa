# Biz Sale Project Product Info Compatibility

Date: 2026-06-03

Agent: api-agent

## Scope

This document maps the Java `BizSaleProjectProductInfoController` APIs used by the copied Vue page `biz/saleprojectproductinfo`.

Java source remains read-only at `F:\AI\projects\testJava\OA`.

## Added Routes

| Method | Path | Controller | Notes |
| --- | --- | --- | --- |
| GET | `/biz/saleprojectproductinfo/page` | `SaleProjectProductInfoController::page` | Paged read. |
| GET | `/biz/saleprojectproductinfo/list` | `SaleProjectProductInfoController::list` | Supports `targetIds` as comma-separated string or array. |
| GET | `/biz/saleprojectproductinfo/detail` | `SaleProjectProductInfoController::detail` | Reads one row by `id`. |
| POST | `/biz/saleprojectproductinfo/add` | `SaleProjectProductInfoController::add` | Creates one package/version row. |
| POST | `/biz/saleprojectproductinfo/edit` | `SaleProjectProductInfoController::edit` | Updates submitted mutable fields. |
| POST | `/biz/saleprojectproductinfo/delete` | `SaleProjectProductInfoController::delete` | Logically deletes rows. |

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

## Write Compatibility

### Add

`POST /biz/saleprojectproductinfo/add`

Required fields:

- `productId`
- `targetId`
- `contentText`

Optional fields:

- `remark`
- `alias`
- `versionType`
- `versionRemark`
- `abbreviation`
- `hardware`
- `oldCode`
- `extJson`
- `tenantId`

The endpoint writes create audit columns, `TENANT_ID`, and `DELETE_FLAG = NOT_DELETE`.

### Edit

`POST /biz/saleprojectproductinfo/edit`

Required fields:

- `id`

Mutable fields are updated only when submitted. This preserves the Java edit parameter behavior, where package/version fields are optional.

### Delete

`POST /biz/saleprojectproductinfo/delete`

Accepted input shapes:

- `[{"id": "..."}]`
- `{"idList": ["..."]}`
- `{"ids": ["..."]}`
- `{"id": "..."}`

The endpoint performs a logical delete by setting `DELETE_FLAG = DELETED`. It does not physically remove imported rows.

## Deferred

- Product master-data writes remain out of scope.
- Sale-project order/product-item, inventory, delivery, finance, workflow, import/export, and report-generation side effects remain out of scope.

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

## 2026-06-15 Business Read HTTP Smoke

`scripts/business-read-http-smoke.ps1` now verifies sale-project product/package info reads alongside the core sale-project smoke.

Covered read checks:

- `GET /biz/saleprojectproductinfo/page`
- `GET /biz/saleprojectproductinfo/detail` when local product-info sample data exists
- `GET /biz/saleprojectproductinfo/list?targetIds=...`

The smoke verifies Java-style pagination keys and frontend-visible fields including `productId`, `targetId`, `contentText`, `alias`, `versionType`, `abbreviation`, `extJson`, `createUserName`, `productName`, and `targetProductName`.

This smoke is read-only. It does not call product-info add, edit, or delete; product master-data writes; sale-project product-item writes; import/export; reports; workflow; finance; inventory; delivery; provider; or file-cleanup behavior.
