# Biz Settlement Account Compatibility

## Scope

This note tracks protected ThinkPHP routes compatible with the Java settlement-account controller and the existing Vue API module.

## Routes

- `GET /biz/settlementaccount/page`
- `GET /biz/settlementaccount/list`
- `POST /biz/settlementaccount/add`
- `POST /biz/settlementaccount/edit`
- `POST /biz/settlementaccount/edit/status`
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

## Base Write Compatibility

The active frontend account form and status switch use:

- `POST /biz/settlementaccount/add`
- `POST /biz/settlementaccount/edit`
- `POST /biz/settlementaccount/edit/status`

Behavior:

- `add` requires `accountName` and `accountNumber`, validates `accountStatus` as `ENABLE` or `DISABLED`, writes `INITIAL_AMOUNT`, initializes `CURRENT_AMOUNT` to the same value, and fills `org` from the current token/user context when the frontend does not send it.
- `edit` updates only Java-compatible master-data fields: `ACCOUNT_NAME`, `ACCOUNT_STATUS`, `SORT_CODE`, and lower-case `org`.
- `edit/status` updates only `ACCOUNT_STATUS`.
- Duplicate active account names are rejected within the same tenant.
- Writes use tenant, audit, and data-scope guards consistent with the existing ThinkPHP master-data write slices.
- No account balance statement, income record, expenditure record, transfer, or archive behavior is triggered by these three base maintenance routes.

## Explicit Exclusions

- No `/biz/settlementaccount/delete` route was added.
- No `/biz/settlementaccount/expenses/add` route was added.
- No `/biz/settlementaccount/payment/add` route was added.
- No `/biz/settlementaccount/transfer/add` route was added.
- No settlement amount mutation, statement write, income/expense record creation, transfer behavior, delete route, database schema change, Java source change, `.env`, Composer file, or public config change was added.

## Verification

- `php -l app/service/biz/SettlementAccountService.php`
- `php -l app/controller/biz/SettlementAccountController.php`
- `php -l route/app.php`
- `php think route:list`
- Token smoke tests for page, list, detail, queryName, and no-token 401.
- DB smoke added a temporary account, verified `CURRENT_AMOUNT` equals `INITIAL_AMOUNT` and `org` comes from the current context, edited name/status/org, toggled status, checked duplicate-name, invalid-status, and non-admin rejection, then removed the temporary row.

## 2026-06-15 HTTP Smoke Coverage

`scripts/settlement-account-read-http-smoke.ps1` now verifies authenticated settlement-account read payloads against the local backend:

- `GET /biz/settlementaccount/page`
- `GET /biz/settlementaccount/list`
- `GET /biz/settlementaccount/detail` when a visible page row exists
- `GET /biz/settlementaccount/queryName` when a visible page row exists

The smoke checks Java-style paging keys and stable frontend-visible fields such as `accountName`, `accountNumber`, `initialAmount`, `currentAmount`, `accountStatus`, `orgName`, `archiveAmount`, `archiveTime`, and decoded `ext`. It does not call add, edit, edit/status, delete, expenses, payment, transfer, balance mutation, statement writes, workflow, provider, or data-change behavior.
