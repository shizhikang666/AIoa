# Business Model Plan - Phase 2

## Decision

Generate passive ThinkPHP Models for the 15 high-priority business tables listed in `biz-table-map.md`.

The models should:

- extend `app\model\BaseModel`
- set only the physical table name with `protected $name`
- avoid relations, scopes, mutators, casts, and business methods for now
- use docblocks to record Java source entity, table name, key relation notes, and important fields
- preserve the original database field casing

## Why Passive Models Only

Later agents own behavior:

- workflow-agent owns approval/process behavior
- api-agent owns Controller mapping
- user-agent owns user/org logic
- auth-agent owns permission logic
- frontend-agent owns UI/API adaptation

db-agent should provide stable table names and field notes without deciding runtime behavior too early.

## Model Files

| File | Table | Notes |
| --- | --- | --- |
| `app/model/BizCcRecords.php` | `biz_cc_records` | Workflow CC records. |
| `app/model/BizFileRelation.php` | `biz_file_relation` | Business object/file relation. |
| `app/model/BizLeaveApplication.php` | `biz_leave_application` | Leave workflow form. |
| `app/model/BizPaymentRecord.php` | `biz_payment_record` | Income/payment collection record. |
| `app/model/BizExpenditureRecord.php` | `biz_expenditure_record` | Expense/payment-out record. |
| `app/model/BizPurchaseOrder.php` | `biz_purchase_order` | Purchase order header. |
| `app/model/BizPurchaseOrderItem.php` | `biz_purchase_order_item` | Purchase order item. |
| `app/model/BizSaleProject.php` | `biz_sale_project` | Sales project/order header. |
| `app/model/BizSaleProjectProductItem.php` | `biz_sale_project_product_item` | Sales project product item. |
| `app/model/BizTeamProject.php` | `biz_team_project` | Team project. |
| `app/model/BizTeamProjectTask.php` | `biz_team_project_task` | Team project task. |
| `app/model/Customer.php` | `customer` | Customer master data. |
| `app/model/Supplier.php` | `supplier` | Supplier master data. |
| `app/model/Warehouses.php` | `warehouses` | Warehouse master data. |
| `app/model/Inventory.php` | `inventory` | Product inventory. |

## Deferred Tables

The following related tables are intentionally deferred to later db-agent slices:

- `biz_collection_receipt`
- `biz_debit_note`
- `biz_draft`
- `biz_payroll`
- `biz_product`
- `biz_relation`
- `biz_sale_project_invoice`
- `biz_sale_project_invoice_item`
- `biz_sale_project_invoicing`
- `biz_sale_project_payment`
- `biz_sale_project_product_info`
- `biz_sale_project_reissue_order`
- `biz_team_project_*` secondary tables
- `customer_follow_up`
- `delivery_record`
- `product_relation`
- `return_order`
- `return_order_item`
- `sale_project_follow_up`
- `sale_project_product_item_relation`
- `sale_project_rate`
- `settlement_account`
- `settlement_account_statement`

## Testing

Run after implementation:

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

`php think test` is not expected unless the ThinkPHP console lists a test command.

