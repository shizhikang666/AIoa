<?php

declare(strict_types=1);

namespace app\service\sys;

use app\model\SysRelation;
use app\service\user\UserDirectoryService;
use RuntimeException;
use think\facade\Db;

/**
 * Read-only homepage queries compatible with Java SysIndexService.
 */
class IndexService
{
    private const SCHEDULE_CATEGORY = 'SYS_USER_SCHEDULE_DATA';
    private const VIS_LOG_CATEGORIES = ['LOGIN', 'LOGOUT'];
    private const OP_LOG_CATEGORIES = ['OPERATE', 'EXCEPTION'];

    public function __construct(private readonly UserDirectoryService $userDirectoryService = new UserDirectoryService())
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function scheduleList(string $userId, string $scheduleDate): array
    {
        $rows = SysRelation::where('OBJECT_ID', $userId)
            ->where('TARGET_ID', $scheduleDate)
            ->where('CATEGORY', self::SCHEDULE_CATEGORY)
            ->select()
            ->toArray();

        return array_map(static function (array $row): array {
            $data = json_decode((string)($row['EXT_JSON'] ?? '{}'), true);
            if (!is_array($data)) {
                $data = [];
            }

            $data['id'] = $row['ID'] ?? null;

            return $data;
        }, $rows);
    }

    public function addSchedule(string $userId, array $input): void
    {
        $scheduleDate = $this->requiredInput($input, 'scheduleDate');
        $scheduleTime = $this->requiredInput($input, 'scheduleTime');
        $scheduleContent = $this->requiredInput($input, 'scheduleContent');
        $userName = $this->currentUserName($userId);

        $extJson = json_encode([
            'scheduleDate' => $scheduleDate,
            'scheduleTime' => $scheduleTime,
            'scheduleContent' => $scheduleContent,
            'scheduleUserId' => $userId,
            'scheduleUserName' => $userName,
        ], JSON_UNESCAPED_UNICODE);

        Db::name('sys_relation')->insert([
            'ID' => $this->newId(),
            'OBJECT_ID' => $userId,
            'TARGET_ID' => $scheduleDate,
            'CATEGORY' => self::SCHEDULE_CATEGORY,
            'EXT_JSON' => $extJson === false ? '{}' : $extJson,
        ]);
    }

    /**
     * @param array<int, string> $ids
     */
    public function deleteSchedule(string $userId, array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            throw new RuntimeException('missing id', 400);
        }

        Db::name('sys_relation')
            ->whereIn('ID', $ids)
            ->where('OBJECT_ID', $userId)
            ->where('CATEGORY', self::SCHEDULE_CATEGORY)
            ->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function messageList(string $userId, array $filters = []): array
    {
        $limit = (int)($filters['limit'] ?? 10);

        return $this->userDirectoryService->loginUnreadMessageList($userId, $limit);
    }

    public function messagePage(string $userId, array $filters = []): array
    {
        return $this->userDirectoryService->loginUnreadMessagePage($userId, $filters);
    }

    public function messageDetail(string $userId, string $id): ?array
    {
        return $this->userDirectoryService->loginUnreadMessageDetail($userId, $id);
    }

    public function allMessageMarkRead(string $userId): void
    {
        $this->userDirectoryService->markAllMessagesRead($userId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function visLogList(string $userId): array
    {
        return $this->logList($this->currentUserName($userId), self::VIS_LOG_CATEGORIES);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function opLogList(string $userId): array
    {
        return $this->logList($this->currentUserName($userId), self::OP_LOG_CATEGORIES);
    }

    private function currentUserName(string $userId): string
    {
        $user = $this->userDirectoryService->detail($userId);
        if (!$user || empty($user['NAME'])) {
            throw new RuntimeException('login user not found', 401);
        }

        return (string)$user['NAME'];
    }

    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }

    /**
     * @param array<int, string> $categories
     * @return array<int, array<string, mixed>>
     */
    private function logList(string $userName, array $categories): array
    {
        $rows = Db::name('dev_log')
            ->where('OP_USER', $userName)
            ->whereIn('CATEGORY', $categories)
            ->order('CREATE_TIME', 'desc')
            ->limit(10)
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->logRow($row), $rows);
    }

    private function logRow(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'name' => $row['NAME'] ?? null,
            'exeStatus' => $row['EXE_STATUS'] ?? null,
            'exeMessage' => $row['EXE_MESSAGE'] ?? null,
            'opIp' => $row['OP_IP'] ?? null,
            'opAddress' => $row['OP_ADDRESS'] ?? null,
            'opBrowser' => $row['OP_BROWSER'] ?? null,
            'opOs' => $row['OP_OS'] ?? null,
            'className' => $row['CLASS_NAME'] ?? null,
            'methodName' => $row['METHOD_NAME'] ?? null,
            'reqMethod' => $row['REQ_METHOD'] ?? null,
            'reqUrl' => $row['REQ_URL'] ?? null,
            'paramJson' => $row['PARAM_JSON'] ?? null,
            'resultJson' => $row['RESULT_JSON'] ?? null,
            'opTime' => $row['OP_TIME'] ?? null,
            'opUser' => $row['OP_USER'] ?? null,
            'signData' => $row['SIGN_DATA'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }
}
