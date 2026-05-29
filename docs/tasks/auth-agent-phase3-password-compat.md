# auth-agent Phase 3 Password Compatibility

## Java Source Findings

- Java login encrypts the submitted password in the frontend with SM2.
- Java backend decrypts the SM2 ciphertext, then stores and compares `CommonCryptogramUtil.doHashValue()` output.
- `CommonCryptogramUtil.doHashValue()` delegates to SM3.
- Safe-password verification uses the same decrypt-then-SM3 comparison before opening a short safe window.
- The updated SQL source `F:\AI\projects\testJava\OA\oa2026.sql` stores 64-character SM3 password hashes in `sys_user.PASSWORD`.

## ThinkPHP Phase 3 Implementation

- Added a pure PHP SM3 hasher so imported Java password hashes can be checked without adding a Composer dependency.
- Added `PasswordService` to centralize password verification.
- Updated login password checks to compare plaintext input by SM3 against `sys_user.PASSWORD`.
- Updated safe-password verification to check the current user's password before writing the `oa:auth:safe:` cache marker.
- Removed direct stored-hash equality from login verification to avoid pass-the-hash behavior.

## Compatibility Boundary

- No SM2 private key is committed.
- Existing Java frontend SM2 ciphertext is detected and rejected with a clear compatibility-adapter message for now.
- Full old-frontend compatibility must be solved by either:
  - a reviewed SM2 decrypt adapter that loads private key material from a secure environment source, or
  - frontend-agent adapting ThinkPHP login submission over HTTPS so the backend can SM3-hash the plaintext password.

## Verification Values

- SM3 test vector `abc` must hash to `66c7f0f462eeedd9d1f2d46bdc10e4e24167c4875cf2f7a2297da02b8f4ba8e0`.
- SQL default password `123456` must hash to `207cf410532f92a47dee245ce9b11ff71f578ebd763eb3bbea44ebd043d018fb`.
