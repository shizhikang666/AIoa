#!/usr/bin/env php
<?php

declare(strict_types=1);

use think\facade\Db;

require __DIR__ . '/lib/installer-target.php';
$installerTarget = installer_target_prepare($argv);
require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();
installer_target_configure($installerTarget);

$apply = in_array('--apply', $argv, true);
$travelColumnExists = Db::query("SHOW COLUMNS FROM `biz_sale_project` LIKE 'TRAVEL_DAYS'") !== [];
$objectIdColumnExists = Db::query("SHOW COLUMNS FROM `biz_leave_application` LIKE 'OBJECT_ID'") !== [];
$travelIndexExists = Db::query("SHOW INDEX FROM `biz_leave_application` WHERE Key_name = 'idx_leave_after_sales_travel'") !== [];

$summary = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'travelDaysColumn' => $travelColumnExists ? 'exists' : 'pending',
    'leaveObjectIdColumn' => $objectIdColumnExists ? 'exists' : 'pending',
    'travelStatisticsIndex' => $travelIndexExists ? 'exists' : 'pending',
];

if ($apply && !$travelColumnExists) {
    Db::execute(<<<'SQL'
ALTER TABLE `biz_sale_project`
ADD COLUMN `TRAVEL_DAYS` decimal(10,1) NOT NULL DEFAULT 0.0 COMMENT '计划出差天数' AFTER `REBATE_AMOUNT`
SQL);
    $summary['travelDaysColumn'] = 'created';
}

if ($apply && !$objectIdColumnExists) {
    Db::execute(<<<'SQL'
ALTER TABLE `biz_leave_application`
ADD COLUMN `OBJECT_ID` varchar(20) DEFAULT NULL COMMENT '关联业务单据ID' AFTER `PROCESS_ID`
SQL);
    $summary['leaveObjectIdColumn'] = 'created';
}

if ($apply && !$travelIndexExists) {
    Db::execute(<<<'SQL'
ALTER TABLE `biz_leave_application`
ADD INDEX `idx_leave_after_sales_travel` (`TENANT_ID`, `OBJECT_ID`, `category`, `DELETE_FLAG`)
SQL);
    $summary['travelStatisticsIndex'] = 'created';
}

fwrite(STDOUT, json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
