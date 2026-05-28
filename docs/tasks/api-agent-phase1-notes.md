# api-agent Phase 1 Notes

## Goal

Define API migration boundaries before writing ThinkPHP controllers.

## Confirmed Boundaries

- auth-agent has already implemented B-side login/token/RBAC/menu foundations.
- user-agent is preparing user, organization, position, and user center behavior.
- workflow-agent is analyzing approval/process behavior.
- db-agent owns database mapping and model foundations.
- api-agent should adapt controller requests/responses after module service boundaries are stable.

## First Safe Controller Queue

The first practical API migration queue should be:

1. Read-only user organization endpoints after user-agent service boundaries are ready.
2. Auth compatibility routes already handled by auth-agent, no duplicate work.
3. Workflow read-only process metadata after workflow-agent confirms engine direction.
4. Business CRUD endpoints only after db-agent model coverage and module ownership are confirmed.

## Deferred Areas

- Import/export endpoints.
- File upload/download.
- SSE and web push.
- Scheduled job management.
- Code generator endpoints.
- Tenant administration.
- Mobile-specific resource APIs.

## Final Merge Requirement

All api-agent commits must eventually merge into `refactor/thinkphp-main` through the merge-agent after `refactor/db`, `refactor/auth`, `refactor/user`, and `refactor/workflow` are stable.
