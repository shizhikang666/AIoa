# frontend-agent Phase 2 Contracts

## Goal

Document backend contracts prepared so far for future frontend adaptation.

## Backend Progress Reflected

- auth-agent now returns both `message` and Java-compatible `msg`.
- auth-agent owns `/sys/userCenter/loginMenu`.
- api-agent has prepared user directory Controller adapters.
- api-agent has prepared workflow read-only Controller adapters.
- Route registration for user directory and workflow read-only endpoints is still pending.

## Frontend Work Still Deferred

- Do not edit `F:\AI\projects\testJava\OA\snowy-admin-web` directly.
- Decide editable frontend target path before making code changes.
- Decide final token header migration strategy.
- Keep upload/download/SSE work deferred until backend modules are ready.

## Next Step

After merge-agent registers approved read-only routes, frontend-agent can compare the old API wrappers against `docs/frontend/backend-contract-map.md`.
