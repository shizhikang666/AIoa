# Java OA Index Analysis

## Scope

Source SQL: `F:\AI\projects\testJava\OA\snowy-web-app\src\main\resources\_sql\2026\oa2026.sql`

This is an analysis document only. No index, table, or field changes were executed.

## Current Index Pattern

### Workflow Engine Tables

The `act_*` workflow engine tables contain many engine-defined indexes and foreign keys. Examples include:

- Runtime task indexes on create time, assignee, owner, tenant id, process definition, process instance, and execution id.
- History indexes on process instance, task id, tenant id, process definition key, start/end time, and removal time.
- Repository indexes on deployment id, tenant id, version tag, and definition relationships.

These indexes should be preserved as-is unless the workflow engine strategy changes.

### Core System Tables

The following foundation tables currently have only primary keys in the SQL snapshot:

- `auth_third_user`
- `client_relation`
- `client_user`
- `mobile_resource`
- `sys_config`
- `sys_org`
- `sys_position`
- `sys_relation`
- `sys_resource`
- `sys_role`
- `sys_user`
- `sys_user_process_config`
- `tenants`

Primary keys:

- Most tables use `PRIMARY KEY (ID)`.
- `tenants` uses `PRIMARY KEY (Tenant_ID)`.

### Business Tables With Additional Indexes

The SQL snapshot includes these non-primary indexes outside the workflow engine:

- `biz_file_relation`: `KEY objectid (OBJECT_ID)`
- `inventory`: `UNIQUE KEY idx_product_warehouse (PRODUCT_ID, WAREHOUSES_ID)`
- `sale_project_rate`: `KEY PROJECT_ID (PROJECT_ID)`

Most other `biz_*` and business support tables currently only have `PRIMARY KEY (ID)`.

## Risk Notes

### RBAC Lookup Risk

`sys_relation` is frequently queried by `OBJECT_ID`, `TARGET_ID`, and `CATEGORY`, but currently has only `PRIMARY KEY (ID)`.

High-frequency relation categories discovered in Java service code:

- `SYS_USER_HAS_ROLE`
- `SYS_ROLE_HAS_RESOURCE`
- `SYS_ROLE_HAS_PERMISSION`
- `SYS_USER_HAS_RESOURCE`
- `SYS_USER_HAS_PERMISSION`
- `SYS_ROLE_HAS_MOBILE_MENU`
- `SYS_USER_WORKBENCH_DATA`

Future DB migration should consider indexes such as:

- `sys_relation(CATEGORY, OBJECT_ID)`
- `sys_relation(CATEGORY, TARGET_ID)`
- `sys_relation(OBJECT_ID, TARGET_ID, CATEGORY)`

Do not add them until a migration file and test plan are agreed.

### Login Lookup Risk

Login will likely query:

- `sys_user.ACCOUNT`
- `sys_user.PHONE`
- `sys_user.EMAIL`
- `sys_user.TENANT_ID`
- `sys_user.DELETE_FLAG`
- `sys_user.USER_STATUS`

The snapshot has no secondary index for these columns. Auth-agent should avoid adding schema changes directly; if needed, request a db-agent migration.

Candidate future indexes:

- `sys_user(TENANT_ID, ACCOUNT, DELETE_FLAG)`
- `sys_user(TENANT_ID, PHONE, DELETE_FLAG)`
- `sys_user(TENANT_ID, EMAIL, DELETE_FLAG)`

Sensitive columns may be encrypted in existing data. Equality search behavior must be tested before indexing encrypted values.

### Menu Tree Risk

Menu rendering and authorization will query:

- `sys_resource.PARENT_ID`
- `sys_resource.MODULE`
- `sys_resource.CATEGORY`
- `sys_resource.MENU_TYPE`
- `sys_resource.DELETE_FLAG`
- `sys_resource.SORT_CODE`

Candidate future indexes:

- `sys_resource(CATEGORY, MODULE, DELETE_FLAG)`
- `sys_resource(PARENT_ID, DELETE_FLAG, SORT_CODE)`

Mobile menu equivalents:

- `mobile_resource(CATEGORY, MODULE, TENANT_ID, DELETE_FLAG)`
- `mobile_resource(PARENT_ID, TENANT_ID, DELETE_FLAG, SORT_CODE)`

### Organization/User Lookup Risk

Organization and position tree pages will query:

- `sys_org.PARENT_ID`
- `sys_org.TENANT_ID`
- `sys_org.DELETE_FLAG`
- `sys_position.ORG_ID`
- `sys_position.TENANT_ID`
- `sys_position.DELETE_FLAG`

Candidate future indexes:

- `sys_org(TENANT_ID, PARENT_ID, DELETE_FLAG, SORT_CODE)`
- `sys_position(TENANT_ID, ORG_ID, DELETE_FLAG, SORT_CODE)`

### Business Workflow Lookup Risk

Business/workflow tables often include user ids, process ids, status fields, and tenant ids but mostly only define primary keys. Later workflow/db slices should inspect these tables one by one before API work begins.

Candidate areas for future review:

- `biz_cc_records(PROCESS_ID, INSTANCE_ID, USER)`
- `biz_team_project_task(PROJECT_ID, USER, STATUS)`
- `biz_leave_application(CREATE_USER, TENANT_ID, DELETE_FLAG)`
- `biz_payment_record`, `biz_expenditure_record`, and sale project tables by project/process/status fields

## Current db-agent Decision

No indexes were added in this phase. This avoids hidden behavior changes before the auth/user/workflow agents define actual query paths and before a migration strategy exists.

