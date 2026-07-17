<?php

declare(strict_types=1);

namespace app\support\migration;

final class JavaSerializationDecodedValue
{
    public string $type;
    public mixed $value;

    public function __construct(string $type, mixed $value)
    {
        $this->type = $type;
        $this->value = $value;
    }
}

/**
 * A deliberately small Java Object Serialization reader.
 *
 * It never instantiates a class and never invokes application callbacks. Only
 * the exact descriptors audited for the legacy workflow variables are
 * accepted. Everything else, including proxies and Externalizable objects, is
 * rejected before class data is interpreted.
 */
class JavaSerializationDecoder
{
    private const STREAM_MAGIC = "\xAC\xED";
    private const STREAM_VERSION = "\x00\x05";

    private const TC_NULL = 0x70;
    private const TC_REFERENCE = 0x71;
    private const TC_CLASSDESC = 0x72;
    private const TC_OBJECT = 0x73;
    private const TC_STRING = 0x74;
    private const TC_ARRAY = 0x75;
    private const TC_CLASS = 0x76;
    private const TC_BLOCKDATA = 0x77;
    private const TC_ENDBLOCKDATA = 0x78;
    private const TC_RESET = 0x79;
    private const TC_BLOCKDATALONG = 0x7A;
    private const TC_EXCEPTION = 0x7B;
    private const TC_LONGSTRING = 0x7C;
    private const TC_PROXYCLASSDESC = 0x7D;
    private const TC_ENUM = 0x7E;

    private const BASE_WIRE_HANDLE = 0x7E0000;
    private const MAX_STREAM_BYTES = 8388608;
    private const MAX_STRING_BYTES = 1048576;
    private const MAX_ARRAY_ITEMS = 10000;
    private const MAX_BIG_INTEGER_MAGNITUDE_BYTES = 1661;
    private const MAX_HANDLES = 20000;
    private const MAX_DEPTH = 64;

    private const CLASS_ARRAY_LIST = 'java.util.ArrayList';
    private const CLASS_EMPTY_LIST = 'java.util.Collections$EmptyList';
    private const CLASS_BIG_DECIMAL = 'java.math.BigDecimal';
    private const CLASS_BIG_INTEGER = 'java.math.BigInteger';
    private const CLASS_NUMBER = 'java.lang.Number';
    private const CLASS_BYTE_ARRAY = '[B';
    private const CLASS_PROCURE_PRODUCT =
        'vip.xiaonuo.biz.modular.bizprocess.param.process.procure.BizProcessProcureProductParam';
    private const CLASS_SUPPLIER = 'vip.xiaonuo.biz.modular.supplier.param.SupplierAddParam';

