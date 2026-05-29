<?php

namespace app\service\auth;

class PasswordService
{
    public function __construct(private readonly Sm2Decryptor $sm2Decryptor = new Sm2Decryptor())
    {
    }

    public function verify(string $inputPassword, string $storedPassword): bool
    {
        $storedPassword = trim($storedPassword);
        if ($storedPassword === '' || $inputPassword === '') {
            return false;
        }

        if ($this->verifySm3($inputPassword, $storedPassword)) {
            return true;
        }

        if ($this->verifyPasswordHash($inputPassword, $storedPassword)) {
            return true;
        }

        return hash_equals(strtolower($storedPassword), hash('sha256', $inputPassword));
    }

    public function looksLikeSm2Ciphertext(string $inputPassword): bool
    {
        return $this->sm2Decryptor->isCiphertext($inputPassword);
    }

    public function decodeTransportPassword(string $inputPassword): ?string
    {
        if (!$this->looksLikeSm2Ciphertext($inputPassword)) {
            return $inputPassword;
        }

        return $this->sm2Decryptor->decrypt($inputPassword);
    }

    private function verifySm3(string $inputPassword, string $storedPassword): bool
    {
        if (preg_match('/^[0-9a-f]{64}$/i', $storedPassword) !== 1) {
            return false;
        }

        return hash_equals(strtolower($storedPassword), Sm3Hasher::hash($inputPassword));
    }

    private function verifyPasswordHash(string $inputPassword, string $storedPassword): bool
    {
        $info = password_get_info($storedPassword);
        if (($info['algo'] ?? null) === null || ($info['algo'] ?? null) === 0) {
            return false;
        }

        return password_verify($inputPassword, $storedPassword);
    }
}
