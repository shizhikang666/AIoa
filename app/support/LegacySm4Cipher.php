<?php

declare(strict_types=1);

namespace app\support;

use RuntimeException;
use UnexpectedValueException;

/**
 * Java OA compatible SM4 storage codec.
 *
 * The Java code requests CBC, but the deployed sm-crypto 0.3.2 JavaScript
 * bridge passes an extra positional argument and drops the options object.
 * The persisted format is therefore lower-case hexadecimal SM4-ECB with a
 * fixed 128-bit key and PKCS#7 padding. The key is loaded only from the
 * environment and is never logged or embedded in source control.
 */
final class LegacySm4Cipher
{
    public const KEY_ENV = 'OA_LEGACY_SM4_KEY_HEX';

    private const BLOCK_BYTES = 16;

    private readonly string $keyHex;

    public function __construct(?string $keyHex = null)
    {
        if (func_num_args() === 0) {
            $keyHex = $this->environment(self::KEY_ENV);
        }

        $this->keyHex = trim((string)$keyHex);
    }

    public function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return $plaintext;
        }
        if (self::looksLikeCiphertext($plaintext)) {
            throw new UnexpectedValueException('Refusing to encrypt a value that already has the legacy ciphertext shape');
        }
        if (preg_match('//u', $plaintext) !== 1) {
            throw new UnexpectedValueException('SM4 plaintext must be valid UTF-8');
        }

        $key = $this->material();
        $paddingLength = self::BLOCK_BYTES - (strlen($plaintext) % self::BLOCK_BYTES);
        $padded = $plaintext . str_repeat(chr($paddingLength), $paddingLength);
        $ciphertext = '';

        for ($offset = 0, $length = strlen($padded); $offset < $length; $offset += self::BLOCK_BYTES) {
            $ciphertext .= Sm4::encryptBlock($key, substr($padded, $offset, self::BLOCK_BYTES));
        }

        return strtolower(bin2hex($ciphertext));
    }

    public function decrypt(?string $ciphertextHex): ?string
    {
        if ($ciphertextHex === null || $ciphertextHex === '') {
            return $ciphertextHex;
        }
        if (!self::looksLikeCiphertext($ciphertextHex)) {
            throw new UnexpectedValueException('Stored SM4 value does not have the legacy ciphertext shape');
        }

        $key = $this->material();
        $ciphertext = hex2bin($ciphertextHex);
        if (!is_string($ciphertext)) {
            throw new UnexpectedValueException('Stored SM4 value is not valid hexadecimal ciphertext');
        }

        $padded = '';
        for ($offset = 0, $length = strlen($ciphertext); $offset < $length; $offset += self::BLOCK_BYTES) {
            $padded .= Sm4::decryptBlock($key, substr($ciphertext, $offset, self::BLOCK_BYTES));
        }

        $paddingLength = ord($padded[strlen($padded) - 1]);
        if ($paddingLength < 1 || $paddingLength > self::BLOCK_BYTES) {
            throw new UnexpectedValueException('SM4 ciphertext has invalid PKCS#7 padding');
        }
        $padding = substr($padded, -$paddingLength);
        if (!hash_equals(str_repeat(chr($paddingLength), $paddingLength), $padding)) {
            throw new UnexpectedValueException('SM4 ciphertext has invalid PKCS#7 padding');
        }

        $plaintext = substr($padded, 0, -$paddingLength);
        if (preg_match('//u', $plaintext) !== 1) {
            throw new UnexpectedValueException('SM4 plaintext is not valid UTF-8');
        }
        if ($plaintext !== '' && self::looksLikeCiphertext($plaintext)) {
            throw new UnexpectedValueException('SM4 value still resembles ciphertext after one legacy layer');
        }

        return $plaintext;
    }

    public function encryptForLookup(string $plaintext): string
    {
        $encrypted = $this->encrypt($plaintext);
        if (!is_string($encrypted) || $encrypted === '') {
            throw new UnexpectedValueException('SM4 lookup value must not be empty');
        }

        return $encrypted;
    }

    public static function looksLikeCiphertext(string $value): bool
    {
        $length = strlen($value);

        return $length >= 32 && $length % 32 === 0 && preg_match('/\A[0-9a-f]+\z/iD', $value) === 1;
    }

    private function material(): string
    {
        if (preg_match('/\A[0-9a-f]{32}\z/iD', $this->keyHex) !== 1) {
            throw new RuntimeException(self::KEY_ENV . ' must be configured as exactly 32 hexadecimal characters', 500);
        }

        $key = hex2bin($this->keyHex);
        if (!is_string($key)) {
            throw new RuntimeException('Legacy SM4 runtime configuration is invalid', 500);
        }

        return $key;
    }

    private function environment(string $name): string
    {
        if (function_exists('env')) {
            return (string)env($name, '');
        }

        $value = getenv($name);

        return is_string($value) ? $value : '';
    }
}