    /**
     * UID values are compared as their unsigned, big-endian wire bytes.
     *
     * @var array<string, array{
     *     uid: string,
     *     flags: int,
     *     fields: array<string, string>,
     *     super: string|null
     * }>
     */
    private const ALLOWED_CLASSES = [
        self::CLASS_ARRAY_LIST => [
            'uid' => '7881D21D99C7619D',
            'flags' => 0x03,
            'fields' => ['size' => 'I'],
            'super' => null,
        ],
        self::CLASS_EMPTY_LIST => [
            'uid' => '7AB817B43CA79EDE',
            'flags' => 0x02,
            'fields' => [],
            'super' => null,
        ],
        self::CLASS_BIG_DECIMAL => [
            'uid' => '54C71557F981284F',
            'flags' => 0x03,
            'fields' => [
                'scale' => 'I',
                'intVal' => 'Ljava/math/BigInteger;',
            ],
            'super' => self::CLASS_NUMBER,
        ],
        self::CLASS_BIG_INTEGER => [
            'uid' => '8CFC9F1FA93BFB1D',
            'flags' => 0x03,
            'fields' => [
                'bitCount' => 'I',
                'bitLength' => 'I',
                'firstNonzeroByteNum' => 'I',
                'lowestSetBit' => 'I',
                'signum' => 'I',
                'magnitude' => '[B',
            ],
            'super' => self::CLASS_NUMBER,
        ],
        // Serializable superclass metadata required by BigDecimal/BigInteger.
        self::CLASS_NUMBER => [
            'uid' => '86AC951D0B94E08B',
            'flags' => 0x02,
            'fields' => [],
            'super' => null,
        ],
        // Structural byte[] used only for BigInteger.magnitude.
        self::CLASS_BYTE_ARRAY => [
            'uid' => 'ACF317F8060854E0',
            'flags' => 0x02,
            'fields' => [],
            'super' => null,
        ],
        self::CLASS_PROCURE_PRODUCT => [
            'uid' => 'FC7E95B0FCC8A5FC',
            'flags' => 0x02,
            'fields' => [
                'link' => 'Ljava/lang/String;',
                'model' => 'Ljava/lang/String;',
                'number' => 'Ljava/math/BigDecimal;',
                'productName' => 'Ljava/lang/String;',
                'remark' => 'Ljava/lang/String;',
                'specs' => 'Ljava/lang/String;',
            ],
            'super' => null,
        ],
        self::CLASS_SUPPLIER => [
            'uid' => 'D6FEF05C6FB5B42F',
            'flags' => 0x02,
            'fields' => [
                'aliasName' => 'Ljava/lang/String;',
                'bankAccount' => 'Ljava/lang/String;',
                'bankName' => 'Ljava/lang/String;',
                'contacts' => 'Ljava/lang/String;',
                'enterpriseNature' => 'Ljava/lang/String;',
                'name' => 'Ljava/lang/String;',
                'paymentMethod' => 'Ljava/lang/String;',
                'phone' => 'Ljava/lang/String;',
                'sortCode' => 'Ljava/lang/Integer;',
                'status' => 'Ljava/lang/String;',
                'taxRegistrationNumber' => 'Ljava/lang/String;',
            ],
            'super' => null,
        ],
    ];

    private string $bytes = '';
    private int $offset = 0;
    private int $nextHandle = self::BASE_WIRE_HANDLE;

    /** @var array<int, array{kind: string, value: mixed}> */
    private array $handles = [];

    public function decode(string $serialized): mixed
    {
        $length = strlen($serialized);
        if ($length < 5 || $length > self::MAX_STREAM_BYTES) {
            throw new WorkflowVariableMigrationException('JAVA_STREAM_SIZE_REJECTED');
        }

        $this->bytes = $serialized;
        $this->offset = 0;
        $this->nextHandle = self::BASE_WIRE_HANDLE;
        $this->handles = [];

        if ($this->readBytes(2) !== self::STREAM_MAGIC || $this->readBytes(2) !== self::STREAM_VERSION) {
            throw new WorkflowVariableMigrationException('JAVA_STREAM_HEADER_REJECTED');
        }

        $value = $this->readContent(0);
        if ($this->offset !== strlen($this->bytes)) {
            throw new WorkflowVariableMigrationException('JAVA_STREAM_TRAILING_DATA_REJECTED');
        }
        if ($value === null || is_string($value)) {
            throw new WorkflowVariableMigrationException('JAVA_ROOT_VALUE_REJECTED');
        }

        return $this->normalizeOutput($value);
    }

    private function readContent(int $depth): mixed
    {
        $this->assertDepth($depth);
        return $this->readContentWithToken($this->readByte(), $depth);
    }

