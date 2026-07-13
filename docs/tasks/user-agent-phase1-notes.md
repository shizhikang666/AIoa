# user-agent Phase 1 Notes

## Database Tables

- `sys_user`: user profile, account, password hash, org/position links, login metadata, tenant id, HR-related extension fields.
- `sys_org`: organization tree with `PARENT_ID`, `DIRECTOR_ID`, `CATEGORY`, `SORT_CODE`, `TENANT_ID`, and soft delete flag.
- `sys_position`: position records scoped to org and tenant.
- `sys_relation`: user-role, user-resource, role-resource, user-permission, role-permission, and workbench relations.
- `sys_user_process_config`: user-specific workflow approval config; workflow-agent should own runtime semantics.

## Boundary Decisions

- auth-agent owns login, token, RBAC session payload, permission codes, button codes, and login menu.
- user-agent owns user CRUD, org CRUD, position CRUD, user-center profile APIs, selectors, and org/position lookup helpers.
- Grants that write `sys_relation` require coordination with auth-agent because they change RBAC behavior.

## Recommended Phase 2

Start with read-only endpoints and services:

1. organization tree/query service
2. position page/detail/selector service
3. user page/detail/selector service
4. user-center login org tree and login position info

Defer these until later:

- user add/edit/delete
- enable/disable/reset password
- role/resource/permission grants
- import/export
- avatar/signature upload
- process config editing
