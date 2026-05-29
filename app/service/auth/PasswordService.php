<?php

namespace app\service\auth;

class PasswordService
{
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
        $inputPassword = trim($inputPassword);

        return preg_match('/^[0-9a-f]{160,}$/i', $inputPassword) === 1;
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
