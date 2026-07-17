<?php

declare(strict_types=1);

/**
 * Deterministic, dependency-free Java serialization fixtures for the offline
 * migration smoke. This is a test writer, not production deserialization code.
 */
final class WorkflowJavaVariableFixtureBuilder
{
    private const TC_NULL = "\x70";
    private const TC_CLASSDESC = "\x72";
    private const TC_OBJECT = "\x73";
    private const TC_STRING = "\x74";
    private const TC_ARRAY = "\x75";
    private const TC_BLOCKDATA = "\x77";
    private const TC_ENDBLOCKDATA = "\x78";

    private const NUMBER = 'java.lang.Number';
    private const ARRAY_LIST = 'java.util.ArrayList';
    private const EMPTY_LIST = 'java.util.Collections$EmptyList';
    private const BIG_DECIMAL = 'java.math.BigDecimal';
    private const BIG_INTEGER = 'java.math.BigInteger';
    private const BYTE_ARRAY = '[B';
    private const PROCURE_PRODUCT =
        'vip.xiaonuo.biz.modular.bizprocess.param.process.procure.BizProcessProcureProductParam';
    private const SUPPLIER = 'vip.xiaonuo.biz.modular.supplier.param.SupplierAddParam';

    public static function allowedObjectList(): string
    {
        $items = [
            self::procureProduct(),
            self::supplier(),
            self::newString('fixture-id'),
        ];
        return self::stream(self::arrayList($items));
    }

    /** @param array<int, string> $values */
    public static function stringList(array $values): string
    {
        return self::stream(self::arrayList(array_map(
            static fn (string $value): string => self::newString($value),
            $values
        )));
    }

    public static function emptyList(): string
    {
        return self::stream(
            self::TC_OBJECT
            . self::classDesc(self::EMPTY_LIST, '7AB817B43CA79EDE', 0x02, [])
        );
    }

    public static function unknownObject(): string
    {
        return self::stream(
            self::TC_OBJECT
            . self::classDesc('fixture.UnknownCallback', '0000000000000001', 0x02, [])
        );
    }

    public static function proxyObject(): string
    {
        // A proxy descriptor is rejected immediately; no proxy metadata needs
        // to be interpreted by the production decoder.
        return self::stream(self::TC_OBJECT . "\x7D");
    }

    public static function rootString(): string
    {
        return self::stream(self::newString('fixture-root-string'));
    }

    public static function reusedClassDescriptorList(): string
    {
        $first = self::TC_OBJECT
            . self::classDesc(self::EMPTY_LIST, '7AB817B43CA79EDE', 0x02, []);
        // Outer descriptor/object use handles 0x7e0000/1; the first EmptyList
        // descriptor is 0x7e0002 and is reused by the second object.
        $second = self::TC_OBJECT . "\x71" . pack('N', 0x7E0002);
        return self::stream(self::arrayList([$first, $second]));
    }

    public static function repeatedStringReferenceList(string $value, int $occurrences): string
    {
        if ($occurrences < 1) {
            throw new RuntimeException('fixture occurrence count rejected');
        }
        // Outer descriptor/object are handles 0x7e0000/1. The first string is
        // handle 0x7e0002; later entries reuse it without repeating its bytes.
        $items = [self::newString($value)];
        for ($index = 1; $index < $occurrences; $index++) {
            $items[] = "\x71" . pack('N', 0x7E0002);
        }
        return self::stream(self::arrayList($items));
    }

