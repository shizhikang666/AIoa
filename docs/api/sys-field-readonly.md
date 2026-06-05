# Sys Field Read-Only Compatibility

Date: 2026-06-05

Agent: user-agent / api-agent / frontend-agent

## Scope

This slice supports the copied system resource field wrapper:

- `snowy-admin-web/src/api/sys/resource/fieldApi.js`

The Java source remains read-only under `F:\AI\projects\testJava\OA`.

## Routes

| Method | Path | Controller |
| --- | --- | --- |
| GET | `/sys/field/page` | `sys.FieldController/page` |
| GET | `/sys/field/tree` | `sys.FieldController/tree` |
| GET | `/sys/field/detail` | `sys.FieldController/detail` |
| GET | `/sys/field/MenuTreeSelector` | `sys.FieldController/menuTreeSelector` |

## Behavior

- `page` reads `sys_resource` rows where `CATEGORY = FIELD`.
- `tree` reads the same field rows and builds the standard resource tree shape.
- `detail` reads one field resource by `id`.
- `MenuTreeSelector` delegates to the existing menu tree selector so the copied field form can choose an owning menu.
- The current imported database has no `FIELD` rows, so page/tree return stable empty read structures.

## Response Fields

Field rows use the existing resource shape:

- `id`
- `parentId`
- `title`
- `name`
- `code`
- `category`
- `module`
- `menuType`
- `path`
- `component`
- `icon`
- `color`
- `visible`
- `sortCode`
- `extJson`
- `createTime`
- `createUser`
- `updateTime`
- `updateUser`

## Deferred

- `/sys/field/add`
- `/sys/field/edit`
- `/sys/field/delete`
- Menu, button, module, or field write behavior
