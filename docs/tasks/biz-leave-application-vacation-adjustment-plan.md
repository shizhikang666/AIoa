# Biz Leave Application Vacation Adjustment Plan

## Scope

Add current-year annual-leave balance adjustment to direct business-row maintenance:

- `POST /biz/bizleaveapplication/edit`
- `POST /biz/bizleaveapplication/delete`
- only rows with `category = annualLeave`
- target table: `biz_user_vacation`

## Behavior

Direct edit computes the difference between the locked existing leave row and the submitted Java edit payload:

- annual to annual: adjust `USED_AMOUNT` by `new amount - old amount`
- annual to non-annual: restore the old annual-leave amount
- non-annual to annual: deduct the new annual-leave amount
- user changes restore the old user's balance and deduct the new user's balance

Direct delete restores the deleted annual-leave amount before logically deleting the leave row. Batch deletes validate all ids before any balance or delete write.

## Guardrails

- Adjustments run in the same transaction as the leave-row edit/delete.
- Only current-year annual-leave rows are adjusted.
- Missing annual-leave balance returns `400`.
- Deduction rejects insufficient remaining balance.
- Restoration rejects used-amount underflow instead of writing a negative balance.
- Vacation generation, direct leave add, payroll recalculation, copy-user generation outside active leave start, notifications, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred. Active leave-start copy-user CC rows are covered by `docs/tasks/workflow-copy-user-records-plan.md`.

## Verification

`scripts/biz-leave-application-vacation-adjustment-http-smoke.ps1` verifies:

- no-token and missing-id guards for direct edit/delete;
- increasing an annual-leave row increments `USED_AMOUNT` by the amount delta;
- changing annual leave to non-annual restores the annual amount;
- insufficient balance rolls back both the leave edit and vacation row;
- deleting an annual-leave row restores `USED_AMOUNT`;
- temporary leave/vacation rows are physically cleaned up.
