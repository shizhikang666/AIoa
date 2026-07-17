<?php

declare(strict_types=1);

namespace app\service\biz;

use DateTimeImmutable;
use RuntimeException;
use think\facade\Db;

/**
 * Calculates annual-leave entitlement and lazily initializes the current-year balance.
 */
class AnnualLeaveEntitlementService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const CATEGORY = 'annualLeave';
    private const STANDARD_DAYS = 5;
    private const TEN_YEAR_DAYS = 10;

    /**
     * The first eligible calendar year only counts complete months after the first anniversary month.
     */
    public function calculateEntitlement(string $entryDate, ?DateTimeImmutable $asOf = null): string
    {
        $asOf = $this->asOfDate($asOf);
        $entry = $this->entryDate($entryDate);
        if ($entry === null) {
            return '0.00';
        }

        $firstAnniversary = $this->anniversary($entry, 1);
        if ($asOf < $firstAnniversary) {
            return '0.00';
        }

        $serviceYears = $this->completedServiceYears($entry, $asOf);
        if ($serviceYears >= 10) {
            return number_format(self::TEN_YEAR_DAYS, 2, '.', '');
        }

        if ($asOf->format('Y') === $firstAnniversary->format('Y')) {
            $remainingMonths = max(0, 12 - (int)$firstAnniversary->format('n'));
            $days = round($remainingMonths * self::STANDARD_DAYS / 12, 2, PHP_ROUND_HALF_UP);

            return number_format($days, 2, '.', '');
        }

        return number_format(self::STANDARD_DAYS, 2, '.', '');
    }

    /**
     * Returns the effective current-year balance without writing to the database.
     * A persisted non-zero amount or usage is always preserved.
     *
     * @return array<string, mixed>
     */
    public function previewCurrentYearBalance(
        string $userId,
        string $tenantId = '',
        ?DateTimeImmutable $asOf = null
    ): array {
        $asOf = $this->asOfDate($asOf);
        $user = $this->activeUser($userId, $tenantId, false);
        $effectiveTenantId = trim((string)($user['TENANT_ID'] ?? $tenantId));
        $row = $this->currentYearBalance($userId, $effectiveTenantId, $asOf, false);
        $calculatedAmount = $this->calculateEntitlement((string)($user['ENTRY_DATE'] ?? ''), $asOf);

        return $this->effectiveBalance($row, $user, $effectiveTenantId, $calculatedAmount, false);
    }

    /**
     * Creates a missing balance or fills an untouched zero/zero balance for the current year.
     * Rows with either a non-zero amount or a non-zero used amount are never changed.
     *
     * @return array<string, mixed>
     */
    public function ensureCurrentYearBalance(
        string $userId,
        string $tenantId = '',
        string $operatorId = '',
        ?DateTimeImmutable $asOf = null
    ): array {
        $asOf = $this->asOfDate($asOf);

        return Db::transaction(function () use ($userId, $tenantId, $operatorId, $asOf): array {
            $user = $this->activeUser($userId, $tenantId, true);
            $effectiveTenantId = trim((string)($user['TENANT_ID'] ?? $tenantId));
            if ($effectiveTenantId === '') {
                $effectiveTenantId = '1';
            }

            $row = $this->currentYearBalance($userId, $effectiveTenantId, $asOf, true);
            $calculatedAmount = $this->calculateEntitlement((string)($user['ENTRY_DATE'] ?? ''), $asOf);

            if (is_array($row) && $row !== []) {
                if (!$this->isUntouchedZeroBalance($row) || (float)$calculatedAmount <= 0) {
                    return $this->effectiveBalance($row, $user, $effectiveTenantId, $calculatedAmount, false);
                }

                $now = date('Y-m-d H:i:s');
                Db::name('biz_user_vacation')
                    ->where('ID', (string)$row['ID'])
                    ->update([
                        'AMOUNT' => $calculatedAmount,
                        'UPDATE_TIME' => $now,
                        'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
                        'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                    ]);

                $row['AMOUNT'] = $calculatedAmount;
                $row['UPDATE_TIME'] = $now;
                $row['UPDATE_USER'] = $operatorId !== '' ? $operatorId : null;
                $row['VERSION'] = (int)($row['VERSION'] ?? 0) + 1;

                return $this->effectiveBalance($row, $user, $effectiveTenantId, $calculatedAmount, true);
            }

            if ((float)$calculatedAmount <= 0) {
                return $this->effectiveBalance(null, $user, $effectiveTenantId, $calculatedAmount, false);
            }

            $id = $this->newId();
            $now = date('Y-m-d H:i:s');
            $row = [
                'ID' => $id,
                'USER_ID' => $userId,
                'AMOUNT' => $calculatedAmount,
                'USED_AMOUNT' => '0.00',
                'CATEGORY' => self::CATEGORY,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $operatorId !== '' ? $operatorId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $effectiveTenantId,
                'VERSION' => 0,
            ];
            Db::name('biz_user_vacation')->insert($row);

            return $this->effectiveBalance($row, $user, $effectiveTenantId, $calculatedAmount, true);
        });
    }

    private function asOfDate(?DateTimeImmutable $asOf): DateTimeImmutable
    {
        return ($asOf ?? new DateTimeImmutable('now'))->setTime(0, 0);
    }

    private function entryDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date instanceof DateTimeImmutable || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date;
    }

    private function anniversary(DateTimeImmutable $entry, int $years): DateTimeImmutable
    {
        $year = (int)$entry->format('Y') + $years;
        $month = (int)$entry->format('n');
        $day = (int)$entry->format('j');
        $firstOfMonth = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $lastDay = (int)$firstOfMonth->modify('last day of this month')->format('j');

        return $firstOfMonth->setDate($year, $month, min($day, $lastDay));
    }

    private function completedServiceYears(DateTimeImmutable $entry, DateTimeImmutable $asOf): int
    {
        $years = (int)$asOf->format('Y') - (int)$entry->format('Y');
        if ($years <= 0) {
            return 0;
        }

        if ($asOf < $this->anniversary($entry, $years)) {
            --$years;
        }

        return max(0, $years);
    }

    /**
     * @return array<string, mixed>
     */
    private function activeUser(string $userId, string $tenantId, bool $lock): array
    {
        $userId = trim($userId);
        if ($userId === '') {
            throw new RuntimeException('missing annual leave userId', 400);
        }

        $query = Db::name('sys_user')
            ->where('ID', $userId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->field('ID,NAME,ENTRY_DATE,TENANT_ID');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }
        if ($lock) {
            $query->lock(true);
        }

        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('annual leave user not found', 404);
        }

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentYearBalance(
        string $userId,
        string $tenantId,
        DateTimeImmutable $asOf,
        bool $lock
    ): ?array {
        $query = Db::name('biz_user_vacation')
            ->where('USER_ID', $userId)
            ->where('CATEGORY', self::CATEGORY)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->whereBetweenTime(
                'CREATE_TIME',
                $asOf->format('Y-01-01 00:00:00'),
                $asOf->format('Y-12-31 23:59:59')
            );
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }
        if ($lock) {
            $query->lock(true);
        }

        $row = $query
            ->field('ID,USER_ID,AMOUNT,USED_AMOUNT,CATEGORY,DELETE_FLAG,CREATE_TIME,CREATE_USER,UPDATE_TIME,UPDATE_USER,TENANT_ID,VERSION')
            ->order('CREATE_TIME', 'desc')
            ->order('ID', 'desc')
            ->find();

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function isUntouchedZeroBalance(array $row): bool
    {
        return abs((float)($row['AMOUNT'] ?? 0)) < 0.00001
            && abs((float)($row['USED_AMOUNT'] ?? 0)) < 0.00001;
    }

    /**
     * @param array<string, mixed>|null $row
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function effectiveBalance(
        ?array $row,
        array $user,
        string $tenantId,
        string $calculatedAmount,
        bool $generated
    ): array {
        $preserve = is_array($row) && !$this->isUntouchedZeroBalance($row);
        $amount = $preserve
            ? number_format((float)($row['AMOUNT'] ?? 0), 2, '.', '')
            : $calculatedAmount;
        $usedAmount = number_format((float)($row['USED_AMOUNT'] ?? 0), 2, '.', '');

        return [
            'id' => is_array($row) ? (string)($row['ID'] ?? '') : null,
            'userId' => (string)($user['ID'] ?? ''),
            'userName' => (string)($user['NAME'] ?? ''),
            'tenantId' => $tenantId,
            'entryDate' => (string)($user['ENTRY_DATE'] ?? ''),
            'amount' => $amount,
            'usedAmount' => $usedAmount,
            'remainingAmount' => number_format(max(0, (float)$amount - (float)$usedAmount), 2, '.', ''),
            'generated' => $generated,
            'persisted' => is_array($row),
        ];
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }
}