    private function readContentWithToken(int $token, int $depth): mixed
    {
        $this->assertDepth($depth);

        if ($token === self::TC_NULL) {
            return null;
        }
        if ($token === self::TC_REFERENCE) {
            $entry = $this->readHandle();
            if (!in_array($entry['kind'], ['string', 'object', 'array'], true)) {
                throw new WorkflowVariableMigrationException('JAVA_REFERENCE_KIND_REJECTED');
            }
            return $entry['value'];
        }
        if ($token === self::TC_STRING || $token === self::TC_LONGSTRING) {
            return $this->readNewString($token);
        }
        if ($token === self::TC_OBJECT) {
            return $this->readNewObject($depth + 1);
        }
        if ($token === self::TC_ARRAY) {
            return $this->readNewArray($depth + 1);
        }

        if (in_array($token, [
            self::TC_CLASS,
            self::TC_BLOCKDATA,
            self::TC_ENDBLOCKDATA,
            self::TC_RESET,
            self::TC_BLOCKDATALONG,
            self::TC_EXCEPTION,
            self::TC_PROXYCLASSDESC,
            self::TC_ENUM,
            self::TC_CLASSDESC,
        ], true)) {
            throw new WorkflowVariableMigrationException('JAVA_CONTENT_TOKEN_REJECTED');
        }

        throw new WorkflowVariableMigrationException('JAVA_CONTENT_TOKEN_UNKNOWN');
    }

    private function readNewString(int $token): string
    {
        if ($token === self::TC_STRING) {
            $length = $this->readUnsignedShort();
        } else {
            $high = $this->readUnsignedInt();
            $low = $this->readUnsignedInt();
            if ($high !== 0) {
                throw new WorkflowVariableMigrationException('JAVA_STRING_SIZE_REJECTED');
            }
            $length = $low;
        }

        if ($length > self::MAX_STRING_BYTES) {
            throw new WorkflowVariableMigrationException('JAVA_STRING_SIZE_REJECTED');
        }

        $value = $this->decodeModifiedUtf8($this->readBytes($length));
        $this->registerHandle('string', $value);
        return $value;
    }

    private function readNewObject(int $depth): mixed
    {
        $descriptor = $this->readClassDesc($depth + 1);
        if ($descriptor === null) {
            throw new WorkflowVariableMigrationException('JAVA_OBJECT_DESCRIPTOR_REJECTED');
        }

        $handle = $this->registerHandle('pending', null);
        $slots = [];
        $this->readClassData($descriptor, $slots, $depth + 1);
        $value = $this->transformObject($descriptor['name'], $slots);
        $this->handles[$handle] = ['kind' => 'object', 'value' => $value];
        return $value;
    }

    private function readNewArray(int $depth): JavaSerializationDecodedValue
    {
        $descriptor = $this->readClassDesc($depth + 1);
        if ($descriptor === null || $descriptor['name'] !== self::CLASS_BYTE_ARRAY) {
            throw new WorkflowVariableMigrationException('JAVA_ARRAY_CLASS_REJECTED');
        }

        $handle = $this->registerHandle('pending', null);
        $length = $this->readSignedInt();
        if ($length < 0 || $length > self::MAX_STREAM_BYTES) {
            throw new WorkflowVariableMigrationException('JAVA_ARRAY_SIZE_REJECTED');
        }

        $value = new JavaSerializationDecodedValue('byte-array', $this->readBytes($length));
        $this->handles[$handle] = ['kind' => 'array', 'value' => $value];
        return $value;
    }

    /**
     * @param array{name: string, flags: int, fields: array<int, array{name: string, type: string}>, super: array|null} $descriptor
     * @param array<string, array<string, mixed>> $slots
     */
    private function readClassData(array $descriptor, array &$slots, int $depth): void
    {
        $this->assertDepth($depth);
        if (is_array($descriptor['super'])) {
            $this->readClassData($descriptor['super'], $slots, $depth + 1);
        }

        $values = [];
        foreach ($descriptor['fields'] as $field) {
            $values[$field['name']] = $this->readFieldValue($field['type'], $depth + 1);
        }
        $slots[$descriptor['name']] = $values;

        if (($descriptor['flags'] & 0x01) === 0) {
            return;
        }

        if ($descriptor['name'] === self::CLASS_ARRAY_LIST) {
            $slots[$descriptor['name']]['items'] = $this->readArrayListCustomData(
                (int)($values['size'] ?? -1),
                $depth + 1
            );
            return;
        }

        if (in_array($descriptor['name'], [self::CLASS_BIG_DECIMAL, self::CLASS_BIG_INTEGER], true)) {
            if ($this->readByte() !== self::TC_ENDBLOCKDATA) {
                throw new WorkflowVariableMigrationException('JAVA_CUSTOM_DATA_REJECTED');
            }
            return;
        }

        throw new WorkflowVariableMigrationException('JAVA_CUSTOM_CLASS_REJECTED');
    }

