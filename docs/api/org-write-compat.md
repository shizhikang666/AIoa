# Organization Write Compatibility

Date: 2026-06-06

Agent: user-agent / frontend-agent

## Scope

This slice supports copied system and business organization maintenance pages:

- `snowy-admin-web/src/api/sys/orgApi.js`
- `snowy-admin-web/src/api/biz/bizOrgApi.js`
- `snowy-admin-web/src/views/sys/org/index.vue`
- `snowy-admin-web/src/views/sys/org/form.vue`
- `snowy-admin-web/src/views/biz/org/index.vue`
- `snowy-admin-web/src/views/biz/org/form.vue`

The Java source project remains read-only at `F:\AI\projects\testJava\OA`.

## Implemented Routes

| Method | Path | Target |
| --- | --- | --- |
| POST | `/sys/org/add` | `sys.OrgController/add` |
| POST | `/sys/org/edit` | `sys.OrgController/edit` |
| POST | `/sys/org/delete` | `sys.OrgController/delete` |
| POST | `/biz/org/add` | `sys.OrgController/bizAdd` |
| POST | `/biz/org/edit` | `sys.OrgController/bizEdit` |
| POST | `/biz/org/delete` | `sys.OrgController/bizDelete` |

All routes are protected by `AuthMiddleware`.

## Request Compatibility

Add and edit accept copied frontend camelCase fields:

- `parentId`
- `name`
- `category`
- `sortCode`
- `directorId`
- `extJson`

Delete accepts copied frontend batch payloads such as:

```json
[{ "id": "..." }]
```

It also accepts common `id`, `ids`, `idList`, and `orgIds` payload forms.

## Behavior

- Add validates parent organization, same-level duplicate names, category values `COMPANY` and `DEPT`, sort code, optional director, tenant compatibility, and base route/button permission.
- Edit validates the active organization, parent organization, parent cycle prevention, same-level duplicate names, optional director, tenant compatibility, and base route/button permission.
- Delete expands selected organizations to active children before checking dependencies.
- Delete blocks organizations referenced by active users, user extra-position JSON, active roles, or active positions.
- Delete uses logical `sys_org.DELETE_FLAG = DELETED` instead of Java's physical removal to keep the staged refactor safer on imported data.
- Business routes add Java-compatible data-scope checks:
  - add requires the selected parent organization inside the token data scope;
  - edit requires the original organization id and original parent id inside the token data scope;
  - delete requires every selected root organization inside the token data scope.

## Deferred

- Java data-change event publishing
- Java physical delete behavior
- Position add/edit/delete
- User import/export
- Route-permission middleware
- Frontend source changes
- Database schema changes
- Workflow, finance, stock, and unrelated business side effects

## Verification

- `php -l app\controller\sys\OrgController.php`
- `php -l app\service\user\OrgService.php`
- `php -l route\app.php`
- `php think route:list`
- Direct service smoke creates, edits, logically deletes, and physically cleans only temporary smoke organizations.
- No-token HTTP smoke for all six POST routes returns business `code=401`.
