# Biz Sale Project Product Item Relation API Compatibility

Date: 2026-06-06

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
| POST | `/biz/saleprojectproductitemrelation/mark/edit` | `biz.SaleProjectProductItemRelationController/editMark` | Updates relation `MARK` only |
| POST | `/biz/saleprojectproductitem/mark/edit` | `biz.SaleProjectProductItemController/editMark` | Updates sale-project product item `MARK` only |
| POST | `/biz/saleprojectproductitem/add` | `biz.SaleProjectProductItemController/add` | Adds one normal product item for a visible `FOLLOW` sale project |
| POST | `/biz/saleprojectproductitem/edit` | `biz.SaleProjectProductItemController/edit` | Edits one normal product item for a visible `FOLLOW` sale project |
| POST | `/biz/saleprojectproductitem/delete` | `biz.SaleProjectProductItemController/delete` | Logically deletes product items for visible `FOLLOW` sale projects |

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

## Mark Edit Compatibility

Both mark-edit endpoints accept JSON/form payloads:

```json
{
  "id": "row-id",
  "mark": "optional marker"
}
```

Behavior:

- Relation mark edit validates the active relation through its owning active product item and active sale project, then updates only `sale_project_product_item_relation.MARK` plus update audit fields.
- Product item mark edit validates the active product item through its owning active sale project, then updates only `biz_sale_project_product_item.MARK` plus update audit fields.
- Empty or missing `mark` is stored as an empty string, matching the Java nullable edit param behavior.
- Relation `MARK` is capped at 255 characters and product item `MARK` is capped at 50 characters to match the physical table columns.

## Standalone Product Item Maintenance

The standalone product-item routes accept JSON/form payloads compatible with Java-style item fields:

```json
{
  "projectId": "sale-project-id",
  "productId": "product-id",
  "number": 1,
  "unitPrice": "100.00",
  "discountRate": "0",
  "price": "100.00",
  "remark": "optional",
  "children": [
    { "productId": "child-product-id", "number": 1, "remark": "optional", "mark": "optional" }
  ]
}
```

Behavior:

- Add/edit/delete validate the owning sale project through tenant and data-scope rules.
- Mutations are limited to `FOLLOW` sale projects.
- Normal rows are stored as `CATEGORY = INIT`, `STATE = WAIT_DELIVER`, and `DELIVERY = 0` on add.
- Kit child relations are written in the same transaction and preserve `EXT_JSON.product` snapshots for frontend parsers.
- Edit supports partial product-item fields. When `children` is omitted and `productId` is unchanged, current child relations are preserved.
- Delete logically deletes product-item rows and their child relation rows.
- Active invoice-item or return-order-item references, and any delivered quantity, block delete and product/quantity/money/children edits with rollback.
- Delivery, invoice, return, inventory, finance, workflow, notification, and Java event-bus side effects are not executed by these standalone routes.

## Data Scope

- The query joins relation rows to `biz_sale_project_product_item` and `biz_sale_project`.
- It limits rows to visible sale projects through auth payload data-scope organization ids or current responsible user.
- Super-admin style local accounts/roles may see all rows, matching the existing follow-up compatibility services.

## Deferred Routes

The following Java/frontend routes remain intentionally unimplemented in this slice:

| Route | Reason |
| --- | --- |
| Non-`FOLLOW` sale-project product item mutation | Requires delivery/invoice/stock/project-state side-effect design |
| Delivery, invoice, return, inventory, finance, workflow actions | Transactional side effects require separate module plans |

## Test Commands

```powershell
php -l app\controller\biz\SaleProjectProductItemRelationController.php
php -l app\controller\biz\SaleProjectProductItemController.php
php -l app\service\biz\SaleProjectProductItemRelationService.php
php -l app\service\biz\SaleProjectProductItemService.php
php -l app\service\biz\SaleProjectService.php
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

Date: 2026-06-06

- Direct service smoke updated and restored one sampled product item `MARK`.
- Direct service smoke updated and restored one sampled product item relation `MARK`.
- `php think route:list` lists both mark-edit routes.
- Unauthenticated HTTP smoke for `/biz/saleprojectproductitem/mark/edit` returned `code = 401`.
- Unauthenticated HTTP smoke for `/biz/saleprojectproductitemrelation/mark/edit` returned `code = 401`.

Date: 2026-06-15

- `scripts/business-read-http-smoke.ps1` now verifies `/biz/saleprojectproductitemrelation/list` with an existing active product-item relation object id when local sample data exists.
- The smoke checks frontend-visible fields including `objectId`, `targetId`, `productId`, `mark`, `number`, `extJson`, `projectId`, `projectName`, `projectUser`, `projectOrg`, `productName`, `productCategory`, `productSysCategory`, and `specs`.
- The smoke remains read-only and does not call `/biz/saleprojectproductitemrelation/mark/edit`, `/biz/saleprojectproductitem/mark/edit`, sale-project product item add/edit/delete, delivery, invoice, inventory, finance, workflow, provider, or file-cleanup behavior.

Date: 2026-06-25

- `scripts/sale-project-product-item-standalone-http-smoke.ps1` passed against `http://127.0.0.1:82`.
- The smoke covers no-token, missing-product rollback, kit child relation add, edit preserving child relations when `children` is omitted, reference-protected edit/delete rollback, logical delete, non-`FOLLOW` project guard, and unchanged delivery/inventory/invoice/finance/workflow table counts.
