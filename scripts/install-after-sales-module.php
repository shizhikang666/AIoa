#!/usr/bin/env php
<?php

declare(strict_types=1);

use think\facade\Db;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

$apply = in_array('--apply', $argv, true);
$operator = 'after-sales-installer';

function installer_id(): string
{
    usleep(1000);

    return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
}

function active_row($query): mixed
{
    return $query->where(function ($query): void {
        $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
    });
}

$summary = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'tables' => ['biz_after_sales_category', 'biz_after_sales_record'],
    'menuPath' => '/biz/aftersales',
    'menuCreated' => false,
    'roleMenuGrants' => 0,
    'roleApiGrants' => 0,
    'defaultCategories' => 0,
];

if ($apply) {
    Db::execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `biz_after_sales_category` (
  `ID` varchar(20) NOT NULL,
  `NAME` varchar(100) NOT NULL,
  `SORT_CODE` int NOT NULL DEFAULT 100,
  `STATUS` varchar(20) NOT NULL DEFAULT 'ENABLE',
  `REMARK` varchar(500) DEFAULT NULL,
  `DELETE_FLAG` varchar(20) DEFAULT 'NOT_DELETE',
  `CREATE_TIME` datetime DEFAULT NULL,
  `CREATE_USER` varchar(20) DEFAULT NULL,
  `UPDATE_TIME` datetime DEFAULT NULL,
  `UPDATE_USER` varchar(20) DEFAULT NULL,
  `TENANT_ID` varchar(20) NOT NULL DEFAULT '1',
  PRIMARY KEY (`ID`),
  KEY `idx_after_sales_category_tenant` (`TENANT_ID`,`DELETE_FLAG`,`STATUS`,`SORT_CODE`),
  KEY `idx_after_sales_category_name` (`TENANT_ID`,`NAME`,`DELETE_FLAG`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    Db::execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `biz_after_sales_record` (
  `ID` varchar(20) NOT NULL,
  `CATEGORY_ID` varchar(20) NOT NULL,
  `PROJECT_ID` varchar(20) DEFAULT NULL,
  `TITLE` varchar(200) NOT NULL,
  `CONTENT` longtext NOT NULL,
  `HANDLE_TIME` datetime NOT NULL,
  `DELETE_FLAG` varchar(20) DEFAULT 'NOT_DELETE',
  `CREATE_TIME` datetime DEFAULT NULL,
  `CREATE_USER` varchar(20) DEFAULT NULL,
  `UPDATE_TIME` datetime DEFAULT NULL,
  `UPDATE_USER` varchar(20) DEFAULT NULL,
  `TENANT_ID` varchar(20) NOT NULL DEFAULT '1',
  PRIMARY KEY (`ID`),
  KEY `idx_after_sales_record_tenant_time` (`TENANT_ID`,`DELETE_FLAG`,`HANDLE_TIME`),
  KEY `idx_after_sales_record_category` (`TENANT_ID`,`CATEGORY_ID`,`DELETE_FLAG`),
  KEY `idx_after_sales_record_project` (`TENANT_ID`,`PROJECT_ID`,`DELETE_FLAG`),
  KEY `idx_after_sales_record_creator` (`TENANT_ID`,`CREATE_USER`,`DELETE_FLAG`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}

$existingMenu = active_row(Db::name('sys_resource')->where('CATEGORY', 'MENU')->where('PATH', '/biz/aftersales'))->find();
$sibling = active_row(Db::name('sys_resource')->where('CATEGORY', 'MENU')->where('PATH', '/biz/saleprojectfollowup'))->find();
if ((!is_array($sibling) || $sibling === []) && (!is_array($existingMenu) || $existingMenu === [])) {
    $sibling = active_row(Db::name('sys_resource')->where('CATEGORY', 'MENU')->where('TITLE', '项目跟进记录'))->find();
}
if ((!is_array($sibling) || $sibling === []) && (!is_array($existingMenu) || $existingMenu === [])) {
    throw new RuntimeException('sales menu sibling not found; expected /biz/saleprojectfollowup');
}

$menuId = is_array($existingMenu) && $existingMenu !== [] ? (string)$existingMenu['ID'] : installer_id();
if ($apply && (!is_array($existingMenu) || $existingMenu === [])) {
    $now = date('Y-m-d H:i:s');
    Db::name('sys_resource')->insert([
        'ID' => $menuId,
        'PARENT_ID' => $sibling['PARENT_ID'] ?? '0',
        'TITLE' => '售后记录表',
        'NAME' => 'bizAfterSales',
        'CODE' => null,
        'CATEGORY' => 'MENU',
        'MODULE' => $sibling['MODULE'] ?? null,
        'MENU_TYPE' => 'MENU',
        'PATH' => '/biz/aftersales',
        'COMPONENT' => 'biz/aftersales/index',
        'ICON' => 'FileTextOutlined',
        'COLOR' => null,
        'VISIBLE' => 'TRUE',
        'SORT_CODE' => ((int)($sibling['SORT_CODE'] ?? 100)) + 1,
        'EXT_JSON' => null,
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => $now,
        'CREATE_USER' => $operator,
        'UPDATE_TIME' => null,
        'UPDATE_USER' => null,
    ]);
    $summary['menuCreated'] = true;
}

$apiPaths = [
    '/biz/aftersales/page',
    '/biz/aftersales/detail',
    '/biz/aftersales/add',
    '/biz/aftersales/edit',
    '/biz/aftersales/delete',
    '/biz/aftersales/category/list',
    '/biz/aftersales/category/add',
    '/biz/aftersales/category/edit',
    '/biz/aftersales/category/delete',
    '/dev/file/uploadDynamicReturnUrl',
    '/dev/file/uploadLocalReturnId',
    '/dev/file/download',
];

$roles = active_row(Db::name('sys_role'))->field('ID,TENANT_ID')->select()->toArray();
if ($apply) {
    Db::transaction(function () use ($roles, $menuId, $apiPaths, &$summary): void {
        foreach ($roles as $role) {
            $roleId = (string)$role['ID'];
            $menuExists = (int)Db::name('sys_relation')->where('OBJECT_ID', $roleId)
                ->where('TARGET_ID', $menuId)->where('CATEGORY', 'SYS_ROLE_HAS_RESOURCE')->count() > 0;
            if (!$menuExists) {
                Db::name('sys_relation')->insert([
                    'ID' => installer_id(),
                    'OBJECT_ID' => $roleId,
                    'TARGET_ID' => $menuId,
                    'CATEGORY' => 'SYS_ROLE_HAS_RESOURCE',
                    'EXT_JSON' => json_encode(['buttonInfo' => []], JSON_UNESCAPED_UNICODE),
                ]);
                $summary['roleMenuGrants']++;
            }
            foreach ($apiPaths as $apiPath) {
                $exists = (int)Db::name('sys_relation')->where('OBJECT_ID', $roleId)
                    ->where('TARGET_ID', $apiPath)->where('CATEGORY', 'SYS_ROLE_HAS_PERMISSION')->count() > 0;
                if ($exists) {
                    continue;
                }
                Db::name('sys_relation')->insert([
                    'ID' => installer_id(),
                    'OBJECT_ID' => $roleId,
                    'TARGET_ID' => $apiPath,
                    'CATEGORY' => 'SYS_ROLE_HAS_PERMISSION',
                    'EXT_JSON' => json_encode([
                        'scopeCategory' => 'SCOPE_ALL',
                        'scopeDefineOrgIdList' => [],
                    ], JSON_UNESCAPED_UNICODE),
                ]);
                $summary['roleApiGrants']++;
            }
        }
    });
}

$tenantIds = array_values(array_unique(array_filter(array_map(
    static fn (array $row): string => trim((string)($row['TENANT_ID'] ?? '')),
    $roles
))));
if ($tenantIds === []) {
    $tenantIds = ['1'];
}
$defaultCategories = ['质量问题', '使用指导', '维修处理', '退换货', '其他'];
if ($apply) {
    foreach ($tenantIds as $tenantId) {
        foreach ($defaultCategories as $index => $name) {
            $exists = (int)active_row(Db::name('biz_after_sales_category')->where('TENANT_ID', $tenantId)->where('NAME', $name))->count() > 0;
            if ($exists) {
                continue;
            }
            Db::name('biz_after_sales_category')->insert([
                'ID' => installer_id(),
                'NAME' => $name,
                'SORT_CODE' => ($index + 1) * 10,
                'STATUS' => 'ENABLE',
                'REMARK' => null,
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => date('Y-m-d H:i:s'),
                'CREATE_USER' => $operator,
                'TENANT_ID' => $tenantId,
            ]);
            $summary['defaultCategories']++;
        }
    }
}

$summary['menuId'] = $menuId;
$summary['roleCount'] = count($roles);
$summary['apiPathCount'] = count($apiPaths);
fwrite(STDOUT, json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
