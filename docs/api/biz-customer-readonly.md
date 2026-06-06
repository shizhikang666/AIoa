# Biz Customer API Compatibility

Date: 2026-06-06

Agent: api-agent

## Scope

This document maps the Java customer and customer-follow-up endpoints currently supported by the ThinkPHP compatibility layer:

- `GET /biz/customer/page`
- `POST /biz/customer/add`
- `POST /biz/customer/edit`
- `POST /biz/customer/delete`
- `GET /biz/customer/detail`
- `POST /biz/customer/detail/list`
- `POST /biz/customer/head/edit`
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

### Customer add

`POST /biz/customer/add`

Required fields:

- `fileId`

Supported mutable fields:

- `name`
- `contacts`
- `phone`
- `detailsAddress`
- `address`
- `sourceType`
- `customType`
- `status`
- `sortCode`
- `remark`
- `firstContactTime`
- `extJson`

The endpoint inserts a new active customer row with `DELETE_FLAG = NOT_DELETE`, `VERSION = 0`, and audit fields. If `user`/`org` are not submitted, it defaults the owner and organization from the current token user. Submitted owner/organization values are still checked against the current token user's write scope.

### Customer edit

`POST /biz/customer/edit`

Required fields:

- `id`

The endpoint validates that the current token user can write the customer through the same conservative owner/org data-scope used by customer follow-up writes. It updates only submitted mutable fields plus `UPDATE_TIME`, `UPDATE_USER`, and `VERSION`.

### Customer delete

`POST /biz/customer/delete`

Accepted input shapes:

- `[{"id": "..."}]`
- `{"idList": ["..."]}`
- `{"ids": ["..."]}`
- `{"id": "..."}`

The endpoint validates every target customer through the current user's write scope, then performs a logical delete by setting `DELETE_FLAG = DELETED`. It does not physically remove imported customer data.

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

### Customer head edit

`POST /biz/customer/head/edit`

Required fields:

- `id`
- `user`

The endpoint validates the current token can edit the active customer, then validates the target user through Java-compatible data-scope rules:

- Admin-compatible accounts/roles can assign any active user.
- Scoped users can assign users inside their token data-scope organization ids.
- Users without explicit data scope can assign only themselves.

The endpoint updates only `USER`, `ORG`, update audit fields, and increments `VERSION`. It does not trigger sale-project reassignment, notifications, or Java data-change events.

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

Customer follow-up attachment uploads, file cleanup, and notification side effects remain deferred.

Customer write routes store submitted `PHONE` and `DETAILS_ADDRESS` values as received. A full Java-compatible SM4 encryption/decryption strategy remains deferred until approved.

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
