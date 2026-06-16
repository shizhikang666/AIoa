# Biz Expenditure Record Edit Plan

Date: 2026-06-16

Agent: merge-agent / api-agent / test-agent / docs-agent

## Scope

Replace only `/biz/bizexpenditurerecord/edit` controlled-deferred behavior with the narrow Java-compatible correction path.

## Java Contract

Java `BizExpenditureRecordEditParam` accepts:

- `id`
- `payerTime`
- `settlementCategory`

`BizExpenditureRecordServiceImpl.edit` loads the expenditure record, blocks protected category changes, blocks object-linked records, copies non-null edit fields to the expenditure row, and calls the settlement-account statement edit service with the linked `serialId` and submitted `payerTime`.

## Implementation Rules

- Validate `id`.
- Accept optional `payerTime` and optional `settlementCategory`.
- Require an active expenditure record visible to the token tenant and write scope.
- Reject object-linked rows.
- Reject category changes when the current category is `ReturnAndRefund`, `GOODS_EXPENDITURE`, or `repayment`.
- Reject target categories `CUSTOMER_REBATE`, `ReturnAndRefund`, `repayment`, and `TravelExpenses`.
- Update only expenditure `PAYER_TIME`, `SETTLEMENT_CATEGORY`, `UPDATE_TIME`, and `UPDATE_USER`.
- Sync only linked statement `PAYER_TIME`, `UPDATE_TIME`, and `UPDATE_USER` when `payerTime` is supplied.
- Roll back if the linked statement is missing.

## Deferred

- Add/delete.
- Account switch through `/biz/bizexpenditurerecord/edit/account`.
- Settlement-account balance mutation.
- Statement category/account changes.
- Purchase, inventory, return, workflow, notification, and data-change side effects.
- Java source, schema, Composer, `.env`, public config, and production data changes.

## Acceptance

- Valid authenticated edit returns `code=200`.
- No-token edit returns `code=401`.
- Missing `id` returns `code=400`.
- Protected category target returns `code=400`.
- Object-linked record returns `code=400`.
- Missing linked statement returns `code=404` and leaves the expenditure row unchanged.
- Expenditure payer time/category and linked statement payer time update as expected.
- Client-spoofed object, target, serial, process, amount, account, tenant, delete, user, and org fields are ignored.
- Deferred wrapper smoke no longer includes `/biz/bizexpenditurerecord/edit`.
