# Public File Change Request

Agent: auth-agent

## Requested File

- `F:\AI\projects\testJava\OA-auth\route\app.php`

## Reason

The Java OA auth module exposes fixed HTTP paths such as:

- `GET /auth/b/getPicCaptcha`
- `POST /auth/b/doLogin`
- `POST /auth/b/doLoginByPhone`
- `GET /auth/b/doLogout`
- `GET /auth/b/getLoginUser`
- `POST /auth/b/safe/password`

To keep frontend/API compatibility during the ThinkPHP refactor, auth-agent needs explicit ThinkPHP route definitions for these paths.

## Proposed Change

After confirmation, auth-agent will add only auth-scoped routes to `route/app.php`.

Expected route group:

```php
Route::group('auth/b', function () {
    Route::get('getPicCaptcha', 'auth.AuthController/getPicCaptcha');
    Route::post('doLogin', 'auth.AuthController/doLogin');
    Route::post('doLoginByPhone', 'auth.AuthController/doLoginByPhone');
    Route::get('doLogout', 'auth.AuthController/doLogout');
    Route::get('getLoginUser', 'auth.AuthController/getLoginUser');
    Route::post('safe/password', 'auth.AuthController/openSafe');
});
```

Final controller path may be adjusted to the actual ThinkPHP controller namespace chosen during implementation.

## Safety Constraints

- Do not alter existing demo routes unless explicitly requested.
- Do not add routes for user, workflow, api, or frontend modules.
- Do not modify `config/app.php`, `config/database.php`, `.env`, `.env.example`, or `app/common.php`.
- Do not write secrets or credentials.

## Status

Applied during auth-agent Phase 2 after the user continued from this request. The change added only the listed `/auth/b/*` routes.

## Additional Request: login menu compatibility route

### Requested File

- `F:\AI\projects\testJava\OA-auth\route\app.php`

### Reason

The old frontend calls `GET /sys/userCenter/loginMenu` immediately after login to load the authorized menu tree. The route path lives under user center, but the data is derived from auth/RBAC menu permissions.

This creates a module-boundary risk:

- auth-agent owns menus and permissions.
- user-agent owns user center APIs.
- `route/app.php` is locked by project rules.

### Proposed Options

1. auth-agent implements only the menu tree service and waits for user-agent/api-agent to expose `GET /sys/userCenter/loginMenu`.
2. auth-agent adds a compatibility route for `GET /sys/userCenter/loginMenu` that delegates to an auth-scoped menu controller.
3. Defer this route to merge-agent after auth-agent and user-agent are both committed.

### Safety Constraints

- Do not implement user profile, organization, position, workbench, or message APIs in auth-agent.
- Do not modify `route/app.php` for this route until the module ownership decision is confirmed.
- Do not modify Java source files.
- Do not modify database schema or seed data.

### Status

Waiting for confirmation on which option should own `GET /sys/userCenter/loginMenu`.
