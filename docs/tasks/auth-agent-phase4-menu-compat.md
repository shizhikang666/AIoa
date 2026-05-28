# auth-agent Phase 4 Menu Compatibility

## Java Source Findings

- The old frontend stores the login token, calls `GET /auth/b/getLoginUser`, then calls `GET /sys/userCenter/loginMenu`.
- Java builds login menus from `SYS_USER_HAS_RESOURCE` and `SYS_ROLE_HAS_RESOURCE` relations.
- Java includes parent menus and the owning module, then returns a tree rooted at `parentId = 0`.
- Frontend routing expects each menu node to contain `id`, `parentId`, `path`, `name`, `component`, `children`, and `meta`.

## ThinkPHP Implementation

- Added `MenuService` under auth-agent because this data is RBAC/menu permission data.
- Added a compatibility controller for only `GET /sys/userCenter/loginMenu`.
- Added the minimum route in locked `route/app.php` after the user allowed the main agent to decide the next parallel plan.
- Did not implement user center profile, organization, position, workbench, process config, or message APIs.

## Merge Boundary

- user-agent should own the rest of `/sys/userCenter/*`.
- During merge, keep this route if user-agent does not implement `loginMenu`.
- If user-agent later implements a richer `loginMenu`, merge-agent should compare outputs and keep a single route.
