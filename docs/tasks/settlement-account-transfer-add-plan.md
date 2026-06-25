# Settlement Account Transfer Add Plan

Date: 2026-06-17

Agent: merge-agent / api-agent / test-agent / docs-agent

## Goal

Replace the controlled-deferred `/biz/settlementaccount/transfer/add` wrapper with a narrow Java-compatible transfer implementation used by the copied settlement-account transfer form.

## Java Reference

- `vip.xiaonuo.biz.modular.settlementaccount.controller.SettlementAccountController::transfer`
- `vip.xiaonuo.biz.modular.settlementaccount.service.impl.SettlementAccountServiceImpl::transfer`
- `vip.xiaonuo.biz.modular.settlementaccount.param.SettlementAccountTransferParam`
- `vip.xiaonuo.biz.modular.settlementaccount.param.SettlementAccountCorrectParam`
- `vip.xiaonuo.biz.modular.bizexpenditurerecord.param.BizExpenditureRecordAddParam`
- `vip.xiaonuo.biz.modular.bizpaymentrecord.param.BizPaymentRecordAddParam`

## Behavior

- Require `expensesAccountId`, `revenueAccountId`, `payerTime`, and positive `amount`.
- Reject transfers where the expense and income account ids are the same.
- Accept optional `remark`.
- Lock both settlement accounts in stable id order, read current balances, subtract the amount from the expense account, and add the amount to the income account.
- Use fixed settlement category `dealings`, matching Java `SettlementCategoryEnum.dealings`.
- Create one expense-side `settlement_account_statement` row with `SETTLEMENT_TYPE = EXPEND`, `PROCESS_ID = Process_sys`, `PROCESS_CATEGORY = Process_sys`, before/after amounts, tenant, audit, and payer time.
- Create one income-side `settlement_account_statement` row with `SETTLEMENT_TYPE = INCOME`, `PROCESS_ID = Process_sys`, `PROCESS_CATEGORY = Process_sys`, before/after amounts, tenant, audit, and payer time.
- Create one linked `biz_expenditure_record` row whose `TARGET_ID` is the expense account and `OBJECT_ID`/payer/bank account point at the income account.
- Create one linked `biz_payment_record` row whose `TARGET_ID` is the income account and `OBJECT_ID`/payer/bank account point at the expense account.
- Keep the route behind `AuthMiddleware` and existing service permission/data-scope rules.

## Verification

- PHP lint for the touched controller/service and route file.
- PowerShell parse check for the new smoke script.
- `scripts/settlement-account-transfer-add-http-smoke.ps1`:
  - verifies no-token 401;
  - verifies missing expense account, missing income account, zero amount, same-account, and missing-account failures;
  - proves failed requests do not change balances or finance row counts;
  - verifies successful two-account balance movement, two statement rows, one expenditure row, one payment row, detail readback, tenant/user/org links, and cleanup.
- Existing settlement-account payment/expense/read and finance smokes continue to pass.
- Deferred-wrapper smoke count drops by one because this route is no longer deferred.

## Explicit Non-Goals

- Do not implement `/biz/settlementaccount/delete`.
- Do not implement Java data-change events, workflow hooks, collection-receipt settlement propagation, debit-note repayment propagation, frontend source changes, Java source edits, schema changes, `.env` edits, Composer/npm changes, production data operations, or commits.

Subsequent state: protected logical delete is covered by `docs/tasks/settlement-account-delete-plan.md`. This plan's non-goal describes only the 2026-06-17 transfer-add slice boundary.
