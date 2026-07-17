# Legacy Java SM4 Compatibility

## Scope

The migrated OA database keeps the Java OA ciphertext for these columns:

- `sys_user.ID_CARD_NUMBER`
- `sys_user.PHONE`
- `sys_user.EMERGENCY_PHONE`
- `client_user.ID_CARD_NUMBER`
- `client_user.PHONE`
- `client_user.EMERGENCY_PHONE`
- `customer.PHONE`
- `customer.DETAILS_ADDRESS`

The Java source requests CBC mode, but the deployed `sm-crypto` 0.3.2 JavaScript bridge passes an extra positional argument and discards the options object. The stored legacy format is therefore deterministic SM4-ECB with PKCS#5/PKCS#7 padding and lower-case hexadecimal output. This behavior was confirmed against the deployed JAR resource and read-only production database samples.

The PHP compatibility layer decrypts these values only at the application boundary and encrypts new or edited values in that same effective Java format. It does not bulk-convert sensitive data to plaintext.

## Runtime configuration

Production must provide this variable in the server-side ignored `.env` before the migrated database is enabled:

- `OA_LEGACY_SM4_KEY_HEX`

The value must contain exactly 32 hexadecimal characters. The actual value must be copied through the approved secret-handling channel and must never be added to source control, deployment logs, tickets, or command output. An IV is intentionally not configured because the deployed Java bridge never applied it to persisted data.

Missing or malformed configuration is rejected when a non-empty protected value is read, searched, or written. Malformed stored values, invalid PKCS#7 padding, invalid UTF-8 plaintext, nested ciphertext-like values, and values that already resemble legacy ciphertext on a write path are also rejected. There is no fallback that returns ciphertext as if it were plaintext. Suspected multi-layer or abnormal legacy rows must be isolated for explicit cleanup instead of being silently presented as plaintext.

## Search behavior

- User and customer phone filters use deterministic encryption and equality against the stored ciphertext.
- `client_user` currently has no PHP service write path, but its Java-protected columns are registered in the shared field map so a future client-user service cannot silently treat them as unprotected fields.
- Customer detailed-address substring filtering is performed only after tenant/data-scope filtering and in-process decryption. Plaintext search values are not placed in SQL `LIKE` expressions or logs.
- Duplicate-phone checks use deterministic encrypted equality.

## Verification

Run the dependency-free smoke test with the project PHP runtime:

```powershell
& 'E:\project\socket\AI\testPhp\files\tools\php\php.exe' scripts\legacy-sm4-smoke.php
```

The smoke covers an independently generated non-production SM4 block vector, the effective deployed Java bridge behavior, ECB/PKCS#7 roundtrip, deterministic lookup, wrong-key/malformed/missing-config refusal, double-encryption refusal, service-boundary mappings, and all protected table field maps.

An actual Java-produced sample may be verified locally without writing it to disk by setting process-only environment variables for the legacy ciphertext and the expected plaintext SHA-256 digest. The smoke reports only whether the sample was verified and never prints the sample, key, or plaintext.
