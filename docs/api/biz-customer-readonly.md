# Biz Customer API Compatibility

Date: 2026-06-02

Agent: api-agent

## Scope

This slice maps the read-only Java customer and customer-follow-up endpoints needed by the copied Vue frontend:

- `GET /biz/customer/page`
- `GET /biz/customer/detail`
- `POST /biz/customer/detail/list`
- `GET /biz/customerfollowup/page`
- `GET /biz/customerfollowup/detail`
- `POST /biz/customerfollowup/add`
- `POST /biz/customerfollowup/edit`
- `POST /biz/customerfollowup/delete`

All routes are protected by the existing `AuthMiddleware`.

## Java Sources

- `vip.xiaonuo.biz.modular.customer.controller.CustomerController`
- `vip.xiaonuo.biz.modular.customer.service.impl.CustomerServiceImpl`
- `vip.xiaonuo.biz.modular.customer.entity.Customer`
- `vip.xiaonuo.biz.modular.followup.controller.CustomerFollowUpController`
- `vip.xiaonuo.biz.modular.followup.service.impl.CustomerFollowUpServiceImpl`
- `vip.xiaonuo.biz.modular.followup.entity.CustomerFollowUp`

SQL reference:

- `F:\AI\projects\testJava\OA\oa2026.sql`

## Implemented Behavior

### Customer page

`GET /biz/customer/page`

Supported filters:

- `name`
- `contacts`
- `phone`
- `detailsAddress`
- `address`
- `status`
- `sourceType`
- `customType`
- `headName`
- `createUserName`
- `orgId`
- `showRepeat`
- `searchKey`
- `tenantId`
- `current` / `page` / `pageNo`
- `size` / `limit` / `pageSize`
- `sortField` / `sortOrder`

Returned display fields include:

- `headName`
- `orgName`
- `createUserName`
- `downloadPath`
- `firstContactTime`

### Customer detail

`GET /biz/customer/detail?id=<id>`

Returns one customer row with Java/frontend-compatible camelCase fields.

### Customer detail list

`POST /biz/customer/detail/list`

Returns export-compatible rows:

```json
[
  {
    "customer": {},
    "customerFollowUps": []
  }
]
```

The frontend export page uses this shape to combine customer base information and follow-up records.

### Customer follow-up page

`GET /biz/customerfollowup/page`

Supported filters:

- `customerId`
- `customerName`
- `content`
- `startFollowUpTime`
- `endFollowUpTime`
- `tenantId`
- `current` / `page` / `pageNo`
- `size` / `limit` / `pageSize`
- `sortField` / `sortOrder`

Returned display fields include:

- `customerName`
- `createUserName`
- `avatar`
- `createUserOrgId`
- `createUserOrgName`

### Customer follow-up detail

`GET /biz/customerfollowup/detail?id=<id>`

Returns one customer follow-up row with Java/frontend-compatible camelCase fields.

### Customer follow-up add

`POST /biz/customerfollowup/add`

Required fields:

- `customerId`
- `followUpTime`
- `content`

Optional fields:

- `extJson`
- `tenantId`

The endpoint validates that the target customer is writable by the current token user before inserting. It writes `CREATE_TIME`, `CREATE_USER`, `TENANT_ID`, and `DELETE_FLAG = NOT_DELETE`.

### Customer follow-up edit

`POST /biz/customerfollowup/edit`

Required fields:

- `id`

Mutable fields when present:

- `followUpTime`
- `content`
- `extJson`

The endpoint loads the existing non-deleted follow-up row, validates write permission from the owning customer, and updates only submitted mutable fields plus update audit columns.

### Customer follow-up delete

`POST /biz/customerfollowup/delete`

Accepted input shapes:

- `[{"id": "..."}]`
- `{"idList": ["..."]}`
- `{"ids": ["..."]}`
- `{"id": "..."}`

The endpoint validates every target row through the owning customer, then performs a logical delete by setting `DELETE_FLAG = DELETED`. It does not physically remove imported data.

## Data Scope

The slice applies the following conservative visibility order:

1. Explicit `orgId` filter expands to the organization and children.
2. Admin accounts/roles (`bizAdmin`, `superAdmin`, `tenantAdmin`) can read all tenant customer rows.
3. Token data-scope organization IDs are used when present.
4. Otherwise, the query falls back to rows owned by the current user.

## Encrypted Field Note

The Java project uses SM4 type handlers for customer `PHONE` and `DETAILS_ADDRESS`.

This slice does not implement SM4 encryption/decryption. It preserves stored values and supports raw stored-value matching only. Plaintext phone/detail-address search is deferred until a dedicated crypto compatibility plan is approved.

## Deferred Routes

The following Java/frontend routes remain intentionally unregistered:

- `/biz/customer/add`
- `/biz/customer/edit`
- `/biz/customer/delete`
- `/biz/customer/head/edit`

Customer write routes require validation, mutation permissions, SM4 handling, audit fields, and side-effect review before implementation.

Customer follow-up attachment uploads, file cleanup, and notification side effects remain deferred.

## Verification

Required checks:

```powershell
php -l app\controller\biz\CustomerController.php
php -l app\controller\biz\CustomerFollowUpController.php
php -l app\service\biz\CustomerService.php
php -l app\service\biz\CustomerFollowUpService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```
