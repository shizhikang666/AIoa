<?php

namespace app\service\auth;

use think\facade\Db;

class RbacService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const USER_HAS_ROLE = 'SYS_USER_HAS_ROLE';
    private const USER_HAS_RESOURCE = 'SYS_USER_HAS_RESOURCE';
    private const ROLE_HAS_RESOURCE = 'SYS_ROLE_HAS_RESOURCE';
    private const USER_HAS_PERMISSION = 'SYS_USER_HAS_PERMISSION';
    private const ROLE_HAS_PERMISSION = 'SYS_ROLE_HAS_PERMISSION';
    private const ROLE_HAS_MOBILE_MENU = 'SYS_ROLE_HAS_MOBILE_MENU';

    public function buildForUser(array $user): array
    {
        $userId = (string)($user['ID'] ?? '');
        if ($userId === '') {
            return $this->emptyContext();
        }

        $roleIds = $this->relationTargetIds([$userId], [self::USER_HAS_ROLE]);
        $roles = $this->roles($roleIds);
        $roleCodes = array_values(array_filter(array_map(static fn (array $role) => $role['CODE'] ?? null, $roles)));
        $userAndRoleIds = array_values(array_unique(array_merge([$userId], $roleIds)));

        $resourceRelations = $this->relations($userAndRoleIds, [self::USER_HAS_RESOURCE, self::ROLE_HAS_RESOURCE]);
        $menuIds = array_values(array_unique(array_filter(array_map(static fn (array $row) => $row['TARGET_ID'] ?? null, $resourceRelations))));
        $buttonIds = $this->buttonIdsFromRelationExt($resourceRelations);

        $mobileRelations = $this->relations($userAndRoleIds, [self::ROLE_HAS_MOBILE_MENU]);
        $mobileButtonIds = $this->buttonIdsFromRelationExt($mobileRelations);

        $permissionRelations = $this->relations($userAndRoleIds, [self::USER_HAS_PERMISSION, self::ROLE_HAS_PERMISSION]);
        $permissionCodes = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => strtolower(trim((string)($row['TARGET_ID'] ?? ''))),
            $permissionRelations
        ))));
        $dataScopes = $this->dataScopes($permissionRelations, $user);

        return [
            'role_ids' => $roleIds,
            'role_codes' => $roleCodes,
            'button_codes' => $this->resourceCodes($buttonIds),
            'mobile_button_codes' => $this->mobileResourceCodes($mobileButtonIds),
            'permission_codes' => $permissionCodes,
            'menu_ids' => $menuIds,
            'menus' => $this->resources($menuIds),
            'data_scope_org_ids' => $this->dataScopeOrgIds($dataScopes, (string)($user['ORG_ID'] ?? '')),
            'data_scopes' => $dataScopes,
        ];
    }

    private function emptyContext(): array
    {
        return [
            'role_ids' => [],
            'role_codes' => [],
            'button_codes' => [],
            'mobile_button_codes' => [],
            'permission_codes' => [],
            'menu_ids' => [],
            'menus' => [],
            'data_scope_org_ids' => [],
            'data_scopes' => [],
        ];
    }

    private function relationTargetIds(array $objectIds, array $categories): array
    {
        if ($objectIds === []) {
            return [];
        }

        return array_values(array_unique(array_filter(
            Db::name('sys_relation')
                ->whereIn('OBJECT_ID', $objectIds)
                ->whereIn('CATEGORY', $categories)
                ->column('TARGET_ID')
        )));
    }

    private function relations(array $objectIds, array $categories): array
    {
        if ($objectIds === []) {
            return [];
        }

        return Db::name('sys_relation')
            ->whereIn('OBJECT_ID', $objectIds)
            ->whereIn('CATEGORY', $categories)
            ->select()
            ->toArray();
    }

    private function roles(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        return Db::name('sys_role')->whereIn('ID', $roleIds)->select()->toArray();
    }

    private function resources(array $resourceIds): array
    {
        if ($resourceIds === []) {
            return [];
        }

        return Db::name('sys_resource')
            ->whereIn('ID', $resourceIds)
            ->order('SORT_CODE', 'asc')
            ->select()
            ->toArray();
    }

    private function resourceCodes(array $resourceIds): array
    {
        if ($resourceIds === []) {
            return [];
        }

        return array_values(array_filter(Db::name('sys_resource')->whereIn('ID', $resourceIds)->column('CODE')));
    }

    private function mobileResourceCodes(array $resourceIds): array
    {
        if ($resourceIds === []) {
            return [];
        }

        return array_values(array_filter(Db::name('mobile_resource')->whereIn('ID', $resourceIds)->column('CODE')));
    }

    private function buttonIdsFromRelationExt(array $relations): array
    {
        $buttonIds = [];

        foreach ($relations as $relation) {
            $extJson = $relation['EXT_JSON'] ?? null;
            if (!is_string($extJson) || trim($extJson) === '') {
                continue;
            }

            $decoded = json_decode($extJson, true);
            if (!is_array($decoded) || !isset($decoded['buttonInfo']) || !is_array($decoded['buttonInfo'])) {
                continue;
            }

            foreach ($decoded['buttonInfo'] as $buttonId) {
                if (is_string($buttonId) && $buttonId !== '') {
                    $buttonIds[] = $buttonId;
                }
            }
        }

        return array_values(array_unique($buttonIds));
    }

    private function dataScopes(array $permissionRelations, array $user): array
    {
        $scopesByApi = [];
        $scopeOrgIdsCache = [];
        foreach ($permissionRelations as $relation) {
            $apiUrl = strtolower(trim((string)($relation['TARGET_ID'] ?? '')));
            if ($apiUrl === '') {
                continue;
            }

            [$scopeCategory, $defineOrgIds] = $this->scopeInfo($relation);
            $scopeCacheKey = $scopeCategory . ':' . implode(',', $defineOrgIds);
            if (!array_key_exists($scopeCacheKey, $scopeOrgIdsCache)) {
                $scopeOrgIdsCache[$scopeCacheKey] = $this->scopeOrgIdsForCategory($scopeCategory, $defineOrgIds, $user);
            }
            $orgIds = $scopeOrgIdsCache[$scopeCacheKey];

            if (!isset($scopesByApi[$apiUrl])) {
                $scopesByApi[$apiUrl] = [
                    'apiUrl' => $apiUrl,
                    'orgId' => $orgIds[0] ?? null,
                    'scopeCategory' => $scopeCategory,
                    'scopeDefineOrgIdList' => [],
                    'scopeOrgIdList' => [],
                ];
            }

            if ($scopesByApi[$apiUrl]['orgId'] === null && $orgIds !== []) {
                $scopesByApi[$apiUrl]['orgId'] = $orgIds[0];
            }

            if ($scopeCategory === 'SCOPE_ALL') {
                $scopesByApi[$apiUrl]['scopeCategory'] = 'SCOPE_ALL';
            }

            $scopesByApi[$apiUrl]['scopeDefineOrgIdList'] = array_values(array_unique(array_merge(
                $scopesByApi[$apiUrl]['scopeDefineOrgIdList'],
                $defineOrgIds
            )));
            $scopesByApi[$apiUrl]['scopeOrgIdList'] = array_values(array_unique(array_merge(
                $scopesByApi[$apiUrl]['scopeOrgIdList'],
                $orgIds
            )));
        }

        return array_values($scopesByApi);
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private function scopeInfo(array $relation): array
    {
        $scopeCategory = 'SCOPE_ORG_CHILD';
        $defineOrgIds = [];
        $extJson = $relation['EXT_JSON'] ?? null;
        if (is_string($extJson) && trim($extJson) !== '') {
            $decoded = json_decode($extJson, true);
            if (is_array($decoded)) {
                $scopeCategory = trim((string)($decoded['scopeCategory'] ?? $decoded['scope_category'] ?? $scopeCategory));
                $defineOrgIds = $this->stringList($decoded['scopeDefineOrgIdList'] ?? $decoded['scope_define_org_id_list'] ?? []);
            }
        }

        if (!in_array($scopeCategory, [
            'SCOPE_ALL',
            'SCOPE_SELF',
            'SCOPE_ORG',
            'SCOPE_ORG_CHILD',
            'SCOPE_COMPANY_CHILD',
            'SCOPE_ORG_DEFINE',
        ], true)) {
            $scopeCategory = 'SCOPE_ORG_CHILD';
        }

        return [$scopeCategory, $defineOrgIds];
    }

    /**
     * @return array<int, string>
     */
    private function scopeOrgIdsForCategory(string $scopeCategory, array $defineOrgIds, array $user): array
    {
        $orgId = trim((string)($user['ORG_ID'] ?? ''));

        return match ($scopeCategory) {
            'SCOPE_ALL' => $this->tenantOrgIds($user),
            'SCOPE_SELF' => [],
            'SCOPE_ORG' => $orgId !== '' ? [$orgId] : [],
            'SCOPE_COMPANY_CHILD' => $this->companyAndDescendantIds($orgId),
            'SCOPE_ORG_DEFINE' => $this->expandOrgIds($defineOrgIds),
            default => $this->orgAndDescendantIds($orgId),
        };
    }

    /**
     * @param array<int, array<string, mixed>> $dataScopes
     * @return array<int, string>
     */
    private function dataScopeOrgIds(array $dataScopes, string $fallbackOrgId): array
    {
        $orgIds = [];
        foreach ($dataScopes as $scope) {
            $orgId = trim((string)($scope['orgId'] ?? ''));
            if ($orgId !== '') {
                $orgIds[] = $orgId;
            }

            $scopeOrgIds = $scope['scopeOrgIdList'] ?? $scope['scope_org_id_list'] ?? [];
            if (is_string($scopeOrgIds)) {
                $scopeOrgIds = explode(',', $scopeOrgIds);
            }
            if (is_array($scopeOrgIds)) {
                foreach ($scopeOrgIds as $scopeOrgId) {
                    $scopeOrgId = trim((string)$scopeOrgId);
                    if ($scopeOrgId !== '') {
                        $orgIds[] = $scopeOrgId;
                    }
                }
            }
        }

        $orgIds = array_values(array_unique($orgIds));
        if ($orgIds !== []) {
            return $orgIds;
        }

        return $this->orgAndDescendantIds($fallbackOrgId);
    }

    /**
     * @return array<int, string>
     */
    private function orgAndDescendantIds(string $orgId): array
    {
        return $this->expandOrgIds([$orgId]);
    }

    /**
     * @param array<int, string> $orgIds
     * @return array<int, string>
     */
    private function expandOrgIds(array $orgIds): array
    {
        $seen = [];
        $queue = [];
        foreach ($orgIds as $orgId) {
            $orgId = trim((string)$orgId);
            if ($orgId === '' || isset($seen[$orgId])) {
                continue;
            }
            $seen[$orgId] = true;
            $queue[] = $orgId;
        }

        while ($queue !== []) {
            $children = Db::name('sys_org')
                ->whereIn('PARENT_ID', $queue)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->column('ID');

            $queue = [];
            foreach ($children as $childId) {
                $childId = trim((string)$childId);
                if ($childId === '' || isset($seen[$childId])) {
                    continue;
                }
                $seen[$childId] = true;
                $queue[] = $childId;
            }
        }

        return array_keys($seen);
    }

    /**
     * @return array<int, string>
     */
    private function tenantOrgIds(array $user): array
    {
        $tenantIds = $this->stringList([
            $user['TENANT_ID'] ?? '',
            $this->orgTenantId((string)($user['ORG_ID'] ?? '')),
        ]);
        if ($tenantIds === []) {
            return [];
        }

        return array_values(array_filter(array_map('strval', Db::name('sys_org')
            ->whereIn('TENANT_ID', $tenantIds)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->column('ID'))));
    }

    private function orgTenantId(string $orgId): string
    {
        $orgId = trim($orgId);
        if ($orgId === '') {
            return '';
        }

        return trim((string)Db::name('sys_org')
            ->where('ID', $orgId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->value('TENANT_ID'));
    }

    /**
     * @return array<int, string>
     */
    private function companyAndDescendantIds(string $orgId): array
    {
        $companyId = $this->companyAncestorId($orgId);

        return $companyId !== '' ? $this->orgAndDescendantIds($companyId) : $this->orgAndDescendantIds($orgId);
    }

    private function companyAncestorId(string $orgId): string
    {
        $orgId = trim($orgId);
        $last = '';
        while ($orgId !== '') {
            $row = Db::name('sys_org')
                ->where('ID', $orgId)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->field(['ID', 'PARENT_ID', 'CATEGORY'])
                ->find();
            if (!is_array($row) || $row === []) {
                break;
            }

            $last = (string)($row['ID'] ?? $orgId);
            if ((string)($row['CATEGORY'] ?? '') === 'COMPANY' || trim((string)($row['PARENT_ID'] ?? '')) === '0') {
                return $last;
            }

            $parentId = trim((string)($row['PARENT_ID'] ?? ''));
            if ($parentId === '' || $parentId === $orgId) {
                break;
            }
            $orgId = $parentId;
        }

        return $last;
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

        $ids = [];
        foreach ($value as $item) {
            $id = trim((string)$item);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
