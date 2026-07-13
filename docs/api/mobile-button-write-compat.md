# Mobile Button Write Compatibility

Date: 2026-06-09

Agent: merge-agent / main control agent with worker review

## Scope

This slice adds Java-compatible mobile button write routes used by the copied Vue mobile resource button page:

- `POST /mobile/button/add`
- `POST /mobile/button/edit`
- `POST /mobile/button/delete`

The routes stay protected by `AuthMiddleware`.

## Java Reference

- `MobileButtonController`
- `MobileButtonServiceImpl`
- `SysRelationApiProvider::removeRoleHasMobileButtonRelation`

## Behavior

- Add and edit require `parentId`, `title`, `code`, and numeric `sortCode`.
- `extJson` is optional and is stored as JSON text when an object or array is submitted.
- Button rows are written to `mobile_resource` with `CATEGORY = BUTTON`.
- Button `CODE` is unique across active mobile button rows.
- New rows inherit `TENANT_ID` from the parent mobile menu when available, then from the auth payload, then from a safe fallback.
- Delete accepts Java-style array payloads such as `[{ "id": "..." }]` and also accepts common `idList`, `ids`, `id`, and `buttonIds` wrappers.
- Delete logically marks active button rows as `DELETED`.
- Delete removes deleted ids from `sys_relation.EXT_JSON.buttonInfo` for `CATEGORY = SYS_ROLE_HAS_MOBILE_MENU` and `TARGET_ID` in the deleted buttons' parent menu ids.
- Delete does not remove `sys_relation` rows and does not fail the whole request when the array includes missing button ids.

## Guardrails

- No Java source was modified.
- No database schema, Composer files, `.env`, or frontend source files were changed.
- This slice does not add mobile module or mobile menu write routes.
- This slice does not implement data-change events, cache invalidation hooks, or notification side effects.

## Verification

- `php -l app/controller/mobile/ButtonController.php`
- `php -l app/service/mobile/MobileResourceService.php`
- `php -l route/app.php`
- `php think route:list` confirms the three POST routes.
- `scripts/test-agent-db-smoke.ps1` covers add, duplicate-code rejection, edit, logical delete, mixed missing-id delete tolerance, and `SYS_ROLE_HAS_MOBILE_MENU` `buttonInfo` cleanup.
- `scripts/test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -MobileButtonHttpSmoke` covers the authenticated HTTP flow.
