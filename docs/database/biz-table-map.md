# Business Table Map - Phase 2

## Scope

- Agent: db-agent
- SQL source: `F:\AI\projects\testJava\OA\oa2026.sql`
- Java source: `F:\AI\projects\testJava\OA` (read-only)
- ThinkPHP target: `F:\AI\projects\testJava\OA-db`

This phase covers only the high-dependency business tables that workflow-agent and api-agent will likely need first. It does not modify table structure, add migrations, or implement business logic.

## Tables Covered

| Table | Java entity | ThinkPHP model | Purpose |
| --- | --- | --- | --- |
| `biz_cc_records` | `BizCcRecords` | `BizCcRecords` | Workflow copy/CC records. |
| `biz_file_relation` | `BizFileRelation` | `BizFileRelation` | Business object to file relation. |
| `biz_leave_application` | `BizLeaveApplication` | `BizLeaveApplication` | Leave application workflow form. |
| `biz_payment_record` | `BizPaymentRecord` | `BizPaymentRecord` | Income/payment collection record. |
| `biz_expenditure_record` | `BizExpenditureRecord` | `BizExpenditureRecord` | Expense/payment-out record. |
| `biz_purchase_order` | `BizPurchaseOrder` | `BizPurchaseOrder` | Purchase order header. |
| `biz_purchase_order_item` | `BizPurchaseOrderItem` | `BizPurchaseOrderItem` | Purchase order line item. |
| `biz_sale_project` | `BizSaleProject` | `BizSaleProject` | Sales project/order header. |
| `biz_sale_project_product_item` | `BizSaleProjectProductItem` | `BizSaleProjectProductItem` | Sales project product item. |
| `biz_team_project` | `BizTeamProject` | `BizTeamProject` | Team project/work board. |
| `biz_team_project_task` | `BizTeamProjectTask` | `BizTeamProjectTask` | Team project task. |
| `customer` | `Customer` | `Customer` | Customer master data. |
| `supplier` | `Supplier` | `Supplier` | Supplier master data. |
| `warehouses` | `Warehouses` | `Warehouses` | Warehouse master data. |
| `inventory` | `Inventory` | `Inventory` | Warehouse product inventory. |

## Field Notes

The updated SQL file contains a few mixed-case or lower-case fields that must be preserved:

- `biz_leave_application.category`
- `biz_sale_project.special_type`
- `customer.remark`
- `supplier.org`

Do not rename these fields during migration. If a later service layer needs camelCase response names, map them outside the database model.

## Key Field Groups

### Workflow Form Tables

`biz_cc_records`:

- Identity: `ID`
- Workflow: `PROCESS_ID`, `INSTANCE_ID`, `CATEGORY`
- Users: `PROMOTER_ID`, `USER`
- Data: `TITLE`, `EXT_JSON`
- Common: `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`, `TENANT_ID`

`biz_leave_application`:

- Identity: `ID`
- Workflow: `PROCESS_ID`, `OBJECT_ID`
- User: `USER_ID`
- Form fields: `category`, `AMOUNT`, `REMARK`, `START_TIME`, `END_TIME`
- Common: `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`, `TENANT_ID`

### Finance Records

`biz_payment_record` and `biz_expenditure_record` share the same core shape:

- Identity: `ID`
- Business object: `OBJECT_ID`
- Account/settlement target: `TARGET_ID`, `SERIAL_ID`, `SETTLEMENT_CATEGORY`
- Workflow: `PROCESS_ID`
- Counterparty fields: `PAYER`, `BANK_NAME`, `BANK_ACCOUNT`
- Money/time: `AMOUNT`, `PAYER_TIME`
- Ownership: `USER`, `ORG`, `TENANT_ID`
- Common: `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`

### Purchase Tables

`biz_purchase_order`:

- Identity: `ID`
- Header: `TITLE`, `SETTLEMENT_STATUS`, `STORAGE_STATUS`, `SUPPLIER_ID`, `INSTANCE_ID`, `DESIRE_PURCHASE_DATE`, `AMOUNT`, `REMARK`, `EXT_JSON`
- Ownership/version: `TENANT_ID`, `ORG`, `VERSION`
- Common: `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`

`biz_purchase_order_item`:

- Identity: `ID`
- Header relation: `PURCHASE_ORDER_ID`
- Product relation: `PRODUCT_ID`
- Quantity/money: `NUMBER`, `AMOUNT`, `UNIT_AMOUNT`, `DISCOUNT_RATE`, `FREIGHT_SHARE_AMOUNT`, `UNIT_COST_WITH_FREIGHT`
- State: `STORAGE_STATUS`
- Ownership/version: `TENANT_ID`, `VERSION`
- Common: `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`

### Sale Project Tables

`biz_sale_project`:

