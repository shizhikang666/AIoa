# Sales Support Table Map

Agent: db-agent

Source SQL:

- `F:\AI\projects\testJava\OA\oa2026.sql`

Java source scope:

- `snowy-plugin-biz` sales, product, follow-up, return order, and warehouse entity/mapper packages.

## Scope

This phase extends the Phase 2 sales project foundation with passive table coverage for product master data and sales project support documents. It does not implement services, controllers, routes, workflow behavior, or API logic.

## Table Groups

### Product Foundation

| Table | Java entity | Purpose |
| --- | --- | --- |
| `biz_product` | `BizProduct` | Product master data used by sale items, warehouse records, and package relations. |
| `product_relation` | `ProductRelation` | Generic product relation table. Java declares `PRODUCT_RELATION`, but the SQL dump contains `product_relation`; ThinkPHP Models use the SQL physical table name. |
| `biz_sale_project_product_info` | `BizSaleProjectProductInfo` | Product package/version/content information. |
| `sale_project_product_item_relation` | `SaleProjectProductItemRelation` | Relation between a sale project product item and component products. |

### Delivery And Invoicing

| Table | Java entity | Purpose |
| --- | --- | --- |
| `biz_sale_project_invoice` | `BizSaleProjectInvoice` | Delivery/logistics master document for a sales project. |
| `biz_sale_project_invoice_item` | `BizSaleProjectInvoiceItem` | Delivery line items, linked to project product items and warehouses. |
| `delivery_record` | `DeliveryRecord` | Warehouse delivery/in-out record by product, warehouse, and process. |
| `biz_sale_project_invoicing` | `BizSaleProjectInvoicing` | Tax invoice application and invoice information. |

### Sales Process Support

| Table | Java entity | Purpose |
| --- | --- | --- |
| `biz_sale_project_reissue_order` | `BizSaleProjectReissueOrder` | Reissue order for a sales project. |
| `sale_project_follow_up` | `SaleProjectFollowUp` | Follow-up records for sales projects. |
| `customer_follow_up` | `CustomerFollowUp` | Follow-up records for customers. |
| `sale_project_rate` | `SaleProjectRate` | Customer/project rating records. |
| `sales_project_field_change_log` | `SalesProjectFieldChangeLog` | Field-level change log for sales projects. |

### Returns

| Table | Java entity | Purpose |
| --- | --- | --- |
| `return_order` | `ReturnOrder` | Return order master document. |
| `return_order_item` | `ReturnOrderItem` | Return order line items. |

## Relation Notes

- `biz_product.ORG` points to `sys_org.ID`.
- `product_relation.OBJECT_ID` and `TARGET_ID` are generic product/object relation keys.
- `biz_sale_project_invoice.PROJECT_ID`, `biz_sale_project_invoicing.PROJECT_ID`, `biz_sale_project_reissue_order.PROJECT_ID`, `sale_project_follow_up.PROJECT_ID`, `sale_project_rate.PROJECT_ID`, and `return_order.PROJECT_ID` point to `biz_sale_project.ID`.
- `biz_sale_project_invoice.PROCESS_ID`, `biz_sale_project_invoicing.PROCESS_ID`, `biz_sale_project_reissue_order.PROCESS_ID`, `return_order.PROCESS_ID`, and `delivery_record.PROCESS_ID` hold workflow/process identifiers.
- `biz_sale_project_invoice_item.INVOICE_ID` points to `biz_sale_project_invoice.ID`.
- `biz_sale_project_invoice_item.PROJECT_PRODUCT_ITEM_ID`, `return_order_item.PROJECT_PRODUCT_ITEM_ID`, and `sale_project_product_item_relation.OBJECT_ID` point to `biz_sale_project_product_item.ID`.
- `biz_sale_project_invoice_item.WAREHOUSES_ID`, `delivery_record.WAREHOUSES_ID`, and `return_order.WAREHOUSES_ID` point to `warehouses.ID`.
- `delivery_record.PRODUCT_ID`, `biz_sale_project_product_info.PRODUCT_ID`, and `sale_project_product_item_relation.TARGET_ID` point to product identifiers.
- `customer_follow_up.CUSTOMER_ID` points to `customer.ID`.
- `return_order_item.RETURN_ORDER_ID` points to `return_order.ID`.
- `sales_project_field_change_log.OBJECT_ID` points to a sales project id.
- Java translation-only fields annotated with `@TableField(exist = false)` are intentionally documented only and are not generated as columns.

## Deferred Tables

The following related areas remain for later db-agent slices:

- Finance and settlement: `biz_collection_receipt`, `biz_debit_note`, `settlement_account`, `settlement_account_statement`.
- Team collaboration comments/users/categories around `biz_team_project`.
- Draft/history/payroll/user-vacation support tables.
- Dev/sys shared infrastructure tables not required by this sales support slice.
