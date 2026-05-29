# Auth SM2 Compatibility

Date: 2026-05-29

Agent: merge-agent

## Goal

Allow the ThinkPHP auth module to support the legacy Vue login password transport format without committing private key material.

## Java And Frontend Behavior

- The old Vue login page encrypts the plaintext password with SM2 using `sm-crypto`.
- The frontend cipher mode is `1`, which is C1C3C2.
- Java decrypts the submitted ciphertext and then compares the SM3 hash with `sys_user.PASSWORD`.

## ThinkPHP Behavior

- Plaintext local login remains supported for smoke testing and backend API clients.
- SM2-looking ciphertext is detected by `PasswordService`.
- `Sm2Decryptor` attempts to decrypt the ciphertext only when `AUTH_SM2_PRIVATE_KEY` is configured in the runtime environment.
- The private key must stay in local or deployment secrets and must not be committed.
- If the key is missing or decryption fails, login returns a clear compatibility configuration error instead of falling back to unsafe behavior.

## Runtime Configuration

Optional local-only key:

```dotenv
AUTH_SM2_PRIVATE_KEY=<local-only-64-hex-private-key>
```

Do not put this value in tracked docs, `.env.example`, code, logs, or commit messages.

## Deferred

- Browser login verification with the old Vue frontend against the ThinkPHP backend.
- Rotating the legacy Java key pair before production.
- Deciding whether to keep SM2 transport long-term or replace it with HTTPS-only plaintext submission plus server-side SM3 hashing.
