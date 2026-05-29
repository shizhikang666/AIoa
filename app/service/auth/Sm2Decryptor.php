<?php

declare(strict_types=1);

namespace app\service\auth;

/**
 * Minimal SM2 decryptor for sm-crypto C1C3C2 transport ciphertext.
 *
 * Private key material must be supplied from the runtime environment.
 */
class Sm2Decryptor
{
    private const P = 'FFFFFFFEFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF00000000FFFFFFFFFFFFFFFF';
    private const A = 'FFFFFFFEFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF00000000FFFFFFFFFFFFFFFC';

    public function decrypt(string $ciphertext, ?string $privateKey = null, int $cipherMode = 1): ?string
    {
        if (!extension_loaded('bcmath')) {
            return null;
        }

        bcscale(0);

        $privateKey = $this->normalizeHex($privateKey ?? (string)env('AUTH_SM2_PRIVATE_KEY', ''));
        if ($privateKey === '') {
            return null;
        }

        $ciphertext = $this->normalizeHex($ciphertext);
        if ($ciphertext === '') {
            return null;
        }

        if (str_starts_with($ciphertext, '04') && strlen($ciphertext) >= 194) {
            $ciphertext = substr($ciphertext, 2);
        }

        if (strlen($ciphertext) < 194 || strlen($ciphertext) % 2 !== 0) {
            return null;
        }

        $c1 = substr($ciphertext, 0, 128);
        if ($cipherMode === 0) {
            $c2 = substr($ciphertext, 128, -64);
            $c3 = substr($ciphertext, -64);
        } else {
            $c3 = substr($ciphertext, 128, 64);
            $c2 = substr($ciphertext, 192);
        }

        if ($c2 === '' || $c3 === '') {
            return null;
        }

        $point = [
            'x' => $this->hexToDec(substr($c1, 0, 64)),
            'y' => $this->hexToDec(substr($c1, 64, 64)),
        ];

        $sharedPoint = $this->multiply($this->hexToDec($privateKey), $point);
        if ($sharedPoint === null) {
            return null;
        }

        $x2 = $this->padHex($this->decToHex($sharedPoint['x']), 64);
        $y2 = $this->padHex($this->decToHex($sharedPoint['y']), 64);
        $keyStream = $this->kdf($x2 . $y2, intdiv(strlen($c2), 2));
        if ($keyStream === '' || preg_match('/^0+$/', $keyStream) === 1) {
            return null;
        }

        $plainHex = $this->xorHex($c2, $keyStream);
        $plainBytes = hex2bin($plainHex);
        if ($plainBytes === false) {
            return null;
        }

        $expectedC3 = Sm3Hasher::hash(hex2bin($x2) . $plainBytes . hex2bin($y2));
        if (!hash_equals(strtolower($c3), strtolower($expectedC3))) {
            return null;
        }

        return $plainBytes;
    }

    public function isCiphertext(string $value): bool
    {
        $value = $this->normalizeHex($value);

        return strlen($value) >= 194 && strlen($value) % 2 === 0;
    }

    private function normalizeHex(string $value): string
    {
        $value = strtolower(trim($value));
        if (str_starts_with($value, '0x')) {
            $value = substr($value, 2);
        }

        return preg_match('/^[0-9a-f]+$/', $value) === 1 ? $value : '';
    }

    /**
     * @param array{x: string, y: string} $point
     * @return array{x: string, y: string}|null
     */
    private function multiply(string $scalar, array $point): ?array
    {
        $scalarHex = $this->decToHex($scalar);
        $result = null;

        foreach (str_split($scalarHex) as $hexDigit) {
            $bits = str_pad(decbin(hexdec($hexDigit)), 4, '0', STR_PAD_LEFT);
            foreach (str_split($bits) as $bit) {
                $result = $this->double($result);
                if ($bit === '1') {
                    $result = $this->add($result, $point);
                }
            }
        }

        return $result;
    }

