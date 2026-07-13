# test-agent Risk List

## Purpose

This file tracks testing risks that may appear as module branches are merged into `refactor/thinkphp-main`.

## Current Baseline Risks

| Area | Risk | Mitigation |
| --- | --- | --- |
| Routes | Multiple agents may add overlapping paths | Run `php think route:list` after each merge |
| Namespaces | Controller, service, or model namespaces may drift | Run PHP lint and targeted class loading checks |
| Composer | Dependency files are locked public files and should not drift across agents | Require change request before editing Composer files |
| Database | Models may depend on tables not present in current local database | Keep db-agent table map as the source of truth |
| Auth | Token and RBAC services may require Redis/database runtime config | Test bootstrap without requiring live secrets |
| User module | User and auth modules may both touch login-user related endpoints | Assign endpoint ownership before implementation |
| Workflow | Workflow relations may depend on user/org models | Merge after db/auth/user foundations |
| Frontend | Old frontend may expect Java API response/path shapes | Keep API mapping and compatibility tests |
| Docs | Docs can become stale after code branches merge | Update docs during merge-agent review |

## Merge Checkpoints

1. After merging `refactor/db`, run Composer, ThinkPHP console, route list, and PHP lint.
2. After merging `refactor/auth`, verify login/auth routes and no committed secrets.
3. After merging `refactor/user`, verify organization/user route collisions and relation naming.
4. After merging `refactor/workflow`, verify workflow dependencies and namespace loading.
5. After merging `refactor/api`, verify route collisions and response format consistency.
6. After merging `refactor/frontend`, verify API path compatibility and Token header convention.
7. After merging `refactor/test`, verify test docs and scripts match the final merged structure.
8. After merging `refactor/docs`, verify deployment and API documentation is current.

## Stop Conditions

- A test requires editing a locked public file without approval.
- A test failure indicates missing or deleted database fields.
- A namespace or route conflict cannot be resolved inside test-agent scope.
- A branch merge requires business code changes.
- A secret, private key, token, or password is found in committed code.

