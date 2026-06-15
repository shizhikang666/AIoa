# Dev Email And Sms Compatibility

## Scope

This slice adds authenticated compatibility for Java email and SMS send record query endpoints plus low-risk metadata logical delete.

## Java Reference

- Java project, read-only: `F:\AI\projects\testJava\OA`
- Email controller: `DevEmailController`
- Email service: `DevEmailServiceImpl`
- Email SQL table: `dev_email`
- Email frontend API: `snowy-admin-web/src/api/dev/emailApi.js`
- SMS controller: `DevSmsController`
- SMS service: `DevSmsServiceImpl`
- SMS SQL table: `dev_sms`
- SMS frontend API: `snowy-admin-web/src/api/dev/smsApi.js`

## Added Routes

Protected routes:

- `GET /dev/email/page`
- `GET /dev/email/detail`
- `POST /dev/email/delete`
- `GET /dev/sms/page`
- `GET /dev/sms/detail`
- `POST /dev/sms/sendAliyun`
- `POST /dev/sms/sendTencent`
- `POST /dev/sms/sendXiaonuo`
- `POST /dev/sms/delete`

## Email Response Shape

Email rows return:

- `id`
- `engine`
- `sendAccount`
- `sendUser`
- `receiveAccounts`
- `subject`
- `content`
- `tagName`
- `templateName`
- `templateParam`
- `receiptInfo`
- `extJson`
- `tenantId`
- `createTime`
- `createUser`
- `updateTime`
- `updateUser`

## SMS Response Shape

SMS rows return:

- `id`
- `engine`
- `phoneNumbers`
- `signName`
- `templateCode`
- `templateParam`
- `receiptInfo`
- `extJson`
- `tenantId`
- `createTime`
- `createUser`
- `updateTime`
- `updateUser`

## Page Response Shape

Page responses return:

- `records`
- `total`
- `page`
- `current`
- `limit`
- `size`
- `pages`

## Supported Filters

Common filters:

- `id`
- `engine`
- `searchKey`
- `current`, `page`, or `pageNo`
- `size`, `limit`, or `pageSize`
- `sortField`
- `sortOrder`

Email-specific filters:

- `sendAccount`
- `receiveAccounts`

SMS-specific filters:

- `signName`
- `templateCode`

## Endpoint Notes

- Email `searchKey` follows Java behavior and searches `SUBJECT`.
- SMS `searchKey` follows Java behavior and searches `PHONE_NUMBERS`.
- Queries are scoped to the current token tenant when the bearer token contains tenant information.
- Details return `null` for missing or out-of-tenant rows.
- Delete accepts the Java/frontend array body shape `[{ "id": "..." }]`.
- Delete also accepts `idList`, `ids`, or single `id` payloads for compatibility with existing ThinkPHP helpers.
- Delete is tenant-scoped and updates only active rows in the current token tenant.
- Delete sets `DELETE_FLAG = DELETED`, updates audit fields, and returns `data = null`.
- SMS provider send wrappers are routed behind `AuthMiddleware` but intentionally return `code = 400` with `sms sending is deferred`.
- SMS provider send wrappers do not read provider credentials, call SMS SDKs, insert send records, or contact external services.

## Deliberate Exclusions

- No email send routes are implemented.
- SMS send routes are controlled-deferred wrappers only; real SMS sending is not implemented.
- No local mail client, cloud email provider, or SMS provider integration is called.
- No third-party credentials, API keys, database schema, or Java source files are changed.

## Later Work

Email/SMS sending needs a dedicated write-endpoint plan covering provider credentials, permission checks, validation, rate limiting, audit logging, send failure handling, and secret storage.
