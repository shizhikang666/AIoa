# Java OA To ThinkPHP Refactor Progress Dashboard

Last updated: 2026-06-01 10:30 +08:00

Agent: merge-agent / main control agent

## Current Estimate

Overall production-ready completion: **45%**

Read-only API compatibility completion: **65%**

This estimate uses the final goal as the denominator: one complete runnable ThinkPHP OA system with login, RBAC, user/org, workflow, business APIs, frontend adaptation, tests, deployment, and final production data sync.

## Current Evidence

| Metric | Current Value | Notes |
| --- | ---: | --- |
| SQL tables analyzed/imported | 121 | From `F:\AI\projects\testJava\OA\oa2026.sql` |
| ThinkPHP Models | 67 | Database/model foundation is mostly in place |
| ThinkPHP Controllers | 52 | Includes auth, sys, dev, biz, mobile, gen, tenant, workflow read adapters |
| ThinkPHP Services | 46 | Includes auth/RBAC, user directory, workflow reads, and business read-only services |
| Registered route entries | 179 | Most are protected read-only compatibility routes |
| API compatibility docs | 38 | Stored under `docs/api` |
| Database docs | 10 | Stored under `docs/database` |
| Java Controllers in original project | 84 | Read-only reference baseline |
| Frontend API files in original project | 76 | Remaining gap driver for endpoint coverage |
| Frontend baseline files copied | 908 | Copied into `snowy-admin-web`; generated/cache files excluded |
| Current branch | `refactor/thinkphp-main` | Clean and synced with origin at last check |
| Latest business-slice commit | `f02aa9e` | `merge-agent: add readonly return order routes` |

## Module Progress

| Area | Completion | Status | Done | Remaining |
| --- | ---: | --- | --- | --- |
| Project engineering setup | 95% | Green | Git, remote sync, worktree plan, docs, AGENTS rules, baseline commands | Keep docs updated after each slice |
| Database and Models | 85% | Green | 121-table SQL reference used; 67 passive Models; field/relation/index docs | Low-priority tables, model relation methods where needed, final migration review |
| Auth / Token / RBAC / Menu | 70% | Yellow | Login, password compatibility base, token middleware, RBAC/menu/session reads, protected routes | Refresh/session hardening, fine-grained permission enforcement, data-scope expansion |
| User / Org / Position | 60% | Yellow | Read-only org tree, position, user directory, user-center selectors | User CRUD, grants, upload/avatar, import/export, encrypted profile fields |
| Workflow | 35% | Yellow | Runtime strategy, read-only task/process routes, variable normalization | Approval/reject/cancel/start, side effects, workflow write runtime |
| Dev/System/Mobile/Gen/Tenant reads | 65% | Yellow | Many read-only management endpoints added and routed | Write routes, provider actions, code generation, scheduler execution are intentionally deferred |
| Business read-only APIs | 55% | Yellow | Product, supplier, warehouse, inventory, delivery, purchase, settlement, payment, expenditure, collection, debit, file relation, team project, return order | Sale project, customer, invoice/invoicing, reissue, follow-up, rating, remaining selectors and detail consumers |
| Business write APIs | 10% | Red | Mostly deferred by design | Add/edit/delete/audit/status/stock/payment/refund flows with transactions and side effects |
| Frontend adaptation | 42% | Yellow | Original Vue project copied into target repo; request prefix, Bearer token, upload/SSE token headers, local SM2 fallback, double-prefix fix, and menu leaf handling adapted; browser smoke reaches `/sys/org` and `/sys/user` | API gap map, org/user field display alignment, missing SSE route, broken API method cleanup |
| Testing / QA | 40% | Yellow | Composer, `php think`, route list, PHP lint, smoke tests per slice | Automated route/API test suite, regression matrix, frontend smoke, negative tests |
| Deployment | 15% | Red | Local MySQL/Redis startup method known; env is local | Production config, queue/runtime/log permissions, Nginx/PHP deployment checks |
| Final online data sync | 0% | Red | Requirement recorded as final-stage reminder | Must design and confirm after project completion; do not start early |

## Completed Compatibility Slices

