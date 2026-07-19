<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;

/**
 * Read sale-project attachments without exposing the generic file-relation list.
 */
class SaleProjectFileRelationService
{
    private const CATEGORY = 'SALE_PROJECT';

    public function __construct(
        private readonly SaleProjectService $saleProjectService = new SaleProjectService(),
        private readonly FileRelationService $fileRelationService = new FileRelationService()
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(string $projectId, array $payload = []): array
    {
        $projectId = trim($projectId);
        if ($projectId === '') {
            throw new RuntimeException('missing projectId', 400);
        }

        $this->saleProjectService->assertReadable($projectId, $payload);

        return $this->fileRelationService->list([
            'objectId' => $projectId,
            'category' => self::CATEGORY,
        ], $payload);
    }
}
