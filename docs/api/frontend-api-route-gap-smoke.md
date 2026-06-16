# Frontend API Route Gap Smoke

Date: 2026-06-16

## Scope

`scripts/frontend-api-route-gap-smoke.ps1` statically scans copied frontend API wrapper files under `snowy-admin-web/src/api` and compares their normalized request paths with `php think route:list`.

This is an advisory route coverage scanner. It does not prove that a route is semantically complete, and it does not grant permission to implement every missing side-effect endpoint.

## Usage

```powershell
.\scripts\frontend-api-route-gap-smoke.ps1
.\scripts\frontend-api-route-gap-smoke.ps1 -ShowMissing
.\scripts\frontend-api-route-gap-smoke.ps1 -Json
.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing
```

Default mode prints only summary counts and exits successfully. `-ShowMissing` prints missing read-like and side-effect-like endpoint groups. `-FailOnReadMissing` is intended for targeted checks after a read-route slice, not for the default preflight, because many side-effect routes are intentionally deferred.

`scripts/project-preflight.ps1` now runs this smoke with `-FailOnReadMissing` by default. Use `-SkipFrontendApiRouteGap` only when route-list generation is intentionally unavailable.

## Classification

The scanner classifies endpoints as side-effect-like when the frontend method name or path includes write/action verbs such as add, edit, delete, cancel, approve, start, upload, import, export, send, grant, reset, enable, disable, mark, run, complete, amount, deal, visibility, or history.

Every remaining missing endpoint is treated as read-like for planning. This classification is conservative and should be checked against the copied page before implementation.

## Current Verification

- `.\scripts\frontend-api-route-gap-smoke.ps1`: passed in summary mode with 560 unique frontend endpoints, 560 covered route paths, 0 missing read-like endpoints, and 0 missing side-effect-like endpoints.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -ShowMissing`: produced the current route-gap planning list without changing code, database, schema, Java source, runtime config, or Git history.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed.
