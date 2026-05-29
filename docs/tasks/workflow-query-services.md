# Workflow Query Services

## Goal

Add read-only workflow query services without adding controllers, routes, mutations, or side effects.

## Added Services

- `WorkflowVariableService`
- `WorkflowQueryService`

## Supported Read Paths

- pending task count by current user
- pending task page/list by current user
- historic task page by current user
- started process page by current user
- process detail with variables, activities, and comments
- runtime and historic variable normalization

## Merge Dependency

These services reference db-agent models:

- `ActRuTask`
- `ActRuVariable`
- `ActHiTaskinst`
- `ActHiProcinst`
- `ActHiVarinst`
- `ActHiActinst`
- `ActHiComment`

The final merge order must keep `refactor/db` before `refactor/workflow`.

## Deliberately Deferred

- workflow controllers
- route registration
- approve/reject/cancel
- process start APIs
- Java delegate replacements
- business table mutations
- SSE notifications

## Next Step

Add a workflow public route change request only after read-only service behavior is validated in the merged integration branch.
