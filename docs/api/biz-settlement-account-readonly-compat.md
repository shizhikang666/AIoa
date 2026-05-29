# Biz Settlement Account Read-Only Compatibility

## Scope

This slice adds protected read-only ThinkPHP routes compatible with the Java settlement-account controller and the existing Vue API module.

## Routes

- `GET /biz/settlementaccount/page`
- `GET /biz/settlementaccount/list`
- `GET /biz/settlementaccount/detail`
- `GET /biz/settlementaccount/queryName`

## Java References

- `vip.xiaonuo.biz.modular.settlementaccount.controller.SettlementAccountController`
- `vip.xiaonuo.biz.modular.settlementaccount.service.impl.SettlementAccountServiceImpl`
- `vip.xiaonuo.biz.modular.settlementaccount.entity.SettlementAccount`
- `vip.xiaonuo.biz.modular.settlementaccount.param.SettlementAccountPageParam`
- `vip.xiaonuo.biz.modular.settlementaccount.param.SettlementAccountQueryParam`

## Tables

- `settlement_account`
- `sys_org`

## Supported Filters

- `current`, `size`, `page`, `limit`, `pageNo`, `pageSize`
- `sortField`, `sortOrder`
- `id`
- `accountName`
- `name`
- `accountNumber`
- `accountStatus`
- `searchKey`
- `orgId`
- `tenantId`

## Response Notes

- `page` returns `records`, `total`, `current`, `size`, `pages`.
- `list` returns only enabled accounts, matching Java `queryEnableList`.
- `detail` returns a single account row.
- `queryName` returns the account name string.
- SQL column `org` is intentionally preserved as the source organization field and enriched with `orgName` from `sys_org`.

## Explicit Exclusions

- No `/biz/settlementaccount/add` route was added.
- No `/biz/settlementaccount/edit` route was added.
- No `/biz/settlementaccount/delete` route was added.
- No `/biz/settlementaccount/edit/status` route was added.
- No `/biz/settlementaccount/expenses/add` route was added.
- No `/biz/settlementaccount/payment/add` route was added.
- No `/biz/settlementaccount/transfer/add` route was added.
- No settlement amount mutation, statement write, income/expense record creation, transfer behavior, database schema change, Java source change, `.env`, Composer file, or public config change was added.

## Verification

- `composer dump-autoload`
- `php think`
- `php think route:list`
- PHP syntax lint
- Token smoke tests for page, list, detail, queryName, and no-token 401.
