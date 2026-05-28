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

Waiting for user confirmation before editing `route/app.php`.
