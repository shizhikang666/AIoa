# Biz Payment Record Read-Only Compatibility

## Scope

This slice adds protected read-only ThinkPHP routes compatible with the Java payment-record controller and the existing Vue API module.

## Routes

- `GET /biz/bizpaymentrecord/page`
- `GET /biz/bizpaymentrecord/listdetails`
- `GET /biz/bizpaymentrecord/list`
- `GET /biz/bizpaymentrecord/detail`

## Java References

- `vip.xiaonuo.biz.modular.bizpaymentrecord.controller.BizPaymentRecordController`
- `vip.xiaonuo.biz.modular.bizpaymentrecord.service.impl.BizPaymentRecordServiceImpl`
- `vip.xiaonuo.biz.modular.bizpaymentrecord.entity.BizPaymentRecord`
- `vip.xiaonuo.biz.modular.bizpaymentrecord.param.BizPaymentRecordPageParam`
- `vip.xiaonuo.biz.modular.bizpaymentrecord.param.BizPaymentRecordQueryParam`

## Tables

- `biz_payment_record`
- `settlement_account`
- `sys_org`

## Supported Filters

- `current`, `size`, `page`, `limit`, `pageNo`, `pageSize`
- `sortField`, `sortOrder`
- `id`
- `objectId`
- `objectIds`
- `targetId`
- `serialId`
- `processId`
- `settlementCategory`
- `startPayerTime`, `endPayerTime`
- `payerStartTime`, `payerEndTime`
- `startCreateTime`, `endCreateTime`
- `amount`
- `accountName`
- `orgId`
- `searchKey`
- `tenantId`

## Response Notes

- `page` returns `records`, `total`, `current`, `size`, `pages`.
- `listdetails` returns plain read rows using page-style filters.
- `list` returns plain read rows using query-style filters.
- `detail` is added for old frontend compatibility; the analyzed Java controller does not expose a detail route, but the Java service has a `detail` method and the Vue wrapper calls it.
- Rows include settlement-account display fields `accountName` and `accountNumber`, plus `orgName` from `sys_org`.

## Explicit Exclusions

- No `/biz/bizpaymentrecord/edit` route was added.
- No `/biz/bizpaymentrecord/edit/account` route was added.
- No payment-record mutation, settlement-account transfer, statement edit, data-change event, database schema change, Java source change, `.env`, Composer file, or public config change was added.

## Verification

- `composer dump-autoload`
- `php think`
- `php think route:list`
- PHP syntax lint
- Token smoke tests for page, listdetails, list, detail, and no-token 401.

## 2026-06-15 HTTP Smoke Coverage

`scripts/finance-read-http-smoke.ps1` now verifies authenticated payment-record read payloads against the local backend:

- `GET /biz/bizpaymentrecord/page`
- `GET /biz/bizpaymentrecord/listdetails`
- `GET /biz/bizpaymentrecord/list`
- `GET /biz/bizpaymentrecord/detail` when a visible page row exists

The smoke checks Java-style paging keys and stable frontend-visible finance fields such as `objectId`, `targetId`, `accountName`, `accountNumber`, `serialId`, `processId`, `settlementCategory`, `payerTime`, `amount`, and `orgName`. It does not call payment edit/account routes, settlement-account transfers, statements, provider actions, workflow, or any finance mutation.
