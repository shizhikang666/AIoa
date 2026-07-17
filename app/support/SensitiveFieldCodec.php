<?php

declare(strict_types=1);

namespace app\support;

use InvalidArgumentException;

/**
 * Explicit field map for Java OA columns protected by CommonSm4CbcTypeHandler.
 */
final class SensitiveFieldCodec
{
    private const FIELD_MAP = [
        'sys_user' => ['ID_CARD_NUMBER', 'PHONE', 'EMERGENCY_PHONE'],
        'client_user' => ['ID_CARD_NUMBER', 'PHONE', 'EMERGENCY_PHONE'],
        'customer' => ['PHONE', 'DETAILS_ADDRESS'],
    ];

    public function __construct(private readonly LegacySm4Cipher $cipher = new LegacySm4Cipher())
    {
    }

    /**
     * @return array<int, string>
     */
    public function fieldsFor(string $table): array
    {
        return self::FIELD_MAP[strtolower($table)] ?? [];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function encodeRow(string $table, array $row): array
    {
        foreach ($this->fieldsFor($table) as $column) {
            if (!array_key_exists($column, $row)) {
                continue;
            }

            $value = $row[$column];
            $row[$column] = $value === null ? null : $this->cipher->encrypt((string)$value);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function decodeRow(string $table, array $row): array
    {
        foreach ($this->fieldsFor($table) as $column) {
            if (!array_key_exists($column, $row)) {
                continue;
            }

            $value = $row[$column];
            $row[$column] = $value === null ? null : $this->cipher->decrypt((string)$value);
        }

        return $row;
    }

    public function decodeValue(string $table, string $column, mixed $value): ?string
    {
        $this->assertMapped($table, $column);

        return $value === null ? null : $this->cipher->decrypt((string)$value);
    }

    public function lookupValue(string $table, string $column, string $plaintext): string
    {
        $this->assertMapped($table, $column);

        return $this->cipher->encryptForLookup($plaintext);
    }

    private function assertMapped(string $table, string $column): void
    {
        if (!in_array(strtoupper($column), $this->fieldsFor($table), true)) {
            throw new InvalidArgumentException('Column is not registered as a legacy SM4 field');
        }
    }
}
