<?php

declare(strict_types=1);

namespace Oa\IsolatedValidation;

use RuntimeException;
use app\service\auth\RbacService;
use app\service\auth\TokenService;
use think\facade\Db;
use function Oa\IsolatedValidationParameters\environmentConfiguration;

/** @return array<string, mixed> */
function approvalContext(): array
{
    $rows = Db::query(<<<'SQL'
SELECT t.ID_ AS task_id,t.PROC_INST_ID_ AS process_id,t.ASSIGNEE_ AS user_id
FROM act_ru_task t
INNER JOIN act_hi_procinst p ON BINARY p.PROC_INST_ID_=BINARY t.PROC_INST_ID_
INNER JOIN sys_user u ON BINARY u.ID=BINARY t.ASSIGNEE_
INNER JOIN act_hi_varinst v ON BINARY v.PROC_INST_ID_=BINARY t.PROC_INST_ID_ AND v.NAME_='procure'
INNER JOIN sys_user n ON BINARY n.ID=BINARY v.TEXT_
WHERE p.PROC_DEF_KEY_='Process_procure'
AND t.TASK_DEF_KEY_='Activity_approval'
AND p.STATE_='ACTIVE'
AND p.END_TIME_ IS NULL
AND u.USER_STATUS='ENABLE'
AND (u.DELETE_FLAG IS NULL OR u.DELETE_FLAG='NOT_DELETE')
AND n.USER_STATUS='ENABLE'
AND (n.DELETE_FLAG IS NULL OR n.DELETE_FLAG='NOT_DELETE')
AND BINARY u.TENANT_ID=BINARY t.TENANT_ID_
AND BINARY n.TENANT_ID=BINARY t.TENANT_ID_
ORDER BY t.CREATE_TIME_,t.ID_
SQL);
    if (count($rows) !== 1) {
        throw new RuntimeException('eligible continuation task count changed');
    }

    $candidate = $rows[0];
    $user = Db::name('sys_user')->where('ID', $candidate['user_id'])->find();
    if (!is_array($user) || $user === []) {
        throw new RuntimeException('candidate user missing');
    }

    $auth = (new RbacService())->buildForUser($user);
    $validation = environmentConfiguration();
    $devicePrefix = strtoupper((string) preg_replace(
        '/[^a-z0-9]+/i',
        '_',
        $validation['runLabel'] . '_' . $validation['runDate']
    ));
    $auth['device'] = $devicePrefix . '_ISOLATED_CONTINUATION_VALIDATION';

    return [
        'token' => (new TokenService())->create($user, $auth),
        'taskId' => (string) $candidate['task_id'],
        'processId' => (string) $candidate['process_id'],
        'authorization' => authorizationSummary($auth),
        'businessFingerprints' => businessFingerprints(),
    ];
}