- Identity: `ID`
- Customer relation: `CUSTOMER`
- Project state: `PROJECT_NAME`, `PROJECT_STATE`, `PLAY_STATE`, `VISIBILITY`, `PROJECT_CATEGORY`, `special_type`
- Money: `INIT_PRICE`, `TOTAL_PRICE`, `AMOUNT_COLLECTED`, `FREIGHT`, `REBATE_AMOUNT`, `DEAL_AMOUNT`, `HISTORY_AMOUNT`, `TOTAL_RETURN_AMOUNT`, `TOTAL_REFUND_AMOUNT`
- Contact/delivery: `CONSIGNEE`, `PHONE`, `UNIT`, `ADDRESS`, `AREA`, `DETAILS_ADDRESS`, `LOGISTICS_CATEGORY`, `DELIVERY_NOTE`
- Workflow/account: `PROCESS_ID`, `ACCOUNT_ID`, `PAYER_CATEGORY`, `FREIGHT_CATEGORY`
- Ownership/version: `USER`, `ORG`, `TENANT_ID`, `VERSION`
- Common: `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`

`biz_sale_project_product_item`:

- Identity: `ID`
- Project/product relation: `PROJECT_ID`, `PRODUCT_ID`
- Item state: `CATEGORY`, `STATE`, `MARK`, `PROJECT_REISSUE_ORDER_ID`
- Quantity/money: `NUMBER`, `DELIVERY`, `UNIT_PRICE`, `DISCOUNT_RATE`, `PRICE`
- Data: `REMARK`, `EXT_JSON`
- Ownership/version: `TENANT_ID`, `VERSION`
- Common: `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`

### Team Project Tables

`biz_team_project`:

- Identity: `ID`
- Project data: `NAME`, `DESCRIPTION`, `PROJECT_STATUS`, `COMPLETION_TIME`
- Ownership/version: `USER`, `ORG`, `TENANT_ID`, `VERSION`
- Common: `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`

`biz_team_project_task`:

- Identity: `ID`
- Parent/category: `TEAM_PROJECT_ID`, `TEAM_PROJECT_TASK_CATEGORY_ID`
- Task data: `STATUS`, `TITLE`, `PROGRESS`, `CONTENT_TEXT`, `SORT_CODE`, `EXT_JSON`
- Ownership/version: `TENANT_ID`, `VERSION`
- Common: `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`

### Master Data And Inventory

`customer`:

- Identity: `ID`
- Basic data: `NAME`, `CONTACTS`, `PHONE`, `DETAILS_ADDRESS`, `ADDRESS`, `SOURCE_TYPE`, `CUSTOM_TYPE`, `STATUS`, `FILE_ID`, `FIRST_CONTACT_TIME`
- Ownership/version: `ORG`, `USER`, `TENANT_ID`, `VERSION`
- Money/count: `DEAL_AMOUNT`
- Common/data: `SORT_CODE`, `EXT_JSON`, `remark`, `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`

`supplier`:

- Identity: `ID`
- Basic data: `NAME`, `CONTACTS`, `PHONE`, `BANK_NAME`, `BANK_ACCOUNT`, `STATUS`, `ENTERPRISE_NATURE`, `TAX_REGISTRATION_NUMBER`, `PAYMENT_METHOD`, `ALIAS_NAME`
- Ownership: `org`, `TENANT_ID`
- Common/data: `SORT_CODE`, `EXT_JSON`, `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`

`warehouses`:

- Identity: `ID`
- Basic data: `NAME`, `CODE`, `ADDRESS`
- Ownership: `USER`, `ORG`, `TENANT_ID`
- Common/data: `SORT_CODE`, `EXT_JSON`, `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`

`inventory`:

- Identity: `ID`
- Warehouse/product relation: `WAREHOUSES_ID`, `PRODUCT_ID`
- Quantity/version: `CURRENT_COUNT`, `VERSION`
- Common: `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`, `TENANT_ID`

## Non-Persistent Java Fields

Several Java entities include `@TableField(exist = false)` fields for display or assembled data. These are not database columns and should not be added to the schema.

Examples:

- `BizLeaveApplication.name`
- `BizFileRelation.downloadPath`, `thumbnail`, `sizeKb`, `suffix`, `name`, `createUserName`, `avatar`
- `BizPaymentRecord.accountName`
- `BizExpenditureRecord.accountName`
- `BizPurchaseOrderItem.productName`
- `Customer.orgName`, `headName`, `downloadPath`
- `Warehouses.headName`, `orgName`
- `BizSaleProject.customerSourceType`, `customType`, `headName`, `headPhone`, `accountName`, `returnOrders`, `productList`

## Sensitive Field Notes

Java entities use `CommonSm4CbcTypeHandler` on some business fields. At least these were observed:

- `Customer.PHONE`
- `Customer.DETAILS_ADDRESS`

Later service/API agents must preserve compatibility with already stored encrypted values.

