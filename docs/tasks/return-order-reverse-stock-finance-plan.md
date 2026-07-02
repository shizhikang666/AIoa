## Scope

- Replace the direct return-order edit/delete side-effect guards with transactional reverse correction.
- Keep workflow-owned return orders protected: direct add/edit/delete reject process ids found in `act_hi_procinst.PROC_INST_ID_`.
- On direct edit, validate the new master/product payload first, then reverse existing active `ReturnAndRefund` expenditure/statement/account effects and active return IN delivery/inventory effects before updating the return order and rebuilding delivery/inventory rows for the edited product list.
- On direct delete, validate the whole selected batch first, reverse active refund finance and return IN inventory side effects, then logically delete the return orders and child rows.
- Recalculate affected sale-project `TOTAL_REFUND_AMOUNT`, `TOTAL_RETURN_AMOUNT`, and `TOTAL_PRICE` after edit/delete.

## Boundaries

- Reverse correction soft-deletes the generated `biz_expenditure_record`, `settlement_account_statement`, and `delivery_record` rows; inventory rows remain active with corrected `CURRENT_COUNT`.
- Account balances are restored from the stored expenditure amounts.
- Missing reverse dependencies, deleted inventory conflicts, or inventory underflow roll back the whole edit/delete transaction.
- No Java source edits, frontend source edits, schema changes, `.env` changes, production data operations, commits, notification side effects, file cleanup, or Java event-bus behavior.

## Verification

- `php -l app\service\biz\ReturnOrderService.php`
- PowerShell parser check for `scripts\return-order-write-http-smoke.ps1`
- `.\scripts\return-order-write-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`
- Regression smokes for workflow project return approval and settlement-account expenses.
- Frontend route/method/deferred-wrapper smokes and project-progress lean check.
