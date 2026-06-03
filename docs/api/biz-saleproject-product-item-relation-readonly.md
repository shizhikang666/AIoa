# Biz Sale Project Product Item Relation Read-Only API Compatibility

Date: 2026-06-03

Agent: api-agent

## Java Reference

- Controller: `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\saleprojectproductitemrelation\controller\SaleProjectProductItemRelationController.java`
- Service: `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\saleprojectproductitemrelation\service\impl\SaleProjectProductItemRelationServiceImpl.java`
- Entity: `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\saleprojectproductitemrelation\entity\SaleProjectProductItemRelation.java`
- SQL reference: `F:\AI\projects\testJava\OA\oa2026.sql`

## Implemented ThinkPHP Route

The route is protected by `AuthMiddleware`.

| Method | Route | ThinkPHP Handler | Behavior |
| --- | --- | --- | --- |
| POST | `/biz/saleprojectproductitemrelation/list` | `biz.SaleProjectProductItemRelationController/list` | Reads combo-product child relation rows by sale-project product item ids |

## Request Compatibility

The route accepts Java-style body rows:

```json
[
  { "id": "sale-project-product-item-id" }
]
```

It also accepts compatibility alternatives:

- `{ "id": "..." }`
- `{ "ids": ["..."] }`
- `{ "idList": ["..."] }`

## Response Compatibility

Rows include Java/frontend camelCase fields:

- `id`
- `objectId`
- `targetId`
- `productId`
- `mark`
- `number`
- `deleteFlag`
- `createTime`
- `createUser`
- `updateTime`
- `updateUser`
- `extJson`
- `tenantId`
- `remark`
- `projectId`
- `projectName`
- `projectUser`
- `projectOrg`
- `productName`
- `productCategory`
- `productSysCategory`
- `specs`
- `purchasePrice`
- `salePrice`
- `minPrice`

`productId` is returned as an alias of `targetId`, matching the Java entity `@Alias("productId")` convention.

If `EXT_JSON` is empty, the service returns a minimal compatible `{"product": ...}` JSON string from joined product fields, matching the existing sale-project detail child-row behavior.

## Data Scope

- The query joins relation rows to `biz_sale_project_product_item` and `biz_sale_project`.
- It limits rows to visible sale projects through auth payload data-scope organization ids or current responsible user.
- Super-admin style local accounts/roles may see all rows, matching the existing follow-up compatibility services.

## Deferred Routes

The following Java/frontend routes remain intentionally unimplemented in this slice:

| Route | Reason |
| --- | --- |
| `POST /biz/saleprojectproductitemrelation/mark/edit` | Mutates relation `MARK` |
| `POST /biz/saleprojectproductitem/mark/edit` | Mutates sale-project product item `MARK` |

## Test Commands

```powershell
php -l app\controller\biz\SaleProjectProductItemRelationController.php
php -l app\service\biz\SaleProjectProductItemRelationService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

## Local Smoke Result

Date: 2026-06-03

- `php think route:list` lists `/biz/saleprojectproductitemrelation/list`.
- Direct service smoke with sample object id `2007746037931307010` returned 10 relation rows.
- The first sampled row included `productId` and non-empty `extJson`.
- Authenticated HTTP smoke for `/biz/saleprojectproductitemrelation/list` returned `code = 200`, 10 relation rows, `productId`, and non-empty `extJson`.
- Unauthenticated HTTP smoke for `/biz/saleprojectproductitemrelation/list` returned `code = 401`.
