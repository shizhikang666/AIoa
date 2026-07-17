<?php

declare(strict_types=1);

use app\service\biz\AnnualLeaveEntitlementService;

require dirname(__DIR__) . '/vendor/autoload.php';

$service = new AnnualLeaveEntitlementService();

$assertEntitlement = static function (
    string $entryDate,
    string $asOf,
    string $expected
) use ($service): void {
    $actual = $service->calculateEntitlement($entryDate, new DateTimeImmutable($asOf));
    if ($actual !== $expected) {
        throw new RuntimeException(
            sprintf('%s at %s expected %s, got %s', $entryDate, $asOf, $expected, $actual)
        );
    }
};

$assertEntitlement('2025-06-15', '2026-06-14', '0.00');
$assertEntitlement('2025-06-15', '2026-06-15', '2.50');
$assertEntitlement('2025-06-15', '2026-12-31', '2.50');
$assertEntitlement('2025-06-15', '2027-01-01', '5.00');
$assertEntitlement('2025-03-20', '2026-03-20', '3.75');
$assertEntitlement('2025-11-01', '2026-11-01', '0.42');
$assertEntitlement('2016-07-16', '2026-07-15', '5.00');
$assertEntitlement('2016-07-16', '2026-07-16', '10.00');
$assertEntitlement('2024-02-29', '2025-02-27', '0.00');
$assertEntitlement('2024-02-29', '2025-02-28', '4.17');
$assertEntitlement('', '2026-06-15', '0.00');

$zeroBalanceMethod = new ReflectionMethod(AnnualLeaveEntitlementService::class, 'isUntouchedZeroBalance');
$zeroBalanceMethod->setAccessible(true);
$cases = [
    [['AMOUNT' => '0.00', 'USED_AMOUNT' => '0.00'], true],
    [['AMOUNT' => '5.00', 'USED_AMOUNT' => '0.00'], false],
    [['AMOUNT' => '5.00', 'USED_AMOUNT' => '5.00'], false],
    [['AMOUNT' => '0.00', 'USED_AMOUNT' => '1.00'], false],
];
foreach ($cases as [$row, $expected]) {
    $actual = $zeroBalanceMethod->invoke($service, $row);
    if ($actual !== $expected) {
        throw new RuntimeException('annual leave zero-balance preservation rule failed');
    }
}

fwrite(STDOUT, "annual leave entitlement smoke passed\n");
