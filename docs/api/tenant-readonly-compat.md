# Tenant Compatibility

Date: 2026-06-15

## Scope

The copied tenant management page uses these protected endpoints:

- `GET /tenants/tenant/page`
- `GET /tenants/tenant/detail`
- `POST /tenants/tenant/add`
- `POST /tenants/tenant/edit`
- `POST /tenants/tenant/delete`

This compatibility note started as read-only smoke coverage. Tenant add, edit, and delete now provide narrow `tenants` row metadata maintenance, while default-user generation, default-role generation, cache mutation, and data-change events remain deferred.

## Verified Shape

`scripts/tenant-read-http-smoke.ps1` verifies:

- local bearer-token authentication without printing credentials or tokens;
- `page` returns Java-style `records`, `total`, `current`, `size`, and `pages`;
- page rows include non-blank `tenantId` and `tenantName` when sample tenant rows exist;
- `detail` includes non-blank `tenantId` and `tenantName` when the local database has an active tenant sample.

`scripts/tenant-write-http-smoke.ps1` verifies:

- no-token and missing-name rejection;
- add creates one active tenant row and returns `data = null`;
- duplicate add, system-tenant edit, and missing mixed-delete batches are rejected;
- edit updates the tenant name while preserving code, delete flag, and create audit fields;
- delete requires safe-password marker state and then logically deletes the row;
- default sys user, role, resource, and relation row counts stay unchanged.

`scripts/project-preflight.ps1` runs the read smoke by default unless `-SkipTenantRead` is passed. Run `scripts/tenant-write-http-smoke.ps1` for the focused write-maintenance checks.
