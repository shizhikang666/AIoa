<?php

declare(strict_types=1);

namespace Oa\IsolatedValidation;

use RuntimeException;
use app\service\auth\RbacService;
use app\service\auth\TokenService;
use think\facade\Db;
use function Oa\IsolatedValidationParameters\environmentConfiguration;

/** @return array<string, mixed> */
function approvalAfter(string $processId, string $oldTaskId, string $nextTaskId): array
{
    if ($processId === '' || $oldTaskId === '' || $nextTaskId === '') {
        throw new RuntimeException('isolated approval identifiers are missing');
    }

    $runtimeTasks = Db::name('act_ru_task')->where('PROC_INST_ID_', $processId)->select()->toArray();
    $nextTask = Db::name('act_ru_task')->where('ID_', $nextTaskId)->find();
    $historicOld = Db::name('act_hi_taskinst')->where('ID_', $oldTaskId)->find();
    $process = Db::name('act_hi_procinst')->where('PROC_INST_ID_', $processId)->find();
    $nextUser = is_array($nextTask)
        ? Db::name('sys_user')->where('ID', (string) ($nextTask['ASSIGNEE_'] ?? ''))->find()
        : null;
    $nextToken = '';
    $authorization = [];
    $directPendingCount = -1;
    if (is_array($nextUser) && $nextUser !== []) {
        $auth = (new RbacService())->buildForUser($nextUser);
        $validation = environmentConfiguration();
        $devicePrefix = strtoupper((string) preg_replace(
            '/[^a-z0-9]+/i',
            '_',
            $validation['runLabel'] . '_' . $validation['runDate']
        ));
        $auth['device'] = $devicePrefix . '_ISOLATED_NEXT_TASK_VISIBILITY';
        $nextToken = (new TokenService())->create($nextUser, $auth);
        $authorization = authorizationSummary($auth);
        $directPendingCount = Db::name('act_ru_task')
            ->where('ASSIGNEE_', (string) ($nextUser['ID'] ?? ''))
            ->count();
    }

    return [
        'runtimeTaskCount' => count($runtimeTasks),
        'oldRuntimeTaskCount' => Db::name('act_ru_task')->where('ID_', $oldTaskId)->count(),
        'nextTaskExists' => is_array($nextTask) && $nextTask !== [],
        'nextTaskMatchesResponse' => is_array($nextTask) && (string) ($nextTask['ID_'] ?? '') === $nextTaskId,
        'nextTaskProcessMatches' => is_array($nextTask)
            && (string) ($nextTask['PROC_INST_ID_'] ?? '') === $processId,
        'nextTaskIsOnlyProcessTask' => count($runtimeTasks) === 1
            && (string) ($runtimeTasks[0]['ID_'] ?? '') === $nextTaskId
            && (string) ($runtimeTasks[0]['PROC_INST_ID_'] ?? '') === $processId,
        'nextTaskDefinitionKey' => is_array($nextTask) ? (string) ($nextTask['TASK_DEF_KEY_'] ?? '') : '',
        'nextAssigneeActive' => is_array($nextUser) && $nextUser !== [] && (string) ($nextUser['USER_STATUS'] ?? '') === 'ENABLE',
        'nextAssigneeTenantMatches' => is_array($nextUser)
            && is_array($nextTask)
            && (string) ($nextUser['TENANT_ID'] ?? '') === (string) ($nextTask['TENANT_ID_'] ?? ''),
        'nextTaskTenantMatchesProcess' => is_array($nextTask)
            && is_array($process)
            && (string) ($nextTask['TENANT_ID_'] ?? '') === (string) ($process['TENANT_ID_'] ?? ''),
        'historicTaskEnded' => is_array($historicOld) && trim((string) ($historicOld['END_TIME_'] ?? '')) !== '',
        'processStillActive' => is_array($process)
            && (string) ($process['STATE_'] ?? '') === 'ACTIVE'
            && trim((string) ($process['END_TIME_'] ?? '')) === '',
        'nextToken' => $nextToken,
        'directPendingCount' => $directPendingCount,
        'authorization' => $authorization,
        'businessFingerprints' => businessFingerprints(),
    ];
}
