# Tenant Read-Only Compatibility

Date: 2026-06-15

## Scope

The copied tenant management page uses these protected read endpoints:

- `GET /tenants/tenant/page`
- `GET /tenants/tenant/detail`

This slice adds authenticated HTTP smoke coverage for the existing read-only routes. It does not add tenant add, edit, delete, default-user generation, default-role generation, cache mutation, or data-change events.

## Verified Shape

`scripts/tenant-read-http-smoke.ps1` verifies:

- local bearer-token authentication without printing credentials or tokens;
- `page` returns Java-style `records`, `total`, `current`, `size`, and `pages`;
- page rows include non-blank `tenantId` and `tenantName` when sample tenant rows exist;
- `detail` includes non-blank `tenantId` and `tenantName` when the local database has an active tenant sample.

`scripts/project-preflight.ps1` runs this smoke by default unless `-SkipTenantRead` is passed.
