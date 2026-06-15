# Biz Sale Project Follow-Up API Compatibility

Date: 2026-06-03

Agent: api-agent

## Java Reference

- Controller: `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\followup\controller\SaleProjectFollowUpController.java`
- Service: `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\followup\service\impl\SaleProjectFollowUpServiceImpl.java`
- Entity: `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\followup\entity\SaleProjectFollowUp.java`
- SQL reference: `F:\AI\projects\testJava\OA\oa2026.sql`

## Implemented ThinkPHP Routes

All routes are protected by `AuthMiddleware`.

| Method | Route | ThinkPHP Handler | Behavior |
| --- | --- | --- | --- |
| GET | `/biz/saleprojectfollowup/page` | `biz.SaleProjectFollowUpController/page` | Paginated sale-project follow-up records |
| GET | `/biz/saleprojectfollowup/detail` | `biz.SaleProjectFollowUpController/detail` | Single sale-project follow-up record |
| POST | `/biz/saleprojectfollowup/add` | `biz.SaleProjectFollowUpController/add` | Create a sale-project follow-up record |
| POST | `/biz/saleprojectfollowup/edit` | `biz.SaleProjectFollowUpController/edit` | Update a sale-project follow-up record |
| POST | `/biz/saleprojectfollowup/delete` | `biz.SaleProjectFollowUpController/delete` | Logically delete sale-project follow-up records |

## Request Compatibility

`page` supports the Java/frontend query fields:

- `current`
- `size`
- `sortField`
- `sortOrder`
- `searchKey`
- `projectId`
- `startFollowUpTime`
- `endFollowUpTime`
- `category`
- `content`

## Response Compatibility

Rows preserve Java/frontend camelCase fields:

- `id`
- `projectId`
- `projectName`
- `projectUser`
- `projectOrg`
- `followUpTime`
- `category`
- `content`
- `deleteFlag`
- `createTime`
- `createUser`
- `createUserName`
- `avatar`
- `createUserOrgId`
- `createUserOrgName`
- `updateTime`
- `updateUser`
- `tenantId`
- `extJson`

The service returns `extJson` unchanged because the copied sale-project detail tab parses `extJson.fileList` on the frontend.

## Write Compatibility

### Add

`POST /biz/saleprojectfollowup/add`

Required fields:

- `projectId`
- `followUpTime`
- `category`
- `content`

Optional fields:

- `fileList`
- `extJson`
- `tenantId`

When `fileList` is submitted, the endpoint stores it as:

```json
{"fileList":[]}
```

This matches the Java service behavior and keeps the copied sale-project detail follow-up tab able to parse attachments from `extJson`.

### Edit

`POST /biz/saleprojectfollowup/edit`

Required fields:

- `id`
- `projectId`
- `followUpTime`
- `category`
- `content`

The endpoint updates the submitted business fields plus update audit columns. It does not modify `extJson`, matching the Java edit parameter.

### Delete

`POST /biz/saleprojectfollowup/delete`

Accepted input shapes:

- `[{"id": "..."}]`
- `{"idList": ["..."]}`
- `{"ids": ["..."]}`
- `{"id": "..."}`

The endpoint validates every target row through its owning sale project, then performs a logical delete by setting `DELETE_FLAG = DELETED`. It does not physically remove imported data.

## Data Scope

- The query joins `sale_project_follow_up` to `biz_sale_project`.
- If `orgId` is supplied, the route limits results to that organization and its children.
- If the auth payload has data-scope organization ids, the route limits results to sale projects in those organizations.
- If no data-scope organization ids are available, the route falls back to the current user's responsible sale projects.
- Super-admin style local accounts/roles may see all records, matching the existing ThinkPHP customer-follow-up compatibility service.
- Write endpoints apply the same visibility order against the owning sale project before changing data.

## Deferred Routes

The following related behavior remains intentionally unimplemented in this slice:

| Behavior | Reason |
| --- | --- |
| File upload/storage and physical cleanup | Requires storage provider strategy and file lifecycle review |
| Notifications | Requires message/runtime strategy |
| Sale-project state, workflow, finance, or inventory side effects | Out of scope for a low-risk follow-up record write slice |

## Test Commands

```powershell
php -l app\controller\biz\SaleProjectFollowUpController.php
php -l app\service\biz\SaleProjectFollowUpService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

## Local Smoke Result

Date: 2026-06-03

- `php think route:list` lists both sale-project follow-up read routes.
- Direct service smoke returned 836 follow-up records and successfully loaded detail for the first sampled record.
- Authenticated HTTP smoke returned:
  - login `code = 200`;
  - `/biz/saleprojectfollowup/page` `code = 200`, `total = 836`;
  - `/biz/saleprojectfollowup/detail` `code = 200`;
  - unauthenticated `/biz/saleprojectfollowup/page` `code = 401`.
- Browser direct route `/biz/saleprojectfollowup` currently returns the copied Vue 404 page because the frontend route/menu entry is not registered. The API itself is ready for components that call it, including the sale-project detail follow-up tab.
- Browser was restored to `/biz/saleproject` after this smoke.

## 2026-06-15 Business Read HTTP Smoke

`scripts/business-read-http-smoke.ps1` now verifies sale-project follow-up read payloads alongside the core sale-project read contracts.

Covered sale-project follow-up checks:

- `GET /biz/saleprojectfollowup/page`
- `GET /biz/saleprojectfollowup/detail` when local follow-up sample data exists

The smoke loads an existing active sale-project follow-up id from the local database when available, verifies Java-style paging keys for page responses, and confirms frontend-visible fields: `id`, `projectId`, `projectName`, `projectUser`, `projectOrg`, `followUpTime`, `category`, `content`, `createUserName`, `avatar`, `createUserOrgId`, `createUserOrgName`, and `extJson`.

This smoke is read-only. It does not call sale-project follow-up add, edit, delete, attachment cleanup, notifications, sale-project state writes, workflow, finance, inventory, or provider actions.
