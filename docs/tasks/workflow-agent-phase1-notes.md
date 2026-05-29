# workflow-agent Phase 1 Notes

## What Was Analyzed

- Java `bizprocess` Controllers and Services.
- Java `userprocessconfig` module.
- Camunda BPMN files under `bpmn`.
- `oa2026.sql` workflow/process tables.

## Key Finding

The Java OA workflow module is not a simple CRUD module. It uses Camunda as the process runtime and combines:

- BPMN process definitions
- Runtime process instances and tasks
- Historic process/task/variable tables
- User-selected approvers and copy users
- Java delegate side effects after approval
- SSE notification refresh after start/approve/reject

## Current ThinkPHP Strategy Recommendation

For Phase 2, choose one of these strategies before writing code:

1. Build a lightweight PHP workflow runtime compatible with the existing process keys and key `act_*` tables.
2. Keep Camunda as an external workflow service and let ThinkPHP call it through API.
3. Implement a transitional approval runtime in ThinkPHP using new PHP services while preserving existing table reads for migrated data.

Recommended first implementation path:

- Keep existing `act_*` tables read-compatible.
- Implement PHP workflow services around process key, instance ID, task ID, variables, approval state, and assignee.
- Replace Java delegates with explicit PHP domain services, one process at a time.
- Start with read/list/detail/task approval skeleton before complex side effects.

## Do Not Implement Yet

This phase intentionally did not implement:

- Workflow routes.
- Workflow Controller.
- Workflow Service.
- Workflow Model.
- Workflow database changes.
- BPMN parser/runtime.
- Java delegate replacements.

## Public File Boundary

No locked public file was modified.

Locked files remain:

- `composer.json`
- `composer.lock`
- `config/app.php`
- `config/database.php`
- `route/app.php`
- `.env`
- `.env.example`
- `app/common.php`

If workflow-agent later needs routes or configuration, it must create a public file change request first.

## Dependencies On Other Agents

- auth-agent: current user ID, tenant ID, org ID, RBAC permission checks.
- user-agent: user, org tree, department lookup, approver validation.
- db-agent: base models and table field compatibility.
- api-agent: final route naming and response standard.
- frontend-agent: workflow form payload compatibility.
- test-agent: route/list/task approval regression checks.

## Phase 2 Proposed Goal

Create workflow design docs before code:

- `docs/tasks/workflow-runtime-design.md`
- `docs/tasks/workflow-api-map.md`
- `docs/tasks/workflow-side-effect-map.md`

Then wait for confirmation before adding PHP workflow runtime classes or routes.

## Important Reminder

The final system must also handle online real-time data synchronization after project completion. This requirement is not implemented in workflow-agent Phase 1, but it affects workflow tables because live process and task data may need synchronization.
