# auth-agent Java Auth Map

Agent: auth-agent

Java source is read-only:

- `F:\AI\projects\testJava\OA`

## Java Endpoints

| Java path | Method | Java source | ThinkPHP scope |
| --- | --- | --- | --- |
| `/auth/b/getPicCaptcha` | GET | `AuthController.getPicCaptcha` | captcha generation and Redis-backed code storage |
| `/auth/b/getPhoneValidCode` | GET | `AuthController.getPhoneValidCode` | defer SMS implementation unless required |
| `/auth/b/doLogin` | POST | `AuthController.doLogin` | account/password/tenant login |
| `/auth/b/subscription` | POST | `AuthController.subscription` | defer web push subscription |
| `/auth/b/doLoginByPhone` | POST | `AuthController.doLoginByPhone` | defer phone-code login unless required |
| `/auth/b/doLogout` | GET | `AuthController.doLogout` | Token invalidation |
| `/auth/b/getLoginUser` | GET | `AuthController.getLoginUser` | return current login user without password/sensitive fields |
| `/auth/b/safe/password` | POST | `AuthController.openSafe` | second verification, defer if password compatibility is unresolved |

## Java Login Flow

Observed in `AuthServiceImpl`:

1. Optional captcha validation through Redis key prefix `auth-validCode:`.
2. Login error counter through Redis key prefix `login-error-times:`.
3. Tenant validation before password verification.
4. Password arrives encrypted, then Java decrypts and hashes before comparing against stored `SYS_USER.PASSWORD`.
5. Login creates a Sa-Token session.
6. Login user payload includes role ids, role codes, button codes, mobile button codes, data scopes, permission codes, and tenant id.
7. Login user session data removes password before returning current user.

## ThinkPHP Auth Plan

Initial ThinkPHP implementation should preserve the behavior shape but keep risky compatibility areas isolated:

- `AuthController`: endpoint adapter only.
- `AuthService`: login, logout, current user, permission payload assembly.
- `TokenService`: generate, persist, read, and revoke bearer tokens using Redis-compatible storage.
- `RbacService`: load roles, resources, button codes, permission codes, and menu ids from db-agent tables.
- `AuthMiddleware`: validate `Authorization: Bearer <token>`.

## Dependency On db-agent

auth-agent depends on the following db-agent Models after final merge:

- `SysUser`
- `SysRole`
- `SysResource`
- `SysRelation`
- `Tenant`
- `AuthThirdUser`
- `ClientUser`
- `ClientRelation`
- `MobileResource`

auth-agent branch may not contain these Model files until final merge. Auth code should be designed to merge after `refactor/db`, following the configured merge order.

## Deferred Items

- SMS sending and phone-code login can be stubbed or deferred unless the user asks for it now.
- Web push subscription can be deferred.
- Full SM2 password compatibility needs a dedicated password compatibility note before production login is considered complete.
- Production online realtime data synchronization is a final-stage requirement and must not start during auth-agent.