    /** @return array<int, mixed> */
    private function readArrayListCustomData(int $declaredSize, int $depth): array
    {
        if ($declaredSize < 0 || $declaredSize > self::MAX_ARRAY_ITEMS) {
            throw new WorkflowVariableMigrationException('JAVA_LIST_SIZE_REJECTED');
        }

        $token = $this->readByte();
        if ($token === self::TC_BLOCKDATA) {
            $length = $this->readByte();
        } elseif ($token === self::TC_BLOCKDATALONG) {
            $length = $this->readSignedInt();
        } else {
            throw new WorkflowVariableMigrationException('JAVA_LIST_BLOCK_REJECTED');
        }
        if ($length !== 4 || $this->readSignedInt() !== $declaredSize) {
            throw new WorkflowVariableMigrationException('JAVA_LIST_SIZE_MISMATCH');
        }

        $items = [];
        for ($index = 0; $index < $declaredSize; $index++) {
            $items[] = $this->readContent($depth + 1);
        }
        if ($this->readByte() !== self::TC_ENDBLOCKDATA) {
            throw new WorkflowVariableMigrationException('JAVA_LIST_END_REJECTED');
        }

        return $items;
    }

    private function readFieldValue(string $type, int $depth): mixed
    {
        if ($type === 'I') {
            return $this->readSignedInt();
        }
        if ($type !== '' && ($type[0] === 'L' || $type[0] === '[')) {
            return $this->readContent($depth + 1);
        }

        throw new WorkflowVariableMigrationException('JAVA_FIELD_TYPE_REJECTED');
    }

    /**
     * @return array{name: string, flags: int, fields: array<int, array{name: string, type: string}>, super: array|null}|null
     */
    private function readClassDesc(int $depth): ?array
    {
        $this->assertDepth($depth);
        $token = $this->readByte();
        if ($token === self::TC_NULL) {
            return null;
        }
        if ($token === self::TC_REFERENCE) {
            $entry = $this->readHandle();
            if ($entry['kind'] !== 'classdesc' || !is_array($entry['value'])) {
                throw new WorkflowVariableMigrationException('JAVA_CLASS_REFERENCE_REJECTED');
            }
            return $entry['value'];
        }
        if ($token === self::TC_PROXYCLASSDESC) {
            throw new WorkflowVariableMigrationException('JAVA_PROXY_REJECTED');
        }
        if ($token !== self::TC_CLASSDESC) {
            throw new WorkflowVariableMigrationException('JAVA_CLASS_DESCRIPTOR_REJECTED');
        }

        $name = $this->readUtf();
        if (!isset(self::ALLOWED_CLASSES[$name])) {
            throw new WorkflowVariableMigrationException('JAVA_CLASS_NOT_ALLOWED');
        }

        $uid = strtoupper(bin2hex($this->readBytes(8)));
        $flags = $this->readByte();
        $fieldCount = $this->readUnsignedShort();
        if ($fieldCount > 64) {
            throw new WorkflowVariableMigrationException('JAVA_FIELD_COUNT_REJECTED');
        }

        $handle = $this->registerHandle('pending', null);
        $fields = [];
        for ($index = 0; $index < $fieldCount; $index++) {
            $type = chr($this->readByte());
            $fieldName = $this->readUtf();
            $signature = $type;
            if ($type === 'L' || $type === '[') {
                $signature = $this->readTypeString();
            }
            $fields[] = ['name' => $fieldName, 'type' => $signature];
        }

        if ($this->readByte() !== self::TC_ENDBLOCKDATA) {
            throw new WorkflowVariableMigrationException('JAVA_CLASS_ANNOTATION_REJECTED');
        }
        $super = $this->readClassDesc($depth + 1);

        $descriptor = [
            'name' => $name,
            'flags' => $flags,
            'fields' => $fields,
            'super' => $super,
        ];
        $this->validateClassDescriptor($descriptor, $uid);
        $this->handles[$handle] = ['kind' => 'classdesc', 'value' => $descriptor];
        return $descriptor;
    }