| Slice | Current Capability | Write Behavior |
| --- | --- | --- |
| Auth | Login, logout, current user, token, RBAC/menu reads | Partial; password-safe endpoint only |
| User directory | Org/user/position selectors and read-only directory | Deferred |
| Workflow reads | Task count/list/page/history, process page/detail/variable reads | Deferred |
| System/dev/mobile/gen reads | Config, dict, file, email/sms records, job metadata, logs, messages, monitor, resource/menu/mobile resource, gen metadata, tenant reads | Deferred |
| Business reads | Product, supplier, settlement account, payment record, expenditure record, collection receipt, debit note, file relation, team project, task/comments, warehouses, inventory, delivery, purchase order, return order | Deferred |

## Remaining High-Level Plan

| Phase | Planned Dates | Target | Deliverables | Exit Criteria |
| --- | --- | --- | --- | --- |
| 1. Progress audit and gap map | 2026-06-01 to 2026-06-02 | Freeze current gap list | `docs/tasks/api-gap-map.md`, updated dashboard | Remaining frontend API files mapped to implemented/deferred |
| 2. Remaining read-only business APIs | 2026-06-03 to 2026-06-07 | Finish safe GET/list/detail coverage | Sale project reads, customer strategy, invoice/invoicing/reissue/follow-up/rating reads | Main old frontend pages can load read-only data |
| 3. Data-scope and permission tightening | 2026-06-08 to 2026-06-10 | Align RBAC/data scope closer to Java | Data-scope org expansion, permission middleware checks where safe | Protected reads match expected user/org access |
| 4. Low-risk write endpoints | 2026-06-11 to 2026-06-15 | Add isolated CRUD without heavy side effects | Master-data writes for selected modules after review | Transactional writes smoke tested; no stock/payment/workflow side effects yet |
| 5. Business transactional writes | 2026-06-16 to 2026-06-22 | Implement side-effect-heavy writes | Purchase, inventory, delivery, settlement, payment/refund/status flows | Transactions, rollback tests, state updates, event replacement notes |
| 6. Workflow write runtime | 2026-06-23 to 2026-06-28 | Implement approval actions | Start/approve/reject/cancel and business side-effect hooks | Workflow smoke passes on imported data |
| 7. Frontend adaptation | Starts now as a parallel track; deeper work 2026-06-29 to 2026-07-03 | Make Vue frontend work against ThinkPHP API | Baseline import, token/request/menu/permission adaptation, missing API wrappers, joint backend/frontend smoke | Main workflows usable from browser |
| 8. Test hardening | 2026-07-04 to 2026-07-07 | Stabilize full system | Route/API regression suite, syntax/namespace checks, negative auth tests | `composer install`, `php think`, `route:list`, lint, smoke all pass |
| 9. Deployment rehearsal | 2026-07-08 to 2026-07-10 | Prepare runtime deployment | Env checklist, Nginx/PHP/runtime permission notes, backup plan | Staging deployment checklist passes |
| 10. Final online data sync plan | After phase 9, with user confirmation | Sync production/online realtime data into final project | Data backup, sync direction, downtime/rollback plan | User confirms sync plan before any production data operation |

## Time Forecast

| Delivery Level | Estimated Remaining Time | Meaning |
| --- | --- | --- |
| Backend read-only demo | 5 to 7 working days | Login plus most pages can load read-only data |
| Internal test version | 12 to 18 working days | Core writes and workflow begin to work; frontend smoke starts |
| Production-ready candidate | 22 to 30 working days | Full regression, deployment rehearsal, and data-sync plan ready |

The estimate assumes continued small commits and local MySQL/Redis availability. Heavy workflow side effects, encrypted customer fields, and frontend mismatches are the largest uncertainty.

## Next Immediate Actions

1. Generate an API gap map from the copied frontend API files.
2. Align org/user response fields and dictionary labels for visible tables.
3. Review Java SSE implementation before adding `/dev/message/createSseConnect`.
4. Finish remaining safe read-only business endpoints before adding writes.
5. Document the customer encrypted-field strategy before customer and sale-project detail work.
6. Keep final production data sync deferred until the system is complete and the user confirms the sync plan.

## Frontend Joint Testing Rule

Frontend adaptation is now part of the active workflow, not a final-only task. Future backend slices should record whether they affect visible frontend pages. After the frontend baseline is imported, every completed route slice should be followed by a backend plus frontend smoke cycle:

1. Start MySQL/Redis.
2. Start ThinkPHP on port `82`.
3. Start Vue frontend on port `83`.
4. Browser-test login, menu loading, and the affected pages.
5. Record missing frontend calls in `docs/tasks/api-gap-map.md`.

Detailed workflow: `docs/tasks/frontend-joint-test-workflow.md`
