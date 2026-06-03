# Biz Sale Project Follow-Up Read-Only API Compatibility

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

## Data Scope

- The query joins `sale_project_follow_up` to `biz_sale_project`.
- If `orgId` is supplied, the route limits results to that organization and its children.
- If the auth payload has data-scope organization ids, the route limits results to sale projects in those organizations.
- If no data-scope organization ids are available, the route falls back to the current user's responsible sale projects.
- Super-admin style local accounts/roles may see all records, matching the existing ThinkPHP customer-follow-up compatibility service.

## Deferred Routes

The following Java/frontend routes remain intentionally unimplemented in this slice:

| Route | Reason |
| --- | --- |
| `POST /biz/saleprojectfollowup/add` | Creates follow-up records and may include attachment metadata |
| `POST /biz/saleprojectfollowup/edit` | Mutates follow-up records |
| `POST /biz/saleprojectfollowup/delete` | Deletes follow-up records and requires write-side permission validation |

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
