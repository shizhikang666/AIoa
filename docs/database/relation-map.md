# Java OA Relation Map

## Scope

This document records database relations discovered from the SQL snapshot, Java entities, Mapper XML, and service category usage. It is a mapping document only; no database schema changes were made.

## Relation Principles

- Most system tables do not define SQL foreign keys. Relations are enforced in Java service logic.
- `sys_relation` is the central RBAC relation table.
- `client_relation` and `biz_relation` follow the same generic relation pattern for other domains.
- Tree structures use `PARENT_ID` and seed data commonly uses `0` as a virtual root.
- Tenant isolation uses `TENANT_ID` on system/resource/user tables and `Tenant_ID` on `tenants`.

## Core Relations

| Source | Target | Storage | Meaning |
| --- | --- | --- | --- |
| `sys_user.ORG_ID` | `sys_org.ID` | direct field | User primary organization. |
| `sys_user.POSITION_ID` | `sys_position.ID` | direct field | User primary position. |
| `sys_user.DIRECTOR_ID` | `sys_user.ID` | direct field | User supervisor. |
| `sys_user.TENANT_ID` | `tenants.Tenant_ID` | direct field | User tenant. |
| `sys_role.ORG_ID` | `sys_org.ID` | direct field | Organization-scoped role. Nullable for global roles. |
| `sys_role.TENANT_ID` | `tenants.Tenant_ID` | direct field | Role tenant. |
| `sys_org.PARENT_ID` | `sys_org.ID` | direct field | Organization tree. |
| `sys_org.DIRECTOR_ID` | `sys_user.ID` | direct field | Organization director. |
| `sys_org.TENANT_ID` | `tenants.Tenant_ID` | direct field | Organization tenant. |
| `sys_position.ORG_ID` | `sys_org.ID` | direct field | Position organization. |
| `sys_position.TENANT_ID` | `tenants.Tenant_ID` | direct field | Position tenant. |
| `sys_resource.PARENT_ID` | `sys_resource.ID` | direct field | Menu/catalog/button tree. |
| `sys_resource.MODULE` | `sys_resource.ID` | direct field | Module ownership for menu resources. |
| `mobile_resource.PARENT_ID` | `mobile_resource.ID` | direct field | Mobile menu/button tree. |
| `mobile_resource.MODULE` | `mobile_resource.ID` | direct field | Mobile module ownership. |
| `auth_third_user.USER_ID` | `sys_user.ID` | direct field | Third-party account binding. |
| `sys_user_process_config.CONFIG_JSON` | `sys_user.ID` | JSON ids | Workflow approval, copy, treasurer, and procurement user id lists. |

## `sys_relation` Categories

Java enum source: `snowy-plugin/snowy-plugin-sys/src/main/java/vip/xiaonuo/sys/modular/relation/enums/SysRelationCategoryEnum.java`.

| Category | Object side | Target side | Notes |
| --- | --- | --- | --- |
| `SYS_USER_WORKBENCH_DATA` | `sys_user.ID` | nullable | `EXT_JSON` stores workbench shortcut/config JSON. |
| `SYS_USER_HAS_RESOURCE` | `sys_user.ID` | `sys_resource.ID` | Direct user menu/resource grant. |
| `SYS_USER_HAS_PERMISSION` | `sys_user.ID` | API URL string | Direct user API permission. `EXT_JSON` stores data scope metadata. |
| `SYS_USER_HAS_ROLE` | `sys_user.ID` | `sys_role.ID` | User-role grant. |
| `SYS_ROLE_HAS_RESOURCE` | `sys_role.ID` | `sys_resource.ID` | Role menu/resource grant. `EXT_JSON` can include button ids. |
| `SYS_ROLE_HAS_MOBILE_MENU` | `sys_role.ID` | `mobile_resource.ID` | Role mobile menu grant. `EXT_JSON` can include mobile button ids. |
| `SYS_ROLE_HAS_PERMISSION` | `sys_role.ID` | API URL string | Role API permission. `EXT_JSON` stores data scope metadata. |

## Resource Table Polymorphism

`sys_resource` is not one Java entity in practice:

- `SysModule` records use `CATEGORY=MODULE`.
- `SysMenu` records use `CATEGORY=MENU` plus `MENU_TYPE` such as `CATALOG`, `MENU`, or `IFRAME`.
- `SysButton` records use `CATEGORY=BUTTON`.

The ThinkPHP foundation keeps one `SysResource` model. Later auth/menu agents can add query scopes or service-layer filters for `CATEGORY`/`MENU_TYPE` without changing the table.

`mobile_resource` follows the same pattern:

- `MobileModule`
- `MobileMenu`
- `MobileButton`

## Mapper XML Findings

The inspected core Mapper XML files mainly provide physical delete or bypass-interceptor queries:

- `SysUserMapper.xml`: `DELETE FROM SYS_USER ...`, `select * FROM SYS_USER ...`
- `SysRoleMapper.xml`: `DELETE FROM SYS_ROLE ...`
- `SysOrgMapper.xml`: `DELETE FROM SYS_ORG ...`
- `SysPositionMapper.xml`: `DELETE FROM SYS_POSITION ...`
- `SysModuleMapper.xml`, `SysMenuMapper.xml`, `SysButtonMapper.xml`: `DELETE FROM SYS_RESOURCE ...`

The business meaning of relations is mostly in Java service code, especially `SysRoleServiceImpl`, `SysUserServiceImpl`, and resource service implementations.

## Workflow Relation Notes

- The `act_*` tables keep workflow engine runtime/history relations with engine-defined foreign keys.
- OA-specific workflow configuration is in `sys_user_process_config.CONFIG_JSON`.
- Workflow-related business rows use fields such as process id, process instance id, promoter/user ids, and tenant id in `biz_*` tables.
- The workflow-agent must preserve process names found in seed data, for example `Process_reimbursement`, `Process_make_payment`, `Process_procure`, `Process_procure_in_warehouse`, `Process_payment`, `Process_sale_project_init`, `Process_sale_project_play`, `Process_sale_project_delivery`, `Process_project_reissue_product`, and `Process_ask_leave`.

## Implementation Boundary For Later Agents

- db-agent generated only passive foundation models and mapping documents.
- auth-agent may add RBAC query methods/services on top of `SysUser`, `SysRole`, `SysResource`, and `SysRelation`.
- user-agent may add organization/user service logic on top of `SysUser`, `SysOrg`, and `SysPosition`.
- workflow-agent may add workflow models and services in a later phase after a dedicated workflow DB slice.
- No agent should rename or delete fields to make relations look cleaner.

