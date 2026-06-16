# Biz User Vacation Manual Maintenance Plan

Date: 2026-06-16

Agent: merge-agent / api-agent / test-agent

## Scope

Replace the controlled-deferred `biz_user_vacation` write wrappers with narrow manual balance maintenance:

- `POST /biz/bizuservacation/add`
- `POST /biz/bizuservacation/edit`
- `POST /biz/bizuservacation/delete`

This slice is intentionally limited to the `biz_user_vacation` table.

## Compatibility Basis

- The Java controller currently exposes `GET /biz/bizuservacation/detail`.
- The Java service and generated parameter classes still define `add`, `edit`, and `delete` behavior.
- The copied frontend API wrapper exposes `bizUserVacationSubmitForm` and `bizUserVacationDelete`, even though the current copied views only actively read vacation detail in leave-process pages.

## Transaction And Validation Plan

- Wrap add, edit, and delete in `Db::transaction`.
- Require Java parameter fields for add/edit: `userId`, `amount`, `usedAmount`, and `category`; edit also requires `id`.
- Reject `userId`, `id`, or `category` values that exceed the physical column length instead of surfacing database truncation errors.
- Reject negative amounts and `usedAmount > amount`.
- Require the target user to exist and match the current token tenant when the token carries a tenant id.
- Reject an active duplicate row for the same user, category, tenant, and current year.
- Keep delete as logical delete with full-batch validation before any update.
- Increment `VERSION` on edit and delete.

## Explicit Non-Goals

- No annual-leave generation.
- No leave approval deduction.
- No workflow start, approve, reject, or cancel behavior.
- No payroll recalculation.
- No notification or data-change events.
- No Java source, schema, `.env`, provider, scheduler, or production data changes.

## Verification

Use:

```powershell
.\scripts\biz-user-vacation-write-http-smoke.ps1
.\scripts\hr-read-http-smoke.ps1
.\scripts\frontend-deferred-write-wrapper-smoke.ps1
```

The write smoke creates a temporary category row for the local smoke account, verifies add/detail/duplicate rejection/edit/invalid-edit rejection/batch-delete rollback/logical delete, checks leave and payroll row counts stay unchanged, and physically removes only its own temporary rows in cleanup.