    /**
     * @param array{x: string, y: string}|null $point
     * @return array{x: string, y: string}|null
     */
    private function double(?array $point): ?array
    {
        if ($point === null || $point['y'] === '0') {
            return null;
        }

        $p = $this->hexToDec(self::P);
        $a = $this->hexToDec(self::A);
        $numerator = $this->mod($this->addDec($this->mulDec('3', $this->mulDec($point['x'], $point['x'])), $a), $p);
        $denominator = $this->mod($this->mulDec('2', $point['y']), $p);
        $lambda = $this->mod($this->mulDec($numerator, $this->inverse($denominator, $p)), $p);
        $x3 = $this->mod($this->subDec($this->subDec($this->mulDec($lambda, $lambda), $point['x']), $point['x']), $p);
        $y3 = $this->mod($this->subDec($this->mulDec($lambda, $this->subDec($point['x'], $x3)), $point['y']), $p);

        return ['x' => $x3, 'y' => $y3];
    }

    /**
     * @param array{x: string, y: string}|null $left
     * @param array{x: string, y: string}|null $right
     * @return array{x: string, y: string}|null
     */
    private function add(?array $left, ?array $right): ?array
    {
        if ($left === null) {
            return $right;
        }

        if ($right === null) {
            return $left;
        }

        $p = $this->hexToDec(self::P);
        if ($left['x'] === $right['x']) {
            if ($this->mod($this->addDec($left['y'], $right['y']), $p) === '0') {
                return null;
            }

            return $this->double($left);
        }

        $numerator = $this->mod($this->subDec($right['y'], $left['y']), $p);
        $denominator = $this->mod($this->subDec($right['x'], $left['x']), $p);
        $lambda = $this->mod($this->mulDec($numerator, $this->inverse($denominator, $p)), $p);
        $x3 = $this->mod($this->subDec($this->subDec($this->mulDec($lambda, $lambda), $left['x']), $right['x']), $p);
        $y3 = $this->mod($this->subDec($this->mulDec($lambda, $this->subDec($left['x'], $x3)), $left['y']), $p);

        return ['x' => $x3, 'y' => $y3];
    }

    private function inverse(string $value, string $modulus): string
    {
        $value = $this->mod($value, $modulus);
        $oldR = $modulus;
        $r = $value;
        $oldS = '0';
        $s = '1';

        while ($r !== '0') {
            $quotient = bcdiv($oldR, $r);
            [$oldR, $r] = [$r, $this->subDec($oldR, $this->mulDec($quotient, $r))];
            [$oldS, $s] = [$s, $this->subDec($oldS, $this->mulDec($quotient, $s))];
        }

        return $this->mod($oldS, $modulus);
    }

    private function kdf(string $zHex, int $length): string
    {
        $result = '';
        $counter = 1;
        while (strlen($result) < $length * 2) {
            $counterHex = str_pad(dechex($counter), 8, '0', STR_PAD_LEFT);
            $bytes = hex2bin($zHex . $counterHex);
            if ($bytes === false) {
                return '';
            }

            $result .= Sm3Hasher::hash($bytes);
            $counter++;
        }

        return substr($result, 0, $length * 2);
    }

    private function xorHex(string $left, string $right): string
    {
        $result = '';
        $length = min(strlen($left), strlen($right));
        for ($i = 0; $i < $length; $i += 2) {
            $result .= str_pad(dechex(hexdec(substr($left, $i, 2)) ^ hexdec(substr($right, $i, 2))), 2, '0', STR_PAD_LEFT);
        }

        return $result;
    }

    private function hexToDec(string $hex): string
    {
        $decimal = '0';
        foreach (str_split(strtolower($hex)) as $char) {
            $decimal = bcadd(bcmul($decimal, '16'), (string)hexdec($char));
        }

        return $decimal;
    }

    private function decToHex(string $decimal): string
    {
        if ($decimal === '0') {
            return '0';
        }

        $hex = '';
        while (bccomp($decimal, '0') > 0) {
            $remainder = (int)bcmod($decimal, '16');
            $hex = dechex($remainder) . $hex;
            $decimal = bcdiv($decimal, '16');
        }

        return $hex;
    }

    private function padHex(string $hex, int $length): string
    {
        return str_pad($hex, $length, '0', STR_PAD_LEFT);
    }

    private function mod(string $value, string $modulus): string
    {
        $result = bcmod($value, $modulus);
        if (bccomp($result, '0') < 0) {
            $result = bcadd($result, $modulus);
        }

        return $result;
    }

    private function addDec(string ...$values): string
    {
        $result = '0';
        foreach ($values as $value) {
            $result = bcadd($result, $value);
        }

        return $result;
    }

    private function subDec(string $left, string $right): string
    {
        return bcsub($left, $right);
    }

    private function mulDec(string $left, string $right): string
    {
        return bcmul($left, $right);
    }
}
