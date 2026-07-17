<?php

declare(strict_types=1);

namespace app\service\biz;

use app\support\FileDownloadUrl;
use app\support\SensitiveFieldCodec;
use RuntimeException;
use think\facade\Db;

/**
 * Read-only customer queries compatible with Java CustomerController.
 */
class CustomerService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';

    private const CUSTOMER_FIELDS = <<<SQL
c.ID AS ID,
c.NAME AS NAME,
c.CONTACTS AS CONTACTS,
c.PHONE AS PHONE,
c.DETAILS_ADDRESS AS DETAILS_ADDRESS,
c.ADDRESS AS ADDRESS,
c.SOURCE_TYPE AS SOURCE_TYPE,
c.CUSTOM_TYPE AS CUSTOM_TYPE,
c.ORG AS ORG,
c.USER AS USER_ID,
c.STATUS AS STATUS,
c.SORT_CODE AS SORT_CODE,
c.DELETE_FLAG AS DELETE_FLAG,
c.CREATE_TIME AS CREATE_TIME,
c.CREATE_USER AS CREATE_USER,
c.UPDATE_TIME AS UPDATE_TIME,
c.UPDATE_USER AS UPDATE_USER,
c.EXT_JSON AS EXT_JSON,
c.TENANT_ID AS TENANT_ID,
c.FILE_ID AS FILE_ID,
c.VERSION AS VERSION,
c.DEAL_AMOUNT AS DEAL_AMOUNT,
c.remark AS REMARK,
c.FIRST_CONTACT_TIME AS FIRST_CONTACT_TIME,
head.NAME AS HEAD_NAME,
org.NAME AS ORG_NAME,
creator.NAME AS CREATE_USER_NAME,
df.ENGINE AS FILE_ENGINE,
df.DOWNLOAD_PATH AS DOWNLOAD_PATH
SQL;

    private const SORT_FIELD_MAP = [
        'id' => 'c.ID',
        'name' => 'c.NAME',
        'contacts' => 'c.CONTACTS',
        'phone' => 'c.PHONE',
        'address' => 'c.ADDRESS',
        'detailsAddress' => 'c.DETAILS_ADDRESS',
        'sourceType' => 'c.SOURCE_TYPE',
        'customType' => 'c.CUSTOM_TYPE',
        'org' => 'c.ORG',
        'orgName' => 'org.NAME',
        'user' => 'c.USER',
        'headName' => 'head.NAME',
        'status' => 'c.STATUS',
        'sortCode' => 'c.SORT_CODE',
        'createTime' => 'c.CREATE_TIME',
        'createUserName' => 'creator.NAME',
        'firstContactTime' => 'c.FIRST_CONTACT_TIME',
        'tenantId' => 'c.TENANT_ID',
    ];

    private readonly SensitiveFieldCodec $sensitiveFields;

    public function __construct(
        private readonly CustomerFollowUpService $followUpService = new CustomerFollowUpService(),
        ?SensitiveFieldCodec $sensitiveFields = null
    ) {
        $this->sensitiveFields = $sensitiveFields ?? new SensitiveFieldCodec();
    }

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);

        if ($this->needsInMemorySensitiveHandling($filters)) {
            $rows = $this->applySort($this->customerQuery($filters, $payload, true), $filters)
                ->field(self::CUSTOMER_FIELDS)
                ->select()
                ->toArray();
            $records = $this->filterAndSortSensitiveRows($this->customerRows($rows), $filters);
            $total = count($records);

            return [
                'records' => array_slice($records, ($page - 1) * $limit, $limit),
                'total' => $total,
                'page' => $page,
                'current' => $page,
                'limit' => $limit,
                'size' => $limit,
                'pages' => (int)ceil($total / $limit),
            ];
        }

        $total = (int)$this->customerQuery($filters, $payload, true)->count('DISTINCT c.ID');
        $rows = $this->applySort($this->customerQuery($filters, $payload, true), $filters)
            ->field(self::CUSTOMER_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->customerRows($rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->customerQuery(['id' => $id], $payload, true)
            ->field(self::CUSTOMER_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('customer not found', 404);
        }

        return $this->customerRows([$row])[0];
    }

    /**
     * @return array<int, array{customer: array<string, mixed>, customerFollowUps: array<int, array<string, mixed>>}>
     */
    public function detailList(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->customerQuery($filters, $payload, true), $filters)
            ->field(self::CUSTOMER_FIELDS)
            ->select()
            ->toArray();
        $customers = $this->filterAndSortSensitiveRows($this->customerRows($rows), $filters);
        $followUps = $this->followUpService->listByCustomerIds(array_column($customers, 'id'), $payload);

        return array_map(static fn (array $customer): array => [
            'customer' => $customer,
            'customerFollowUps' => $followUps[(string)$customer['id']] ?? [],
        ], $customers);
    }

    public function add(array $input, array $payload = []): array
    {
        return Db::transaction(function () use ($input, $payload): array {
            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            $id = $this->newId();
            $row = [
                'ID' => $id,
                'ORG' => $this->defaultOrgId($input, $payload),
                'USER' => $this->defaultUserId($input, $payload),
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $this->tenantId($input, $payload),
                'VERSION' => 0,
                'DEAL_AMOUNT' => 0,
            ];

            $this->applyCustomerInput($row, $input, false);
            $this->assertNewCustomerWritable($row, $payload);

            Db::name('customer')->insert($row);

            return ['id' => $id];
        });
    }

    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');

        return Db::transaction(function () use ($id, $input, $payload): array {
            $this->assertCustomerWritable($id, $payload, 'edit');
            $userId = $this->currentUserId($payload);
            $row = [
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $userId !== '' ? $userId : null,
                'VERSION' => Db::raw('IFNULL(VERSION, 0) + 1'),
            ];

            $this->applyCustomerInput($row, $input, true);

            $updated = Db::name('customer')
                ->where('ID', $id)
                ->update($row);

            return ['id' => $id, 'count' => $updated];
        });
    }

    /**
     * @param array<int, mixed> $ids
     */
    public function delete(array $ids, array $payload = []): array
    {
        $idList = $this->stringList($ids);
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }

        return Db::transaction(function () use ($idList, $payload): array {
            foreach ($idList as $id) {
                $this->assertCustomerWritable($id, $payload, 'delete');
            }

            $userId = $this->currentUserId($payload);
            $updated = Db::name('customer')
                ->whereIn('ID', $idList)
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                    'VERSION' => Db::raw('IFNULL(VERSION, 0) + 1'),
                ]);

            return ['ids' => $idList, 'count' => $updated];
        });
    }

    public function headEdit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');
        $targetUserId = $this->requiredInput($input, 'user');

        return Db::transaction(function () use ($id, $targetUserId, $payload): array {
            $this->assertCustomerWritable($id, $payload, 'edit customer head');
            $targetUser = $this->assignableUser($targetUserId, $payload);
            $currentUserId = $this->currentUserId($payload);

            $updated = Db::name('customer')
                ->where('ID', $id)
                ->update([
                    'USER' => $targetUserId,
                    'ORG' => $targetUser['ORG_ID'],
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                    'VERSION' => Db::raw('IFNULL(VERSION, 0) + 1'),
                ]);

            return [
                'id' => $id,
                'user' => $targetUserId,
                'org' => $targetUser['ORG_ID'],
                'count' => $updated,
            ];
        });
    }

    private function applyCustomerInput(array &$row, array $input, bool $partial): void
    {
        $fields = [
            'name' => 'NAME',
            'contacts' => 'CONTACTS',
            'phone' => 'PHONE',
            'detailsAddress' => 'DETAILS_ADDRESS',
            'address' => 'ADDRESS',
            'sourceType' => 'SOURCE_TYPE',
            'customType' => 'CUSTOM_TYPE',
            'status' => 'STATUS',
            'sortCode' => 'SORT_CODE',
            'fileId' => 'FILE_ID',
            'remark' => 'remark',
            'firstContactTime' => 'FIRST_CONTACT_TIME',
            'extJson' => 'EXT_JSON',
        ];

        foreach ($fields as $inputKey => $column) {
            if (!array_key_exists($inputKey, $input)) {
                continue;
            }

            $row[$column] = match ($inputKey) {
                'address' => $this->addressValue($input[$inputKey]),
                'sortCode' => $this->nullableInt($input[$inputKey]),
                'fileId' => $this->nullableString($input[$inputKey]),
                'firstContactTime' => $this->nullableString($input[$inputKey]),
                default => $this->nullableString($input[$inputKey]),
            };
        }

        $row = $this->sensitiveFields->encodeRow('customer', $row);
    }

    private function assertCustomerWritable(string $customerId, array $payload, string $action): array
    {
        $row = $this->customerQuery(['id' => $customerId], $payload, true)
            ->field('c.ID, c.ORG, c.USER AS USER_ID, c.TENANT_ID')
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('customer not found', 404);
        }

        if ($this->canSeeAll($payload)) {
            return $row;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $customerOrg = trim((string)($row['ORG'] ?? ''));
        if ($scopeOrgIds !== [] && in_array($customerOrg, $scopeOrgIds, true)) {
            return $row;
        }

        $customerUser = trim((string)($row['USER_ID'] ?? ''));
        $userId = $this->currentUserId($payload);
        if ($userId !== '' && $customerUser === $userId) {
            return $row;
        }

        throw new RuntimeException("no permission to {$action} this customer", 403);
    }

    private function assertNewCustomerWritable(array $row, array $payload): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $orgId = trim((string)($row['ORG'] ?? ''));
        if ($scopeOrgIds !== [] && in_array($orgId, $scopeOrgIds, true)) {
            return;
        }

        $currentUserId = $this->currentUserId($payload);
        $ownerUserId = trim((string)($row['USER'] ?? ''));
        if ($currentUserId !== '' && $ownerUserId === $currentUserId) {
            return;
        }

        throw new RuntimeException('no permission to add this customer', 403);
    }

    private function assignableUser(string $userId, array $payload): array
    {
        $query = Db::name('sys_user')
            ->where('ID', $userId)
            ->field('ID, ORG_ID, DELETE_FLAG');
        $this->whereNotDeleted($query, 'DELETE_FLAG');

        if (!$this->canSeeAll($payload)) {
            $scopeOrgIds = $this->scopeOrgIds($payload);
            if ($scopeOrgIds !== []) {
                $query->whereIn('ORG_ID', $scopeOrgIds);
            } else {
                $currentUserId = $this->currentUserId($payload);
                if ($currentUserId !== '') {
                    $query->where('ID', $currentUserId);
                }
            }
        }

        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('target user not found', 404);
        }

        $orgId = trim((string)($row['ORG_ID'] ?? ''));
        if ($orgId === '') {
            throw new RuntimeException('target user has no organization', 400);
        }

        return [
            'ID' => (string)$row['ID'],
            'ORG_ID' => $orgId,
        ];
    }

    private function customerQuery(array $filters, array $payload, bool $applyDataScope)
    {
        $query = Db::name('customer')
            ->alias('c')
            ->leftJoin('sys_user head', 'head.ID = c.USER')
            ->leftJoin('sys_org org', 'org.ID = c.ORG')
            ->leftJoin('sys_user creator', 'creator.ID = c.CREATE_USER')
            ->leftJoin('dev_file df', 'df.ID = c.FILE_ID');
        $this->whereNotDeleted($query, 'c.DELETE_FLAG');

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('c.TENANT_ID', $tenantId);
        }

        foreach ([
            'id' => 'c.ID',
            'status' => 'c.STATUS',
            'sourceType' => 'c.SOURCE_TYPE',
            'customType' => 'c.CUSTOM_TYPE',
            'org' => 'c.ORG',
            'user' => 'c.USER',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        foreach ([
            'name' => 'c.NAME',
            'contacts' => 'c.CONTACTS',
            'address' => 'c.ADDRESS',
            'remark' => 'c.remark',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->whereLike($column, '%' . trim((string)$filters[$filter]) . '%');
            }
        }

        if (!empty($filters['phone'])) {
            $query->where(
                'c.PHONE',
                $this->sensitiveFields->lookupValue('customer', 'PHONE', trim((string)$filters['phone']))
            );
        }

        if (!empty($filters['headName'])) {
            $this->applyUserNameFilter($query, (string)$filters['headName'], 'c.USER');
        }

        if (!empty($filters['createUserName'])) {
            $this->applyUserNameFilter($query, (string)$filters['createUserName'], 'c.CREATE_USER');
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->whereRaw(
                '(c.NAME LIKE ? OR c.CONTACTS LIKE ? OR c.ADDRESS LIKE ? OR head.NAME LIKE ? OR creator.NAME LIKE ?)',
                [$keyword, $keyword, $keyword, $keyword, $keyword]
            );
        }

        if ($this->truthy($filters['showRepeat'] ?? false)) {
            $query->whereRaw(
                'c.PHONE IN (SELECT PHONE FROM customer WHERE PHONE IS NOT NULL AND (DELETE_FLAG IS NULL OR DELETE_FLAG = ?) GROUP BY PHONE HAVING COUNT(*) > 1)',
                [self::NOT_DELETE]
            );
        }

        $this->applyTimeRange($query, 'c.CREATE_TIME', $filters['startCreateTime'] ?? '', $filters['endCreateTime'] ?? '');
        $this->applyTimeRange($query, 'c.FIRST_CONTACT_TIME', $filters['startFirstContactTime'] ?? '', $filters['endFirstContactTime'] ?? '');

        if ($applyDataScope) {
            $this->applyDataScope($query, $filters, $payload);
        }

        return $query;
    }

    private function applyUserNameFilter($query, string $name, string $targetColumn): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $userIds = Db::name('sys_user')
            ->whereLike('NAME', '%' . $name . '%')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->column('ID');

        if ($userIds !== []) {
            $query->whereIn($targetColumn, array_map('strval', $userIds));

            return;
        }

        $query->where($targetColumn, $name);
    }

    private function applyDataScope($query, array $filters, array $payload): void
    {
        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            $orgIds === [] ? $query->whereRaw('1 = 0') : $query->whereIn('c.ORG', $orgIds);

            return;
        }

        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== []) {
            $query->whereIn('c.ORG', $scopeOrgIds);

            return;
        }

        $userId = $this->currentUserId($payload);
        if ($userId !== '') {
            $query->where('c.USER', $userId);
        }
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField]) && !in_array($sortField, ['phone', 'detailsAddress'], true)) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('c.ID', 'asc');
        }

        return $query->order('c.SORT_CODE', 'asc')->order('c.ID', 'asc');
    }

    private function applyTimeRange($query, string $column, mixed $startValue, mixed $endValue): void
    {
        $start = trim((string)$startValue);
        $end = trim((string)$endValue);
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime($column, $start, $end);
        } elseif ($start !== '') {
            $query->whereTime($column, '>=', $start);
        } elseif ($end !== '') {
            $query->whereTime($column, '<=', $end);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function customerRows(array $rows): array
    {
        return array_map(function (array $row): array {
            $row = $this->sensitiveFields->decodeRow('customer', $row);

            return [
                'id' => $this->value($row, 'ID', 'id'),
                'name' => $this->value($row, 'NAME', 'name'),
                'contacts' => $this->value($row, 'CONTACTS', 'contacts'),
                'phone' => $this->value($row, 'PHONE', 'phone'),
                'detailsAddress' => $this->value($row, 'DETAILS_ADDRESS', 'detailsAddress'),
                'address' => $this->value($row, 'ADDRESS', 'address'),
                'sourceType' => $this->value($row, 'SOURCE_TYPE', 'sourceType'),
                'customType' => $this->value($row, 'CUSTOM_TYPE', 'customType'),
                'org' => $this->value($row, 'ORG', 'org'),
                'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
                'user' => $this->value($row, 'USER_ID', 'user'),
                'headName' => $this->value($row, 'HEAD_NAME', 'headName'),
                'status' => $this->value($row, 'STATUS', 'status'),
                'sortCode' => $this->integer($this->value($row, 'SORT_CODE', 'sortCode')),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'createUserName' => $this->value($row, 'CREATE_USER_NAME', 'createUserName'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
                'fileId' => $this->value($row, 'FILE_ID', 'fileId'),
                'downloadPath' => FileDownloadUrl::normalize(
                    $this->value($row, 'FILE_ID', 'fileId'),
                    $this->value($row, 'FILE_ENGINE', 'fileEngine'),
                    $this->value($row, 'DOWNLOAD_PATH', 'downloadPath')
                ),
                'version' => $this->integer($this->value($row, 'VERSION', 'version')),
                'dealAmount' => $this->decimal($this->value($row, 'DEAL_AMOUNT', 'dealAmount')),
                'remark' => $this->value($row, 'REMARK', 'remark'),
                'firstContactTime' => $this->value($row, 'FIRST_CONTACT_TIME', 'firstContactTime'),
            ];
        }, $rows);
    }

    private function needsInMemorySensitiveHandling(array $filters): bool
    {
        $detailsAddress = trim((string)($filters['detailsAddress'] ?? ''));
        $sortField = trim((string)($filters['sortField'] ?? ''));

        return $detailsAddress !== '' || in_array($sortField, ['phone', 'detailsAddress'], true);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function filterAndSortSensitiveRows(array $rows, array $filters): array
    {
        $needle = trim((string)($filters['detailsAddress'] ?? ''));
        if ($needle !== '') {
            $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
                $address = (string)($row['detailsAddress'] ?? '');

                return function_exists('mb_stripos')
                    ? mb_stripos($address, $needle, 0, 'UTF-8') !== false
                    : stripos($address, $needle) !== false;
            }));
        }

        $sortField = trim((string)($filters['sortField'] ?? ''));
        if (!in_array($sortField, ['phone', 'detailsAddress'], true)) {
            return $rows;
        }

        $descending = in_array(
            strtolower(trim((string)($filters['sortOrder'] ?? ''))),
            ['desc', 'descend', 'descending'],
            true
        );
        usort($rows, static function (array $left, array $right) use ($sortField, $descending): int {
            $comparison = strcmp((string)($left[$sortField] ?? ''), (string)($right[$sortField] ?? ''));
            if ($comparison !== 0) {
                return $descending ? -$comparison : $comparison;
            }

            return strcmp((string)($left['id'] ?? ''), (string)($right['id'] ?? ''));
        });

        return $rows;
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
    }

    /**
     * @return array<int, string>
     */
    private function orgAndChildren(string $orgId): array
    {
        $orgId = trim($orgId);
        if ($orgId === '') {
            return [];
        }

        $rows = Db::name('sys_org')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->field(['ID', 'PARENT_ID'])
            ->select()
            ->toArray();

        $childrenByParent = [];
        foreach ($rows as $row) {
            $childrenByParent[(string)($row['PARENT_ID'] ?? '')][] = (string)$row['ID'];
        }

        $result = [];
        $queue = [$orgId];
        while ($queue !== []) {
            $current = array_shift($queue);
            if ($current === null || in_array($current, $result, true)) {
                continue;
            }

            $result[] = $current;
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function scopeOrgIds(array $payload): array
    {
        $ids = [];
        $direct = $payload['data_scope_org_ids'] ?? [];
        if (is_string($direct)) {
            $direct = explode(',', $direct);
        }
        if (is_array($direct)) {
            $ids = array_merge($ids, $direct);
        }

        $scopes = $payload['data_scopes'] ?? $payload['dataScopeList'] ?? [];
        if (is_array($scopes)) {
            foreach ($scopes as $scope) {
                if (is_array($scope)) {
                    $ids[] = $scope['orgId'] ?? $scope['org_id'] ?? '';
                }
            }
        }

        return array_values(array_unique(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $ids))));
    }

    private function canSeeAll(array $payload): bool
    {
        $account = strtolower((string)($payload['account'] ?? ''));
        if (in_array($account, ['bizadmin', 'superadmin'], true)) {
            return true;
        }

        $roleCodes = $payload['role_codes'] ?? [];
        if (!is_array($roleCodes)) {
            return false;
        }

        foreach ($roleCodes as $roleCode) {
            if (in_array(strtolower((string)$roleCode), ['superadmin', 'tenantadmin', 'bizadmin'], true)) {
                return true;
            }
        }

        return false;
    }

    private function currentUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'y', 'on'], true);
    }

    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = is_array($value) ? implode(',', array_map('strval', $value)) : (string)$value;

        return $value !== '' ? $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = trim((string)$value);

        return $value !== '' ? (int)$value : null;
    }

    private function addressValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = implode('/', array_filter(array_map(static fn (mixed $part): string => trim((string)$part), $value)));
        }

        return $this->nullableString($value);
    }

    private function defaultUserId(array $input, array $payload): ?string
    {
        $userId = trim((string)($input['user'] ?? $input['userId'] ?? ''));
        if ($userId !== '') {
            return $userId;
        }

        $currentUserId = $this->currentUserId($payload);

        return $currentUserId !== '' ? $currentUserId : null;
    }

    private function defaultOrgId(array $input, array $payload): ?string
    {
        $orgId = trim((string)($input['org'] ?? $input['orgId'] ?? $payload['org_id'] ?? $payload['orgId'] ?? ''));
        if ($orgId !== '') {
            return $orgId;
        }

        $userId = $this->currentUserId($payload);
        if ($userId === '') {
            return null;
        }

        $user = Db::name('sys_user')
            ->where('ID', $userId)
            ->field('ORG_ID')
            ->find();
        if (!is_array($user) || $user === []) {
            return null;
        }

        $orgId = trim((string)($user['ORG_ID'] ?? ''));

        return $orgId !== '' ? $orgId : null;
    }

    private function tenantId(array $input, array $payload): ?string
    {
        $tenantId = trim((string)($input['tenantId'] ?? $input['tenant_id'] ?? $payload['tenant_id'] ?? $payload['tenantId'] ?? ''));

        return $tenantId !== '' ? $tenantId : '1';
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static function (mixed $item): string {
            if (is_array($item)) {
                return trim((string)($item['id'] ?? $item['ID'] ?? ''));
            }

            return trim((string)$item);
        }, $value))));
    }

    private function integer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }

    private function decimal(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float)$value;

        return fmod($number, 1.0) === 0.0 ? (int)$number : $number;
    }

    private function value(array $row, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }
}
