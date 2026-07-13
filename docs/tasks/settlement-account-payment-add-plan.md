# Settlement Account Payment Add Plan

Date: 2026-06-17

Agent: merge-agent / api-agent / test-agent / docs-agent

## Goal

Replace the controlled-deferred `/biz/settlementaccount/payment/add` wrapper with a narrow Java-compatible quick-income implementation used by the copied settlement-account income form.

## Java Reference

- `vip.xiaonuo.biz.modular.settlementaccount.controller.SettlementAccountController::income`
- `vip.xiaonuo.biz.modular.settlementaccount.service.impl.SettlementAccountServiceImpl::SettlementAccountCorrectIncome`
- `vip.xiaonuo.biz.modular.settlementaccount.param.SettlementAccountCorrectParam`
- `vip.xiaonuo.biz.modular.bizpaymentrecord.param.BizPaymentRecordAddParam`

## Behavior

- Require `targetId`, `settlementCategory`, `payer`, `payerTime`, and positive `amount`.
- Accept optional `objectId`, `bankName`, `bankAccount`, and `remark`.
- Lock the target settlement account, read its current balance, and update the balance by adding the submitted amount.
- Create one `settlement_account_statement` row with `SETTLEMENT_TYPE = INCOME`, `PROCESS_ID = Process_sys`, `PROCESS_CATEGORY = Process_sys`, before/after amounts, tenant, audit, and payer time.
- Create one linked `biz_payment_record` row with `SERIAL_ID` set to the statement id, `TARGET_ID` set to the account id, `USER` from the token, and `ORG` from the settlement account.
- Keep the route behind `AuthMiddleware` and existing service permission/data-scope rules.

## Verification

- PHP lint for the touched controller/service and route file.
- PowerShell parse check for the new smoke script.
- `scripts/settlement-account-payment-add-http-smoke.ps1`:
  - verifies no-token 401;
  - verifies missing target, zero amount, and missing account failures;
  - proves failed requests do not change balances or finance row counts;
  - verifies successful account balance increment, statement row, payment row, detail readback, tenant/user/org links, and cleanup.
- Existing settlement-account/payment read and finance smokes continue to pass.
- Deferred-wrapper smoke count drops by one because this route is no longer deferred.

## Explicit Non-Goals

- Do not implement `/biz/settlementaccount/expenses/add`, `/biz/settlementaccount/transfer/add`, or `/biz/settlementaccount/delete`.
- Do not implement Java data-change events, workflow hooks, notification behavior, frontend source changes, Java source edits, schema changes, `.env` edits, Composer/npm changes, production data operations, or commits.

Subsequent state: expenses add, transfer add, and protected logical delete are covered by later focused plans. This plan's non-goals describe only the 2026-06-17 payment-add slice boundary.