    private function readTypeString(): string
    {
        $token = $this->readByte();
        if ($token === self::TC_STRING || $token === self::TC_LONGSTRING) {
            return $this->readNewString($token);
        }
        if ($token === self::TC_REFERENCE) {
            $entry = $this->readHandle();
            if ($entry['kind'] !== 'string' || !is_string($entry['value'])) {
                throw new WorkflowVariableMigrationException('JAVA_FIELD_SIGNATURE_REJECTED');
            }
            return $entry['value'];
        }

        throw new WorkflowVariableMigrationException('JAVA_FIELD_SIGNATURE_REJECTED');
    }

    /**
     * @param array{name: string, flags: int, fields: array<int, array{name: string, type: string}>, super: array|null} $descriptor
     */
    private function validateClassDescriptor(array $descriptor, string $uid): void
    {
        $expected = self::ALLOWED_CLASSES[$descriptor['name']];
        if ($uid !== $expected['uid'] || $descriptor['flags'] !== $expected['flags']) {
            throw new WorkflowVariableMigrationException('JAVA_CLASS_IDENTITY_REJECTED');
        }

        $actualFields = [];
        foreach ($descriptor['fields'] as $field) {
            if (isset($actualFields[$field['name']])) {
                throw new WorkflowVariableMigrationException('JAVA_FIELD_DUPLICATE_REJECTED');
            }
            $actualFields[$field['name']] = $field['type'];
        }
        if ($actualFields !== $expected['fields']) {
            throw new WorkflowVariableMigrationException('JAVA_CLASS_FIELDS_REJECTED');
        }

        $actualSuper = is_array($descriptor['super']) ? $descriptor['super']['name'] : null;
        if ($actualSuper !== $expected['super']) {
            throw new WorkflowVariableMigrationException('JAVA_CLASS_SUPER_REJECTED');
        }
    }

    /**
     * @param array<string, array<string, mixed>> $slots
     */
    private function transformObject(string $className, array $slots): mixed
    {
        $values = $slots[$className] ?? [];
        if ($className === self::CLASS_ARRAY_LIST) {
            return array_values($values['items'] ?? []);
        }
        if ($className === self::CLASS_EMPTY_LIST) {
            return [];
        }
        if ($className === self::CLASS_BIG_INTEGER) {
            return $this->bigIntegerValue($values);
        }
        if ($className === self::CLASS_BIG_DECIMAL) {
            return $this->bigDecimalValue($values);
        }
        if ($className === self::CLASS_PROCURE_PRODUCT) {
            $this->assertStringOrNullFields($values, ['link', 'model', 'productName', 'remark', 'specs']);
            if (!$this->isDecodedType($values['number'] ?? null, 'big-decimal')) {
                throw new WorkflowVariableMigrationException('JAVA_PROCURE_NUMBER_REJECTED');
            }
            return $values;
        }
        if ($className === self::CLASS_SUPPLIER) {
            $this->assertStringOrNullFields($values, [
                'aliasName',
                'bankAccount',
                'bankName',
                'contacts',
                'enterpriseNature',
                'name',
                'paymentMethod',
                'phone',
                'status',
                'taxRegistrationNumber',
            ]);
            // Integer was not present in the audited class inventory. A null
            // declared field is safe; a non-null wrapper is rejected by the
            // class allow-list before this point.
            if (($values['sortCode'] ?? null) !== null) {
                throw new WorkflowVariableMigrationException('JAVA_SUPPLIER_SORT_CODE_REJECTED');
            }
            return $values;
        }

        throw new WorkflowVariableMigrationException('JAVA_ROOT_CLASS_REJECTED');
    }

