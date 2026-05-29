# Biz Collection Receipt Read-Only Compatibility

## Scope

This slice adds read-only ThinkPHP compatibility endpoints for the old Java collection-receipt APIs used by the Vue OA frontend.

Java source analyzed:

- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizcollectionreceipt/controller/BizCollectionReceiptController.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizcollectionreceipt/service/impl/BizCollectionReceiptServiceImpl.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizcollectionreceipt/param/BizCollectionReceiptPageParam.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizcollectionreceipt/entity/BizCollectionReceipt.java`
- `F:\AI\projects\testJava\OA\oa2026.sql`

Frontend source analyzed:

- `snowy-admin-web/src/api/biz/bizCollectionReceiptApi.js`

## Added Routes

All routes are protected by `AuthMiddleware`.

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/biz/bizcollectionreceipt/page` | Paginated collection-receipt list. |
| GET | `/biz/bizcollectionreceipt/list` | Non-paginated collection-receipt list. |
| GET | `/biz/bizcollectionreceipt/detail` | Read-only detail lookup for old frontend compatibility. |

## Explicitly Deferred Routes

These Java/frontend routes are not implemented in this slice because they mutate settlement state, expenditure records, or collection receipt records:

- `POST /biz/bizcollectionreceipt/batchExpenditure/edit`
- `POST /biz/bizcollectionreceipt/mark/success/edit`
- `POST /biz/bizcollectionreceipt/add`
- `POST /biz/bizcollectionreceipt/edit`
- `POST /biz/bizcollectionreceipt/delete`

## Query Compatibility

Supported query parameters:

- `current`, `size`, `page`, `limit`, `pageNo`, `pageSize`
- `sortField`, `sortOrder`
- `id`
- `paymentRecordId`
- `playStatus`
- `remark`
- `accountName`
- `searchKey`
- `tenantId`

The service reads `biz_collection_receipt` and enriches rows through:

- `biz_payment_record` by `PAYMENT_RECORD_ID`
- `settlement_account` by `biz_payment_record.TARGET_ID`
- `sys_org` by `biz_payment_record.ORG`

## Response Shape

Rows return frontend-friendly camelCase fields:

- `id`
- `paymentRecordId`
- `remark`
- `playStatus`
- `amount`
- `settlementAmount`
- `deleteFlag`
- `createTime`
- `createUser`
- `updateTime`
- `updateUser`
- `tenantId`
- `version`
- `payerTime`
- `accountId`
- `accountName`
- `accountNumber`
- `settlementCategory`
- `payer`
- `bankName`
- `bankAccount`
- `org`
- `orgName`

## Notes

- The Java controller comments out add, edit, delete, and detail mappings. The old frontend still has a `detail` wrapper, so ThinkPHP exposes only a protected read-only detail endpoint.
- Java's mark-settlement and batch-expenditure flows update settlement state and create/update expenditure data. They require a later transaction design before implementation.
- This slice does not modify Java source, database schema, Composer files, `.env`, or any write endpoint.
