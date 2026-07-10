<?php

declare(strict_types=1);

namespace app\service\user;

use app\service\auth\PasswordService;
use app\service\auth\Sm3Hasher;
use RuntimeException;
use think\facade\Db;
use think\file\UploadedFile;

class UserCenterWriteService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const WORKBENCH_CATEGORY = 'SYS_USER_WORKBENCH_DATA';

    public function __construct(private readonly PasswordService $passwordService = new PasswordService())
    {
    }

    public function updatePassword(array $input, array $payload = []): array
    {
        $password = $this->requiredInput($input, 'password');
        $newPassword = $this->requiredInput($input, 'newPassword');

        $password = $this->decodedPassword($password);
        $newPassword = $this->decodedPassword($newPassword);

        return Db::transaction(function () use ($password, $newPassword, $payload): array {
            $user = $this->activeCurrentUser($payload, 'ID, PASSWORD');
            if (!$this->passwordService->verify($password, (string)($user['PASSWORD'] ?? ''))) {
                throw new RuntimeException('password is incorrect', 401);
            }

            Db::name('sys_user')
                ->where('ID', (string)$user['ID'])
                ->update([
                    'PASSWORD' => Sm3Hasher::hash($newPassword),
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => (string)$user['ID'],
                ]);

            return ['id' => (string)$user['ID']];
        });
    }

    public function updateAvatar(?UploadedFile $file, array $payload = []): string
    {
        if (!$file) {
            throw new RuntimeException('missing file', 400);
        }

        $avatar = $this->uploadedImageDataUri($file);

        return Db::transaction(function () use ($avatar, $payload): string {
            $user = $this->activeCurrentUser($payload, 'ID');
            Db::name('sys_user')
                ->where('ID', (string)$user['ID'])
                ->update([
                    'AVATAR' => $avatar,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => (string)$user['ID'],
                ]);

            return $avatar;
        });
    }

    public function updateSignature(array $input, array $payload = []): array
    {
        $signature = $this->requiredInput($input, 'signature');

        return Db::transaction(function () use ($signature, $payload): array {
            $user = $this->activeCurrentUser($payload, 'ID');
            Db::name('sys_user')
                ->where('ID', (string)$user['ID'])
                ->update([
                    'SIGNATURE' => $this->normalizeDataUri($signature, 'image/png'),
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => (string)$user['ID'],
                ]);

            return ['id' => (string)$user['ID']];
        });
    }

    public function updateUserInfo(array $input, array $payload = [], bool $forceCurrentUser = false): array
    {
        $userId = $this->currentUserId($payload);
        $submittedId = $forceCurrentUser ? $userId : $this->requiredInput($input, 'id');
        if (!$forceCurrentUser && $submittedId !== $userId) {
            throw new RuntimeException('cannot update another user profile', 403);
        }

        if ($forceCurrentUser) {
            foreach (['account', 'name', 'orgId', 'positionId'] as $key) {
                if (array_key_exists($key, $input) && trim((string)$input[$key]) === '') {
                    throw new RuntimeException("missing {$key}", 400);
                }
            }
        } elseif (array_key_exists('name', $input) && trim((string)$input['name']) === '') {
            throw new RuntimeException('missing name', 400);
        }

        return Db::transaction(function () use ($submittedId, $input, $payload): array {
            $this->activeCurrentUser($payload, 'ID');
            $this->assertProfileInput($input, $submittedId);
            $row = [
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $submittedId,
            ];

            foreach ($this->profileFieldMap() as $inputKey => $column) {
                if (!array_key_exists($inputKey, $input)) {
                    continue;
                }

                $row[$column] = match ($column) {
                    'SORT_CODE' => $this->nullableInt($input[$inputKey]),
                    'BANK_NAME', 'BANK_ACCOUNT' => $this->stringValue($input[$inputKey]),
                    default => $this->nullableString($input[$inputKey]),
                };
            }

            if (count($row) <= 2) {
                throw new RuntimeException('missing profile fields', 400);
            }

            Db::name('sys_user')->where('ID', $submittedId)->update($row);

            return ['id' => $submittedId];
        });
    }

    public function updateWorkbench(array $input, array $payload = []): array
    {
        $workbenchData = $this->requiredInput($input, 'workbenchData');

        return Db::transaction(function () use ($workbenchData, $payload): array {
            $user = $this->activeCurrentUser($payload, 'ID');
            $this->upsertRelation((string)$user['ID'], self::WORKBENCH_CATEGORY, $workbenchData);

            return ['id' => (string)$user['ID']];
        });
    }

    public function editProcessConfig(array $input, array $payload = []): array
    {
        $config = $input['config'] ?? null;
        if (!is_array($config) || $config === []) {
            throw new RuntimeException('missing config', 400);
        }
        $config = $this->normalizeProcessConfigList($config);
        if ($config === []) {
            throw new RuntimeException('missing config', 400);
        }

        return Db::transaction(function () use ($config, $payload): array {
            $user = $this->activeCurrentUser($payload, 'ID, TENANT_ID');
            $userId = (string)$user['ID'];
            $now = date('Y-m-d H:i:s');
            $configJson = json_encode(['config' => $config], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($configJson === false) {
                throw new RuntimeException('invalid config', 400);
            }

            $row = Db::name('sys_user_process_config')
                ->where('CREATE_USER', $userId)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->order('UPDATE_TIME', 'desc')
                ->order('CREATE_TIME', 'desc')
                ->find();

            if (is_array($row) && !empty($row['ID'])) {
                Db::name('sys_user_process_config')
                    ->where('ID', (string)$row['ID'])
                    ->update([
                        'CONFIG_JSON' => $configJson,
                        'UPDATE_TIME' => $now,
                        'UPDATE_USER' => $userId,
                        'VERSION' => Db::raw('IFNULL(VERSION, 0) + 1'),
                    ]);

                return ['id' => (string)$row['ID']];
            }

            $id = $this->newId();
            Db::name('sys_user_process_config')->insert([
                'ID' => $id,
                'CONFIG_JSON' => $configJson,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => (string)($user['TENANT_ID'] ?? '1'),
                'VERSION' => 0,
            ]);

            return ['id' => $id];
        });
    }

    /**
     * @param array<int|string, mixed> $config
     * @return array<int, array<string, mixed>>
     */
    private function normalizeProcessConfigList(array $config): array
    {
        $result = [];
        foreach ($config as $item) {
            if (!is_array($item)) {
                continue;
            }

            $processName = trim((string)($item['processName'] ?? $item['key'] ?? ''));
            if ($processName === '') {
                continue;
            }

            $row = [
                'processName' => $processName,
                'approveUserIdList' => $this->userIdListValue($item['approveUserIdList'] ?? []),
                'copyUserIdList' => $this->userIdListValue($item['copyUserIdList'] ?? []),
            ];

            if (array_key_exists('treasurer', $item) || array_key_exists('treasurerId', $item)) {
                $row['treasurer'] = $this->singleUserIdValue($item['treasurer'] ?? $item['treasurerId'] ?? '');
            }
            if (array_key_exists('procure', $item) || array_key_exists('procureId', $item)) {
                $row['procure'] = $this->singleUserIdValue($item['procure'] ?? $item['procureId'] ?? '');
            }
            if (array_key_exists('open', $item)) {
                $row['open'] = (bool)$item['open'];
            }

            $result[] = $row;
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function userIdListValue(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [];
            }

            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $value);
        }

        if (!is_array($value)) {
            $id = $this->userIdFromSelection($value);

            return $id === '' ? [] : [$id];
        }

        $items = $this->isAssociativeArray($value) ? [$value] : $value;
        $ids = [];
        foreach ($items as $item) {
            $id = $this->userIdFromSelection($item);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function singleUserIdValue(mixed $value): string
    {
        return $this->userIdListValue($value)[0] ?? '';
    }

    private function userIdFromSelection(mixed $value): string
    {
        if (is_array($value)) {
            foreach (['userId', 'id', 'value', 'USER_ID', 'ID'] as $key) {
                if (!array_key_exists($key, $value)) {
                    continue;
                }

                $id = $this->userIdFromSelection($value[$key]);
                if ($id !== '') {
                    return $id;
                }
            }

            return '';
        }

        return trim((string)$value);
    }

    private function isAssociativeArray(array $value): bool
    {
        return $value !== [] && array_keys($value) !== range(0, count($value) - 1);
    }

    private function uploadedImageDataUri(UploadedFile $file): string
    {
        $path = $file->getRealPath() ?: (method_exists($file, 'getPathname') ? $file->getPathname() : '');
        if (!is_string($path) || $path === '' || !is_file($path)) {
            throw new RuntimeException('invalid uploaded file', 400);
        }

        $bytes = file_get_contents($path);
        if (!is_string($bytes) || $bytes === '') {
            throw new RuntimeException('invalid uploaded file', 400);
        }

        if (strlen($bytes) > 2 * 1024 * 1024) {
            throw new RuntimeException('avatar file is too large', 400);
        }

        $mime = $this->imageMime($bytes);

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    private function imageMime(string $bytes): string
    {
        $info = @getimagesizefromstring($bytes);
        $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            throw new RuntimeException('unsupported avatar image type', 400);
        }

        return $mime;
    }

    private function normalizeDataUri(string $value, string $defaultMime): string
    {
        $value = trim($value);
        if (str_starts_with($value, 'data:')) {
            return $value;
        }

        if (str_contains($value, ',')) {
            $parts = explode(',', $value);
            $value = trim((string)end($parts));
        }

        return 'data:' . $defaultMime . ';base64,' . $value;
    }

    private function activeCurrentUser(array $payload, string $field): array
    {
        $userId = $this->currentUserId($payload);
        $row = Db::name('sys_user')
            ->where('ID', $userId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->field($field)
            ->find();

        if (!is_array($row) || $row === []) {
            throw new RuntimeException('user not found', 404);
        }

        return $row;
    }

    private function upsertRelation(string $objectId, string $category, string $extJson): void
    {
        $relation = Db::name('sys_relation')
            ->where('OBJECT_ID', $objectId)
            ->where('CATEGORY', $category)
            ->find();

        if (is_array($relation) && !empty($relation['ID'])) {
            Db::name('sys_relation')
                ->where('ID', (string)$relation['ID'])
                ->update([
                    'TARGET_ID' => null,
                    'EXT_JSON' => $extJson,
                ]);

            return;
        }

        Db::name('sys_relation')->insert([
            'ID' => $this->newId(),
            'OBJECT_ID' => $objectId,
            'TARGET_ID' => null,
            'CATEGORY' => $category,
            'EXT_JSON' => $extJson,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function profileFieldMap(): array
    {
        return [
            'account' => 'ACCOUNT',
            'name' => 'NAME',
            'avatar' => 'AVATAR',
            'phone' => 'PHONE',
            'nickname' => 'NICKNAME',
            'gender' => 'GENDER',
            'age' => 'AGE',
            'birthday' => 'BIRTHDAY',
            'email' => 'EMAIL',
            'signature' => 'SIGNATURE',
            'bankName' => 'BANK_NAME',
            'bankAccount' => 'BANK_ACCOUNT',
            'nation' => 'NATION',
            'nativePlace' => 'NATIVE_PLACE',
            'homeAddress' => 'HOME_ADDRESS',
            'mailingAddress' => 'MAILING_ADDRESS',
            'idCardType' => 'ID_CARD_TYPE',
            'idCardNumber' => 'ID_CARD_NUMBER',
            'cultureLevel' => 'CULTURE_LEVEL',
            'politicalOutlook' => 'POLITICAL_OUTLOOK',
            'college' => 'COLLEGE',
            'education' => 'EDUCATION',
            'eduLength' => 'EDU_LENGTH',
            'degree' => 'DEGREE',
            'homeTel' => 'HOME_TEL',
            'officeTel' => 'OFFICE_TEL',
            'emergencyContact' => 'EMERGENCY_CONTACT',
            'emergencyPhone' => 'EMERGENCY_PHONE',
            'emergencyAddress' => 'EMERGENCY_ADDRESS',
            'empNo' => 'EMP_NO',
            'entryDate' => 'ENTRY_DATE',
            'orgId' => 'ORG_ID',
            'positionId' => 'POSITION_ID',
            'positionLevel' => 'POSITION_LEVEL',
            'directorId' => 'DIRECTOR_ID',
            'positionJson' => 'POSITION_JSON',
            'sortCode' => 'SORT_CODE',
            'extJson' => 'EXT_JSON',
            'workStartDate' => 'WORK_START_DATE',
            'healthStatus' => 'HEALTH_STATUS',
            'specialtySkills' => 'SPECIALTY_SKILLS',
            'onJobEducationJson' => 'ON_JOB_EDUCATION_JSON',
            'fullTimeEducationJson' => 'FULL_TIME_EDUCATION_JSON',
            'jobTitle' => 'JOB_TITLE',
            'socialAppointments' => 'SOCIAL_APPOINTMENTS',
            'departmentAttribute' => 'DEPARTMENT_ATTRIBUTE',
            'personalInformation' => 'PERSONAL_INFORMATION',
            'mainStudyAndWorkExperience' => 'MAIN_STUDY_AND_WORK_EXPERIENCE',
            'awardsAndAchievements' => 'AWARDS_AND_ACHIEVEMENTS',
            'familyMembersAndSocialRelationshipsJson' => 'FAMILY_MEMBERS_AND_SOCIAL_RELATIONSHIPS_JSON',
            'partyOrganizationOpinion' => 'PARTY_ORGANIZATION_OPINION',
            'entryMethod' => 'ENTRY_METHOD',
            'companyEmployeeId' => 'COMPANY_EMPLOYEE_ID',
        ];
    }

    private function assertProfileInput(array $input, string $userId): void
    {
        if (array_key_exists('account', $input)) {
            $account = trim((string)$input['account']);
            if ($account === '') {
                throw new RuntimeException('missing account', 400);
            }
            $this->assertUniqueUserColumn('ACCOUNT', $account, $userId, 'account already exists');
        }

        if (array_key_exists('phone', $input)) {
            $phone = trim((string)$input['phone']);
            if ($phone !== '' && preg_match('/^1[3-9]\d{9}$/', $phone) !== 1) {
                throw new RuntimeException('invalid phone', 400);
            }
            if ($phone !== '') {
                $this->assertUniqueUserColumn('PHONE', $phone, $userId, 'phone already exists');
            }
        }

        if (array_key_exists('email', $input)) {
            $email = trim((string)$input['email']);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('invalid email', 400);
            }
            if ($email !== '') {
                $this->assertUniqueUserColumn('EMAIL', $email, $userId, 'email already exists');
            }
        }

        foreach (['orgId' => ['sys_org', 'organization not found'], 'positionId' => ['sys_position', 'position not found']] as $key => [$table, $message]) {
            if (!array_key_exists($key, $input) || trim((string)$input[$key]) === '') {
                continue;
            }
            $query = Db::name($table)->where('ID', trim((string)$input[$key]));
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            if ((int)$query->count() === 0) {
                throw new RuntimeException($message, 400);
            }
        }

        if (array_key_exists('directorId', $input) && trim((string)$input['directorId']) !== '') {
            $query = Db::name('sys_user')->where('ID', trim((string)$input['directorId']));
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            if ((int)$query->count() === 0) {
                throw new RuntimeException('director user not found', 400);
            }
        }
    }

    private function assertUniqueUserColumn(string $column, string $value, string $userId, string $message): void
    {
        $query = Db::name('sys_user')
            ->where($column, $value)
            ->where('ID', '<>', $userId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ((int)$query->count() > 0) {
            throw new RuntimeException($message, 400);
        }
    }

    private function decodedPassword(string $password): string
    {
        $decoded = $this->passwordService->decodeTransportPassword($password);
        if ($decoded === null || $decoded === '') {
            throw new RuntimeException('invalid password transport value', 400);
        }

        return $decoded;
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

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function stringValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return trim((string)$value);
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = trim((string)$value);

        return $value === '' ? null : (int)$value;
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
    }

    private function currentUserId(array $payload): string
    {
        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
        if ($userId === '') {
            throw new RuntimeException('unauthenticated', 401);
        }

        return $userId;
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }
}
