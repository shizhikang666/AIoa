# Java OA Database Table Map

## Scope

- Java source project: `F:\AI\projects\testJava\OA` (read-only)
- ThinkPHP db-agent worktree: `F:\AI\projects\testJava\OA-db`
- Main SQL snapshot: `snowy-web-app/src/main/resources/_sql/2026/oa2026.sql`
- Baseline SQL snapshot: `snowy-web-app/src/main/resources/_sql/v1000/oa.sql`
- Java entities inspected under:
  - `snowy-plugin/snowy-plugin-sys`
  - `snowy-plugin/snowy-plugin-auth`
  - `snowy-plugin/snowy-plugin-client`
  - `snowy-plugin/snowy-plugin-mobile`
  - `snowy-plugin/fudi-plugin-tenants`

This document keeps the Java OA table and field spelling unchanged. Later agents should map request/response field names separately and must not rename database fields during migration.

## SQL Snapshot Summary

The 2026 SQL snapshot contains:

- Workflow engine tables: `act_*`
- System/auth/user tables: `sys_*`, `auth_third_user`, `mobile_resource`
- Tenant/client tables: `tenants`, `client_user`, `client_relation`
- Development and generator tables: `dev_*`, `gen_*`
- OA business tables: `biz_*`, `customer`, `supplier`, `warehouses`, `inventory`, settlement and sale/project tables

The `v1000/oa.sql` snapshot is useful as a clean foundation for original system tables. The `2026/oa2026.sql` snapshot is the current compatibility source because it includes newer OA business and workflow data structures.

## Core Foundation Tables

| Table | Java entity/source | ThinkPHP model | Primary key | Notes |
| --- | --- | --- | --- | --- |
| `sys_user` | `SysUser` | `app\model\SysUser` | `ID` | System user table. Contains login account, encrypted password, profile, org/position links, tenant id, audit fields, and later HR/payroll extension fields. |
| `sys_role` | `SysRole` | `app\model\SysRole` | `ID` | Role metadata. Role grants are stored in `sys_relation`, not in this table. |
| `sys_resource` | `SysModule`, `SysMenu`, `SysButton` | `app\model\SysResource` | `ID` | One physical table represents module, menu, catalog, iframe, and button records by `CATEGORY`/`MENU_TYPE`. |
| `sys_relation` | `SysRelation` | `app\model\SysRelation` | `ID` | Generic RBAC and workbench relation table. `CATEGORY` decides relation meaning. |
| `sys_org` | `SysOrg` | `app\model\SysOrg` | `ID` | Organization tree. `PARENT_ID=0` is used for company roots in seed data. |
| `sys_position` | `SysPosition` | `app\model\SysPosition` | `ID` | Position table linked to organization by `ORG_ID`. |
| `sys_user_process_config` | `SysUserProcessConfig` | `app\model\SysUserProcessConfig` | `ID` | Per-user/per-tenant workflow configuration JSON. |
| `tenants` | `Tenants` | `app\model\Tenant` | `Tenant_ID` | Mixed-case column names are preserved exactly. |
| `auth_third_user` | `AuthThirdUser` | `app\model\AuthThirdUser` | `ID` | Third-party account binding table. `USER_ID` points to `sys_user.ID`. |
| `client_user` | `ClientUser` | `app\model\ClientUser` | `ID` | C-side user table, separate from `sys_user`. |
| `client_relation` | `ClientRelation` | `app\model\ClientRelation` | `ID` | Generic C-side relation table, same shape as `sys_relation`. |
| `mobile_resource` | `MobileModule`, `MobileMenu`, `MobileButton` | `app\model\MobileResource` | `ID` | One physical table represents mobile module/menu/button records. |

## Foundation Columns

### `sys_user`

Primary fields from `oa2026.sql`:

