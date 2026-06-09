# Mobile Module Write Compatibility

Date: 2026-06-09

Agent: merge-agent / main control agent with worker review

## Scope

This slice adds Java-compatible mobile module write routes used by the copied Vue mobile resource module page:

- `POST /mobile/module/add`
- `POST /mobile/module/edit`
- `POST /mobile/module/delete`

The routes stay protected by `AuthMiddleware`.

## Java Reference

- `MobileModuleController`
- `MobileModuleServiceImpl`
- `MobileModuleAddParam`
- `MobileModuleEditParam`
- `SysRelationApiProvider::removeRoleHasMobileMenuRelation`

## Behavior

- Add and edit require `title`, `icon`, `color`, and numeric `sortCode`.
- `extJson` is optional and is stored as JSON text when an object or array is submitted.
- Module rows are written to `mobile_resource` with `CATEGORY = MODULE`.
- New module `CODE` is generated as a Java-style random 10-character string and is not accepted from the client.
- Module `TITLE` is unique across active mobile module rows.
- Delete accepts Java-style array payloads such as `[{ "id": "..." }]` and also accepts common `idList`, `ids`, `id`, and `moduleIds` wrappers.
- Delete logically marks active module rows and module-owned mobile menu rows as `DELETED`.
- Delete removes whole `sys_relation` rows where `CATEGORY = SYS_ROLE_HAS_MOBILE_MENU` and `TARGET_ID` is one of the deleted module/menu ids.
- Java module delete does not collect `CATEGORY = BUTTON` rows, so this compatibility slice does not delete mobile button rows as part of module delete.

## Guardrails

- No Java source was modified.
- No database schema, Composer files, `.env`, or frontend source files were changed.
- This slice does not add mobile menu write routes.
- This slice does not implement data-change events, cache invalidation hooks, notification side effects, or mobile button cleanup beyond Java's module-delete behavior.

## Verification

- `php -l app/controller/mobile/ModuleController.php`
- `php -l app/service/mobile/MobileResourceService.php`
- `php -l route/app.php`
- `php think route:list` confirms the three POST routes.
- `scripts/test-agent-db-smoke.ps1` covers add, 10-character generated code, duplicate-title rejection, edit, logical delete of module/menu rows, mixed missing-id delete tolerance, and `SYS_ROLE_HAS_MOBILE_MENU` relation deletion.
- `scripts/test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -MobileModuleHttpSmoke` covers the authenticated HTTP flow.
