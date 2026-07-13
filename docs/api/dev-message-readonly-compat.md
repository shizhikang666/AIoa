# Dev Message Compatibility

## Scope

This document tracks authenticated compatibility for Java `DevMessageController` message management endpoints.

## Java Reference

- Java project, read-only: `F:\AI\projects\testJava\OA`
- Controller: `DevMessageController`
- Service: `DevMessageServiceImpl`
- SQL tables: `dev_message`, `dev_relation`, `sys_user`

## Added Routes

- `GET /dev/message/page`
- `GET /dev/message/detail`
- `POST /dev/message/send`
- `POST /dev/message/delete`

All routes are protected by `AuthMiddleware`.

## Response Shape

Page responses return:

- `records`
- `total`
- `page`
- `current`
- `limit`
- `size`
- `pages`

Rows include Java-compatible camel-case fields plus receiver counts:

- `id`
- `category`
- `subject`
- `content`
- `extJson`
- `tenantId`
- `receiveCount`
- `readCount`
- `createTime`
- `createUser`
- `updateTime`
- `updateUser`

Detail responses also include `receiveInfoList`, with:

- `receiveUserId`
- `receiveUserName`
- `read`

## Supported Filters

- `id`
- `category`
- `searchKey`
- `receiveUserId`
- `receiverId`
- `current`, `page`, or `pageNo`
- `size`, `limit`, or `pageSize`
- `sortField`
- `sortOrder`

Supported sort fields are:

- `id`
- `category`
- `subject`
- `createTime`
- `updateTime`

## Deliberate Exclusions

- Detail reads do not send SSE/WebPush notifications.
- Send does not perform full SSE/WebPush realtime push.
- Delete does not send SSE/WebPush notifications.
- No database schema or Java source files are changed.

## Detail Mark-Read Behavior

`GET /dev/message/detail` follows Java `DevMessageServiceImpl.detail` for read state:

- if the current token user has a receiver relation for the message, that relation is marked as read
- only rows with `CATEGORY = MSG_TO_USER` are considered
- existing `EXT_JSON` keys are preserved while `read` is set to `true`
- other receivers are not changed
- full SSE/WebPush refresh behavior remains deferred

## Send Behavior

`POST /dev/message/send` accepts the copied frontend message form payload:

```json
{
  "subject": "Notice title",
  "category": "SYS",
  "content": "Notice body",
  "href": "/sys/index",
  "receiverIdList": ["1543837863788879873"]
}
```

It also accepts receiver objects containing `id`, `userId`, `value`, or `key` for user-selector compatibility.

The endpoint:

- requires `subject`
- requires at least one receiver
- defaults blank `content` to `subject`
- defaults blank `category` to `SYS`
- limits access to admin-compatible accounts or roles until fine-grained route permissions are complete
- limits receivers to active users in the current tenant when the bearer token carries tenant information
- inserts one `dev_message` row with `EXT_JSON.href`
- inserts one `dev_relation` row per receiver with `CATEGORY = MSG_TO_USER` and `EXT_JSON.read = false`

## Delete Behavior

`POST /dev/message/delete` accepts Java-style arrays:

```json
[
  { "id": "2032011542112157698" }
]
```

It also accepts `idList`, `ids`, or a single `id` for frontend compatibility.

The endpoint:

- validates a non-empty id list
- limits rows to the current tenant when the bearer token carries `tenant_id`
- allows admin-compatible accounts or roles to delete tenant messages
- allows ordinary users to delete only messages they created
- deletes `dev_relation` rows with `CATEGORY = MSG_TO_USER`
- deletes the selected `dev_message` rows

## Later Work

Full SSE/WebPush notification parity still needs a later plan.

## 2026-06-15 HTTP Smoke Coverage

`scripts/dev-read-http-smoke.ps1` now covers authenticated message management reads for:

- `GET /dev/message/page`

The smoke asserts Java-style paging keys and page row count fields. It intentionally does not call `GET /dev/message/detail` because detail marks the current receiver relation as read. It also does not call message send/delete, SSE/WebPush fanout, provider routes, or external services.
