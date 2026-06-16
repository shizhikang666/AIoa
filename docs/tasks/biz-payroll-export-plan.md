# Biz Payroll Export Plan

Date: 2026-06-16

Agent: api-agent / test-agent / docs-agent single-conversation fallback

## Scope

Open `GET /biz/bizpayroll/export` as a read/download route for the copied payroll page.

The route may:

- apply the existing payroll query filters and data-scope rules;
- sort by organization when no sort field is supplied, matching the Java export behavior;
- return an authenticated CSV blob that Excel can open.

## Non-goals

- No salary import parsing.
- No payroll generation.
- No payroll add workflow.
- No leave balance recalculation.
- No finance, workflow, message, file, schema, Composer, Java source, or `.env` changes.
- No xlsx writer dependency until an approved spreadsheet dependency exists.

## Compatibility Notes

The Java route writes an `.xlsx` with EasyExcel. The current ThinkPHP project does not include a spreadsheet writer dependency, while existing user exports already use dependency-free CSV downloads. This slice uses CSV intentionally instead of introducing a new package or pretending to emit xlsx bytes.

The CSV includes the Java export-visible columns:

- organization group and employee name;
- salary cost fields;
- commission fields;
- leave/year-end/payable/deduction/actual fields;
- public/private account fields and remark.

## Acceptance Checks

- PHP lint for touched controller and service files.
- Route list still exposes `GET /biz/bizpayroll/export`.
- Authenticated HTTP smoke inserts one temporary payroll row, downloads CSV through `/biz/bizpayroll/export`, asserts CSV headers and row marker, verifies no related table counts changed, then cleans up.
- No-token GET returns `code = 401`.
- Frontend deferred-wrapper smoke no longer expects `/biz/bizpayroll/export` to return controlled deferred.
