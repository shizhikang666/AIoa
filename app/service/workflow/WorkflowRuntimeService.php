<?php

declare(strict_types=1);

namespace app\service\workflow;

use app\service\biz\PurchaseOrderService;
use app\service\biz\SaleProjectService;
use app\service\biz\SettlementAccountService;
use RuntimeException;
use think\facade\Db;

/**
 * Transitional workflow runtime writes for Camunda-compatible act_* tables.
 */
class WorkflowRuntimeService
{
    private const PROCESS_ASK_LEAVE = 'Process_ask_leave';
    private const PROCESS_PROCURE = 'Process_procure';
    private const PROCESS_PROCURE_IN_WAREHOUSE = 'Process_procure_in_warehouse';
    private const PROCESS_REIMBURSEMENT = 'Process_reimbursement';
    private const PROCESS_MAKE_PAYMENT = 'Process_make_payment';
    private const PROCESS_PAYMENT = 'Process_payment';
    private const PROCESS_SALE_PROJECT_INIT = 'Process_sale_project_init';
    private const PROCESS_SALE_PROJECT_DELIVERY = 'Process_sale_project_delivery';
    private const PROCESS_SALE_PROJECT_PLAY = 'Process_sale_project_play';
    private const ACTIVITY_APPROVAL = 'Activity_approval';
    private const ACTIVITY_PROCURE_APPROVAL = 'Activity_procure_approval';
    private const ACTIVITY_APPROVAL_PROCURE = 'Activity_approval_procure';
    private const ACTIVITY_PAY_APPROVAL = 'Activity_pay_approval';
    private const ACTIVITY_PAYMENT_APPROVAL = 'Activity_payment_approval';
    private const TASK_NAME_APPROVAL = "\u{53c2}\u{4e0e}\u{4eba}\u{5ba1}\u{6279}";
    private const TASK_NAME_LEADER_APPROVAL = "\u{9886}\u{5bfc}\u{5ba1}\u{6279}";
    private const TASK_NAME_PROCURE_CONFIRM = "\u{91c7}\u{8d2d}";
    private const TASK_NAME_GENERAL_OFFICE_APPROVAL = "\u{603b}\u{7ecf}\u{529e}\u{5ba1}\u{6279}";
    private const TASK_NAME_FINANCE_EXPENSE_CONFIRM = "\u{8d22}\u{52a1}\u{652f}\u{51fa}\u{786e}\u{8ba4}";
    private const TASK_NAME_FINANCE_CONFIRM = "\u{8d22}\u{52a1}\u{786e}\u{8ba4}";
    private const STATUS_PROGRESS = 'progress';
    private const STATUS_AGREE = 'AGREE';
    private const STATUS_REJECT = 'REJECT';
    private const STATUS_CANCEL = 'cancel';
    private const LEAVE_END_EVENT = 'Event_0kb2f2q';
    private const NOT_DELETE = 'NOT_DELETE';
    private const LEAVE_CATEGORY_ANNUAL = 'annualLeave';
    private const PROJECT_PLAY_SETTLEMENT_CATEGORY = 'PROJECT_PLAY';

