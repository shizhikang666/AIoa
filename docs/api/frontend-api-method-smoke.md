# Frontend API Method Smoke

Date: 2026-06-15

## Scope

`scripts/frontend-api-method-smoke.ps1` statically checks copied Vue `views` and `components` for default-imported API modules under `snowy-admin-web/src/api`.

The smoke fails when a frontend component calls a read-like method such as `someApi.someDetail()` or `someApi.somePage()` that is not exported by the imported API module. This targets the browser failure mode where a page crashes with `is not a function` before any backend route is reached.

## Deferred Writes

Write-like missing methods, including names ending in `SubmitForm`, `Delete`, `Add`, `Edit`, `Approve`, `Reject`, `Upload`, or similar side-effect verbs, are not failed by default. Those remain governed by the module-specific deferred write plans.

Use `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred` to print those deferred write-like missing method references for planning.

## Preflight

`scripts/project-preflight.ps1` runs this static smoke by default unless `-SkipFrontendApiMethod` is passed.

`scripts/test-agent-smoke.ps1` also runs this static smoke by default unless `-SkipFrontendApiMethod` is passed.

## 2026-06-16 Comment Handling

The smoke strips Vue/HTML comments plus JavaScript line and block comments before scanning imports and API method calls. Commented legacy calls should not appear in the missing or deferred method lists.