`ID`, `AVATAR`, `SIGNATURE`, `ACCOUNT`, `PASSWORD`, `NAME`, `NICKNAME`, `GENDER`, `AGE`, `BIRTHDAY`, `NATION`, `NATIVE_PLACE`, `HOME_ADDRESS`, `MAILING_ADDRESS`, `ID_CARD_TYPE`, `ID_CARD_NUMBER`, `CULTURE_LEVEL`, `POLITICAL_OUTLOOK`, `COLLEGE`, `EDUCATION`, `EDU_LENGTH`, `DEGREE`, `PHONE`, `EMAIL`, `HOME_TEL`, `OFFICE_TEL`, `EMERGENCY_CONTACT`, `EMERGENCY_PHONE`, `EMERGENCY_ADDRESS`, `EMP_NO`, `ENTRY_DATE`, `ORG_ID`, `POSITION_ID`, `POSITION_LEVEL`, `DIRECTOR_ID`, `POSITION_JSON`, `LAST_LOGIN_IP`, `LAST_LOGIN_ADDRESS`, `LAST_LOGIN_TIME`, `LAST_LOGIN_DEVICE`, `LATEST_LOGIN_IP`, `LATEST_LOGIN_ADDRESS`, `LATEST_LOGIN_TIME`, `LATEST_LOGIN_DEVICE`, `USER_STATUS`, `SORT_CODE`, `EXT_JSON`, `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`, `TENANT_ID`, `BANK_NAME`, `BANK_ACCOUNT`, `BASIC_SALARY`, `WORK_START_DATE`, `HEALTH_STATUS`, `SPECIALTY_SKILLS`, `ON_JOB_EDUCATION_JSON`, `FULL_TIME_EDUCATION_JSON`, `JOB_TITLE`, `SOCIAL_APPOINTMENTS`, `DEPARTMENT_ATTRIBUTE`, `PERSONAL_INFORMATION`, `MAIN_STUDY_AND_WORK_EXPERIENCE`, `AWARDS_AND_ACHIEVEMENTS`, `FAMILY_MEMBERS_AND_SOCIAL_RELATIONSHIPS_JSON`, `PARTY_ORGANIZATION_OPINION`, `ENTRY_METHOD`, `COMPANY_EMPLOYEE_ID`.

Java `SysUser` also has non-persistent view fields marked with `@TableField(exist = false)`: `orgName`, `positionName`, `directorName`. Do not create these as database columns.

Sensitive Java fields using `CommonSm4CbcTypeHandler`: `ID_CARD_NUMBER`, `PHONE`, `EMERGENCY_PHONE`. The ThinkPHP implementation must preserve compatibility with already stored encrypted values.

### `sys_role`

Fields: `ID`, `ORG_ID`, `NAME`, `CODE`, `CATEGORY`, `SORT_CODE`, `EXT_JSON`, `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`, `TENANT_ID`.

### `sys_resource`

Fields: `ID`, `PARENT_ID`, `TITLE`, `NAME`, `CODE`, `CATEGORY`, `MODULE`, `MENU_TYPE`, `PATH`, `COMPONENT`, `ICON`, `COLOR`, `VISIBLE`, `SORT_CODE`, `EXT_JSON`, `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`.

Java maps the same table with three entities:

- `SysModule`: module records
- `SysMenu`: menu/catalog/iframe records
- `SysButton`: button permission records

### `sys_relation`

Fields: `ID`, `OBJECT_ID`, `TARGET_ID`, `CATEGORY`, `EXT_JSON`.

The table is an application-level relation table. There are no SQL foreign keys in the current snapshot.

### `sys_org`

Fields: `ID`, `PARENT_ID`, `DIRECTOR_ID`, `NAME`, `CODE`, `CATEGORY`, `SORT_CODE`, `EXT_JSON`, `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`, `TENANT_ID`.

### `sys_position`

Fields: `ID`, `ORG_ID`, `NAME`, `CODE`, `CATEGORY`, `SORT_CODE`, `EXT_JSON`, `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`, `TENANT_ID`.

### `sys_user_process_config`

Fields: `ID`, `CONFIG_JSON`, `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`, `TENANT_ID`, `VERSION`.

`CONFIG_JSON` stores workflow names and user id lists. It should remain JSON text for compatibility.

### `tenants`