    private const LEAVE_CATEGORY_LABELS = [
            self::LEAVE_CATEGORY_ANNUAL => "\u{5e74}\u{5047}",
        'leaveOfAbsence' => "\u{4e8b}\u{5047}",
        'bid' => "\u{6295}\u{6807}",
        'ProjectInstallation' => "\u{9879}\u{76ee}\u{5b89}\u{88c5}",
        'AfterSalesService' => "\u{552e}\u{540e}\u{5904}\u{7406}",
        'visit_clients' => "\u{62dc}\u{8bbf}\u{5ba2}\u{6237}",
    ];

    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService = new PurchaseOrderService(),
        private readonly SettlementAccountService $settlementAccountService = new SettlementAccountService(),
        private readonly SaleProjectService $saleProjectService = new SaleProjectService()
    ) {
    }

    private const NON_PROJECT_START_KEYS = [
        self::PROCESS_PROCURE,
        self::PROCESS_PROCURE_IN_WAREHOUSE,
        self::PROCESS_REIMBURSEMENT,
        self::PROCESS_MAKE_PAYMENT,
        self::PROCESS_PAYMENT,
    ];

    private const PROCESS_START_EVENTS = [
        self::PROCESS_ASK_LEAVE => 'Event_1rplikq',
        self::PROCESS_PROCURE => 'StartEvent_1',
        self::PROCESS_PROCURE_IN_WAREHOUSE => 'StartEvent_1',
        self::PROCESS_REIMBURSEMENT => 'Event_09dguuq',
        self::PROCESS_MAKE_PAYMENT => 'Event_09dguuq',
        self::PROCESS_PAYMENT => 'StartEvent_1',
        self::PROCESS_SALE_PROJECT_INIT => 'StartEvent_1',
        self::PROCESS_SALE_PROJECT_DELIVERY => 'StartEvent_1',
        self::PROCESS_SALE_PROJECT_PLAY => 'StartEvent_1',
    ];

    private const PROCESS_END_EVENTS = [
        self::PROCESS_ASK_LEAVE => self::LEAVE_END_EVENT,
        self::PROCESS_PROCURE => self::LEAVE_END_EVENT,
        self::PROCESS_PROCURE_IN_WAREHOUSE => self::LEAVE_END_EVENT,
        self::PROCESS_REIMBURSEMENT => 'Event_1q6ckfm',
        self::PROCESS_MAKE_PAYMENT => 'Event_1q6ckfm',
        self::PROCESS_PAYMENT => 'Event_148gpcc',
        self::PROCESS_SALE_PROJECT_INIT => self::LEAVE_END_EVENT,
        self::PROCESS_SALE_PROJECT_DELIVERY => self::LEAVE_END_EVENT,
        self::PROCESS_SALE_PROJECT_PLAY => 'Event_1q6ckfm',
    ];

    public function startLeaveProcess(array $input, array $payload = []): array
    {
        $currentUserId = $this->requiredCurrentUserId($payload);
        $starter = $this->userRow($currentUserId);
        $tenantId = $this->tenantId($input, $payload, $starter);
        $orgId = $this->orgId($payload, $starter);
        $category = $this->requiredString($input, 'category');
        $categoryLabel = self::LEAVE_CATEGORY_LABELS[$category] ?? null;
        if ($categoryLabel === null) {
            throw new RuntimeException('invalid category', 400);
        }

        [$startTime, $startMillis] = $this->requiredTime($input, 'startTime');
        [$endTime, $endMillis] = $this->optionalTime($input, 'endTime');
        $approveUserIds = $this->requiredStringList($input['approveUserIdList'] ?? $input['approveUsers'] ?? []);
        $copyUserIds = $this->stringList($input['copyUserIdList'] ?? $input['copyUsers'] ?? []);
        $fileIds = $this->stringList($input['fileIdList'] ?? []);
        $this->assertUsers($approveUserIds, $tenantId, 'approve user not found');
        $this->assertUsers($copyUserIds, $tenantId, 'copy user not found');

        $assignee = $approveUserIds[0];
        $starterName = trim((string)($starter['NAME'] ?? $payload['name'] ?? $currentUserId));
        $title = $starterName . "\u{53d1}\u{8d77}\u{7684}" . $categoryLabel . "\u{7533}\u{8bf7}";
        $remark = $this->optionalString($input['remark'] ?? null, 4000);
        $objectId = $this->optionalString($input['objectId'] ?? null, 255) ?? '';
        $amount = $this->optionalString($input['amount'] ?? null, 100) ?? '';
        $isEdit = array_key_exists('isEdit', $input) ? (bool)$input['isEdit'] : $endTime === null;

        $variables = [
            'initiator' => $currentUserId,
            'approveUserIdList' => $approveUserIds,
            'amount' => $amount,
            'copyUserIdList' => $copyUserIds,
            'fileIdList' => $fileIds === [] ? null : $fileIds,
            'org' => $orgId,
            'approval' => true,
            'remark' => $remark,
            'title' => $title,
            'tenantId' => $tenantId,
            'startTime' => $this->dateVariable($startMillis),
            'endTime' => $endMillis === null ? null : $this->dateVariable($endMillis),
            'category' => $category,
            'objectId' => $objectId,
            'isEdit' => $isEdit,
            'status' => self::STATUS_PROGRESS,
            'nrOfInstances' => count($approveUserIds),
            'nrOfCompletedInstances' => 0,
            'nrOfActiveInstances' => 1,
            'loopCounter' => 0,
            'user' => $assignee,
        ];

        return $this->startInitialApprovalProcess(
            self::PROCESS_ASK_LEAVE,
            $currentUserId,
            $tenantId,
            $approveUserIds,
            $copyUserIds,
            $fileIds,
            $assignee,
            $title,
            $variables,
            self::TASK_NAME_APPROVAL,
            [
                'startTime' => $startTime,
                'endTime' => $endTime,
            ]
        );
    }

    public function startGeneralProcess(string $processKey, array $input, array $payload = []): array
    {
        if (!in_array($processKey, self::NON_PROJECT_START_KEYS, true)) {
            throw new RuntimeException('process start is deferred for this process', 400);
        }

        $currentUserId = $this->requiredCurrentUserId($payload);
        $starter = $this->userRow($currentUserId);
        $tenantId = $this->tenantId($input, $payload, $starter);
        $orgId = $this->orgId($payload, $starter);
        $approveUserIds = $this->requiredStringList($input['approveUserIdList'] ?? $input['approveUsers'] ?? []);
        $copyUserIds = $this->stringList($input['copyUserIdList'] ?? $input['copyUsers'] ?? []);
        $fileIds = $this->stringList($input['fileIdList'] ?? []);
        $this->assertUsers($approveUserIds, $tenantId, 'approve user not found');
        $this->assertUsers($copyUserIds, $tenantId, 'copy user not found');
        $this->assertGeneralProcessInput($processKey, $input, $tenantId);

        $assignee = $approveUserIds[0];
        $starterName = trim((string)($starter['NAME'] ?? $payload['name'] ?? $currentUserId));
        $title = $this->generalProcessTitle($processKey, $input, $starterName);
        $variables = $this->generalProcessVariables($input);
        $variables = array_merge($variables, [
            'initiator' => $currentUserId,
            'approveUserIdList' => $approveUserIds,
            'copyUserIdList' => $copyUserIds,
            'fileIdList' => $fileIds === [] ? null : $fileIds,
            'org' => $orgId,
            'approval' => true,
            'title' => $title,
            'tenantId' => $tenantId,
            'status' => self::STATUS_PROGRESS,
            'nrOfInstances' => count($approveUserIds),
            'nrOfCompletedInstances' => 0,
            'nrOfActiveInstances' => 1,
            'loopCounter' => 0,
            'user' => $assignee,
        ]);

        return $this->startInitialApprovalProcess(
            $processKey,
            $currentUserId,
            $tenantId,
            $approveUserIds,
            $copyUserIds,
            $fileIds,
            $assignee,
            $title,
            $variables,
            $this->firstApprovalTaskName($processKey)
        );
    }

    public function startProjectInitProcess(array $input, array $payload = []): array
    {
        $currentUserId = $this->requiredCurrentUserId($payload);
        $starter = $this->userRow($currentUserId);
        $tenantId = $this->tenantId($input, $payload, $starter);
        $orgId = $this->orgId($payload, $starter);
        $projectId = $this->requiredInputString($input, 'bizSaleProjectId');
        $approveUserIds = $this->requiredStringList($input['approveUserIdList'] ?? $input['approveUsers'] ?? []);
        $copyUserIds = $this->stringList($input['copyUserIdList'] ?? $input['copyUsers'] ?? []);
        $fileIds = $this->requiredStringList($input['fileIdList'] ?? []);
        $productList = $this->decodedArrayList($input['productList'] ?? null, 'productList');
        $this->assertUsers($approveUserIds, $tenantId, 'approve user not found');
        $this->assertUsers($copyUserIds, $tenantId, 'copy user not found');
        $this->assertProjectInitInput($input);

        return Db::transaction(function () use (
            $input,
            $payload,
            $currentUserId,
            $starter,
            $tenantId,
            $orgId,
            $projectId,
            $approveUserIds,
            $copyUserIds,
            $fileIds,
            $productList
        ): array {
            $project = $this->saleProjectService->markProjectPendingApproval($projectId, $payload, $tenantId);
            $assignee = $approveUserIds[0];
            $starterName = trim((string)($starter['NAME'] ?? $payload['name'] ?? $currentUserId));
            $projectName = trim((string)($project['PROJECT_NAME'] ?? $projectId));
            $title = $starterName . "\u{53d1}\u{8d77}\u{7684}" . $projectName . "\u{9879}\u{76ee}\u{7533}\u{8bf7}";
            $completionDate = $this->projectCompletionDate($input['completionDate'] ?? null);
            $variables = $this->generalProcessVariables(array_merge($input, [
                'bizSaleProjectId' => $projectId,
                'projectId' => $projectId,
                'productList' => $productList,
                'completionDate' => $this->dateVariable(strtotime($completionDate) * 1000),
            ]));
            $variables = array_merge($variables, [
                'initiator' => $currentUserId,
                'approveUserIdList' => $approveUserIds,
                'copyUserIdList' => $copyUserIds,
                'fileIdList' => $fileIds,
                'org' => $orgId,
                'approval' => true,
                'title' => $title,
                'tenantId' => $tenantId,
                'status' => self::STATUS_PROGRESS,
                'nrOfInstances' => count($approveUserIds),
                'nrOfCompletedInstances' => 0,
                'nrOfActiveInstances' => 1,
                'loopCounter' => 0,
                'user' => $assignee,
            ]);

            return $this->startInitialApprovalProcess(
                self::PROCESS_SALE_PROJECT_INIT,
                $currentUserId,
                $tenantId,
                $approveUserIds,
                $copyUserIds,
                $fileIds,
                $assignee,
                $title,
                $variables,
                self::TASK_NAME_APPROVAL,
                [
                    'projectId' => $projectId,
                    'projectState' => 'PENDING_APPROVAL',
                ]
            );
        });
    }

    public function startProjectPlayProcess(array $input, array $payload = []): array
    {
        $currentUserId = $this->requiredCurrentUserId($payload);
        $starter = $this->userRow($currentUserId);
        $tenantId = $this->tenantId($input, $payload, $starter);
        $orgId = $this->orgId($payload, $starter);
        $projectId = $this->requiredProjectIdInput($input);
        $approveUserIds = $this->requiredStringList($input['approveUserIdList'] ?? $input['approveUsers'] ?? []);
        $copyUserIds = $this->stringList($input['copyUserIdList'] ?? $input['copyUsers'] ?? []);
        $fileIds = $this->stringList($input['fileIdList'] ?? []);
        $this->assertUsers($approveUserIds, $tenantId, 'approve user not found');
        $this->assertUsers($copyUserIds, $tenantId, 'copy user not found');
        $this->assertSingleUserInput($input, 'treasurer', $tenantId, 'treasurer user not found');
        $this->requiredInputString($input, 'accountId');
        $this->requiredInputTime($input, 'payerTime');
        $this->requiredPositiveInputDecimal($input, 'amount');

        $project = $this->saleProjectService->workflowProjectStartInfo($projectId, $payload, $tenantId);
        $assignee = $approveUserIds[0];
        $starterName = trim((string)($starter['NAME'] ?? $payload['name'] ?? $currentUserId));
        $projectName = trim((string)($project['PROJECT_NAME'] ?? $projectId));
        $title = $starterName . "\u{53d1}\u{8d77}\u{7684}" . $projectName . "\u{9879}\u{76ee}\u{6536}\u{6b3e}\u{786e}\u{8ba4}";
        $variables = $this->generalProcessVariables(array_merge($input, [
            'projectId' => $projectId,
            'bizSaleProjectId' => $projectId,
            'objectId' => $projectId,
            'settlementCategory' => self::PROJECT_PLAY_SETTLEMENT_CATEGORY,
        ]));
        $variables = array_merge($variables, [
            'initiator' => $currentUserId,
            'approveUserIdList' => $approveUserIds,
            'copyUserIdList' => $copyUserIds,
            'fileIdList' => $fileIds === [] ? null : $fileIds,
            'org' => $orgId,
            'approval' => true,
            'title' => $title,
            'tenantId' => $tenantId,
            'status' => self::STATUS_PROGRESS,
            'nrOfInstances' => count($approveUserIds),
            'nrOfCompletedInstances' => 0,
            'nrOfActiveInstances' => 1,
            'loopCounter' => 0,
            'user' => $assignee,
        ]);

        return $this->startInitialApprovalProcess(
            self::PROCESS_SALE_PROJECT_PLAY,
            $currentUserId,
            $tenantId,
            $approveUserIds,
            $copyUserIds,
            $fileIds,
            $assignee,
            $title,
            $variables,
            self::TASK_NAME_APPROVAL,
            [
                'projectId' => $projectId,
                'projectState' => (string)($project['PROJECT_STATE'] ?? ''),
                'playState' => (string)($project['PLAY_STATE'] ?? ''),
            ]
        );
    }

    public function startProjectDeliveryProcess(array $input, array $payload = []): array
    {
        $currentUserId = $this->requiredCurrentUserId($payload);
        $starter = $this->userRow($currentUserId);
        $tenantId = $this->tenantId($input, $payload, $starter);
        $orgId = $this->orgId($payload, $starter);
        $projectId = $this->requiredProjectIdInput($input);
        $approveUserIds = $this->requiredStringList($input['approveUserIdList'] ?? $input['approveUsers'] ?? []);
        $copyUserIds = $this->stringList($input['copyUserIdList'] ?? $input['copyUsers'] ?? []);
        $fileIds = $this->stringList($input['fileIdList'] ?? []);
        $projectProductItemList = $this->decodedArrayList(
            $input['projectProductItemList'] ?? $input['productList'] ?? null,
            'projectProductItemList'
        );
        $this->assertUsers($approveUserIds, $tenantId, 'approve user not found');
        $this->assertUsers($copyUserIds, $tenantId, 'copy user not found');
        foreach (['consignee', 'logisticsCategory', 'phone', 'logisticsId', 'freightCategory', 'unit', 'address'] as $key) {
            $this->requiredInputString($input, $key);
        }
        $this->requiredNonNegativeInputDecimal($input, 'freight');
        $freightTime = $this->requiredInputTime($input, 'freightTime');

        $project = $this->saleProjectService->workflowProjectDeliveryStartInfo(
            $projectId,
            $projectProductItemList,
            $payload,
            $tenantId
        );
        $assignee = $approveUserIds[0];
        $starterName = trim((string)($starter['NAME'] ?? $payload['name'] ?? $currentUserId));
        $projectName = trim((string)($project['PROJECT_NAME'] ?? $projectId));
        $title = $starterName . "\u{53d1}\u{8d77}\u{7684}" . $projectName . "\u{9879}\u{76ee}\u{53d1}\u{8d27}\u{5355}\u{7533}\u{8bf7}\u{786e}\u{8ba4}";
        $variables = $this->generalProcessVariables(array_merge($input, [
            'projectId' => $projectId,
            'bizSaleProjectId' => $projectId,
            'objectId' => $projectId,
            'projectProductItemList' => $projectProductItemList,
            'freightTime' => $this->dateVariable(strtotime($freightTime) * 1000),
        ]));
        $variables = array_merge($variables, [
            'initiator' => $currentUserId,
            'approveUserIdList' => $approveUserIds,
            'copyUserIdList' => $copyUserIds,
            'fileIdList' => $fileIds === [] ? null : $fileIds,
            'org' => $orgId,
            'approval' => true,
            'title' => $title,
            'tenantId' => $tenantId,
            'status' => self::STATUS_PROGRESS,
            'nrOfInstances' => count($approveUserIds),
            'nrOfCompletedInstances' => 0,
            'nrOfActiveInstances' => 1,
            'loopCounter' => 0,
            'user' => $assignee,
        ]);

        return $this->startInitialApprovalProcess(
            self::PROCESS_SALE_PROJECT_DELIVERY,
            $currentUserId,
            $tenantId,
            $approveUserIds,
            $copyUserIds,
            $fileIds,
            $assignee,
            $title,
            $variables,
            self::TASK_NAME_APPROVAL,
            [
                'projectId' => $projectId,
                'projectState' => (string)($project['PROJECT_STATE'] ?? ''),
                'projectProductItemCount' => (int)($project['PROJECT_PRODUCT_ITEM_COUNT'] ?? count($projectProductItemList)),
            ]
        );
    }

    /**
     * @param array<int, string> $approveUserIds
     * @param array<int, string> $copyUserIds
     * @param array<int, string> $fileIds
     * @param array<string, mixed> $variables
     * @param array<string, mixed> $extraResponse
     */
    private function startInitialApprovalProcess(
        string $processKey,
        string $currentUserId,
        string $tenantId,
        array $approveUserIds,
        array $copyUserIds,
        array $fileIds,
        string $assignee,
        string $title,
        array $variables,
        string $taskName,
        array $extraResponse = []
    ): array {
        return Db::transaction(function () use (
            $processKey,
            $currentUserId,
            $tenantId,
            $approveUserIds,
            $copyUserIds,
            $fileIds,
            $assignee,
            $title,
            $variables,
            $taskName,
            $extraResponse
        ): array {
            $definition = $this->processDefinition($processKey);
            $definitionId = (string)$definition['ID_'];
            $now = date('Y-m-d H:i:s');
            $processInstanceId = $this->uuid();
            $executionId = $this->uuid();
            $taskId = $this->uuid();
            $activityInstanceId = self::ACTIVITY_APPROVAL . ':' . $this->uuid();
            $startEvent = self::PROCESS_START_EVENTS[$processKey] ?? null;
            if ($startEvent === null) {
                throw new RuntimeException('process start is deferred for this process', 400);
            }

            Db::name('act_ru_execution')->insertAll([
                [
                    'ID_' => $processInstanceId,
                    'REV_' => 1,
                    'ROOT_PROC_INST_ID_' => $processInstanceId,
                    'PROC_INST_ID_' => $processInstanceId,
                    'BUSINESS_KEY_' => null,
                    'PARENT_ID_' => null,
                    'PROC_DEF_ID_' => $definitionId,
                    'ACT_ID_' => null,
                    'ACT_INST_ID_' => null,
                    'IS_ACTIVE_' => 0,
                    'IS_CONCURRENT_' => 0,
                    'IS_SCOPE_' => 1,
                    'IS_EVENT_SCOPE_' => 0,
                    'SUSPENSION_STATE_' => 1,
                    'CACHED_ENT_STATE_' => 16,
                    'SEQUENCE_COUNTER_' => 4,
                    'TENANT_ID_' => $tenantId,
                ],
                [
                    'ID_' => $executionId,
                    'REV_' => 1,
                    'ROOT_PROC_INST_ID_' => null,
                    'PROC_INST_ID_' => $processInstanceId,
                    'BUSINESS_KEY_' => null,
                    'PARENT_ID_' => $processInstanceId,
                    'PROC_DEF_ID_' => $definitionId,
                    'ACT_ID_' => self::ACTIVITY_APPROVAL,
                    'ACT_INST_ID_' => $activityInstanceId,
                    'IS_ACTIVE_' => 1,
                    'IS_CONCURRENT_' => count($approveUserIds) > 1 ? 1 : 0,
                    'IS_SCOPE_' => 1,
                    'IS_EVENT_SCOPE_' => 0,
                    'SUSPENSION_STATE_' => 1,
                    'CACHED_ENT_STATE_' => 18,
                    'SEQUENCE_COUNTER_' => 6,
                    'TENANT_ID_' => $tenantId,
                ],
            ]);

            Db::name('act_ru_task')->insert([
                'ID_' => $taskId,
                'REV_' => 1,
                'EXECUTION_ID_' => $executionId,
                'PROC_INST_ID_' => $processInstanceId,
                'PROC_DEF_ID_' => $definitionId,
                'NAME_' => $taskName,
                'TASK_DEF_KEY_' => self::ACTIVITY_APPROVAL,
                'ASSIGNEE_' => $assignee,
                'PRIORITY_' => 50,
                'CREATE_TIME_' => $now,
                'SUSPENSION_STATE_' => 1,
                'TENANT_ID_' => $tenantId,
            ]);

            Db::name('act_hi_procinst')->insert([
                'ID_' => $processInstanceId,
                'PROC_INST_ID_' => $processInstanceId,
                'BUSINESS_KEY_' => null,
                'PROC_DEF_KEY_' => $processKey,
                'PROC_DEF_ID_' => $definitionId,
                'START_TIME_' => $now,
                'END_TIME_' => null,
                'REMOVAL_TIME_' => null,
                'DURATION_' => null,
                'START_USER_ID_' => $currentUserId,
                'START_ACT_ID_' => $startEvent,
                'END_ACT_ID_' => null,
                'SUPER_PROCESS_INSTANCE_ID_' => null,
                'ROOT_PROC_INST_ID_' => $processInstanceId,
                'SUPER_CASE_INSTANCE_ID_' => null,
                'CASE_INST_ID_' => null,
                'DELETE_REASON_' => null,
                'TENANT_ID_' => $tenantId,
                'STATE_' => 'ACTIVE',
            ]);

            Db::name('act_hi_taskinst')->insert([
                'ID_' => $taskId,
                'TASK_DEF_KEY_' => self::ACTIVITY_APPROVAL,
                'PROC_DEF_KEY_' => $processKey,
                'PROC_DEF_ID_' => $definitionId,
                'ROOT_PROC_INST_ID_' => $processInstanceId,
                'PROC_INST_ID_' => $processInstanceId,
                'EXECUTION_ID_' => $executionId,
                'ACT_INST_ID_' => $activityInstanceId,
                'NAME_' => $taskName,
                'ASSIGNEE_' => $assignee,
                'START_TIME_' => $now,
                'END_TIME_' => null,
                'DURATION_' => null,
                'DELETE_REASON_' => null,
                'PRIORITY_' => 50,
                'TENANT_ID_' => $tenantId,
                'REMOVAL_TIME_' => null,
            ]);

            Db::name('act_hi_actinst')->insert([
                'ID_' => $activityInstanceId,
                'PARENT_ACT_INST_ID_' => $processInstanceId,
                'PROC_DEF_KEY_' => $processKey,
                'PROC_DEF_ID_' => $definitionId,
                'ROOT_PROC_INST_ID_' => $processInstanceId,
                'PROC_INST_ID_' => $processInstanceId,
                'EXECUTION_ID_' => $executionId,
                'ACT_ID_' => self::ACTIVITY_APPROVAL,
                'TASK_ID_' => $taskId,
                'ACT_NAME_' => $taskName,
                'ACT_TYPE_' => 'userTask',
                'ASSIGNEE_' => $assignee,
                'START_TIME_' => $now,
                'END_TIME_' => null,
                'DURATION_' => null,
                'ACT_INST_STATE_' => 0,
                'SEQUENCE_COUNTER_' => 6,
                'TENANT_ID_' => $tenantId,
                'REMOVAL_TIME_' => null,
            ]);

            $this->insertVariables($variables, $definitionId, $processInstanceId, $processInstanceId, $tenantId, $now, $processKey);
            $ccRecordCount = $this->insertCopyUserRecords(
                $copyUserIds,
                $definitionId,
                $processInstanceId,
                $title,
                $currentUserId,
                $tenantId,
                $now,
                $processKey
            );
            $fileRelationCount = $this->insertWorkflowFileRelations(
                $fileIds,
                $processInstanceId,
                $currentUserId,
                $tenantId,
                $now,
                $processKey
            );

            return array_merge([
                'id' => $processInstanceId,
                'processInstanceId' => $processInstanceId,
                'processDefinitionId' => $definitionId,
                'processKey' => $processKey,
                'taskId' => $taskId,
                'assignee' => $assignee,
                'title' => $title,
                'status' => self::STATUS_PROGRESS,
                'ccRecordCount' => $ccRecordCount,
                'fileRelationCount' => $fileRelationCount,
            ], $extraResponse);
        });
    }

    private function generalProcessTitle(string $processKey, array $input, string $starterName): string
    {
        $category = $this->inputString($input['settlementCategory'] ?? $input['category'] ?? '');

        return match ($processKey) {
            self::PROCESS_PROCURE => $starterName . "\u{53d1}\u{8d77}\u{7684}\u{91c7}\u{8d2d}\u{7533}\u{8bf7}",
            self::PROCESS_PROCURE_IN_WAREHOUSE => $starterName . "\u{53d1}\u{8d77}\u{7684}\u{5165}\u{5e93}\u{7533}\u{8bf7}",
            self::PROCESS_REIMBURSEMENT => $starterName . "\u{53d1}\u{8d77}\u{7684}" . $category . "\u{62a5}\u{9500}\u{7533}\u{8bf7}",
            self::PROCESS_MAKE_PAYMENT => $starterName . "\u{53d1}\u{8d77}\u{7684}" . $category . "\u{4ed8}\u{6b3e}\u{7533}\u{8bf7}",
            self::PROCESS_PAYMENT => $starterName . "\u{53d1}\u{8d77}\u{7684}" . $category . "\u{6536}\u{6b3e}\u{5355}\u{7533}\u{8bf7}",
            default => $starterName . "\u{53d1}\u{8d77}\u{7684}\u{6d41}\u{7a0b}\u{7533}\u{8bf7}",
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function generalProcessVariables(array $input): array
    {
        $variables = [];
        foreach ($input as $key => $value) {
            $name = trim((string)$key);
            if ($name === '') {
                continue;
            }
            $variables[$name] = $this->generalProcessVariableValue($name, $value);
        }

        return $variables;
    }

    private function generalProcessVariableValue(string $name, mixed $value): mixed
    {
        if ($name === 'settlementCategory') {
            $value = $this->inputString($value);
        }
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === null) {
            return null;
        }

        if (in_array($name, ['payerTime', 'desirePurchaseDate', 'createTime', 'deliveryTime'], true)) {
            $timestamp = strtotime((string)$value);
            if ($timestamp !== false) {
                return $this->dateVariable($timestamp * 1000);
            }
        }

        return $value;
    }

    private function assertGeneralProcessInput(string $processKey, array $input, string $tenantId): void
    {
        match ($processKey) {
            self::PROCESS_PAYMENT => $this->assertPaymentProcessInput($input, $tenantId),
            self::PROCESS_REIMBURSEMENT, self::PROCESS_MAKE_PAYMENT => $this->assertPaymentOutProcessInput($input, $tenantId),
            self::PROCESS_PROCURE => $this->assertProcureProcessInput($input, $tenantId),
            self::PROCESS_PROCURE_IN_WAREHOUSE => $this->assertProcureWarehouseProcessInput($input),
            default => throw new RuntimeException('process start is deferred for this process', 400),
        };
    }

    private function assertPaymentProcessInput(array $input, string $tenantId): void
    {
        $this->requiredInputString($input, 'settlementCategory');
        $this->requiredInputString($input, 'accountId');
        $this->requiredInputTime($input, 'payerTime');
        $this->requiredPositiveInputDecimal($input, 'amount');
        $this->assertSingleUserInput($input, 'treasurer', $tenantId, 'treasurer user not found');
    }

    private function assertPaymentOutProcessInput(array $input, string $tenantId): void
    {
        $this->requiredPositiveInputDecimal($input, 'amount');
        $this->requiredInputString($input, 'bankAccount');
        $this->requiredInputString($input, 'bankName');
        $this->requiredInputString($input, 'payer');
        if (!array_key_exists('useAdvancePayment', $input)) {
            throw new RuntimeException('missing useAdvancePayment', 400);
        }
        $this->assertSingleUserInput($input, 'treasurer', $tenantId, 'treasurer user not found');
        if ($this->booleanValue($input['useAdvancePayment']) === true) {
            $this->requiredInputString($input, 'accountId');
            $this->requiredInputTime($input, 'payerTime');
        }
    }

    private function assertProcureProcessInput(array $input, string $tenantId): void
    {
        if (empty($input['supplier']) || !is_array($input['supplier'])) {
            throw new RuntimeException('missing supplier', 400);
        }
        $this->requiredInputTime($input, 'desirePurchaseDate');
        $this->assertSingleUserInput($input, 'procure', $tenantId, 'procure user not found');
        $this->assertUsers($this->stringList($input['approvesGeneralOffice'] ?? []), $tenantId, 'approves general office user not found');
    }

    private function assertProcureWarehouseProcessInput(array $input): void
    {
        $this->requiredInputString($input, 'orderId');
        $this->requiredInputString($input, 'warehousesId');
    }

    private function assertProjectInitInput(array $input): void
    {
        $this->requiredInputString($input, 'accountId');
        $this->requiredInputString($input, 'payerCategory');
        $this->requiredNonNegativeInputDecimal($input, 'initPrice');
        $this->requiredNonNegativeInputDecimal($input, 'rebateAmount');
        if (!array_key_exists('isInvoicing', $input)) {
            throw new RuntimeException('missing isInvoicing', 400);
        }
        if ($this->booleanValue($input['isInvoicing']) === null) {
            throw new RuntimeException('invalid isInvoicing', 400);
        }
        if ($this->booleanValue($input['isInvoicing']) === true
            && (empty($input['invoicingInfo']) || !is_array($input['invoicingInfo']))) {
            throw new RuntimeException('missing invoicingInfo', 400);
        }
    }

    private function assertSingleUserInput(array $input, string $key, string $tenantId, string $message): void
    {
        $value = $this->requiredInputString($input, $key);
        $this->assertUsers([$value], $tenantId, $message);
    }

    private function requiredInputString(array $input, string $key): string
    {
        $value = $this->inputString($input[$key] ?? null);
        if ($value === '') {
            throw new RuntimeException('missing ' . $key, 400);
        }

        return $value;
    }

    private function requiredProjectIdInput(array $input): string
    {
        $value = $this->inputString($input['projectId'] ?? $input['bizSaleProjectId'] ?? null);
        if ($value === '') {
            throw new RuntimeException('missing projectId', 400);
        }

        return $value;
    }

    private function requiredPositiveInputDecimal(array $input, string $key): string
    {
        $value = $this->requiredInputString($input, $key);
        if (!is_numeric($value) || (float)$value <= 0) {
            throw new RuntimeException('invalid ' . $key, 400);
        }

        return number_format((float)$value, 2, '.', '');
    }

    private function requiredNonNegativeInputDecimal(array $input, string $key): string
    {
        $value = $this->requiredInputString($input, $key);
        if (!is_numeric($value) || (float)$value < 0) {
            throw new RuntimeException('invalid ' . $key, 400);
        }

        return number_format((float)$value, 2, '.', '');
    }

    private function projectCompletionDate(mixed $value): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return date('Y-m-d H:i:s');
        }
        $timestamp = strtotime($text);
        if ($timestamp === false) {
            throw new RuntimeException('invalid completionDate', 400);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function requiredInputTime(array $input, string $key): string
    {
        $value = $this->requiredInputString($input, $key);
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException('invalid ' . $key, 400);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function inputString(mixed $value): string
    {
        if (is_array($value)) {
            if (isset($value['id']) || isset($value['userId']) || isset($value['value']) || isset($value['key'])) {
                return trim((string)($value['id'] ?? $value['userId'] ?? $value['value'] ?? $value['key'] ?? ''));
            }

            return implode('/', array_values(array_filter(array_map(
                static fn (mixed $item): string => is_scalar($item) ? trim((string)$item) : '',
                $value
            ), static fn (string $item): bool => $item !== '')));
        }

        return trim((string)($value ?? ''));
    }

    private function firstApprovalTaskName(string $processKey): string
    {
        return $processKey === self::PROCESS_PROCURE ? self::TASK_NAME_LEADER_APPROVAL : self::TASK_NAME_APPROVAL;
    }

    private function canCancelInitialApprovalProcess(string $processKey): bool
    {
        return $processKey === self::PROCESS_ASK_LEAVE
            || $processKey === self::PROCESS_SALE_PROJECT_INIT
            || $processKey === self::PROCESS_SALE_PROJECT_DELIVERY
            || $processKey === self::PROCESS_SALE_PROJECT_PLAY
            || in_array($processKey, self::NON_PROJECT_START_KEYS, true);
    }

    private function endEventForProcess(string $processKey): string
    {
        return self::PROCESS_END_EVENTS[$processKey] ?? self::LEAVE_END_EVENT;
    }

    public function approveTask(array $input, array $payload = []): array
    {
        $taskId = $this->requiredString($input, 'id');
        $form = $input['form'] ?? null;
        if (!is_array($form)) {
            throw new RuntimeException('missing form', 400);
        }

        $approval = $this->booleanValue($form['approval'] ?? $input['approval'] ?? null);
        $comment = $this->optionalString($form['comment'] ?? $input['comment'] ?? null, 4000);
        if ($approval !== true) {
            return $this->transitionApprovalTask($taskId, $payload, self::STATUS_REJECT, false, $comment, $form);
        }

        return $this->transitionApprovalTask($taskId, $payload, self::STATUS_AGREE, true, $comment, $form);
    }

    public function rejectTask(array $input, array $payload = []): array
    {
        $taskId = $this->requiredString($input, 'id');
        $form = $input['form'] ?? [];
        $form = is_array($form) ? $form : [];
        $comment = $this->optionalString($form['comment'] ?? $input['comment'] ?? null, 4000);

        return $this->transitionApprovalTask($taskId, $payload, self::STATUS_REJECT, false, $comment, $form);
    }

    public function cancelProcess(array $input, array $payload = []): array
    {
        $currentUserId = $this->requiredCurrentUserId($payload);
        $processInstanceId = $this->requiredString($input, 'id');

        return Db::transaction(function () use ($processInstanceId, $currentUserId): array {
            $historicProcess = Db::name('act_hi_procinst')
                ->where('PROC_INST_ID_', $processInstanceId)
                ->lock(true)
                ->find();
            if (!is_array($historicProcess) || $historicProcess === []) {
                throw new RuntimeException('process not found or completed', 404);
            }
            if ((string)($historicProcess['STATE_'] ?? '') !== 'ACTIVE' || !empty($historicProcess['END_TIME_'])) {
                throw new RuntimeException('process not found or completed', 404);
            }
            if ((string)($historicProcess['START_USER_ID_'] ?? '') !== $currentUserId) {
                throw new RuntimeException('process can only be cancelled by the initiator', 403);
            }

            $definitionId = (string)($historicProcess['PROC_DEF_ID_'] ?? '');
            $definitionKey = (string)($historicProcess['PROC_DEF_KEY_'] ?? $this->definitionKey($definitionId));
            if (!$this->canCancelInitialApprovalProcess($definitionKey)) {
                throw new RuntimeException('process cancel is deferred for this process', 400);
            }

            $finishedTaskCount = Db::name('act_hi_taskinst')
                ->where('PROC_INST_ID_', $processInstanceId)
                ->whereNotNull('END_TIME_')
                ->count();
            if ($finishedTaskCount > 0) {
                throw new RuntimeException('process already has completed tasks and cannot be cancelled', 400);
            }

            $task = Db::name('act_ru_task')
                ->where('PROC_INST_ID_', $processInstanceId)
                ->order('CREATE_TIME_', 'asc')
                ->lock(true)
                ->find();
            if (!is_array($task) || $task === []) {
                throw new RuntimeException('no active user task to cancel', 400);
            }
            if ((string)($task['TASK_DEF_KEY_'] ?? '') !== self::ACTIVITY_APPROVAL) {
                throw new RuntimeException('process cancel is deferred for this activity', 400);
            }

            $taskId = (string)$task['ID_'];
            $executionId = (string)($task['EXECUTION_ID_'] ?? $processInstanceId);
            $tenantId = (string)($task['TENANT_ID_'] ?? ($historicProcess['TENANT_ID_'] ?? ''));
            $historicTask = Db::name('act_hi_taskinst')->where('ID_', $taskId)->lock(true)->find();
            $now = date('Y-m-d H:i:s');
            $taskStartTime = is_array($historicTask) ? (string)($historicTask['START_TIME_'] ?? $task['CREATE_TIME_'] ?? '') : (string)($task['CREATE_TIME_'] ?? '');
            $processStartTime = (string)($historicProcess['START_TIME_'] ?? '');
            $duration = $this->durationMillis($taskStartTime, $now);
            $processDuration = $this->durationMillis($processStartTime, $now);
            $activityInstanceId = is_array($historicTask) ? (string)($historicTask['ACT_INST_ID_'] ?? '') : '';
            if ($activityInstanceId === '') {
                $activityInstanceId = (string)Db::name('act_hi_actinst')
                    ->where('TASK_ID_', $taskId)
                    ->value('ID_');
            }

            $this->upsertHistoryVariables([
                'approval' => false,
                'cancel' => true,
                'status' => self::STATUS_CANCEL,
                'state' => self::STATUS_CANCEL,
                'nrOfCompletedInstances' => 0,
                'nrOfActiveInstances' => 0,
            ], $definitionId, $processInstanceId, $executionId, $activityInstanceId, $tenantId, $now, $definitionKey);

            if ($definitionKey === self::PROCESS_SALE_PROJECT_INIT) {
                $this->saleProjectService->rejectProjectInitFromWorkflow(
                    $this->historyVariableValues($processInstanceId),
                    $tenantId,
                    $currentUserId
                );
            }

            Db::name('act_hi_taskinst')->where('ID_', $taskId)->update([
                'END_TIME_' => $now,
                'DURATION_' => $duration,
                'DELETE_REASON_' => 'deleted',
            ]);
            Db::name('act_hi_actinst')->where('TASK_ID_', $taskId)->update([
                'END_TIME_' => $now,
                'DURATION_' => $duration,
                'ACT_INST_STATE_' => 4,
            ]);
            Db::name('act_hi_procinst')->where('PROC_INST_ID_', $processInstanceId)->update([
                'END_TIME_' => $now,
                'DURATION_' => $processDuration,
                'END_ACT_ID_' => $this->endEventForProcess($definitionKey),
                'STATE_' => 'COMPLETED',
            ]);

            Db::name('act_ru_task')->where('PROC_INST_ID_', $processInstanceId)->delete();
            Db::name('act_ru_variable')->where('PROC_INST_ID_', $processInstanceId)->delete();
            Db::name('act_ru_execution')
                ->where('PROC_INST_ID_', $processInstanceId)
                ->where('ID_', '<>', $processInstanceId)
                ->delete();
            Db::name('act_ru_execution')->where('ID_', $processInstanceId)->delete();

            return [
                'id' => $processInstanceId,
                'processInstanceId' => $processInstanceId,
                'taskId' => $taskId,
                'processKey' => $definitionKey,
                'status' => self::STATUS_CANCEL,
                'state' => self::STATUS_CANCEL,
                'cancel' => true,
            ];
        });
    }

    public function editLeaveProcess(array $input, array $payload = []): array
    {
        $currentUserId = $this->requiredCurrentUserId($payload);
        $processInstanceId = $this->requiredString($input, 'id');
        [$endTime, $endMillis] = $this->requiredTime($input, 'endTime');
        $amount = $this->requiredDecimal($input['amount'] ?? null, 'amount');
        $remark = $this->optionalString($input['remark'] ?? null, 4000);

        return Db::transaction(function () use ($processInstanceId, $currentUserId, $endTime, $endMillis, $amount, $remark): array {
            $historicProcess = Db::name('act_hi_procinst')
                ->where('PROC_INST_ID_', $processInstanceId)
                ->lock(true)
                ->find();
            if (!is_array($historicProcess) || $historicProcess === []) {
                throw new RuntimeException('process not found or completed', 404);
            }
            if ((string)($historicProcess['STATE_'] ?? '') !== 'ACTIVE' || !empty($historicProcess['END_TIME_'])) {
                throw new RuntimeException('process not found or completed', 404);
            }
            if ((string)($historicProcess['START_USER_ID_'] ?? '') !== $currentUserId) {
                throw new RuntimeException('leave process can only be edited by the initiator', 403);
            }

            $definitionId = (string)($historicProcess['PROC_DEF_ID_'] ?? '');
            $definitionKey = (string)($historicProcess['PROC_DEF_KEY_'] ?? $this->definitionKey($definitionId));
            if ($definitionKey !== self::PROCESS_ASK_LEAVE) {
                throw new RuntimeException('leave process edit is deferred for this process', 400);
            }

            $task = Db::name('act_ru_task')
                ->where('PROC_INST_ID_', $processInstanceId)
                ->order('CREATE_TIME_', 'asc')
                ->lock(true)
                ->find();
            if (!is_array($task) || $task === []) {
                throw new RuntimeException('no active leave task to edit', 400);
            }
            if ((string)($task['TASK_DEF_KEY_'] ?? '') !== self::ACTIVITY_APPROVAL) {
                throw new RuntimeException('leave process edit is deferred for this activity', 400);
            }

            $variables = $this->historyVariableValues($processInstanceId);
            $isEdit = $this->booleanValue($variables['isEdit'] ?? null);
            if ($isEdit !== true) {
                throw new RuntimeException('leave process is not editable', 400);
            }

            $startTime = $this->requiredDateVariable($variables['startTime'] ?? null, 'startTime');
            if (strtotime($endTime) < strtotime($startTime)) {
                throw new RuntimeException('invalid leave time range', 400);
            }

            $tenantId = trim((string)($task['TENANT_ID_'] ?? ($variables['tenantId'] ?? ($historicProcess['TENANT_ID_'] ?? ''))));
            if ($tenantId === '') {
                throw new RuntimeException('missing leave tenantId', 400);
            }
            $initiator = trim((string)($variables['initiator'] ?? ($historicProcess['START_USER_ID_'] ?? '')));
            if ($initiator === '') {
                throw new RuntimeException('missing leave initiator', 400);
            }
            $this->assertNoOverlappingLeave($initiator, $startTime, $endTime, $tenantId, $processInstanceId);

            $taskId = (string)$task['ID_'];
            $executionId = (string)($task['EXECUTION_ID_'] ?? $processInstanceId);
            $historicTask = Db::name('act_hi_taskinst')->where('ID_', $taskId)->lock(true)->find();
            $activityInstanceId = is_array($historicTask) ? (string)($historicTask['ACT_INST_ID_'] ?? '') : '';
            if ($activityInstanceId === '') {
                $activityInstanceId = (string)Db::name('act_hi_actinst')
                    ->where('TASK_ID_', $taskId)
                    ->value('ID_');
            }
            $now = date('Y-m-d H:i:s');
            $updatedVariables = [
                'endTime' => $this->dateVariable((int)$endMillis),
                'amount' => $amount,
                'remark' => $remark,
                'isEdit' => false,
            ];

            $this->upsertRuntimeVariables($updatedVariables, $definitionId, $processInstanceId, $processInstanceId, $tenantId);
            $this->upsertHistoryVariables($updatedVariables, $definitionId, $processInstanceId, $executionId, $activityInstanceId, $tenantId, $now);

            return [
                'id' => $processInstanceId,
                'processInstanceId' => $processInstanceId,
                'taskId' => $taskId,
                'processKey' => self::PROCESS_ASK_LEAVE,
                'endTime' => $endTime,
                'amount' => $amount,
                'remark' => $remark,
                'isEdit' => false,
            ];
        });
    }

    private function transitionApprovalTask(
        string $taskId,
        array $payload,
        string $state,
        bool $approval,
        ?string $comment,
        array $form = []
    ): array {
        $currentUserId = $this->requiredCurrentUserId($payload);

        return Db::transaction(function () use ($taskId, $currentUserId, $state, $approval, $comment, $form): array {
            $task = Db::name('act_ru_task')
                ->where('ID_', $taskId)
                ->where('ASSIGNEE_', $currentUserId)
                ->lock(true)
                ->find();
            if (!is_array($task) || $task === []) {
                throw new RuntimeException('task not found or completed', 404);
            }

            $taskDefinitionKey = (string)($task['TASK_DEF_KEY_'] ?? '');
            if (!in_array($taskDefinitionKey, [
                self::ACTIVITY_APPROVAL,
                self::ACTIVITY_PROCURE_APPROVAL,
                self::ACTIVITY_APPROVAL_PROCURE,
                self::ACTIVITY_PAY_APPROVAL,
                self::ACTIVITY_PAYMENT_APPROVAL,
            ], true)) {
                throw new RuntimeException('task transition is deferred for this activity', 400);
            }

            $definitionId = (string)($task['PROC_DEF_ID_'] ?? '');
            $definitionKey = $this->definitionKey($definitionId);
            if (!$this->canTransitionApprovalProcess($definitionKey)) {
                throw new RuntimeException('task transition is deferred for this process', 400);
            }

            $processInstanceId = (string)($task['PROC_INST_ID_'] ?? '');
            $executionId = (string)($task['EXECUTION_ID_'] ?? $processInstanceId);
            if ($processInstanceId === '') {
                throw new RuntimeException('missing processInstanceId', 400);
            }

            $historicTask = Db::name('act_hi_taskinst')->where('ID_', $taskId)->lock(true)->find();
            $historicProcess = Db::name('act_hi_procinst')->where('PROC_INST_ID_', $processInstanceId)->lock(true)->find();
            $now = date('Y-m-d H:i:s');
            $taskStartTime = is_array($historicTask) ? (string)($historicTask['START_TIME_'] ?? $task['CREATE_TIME_'] ?? '') : (string)($task['CREATE_TIME_'] ?? '');
            $processStartTime = is_array($historicProcess) ? (string)($historicProcess['START_TIME_'] ?? '') : '';
            $duration = $this->durationMillis($taskStartTime, $now);
            $processDuration = $this->durationMillis($processStartTime, $now);
            $deleteReason = $state === self::STATUS_REJECT ? 'deleted' : 'completed';
            $tenantId = (string)($task['TENANT_ID_'] ?? ($historicProcess['TENANT_ID_'] ?? ''));
            $activityInstanceId = is_array($historicTask) ? (string)($historicTask['ACT_INST_ID_'] ?? '') : '';
            if ($activityInstanceId === '') {
                $activityInstanceId = (string)Db::name('act_hi_actinst')
                    ->where('TASK_ID_', $taskId)
                    ->value('ID_');
            }

            if (in_array($taskDefinitionKey, [self::ACTIVITY_PAY_APPROVAL, self::ACTIVITY_PAYMENT_APPROVAL], true)
                && !$this->isFinanceConfirmationProcess($definitionKey)) {
                throw new RuntimeException('task transition is deferred for this activity', 400);
            }
            if (in_array($taskDefinitionKey, [self::ACTIVITY_PROCURE_APPROVAL, self::ACTIVITY_APPROVAL_PROCURE], true)
                && !$this->isProcureProcess($definitionKey)) {
                throw new RuntimeException('task transition is deferred for this activity', 400);
            }

            if ($taskDefinitionKey === self::ACTIVITY_APPROVAL && $this->isFinanceConfirmationProcess($definitionKey) && $approval) {
                return $this->advancePaymentOutToFinanceConfirmation(
                    $task,
                    $historicTask,
                    $historicProcess,
                    $definitionId,
                    $definitionKey,
                    $processInstanceId,
                    $executionId,
                    $tenantId,
                    $activityInstanceId,
                    $now,
                    $duration,
                    $comment,
                    $definitionKey === self::PROCESS_SALE_PROJECT_PLAY
                        ? self::TASK_NAME_FINANCE_CONFIRM
                        : self::TASK_NAME_FINANCE_EXPENSE_CONFIRM,
                    $definitionKey === self::PROCESS_SALE_PROJECT_PLAY
                        ? self::ACTIVITY_PAYMENT_APPROVAL
                        : self::ACTIVITY_PAY_APPROVAL
                );
            }

            if ($taskDefinitionKey === self::ACTIVITY_APPROVAL && $this->isProcureProcess($definitionKey) && $approval) {
                return $this->advanceProcureToProcurementConfirmation(
                    $task,
                    $historicTask,
                    $historicProcess,
                    $definitionId,
                    $definitionKey,
                    $processInstanceId,
                    $executionId,
                    $tenantId,
                    $activityInstanceId,
                    $now,
                    $duration,
                    $comment
                );
            }

            if (in_array($taskDefinitionKey, [self::ACTIVITY_PAY_APPROVAL, self::ACTIVITY_PAYMENT_APPROVAL], true) && $approval) {
                $updates = $this->financeConfirmationVariableUpdates($definitionKey, $form);
                if ($updates !== []) {
                    $this->upsertRuntimeVariables($updates, $definitionId, $processInstanceId, $processInstanceId, $tenantId);
                    $this->upsertHistoryVariables($updates, $definitionId, $processInstanceId, $executionId, $activityInstanceId, $tenantId, $now, $definitionKey);
                }
            }

            if ($taskDefinitionKey === self::ACTIVITY_PROCURE_APPROVAL && $approval) {
                $updates = $this->procureApprovalVariableUpdates($form);
                $this->upsertRuntimeVariables($updates, $definitionId, $processInstanceId, $processInstanceId, $tenantId);
                $this->upsertHistoryVariables($updates, $definitionId, $processInstanceId, $executionId, $activityInstanceId, $tenantId, $now, $definitionKey);

                $variables = $this->historyVariableValues($processInstanceId);
                $generalOfficeApprovers = $this->variableStringList($variables['approvesGeneralOffice'] ?? []);
                if ($generalOfficeApprovers !== []) {
                    return $this->advanceToUserTask(
                        $task,
                        $definitionId,
                        $definitionKey,
                        $processInstanceId,
                        $executionId,
                        $tenantId,
                        $activityInstanceId,
                        $now,
                        $duration,
                        $comment,
                        self::ACTIVITY_APPROVAL_PROCURE,
                        self::TASK_NAME_GENERAL_OFFICE_APPROVAL,
                        $generalOfficeApprovers[0]
                    );
                }
            }

            $sideEffect = $this->approvalSideEffect(
                $definitionKey,
                $processInstanceId,
                $tenantId,
                $now,
                $currentUserId,
                $approval
            );

            $this->upsertHistoryVariables([
                'approval' => $approval,
                'status' => $state,
                'state' => $state,
                'comment' => $comment,
                'nrOfCompletedInstances' => 1,
                'nrOfActiveInstances' => 0,
            ], $definitionId, $processInstanceId, $executionId, $activityInstanceId, $tenantId, $now, $definitionKey);

            Db::name('act_hi_taskinst')->where('ID_', $taskId)->update([
                'END_TIME_' => $now,
                'DURATION_' => $duration,
                'DELETE_REASON_' => $deleteReason,
            ]);
            Db::name('act_hi_actinst')->where('TASK_ID_', $taskId)->update([
                'END_TIME_' => $now,
                'DURATION_' => $duration,
                'ACT_INST_STATE_' => 4,
            ]);
            Db::name('act_hi_procinst')->where('PROC_INST_ID_', $processInstanceId)->update([
                'END_TIME_' => $now,
                'DURATION_' => $processDuration,
                'END_ACT_ID_' => $this->endEventForProcess($definitionKey),
                'STATE_' => 'COMPLETED',
            ]);

            Db::name('act_ru_task')->where('ID_', $taskId)->delete();
            Db::name('act_ru_variable')->where('PROC_INST_ID_', $processInstanceId)->delete();
            Db::name('act_ru_execution')
                ->where('PROC_INST_ID_', $processInstanceId)
                ->where('ID_', '<>', $processInstanceId)
                ->delete();
            Db::name('act_ru_execution')->where('ID_', $processInstanceId)->delete();

            return [
                'id' => $taskId,
                'taskId' => $taskId,
                'processInstanceId' => $processInstanceId,
                'processKey' => $definitionKey,
                'state' => $state,
                'status' => $state,
                'approval' => $approval,
                'comment' => $comment,
            ] + $sideEffect;
        });
    }

    private function canTransitionApprovalProcess(string $definitionKey): bool
    {
        return in_array($definitionKey, [
            self::PROCESS_ASK_LEAVE,
            self::PROCESS_PROCURE,
            self::PROCESS_PROCURE_IN_WAREHOUSE,
            self::PROCESS_REIMBURSEMENT,
            self::PROCESS_MAKE_PAYMENT,
            self::PROCESS_PAYMENT,
            self::PROCESS_SALE_PROJECT_INIT,
            self::PROCESS_SALE_PROJECT_DELIVERY,
            self::PROCESS_SALE_PROJECT_PLAY,
        ], true);
    }

    /**
     * @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    private function advanceProcureToProcurementConfirmation(
        array $task,
        mixed $_historicTask,
        mixed $_historicProcess,
        string $definitionId,
        string $definitionKey,
        string $processInstanceId,
        string $executionId,
        string $tenantId,
        string $activityInstanceId,
        string $now,
        int $duration,
        ?string $comment
    ): array {
        $variables = $this->historyVariableValues($processInstanceId);
        $procure = trim((string)($variables['procure'] ?? ''));
        if ($procure === '') {
            throw new RuntimeException('missing procure', 400);
        }

        return $this->advanceToUserTask(
            $task,
            $definitionId,
            $definitionKey,
            $processInstanceId,
            $executionId,
            $tenantId,
            $activityInstanceId,
            $now,
            $duration,
            $comment,
            self::ACTIVITY_PROCURE_APPROVAL,
            self::TASK_NAME_PROCURE_CONFIRM,
            $procure,
            ['procure' => $procure]
        );
    }

    /**
     * @param array<string, mixed> $task
     * @param array<string, mixed> $extraVariables
     * @return array<string, mixed>
     */
    private function advanceToUserTask(
        array $task,
        string $definitionId,
        string $definitionKey,
        string $processInstanceId,
        string $executionId,
        string $tenantId,
        string $activityInstanceId,
        string $now,
        int $duration,
        ?string $comment,
        string $nextTaskDefinitionKey,
        string $nextTaskName,
        string $assignee,
        array $extraVariables = []
    ): array {
        $assignee = trim($assignee);
        if ($assignee === '') {
            throw new RuntimeException('missing next assignee', 400);
        }

        $taskId = (string)$task['ID_'];
        $nextTaskId = $this->uuid();
        $nextActivityInstanceId = $nextTaskDefinitionKey . ':' . $this->uuid();
        $variableUpdates = array_merge($extraVariables, [
            'approval' => true,
            'status' => self::STATUS_PROGRESS,
            'state' => self::STATUS_AGREE,
            'comment' => $comment,
            'nrOfCompletedInstances' => 1,
            'nrOfActiveInstances' => 1,
            'user' => $assignee,
        ]);

        $this->upsertRuntimeVariables($variableUpdates, $definitionId, $processInstanceId, $processInstanceId, $tenantId);
        $this->upsertHistoryVariables($variableUpdates, $definitionId, $processInstanceId, $executionId, $activityInstanceId, $tenantId, $now, $definitionKey);

        Db::name('act_hi_taskinst')->where('ID_', $taskId)->update([
            'END_TIME_' => $now,
            'DURATION_' => $duration,
            'DELETE_REASON_' => 'completed',
        ]);
        Db::name('act_hi_actinst')->where('TASK_ID_', $taskId)->update([
            'END_TIME_' => $now,
            'DURATION_' => $duration,
            'ACT_INST_STATE_' => 4,
        ]);

        Db::name('act_ru_task')->where('ID_', $taskId)->delete();
        Db::name('act_ru_execution')->where('ID_', $executionId)->update([
            'REV_' => Db::raw('COALESCE(REV_, 0) + 1'),
            'ACT_ID_' => $nextTaskDefinitionKey,
            'ACT_INST_ID_' => $nextActivityInstanceId,
            'SEQUENCE_COUNTER_' => Db::raw('COALESCE(SEQUENCE_COUNTER_, 0) + 1'),
        ]);

        Db::name('act_ru_task')->insert([
            'ID_' => $nextTaskId,
            'REV_' => 1,
            'EXECUTION_ID_' => $executionId,
            'PROC_INST_ID_' => $processInstanceId,
            'PROC_DEF_ID_' => $definitionId,
            'NAME_' => $nextTaskName,
            'TASK_DEF_KEY_' => $nextTaskDefinitionKey,
            'ASSIGNEE_' => $assignee,
            'PRIORITY_' => 50,
            'CREATE_TIME_' => $now,
            'SUSPENSION_STATE_' => 1,
            'TENANT_ID_' => $tenantId,
        ]);

        Db::name('act_hi_taskinst')->insert([
            'ID_' => $nextTaskId,
            'TASK_DEF_KEY_' => $nextTaskDefinitionKey,
            'PROC_DEF_KEY_' => $definitionKey,
            'PROC_DEF_ID_' => $definitionId,
            'ROOT_PROC_INST_ID_' => $processInstanceId,
            'PROC_INST_ID_' => $processInstanceId,
            'EXECUTION_ID_' => $executionId,
            'ACT_INST_ID_' => $nextActivityInstanceId,
            'NAME_' => $nextTaskName,
            'ASSIGNEE_' => $assignee,
            'START_TIME_' => $now,
            'END_TIME_' => null,
            'DURATION_' => null,
            'DELETE_REASON_' => null,
            'PRIORITY_' => 50,
            'TENANT_ID_' => $tenantId,
            'REMOVAL_TIME_' => null,
        ]);

        Db::name('act_hi_actinst')->insert([
            'ID_' => $nextActivityInstanceId,
            'PARENT_ACT_INST_ID_' => $processInstanceId,
            'PROC_DEF_KEY_' => $definitionKey,
            'PROC_DEF_ID_' => $definitionId,
            'ROOT_PROC_INST_ID_' => $processInstanceId,
            'PROC_INST_ID_' => $processInstanceId,
            'EXECUTION_ID_' => $executionId,
            'ACT_ID_' => $nextTaskDefinitionKey,
            'TASK_ID_' => $nextTaskId,
            'ACT_NAME_' => $nextTaskName,
            'ACT_TYPE_' => 'userTask',
            'ASSIGNEE_' => $assignee,
            'START_TIME_' => $now,
            'END_TIME_' => null,
            'DURATION_' => null,
            'ACT_INST_STATE_' => 0,
            'SEQUENCE_COUNTER_' => 7,
            'TENANT_ID_' => $tenantId,
            'REMOVAL_TIME_' => null,
        ]);

        return [
            'id' => $nextTaskId,
            'taskId' => $nextTaskId,
            'completedTaskId' => $taskId,
            'nextTaskId' => $nextTaskId,
            'processInstanceId' => $processInstanceId,
            'processKey' => $definitionKey,
            'taskDefinitionKey' => $nextTaskDefinitionKey,
            'assignee' => $assignee,
            'state' => self::STATUS_PROGRESS,
            'status' => self::STATUS_PROGRESS,
            'approval' => true,
            'comment' => $comment,
        ];
    }

    /**
     * @param array<string, mixed> $task
     * @param array<string, mixed>|null|false $historicTask
     * @param array<string, mixed>|null|false $historicProcess
     * @return array<string, mixed>
     */
    private function advancePaymentOutToFinanceConfirmation(
        array $task,
        mixed $historicTask,
        mixed $historicProcess,
        string $definitionId,
        string $definitionKey,
        string $processInstanceId,
        string $executionId,
        string $tenantId,
        string $activityInstanceId,
        string $now,
        int $duration,
        ?string $comment,
        string $nextTaskName = self::TASK_NAME_FINANCE_EXPENSE_CONFIRM,
        string $nextTaskDefinitionKey = self::ACTIVITY_PAY_APPROVAL
    ): array {
        $taskId = (string)$task['ID_'];
        $variables = $this->historyVariableValues($processInstanceId);
        $treasurer = trim((string)($variables['treasurer'] ?? ''));
        if ($treasurer === '') {
            throw new RuntimeException('missing treasurer', 400);
        }

        $nextTaskId = $this->uuid();
        $nextActivityInstanceId = $nextTaskDefinitionKey . ':' . $this->uuid();

        $this->upsertRuntimeVariables([
            'approval' => true,
            'status' => self::STATUS_PROGRESS,
            'state' => self::STATUS_AGREE,
            'comment' => $comment,
            'nrOfCompletedInstances' => 1,
            'nrOfActiveInstances' => 1,
            'user' => $treasurer,
        ], $definitionId, $processInstanceId, $processInstanceId, $tenantId);
        $this->upsertHistoryVariables([
            'approval' => true,
            'status' => self::STATUS_PROGRESS,
            'state' => self::STATUS_AGREE,
            'comment' => $comment,
            'nrOfCompletedInstances' => 1,
            'nrOfActiveInstances' => 1,
            'user' => $treasurer,
        ], $definitionId, $processInstanceId, $executionId, $activityInstanceId, $tenantId, $now, $definitionKey);

        Db::name('act_hi_taskinst')->where('ID_', $taskId)->update([
            'END_TIME_' => $now,
            'DURATION_' => $duration,
            'DELETE_REASON_' => 'completed',
        ]);
        Db::name('act_hi_actinst')->where('TASK_ID_', $taskId)->update([
            'END_TIME_' => $now,
            'DURATION_' => $duration,
            'ACT_INST_STATE_' => 4,
        ]);

        Db::name('act_ru_task')->where('ID_', $taskId)->delete();
        Db::name('act_ru_execution')->where('ID_', $executionId)->update([
            'REV_' => Db::raw('COALESCE(REV_, 0) + 1'),
            'ACT_ID_' => $nextTaskDefinitionKey,
            'ACT_INST_ID_' => $nextActivityInstanceId,
            'SEQUENCE_COUNTER_' => Db::raw('COALESCE(SEQUENCE_COUNTER_, 0) + 1'),
        ]);

        Db::name('act_ru_task')->insert([
            'ID_' => $nextTaskId,
            'REV_' => 1,
            'EXECUTION_ID_' => $executionId,
            'PROC_INST_ID_' => $processInstanceId,
            'PROC_DEF_ID_' => $definitionId,
            'NAME_' => $nextTaskName,
            'TASK_DEF_KEY_' => $nextTaskDefinitionKey,
            'ASSIGNEE_' => $treasurer,
            'PRIORITY_' => 50,
            'CREATE_TIME_' => $now,
            'SUSPENSION_STATE_' => 1,
            'TENANT_ID_' => $tenantId,
        ]);

        Db::name('act_hi_taskinst')->insert([
            'ID_' => $nextTaskId,
            'TASK_DEF_KEY_' => $nextTaskDefinitionKey,
            'PROC_DEF_KEY_' => $definitionKey,
            'PROC_DEF_ID_' => $definitionId,
            'ROOT_PROC_INST_ID_' => $processInstanceId,
            'PROC_INST_ID_' => $processInstanceId,
            'EXECUTION_ID_' => $executionId,
            'ACT_INST_ID_' => $nextActivityInstanceId,
            'NAME_' => $nextTaskName,
            'ASSIGNEE_' => $treasurer,
            'START_TIME_' => $now,
            'END_TIME_' => null,
            'DURATION_' => null,
            'DELETE_REASON_' => null,
            'PRIORITY_' => 50,
            'TENANT_ID_' => $tenantId,
            'REMOVAL_TIME_' => null,
        ]);

        Db::name('act_hi_actinst')->insert([
            'ID_' => $nextActivityInstanceId,
            'PARENT_ACT_INST_ID_' => $processInstanceId,
            'PROC_DEF_KEY_' => $definitionKey,
            'PROC_DEF_ID_' => $definitionId,
            'ROOT_PROC_INST_ID_' => $processInstanceId,
            'PROC_INST_ID_' => $processInstanceId,
            'EXECUTION_ID_' => $executionId,
            'ACT_ID_' => $nextTaskDefinitionKey,
            'TASK_ID_' => $nextTaskId,
            'ACT_NAME_' => $nextTaskName,
            'ACT_TYPE_' => 'userTask',
            'ASSIGNEE_' => $treasurer,
            'START_TIME_' => $now,
            'END_TIME_' => null,
            'DURATION_' => null,
            'ACT_INST_STATE_' => 0,
            'SEQUENCE_COUNTER_' => 7,
            'TENANT_ID_' => $tenantId,
            'REMOVAL_TIME_' => null,
        ]);

        return [
            'id' => $nextTaskId,
            'taskId' => $nextTaskId,
            'completedTaskId' => $taskId,
            'nextTaskId' => $nextTaskId,
            'processInstanceId' => $processInstanceId,
            'processKey' => $definitionKey,
            'taskDefinitionKey' => $nextTaskDefinitionKey,
            'assignee' => $treasurer,
            'state' => self::STATUS_PROGRESS,
            'status' => self::STATUS_PROGRESS,
            'approval' => true,
            'comment' => $comment,
        ];
    }

    private function isPaymentOutProcess(string $definitionKey): bool
    {
        return in_array($definitionKey, [self::PROCESS_REIMBURSEMENT, self::PROCESS_MAKE_PAYMENT], true);
    }

    private function isProjectPlayProcess(string $definitionKey): bool
    {
        return $definitionKey === self::PROCESS_SALE_PROJECT_PLAY;
    }

    private function isFinanceConfirmationProcess(string $definitionKey): bool
    {
        return $this->isPaymentOutProcess($definitionKey) || $this->isProjectPlayProcess($definitionKey);
    }

    private function isProcureProcess(string $definitionKey): bool
    {
        return $definitionKey === self::PROCESS_PROCURE;
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentOutFinanceVariableUpdates(array $form): array
    {
        $updates = [];
        foreach (['settlementCategory', 'accountId', 'payerTime', 'amount', 'payer', 'bankName', 'bankAccount', 'objectId', 'remark', 'useAdvancePayment'] as $key) {
            if (!array_key_exists($key, $form)) {
                continue;
            }

            $value = in_array($key, ['accountId', 'payer', 'bankName', 'bankAccount', 'objectId', 'remark'], true)
                ? $this->inputString($form[$key])
                : $this->generalProcessVariableValue($key, $form[$key]);
            if ($value === null || $value === '') {
                continue;
            }
            $updates[$key] = $value;
        }

        return $updates;
    }

    /**
     * @return array<string, mixed>
     */
    private function financeConfirmationVariableUpdates(string $definitionKey, array $form): array
    {
        $updates = $this->paymentOutFinanceVariableUpdates($form);
        if ($definitionKey === self::PROCESS_SALE_PROJECT_PLAY) {
            $updates['settlementCategory'] = self::PROJECT_PLAY_SETTLEMENT_CATEGORY;
        }

        return $updates;
    }

    /**
     * @return array<string, mixed>
     */
    private function procureApprovalVariableUpdates(array $form): array
    {
        if (!array_key_exists('productList', $form)) {
            throw new RuntimeException('missing productList', 400);
        }
        if (!array_key_exists('amount', $form)) {
            throw new RuntimeException('missing amount', 400);
        }

        $amount = $this->inputString($form['amount']);
        if ($amount === '' || !is_numeric($amount) || (float)$amount < 0) {
            throw new RuntimeException('invalid amount', 400);
        }

        return [
            'productList' => $this->decodedArrayList($form['productList'], 'productList'),
            'amount' => number_format((float)$amount, 2, '.', ''),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodedArrayList(mixed $value, string $label): array
    {
        if (is_string($value)) {
            $text = trim($value);
            if ($text === '') {
                throw new RuntimeException('missing ' . $label, 400);
            }
            $decoded = json_decode($text, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('invalid ' . $label, 400);
            }
            $value = $decoded;
        }

        if (!is_array($value) || $value === []) {
            throw new RuntimeException('missing ' . $label, 400);
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('invalid ' . $label . ' item', 400);
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return array<int, string>
     */
    private function variableStringList(mixed $value): array
    {
        if (is_string($value)) {
            $text = trim($value);
            if ($text === '') {
                return [];
            }
            if (str_starts_with($text, '[') || str_starts_with($text, '{')) {
                $decoded = json_decode($text, true);
                if (is_array($decoded)) {
                    return $this->stringList($decoded);
                }
            }
        }

        return $this->stringList($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function approvalSideEffect(
        string $definitionKey,
        string $processInstanceId,
        string $tenantId,
        string $now,
        string $currentUserId,
        bool $approval
    ): array {
        if (!$approval) {
            if ($definitionKey === self::PROCESS_SALE_PROJECT_INIT) {
                $this->saleProjectService->rejectProjectInitFromWorkflow(
                    $this->historyVariableValues($processInstanceId),
                    $tenantId,
                    $currentUserId
                );
            }

            return [];
        }

        if ($definitionKey === self::PROCESS_ASK_LEAVE) {
            $leaveApplication = $this->upsertLeaveApplication($processInstanceId, $tenantId, $now, $currentUserId);

            return [
                'leaveApplicationId' => $leaveApplication['id'] ?? null,
                'vacationDeduction' => $leaveApplication['vacationDeduction'] ?? null,
            ];
        }

        if ($definitionKey === self::PROCESS_PROCURE) {
            return [
                'purchaseOrder' => $this->approveProcure($processInstanceId, $tenantId),
            ];
        }

        if ($definitionKey === self::PROCESS_PROCURE_IN_WAREHOUSE) {
            return [
                'purchaseWarehouse' => $this->approveProcureInWarehouse($processInstanceId, $tenantId),
            ];
        }

        if ($definitionKey === self::PROCESS_SALE_PROJECT_INIT) {
            return [
                'saleProject' => $this->approveProjectInit($processInstanceId, $tenantId, $currentUserId),
            ];
        }

        if ($definitionKey === self::PROCESS_SALE_PROJECT_DELIVERY) {
            return [
                'saleProjectDelivery' => $this->approveProjectDelivery($processInstanceId, $tenantId, $currentUserId),
            ];
        }

        if ($definitionKey === self::PROCESS_SALE_PROJECT_PLAY) {
            return [
                'payment' => $this->approveProjectPlay($processInstanceId, $tenantId),
            ];
        }

        if ($definitionKey === self::PROCESS_PAYMENT) {
            return [
                'payment' => $this->approvePayment($processInstanceId, $tenantId),
            ];
        }

        if ($this->isPaymentOutProcess($definitionKey)) {
            return [
                'expenditure' => $this->approvePaymentOut($processInstanceId, $tenantId, $definitionKey),
            ];
        }

        throw new RuntimeException('task transition is deferred for this process', 400);
    }

    /**
     * @return array<string, mixed>
     */
    private function approveProjectInit(string $processInstanceId, string $tenantId, string $currentUserId): array
    {
        $variables = $this->historyVariableValues($processInstanceId);
        $effectiveTenantId = $tenantId !== '' ? $tenantId : trim((string)($variables['tenantId'] ?? ''));

        return $this->saleProjectService->applyProjectInitFromWorkflow(
            $variables,
            $processInstanceId,
            $effectiveTenantId,
            $currentUserId
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function approveProjectDelivery(string $processInstanceId, string $tenantId, string $currentUserId): array
    {
        $variables = $this->historyVariableValues($processInstanceId);
        $effectiveTenantId = $tenantId !== '' ? $tenantId : trim((string)($variables['tenantId'] ?? ''));

        return $this->saleProjectService->applyProjectDeliveryFromWorkflow(
            $variables,
            $processInstanceId,
            $effectiveTenantId,
            $currentUserId
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function approvePayment(string $processInstanceId, string $tenantId): array
    {
        $variables = $this->historyVariableValues($processInstanceId);
        $initiator = trim((string)($variables['initiator'] ?? ''));
        if ($initiator === '') {
            throw new RuntimeException('missing initiator', 400);
        }

        $effectiveTenantId = $tenantId !== '' ? $tenantId : trim((string)($variables['tenantId'] ?? ''));

        return $this->settlementAccountService->paymentFromWorkflow(
            $variables,
            $processInstanceId,
            $effectiveTenantId,
            $initiator
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function approveProjectPlay(string $processInstanceId, string $tenantId): array
    {
        $variables = $this->historyVariableValues($processInstanceId);
        $initiator = trim((string)($variables['initiator'] ?? ''));
        if ($initiator === '') {
            throw new RuntimeException('missing initiator', 400);
        }
        $projectId = $this->requiredProjectIdInput($variables);
        $effectiveTenantId = $tenantId !== '' ? $tenantId : trim((string)($variables['tenantId'] ?? ''));
        $paymentInput = array_merge($variables, [
            'targetId' => $variables['accountId'] ?? '',
            'objectId' => $projectId,
            'settlementCategory' => self::PROJECT_PLAY_SETTLEMENT_CATEGORY,
        ]);
        if (trim((string)($paymentInput['payer'] ?? '')) === '') {
            $paymentInput['payer'] = $paymentInput['treasurer'] ?? $initiator;
        }

        $payment = $this->settlementAccountService->paymentFromWorkflow(
            $paymentInput,
            $processInstanceId,
            $effectiveTenantId,
            $initiator,
            self::PROCESS_SALE_PROJECT_PLAY
        );
        $project = $this->saleProjectService->refreshProjectPaymentStatusFromWorkflow(
            $projectId,
            $effectiveTenantId,
            $initiator
        );

        return $payment + ['saleProject' => $project];
    }

    /**
     * @return array<string, mixed>
     */
    private function approvePaymentOut(string $processInstanceId, string $tenantId, string $definitionKey): array
    {
        $variables = $this->historyVariableValues($processInstanceId);
        $initiator = trim((string)($variables['initiator'] ?? ''));
        if ($initiator === '') {
            throw new RuntimeException('missing initiator', 400);
        }

        $effectiveTenantId = $tenantId !== '' ? $tenantId : trim((string)($variables['tenantId'] ?? ''));

        return $this->settlementAccountService->expensesFromWorkflow(
            $variables,
            $processInstanceId,
            $effectiveTenantId,
            $initiator,
            $definitionKey
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function approveProcure(string $processInstanceId, string $tenantId): array
    {
        $variables = $this->historyVariableValues($processInstanceId);
        $initiator = trim((string)($variables['initiator'] ?? ''));
        if ($initiator === '') {
            throw new RuntimeException('missing initiator', 400);
        }

        $effectiveTenantId = $tenantId !== '' ? $tenantId : trim((string)($variables['tenantId'] ?? ''));

        return $this->purchaseOrderService->purchaseOrderFromWorkflow(
            $variables,
            $processInstanceId,
            $effectiveTenantId,
            $initiator
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function approveProcureInWarehouse(string $processInstanceId, string $tenantId): array
    {
        $variables = $this->historyVariableValues($processInstanceId);
        $initiator = trim((string)($variables['initiator'] ?? ''));
        if ($initiator === '') {
            throw new RuntimeException('missing initiator', 400);
        }

        $effectiveTenantId = $tenantId !== '' ? $tenantId : trim((string)($variables['tenantId'] ?? ''));

        return $this->purchaseOrderService->warehouseOneFromWorkflow(
            $variables,
            $processInstanceId,
            $effectiveTenantId,
            $initiator
        );
    }

    /**
     * @return array{id: string, count: int, vacationDeduction?: array<string, mixed>}
     */
    private function upsertLeaveApplication(
        string $processInstanceId,
        string $tenantId,
        string $now,
        string $approverId
    ): array {
        $variables = $this->historyVariableValues($processInstanceId);
        $historicProcess = Db::name('act_hi_procinst')
            ->where('PROC_INST_ID_', $processInstanceId)
            ->field('START_USER_ID_,TENANT_ID_')
            ->find();

        $initiator = trim((string)($variables['initiator'] ?? ($historicProcess['START_USER_ID_'] ?? '')));
        if ($initiator === '') {
            throw new RuntimeException('missing leave initiator', 400);
        }

        $category = trim((string)($variables['category'] ?? ''));
        if ($category === '') {
            throw new RuntimeException('missing leave category', 400);
        }

        $amount = $this->requiredDecimal($variables['amount'] ?? null, 'amount');
        $startTime = $this->requiredDateVariable($variables['startTime'] ?? null, 'startTime');
        $endTime = $this->requiredDateVariable($variables['endTime'] ?? null, 'endTime');
        if (strtotime($endTime) < strtotime($startTime)) {
            throw new RuntimeException('invalid leave time range', 400);
        }

        $effectiveTenantId = trim((string)($tenantId !== '' ? $tenantId : ($variables['tenantId'] ?? ($historicProcess['TENANT_ID_'] ?? ''))));
        if ($effectiveTenantId === '') {
            throw new RuntimeException('missing leave tenantId', 400);
        }

        $objectId = trim((string)($variables['objectId'] ?? ''));
        if (strlen($objectId) > 20) {
            throw new RuntimeException('objectId is too long', 400);
        }

        $this->assertNoOverlappingLeave($initiator, $startTime, $endTime, $effectiveTenantId, $processInstanceId);

        $row = [
            'USER_ID' => $initiator,
            'PROCESS_ID' => $processInstanceId,
            'category' => $category,
            'AMOUNT' => $amount,
            'REMARK' => $variables['remark'] ?? null,
            'START_TIME' => $startTime,
            'END_TIME' => $endTime,
            'DELETE_FLAG' => self::NOT_DELETE,
            'TENANT_ID' => $effectiveTenantId,
            'OBJECT_ID' => $objectId,
        ];

        $existing = Db::name('biz_leave_application')
            ->where('PROCESS_ID', $processInstanceId)
            ->lock(true)
            ->find();
        if (is_array($existing) && $existing !== []) {
            $id = (string)$existing['ID'];
            $updated = Db::name('biz_leave_application')
                ->where('ID', $id)
                ->update(array_merge($row, [
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $approverId !== '' ? $approverId : null,
                ]));

            return ['id' => $id, 'count' => $updated];
        }

        $id = $this->newBusinessId('biz_leave_application');
        Db::name('biz_leave_application')->insert(array_merge($row, [
            'ID' => $id,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $initiator,
            'UPDATE_TIME' => null,
            'UPDATE_USER' => null,
        ]));

        $result = ['id' => $id, 'count' => 1];
        if ($category === self::LEAVE_CATEGORY_ANNUAL) {
            $result['vacationDeduction'] = $this->reduceAnnualLeaveBalance(
                $initiator,
                $effectiveTenantId,
                $amount,
                $now,
                $approverId
            );
        }

        return $result;
    }

    /**
     * @return array{id: string, amount: string, usedAmount: string, deductedAmount: string}
     */
    private function reduceAnnualLeaveBalance(
        string $userId,
        string $tenantId,
        string $deductAmount,
        string $now,
        string $updateUser
    ): array {
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
            ->field('ID,AMOUNT,USED_AMOUNT,TENANT_ID,VERSION')
            ->order('CREATE_TIME', 'desc')
            ->order('ID', 'desc')
            ->lock(true)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('user annual leave balance not found', 400);
        }

        $amount = number_format((float)($row['AMOUNT'] ?? 0), 2, '.', '');
        $usedAmount = number_format((float)($row['USED_AMOUNT'] ?? 0), 2, '.', '');
        $deduct = number_format((float)$deductAmount, 2, '.', '');
        $remaining = (float)$amount - (float)$usedAmount;
        if ($remaining + 0.00001 < (float)$deduct) {
            throw new RuntimeException('insufficient annual leave balance', 400);
        }

        $newUsedAmount = number_format((float)$usedAmount + (float)$deduct, 2, '.', '');
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
            'amount' => $amount,
            'usedAmount' => $newUsedAmount,
            'deductedAmount' => $deduct,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function historyVariableValues(string $processInstanceId): array
    {
        $rows = Db::name('act_hi_varinst')
            ->where('PROC_INST_ID_', $processInstanceId)
            ->field('NAME_,VAR_TYPE_,LONG_,DOUBLE_,TEXT_,TEXT2_')
            ->order('CREATE_TIME_', 'asc')
            ->order('ID_', 'asc')
            ->select()
            ->toArray();

        $values = [];
        foreach ($rows as $row) {
            $name = (string)($row['NAME_'] ?? '');
            if ($name === '') {
                continue;
            }

            $values[$name] = $this->historyVariableValue($row);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function historyVariableValue(array $row): mixed
    {
        $type = (string)($row['VAR_TYPE_'] ?? '');
        if ($type === 'date') {
            $millis = $row['LONG_'] ?? null;
            if ($millis === null || $millis === '') {
                return null;
            }

            return date('Y-m-d H:i:s', intdiv((int)$millis, 1000));
        }
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

    private function requiredDecimal(mixed $value, string $label): string
    {
        if ($value === null || $value === '') {
            throw new RuntimeException('missing leave ' . $label, 400);
        }
        if (!is_numeric($value)) {
            throw new RuntimeException('invalid leave ' . $label, 400);
        }

        $number = (float)$value;
        if ($number < 0) {
            throw new RuntimeException('invalid leave ' . $label, 400);
        }

        return number_format($number, 2, '.', '');
    }

    private function requiredDateVariable(mixed $value, string $label): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            throw new RuntimeException('missing leave ' . $label, 400);
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException('invalid leave ' . $label, 400);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function assertNoOverlappingLeave(
        string $userId,
        string $startTime,
        string $endTime,
        string $tenantId,
        string $processInstanceId
    ): void {
        $query = Db::name('biz_leave_application')
            ->where('USER_ID', $userId)
            ->where('TENANT_ID', $tenantId)
            ->where('PROCESS_ID', '<>', $processInstanceId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query) use ($startTime, $endTime): void {
                $query->whereBetweenTime('START_TIME', $startTime, $endTime)
                    ->whereOr(function ($query) use ($startTime, $endTime): void {
                        $query->whereBetweenTime('END_TIME', $startTime, $endTime);
                    })
                    ->whereOr(function ($query) use ($startTime, $endTime): void {
                        $query->where('START_TIME', '<=', $startTime)
                            ->where('END_TIME', '>=', $endTime);
                    });
            });

        if ($query->count() > 0) {
            throw new RuntimeException('user already has leave in this time range', 400);
        }
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function insertVariables(
        array $variables,
        string $definitionId,
        string $processInstanceId,
        string $executionId,
        string $tenantId,
        string $now,
        string $processKey = self::PROCESS_ASK_LEAVE
    ): void {
        $runtimeRows = [];
        $historyRows = [];
        foreach ($variables as $name => $value) {
            $columns = $this->variableColumns($value, $name);
            $runtimeRows[] = array_merge([
                'ID_' => $this->uuid(),
                'REV_' => 1,
                'NAME_' => $name,
                'EXECUTION_ID_' => $executionId,
                'PROC_INST_ID_' => $processInstanceId,
                'PROC_DEF_ID_' => $definitionId,
                'TASK_ID_' => null,
                'VAR_SCOPE_' => $executionId,
                'SEQUENCE_COUNTER_' => 1,
                'IS_CONCURRENT_LOCAL_' => 0,
                'TENANT_ID_' => $tenantId,
            ], $columns['runtime']);

            $historyRows[] = array_merge([
                'ID_' => $this->uuid(),
                'PROC_DEF_KEY_' => $processKey,
                'PROC_DEF_ID_' => $definitionId,
                'ROOT_PROC_INST_ID_' => $processInstanceId,
                'PROC_INST_ID_' => $processInstanceId,
                'EXECUTION_ID_' => $executionId,
                'ACT_INST_ID_' => $processInstanceId,
                'TASK_ID_' => null,
                'NAME_' => $name,
                'CREATE_TIME_' => $now,
                'REV_' => 0,
                'TENANT_ID_' => $tenantId,
                'STATE_' => 'CREATED',
                'REMOVAL_TIME_' => null,
            ], $columns['history']);
        }

        Db::name('act_ru_variable')->insertAll($runtimeRows);
        Db::name('act_hi_varinst')->insertAll($historyRows);
    }

    /**
     * @param array<int, string> $copyUserIds
     */
    private function insertCopyUserRecords(
        array $copyUserIds,
        string $definitionId,
        string $processInstanceId,
        string $title,
        string $promoterId,
        string $tenantId,
        string $now,
        string $processKey = self::PROCESS_ASK_LEAVE
    ): int {
        if ($copyUserIds === []) {
            return 0;
        }

        $rows = [];
        $allocatedIds = [];
        foreach ($copyUserIds as $copyUserId) {
            do {
                $id = $this->newBusinessId('biz_cc_records');
            } while (isset($allocatedIds[$id]));
            $allocatedIds[$id] = true;

            $rows[] = [
                'ID' => $id,
                'TITLE' => $title,
                'PROCESS_ID' => $definitionId,
                'PROMOTER_ID' => $promoterId,
                'INSTANCE_ID' => $processInstanceId,
                'CATEGORY' => $processKey,
                'EXT_JSON' => null,
                'USER' => $copyUserId,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $copyUserId,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
            ];
        }

        Db::name('biz_cc_records')->insertAll($rows);

        return count($rows);
    }

    /**
     * @param array<int, string> $fileIds
     */
    private function insertWorkflowFileRelations(
        array $fileIds,
        string $processInstanceId,
        string $currentUserId,
        string $tenantId,
        string $now,
        string $processKey = self::PROCESS_ASK_LEAVE
    ): int {
        if ($fileIds === []) {
            return 0;
        }

        $files = Db::name('dev_file')
            ->whereIn('ID', $fileIds)
            ->where('TENANT_ID', $tenantId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->column('NAME', 'ID');

        foreach ($fileIds as $fileId) {
            if (!array_key_exists($fileId, $files)) {
                throw new RuntimeException('file not found', 404);
            }
        }

        $rows = [];
        $allocatedIds = [];
        foreach ($fileIds as $fileId) {
            do {
                $id = $this->newBusinessId('biz_file_relation');
            } while (isset($allocatedIds[$id]));
            $allocatedIds[$id] = true;

            $rows[] = [
                'ID' => $id,
                'OBJECT_ID' => $processInstanceId,
                'TARGET_ID' => $fileId,
                'CATEGORY' => $processKey,
                'FILE_NAME' => $files[$fileId] ?? null,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $currentUserId,
                'EXT_JSON' => null,
                'TENANT_ID' => $tenantId,
            ];
        }

        Db::name('biz_file_relation')->insertAll($rows);

        return count($rows);
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function upsertRuntimeVariables(
        array $variables,
        string $definitionId,
        string $processInstanceId,
        string $executionId,
        string $tenantId
    ): void {
        foreach ($variables as $name => $value) {
            $columns = $this->variableColumns($value, $name)['runtime'];
            $existing = Db::name('act_ru_variable')
                ->where('PROC_INST_ID_', $processInstanceId)
                ->where('NAME_', $name)
                ->order('ID_', 'asc')
                ->find();
            if (is_array($existing) && $existing !== []) {
                Db::name('act_ru_variable')->where('ID_', (string)$existing['ID_'])->update(array_merge($columns, [
                    'REV_' => ((int)($existing['REV_'] ?? 0)) + 1,
                    'EXECUTION_ID_' => (string)($existing['EXECUTION_ID_'] ?? $executionId),
                    'PROC_DEF_ID_' => $definitionId,
                    'VAR_SCOPE_' => (string)($existing['VAR_SCOPE_'] ?? $executionId),
                    'SEQUENCE_COUNTER_' => ((int)($existing['SEQUENCE_COUNTER_'] ?? 0)) + 1,
                    'TENANT_ID_' => $tenantId,
                ]));
                continue;
            }

            Db::name('act_ru_variable')->insert(array_merge([
                'ID_' => $this->uuid(),
                'REV_' => 1,
                'NAME_' => $name,
                'EXECUTION_ID_' => $executionId,
                'PROC_INST_ID_' => $processInstanceId,
                'PROC_DEF_ID_' => $definitionId,
                'TASK_ID_' => null,
                'VAR_SCOPE_' => $executionId,
                'SEQUENCE_COUNTER_' => 1,
                'IS_CONCURRENT_LOCAL_' => 0,
                'TENANT_ID_' => $tenantId,
            ], $columns));
        }
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function upsertHistoryVariables(
        array $variables,
        string $definitionId,
        string $processInstanceId,
        string $executionId,
        string $activityInstanceId,
        string $tenantId,
        string $now,
        string $processKey = self::PROCESS_ASK_LEAVE
    ): void {
        foreach ($variables as $name => $value) {
            $columns = $this->variableColumns($value, $name)['history'];
            $existing = Db::name('act_hi_varinst')
                ->where('PROC_INST_ID_', $processInstanceId)
                ->where('NAME_', $name)
                ->order('CREATE_TIME_', 'asc')
                ->order('ID_', 'asc')
                ->find();
            if (is_array($existing) && $existing !== []) {
                Db::name('act_hi_varinst')->where('ID_', (string)$existing['ID_'])->update(array_merge($columns, [
                    'REV_' => ((int)($existing['REV_'] ?? 0)) + 1,
                ]));
                continue;
            }

            Db::name('act_hi_varinst')->insert(array_merge([
                'ID_' => $this->uuid(),
                'PROC_DEF_KEY_' => $processKey,
                'PROC_DEF_ID_' => $definitionId,
                'ROOT_PROC_INST_ID_' => $processInstanceId,
                'PROC_INST_ID_' => $processInstanceId,
                'EXECUTION_ID_' => $executionId,
                'ACT_INST_ID_' => $activityInstanceId !== '' ? $activityInstanceId : $processInstanceId,
                'TASK_ID_' => null,
                'NAME_' => $name,
                'CREATE_TIME_' => $now,
                'REV_' => 0,
                'TENANT_ID_' => $tenantId,
                'STATE_' => 'CREATED',
                'REMOVAL_TIME_' => null,
            ], $columns));
        }
    }

    /**
     * @return array{runtime: array<string, mixed>, history: array<string, mixed>}
     */
    private function variableColumns(mixed $value, string $name): array
    {
        $type = 'string';
        $bytearrayId = null;
        $double = null;
        $long = null;
        $text = null;
        $text2 = null;

        if (is_array($value) && ($value['__workflow_type'] ?? null) === 'date') {
            $type = 'date';
            $long = (int)$value['millis'];
        } elseif (is_bool($value)) {
            $type = 'boolean';
            $long = $value ? 1 : 0;
        } elseif (is_int($value)) {
            $type = 'integer';
            $long = $value;
            $text = (string)$value;
        } elseif (is_float($value)) {
            $type = 'double';
            $double = $value;
            $text = (string)$value;
        } elseif (is_array($value)) {
            $type = 'string';
            $text = json_encode($this->jsonReadyArray($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif ($value === null) {
            $type = 'null';
        } else {
            $text = (string)$value;
            if ($text === '') {
                $text2 = '!emptyString!';
            }
        }

        if ($text !== null && strlen($text) > 4000) {
            throw new RuntimeException($name . ' is too long', 400);
        }

        return [
            'runtime' => [
                'TYPE_' => $type,
                'BYTEARRAY_ID_' => $bytearrayId,
                'DOUBLE_' => $double,
                'LONG_' => $long,
                'TEXT_' => $text,
                'TEXT2_' => $text2,
            ],
            'history' => [
                'VAR_TYPE_' => $type,
                'BYTEARRAY_ID_' => $bytearrayId,
                'DOUBLE_' => $double,
                'LONG_' => $long,
                'TEXT_' => $text,
                'TEXT2_' => $text2,
            ],
        ];
    }

    /**
     * @param array<mixed> $value
     */
    private function jsonReadyArray(array $value): array
    {
        if ($this->isListArray($value)) {
            return array_values(array_map(fn (mixed $item): mixed => is_array($item) ? $this->jsonReadyArray($item) : $item, $value));
        }

        $ready = [];
        foreach ($value as $key => $item) {
            $ready[$key] = is_array($item) ? $this->jsonReadyArray($item) : $item;
        }

        return $ready;
    }

    /**
     * @param array<mixed> $value
     */
    private function isListArray(array $value): bool
    {
        $expected = 0;
        foreach ($value as $key => $_) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }

        return true;
    }

    private function processDefinition(string $key): array
    {
        $row = Db::name('act_re_procdef')
            ->where('KEY_', $key)
            ->where('STARTABLE_', 1)
            ->where('SUSPENSION_STATE_', 1)
            ->order('VERSION_', 'desc')
            ->order('ID_', 'desc')
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('process definition not found', 404);
        }

        return $row;
    }

    private function definitionKey(string $definitionId): string
    {
        if ($definitionId === '') {
            return '';
        }

        $key = Db::name('act_re_procdef')->where('ID_', $definitionId)->value('KEY_');
        if (is_string($key) && $key !== '') {
            return $key;
        }

        return str_contains($definitionId, ':') ? explode(':', $definitionId)[0] : $definitionId;
    }

    /**
     * @return array<string, mixed>
     */
    private function userRow(string $userId): array
    {
        $row = Db::name('sys_user')->where('ID', $userId)->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('user not found', 404);
        }

        return $row;
    }

    /**
     * @param array<int, string> $userIds
     */
    private function assertUsers(array $userIds, string $tenantId, string $message): void
    {
        if ($userIds === []) {
            return;
        }

        $rows = Db::name('sys_user')->whereIn('ID', $userIds)->select()->toArray();
        $found = array_fill_keys(array_map(static fn (array $row): string => (string)$row['ID'], $rows), true);
        foreach ($userIds as $userId) {
            if (!isset($found[$userId])) {
                throw new RuntimeException($message, 404);
            }
        }
        if ($tenantId === '') {
            return;
        }
        foreach ($rows as $row) {
            $rowTenantId = trim((string)($row['TENANT_ID'] ?? ''));
            if ($rowTenantId !== '' && $rowTenantId !== $tenantId) {
                throw new RuntimeException('user tenant mismatch', 403);
            }
        }
    }

    private function requiredCurrentUserId(array $payload): string
    {
        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
        if ($userId === '') {
            throw new RuntimeException('missing current user', 400);
        }

        return $userId;
    }

    private function tenantId(array $input, array $payload, array $user): string
    {
        $tenantId = trim((string)($input['tenantId'] ?? $input['tenant_id'] ?? $payload['tenant_id'] ?? $payload['tenantId'] ?? $user['TENANT_ID'] ?? ''));
        if ($tenantId === '') {
            throw new RuntimeException('missing tenantId', 400);
        }

        return $tenantId;
    }

    private function orgId(array $payload, array $user): string
    {
        return trim((string)($payload['org_id'] ?? $payload['orgId'] ?? $user['ORG_ID'] ?? ''));
    }

    private function requiredString(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException('missing ' . $key, 400);
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    private function requiredStringList(mixed $value): array
    {
        $list = $this->stringList($value);
        if ($list === []) {
            throw new RuntimeException('missing approveUserIdList', 400);
        }

        return $list;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = str_contains($value, ',') ? explode(',', $value) : [$value];
        }
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $item = $item['id'] ?? $item['userId'] ?? $item['value'] ?? $item['key'] ?? '';
            }
            $item = trim((string)$item);
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    private function optionalString(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        if (strlen($value) > $maxLength) {
            throw new RuntimeException('input is too long', 400);
        }

        return $value;
    }

    private function booleanValue(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (int)$value !== 0;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return null;
    }

    private function durationMillis(string $startTime, string $endTime): int
    {
        $start = strtotime($startTime);
        $end = strtotime($endTime);
        if ($start === false || $end === false || $end < $start) {
            return 0;
        }

        return ($end - $start) * 1000;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function requiredTime(array $input, string $key): array
    {
        [$time, $millis] = $this->timeInput($input, $key);
        if ($time === null || $millis === null) {
            throw new RuntimeException('missing ' . $key, 400);
        }

        return [$time, $millis];
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    private function optionalTime(array $input, string $key): array
    {
        return $this->timeInput($input, $key);
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    private function timeInput(array $input, string $key): array
    {
        $value = $input[$key] ?? null;
        if ($value === null && isset($input['createTime']) && is_array($input['createTime'])) {
            $value = $key === 'startTime' ? ($input['createTime'][0] ?? null) : ($input['createTime'][1] ?? null);
        }
        if ($value === null || $value === '') {
            return [null, null];
        }

        if (is_numeric($value)) {
            $raw = (int)$value;
            $seconds = $raw > 9999999999 ? intdiv($raw, 1000) : $raw;
            return [date('Y-m-d H:i:s', $seconds), $seconds * 1000];
        }

        $timestamp = strtotime((string)$value);
        if ($timestamp === false) {
            throw new RuntimeException('invalid ' . $key, 400);
        }

        return [date('Y-m-d H:i:s', $timestamp), $timestamp * 1000];
    }

    /**
     * @return array{__workflow_type: string, millis: int}
     */
    private function dateVariable(int $millis): array
    {
        return [
            '__workflow_type' => 'date',
            'millis' => $millis,
        ];
    }

    private function newBusinessId(string $table): string
    {
        for ($i = 0; $i < 5; $i++) {
            $id = (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
            if (Db::name($table)->where('ID', $id)->count() === 0) {
                return $id;
            }
        }

        throw new RuntimeException('failed to allocate business id', 500);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-'
            . substr($hex, 8, 4) . '-'
            . substr($hex, 12, 4) . '-'
            . substr($hex, 16, 4) . '-'
            . substr($hex, 20);
    }
}
