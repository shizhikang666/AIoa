<?php

namespace app\service\auth;

class Sm3Hasher
{
    private const IV = [
        0x7380166f,
        0x4914b2b9,
        0x172442d7,
        0xda8a0600,
        0xa96f30bc,
        0x163138aa,
        0xe38dee4d,
        0xb0fb0e4e,
    ];

    public static function hash(string $message): string
    {
        $message = self::pad($message);
        $vector = self::IV;
        $blockCount = intdiv(strlen($message), 64);

        for ($i = 0; $i < $blockCount; $i++) {
            $block = substr($message, $i * 64, 64);
            $vector = self::compress($vector, $block);
        }

        return implode('', array_map(static fn (int $value): string => sprintf('%08x', $value), $vector));
    }

    private static function pad(string $message): string
    {
        $length = strlen($message);
        $bitLength = $length * 8;
        $message .= "\x80";

        $paddingLength = (56 - (strlen($message) % 64) + 64) % 64;
        if ($paddingLength > 0) {
            $message .= str_repeat("\x00", $paddingLength);
        }

        $high = intdiv($bitLength, 0x100000000);
        $low = $bitLength & 0xffffffff;

        return $message . pack('N2', $high, $low);
    }

    /**
     * @param array<int, int> $vector
     * @return array<int, int>
     */
    private static function compress(array $vector, string $block): array
    {
        $words = array_values(unpack('N16', $block));

        for ($j = 16; $j < 68; $j++) {
            $words[$j] = self::p1($words[$j - 16] ^ $words[$j - 9] ^ self::rotl($words[$j - 3], 15))
                ^ self::rotl($words[$j - 13], 7)
                ^ $words[$j - 6];
            $words[$j] = self::u32($words[$j]);
        }

        $expanded = [];
        for ($j = 0; $j < 64; $j++) {
            $expanded[$j] = self::u32($words[$j] ^ $words[$j + 4]);
        }

        [$a, $b, $c, $d, $e, $f, $g, $h] = $vector;

        for ($j = 0; $j < 64; $j++) {
            $t = $j <= 15 ? 0x79cc4519 : 0x7a879d8a;
            $ss1 = self::rotl(self::add(self::rotl($a, 12), $e, self::rotl($t, $j % 32)), 7);
            $ss2 = self::u32($ss1 ^ self::rotl($a, 12));
            $tt1 = self::add(self::ff($j, $a, $b, $c), $d, $ss2, $expanded[$j]);
            $tt2 = self::add(self::gg($j, $e, $f, $g), $h, $ss1, $words[$j]);

            $d = $c;
            $c = self::rotl($b, 9);
            $b = $a;
            $a = $tt1;
            $h = $g;
            $g = self::rotl($f, 19);
            $f = $e;
            $e = self::p0($tt2);
        }

        return [
            self::u32($a ^ $vector[0]),
            self::u32($b ^ $vector[1]),
            self::u32($c ^ $vector[2]),
            self::u32($d ^ $vector[3]),
            self::u32($e ^ $vector[4]),
            self::u32($f ^ $vector[5]),
            self::u32($g ^ $vector[6]),
            self::u32($h ^ $vector[7]),
        ];
    }

    private static function ff(int $j, int $x, int $y, int $z): int
    {
        if ($j <= 15) {
            return self::u32($x ^ $y ^ $z);
        }

        return self::u32(($x & $y) | ($x & $z) | ($y & $z));
    }

    private static function gg(int $j, int $x, int $y, int $z): int
    {
        if ($j <= 15) {
            return self::u32($x ^ $y ^ $z);
        }

        return self::u32(($x & $y) | ((~$x & 0xffffffff) & $z));
    }

    private static function p0(int $x): int
    {
        return self::u32($x ^ self::rotl($x, 9) ^ self::rotl($x, 17));
    }

    private static function p1(int $x): int
    {
        return self::u32($x ^ self::rotl($x, 15) ^ self::rotl($x, 23));
    }

    private static function rotl(int $x, int $bits): int
    {
        $bits %= 32;
        $x = self::u32($x);

        return self::u32(($x << $bits) | ($x >> (32 - $bits)));
    }

    private static function add(int ...$values): int
    {
        $sum = 0;
        foreach ($values as $value) {
            $sum = self::u32($sum + $value);
        }

        return $sum;
    }

    private static function u32(int $value): int
    {
        return $value & 0xffffffff;
    }
}
