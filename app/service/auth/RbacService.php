<?php

namespace app\service\auth;

use think\facade\Db;

class RbacService
{
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

        $permissionCodes = $this->relationTargetIds($userAndRoleIds, [self::USER_HAS_PERMISSION, self::ROLE_HAS_PERMISSION]);

        return [
            'role_ids' => $roleIds,
            'role_codes' => $roleCodes,
            'button_codes' => $this->resourceCodes($buttonIds),
            'mobile_button_codes' => $this->mobileResourceCodes($mobileButtonIds),
            'permission_codes' => $permissionCodes,
            'menu_ids' => $menuIds,
            'menus' => $this->resources($menuIds),
            'data_scopes' => $this->dataScopes($permissionCodes, (string)($user['ORG_ID'] ?? '')),
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

    private function dataScopes(array $permissionCodes, string $orgId): array
    {
        return array_map(static fn (string $apiUrl) => [
            'apiUrl' => $apiUrl,
            'orgId' => $orgId,
        ], $permissionCodes);
    }
}