    /** @param array<int, string> $leafValues */
    public static function repeatedNestedListDag(array $leafValues, int $occurrences): string
    {
        if ($occurrences < 1) {
            throw new RuntimeException('fixture occurrence count rejected');
        }
        $leafItems = array_map(
            static fn (string $value): string => self::newString($value),
            $leafValues
        );
        // Reuse the outer ArrayList descriptor (0x7e0000). The nested list
        // object becomes handle 0x7e0002 and is then referenced repeatedly.
        $nested = self::TC_OBJECT
            . "\x71" . pack('N', 0x7E0000)
            . pack('N', count($leafItems))
            . self::TC_BLOCKDATA . "\x04" . pack('N', count($leafItems))
            . implode('', $leafItems)
            . self::TC_ENDBLOCKDATA;
        $items = [$nested];
        for ($index = 1; $index < $occurrences; $index++) {
            $items[] = "\x71" . pack('N', 0x7E0002);
        }
        return self::stream(self::arrayList($items));
    }

    public static function nullList(int $items): string
    {
        if ($items < 0) {
            throw new RuntimeException('fixture item count rejected');
        }
        return self::stream(self::arrayList(array_fill(0, $items, self::TC_NULL)));
    }

    public static function repeatedEmptyListReferenceList(int $occurrences): string
    {
        if ($occurrences < 1) {
            throw new RuntimeException('fixture occurrence count rejected');
        }
        // Outer descriptor/object are 0x7e0000/1. The EmptyList descriptor and
        // object are 0x7e0002/3; later entries reuse the empty object.
        $first = self::TC_OBJECT
            . self::classDesc(self::EMPTY_LIST, '7AB817B43CA79EDE', 0x02, []);
        $items = [$first];
        for ($index = 1; $index < $occurrences; $index++) {
            $items[] = "\x71" . pack('N', 0x7E0003);
        }
        return self::stream(self::arrayList($items));
    }

    public static function nestedWireNullLists(int $outerItems, int $innerItems): string
    {
        if ($outerItems < 1 || $innerItems < 0) {
            throw new RuntimeException('fixture nested item count rejected');
        }
        $nested = self::TC_OBJECT
            . "\x71" . pack('N', 0x7E0000)
            . pack('N', $innerItems)
            . self::TC_BLOCKDATA . "\x04" . pack('N', $innerItems)
            . str_repeat(self::TC_NULL, $innerItems)
            . self::TC_ENDBLOCKDATA;
        return self::stream(self::arrayList(array_fill(0, $outerItems, $nested)));
    }

    public static function referencedNestedListChain(int $listCount): string
    {
        if ($listCount < 1) {
            throw new RuntimeException('fixture chain length rejected');
        }
        $items = [];
        for ($index = 0; $index < $listCount; $index++) {
            $size = $index === 0 ? 0 : 1;
            $content = '';
            if ($index > 0) {
                // Nested objects start at 0x7e0002 and no other handles are
                // allocated, so the previous object is 0x7e0001 + index.
                $content = "\x71" . pack('N', 0x7E0001 + $index);
            }
            $items[] = self::TC_OBJECT
                . "\x71" . pack('N', 0x7E0000)
                . pack('N', $size)
                . self::TC_BLOCKDATA . "\x04" . pack('N', $size)
                . $content
                . self::TC_ENDBLOCKDATA;
        }
        return self::stream(self::arrayList($items));
    }

    private static function stream(string $content): string
    {
        return "\xAC\xED\x00\x05" . $content;
    }

    /** @param array<int, string> $items */
    private static function arrayList(array $items): string
    {
        $size = count($items);
        return self::TC_OBJECT
            . self::classDesc(self::ARRAY_LIST, '7881D21D99C7619D', 0x03, ['size' => 'I'])
            . pack('N', $size)
            . self::TC_BLOCKDATA . "\x04" . pack('N', $size)
            . implode('', $items)
            . self::TC_ENDBLOCKDATA;
    }

    private static function procureProduct(): string
    {
        $fields = [
            'link' => 'Ljava/lang/String;',
            'model' => 'Ljava/lang/String;',
            'number' => 'Ljava/math/BigDecimal;',
            'productName' => 'Ljava/lang/String;',
            'remark' => 'Ljava/lang/String;',
            'specs' => 'Ljava/lang/String;',
        ];
        return self::TC_OBJECT
            . self::classDesc(self::PROCURE_PRODUCT, 'FC7E95B0FCC8A5FC', 0x02, $fields)
            . self::newString('fixture-link')
            . self::newString('fixture-model')
            . self::bigDecimal(2, 1250)
            . self::newString('fixture-product')
            . self::TC_NULL
            . self::newString('fixture-specs');
    }