    /** @param array<string, mixed> $values */
    private function bigIntegerValue(array $values): JavaSerializationDecodedValue
    {
        $signum = $values['signum'] ?? null;
        $magnitudeValue = $values['magnitude'] ?? null;
        if (!is_int($signum)
            || !$this->isDecodedType($magnitudeValue, 'byte-array')
            || !in_array($signum, [-1, 0, 1], true)
            || ($values['bitCount'] ?? null) !== -1
            || ($values['bitLength'] ?? null) !== -1
            || ($values['firstNonzeroByteNum'] ?? null) !== -2
            || ($values['lowestSetBit'] ?? null) !== -2) {
            throw new WorkflowVariableMigrationException('JAVA_BIG_INTEGER_REJECTED');
        }
        $magnitude = $magnitudeValue->value;
        if (!is_string($magnitude)) {
            throw new WorkflowVariableMigrationException('JAVA_BIG_INTEGER_REJECTED');
        }
        if (strlen($magnitude) > self::MAX_BIG_INTEGER_MAGNITUDE_BYTES) {
            throw new WorkflowVariableMigrationException('JAVA_BIG_INTEGER_SIZE_REJECTED');
        }
        if (($signum === 0) !== ($magnitude === '')) {
            throw new WorkflowVariableMigrationException('JAVA_BIG_INTEGER_REJECTED');
        }
        if ($magnitude !== '' && ord($magnitude[0]) === 0) {
            throw new WorkflowVariableMigrationException('JAVA_BIG_INTEGER_REJECTED');
        }

        $decimal = '0';
        $length = strlen($magnitude);
        for ($index = 0; $index < $length; $index++) {
            $decimal = $this->decimalMultiplyAndAdd($decimal, 256, ord($magnitude[$index]));
        }
        if ($signum < 0 && $decimal !== '0') {
            $decimal = '-' . $decimal;
        }
        return new JavaSerializationDecodedValue('big-integer', $decimal);
    }

    /** @param array<string, mixed> $values */
    private function bigDecimalValue(array $values): JavaSerializationDecodedValue
    {
        $scale = $values['scale'] ?? null;
        $unscaledValue = $values['intVal'] ?? null;
        if (!is_int($scale) || !$this->isDecodedType($unscaledValue, 'big-integer')) {
            throw new WorkflowVariableMigrationException('JAVA_BIG_DECIMAL_REJECTED');
        }
        $unscaled = $unscaledValue->value;
        if (!is_string($unscaled) || !preg_match('/^-?\d+$/', $unscaled)) {
            throw new WorkflowVariableMigrationException('JAVA_BIG_DECIMAL_REJECTED');
        }
        if (abs($scale) > 10000) {
            throw new WorkflowVariableMigrationException('JAVA_BIG_DECIMAL_SCALE_REJECTED');
        }

        $negative = str_starts_with($unscaled, '-');
        $digits = $negative ? substr($unscaled, 1) : $unscaled;
        if ($scale > 0) {
            if (strlen($digits) <= $scale) {
                $digits = '0.' . str_repeat('0', $scale - strlen($digits)) . $digits;
            } else {
                $digits = substr($digits, 0, -$scale) . '.' . substr($digits, -$scale);
            }
        } elseif ($scale < 0) {
            $digits .= str_repeat('0', -$scale);
        }

        return new JavaSerializationDecodedValue(
            'big-decimal',
            $negative && $digits !== '0' ? '-' . $digits : $digits
        );
    }

