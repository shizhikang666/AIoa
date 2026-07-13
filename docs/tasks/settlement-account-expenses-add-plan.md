# Settlement Account Expenses Add Plan

Date: 2026-06-17

Agent: merge-agent / api-agent / test-agent / docs-agent

## Goal

Replace the controlled-deferred `/biz/settlementaccount/expenses/add` wrapper with a narrow Java-compatible quick-expense implementation used by the copied settlement-account expense form.

## Java Reference

- `vip.xiaonuo.biz.modular.settlementaccount.controller.SettlementAccountController::expenses`
- `vip.xiaonuo.biz.modular.settlementaccount.service.impl.SettlementAccountServiceImpl::SettlementAccountCorrectExpenses`
- `vip.xiaonuo.biz.modular.settlementaccount.param.SettlementAccountCorrectParam`
- `vip.xiaonuo.biz.modular.bizexpenditurerecord.param.BizExpenditureRecordAddParam`

## Behavior

- Require `targetId`, `settlementCategory`, `payer`, `payerTime`, and positive `amount`.
- Accept optional `objectId`, `bankName`, `bankAccount`, and `remark`.
- Lock the target settlement account, read its current balance, and update the balance by subtracting the submitted amount.
- Create one `settlement_account_statement` row with `SETTLEMENT_TYPE = EXPEND`, `PROCESS_ID = Process_sys`, `PROCESS_CATEGORY = Process_sys`, before/after amounts, tenant, audit, and payer time.
- Create one linked `biz_expenditure_record` row with `SERIAL_ID` set to the statement id, `TARGET_ID` set to the account id, `USER` from the token, and `ORG` from the settlement account.
- Keep the route behind `AuthMiddleware` and existing service permission/data-scope rules.

## Verification

- PHP lint for the touched controller/service and route file.
- PowerShell parse check for the new smoke script.
- `scripts/settlement-account-expenses-add-http-smoke.ps1`:
  - verifies no-token 401;
  - verifies missing target, zero amount, and missing account failures;
  - proves failed requests do not change balances or finance row counts;
  - verifies successful account balance decrement, statement row, expenditure row, detail readback, tenant/user/org links, and cleanup.
- Existing settlement-account/payment read and finance smokes continue to pass.
- Deferred-wrapper smoke count drops by one because this route is no longer deferred.

## Explicit Non-Goals

- Do not implement `/biz/settlementaccount/transfer/add` or `/biz/settlementaccount/delete`.
- Do not implement Java data-change events, workflow hooks, collection-receipt settlement propagation, frontend source changes, Java source edits, schema changes, `.env` edits, Composer/npm changes, production data operations, or commits.

Subsequent state: transfer add and protected logical delete are covered by later focused plans. This plan's non-goals describe only the 2026-06-17 expenses-add slice boundary.
