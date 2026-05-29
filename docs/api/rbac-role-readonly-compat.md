# RBAC Role Read-Only Compatibility

Date: 2026-05-29

Agent: merge-agent

## Goal

Expose the read-only `/sys/role/*` endpoints used by the existing Vue role management API while keeping role mutation and grant writes deferred.

## Java Inputs

- `snowy-admin-web/src/api/sys/roleApi.js`
- `snowy-plugin-sys/.../role/controller/SysRoleController.java`
- `snowy-plugin-sys/.../role/service/impl/SysRoleServiceImpl.java`

## Added Read-Only Endpoints

- `GET /sys/role/page`
- `GET /sys/role/detail`
- `GET /sys/role/ownResource`
- `GET /sys/role/ownMobileMenu`
- `GET /sys/role/ownPermission`
- `GET /sys/role/ownUser`
- `GET /sys/role/orgTreeSelector`
- `GET /sys/role/resourceTreeSelector`
- `GET /sys/role/mobileMenuTreeSelector`
- `GET /sys/role/permissionTreeSelector`
- `GET /sys/role/roleSelector`
- `GET /sys/role/userSelector`

All routes are protected by `AuthMiddleware`.

## Data Sources

- Roles: `sys_role`
- Role relations: `sys_relation`
- Web resources: `sys_resource`
- Mobile resources: `mobile_resource`
- Organization selector: existing `OrgService`
- User selector: existing `UserDirectoryService`

## Deferred

- `POST /sys/role/add`
- `POST /sys/role/edit`
- `POST /sys/role/delete`
- `POST /sys/role/grantResource`
- `POST /sys/role/grantMobileMenu`
- `POST /sys/role/grantPermission`
- `POST /sys/role/grantUser`

Grant mutations need dedicated validation, built-in role protection, audit logging, and test coverage before implementation.
