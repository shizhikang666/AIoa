# Workflow Runtime Design

## Decision

Use a transitional ThinkPHP workflow runtime that is read-compatible with existing Camunda `act_*` tables, then replace Java delegates with explicit PHP domain services one process at a time.

This means:

- Keep existing `act_*` table data readable for migrated/live history.
- Do not try to execute Java delegates in PHP.
- Do not embed a full BPMN engine in Phase 2.
- Start with listing, detail, task count, pending task page, and history page.
- Implement approve/reject only after auth, user, and process variable mapping are merged and tested.

## Rejected Options For Now

### Direct Camunda Runtime In PHP

Rejected for now because the Java project uses Camunda engine classes, Java delegates, identity service, form service, runtime service, task service, and history service. PHP cannot directly run those delegates.

### External Camunda Service

Deferred because it requires service deployment, network/API design, auth bridging, and operating a second runtime. It may still be useful later if strict BPMN execution compatibility becomes mandatory.

### New Unrelated PHP Workflow Schema

Rejected for now because `oa2026.sql` already contains process/task/history data, and the final system must preserve migrated data compatibility.

## Minimal Runtime Concepts

The ThinkPHP workflow layer should model:

- process key
- process instance ID
- task ID
- initiator
- assignee
- tenant ID
- organization ID
- variables
- task state
- process status
- approval opinion
- copy-to users

## Suggested Service Boundaries

- `WorkflowQueryService`: process and task list/detail reads.
- `WorkflowVariableService`: normalize `act_ru_variable` and `act_hi_varinst`.
- `WorkflowTaskService`: approve/reject orchestration after later confirmation.
- `WorkflowConfigService`: user process config read/edit after user-agent boundaries settle.
- `WorkflowSideEffectDispatcher`: call explicit PHP side-effect services by process key and approval result.

## Phase Order

1. Read-only query services.
2. User process config read.
3. Task approve/reject skeleton without business side effects.
4. Side-effect dispatcher with one low-risk process.
5. Expand process-by-process after tests.

## Dependencies

- db-agent must provide model/table compatibility.
- auth-agent must provide current user, token, tenant, org, and permission checks.
- user-agent must provide user/org lookup and approver validation.
- api-agent must register routes only after public file change approval.
- test-agent must add route/task regression checks before merge.
