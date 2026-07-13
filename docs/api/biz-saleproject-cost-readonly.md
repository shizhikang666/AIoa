# Biz Sale Project Cost Read-Only API Compatibility

Date: 2026-06-03

Agent: api-agent

## Java Reference

- Controller: `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\saleproject\controller\BizSaleProjectController.java`
- Sale project service: `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\saleproject\service\impl\BizSaleProjectServiceImpl.java`
- Purchase cost service: `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizpurchaseorder\service\impl\BizPurchaseOrderServiceImpl.java`
- SQL reference: `F:\AI\projects\testJava\OA\oa2026.sql`

## Implemented ThinkPHP Routes

All routes are protected by `AuthMiddleware`.

| Method | Route | ThinkPHP Handler | Behavior |
| --- | --- | --- | --- |
| POST | `/biz/saleproject/cost/details` | `biz.SaleProjectController/costDetails` | Returns sale-project cost detail data for frontend display |
| POST | `/biz/saleproject/cost` | `biz.SaleProjectController/cost` | Returns a read-only aggregate cost derived from the detail rows |

## Response Shape

`POST /biz/saleproject/cost/details` returns:

```json
{
  "items": [],
  "productItems": [],
  "returnOrders": []
}
```

Cost item fields:

| Field | Meaning |
| --- | --- |
| `productId` | Product id |
| `productName` | Product display name when available |
| `amount` | Net product quantity after sale-product product rows and return-order rows |
| `avgUnitAmount` | Average purchase order item `UNIT_AMOUNT` for completed purchase orders |
| `transMap` | Java `TransPojo` compatibility placeholder |

## Compatibility Notes

- The implementation first verifies sale-project access through the existing data-scope-aware project query.
- Route precedence matters in ThinkPHP: `cost/details` must be registered before `cost` so the detail route does not return the numeric aggregate response.
- Product quantities are expanded from `biz_sale_project_product_item`.
- Combo-product children are expanded from `sale_project_product_item_relation`.
- Return orders are read with `return_order_item` rows and attached as `productList`.
- Average purchase unit amount is calculated from `biz_purchase_order_item.UNIT_AMOUNT` joined to completed `biz_purchase_order` rows.
- The slice is read-only. It does not update sale projects, product items, return orders, purchase orders, inventory, settlement accounts, payment records, workflow state, or account balances.

## Deferred Routes

The following sale-project write routes now return controlled `code = 400` deferred responses:

| Route | Reason |
| --- | --- |
| `POST /biz/saleproject/add` | Real project creation remains deferred |
| `POST /biz/saleproject/edit` | Real project and product binding mutation remains deferred |
| `POST /biz/saleproject/history/add` | Real history order creation remains deferred |
| `POST /biz/saleproject/special/add` | Real special reimbursement project creation remains deferred |

`POST /biz/saleproject/visibility/edit` is covered separately as a narrow visibility/specimen field update. `POST /biz/saleproject/amount/edit` is covered separately as focused amount/status/totals maintenance with one `INIT_PRICE` change-log row. `POST /biz/saleproject/deal/edit` is covered separately as narrow delivery/freight field maintenance. `POST /biz/saleproject/cancel` is covered separately as WAIT_DELIVER-to-FOLLOW rollback with invoicing logical delete. `POST /biz/saleproject/repeal` is covered separately as FOLLOW-to-DISCARD discard maintenance. `POST /biz/saleproject/delete` is covered separately as FOLLOW-only logical delete maintenance. The remaining controlled-deferred wrappers do not create, update, history-create, special-create, or otherwise broadly change sale projects, do not mutate products, files, invoices, finance rows, workflow state, change logs, or notifications, and do not change schema or Java source.

## Test Commands

```powershell
php -l app\controller\biz\SaleProjectController.php
php -l app\service\biz\SaleProjectService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```