    private static function supplier(): string
    {
        $fields = [
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
        ];
        return self::TC_OBJECT
            . self::classDesc(self::SUPPLIER, 'D6FEF05C6FB5B42F', 0x02, $fields)
            . self::newString('fixture-alias')
            . self::newString('fixture-bank-account')
            . self::newString('fixture-bank')
            . self::newString('fixture-contact')
            . self::newString('fixture-nature')
            . self::newString('fixture-supplier')
            . self::newString('fixture-payment')
            . self::newString('fixture-phone')
            . self::TC_NULL
            . self::newString('ENABLE')
            . self::newString('fixture-tax');
    }

    private static function bigDecimal(int $scale, int $unscaled): string
    {
        $fields = [
            'scale' => 'I',
            'intVal' => 'Ljava/math/BigInteger;',
        ];
        return self::TC_OBJECT
            . self::classDesc(
                self::BIG_DECIMAL,
                '54C71557F981284F',
                0x03,
                $fields,
                self::numberDesc()
            )
            . pack('N', $scale)
            . self::bigInteger($unscaled)
            . self::TC_ENDBLOCKDATA;
    }

    private static function bigInteger(int $value): string
    {
        if ($value < 0 || $value > 65535) {
            throw new RuntimeException('fixture integer out of range');
        }
        $fields = [
            'bitCount' => 'I',
            'bitLength' => 'I',
            'firstNonzeroByteNum' => 'I',
            'lowestSetBit' => 'I',
            'signum' => 'I',
            'magnitude' => '[B',
        ];
        $magnitude = $value === 0 ? '' : ltrim(pack('n', $value), "\x00");
        return self::TC_OBJECT
            . self::classDesc(
                self::BIG_INTEGER,
                '8CFC9F1FA93BFB1D',
                0x03,
                $fields,
                self::numberDesc()
            )
            . pack('N', -1)
            . pack('N', -1)
            . pack('N', -2)
            . pack('N', -2)
            . pack('N', $value === 0 ? 0 : 1)
            . self::byteArray($magnitude)
            . self::TC_ENDBLOCKDATA;
    }

    private static function byteArray(string $bytes): string
    {
        return self::TC_ARRAY
            . self::classDesc(self::BYTE_ARRAY, 'ACF317F8060854E0', 0x02, [])
            . pack('N', strlen($bytes))
            . $bytes;
    }

    private static function numberDesc(): string
    {
        return self::classDesc(self::NUMBER, '86AC951D0B94E08B', 0x02, []);
    }

    /**
     * @param array<string, string> $fields
     */
    private static function classDesc(
        string $name,
        string $uid,
        int $flags,
        array $fields,
        ?string $super = null
    ): string {
        $descriptor = self::TC_CLASSDESC
            . self::utf($name)
            . pack('H*', $uid)
            . chr($flags)
            . pack('n', count($fields));
        foreach ($fields as $fieldName => $signature) {
            $type = strlen($signature) === 1 ? $signature : $signature[0];
            $descriptor .= $type . self::utf($fieldName);
            if ($type === 'L' || $type === '[') {
                $descriptor .= self::newString($signature);
            }
        }
        return $descriptor
            . self::TC_ENDBLOCKDATA
            . ($super ?? self::TC_NULL);
    }

    private static function newString(string $value): string
    {
        return self::TC_STRING . self::utf($value);
    }

    private static function utf(string $value): string
    {
        if (strlen($value) > 65535) {
            throw new RuntimeException('fixture UTF value too long');
        }
        return pack('n', strlen($value)) . $value;
    }
}
