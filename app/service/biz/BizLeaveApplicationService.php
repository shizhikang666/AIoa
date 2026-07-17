<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Leave-application compatibility for Java BizLeaveApplicationController.
 */
class BizLeaveApplicationService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const LEAVE_CATEGORY_ANNUAL = 'annualLeave';
    private const EDITABLE_FIELDS = [
        'userId' => 'USER_ID',
        'processId' => 'PROCESS_ID',
        'category' => 'category',
        'amount' => 'AMOUNT',
        'remark' => 'REMARK',
        'startTime' => 'START_TIME',
        'endTime' => 'END_TIME',
    ];
    private const FIELDS = <<<SQL
l.ID AS ID,
l.USER_ID AS USER_ID,
l.PROCESS_ID AS PROCESS_ID,
l.`category` AS CATEGORY,
l.AMOUNT AS AMOUNT,
l.REMARK AS REMARK,
l.START_TIME AS START_TIME,
l.END_TIME AS END_TIME,
l.DELETE_FLAG AS DELETE_FLAG,
l.CREATE_TIME AS CREATE_TIME,
l.CREATE_USER AS CREATE_USER,
l.UPDATE_TIME AS UPDATE_TIME,
l.UPDATE_USER AS UPDATE_USER,
l.TENANT_ID AS TENANT_ID,
l.OBJECT_ID AS OBJECT_ID,
u.NAME AS NAME,
u.ORG_ID AS ORG_ID,
org.NAME AS ORG_NAME,
creator.NAME AS CREATE_USER_NAME,
updater.NAME AS UPDATE_USER_NAME
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'l.ID',
        'userId' => 'l.USER_ID',
        'name' => 'u.NAME',
        'orgId' => 'u.ORG_ID',
        'orgName' => 'org.NAME',
        'processId' => 'l.PROCESS_ID',
        'category' => 'l.category',
        'amount' => 'l.AMOUNT',
        'remark' => 'l.REMARK',
        'startTime' => 'l.START_TIME',
        'endTime' => 'l.END_TIME',
        'createTime' => 'l.CREATE_TIME',
        'updateTime' => 'l.UPDATE_TIME',
        'objectId' => 'l.OBJECT_ID',
    ];

    public function __construct(
        private readonly AnnualLeaveEntitlementService $annualLeaveEntitlementService = new AnnualLeaveEntitlementService()
    ) {
    }

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->leaveQuery($filters, $payload, false)->count();
        $rows = $this->applySort($this->leaveQuery($filters, $payload, false), $filters)
            ->field(self::FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->leaveRows($rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function myPage(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->leaveQuery($filters, $payload, true)->count();
        $rows = $this->applySort($this->leaveQuery($filters, $payload, true), $filters)
            ->field(self::FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->leaveRows($rows),
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
        $row = $this->leaveQuery(['id' => $id], $payload, false)
            ->field(self::FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('leave application not found', 404);
        }

        return $this->leaveRows([$row])[0];
    }

    public function add(array $input, array $payload = []): array
    {
        $userId = $this->requiredInput($input, 'userId');
        $processId = $this->requiredInput($input, 'processId');
        $category = $this->requiredInput($input, 'category');
        $amount = $this->decimalAmount($input['amount'] ?? null);
        $startTime = $this->requiredTime($input, 'startTime');
        $endTime = $this->requiredTime($input, 'endTime');
        $this->assertValidTimeRange($startTime, $endTime);
        $objectId = trim((string)($input['objectId'] ?? ''));
        if (strlen($processId) > 80) {
            throw new RuntimeException('processId is too long', 400);
        }
        if (strlen($category) > 50) {
            throw new RuntimeException('category is too long', 400);
        }
        if (strlen($objectId) > 20) {
            throw new RuntimeException('objectId is too long', 400);
        }

        return Db::transaction(function () use ($payload, $userId, $processId, $category, $amount, $startTime, $endTime, $objectId, $input): array {
            $targetUser = $this->activeTargetUser($userId, $payload);
            $this->assertTargetUserWritable($userId, $payload);

            $tenantId = $this->tenantId($payload);
            if ($tenantId === '') {
                $tenantId = trim((string)($targetUser['TENANT_ID'] ?? ''));
            }
            if ($tenantId === '') {
                $tenantId = '1';
            }

            $this->assertNoOverlappingLeave($userId, $startTime, $endTime, $tenantId);

            $id = $this->newId();
            $now = date('Y-m-d H:i:s');
            $operatorId = $this->currentUserId($payload);
            $row = [
                'ID' => $id,
                'USER_ID' => $userId,
                'PROCESS_ID' => $processId,
                'category' => $category,
                'AMOUNT' => $amount,
                'REMARK' => trim((string)($input['remark'] ?? '')),
                'START_TIME' => $startTime,
                'END_TIME' => $endTime,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $operatorId !== '' ? $operatorId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
            ];

            if ($this->leaveHasObjectIdColumn()) {
                $row['OBJECT_ID'] = $objectId;
            }

            $adjustments = $this->applyAnnualLeaveBalanceDeltas(
                $this->annualLeaveAddDeltas($row),
                $now,
                $operatorId
            );
            Db::name('biz_leave_application')->insert($row);

            return [
                'id' => $id,
                'count' => 1,
                'vacationAdjustments' => $adjustments,
            ];
        });
    }

    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');

        return Db::transaction(function () use ($id, $input, $payload): array {
            $existing = $this->assertLeaveWritable($id, $payload, 'edit leave application');
            $targetUserId = $this->requiredInput($input, 'userId');
            $this->assertTargetUserWritable($targetUserId, $payload);
            $update = $this->editableUpdate($input, $payload);
            $adjustments = $this->applyAnnualLeaveBalanceDeltas(
                $this->annualLeaveEditDeltas($existing, $update),
                date('Y-m-d H:i:s'),
                $this->currentUserId($payload)
            );
            $updated = Db::name('biz_leave_application')
                ->where('ID', $id)
                ->update($update);

            return [
                'id' => $id,
                'count' => $updated,
                'vacationAdjustments' => $adjustments,
            ];
        });
    }

    public function delete(array $input, array $payload = []): array
    {
        $ids = $this->deleteIds($input);

        return Db::transaction(function () use ($ids, $payload): array {
            $rows = $this->activeLeaveRows($ids, $payload);
            if (count($rows) !== count($ids)) {
                throw new RuntimeException('leave application batch contains missing rows', 400);
            }

            foreach ($ids as $id) {
                $this->assertLeaveRowWritable($rows[$id], $payload, 'delete leave application');
            }

            $userId = $this->currentUserId($payload);
            $now = date('Y-m-d H:i:s');
            $adjustments = $this->applyAnnualLeaveBalanceDeltas(
                $this->annualLeaveDeleteDeltas($rows),
                $now,
                $userId
            );
            $updated = Db::name('biz_leave_application')
                ->whereIn('ID', $ids)
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                ]);

            return [
                'count' => $updated,
                'vacationAdjustments' => $adjustments,
            ];
        });
    }

    private function leaveQuery(array $filters, array $payload, bool $onlyCurrentUser)
    {
        $query = Db::name('biz_leave_application')
            ->alias('l')
            ->leftJoin('sys_user u', 'u.ID = l.USER_ID')
            ->leftJoin('sys_org org', 'org.ID = u.ORG_ID')
            ->leftJoin('sys_user creator', 'creator.ID = l.CREATE_USER')
            ->leftJoin('sys_user updater', 'updater.ID = l.UPDATE_USER')
            ->where(function ($query): void {
                $query->whereNull('l.DELETE_FLAG')->whereOr('l.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('l.TENANT_ID', $tenantId);
        }

        if (!empty($filters['id'])) {
            $query->where('l.ID', (string)$filters['id']);
        }

        if ($onlyCurrentUser) {
            $userId = $this->currentUserId($payload);
            $userId === '' ? $query->whereRaw('1 = 0') : $query->where('l.USER_ID', $userId);
        } else {
            $scopeOrgIds = $this->scopeOrgIds($payload);
            if ($scopeOrgIds !== []) {
                $query->whereIn('u.ORG_ID', $scopeOrgIds);
            } else {
                $userId = $this->currentUserId($payload);
                $userId === '' ? $query->whereRaw('1 = 0') : $query->where('l.USER_ID', $userId);
            }
        }

        if (!empty($filters['userId'])) {
            $query->where('l.USER_ID', (string)$filters['userId']);
        }

        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            $orgIds === [] ? $query->whereRaw('1 = 0') : $query->whereIn('u.ORG_ID', $orgIds);
        }

        if (!empty($filters['name'])) {
            $query->whereLike('u.NAME', '%' . trim((string)$filters['name']) . '%');
        }

        $categories = $this->listParam($filters['category'] ?? []);
        if ($categories !== []) {
            $query->whereIn('l.category', $categories);
        }

        if (!empty($filters['amount'])) {
            $query->whereLike('l.AMOUNT', '%' . trim((string)$filters['amount']) . '%');
        }

        if (!empty($filters['remark'])) {
            $query->whereLike('l.REMARK', '%' . trim((string)$filters['remark']) . '%');
        }

        if (!empty($filters['processId'])) {
            $query->where('l.PROCESS_ID', (string)$filters['processId']);
        }

        if (!empty($filters['objectId'])) {
            $query->where('l.OBJECT_ID', (string)$filters['objectId']);
        }

        $this->applyTimeRange($query, $filters, 'l.START_TIME', 'startStartTime', 'endStartTime');
        $this->applyTimeRange($query, $filters, 'l.END_TIME', 'startEndTime', 'endEndTime');

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('u.NAME', $keyword)
                    ->whereOr('l.REMARK', 'like', $keyword)
                    ->whereOr('l.PROCESS_ID', 'like', $keyword)
                    ->whereOr('l.OBJECT_ID', 'like', $keyword)
                    ->whereOr('org.NAME', 'like', $keyword);
            });
        }

        return $query;
    }

    private function assertLeaveWritable(string $id, array $payload, string $action): array
    {
        $rows = $this->activeLeaveRows([$id], $payload);
        if ($rows === []) {
            throw new RuntimeException('leave application not found', 404);
        }

        return $this->assertLeaveRowWritable(reset($rows), $payload, $action);
    }

    private function assertLeaveRowWritable(array $row, array $payload, string $action): array
    {
        if ($this->canSeeAll($payload)) {
            return $row;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $rowOrg = trim((string)($row['ORG_ID'] ?? ''));
        if ($scopeOrgIds !== [] && $rowOrg !== '' && in_array($rowOrg, $scopeOrgIds, true)) {
            return $row;
        }

        $currentUserId = $this->currentUserId($payload);
        $rowUser = trim((string)($row['USER_ID'] ?? ''));
        $createUser = trim((string)($row['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && ($rowUser === $currentUserId || $createUser === $currentUserId)) {
            return $row;
        }

        throw new RuntimeException("no permission to {$action}", 403);
    }

    /**
     * @param array<int, string> $ids
     * @return array<string, array<string, mixed>>
     */
    private function activeLeaveRows(array $ids, array $payload): array
    {
        $query = Db::name('biz_leave_application')
            ->alias('l')
            ->leftJoin('sys_user u', 'u.ID = l.USER_ID')
            ->whereIn('l.ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('l.DELETE_FLAG')->whereOr('l.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('l.TENANT_ID', $tenantId);
        }

        $rows = $query
            ->field('l.ID,l.USER_ID,l.PROCESS_ID,l.`category` AS CATEGORY,l.AMOUNT,l.REMARK,l.START_TIME,l.END_TIME,l.CREATE_TIME,l.CREATE_USER,l.TENANT_ID,l.OBJECT_ID,u.ORG_ID AS ORG_ID')
            ->lock(true)
            ->select()
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $result[(string)$row['ID']] = $row;
        }

        return $result;
    }

    private function activeTargetUser(string $userId, array $payload): array
    {
        $query = Db::name('sys_user')
            ->where('ID', $userId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->field('ID,ORG_ID,TENANT_ID');

        $tenantId = $this->tenantId($payload);
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query->lock(true)->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('target user not found', 404);
        }

        return $row;
    }

    private function assertNoOverlappingLeave(string $userId, string $startTime, string $endTime, string $tenantId): void
    {
        $query = Db::name('biz_leave_application')
            ->where('USER_ID', $userId)
            ->whereRaw(
                '((START_TIME BETWEEN ? AND ?) OR (END_TIME BETWEEN ? AND ?) OR (START_TIME <= ? AND END_TIME >= ?))',
                [$startTime, $endTime, $startTime, $endTime, $startTime, $endTime]
            )
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $rows = $query->field('ID,PROCESS_ID')->lock(true)->select()->toArray();
        foreach ($rows as $row) {
            if ($this->leaveApplicationBlocksTimeRange($row['PROCESS_ID'] ?? null)) {
                throw new RuntimeException('user already has leave application in range', 400);
            }
        }
    }

    private function leaveApplicationBlocksTimeRange(mixed $processId): bool
    {
        $processId = trim((string)$processId);
        if ($processId === '') {
            return true;
        }

        $rows = Db::name('act_hi_varinst')
            ->where('PROC_INST_ID_', $processId)
            ->whereIn('NAME_', ['approval', 'cancel', 'state', 'status'])
            ->field('NAME_,VAR_TYPE_,LONG_,DOUBLE_,TEXT_,TEXT2_')
            ->select()
            ->toArray();
        if ($rows === []) {
            return true;
        }

        $variables = [];
        foreach ($rows as $row) {
            $name = (string)($row['NAME_'] ?? '');
            if ($name === '') {
                continue;
            }
            $variables[$name] = $this->workflowVariableValue($row);
        }

        $status = strtoupper(trim((string)($variables['status'] ?? '')));
        $state = strtoupper(trim((string)($variables['state'] ?? '')));
        if (in_array($status, ['REJECT', 'CANCEL', 'CANCELED', 'CANCELLED'], true)
            || in_array($state, ['REJECT', 'CANCEL', 'CANCELED', 'CANCELLED'], true)
            || ($variables['cancel'] ?? false) === true
            || (($variables['approval'] ?? null) === false && ($status !== '' || $state !== ''))) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function workflowVariableValue(array $row): mixed
    {
        $type = (string)($row['VAR_TYPE_'] ?? '');
        if ($type === 'boolean') {
            return (int)($row['LONG_'] ?? 0) === 1;
        }
        if ($type === 'integer' || $type === 'long') {
            return (int)($row['LONG_'] ?? 0);
        }
        if ($type === 'double') {
            return (float)($row['DOUBLE_'] ?? 0);
        }
        if ($type === 'null') {
            return null;
        }
        if ((string)($row['TEXT2_'] ?? '') === '!emptyString!') {
            return '';
        }

        return $row['TEXT_'] ?? null;
    }

    private function assertTargetUserWritable(string $userId, array $payload): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $query = Db::name('sys_user')
            ->where('ID', $userId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->field('ID,ORG_ID,TENANT_ID');

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('target user not found', 404);
        }

        $currentUserId = $this->currentUserId($payload);
        if ($currentUserId !== '' && (string)$row['ID'] === $currentUserId) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $orgId = trim((string)($row['ORG_ID'] ?? ''));
        if ($scopeOrgIds !== [] && $orgId !== '' && in_array($orgId, $scopeOrgIds, true)) {
            return;
        }

        throw new RuntimeException('no permission to assign leave application user', 403);
    }

    private function editableUpdate(array $input, array $payload): array
    {
        $row = [];
        foreach (self::EDITABLE_FIELDS as $field => $column) {
            $row[$column] = match ($field) {
                'amount' => $this->decimalAmount($input[$field] ?? null),
                'startTime', 'endTime' => $this->requiredTime($input, $field),
                default => $this->requiredInput($input, $field),
            };
        }

        $userId = $this->currentUserId($payload);
        $row['UPDATE_TIME'] = date('Y-m-d H:i:s');
        $row['UPDATE_USER'] = $userId !== '' ? $userId : null;

        return $row;
    }

    /**
     * @return array<string, array{userId: string, tenantId: string, delta: float}>
     */
    private function annualLeaveAddDeltas(array $row): array
    {
        $deltas = [];
        if ($this->isAnnualLeaveCategory($row['category'] ?? $row['CATEGORY'] ?? null)) {
            $this->appendAnnualLeaveDelta(
                $deltas,
                (string)($row['USER_ID'] ?? ''),
                (string)($row['TENANT_ID'] ?? ''),
                (float)$this->decimalAmount($row['AMOUNT'] ?? null),
                $row
            );
        }

        return $deltas;
    }

    /**
     * @return array<string, array{userId: string, tenantId: string, delta: float}>
     */
    private function annualLeaveEditDeltas(array $existing, array $update): array
    {
        $deltas = [];
        if ($this->isAnnualLeaveCategory($existing['CATEGORY'] ?? null)) {
            $this->appendAnnualLeaveDelta(
                $deltas,
                (string)($existing['USER_ID'] ?? ''),
                (string)($existing['TENANT_ID'] ?? ''),
                -(float)$this->decimalAmount($existing['AMOUNT'] ?? null),
                $existing
            );
        }

        if ($this->isAnnualLeaveCategory($update['category'] ?? $update['CATEGORY'] ?? null)) {
            $this->appendAnnualLeaveDelta(
                $deltas,
                (string)($update['USER_ID'] ?? ''),
                (string)($existing['TENANT_ID'] ?? ''),
                (float)$this->decimalAmount($update['AMOUNT'] ?? null),
                $existing
            );
        }

        return $deltas;
    }

    /**
     * @param array<string, array<string, mixed>> $rows
     * @return array<string, array{userId: string, tenantId: string, delta: float}>
     */
    private function annualLeaveDeleteDeltas(array $rows): array
    {
        $deltas = [];
        foreach ($rows as $row) {
            if (!$this->isAnnualLeaveCategory($row['CATEGORY'] ?? null)) {
                continue;
            }

            $this->appendAnnualLeaveDelta(
                $deltas,
                (string)($row['USER_ID'] ?? ''),
                (string)($row['TENANT_ID'] ?? ''),
                -(float)$this->decimalAmount($row['AMOUNT'] ?? null),
                $row
            );
        }

        return $deltas;
    }

    /**
     * @param array<string, array{userId: string, tenantId: string, delta: float}> $deltas
     * @return array<int, array<string, mixed>>
     */
    private function applyAnnualLeaveBalanceDeltas(array $deltas, string $now, string $updateUser): array
    {
        $adjustments = [];
        foreach ($deltas as $delta) {
            $amountDelta = (float)$delta['delta'];
            if (abs($amountDelta) < 0.00001) {
                continue;
            }

            $adjustments[] = $this->adjustAnnualLeaveBalance(
                $delta['userId'],
                $delta['tenantId'],
                $amountDelta,
                $now,
                $updateUser
            );
        }

        return $adjustments;
    }

    /**
     * @param array<string, array{userId: string, tenantId: string, delta: float}> $deltas
     */
    private function appendAnnualLeaveDelta(array &$deltas, string $userId, string $tenantId, float $amountDelta, array $sourceRow): void
    {
        if ($userId === '') {
            throw new RuntimeException('missing leave userId', 400);
        }
        if (abs($amountDelta) < 0.00001) {
            return;
        }

        $this->assertCurrentYearAnnualLeaveRow($sourceRow);
        $key = $userId . '|' . $tenantId;
        if (!isset($deltas[$key])) {
            $deltas[$key] = [
                'userId' => $userId,
                'tenantId' => $tenantId,
                'delta' => 0.0,
            ];
        }

        $deltas[$key]['delta'] += $amountDelta;
    }

    /**
     * @return array<string, mixed>
     */
    private function adjustAnnualLeaveBalance(
        string $userId,
        string $tenantId,
        float $amountDelta,
        string $now,
        string $updateUser
    ): array {
        if ($amountDelta > 0) {
            $this->annualLeaveEntitlementService->ensureCurrentYearBalance(
                $userId,
                $tenantId,
                $updateUser
            );
        }

        $query = Db::name('biz_user_vacation')
            ->where('USER_ID', $userId)
            ->where('CATEGORY', self::LEAVE_CATEGORY_ANNUAL)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->whereBetweenTime('CREATE_TIME', date('Y-01-01 00:00:00'), date('Y-12-31 23:59:59'));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query
            ->field('ID,USER_ID,AMOUNT,USED_AMOUNT,CATEGORY,TENANT_ID,VERSION')
            ->order('CREATE_TIME', 'desc')
            ->order('ID', 'desc')
            ->lock(true)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('user annual leave balance not found', 400);
        }

        $amount = number_format((float)($row['AMOUNT'] ?? 0), 2, '.', '');
        $usedAmount = number_format((float)($row['USED_AMOUNT'] ?? 0), 2, '.', '');
        if ($amountDelta > 0) {
            $remaining = (float)$amount - (float)$usedAmount;
            if ($remaining + 0.00001 < $amountDelta) {
                throw new RuntimeException('insufficient annual leave balance', 400);
            }
        }
        if ($amountDelta < 0 && (float)$usedAmount + 0.00001 < abs($amountDelta)) {
            throw new RuntimeException('annual leave used amount underflow', 400);
        }

        $newUsedAmount = number_format((float)$usedAmount + $amountDelta, 2, '.', '');
        Db::name('biz_user_vacation')
            ->where('ID', (string)$row['ID'])
            ->update([
                'USED_AMOUNT' => $newUsedAmount,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $updateUser !== '' ? $updateUser : null,
                'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
            ]);

        return [
            'id' => (string)$row['ID'],
            'userId' => $userId,
            'tenantId' => (string)($row['TENANT_ID'] ?? $tenantId),
            'amount' => $amount,
            'usedAmount' => $newUsedAmount,
            'deltaAmount' => number_format($amountDelta, 2, '.', ''),
        ];
    }

    private function assertCurrentYearAnnualLeaveRow(array $row): void
    {
        $time = trim((string)($row['CREATE_TIME'] ?? ''));
        if ($time === '') {
            $time = trim((string)($row['START_TIME'] ?? ''));
        }

        $timestamp = strtotime($time);
        if ($timestamp === false || date('Y', $timestamp) !== date('Y')) {
            throw new RuntimeException('direct annual leave adjustment only supports current-year leave rows', 400);
        }
    }

    private function isAnnualLeaveCategory(mixed $category): bool
    {
        return trim((string)$category) === self::LEAVE_CATEGORY_ANNUAL;
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('l.ID', 'asc');
        }

        return $query->order('l.ID', 'asc');
    }

    private function applyTimeRange($query, array $filters, string $column, string $startKey, string $endKey): void
    {
        $start = trim((string)($filters[$startKey] ?? ''));
        $end = trim((string)($filters[$endKey] ?? ''));
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime($column, $start, $end);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function leaveRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->leaveRow($row), $rows);
    }

    private function leaveRow(array $row): array
    {
        return [
            'id' => $this->value($row, 'ID', 'id'),
            'userId' => $this->value($row, 'USER_ID', 'userId'),
            'name' => $this->value($row, 'NAME', 'name'),
            'orgId' => $this->value($row, 'ORG_ID', 'orgId'),
            'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
            'processId' => $this->value($row, 'PROCESS_ID', 'processId'),
            'category' => $this->value($row, 'CATEGORY', 'category'),
            'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
            'remark' => $this->value($row, 'REMARK', 'remark'),
            'startTime' => $this->value($row, 'START_TIME', 'startTime'),
            'endTime' => $this->value($row, 'END_TIME', 'endTime'),
            'objectId' => $this->value($row, 'OBJECT_ID', 'objectId'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'createUserName' => $this->value($row, 'CREATE_USER_NAME', 'createUserName'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'updateUserName' => $this->value($row, 'UPDATE_USER_NAME', 'updateUserName'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
        ];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    /**
     * @return array<int, string>
     */
    private function listParam(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = explode(',', $value);
            }
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static fn ($item): string => trim((string)$item), $value))));
    }

    /**
     * @return array<int, string>
     */
    private function deleteIds(array $input): array
    {
        $source = $input['ids'] ?? $input['idList'] ?? null;
        if ($source === null && array_key_exists('id', $input)) {
            $source = [$input['id']];
        }
        if ($source === null && $this->isListArray($input)) {
            $source = array_map(static function (mixed $item): mixed {
                return is_array($item) ? ($item['id'] ?? null) : $item;
            }, $input);
        }
        if (is_string($source)) {
            $source = explode(',', $source);
        }
        if (!is_array($source)) {
            throw new RuntimeException('missing id', 400);
        }

        $ids = array_values(array_unique(array_filter(array_map(
            fn (mixed $id): string => $this->idValue($id),
            $source
        ))));
        if ($ids === []) {
            throw new RuntimeException('missing id', 400);
        }

        return $ids;
    }

    private function idValue(mixed $value): string
    {
        if (is_array($value)) {
            return trim((string)($value['id'] ?? $value['ID'] ?? ''));
        }

        return trim((string)$value);
    }

    private function isListArray(array $input): bool
    {
        $index = 0;
        foreach (array_keys($input) as $key) {
            if ($key !== $index) {
                return false;
            }
            $index++;
        }

        return true;
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
        $direct = $payload['data_scope_org_ids'] ?? [];
        if (is_string($direct)) {
            $direct = explode(',', $direct);
        }
        if (is_array($direct) && $direct !== []) {
            return array_values(array_unique(array_filter(array_map('strval', $direct))));
        }

        $scopes = $payload['data_scopes'] ?? $payload['dataScopeList'] ?? [];
        if (!is_array($scopes)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static function (mixed $scope): string {
            if (!is_array($scope)) {
                return '';
            }

            return trim((string)($scope['orgId'] ?? $scope['org_id'] ?? ''));
        }, $scopes))));
    }

    private function currentUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
    }

    private function tenantId(array $payload): string
    {
        return trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
    }

    private function canSeeAll(array $payload): bool
    {
        $account = strtolower((string)($payload['account'] ?? ''));
        if (in_array($account, ['bizadmin', 'superadmin'], true)) {
            return true;
        }

        $roleCodes = $payload['role_codes'] ?? $payload['roleCodeList'] ?? [];
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

    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    private function decimalAmount(mixed $value): string
    {
        if ($value === null || $value === '') {
            throw new RuntimeException('missing amount', 400);
        }
        if (!is_numeric($value)) {
            throw new RuntimeException('invalid amount', 400);
        }

        return number_format((float)$value, 2, '.', '');
    }

    private function requiredTime(array $input, string $key): string
    {
        $value = $this->requiredInput($input, $key);
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException("invalid {$key}", 400);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function assertValidTimeRange(string $startTime, string $endTime): void
    {
        $startTimestamp = strtotime($startTime);
        $endTimestamp = strtotime($endTime);
        if ($startTimestamp === false || $endTimestamp === false || $endTimestamp < $startTimestamp) {
            throw new RuntimeException('invalid leave time range', 400);
        }
    }

    private function leaveHasObjectIdColumn(): bool
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $columns = Db::query("SHOW COLUMNS FROM `biz_leave_application` LIKE 'OBJECT_ID'");
        $hasColumn = $columns !== [];

        return $hasColumn;
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
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
