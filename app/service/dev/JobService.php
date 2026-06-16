<?php

declare(strict_types=1);

namespace app\service\dev;

use RuntimeException;
use think\facade\Db;

/**
 * Scheduled job metadata compatibility for Java DevJobController.
 */
class JobService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const STATUS_STOPPED = 'STOPPED';
    private const STATUS_RUNNING = 'RUNNING';
    private const CATEGORIES = ['FRM', 'BIZ'];
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'name' => 'NAME',
        'code' => 'CODE',
        'category' => 'CATEGORY',
        'actionClass' => 'ACTION_CLASS',
        'cronExpression' => 'CRON_EXPRESSION',
        'jobStatus' => 'JOB_STATUS',
        'sortCode' => 'SORT_CODE',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    public function page(array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->jobQuery($filters)->count();
        $rows = $this->applySort($this->jobQuery($filters), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->jobRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $rows = $this->applySort($this->jobQuery($filters), $filters)
            ->limit(500)
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->jobRow($row), $rows);
    }

    public function detail(string $id): ?array
    {
        $row = $this->jobQuery(['id' => $id])->find();
        if (!$row) {
            return null;
        }

        return $this->jobRow(is_array($row) ? $row : $row->toArray());
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function add(array $input, array $payload = []): array
    {
        $data = $this->jobPayload($input);

        return Db::transaction(function () use ($data, $payload): array {
            $this->assertActionClassAllowed($data['actionClass']);
            $this->assertNoDuplicate($data['actionClass'], $data['cronExpression']);

            $id = $this->newId();

            Db::name('dev_job')->insert([
                'ID' => $id,
                'NAME' => $data['name'],
                'CODE' => $this->randomCode(),
                'CATEGORY' => $data['category'],
                'ACTION_CLASS' => $data['actionClass'],
                'CRON_EXPRESSION' => $data['cronExpression'],
                'JOB_STATUS' => self::STATUS_STOPPED,
                'SORT_CODE' => $data['sortCode'],
                'EXT_JSON' => $data['extJson'],
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => date('Y-m-d H:i:s'),
                'CREATE_USER' => $this->payloadUserId($payload),
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
            ]);

            return ['id' => $id];
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function edit(array $input, array $payload = []): ?array
    {
        $id = $this->requiredString($input, ['id', 'ID'], 'id');
        $this->assertMaxLength($id, 'id', 20);
        $data = $this->jobPayload($input);

        return Db::transaction(function () use ($id, $data, $payload): ?array {
            $existing = $this->activeJobRow($id);
            if ((string)($existing['JOB_STATUS'] ?? '') === self::STATUS_RUNNING) {
                throw new RuntimeException('running job cannot be edited', 400);
            }

            $this->assertActionClassAllowed($data['actionClass']);
            $this->assertNoDuplicate($data['actionClass'], $data['cronExpression'], $id);

            Db::name('dev_job')
                ->where('ID', $id)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update([
                    'NAME' => $data['name'],
                    'CATEGORY' => $data['category'],
                    'ACTION_CLASS' => $data['actionClass'],
                    'CRON_EXPRESSION' => $data['cronExpression'],
                    'SORT_CODE' => $data['sortCode'],
                    'EXT_JSON' => $data['extJson'],
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $this->payloadUserId($payload),
                ]);

            return null;
        });
    }

    /**
     * @param array<int, string> $ids
     * @param array<string, mixed> $payload
     */
    public function delete(array $ids, array $payload = []): ?array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $ids))));
        if ($ids === []) {
            throw new RuntimeException('missing idList', 400);
        }

        $rows = Db::name('dev_job')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->select()
            ->toArray();
        if (count($rows) !== count($ids)) {
            throw new RuntimeException('job not found', 404);
        }

        Db::name('dev_job')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update([
                'DELETE_FLAG' => self::DELETED,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $this->payloadUserId($payload),
            ]);

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function actionClasses(): array
    {
        $classes = Db::name('dev_job')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->whereNotNull('ACTION_CLASS')
            ->where('ACTION_CLASS', '<>', '')
            ->order('ACTION_CLASS', 'asc')
            ->column('ACTION_CLASS');

        return array_values(array_unique(array_map('strval', $classes)));
    }

    private function jobQuery(array $filters)
    {
        $query = Db::name('dev_job')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['jobStatus'])) {
            $query->whereLike('JOB_STATUS', '%' . trim((string)$filters['jobStatus']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['searchKey']) . '%');
        }

        return $query;
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('ID', 'asc');
        }

        return $query->order('SORT_CODE', 'asc')->order('ID', 'asc');
    }

    private function jobRow(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'name' => $row['NAME'] ?? null,
            'code' => $row['CODE'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'actionClass' => $row['ACTION_CLASS'] ?? null,
            'cronExpression' => $row['CRON_EXPRESSION'] ?? null,
            'jobStatus' => $row['JOB_STATUS'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }

    private function payloadUserId(array $payload): ?string
    {
        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));

        return $userId === '' ? null : $userId;
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array{name: string, category: string, actionClass: string, cronExpression: string, sortCode: int, extJson: ?string}
     */
    private function jobPayload(array $input): array
    {
        $name = $this->requiredString($input, ['name', 'NAME'], 'name');
        $category = strtoupper($this->requiredString($input, ['category', 'CATEGORY'], 'category'));
        $actionClass = $this->requiredString($input, ['actionClass', 'ACTION_CLASS'], 'actionClass');
        $cronExpression = $this->requiredString($input, ['cronExpression', 'CRON_EXPRESSION'], 'cronExpression');
        $sortCode = $this->requiredInt($input, ['sortCode', 'SORT_CODE'], 'sortCode');
        $extJson = $this->nullableString($input, ['extJson', 'EXT_JSON']);

        $this->assertMaxLength($name, 'name', 255);
        $this->assertMaxLength($category, 'category', 255);
        $this->assertMaxLength($actionClass, 'actionClass', 255);
        $this->assertMaxLength($cronExpression, 'cronExpression', 255);
        $this->assertCategory($category);
        $this->assertCronExpression($cronExpression);

        return [
            'name' => $name,
            'category' => $category,
            'actionClass' => $actionClass,
            'cronExpression' => $cronExpression,
            'sortCode' => $sortCode,
            'extJson' => $extJson,
        ];
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<int, string> $keys
     */
    private function requiredString(array $input, array $keys, string $label): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $value = trim((string)$input[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        throw new RuntimeException("missing {$label}", 400);
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<int, string> $keys
     */
    private function requiredInt(array $input, array $keys, string $label): int
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $value = trim((string)$input[$key]);
                if ($value !== '' && preg_match('/^-?\d+$/', $value) === 1) {
                    return (int)$value;
                }
            }
        }

        throw new RuntimeException("missing {$label}", 400);
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<int, string> $keys
     */
    private function nullableString(array $input, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $value = $input[$key];
                if ($value === null) {
                    return null;
                }

                return (string)$value;
            }
        }

        return null;
    }

    private function assertMaxLength(string $value, string $label, int $maxLength): void
    {
        if (strlen($value) > $maxLength) {
            throw new RuntimeException("{$label} is too long", 400);
        }
    }

    private function assertCategory(string $category): void
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new RuntimeException('unsupported job category', 400);
        }
    }

    private function assertCronExpression(string $cronExpression): void
    {
        $parts = preg_split('/\s+/', trim($cronExpression));
        if (!is_array($parts) || !in_array(count($parts), [6, 7], true)) {
            throw new RuntimeException('invalid cronExpression', 400);
        }

        foreach ($parts as $part) {
            if ($part === '' || preg_match('/^[0-9A-Za-z*,?\/#LW\-]+$/', $part) !== 1) {
                throw new RuntimeException('invalid cronExpression', 400);
            }
        }

        if (count($parts) === 6 && $parts[3] !== '?' && $parts[5] !== '?') {
            throw new RuntimeException('invalid cronExpression', 400);
        }
    }

    private function assertActionClassAllowed(string $actionClass): void
    {
        if (!in_array($actionClass, $this->actionClasses(), true)) {
            throw new RuntimeException('unsupported actionClass', 400);
        }
    }

    private function assertNoDuplicate(string $actionClass, string $cronExpression, ?string $excludeId = null): void
    {
        $query = Db::name('dev_job')
            ->where('ACTION_CLASS', $actionClass)
            ->where('CRON_EXPRESSION', $cronExpression)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($excludeId !== null && $excludeId !== '') {
            $query->where('ID', '<>', $excludeId);
        }

        if ($query->count() > 0) {
            throw new RuntimeException('duplicate job actionClass and cronExpression', 400);
        }
    }

    private function activeJobRow(string $id): array
    {
        $row = Db::name('dev_job')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->find();

        if (!is_array($row) || $row === []) {
            throw new RuntimeException('job not found', 404);
        }

        return $row;
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }

    private function randomCode(): string
    {
        $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 10; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
