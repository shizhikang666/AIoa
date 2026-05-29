# workflow-agent Plans

## Active Plan: Phase 1 - Workflow Analysis And Planning

Date: 2026-05-28

### 1. Current Goal

Start workflow-agent first phase for the Java OA to ThinkPHP refactor.

This phase only analyzes and plans the workflow module. It does not implement business Controller, Service, route, Model, or database changes.

### 2. Module Scope

- Approval flows
- Process definitions
- Process instances
- Approval task records
- Copy-to records
- User workflow configuration

### 3. Files To Modify

Only workflow-agent worktree files:

- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`
- `docs/tasks/workflow-agent-java-map.md`
- `docs/tasks/workflow-agent-phase1-notes.md`
- `docs/tasks/workflow-table-map.md`

### 4. Java Read-Only Inputs

- `F:\AI\projects\testJava\OA\bpmn`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizprocess`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-sys\src\main\java\vip\xiaonuo\sys\modular\userprocessconfig`
- `F:\AI\projects\testJava\OA\oa2026.sql`

### 5. Risks

- Java project uses Camunda engine APIs and `act_*` tables; ThinkPHP needs a later design decision before runtime workflow implementation.
- BPMN files contain Java delegate class names; direct execution in PHP is not possible without replacing delegate behavior.
- Workflow tables share business side effects with finance, sale project, warehouse, procurement, and leave modules.
- Some Java source comments are mojibake, so analysis should rely on class names, route paths, enum values, BPMN IDs, SQL comments, and method behavior.

### 6. Test Commands

```powershell
git status --short --branch
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

If the ThinkPHP console later exposes a test command:

```powershell
php think test
```

### 7. Acceptance Criteria

- Workflow Java routes, services, delegates, BPMN files, and SQL tables are mapped in docs.
- No Java source files are modified.
- No locked public files are modified.
- No ThinkPHP business code is generated.
- Git commit is created with message `workflow-agent: add workflow analysis plan`.

### 8. Forbidden Scope

- Do not modify `F:\AI\projects\testJava\OA`.
- Do not modify `composer.json`, `composer.lock`, `config/app.php`, `config/database.php`, `route/app.php`, `.env`, `.env.example`, or `app/common.php`.
- Do not modify auth-agent or user-agent paths.
- Do not implement workflow Controller, Service, Model, or route in this phase.

## Active Plan: Phase 2 - Workflow Runtime Strategy

Date: 2026-05-29

### 1. Current Goal

Choose and document the safest ThinkPHP workflow runtime strategy before implementing workflow business code.

### 2. Module Scope

- Workflow runtime migration strategy
- Workflow API migration order
- Approval side-effect replacement map
- Existing `act_*` table compatibility

### 3. Files To Modify

Only workflow-agent worktree files:

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/workflow-runtime-design.md`
- `docs/tasks/workflow-api-map.md`
- `docs/tasks/workflow-side-effect-map.md`

### 4. Risks

- Direct Camunda execution is Java-specific and cannot be run inside ThinkPHP.
- Full workflow runtime implementation is high risk without merged auth/user/db foundations.
- Delegate side effects touch sales, finance, warehouse, procurement, and personnel modules.

### 5. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

### 6. Acceptance Criteria

- A workflow runtime strategy is documented.
- API migration batches are documented.
- Java delegate side effects are mapped to future PHP services.
- No Java source files are modified.
- No ThinkPHP workflow code, routes, models, or database changes are added.

## Current Plan: Phase 3 - Read-Only Workflow Query Services

Date: 2026-05-29

### 1. Current Goal

Add read-only workflow query service classes for pending tasks, history tasks, started processes, process detail, and variable normalization.

### 2. Module Scope

- Runtime task reads.
- Historic task reads.
- Historic process instance reads.
- Historic activity and comment reads.
- Runtime/history variable normalization.

### 3. Files To Modify

Only workflow-agent worktree files:

- `PLANS.md`
- `STATUS.md`
- `app/service/workflow/WorkflowVariableService.php`
- `app/service/workflow/WorkflowQueryService.php`
- `docs/tasks/workflow-query-services.md`

### 4. Risks

- The current workflow branch does not yet contain db-agent `Act*` models; final merged branch must merge `refactor/db` first.
- Query services are read-only and intentionally do not mutate workflow state.
- Process variable value compatibility needs real data tests after database configuration exists.

### 5. Forbidden Scope

- Do not modify Java source files.
- Do not modify locked public files.
- Do not add routes or controllers.
- Do not implement approve, reject, cancel, process start, or side effects.

### 6. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```
