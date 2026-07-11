<?php

declare(strict_types=1);

namespace app\service\biz;

use app\support\FileDownloadUrl;
use RuntimeException;
use think\facade\Db;

class AfterSalesService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const ENABLE = 'ENABLE';
    private const FILE_CATEGORY = 'AFTER_SALES_RECORD';

    private const RECORD_FIELDS = <<<SQL
r.ID AS ID,
r.CATEGORY_ID AS CATEGORY_ID,
r.PROJECT_ID AS PROJECT_ID,
r.TITLE AS TITLE,
r.CONTENT AS CONTENT,
r.HANDLE_TIME AS HANDLE_TIME,
r.DELETE_FLAG AS DELETE_FLAG,
r.CREATE_TIME AS CREATE_TIME,
r.CREATE_USER AS CREATE_USER,
r.UPDATE_TIME AS UPDATE_TIME,
r.UPDATE_USER AS UPDATE_USER,
r.TENANT_ID AS TENANT_ID,
c.NAME AS CATEGORY_NAME,
p.PROJECT_NAME AS PROJECT_NAME,
creator.NAME AS CREATE_USER_NAME,
creatorOrg.NAME AS CREATE_USER_ORG_NAME,
(SELECT COUNT(1) FROM biz_file_relation fr
  WHERE fr.OBJECT_ID = r.ID
    AND fr.CATEGORY = 'AFTER_SALES_RECORD'
    AND fr.TENANT_ID = r.TENANT_ID
    AND (fr.DELETE_FLAG IS NULL OR fr.DELETE_FLAG = 'NOT_DELETE')) AS ATTACHMENT_COUNT
