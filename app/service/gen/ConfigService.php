<?php

declare(strict_types=1);

namespace app\service\gen;

use RuntimeException;
use think\facade\Db;

/**
 * Generator field configuration queries and metadata saves compatible with Java GenConfigController.
 */
class ConfigService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const REQUIRED_EDIT_FIELDS = [
        'basicId',
        'isTableKey',
        'fieldName',
        'fieldRemark',
        'fieldType',
        'fieldJavaType',
        'effectType',
        'whetherTable',
        'whetherRetract',
        'whetherAddUpdate',
        'whetherRequired',
        'queryWhether',
    ];
    private const EDIT_FIELD_MAP = [
        'basicId' => 'BASIC_ID',
        'isTableKey' => 'IS_TABLE_KEY',
        'fieldName' => 'FIELD_NAME',
        'fieldRemark' => 'FIELD_REMARK',
        'fieldType' => 'FIELD_TYPE',
        'fieldJavaType' => 'FIELD_JAVA_TYPE',
        'effectType' => 'EFFECT_TYPE',
        'dictTypeCode' => 'DICT_TYPE_CODE',
        'whetherTable' => 'WHETHER_TABLE',
        'whetherRetract' => 'WHETHER_RETRACT',
        'whetherAddUpdate' => 'WHETHER_ADD_UPDATE',
        'whetherRequired' => 'WHETHER_REQUIRED',
        'queryWhether' => 'QUERY_WHETHER',
        'queryType' => 'QUERY_TYPE',
        'sortCode' => 'SORT_CODE',
    ];
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'basicId' => 'BASIC_ID',
        'isTableKey' => 'IS_TABLE_KEY',
        'fieldName' => 'FIELD_NAME',
        'fieldRemark' => 'FIELD_REMARK',
        'fieldType' => 'FIELD_TYPE',
        'fieldJavaType' => 'FIELD_JAVA_TYPE',
        'effectType' => 'EFFECT_TYPE',
        'dictTypeCode' => 'DICT_TYPE_CODE',
        'whetherTable' => 'WHETHER_TABLE',
        'whetherRetract' => 'WHETHER_RETRACT',
        'whetherAddUpdate' => 'WHETHER_ADD_UPDATE',
        'whetherRequired' => 'WHETHER_REQUIRED',
        'queryWhether' => 'QUERY_WHETHER',
        'queryType' => 'QUERY_TYPE',
        'sortCode' => 'SORT_CODE',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $basicId = trim((string)($filters['basicId'] ?? ''));
        if ($basicId === '') {
            return [];
        }

        $rows = $this->applySort($this->configQuery($filters), $filters)
            ->limit(500)
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->configRow($row), $rows);
    }

    public function detail(string $id): ?array
    {
        $row = $this->configQuery(['id' => $id])->find();
        if (!$row) {
            return null;
        }

        return $this->configRow(is_array($row) ? $row : $row->toArray());
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function edit(array $input, array $payload = []): ?array
    {
        $update = $this->editPayload($input);
        $this->assertActiveConfigIds([$update['id']]);

        Db::transaction(function () use ($update, $payload): void {
            $id = $update['id'];
            unset($update['id']);
            $update['UPDATE_TIME'] = date('Y-m-d H:i:s');
            $update['UPDATE_USER'] = $this->payloadUserId($payload);

            Db::name('gen_config')
                ->where('ID', $id)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update($update);
        });

        return null;
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function delete(array $input, array $payload = []): ?array
    {
        $ids = $this->deleteIds($input);
        if ($ids === []) {
            throw new RuntimeException('missing id', 400);
        }
        $this->assertActiveConfigIds($ids);

        Db::transaction(function () use ($ids, $payload): void {
            Db::name('gen_config')
                ->whereIn('ID', $ids)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $this->payloadUserId($payload),
                ]);
        });

        return null;
    }

    /**
     * @param array<string|int, mixed> $items
     * @param array<string, mixed> $payload
     */
    public function editBatch(array $items, array $payload = []): ?array
    {
        if (!$this->isList($items) || $items === []) {
            throw new RuntimeException('missing config list', 400);
        }

        $updates = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('invalid config item', 400);
            }

            $updates[] = $this->editPayload($item);
        }

        $ids = array_values(array_unique(array_map(static fn (array $update): string => $update['id'], $updates)));
        $this->assertActiveConfigIds($ids);

        Db::transaction(function () use ($updates, $payload): void {
            $now = date('Y-m-d H:i:s');
            $userId = $this->payloadUserId($payload);
            foreach ($updates as $update) {
                $id = $update['id'];
                unset($update['id']);
                $update['UPDATE_TIME'] = $now;
                $update['UPDATE_USER'] = $userId;

                Db::name('gen_config')
                    ->where('ID', $id)
                    ->where(function ($query): void {
                        $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                    })
                    ->update($update);
            }
        });

        return null;
    }

    private function configQuery(array $filters)
    {
        $query = Db::name('gen_config')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['basicId'])) {
            $query->where('BASIC_ID', (string)$filters['basicId']);
        }

        if (!empty($filters['fieldName'])) {
            $query->whereLike('FIELD_NAME', '%' . trim((string)$filters['fieldName']) . '%');
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

    /**
     * @param array<string|int, mixed> $item
     * @return array<string, mixed>
     */
    private function editPayload(array $item): array
    {
        $payload = ['id' => $this->requiredValue($item, 'id')];
        $this->assertMaxLength($payload['id'], 'id', 20);

        foreach (self::REQUIRED_EDIT_FIELDS as $field) {
            $payload[self::EDIT_FIELD_MAP[$field]] = $this->requiredValue($item, $field);
        }

        $payload['DICT_TYPE_CODE'] = $this->optionalString($item, 'dictTypeCode');
        $payload['QUERY_TYPE'] = $this->optionalString($item, 'queryType');
        $payload['SORT_CODE'] = $this->optionalInt($item, 'sortCode');

        return $payload;
    }

    /**
     * @param array<int, string> $ids
     */
    private function assertActiveConfigIds(array $ids): void
    {
        if ($ids === []) {
            throw new RuntimeException('missing id', 400);
        }

        $existingIds = Db::name('gen_config')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->column('ID');
        $existingIds = array_map('strval', $existingIds);
        foreach ($ids as $id) {
            if (!in_array($id, $existingIds, true)) {
                throw new RuntimeException('config not found', 404);
            }
        }
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array<int, string>
     */
    private function deleteIds(array $input): array
    {
        $source = null;
        if ($this->isList($input)) {
            $source = $input;
        } elseif (array_key_exists('idList', $input)) {
            $source = $input['idList'];
        } elseif (array_key_exists('ids', $input)) {
            $source = $input['ids'];
        } elseif (array_key_exists('id', $input)) {
            $source = [$input['id']];
        }

        if (is_string($source)) {
            $source = explode(',', $source);
        }
        if (!is_array($source)) {
            return [];
        }

        $ids = [];
        foreach ($source as $item) {
            $id = is_array($item) ? (string)($item['id'] ?? $item['ID'] ?? '') : (string)$item;
            $id = trim($id);
            if ($id === '') {
                continue;
            }
            $this->assertMaxLength($id, 'id', 20);
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<string|int, mixed> $item
     */
    private function requiredValue(array $item, string $field): string
    {
        $value = $this->fieldValue($item, $field);
        if (is_bool($value)) {
            return $value ? 'Y' : 'N';
        }

        $value = trim((string)$value);
        if ($value === '') {
            throw new RuntimeException('missing ' . $field, 400);
        }

        return $value;
    }

    private function assertMaxLength(string $value, string $label, int $maxLength): void
    {
        if (strlen($value) > $maxLength) {
            throw new RuntimeException($label . ' is too long', 400);
        }
    }

    /**
     * @param array<string|int, mixed> $item
     */
    private function optionalString(array $item, string $field): ?string
    {
        $value = $this->fieldValue($item, $field);
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string|int, mixed> $item
     */
    private function optionalInt(array $item, string $field): ?int
    {
        $value = $this->fieldValue($item, $field);
        if ($value === null || trim((string)$value) === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new RuntimeException('invalid ' . $field, 400);
        }

        return (int)$value;
    }

    /**
     * @param array<string|int, mixed> $item
     */
    private function fieldValue(array $item, string $field): mixed
    {
        if (array_key_exists($field, $item)) {
            return $item[$field];
        }

        $column = self::EDIT_FIELD_MAP[$field] ?? strtoupper((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $field));
        if (array_key_exists($column, $item)) {
            return $item[$column];
        }

        return null;
    }

    private function isList(array $items): bool
    {
        if ($items === []) {
            return true;
        }

        return array_keys($items) === range(0, count($items) - 1);
    }

    private function configRow(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'basicId' => $row['BASIC_ID'] ?? null,
            'isTableKey' => $row['IS_TABLE_KEY'] ?? null,
            'fieldName' => $row['FIELD_NAME'] ?? null,
            'fieldRemark' => $row['FIELD_REMARK'] ?? null,
            'fieldType' => $row['FIELD_TYPE'] ?? null,
            'fieldJavaType' => $row['FIELD_JAVA_TYPE'] ?? null,
            'effectType' => $row['EFFECT_TYPE'] ?? null,
            'dictTypeCode' => $row['DICT_TYPE_CODE'] ?? null,
            'whetherTable' => $row['WHETHER_TABLE'] ?? null,
            'whetherRetract' => $row['WHETHER_RETRACT'] ?? null,
            'whetherAddUpdate' => $row['WHETHER_ADD_UPDATE'] ?? null,
            'whetherRequired' => $row['WHETHER_REQUIRED'] ?? null,
            'queryWhether' => $row['QUERY_WHETHER'] ?? null,
            'queryType' => $row['QUERY_TYPE'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
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
}
