# workflow-agent Status

## 2026-05-28 - workflow-agent - Phase 1 Started

### Completed Content

- Read `AGENTS.md`.
- Confirmed `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md` were missing and created them in the workflow worktree.
- Analyzed Java workflow/process source code in read-only mode.
- Analyzed BPMN process definitions.
- Analyzed workflow-related SQL tables in `oa2026.sql`.
- Generated workflow mapping and phase notes documents.

### Modified Files

- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`
- `docs/tasks/workflow-agent-java-map.md`
- `docs/tasks/workflow-agent-phase1-notes.md`
- `docs/tasks/workflow-table-map.md`

### Test Results

- `composer dump-autoload`: passed after running `composer install --no-interaction --prefer-dist` because the worktree vendor directory was incomplete.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed, current route baseline only contains `think` and `hello/<name>`.
- PHP lint for `app`, `config`, and `route`: passed.
- `git status --short --branch`: passed; only workflow-agent docs/status files are untracked before commit.
- `php think test`: not run because the current ThinkPHP console does not expose a `test` command.

### Current Problems

- Workflow runtime implementation needs a later decision: Camunda-compatible tables can be read, but Java delegates cannot run in PHP.
- Workflow side effects are spread across finance, sale project, warehouse, procurement, and leave modules.
- Public route/config files remain locked and were not modified.

### Next Plan

- Phase 2 should choose the ThinkPHP workflow runtime strategy before any Controller or Service implementation.

## 2026-05-29 - workflow-agent - Phase 2 Runtime Strategy

### Completed Content

- Documented the recommended workflow runtime strategy.
- Chose a transitional ThinkPHP runtime that keeps existing Camunda `act_*` tables read-compatible.
- Mapped first read-only workflow API batch, config batch, and deferred mutation batch.
- Mapped Java delegate side effects to future explicit PHP services.
- Kept Phase 2 documentation-only and did not modify routes, models, services, or Java source.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/workflow-runtime-design.md`
- `docs/tasks/workflow-api-map.md`
- `docs/tasks/workflow-side-effect-map.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed, current route baseline only contains `think` and `hello/<name>`.
- PHP lint for `app`, `config`, and `route`: passed.

### Current Problems

- Runtime workflow code is still blocked until db/auth/user foundations are merged and tested.
- Route registration still requires a public file change request before modifying `route/app.php`.
- Approval mutation and side effects remain high risk and must be implemented process by process.

### Next Plan

- Start workflow code with read-only query services only after db-agent model coverage for `act_*` tables is confirmed.
- Defer approve/reject and process start routes until test-agent has baseline route/task checks.

## 2026-05-29 - workflow-agent - Phase 3 Query Services

### Completed Content

- Added read-only workflow query services.
- Added variable normalization for runtime and historic Camunda variables.
- Covered pending task count/list/page, historic task page, started process page, and process detail query shape.
- Kept Phase 3 free of routes, controllers, approve/reject/cancel/start behavior, and business side effects.
- Documented dependency on db-agent `Act*` models.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/workflow/WorkflowVariableService.php`
- `app/service/workflow/WorkflowQueryService.php`
- `docs/tasks/workflow-query-services.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed, current route baseline only contains `think` and `hello/<name>`.
- PHP lint for `app`, `config`, and `route`: passed.

### Current Problems

- Runtime DB query testing must wait until `refactor/db` is merged before `refactor/workflow`.
- API routes are still not registered and require a public file change request.
- Mutation behavior and Java delegate replacement remain deferred.

### Next Plan

- Add workflow public route change request for the read-only API batch.
- After merged model/service validation, add thin controller adapters that call these services.