SQL;

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = (int)$this->recordQuery($filters, $payload)->count();
        $rows = $this->recordQuery($filters, $payload)
            ->field(self::RECORD_FIELDS)
            ->order('r.HANDLE_TIME', 'desc')
            ->order('r.ID', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->recordRow($row, $payload), $rows),
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
        $row = $this->recordQuery(['id' => $id], $payload)->field(self::RECORD_FIELDS)->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('after-sales record not found', 404);
        }

        $result = $this->recordRow($row, $payload);
        $files = $this->recordFiles($id, (string)$result['tenantId']);
        $result['fileIdList'] = array_column($files, 'id');
        $result['fileList'] = $files;
        $result['canEdit'] = $this->canModify((string)($result['createUser'] ?? ''), $payload);

        return $result;
    }

    public function add(array $input, array $payload = []): array
    {
        $tenantId = $this->tenantId($payload);
        $userId = $this->requiredUserId($payload);
        $categoryId = $this->requiredText($input, 'categoryId', 20);
        $this->activeCategory($categoryId, $tenantId, true);
        $projectId = $this->optionalText($input, 'projectId', 20);
        $this->assertProject($projectId, $tenantId);
        $title = $this->requiredText($input, 'title', 200);
        $content = $this->sanitizeHtml($this->requiredText($input, 'content', 200000));
        if (trim(strip_tags($content)) === '') {
            throw new RuntimeException('after-sales content is required', 400);
        }
        $handleTime = $this->dateTime($input['handleTime'] ?? null) ?? date('Y-m-d H:i:s');
        $fileIds = $this->fileIds($input['fileIdList'] ?? []);
        $files = $this->activeFiles($fileIds, $tenantId);

        return Db::transaction(function () use ($tenantId, $userId, $categoryId, $projectId, $title, $content, $handleTime, $files): array {
            $id = $this->newId();
            $now = date('Y-m-d H:i:s');
            Db::name('biz_after_sales_record')->insert([
                'ID' => $id,
                'CATEGORY_ID' => $categoryId,
                'PROJECT_ID' => $projectId,
                'TITLE' => $title,
                'CONTENT' => $content,
                'HANDLE_TIME' => $handleTime,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
            ]);
            $this->syncFiles($id, $files, $tenantId, $userId, $now);

            return ['id' => $id];
        });
    }

    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredText($input, 'id', 20);
        $tenantId = $this->tenantId($payload);
        $record = $this->activeRecord($id, $tenantId, true);
        $this->assertCanModify((string)($record['CREATE_USER'] ?? ''), $payload);
        $categoryId = $this->requiredText($input, 'categoryId', 20);
        $this->activeCategory($categoryId, $tenantId, true);
        $projectId = $this->optionalText($input, 'projectId', 20);
        $this->assertProject($projectId, $tenantId);
        $title = $this->requiredText($input, 'title', 200);
        $content = $this->sanitizeHtml($this->requiredText($input, 'content', 200000));
        if (trim(strip_tags($content)) === '') {
            throw new RuntimeException('after-sales content is required', 400);
        }
        $handleTime = $this->dateTime($input['handleTime'] ?? null) ?? (string)$record['HANDLE_TIME'];
        $files = $this->activeFiles($this->fileIds($input['fileIdList'] ?? []), $tenantId);
        $userId = $this->requiredUserId($payload);

        return Db::transaction(function () use ($id, $categoryId, $projectId, $title, $content, $handleTime, $files, $tenantId, $userId): array {
            $now = date('Y-m-d H:i:s');
            Db::name('biz_after_sales_record')->where('ID', $id)->where('TENANT_ID', $tenantId)->update([
                'CATEGORY_ID' => $categoryId,
                'PROJECT_ID' => $projectId,
                'TITLE' => $title,
                'CONTENT' => $content,
                'HANDLE_TIME' => $handleTime,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId,
            ]);
            $this->syncFiles($id, $files, $tenantId, $userId, $now);

            return ['id' => $id];
        });
    }

    public function delete(array $input, array $payload = []): array
    {
        $ids = $this->idList($input['idList'] ?? $input['ids'] ?? $input['id'] ?? $input);
        if ($ids === []) {
            throw new RuntimeException('missing idList', 400);
        }
        $tenantId = $this->tenantId($payload);
        $userId = $this->requiredUserId($payload);

        return Db::transaction(function () use ($ids, $tenantId, $userId, $payload): array {
            $query = Db::name('biz_after_sales_record')->whereIn('ID', $ids)->where('TENANT_ID', $tenantId);
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            $rows = $query->lock(true)->select()->toArray();
            if (count($rows) !== count($ids)) {
                throw new RuntimeException('after-sales record not found', 404);
            }
            foreach ($rows as $row) {
                $this->assertCanModify((string)($row['CREATE_USER'] ?? ''), $payload);
            }
            $now = date('Y-m-d H:i:s');
            Db::name('biz_after_sales_record')->whereIn('ID', $ids)->where('TENANT_ID', $tenantId)->update([
                'DELETE_FLAG' => self::DELETED,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId,
            ]);
            $this->softDeleteFileRelations($ids, $tenantId);

            return ['ids' => $ids, 'count' => count($ids)];
        });
    }

    public function categoryList(array $payload = [], bool $includeDisabled = false): array
    {
        $query = Db::name('biz_after_sales_category')->where('TENANT_ID', $this->tenantId($payload));
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if (!$includeDisabled) {
            $query->where('STATUS', self::ENABLE);
        }
        $rows = $query->order('SORT_CODE', 'asc')->order('ID', 'asc')->select()->toArray();

        return array_map(fn (array $row): array => $this->categoryRow($row, $payload), $rows);
    }

    public function categoryAdd(array $input, array $payload = []): array
    {
        $tenantId = $this->tenantId($payload);
        $userId = $this->requiredUserId($payload);
        $name = $this->requiredText($input, 'name', 100);
        $this->assertUniqueCategoryName($name, $tenantId, null);
        $id = $this->newId();
        Db::name('biz_after_sales_category')->insert([
            'ID' => $id,
            'NAME' => $name,
            'SORT_CODE' => max(0, (int)($input['sortCode'] ?? 100)),
            'STATUS' => strtoupper(trim((string)($input['status'] ?? self::ENABLE))) === 'DISABLE' ? 'DISABLE' : self::ENABLE,
            'REMARK' => $this->optionalText($input, 'remark', 500),
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => date('Y-m-d H:i:s'),
            'CREATE_USER' => $userId,
            'UPDATE_TIME' => null,
            'UPDATE_USER' => null,
            'TENANT_ID' => $tenantId,
        ]);

        return ['id' => $id];
    }

    public function categoryEdit(array $input, array $payload = []): array
    {
        $id = $this->requiredText($input, 'id', 20);
        $tenantId = $this->tenantId($payload);
        $category = $this->activeCategory($id, $tenantId, false);
        $this->assertCanModify((string)($category['CREATE_USER'] ?? ''), $payload);
        $name = $this->requiredText($input, 'name', 100);
        $this->assertUniqueCategoryName($name, $tenantId, $id);
        Db::name('biz_after_sales_category')->where('ID', $id)->where('TENANT_ID', $tenantId)->update([
            'NAME' => $name,
            'SORT_CODE' => max(0, (int)($input['sortCode'] ?? 100)),
            'STATUS' => strtoupper(trim((string)($input['status'] ?? self::ENABLE))) === 'DISABLE' ? 'DISABLE' : self::ENABLE,
            'REMARK' => $this->optionalText($input, 'remark', 500),
            'UPDATE_TIME' => date('Y-m-d H:i:s'),
            'UPDATE_USER' => $this->requiredUserId($payload),
        ]);

        return ['id' => $id];
    }

    public function categoryDelete(array $input, array $payload = []): array
    {
        $id = $this->requiredText($input, 'id', 20);
        $tenantId = $this->tenantId($payload);
        $category = $this->activeCategory($id, $tenantId, false);
        $this->assertCanModify((string)($category['CREATE_USER'] ?? ''), $payload);
        $recordQuery = Db::name('biz_after_sales_record')->where('CATEGORY_ID', $id)->where('TENANT_ID', $tenantId);
        $this->whereNotDeleted($recordQuery, 'DELETE_FLAG');
        if ((int)$recordQuery->count() > 0) {
            throw new RuntimeException('category is used by after-sales records', 400);
        }
        Db::name('biz_after_sales_category')->where('ID', $id)->where('TENANT_ID', $tenantId)->update([
            'DELETE_FLAG' => self::DELETED,
            'UPDATE_TIME' => date('Y-m-d H:i:s'),
            'UPDATE_USER' => $this->requiredUserId($payload),
        ]);

        return ['id' => $id];
    }

    private function recordQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_after_sales_record')->alias('r')
            ->join('biz_after_sales_category c', 'c.ID = r.CATEGORY_ID', 'INNER')
            ->leftJoin('biz_sale_project p', 'p.ID = r.PROJECT_ID')
            ->leftJoin('sys_user creator', 'creator.ID = r.CREATE_USER')
            ->leftJoin('sys_org creatorOrg', 'creatorOrg.ID = creator.ORG_ID')
            ->where('r.TENANT_ID', $this->tenantId($payload));
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');
        $this->whereNotDeleted($query, 'c.DELETE_FLAG');
        if (!empty($filters['id'])) {
            $query->where('r.ID', (string)$filters['id']);
        }
        if (!empty($filters['categoryId'])) {
            $query->where('r.CATEGORY_ID', (string)$filters['categoryId']);
        }
        if (!empty($filters['projectId'])) {
            $query->where('r.PROJECT_ID', (string)$filters['projectId']);
        }
        if (!empty($filters['projectName'])) {
            $query->whereLike('p.PROJECT_NAME', '%' . trim((string)$filters['projectName']) . '%');
        }
        if (!empty($filters['createUserName'])) {
            $query->whereLike('creator.NAME', '%' . trim((string)$filters['createUserName']) . '%');
        }
        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('r.TITLE', $keyword)
                    ->whereOr('r.CONTENT', 'like', $keyword)
                    ->whereOr('p.PROJECT_NAME', 'like', $keyword)
                    ->whereOr('c.NAME', 'like', $keyword);
            });
        }
        $this->applyTimeRange($query, $filters['startHandleTime'] ?? '', $filters['endHandleTime'] ?? '');

        return $query;
    }

    private function recordRow(array $row, array $payload): array
    {
        $content = (string)($row['CONTENT'] ?? '');

        return [
            'id' => $row['ID'] ?? null,
            'categoryId' => $row['CATEGORY_ID'] ?? null,
            'categoryName' => $row['CATEGORY_NAME'] ?? null,
            'projectId' => $row['PROJECT_ID'] ?? null,
            'projectName' => $row['PROJECT_NAME'] ?? null,
            'title' => $row['TITLE'] ?? null,
            'content' => $content,
            'contentSummary' => $this->summary($content),
            'handleTime' => $row['HANDLE_TIME'] ?? null,
            'attachmentCount' => (int)($row['ATTACHMENT_COUNT'] ?? 0),
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'createUserName' => $row['CREATE_USER_NAME'] ?? null,
            'createUserOrgName' => $row['CREATE_USER_ORG_NAME'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
            'canEdit' => $this->canModify((string)($row['CREATE_USER'] ?? ''), $payload),
        ];
    }

    private function categoryRow(array $row, array $payload): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'name' => $row['NAME'] ?? null,
            'sortCode' => (int)($row['SORT_CODE'] ?? 0),
            'status' => $row['STATUS'] ?? self::ENABLE,
            'remark' => $row['REMARK'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'canEdit' => $this->canModify((string)($row['CREATE_USER'] ?? ''), $payload),
        ];
    }

    private function activeRecord(string $id, string $tenantId, bool $lock): array
    {
        $query = Db::name('biz_after_sales_record')->where('ID', $id)->where('TENANT_ID', $tenantId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $row = $lock ? $query->lock(true)->find() : $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('after-sales record not found', 404);
        }

        return $row;
    }

    private function activeCategory(string $id, string $tenantId, bool $enabledOnly): array
    {
        $query = Db::name('biz_after_sales_category')->where('ID', $id)->where('TENANT_ID', $tenantId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($enabledOnly) {
            $query->where('STATUS', self::ENABLE);
        }
        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('after-sales category not found', 404);
        }

        return $row;
    }

    private function assertProject(?string $projectId, string $tenantId): void
    {
        if ($projectId === null) {
            return;
        }
        $query = Db::name('biz_sale_project')->where('ID', $projectId)->where('TENANT_ID', $tenantId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ((int)$query->count() === 0) {
            throw new RuntimeException('sale project not found', 404);
        }
    }

    private function assertUniqueCategoryName(string $name, string $tenantId, ?string $excludeId): void
    {
        $query = Db::name('biz_after_sales_category')->where('TENANT_ID', $tenantId)->where('NAME', $name);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($excludeId !== null) {
            $query->where('ID', '<>', $excludeId);
        }
        if ((int)$query->count() > 0) {
            throw new RuntimeException('after-sales category name already exists', 400);
        }
    }

    private function activeFiles(array $ids, string $tenantId): array
    {
        if ($ids === []) {
            return [];
        }
        $query = Db::name('dev_file')->whereIn('ID', $ids)->where('TENANT_ID', $tenantId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $rows = $query->field('ID,NAME')->select()->toArray();
        if (count($rows) !== count($ids)) {
            throw new RuntimeException('attachment file not found', 404);
        }

        return $rows;
    }

    private function syncFiles(string $recordId, array $files, string $tenantId, string $userId, string $now): void
    {
        $this->softDeleteFileRelations([$recordId], $tenantId);
        foreach ($files as $file) {
            Db::name('biz_file_relation')->insert([
                'ID' => $this->newId(),
                'OBJECT_ID' => $recordId,
                'TARGET_ID' => (string)$file['ID'],
                'CATEGORY' => self::FILE_CATEGORY,
                'FILE_NAME' => $file['NAME'] ?? null,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId,
                'EXT_JSON' => null,
                'TENANT_ID' => $tenantId,
            ]);
        }
    }

    private function softDeleteFileRelations(array $recordIds, string $tenantId): void
    {
        $query = Db::name('biz_file_relation')->whereIn('OBJECT_ID', $recordIds)
            ->where('CATEGORY', self::FILE_CATEGORY)->where('TENANT_ID', $tenantId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $query->update(['DELETE_FLAG' => self::DELETED]);
    }

    private function recordFiles(string $recordId, string $tenantId): array
    {
        $query = Db::name('biz_file_relation')->alias('r')
            ->join('dev_file f', 'f.ID = r.TARGET_ID', 'INNER')
            ->where('r.OBJECT_ID', $recordId)->where('r.CATEGORY', self::FILE_CATEGORY)
            ->where('r.TENANT_ID', $tenantId)
            ->field('f.ID,f.NAME,f.SUFFIX,f.SIZE_KB,f.ENGINE,f.DOWNLOAD_PATH');
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');
        $this->whereNotDeleted($query, 'f.DELETE_FLAG');

        return array_map(static fn (array $row): array => [
            'id' => $row['ID'] ?? null,
            'name' => $row['NAME'] ?? null,
            'suffix' => $row['SUFFIX'] ?? null,
            'sizeKb' => (int)($row['SIZE_KB'] ?? 0),
            'downloadPath' => FileDownloadUrl::normalize($row['ID'] ?? null, $row['ENGINE'] ?? null, $row['DOWNLOAD_PATH'] ?? null),
        ], $query->select()->toArray());
    }

    private function sanitizeHtml(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = strip_tags($html, '<p><br><div><span><strong><b><em><i><u><s><ul><ol><li><blockquote><pre><code><h1><h2><h3><h4><h5><h6><table><thead><tbody><tr><th><td><a><img><hr>');

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="after-sales-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        if (!$loaded) {
            return '';
        }

        $root = $document->getElementById('after-sales-root');
        if (!$root instanceof \DOMElement) {
            return '';
        }

        // Keep TinyMCE formatting while removing executable attributes and unsafe URLs.
        foreach ($root->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $allowedAttributes = ['class', 'style', 'title'];
            if ($element->tagName === 'a') {
                $allowedAttributes = array_merge($allowedAttributes, ['href', 'target', 'rel']);
            } elseif ($element->tagName === 'img') {
                $allowedAttributes = array_merge($allowedAttributes, ['src', 'alt', 'width', 'height']);
            } elseif (in_array($element->tagName, ['td', 'th'], true)) {
                $allowedAttributes = array_merge($allowedAttributes, ['colspan', 'rowspan']);
            }

            $attributeNames = [];
            foreach ($element->attributes as $attribute) {
                $attributeNames[] = strtolower($attribute->name);
            }
            foreach ($attributeNames as $attributeName) {
                if (!in_array($attributeName, $allowedAttributes, true)) {
                    $element->removeAttribute($attributeName);
                    continue;
                }
                $value = trim(html_entity_decode($element->getAttribute($attributeName), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if (in_array($attributeName, ['href', 'src'], true) && !$this->isSafeRichTextUrl($value, $attributeName === 'src')) {
                    $element->removeAttribute($attributeName);
                } elseif ($attributeName === 'style' && preg_match('/expression\s*\(|javascript\s*:|data\s*:\s*text\/html|behavior\s*:|-moz-binding/i', $value)) {
                    $element->removeAttribute($attributeName);
                }
            }
            if ($element->tagName === 'a' && strtolower($element->getAttribute('target')) === '_blank') {
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }

    private function isSafeRichTextUrl(string $url, bool $allowImageData): bool
    {
        if ($url === '') {
            return false;
        }
        $normalized = preg_replace('/[\x00-\x20\x7f]+/u', '', $url) ?? '';
        if ($allowImageData && preg_match('#^data:image/(png|jpe?g|gif|webp|bmp);base64,#i', $normalized)) {
            return true;
        }
        if (!preg_match('#^([a-z][a-z0-9+.-]*):#i', $normalized, $matches)) {
            return true;
        }

        return in_array(strtolower($matches[1]), ['http', 'https', 'mailto', 'tel'], true);
    }

    private function summary(string $html): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        return function_exists('mb_substr') ? mb_substr($text, 0, 180) : substr($text, 0, 180);
    }

    private function fileIds(mixed $value): array
    {
        return array_slice($this->idList($value), 0, 20);
    }

    private function idList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static function (mixed $item): string {
            return trim((string)(is_array($item) ? ($item['id'] ?? $item['ID'] ?? '') : $item));
        }, $value))));
    }

    private function requiredText(array $input, string $key, int $maxLength): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length > $maxLength) {
            throw new RuntimeException("invalid {$key}", 400);
        }

        return $value;
    }

    private function optionalText(array $input, string $key, int $maxLength): ?string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            return null;
        }
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length > $maxLength) {
            throw new RuntimeException("invalid {$key}", 400);
        }

        return $value;
    }

    private function dateTime(mixed $value): ?string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException('invalid handleTime', 400);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function applyTimeRange($query, mixed $start, mixed $end): void
    {
        $start = trim((string)$start);
        $end = trim((string)$end);
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime('r.HANDLE_TIME', $start, $end);
        } elseif ($start !== '') {
            $query->whereTime('r.HANDLE_TIME', '>=', $start);
        } elseif ($end !== '') {
            $query->whereTime('r.HANDLE_TIME', '<=', $end);
        }
    }

    private function assertCanModify(string $ownerId, array $payload): void
    {
        if (!$this->canModify($ownerId, $payload)) {
            throw new RuntimeException('no permission to modify this after-sales record', 403);
        }
    }

    private function canModify(string $ownerId, array $payload): bool
    {
        if ($this->canSeeAll($payload)) {
            return true;
        }

        return $ownerId !== '' && $ownerId === $this->requiredUserId($payload);
    }

    private function canSeeAll(array $payload): bool
    {
        $account = strtolower((string)($payload['account'] ?? ''));
        if (in_array($account, ['bizadmin', 'superadmin'], true)) {
            return true;
        }
        $roles = $payload['role_codes'] ?? $payload['roleCodeList'] ?? [];

        return is_array($roles) && array_filter($roles, static fn (mixed $role): bool => in_array(strtolower((string)$role), ['superadmin', 'tenantadmin', 'bizadmin'], true)) !== [];
    }

    private function tenantId(array $payload): string
    {
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));

        return $tenantId !== '' ? $tenantId : '1';
    }

    private function requiredUserId(array $payload): string
    {
        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
        if ($userId === '') {
            throw new RuntimeException('missing current user', 401);
        }

        return $userId;
    }

    private function pagination(array $filters): array
    {
        return [
            max(1, (int)($filters['current'] ?? $filters['page'] ?? 1)),
            max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? 20))),
        ];
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }
}