    private function isDecodedType(mixed $value, string $type): bool
    {
        return $value instanceof JavaSerializationDecodedValue && $value->type === $type;
    }

    private function normalizeOutput(mixed $value): mixed
    {
        if ($value instanceof JavaSerializationDecodedValue) {
            if (!in_array($value->type, ['big-integer', 'big-decimal'], true)
                || !is_string($value->value)) {
                throw new WorkflowVariableMigrationException('JAVA_INTERNAL_VALUE_REJECTED');
            }
            return $value->value;
        }
        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizeOutput($item);
        }
        return $normalized;
    }

    private function decimalMultiplyAndAdd(string $decimal, int $multiplier, int $addend): string
    {
        $carry = $addend;
        $result = '';
        for ($index = strlen($decimal) - 1; $index >= 0; $index--) {
            $value = ((ord($decimal[$index]) - 48) * $multiplier) + $carry;
            $result = chr(48 + ($value % 10)) . $result;
            $carry = intdiv($value, 10);
        }
        while ($carry > 0) {
            $result = chr(48 + ($carry % 10)) . $result;
            $carry = intdiv($carry, 10);
        }
        return ltrim($result, '0') ?: '0';
    }

    /**
     * @param array<string, mixed> $values
     * @param array<int, string> $fields
     */
    private function assertStringOrNullFields(array $values, array $fields): void
    {
        foreach ($fields as $field) {
            $value = $values[$field] ?? null;
            if ($value !== null && !is_string($value)) {
                throw new WorkflowVariableMigrationException('JAVA_CUSTOM_FIELD_VALUE_REJECTED');
            }
        }
    }

    /** @return array{kind: string, value: mixed} */
    private function readHandle(): array
    {
        $handle = $this->readUnsignedInt();
        if (!isset($this->handles[$handle])) {
            throw new WorkflowVariableMigrationException('JAVA_REFERENCE_UNKNOWN');
        }
        $entry = $this->handles[$handle];
        if ($entry['kind'] === 'pending') {
            throw new WorkflowVariableMigrationException('JAVA_CYCLIC_REFERENCE_REJECTED');
        }
        return $entry;
    }

    private function registerHandle(string $kind, mixed $value): int
    {
        if (count($this->handles) >= self::MAX_HANDLES) {
            throw new WorkflowVariableMigrationException('JAVA_HANDLE_LIMIT_REJECTED');
        }
        $handle = $this->nextHandle++;
        $this->handles[$handle] = ['kind' => $kind, 'value' => $value];
        return $handle;
    }

    private function readUtf(): string
    {
        $length = $this->readUnsignedShort();
        if ($length > self::MAX_STRING_BYTES) {
            throw new WorkflowVariableMigrationException('JAVA_UTF_SIZE_REJECTED');
        }
        return $this->decodeModifiedUtf8($this->readBytes($length));
    }

    private function decodeModifiedUtf8(string $encoded): string
    {
        $units = [];
        $length = strlen($encoded);
        for ($index = 0; $index < $length;) {
            $first = ord($encoded[$index++]);
            if ($first >= 0x01 && $first <= 0x7F) {
                $units[] = $first;
                continue;
            }
            if (($first & 0xE0) === 0xC0) {
                if ($index >= $length) {
                    throw new WorkflowVariableMigrationException('JAVA_UTF_REJECTED');
                }
                $second = ord($encoded[$index++]);
                if (($second & 0xC0) !== 0x80) {
                    throw new WorkflowVariableMigrationException('JAVA_UTF_REJECTED');
                }
                $unit = (($first & 0x1F) << 6) | ($second & 0x3F);
                if ($unit !== 0 && $unit < 0x80) {
                    throw new WorkflowVariableMigrationException('JAVA_UTF_REJECTED');
                }
                $units[] = $unit;
                continue;
            }
            if (($first & 0xF0) === 0xE0) {
                if ($index + 1 >= $length) {
                    throw new WorkflowVariableMigrationException('JAVA_UTF_REJECTED');
                }
                $second = ord($encoded[$index++]);
                $third = ord($encoded[$index++]);
                if (($second & 0xC0) !== 0x80 || ($third & 0xC0) !== 0x80) {
                    throw new WorkflowVariableMigrationException('JAVA_UTF_REJECTED');
                }
                $unit = (($first & 0x0F) << 12) | (($second & 0x3F) << 6) | ($third & 0x3F);
                if ($unit < 0x800) {
                    throw new WorkflowVariableMigrationException('JAVA_UTF_REJECTED');
                }
                $units[] = $unit;
                continue;
            }
            throw new WorkflowVariableMigrationException('JAVA_UTF_REJECTED');
        }

        $decoded = '';
        $count = count($units);
        for ($index = 0; $index < $count; $index++) {
            $unit = $units[$index];
            if ($unit >= 0xD800 && $unit <= 0xDBFF) {
                if (++$index >= $count) {
                    throw new WorkflowVariableMigrationException('JAVA_UTF_SURROGATE_REJECTED');
                }
                $low = $units[$index];
                if ($low < 0xDC00 || $low > 0xDFFF) {
                    throw new WorkflowVariableMigrationException('JAVA_UTF_SURROGATE_REJECTED');
                }
                $decoded .= $this->encodeCodePoint(
                    0x10000 + (($unit - 0xD800) << 10) + ($low - 0xDC00)
                );
                continue;
            }
            if ($unit >= 0xDC00 && $unit <= 0xDFFF) {
                throw new WorkflowVariableMigrationException('JAVA_UTF_SURROGATE_REJECTED');
            }
            $decoded .= $this->encodeCodePoint($unit);
        }
        return $decoded;
    }

    private function encodeCodePoint(int $codePoint): string
    {
        if ($codePoint <= 0x7F) {
            return chr($codePoint);
        }
        if ($codePoint <= 0x7FF) {
            return chr(0xC0 | ($codePoint >> 6))
                . chr(0x80 | ($codePoint & 0x3F));
        }
        if ($codePoint <= 0xFFFF) {
            return chr(0xE0 | ($codePoint >> 12))
                . chr(0x80 | (($codePoint >> 6) & 0x3F))
                . chr(0x80 | ($codePoint & 0x3F));
        }
        if ($codePoint <= 0x10FFFF) {
            return chr(0xF0 | ($codePoint >> 18))
                . chr(0x80 | (($codePoint >> 12) & 0x3F))
                . chr(0x80 | (($codePoint >> 6) & 0x3F))
                . chr(0x80 | ($codePoint & 0x3F));
        }
        throw new WorkflowVariableMigrationException('JAVA_CODE_POINT_REJECTED');
    }

    private function readByte(): int
    {
        return ord($this->readBytes(1));
    }

    private function readUnsignedShort(): int
    {
        $value = unpack('nvalue', $this->readBytes(2));
        return (int)$value['value'];
    }

    private function readUnsignedInt(): int
    {
        $value = unpack('Nvalue', $this->readBytes(4));
        return (int)$value['value'];
    }

    private function readSignedInt(): int
    {
        $value = $this->readUnsignedInt();
        return $value >= 0x80000000 ? $value - 0x100000000 : $value;
    }

    private function readBytes(int $length): string
    {
        if ($length < 0 || $this->offset + $length > strlen($this->bytes)) {
            throw new WorkflowVariableMigrationException('JAVA_STREAM_TRUNCATED');
        }
        $value = substr($this->bytes, $this->offset, $length);
        $this->offset += $length;
        return $value;
    }

    private function assertDepth(int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new WorkflowVariableMigrationException('JAVA_DEPTH_REJECTED');
        }
    }
}