Fields: `Tenant_ID`, `Tenant_Name`, `CODE`, `CREATE_TIME`, `DELETE_FLAG`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`.

Column casing is mixed and must be preserved.

### `auth_third_user`

Fields: `ID`, `THIRD_ID`, `USER_ID`, `AVATAR`, `NAME`, `NICKNAME`, `GENDER`, `CATEGORY`, `EXT_JSON`, `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`.

### `client_user`

Fields mirror the independent C-side user profile:

`ID`, `AVATAR`, `SIGNATURE`, `ACCOUNT`, `PASSWORD`, `NAME`, `NICKNAME`, `GENDER`, `AGE`, `BIRTHDAY`, `NATION`, `NATIVE_PLACE`, `HOME_ADDRESS`, `MAILING_ADDRESS`, `ID_CARD_TYPE`, `ID_CARD_NUMBER`, `CULTURE_LEVEL`, `POLITICAL_OUTLOOK`, `COLLEGE`, `EDUCATION`, `EDU_LENGTH`, `DEGREE`, `PHONE`, `EMAIL`, `HOME_TEL`, `OFFICE_TEL`, `EMERGENCY_CONTACT`, `EMERGENCY_PHONE`, `EMERGENCY_ADDRESS`, `LAST_LOGIN_IP`, `LAST_LOGIN_ADDRESS`, `LAST_LOGIN_TIME`, `LAST_LOGIN_DEVICE`, `LATEST_LOGIN_IP`, `LATEST_LOGIN_ADDRESS`, `LATEST_LOGIN_TIME`, `LATEST_LOGIN_DEVICE`, `USER_STATUS`, `SORT_CODE`, `EXT_JSON`, `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`.

### `client_relation`

Fields: `ID`, `OBJECT_ID`, `TARGET_ID`, `CATEGORY`, `EXT_JSON`.

### `mobile_resource`

Fields: `ID`, `PARENT_ID`, `TITLE`, `CODE`, `CATEGORY`, `MODULE`, `MENU_TYPE`, `PATH`, `ICON`, `COLOR`, `REG_TYPE`, `STATUS`, `SORT_CODE`, `EXT_JSON`, `DELETE_FLAG`, `CREATE_TIME`, `CREATE_USER`, `UPDATE_TIME`, `UPDATE_USER`, `TENANT_ID`.

Java maps the same table with `MobileModule`, `MobileMenu`, and `MobileButton`.

## Other Table Groups

### Workflow Engine Tables

The `act_*` tables are Camunda/Activiti workflow engine tables and include their own indexes and foreign keys. They should be treated as engine-managed tables unless the workflow-agent later replaces the engine behavior.

Important groups:

- Repository/deployment: `act_re_deployment`, `act_re_procdef`, `act_re_case_def`, `act_re_decision_def`, `act_re_decision_req_def`
- Runtime: `act_ru_execution`, `act_ru_task`, `act_ru_variable`, `act_ru_identitylink`, `act_ru_job`, `act_ru_incident`
- History: `act_hi_procinst`, `act_hi_taskinst`, `act_hi_actinst`, `act_hi_varinst`, `act_hi_comment`, `act_hi_attachment`
- Identity: `act_id_user`, `act_id_group`, `act_id_membership`, `act_id_tenant`, `act_id_tenant_member`

### OA Business Tables

Business tables found in `oa2026.sql` include:

`biz_cc_records`, `biz_collection_receipt`, `biz_debit_note`, `biz_draft`, `biz_expenditure_record`, `biz_file_relation`, `biz_leave_application`, `biz_payment_record`, `biz_payroll`, `biz_product`, `biz_purchase_order`, `biz_purchase_order_item`, `biz_relation`, `biz_sale_project`, `biz_sale_project_invoice`, `biz_sale_project_invoice_item`, `biz_sale_project_invoicing`, `biz_sale_project_payment`, `biz_sale_project_product_info`, `biz_sale_project_product_item`, `biz_sale_project_reissue_order`, `biz_team_project`, `biz_team_project_comment`, `biz_team_project_comment_reply`, `biz_team_project_task`, `biz_team_project_task_category`, `biz_team_project_task_comment`, `biz_team_project_task_user`, `biz_team_project_user`, `biz_user_vacation`.

Additional business/support tables:

`customer`, `customer_follow_up`, `delivery_record`, `inventory`, `product_relation`, `return_order`, `return_order_item`, `sale_project_follow_up`, `sale_project_product_item_relation`, `sale_project_rate`, `settlement_account`, `settlement_account_statement`, `supplier`, `warehouses`.

These tables are documented at inventory level in this db-agent pass. Detailed business Model generation should be done in later db-agent slices before workflow/api agents depend on them.

### Dev/Generator Tables

`dev_config`, `dev_dict`, `dev_email`, `dev_file`, `dev_job`, `dev_log`, `dev_message`, `dev_relation`, `dev_sms`, `gen_basic`, `gen_config`.

These support platform configuration, files, logs, messages, jobs, dictionary, and generator metadata.

