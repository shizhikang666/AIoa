# auth-agent Phase 2 Notes

## Implemented Scope

- Added Java-compatible B-side auth routes.
- Added account/password login skeleton.
- Added bearer Token creation, lookup, and revocation using ThinkPHP Cache facade with `oa:auth:` key prefix.
- Added RBAC context assembly from `sys_relation`, `sys_role`, `sys_resource`, and `mobile_resource`.
- Added current-login-user endpoint that strips `PASSWORD`.
- Added auth middleware for future protected routes.

## Compatibility Notes

- Java uses Sa-Token. ThinkPHP phase 2 uses an internal TokenService with Redis-compatible key names.
- Java stores captcha values in Redis; ThinkPHP phase 2 stores captcha values through ThinkPHP Cache facade with `oa:auth:captcha:` keys.
- Java password login uses SM2 decrypt plus SM3 hash. Phase 2 password verification supports direct stored-hash comparison, PHP `password_hash`, and SHA-256 fallback only. Full SM2/SM3 compatibility still needs a dedicated compatibility slice.
- Phone-code login and web push subscription are deferred.

## Public File Change

`route/app.php` was changed after the user continued from the submitted public file change request. Only auth-scoped routes were added.

## Deferred Work

- Configure Redis store through environment/deployment policy without committing credentials.
- Implement SM2/SM3 Java password compatibility.
- Add integration tests once database and Redis test fixtures are available.
- Add menu tree shaping after frontend-agent confirms the required frontend route schema.
