# Biz Expenditure Record Read-Only Compatibility

## Scope

This slice adds protected read-only ThinkPHP routes compatible with the Java expenditure-record controller and the existing Vue API module.

## Routes

- `GET /biz/bizexpenditurerecord/page`
- `GET /biz/bizexpenditurerecord/listDetails`
- `GET /biz/bizexpenditurerecord/list`
- `GET /biz/bizexpenditurerecord/detail`

## Java References

- `vip.xiaonuo.biz.modular.bizexpenditurerecord.controller.BizExpenditureRecordController`
- `vip.xiaonuo.biz.modular.bizexpenditurerecord.service.impl.BizExpenditureRecordServiceImpl`
- `vip.xiaonuo.biz.modular.bizexpenditurerecord.entity.BizExpenditureRecord`
- `vip.xiaonuo.biz.modular.bizexpenditurerecord.param.BizExpenditureRecordPageParam`
- `vip.xiaonuo.biz.modular.bizexpenditurerecord.param.BizExpenditureRecordQueryParam`

## Tables

- `biz_expenditure_record`
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
- `payer`
- `bankName`
- `bankAccount`
- `remark`
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
- `listDetails` returns plain rows using page-style filters.
- `list` returns plain rows using query-style filters.
- `detail` returns a single read row.
- Rows include settlement-account display fields `accountName` and `accountNumber`, plus `orgName` from `sys_org`.

## Explicit Exclusions

- No `/biz/bizexpenditurerecord/add` route was added.
- No `/biz/bizexpenditurerecord/edit` route was added.
- No `/biz/bizexpenditurerecord/edit/account` route was added.
- No `/biz/bizexpenditurerecord/delete` route was added.
- No expenditure-record mutation, settlement-account transfer, statement edit, data-change event, database schema change, Java source change, `.env`, Composer file, or public config change was added.

## Verification

- `composer dump-autoload`
- `php think`
- `php think route:list`
- PHP syntax lint
- Token smoke tests for page, listDetails, list, detail, and no-token 401.

## 2026-06-15 HTTP Smoke Coverage

`scripts/finance-read-http-smoke.ps1` now verifies authenticated expenditure-record read payloads against the local backend:

- `GET /biz/bizexpenditurerecord/page`
- `GET /biz/bizexpenditurerecord/listDetails`
- `GET /biz/bizexpenditurerecord/list`
- `GET /biz/bizexpenditurerecord/detail` when a visible page row exists

The smoke checks Java-style paging keys and stable frontend-visible finance fields such as `objectId`, `targetId`, `accountName`, `accountNumber`, `serialId`, `processId`, `settlementCategory`, `payerTime`, `amount`, and `orgName`. It does not call add/edit/delete, account edit routes, settlement-account transfers, statements, provider actions, workflow, or any finance mutation.
